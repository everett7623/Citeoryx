<?php
/**
 * AI provider configuration and protocol tests.
 *
 * @package Citeoryx\Tests\Unit
 */

namespace Citeoryx\Tests\Unit;

use Citeoryx\Core\Container;
use Citeoryx\Infrastructure\Encryption\KeyStore;
use Citeoryx\Integrations\AiProviders\AiProviderFactory;
use Citeoryx\Integrations\AiProviders\AnthropicCompatibleProvider;
use Citeoryx\Integrations\AiProviders\AnthropicProvider;
use Citeoryx\Integrations\AiProviders\OpenAiCompatibleProvider;
use Citeoryx\Integrations\AiProviders\OpenAiResponsesProvider;
use Citeoryx\Rest\Controllers\AiController;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Covers encrypted configuration and provider protocol mapping.
 */
class AiProviderTest extends WP_UnitTestCase {

	/**
	 * Remove provider state created by a test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		delete_option( AiProviderFactory::OPTION_PROVIDER );
		delete_option( AiProviderFactory::OPTION_SETTINGS );

		$key_store = new KeyStore();
		foreach (
			array(
				'openai_api_key',
				'anthropic_api_key',
				'deepseek_api_key',
				'openai_compatible_api_key',
				'anthropic_compatible_api_key',
			) as $key_name
		) {
			$key_store->delete( $key_name );
		}

		parent::tearDown();
	}

	/**
	 * Anthropic credentials must be stored separately and never returned.
	 *
	 * @return void
	 */
	public function test_save_anthropic_provider_uses_encrypted_key_storage(): void {
		$response = ( new AiController( new Container() ) )->save_settings(
			$this->request(
				array(
					'provider' => 'anthropic',
					'api_key'  => 'anthropic-test-key',
					'model'    => 'claude-haiku-4-5-20251001',
				)
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'anthropic', get_option( AiProviderFactory::OPTION_PROVIDER ) );
		$this->assertTrue( ( new AnthropicProvider() )->is_configured() );
		$this->assertSame(
			'claude-haiku-4-5-20251001',
			AiProviderFactory::get_provider_settings( 'anthropic' )['model']
		);

		$status = ( new AiController( new Container() ) )->get_status()->get_data()['data'];
		$this->assertTrue( $status['has_anthropic_key'] );
		$this->assertSame( 'claude-haiku-4-5-20251001', $status['settings']['model'] );
		$this->assertArrayNotHasKey( 'api_key', $status['provider_settings']['anthropic'] );
	}

	/**
	 * Compatible providers require HTTPS base URLs before changing the active provider.
	 *
	 * @return void
	 */
	public function test_insecure_compatible_endpoint_is_rejected(): void {
		update_option( AiProviderFactory::OPTION_PROVIDER, 'openai' );

		$response = ( new AiController( new Container() ) )->save_settings(
			$this->request(
				array(
					'provider' => 'openai_compatible',
					'api_key'  => 'test-key',
					'model'    => 'third-party-model',
					'base_url' => 'http://api.example.com/v1',
				)
			)
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'openai', get_option( AiProviderFactory::OPTION_PROVIDER ) );
	}

	/**
	 * OpenAI and Anthropic compatibility modes must use their native request shapes.
	 *
	 * @return void
	 */
	public function test_compatible_providers_send_native_protocol_requests(): void {
		OpenAiCompatibleProvider::save_api_key( 'openai-compatible-key' );
		AnthropicCompatibleProvider::save_api_key( 'anthropic-compatible-key' );
		$requests = array();
		$filter   = static function ( $preempt, $args, $url ) use ( &$requests ) {
			$requests[] = compact( 'args', 'url' );
			$body       = str_contains( $url, 'chat/completions' )
				? array(
					'choices' => array(
						array(
							'message' => array( 'content' => '{"suggestions":[]}' ),
						),
					),
				)
				: array(
					'content' => array(
						array(
							'type' => 'text',
							'text' => '{"suggestions":[]}',
						),
					),
				);

			return array(
				'headers'  => array(),
				'body'     => wp_json_encode( $body ),
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'cookies'  => array(),
				'filename' => null,
			);
		};
		add_filter( 'pre_http_request', $filter, 10, 3 );

		try {
			$openai    = new OpenAiCompatibleProvider(
				'https://example.com/v1/chat/completions',
				'third-party-openai-model'
			);
			$anthropic = new AnthropicCompatibleProvider(
				'https://example.com/v1/messages',
				'third-party-anthropic-model'
			);

			$this->assertSame( array(), $openai->suggest_improvements( 'Example content' )['suggestions'] );
			$this->assertSame( array(), $anthropic->suggest_improvements( 'Example content' )['suggestions'] );
		} finally {
			remove_filter( 'pre_http_request', $filter, 10 );
		}

		$openai_request    = json_decode( $requests[0]['args']['body'], true );
		$anthropic_request = json_decode( $requests[1]['args']['body'], true );

		$this->assertSame( 'Bearer openai-compatible-key', $requests[0]['args']['headers']['Authorization'] );
		$this->assertSame( 'third-party-openai-model', $openai_request['model'] );
		$this->assertSame( 'user', $openai_request['messages'][0]['role'] );
		$this->assertSame( 'anthropic-compatible-key', $requests[1]['args']['headers']['x-api-key'] );
		$this->assertSame( '2023-06-01', $requests[1]['args']['headers']['anthropic-version'] );
		$this->assertSame( 'third-party-anthropic-model', $anthropic_request['model'] );
		$this->assertSame( 'user', $anthropic_request['messages'][0]['role'] );
	}

	/**
	 * Connection validation must make a real request using the saved provider.
	 *
	 * @return void
	 */
	public function test_validate_connection_uses_saved_provider(): void {
		$endpoint   = 'https://example.com/custom-openai-endpoint?route=sub2api';
		$controller = new AiController( new Container() );
		$save       = $controller->save_settings(
			$this->request(
				array(
					'provider' => 'openai_compatible',
					'api_key'  => 'connection-test-key',
					'model'    => 'third-party-model',
					'base_url' => $endpoint,
				)
			)
		);
		$this->assertSame( 200, $save->get_status() );

		$requested_url = '';
		$filter        = static function ( $preempt, $args, $url ) use ( &$requested_url ) {
			$requested_url = $url;
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
		add_filter( 'pre_http_request', $filter, 10, 3 );

		try {
			$response = $controller->validate_connection();
		} finally {
			remove_filter( 'pre_http_request', $filter, 10 );
		}

		$result = $response->get_data()['data'];
		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $result['valid'] );
		$this->assertNotSame( '', $result['message'] );
		$this->assertSame( $endpoint, $requested_url );
	}

	/**
	 * Responses mode must use the service root with the Responses request shape.
	 *
	 * @return void
	 */
	public function test_responses_provider_uses_responses_protocol(): void {
		OpenAiResponsesProvider::save_api_key( 'responses-test-key' );
		$requests = array();
		$filter   = static function ( $preempt, $args, $url ) use ( &$requests ) {
			unset( $preempt );
			$requests[] = array(
				'args' => $args,
				'url'  => $url,
			);
			return array(
				'headers'  => array(),
				'body'     => wp_json_encode(
					array(
						'output' => array(
							array(
								'type'    => 'message',
								'content' => array(
									array(
										'type' => 'output_text',
										'text' => 'OK',
									),
								),
							),
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
		add_filter( 'pre_http_request', $filter, 10, 3 );

		try {
			$result = ( new OpenAiResponsesProvider( 'https://sub2.uukk.de/', 'gpt-5.6-sol' ) )->test_connection();
		} finally {
			remove_filter( 'pre_http_request', $filter, 10 );
		}

		$request = json_decode( $requests[0]['args']['body'], true );
		$this->assertTrue( $result['valid'] );
		$this->assertSame( 'https://sub2.uukk.de/v1/responses', $requests[0]['url'] );
		$this->assertSame( 'Bearer responses-test-key', $requests[0]['args']['headers']['Authorization'] );
		$this->assertSame( 'gpt-5.6-sol', $request['model'] );
		$this->assertSame( 'input_text', $request['input'][0]['content'][0]['type'] );
		$this->assertFalse( $request['store'] );
	}

	/**
	 * The endpoint suffix must come before a service root query string.
	 *
	 * @return void
	 */
	public function test_responses_provider_preserves_service_root_query_parameters(): void {
		OpenAiResponsesProvider::save_api_key( 'responses-test-key' );
		$requested_url = '';
		$filter        = static function ( $preempt, $args, $url ) use ( &$requested_url ) {
			unset( $preempt, $args );
			$requested_url = $url;

			return array(
				'headers'  => array(),
				'body'     => wp_json_encode(
					array(
						'output_text' => 'OK',
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
		add_filter( 'pre_http_request', $filter, 10, 3 );

		try {
			$result = ( new OpenAiResponsesProvider( 'https://sub2.uukk.de/?route=responses', 'gpt-5.6-sol' ) )->test_connection();
		} finally {
			remove_filter( 'pre_http_request', $filter, 10 );
		}

		$this->assertTrue( $result['valid'] );
		$this->assertSame( 'https://sub2.uukk.de/v1/responses?route=responses', $requested_url );
	}

	/**
	 * Connection failures must expose the HTTP status without response content.
	 *
	 * @return void
	 */
	public function test_connection_reports_http_status_without_response_body(): void {
		OpenAiCompatibleProvider::save_api_key( 'error-test-key' );
		$filter = static function () {
			return array(
				'headers'  => array(),
				'body'     => '{"error":"private response body"}',
				'response' => array(
					'code'    => 404,
					'message' => 'Not Found',
				),
				'cookies'  => array(),
				'filename' => null,
			);
		};
		add_filter( 'pre_http_request', $filter );

		try {
			$result = ( new OpenAiCompatibleProvider( 'https://example.com/not-found' ) )->test_connection();
		} finally {
			remove_filter( 'pre_http_request', $filter );
		}

		$this->assertFalse( $result['valid'] );
		$this->assertStringContainsString( 'HTTP 404', $result['message'] );
		$this->assertStringNotContainsString( 'private response body', $result['message'] );
	}

	/**
	 * Build a REST request fixture.
	 *
	 * @param array<string, string> $data Request data.
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
