<?php
/**
 * Metrics repository.
 *
 * @package Citeoryx\Domain\Metrics
 */

namespace Citeoryx\Domain\Metrics;

/**
 * Repository for daily metrics.
 */
class MetricsRepository {

	/**
	 * Table name.
	 *
	 * @return string
	 */
	private function table(): string {
		global $wpdb;
		return $wpdb->prefix . CITEORYX_TABLE_METRICS_DAILY;
	}

	/**
	 * Save daily metrics.
	 *
	 * @param int                  $content_id Content ID.
	 * @param string               $date Date.
	 * @param string               $source Source.
	 * @param array<string, mixed> $metrics Metrics.
	 * @return int
	 */
	public function save( int $content_id, string $date, string $source, array $metrics ): int {
		global $wpdb;

		$data = array(
			'content_id'   => $content_id,
			'metric_date'  => $date,
			'source'       => $source,
			'impressions'  => $metrics['impressions'] ?? null,
			'clicks'       => $metrics['clicks'] ?? null,
			'ctr'          => $metrics['ctr'] ?? null,
			'position_avg' => $metrics['position_avg'] ?? null,
			'sessions'     => $metrics['sessions'] ?? null,
			'conversions'  => $metrics['conversions'] ?? null,
			'revenue'      => $metrics['revenue'] ?? null,
			'extra_json'   => ! empty( $metrics['extra'] ) ? wp_json_encode( $metrics['extra'] ) : null,
		);

		$existing = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT id FROM %i WHERE content_id = %d AND metric_date = %s AND source = %s',
				$this->table(),
				$content_id,
				$date,
				$source
			)
		);

		if ( $existing ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$this->table(),
				$data,
				array( 'id' => $existing ),
				array( '%d', '%s', '%s', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%s' ),
				array( '%d' )
			);
			return (int) $existing;
		}

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			$data,
			array( '%d', '%s', '%s', '%f', '%f', '%f', '%f', '%f', '%f', '%f', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get latest metrics for content.
	 *
	 * @param int    $content_id Content ID.
	 * @param string $source Source.
	 * @param int    $days Number of days.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_recent( int $content_id, string $source, int $days = 28 ): array {
		global $wpdb;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT * FROM %i WHERE content_id = %d AND source = %s AND metric_date >= DATE_SUB(CURDATE(), INTERVAL %d DAY) ORDER BY metric_date DESC',
				$this->table(),
				$content_id,
				$source,
				$days
			),
			ARRAY_A
		);

		return $rows ?: array();
	}

	/**
	 * Aggregate metrics over period.
	 *
	 * @param int    $content_id Content ID.
	 * @param string $source Source.
	 * @param int    $days Days.
	 * @return array<string, float|null>
	 */
	public function aggregate( int $content_id, string $source, int $days = 28 ): array {
		global $wpdb;

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT SUM(impressions) as impressions, SUM(clicks) as clicks, AVG(position_avg) as position_avg FROM %i WHERE content_id = %d AND source = %s AND metric_date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)',
				$this->table(),
				$content_id,
				$source,
				$days
			),
			ARRAY_A
		);

		return $this->normalize_aggregate( is_array( $row ) ? $row : array() );
	}

	public function aggregate_site( int $days = 28 ): array {
		global $wpdb;

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT SUM(impressions) as impressions, SUM(clicks) as clicks, AVG(position_avg) as position_avg, MAX(metric_date) as last_imported_date FROM %i WHERE metric_date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)',
				$this->table(),
				$days
			),
			ARRAY_A
		);

		$aggregate                       = $this->normalize_aggregate( is_array( $row ) ? $row : array() );
		$aggregate['last_imported_date'] = $row['last_imported_date'] ?? null;
		return $aggregate;
	}

	private function normalize_aggregate( array $row ): array {
		$impressions = null !== ( $row['impressions'] ?? null ) ? (float) $row['impressions'] : null;
		$clicks      = null !== ( $row['clicks'] ?? null ) ? (float) $row['clicks'] : null;

		return array(
			'impressions'  => $impressions,
			'clicks'       => $clicks,
			'ctr'          => null !== $impressions && $impressions > 0 && null !== $clicks ? $clicks / $impressions : null,
			'position_avg' => null !== ( $row['position_avg'] ?? null ) ? (float) $row['position_avg'] : null,
		);
	}
}
