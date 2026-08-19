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
	 * Query/page dimension table name.
	 *
	 * @return string
	 */
	private function query_table(): string {
		global $wpdb;
		return $wpdb->prefix . CITEORYX_TABLE_QUERY_PAGES;
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
	 * Save daily query, country, and device snapshots for a content item.
	 *
	 * @param int                              $content_id Content ID.
	 * @param string                           $source Provider source.
	 * @param string                           $period_start Start date.
	 * @param string                           $period_end End date.
	 * @param array<int, array<string, mixed>> $rows Provider rows.
	 * @return int Number of rows saved.
	 */
	public function save_query_pages( int $content_id, string $source, string $period_start, string $period_end, array $rows ): int {
		global $wpdb;

		$saved = 0;
		foreach ( $rows as $row ) {
			$query = sanitize_text_field( (string) ( $row['query'] ?? '' ) );
			if ( '' === $query ) {
				continue;
			}

			$country     = strtolower( substr( sanitize_text_field( (string) ( $row['country'] ?? '' ) ), 0, 8 ) );
			$device      = strtolower( substr( sanitize_key( (string) ( $row['device'] ?? '' ) ), 0, 20 ) );
			$impressions = (float) ( $row['impressions'] ?? 0 );
			$clicks      = (float) ( $row['clicks'] ?? 0 );
			$data        = array(
				'content_id'   => $content_id,
				'source'       => sanitize_key( $source ),
				'query_text'   => $this->truncate_text( $query, 500 ),
				'query_hash'   => $this->query_hash( $query ),
				'country_code' => $country,
				'device'       => $device,
				'period_start' => $period_start,
				'period_end'   => $period_end,
				'impressions'  => $impressions,
				'clicks'       => $clicks,
				'ctr'          => $impressions > 0 ? $clicks / $impressions : 0.0,
				'position_avg' => (float) ( $row['position'] ?? $row['position_avg'] ?? 0 ),
			);

			$existing = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					'SELECT id FROM %i WHERE content_id = %d AND source = %s AND query_hash = %s AND country_code = %s AND device = %s AND period_start = %s AND period_end = %s',
					$this->query_table(),
					$content_id,
					$data['source'],
					$data['query_hash'],
					$country,
					$device,
					$period_start,
					$period_end
				)
			);

			$formats = array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%f' );
			if ( $existing ) {
				$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$this->query_table(),
					$data,
					array( 'id' => (int) $existing ),
					$formats,
					array( '%d' )
				);
				++$saved;
				continue;
			}

			$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$this->query_table(),
				$data,
				$formats
			);
			if ( false !== $inserted ) {
				++$saved;
			}
		}

		return $saved;
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

	/**
	 * Aggregate a content item's performance over a fixed date range, separately
	 * for each imported search provider.
	 *
	 * @param int    $content_id Content ID.
	 * @param string $start_date Inclusive ISO date.
	 * @param string $end_date Inclusive ISO date.
	 * @return array<string, array<string, float|int|string|null>>
	 */
	public function aggregate_by_source_between( int $content_id, string $start_date, string $end_date ): array {
		global $wpdb;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT source, SUM(impressions) AS impressions, SUM(clicks) AS clicks, SUM(position_avg * impressions) AS position_weight, COUNT(DISTINCT metric_date) AS days_with_data, MIN(metric_date) AS first_metric_date, MAX(metric_date) AS last_metric_date FROM %i WHERE content_id = %d AND metric_date BETWEEN %s AND %s GROUP BY source ORDER BY source ASC',
				$this->table(),
				$content_id,
				$start_date,
				$end_date
			),
			ARRAY_A
		);

		$aggregates = array();
		foreach ( $rows ?: array() as $row ) {
			$aggregate                             = $this->normalize_aggregate( $row );
			$impressions                           = (float) ( $row['impressions'] ?? 0 );
			$aggregate['position_avg']             = $impressions > 0
				? (float) ( $row['position_weight'] ?? 0 ) / $impressions
				: null;
			$aggregate['days_with_data']           = (int) ( $row['days_with_data'] ?? 0 );
			$aggregate['first_metric_date']        = $row['first_metric_date'] ?: null;
			$aggregate['last_metric_date']         = $row['last_metric_date'] ?: null;
			$aggregates[ (string) $row['source'] ] = $aggregate;
		}

		return $aggregates;
	}

	public function aggregate_site( int $days = 28 ): array {
		global $wpdb;

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT SUM(impressions) AS impressions, SUM(clicks) AS clicks, SUM(position_avg * impressions) AS position_weight, MAX(metric_date) AS last_imported_date FROM %i WHERE metric_date >= DATE_SUB(CURDATE(), INTERVAL %d DAY)',
				$this->table(),
				$days
			),
			ARRAY_A
		);

		$aggregate                       = $this->normalize_aggregate( is_array( $row ) ? $row : array() );
		$aggregate['position_avg']       = (float) ( $row['impressions'] ?? 0 ) > 0
			? (float) $row['position_weight'] / (float) $row['impressions']
			: null;
		$aggregate['last_imported_date'] = $row['last_imported_date'] ?? null;
		$aggregate['history']            = $this->get_site_history( $days );
		$aggregate['dimensions']         = $this->aggregate_query_dimensions( $days );
		return $aggregate;
	}

	/**
	 * Return daily site-level search performance history.
	 *
	 * @param int $days Days.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_site_history( int $days = 28 ): array {
		global $wpdb;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT metric_date, SUM(impressions) AS impressions, SUM(clicks) AS clicks, SUM(position_avg * impressions) AS position_weight FROM %i WHERE metric_date >= DATE_SUB(CURDATE(), INTERVAL %d DAY) GROUP BY metric_date ORDER BY metric_date ASC',
				$this->table(),
				$days
			),
			ARRAY_A
		);

		return array_map(
			function ( array $row ): array {
				$normalized                 = $this->normalize_aggregate( $row );
				$normalized['metric_date']  = (string) $row['metric_date'];
				$normalized['position_avg'] = (float) $row['impressions'] > 0
					? (float) $row['position_weight'] / (float) $row['impressions']
					: null;
				return $normalized;
			},
			$rows ?: array()
		);
	}

	/**
	 * Aggregate query, country, and device snapshots over a period.
	 *
	 * @param int $days Days.
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public function aggregate_query_dimensions( int $days = 28 ): array {
		global $wpdb;

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT source, query_text, query_hash, country_code, device, SUM(impressions) AS impressions, SUM(clicks) AS clicks, SUM(position_avg * impressions) AS position_weight FROM %i WHERE period_end >= DATE_SUB(CURDATE(), INTERVAL %d DAY) GROUP BY source, query_text, query_hash, country_code, device ORDER BY impressions DESC LIMIT 2000',
				$this->query_table(),
				$days
			),
			ARRAY_A
		);

		$queries   = array();
		$countries = array();
		$devices   = array();
		foreach ( $rows ?: array() as $row ) {
			$this->add_dimension_value(
				$queries,
				(string) $row['source'] . ':' . (string) $row['query_hash'],
				(string) $row['query_text'],
				$row,
				(string) $row['source']
			);
			if ( ! empty( $row['country_code'] ) ) {
				$this->add_dimension_value( $countries, (string) $row['country_code'], strtoupper( (string) $row['country_code'] ), $row );
			}
			if ( ! empty( $row['device'] ) ) {
				$this->add_dimension_value( $devices, (string) $row['device'], (string) $row['device'], $row );
			}
		}

		return array(
			'queries'   => $this->finalize_dimensions( $queries, 20 ),
			'countries' => $this->finalize_dimensions( $countries, 20 ),
			'devices'   => $this->finalize_dimensions( $devices, 20 ),
		);
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

	/**
	 * Create a stable query hash while preserving the original display text.
	 *
	 * @param string $query Query text.
	 * @return string
	 */
	private function query_hash( string $query ): string {
		$normalized = function_exists( 'mb_strtolower' ) ? mb_strtolower( $query ) : strtolower( $query );
		return md5( trim( $normalized ) );
	}

	/**
	 * Truncate text without splitting multibyte query characters.
	 *
	 * @param string $value Text value.
	 * @param int    $length Maximum character count.
	 * @return string
	 */
	private function truncate_text( string $value, int $length ): string {
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length ) : substr( $value, 0, $length );
	}

	/**
	 * Add one database row to an aggregate bucket.
	 *
	 * @param array<string, array<string, mixed>> $buckets Buckets.
	 * @param string                              $key Bucket key.
	 * @param string                              $label Display label.
	 * @param array<string, mixed>                $row Database row.
	 * @param string|null                         $source Optional provider source.
	 * @return void
	 */
	private function add_dimension_value( array &$buckets, string $key, string $label, array $row, ?string $source = null ): void {
		if ( ! isset( $buckets[ $key ] ) ) {
			$buckets[ $key ] = array(
				'label'           => $label,
				'impressions'     => 0.0,
				'clicks'          => 0.0,
				'position_weight' => 0.0,
			);
			if ( $source ) {
				$buckets[ $key ]['source'] = $source;
			}
		}

		$buckets[ $key ]['impressions']     += (float) ( $row['impressions'] ?? 0 );
		$buckets[ $key ]['clicks']          += (float) ( $row['clicks'] ?? 0 );
		$buckets[ $key ]['position_weight'] += (float) ( $row['position_weight'] ?? 0 );
	}

	/**
	 * Normalize and sort aggregate buckets.
	 *
	 * @param array<string, array<string, mixed>> $buckets Buckets.
	 * @param int                                 $limit Result limit.
	 * @return array<int, array<string, mixed>>
	 */
	private function finalize_dimensions( array $buckets, int $limit ): array {
		$items = array_values(
			array_map(
				static function ( array $item ): array {
					$impressions          = (float) $item['impressions'];
					$clicks               = (float) $item['clicks'];
					$item['ctr']          = $impressions > 0 ? $clicks / $impressions : 0.0;
					$item['position_avg'] = $impressions > 0
						? (float) $item['position_weight'] / $impressions
						: null;
					unset( $item['position_weight'] );
					return $item;
				},
				$buckets
			)
		);

		usort(
			$items,
			static fn ( array $left, array $right ): int => $right['impressions'] <=> $left['impressions']
		);
		return array_slice( $items, 0, $limit );
	}
}
