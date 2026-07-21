<?php
/**
 * SEOPress adapter.
 *
 * @package Citeoryx\Integrations\SeoPlugins
 */

namespace Citeoryx\Integrations\SeoPlugins;

/**
 * SEOPress read-only adapter.
 */
class SeopressAdapter implements SeoPluginAdapterInterface {

	/**
	 * {@inheritdoc}
	 */
	public function is_active(): bool {
		return defined( 'SEOPRESS_VERSION' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_title( int $post_id ): ?string {
		$title = get_post_meta( $post_id, '_seopress_titles_title', true );
		return $title ?: null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_description( int $post_id ): ?string {
		$desc = get_post_meta( $post_id, '_seopress_titles_desc', true );
		return $desc ?: null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_canonical( int $post_id ): ?string {
		$canonical = get_post_meta( $post_id, '_seopress_robots_canonical', true );
		return $canonical ?: null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_robots( int $post_id ): array {
		$robots           = array();
		$noindex          = get_post_meta( $post_id, '_seopress_robots_index', true );
		$robots['index']  = empty( $noindex );
		$nofollow         = get_post_meta( $post_id, '_seopress_robots_follow', true );
		$robots['follow'] = empty( $nofollow );
		return $robots;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_focus_keywords( int $post_id ): array {
		$keywords = get_post_meta( $post_id, '_seopress_analysis_target_keywords', true );
		if ( ! $keywords ) {
			return array();
		}
		return array_map( 'trim', explode( ',', $keywords ) );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_schema_types( int $post_id ): array {
		$type = get_post_meta( $post_id, '_seopress_pro_rich_snippets_type', true );
		return $type ? array( (string) $type ) : array();
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_edit_url( int $post_id ): ?string {
		return null;
	}
}
