<?php
/**
 * Null SEO plugin adapter.
 *
 * @package Citeoryx\Integrations\SeoPlugins
 */

namespace Citeoryx\Integrations\SeoPlugins;

/**
 * Default no-op adapter when no SEO plugin is detected.
 */
class NullAdapter implements SeoPluginAdapterInterface {

	/**
	 * {@inheritdoc}
	 */
	public function is_active(): bool {
		return false;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_title( int $post_id ): ?string {
		return null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_description( int $post_id ): ?string {
		return null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_canonical( int $post_id ): ?string {
		return null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_robots( int $post_id ): array {
		return array();
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_focus_keywords( int $post_id ): array {
		return array();
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_schema_types( int $post_id ): array {
		return array();
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_edit_url( int $post_id ): ?string {
		return null;
	}
}
