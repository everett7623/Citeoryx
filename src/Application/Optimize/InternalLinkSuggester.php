<?php
/**
 * Internal link suggestion service.
 *
 * @package Citeoryx\Application\Optimize
 */

namespace Citeoryx\Application\Optimize;

use Citeoryx\Domain\Content\ContentItem;
use Citeoryx\Domain\Content\ContentRepository;
use Citeoryx\Domain\Link\LinkRepository;

/**
 * Ranks bounded, public internal-link targets using local content metadata.
 */
class InternalLinkSuggester {

	private ContentRepository $content_repo;
	private LinkRepository $link_repo;

	public function __construct( ContentRepository $content_repo, LinkRepository $link_repo ) {
		$this->content_repo = $content_repo;
		$this->link_repo    = $link_repo;
	}

	/**
	 * Suggest public targets that are relevant and not already linked.
	 *
	 * @param int $content_id Source content ID.
	 * @param int $limit      Maximum suggestions.
	 * @return array<int, array<string, mixed>>
	 */
	public function suggest( int $content_id, int $limit = 5 ): array {
		$source = $this->content_repo->find( $content_id );
		if ( ! $source ) {
			return array();
		}

		$source_tokens = $this->content_tokens( $source );
		if ( empty( $source_tokens ) ) {
			return array();
		}

		$linked_hashes = array_fill_keys( $this->link_repo->find_internal_target_hashes( $content_id ), true );
		$suggestions   = array();
		$candidates    = $this->content_repo->list_public_link_candidates( $content_id );

		foreach ( $candidates as $candidate ) {
			if ( isset( $linked_hashes[ $candidate->url_hash ] ) || ! $this->is_compatible_candidate( $source, $candidate ) ) {
				continue;
			}

			$title            = $this->content_title( $candidate );
			$candidate_tokens = $this->content_tokens( $candidate );
			$shared_tokens    = array_intersect_key( $source_tokens, $candidate_tokens );
			$overlap          = count( $shared_tokens );
			if ( '' === $title || 0 === $overlap ) {
				continue;
			}

			$reasons = array();
			/* translators: %d: number of shared topic terms. */
			$reasons[] = sprintf( __( 'Shared topic terms: %d', 'citeoryx' ), $overlap );
			if ( $source->language_code && $candidate->language_code ) {
				$reasons[] = __( 'Same language', 'citeoryx' );
			}
			if ( $source->post_type && $source->post_type === $candidate->post_type ) {
				$reasons[] = __( 'Same content type', 'citeoryx' );
			}

			$suggestions[] = array(
				'target_content_id' => (int) $candidate->id,
				'target_post_id'    => (int) $candidate->object_id,
				'title'             => $title,
				'url'               => esc_url_raw( $candidate->canonical_url ),
				'suggested_anchor'  => $title,
				'score'             => $this->score( $source, $candidate, $overlap ),
				'reasons'           => $reasons,
			);
		}

		usort(
			$suggestions,
			static function ( array $left, array $right ): int {
				$score_order = $right['score'] <=> $left['score'];
				return 0 !== $score_order ? $score_order : strcasecmp( $left['title'], $right['title'] );
			}
		);

		return array_slice( $suggestions, 0, max( 1, min( 10, $limit ) ) );
	}

	/**
	 * Reject language mismatches and non-public custom post types.
	 *
	 * @param ContentItem $source    Source item.
	 * @param ContentItem $candidate Candidate item.
	 * @return bool
	 */
	private function is_compatible_candidate( ContentItem $source, ContentItem $candidate ): bool {
		if ( ! $candidate->object_id || ! $candidate->canonical_url ) {
			return false;
		}

		if (
			$source->language_code &&
			$candidate->language_code &&
			strtolower( $source->language_code ) !== strtolower( $candidate->language_code )
		) {
			return false;
		}

		$post = get_post( $candidate->object_id );
		return $post && is_post_publicly_viewable( $post );
	}

	/**
	 * Build topic tokens from the indexed title and focus keywords.
	 *
	 * @param ContentItem $item Content item.
	 * @return array<string, true>
	 */
	private function content_tokens( ContentItem $item ): array {
		$parts    = array( $this->content_title( $item ) );
		$keywords = $item->metadata['focus_keywords'] ?? array();
		foreach ( is_array( $keywords ) ? $keywords : array( $keywords ) as $keyword ) {
			if ( is_scalar( $keyword ) ) {
				$parts[] = (string) $keyword;
			}
		}

		return $this->tokenize( implode( ' ', $parts ) );
	}

	/**
	 * Get a stable display title.
	 *
	 * @param ContentItem $item Content item.
	 * @return string
	 */
	private function content_title( ContentItem $item ): string {
		$title = sanitize_text_field( (string) ( $item->metadata['title'] ?? '' ) );
		if ( '' === $title && $item->object_id ) {
			$title = sanitize_text_field( (string) get_the_title( $item->object_id ) );
		}

		return $title;
	}

	/**
	 * Tokenize Latin words and CJK bigrams without requiring mbstring.
	 *
	 * @param string $text Subject text.
	 * @return array<string, true>
	 */
	private function tokenize( string $text ): array {
		$text     = strtolower( remove_accents( wp_strip_all_tags( $text ) ) );
		$segments = preg_split( '/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $segments ) ) {
			return array();
		}

		$stop_words = array_fill_keys(
			array( 'the', 'and', 'for', 'with', 'from', 'into', 'your', 'how', 'what', 'why', 'this', 'that', 'are', 'is', 'of', 'to', 'in', 'on', 'by', '如何' ),
			true
		);
		$tokens     = array();

		foreach ( $segments as $segment ) {
			if ( isset( $stop_words[ $segment ] ) ) {
				continue;
			}

			if ( preg_match( '/\p{Han}/u', $segment ) ) {
				$characters = preg_split( '//u', $segment, -1, PREG_SPLIT_NO_EMPTY );
				if ( ! is_array( $characters ) ) {
					continue;
				}
				for ( $index = 0, $count = count( $characters ) - 1; $index < $count; $index++ ) {
					$tokens[ $characters[ $index ] . $characters[ $index + 1 ] ] = true;
					if ( 80 === count( $tokens ) ) {
						break 2;
					}
				}
				continue;
			}

			if ( strlen( $segment ) > 1 ) {
				$tokens[ $segment ] = true;
			}
			if ( 80 === count( $tokens ) ) {
				break;
			}
		}

		return $tokens;
	}

	/**
	 * Calculate a conservative, explainable relevance score.
	 *
	 * @param ContentItem $source    Source content.
	 * @param ContentItem $candidate Candidate content.
	 * @param int         $overlap   Shared topic token count.
	 * @return int
	 */
	private function score( ContentItem $source, ContentItem $candidate, int $overlap ): int {
		$score = 35 + min( 45, $overlap * 12 );
		if ( $source->language_code && $candidate->language_code ) {
			$score += 5;
		}
		if ( $source->post_type && $source->post_type === $candidate->post_type ) {
			$score += 5;
		}

		return min( 100, $score );
	}
}
