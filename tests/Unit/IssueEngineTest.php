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
			new AiReadinessScorer()
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
}
