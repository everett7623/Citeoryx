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
	 * Validate the remote connection and return a stable result.
	 *
	 * @return array{valid: bool, status: string, message: string, site_count: int}
	 */
	public function validate_connection(): array;

	/**
	 * Get the last request error, if any.
	 *
	 * @return string|null
	 */
	public function get_last_error(): ?string;

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
	 * @param string               $end_date End date.
	 * @param array<string, mixed> $options Options such as dimensions and row limit.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_queries_for_url( string $url, string $start_date, string $end_date, array $options = array() ): array;
}
