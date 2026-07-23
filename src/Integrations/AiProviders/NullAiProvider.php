<?php
/**
 * Null AI provider.
 *
 * @package Citeoryx\Integrations\AiProviders
 */

namespace Citeoryx\Integrations\AiProviders;

/**
 * Null object for AI provider.
 */
class NullAiProvider implements AiProviderInterface {

	/**
	 * Always not configured.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return false;
	}

	/**
	 * Return a failed connection result.
	 *
	 * @return array{valid: bool, message: string}
	 */
	public function test_connection(): array {
		return array(
			'valid'   => false,
			'message' => __( '请先保存 AI 提供商和 API Key。', 'citeoryx' ),
		);
	}

	/**
	 * Return empty suggestions.
	 *
	 * @param string               $content Content.
	 * @param array<string, mixed> $context Context.
	 * @return array<string, mixed>
	 */
	public function suggest_improvements( string $content, array $context = array() ): array {
		return array(
			'configured'  => false,
			'suggestions' => array(),
			'message'     => __( 'No AI provider is configured.', 'citeoryx' ),
		);
	}

	/**
	 * Return empty discoverability analysis.
	 *
	 * @param string               $content Content.
	 * @param array<string, mixed> $context Context.
	 * @return array<string, mixed>
	 */
	public function analyze_discoverability( string $content, array $context = array() ): array {
		return array(
			'configured' => false,
			'score'      => 0,
			'message'    => __( 'No AI provider is configured.', 'citeoryx' ),
		);
	}
}
