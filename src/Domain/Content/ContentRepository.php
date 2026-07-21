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
				'SELECT * FROM %i WHERE id = %d',
				$this->table(),
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
				'SELECT * FROM %i WHERE object_type = %s AND object_id = %d',
				$this->table(),
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
				'SELECT * FROM %i WHERE url_hash = %s',
				$this->table(),
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
			'object_id'          => $item->object_id,
			'object_type'        => $item->object_type,
			'post_type'          => $item->post_type,
			'canonical_url'      => $item->canonical_url,
			'url_hash'           => $item->url_hash,
			'language_code'      => $item->language_code,
			'status'             => $item->status,
			'health_score'       => $item->health_score,
			'health_confidence'  => $item->health_confidence,
			'ai_readiness_score' => $item->ai_readiness_score,
			'content_hash'       => $item->content_hash,
			'published_at'       => $item->published_at,
			'modified_at'        => $item->modified_at,
			'last_scanned_at'    => $item->last_scanned_at,
			'last_reviewed_at'   => $item->last_reviewed_at,
			'assigned_user_id'   => $item->assigned_user_id,
			'metadata_json'      => $item->metadata ? wp_json_encode( $item->metadata ) : null,
			'updated_at'         => $now,
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

		$from  = $this->table() . ' AS content';
		$where = array( '1=1' );
		$args  = array();

		if ( array_key_exists( 'author_id', $filters ) ) {
			$from   .= " INNER JOIN {$wpdb->posts} AS posts ON posts.ID = content.object_id AND content.object_type = 'post'";
			$where[] = 'posts.post_author = %d';
			$args[]  = max( 0, (int) $filters['author_id'] );
		}

		if ( ! empty( $filters['status'] ) ) {
			$where[] = 'content.status = %s';
			$args[]  = $filters['status'];
		}
		if ( ! empty( $filters['post_type'] ) ) {
			$where[] = 'content.post_type = %s';
			$args[]  = $filters['post_type'];
		}
		if ( isset( $filters['object_type'] ) ) {
			$where[] = 'content.object_type = %s';
			$args[]  = $filters['object_type'];
		}
		if ( ! empty( $filters['search'] ) ) {
			$where[] = 'content.canonical_url LIKE %s';
			$args[]  = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
		}

		$where_sql = implode( ' AND ', $where );
		$offset    = ( $page - 1 ) * $per_page;

		// Query fragments contain only internal table names and fixed filter clauses.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$count_sql = "SELECT COUNT(*) FROM {$from} WHERE {$where_sql}";
		if ( ! empty( $args ) ) {
			$count_sql = $wpdb->prepare( $count_sql, ...$args );
		}
		$total = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT content.* FROM {$from} WHERE {$where_sql} ORDER BY content.updated_at DESC LIMIT %d OFFSET %d",
				...array_merge( $args, array( $per_page, $offset ) )
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

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
	 * List content in stable ID order for background processing.
	 *
	 * @param int $after_id Exclusive cursor.
	 * @param int $limit Batch size.
	 * @return array<ContentItem>
	 */
	public function list_after_id( int $after_id = 0, int $limit = 100 ): array {
		global $wpdb;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id > %d ORDER BY id ASC LIMIT %d',
				$this->table(),
				max( 0, $after_id ),
				max( 1, min( 100, $limit ) )
			)
		);

		return array_map( static fn ( $row ) => ContentItem::from_row( $row ), $rows ?: array() );
	}

	/**
	 * Get count by status.
	 *
	 * @return array<string, int>
	 */
	public function count_by_status(): array {
		global $wpdb;

		$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT status, COUNT(*) as count FROM %i GROUP BY status', $this->table() ),
			ARRAY_A
		);

		$counts = array();
		foreach ( $results as $result ) {
			$counts[ $result['status'] ] = (int) $result['count'];
		}

		return $counts;
	}

	/**
	 * Get aggregate content scores for reporting.
	 *
	 * @return array{total:int,average_health_score:float|null,average_ai_readiness_score:float|null,last_scanned_at:string|null}
	 */
	public function report_summary(): array {
		global $wpdb;

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT COUNT(*) AS total, AVG(health_score) AS average_health_score, AVG(ai_readiness_score) AS average_ai_readiness_score, MAX(last_scanned_at) AS last_scanned_at FROM %i', $this->table() ),
			ARRAY_A
		);

		return array(
			'total'                      => (int) ( $row['total'] ?? 0 ),
			'average_health_score'       => isset( $row['average_health_score'] ) && null !== $row['average_health_score'] ? round( (float) $row['average_health_score'], 2 ) : null,
			'average_ai_readiness_score' => isset( $row['average_ai_readiness_score'] ) && null !== $row['average_ai_readiness_score'] ? round( (float) $row['average_ai_readiness_score'], 2 ) : null,
			'last_scanned_at'            => $row['last_scanned_at'] ?? null,
		);
	}
}
