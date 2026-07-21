<?php
/**
 * Issue engine unit test example.
 *
 * @package Citeoryx\Tests\Unit
 */

namespace Citeoryx\Tests\Unit;

use Citeoryx\Domain\Content\ContentItem;
use Citeoryx\Domain\Content\ContentRepository;
use Citeoryx\Domain\Issue\IssueRepository;
use Citeoryx\Domain\Link\LinkRepository;
use Citeoryx\Application\Analyze\HealthScorer;
use Citeoryx\Application\Analyze\AiReadinessScorer;
use Citeoryx\Application\Analyze\IssueEngine;
use Citeoryx\Application\Analyze\ContentStatusClassifier;
use WP_UnitTestCase;

/**
 * Tests for IssueEngine.
 */
class IssueEngineTest extends WP_UnitTestCase {

	private IssueEngine $engine;
	private ContentRepository $content_repo;

	/**
	 * Set up.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->content_repo = new ContentRepository();
		$this->engine       = new IssueEngine(
			new IssueRepository(),
			$this->content_repo,
			new LinkRepository(),
			new HealthScorer(),
			new AiReadinessScorer(),
			new ContentStatusClassifier()
		);
	}

	/**
	 * Test orphan page detection.
	 *
	 * @return void
	 */
	public function test_detects_orphan_page(): void {
		$item                = new ContentItem();
		$item->object_type   = 'post';
		$item->post_type     = 'post';
		$item->canonical_url = home_url( '/orphan-page' );
		$item->url_hash      = md5( $item->canonical_url );
		$item->metadata      = array( 'word_count' => 500 );
		$item->id            = $this->content_repo->save( $item );

		$issues = $this->engine->analyze( $item );

		$codes = array_map( static fn ( $i ) => $i->issue_code, $issues );
		$this->assertContains( 'CX_LINK_ORPHANED', $codes );
	}

	/**
	 * Resolved content should close an issue that was being worked on.
	 *
	 * @return void
	 */
	public function test_resolves_in_progress_issue_when_condition_disappears(): void {
		$item                = new ContentItem();
		$item->object_type   = 'post';
		$item->post_type     = 'post';
		$item->canonical_url = home_url( '/resolved-in-progress' );
		$item->url_hash      = md5( $item->canonical_url );
		$item->metadata      = array( 'word_count' => 500 );
		$item->id            = $this->content_repo->save( $item );

		$this->engine->analyze( $item );
		$repo   = new IssueRepository();
		$issues = $repo->list(
			array(
				'content_id' => $item->id,
				'status'     => 'open',
			),
			1,
			20
		);
		$orphan = null;
		foreach ( $issues['items'] as $issue ) {
			if ( 'CX_LINK_ORPHANED' === $issue->issue_code ) {
				$orphan = $issue;
				break;
			}
		}

		$this->assertNotNull( $orphan );
		$orphan->status = 'in_progress';
		$repo->save( $orphan );

		$item->metadata['word_count'] = 100;
		$this->engine->analyze( $item );

		$refreshed = $repo->find( $orphan->id );
		$this->assertNotNull( $refreshed );
		$this->assertSame( 'resolved', $refreshed->status );
	}
}
