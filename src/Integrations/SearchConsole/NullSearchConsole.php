<?php
/**
 * Null Search Console adapter.
 *
 * @package Citeoryx\Integrations\SearchConsole
 */

namespace Citeoryx\Integrations\SearchConsole;

/**
 * Null object for search console integration.
 */
class NullSearchConsole implements SearchConsoleInterface {

	/**
	 * Always disconnected.
	 *
	 * @return bool
	 */
	public function is_connected(): bool {
		return false;
	}

	/**
	 * Return a disconnected validation result.
	 *
	 * @return array{valid: bool, status: string, message: string, site_count: int}
	 */
	public function validate_connection(): array {
		return array(
			'valid'      => false,
			'status'     => 'not_configured',
			'message'    => 'Search provider is not configured.',
			'site_count' => 0,
		);
	}

	/**
	 * Return no request error for the null object.
	 *
	 * @return string|null
	 */
	public function get_last_error(): ?string {
		return null;
	}

	/**
	 * Return empty metrics.
	 *
	 * @param string               $start_date Start date.
	 * @param string               $end_date End date.
	 * @param array<string, mixed> $options Options.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_metrics( string $start_date, string $end_date, array $options = array() ): array {
		return array();
	}

	/**
	 * Return empty queries.
	 *
	 * @param string $url URL.
	 * @param string $start_date Start date.
	 * @param string               $end_date End date.
	 * @param array<string, mixed> $options Options.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_queries_for_url( string $url, string $start_date, string $end_date, array $options = array() ): array {
		return array();
	}
}
