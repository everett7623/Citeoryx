<?php
/**
 * Reports REST controller tests.
 *
 * @package Citeoryx\Tests\Unit
 */

namespace Citeoryx\Tests\Unit;

use Citeoryx\Core\Container;
use Citeoryx\Rest\Controllers\ReportsController;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Tests the report response contract.
 */
class ReportsControllerTest extends WP_UnitTestCase {

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
}
