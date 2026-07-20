<?php
/**
 * Yoast SEO adapter.
 *
 * @package Citeoryx\Integrations\SeoPlugins
 */

namespace Citeoryx\Integrations\SeoPlugins;

/**
 * Yoast SEO read-only adapter.
 */
class YoastAdapter implements SeoPluginAdapterInterface {

	/**
	 * {@inheritdoc}
	 */
	public function is_active(): bool {
		return defined( 'WPSEO_VERSION' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_title( int $post_id ): ?string {
		$title = get_post_meta( $post_id, '_yoast_wpseo_title', true );
		return $title ?: null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_description( int $post_id ): ?string {
		$desc = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );
		return $desc ?: null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_canonical( int $post_id ): ?string {
		$canonical = get_post_meta( $post_id, '_yoast_wpseo_canonical', true );
		return $canonical ?: null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_robots( int $post_id ): array {
		$robots = array();
		$index  = get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true );
		$robots['index'] = empty( $index );
		$nofollow = get_post_meta( $post_id, '_yoast_wpseo_meta-robots-nofollow', true );
		$robots['follow'] = empty( $nofollow );
		return $robots;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_focus_keywords( int $post_id ): array {
		$keywords = get_post_meta( $post_id, '_yoast_wpseo_focuskw', true );
		if ( ! $keywords ) {
			return array();
		}
		return array_map( 'trim', explode( ',', $keywords ) );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_schema_types( int $post_id ): array {
		$type = get_post_meta( $post_id, '_yoast_wpseo_schema_type', true );
		return $type ? array( (string) $type ) : array();
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_edit_url( int $post_id ): ?string {
		return null;
	}
}
