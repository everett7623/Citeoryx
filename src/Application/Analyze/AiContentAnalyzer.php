<?php
/**
 * AI content analysis service.
 *
 * @package Citeoryx\Application\Analyze
 */

namespace Citeoryx\Application\Analyze;

use Citeoryx\Domain\Content\ContentRepository;
use Citeoryx\Domain\Issue\IssueRepository;
use Citeoryx\Integrations\AiProviders\AiProviderFactory;

/**
 * Builds bounded, plain-text AI analysis input from a content item.
 */
class AiContentAnalyzer {

	private ContentRepository $content_repo;
	private IssueRepository $issue_repo;
	private AiProviderFactory $provider_factory;

	public function __construct(
		ContentRepository $content_repo,
		IssueRepository $issue_repo,
		AiProviderFactory $provider_factory
	) {
		$this->content_repo     = $content_repo;
		$this->issue_repo       = $issue_repo;
		$this->provider_factory = $provider_factory;
	}

	/**
	 * Check whether the active provider can perform analysis.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return $this->provider_factory->make()->is_configured();
	}

	/**
	 * Analyze one indexed content item.
	 *
	 * @param int $content_id Content item ID.
	 * @return array<string, mixed>
	 */
	public function analyze( int $content_id ): array {
		$provider = $this->provider_factory->make();
		if ( ! $provider->is_configured() ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception text is converted to a safe task error.
			throw new \RuntimeException( __( 'No AI provider is configured.', 'citeoryx' ) );
		}

		$item = $this->content_repo->find( $content_id );
		if ( ! $item ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception text is not rendered directly.
			throw new \InvalidArgumentException( __( 'Content item not found.', 'citeoryx' ) );
		}

		$issues_result = $this->issue_repo->list(
			array(
				'content_id' => $content_id,
				'status'     => 'open',
			),
			1,
			20
		);
		$issues        = array_map( static fn ( $issue ) => $issue->to_array(), $issues_result['items'] );

		$post_content = '';
		if ( $item->object_id && 'post' === $item->object_type ) {
			$post = get_post( $item->object_id );
			if ( $post ) {
				$post_content = wp_strip_all_tags( $post->post_content, true );
			}
		}

		$context = array(
			'title'  => $item->metadata['title'] ?? '',
			'url'    => $item->canonical_url,
			'issues' => $issues,
			'scores' => array(
				'health' => $item->health_score,
				'aeo'    => $item->ai_readiness_score,
			),
		);

		$suggestions     = $provider->suggest_improvements( $post_content, $context );
		$discoverability = $provider->analyze_discoverability( $post_content, $context );
		if ( ! $this->has_valid_provider_response( $suggestions, $discoverability ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception text is converted to a safe task error.
			throw new \RuntimeException( __( 'The AI provider returned an invalid analysis response.', 'citeoryx' ) );
		}

		return array(
			'content_id'      => $content_id,
			'suggestions'     => $this->normalize_suggestions( $suggestions ),
			'discoverability' => $this->normalize_discoverability( $discoverability ),
		);
	}

	/**
	 * Confirm both provider calls produced parseable output.
	 *
	 * @param array<string, mixed> $suggestions     Suggestion response.
	 * @param array<string, mixed> $discoverability Discoverability response.
	 * @return bool
	 */
	private function has_valid_provider_response( array $suggestions, array $discoverability ): bool {
		return ! empty( $suggestions['parsed'] ) && ! empty( $discoverability['parsed'] );
	}

	/**
	 * Limit suggestion output before retaining it in a transient.
	 *
	 * @param array<string, mixed> $result Provider response.
	 * @return array<string, mixed>
	 */
	private function normalize_suggestions( array $result ): array {
		$items      = array();
		$categories = array( 'content', 'structure', 'seo', 'aeo', 'links', 'discoverability', 'general' );
		foreach ( (array) ( $result['suggestions'] ?? array() ) as $suggestion ) {
			if ( ! is_array( $suggestion ) || 5 === count( $items ) ) {
				continue;
			}

			$priority = sanitize_key( (string) ( $suggestion['priority'] ?? 'medium' ) );
			$category = sanitize_key( (string) ( $suggestion['category'] ?? 'content' ) );
			$items[]  = array(
				'priority'    => in_array( $priority, array( 'high', 'medium', 'low' ), true ) ? $priority : 'medium',
				'category'    => in_array( $category, $categories, true ) ? $category : 'general',
				'title'       => sanitize_text_field( (string) ( $suggestion['title'] ?? '' ) ),
				'description' => sanitize_textarea_field( (string) ( $suggestion['description'] ?? '' ) ),
			);
		}

		return array(
			'configured'  => true,
			'suggestions' => $items,
		);
	}

	/**
	 * Limit discoverability output to fields used by the admin interface.
	 *
	 * @param array<string, mixed> $result Provider response.
	 * @return array<string, mixed>
	 */
	private function normalize_discoverability( array $result ): array {
		$confidence = sanitize_key( (string) ( $result['confidence'] ?? 'low' ) );

		return array(
			'configured' => true,
			'score'      => max( 0, min( 100, (int) ( $result['score'] ?? 0 ) ) ),
			'confidence' => in_array( $confidence, array( 'low', 'medium', 'high' ), true ) ? $confidence : 'low',
			'strengths'  => $this->normalize_text_list( $result['strengths'] ?? array() ),
			'weaknesses' => $this->normalize_text_list( $result['weaknesses'] ?? array() ),
			'summary'    => sanitize_textarea_field( (string) ( $result['summary'] ?? '' ) ),
		);
	}

	/**
	 * Normalize short text lists returned from a provider.
	 *
	 * @param mixed $value Provider field.
	 * @return array<int, string>
	 */
	private function normalize_text_list( $value ): array {
		$items = array();
		foreach ( (array) $value as $item ) {
			if ( ! is_scalar( $item ) || 5 === count( $items ) ) {
				continue;
			}

			$text = sanitize_text_field( (string) $item );
			if ( '' !== $text ) {
				$items[] = $text;
			}
		}

		return $items;
	}
}
