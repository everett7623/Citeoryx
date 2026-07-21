<?php
/**
 * Metrics repository tests.
 *
 * @package Citeoryx\Tests\Unit
 */

namespace Citeoryx\Tests\Unit;

use Citeoryx\Domain\Metrics\MetricsRepository;
use WP_UnitTestCase;

/**
 * Tests daily metrics and search dimension snapshots.
 */
class MetricsRepositoryTest extends WP_UnitTestCase {

	private MetricsRepository $repository;

	/**
	 * Set up the repository.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->repository = new MetricsRepository();
	}

	/**
	 * Remove metric fixtures.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $wpdb->prefix . CITEORYX_TABLE_QUERY_PAGES ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $wpdb->prefix . CITEORYX_TABLE_METRICS_DAILY ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		parent::tearDown();
	}

	/**
	 * Re-importing one dimension key should update its snapshot in place.
	 *
	 * @return void
	 */
	public function test_query_dimension_snapshot_is_idempotent(): void {
		global $wpdb;
		$date = gmdate( 'Y-m-d' );
		$row  = array(
			'query'       => 'Example Query',
			'country'     => 'usa',
			'device'      => 'mobile',
			'impressions' => 40,
			'clicks'      => 4,
			'position'    => 3.5,
		);

		$this->assertSame( 1, $this->repository->save_query_pages( 1, 'google_search_console', $date, $date, array( $row ) ) );
		$row['impressions'] = 50;
		$this->assertSame( 1, $this->repository->save_query_pages( 1, 'google_search_console', $date, $date, array( $row ) ) );

		$count = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i', $wpdb->prefix . CITEORYX_TABLE_QUERY_PAGES )
		);
		$this->assertSame( 1, (int) $count );
	}

	/**
	 * Dimension aggregates should merge countries and devices without merging providers.
	 *
	 * @return void
	 */
	public function test_dimension_aggregates_group_expected_values(): void {
		$date = gmdate( 'Y-m-d' );
		$this->repository->save_query_pages(
			1,
			'google_search_console',
			$date,
			$date,
			array(
				array(
					'query'       => 'alpha',
					'country'     => 'usa',
					'device'      => 'desktop',
					'impressions' => 100,
					'clicks'      => 10,
					'position'    => 2,
				),
				array(
					'query'       => 'alpha',
					'country'     => 'usa',
					'device'      => 'mobile',
					'impressions' => 50,
					'clicks'      => 5,
					'position'    => 4,
				),
				array(
					'query'       => 'beta',
					'country'     => 'deu',
					'device'      => 'mobile',
					'impressions' => 30,
					'clicks'      => 3,
					'position'    => 6,
				),
			)
		);
		$this->repository->save_query_pages(
			1,
			'bing_webmaster_tools',
			$date,
			$date,
			array(
				array(
					'query'       => 'alpha',
					'impressions' => 20,
					'clicks'      => 2,
					'position'    => 3,
				),
			)
		);

		$dimensions = $this->repository->aggregate_query_dimensions( 28 );

		$this->assertSame( 'alpha', $dimensions['queries'][0]['label'] );
		$this->assertSame( 'google_search_console', $dimensions['queries'][0]['source'] );
		$this->assertSame( 150.0, $dimensions['queries'][0]['impressions'] );
		$this->assertSame( 'USA', $dimensions['countries'][0]['label'] );
		$this->assertSame( 150.0, $dimensions['countries'][0]['impressions'] );
		$this->assertSame( 'desktop', $dimensions['devices'][0]['label'] );
		$this->assertSame( 100.0, $dimensions['devices'][0]['impressions'] );
	}

	/**
	 * Site aggregation should include a daily history contract.
	 *
	 * @return void
	 */
	public function test_site_aggregate_includes_daily_history(): void {
		$date = gmdate( 'Y-m-d' );
		$this->repository->save(
			1,
			$date,
			'google_search_console',
			array(
				'impressions' => 100,
				'clicks'      => 10,
				'position_avg' => 2,
			)
		);
		$this->repository->save(
			2,
			$date,
			'bing_webmaster_tools',
			array(
				'impressions' => 50,
				'clicks'      => 5,
				'position_avg' => 4,
			)
		);

		$aggregate = $this->repository->aggregate_site( 28 );

		$this->assertCount( 1, $aggregate['history'] );
		$this->assertSame( 150.0, $aggregate['history'][0]['impressions'] );
		$this->assertSame( 15.0, $aggregate['history'][0]['clicks'] );
		$this->assertEqualsWithDelta( 2.666, $aggregate['history'][0]['position_avg'], 0.001 );
	}
}
