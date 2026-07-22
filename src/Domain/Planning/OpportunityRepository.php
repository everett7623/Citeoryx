<?php
/**
 * Topic opportunity data access.
 *
 * @package Citeoryx\Domain\Planning
 */

namespace Citeoryx\Domain\Planning;

/**
 * Reads bounded query-to-page aggregates for opportunity discovery.
 */
class OpportunityRepository {

	/**
	 * Find recent query/page candidates in one bounded query.
	 *
	 * @param int   $days Recent period in days.
	 * @param float $min_impressions Minimum aggregate impressions per page.
	 * @param int   $limit Maximum returned rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function find_candidates( int $days = 28, float $min_impressions = 20, int $limit = 1000 ): array {
		global $wpdb;

		$days            = max( 7, min( 90, $days ) );
		$min_impressions = max( 0, $min_impressions );
		$limit           = max( 1, min( 1000, $limit ) );
		$period_start    = gmdate( 'Y-m-d', time() - ( DAY_IN_SECONDS * ( $days - 1 ) ) );
		$query_table     = $wpdb->prefix . CITEORYX_TABLE_QUERY_PAGES;
		$content_table   = $wpdb->prefix . CITEORYX_TABLE_CONTENT_ITEMS;

		$sql = $wpdb->prepare(
			'SELECT qp.source, qp.query_hash, MAX(qp.query_text) AS query_text,
				qp.content_id, content.object_id, content.canonical_url, content.status,
				content.health_score, content.modified_at,
				SUM(qp.impressions) AS impressions, SUM(qp.clicks) AS clicks,
				SUM(qp.position_avg * qp.impressions) AS position_weight,
				SUM(CASE WHEN qp.position_avg IS NOT NULL THEN qp.impressions ELSE 0 END) AS position_impressions
			FROM %i qp
			INNER JOIN %i content ON content.id = qp.content_id
			WHERE qp.period_end >= %s
			GROUP BY qp.source, qp.query_hash, qp.content_id, content.object_id,
				content.canonical_url, content.status, content.health_score, content.modified_at
			HAVING SUM(qp.impressions) >= %f
			ORDER BY impressions DESC
			LIMIT %d',
			$query_table,
			$content_table,
			$period_start,
			$min_impressions,
			$limit
		);

		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
		return array_map( array( $this, 'normalize_row' ), $rows ?: array() );
	}

	/**
	 * Normalize database scalar values.
	 *
	 * @param array<string, mixed> $row Database row.
	 * @return array<string, mixed>
	 */
	private function normalize_row( array $row ): array {
		$row['content_id']           = (int) $row['content_id'];
		$row['object_id']            = null === $row['object_id'] ? null : (int) $row['object_id'];
		$row['health_score']         = null === $row['health_score'] ? null : (float) $row['health_score'];
		$row['impressions']          = (float) $row['impressions'];
		$row['clicks']               = (float) $row['clicks'];
		$row['position_weight']      = (float) $row['position_weight'];
		$row['position_impressions'] = (float) $row['position_impressions'];
		return $row;
	}
}
