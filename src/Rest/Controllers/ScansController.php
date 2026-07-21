<?php
/**
 * Scans REST controller.
 *
 * @package Citeoryx\Rest\Controllers
 */

namespace Citeoryx\Rest\Controllers;

use Citeoryx\Core\Capabilities;
use Citeoryx\Domain\Scan\ScanRunRepository;
use Citeoryx\Infrastructure\Queue\Scheduler;
use WP_REST_Request;

/**
 * Scan task endpoints.
 */
class ScansController extends BaseController {

	/**
	 * Register routes.
	 *
	 * @param string $namespace Namespace.
	 * @return void
	 */
	public function register( string $namespace ): void {
		register_rest_route(
			$namespace,
			'/scans',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_scan' ),
					'permission_callback' => array( $this, 'scan_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/scans/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_scan' ),
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
	public function scan_permissions_check(): bool {
		return $this->check_cap( Capabilities::RUN_SCANS );
	}

	/**
	 * Create a scan run.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function create_scan( WP_REST_Request $request ): \WP_REST_Response {
		$params = $request->get_json_params();
		$params = is_array( $params ) ? $params : array();
		$value  = $params['scan_type'] ?? 'full';
		if ( ! is_scalar( $value ) ) {
			return $this->error( __( 'Invalid scan type.', 'citeoryx' ), 400 );
		}
		$scan_type = sanitize_text_field( (string) $value );
		if ( ! in_array( $scan_type, array( 'full', 'incremental' ), true ) ) {
			return $this->error( __( 'Invalid scan type.', 'citeoryx' ), 400 );
		}

		$scheduler = $this->container->get( Scheduler::class );
		$run       = $scheduler->enqueue_scan(
			$scan_type,
			'manual',
			array( 'requested_by' => get_current_user_id() )
		);

		return $this->success( $run->to_array(), 202 );
	}

	/**
	 * Get scan progress.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_scan( WP_REST_Request $request ): \WP_REST_Response {
		$repo = $this->container->get( ScanRunRepository::class );
		$run  = $repo->find( (int) $request->get_param( 'id' ) );

		if ( ! $run ) {
			return $this->error( __( 'Scan not found.', 'citeoryx' ), 404 );
		}

		return $this->success( $run->to_array() );
	}
}
