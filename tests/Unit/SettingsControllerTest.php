<?php
/**
 * Settings REST controller tests.
 *
 * @package Citeoryx\Tests\Unit
 */

namespace Citeoryx\Tests\Unit;

use Citeoryx\Core\Container;
use Citeoryx\Rest\Controllers\SettingsController;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Tests for the settings response and site profile contract.
 */
class SettingsControllerTest extends WP_UnitTestCase {

	private SettingsController $controller;

	/**
	 * Set up the controller.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->controller = new SettingsController( new Container() );
	}

	/**
	 * Remove options written by a test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		delete_option( 'citeoryx_settings' );
		delete_option( 'citeoryx_site_profile' );
		delete_option( 'citeoryx_remove_data_on_uninstall' );
		delete_option( 'citeoryx_notification_status' );
		parent::tearDown();
	}

	/**
	 * An empty stored profile should expose usable options without being complete.
	 *
	 * @return void
	 */
	public function test_get_settings_exposes_profile_options(): void {
		$response = $this->controller->get_settings();
		$data     = $response->get_data()['data'];
		$types    = array_column( $data['profile_options']['content_types'], 'value' );

		$this->assertFalse( $data['profile_complete'] );
		$this->assertContains( 'post', $types );
		$this->assertContains( 'page', $types );
		$this->assertNotContains( 'attachment', $types );
		$this->assertFalse( $data['settings']['weekly_digest_enabled'] );
		$this->assertSame( 'never', $data['notification_status']['status'] );
	}

	/**
	 * An optional notification failure must not block the first settings step.
	 *
	 * @return void
	 */
	public function test_get_settings_survives_notification_service_failure(): void {
		$container  = new class() extends Container {
			public function get( string $class ): object {
				throw new \RuntimeException( 'Notification service unavailable.' );
			}
		};
		$controller = new SettingsController( $container );

		$response = $controller->get_settings();
		$data     = $response->get_data()['data'];

		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( $data['profile_complete'] );
		$this->assertSame( 'never', $data['notification_status']['status'] );
	}

	/**
	 * A complete profile should be saved and returned through the same contract.
	 *
	 * @return void
	 */
	public function test_update_settings_saves_complete_profile(): void {
		$response = $this->controller->update_settings(
			$this->request(
				$this->valid_profile(),
				array(
					'auto_scan'                => true,
					'remove_data_on_uninstall' => false,
				)
			)
		);
		$data     = $response->get_data()['data'];

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $data['profile_complete'] );
		$this->assertSame( array( 'post', 'page' ), $data['profile']['core_content_types'] );
		$this->assertSame( $data['profile'], get_option( 'citeoryx_site_profile' ) );
	}

	/**
	 * Invalid content type values must not overwrite an existing profile.
	 *
	 * @return void
	 */
	public function test_update_settings_rejects_invalid_content_type(): void {
		$existing = array( 'site_type' => 'corporate' );
		update_option( 'citeoryx_site_profile', $existing );
		$profile                       = $this->valid_profile();
		$profile['core_content_types'] = array( 'not-a-real-post-type' );

		$response = $this->controller->update_settings(
			$this->request( $profile, array( 'auto_scan' => true ) )
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( $existing, get_option( 'citeoryx_site_profile' ) );
	}

	/**
	 * String booleans must not be silently cast to the opposite value.
	 *
	 * @return void
	 */
	public function test_update_settings_rejects_string_boolean(): void {
		$response = $this->controller->update_settings(
			$this->request( $this->valid_profile(), array( 'auto_scan' => 'false' ) )
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertFalse( get_option( 'citeoryx_site_profile', false ) );
	}

	public function test_update_settings_rejects_invalid_notification_email(): void {
		$response = $this->controller->update_settings(
			$this->request(
				$this->valid_profile(),
				array(
					'weekly_digest_enabled' => true,
					'notification_email'    => 'not-an-email',
				)
			)
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertFalse( get_option( 'citeoryx_site_profile', false ) );
	}

	/**
	 * Build a complete profile request fixture.
	 *
	 * @return array<string, mixed>
	 */
	private function valid_profile(): array {
		return array(
			'site_type'          => 'blog',
			'primary_goal'       => 'traffic',
			'core_content_types' => array( 'post', 'page' ),
			'main_language'      => 'zh_CN',
			'main_region'        => '全球',
			'update_rhythm'      => 'monthly',
			'risk_level'         => 'standard',
			'review_cycle_days'  => 90,
		);
	}

	/**
	 * Build a JSON REST request.
	 *
	 * @param array<string, mixed> $profile Profile payload.
	 * @param array<string, mixed> $settings Settings payload.
	 * @return WP_REST_Request
	 */
	private function request( array $profile, array $settings ): WP_REST_Request {
		$request = new WP_REST_Request( 'POST', '/citeoryx/v1/settings' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( compact( 'settings', 'profile' ) ) );
		return $request;
	}
}
