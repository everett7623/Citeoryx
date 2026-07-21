<?php
/**
 * Scan run repository.
 *
 * @package Citeoryx\Domain\Scan
 */

namespace Citeoryx\Domain\Scan;

/**
 * Repository for scan runs.
 */
class ScanRunRepository {

	/**
	 * Table name.
	 *
	 * @return string
	 */
	private function table(): string {
		global $wpdb;
		return $wpdb->prefix . CITEORYX_TABLE_SCAN_RUNS;
	}

	/**
	 * Create scan run.
	 *
	 * @param ScanRun $run Scan run.
	 * @return int
	 */
	public function create( ScanRun $run ): int {
		global $wpdb;

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			array(
				'scan_type'       => $run->scan_type,
				'status'          => $run->status,
				'total_items'     => $run->total_items,
				'processed_items' => $run->processed_items,
				'failed_items'    => $run->failed_items,
				'trigger_type'    => $run->trigger_type,
				'config_json'     => $run->config ? wp_json_encode( $run->config ) : null,
				'started_at'      => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Find by ID.
	 *
	 * @param int $id Scan run ID.
	 * @return ScanRun|null
	 */
	public function find( int $id ): ?ScanRun {
		global $wpdb;

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT * FROM %i WHERE id = %d',
				$this->table(),
				$id
			)
		);

		if ( ! $row ) {
			return null;
		}

		return ScanRun::from_row( $row );
	}

	/**
	 * Find an existing queued or running scan.
	 *
	 * @return ScanRun|null
	 */
	public function find_running(): ?ScanRun {
		global $wpdb;

		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE status IN ('queued', 'running') ORDER BY id DESC LIMIT 1", $this->table() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $row ? ScanRun::from_row( $row ) : null;
	}

	/**
	 * Mark a queued run as running.
	 *
	 * @param int $id Run ID.
	 * @return bool True when this call acquired the queued run.
	 */
	public function mark_running( int $id ): bool {
		global $wpdb;

		$result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			array(
				'status'     => 'running',
				'started_at' => current_time( 'mysql' ),
			),
			array(
				'id'     => $id,
				'status' => 'queued',
			),
			array( '%s', '%s' ),
			array( '%d', '%s' )
		);
		return 1 === $result;
	}

	/**
	 * Update progress.
	 *
	 * @param int    $id Scan run ID.
	 * @param int    $processed Processed count.
	 * @param int    $failed Failed count.
	 * @param string $status Status.
	 * @param int|null $total Total count when known.
	 * @return void
	 */
	public function update_progress( int $id, int $processed, int $failed, string $status, ?int $total = null ): void {
		global $wpdb;

		$data    = array(
			'processed_items' => $processed,
			'failed_items'    => $failed,
			'status'          => $status,
		);
		$formats = array( '%d', '%d', '%s' );
		if ( null !== $total ) {
			$data['total_items'] = $total;
			$formats[]           = '%d';
		}

		if ( in_array( $status, array( 'completed', 'failed', 'cancelled' ), true ) ) {
			$data['finished_at'] = current_time( 'mysql' );
			$formats[]           = '%s';
		}

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			$data,
			array( 'id' => $id ),
			$formats,
			array( '%d' )
		);
	}

	/**
	 * Persist a failed task with a bounded error message.
	 *
	 * @param int    $id Run ID.
	 * @param string $message Error message.
	 * @return void
	 */
	public function mark_failed( int $id, string $message ): void {
		global $wpdb;

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$this->table(),
			array(
				'status'      => 'failed',
				'finished_at' => current_time( 'mysql' ),
				'error_log'   => substr( sanitize_text_field( $message ), 0, 2000 ),
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * List scan runs.
	 *
	 * @param int $page Page.
	 * @param int $per_page Per page.
	 * @return array{items: array<ScanRun>, total: int}
	 */
	public function list( int $page = 1, int $per_page = 20 ): array {
		global $wpdb;

		$offset = ( $page - 1 ) * $per_page;
		$total  = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $this->table() ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'SELECT * FROM %i ORDER BY started_at DESC LIMIT %d OFFSET %d',
				$this->table(),
				$per_page,
				$offset
			)
		);

		$items = array();
		foreach ( $rows as $row ) {
			$items[] = ScanRun::from_row( $row );
		}

		return array(
			'items' => $items,
			'total' => $total,
		);
	}
}
