<?php
/**
 * 变更检测分批回归测试。
 *
 * @package Citeoryx\Tests\Unit
 */

namespace Citeoryx\Tests\Unit;

use Citeoryx\Core\Container;
use Citeoryx\Domain\Content\ContentRepository;
use Citeoryx\Infrastructure\Queue\Scheduler;
use WP_UnitTestCase;

/**
 * 验证批量修改和同秒修改不会被检测水位跳过。
 */
class ChangeDetectionTest extends WP_UnitTestCase {

	public function tearDown(): void {
		delete_option( 'citeoryx_settings' );
		delete_option( 'citeoryx_site_profile' );
		delete_option( 'citeoryx_last_change_detection' );
		delete_option( 'citeoryx_change_detection_window' );
		wp_clear_scheduled_hook( 'citeoryx_scan_single_post' );
		wp_clear_scheduled_hook( 'citeoryx_change_detection_batch' );
		parent::tearDown();
	}

	public function test_more_than_one_batch_preserves_watermark_until_complete(): void {
		update_option( 'citeoryx_settings', array( 'auto_scan' => true ) );
		update_option( 'citeoryx_site_profile', array( 'core_content_types' => array( 'post' ) ) );
		$since = current_datetime()->modify( '-1 hour' )->format( 'Y-m-d H:i:s' );
		update_option( 'citeoryx_last_change_detection', $since );
		$ids       = self::factory()->post->create_many( 101 );
		$scheduler = ( new Container() )->get( Scheduler::class );
		$scheduler->detect_changes();
		$window = get_option( 'citeoryx_change_detection_window' );
		$this->assertIsArray( $window );
		$this->assertSame( $since, get_option( 'citeoryx_last_change_detection' ) );
		$this->assertGreaterThan( 0, $window['after_id'] );
		$this->assertNotFalse( wp_next_scheduled( 'citeoryx_change_detection_batch', array( 'after_id' => $window['after_id'] ) ) );

		$scheduler->detect_changes();
		$this->assertFalse( get_option( 'citeoryx_change_detection_window' ) );
		$this->assertSame( $window['until'], get_option( 'citeoryx_last_change_detection' ) );
		foreach ( $ids as $post_id ) {
			$this->assertNotFalse( wp_next_scheduled( 'citeoryx_scan_single_post', array( 'post_id' => $post_id ) ) );
		}
	}

	public function test_repository_includes_equal_timestamp_and_uses_id_cursor(): void {
		$post_id = self::factory()->post->create();
		$post    = get_post( $post_id );
		$repo    = new ContentRepository();
		$this->assertContains( $post_id, $repo->modified_post_ids( array( 'post' ), $post->post_modified, $post->post_modified ) );
		$this->assertNotContains( $post_id, $repo->modified_post_ids( array( 'post' ), $post->post_modified, $post->post_modified, $post_id ) );
		$this->assertSame( array(), $repo->modified_post_ids( array( 'page' ), $post->post_modified, $post->post_modified ) );
	}

	public function test_failed_enqueue_does_not_advance_detection_watermark(): void {
		update_option( 'citeoryx_settings', array( 'auto_scan' => true ) );
		update_option( 'citeoryx_site_profile', array( 'core_content_types' => array( 'post' ) ) );
		$since = current_datetime()->modify( '-1 hour' )->format( 'Y-m-d H:i:s' );
		update_option( 'citeoryx_last_change_detection', $since );
		self::factory()->post->create();
		$reject = static function ( $pre, $event ) {
			return 'citeoryx_scan_single_post' === $event->hook ? false : $pre;
		};
		add_filter( 'pre_schedule_event', $reject, 10, 2 );
		try {
			( new Container() )->get( Scheduler::class )->detect_changes();
		} finally {
			remove_filter( 'pre_schedule_event', $reject, 10 );
		}
		$this->assertSame( $since, get_option( 'citeoryx_last_change_detection' ) );
		$this->assertSame( 0, get_option( 'citeoryx_change_detection_window' )['after_id'] );
	}
}
