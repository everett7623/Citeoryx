<?php
/**
 * Temporary AI analysis task storage.
 *
 * @package Citeoryx\Infrastructure\Queue
 */

namespace Citeoryx\Infrastructure\Queue;

use Citeoryx\Infrastructure\Cache\Transients;

/**
 * Persists short-lived tasks and locks active user/content pairs.
 */
class AiAnalysisTaskStore {

	private const TTL = HOUR_IN_SECONDS;

	private Transients $transients;

	public function __construct( Transients $transients ) {
		$this->transients = $transients;
	}

	/**
	 * Create a task or return the active task for the same requester and content.
	 *
	 * @param int $content_id Content item ID.
	 * @param int $user_id    Requesting user ID.
	 * @return array{task: array<string, mixed>, reused: bool}
	 */
	public function create_or_get( int $content_id, int $user_id ): array {
		$active = $this->find_active( $content_id, $user_id );
		if ( $active ) {
			$this->transients->set(
				$this->latest_key( $content_id, $user_id ),
				(string) $active['task_id'],
				self::TTL
			);
			return array(
				'task'   => $active,
				'reused' => true,
			);
		}

		$task_id = wp_generate_uuid4();
		$now     = gmdate( 'c' );
		$task    = array(
			'task_id'    => $task_id,
			'content_id' => $content_id,
			'user_id'    => $user_id,
			'status'     => 'queued',
			'created_at' => $now,
			'updated_at' => $now,
		);

		if ( ! $this->save( $task ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception text is not rendered directly.
			throw new \RuntimeException( __( 'Unable to save the AI analysis task.', 'citeoryx' ) );
		}

		$lock_option = $this->lock_option( $content_id, $user_id );
		if ( ! add_option( $lock_option, $task_id, '', false ) ) {
			$this->transients->delete( $this->task_key( $task_id ) );
			$active = $this->find_active( $content_id, $user_id );
			if ( $active ) {
				return array(
					'task'   => $active,
					'reused' => true,
				);
			}

			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception text is not rendered directly.
			throw new \RuntimeException( __( 'Unable to reserve the AI analysis task.', 'citeoryx' ) );
		}
		$this->transients->set( $this->latest_key( $content_id, $user_id ), $task_id, self::TTL );

		return array(
			'task'   => $task,
			'reused' => false,
		);
	}

	/**
	 * Get an active queued or running task for a requester/content pair.
	 *
	 * @param int $content_id Content item ID.
	 * @param int $user_id    Requesting user ID.
	 * @return array<string, mixed>|null
	 */
	public function find_active( int $content_id, int $user_id ): ?array {
		$option  = $this->lock_option( $content_id, $user_id );
		$task_id = get_option( $option, '' );
		if ( ! is_string( $task_id ) || '' === $task_id ) {
			return null;
		}

		$task = $this->get( $task_id );
		if (
			! $task ||
			(int) $task['content_id'] !== $content_id ||
			(int) $task['user_id'] !== $user_id ||
			! in_array( $task['status'], array( 'queued', 'running' ), true )
		) {
			delete_option( $option );
			return null;
		}

		return $task;
	}

	/**
	 * Get one task by its opaque ID.
	 *
	 * @param string $task_id Task ID.
	 * @return array<string, mixed>|null
	 */
	public function get( string $task_id ): ?array {
		$task = $this->transients->get( $this->task_key( $task_id ) );
		return is_array( $task ) ? $task : null;
	}

	/**
	 * Get the latest retained task for a requester/content pair.
	 *
	 * @param int $content_id Content item ID.
	 * @param int $user_id    Requesting user ID.
	 * @return array<string, mixed>|null
	 */
	public function find_latest( int $content_id, int $user_id ): ?array {
		$key     = $this->latest_key( $content_id, $user_id );
		$task_id = $this->transients->get( $key, '' );
		if ( ! is_string( $task_id ) || '' === $task_id ) {
			return null;
		}

		$task = $this->get( $task_id );
		if ( ! $task || (int) $task['content_id'] !== $content_id || (int) $task['user_id'] !== $user_id ) {
			$this->transients->delete( $key );
			return null;
		}

		return $task;
	}

	/**
	 * Apply task fields and renew its result window.
	 *
	 * @param string               $task_id Task ID.
	 * @param array<string, mixed> $changes State changes.
	 * @return array<string, mixed>|null
	 */
	public function update( string $task_id, array $changes ): ?array {
		$task = $this->get( $task_id );
		if ( ! $task ) {
			return null;
		}

		$task               = array_merge( $task, $changes );
		$task['updated_at'] = gmdate( 'c' );
		if ( ! $this->save( $task ) ) {
			return null;
		}
		$this->transients->set(
			$this->latest_key( (int) $task['content_id'], (int) $task['user_id'] ),
			$task_id,
			self::TTL
		);

		if ( in_array( $task['status'], array( 'completed', 'failed' ), true ) ) {
			$this->release_lock( $task );
		}

		return $task;
	}

	/**
	 * Reduce a task to fields that are safe for its owner to read.
	 *
	 * @param array<string, mixed> $task   Stored task.
	 * @param bool                 $reused Whether an active task was reused.
	 * @return array<string, mixed>
	 */
	public function to_response( array $task, bool $reused = false ): array {
		$response = array(
			'task_id'    => (string) $task['task_id'],
			'content_id' => (int) $task['content_id'],
			'status'     => (string) $task['status'],
			'created_at' => (string) $task['created_at'],
			'updated_at' => (string) $task['updated_at'],
			'reused'     => $reused,
		);

		if ( isset( $task['result'] ) && is_array( $task['result'] ) ) {
			$response['result'] = $task['result'];
		}
		if ( ! empty( $task['error'] ) ) {
			$response['error'] = (string) $task['error'];
		}

		return $response;
	}

	/**
	 * Save an internal task state.
	 *
	 * @param array<string, mixed> $task Task state.
	 * @return bool
	 */
	private function save( array $task ): bool {
		return $this->transients->set( $this->task_key( (string) $task['task_id'] ), $task, self::TTL );
	}

	/**
	 * Clear the owner/content lock after a terminal transition.
	 *
	 * @param array<string, mixed> $task Task state.
	 * @return void
	 */
	private function release_lock( array $task ): void {
		$option = $this->lock_option( (int) $task['content_id'], (int) $task['user_id'] );
		if ( (string) get_option( $option, '' ) === (string) $task['task_id'] ) {
			delete_option( $option );
		}
	}

	/**
	 * Build the transient key suffix for one task.
	 *
	 * @param string $task_id Task ID.
	 * @return string
	 */
	private function task_key( string $task_id ): string {
		return 'ai_analysis_task_' . preg_replace( '/[^a-zA-Z0-9_-]/', '', $task_id );
	}

	/**
	 * Build an atomic option lock key for one user/content pair.
	 *
	 * @param int $content_id Content item ID.
	 * @param int $user_id    User ID.
	 * @return string
	 */
	private function lock_option( int $content_id, int $user_id ): string {
		return 'citeoryx_ai_analysis_lock_' . md5( $user_id . ':' . $content_id );
	}

	/**
	 * Build the latest-task transient key for one user/content pair.
	 *
	 * @param int $content_id Content item ID.
	 * @param int $user_id    User ID.
	 * @return string
	 */
	private function latest_key( int $content_id, int $user_id ): string {
		return 'ai_analysis_latest_' . md5( $user_id . ':' . $content_id );
	}
}
