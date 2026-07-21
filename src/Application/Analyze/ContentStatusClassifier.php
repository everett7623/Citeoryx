<?php
/**
 * Content status classifier.
 *
 * @package Citeoryx\Application\Analyze
 */

namespace Citeoryx\Application\Analyze;

use Citeoryx\Domain\Issue\Issue;

/**
 * Maps analysis output to the inventory status taxonomy.
 */
class ContentStatusClassifier {

	/**
	 * Classify content from current issues and its health score.
	 *
	 * @param array<Issue> $issues Current issues.
	 * @param float        $health_score Health score.
	 * @return string
	 */
	public function classify( array $issues, float $health_score ): string {
		$codes = array_map( static fn ( Issue $issue ): string => $issue->issue_code, $issues );

		if ( in_array( 'CX_INDEX_NOINDEX', $codes, true ) || in_array( 'CX_INDEX_CANONICAL_EXTERNAL', $codes, true ) ) {
			return CITEORYX_STATUS_NEEDS_REVIEW;
		}
		if ( in_array( 'CX_LINK_BROKEN_EXTERNAL', $codes, true ) ) {
			return CITEORYX_STATUS_BROKEN;
		}
		if ( in_array( 'CX_LINK_ORPHANED', $codes, true ) ) {
			return CITEORYX_STATUS_ORPHANED;
		}
		if ( in_array( 'CX_CONTENT_STALE', $codes, true ) ) {
			return CITEORYX_STATUS_STALE;
		}

		return $health_score >= 80 ? CITEORYX_STATUS_HEALTHY : CITEORYX_STATUS_OPPORTUNITY;
	}
}
