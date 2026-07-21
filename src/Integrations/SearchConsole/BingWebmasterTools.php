<?php
/**
 * Bing Webmaster Tools adapter.
 *
 * @package Citeoryx\Integrations\SearchConsole
 */

namespace Citeoryx\Integrations\SearchConsole;

use Citeoryx\Infrastructure\Encryption\KeyStore;

/**
 * Fetches search performance data from the Bing Webmaster Tools API.
 */
class BingWebmasterTools implements SearchConsoleInterface {

	const API_BASE = 'https://www.bing.com/webmaster/api.svc/json/';
	const KEY_NAME = 'bing_webmaster_api_key';

	/**
	 * @var string
	 */
	private string $site_url;

	/**
	 * @var string|null
	 */
	private ?string $last_error = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->site_url = trailingslashit( get_option( 'siteurl', home_url() ) );
	}

	/**
	 * Check if API key is configured.
	 *
	 * @return bool
	 */
	public function is_connected(): bool {
		return ! empty( $this->get_api_key() );
	}

	/**
	 * Validate access to the Bing sites endpoint.
	 *
	 * @return array{valid: bool, status: string, message: string, site_count: int}
	 */
	public function validate_connection(): array {
		if ( ! $this->is_connected() ) {
			return array(
				'valid'      => false,
				'status'     => 'not_configured',
				'message'    => 'Bing Webmaster Tools is not connected.',
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
	 * Get stored API key.
	 *
	 * @return string
	 */
	private function get_api_key(): string {
		return ( new KeyStore() )->get( self::KEY_NAME ) ?? '';
	}

	/**
	 * Save API key.
	 *
	 * @param string $api_key API key.
	 * @return bool
	 */
	public static function save_api_key( string $api_key ): bool {
		return ( new KeyStore() )->set( self::KEY_NAME, $api_key );
	}

	/**
	 * Delete stored API key.
	 *
	 * @return bool
	 */
	public static function delete_api_key(): bool {
		return ( new KeyStore() )->delete( self::KEY_NAME );
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
		$query = http_build_query(
			array(
				'siteUrl'   => $this->site_url,
				'startDate' => $start_date,
				'endDate'   => $end_date,
			)
		);

		$data = $this->request( 'GetQueryStats?' . $query );
		$rows = $data['d']['results'] ?? $data['d']['QueryStats'] ?? array();

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map(
			static function ( array $row ): array {
				return array(
					'query'       => $row['Query'] ?? '',
					'impressions' => $row['Impressions'] ?? 0,
					'clicks'      => $row['Clicks'] ?? 0,
					'ctr'         => 0.0,
					'position'    => $row['AvgPosition'] ?? 0,
				);
			},
			$rows
		);
	}

	/**
	 * Get top queries for a specific URL.
	 *
	 * @param string $url        URL.
	 * @param string $start_date Start date.
	 * @param string $end_date   End date.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_queries_for_url( string $url, string $start_date, string $end_date ): array {
		$query = http_build_query(
			array(
				'siteUrl'   => $this->site_url,
				'url'       => $url,
				'startDate' => $start_date,
				'endDate'   => $end_date,
			)
		);

		$data = $this->request( 'GetUrlQueryStats?' . $query );
		$rows = $data['d']['results'] ?? $data['d']['UrlQueryStats'] ?? array();

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map(
			static function ( array $row ): array {
				return array(
					'query'       => $row['Query'] ?? '',
					'impressions' => $row['Impressions'] ?? 0,
					'clicks'      => $row['Clicks'] ?? 0,
					'ctr'         => 0.0,
					'position'    => $row['AvgPosition'] ?? 0,
				);
			},
			$rows
		);
	}

	/**
	 * List sites registered in Bing Webmaster Tools.
	 *
	 * @return array<int, string>
	 */
	public function list_sites(): array {
		$data  = $this->request( 'GetUserSites' );
		$sites = $data['d']['results'] ?? $data['d']['Sites'] ?? array();
		return array_map(
			static function ( array $site ): string {
				return $site['Url'] ?? '';
			},
			is_array( $sites ) ? $sites : array()
		);
	}

	/**
	 * Make an authenticated request to the Bing Webmaster Tools API.
	 *
	 * @param string $path API path including query string.
	 * @return array<string, mixed>
	 */
	private function request( string $path ): array {
		$this->last_error = null;
		$api_key          = $this->get_api_key();
		if ( ! $api_key ) {
			$this->last_error = 'Bing Webmaster Tools API key is unavailable.';
			return array();
		}

		$separator = strpos( $path, '?' ) === false ? '?' : '&';
		$url       = self::API_BASE . $path . $separator . 'apikey=' . rawurlencode( $api_key );

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array( 'Accept' => 'application/json' ),
				'timeout' => 20,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->last_error = sprintf(
				'Bing Webmaster Tools request failed (%s).',
				sanitize_key( (string) $response->get_error_code() )
			);
			return array();
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			$this->last_error = sprintf( 'Bing Webmaster Tools returned HTTP %d.', $code );
			return array();
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			$this->last_error = 'Bing Webmaster Tools returned an invalid JSON response.';
			return array();
		}

		return $data;
	}
}
