<?php
/**
 * HTTP client tests.
 *
 * @package Citeoryx\Tests\Unit
 */

namespace Citeoryx\Tests\Unit;

use Citeoryx\Infrastructure\Http\HttpClient;
use WP_UnitTestCase;

/**
 * Tests for safe external URL handling.
 */
class HttpClientTest extends WP_UnitTestCase {

	public function test_public_domain_is_allowed(): void {
		add_filter(
			'pre_http_request',
			static fn () => array(
				'headers'  => array(),
				'body'     => 'ok',
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'cookies'  => array(),
				'filename' => null,
			)
		);

		$response = ( new HttpClient() )->get( 'https://example.com/' );
		$this->assertTrue( $response['success'] );
	}

	public function test_private_ip_is_rejected(): void {
		$response = ( new HttpClient() )->get( 'http://127.0.0.1/' );
		$this->assertFalse( $response['success'] );
		$this->assertSame( 0, $response['code'] );
	}
}
