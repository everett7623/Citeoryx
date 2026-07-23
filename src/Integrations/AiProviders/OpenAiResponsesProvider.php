<?php
/**
 * OpenAI Responses API provider adapter.
 *
 * @package Citeoryx\Integrations\AiProviders
 */

namespace Citeoryx\Integrations\AiProviders;

/**
 * Uses the OpenAI Responses protocol for Codex-compatible gateways.
 */
class OpenAiResponsesProvider extends AbstractAiProvider {

	public const KEY_NAME      = OpenAiCompatibleProvider::KEY_NAME;
	public const DEFAULT_MODEL = 'gpt-4o-mini';

	private string $api_url;

	/**
	 * @param string $base_url HTTPS service base URL.
	 * @param string $model    Model identifier.
	 */
	public function __construct( string $base_url, string $model = self::DEFAULT_MODEL ) {
		parent::__construct( self::KEY_NAME, $model ?: self::DEFAULT_MODEL );
		$this->api_url = $this->responses_url( $base_url );
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
	 * Send an OpenAI Responses API request.
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
				'model'             => $this->get_model(),
				'input'             => array(
					array(
						'role'    => 'user',
						'content' => array(
							array(
								'type' => 'input_text',
								'text' => $prompt,
							),
						),
					),
				),
				'max_output_tokens' => 1024,
				'store'             => false,
			)
		);

		if ( isset( $body['output_text'] ) && is_string( $body['output_text'] ) ) {
			return $body['output_text'];
		}

		$text = '';
		foreach ( $body['output'] ?? array() as $item ) {
			if ( ! is_array( $item ) || 'message' !== ( $item['type'] ?? '' ) ) {
				continue;
			}

			foreach ( $item['content'] ?? array() as $content ) {
				if ( is_array( $content ) && 'output_text' === ( $content['type'] ?? '' ) && is_string( $content['text'] ?? null ) ) {
					$text .= $content['text'];
				}
			}
		}

		return $text;
	}

	/**
	 * Append the Responses endpoint before any service-root query parameters.
	 *
	 * @param string $base_url HTTPS service base URL.
	 * @return string
	 */
	private function responses_url( string $base_url ): string {
		$query_start = strpos( $base_url, '?' );
		$root_url    = false === $query_start ? $base_url : substr( $base_url, 0, $query_start );
		$query       = false === $query_start ? '' : substr( $base_url, $query_start );

		return untrailingslashit( $root_url ) . '/v1/responses' . $query;
	}
}
