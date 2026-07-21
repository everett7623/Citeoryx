<?php
/**
 * Content score tests.
 *
 * @package Citeoryx\Tests\Unit
 */

namespace Citeoryx\Tests\Unit;

use Citeoryx\Application\Analyze\AiReadinessScorer;
use Citeoryx\Application\Analyze\HealthScorer;
use Citeoryx\Application\Analyze\ContentStatusClassifier;
use Citeoryx\Domain\Issue\Issue;
use Citeoryx\Domain\Content\ContentItem;
use WP_UnitTestCase;

/**
 * Tests score component boundaries.
 */
class ScorerTest extends WP_UnitTestCase {

	public function test_noindex_disables_access_components(): void {
		$item           = new ContentItem();
		$item->metadata = array( 'seo_robots' => array( 'index' => false ) );

		$health = ( new HealthScorer() )->score( $item );
		$ai     = ( new AiReadinessScorer() )->score( $item );

		$this->assertSame( 0, $health['components']['discoverability'] );
		$this->assertSame( 0, $ai['components']['access_eligibility'] );
	}

	public function test_orphan_issue_controls_inventory_status(): void {
		$issue             = new Issue();
		$issue->issue_code = 'CX_LINK_ORPHANED';

		$status = ( new ContentStatusClassifier() )->classify( array( $issue ), 92 );
		$this->assertSame( CITEORYX_STATUS_ORPHANED, $status );
	}
}
