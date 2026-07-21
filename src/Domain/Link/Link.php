<?php
/**
 * Link model.
 *
 * @package Citeoryx\Domain\Link
 */

namespace Citeoryx\Domain\Link;

/**
 * Link value object.
 */
class Link {

	public ?int $id                 = null;
	public int $source_content_id   = 0;
	public ?int $target_content_id  = null;
	public string $target_url       = '';
	public string $target_url_hash  = '';
	public ?string $anchor_text     = null;
	public ?string $link_context    = null;
	public ?string $rel_flags       = null;
	public ?int $http_status        = null;
	public bool $is_internal        = false;
	public string $first_seen_at    = '';
	public string $last_seen_at     = '';
	public ?string $last_checked_at = null;
	public ?string $last_error      = null;

	/**
	 * Create from row.
	 *
	 * @param object $row Database row.
	 * @return self
	 */
	public static function from_row( object $row ): self {
		$link                    = new self();
		$link->id                = (int) $row->id;
		$link->source_content_id = (int) $row->source_content_id;
		$link->target_content_id = $row->target_content_id ? (int) $row->target_content_id : null;
		$link->target_url        = $row->target_url;
		$link->target_url_hash   = $row->target_url_hash;
		$link->anchor_text       = $row->anchor_text;
		$link->link_context      = $row->link_context;
		$link->rel_flags         = $row->rel_flags;
		$link->http_status       = null !== $row->http_status ? (int) $row->http_status : null;
		$link->is_internal       = (bool) $row->is_internal;
		$link->first_seen_at     = $row->first_seen_at;
		$link->last_seen_at      = $row->last_seen_at;
		$link->last_checked_at   = $row->last_checked_at;
		$link->last_error        = $row->last_error ?? null;

		return $link;
	}
}
