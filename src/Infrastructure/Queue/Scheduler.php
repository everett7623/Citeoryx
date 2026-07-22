<?php
/**
 * Background task scheduler.
 *
 * @package Citeoryx\Infrastructure\Queue
 */

namespace Citeoryx\Infrastructure\Queue;

use Citeoryx\Application\Analyze\IssueEngine;
use Citeoryx\Application\Scan\ContentScanner;
use Citeoryx\Application\Scan\LinkChecker;
use Citeoryx\Application\Search\SearchPerformanceImporter;
use Citeoryx\Domain\Content\ContentRepository;
use Citeoryx\Domain\Scan\ScanRun;
use Citeoryx\Domain\Scan\ScanRunRepository;

/**
 * Schedules and runs bounded background tasks.
 */
class Scheduler {

	private ContentScanner $scanner;
	private IssueEngine $issue_engine;
	private LinkChecker $link_checker;
	private ScanRunRepository $scan_repo;
	private SearchPerformanceImporter $search_importer;

	public function __construct(
		ContentScanner $scanner,
		IssueEngine $issue_engine,
		LinkChecker $link_checker,
		ScanRunRepository $scan_repo,
		SearchPerformanceImporter $search_importer
	) {
		$this->scanner         = $scanner;
		$this->issue_engine    = $issue_engine;
		$this->link_checker    = $link_checker;
		$this->scan_repo       = $scan_repo;
		$this->search_importer = $search_importer;
	}

	/**
	 * Detect content changes and queue single-item scans.
	 *
	 * @return void
	 */
	public function detect_changes(): void {
		$settings = get_option( 'citeoryx_settings', array() );
		if ( empty( $settings['auto_scan'] ) ) {
			return;
		}

		$since = get_option( 'citeoryx_last_change_detection', gmdate( 'Y-m-d H:i:s', strtotime( '-1 hour' ) ) );
		$posts = get_posts(
			array(
				'post_type'      => $this->scanner->get_scan_post_types(),
				'post_status'    => array( 'publish', 'future', 'draft', 'private' ),
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'date_query'     => array(
					array(
						'column' => 'post_modified',
						'after'  => $since,
					),
				),
			)
		);

		foreach ( $posts as $post_id ) {
			$this->schedule_single_scan( (int) $post_id );
		}

		update_option( 'citeoryx_last_change_detection', current_time( 'mysql' ) );
	}

	/**
	 * Queue a persisted scan run.
	 *
	 * @param string               $scan_type Scan type.
	 * @param string               $trigger_type Trigger source.
	 * @param array<string, mixed> $config Additional config.
	 * @return ScanRun
	 */
	public function enqueue_scan( string $scan_type, string $trigger_type = 'manual', array $config = array() ): ScanRun {
		$running = $this->scan_repo->find_running();
		if ( $running ) {
			return $running;
		}

		$modified_after = 'incremental' === $scan_type
			? (string) get_option( 'citeoryx_last_incremental_scan', '' )
			: '';
		$post_types     = $this->scanner->get_scan_post_types( (array) ( $config['post_types'] ?? array() ) );

		$run               = new ScanRun();
		$run->scan_type    = $scan_type;
		$run->status       = 'queued';
		$run->trigger_type = $trigger_type;
		$run->total_items  = $this->scanner->count_items( $post_types, $modified_after ?: null );
		$run->config       = array_merge(
			$config,
			array(
				'post_types'     => $post_types,
				'offset'         => 0,
				'modified_after' => $modified_after,
			)
		);

		$run->id = $this->scan_repo->create( $run );
		if ( ! $run->id ) {
			$run->status    = 'failed';
			$run->error_log = __( '无法创建扫描任务。', 'citeoryx' );
			return $run;
		}

		if ( ! $this->schedule_run( $run->id ) ) {
			$this->scan_repo->mark_failed( $run->id, __( '无法调度扫描任务。', 'citeoryx' ) );
			$run = $this->scan_repo->find( $run->id ) ?: $run;
		}

		return $run;
	}

	/**
	 * Process one bounded scan batch and continue it when needed.
	 *
	 * @param int $run_id Run ID.
	 * @return void
	 */
	public function run_scan( int $run_id ): void {
		$run = $this->scan_repo->find( $run_id );
		if ( ! $run || in_array( $run->status, array( 'completed', 'failed', 'cancelled' ), true ) ) {
			return;
		}

		if ( 'queued' === $run->status && ! $this->scan_repo->mark_running( $run_id ) ) {
			return;
		}
		$run = $this->scan_repo->find( $run_id ) ?: $run;

		try {
			$config = $run->config;
			$batch  = $this->scanner->scan_batch(
				(array) ( $config['post_types'] ?? array() ),
				(int) ( $config['offset'] ?? 0 ),
				50,
				! empty( $config['modified_after'] ) ? (string) $config['modified_after'] : null
			);

			$failed = $run->failed_items + $batch['failed'];
			foreach ( $batch['items'] as $item ) {
				try {
					$this->issue_engine->analyze( $item );
				} catch ( \Throwable $exception ) {
					++$failed;
				}
			}

			$processed        = $run->processed_items + $batch['scanned'];
			$config['offset'] = $batch['next_offset'];
			$run_status       = $batch['complete'] ? 'completed' : 'running';
			$this->scan_repo->update_progress( $run_id, $processed, $failed, $run_status, $run->total_items );

			if ( $batch['complete'] ) {
				if ( 'incremental' === $run->scan_type ) {
					update_option( 'citeoryx_last_incremental_scan', current_time( 'mysql' ) );
				}
				do_action( 'citeoryx_scan_completed', $run_id );
				return;
			}

			$run->config = $config;
			$this->update_config( $run_id, $config );
			if ( ! $this->schedule_run( $run_id ) ) {
				$this->scan_repo->mark_failed( $run_id, __( '无法继续调度扫描任务。', 'citeoryx' ) );
			}
		} catch ( \Throwable $exception ) {
			$this->scan_repo->mark_failed( $run_id, $exception->getMessage() );
		}
	}

	/**
	 * Process a single post queued by change detection.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function scan_single_post( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$item = $this->scanner->scan_post( $post_id, $post->post_type );
		if ( $item ) {
			$this->issue_engine->analyze( $item );
		}
	}

	/**
	 * Queue the scheduled incremental scan.
	 *
	 * @return void
	 */
	public function run_incremental_scan(): void {
		$settings = get_option( 'citeoryx_settings', array() );
		if ( ! empty( $settings['auto_scan'] ) ) {
			$this->enqueue_scan( 'incremental', 'scheduled' );
		}
	}

	/**
	 * Recalculate health for existing content.
	 *
	 * @return void
	 */
	public function recalc_health(): void {
		$this->schedule_health_batch( 0 );
	}

	/**
	 * Process one health batch and continue from an immutable ID cursor.
	 *
	 * @param int $after_id Last processed content ID.
	 * @return void
	 */
	public function recalc_health_batch( int $after_id = 0 ): void {
		$content_repo = new ContentRepository();
		$items        = $content_repo->list_after_id( $after_id, 50 );
		if ( empty( $items ) ) {
			return;
		}

		$last_id = $after_id;
		foreach ( $items as $item ) {
			$this->issue_engine->analyze( $item );
			$last_id = (int) $item->id;
		}

		$this->schedule_health_batch( $last_id );
	}

	/**
	 * Check a bounded set of external links.
	 *
	 * @return void
	 */
	public function check_links(): void {
		$this->schedule_link_batch( 0 );
	}

	/**
	 * Process a small link batch and continue when more links remain.
	 *
	 * @param int $after_id Exclusive link ID cursor.
	 * @return void
	 */
	public function check_links_batch( int $after_id = 0 ): void {
		$result = $this->link_checker->check_batch( 5, $after_id );
		if ( $result['checked'] >= 5 ) {
			$this->schedule_link_batch( $result['last_id'] );
		}
	}

	public function ensure_search_performance_schedule(): void {
		if ( ! wp_next_scheduled( 'citeoryx_daily_search_performance_import' ) ) {
			wp_schedule_event( time(), 'daily', 'citeoryx_daily_search_performance_import' );
		}
	}

	public function import_search_performance(): void {
		$this->schedule_search_performance_batch( 0 );
	}

	public function import_search_performance_batch( int $after_id = 0 ): void {
		$result = $this->search_importer->import_batch( $after_id, 20 );
		if ( ! $result['complete'] ) {
			$this->schedule_search_performance_batch( $result['last_id'] );
		}
	}

	private function schedule_single_scan( int $post_id ): void {
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( 'citeoryx_scan_single_post', array( 'post_id' => $post_id ), 'citeoryx', true );
			return;
		}

		wp_schedule_single_event( time() + 1, 'citeoryx_scan_single_post', array( 'post_id' => $post_id ) );
	}

	private function schedule_run( int $run_id ): bool {
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			$action_id = as_enqueue_async_action( 'citeoryx_run_scan', array( 'run_id' => $run_id ), 'citeoryx', true );
			return (int) $action_id > 0;
		}

		return (bool) wp_schedule_single_event( time() + 1, 'citeoryx_run_scan', array( 'run_id' => $run_id ) );
	}

	private function schedule_health_batch( int $after_id ): void {
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( 'citeoryx_recalc_health_batch', array( 'after_id' => $after_id ), 'citeoryx', true );
			return;
		}
		wp_schedule_single_event( time() + 1, 'citeoryx_recalc_health_batch', array( 'after_id' => $after_id ) );
	}

	private function schedule_link_batch( int $after_id ): void {
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( 'citeoryx_check_links_batch', array( 'after_id' => $after_id ), 'citeoryx', true );
			return;
		}
		wp_schedule_single_event( time() + 1, 'citeoryx_check_links_batch', array( 'after_id' => $after_id ) );
	}

	private function schedule_search_performance_batch( int $after_id ): void {
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( 'citeoryx_import_search_performance_batch', array( 'after_id' => $after_id ), 'citeoryx', true );
			return;
		}
		wp_schedule_single_event( time() + 1, 'citeoryx_import_search_performance_batch', array( 'after_id' => $after_id ) );
	}

	private function update_config( int $run_id, array $config ): void {
		global $wpdb;
		$table = $wpdb->prefix . CITEORYX_TABLE_SCAN_RUNS;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$table,
			array( 'config_json' => wp_json_encode( $config ) ),
			array( 'id' => $run_id ),
			array( '%s' ),
			array( '%d' )
		);
	}
}
