<?php
/**
 * Health scorer.
 *
 * @package Citeoryx\Application\Analyze
 */

namespace Citeoryx\Application\Analyze;

use Citeoryx\Domain\Content\ContentItem;

/**
 * Computes content health score.
 */
class HealthScorer {

	/**
	 * Calculate health score.
	 *
	 * @param ContentItem       $item Content item.
	 * @param array<IssueInput> $issues Issues.
	 * @return array{score: float, confidence: string, components: array<string, float>}
	 */
	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Kept for scorer interface compatibility.
	public function score( ContentItem $item, array $issues = array() ): array {
		$components = array(
			'discoverability'    => 100,
			'search_performance' => 100,
			'freshness'          => 100,
			'intent_coverage'    => 100,
			'answerability'      => 100,
			'trust_evidence'     => 100,
			'link_integrity'     => 100,
		);

		$meta = $item->metadata;

		// Discoverability.
		if ( isset( $meta['seo_robots']['index'] ) && false === $meta['seo_robots']['index'] ) {
			$components['discoverability'] = 0;
		}

		// Freshness.
		$modified = strtotime( $item->modified_at ?? '' );
		if ( $modified && ( time() - $modified ) > YEAR_IN_SECONDS ) {
			$components['freshness'] -= 30;
		}

		// Intent & coverage.
		$word_count = $meta['word_count'] ?? 0;
		if ( $word_count < 300 ) {
			$components['intent_coverage'] -= 40;
		} elseif ( $word_count < 600 ) {
			$components['intent_coverage'] -= 15;
		}

		// Answerability.
		$headings = $meta['headings'] ?? array();
		$h2_count = $headings[2] ?? 0;
		if ( $h2_count < 2 && $word_count > 300 ) {
			$components['answerability'] -= 20;
		}

		// Trust.
		if ( empty( $meta['author_id'] ) ) {
			$components['trust_evidence'] -= 20;
		}

		// Links.
		$internal = $meta['internal_links'] ?? 0;
		if ( $internal < 1 && $word_count > 200 ) {
			$components['link_integrity'] -= 30;
		}

		$weights = array(
			'discoverability'    => 0.20,
			'search_performance' => 0.20,
			'freshness'          => 0.15,
			'intent_coverage'    => 0.15,
			'answerability'      => 0.15,
			'trust_evidence'     => 0.10,
			'link_integrity'     => 0.05,
		);

		$score = 0;
		foreach ( $components as $key => $value ) {
			$score += max( 0, min( 100, $value ) ) * $weights[ $key ];
		}

		return array(
			'score'      => round( $score, 2 ),
			'confidence' => 'medium',
			'components' => array_map(
				static function ( $v ) {
					return max( 0, min( 100, $v ) );
				},
				$components
			),
		);
	}
}
