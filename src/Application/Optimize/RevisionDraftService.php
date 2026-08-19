<?php
/**
 * Safe WordPress revision draft service.
 *
 * @package Citeoryx\Application\Optimize
 */

namespace Citeoryx\Application\Optimize;

use Citeoryx\Domain\Content\ContentItem;
use Citeoryx\Domain\Content\ContentRepository;
use WP_Error;
use WP_Post;

/**
 * Creates reviewable revisions without updating the parent post.
 */
class RevisionDraftService {

	private const PROPOSAL_HASH_META = '_citeoryx_proposal_hash';
	private const BASE_HASH_META     = '_citeoryx_base_content_hash';
	private const SUMMARY_META       = '_citeoryx_change_summary';
	private const PUBLISHED_AT_META  = '_citeoryx_proposal_published_at';
	private const VERIFIED_AT_META   = '_citeoryx_proposal_verified_at';

	private ContentRepository $content_repo;

	public function __construct( ContentRepository $content_repo ) {
		$this->content_repo = $content_repo;
	}

	/**
	 * Get the editable WordPress fields and concurrency token.
	 *
	 * @param int $content_id Citeoryx content ID.
	 * @return array<string, mixed>
	 */
	public function get_snapshot( int $content_id ): array {
		$post = $this->resolve_post( $content_id );
		if ( is_wp_error( $post ) ) {
			return array(
				'available' => false,
				'message'   => $post->get_error_message(),
			);
		}

		$revisions_enabled = $this->revisions_enabled( $post );
		return array(
			'available'         => true,
			'post_id'           => $post->ID,
			'title'             => $post->post_title,
			'content'           => $post->post_content,
			'excerpt'           => $post->post_excerpt,
			'base_content_hash' => $this->fields_hash( $post->post_title, $post->post_content, $post->post_excerpt ),
			'revisions_enabled' => $revisions_enabled,
			'message'           => $revisions_enabled ? '' : __( '该内容类型或站点配置已禁用 WordPress Revision。', 'citeoryx' ),
			'edit_url'          => get_edit_post_link( $post->ID, 'raw' ) ?: '',
			'workflow'          => $this->get_workflow_status( $content_id ),
		);
	}

	/**
	 * Get the latest Citeoryx proposal lifecycle for a content item.
	 *
	 * @param int $content_id Citeoryx content ID.
	 * @return array<string, mixed>
	 */
	public function get_workflow_status( int $content_id ): array {
		$item = $this->content_repo->find( $content_id );
		if ( ! $item || 'post' !== $item->object_type || ! $item->object_id ) {
			return $this->empty_workflow();
		}

		$post = get_post( $item->object_id );
		if ( ! $post || 'revision' === $post->post_type ) {
			return $this->empty_workflow();
		}

		$revision = $this->find_latest_proposal( $post->ID );
		if ( ! $revision ) {
			return $this->empty_workflow( $post->post_status, $item->last_scanned_at );
		}

		$proposal_hash = (string) get_metadata( 'post', $revision->ID, self::PROPOSAL_HASH_META, true );
		$base_hash     = (string) get_metadata( 'post', $revision->ID, self::BASE_HASH_META, true );
		$published_at  = sanitize_text_field( (string) get_metadata( 'post', $revision->ID, self::PUBLISHED_AT_META, true ) );
		$verified_at   = sanitize_text_field( (string) get_metadata( 'post', $revision->ID, self::VERIFIED_AT_META, true ) );
		$current_hash  = $this->fields_hash( $post->post_title, $post->post_content, $post->post_excerpt );

		$matches_base     = 64 === strlen( $base_hash ) && hash_equals( $base_hash, $current_hash );
		$matches_proposal = 64 === strlen( $proposal_hash ) && hash_equals( $proposal_hash, $current_hash );
		$scan_current     = $matches_proposal && $this->scan_matches_post( $item, $post );

		if ( $matches_base ) {
			$state = 'awaiting_review';
		} elseif ( $matches_proposal && 'publish' !== $post->post_status ) {
			$state = 'applied_unpublished';
		} elseif ( $matches_proposal && ! $scan_current ) {
			$state = 'published_pending_scan';
		} elseif ( $matches_proposal ) {
			$state = 'verified';
		} else {
			$state = 'superseded';
		}

		return array(
			'state'           => $state,
			'revision'        => $this->format_revision( $revision, false ),
			'summary'         => sanitize_text_field( (string) get_metadata( 'post', $revision->ID, self::SUMMARY_META, true ) ),
			'post_status'     => $post->post_status,
			'published'       => $matches_proposal && 'publish' === $post->post_status,
			'verified'        => 'verified' === $state,
			'can_verify'      => 'published_pending_scan' === $state,
			'last_scanned_at' => $item->last_scanned_at ?: null,
			'published_at'    => $published_at ?: null,
			'verified_at'     => $verified_at ?: null,
		);
	}

	/**
	 * Persist the immutable observation points for a successfully verified proposal.
	 *
	 * This is deliberately called by the explicit post-publish verification scan, so
	 * performance measurement stays tied to an editor-confirmed deployed revision.
	 *
	 * @param int $content_id Citeoryx content ID.
	 * @return void
	 */
	public function record_verified_scan( int $content_id ): void {
		$item = $this->content_repo->find( $content_id );
		if ( ! $item || 'post' !== $item->object_type || ! $item->object_id ) {
			return;
		}

		$post = get_post( $item->object_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return;
		}

		$revision = $this->find_latest_proposal( $post->ID );
		if ( ! $revision ) {
			return;
		}

		$proposal_hash = (string) get_metadata( 'post', $revision->ID, self::PROPOSAL_HASH_META, true );
		$current_hash  = $this->fields_hash( $post->post_title, $post->post_content, $post->post_excerpt );
		if (
			64 !== strlen( $proposal_hash ) ||
			! hash_equals( $proposal_hash, $current_hash ) ||
			! $this->scan_matches_post( $item, $post )
		) {
			return;
		}

		if ( ! get_metadata( 'post', $revision->ID, self::PUBLISHED_AT_META, true ) ) {
			add_metadata( 'post', $revision->ID, self::PUBLISHED_AT_META, $post->post_modified, true );
		}
		if ( ! get_metadata( 'post', $revision->ID, self::VERIFIED_AT_META, true ) ) {
			add_metadata( 'post', $revision->ID, self::VERIFIED_AT_META, $item->last_scanned_at, true );
		}
	}

	/**
	 * Create an idempotent proposal revision.
	 *
	 * @param int                  $content_id Citeoryx content ID.
	 * @param array<string, mixed> $proposal Sanitized proposal fields.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create( int $content_id, array $proposal ) {
		$post = $this->resolve_post( $content_id );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( ! $this->revisions_enabled( $post ) ) {
			return $this->error(
				'citeoryx_revisions_disabled',
				__( '该内容类型或站点配置已禁用 WordPress Revision。', 'citeoryx' ),
				409
			);
		}

		$current_hash = $this->fields_hash( $post->post_title, $post->post_content, $post->post_excerpt );
		if ( ! hash_equals( $current_hash, (string) ( $proposal['base_content_hash'] ?? '' ) ) ) {
			return $this->error(
				'citeoryx_revision_conflict',
				__( '原内容已被其他用户更新，请重新生成建议后再创建修订。', 'citeoryx' ),
				409
			);
		}

		$title   = (string) ( $proposal['title'] ?? '' );
		$content = (string) ( $proposal['content'] ?? '' );
		$excerpt = (string) ( $proposal['excerpt'] ?? '' );
		if ( $title === $post->post_title && $content === $post->post_content && $excerpt === $post->post_excerpt ) {
			return $this->error(
				'citeoryx_revision_unchanged',
				__( '拟议内容与当前内容相同，无需创建修订。', 'citeoryx' ),
				400
			);
		}

		$proposal_hash = $this->fields_hash( $title, $content, $excerpt );
		$existing      = $this->find_existing( $post->ID, $proposal_hash, $current_hash );
		if ( $existing ) {
			return $this->format_revision( $existing, false );
		}

		$revision_id = $this->insert_revision( $post, $title, $content, $excerpt );
		if ( is_wp_error( $revision_id ) ) {
			return $this->error( 'citeoryx_revision_insert_failed', __( 'WordPress 未能创建修订。', 'citeoryx' ), 500 );
		}
		if ( ! $revision_id ) {
			return $this->error( 'citeoryx_revision_insert_failed', __( 'WordPress 未能创建修订。', 'citeoryx' ), 500 );
		}

		$metadata = array(
			self::PROPOSAL_HASH_META => $proposal_hash,
			self::BASE_HASH_META     => $current_hash,
		);
		if ( '' !== (string) ( $proposal['summary'] ?? '' ) ) {
			$metadata[ self::SUMMARY_META ] = (string) $proposal['summary'];
		}
		foreach ( $metadata as $key => $value ) {
			if ( ! add_metadata( 'post', $revision_id, $key, $value, true ) ) {
				wp_delete_post_revision( $revision_id );
				return $this->error(
					'citeoryx_revision_metadata_failed',
					__( '修订元数据保存失败，未保留不完整修订。', 'citeoryx' ),
					500
				);
			}
		}

		$revision = wp_get_post_revision( $revision_id );
		if ( ! $revision ) {
			return $this->error( 'citeoryx_revision_missing', __( '修订创建后无法读取。', 'citeoryx' ), 500 );
		}

		return $this->format_revision( $revision, true );
	}

	/**
	 * Resolve a content record to its WordPress post.
	 *
	 * @param int $content_id Citeoryx content ID.
	 * @return WP_Post|WP_Error
	 */
	private function resolve_post( int $content_id ) {
		$item = $this->content_repo->find( $content_id );
		if ( ! $item ) {
			return $this->error( 'citeoryx_content_missing', __( 'Content item not found.', 'citeoryx' ), 404 );
		}
		if ( 'post' !== $item->object_type || ! $item->object_id ) {
			return $this->error( 'citeoryx_revision_unsupported', __( '该内容不是可修订的 WordPress 内容。', 'citeoryx' ), 400 );
		}

		$post = get_post( $item->object_id );
		if ( ! $post || 'revision' === $post->post_type ) {
			return $this->error( 'citeoryx_post_missing', __( '对应的 WordPress 内容不存在。', 'citeoryx' ), 404 );
		}
		return $post;
	}

	private function revisions_enabled( WP_Post $post ): bool {
		return post_type_supports( $post->post_type, 'revisions' ) && wp_revisions_enabled( $post );
	}

	/**
	 * Find a recently created identical Citeoryx proposal.
	 */
	private function find_existing( int $post_id, string $proposal_hash, string $base_hash ): ?WP_Post {
		$revisions = get_posts(
			array(
				'post_type'      => 'revision',
				'post_status'    => 'inherit',
				'post_parent'    => $post_id,
				'posts_per_page' => 1,
				'order'          => 'DESC',
				'orderby'        => 'ID',
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'   => self::PROPOSAL_HASH_META,
						'value' => $proposal_hash,
					),
					array(
						'key'   => self::BASE_HASH_META,
						'value' => $base_hash,
					),
				),
			)
		);
		return $revisions ? reset( $revisions ) : null;
	}

	/**
	 * Find the latest revision created through the Citeoryx proposal flow.
	 *
	 * @param int $post_id Parent post ID.
	 * @return WP_Post|null
	 */
	private function find_latest_proposal( int $post_id ): ?WP_Post {
		$revisions = get_posts(
			array(
				'post_type'      => 'revision',
				'post_status'    => 'inherit',
				'post_parent'    => $post_id,
				'posts_per_page' => 1,
				'order'          => 'DESC',
				'orderby'        => 'ID',
				'meta_query'     => array(
					array(
						'key'     => self::PROPOSAL_HASH_META,
						'compare' => 'EXISTS',
					),
				),
			)
		);

		return $revisions ? reset( $revisions ) : null;
	}

	/**
	 * Confirm the indexed content represents the current published post.
	 *
	 * @param ContentItem $item Indexed content item.
	 * @param WP_Post $post Parent post.
	 * @return bool
	 */
	private function scan_matches_post( ContentItem $item, WP_Post $post ): bool {
		$expected_hash = hash( 'sha256', $post->post_content );
		if (
			! $item->content_hash ||
			! hash_equals( $expected_hash, (string) $item->content_hash ) ||
			! $item->last_scanned_at
		) {
			return false;
		}

		return ! $post->post_modified || $item->last_scanned_at >= $post->post_modified;
	}

	/**
	 * Build the stable no-proposal workflow response.
	 *
	 * @param string      $post_status     Parent post status.
	 * @param string|null $last_scanned_at Last indexed scan time.
	 * @return array<string, mixed>
	 */
	private function empty_workflow( string $post_status = '', ?string $last_scanned_at = null ): array {
		return array(
			'state'           => 'idle',
			'revision'        => null,
			'summary'         => '',
			'post_status'     => $post_status,
			'published'       => false,
			'verified'        => false,
			'can_verify'      => false,
			'last_scanned_at' => $last_scanned_at,
			'published_at'    => null,
			'verified_at'     => null,
		);
	}

	private function fields_hash( string $title, string $content, string $excerpt ): string {
		return hash( 'sha256', maybe_serialize( array( $title, $content, $excerpt ) ) );
	}

	/**
	 * Insert a revision using public WordPress post APIs only.
	 *
	 * @return int|WP_Error
	 */
	private function insert_revision( WP_Post $post, string $title, string $content, string $excerpt ) {
		$now = current_time( 'mysql' );
		return wp_insert_post(
			wp_slash(
				array(
					'post_author'       => get_current_user_id(),
					'post_title'        => $title,
					'post_content'      => $content,
					'post_excerpt'      => $excerpt,
					'post_status'       => 'inherit',
					'post_name'         => $post->ID . '-revision-v1',
					'post_parent'       => $post->ID,
					'post_type'         => 'revision',
					'post_date'         => $now,
					'post_date_gmt'     => get_gmt_from_date( $now ),
					'post_modified'     => $now,
					'post_modified_gmt' => get_gmt_from_date( $now ),
				)
			),
			true
		);
	}

	/**
	 * Format a stable API result.
	 *
	 * @return array<string, mixed>
	 */
	private function format_revision( WP_Post $revision, bool $created ): array {
		return array(
			'id'          => $revision->ID,
			'parent_id'   => $revision->post_parent,
			'created_at'  => mysql_to_rfc3339( $revision->post_date ),
			'author_id'   => (int) $revision->post_author,
			'compare_url' => admin_url( 'revision.php?revision=' . $revision->ID ),
			'edit_url'    => get_edit_post_link( $revision->post_parent, 'raw' ) ?: '',
			'created'     => $created,
		);
	}

	private function error( string $code, string $message, int $status ): WP_Error {
		return new WP_Error( $code, $message, array( 'status' => $status ) );
	}
}
