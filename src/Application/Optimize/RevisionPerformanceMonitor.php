<?php
/**
 * Revision post-publish performance monitor.
 *
 * @package Citeoryx\Application\Optimize
 */

namespace Citeoryx\Application\Optimize;

use DateTimeImmutable;
use Citeoryx\Domain\Metrics\MetricsRepository;

/**
 * Compares fixed pre- and post-publication periods for a verified proposal.
 */
class RevisionPerformanceMonitor {

	private RevisionDraftService $revision_drafts;
	private MetricsRepository $metrics;

	public function __construct( RevisionDraftService $revision_drafts, MetricsRepository $metrics ) {
		$this->revision_drafts = $revision_drafts;
		$this->metrics         = $metrics;
	}

	/**
	 * Get seven- and twenty-eight-day provider-separated comparisons.
	 *
	 * @param int $content_id Citeoryx content ID.
	 * @return array<string, mixed>
	 */
	public function get_performance( int $content_id ): array {
		$workflow     = $this->revision_drafts->get_workflow_status( $content_id );
		$published_at = (string) ( $workflow['published_at'] ?? '' );
		$verified_at  = (string) ( $workflow['verified_at'] ?? '' );
		if ( ! $workflow['verified'] || '' === $published_at || '' === $verified_at ) {
			return $this->unavailable();
		}

		try {
			$published = ( new DateTimeImmutable( $published_at, wp_timezone() ) )->setTime( 0, 0 );
			$today     = new DateTimeImmutable( substr( current_time( 'mysql' ), 0, 10 ), wp_timezone() );
		} catch ( \Exception $exception ) {
			return $this->unavailable();
		}

		if ( $published > $today ) {
			return $this->unavailable();
		}

		return array(
			'available'    => true,
			'published_at' => mysql_to_rfc3339( $published_at ),
			'verified_at'  => mysql_to_rfc3339( $verified_at ),
			'windows'      => array(
				$this->compare_window( $content_id, $published, $today, 7 ),
				$this->compare_window( $content_id, $published, $today, 28 ),
			),
		);
	}

	/**
	 * Compare a same-length before/after interval for one duration.
	 *
	 * @return array<string, mixed>
	 */
	private function compare_window( int $content_id, DateTimeImmutable $published, DateTimeImmutable $today, int $days ): array {
		$days_since     = (int) $published->diff( $today )->format( '%r%a' );
		$elapsed        = min( $days, max( 1, $days_since + 1 ) );
		$current_end    = $published->modify( '+' . ( $elapsed - 1 ) . ' days' );
		$baseline_end   = $published->modify( '-1 day' );
		$baseline_start = $published->modify( '-' . $elapsed . ' days' );

		$baseline = $this->metrics->aggregate_by_source_between(
			$content_id,
			$baseline_start->format( 'Y-m-d' ),
			$baseline_end->format( 'Y-m-d' )
		);
		$current  = $this->metrics->aggregate_by_source_between(
			$content_id,
			$published->format( 'Y-m-d' ),
			$current_end->format( 'Y-m-d' )
		);
		$sources  = array_values( array_unique( array_merge( array_keys( $baseline ), array_keys( $current ) ) ) );
		sort( $sources, SORT_STRING );

		$comparisons    = array();
		$has_comparison = false;
		foreach ( $sources as $source ) {
			$before         = $baseline[ $source ] ?? $this->empty_aggregate();
			$after          = $current[ $source ] ?? $this->empty_aggregate();
			$comparable     = $before['days_with_data'] > 0 && $after['days_with_data'] > 0;
			$has_comparison = $has_comparison || $comparable;
			$comparisons[]  = array(
				'source'   => $source,
				'state'    => $comparable ? 'ready' : 'unavailable',
				'baseline' => $before,
				'current'  => $after,
				'delta'    => $this->delta( $before, $after ),
			);
		}

		return array(
			'days'         => $days,
			'elapsed_days' => $elapsed,
			'state'        => $elapsed < $days ? 'collecting' : ( $has_comparison ? 'ready' : 'unavailable' ),
			'baseline'     => array(
				'start_date' => $baseline_start->format( 'Y-m-d' ),
				'end_date'   => $baseline_end->format( 'Y-m-d' ),
			),
			'current'      => array(
				'start_date' => $published->format( 'Y-m-d' ),
				'end_date'   => $current_end->format( 'Y-m-d' ),
			),
			'sources'      => $comparisons,
		);
	}

	/**
	 * @return array<string, float|int|null>
	 */
	private function empty_aggregate(): array {
		return array(
			'impressions'       => null,
			'clicks'            => null,
			'ctr'               => null,
			'position_avg'      => null,
			'days_with_data'    => 0,
			'first_metric_date' => null,
			'last_metric_date'  => null,
		);
	}

	/**
	 * @param array<string, float|int|null> $baseline Baseline aggregate.
	 * @param array<string, float|int|null> $current Current aggregate.
	 * @return array<string, float|null>
	 */
	private function delta( array $baseline, array $current ): array {
		$delta = array();
		foreach ( array( 'impressions', 'clicks', 'ctr', 'position_avg' ) as $field ) {
			$delta[ $field ] = null !== $baseline[ $field ] && null !== $current[ $field ]
				? (float) $current[ $field ] - (float) $baseline[ $field ]
				: null;
		}
		return $delta;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function unavailable(): array {
		return array(
			'available'    => false,
			'published_at' => null,
			'verified_at'  => null,
			'windows'      => array(),
		);
	}
}
