<?php
/**
 * Content repository unit test example.
 *
 * @package Citeoryx\Tests\Unit
 */

namespace Citeoryx\Tests\Unit;

use Citeoryx\Domain\Content\ContentItem;
use Citeoryx\Domain\Content\ContentRepository;
use WP_UnitTestCase;

/**
 * Tests for ContentRepository.
 */
class ContentRepositoryTest extends WP_UnitTestCase {

	private ContentRepository $repository;

	/**
	 * Set up.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->repository = new ContentRepository();
	}

	/**
	 * Test save and find.
	 *
	 * @return void
	 */
	public function test_save_and_find(): void {
		$item                = new ContentItem();
		$item->object_type   = 'post';
		$item->post_type     = 'post';
		$item->canonical_url = home_url( '/hello-world' );
		$item->url_hash      = md5( $item->canonical_url );
		$item->status        = 'healthy';

		$id = $this->repository->save( $item );
		$this->assertGreaterThan( 0, $id );

		$found = $this->repository->find( $id );
		$this->assertNotNull( $found );
		$this->assertSame( $item->canonical_url, $found->canonical_url );
	}

	/**
	 * Test find by url hash.
	 *
	 * @return void
	 */
	public function test_find_by_url_hash(): void {
		$item                = new ContentItem();
		$item->object_type   = 'post';
		$item->canonical_url = home_url( '/test-url-hash' );
		$item->url_hash      = md5( $item->canonical_url );

		$this->repository->save( $item );

		$found = $this->repository->find_by_url_hash( $item->url_hash );
		$this->assertNotNull( $found );
		$this->assertSame( $item->canonical_url, $found->canonical_url );
	}
}
