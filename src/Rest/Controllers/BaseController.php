<?php
/**
 * Base REST controller.
 *
 * @package Citeoryx\Rest\Controllers
 */

namespace Citeoryx\Rest\Controllers;

use Citeoryx\Core\Container;
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
		return new WP_REST_Response( array( 'success' => true, 'data' => $data ), $status );
	}

	/**
	 * Create error response.
	 *
	 * @param string $message Error message.
	 * @param int    $status HTTP status.
	 * @return WP_REST_Response
	 */
	protected function error( string $message, int $status = 400 ): WP_REST_Response {
		return new WP_REST_Response( array( 'success' => false, 'message' => $message ), $status );
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
