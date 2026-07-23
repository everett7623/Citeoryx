<?php
/**
 * Anthropic-compatible provider adapter.
 *
 * @package Citeoryx\Integrations\AiProviders
 */

namespace Citeoryx\Integrations\AiProviders;

/**
 * Uses the Anthropic Messages protocol.
 */
class AnthropicCompatibleProvider extends AbstractAiProvider {

	public const KEY_NAME      = 'anthropic_compatible_api_key';
	public const DEFAULT_MODEL = 'claude-haiku-4-5-20251001';

	private string $api_url;

	/**
	 * @param string $api_url  Messages endpoint.
	 * @param string $model    Model identifier.
	 * @param string $key_name Encrypted key-store name.
	 */
	public function __construct( string $api_url, string $model = self::DEFAULT_MODEL, string $key_name = self::KEY_NAME ) {
		parent::__construct( $key_name, $model ?: self::DEFAULT_MODEL );
		$this->api_url = $api_url;
	}

	/**
	 * Check credentials and endpoint configuration.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return parent::is_configured() && false !== wp_http_validate_url( $this->api_url );
	}

	/**
	 * Send an Anthropic Messages request.
	 *
	 * @param string $prompt User prompt.
	 * @return string
	 */
	protected function chat( string $prompt ): string {
		$body = $this->post_json(
			$this->api_url,
			array(
				'Content-Type'      => 'application/json',
				'x-api-key'         => $this->get_api_key(),
				'anthropic-version' => '2023-06-01',
			),
			array(
				'model'       => $this->get_model(),
				'max_tokens'  => 1024,
				'temperature' => 0.3,
				'messages'    => array(
					array(
						'role'    => 'user',
						'content' => $prompt,
					),
				),
			)
		);

		$content = '';
		foreach ( $body['content'] ?? array() as $block ) {
			if ( is_array( $block ) && isset( $block['text'] ) && is_string( $block['text'] ) ) {
				$content .= $block['text'];
			}
		}

		return $content;
	}
}
