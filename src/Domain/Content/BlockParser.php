<?php
/**
 * Block parser utility.
 *
 * @package Citeoryx\Domain\Content
 */

namespace Citeoryx\Domain\Content;

/**
 * Parse Gutenberg blocks and HTML content.
 */
class BlockParser {

	/**
	 * Parse blocks from post content.
	 *
	 * @param string $content Post content.
	 * @return array<int, array<string, mixed>>
	 */
	public function parse_blocks( string $content ): array {
		if ( ! has_blocks( $content ) ) {
			return array();
		}

		$blocks = parse_blocks( $content );
		$flat   = array();

		foreach ( $blocks as $block ) {
			$flat = array_merge( $flat, $this->flatten_blocks( $block ) );
		}

		return $flat;
	}

	/**
	 * Flatten nested blocks.
	 *
	 * @param array<string, mixed> $block Block.
	 * @return array<int, array<string, mixed>>
	 */
	private function flatten_blocks( array $block ): array {
		$flat = array();

		if ( ! empty( $block['blockName'] ) ) {
			$flat[] = array(
				'name'     => $block['blockName'],
				'attrs'    => $block['attrs'] ?? array(),
				'inner'    => $this->strip_inner_blocks( $block['innerHTML'] ?? '' ),
			);
		}

		if ( ! empty( $block['innerBlocks'] ) ) {
			foreach ( $block['innerBlocks'] as $inner ) {
				$flat = array_merge( $flat, $this->flatten_blocks( $inner ) );
			}
		}

		return $flat;
	}

	/**
	 * Strip block markers from inner HTML.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	private function strip_inner_blocks( string $html ): string {
		$text = strip_tags( $html );
		return trim( $text );
	}

	/**
	 * Count heading levels.
	 *
	 * @param string $content Post content.
	 * @return array<int, int>
	 */
	public function count_headings( string $content ): array {
		$counts = array( 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0 );

		if ( has_blocks( $content ) ) {
			$blocks = $this->parse_blocks( $content );
			foreach ( $blocks as $block ) {
				if ( strpos( $block['name'] ?? '', 'heading' ) !== false ) {
					$level = $block['attrs']['level'] ?? 2;
					$counts[ (int) $level ] = ( $counts[ (int) $level ] ?? 0 ) + 1;
				}
			}
		} else {
			preg_match_all( '/<h([1-6])[^>]*>/i', $content, $matches );
			foreach ( $matches[1] as $level ) {
				$counts[ (int) $level ] = ( $counts[ (int) $level ] ?? 0 ) + 1;
			}
		}

		return $counts;
	}

	/**
	 * Extract internal and external links.
	 *
	 * @param string $content Post content.
	 * @return array<int, array{url: string, anchor: string, rel: string}>
	 */
	public function extract_links( string $content ): array {
		$links = array();

		preg_match_all( '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/i', $content, $matches, PREG_SET_ORDER );

		foreach ( $matches as $match ) {
			$links[] = array(
				'url'    => $match[1],
				'anchor' => wp_strip_all_tags( $match[2] ),
				'rel'    => $this->extract_rel( $match[0] ),
			);
		}

		return $links;
	}

	/**
	 * Extract rel attribute.
	 *
	 * @param string $anchor_tag Anchor tag.
	 * @return string
	 */
	private function extract_rel( string $anchor_tag ): string {
		if ( preg_match( '/rel=["\']([^"\']+)["\']/i', $anchor_tag, $rel_match ) ) {
			return $rel_match[1];
		}
		return '';
	}

	/**
	 * Count words in content.
	 *
	 * @param string $content Post content.
	 * @return int
	 */
	public function word_count( string $content ): int {
		$text = wp_strip_all_tags( $content );
		return str_word_count( $text );
	}
}
