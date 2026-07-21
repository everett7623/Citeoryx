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
		$this->stub_http_response( 403, '{"error":"forbidden"}' );
		$adapter = new GoogleSearchConsole( $this->connected_google_oauth() );

		$result = $adapter->validate_connection();

		$this->assertFalse( $result['valid'] );
		$this->assertSame( 'error', $result['status'] );
		$this->assertStringContainsString( 'HTTP 403', $result['message'] );
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
	 * Build an OAuth test double with a usable token.
	 *
	 * @return GoogleOAuth
	 */
	private function connected_google_oauth(): GoogleOAuth {
		return new class() extends GoogleOAuth {
			public function is_connected(): bool {
				return true;
			}

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
			static fn () => array(
				'headers'  => array(),
				'body'     => $body,
				'response' => array(
					'code'    => $code,
					'message' => 200 === $code ? 'OK' : 'Error',
				),
				'cookies'  => array(),
				'filename' => null,
			)
		);
	}
}
