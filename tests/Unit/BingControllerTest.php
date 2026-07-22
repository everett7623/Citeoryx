<?php
/**
 * Bing REST controller tests.
 *
 * @package Citeoryx\Tests\Unit
 */

namespace Citeoryx\Tests\Unit;

use Citeoryx\Application\Search\SearchIntegrationHealth;
use Citeoryx\Core\Container;
use Citeoryx\Integrations\SearchConsole\BingWebmasterTools;
use Citeoryx\Rest\Controllers\BingController;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Tests the Bing configuration response contract.
 */
class BingControllerTest extends WP_UnitTestCase {

	/**
	 * Clear stored Bing fixtures.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		BingWebmasterTools::delete_api_key();
		delete_option( SearchIntegrationHealth::OPTION );
		parent::tearDown();
	}

	/**
	 * Disconnected status still exposes the complete health shape.
	 *
	 * @return void
	 */
	public function test_status_returns_stable_health_contract(): void {
		$response = ( new BingController( new Container() ) )->get_status();
		$data     = $response->get_data()['data'];

		$this->assertFalse( $data['connected'] );
		$this->assertSame( 'unknown', $data['health']['status'] );
		$this->assertArrayHasKey( 'consecutive_failures', $data['health'] );
	}

	/**
	 * Empty secrets must not be persisted as a configured integration.
	 *
	 * @return void
	 */
	public function test_empty_api_key_is_rejected_without_changing_state(): void {
		$request = new WP_REST_Request( 'POST', '/citeoryx/v1/integrations/bing/settings' );
		$request->set_param( 'api_key', '' );

		$response = ( new BingController( new Container() ) )->save_settings( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertFalse( $response->get_data()['success'] );
		$this->assertFalse( ( new BingWebmasterTools() )->is_connected() );
	}
}
