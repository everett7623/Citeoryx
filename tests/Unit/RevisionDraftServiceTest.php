<?php
/**
 * Revision draft service tests.
 *
 * @package Citeoryx\Tests\Unit
 */

namespace Citeoryx\Tests\Unit;

use Citeoryx\Application\Optimize\RevisionDraftService;
use Citeoryx\Core\Capabilities;
use Citeoryx\Core\Container;
use Citeoryx\Domain\Content\ContentItem;
use Citeoryx\Domain\Content\ContentRepository;
use Citeoryx\Rest\Controllers\OptimizerController;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Verifies that proposals create revisions without changing parent posts.
 */
class RevisionDraftServiceTest extends WP_UnitTestCase {

	private ContentRepository $content_repo;
	private RevisionDraftService $service;
	private int $admin_id;

	public function setUp(): void {
		parent::setUp();
		Capabilities::assign();
		$this->admin_id     = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->content_repo = new ContentRepository();
		$this->service      = new RevisionDraftService( $this->content_repo );
		wp_set_current_user( $this->admin_id );
	}

	public function tearDown(): void {
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	public function test_creates_revision_without_updating_parent(): void {
		$item     = $this->create_content();
		$original = get_post( $item->object_id );
		$snapshot = $this->service->get_snapshot( $item->id );
		$result   = $this->service->create(
			$item->id,
			$this->proposal( $original, '<!-- wp:paragraph --><p>Updated body</p><!-- /wp:paragraph -->' )
		);

		$this->assertIsArray( $result );
		$this->assertTrue( $snapshot['available'] );
		$this->assertSame( $this->post_hash( $original ), $snapshot['base_content_hash'] );
		$this->assertTrue( $result['created'] );
		$revision = wp_get_post_revision( $result['id'] );
		$parent   = get_post( $item->object_id );
		$this->assertNotNull( $revision );
		$this->assertSame( $parent->ID, $revision->post_parent );
		$this->assertSame( 'Updated title', $revision->post_title );
		$this->assertStringContainsString( 'Updated body', $revision->post_content );
		$this->assertSame( $original->post_title, $parent->post_title );
		$this->assertSame( $original->post_content, $parent->post_content );
		$this->assertSame(
			$this->post_hash( $original ),
			get_metadata( 'post', $revision->ID, '_citeoryx_base_content_hash', true )
		);
	}

	public function test_duplicate_proposal_returns_existing_revision(): void {
		$item     = $this->create_content();
		$post     = get_post( $item->object_id );
		$proposal = $this->proposal( $post, '<p>Idempotent body</p>' );
		$first    = $this->service->create( $item->id, $proposal );
		$second   = $this->service->create( $item->id, $proposal );

		$this->assertIsArray( $first );
		$this->assertIsArray( $second );
		$this->assertSame( $first['id'], $second['id'] );
		$this->assertFalse( $second['created'] );
	}

	public function test_rejects_stale_parent_hash(): void {
		$item     = $this->create_content();
		$original = get_post( $item->object_id );
		$proposal = $this->proposal( $original, '<p>Proposed body</p>' );
		wp_update_post(
			array(
				'ID'           => $original->ID,
				'post_content' => '<p>Concurrent editor update</p>',
			)
		);

		$result = $this->service->create( $item->id, $proposal );

		$this->assertWPError( $result );
		$this->assertSame( 'citeoryx_revision_conflict', $result->get_error_code() );
		$this->assertSame( 409, $result->get_error_data()['status'] );
	}

	public function test_controller_sanitizes_html_and_returns_stable_contract(): void {
		$item       = $this->create_content();
		$post       = get_post( $item->object_id );
		$request    = $this->request(
			array(
				'content_id'        => $item->id,
				'title'             => 'REST title',
				'content'           => '<p><strong>Allowed</strong><script>alert(1)</script></p>',
				'excerpt'           => '<em>Excerpt</em>',
				'base_content_hash' => $this->post_hash( $post ),
				'summary'           => 'Reviewed proposal',
			)
		);
		$controller = new OptimizerController( new Container() );

		$response = $controller->apply_revision( $request );
		$data     = $response->get_data()['data']['revision'];
		$revision = wp_get_post_revision( $data['id'] );

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( $post->ID, $data['parent_id'] );
		$this->assertTrue( $data['created'] );
		$this->assertStringContainsString( '<strong>Allowed</strong>', $revision->post_content );
		$this->assertStringNotContainsString( '<script', $revision->post_content );
		$this->assertSame( $post->post_content, get_post( $post->ID )->post_content );
	}

	public function test_apply_permission_requires_plugin_and_object_capabilities(): void {
		$author_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$item      = $this->create_content( $author_id );
		$request   = new WP_REST_Request( 'POST', '/citeoryx/v1/recommendations/apply' );
		$request->set_param( 'content_id', $item->id );
		$controller = new OptimizerController( new Container() );

		wp_set_current_user( $author_id );
		$this->assertFalse( $controller->can_apply_revision( $request ) );

		$editor_id = self::factory()->user->create( array( 'role' => 'editor' ) );
		wp_set_current_user( $editor_id );
		$this->assertTrue( $controller->can_apply_revision( $request ) );
	}

	public function test_controller_rejects_non_string_content(): void {
		$item       = $this->create_content();
		$post       = get_post( $item->object_id );
		$request    = $this->request(
			array(
				'content_id'        => $item->id,
				'title'             => 'Invalid proposal',
				'content'           => array( 'not', 'content' ),
				'excerpt'           => '',
				'base_content_hash' => $this->post_hash( $post ),
			)
		);
		$controller = new OptimizerController( new Container() );

		$response = $controller->apply_revision( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertFalse( $response->get_data()['success'] );
	}

	private function create_content( ?int $author_id = null ): ContentItem {
		$post_id             = self::factory()->post->create(
			array(
				'post_author'  => $author_id ?: $this->admin_id,
				'post_status'  => 'publish',
				'post_title'   => 'Original title',
				'post_content' => '<p>Original body</p>',
				'post_excerpt' => 'Original excerpt',
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

	/**
	 * Build a complete proposal from a parent snapshot.
	 *
	 * @return array<string, string>
	 */
	private function proposal( \WP_Post $post, string $content ): array {
		return array(
			'title'             => 'Updated title',
			'content'           => $content,
			'excerpt'           => 'Updated excerpt',
			'base_content_hash' => $this->post_hash( $post ),
			'summary'           => 'Update examples',
		);
	}

	private function request( array $body ): WP_REST_Request {
		$request = new WP_REST_Request( 'POST', '/citeoryx/v1/recommendations/apply' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $body ) );
		$request->set_param( 'content_id', $body['content_id'] );
		return $request;
	}

	private function post_hash( \WP_Post $post ): string {
		return hash(
			'sha256',
			maybe_serialize( array( $post->post_title, $post->post_content, $post->post_excerpt ) )
		);
	}
}
