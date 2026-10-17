<?php
/**
 * Content scanner tests.
 *
 * @package Citeoryx\Tests\Unit
 */

namespace Citeoryx\Tests\Unit;

use Citeoryx\Application\Scan\ContentScanner;
use Citeoryx\Domain\Content\ContentRepository;
use Citeoryx\Domain\Link\LinkRepository;
use Citeoryx\Integrations\SeoPlugins\SeoPluginAdapterFactory;
use WP_UnitTestCase;

/**
 * Tests for scan scope selection.
 */
class ContentScannerTest extends WP_UnitTestCase {

	public function tearDown(): void {
		delete_option( 'citeoryx_site_profile' );
		parent::tearDown();
	}

	public function test_saved_profile_controls_default_post_types(): void {
		update_option( 'citeoryx_site_profile', array( 'core_content_types' => array( 'page' ) ) );
		$scanner = new ContentScanner(
			new ContentRepository(),
			new LinkRepository(),
			new SeoPluginAdapterFactory()
		);

		$this->assertSame( array( 'page' ), $scanner->get_scan_post_types() );
	}

	public function test_non_http_links_are_excluded_and_protocol_relative_links_are_external(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_content' => '<p><a href="//example.com/docs">External</a><a href="mailto:test@example.com">Email</a></p>',
			)
		);
		$scanner = new ContentScanner(
			new ContentRepository(),
			new LinkRepository(),
			new SeoPluginAdapterFactory()
		);
		$item    = $scanner->scan_post( $post_id, 'post' );

		$this->assertNotNull( $item );
		$this->assertSame( 1, $item->metadata['external_links'] );
		global $wpdb;
		$count = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE source_content_id = %d', $wpdb->prefix . CITEORYX_TABLE_LINKS, $item->id ) );
		$this->assertSame( '1', (string) $count );
	}

	public function test_invalid_explicit_post_types_do_not_expand_scan_scope(): void {
		$scanner = new ContentScanner(
			new ContentRepository(),
			new LinkRepository(),
			new SeoPluginAdapterFactory()
		);

		$this->assertSame( 0, $scanner->count_items( array( 'not-a-post-type' ) ) );
		$this->assertTrue( $scanner->scan_batch( array( 'not-a-post-type' ) )['complete'] );
	}

	public function test_incremental_scan_includes_posts_modified_at_watermark(): void {
		$post_id = self::factory()->post->create();
		$post    = get_post( $post_id );
		$scanner = new ContentScanner( new ContentRepository(), new LinkRepository(), new SeoPluginAdapterFactory() );
		$this->assertSame( 1, $scanner->count_items( array( 'post' ), $post->post_modified ) );
		$batch = $scanner->scan_batch( array( 'post' ), 0, 50, $post->post_modified );
		$this->assertSame( array( $post_id ), array_column( $batch['items'], 'object_id' ) );
	}

	public function test_slug_change_reuses_inventory_record_and_preserves_review_state(): void {
		$this->set_permalink_structure( '/%postname%/' );
		$post_id = self::factory()->post->create( array( 'post_name' => 'original-slug' ) );
		$repo    = new ContentRepository();
		$scanner = new ContentScanner( $repo, new LinkRepository(), new SeoPluginAdapterFactory() );
		$first   = $scanner->scan_post( $post_id );
		$this->assertNotNull( $first );
		$old_hash                = $first->url_hash;
		$first->last_reviewed_at = '2026-10-15 09:00:00';
		$first->assigned_user_id = 42;
		$repo->save( $first );
		wp_update_post(
			array(
				'ID'        => $post_id,
				'post_name' => 'updated-slug',
			)
		);

		$updated = $scanner->scan_post( $post_id );
		$this->assertSame( $first->id, $updated->id );
		$this->assertSame( get_permalink( $post_id ), $updated->canonical_url );
		$this->assertSame( md5( get_permalink( $post_id ) ), $updated->url_hash );
		$this->assertNull( $repo->find_by_url_hash( $old_hash ) );
		$this->assertSame( '2026-10-15 09:00:00', $updated->last_reviewed_at );
		$this->assertSame( 42, $updated->assigned_user_id );
	}
}
