<?php
/**
 * Content REST controller.
 *
 * @package Citeoryx\Rest\Controllers
 */

namespace Citeoryx\Rest\Controllers;

use Citeoryx\Core\Capabilities;
use Citeoryx\Domain\Content\ContentRepository;
use Citeoryx\Application\Scan\ContentScanner;
use Citeoryx\Application\Analyze\IssueEngine;
use WP_REST_Request;

/**
 * Content inventory endpoints.
 */
class ContentController extends BaseController {

	/**
	 * Register routes.
	 *
	 * @param string $namespace Namespace.
	 * @return void
	 */
	public function register( string $namespace ): void {
		register_rest_route(
			$namespace,
			'/content',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_content' ),
					'permission_callback' => array( $this, 'get_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/content/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/content/(?P<id>\d+)/scan',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'scan_item' ),
					'permission_callback' => array( $this, 'scan_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Permission check.
	 *
	 * @return bool
	 */
	public function get_permissions_check(): bool {
		return $this->check_cap( Capabilities::VIEW_CONTENT );
	}

	/**
	 * Scan permission check.
	 *
	 * @return bool
	 */
	public function scan_permissions_check(): bool {
		return $this->check_cap( Capabilities::RUN_SCANS );
	}

	/**
	 * List content.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function list_content( WP_REST_Request $request ): \WP_REST_Response {
		$repo = $this->container->get( ContentRepository::class );

		$filters = array();
		if ( $request->get_param( 'status' ) ) {
			$filters['status'] = sanitize_text_field( $request->get_param( 'status' ) );
		}
		if ( $request->get_param( 'post_type' ) ) {
			$filters['post_type'] = sanitize_text_field( $request->get_param( 'post_type' ) );
		}
		if ( $request->get_param( 'search' ) ) {
			$filters['search'] = sanitize_text_field( $request->get_param( 'search' ) );
		}

		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) );

		$result = $repo->list( $filters, $page, $per_page );

		return $this->success(
			array(
				'items' => array_map( static fn ( $i ) => $i->to_array(), $result['items'] ),
				'total' => $result['total'],
				'page'  => $page,
			)
		);
	}

	/**
	 * Get single item.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_item( WP_REST_Request $request ): \WP_REST_Response {
		$repo = $this->container->get( ContentRepository::class );
		$item = $repo->find( (int) $request->get_param( 'id' ) );

		if ( ! $item ) {
			return $this->error( __( 'Content not found.', 'citeoryx' ), 404 );
		}

		return $this->success( $item->to_array() );
	}

	/**
	 * Scan single item.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function scan_item( WP_REST_Request $request ): \WP_REST_Response {
		$scanner = $this->container->get( ContentScanner::class );
		$engine  = $this->container->get( IssueEngine::class );
		$repo    = $this->container->get( ContentRepository::class );

		$id   = (int) $request->get_param( 'id' );
		$item = $repo->find( $id );

		if ( ! $item || ! $item->object_id ) {
			return $this->error( __( 'Content not found.', 'citeoryx' ), 404 );
		}

		$scanned = $scanner->scan_post( $item->object_id, $item->post_type ?: 'post' );
		if ( ! $scanned ) {
			return $this->error( __( 'Unable to scan content.', 'citeoryx' ), 422 );
		}

		$engine->analyze( $scanned );

		return $this->success( $scanned->to_array() );
	}
}
