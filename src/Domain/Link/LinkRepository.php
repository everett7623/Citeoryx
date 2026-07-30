<?php
/**
 * Link repository.
 *
 * @package Citeoryx\Domain\Link
 */

namespace Citeoryx\Domain\Link;

/**
 * Repository for links.
 */
class LinkRepository {

	/**
	 * Table name.
	 *
	 * @return string
	 */
	private function table(): string {
		global $wpdb;
		return $wpdb->prefix . CITEORYX_TABLE_LINKS;
	}

	/**
	 * Save link.
	 *
	 * @param Link $link Link.
	 * @return int
	 */
	public function save( Link $link ): int {
		global $wpdb;

		$now = current_time( 'mysql' );

		$data = array(
			'source_content_id' => $link->source_content_id,
			'target_content_id' => $link->target_content_id,
			'target_url'        => $link->target_url,
			'target_url_hash'   => $link->target_url_hash,
			'anchor_text'       => $link->anchor_text,
			'link_context'      => $link->link_context,
			'rel_flags'         => $link->rel_flags,
			'http_status'       => $link->http_status,
			'is_internal'       => $link->is_internal ? 1 : 0,
			'last_seen_at'      => $now,
		);

		$existing = $this->find_existing( $link->source_content_id, $link->target_url_hash );
		if ( $existing ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$this->table(),
				$data,
				array( 'id' => $existing->id ),
				array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s' ),
				array( '%d' )
			);
			return $existing->id;
		}

		$data['first_seen_at'] = $now;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			$data,
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Find existing link.
	 *
	 * @param int    $source_content_id Source content ID.
	 * @param string $target_url_hash Target URL hash.
	 * @return Link|null
	 */
	public function find_existing( int $source_content_id, string $target_url_hash ): ?Link {
		global $wpdb;

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT * FROM %i WHERE source_content_id = %d AND target_url_hash = %s',
				$this->table(),
				$source_content_id,
				$target_url_hash
			)
		);

		if ( ! $row ) {
			return null;
		}

		return Link::from_row( $row );
	}

	/**
	 * Count inbound links for content.
	 *
	 * @param int $content_id Content ID.
	 * @return int
	 */
	public function count_inbound( int $content_id ): int {
		global $wpdb;

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE target_content_id = %d AND is_internal = 1',
				$this->table(),
				$content_id
			)
		);
	}

	/**
	 * Count outbound links from content.
	 *
	 * @param int $content_id Content ID.
	 * @return int
	 */
	public function count_outbound( int $content_id ): int {
		global $wpdb;

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE source_content_id = %d',
				$this->table(),
				$content_id
			)
		);
	}

	/**
	 * Count broken outbound links for content.
	 *
	 * @param int $content_id Content ID.
	 * @return int
	 */
	public function count_broken_outbound( int $content_id ): int {
		global $wpdb;

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE source_content_id = %d AND is_internal = 0 AND (http_status = 0 OR http_status >= 400)',
				$this->table(),
				$content_id
			)
		);
	}

	/**
	 * Get URL hashes already linked from a content item.
	 *
	 * @param int $content_id Source content ID.
	 * @return array<string>
	 */
	public function find_internal_target_hashes( int $content_id ): array {
		global $wpdb;

		$hashes = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT target_url_hash FROM %i WHERE source_content_id = %d AND is_internal = 1',
				$this->table(),
				$content_id
			)
		);

		return array_values( array_filter( array_map( 'strval', $hashes ?: array() ) ) );
	}

	/**
	 * Get orphan content IDs.
	 *
	 * @return array<int>
	 */
	public function find_orphan_ids(): array {
		global $wpdb;

		$table_content = $wpdb->prefix . CITEORYX_TABLE_CONTENT_ITEMS;
		$table_links   = $this->table();

		$results = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT ci.id FROM %i ci
				LEFT JOIN %i l ON ci.id = l.target_content_id AND l.is_internal = 1
				WHERE l.id IS NULL AND ci.object_type = 'post'",
				$table_content,
				$table_links
			)
		);

		return array_map( 'intval', $results );
	}

	/**
	 * Delete all links from source.
	 *
	 * @param int $source_content_id Source content ID.
	 * @return void
	 */
	public function delete_by_source( int $source_content_id ): void {
		global $wpdb;

		$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			array( 'source_content_id' => $source_content_id ),
			array( '%d' )
		);
	}

	/**
	 * Get links for HTTP status check.
	 *
	 * @param int $limit Number of links.
	 * @param int $after_id Exclusive link ID cursor.
	 * @return array<Link>
	 */
	public function get_for_status_check( int $limit = 50, int $after_id = 0 ): array {
		global $wpdb;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT * FROM %i WHERE is_internal = 0 AND id > %d ORDER BY id ASC LIMIT %d',
				$this->table(),
				max( 0, $after_id ),
				max( 1, min( 100, $limit ) )
			)
		);

		if ( ! $rows ) {
			return array();
		}

		return array_map( static fn ( $row ) => Link::from_row( $row ), $rows );
	}

	/**
	 * Update HTTP status of a link.
	 *
	 * @param int    $id Link ID.
	 * @param int    $status HTTP status code.
	 * @param string $error Error message.
	 * @return void
	 */
	public function update_status( int $id, int $status, string $error = '' ): void {
		global $wpdb;

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			array(
				'http_status'     => $status,
				'last_checked_at' => current_time( 'mysql' ),
				'last_error'      => $error,
			),
			array( 'id' => $id ),
			array( '%d', '%s', '%s' ),
			array( '%d' )
		);
	}
}
