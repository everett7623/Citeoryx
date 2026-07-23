<?php
/**
 * OpenAI-compatible provider adapter.
 *
 * @package Citeoryx\Integrations\AiProviders
 */

namespace Citeoryx\Integrations\AiProviders;

/**
 * Uses the Chat Completions protocol.
 */
class OpenAiCompatibleProvider extends AbstractAiProvider {

	public const KEY_NAME      = 'openai_compatible_api_key';
	public const DEFAULT_MODEL = 'gpt-4o-mini';

	private string $api_url;

	/**
	 * @param string $api_url  Chat Completions endpoint.
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
	 * Send a Chat Completions request.
	 *
	 * @param string $prompt User prompt.
	 * @return string
	 */
	protected function chat( string $prompt ): string {
		$body = $this->post_json(
			$this->api_url,
			array(
				'Authorization' => 'Bearer ' . $this->get_api_key(),
				'Content-Type'  => 'application/json',
			),
			array(
				'model'       => $this->get_model(),
				'messages'    => array(
					array(
						'role'    => 'user',
						'content' => $prompt,
					),
				),
				'temperature' => 0.3,
				'max_tokens'  => 1024,
			)
		);

		$content = $body['choices'][0]['message']['content'] ?? '';
		return is_string( $content ) ? $content : '';
	}
}
