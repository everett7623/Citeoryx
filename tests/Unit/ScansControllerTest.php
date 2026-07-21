<?php
/**
 * Scan REST controller tests.
 *
 * @package Citeoryx\Tests\Unit
 */

namespace Citeoryx\Tests\Unit;

use Citeoryx\Core\Container;
use Citeoryx\Rest\Controllers\ScansController;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Tests for asynchronous scan creation.
 */
class ScansControllerTest extends WP_UnitTestCase {

	private int $run_id = 0;

	public function tearDown(): void {
		if ( $this->run_id ) {
			wp_clear_scheduled_hook( 'citeoryx_run_scan', array( 'run_id' => $this->run_id ) );
			global $wpdb;
			$wpdb->delete( $wpdb->prefix . CITEORYX_TABLE_SCAN_RUNS, array( 'id' => $this->run_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		parent::tearDown();
	}

	public function test_create_scan_returns_queued_task(): void {
		$request = new WP_REST_Request( 'POST', '/citeoryx/v1/scans' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'scan_type' => 'incremental' ) ) );

		$response     = ( new ScansController( new Container() ) )->create_scan( $request );
		$data         = $response->get_data()['data'];
		$this->run_id = (int) $data['id'];

		$this->assertSame( 202, $response->get_status() );
		$this->assertSame( 'queued', $data['status'] );
		$this->assertSame( 'incremental', $data['scan_type'] );
		$this->assertGreaterThan( 0, $data['id'] );
	}

	public function test_create_scan_rejects_unknown_type(): void {
		$request = new WP_REST_Request( 'POST', '/citeoryx/v1/scans' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'scan_type' => 'unknown' ) ) );

		$response = ( new ScansController( new Container() ) )->create_scan( $request );
		$this->assertSame( 400, $response->get_status() );
	}
}
