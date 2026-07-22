<?php
/**
 * Safe WordPress revision draft service.
 *
 * @package Citeoryx\Application\Optimize
 */

namespace Citeoryx\Application\Optimize;

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
		);
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
