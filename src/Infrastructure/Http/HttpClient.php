<?php
/**
 * Safe HTTP client.
 *
 * @package Citeoryx\Infrastructure\Http
 */

namespace Citeoryx\Infrastructure\Http;

/**
 * HTTP client with SSRF protection.
 */
class HttpClient {

	/**
	 * Default request args.
	 *
	 * @var array<string, mixed>
	 */
	private array $default_args = array(
		'timeout'             => 30,
		'redirects'           => 3,
		'stream'              => false,
		'limit_response_size' => 5 * MB_IN_BYTES,
	);

	/**
	 * GET request.
	 *
	 * @param string               $url URL.
	 * @param array<string, mixed> $args Args.
	 * @return array{success: bool, body: string, code: int, error: string}
	 */
	public function get( string $url, array $args = array() ): array {
		if ( ! $this->is_safe_url( $url ) ) {
			return array(
				'success' => false,
				'body'    => '',
				'code'    => 0,
				'error'   => __( 'URL is not safe to request.', 'citeoryx' ),
			);
		}

		$args     = array_merge( $this->default_args, $args );
		$response = wp_safe_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'body'    => '',
				'code'    => 0,
				'error'   => $response->get_error_message(),
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		return array(
			'success' => $code >= 200 && $code < 300,
			'body'    => $body,
			'code'    => (int) $code,
			'error'   => '',
		);
	}

	/**
	 * HEAD request.
	 *
	 * @param string               $url URL.
	 * @param array<string, mixed> $args Args.
	 * @return array{success: bool, body: string, code: int, error: string}
	 */
	public function head( string $url, array $args = array() ): array {
		if ( ! $this->is_safe_url( $url ) ) {
			return array(
				'success' => false,
				'body'    => '',
				'code'    => 0,
				'error'   => __( 'URL is not safe to request.', 'citeoryx' ),
			);
		}

		$args     = array_merge( $this->default_args, $args );
		$response = wp_safe_remote_head( $url, $args );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'body'    => '',
				'code'    => 0,
				'error'   => $response->get_error_message(),
			);
		}

		$code = wp_remote_retrieve_response_code( $response );

		return array(
			'success' => $code >= 200 && $code < 300,
			'body'    => '',
			'code'    => (int) $code,
			'error'   => '',
		);
	}

	/**
	 * Check if URL is safe.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	private function is_safe_url( string $url ): bool {
		if ( function_exists( 'wp_http_validate_url' ) && false === wp_http_validate_url( $url ) ) {
			return false;
		}

		$parsed = wp_parse_url( $url );
		if ( ! $parsed || ! isset( $parsed['host'] ) || ! in_array( $parsed['scheme'] ?? '', array( 'http', 'https' ), true ) ) {
			return false;
		}

		$host = strtolower( trim( $parsed['host'], '[]' ) );
		if ( in_array( $host, array( 'localhost', '0.0.0.0', '169.254.169.254' ), true ) ) {
			return false;
		}

		// Domain names are valid; private and reserved literal IPs are not.
		if ( filter_var( $host, FILTER_VALIDATE_IP ) !== false && false === filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
			return false;
		}

		return true;
	}
}
