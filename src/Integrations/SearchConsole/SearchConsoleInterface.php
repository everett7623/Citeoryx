<?php
/**
 * Search Console interface.
 *
 * @package Citeoryx\Integrations\SearchConsole
 */

namespace Citeoryx\Integrations\SearchConsole;

/**
 * Interface for search performance data providers (Google / Bing).
 */
interface SearchConsoleInterface {

	/**
	 * Check if integration is configured and authenticated.
	 *
	 * @return bool
	 */
	public function is_connected(): bool;

	/**
	 * Get metrics for a date range.
	 *
	 * @param string $start_date Start date YYYY-MM-DD.
	 * @param string $end_date End date YYYY-MM-DD.
	 * @param array<string, mixed> $options Options.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_metrics( string $start_date, string $end_date, array $options = array() ): array;

	/**
	 * Get top queries for a URL.
	 *
	 * @param string $url URL.
	 * @param string $start_date Start date.
	 * @param string $end_date End date.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_queries_for_url( string $url, string $start_date, string $end_date ): array;
}
