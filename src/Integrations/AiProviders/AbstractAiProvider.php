<?php
/**
 * Shared AI provider behavior.
 *
 * @package Citeoryx\Integrations\AiProviders
 */

namespace Citeoryx\Integrations\AiProviders;

use Citeoryx\Infrastructure\Encryption\KeyStore;

/**
 * Provides prompts, encrypted credentials, and response handling.
 */
abstract class AbstractAiProvider implements AiProviderInterface {

	public const KEY_NAME = '';

	private string $key_name;

	private string $model;

	private string $last_request_error = '';

	/**
	 * @param string $key_name Encrypted key-store name.
	 * @param string $model    Model identifier.
	 */
	protected function __construct( string $key_name, string $model ) {
		$this->key_name = $key_name;
		$this->model    = $model;
	}

	/**
	 * Check whether credentials are available.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return '' !== $this->get_api_key();
	}

	/**
	 * Validate credentials and endpoint with a minimal provider request.
	 *
	 * @return array{valid: bool, message: string}
	 */
	public function test_connection(): array {
		if ( ! $this->is_configured() ) {
			return array(
				'valid'   => false,
				'message' => __( '请先保存 AI 提供商和 API Key。', 'citeoryx' ),
			);
		}

		if ( '' === trim( $this->chat( 'Reply with OK only.' ) ) ) {
			return array(
				'valid'   => false,
				'message' => $this->last_request_error
					? sprintf(
						/* translators: %s: safe AI API connection failure detail. */
						__( 'AI API 连接失败：%s', 'citeoryx' ),
						$this->last_request_error
					)
					: __( 'AI API 连接失败，请检查 API Key、模型和 API 地址。', 'citeoryx' ),
			);
		}

		return array(
			'valid'   => true,
			'message' => __( 'AI API 连接成功，已收到有效响应。', 'citeoryx' ),
		);
	}

	/**
	 * Save an API key through encrypted storage.
	 *
	 * @param string $api_key API key.
	 * @return bool
	 */
	public static function save_api_key( string $api_key ): bool {
		return '' !== static::KEY_NAME && ( new KeyStore() )->set( static::KEY_NAME, $api_key );
	}

	/**
	 * Check whether an API key exists in encrypted storage.
	 *
	 * @return bool
	 */
	public static function has_api_key(): bool {
		return '' !== static::KEY_NAME && '' !== ( ( new KeyStore() )->get( static::KEY_NAME ) ?? '' );
	}

	/**
	 * Delete an API key from encrypted storage.
	 *
	 * @return bool
	 */
	public static function delete_api_key(): bool {
		return '' !== static::KEY_NAME && ( new KeyStore() )->delete( static::KEY_NAME );
	}

	/**
	 * Generate content improvement suggestions.
	 *
	 * @param string               $content Content text.
	 * @param array<string, mixed> $context Context data.
	 * @return array<string, mixed>
	 */
	public function suggest_improvements( string $content, array $context = array() ): array {
		if ( ! $this->is_configured() ) {
			return array(
				'configured'  => false,
				'suggestions' => array(),
			);
		}

		$title  = $context['title'] ?? '';
		$url    = $context['url'] ?? '';
		$issues = $context['issues'] ?? array();

		$issue_list = '';
		foreach ( $issues as $issue ) {
			$issue_list .= '- ' . ( is_array( $issue ) ? ( $issue['title'] ?? '' ) : '' ) . "\n";
		}

		$prompt = sprintf(
			"You are an SEO and content quality expert. Analyze the following content and provide 3-5 specific, actionable improvement suggestions.\n\nURL: %s\nTitle: %s\nKnown Issues:\n%s\n\nContent (first 2000 chars):\n%s\n\nRespond in JSON format:\n{\"suggestions\": [{\"priority\": \"high|medium|low\", \"category\": \"content|structure|seo|aeo\", \"title\": \"...\", \"description\": \"...\"}]}",
			$url,
			$title,
			$issue_list ?: 'None',
			mb_substr( wp_strip_all_tags( $content ), 0, 2000 )
		);

		$result = $this->chat( $prompt );
		$parsed = json_decode( $result, true );

		return array(
			'configured'  => true,
			'suggestions' => is_array( $parsed ) ? ( $parsed['suggestions'] ?? array() ) : array(),
			'raw'         => $result,
		);
	}

	/**
	 * Analyze AI discoverability.
	 *
	 * @param string               $content Content text.
	 * @param array<string, mixed> $context Context data.
	 * @return array<string, mixed>
	 */
	public function analyze_discoverability( string $content, array $context = array() ): array {
		if ( ! $this->is_configured() ) {
			return array(
				'configured' => false,
				'score'      => 0,
			);
		}

		$prompt = sprintf(
			"You are an expert in AI search and answer engine optimization (AEO). Evaluate how likely an AI assistant (like ChatGPT or Gemini) would use this content as a cited source.\n\nContent (first 2000 chars):\n%s\n\nRespond in JSON format:\n{\"score\": 0-100, \"confidence\": \"low|medium|high\", \"strengths\": [\"...\"], \"weaknesses\": [\"...\"], \"summary\": \"...\"}",
			mb_substr( wp_strip_all_tags( $content ), 0, 2000 )
		);

		$result = $this->chat( $prompt );
		$parsed = json_decode( $result, true );
		$parsed = is_array( $parsed ) ? $parsed : array();

		return array(
			'configured' => true,
			'score'      => $parsed['score'] ?? 0,
			'confidence' => $parsed['confidence'] ?? 'low',
			'strengths'  => $parsed['strengths'] ?? array(),
			'weaknesses' => $parsed['weaknesses'] ?? array(),
			'summary'    => $parsed['summary'] ?? '',
		);
	}

	/**
	 * Get the configured API key.
	 *
	 * @return string
	 */
	protected function get_api_key(): string {
		return ( new KeyStore() )->get( $this->key_name ) ?? '';
	}

	/**
	 * Get the configured model.
	 *
	 * @return string
	 */
	protected function get_model(): string {
		return $this->model;
	}

	/**
	 * Send a provider-specific chat request.
	 *
	 * @param string $prompt User prompt.
	 * @return string
	 */
	abstract protected function chat( string $prompt ): string;

	/**
	 * Send a JSON request through WordPress' safe HTTP client.
	 *
	 * @param string               $url     Endpoint URL.
	 * @param array<string, string> $headers Request headers.
	 * @param array<string, mixed>  $body    Request payload.
	 * @return array<string, mixed>
	 */
	protected function post_json( string $url, array $headers, array $body ): array {
		$this->last_request_error = '';
		$response                 = wp_safe_remote_post(
			$url,
			array(
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->last_request_error = sanitize_text_field( $response->get_error_message() );
			return array();
		}

		$http_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $http_code ) {
			$this->last_request_error = sprintf( 'HTTP %d', $http_code );
			return array();
		}

		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $decoded ) ? $decoded : array();
	}
}
