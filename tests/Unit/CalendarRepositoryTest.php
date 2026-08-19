<?php
/**
 * Calendar repository tests.
 *
 * @package Citeoryx\Tests\Unit
 */

namespace Citeoryx\Tests\Unit;

use Citeoryx\Domain\Content\ContentItem;
use Citeoryx\Domain\Content\ContentRepository;
use Citeoryx\Domain\Planning\CalendarRepository;
use WP_UnitTestCase;

/**
 * Tests native scheduled posts and bounded review queries.
 */
class CalendarRepositoryTest extends WP_UnitTestCase {

	/**
	 * Clean content fixtures.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $wpdb->prefix . CITEORYX_TABLE_CONTENT_ITEMS ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		parent::tearDown();
	}

	/**
	 * Scheduled query should return only future posts inside the range.
	 *
	 * @return void
	 */
	public function test_find_scheduled_uses_wordpress_future_posts(): void {
		$publish_at = current_datetime()->modify( '+7 days' );
		$post_id    = self::factory()->post->create(
			array(
				'post_title'  => 'Scheduled content',
				'post_status' => 'future',
				'post_date'   => $publish_at->format( 'Y-m-d H:i:s' ),
			)
		);

		$result = ( new CalendarRepository() )->find_scheduled(
			array( 'post' ),
			$publish_at->modify( '-1 day' )->format( 'Y-m-d H:i:s' ),
			$publish_at->modify( '+1 day' )->format( 'Y-m-d H:i:s' )
		);

		$this->assertCount( 1, $result['items'] );
		$this->assertSame( $post_id, $result['items'][0]['id'] );
		$this->assertStringStartsWith(
			$publish_at->format( 'Y-m-d\TH:i:s' ),
			$result['items'][0]['publish_at']
		);
	}

	/**
	 * Due query should include published content and exclude drafts.
	 *
	 * @return void
	 */
	public function test_find_due_reviews_excludes_unpublished_posts(): void {
		$published = $this->create_content( 'publish', 'published-review' );
		$this->create_content( 'draft', 'draft-review' );

		$result = ( new CalendarRepository() )->find_due_reviews( '2026-06-30 23:59:59' );

		$this->assertCount( 1, $result['items'] );
		$this->assertSame( $published, $result['items'][0]['id'] );
	}

	/**
	 * Create a dated content fixture.
	 *
	 * @param string $post_status WordPress post status.
	 * @param string $slug Post slug.
	 * @return int Content item ID.
	 */
	private function create_content( string $post_status, string $slug ): int {
		$post_id             = self::factory()->post->create(
			array(
				'post_status' => $post_status,
				'post_name'   => $slug,
			)
		);
		$item                = new ContentItem();
		$item->object_id     = $post_id;
		$item->object_type   = 'post';
		$item->post_type     = 'post';
		$item->canonical_url = get_permalink( $post_id );
		$item->url_hash      = md5( $item->canonical_url );
		$item->modified_at   = '2026-05-01 00:00:00';
		return ( new ContentRepository() )->save( $item );
	}
}
