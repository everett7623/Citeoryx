<?php
/**
 * Planning calendar data access.
 *
 * @package Citeoryx\Domain\Planning
 */

namespace Citeoryx\Domain\Planning;

/**
 * Reads bounded publishing and review calendar data.
 */
class CalendarRepository {

	/**
	 * Find WordPress posts scheduled inside a local-date range.
	 *
	 * @param array<string> $post_types Post types.
	 * @param string        $start Local start date and time.
	 * @param string        $end Local end date and time.
	 * @param int           $limit Maximum returned items.
	 * @return array{items:array<int, array<string, mixed>>,data_limited:bool}
	 */
	public function find_scheduled( array $post_types, string $start, string $end, int $limit = 50 ): array {
		$limit = max( 1, min( 100, $limit ) );
		if ( empty( $post_types ) ) {
			return array(
				'items'        => array(),
				'data_limited' => false,
			);
		}

		$query = new \WP_Query(
			array(
				'post_type'              => $post_types,
				'post_status'            => 'future',
				'posts_per_page'         => $limit + 1,
				'orderby'                => 'date',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'date_query'             => array(
					array(
						'column'    => 'post_date',
						'after'     => $start,
						'before'    => $end,
						'inclusive' => true,
					),
				),
			)
		);

		$posts   = $query->posts;
		$limited = count( $posts ) > $limit;
		$items   = array_map( array( $this, 'scheduled_row' ), array_slice( $posts, 0, $limit ) );
		return array(
			'items'        => $items,
			'data_limited' => $limited,
		);
	}

	/**
	 * Find content whose review reference is at or before the cutoff.
	 *
	 * @param string $cutoff Local cutoff date and time.
	 * @param int    $limit Maximum returned items.
	 * @return array{items:array<int, array<string, mixed>>,data_limited:bool}
	 */
	public function find_due_reviews( string $cutoff, int $limit = 50 ): array {
		global $wpdb;

		$limit         = max( 1, min( 100, $limit ) );
		$content_table = $wpdb->prefix . CITEORYX_TABLE_CONTENT_ITEMS;
		$sql           = $wpdb->prepare(
			'SELECT content.id, content.object_id, content.object_type, content.post_type,
				content.canonical_url, content.status, content.health_score,
				posts.post_title,
				COALESCE(content.last_reviewed_at, content.modified_at, content.published_at, content.created_at) AS review_reference_at
			FROM %i content
			LEFT JOIN %i posts ON content.object_type = %s AND posts.ID = content.object_id
			WHERE content.status <> %s
				AND (content.object_type <> %s OR posts.post_status = %s)
				AND (
					content.last_reviewed_at <= %s
					OR (content.last_reviewed_at IS NULL AND content.modified_at <= %s)
					OR (content.last_reviewed_at IS NULL AND content.modified_at IS NULL AND content.published_at <= %s)
					OR (content.last_reviewed_at IS NULL AND content.modified_at IS NULL AND content.published_at IS NULL AND content.created_at <= %s)
				)
			ORDER BY review_reference_at ASC, content.id ASC
			LIMIT %d',
			$content_table,
			$wpdb->posts,
			'post',
			'archived',
			'post',
			'publish',
			$cutoff,
			$cutoff,
			$cutoff,
			$cutoff,
			$limit + 1
		);

		$rows    = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		$limited = count( $rows ?: array() ) > $limit;
		$items   = array_map( array( $this, 'review_row' ), array_slice( $rows ?: array(), 0, $limit ) );
		return array(
			'items'        => $items,
			'data_limited' => $limited,
		);
	}

	/**
	 * Normalize a scheduled post.
	 *
	 * @param \WP_Post $post Post.
	 * @return array<string, mixed>
	 */
	private function scheduled_row( \WP_Post $post ): array {
		$publish_at = get_post_datetime( $post, 'date' );
		return array(
			'id'         => (int) $post->ID,
			'title'      => $post->post_title,
			'post_type'  => $post->post_type,
			'author_id'  => (int) $post->post_author,
			'publish_at' => $publish_at ? $publish_at->format( DATE_ATOM ) : null,
			'edit_url'   => admin_url( 'post.php?post=' . (int) $post->ID . '&action=edit' ),
		);
	}

	/**
	 * Normalize a due-review row.
	 *
	 * @param array<string, mixed> $row Database row.
	 * @return array<string, mixed>
	 */
	private function review_row( array $row ): array {
		$row['id']           = (int) $row['id'];
		$row['object_id']    = null === $row['object_id'] ? null : (int) $row['object_id'];
		$row['health_score'] = null === $row['health_score'] ? null : (float) $row['health_score'];
		return $row;
	}
}
