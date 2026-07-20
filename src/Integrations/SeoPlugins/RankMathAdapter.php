<?php
/**
 * Rank Math adapter.
 *
 * @package Citeoryx\Integrations\SeoPlugins
 */

namespace Citeoryx\Integrations\SeoPlugins;

/**
 * Rank Math SEO read-only adapter.
 */
class RankMathAdapter implements SeoPluginAdapterInterface {

	/**
	 * {@inheritdoc}
	 */
	public function is_active(): bool {
		return class_exists( 'RankMath' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_title( int $post_id ): ?string {
		$title = get_post_meta( $post_id, 'rank_math_title', true );
		return $title ?: null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_description( int $post_id ): ?string {
		$desc = get_post_meta( $post_id, 'rank_math_description', true );
		return $desc ?: null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_canonical( int $post_id ): ?string {
		$canonical = get_post_meta( $post_id, 'rank_math_canonical_url', true );
		return $canonical ?: null;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_robots( int $post_id ): array {
		$robots = get_post_meta( $post_id, 'rank_math_advanced_robots', true );
		if ( ! is_array( $robots ) ) {
			$robots = array();
		}
		$robots['index'] = get_post_meta( $post_id, 'rank_math_robots', true ) !== 'noindex';
		return $robots;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_focus_keywords( int $post_id ): array {
		$keywords = get_post_meta( $post_id, 'rank_math_focus_keyword', true );
		if ( ! $keywords ) {
			return array();
		}
		return array_map( 'trim', explode( ',', $keywords ) );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_schema_types( int $post_id ): array {
		$schema = get_post_meta( $post_id, 'rank_math_rich_snippet', true );
		return $schema ? array( (string) $schema ) : array();
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_edit_url( int $post_id ): ?string {
		return null;
	}
}
