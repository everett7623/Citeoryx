<?php
/**
 * Search Console REST controller.
 *
 * @package Citeoryx\Rest\Controllers
 */

namespace Citeoryx\Rest\Controllers;

use Citeoryx\Application\Search\SearchIntegrationHealth;
use Citeoryx\Core\Capabilities;
use Citeoryx\Integrations\SearchConsole\GoogleOAuth;
use Citeoryx\Integrations\SearchConsole\GoogleSearchConsole;
use WP_REST_Request;

/**
 * Exposes Google Search Console integration endpoints.
 */
class SearchConsoleController extends BaseController {

	/**
	 * Register routes.
	 *
	 * @param string $namespace REST namespace.
	 * @return void
	 */
	public function register( string $namespace ): void {
		register_rest_route(
			$namespace,
			'/integrations/gsc',
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
			'/integrations/gsc/client',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'save_client' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'client_id'     => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
						'client_secret' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/integrations/gsc/disconnect',
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
			'/integrations/gsc/validate',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'validate_connection' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/integrations/gsc/metrics',
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
			'/integrations/gsc/queries',
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
			'/integrations/gsc/sites',
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
		$oauth             = new GoogleOAuth();
		$client            = $oauth->get_client();
		$connection_result = get_transient( 'citeoryx_gsc_connection_result' );

		if ( $connection_result ) {
			delete_transient( 'citeoryx_gsc_connection_result' );
		}

		return $this->success(
			array(
				'connected'         => $oauth->is_connected(),
				'has_credentials'   => ! empty( $client['client_id'] ),
				'auth_url'          => $oauth->get_auth_url(),
				'redirect_uri'      => $oauth->get_redirect_uri(),
				'connection_result' => $connection_result ?: null,
				'health'            => $this->container->get( SearchIntegrationHealth::class )->get( 'google_search_console' ),
			)
		);
	}

	/**
	 * Save OAuth client credentials.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function save_client( WP_REST_Request $request ): \WP_REST_Response {
		$oauth = new GoogleOAuth();
		$oauth->save_client(
			(string) $request->get_param( 'client_id' ),
			(string) $request->get_param( 'client_secret' )
		);
		$this->container->get( SearchIntegrationHealth::class )->clear( 'google_search_console' );

		return $this->success(
			array(
				'saved'    => true,
				'auth_url' => $oauth->get_auth_url(),
			)
		);
	}

	/**
	 * Disconnect from GSC.
	 *
	 * @return \WP_REST_Response
	 */
	public function disconnect(): \WP_REST_Response {
		$oauth = new GoogleOAuth();
		$oauth->disconnect();
		$this->container->get( SearchIntegrationHealth::class )->clear( 'google_search_console' );
		return $this->success( array( 'disconnected' => true ) );
	}

	/**
	 * Validate the current Google Search Console connection.
	 *
	 * @return \WP_REST_Response
	 */
	public function validate_connection(): \WP_REST_Response {
		$gsc    = $this->container->get( GoogleSearchConsole::class );
		$health = $this->container->get( SearchIntegrationHealth::class );
		$result = $gsc->validate_connection();

		if ( $result['valid'] ) {
			$state = $health->record_success( 'google_search_console', $result['message'] );
		} elseif ( 'error' === $result['status'] ) {
			$state = $health->record_failure( 'google_search_console', $result['message'] );
		} else {
			$health->clear( 'google_search_console' );
			$state = $health->get( 'google_search_console' );
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
		$gsc = new GoogleSearchConsole( new GoogleOAuth() );
		if ( ! $gsc->is_connected() ) {
			return $this->error( __( 'Google Search Console is not connected.', 'citeoryx' ), 400 );
		}

		$end   = $request->get_param( 'end_date' ) ?: gmdate( 'Y-m-d', strtotime( '-3 days' ) );
		$start = $request->get_param( 'start_date' ) ?: gmdate( 'Y-m-d', strtotime( '-35 days' ) );

		$rows = $gsc->get_metrics( $start, $end );
		return $this->success(
			array(
				'start_date' => $start,
				'end_date'   => $end,
				'rows'       => $rows,
			)
		);
	}

	/**
	 * Get search queries for a specific URL.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_queries_for_url( WP_REST_Request $request ): \WP_REST_Response {
		$gsc = new GoogleSearchConsole( new GoogleOAuth() );
		if ( ! $gsc->is_connected() ) {
			return $this->error( __( 'Google Search Console is not connected.', 'citeoryx' ), 400 );
		}

		$url   = (string) $request->get_param( 'url' );
		$end   = $request->get_param( 'end_date' ) ?: gmdate( 'Y-m-d', strtotime( '-3 days' ) );
		$start = $request->get_param( 'start_date' ) ?: gmdate( 'Y-m-d', strtotime( '-35 days' ) );

		$queries = $gsc->get_queries_for_url( $url, $start, $end );
		return $this->success(
			array(
				'url'     => $url,
				'queries' => $queries,
			)
		);
	}

	/**
	 * List verified sites.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_sites(): \WP_REST_Response {
		$gsc = new GoogleSearchConsole( new GoogleOAuth() );
		if ( ! $gsc->is_connected() ) {
			return $this->error( __( 'Google Search Console is not connected.', 'citeoryx' ), 400 );
		}

		return $this->success( array( 'sites' => $gsc->list_sites() ) );
	}
}
