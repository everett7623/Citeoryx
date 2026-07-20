<?php
/**
 * Background task scheduler.
 *
 * @package Citeoryx\Infrastructure\Queue
 */

namespace Citeoryx\Infrastructure\Queue;

use Citeoryx\Application\Scan\ContentScanner;
use Citeoryx\Application\Scan\LinkChecker;
use Citeoryx\Application\Analyze\IssueEngine;
use Citeoryx\Domain\Content\ContentRepository;

/**
 * Schedules and runs background tasks.
 *
 * Uses WordPress cron / Action Scheduler when available.
 */
class Scheduler {

	private ContentScanner $scanner;
	private IssueEngine $issue_engine;
	private LinkChecker $link_checker;

	public function __construct( ContentScanner $scanner, IssueEngine $issue_engine, LinkChecker $link_checker ) {
		$this->scanner      = $scanner;
		$this->issue_engine = $issue_engine;
		$this->link_checker = $link_checker;
	}

	/**
	 * Detect content changes and queue scans.
	 *
	 * @return void
	 */
	public function detect_changes(): void {
		$settings = get_option( 'citeoryx_settings', array() );
		if ( empty( $settings['auto_scan'] ) ) {
			return;
		}

		$since = get_option( 'citeoryx_last_change_detection', gmdate( 'Y-m-d H:i:s', strtotime( '-1 hour' ) ) );

		$args = array(
			'post_type'      => 'any',
			'post_status'    => 'any',
			'posts_per_page' => 100,
			'fields'         => 'ids',
			'date_query'     => array(
				array(
					'column' => 'post_modified',
					'after'  => $since,
				),
			),
		);

		$posts = get_posts( $args );
		foreach ( $posts as $post_id ) {
			$this->schedule_single_scan( (int) $post_id );
		}

		update_option( 'citeoryx_last_change_detection', current_time( 'mysql' ) );
	}

	/**
	 * Run incremental scan.
	 *
	 * @return void
	 */
	public function run_incremental_scan(): void {
		$settings = get_option( 'citeoryx_settings', array() );
		if ( empty( $settings['auto_scan'] ) ) {
			return;
		}

		$count = $this->scanner->scan_all();

		// Re-analyze changed items.
		$content_repo = new ContentRepository();
		$recent       = $content_repo->list( array(), 1, 200 );
		foreach ( $recent['items'] as $item ) {
			$this->issue_engine->analyze( $item );
		}

		update_option( 'citeoryx_last_incremental_scan', current_time( 'mysql' ) );
	}

	/**
	 * Recalculate health for all content.
	 *
	 * @return void
	 */
	public function recalc_health(): void {
		$content_repo = new ContentRepository();
		$page         = 1;

		while ( true ) {
			$result = $content_repo->list( array(), $page, 100 );
			if ( empty( $result['items'] ) ) {
				break;
			}

			foreach ( $result['items'] as $item ) {
				$this->issue_engine->analyze( $item );
			}

			++$page;
		}
	}

	/**
	 * Check link status for external links.
	 *
	 * @return void
	 */
	public function check_links(): void {
		$this->link_checker->check_batch( 100, 0 );
	}

	/**
	 * Schedule single post scan.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private function schedule_single_scan( int $post_id ): void {
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( 'citeoryx_scan_single_post', array( 'post_id' => $post_id ), 'citeoryx' );
			return;
		}

		// Fallback to immediate scan.
		$post = get_post( $post_id );
		if ( $post ) {
			$item = $this->scanner->scan_post( $post_id, $post->post_type );
			if ( $item ) {
				$this->issue_engine->analyze( $item );
			}
		}
	}
}
