<?php
/**
 * Optimizer evidence tests.
 *
 * @package Citeoryx\Tests\Unit
 */

namespace Citeoryx\Tests\Unit;

use Citeoryx\Application\Analyze\AiReadinessScorer;
use Citeoryx\Application\Analyze\HealthScorer;
use Citeoryx\Application\Optimize\InternalLinkSuggester;
use Citeoryx\Application\Optimize\Optimizer;
use Citeoryx\Application\Optimize\RevisionDraftService;
use Citeoryx\Application\Optimize\RevisionPerformanceMonitor;
use Citeoryx\Domain\Content\ContentItem;
use Citeoryx\Domain\Content\ContentRepository;
use Citeoryx\Domain\Issue\Issue;
use Citeoryx\Domain\Issue\IssueRepository;
use Citeoryx\Domain\Link\LinkRepository;
use Citeoryx\Domain\Metrics\MetricsRepository;
use WP_UnitTestCase;

/**
 * Verifies that issue evidence is mapped to a bounded recommendation contract.
 */
class OptimizerEvidenceTest extends WP_UnitTestCase {

	/**
	 * Optimizer results include only labelled evidence supported by the UI.
	 *
	 * @return void
	 */
	public function test_recommendations_include_formatted_issue_evidence(): void {
		$content_repo        = new ContentRepository();
		$issue_repo          = new IssueRepository();
		$item                = new ContentItem();
		$item->canonical_url = home_url( '/optimizer-evidence' );
		$item->url_hash      = md5( $item->canonical_url );
		$item->metadata      = array(
			'word_count'     => 800,
			'headings'       => array(
				1 => 1,
				2 => 2,
			),
			'internal_links' => 3,
		);
		$item->id            = $content_repo->save( $item );

		$issue             = new Issue();
		$issue->content_id = $item->id;
		$issue->issue_code = 'CX_CONTENT_THIN_VALUE';
		$issue->category   = 'content';
		$issue->title      = 'Thin content';
		$issue->evidence   = array(
			'word_count' => 125,
			'robots'     => array( 'index' => false ),
			'unexpected' => 'not exposed',
		);
		$issue_id          = $issue_repo->save( $issue );

		$optimizer = new Optimizer(
			$content_repo,
			$issue_repo,
			new HealthScorer(),
			new AiReadinessScorer(),
			new InternalLinkSuggester( $content_repo, new LinkRepository() ),
			new RevisionPerformanceMonitor(
				new RevisionDraftService( $content_repo ),
				new MetricsRepository()
			)
		);
		$results   = $optimizer->get_recommendations( $item->id );
		$matches   = array_values(
			array_filter(
				$results['recommendations'],
				static fn ( array $recommendation ): bool => ( $recommendation['issue_id'] ?? 0 ) === $issue_id
			)
		);

		$this->assertCount( 1, $matches );
		$this->assertSame(
			array(
				array(
					'label' => 'Word count',
					'value' => '125',
				),
				array(
					'label' => 'Robots directives',
					'value' => '{"index":false}',
				),
			),
			$matches[0]['evidence']
		);
	}
}
