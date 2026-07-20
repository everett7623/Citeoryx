<?php
/**
 * Content repository.
 *
 * @package Citeoryx\Domain\Content
 */

namespace Citeoryx\Domain\Content;

/**
 * Repository for content items.
 */
class ContentRepository {

	/**
	 * Get table name.
	 *
	 * @return string
	 */
	private function table(): string {
		global $wpdb;
		return $wpdb->prefix . CITEORYX_TABLE_CONTENT_ITEMS;
	}

	/**
	 * Find by ID.
	 *
	 * @param int $id Content item ID.
	 * @return ContentItem|null
	 */
	public function find( int $id ): ?ContentItem {
		global $wpdb;

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM {$this->table()} WHERE id = %d",
				$id
			)
		);

		if ( ! $row ) {
			return null;
		}

		return ContentItem::from_row( $row );
	}

	/**
	 * Find by object type and ID.
	 *
	 * @param string $object_type Object type.
	 * @param int    $object_id Object ID.
	 * @return ContentItem|null
	 */
	public function find_by_object( string $object_type, int $object_id ): ?ContentItem {
		global $wpdb;

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM {$this->table()} WHERE object_type = %s AND object_id = %d",
				$object_type,
				$object_id
			)
		);

		if ( ! $row ) {
			return null;
		}

		return ContentItem::from_row( $row );
	}

	/**
	 * Find by URL hash.
	 *
	 * @param string $url_hash URL hash.
	 * @return ContentItem|null
	 */
	public function find_by_url_hash( string $url_hash ): ?ContentItem {
		global $wpdb;

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM {$this->table()} WHERE url_hash = %s",
				$url_hash
			)
		);

		if ( ! $row ) {
			return null;
		}

		return ContentItem::from_row( $row );
	}

	/**
	 * Save content item.
	 *
	 * @param ContentItem $item Content item.
	 * @return int Insert or updated ID.
	 */
	public function save( ContentItem $item ): int {
		global $wpdb;

		$now = current_time( 'mysql' );

		$data = array(
			'object_id'            => $item->object_id,
			'object_type'          => $item->object_type,
			'post_type'            => $item->post_type,
			'canonical_url'        => $item->canonical_url,
			'url_hash'             => $item->url_hash,
			'language_code'        => $item->language_code,
			'status'               => $item->status,
			'health_score'         => $item->health_score,
			'health_confidence'    => $item->health_confidence,
			'ai_readiness_score'   => $item->ai_readiness_score,
			'content_hash'         => $item->content_hash,
			'published_at'         => $item->published_at,
			'modified_at'          => $item->modified_at,
			'last_scanned_at'      => $item->last_scanned_at,
			'last_reviewed_at'     => $item->last_reviewed_at,
			'assigned_user_id'   => $item->assigned_user_id,
			'metadata_json'        => $item->metadata ? wp_json_encode( $item->metadata ) : null,
			'updated_at'           => $now,
		);

		if ( $item->id ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$this->table(),
				$data,
				array( 'id' => $item->id ),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' ),
				array( '%d' )
			);
			return $item->id;
		}

		$data['created_at'] = $now;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			$data,
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%f', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * List content items with pagination.
	 *
	 * @param array<string, mixed> $filters Filters.
	 * @param int                  $page Page number.
	 * @param int                  $per_page Items per page.
	 * @return array{items: array<ContentItem>, total: int}
	 */
	public function list( array $filters = array(), int $page = 1, int $per_page = 20 ): array {
		global $wpdb;

		$where = array( '1=1' );
		$args  = array();

		if ( ! empty( $filters['status'] ) ) {
			$where[] = 'status = %s';
			$args[]  = $filters['status'];
		}
		if ( ! empty( $filters['post_type'] ) ) {
			$where[] = 'post_type = %s';
			$args[]  = $filters['post_type'];
		}
		if ( isset( $filters['object_type'] ) ) {
			$where[] = 'object_type = %s';
			$args[]  = $filters['object_type'];
		}
		if ( ! empty( $filters['search'] ) ) {
			$where[] = 'canonical_url LIKE %s';
			$args[]  = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
		}

		$where_sql = implode( ' AND ', $where );
		$offset    = ( $page - 1 ) * $per_page;

		$total = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->table()} WHERE {$where_sql}",
				...$args
			)
		);

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT * FROM {$this->table()} WHERE {$where_sql} ORDER BY updated_at DESC LIMIT %d OFFSET %d",
				...array_merge( $args, array( $per_page, $offset ) )
			)
		);

		$items = array();
		foreach ( $rows as $row ) {
			$items[] = ContentItem::from_row( $row );
		}

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * Get count by status.
	 *
	 * @return array<string, int>
	 */
	public function count_by_status(): array {
		global $wpdb;

		$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT status, COUNT(*) as count FROM {$this->table()} GROUP BY status",
			ARRAY_A
		);

		$counts = array();
		foreach ( $results as $result ) {
			$counts[ $result['status'] ] = (int) $result['count'];
		}

		return $counts;
	}
}
