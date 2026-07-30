<?php
/**
 * AI analysis background queue.
 *
 * @package Citeoryx\Infrastructure\Queue
 */

namespace Citeoryx\Infrastructure\Queue;

use Citeoryx\Application\Analyze\AiContentAnalyzer;
use Citeoryx\Infrastructure\Logging\Logger;

/**
 * Schedules remote AI calls outside REST requests.
 */
class AiAnalysisQueue {

	public const HOOK = 'citeoryx_run_ai_analysis';

	private AiAnalysisTaskStore $store;
	private AiContentAnalyzer $analyzer;

	public function __construct( AiAnalysisTaskStore $store, AiContentAnalyzer $analyzer ) {
		$this->store    = $store;
		$this->analyzer = $analyzer;
	}

	/**
	 * Queue a task or reuse the active task for the same requester.
	 *
	 * @param int $content_id Content item ID.
	 * @param int $user_id    Requesting user ID.
	 * @return array{task: array<string, mixed>, reused: bool}
	 */
	public function enqueue( int $content_id, int $user_id ): array {
		$entry = $this->store->create_or_get( $content_id, $user_id );
		if ( $entry['reused'] ) {
			return $entry;
		}

		if ( ! $this->schedule( (string) $entry['task']['task_id'] ) ) {
			$task = $this->store->update(
				(string) $entry['task']['task_id'],
				array(
					'status' => 'failed',
					'error'  => __( 'Unable to schedule the AI analysis task.', 'citeoryx' ),
				)
			);
			if ( $task ) {
				$entry['task'] = $task;
			}
		}

		return $entry;
	}

	/**
	 * Run an analysis task in the configured background queue.
	 *
	 * @param string $task_id Task ID.
	 * @return void
	 */
	public function run( string $task_id ): void {
		$task = $this->store->get( $task_id );
		if ( ! $task || 'queued' !== $task['status'] ) {
			return;
		}

		if ( ! $this->store->update( $task_id, array( 'status' => 'running' ) ) ) {
			return;
		}

		try {
			$result = $this->analyzer->analyze( (int) $task['content_id'] );
			$this->store->update(
				$task_id,
				array(
					'status' => 'completed',
					'result' => $result,
				)
			);
		} catch ( \Throwable $exception ) {
			Logger::error(
				'AI analysis task failed',
				array(
					'task_id'   => $task_id,
					'exception' => get_class( $exception ),
					'message'   => $exception->getMessage(),
				)
			);

			$this->store->update(
				$task_id,
				array(
					'status' => 'failed',
					'error'  => __( 'AI analysis failed. Check the provider connection and try again.', 'citeoryx' ),
				)
			);
		}
	}

	/**
	 * Get a task only when it belongs to the requester and content.
	 *
	 * @param string $task_id    Task ID.
	 * @param int    $content_id Content item ID.
	 * @param int    $user_id    Requesting user ID.
	 * @return array<string, mixed>|null
	 */
	public function get_for_user( string $task_id, int $content_id, int $user_id ): ?array {
		$task = $this->store->get( $task_id );
		if (
			! $task ||
			(int) $task['content_id'] !== $content_id ||
			(int) $task['user_id'] !== $user_id
		) {
			return null;
		}

		return $task;
	}

	/**
	 * Get the latest retained task for a requester/content pair.
	 *
	 * @param int $content_id Content item ID.
	 * @param int $user_id    Requesting user ID.
	 * @return array<string, mixed>|null
	 */
	public function get_latest_for_user( int $content_id, int $user_id ): ?array {
		return $this->store->find_latest( $content_id, $user_id );
	}

	/**
	 * Build a public task response.
	 *
	 * @param array<string, mixed> $task   Task state.
	 * @param bool                 $reused Whether an active task was reused.
	 * @return array<string, mixed>
	 */
	public function to_response( array $task, bool $reused = false ): array {
		return $this->store->to_response( $task, $reused );
	}

	/**
	 * Schedule through Action Scheduler when available, otherwise WP-Cron.
	 *
	 * @param string $task_id Task ID.
	 * @return bool
	 */
	private function schedule( string $task_id ): bool {
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			return (int) as_enqueue_async_action( self::HOOK, array( 'task_id' => $task_id ), 'citeoryx', true ) > 0;
		}

		return (bool) wp_schedule_single_event( time() + 1, self::HOOK, array( 'task_id' => $task_id ) );
	}
}
