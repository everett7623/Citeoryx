<?php
/**
 * AI readiness scorer.
 *
 * @package Citeoryx\Application\Analyze
 */

namespace Citeoryx\Application\Analyze;

use Citeoryx\Domain\Content\ContentItem;

/**
 * Computes AI discoverability readiness score.
 */
class AiReadinessScorer {

	/**
	 * Score readiness.
	 *
	 * @param ContentItem $item Content item.
	 * @return array{score: float, confidence: string, components: array<string, float>}
	 */
	public function score( ContentItem $item ): array {
		$components = array(
			'access_eligibility' => 100,
			'answer_clarity'     => 100,
			'entity_clarity'     => 100,
			'evidence_trust'     => 100,
			'structure_extract'  => 100,
			'freshness'          => 100,
		);

		$meta = $item->metadata;

		// Access eligibility.
		if ( isset( $meta['seo_robots']['index'] ) && false === $meta['seo_robots']['index'] ) {
			$components['access_eligibility'] = 0;
		}

		// Answer clarity.
		$headings   = $meta['headings'] ?? array();
		$word_count = $meta['word_count'] ?? 0;
		if ( $word_count > 300 && ( $headings[2] ?? 0 ) < 2 ) {
			$components['answer_clarity'] -= 25;
		}

		// Entity clarity.
		if ( empty( $meta['author_id'] ) ) {
			$components['entity_clarity'] -= 30;
		}

		// Evidence / trust.
		$external = $meta['external_links'] ?? 0;
		if ( $external < 1 && $word_count > 600 ) {
			$components['evidence_trust'] -= 15;
		}

		// Structure extract.
		$block_count = $meta['block_count'] ?? 0;
		if ( $block_count > 0 && ( $meta['image_count'] ?? 0 ) > 0 ) {
			$components['structure_extract'] -= 0; // baseline ok.
		}

		// Freshness.
		$modified = strtotime( $item->modified_at ?? '' );
		if ( $modified && ( time() - $modified ) > 2 * YEAR_IN_SECONDS ) {
			$components['freshness'] -= 40;
		} elseif ( $modified && ( time() - $modified ) > YEAR_IN_SECONDS ) {
			$components['freshness'] -= 20;
		}

		$weights = array(
			'access_eligibility' => 0.25,
			'answer_clarity'     => 0.20,
			'entity_clarity'     => 0.15,
			'evidence_trust'     => 0.15,
			'structure_extract'  => 0.15,
			'freshness'          => 0.10,
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
