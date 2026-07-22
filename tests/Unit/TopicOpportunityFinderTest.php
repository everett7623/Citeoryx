<?php
/**
 * Topic opportunity finder tests.
 *
 * @package Citeoryx\Tests\Unit
 */

namespace Citeoryx\Tests\Unit;

use Citeoryx\Application\Planning\TopicOpportunityFinder;
use Citeoryx\Domain\Planning\OpportunityRepository;
use WP_UnitTestCase;

/**
 * Tests conservative planning classifications.
 */
class TopicOpportunityFinderTest extends WP_UnitTestCase {

	/**
	 * Finder should classify the three supported opportunity types.
	 *
	 * @return void
	 */
	public function test_finder_classifies_supported_opportunities(): void {
		$finder = new TopicOpportunityFinder( $this->repository_with_rows( $this->candidate_rows() ) );
		$result = $finder->find();
		$types  = array_column( $result['items'], 'type' );

		$this->assertCount( 3, $result['items'] );
		$this->assertContains( 'refresh_before_new', $types );
		$this->assertContains( 'striking_distance', $types );
		$this->assertContains( 'topic_gap_candidate', $types );
		$this->assertFalse( $result['summary']['data_limited'] );
	}

	/**
	 * Filters and pagination should preserve the response contract.
	 *
	 * @return void
	 */
	public function test_finder_filters_and_paginates_results(): void {
		$finder = new TopicOpportunityFinder( $this->repository_with_rows( $this->candidate_rows() ) );
		$result = $finder->find( array( 'type' => 'striking_distance' ), 1, 1 );

		$this->assertSame( 1, $result['pagination']['total'] );
		$this->assertSame( 1, $result['pagination']['per_page'] );
		$this->assertSame( 'striking_distance', $result['items'][0]['type'] );
		$this->assertSame( 'CX_PLAN_EXISTING_PAGE_MATCH', $result['items'][0]['issue_code'] );
		$this->assertArrayHasKey( 'metrics', $result['items'][0] );
		$this->assertArrayHasKey( 'pages', $result['items'][0] );
	}

	/**
	 * Create a repository double with fixed rows.
	 *
	 * @param array<int, array<string, mixed>> $rows Rows.
	 * @return OpportunityRepository
	 */
	private function repository_with_rows( array $rows ): OpportunityRepository {
		return new class( $rows ) extends OpportunityRepository {
			/** @var array<int, array<string, mixed>> */
			private array $rows;

			public function __construct( array $rows ) {
				$this->rows = $rows;
			}

			public function find_candidates( int $days = 28, float $min_impressions = 20, int $limit = 1000 ): array {
				return $this->rows;
			}
		};
	}

	/**
	 * Build one fixture for each opportunity rule.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function candidate_rows(): array {
		return array(
			$this->row( 'refresh query', 'refresh', 'stale', 60, 20 ),
			$this->row( 'near query', 'near', 'healthy', 80, 8 ),
			$this->row( 'gap query', 'gap', 'healthy', 120, 22 ),
		);
	}

	/**
	 * Build a candidate row.
	 *
	 * @param string $query Query text.
	 * @param string $hash Query hash.
	 * @param string $status Content status.
	 * @param float  $impressions Impressions.
	 * @param float  $position Position.
	 * @return array<string, mixed>
	 */
	private function row( string $query, string $hash, string $status, float $impressions, float $position ): array {
		return array(
			'source'               => 'google_search_console',
			'query_hash'           => $hash,
			'query_text'           => $query,
			'content_id'           => crc32( $hash ),
			'object_id'            => 1,
			'canonical_url'        => 'https://example.com/' . $hash,
			'status'               => $status,
			'health_score'         => 70.0,
			'modified_at'          => '2026-07-01 00:00:00',
			'impressions'          => $impressions,
			'clicks'               => 5.0,
			'position_weight'      => $impressions * $position,
			'position_impressions' => $impressions,
		);
	}
}
