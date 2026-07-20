<?php
/**
 * Scan run model.
 *
 * @package Citeoryx\Domain\Scan
 */

namespace Citeoryx\Domain\Scan;

/**
 * Scan run value object.
 */
class ScanRun {

	public ?int $id = null;
	public string $scan_type = '';
	public string $status = 'pending';
	public int $total_items = 0;
	public int $processed_items = 0;
	public int $failed_items = 0;
	public string $trigger_type = 'manual';
	public ?string $started_at = null;
	public ?string $finished_at = null;
	public array $config = array();
	public array $summary = array();
	public ?string $error_log = null;

	/**
	 * Create from row.
	 *
	 * @param object $row Database row.
	 * @return self
	 */
	public static function from_row( object $row ): self {
		$run                       = new self();
		$run->id                   = (int) $row->id;
		$run->scan_type            = $row->scan_type;
		$run->status               = $row->status;
		$run->total_items          = (int) $row->total_items;
		$run->processed_items      = (int) $row->processed_items;
		$run->failed_items         = (int) $row->failed_items;
		$run->trigger_type         = $row->trigger_type;
		$run->started_at           = $row->started_at;
		$run->finished_at          = $row->finished_at;
		$run->config               = ! empty( $row->config_json ) ? json_decode( $row->config_json, true ) ?: array() : array();
		$run->summary              = ! empty( $row->summary_json ) ? json_decode( $row->summary_json, true ) ?: array() : array();
		$run->error_log            = $row->error_log;

		return $run;
	}

	/**
	 * To array.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'id'              => $this->id,
			'scan_type'       => $this->scan_type,
			'status'          => $this->status,
			'total_items'     => $this->total_items,
			'processed_items' => $this->processed_items,
			'failed_items'    => $this->failed_items,
			'trigger_type'    => $this->trigger_type,
			'started_at'      => $this->started_at,
			'finished_at'     => $this->finished_at,
			'config'          => $this->config,
			'summary'         => $this->summary,
			'error_log'       => $this->error_log,
		);
	}
}
