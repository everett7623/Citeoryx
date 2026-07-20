<?php
/**
 * Optimizer REST controller.
 *
 * @package Citeoryx\Rest\Controllers
 */

namespace Citeoryx\Rest\Controllers;

use Citeoryx\Application\Optimize\Optimizer;
use Citeoryx\Core\Container;
use Citeoryx\Core\Capabilities;
use WP_REST_Request;

/**
 * Provides content optimization recommendations.
 */
class OptimizerController extends BaseController {

	/**
	 * Register routes.
	 *
	 * @param string $namespace REST namespace.
	 * @return void
	 */
	public function register( string $namespace ): void {
		register_rest_route(
			$namespace,
			'/optimizer/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_recommendations' ),
					'permission_callback' => array( $this, 'can_manage_issues' ),
					'args'                => array(
						'id' => array(
							'required'          => true,
							'validate_callback' => static fn ( $param ) => is_numeric( $param ) && (int) $param > 0,
							'sanitize_callback' => 'absint',
						),
					),
				),
			)
		);
	}

	/**
	 * Get recommendations for content.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_recommendations( WP_REST_Request $request ): \WP_REST_Response {
		$id        = (int) $request->get_param( 'id' );
		$optimizer = $this->container->get( Optimizer::class );
		$data      = $optimizer->get_recommendations( $id );

		if ( isset( $data['error'] ) ) {
			return $this->error( $data['error'], 404 );
		}

		return $this->success( $data );
	}

	/**
	 * Permission callback.
	 *
	 * @return bool
	 */
	public function can_manage_issues(): bool {
		return current_user_can( Capabilities::MANAGE_ISSUES );
	}
}
