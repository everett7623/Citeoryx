<?php
/**
 * Content item model.
 *
 * @package Citeoryx\Domain\Content
 */

namespace Citeoryx\Domain\Content;

/**
 * Content item value object.
 */
class ContentItem {

	public ?int $id = null;
	public ?int $object_id = null;
	public string $object_type = 'post';
	public ?string $post_type = null;
	public string $canonical_url = '';
	public string $url_hash = '';
	public ?string $language_code = null;
	public string $status = 'unknown';
	public ?float $health_score = null;
	public ?string $health_confidence = null;
	public ?float $ai_readiness_score = null;
	public ?string $content_hash = null;
	public ?string $published_at = null;
	public ?string $modified_at = null;
	public ?string $last_scanned_at = null;
	public ?string $last_reviewed_at = null;
	public ?int $assigned_user_id = null;
	public array $metadata = array();
	public string $created_at = '';
	public string $updated_at = '';

	/**
	 * Create from database row.
	 *
	 * @param object $row Database row.
	 * @return self
	 */
	public static function from_row( object $row ): self {
		$item                  = new self();
		$item->id              = (int) $row->id;
		$item->object_id       = $row->object_id ? (int) $row->object_id : null;
		$item->object_type     = $row->object_type;
		$item->post_type       = $row->post_type;
		$item->canonical_url   = $row->canonical_url;
		$item->url_hash        = $row->url_hash;
		$item->language_code   = $row->language_code;
		$item->status          = $row->status;
		$item->health_score    = $row->health_score !== null ? (float) $row->health_score : null;
		$item->health_confidence = $row->health_confidence;
		$item->ai_readiness_score = $row->ai_readiness_score !== null ? (float) $row->ai_readiness_score : null;
		$item->content_hash    = $row->content_hash;
		$item->published_at    = $row->published_at;
		$item->modified_at     = $row->modified_at;
		$item->last_scanned_at = $row->last_scanned_at;
		$item->last_reviewed_at = $row->last_reviewed_at;
		$item->assigned_user_id = $row->assigned_user_id ? (int) $row->assigned_user_id : null;
		$item->metadata        = ! empty( $row->metadata_json ) ? json_decode( $row->metadata_json, true ) ?: array() : array();
		$item->created_at      = $row->created_at;
		$item->updated_at      = $row->updated_at;

		return $item;
	}

	/**
	 * Convert to array.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'id'                 => $this->id,
			'object_id'          => $this->object_id,
			'object_type'        => $this->object_type,
			'post_type'          => $this->post_type,
			'canonical_url'      => $this->canonical_url,
			'url_hash'           => $this->url_hash,
			'language_code'      => $this->language_code,
			'status'             => $this->status,
			'health_score'       => $this->health_score,
			'health_confidence'  => $this->health_confidence,
			'ai_readiness_score' => $this->ai_readiness_score,
			'content_hash'       => $this->content_hash,
			'published_at'       => $this->published_at,
			'modified_at'        => $this->modified_at,
			'last_scanned_at'    => $this->last_scanned_at,
			'last_reviewed_at'   => $this->last_reviewed_at,
			'assigned_user_id'   => $this->assigned_user_id,
			'metadata'           => $this->metadata,
			'created_at'         => $this->created_at,
			'updated_at'         => $this->updated_at,
		);
	}
}
