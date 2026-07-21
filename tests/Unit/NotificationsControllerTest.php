<?php
/**
 * Notifications REST controller tests.
 *
 * @package Citeoryx\Tests\Unit
 */

namespace Citeoryx\Tests\Unit;

use Citeoryx\Core\Container;
use Citeoryx\Rest\Controllers\NotificationsController;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Tests the test-email request contract.
 */
class NotificationsControllerTest extends WP_UnitTestCase {

	private array $messages = array();

	public function setUp(): void {
		parent::setUp();
		add_filter( 'pre_wp_mail', array( $this, 'capture_mail' ), 10, 2 );
	}

	public function tearDown(): void {
		remove_filter( 'pre_wp_mail', array( $this, 'capture_mail' ), 10 );
		delete_option( 'citeoryx_notification_status' );
		parent::tearDown();
	}

	public function test_rejects_invalid_recipient(): void {
		$response = $this->controller()->send_test( $this->request( 'not-an-email' ) );

		$this->assertSame( 400, $response->get_status() );
		$this->assertCount( 0, $this->messages );
	}

	public function test_returns_stable_success_data(): void {
		$response = $this->controller()->send_test( $this->request( 'owner@example.com' ) );
		$data     = $response->get_data()['data'];

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'sent', $data['status'] );
		$this->assertSame( 'owner@example.com', $data['recipient'] );
		$this->assertIsString( $data['attempted_at'] );
		$this->assertCount( 1, $this->messages );
	}

	public function test_notification_route_is_registered(): void {
		do_action( 'rest_api_init' );

		$this->assertArrayHasKey( '/citeoryx/v1/notifications/test', rest_get_server()->get_routes() );
	}

	private function controller(): NotificationsController {
		return new NotificationsController( new Container() );
	}

	private function request( string $email ): WP_REST_Request {
		$request = new WP_REST_Request( 'POST', '/citeoryx/v1/notifications/test' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'email' => $email ) ) );
		return $request;
	}

	/**
	 * Capture email attributes without calling a mail transport.
	 *
	 * @param mixed                $return Short-circuit value.
	 * @param array<string, mixed> $attributes Mail attributes.
	 * @return bool
	 */
	public function capture_mail( $return, array $attributes ): bool {
		$this->messages[] = $attributes;
		return true;
	}
}
