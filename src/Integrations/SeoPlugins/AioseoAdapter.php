<?php
/**
 * AIOSEO adapter.
 *
 * @package Citeoryx\Integrations\SeoPlugins
 */

namespace Citeoryx\Integrations\SeoPlugins;

/**
 * All in One SEO read-only adapter.
 */
class AioseoAdapter implements SeoPluginAdapterInterface {

	/**
	 * {@inheritdoc}
	 */
	public function is_active(): bool {
		return class_exists( 'AIOSEO\\Plugin\\AIOSEO' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_title( int $post_id ): ?string {
		$title = get_post_meta( $post_id, '_aioseo_title', true );
		return $title ?: null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_description( int $post_id ): ?string {
		$desc = get_post_meta( $post_id, '_aioseo_description', true );
		return $desc ?: null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_canonical( int $post_id ): ?string {
		$canonical = get_post_meta( $post_id, '_aioseo_canonical_url', true );
		return $canonical ?: null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_robots( int $post_id ): array {
		$robots = array();
		$noindex = get_post_meta( $post_id, '_aioseo_noindex', true );
		$robots['index'] = empty( $noindex );
		$nofollow = get_post_meta( $post_id, '_aioseo_nofollow', true );
		$robots['follow'] = empty( $nofollow );
		return $robots;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_focus_keywords( int $post_id ): array {
		$keywords = get_post_meta( $post_id, '_aioseo_keywords', true );
		if ( ! $keywords ) {
			return array();
		}
		return array_map( 'trim', explode( ',', $keywords ) );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_schema_types( int $post_id ): array {
		$type = get_post_meta( $post_id, '_aioseo_schema_type', true );
		return $type ? array( (string) $type ) : array();
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_edit_url( int $post_id ): ?string {
		return null;
	}
}
