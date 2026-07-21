<?php
/**
 * Role-scoped content access tests.
 *
 * @package Citeoryx\Tests\Unit
 */

namespace Citeoryx\Tests\Unit;

use Citeoryx\Core\Capabilities;
use Citeoryx\Core\Container;
use Citeoryx\Domain\Content\ContentItem;
use Citeoryx\Domain\Content\ContentRepository;
use Citeoryx\Domain\Issue\Issue;
use Citeoryx\Domain\Issue\IssueRepository;
use Citeoryx\Rest\Controllers\ContentController;
use Citeoryx\Rest\Controllers\IssuesController;
use Citeoryx\Rest\Controllers\OptimizerController;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Verifies that non-dashboard roles only access content they authored.
 */
class RoleAccessTest extends WP_UnitTestCase {

	private ContentRepository $content_repo;
	private IssueRepository $issue_repo;

	public function setUp(): void {
		parent::setUp();
		Capabilities::assign();
		$this->content_repo = new ContentRepository();
		$this->issue_repo   = new IssueRepository();
	}

	public function tearDown(): void {
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	public function test_author_only_lists_and_reads_own_content(): void {
		$author_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$other_id  = self::factory()->user->create( array( 'role' => 'author' ) );
		$own       = $this->create_content( $author_id, 'author-own-content' );
		$foreign   = $this->create_content( $other_id, 'author-foreign-content' );
		wp_set_current_user( $author_id );

		$controller = new ContentController( new Container() );
		$response   = $controller->list_content( new WP_REST_Request( 'GET', '/citeoryx/v1/content' ) );
		$item_ids   = array_column( $response->get_data()['data']['items'], 'id' );

		$this->assertTrue( current_user_can( Capabilities::VIEW_CONTENT ) );
		$this->assertFalse( current_user_can( Capabilities::VIEW_DASHBOARD ) );
		$this->assertContains( $own->id, $item_ids );
		$this->assertNotContains( $foreign->id, $item_ids );
		$this->assertTrue( $controller->item_permissions_check( $this->item_request( $own->id ) ) );
		$this->assertFalse( $controller->item_permissions_check( $this->item_request( $foreign->id ) ) );
	}

	public function test_author_only_lists_and_manages_own_issues(): void {
		$author_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$other_id  = self::factory()->user->create( array( 'role' => 'author' ) );
		$own       = $this->create_issue( $this->create_content( $author_id, 'author-own-issue' )->id );
		$foreign   = $this->create_issue( $this->create_content( $other_id, 'author-foreign-issue' )->id );
		wp_set_current_user( $author_id );

		$controller = new IssuesController( new Container() );
		$response   = $controller->list_issues( new WP_REST_Request( 'GET', '/citeoryx/v1/issues' ) );
		$issue_ids  = array_column( $response->get_data()['data']['items'], 'id' );

		$this->assertContains( $own->id, $issue_ids );
		$this->assertNotContains( $foreign->id, $issue_ids );
		$this->assertTrue( $controller->manage_permissions_check( $this->item_request( $own->id ) ) );
		$this->assertFalse( $controller->manage_permissions_check( $this->item_request( $foreign->id ) ) );
	}

	public function test_contributor_can_only_view_own_recommendations(): void {
		$contributor_id = self::factory()->user->create( array( 'role' => 'contributor' ) );
		$other_id       = self::factory()->user->create( array( 'role' => 'author' ) );
		$own            = $this->create_content( $contributor_id, 'contributor-own-content' );
		$foreign        = $this->create_content( $other_id, 'contributor-foreign-content' );
		wp_set_current_user( $contributor_id );

		$controller = new OptimizerController( new Container() );

		$this->assertTrue( current_user_can( Capabilities::VIEW_CONTENT ) );
		$this->assertFalse( current_user_can( Capabilities::MANAGE_ISSUES ) );
		$this->assertTrue( $controller->can_view_recommendations( $this->item_request( $own->id ) ) );
		$this->assertFalse( $controller->can_view_recommendations( $this->item_request( $foreign->id ) ) );
	}

	public function test_editor_retains_site_wide_content_access(): void {
		$editor_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		$author_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$foreign   = $this->create_content( $author_id, 'editor-visible-content' );
		wp_set_current_user( $editor_id );

		$controller = new ContentController( new Container() );
		$response   = $controller->list_content( new WP_REST_Request( 'GET', '/citeoryx/v1/content' ) );
		$item_ids   = array_column( $response->get_data()['data']['items'], 'id' );

		$this->assertTrue( current_user_can( Capabilities::VIEW_DASHBOARD ) );
		$this->assertContains( $foreign->id, $item_ids );
		$this->assertTrue( $controller->item_permissions_check( $this->item_request( $foreign->id ) ) );
	}

	private function create_content( int $author_id, string $slug ): ContentItem {
		$post_id             = self::factory()->post->create(
			array(
				'post_author' => $author_id,
				'post_name'   => $slug,
				'post_status' => 'publish',
			)
		);
		$item                = new ContentItem();
		$item->object_id     = $post_id;
		$item->object_type   = 'post';
		$item->post_type     = 'post';
		$item->canonical_url = get_permalink( $post_id );
		$item->url_hash      = md5( $item->canonical_url );
		$item->id            = $this->content_repo->save( $item );
		return $item;
	}

	private function create_issue( int $content_id ): Issue {
		$issue             = new Issue();
		$issue->content_id = $content_id;
		$issue->issue_code = 'CX_TEST_ROLE_ACCESS';
		$issue->category   = 'content';
		$issue->title      = 'Role access issue';
		$issue->id         = $this->issue_repo->save( $issue );
		return $issue;
	}

	private function item_request( int $id ): WP_REST_Request {
		$request = new WP_REST_Request( 'GET', '/citeoryx/v1/items/' . $id );
		$request->set_param( 'id', $id );
		return $request;
	}
}
