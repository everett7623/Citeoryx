<?php
/**
 * Issue engine.
 *
 * @package Citeoryx\Application\Analyze
 */

namespace Citeoryx\Application\Analyze;

use Citeoryx\Domain\Content\ContentItem;
use Citeoryx\Domain\Content\ContentRepository;
use Citeoryx\Domain\Issue\Issue;
use Citeoryx\Domain\Issue\IssueRepository;
use Citeoryx\Domain\Link\LinkRepository;

/**
 * Creates and updates issues from content inventory.
 */
class IssueEngine {

	private IssueRepository $issue_repo;
	private ContentRepository $content_repo;
	private LinkRepository $link_repo;
	private HealthScorer $health_scorer;
	private AiReadinessScorer $ai_scorer;
	private ContentStatusClassifier $status_classifier;

	public function __construct(
		IssueRepository $issue_repo,
		ContentRepository $content_repo,
		LinkRepository $link_repo,
		HealthScorer $health_scorer,
		AiReadinessScorer $ai_scorer,
		ContentStatusClassifier $status_classifier
	) {
		$this->issue_repo        = $issue_repo;
		$this->content_repo      = $content_repo;
		$this->link_repo         = $link_repo;
		$this->health_scorer     = $health_scorer;
		$this->ai_scorer         = $ai_scorer;
		$this->status_classifier = $status_classifier;
	}

	/**
	 * Analyze a single content item and create/update issues.
	 *
	 * @param ContentItem $item Content item.
	 * @return array<Issue>
	 */
	public function analyze( ContentItem $item ): array {
		$issues = array();

		$issues = array_merge( $issues, $this->check_discoverability( $item ) );
		$issues = array_merge( $issues, $this->check_content( $item ) );
		$issues = array_merge( $issues, $this->check_links( $item ) );
		$issues = array_merge( $issues, $this->check_aeo( $item ) );

		// Save issues.
		$saved = array();
		foreach ( $issues as $issue ) {
			$issue->content_id = $item->id;
			$issue->id         = $this->issue_repo->save( $issue );
			$saved[]           = $issue;
		}

		// Resolve issues no longer present.
		$this->resolve_missing( $item, $issues );

		// Recompute scores.
		$health = $this->health_scorer->score( $item, $issues );
		$ai     = $this->ai_scorer->score( $item );

		$item->health_score       = $health['score'];
		$item->health_confidence  = $health['confidence'];
		$item->ai_readiness_score = $ai['score'];
		$item->status             = $this->status_classifier->classify( $issues, $health['score'] );
		$this->content_repo->save( $item );

		return $saved;
	}

	/**
	 * Check discoverability issues.
	 *
	 * @param ContentItem $item Content item.
	 * @return array<Issue>
	 */
	private function check_discoverability( ContentItem $item ): array {
		$issues = array();
		$meta   = $item->metadata;
		$robots = $meta['seo_robots'] ?? array();

		if ( isset( $robots['index'] ) && false === $robots['index'] ) {
			$issues[] = $this->issue(
				'CX_INDEX_NOINDEX',
				'discoverability',
				'high',
				'high',
				__( 'Page is set to noindex.', 'citeoryx' ),
				__( 'Search engines are instructed not to index this page. Confirm whether this is intentional.', 'citeoryx' ),
				array( 'robots' => $robots )
			);
		}

		if ( ! empty( $meta['seo_canonical'] ) && $meta['seo_canonical'] !== $item->canonical_url ) {
			$issues[] = $this->issue(
				'CX_INDEX_CANONICAL_EXTERNAL',
				'discoverability',
				'medium',
				'high',
				__( 'Canonical URL points elsewhere.', 'citeoryx' ),
				__( 'The canonical URL differs from the page URL. Verify the intent.', 'citeoryx' ),
				array(
					'canonical' => $meta['seo_canonical'],
					'url'       => $item->canonical_url,
				)
			);
		}

		return $issues;
	}

	/**
	 * Check content issues.
	 *
	 * @param ContentItem $item Content item.
	 * @return array<Issue>
	 */
	private function check_content( ContentItem $item ): array {
		$issues   = array();
		$meta     = $item->metadata;
		$modified = strtotime( $item->modified_at ?? '' );

		if ( $modified && ( time() - $modified ) > 2 * YEAR_IN_SECONDS ) {
			$issues[] = $this->issue(
				'CX_CONTENT_STALE',
				'content',
				'medium',
				'medium',
				__( 'Content has not been updated in over 2 years.', 'citeoryx' ),
				__( 'Review the page for outdated facts and refresh if still relevant.', 'citeoryx' ),
				array( 'days_since_update' => (int) floor( ( time() - $modified ) / DAY_IN_SECONDS ) )
			);
		}

		$word_count = $meta['word_count'] ?? 0;
		if ( $word_count < 150 ) {
			$issues[] = $this->issue(
				'CX_CONTENT_THIN_VALUE',
				'content',
				'medium',
				'low',
				__( 'Page has very little textual content.', 'citeoryx' ),
				__( 'Consider whether the page provides unique value or should be merged.', 'citeoryx' ),
				array( 'word_count' => $word_count )
			);
		}

		$headings = $meta['headings'] ?? array();
		if ( ( $headings[1] ?? 0 ) === 0 && $word_count > 300 ) {
			$issues[] = $this->issue(
				'CX_CONTENT_TITLE_STRUCTURE',
				'content',
				'low',
				'medium',
				__( 'Missing H1 heading.', 'citeoryx' ),
				__( 'Add a clear H1 heading that describes the page topic.', 'citeoryx' ),
				array( 'headings' => $headings )
			);
		}

		return $issues;
	}

	/**
	 * Check link issues.
	 *
	 * @param ContentItem $item Content item.
	 * @return array<Issue>
	 */
	private function check_links( ContentItem $item ): array {
		$issues = array();
		$meta   = $item->metadata;

		$inbound = $this->link_repo->count_inbound( $item->id );
		if ( 0 === $inbound && ( $meta['word_count'] ?? 0 ) > 200 ) {
			$issues[] = $this->issue(
				'CX_LINK_ORPHANED',
				'links',
				'medium',
				'high',
				__( 'Page appears to be orphaned.', 'citeoryx' ),
				__( 'No other page links to this content. Add relevant internal links.', 'citeoryx' ),
				array( 'inbound_internal' => $inbound )
			);
		}

		$broken = $this->link_repo->count_broken_outbound( $item->id );
		if ( $broken > 0 ) {
			$issues[] = $this->issue(
				'CX_LINK_BROKEN_EXTERNAL',
				'links',
				'medium',
				'high',
				__( 'Page contains broken external links.', 'citeoryx' ),
				__( 'Some external links return errors. Update or remove them.', 'citeoryx' ),
				array( 'broken_count' => $broken )
			);
		}

		return $issues;
	}

	/**
	 * Check AEO / AI readiness issues.
	 *
	 * @param ContentItem $item Content item.
	 * @return array<Issue>
	 */
	private function check_aeo( ContentItem $item ): array {
		$issues = array();
		$meta   = $item->metadata;

		if ( empty( $meta['author_id'] ) ) {
			$issues[] = $this->issue(
				'CX_AEO_AUTHOR_UNCLEAR',
				'aeo',
				'low',
				'medium',
				__( 'Author information is missing.', 'citeoryx' ),
				__( 'Add author metadata to improve trust and entity clarity.', 'citeoryx' ),
				array()
			);
		}

		if ( ( $meta['external_links'] ?? 0 ) < 1 && ( $meta['word_count'] ?? 0 ) > 600 ) {
			$issues[] = $this->issue(
				'CX_AEO_EVIDENCE_MISSING',
				'aeo',
				'low',
				'low',
				__( 'Long content lacks external sources.', 'citeoryx' ),
				__( 'Consider adding verifiable citations for factual claims.', 'citeoryx' ),
				array( 'external_links' => $meta['external_links'] ?? 0 )
			);
		}

		return $issues;
	}

	/**
	 * Create an issue object.
	 *
	 * @param string               $code Issue code.
	 * @param string               $category Category.
	 * @param string               $severity Severity.
	 * @param string               $confidence Confidence.
	 * @param string               $title Title.
	 * @param string               $recommendation Recommendation.
	 * @param array<string, mixed> $evidence Evidence.
	 * @return Issue
	 */
	private function issue( string $code, string $category, string $severity, string $confidence, string $title, string $recommendation, array $evidence ): Issue {
		$issue                 = new Issue();
		$issue->issue_code     = $code;
		$issue->category       = $category;
		$issue->severity       = $severity;
		$issue->confidence     = $confidence;
		$issue->title          = $title;
		$issue->recommendation = $recommendation;
		$issue->evidence       = $evidence;
		$issue->impact_score   = $this->severity_to_score( $severity );
		$issue->effort_score   = 3;
		$issue->priority_score = $this->compute_priority( $issue );

		return $issue;
	}

	/**
	 * Compute priority score.
	 *
	 * @param Issue $issue Issue.
	 * @return float
	 */
	private function compute_priority( Issue $issue ): float {
		$impact_map = array(
			'critical' => 5,
			'high'     => 4,
			'medium'   => 3,
			'low'      => 2,
		);
		$conf_map   = array(
			'high'   => 1.5,
			'medium' => 1.2,
			'low'    => 1.0,
		);

		$impact = $impact_map[ $issue->severity ] ?? 2;
		$conf   = $conf_map[ $issue->confidence ] ?? 1.0;

		return round( $impact * $conf, 3 );
	}

	/**
	 * Map severity to score.
	 *
	 * @param string $severity Severity.
	 * @return float
	 */
	private function severity_to_score( string $severity ): float {
		$map = array(
			'critical' => 5,
			'high'     => 4,
			'medium'   => 3,
			'low'      => 2,
		);
		return $map[ $severity ] ?? 2;
	}

	/**
	 * Resolve issues no longer present.
	 *
	 * @param ContentItem $item Content item.
	 * @param array<Issue> $current_issues Current issues.
	 * @return void
	 */
	private function resolve_missing( ContentItem $item, array $current_issues ): void {
		$codes = array();
		foreach ( $current_issues as $issue ) {
			$codes[] = $issue->issue_code;
		}

		global $wpdb;
		$table = $wpdb->prefix . CITEORYX_TABLE_ISSUES;

		if ( empty( $codes ) ) {
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->prepare(
					"UPDATE %i SET status = 'resolved', resolved_at = %s WHERE content_id = %d AND status IN ('open', 'in_progress')",
					$table,
					current_time( 'mysql' ),
					$item->id
				)
			);
			return;
		}

		$placeholders = implode( ',', array_fill( 0, count( $codes ), '%s' ) );
		$query_args   = array_merge( array( $table, current_time( 'mysql' ), $item->id ), $codes );
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Dynamic placeholders match the issue-code array.
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Placeholder list is generated from a fixed %s token.
				"UPDATE %i SET status = 'resolved', resolved_at = %s WHERE content_id = %d AND status IN ('open', 'in_progress') AND issue_code NOT IN ({$placeholders})",
				$query_args
			)
		);
	}
}
