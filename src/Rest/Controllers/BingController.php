<?php
/**
 * Bing Webmaster Tools REST controller.
 *
 * @package Citeoryx\Rest\Controllers
 */

namespace Citeoryx\Rest\Controllers;

use Citeoryx\Application\Search\SearchIntegrationHealth;
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
	 * @param string $rest_namespace REST namespace.
	 * @return void
	 */
	public function register( string $rest_namespace ): void {
		register_rest_route(
			$rest_namespace,
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
			$rest_namespace,
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
			$rest_namespace,
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
			$rest_namespace,
			'/integrations/bing/validate',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'validate_connection' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		register_rest_route(
			$rest_namespace,
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
			$rest_namespace,
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
			$rest_namespace,
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
		return $this->success(
			array(
				'connected' => $bing->is_connected(),
				'health'    => $this->container->get( SearchIntegrationHealth::class )->get( 'bing_webmaster_tools' ),
			)
		);
	}

	/**
	 * Save API key.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function save_settings( WP_REST_Request $request ): \WP_REST_Response {

		$api_key = sanitize_text_field( (string) $request->get_param( 'api_key' ) );
		if ( '' === $api_key ) {
			return $this->error( __( 'Bing Webmaster Tools API Key is required.', 'citeoryx' ), 400 );
		}
		if ( ! BingWebmasterTools::save_api_key( $api_key ) ) {
			return $this->error( __( 'Unable to store the Bing Webmaster Tools API Key securely.', 'citeoryx' ), 500 );
		}
		$this->container->get( SearchIntegrationHealth::class )->clear( 'bing_webmaster_tools' );
		return $this->success(
			array(
				'saved'     => true,
				'connected' => true,
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
		$this->container->get( SearchIntegrationHealth::class )->clear( 'bing_webmaster_tools' );
		return $this->success( array( 'disconnected' => true ) );
	}

	/**
	 * Validate the current Bing Webmaster Tools connection.
	 *
	 * @return \WP_REST_Response
	 */
	public function validate_connection(): \WP_REST_Response {
		$bing   = $this->container->get( BingWebmasterTools::class );
		$health = $this->container->get( SearchIntegrationHealth::class );
		$result = $bing->validate_connection();

		if ( $result['valid'] ) {
			$state = $health->record_success( 'bing_webmaster_tools', $result['message'] );
		} elseif ( 'error' === $result['status'] ) {
			$state = $health->record_failure( 'bing_webmaster_tools', $result['message'] );
		} else {
			$health->clear( 'bing_webmaster_tools' );
			$state = $health->get( 'bing_webmaster_tools' );
		}

		$result['health'] = $state;
		return $this->success( $result );
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

		$end   = $this->date_param( $request, 'end_date', '-3 days' );
		$start = $this->date_param( $request, 'start_date', '-35 days' );

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
		$end   = $this->date_param( $request, 'end_date', '-3 days' );
		$start = $this->date_param( $request, 'start_date', '-35 days' );

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

	/**
	 * Resolve an optional date parameter with a relative UTC fallback.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param string          $name Parameter name.
	 * @param string          $fallback Relative date fallback.
	 * @return string
	 */
	private function date_param( WP_REST_Request $request, string $name, string $fallback ): string {
		$value = $request->get_param( $name );
		return is_string( $value ) && '' !== $value ? $value : gmdate( 'Y-m-d', strtotime( $fallback ) );
	}
}
