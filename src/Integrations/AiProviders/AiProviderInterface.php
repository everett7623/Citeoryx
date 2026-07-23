<?php
/**
 * AI provider interface.
 *
 * @package Citeoryx\Integrations\AiProviders
 */

namespace Citeoryx\Integrations\AiProviders;

/**
 * Interface for AI content analysis providers.
 */
interface AiProviderInterface {

	/**
	 * Check if provider is configured.
	 *
	 * @return bool
	 */
	public function is_configured(): bool;

	/**
	 * Validate the saved provider configuration with a minimal request.
	 *
	 * @return array{valid: bool, message: string}
	 */
	public function test_connection(): array;

	/**
	 * Generate content improvement suggestions.
	 *
	 * @param string               $content Content.
	 * @param array<string, mixed> $context Context data.
	 * @return array<string, mixed>
	 */
	public function suggest_improvements( string $content, array $context = array() ): array;

	/**
	 * Generate AI discoverability summary.
	 *
	 * @param string               $content Content.
	 * @param array<string, mixed> $context Context data.
	 * @return array<string, mixed>
	 */
	public function analyze_discoverability( string $content, array $context = array() ): array;
}
