<?php
/**
 * Search Console adapter contract tests.
 *
 * @package Citeoryx\Tests\Unit
 */

namespace Citeoryx\Tests\Unit;

use Citeoryx\Integrations\SearchConsole\BingWebmasterTools;
use Citeoryx\Integrations\SearchConsole\GoogleOAuth;
use Citeoryx\Integrations\SearchConsole\GoogleSearchConsole;
use Citeoryx\Infrastructure\Http\RetryPolicy;
use WP_UnitTestCase;

/**
 * Tests connection validation without making external requests.
 */
class SearchConsoleAdapterTest extends WP_UnitTestCase {

	/**
	 * Remove HTTP stubs and encrypted fixtures.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		remove_all_filters( 'pre_http_request' );
		BingWebmasterTools::delete_api_key();
		parent::tearDown();
	}

	/**
	 * A valid empty site list is a healthy connection, not a request error.
	 *
	 * @return void
	 */
	public function test_google_validation_accepts_empty_site_list(): void {
		$this->stub_http_response( 200, '{"siteEntry":[]}' );
		$adapter = new GoogleSearchConsole( $this->connected_google_oauth() );

		$result = $adapter->validate_connection();

		$this->assertTrue( $result['valid'] );
		$this->assertSame( 'healthy', $result['status'] );
		$this->assertSame( 0, $result['site_count'] );
		$this->assertNull( $adapter->get_last_error() );
	}

	/**
	 * HTTP failures must remain distinguishable from valid empty data.
	 *
	 * @return void
	 */
	public function test_google_validation_reports_http_failure(): void {
		$calls  = 0;
		$delays = array();
		add_filter(
			'pre_http_request',
			function () use ( &$calls ): array {
				++$calls;
				return $this->http_response( 403, '{"error":"forbidden"}' );
			}
		);
		$policy  = new RetryPolicy(
			3,
			static function ( int $delay ) use ( &$delays ): void {
				$delays[] = $delay;
			}
		);
		$adapter = new GoogleSearchConsole( $this->connected_google_oauth(), $policy );

		$result = $adapter->validate_connection();

		$this->assertFalse( $result['valid'] );
		$this->assertSame( 'error', $result['status'] );
		$this->assertStringContainsString( 'HTTP 403', $result['message'] );
		$this->assertSame( 1, $calls );
		$this->assertSame( array(), $delays );
	}

	/**
	 * A rate limit should honor the bounded Retry-After delay and recover.
	 *
	 * @return void
	 */
	public function test_google_retries_rate_limit_with_bounded_retry_after(): void {
		$calls  = 0;
		$delays = array();
		add_filter(
			'pre_http_request',
			function () use ( &$calls ): array {
				++$calls;
				return 1 === $calls
					? $this->http_response( 429, '{"error":"rate limited"}', array( 'retry-after' => '10' ) )
					: $this->http_response( 200, '{"siteEntry":[]}' );
			}
		);
		$policy  = new RetryPolicy(
			3,
			static function ( int $delay ) use ( &$delays ): void {
				$delays[] = $delay;
			}
		);
		$adapter = new GoogleSearchConsole( $this->connected_google_oauth(), $policy );

		$result = $adapter->validate_connection();

		$this->assertTrue( $result['valid'] );
		$this->assertSame( 2, $calls );
		$this->assertSame( array( 2000 ), $delays );
	}

	/**
	 * Google dimension keys must map to stable row fields in request order.
	 *
	 * @return void
	 */
	public function test_google_maps_query_country_and_device_dimensions(): void {
		$this->stub_http_response(
			200,
			'{"rows":[{"keys":["example query","usa","mobile"],"clicks":4,"impressions":40,"ctr":0.1,"position":3.5}]}'
		);
		$adapter = new GoogleSearchConsole( $this->connected_google_oauth() );

		$rows = $adapter->get_queries_for_url(
			home_url( '/example' ),
			'2026-07-20',
			'2026-07-20',
			array( 'dimensions' => array( 'query', 'country', 'device' ) )
		);

		$this->assertCount( 1, $rows );
		$this->assertSame( 'example query', $rows[0]['query'] );
		$this->assertSame( 'usa', $rows[0]['country'] );
		$this->assertSame( 'mobile', $rows[0]['device'] );
		$this->assertSame( 10.0, $rows[0]['ctr'] );
	}

	/**
	 * A malformed successful response must be reported as a provider error.
	 *
	 * @return void
	 */
	public function test_bing_validation_reports_invalid_json(): void {
		BingWebmasterTools::save_api_key( 'test-api-key' );
		$this->stub_http_response( 200, 'not-json' );
		$adapter = new BingWebmasterTools();

		$result = $adapter->validate_connection();

		$this->assertFalse( $result['valid'] );
		$this->assertSame( 'error', $result['status'] );
		$this->assertStringContainsString( 'invalid JSON', $result['message'] );
	}

	/**
	 * Network and server failures should use exponential backoff before recovery.
	 *
	 * @return void
	 */
	public function test_bing_retries_network_and_server_failures(): void {

		BingWebmasterTools::save_api_key( 'test-api-key' );
		$calls     = 0;
		$delays    = array();
		$responses = array(
			new \WP_Error( 'http_request_failed', 'Temporary network error.' ),
			$this->http_response( 503, '{"error":"unavailable"}' ),
			$this->http_response( 200, '{"d":[]}' ),
		);
		add_filter(
			'pre_http_request',
			static function () use ( &$calls, &$responses ) {
				++$calls;
				return array_shift( $responses );
			}
		);
		$policy  = new RetryPolicy(
			3,
			static function ( int $delay ) use ( &$delays ): void {
				$delays[] = $delay;
			}
		);
		$adapter = new BingWebmasterTools( $policy );

		$result = $adapter->validate_connection();

		$this->assertTrue( $result['valid'] );
		$this->assertSame( 3, $calls );
		$this->assertSame( array( 250, 500 ), $delays );
	}

	/**
	 * Bing page query statistics must follow the official endpoint and response shape.
	 *
	 * @return void
	 */
	public function test_bing_maps_official_page_query_response(): void {

		BingWebmasterTools::save_api_key( 'test-api-key' );
		$request_url = '';
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( &$request_url ): array {

				$request_url = $url;
				return $this->http_response(
					200,
					'{"d":[{"Query":"example query","Clicks":4,"Impressions":40,"AvgImpressionPosition":3.5,"Date":"/Date(1784505600000)/"}]}'
				);
			},
			10,
			3
		);
		$adapter = new BingWebmasterTools();
		$rows    = $adapter->get_queries_for_url( home_url( '/example' ), '2026-07-01', '2026-07-31' );

		$this->assertStringContainsString( '/GetPageQueryStats?', $request_url );
		$this->assertStringContainsString( 'page=', $request_url );
		$this->assertSame( 'example query', $rows[0]['query'] );
		$this->assertSame( 10.0, $rows[0]['ctr'] );
		$this->assertSame( 3.5, $rows[0]['position'] );
		$this->assertSame( '2026-07-20', $rows[0]['metric_date'] );
	}
	/**
	 * Build an OAuth test double with a usable token.
	 *
	 * @return GoogleOAuth
	 */
	private function connected_google_oauth(): GoogleOAuth {
		return new class() extends GoogleOAuth {
			/** @return bool */
			public function is_connected(): bool {
				return true;
			}

			/** @return string|null */
			public function get_access_token(): ?string {
				return 'test-access-token';
			}
		};
	}

	/**
	 * Stub the next WordPress HTTP request.
	 *
	 * @param int    $code HTTP response code.
	 * @param string $body Response body.
	 * @return void
	 */
	private function stub_http_response( int $code, string $body ): void {
		add_filter(
			'pre_http_request',
			fn () => $this->http_response( $code, $body )
		);
	}

	/**
	 * Build a WordPress HTTP response fixture.
	 *
	 * @param int                  $code HTTP response code.
	 * @param string               $body Response body.
	 * @param array<string,string> $headers Response headers.
	 * @return array<string, mixed>
	 */
	private function http_response( int $code, string $body, array $headers = array() ): array {
		return array(
			'headers'  => $headers,
			'body'     => $body,
			'response' => array(
				'code'    => $code,
				'message' => 200 === $code ? 'OK' : 'Error',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}
}
