<?php
/**
 * OpenAI Responses gateway compatibility tests.
 *
 * @package Citeoryx\Tests\Unit
 */

namespace Citeoryx\Tests\Unit;

use Citeoryx\Integrations\AiProviders\OpenAiCompatibleProvider;
use Citeoryx\Integrations\AiProviders\OpenAiResponsesProvider;
use WP_UnitTestCase;

/**
 * Covers Responses envelopes, SSE fallbacks, and safe diagnostics.
 */
class OpenAiResponsesCompatibilityTest extends WP_UnitTestCase {

	/**
	 * Reset shared compatible-provider credentials and HTTP filters.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		remove_all_filters( 'pre_http_request' );
		OpenAiCompatibleProvider::delete_api_key();
		OpenAiResponsesProvider::save_api_key( 'responses-test-key' );
	}

	/**
	 * Clean up shared state.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_all_filters( 'pre_http_request' );
		OpenAiCompatibleProvider::delete_api_key();
		parent::tear_down();
	}

	/**
	 * Some gateways wrap the final response in a response.completed event.
	 *
	 * @return void
	 */
	public function test_completed_event_envelope_is_accepted(): void {
		$this->mock_http_response(
			wp_json_encode(
				array(
					'type'     => 'response.completed',
					'response' => $this->responses_payload( 'OK' ),
				)
			)
		);

		$result = $this->provider()->test_connection();

		$this->assertTrue( $result['valid'] );
	}

	/**
	 * A third-party gateway may return SSE despite a non-streaming request.
	 *
	 * @return void
	 */
	public function test_sse_text_deltas_are_accepted(): void {
		$this->mock_http_response(
			"event: response.output_text.delta\n"
			. 'data: {"type":"response.output_text.delta","delta":"O"}' . "\n\n"
			. 'data: {"type":"response.output_text.delta","delta":"K"}' . "\n\n"
			. 'data: [DONE]' . "\n",
			'text/event-stream'
		);

		$result = $this->provider()->test_connection();

		$this->assertTrue( $result['valid'] );
	}

	/**
	 * A dashboard root must not look like an API credential failure.
	 *
	 * @return void
	 */
	public function test_html_control_panel_response_has_specific_error(): void {
		$this->mock_http_response(
			'<!doctype html><html><body>private dashboard content</body></html>',
			'text/html; charset=utf-8'
		);

		$result = ( new OpenAiCompatibleProvider( 'https://sub2.uukk.de', 'gpt-5.6-sol' ) )->test_connection();

		$this->assertFalse( $result['valid'] );
		$this->assertStringContainsString( 'HTML', $result['message'] );
		$this->assertStringContainsString( '控制面板', $result['message'] );
		$this->assertStringNotContainsString( 'private dashboard content', $result['message'] );
	}

	/**
	 * Successful HTTP responses with no model text need a protocol diagnosis.
	 *
	 * @return void
	 */
	public function test_empty_responses_output_has_protocol_error(): void {
		$this->mock_http_response( '{"id":"resp_empty","output":[]}' );

		$result = $this->provider()->test_connection();

		$this->assertFalse( $result['valid'] );
		$this->assertStringContainsString( 'Responses 协议', $result['message'] );
	}

	/**
	 * Known JSON error fields may be shown without exposing arbitrary bodies.
	 *
	 * @return void
	 */
	public function test_http_error_includes_safe_provider_message(): void {
		$this->mock_http_response(
			'{"error":{"message":"Invalid API key"},"debug":"private trace"}',
			'application/json',
			401
		);

		$result = $this->provider()->test_connection();

		$this->assertFalse( $result['valid'] );
		$this->assertStringContainsString( 'HTTP 401: Invalid API key', $result['message'] );
		$this->assertStringNotContainsString( 'private trace', $result['message'] );
	}

	/**
	 * @return OpenAiResponsesProvider
	 */
	private function provider(): OpenAiResponsesProvider {
		return new OpenAiResponsesProvider( 'https://sub2.uukk.de', 'gpt-5.6-sol' );
	}

	/**
	 * @param string $text Model output text.
	 * @return array<string, mixed>
	 */
	private function responses_payload( string $text ): array {
		return array(
			'output' => array(
				array(
					'type'    => 'message',
					'content' => array(
						array(
							'type' => 'output_text',
							'text' => $text,
						),
					),
				),
			),
		);
	}

	/**
	 * @param string $body         Response body.
	 * @param string $content_type Response content type.
	 * @param int    $status       HTTP status.
	 * @return void
	 */
	private function mock_http_response(
		string $body,
		string $content_type = 'application/json',
		int $status = 200
	): void {
		add_filter(
			'pre_http_request',
			static function () use ( $body, $content_type, $status ) {
				return array(
					'headers'  => array( 'content-type' => $content_type ),
					'body'     => $body,
					'response' => array(
						'code'    => $status,
						'message' => 200 === $status ? 'OK' : 'Error',
					),
					'cookies'  => array(),
					'filename' => null,
				);
			}
		);
	}
}
