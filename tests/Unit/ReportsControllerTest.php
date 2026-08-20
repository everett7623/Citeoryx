<?php
/**
 * Reports REST controller tests.
 *
 * @package Citeoryx\Tests\Unit
 */

namespace Citeoryx\Tests\Unit;

use Citeoryx\Core\Container;
use Citeoryx\Domain\Content\ContentItem;
use Citeoryx\Domain\Content\ContentRepository;
use Citeoryx\Infrastructure\Cache\RestResponseCache;
use Citeoryx\Rest\Controllers\DashboardController;
use Citeoryx\Rest\Controllers\ReportsController;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Tests the report response contract.
 */
class ReportsControllerTest extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		RestResponseCache::invalidate();
		RestResponseCache::flush();
	}

	public function test_summary_returns_stable_sections(): void {
		$request  = new WP_REST_Request( 'GET', '/citeoryx/v1/reports/summary' );
		$response = ( new ReportsController( new Container() ) )->get_summary( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $data['success'] );
		$this->assertIsString( $data['data']['generated_at'] );
		$this->assertIsArray( $data['data']['content']['status_counts'] );
		$this->assertIsArray( $data['data']['issues']['severity_counts'] );
		$this->assertIsArray( $data['data']['issues']['category_counts'] );
		$this->assertIsArray( $data['data']['issues']['top_items'] );
		$this->assertIsArray( $data['data']['scans']['recent'] );
		$this->assertIsArray( $data['data']['performance']['history'] );
		$this->assertIsArray( $data['data']['performance']['dimensions']['queries'] );
		$this->assertIsArray( $data['data']['performance']['dimensions']['countries'] );
		$this->assertIsArray( $data['data']['performance']['dimensions']['devices'] );
		$this->assertArrayHasKey( 'version', $data['data']['plugin'] );
	}

	public function test_summary_refreshes_after_content_repository_write(): void {
		$request    = new WP_REST_Request( 'GET', '/citeoryx/v1/reports/summary' );
		$controller = new ReportsController( new Container() );
		$before     = $controller->get_summary( $request )->get_data()['data']['content']['total'];
		$item       = new ContentItem();

		$item->object_type   = 'post';
		$item->post_type     = 'post';
		$item->canonical_url = home_url( '/cached-report-write' );
		$item->url_hash      = md5( $item->canonical_url );
		( new ContentRepository() )->save( $item );

		$after = $controller->get_summary( $request )->get_data()['data']['content']['total'];
		$this->assertSame( $before + 1, $after );
	}

	public function test_dashboard_refreshes_after_content_repository_write(): void {
		$request    = new WP_REST_Request( 'GET', '/citeoryx/v1/dashboard' );
		$controller = new DashboardController( new Container() );
		$before     = $controller->get_dashboard( $request )->get_data()['data']['total_content'];
		$item       = new ContentItem();

		$item->object_type   = 'post';
		$item->post_type     = 'post';
		$item->canonical_url = home_url( '/cached-dashboard-write' );
		$item->url_hash      = md5( $item->canonical_url );
		( new ContentRepository() )->save( $item );

		$after = $controller->get_dashboard( $request )->get_data()['data']['total_content'];
		$this->assertSame( $before + 1, $after );
	}
}
