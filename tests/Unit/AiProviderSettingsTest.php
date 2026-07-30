<?php
/**
 * AI provider runtime setting tests.
 *
 * @package Citeoryx\Tests\Unit
 */

namespace Citeoryx\Tests\Unit;

use Citeoryx\Core\Container;
use Citeoryx\Infrastructure\Encryption\KeyStore;
use Citeoryx\Integrations\AiProviders\AiProviderFactory;
use Citeoryx\Integrations\AiProviders\NullAiProvider;
use Citeoryx\Rest\Controllers\AiController;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Covers enabled state and provider request timeouts.
 */
class AiProviderSettingsTest extends WP_UnitTestCase {

	/**
	 * Remove provider state created by a test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		delete_option( AiProviderFactory::OPTION_PROVIDER );
		delete_option( AiProviderFactory::OPTION_SETTINGS );
		delete_option( AiProviderFactory::OPTION_ENABLED );
		delete_option( AiProviderFactory::OPTION_TIMEOUT );
		( new KeyStore() )->delete( 'openai_compatible_api_key' );
		parent::tearDown();
	}

	/**
	 * Saved runtime settings must appear in status and reach HTTP requests.
	 *
	 * @return void
	 */
	public function test_enabled_timeout_is_saved_and_used(): void {
		$controller = new AiController( new Container() );
		$response   = $controller->save_settings(
			$this->request(
				array(
					'provider' => 'openai_compatible',
					'enabled'  => true,
					'timeout'  => 95,
					'api_key'  => 'runtime-setting-key',
					'model'    => 'runtime-model',
					'base_url' => 'https://example.com/v1/chat/completions',
				)
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$status = $controller->get_status()->get_data()['data'];
		$this->assertTrue( $status['enabled'] );
		$this->assertSame( 95, $status['timeout'] );

		$request_timeout = 0;
		$filter          = static function ( $preempt, $args ) use ( &$request_timeout ) {
			unset( $preempt );
			$request_timeout = $args['timeout'];
			return array(
				'headers'  => array(),
				'body'     => wp_json_encode(
					array(
						'choices' => array(
							array( 'message' => array( 'content' => 'OK' ) ),
						),
					)
				),
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'cookies'  => array(),
				'filename' => null,
			);
		};
		add_filter( 'pre_http_request', $filter, 10, 2 );

		try {
			$result = $controller->validate_connection()->get_data()['data'];
		} finally {
			remove_filter( 'pre_http_request', $filter, 10 );
		}

		$this->assertTrue( $result['valid'] );
		$this->assertSame( 95, $request_timeout );
	}

	/**
	 * Disabling analysis must retain the selected provider configuration.
	 *
	 * @return void
	 */
	public function test_disabled_provider_is_retained_but_not_used_for_analysis(): void {
		$controller = new AiController( new Container() );
		$controller->save_settings(
			$this->request(
				array(
					'provider' => 'openai_compatible',
					'enabled'  => true,
					'timeout'  => 60,
					'api_key'  => 'retained-key',
					'model'    => 'retained-model',
					'base_url' => 'https://example.com/custom',
				)
			)
		);
		$response = $controller->save_settings(
			$this->request(
				array(
					'provider' => 'openai_compatible',
					'enabled'  => false,
					'timeout'  => 120,
					'api_key'  => '',
					'model'    => 'retained-model',
					'base_url' => 'https://example.com/custom',
				)
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( AiProviderFactory::is_enabled() );
		$this->assertInstanceOf( NullAiProvider::class, ( new AiProviderFactory() )->make() );
		$this->assertSame( 'retained-model', AiProviderFactory::get_provider_settings( 'openai_compatible' )['model'] );
		$this->assertTrue( ( new KeyStore() )->get( 'openai_compatible_api_key' ) === 'retained-key' );
	}

	/**
	 * Runtime timeout validation must also protect direct controller calls.
	 *
	 * @return void
	 */
	public function test_timeout_outside_bounds_is_rejected(): void {
		$response = ( new AiController( new Container() ) )->save_settings(
			$this->request(
				array(
					'provider' => 'none',
					'enabled'  => false,
					'timeout'  => 181,
				)
			)
		);

		$this->assertSame( 400, $response->get_status() );
	}

	/**
	 * Legacy clients that omit enabled must still activate a selected provider.
	 *
	 * @return void
	 */
	public function test_legacy_save_enables_selected_provider(): void {
		$response = ( new AiController( new Container() ) )->save_settings(
			$this->request(
				array(
					'provider' => 'openai_compatible',
					'api_key'  => 'legacy-key',
					'model'    => 'legacy-model',
					'base_url' => 'https://example.com/legacy',
				)
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( AiProviderFactory::is_enabled() );
	}

	/**
	 * Build a REST request fixture.
	 *
	 * @param array<string, mixed> $data Request data.
	 * @return WP_REST_Request
	 */
	private function request( array $data ): WP_REST_Request {
		$request = new WP_REST_Request( 'POST', '/citeoryx/v1/integrations/ai/settings' );
		foreach ( $data as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return $request;
	}
}
