<?php
/**
 * Internal link suggestion tests.
 *
 * @package Citeoryx\Tests\Unit
 */

namespace Citeoryx\Tests\Unit;

use Citeoryx\Application\Optimize\InternalLinkSuggester;
use Citeoryx\Domain\Content\ContentItem;
use Citeoryx\Domain\Content\ContentRepository;
use Citeoryx\Domain\Link\Link;
use Citeoryx\Domain\Link\LinkRepository;
use WP_UnitTestCase;

/**
 * Verifies public, unlinked, relevant targets are suggested safely.
 */
class InternalLinkSuggesterTest extends WP_UnitTestCase {

	private ContentRepository $content_repo;
	private LinkRepository $link_repo;

	public function setUp(): void {
		parent::setUp();
		$this->content_repo = new ContentRepository();
		$this->link_repo    = new LinkRepository();
	}

	/**
	 * Existing links and non-public targets must not be suggested.
	 *
	 * @return void
	 */
	public function test_suggests_relevant_public_unlinked_content(): void {
		$source = $this->create_item( 'Quasar indexing audit guide', 'publish' );
		$target = $this->create_item( 'Quasar indexing checklist', 'publish' );
		$linked = $this->create_item( 'Quasar indexing tools', 'publish' );
		$this->create_item( 'Quasar indexing draft', 'draft' );
		$this->create_item( 'Quasar indexing private notes', 'publish', 'secret' );
		$this->create_item( 'Chocolate cake recipe', 'publish' );

		$link                    = new Link();
		$link->source_content_id = $source->id;
		$link->target_content_id = $linked->id;
		$link->target_url        = $linked->canonical_url;
		$link->target_url_hash   = $linked->url_hash;
		$link->is_internal       = true;
		$this->link_repo->save( $link );

		$suggestions = ( new InternalLinkSuggester( $this->content_repo, $this->link_repo ) )->suggest( $source->id );

		$this->assertCount( 1, $suggestions );
		$this->assertSame( $target->id, $suggestions[0]['target_content_id'] );
		$this->assertSame( 'Quasar indexing checklist', $suggestions[0]['suggested_anchor'] );
		$this->assertGreaterThan( 0, $suggestions[0]['score'] );
		$this->assertContains( 'Same language', $suggestions[0]['reasons'] );
	}

	/**
	 * CJK titles should receive matches through Unicode bigrams.
	 *
	 * @return void
	 */
	public function test_suggests_related_cjk_content(): void {
		$source = $this->create_item( '量子内容优化指南', 'publish', '', 'zh-CN' );
		$target = $this->create_item( '内容优化检查清单', 'publish', '', 'zh-CN' );
		$this->create_item( '量子内容优化英文版本', 'publish', '', 'en-US' );

		$suggestions = ( new InternalLinkSuggester( $this->content_repo, $this->link_repo ) )->suggest( $source->id );
		$ids         = array_column( $suggestions, 'target_content_id' );

		$this->assertContains( $target->id, $ids );
	}

	/**
	 * Create a post and its indexed Citeoryx content item.
	 *
	 * @param string $title    Post title.
	 * @param string $status   Post status.
	 * @param string $password Post password.
	 * @param string $language Indexed language code.
	 * @return ContentItem
	 */
	private function create_item(
		string $title,
		string $status,
		string $password = '',
		string $language = 'en-US'
	): ContentItem {
		$post_id             = self::factory()->post->create(
			array(
				'post_title'    => $title,
				'post_name'     => sanitize_title( $title ) . '-' . wp_generate_password( 6, false, false ),
				'post_status'   => $status,
				'post_password' => $password,
			)
		);
		$item                = new ContentItem();
		$item->object_id     = $post_id;
		$item->object_type   = 'post';
		$item->post_type     = 'post';
		$item->canonical_url = get_permalink( $post_id );
		$item->url_hash      = md5( $item->canonical_url );
		$item->language_code = $language;
		$item->metadata      = array( 'title' => $title );
		$item->id            = $this->content_repo->save( $item );

		return $item;
	}
}
