<?php
/**
 * Base REST controller.
 *
 * @package Citeoryx\Rest\Controllers
 */

namespace Citeoryx\Rest\Controllers;

use Citeoryx\Core\Container;
use Citeoryx\Core\Capabilities;
use Citeoryx\Domain\Content\ContentItem;
use Citeoryx\Domain\Content\ContentRepository;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Abstract base controller.
 */
abstract class BaseController extends WP_REST_Controller {

	protected Container $container;

	public function __construct( Container $container ) {
		$this->container = $container;
		$this->namespace = CITEORYX_REST_NAMESPACE;
	}

	/**
	 * Create success response.
	 *
	 * @param mixed $data Response data.
	 * @param int   $status HTTP status.
	 * @return WP_REST_Response
	 */
	protected function success( $data, int $status = 200 ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => $data,
			),
			$status
		);
	}

	/**
	 * Create error response.
	 *
	 * @param string $message Error message.
	 * @param int    $status HTTP status.
	 * @return WP_REST_Response
	 */
	protected function error( string $message, int $status = 400 ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'success' => false,
				'message' => $message,
			),
			$status
		);
	}

	/**
	 * Check permission for a capability.
	 *
	 * @param string $capability Capability.
	 * @return bool
	 */
	protected function check_cap( string $capability ): bool {
		return current_user_can( $capability );
	}

	/**
	 * Get the author scope required for the current user.
	 *
	 * Users with dashboard access can view site-wide data. Other content
	 * viewers are restricted to WordPress posts they authored.
	 *
	 * @return int|null Author ID, or null for unrestricted access.
	 */
	protected function content_author_scope(): ?int {
		return current_user_can( Capabilities::VIEW_DASHBOARD ) ? null : get_current_user_id();
	}

	/**
	 * Determine whether the current user can access a content item.
	 *
	 * @param ContentItem $item Content item.
	 * @return bool
	 */
	protected function can_access_content( ContentItem $item ): bool {
		if ( null === $this->content_author_scope() ) {
			return true;
		}

		if ( 'post' !== $item->object_type || ! $item->object_id ) {
			return false;
		}

		return get_current_user_id() === (int) get_post_field( 'post_author', $item->object_id );
	}

	/**
	 * Check access to a content ID while allowing the controller to return 404.
	 *
	 * @param int $content_id Content item ID.
	 * @return bool
	 */
	protected function can_access_content_id( int $content_id ): bool {
		$item = $this->container->get( ContentRepository::class )->find( $content_id );
		return ! $item || $this->can_access_content( $item );
	}

	/**
	 * Validate REST nonce.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	protected function verify_nonce( WP_REST_Request $request ): bool {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce ) {
			$nonce = $request->get_param( '_wpnonce' );
		}
		return wp_verify_nonce( $nonce, 'wp_rest' ) !== false;
	}
}
