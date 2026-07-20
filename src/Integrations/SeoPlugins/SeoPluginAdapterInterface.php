<?php
/**
 * SEO plugin adapter interface.
 *
 * @package Citeoryx\Integrations\SeoPlugins
 */

namespace Citeoryx\Integrations\SeoPlugins;

/**
 * Read-only adapter for SEO plugin metadata.
 */
interface SeoPluginAdapterInterface {

	/**
	 * Whether the SEO plugin is active.
	 *
	 * @return bool
	 */
	public function is_active(): bool;

	/**
	 * Get title.
	 *
	 * @param int $post_id Post ID.
	 * @return string|null
	 */
	public function get_title( int $post_id ): ?string;

	/**
	 * Get description.
	 *
	 * @param int $post_id Post ID.
	 * @return string|null
	 */
	public function get_description( int $post_id ): ?string;

	/**
	 * Get canonical URL.
	 *
	 * @param int $post_id Post ID.
	 * @return string|null
	 */
	public function get_canonical( int $post_id ): ?string;

	/**
	 * Get robots directive.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, mixed>
	 */
	public function get_robots( int $post_id ): array;

	/**
	 * Get focus keywords.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string>
	 */
	public function get_focus_keywords( int $post_id ): array;

	/**
	 * Get schema types.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string>
	 */
	public function get_schema_types( int $post_id ): array;

	/**
	 * Get edit URL.
	 *
	 * @param int $post_id Post ID.
	 * @return string|null
	 */
	public function get_edit_url( int $post_id ): ?string;
}
