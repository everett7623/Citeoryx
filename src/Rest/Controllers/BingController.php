<?php
/**
 * Bing Webmaster Tools REST controller.
 *
 * @package Citeoryx\Rest\Controllers
 */

namespace Citeoryx\Rest\Controllers;

use Citeoryx\Core\Capabilities;
use Citeoryx\Integrations\SearchConsole\BingWebmasterTools;
use WP_REST_Request;

/**
 * Exposes Bing Webmaster Tools integration endpoints.
 */
class BingController extends BaseController {

	/**
	 * Register routes.
	 *
	 * @param string $namespace REST namespace.
	 * @return void
	 */
	public function register( string $namespace ): void {
		register_rest_route(
			$namespace,
			'/integrations/bing',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_status' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/integrations/bing/settings',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'save_settings' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'api_key' => array(
							'required' => true,
							'type'     => 'string',
						),
					),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/integrations/bing/disconnect',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'disconnect' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/integrations/bing/metrics',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_metrics' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'start_date' => array(
							'required' => false,
							'type'     => 'string',
						),
						'end_date'   => array(
							'required' => false,
							'type'     => 'string',
						),
					),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/integrations/bing/queries',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_queries_for_url' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'url'        => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'esc_url_raw',
						),
						'start_date' => array(
							'required' => false,
							'type'     => 'string',
						),
						'end_date'   => array(
							'required' => false,
							'type'     => 'string',
						),
					),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/integrations/bing/sites',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_sites' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);
	}

	/**
	 * Permission check.
	 *
	 * @return bool
	 */
	public function check_permission(): bool {
		return $this->check_cap( Capabilities::MANAGE_INTEGRATIONS );
	}

	/**
	 * Get connection status.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_status(): \WP_REST_Response {
		$bing = new BingWebmasterTools();
		return $this->success( array( 'connected' => $bing->is_connected() ) );
	}

	/**
	 * Save API key.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function save_settings( WP_REST_Request $request ): \WP_REST_Response {
		$api_key = sanitize_text_field( (string) $request->get_param( 'api_key' ) );
		BingWebmasterTools::save_api_key( $api_key );
		return $this->success(
			array(
				'saved'     => true,
				'connected' => ! empty( $api_key ),
			)
		);
	}

	/**
	 * Disconnect.
	 *
	 * @return \WP_REST_Response
	 */
	public function disconnect(): \WP_REST_Response {
		BingWebmasterTools::delete_api_key();
		return $this->success( array( 'disconnected' => true ) );
	}

	/**
	 * Get site-level metrics.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_metrics( WP_REST_Request $request ): \WP_REST_Response {
		$bing = new BingWebmasterTools();
		if ( ! $bing->is_connected() ) {
			return $this->error( __( 'Bing Webmaster Tools is not connected.', 'citeoryx' ), 400 );
		}

		$end   = $request->get_param( 'end_date' ) ?: gmdate( 'Y-m-d', strtotime( '-3 days' ) );
		$start = $request->get_param( 'start_date' ) ?: gmdate( 'Y-m-d', strtotime( '-35 days' ) );

		$rows = $bing->get_metrics( $start, $end );
		return $this->success(
			array(
				'start_date' => $start,
				'end_date'   => $end,
				'rows'       => $rows,
			)
		);
	}

	/**
	 * Get queries for a URL.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_queries_for_url( WP_REST_Request $request ): \WP_REST_Response {
		$bing = new BingWebmasterTools();
		if ( ! $bing->is_connected() ) {
			return $this->error( __( 'Bing Webmaster Tools is not connected.', 'citeoryx' ), 400 );
		}

		$url   = (string) $request->get_param( 'url' );
		$end   = $request->get_param( 'end_date' ) ?: gmdate( 'Y-m-d', strtotime( '-3 days' ) );
		$start = $request->get_param( 'start_date' ) ?: gmdate( 'Y-m-d', strtotime( '-35 days' ) );

		$queries = $bing->get_queries_for_url( $url, $start, $end );
		return $this->success(
			array(
				'url'     => $url,
				'queries' => $queries,
			)
		);
	}

	/**
	 * List sites.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_sites(): \WP_REST_Response {
		$bing = new BingWebmasterTools();
		if ( ! $bing->is_connected() ) {
			return $this->error( __( 'Bing Webmaster Tools is not connected.', 'citeoryx' ), 400 );
		}

		return $this->success( array( 'sites' => $bing->list_sites() ) );
	}
}
