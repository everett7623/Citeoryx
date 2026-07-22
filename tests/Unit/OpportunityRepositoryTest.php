<?php
/**
 * Opportunity repository tests.
 *
 * @package Citeoryx\Tests\Unit
 */

namespace Citeoryx\Tests\Unit;

use Citeoryx\Domain\Content\ContentItem;
use Citeoryx\Domain\Content\ContentRepository;
use Citeoryx\Domain\Metrics\MetricsRepository;
use Citeoryx\Domain\Planning\OpportunityRepository;
use WP_UnitTestCase;

/**
 * Tests bounded topic opportunity aggregation.
 */
class OpportunityRepositoryTest extends WP_UnitTestCase {

	/**
	 * Clean query and content fixtures.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $wpdb->prefix . CITEORYX_TABLE_QUERY_PAGES ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $wpdb->prefix . CITEORYX_TABLE_CONTENT_ITEMS ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		parent::tearDown();
	}

	/**
	 * Repository should aggregate dimensions for one query/page candidate.
	 *
	 * @return void
	 */
	public function test_find_candidates_aggregates_query_dimensions(): void {
		$item                = new ContentItem();
		$item->canonical_url = 'https://example.com/planning';
		$item->url_hash      = md5( $item->canonical_url );
		$item->status        = 'healthy';
		$content_id          = ( new ContentRepository() )->save( $item );
		$date                = gmdate( 'Y-m-d' );
		$metrics             = new MetricsRepository();

		$metrics->save_query_pages(
			$content_id,
			'google_search_console',
			$date,
			$date,
			array(
				array(
					'query'       => 'planning query',
					'device'      => 'desktop',
					'impressions' => 100,
					'clicks'      => 10,
					'position'    => 2,
				),
				array(
					'query'       => 'planning query',
					'device'      => 'mobile',
					'impressions' => 50,
					'clicks'      => 5,
					'position'    => 4,
				),
			)
		);

		$rows = ( new OpportunityRepository() )->find_candidates();

		$this->assertCount( 1, $rows );
		$this->assertSame( 150.0, $rows[0]['impressions'] );
		$this->assertSame( 15.0, $rows[0]['clicks'] );
		$this->assertSame( 400.0, $rows[0]['position_weight'] );
		$this->assertSame( 150.0, $rows[0]['position_impressions'] );
	}
}
