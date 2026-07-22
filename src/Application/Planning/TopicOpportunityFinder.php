<?php
/**
 * Topic opportunity discovery service.
 *
 * @package Citeoryx\Application\Planning
 */

namespace Citeoryx\Application\Planning;

use Citeoryx\Domain\Planning\OpportunityRepository;

/**
 * Classifies search query evidence into conservative planning opportunities.
 */
class TopicOpportunityFinder {

	private const ROW_LIMIT = 1000;

	private OpportunityRepository $repository;

	public function __construct( OpportunityRepository $repository ) {
		$this->repository = $repository;
	}

	/**
	 * Find and paginate topic opportunities.
	 *
	 * @param array<string, mixed> $filters Opportunity filters.
	 * @param int                  $page Page number.
	 * @param int                  $per_page Page size.
	 * @return array<string, mixed>
	 */
	public function find( array $filters = array(), int $page = 1, int $per_page = 20 ): array {
		$days     = max( 7, min( 90, (int) ( $filters['days'] ?? 28 ) ) );
		$rows     = $this->repository->find_candidates( $days, 20, self::ROW_LIMIT );
		$grouped  = $this->group_rows( $rows, $days );
		$type     = (string) ( $filters['type'] ?? '' );
		$source   = (string) ( $filters['source'] ?? '' );
		$filtered = array_values(
			array_filter(
				$grouped,
				static fn ( array $item ): bool => ( ! $type || $item['type'] === $type )
					&& ( ! $source || $item['source'] === $source )
			)
		);

		usort( $filtered, static fn ( array $a, array $b ): int => $b['priority_score'] <=> $a['priority_score'] );
		$page        = max( 1, $page );
		$per_page    = max( 1, min( 100, $per_page ) );
		$total       = count( $filtered );
		$total_pages = max( 1, (int) ceil( $total / $per_page ) );
		$page        = min( $page, $total_pages );

		return array(
			'items'        => array_slice( $filtered, ( $page - 1 ) * $per_page, $per_page ),
			'pagination'   => array(
				'page'        => $page,
				'per_page'    => $per_page,
				'total'       => $total,
				'total_pages' => $total_pages,
			),
			'summary'      => array(
				'total'        => $total,
				'type_counts'  => $this->count_types( $filtered ),
				'data_limited' => count( $rows ) >= self::ROW_LIMIT,
			),
			'generated_at' => current_time( 'mysql' ),
		);
	}

	/**
	 * Group page rows by provider and query.
	 *
	 * @param array<int, array<string, mixed>> $rows Candidate rows.
	 * @param int                              $days Period days.
	 * @return array<int, array<string, mixed>>
	 */
	private function group_rows( array $rows, int $days ): array {
		$groups = array();
		foreach ( $rows as $row ) {
			$key = $row['source'] . ':' . $row['query_hash'];
			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array(
					'source' => $row['source'],
					'query'  => $row['query_text'],
					'pages'  => array(),
				);
			}
			$groups[ $key ]['pages'][] = $this->normalize_page( $row );
		}

		$items = array();
		foreach ( $groups as $key => $group ) {
			$item = $this->classify_group( $key, $group, $days );
			if ( $item ) {
				$items[] = $item;
			}
		}
		return $items;
	}

	/**
	 * Normalize a page evidence row.
	 *
	 * @param array<string, mixed> $row Candidate row.
	 * @return array<string, mixed>
	 */
	private function normalize_page( array $row ): array {
		$impressions          = (float) $row['impressions'];
		$position_impressions = (float) ( $row['position_impressions'] ?? $impressions );
		return array(
			'content_id'           => (int) $row['content_id'],
			'object_id'            => $row['object_id'],
			'url'                  => $row['canonical_url'],
			'status'               => $row['status'],
			'health_score'         => $row['health_score'],
			'impressions'          => $impressions,
			'clicks'               => (float) $row['clicks'],
			'position_avg'         => $position_impressions > 0 ? round( (float) $row['position_weight'] / $position_impressions, 2 ) : null,
			'position_impressions' => $position_impressions,
			'modified_at'          => $row['modified_at'],
		);
	}

	/**
	 * Classify one provider/query group.
	 *
	 * @param string                       $key Stable group key.
	 * @param array<string, mixed>         $group Group data.
	 * @param int                          $days Period days.
	 * @return array<string, mixed>|null
	 */
	private function classify_group( string $key, array $group, int $days ): ?array {
		usort( $group['pages'], static fn ( array $a, array $b ): int => $b['impressions'] <=> $a['impressions'] );
		$pages                = $group['pages'];
		$impressions          = (float) array_sum( array_column( $pages, 'impressions' ) );
		$clicks               = (float) array_sum( array_column( $pages, 'clicks' ) );
		$weight               = 0.0;
		$position_impressions = 0.0;
		foreach ( $pages as $page ) {
			if ( null !== $page['position_avg'] ) {
				$weight               += (float) $page['position_avg'] * (float) $page['position_impressions'];
				$position_impressions += (float) $page['position_impressions'];
			}
		}
		$position = $position_impressions > 0 ? round( $weight / $position_impressions, 2 ) : null;
		$best     = $pages[0];
		foreach ( $pages as $page ) {
			if ( null !== $page['position_avg'] && ( null === $best['position_avg'] || $page['position_avg'] < $best['position_avg'] ) ) {
				$best = $page;
			}
		}

		$classification = $this->classification( $best, $impressions );
		if ( ! $classification ) {
			return null;
		}

		return array_merge(
			array(
				'id'             => md5( $classification['type'] . ':' . $key ),
				'query'          => $group['query'],
				'source'         => $group['source'],
				'priority_score' => $this->priority_score( $classification['type'], $impressions ),
				'metrics'        => array(
					'impressions'  => $impressions,
					'clicks'       => $clicks,
					'ctr'          => $impressions > 0 ? round( $clicks / $impressions, 6 ) : 0.0,
					'position_avg' => $position,
					'period_days'  => $days,
				),
				'pages'          => array_map(
					static function ( array $page ): array {
						unset( $page['position_impressions'] );
						return $page;
					},
					array_slice( $pages, 0, 5 )
				),
			),
			$classification
		);
	}

	/**
	 * Apply conservative opportunity rules.
	 *
	 * @param array<string, mixed> $best Best-ranking page.
	 * @param float                $impressions Query impressions.
	 * @return array<string, mixed>|null
	 */
	private function classification( array $best, float $impressions ): ?array {
		$position       = $best['position_avg'];
		$refresh_status = in_array( $best['status'], array( 'stale', 'opportunity', 'needs_review' ), true );
		if ( $impressions >= 50 && $refresh_status && null !== $position && $position <= 30 ) {
			return array(
				'type'               => 'refresh_before_new',
				'issue_code'         => 'CX_PLAN_REFRESH_BEFORE_NEW',
				'confidence'         => 'high',
				'recommended_action' => 'refresh_existing',
				'evidence'           => array( 'Existing page has search demand but its content status needs attention.' ),
			);
		}
		if ( $impressions >= 50 && null !== $position && $position >= 4 && $position <= 15 ) {
			return array(
				'type'               => 'striking_distance',
				'issue_code'         => 'CX_PLAN_EXISTING_PAGE_MATCH',
				'confidence'         => 'high',
				'recommended_action' => 'improve_existing',
				'evidence'           => array( 'Best page ranks between positions 4 and 15 with meaningful impressions.' ),
			);
		}
		if ( $impressions >= 100 && null !== $position && $position > 15 ) {
			return array(
				'type'               => 'topic_gap_candidate',
				'issue_code'         => 'CX_PLAN_TOPIC_GAP',
				'confidence'         => 'low',
				'recommended_action' => 'review_topic_gap',
				'evidence'           => array( 'No page ranks in the top 15; verify search intent manually before creating content.' ),
			);
		}
		return null;
	}

	/**
	 * Calculate a deterministic display priority.
	 *
	 * @param string $type Opportunity type.
	 * @param float  $impressions Query impressions.
	 * @return int
	 */
	private function priority_score( string $type, float $impressions ): int {
		$base = array(
			'refresh_before_new'  => 75,
			'striking_distance'   => 70,
			'topic_gap_candidate' => 55,
		)[ $type ];
		return min( 100, (int) round( $base + min( 20, log10( max( 10, $impressions ) ) * 5 ) ) );
	}

	/**
	 * Count result types using stable list items.
	 *
	 * @param array<int, array<string, mixed>> $items Opportunities.
	 * @return array<int, array{label:string,count:int}>
	 */
	private function count_types( array $items ): array {
		$counts = array();
		foreach ( $items as $item ) {
			$counts[ $item['type'] ] = ( $counts[ $item['type'] ] ?? 0 ) + 1;
		}
		ksort( $counts );
		$result = array();
		foreach ( $counts as $label => $count ) {
			$result[] = array(
				'label' => $label,
				'count' => $count,
			);
		}
		return $result;
	}
}
