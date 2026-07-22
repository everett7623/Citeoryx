<?php
/**
 * Issue repository tests.
 *
 * @package Citeoryx\Tests\Unit
 */

namespace Citeoryx\Tests\Unit;

use Citeoryx\Domain\Content\ContentItem;
use Citeoryx\Domain\Content\ContentRepository;
use Citeoryx\Domain\Issue\Issue;
use Citeoryx\Domain\Issue\IssueRepository;
use WP_UnitTestCase;

/**
 * Tests for issue refresh behavior.
 */
class IssueRepositoryTest extends WP_UnitTestCase {

	public function test_refresh_reuses_existing_issue(): void {
		$content_repo        = new ContentRepository();
		$issue_repo          = new IssueRepository();
		$item                = new ContentItem();
		$item->canonical_url = home_url( '/issue-refresh-test' );
		$item->url_hash      = md5( $item->canonical_url );
		$item->id            = $content_repo->save( $item );

		$first             = new Issue();
		$first->content_id = $item->id;
		$first->issue_code = 'CX_TEST_REFRESH';
		$first->category   = 'content';
		$first->title      = 'Initial title';
		$first_id          = $issue_repo->save( $first );

		$refreshed             = new Issue();
		$refreshed->content_id = $item->id;
		$refreshed->issue_code = 'CX_TEST_REFRESH';
		$refreshed->category   = 'content';
		$refreshed->title      = 'Refreshed title';
		$refreshed_id          = $issue_repo->save( $refreshed );
		$result                = $issue_repo->list( array( 'content_id' => $item->id ), 1, 20 );

		$this->assertSame( $first_id, $refreshed_id );
		$this->assertSame( 1, $result['total'] );
		$this->assertSame( 'Refreshed title', $result['items'][0]->title );
	}

	public function test_list_alertable_returns_only_unresolved_serious_issues(): void {
		$content_repo        = new ContentRepository();
		$issue_repo          = new IssueRepository();
		$item                = new ContentItem();
		$item->canonical_url = home_url( '/alert-test' );
		$item->url_hash      = md5( $item->canonical_url );
		$item->id            = $content_repo->save( $item );

		foreach ( array( 'high', 'low' ) as $severity ) {
			$issue             = new Issue();
			$issue->content_id = $item->id;
			$issue->issue_code = 'CX_ALERT_' . strtoupper( $severity );
			$issue->category   = 'content';
			$issue->severity   = $severity;
			$issue->title      = ucfirst( $severity ) . ' issue';
			$issue_repo->save( $issue );
		}

		$items = $issue_repo->list_alertable();

		$this->assertCount( 1, $items );
		$this->assertSame( 'high', $items[0]['severity'] );
		$this->assertSame( $item->canonical_url, $items[0]['canonical_url'] );
	}
}
