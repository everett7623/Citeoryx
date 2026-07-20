<?php
/**
 * Content scanner.
 *
 * @package Citeoryx\Application\Scan
 */

namespace Citeoryx\Application\Scan;

use Citeoryx\Domain\Content\ContentItem;
use Citeoryx\Domain\Content\ContentRepository;
use Citeoryx\Domain\Content\BlockParser;
use Citeoryx\Domain\Link\Link;
use Citeoryx\Domain\Link\LinkRepository;
use Citeoryx\Integrations\SeoPlugins\SeoPluginAdapterFactory;

/**
 * Scans WordPress content and stores inventory / links.
 */
class ContentScanner {

	private ContentRepository $content_repo;
	private LinkRepository $link_repo;
	private SeoPluginAdapterFactory $seo_factory;
	private BlockParser $parser;

	public function __construct(
		ContentRepository $content_repo,
		LinkRepository $link_repo,
		SeoPluginAdapterFactory $seo_factory
	) {
		$this->content_repo = $content_repo;
		$this->link_repo    = $link_repo;
		$this->seo_factory  = $seo_factory;
		$this->parser       = new BlockParser();
	}

	/**
	 * Scan a single post.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $post_type Post type.
	 * @return ContentItem|null
	 */
	public function scan_post( int $post_id, string $post_type = 'post' ): ?ContentItem {
		$post = get_post( $post_id );
		if ( ! $post || ! in_array( $post->post_status, array( 'publish', 'future', 'draft', 'private' ), true ) ) {
			return null;
		}

		$seo = $this->seo_factory->active();

		$canonical = get_permalink( $post_id );
		if ( empty( $canonical ) ) {
			return null;
		}

		$url_hash = md5( $canonical );
		$item     = $this->content_repo->find_by_url_hash( $url_hash );
		if ( ! $item ) {
			$item                = new ContentItem();
			$item->object_id     = $post_id;
			$item->object_type   = 'post';
			$item->post_type     = $post_type;
			$item->canonical_url = $canonical;
			$item->url_hash      = $url_hash;
		}

		$item->published_at    = $post->post_date;
		$item->modified_at     = $post->post_modified;
		$item->last_scanned_at = current_time( 'mysql' );
		$item->content_hash    = hash( 'sha256', $post->post_content );
		$item->language_code   = $this->detect_language( $post_id );

		$headings   = $this->parser->count_headings( $post->post_content );
		$word_count = $this->parser->word_count( $post->post_content );
		$links      = $this->parser->extract_links( $post->post_content );

		$robots = $seo->get_robots( $post_id );

		$metadata = array(
			'title'           => $seo->get_title( $post_id ) ?: $post->post_title,
			'excerpt'         => $post->post_excerpt,
			'author_id'       => (int) $post->post_author,
			'word_count'      => $word_count,
			'block_count'     => count( $this->parser->parse_blocks( $post->post_content ) ),
			'headings'        => $headings,
			'image_count'     => substr_count( $post->post_content, '<img' ) + substr_count( $post->post_content, 'wp:image' ),
			'internal_links'  => 0,
			'external_links'  => 0,
			'seo_plugin'      => $this->seo_factory->detect(),
			'seo_title'       => $seo->get_title( $post_id ),
			'seo_description' => $seo->get_description( $post_id ),
			'seo_canonical'   => $seo->get_canonical( $post_id ),
			'seo_robots'      => $robots,
			'focus_keywords'  => $seo->get_focus_keywords( $post_id ),
			'schema_types'    => $seo->get_schema_types( $post_id ),
		);

		$item->metadata = $metadata;
		$item->id       = $this->content_repo->save( $item );

		$this->link_repo->delete_by_source( $item->id );

		$site_url = trailingslashit( home_url() );

		foreach ( $links as $link_data ) {
			$url        = $link_data['url'];
			$resolved   = $this->resolve_url( $url, $canonical );
			$is_internal = strpos( $resolved, $site_url ) === 0;

			if ( $is_internal ) {
				$metadata['internal_links'] = ( $metadata['internal_links'] ?? 0 ) + 1;
			} else {
				$metadata['external_links'] = ( $metadata['external_links'] ?? 0 ) + 1;
			}

			$target_item = $this->content_repo->find_by_url_hash( md5( $resolved ) );

			$link                     = new Link();
			$link->source_content_id  = $item->id;
			$link->target_content_id  = $target_item ? $target_item->id : null;
			$link->target_url         = $resolved;
			$link->target_url_hash    = md5( $resolved );
			$link->anchor_text        = $link_data['anchor'];
			$link->link_context       = 'content';
			$link->rel_flags          = $link_data['rel'];
			$link->is_internal        = $is_internal;
			$link->http_status        = null;

			$this->link_repo->save( $link );
		}

		$item->metadata = $metadata;
		$this->content_repo->save( $item );

		return $item;
	}

	/**
	 * Scan all public post types.
	 *
	 * @param array<string> $post_types Post types.
	 * @return int Number of scanned items.
	 */
	public function scan_all( array $post_types = array() ): int {
		if ( empty( $post_types ) ) {
			$post_types = get_post_types( array( 'public' => true ), 'names' );
		}

		$count = 0;
		foreach ( $post_types as $post_type ) {
			$posts = get_posts(
				array(
					'post_type'      => $post_type,
					'post_status'    => array( 'publish', 'future', 'draft', 'private' ),
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			);

			foreach ( $posts as $post_id ) {
				$this->scan_post( (int) $post_id, $post_type );
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Resolve relative URL.
	 *
	 * @param string $url URL.
	 * @param string $base Base URL.
	 * @return string
	 */
	private function resolve_url( string $url, string $base ): string {
		if ( strpos( $url, '#' ) === 0 ) {
			return $base . $url;
		}
		if ( strpos( $url, '/' ) === 0 && strpos( $url, '//' ) !== 0 ) {
			return home_url( $url );
		}
		if ( strpos( $url, 'http' ) !== 0 ) {
			return home_url( $url );
		}
		return $url;
	}

	/**
	 * Simple language detection.
	 *
	 * @param int $post_id Post ID.
	 * @return string|null
	 */
	private function detect_language( int $post_id ): ?string {
		// Use WPML / Polylang if available.
		if ( function_exists( 'pll_get_post_language' ) ) {
			$lang = pll_get_post_language( $post_id );
			if ( $lang ) {
				return $lang;
			}
		}
		return get_locale();
	}
}
