<?php
/**
 * DeepSeek provider adapter.
 *
 * @package Citeoryx\Integrations\AiProviders
 */

namespace Citeoryx\Integrations\AiProviders;

use Citeoryx\Infrastructure\Encryption\KeyStore;

/**
 * Uses the DeepSeek Chat Completions API for content analysis.
 */
class DeepSeekProvider implements AiProviderInterface {

	const KEY_NAME = 'deepseek_api_key';
	const API_URL  = 'https://api.deepseek.com/chat/completions';
	const MODEL    = 'deepseek-chat';

	/**
	 * Check if configured.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return ! empty( $this->get_api_key() );
	}

	/**
	 * Get stored API key.
	 *
	 * @return string
	 */
	private function get_api_key(): string {
		return ( new KeyStore() )->get( self::KEY_NAME ) ?? '';
	}

	/**
	 * Save an API key through encrypted storage.
	 *
	 * @param string $api_key API key.
	 * @return bool
	 */
	public static function save_api_key( string $api_key ): bool {
		return ( new KeyStore() )->set( self::KEY_NAME, $api_key );
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
			'suggestions' => $parsed['suggestions'] ?? array(),
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
	 * Send a chat completion request.
	 *
	 * @param string $prompt User prompt.
	 * @return string Response text.
	 */
	private function chat( string $prompt ): string {
		$response = wp_remote_post(
			self::API_URL,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->get_api_key(),
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'       => self::MODEL,
						'messages'    => array(
							array(
								'role'    => 'user',
								'content' => $prompt,
							),
						),
						'temperature' => 0.3,
						'max_tokens'  => 1024,
					)
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return '';
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return '';
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return $body['choices'][0]['message']['content'] ?? '';
	}
}
