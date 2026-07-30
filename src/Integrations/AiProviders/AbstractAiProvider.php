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
		$parsed = $this->parse_json_response( $result );

		return array(
			'configured'  => true,
			'parsed'      => null !== $parsed,
			'suggestions' => null !== $parsed ? ( $parsed['suggestions'] ?? array() ) : array(),
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

		$result    = $this->chat( $prompt );
		$parsed    = $this->parse_json_response( $result );
		$parsed_ok = null !== $parsed;
		$parsed    = $parsed_ok ? $parsed : array();

		return array(
			'configured' => true,
			'parsed'     => $parsed_ok,
			'score'      => $parsed['score'] ?? 0,
			'confidence' => $parsed['confidence'] ?? 'low',
			'strengths'  => $parsed['strengths'] ?? array(),
			'weaknesses' => $parsed['weaknesses'] ?? array(),
			'summary'    => $parsed['summary'] ?? '',
		);
	}

	/**
	 * Parse plain, fenced, or briefly prefaced JSON object output.
	 *
	 * @param string $response Provider text response.
	 * @return array<string, mixed>|null
	 */
	private function parse_json_response( string $response ): ?array {
		$text = trim( $response );
		if ( preg_match( '/^```(?:json)?\s*(.*?)\s*```$/is', $text, $matches ) ) {
			$text = trim( $matches[1] );
		}

		$parsed = json_decode( $text, true );
		if ( is_array( $parsed ) ) {
			return $parsed;
		}

		if ( preg_match( '/\{.*\}/s', $text, $matches ) ) {
			$parsed = json_decode( $matches[0], true );
			return is_array( $parsed ) ? $parsed : null;
		}

		return null;
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
	 * Store a safe request error for connection-test feedback.
	 *
	 * @param string $message Safe error detail.
	 * @return void
	 */
	protected function set_last_request_error( string $message ): void {
		$this->last_request_error = sanitize_text_field( $message );
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
				'timeout' => AiProviderFactory::get_timeout(),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->last_request_error = sanitize_text_field( $response->get_error_message() );
			return array();
		}

		$http_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $http_code ) {
			$detail                   = $this->response_error_message( wp_remote_retrieve_body( $response ) );
			$this->last_request_error = $detail
				? sprintf( 'HTTP %d: %s', $http_code, $detail )
				: sprintf( 'HTTP %d', $http_code );
			return array();
		}

		$response_body = trim( wp_remote_retrieve_body( $response ) );
		if ( '' === $response_body ) {
			$this->last_request_error = __( 'HTTP 200，但响应为空。', 'citeoryx' );
			return array();
		}

		$decoded = json_decode( $response_body, true );
		if ( is_array( $decoded ) ) {
			return $decoded;
		}

		$sse_response = $this->decode_responses_sse( $response_body );
		if ( null !== $sse_response ) {
			return $sse_response;
		}

		$content_type = strtolower( (string) wp_remote_retrieve_header( $response, 'content-type' ) );
		if ( str_contains( $content_type, 'text/html' ) || preg_match( '/^\s*<!doctype\s+html|^\s*<html\b/i', $response_body ) ) {
			$this->last_request_error = __( 'HTTP 200，但返回了 HTML 页面；该地址可能是控制面板而不是 API 请求地址。', 'citeoryx' );
		} else {
			$this->last_request_error = __( 'HTTP 200，但响应不是有效 JSON。', 'citeoryx' );
		}

		return array();
	}

	/**
	 * Read a provider error message without exposing arbitrary response content.
	 *
	 * @param string $response_body Raw response body.
	 * @return string
	 */
	private function response_error_message( string $response_body ): string {
		$decoded = json_decode( $response_body, true );
		if ( ! is_array( $decoded ) ) {
			return '';
		}

		$message = $decoded['error']['message'] ?? $decoded['message'] ?? '';
		if ( ! is_string( $message ) ) {
			return '';
		}

		return mb_substr( sanitize_text_field( $message ), 0, 240 );
	}

	/**
	 * Normalize Responses API server-sent events into a non-streaming response.
	 *
	 * @param string $response_body Raw SSE response body.
	 * @return array<string, mixed>|null
	 */
	private function decode_responses_sse( string $response_body ): ?array {
		$found_event = false;
		$output_text = '';
		$final       = null;
		$failure     = '';

		foreach ( preg_split( '/\r?\n/', $response_body ) ?: array() as $line ) {
			if ( ! str_starts_with( $line, 'data:' ) ) {
				continue;
			}

			$payload = trim( substr( $line, 5 ) );
			if ( '' === $payload || '[DONE]' === $payload ) {
				continue;
			}

			$event = json_decode( $payload, true );
			if ( ! is_array( $event ) ) {
				continue;
			}

			$found_event = true;
			$event_type  = $event['type'] ?? '';
			if ( in_array( $event_type, array( 'response.completed', 'response.done' ), true ) && is_array( $event['response'] ?? null ) ) {
				$final = $event['response'];
			}
			if ( 'response.output_text.delta' === $event_type && is_string( $event['delta'] ?? null ) ) {
				$output_text .= $event['delta'];
			}
			if ( in_array( $event_type, array( 'error', 'response.failed', 'response.incomplete' ), true ) ) {
				$failure = $event['error']['message'] ?? $event['response']['error']['message'] ?? '';
			}
		}

		if ( is_array( $final ) ) {
			return $final;
		}
		if ( '' !== $output_text ) {
			return array( 'output_text' => $output_text );
		}
		if ( '' !== $failure && is_string( $failure ) ) {
			$this->last_request_error = mb_substr( sanitize_text_field( $failure ), 0, 240 );
			return array();
		}
		if ( $found_event ) {
			$this->last_request_error = __( 'HTTP 200，但 SSE 响应中没有可识别的模型文本。', 'citeoryx' );
			return array();
		}

		return null;
	}
}
