<?php
/**
 * Google Search Console adapter.
 *
 * @package Citeoryx\Integrations\SearchConsole
 */

namespace Citeoryx\Integrations\SearchConsole;

use Citeoryx\Infrastructure\Http\RetryPolicy;

/**
 * Fetches search performance data from the Google Search Console API.
 */
class GoogleSearchConsole implements SearchConsoleInterface {

	const API_BASE = 'https://www.googleapis.com/webmasters/v3';

	/**
	 * @var GoogleOAuth
	 */
	private GoogleOAuth $oauth;

	/**
	 * @var string
	 */
	private string $site_url;

	/**
	 * @var string|null
	 */
	private ?string $last_error = null;

	/**
	 * @var RetryPolicy
	 */
	private RetryPolicy $retry_policy;

	/**
	 * Constructor.
	 *
	 * @param GoogleOAuth     $oauth OAuth helper.
	 * @param RetryPolicy|null $retry_policy HTTP retry policy.
	 */
	public function __construct( GoogleOAuth $oauth, ?RetryPolicy $retry_policy = null ) {
		$this->oauth        = $oauth;
		$this->retry_policy = $retry_policy ?: new RetryPolicy();
		$this->site_url     = trailingslashit( get_option( 'siteurl', home_url() ) );
	}

	/**
	 * Check if connected.
	 *
	 * @return bool
	 */
	public function is_connected(): bool {
		return $this->oauth->is_connected();
	}

	/**
	 * Validate access to the Search Console sites endpoint.
	 *
	 * @return array{valid: bool, status: string, message: string, site_count: int}
	 */
	public function validate_connection(): array {
		if ( ! $this->is_connected() ) {
			return array(
				'valid'      => false,
				'status'     => 'not_configured',
				'message'    => 'Google Search Console is not connected.',
				'site_count' => 0,
			);
		}

		$sites = $this->list_sites();
		if ( $this->last_error ) {
			return array(
				'valid'      => false,
				'status'     => 'error',
				'message'    => $this->last_error,
				'site_count' => 0,
			);
		}

		return array(
			'valid'      => true,
			'status'     => 'healthy',
			'message'    => sprintf( 'Connection is healthy (%d site(s) available).', count( $sites ) ),
			'site_count' => count( $sites ),
		);
	}

	/**
	 * Get the last request error.
	 *
	 * @return string|null
	 */
	public function get_last_error(): ?string {
		return $this->last_error;
	}

	/**
	 * Get site-level metrics for a date range.
	 *
	 * @param string               $start_date YYYY-MM-DD.
	 * @param string               $end_date   YYYY-MM-DD.
	 * @param array<string, mixed> $options    Options.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_metrics( string $start_date, string $end_date, array $options = array() ): array {
		$dimensions = $options['dimensions'] ?? array( 'query' );
		$row_limit  = $options['row_limit'] ?? 25;

		$body = array(
			'startDate'  => $start_date,
			'endDate'    => $end_date,
			'dimensions' => $dimensions,
			'rowLimit'   => $row_limit,
		);

		$data = $this->request( 'POST', '/sites/' . rawurlencode( $this->site_url ) . '/searchAnalytics/query', $body );
		return $data['rows'] ?? array();
	}

	/**
	 * Get top search queries for a specific URL.
	 *
	 * @param string               $url        URL.
	 * @param string               $start_date Start date.
	 * @param string               $end_date   End date.
	 * @param array<string, mixed> $options    Query dimensions and row limit.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_queries_for_url( string $url, string $start_date, string $end_date, array $options = array() ): array {
		$requested_dimensions = array_values(
			array_filter( (array) ( $options['dimensions'] ?? array( 'query' ) ), 'is_string' )
		);
		$dimensions           = array_values(
			array_intersect(
				array( 'query', 'country', 'device' ),
				array_unique( array_merge( array( 'query' ), $requested_dimensions ) )
			)
		);
		$row_limit            = max( 1, min( 25000, absint( $options['row_limit'] ?? 20 ) ) );

		$body = array(
			'startDate'             => $start_date,
			'endDate'               => $end_date,
			'dimensions'            => $dimensions,
			'dimensionFilterGroups' => array(
				array(
					'filters' => array(
						array(
							'dimension'  => 'page',
							'operator'   => 'equals',
							'expression' => $url,
						),
					),
				),
			),
			'rowLimit'              => $row_limit,
		);

		$data = $this->request( 'POST', '/sites/' . rawurlencode( $this->site_url ) . '/searchAnalytics/query', $body );

		$rows = $data['rows'] ?? array();
		return array_map(
			static function ( array $row ) use ( $dimensions ): array {
				$mapped = array(
					'query'       => $row['keys'][0] ?? '',
					'clicks'      => $row['clicks'] ?? 0,
					'impressions' => $row['impressions'] ?? 0,
					'ctr'         => round( ( $row['ctr'] ?? 0 ) * 100, 2 ),
					'position'    => round( $row['position'] ?? 0, 1 ),
				);
				foreach ( $dimensions as $index => $dimension ) {
					if ( isset( $row['keys'][ $index ] ) ) {
						$mapped[ $dimension ] = sanitize_text_field( (string) $row['keys'][ $index ] );
					}
				}
				return $mapped;
			},
			$rows
		);
	}

	/**
	 * List verified sites in Search Console.
	 *
	 * @return array<int, string>
	 */
	public function list_sites(): array {
		$data  = $this->request( 'GET', '/sites' );
		$sites = $data['siteEntry'] ?? array();
		return array_column( $sites, 'siteUrl' );
	}

	/**
	 * Make an authenticated request to the GSC API.
	 *
	 * @param string               $method HTTP method.
	 * @param string               $path   API path.
	 * @param array<string, mixed> $body   Request body.
	 * @return array<string, mixed>
	 */
	private function request( string $method, string $path, array $body = array() ): array {
		$this->last_error = null;
		$token            = $this->oauth->get_access_token();
		if ( ! $token ) {
			$this->last_error = 'Google Search Console access token is unavailable.';
			return array();
		}

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			),
			'timeout' => 20,
		);

		if ( ! empty( $body ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = $this->retry_policy->execute(
			static fn () => wp_remote_request( self::API_BASE . $path, $args )
		);

		if ( is_wp_error( $response ) ) {
			$this->last_error = sprintf(
				'Google Search Console request failed after %1$d attempt(s) (%2$s).',
				$this->retry_policy->get_last_attempt_count(),
				sanitize_key( (string) $response->get_error_code() )
			);
			return array();
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			$this->last_error = sprintf(
				'Google Search Console returned HTTP %1$d after %2$d attempt(s).',
				$code,
				$this->retry_policy->get_last_attempt_count()
			);
			return array();
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			$this->last_error = 'Google Search Console returned an invalid JSON response.';
			return array();
		}

		return $data;
	}
}
