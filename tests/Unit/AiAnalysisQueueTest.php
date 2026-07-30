<?php
/**
 * AI analysis queue tests.
 *
 * @package Citeoryx\Tests\Unit
 */

namespace Citeoryx\Tests\Unit;

use Citeoryx\Application\Analyze\AiContentAnalyzer;
use Citeoryx\Infrastructure\Cache\Transients;
use Citeoryx\Infrastructure\Queue\AiAnalysisQueue;
use Citeoryx\Infrastructure\Queue\AiAnalysisTaskStore;
use WP_UnitTestCase;

/**
 * Covers task deduplication, ownership, completion, and safe failures.
 */
class AiAnalysisQueueTest extends WP_UnitTestCase {

	/**
	 * Task IDs created by the current test.
	 *
	 * @var array<int, string>
	 */
	private array $task_ids = array();

	/**
	 * User/content pairs locked by the current test.
	 *
	 * @var array<int, array{content_id: int, user_id: int}>
	 */
	private array $pairs = array();

	public function tearDown(): void {
		$cache = new Transients();
		foreach ( $this->task_ids as $task_id ) {
			wp_clear_scheduled_hook( AiAnalysisQueue::HOOK, array( 'task_id' => $task_id ) );
			$cache->delete( 'ai_analysis_task_' . $task_id );
		}
		foreach ( $this->pairs as $pair ) {
			delete_option( $this->lock_option( $pair['content_id'], $pair['user_id'] ) );
			$cache->delete( $this->latest_key( $pair['content_id'], $pair['user_id'] ) );
		}

		parent::tearDown();
	}

	/**
	 * Active tasks must be reused and expose only their public owner-safe shape.
	 *
	 * @return void
	 */
	public function test_queue_reuses_and_completes_owned_task(): void {
		$queue  = $this->queue_with_result();
		$first  = $queue->enqueue( 31, 41 );
		$second = $queue->enqueue( 31, 41 );
		$this->track( $first['task'], 31, 41 );

		$this->assertFalse( $first['reused'] );
		$this->assertTrue( $second['reused'] );
		$this->assertSame( $first['task']['task_id'], $second['task']['task_id'] );

		$queue->run( (string) $first['task']['task_id'] );
		$task = $queue->get_for_user( (string) $first['task']['task_id'], 31, 41 );

		$this->assertSame( 'completed', $task['status'] );
		$this->assertSame( 76, $task['result']['discoverability']['score'] );
		$this->assertSame( $task, $queue->get_latest_for_user( 31, 41 ) );
		$this->assertNull( $queue->get_for_user( (string) $first['task']['task_id'], 31, 99 ) );
		$this->assertNull( $queue->get_for_user( (string) $first['task']['task_id'], 32, 41 ) );

		$response = $queue->to_response( $task );
		$this->assertArrayNotHasKey( 'user_id', $response );
		$this->assertSame( 'completed', $response['status'] );
	}

	/**
	 * Provider exceptions must not leak details and must allow a new attempt.
	 *
	 * @return void
	 */
	public function test_failed_task_releases_lock_with_safe_error(): void {
		$analyzer = new class() extends AiContentAnalyzer {
			public function __construct() {}

			public function analyze( int $content_id ): array {
				throw new \RuntimeException( 'secret upstream response' );
			}
		};
		$queue    = $this->queue( $analyzer );
		$first    = $queue->enqueue( 51, 61 );
		$this->track( $first['task'], 51, 61 );

		$queue->run( (string) $first['task']['task_id'] );
		$failed = $queue->get_for_user( (string) $first['task']['task_id'], 51, 61 );
		$this->assertSame( 'failed', $failed['status'] );
		$this->assertStringNotContainsString( 'secret upstream response', $failed['error'] );

		$retry = $queue->enqueue( 51, 61 );
		$this->track( $retry['task'], 51, 61 );
		$this->assertFalse( $retry['reused'] );
		$this->assertNotSame( $first['task']['task_id'], $retry['task']['task_id'] );
	}

	/**
	 * Build a queue whose analyzer returns a stable fixture.
	 *
	 * @return AiAnalysisQueue
	 */
	private function queue_with_result(): AiAnalysisQueue {
		$analyzer = new class() extends AiContentAnalyzer {
			public function __construct() {}

			public function analyze( int $content_id ): array {
				return array(
					'content_id'      => $content_id,
					'suggestions'     => array(
						'configured'  => true,
						'suggestions' => array(),
					),
					'discoverability' => array(
						'configured' => true,
						'score'      => 76,
					),
				);
			}
		};

		return $this->queue( $analyzer );
	}

	/**
	 * Build a queue with isolated transient storage.
	 *
	 * @param AiContentAnalyzer $analyzer Analyzer fixture.
	 * @return AiAnalysisQueue
	 */
	private function queue( AiContentAnalyzer $analyzer ): AiAnalysisQueue {
		return new AiAnalysisQueue( new AiAnalysisTaskStore( new Transients() ), $analyzer );
	}

	/**
	 * Track task storage created by the test.
	 *
	 * @param array<string, mixed> $task       Task state.
	 * @param int                  $content_id Content ID.
	 * @param int                  $user_id    User ID.
	 * @return void
	 */
	private function track( array $task, int $content_id, int $user_id ): void {
		$this->task_ids[] = (string) $task['task_id'];
		$this->pairs[]    = compact( 'content_id', 'user_id' );
	}

	/**
	 * Mirror the task store lock key for cleanup only.
	 *
	 * @param int $content_id Content ID.
	 * @param int $user_id    User ID.
	 * @return string
	 */
	private function lock_option( int $content_id, int $user_id ): string {
		return 'citeoryx_ai_analysis_lock_' . md5( $user_id . ':' . $content_id );
	}

	/**
	 * Mirror the latest-task transient key for cleanup only.
	 *
	 * @param int $content_id Content ID.
	 * @param int $user_id    User ID.
	 * @return string
	 */
	private function latest_key( int $content_id, int $user_id ): string {
		return 'ai_analysis_latest_' . md5( $user_id . ':' . $content_id );
	}
}
