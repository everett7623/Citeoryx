<?php
/**
 * Planning REST controller tests.
 *
 * @package Citeoryx\Tests\Unit
 */

namespace Citeoryx\Tests\Unit;

use Citeoryx\Core\Container;
use Citeoryx\Rest\Controllers\PlanningController;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Tests the planning response contract.
 */
class PlanningControllerTest extends WP_UnitTestCase {

	/**
	 * Empty data should still return stable pagination and summary sections.
	 *
	 * @return void
	 */
	public function test_opportunities_returns_stable_sections(): void {
		$request  = new WP_REST_Request( 'GET', '/citeoryx/v1/planning/opportunities' );
		$response = ( new PlanningController( new Container() ) )->get_opportunities( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $data['success'] );
		$this->assertIsArray( $data['data']['items'] );
		$this->assertSame( 20, $data['data']['pagination']['per_page'] );
		$this->assertIsArray( $data['data']['summary']['type_counts'] );
		$this->assertIsString( $data['data']['generated_at'] );
	}

	/**
	 * Calendar should return stable bounded sections when empty.
	 *
	 * @return void
	 */
	public function test_calendar_returns_stable_sections(): void {
		update_option( 'citeoryx_site_profile', array( 'review_cycle_days' => 90 ) );
		$request  = new WP_REST_Request( 'GET', '/citeoryx/v1/planning/calendar' );
		$response = ( new PlanningController( new Container() ) )->get_calendar( $request );
		$data     = $response->get_data()['data'];

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'timezone', $data );
		$this->assertIsArray( $data['scheduled']['items'] );
		$this->assertIsArray( $data['overdue_reviews']['items'] );
		$this->assertSame( 90, $data['review_cycle_days'] );
	}

	/**
	 * Completing a missing review should return 404.
	 *
	 * @return void
	 */
	public function test_complete_missing_review_returns_not_found(): void {
		$request = new WP_REST_Request( 'POST', '/citeoryx/v1/planning/reviews/999999/complete' );
		$request->set_param( 'id', 999999 );

		$response = ( new PlanningController( new Container() ) )->complete_review( $request );

		$this->assertSame( 404, $response->get_status() );
		$this->assertFalse( $response->get_data()['success'] );
	}
}
