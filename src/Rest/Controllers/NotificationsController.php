<?php
/**
 * Notifications REST controller.
 *
 * @package Citeoryx\Rest\Controllers
 */

namespace Citeoryx\Rest\Controllers;

use Citeoryx\Application\Notifications\WeeklyDigest;
use Citeoryx\Core\Capabilities;
use WP_REST_Request;

/**
 * Provides notification actions for administrators.
 */
class NotificationsController extends BaseController {

	/**
	 * Register notification routes.
	 *
	 * @param string $namespace Namespace.
	 * @return void
	 */
	public function register( string $namespace ): void {
		register_rest_route(
			$namespace,
			'/notifications/test',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'send_test' ),
					'permission_callback' => array( $this, 'permissions_check' ),
				),
			)
		);
	}

	/**
	 * Check notification permissions.
	 *
	 * @return bool
	 */
	public function permissions_check(): bool {
		return $this->check_cap( Capabilities::MANAGE_SETTINGS );
	}

	/**
	 * Send one test email.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function send_test( WP_REST_Request $request ): \WP_REST_Response {
		$params = $request->get_json_params();
		$email  = is_array( $params ) ? ( $params['email'] ?? null ) : null;
		if ( null !== $email && ( ! is_string( $email ) || ! is_email( sanitize_email( $email ) ) ) ) {
			return $this->error( __( '通知邮箱地址无效。', 'citeoryx' ), 400 );
		}

		$result = $this->container->get( WeeklyDigest::class )->send_test( $email ? sanitize_email( $email ) : null );
		if ( 'sent' !== $result['status'] ) {
			return $this->error( $result['message'], 'failed' === $result['status'] ? 502 : 400 );
		}

		return $this->success( $result );
	}
}
