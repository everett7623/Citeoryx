<?php
/**
 * Planning REST controller.
 *
 * @package Citeoryx\Rest\Controllers
 */

namespace Citeoryx\Rest\Controllers;

use Citeoryx\Application\Planning\TopicOpportunityFinder;
use Citeoryx\Application\Planning\PlanningCalendar;
use Citeoryx\Core\Capabilities;
use WP_REST_Request;

/**
 * Exposes topic opportunities, publishing plans, and review reminders.
 */
class PlanningController extends BaseController {

	/**
	 * Register planning routes.
	 *
	 * @param string $namespace REST namespace.
	 * @return void
	 */
	public function register( string $namespace ): void {
		register_rest_route(
			$namespace,
			'/planning/opportunities',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_opportunities' ),
					'permission_callback' => array( $this, 'get_permissions_check' ),
					'args'                => $this->get_args(),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/planning/calendar',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_calendar' ),
					'permission_callback' => array( $this, 'get_permissions_check' ),
					'args'                => $this->calendar_args(),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/planning/reviews/(?P<id>\d+)/complete',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'complete_review' ),
					'permission_callback' => array( $this, 'manage_review_permissions_check' ),
					'args'                => array(
						'id' => array(
							'sanitize_callback' => 'absint',
							'validate_callback' => static fn ( $value ): bool => is_numeric( $value ) && (int) $value >= 1,
						),
					),
				),
			)
		);
	}

	/**
	 * Check site-wide planning access.
	 *
	 * @return bool
	 */
	public function get_permissions_check(): bool {
		return $this->check_cap( Capabilities::VIEW_DASHBOARD );
	}

	/**
	 * Check whether a user may complete a site-wide review reminder.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return bool
	 */
	public function manage_review_permissions_check( WP_REST_Request $request ): bool {
		return $this->check_cap( Capabilities::VIEW_DASHBOARD )
			&& $this->check_cap( Capabilities::MANAGE_ISSUES )
			&& $this->can_access_content_id( (int) $request->get_param( 'id' ) );
	}

	/**
	 * Return topic opportunities.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function get_opportunities( WP_REST_Request $request ): \WP_REST_Response {
		$finder = $this->container->get( TopicOpportunityFinder::class );
		return $this->success(
			$finder->find(
				array(
					'type'   => (string) $request->get_param( 'type' ),
					'source' => (string) $request->get_param( 'source' ),
					'days'   => (int) ( $request->get_param( 'days' ) ?: 28 ),
				),
				(int) ( $request->get_param( 'page' ) ?: 1 ),
				(int) ( $request->get_param( 'per_page' ) ?: 20 )
			)
		);
	}

	/**
	 * Return publishing and review calendar data.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function get_calendar( WP_REST_Request $request ): \WP_REST_Response {
		$calendar = $this->container->get( PlanningCalendar::class );
		return $this->success(
			$calendar->get(
				(int) ( $request->get_param( 'horizon_days' ) ?: 90 ),
				(int) ( $request->get_param( 'limit' ) ?: 50 )
			)
		);
	}

	/**
	 * Mark one content item as reviewed.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return \WP_REST_Response
	 */
	public function complete_review( WP_REST_Request $request ): \WP_REST_Response {
		$calendar = $this->container->get( PlanningCalendar::class );
		$result   = $calendar->complete_review( (int) $request->get_param( 'id' ) );
		if ( ! $result ) {
			return $this->error( __( '内容不存在。', 'citeoryx' ), 404 );
		}
		return $this->success( $result );
	}

	/**
	 * Define and validate query arguments.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_args(): array {
		return array(
			'page'     => array(
				'default'           => 1,
				'sanitize_callback' => 'absint',
				'validate_callback' => static fn ( $value ): bool => is_numeric( $value ) && (int) $value >= 1,
			),
			'per_page' => array(
				'default'           => 20,
				'sanitize_callback' => 'absint',
				'validate_callback' => static fn ( $value ): bool => is_numeric( $value ) && (int) $value >= 1 && (int) $value <= 100,
			),
			'days'     => array(
				'default'           => 28,
				'sanitize_callback' => 'absint',
				'validate_callback' => static fn ( $value ): bool => is_numeric( $value ) && (int) $value >= 7 && (int) $value <= 90,
			),
			'type'     => array(
				'default'           => '',
				'sanitize_callback' => 'sanitize_key',
				'validate_callback' => static fn ( $value ): bool => in_array(
					$value,
					array( '', 'striking_distance', 'refresh_before_new', 'topic_gap_candidate' ),
					true
				),
			),
			'source'   => array(
				'default'           => '',
				'sanitize_callback' => 'sanitize_key',
				'validate_callback' => static fn ( $value ): bool => in_array(
					$value,
					array( '', 'google_search_console', 'bing_webmaster_tools' ),
					true
				),
			),
		);
	}

	/**
	 * Define calendar query arguments.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function calendar_args(): array {
		return array(
			'horizon_days' => array(
				'default'           => 90,
				'sanitize_callback' => 'absint',
				'validate_callback' => static fn ( $value ): bool => is_numeric( $value ) && (int) $value >= 7 && (int) $value <= 365,
			),
			'limit'        => array(
				'default'           => 50,
				'sanitize_callback' => 'absint',
				'validate_callback' => static fn ( $value ): bool => is_numeric( $value ) && (int) $value >= 1 && (int) $value <= 100,
			),
		);
	}
}
