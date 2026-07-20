<?php
/**
 * Issues REST controller.
 *
 * @package Citeoryx\Rest\Controllers
 */

namespace Citeoryx\Rest\Controllers;

use Citeoryx\Core\Capabilities;
use Citeoryx\Domain\Issue\IssueRepository;
use WP_REST_Request;

/**
 * Issues endpoints.
 */
class IssuesController extends BaseController {

	/**
	 * Register routes.
	 *
	 * @param string $namespace Namespace.
	 * @return void
	 */
	public function register( string $namespace ): void {
		register_rest_route(
			$namespace,
			'/issues',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_issues' ),
					'permission_callback' => array( $this, 'get_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/issues/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'PATCH',
					'callback'            => array( $this, 'update_issue' ),
					'permission_callback' => array( $this, 'manage_permissions_check' ),
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
	 * Manage permission check.
	 *
	 * @return bool
	 */
	public function manage_permissions_check(): bool {
		return $this->check_cap( Capabilities::MANAGE_ISSUES );
	}

	/**
	 * List issues.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function list_issues( WP_REST_Request $request ): \WP_REST_Response {
		$repo = $this->container->get( IssueRepository::class );

		$filters = array();
		if ( $request->get_param( 'status' ) ) {
			$filters['status'] = sanitize_text_field( $request->get_param( 'status' ) );
		}
		if ( $request->get_param( 'category' ) ) {
			$filters['category'] = sanitize_text_field( $request->get_param( 'category' ) );
		}
		if ( $request->get_param( 'severity' ) ) {
			$filters['severity'] = sanitize_text_field( $request->get_param( 'severity' ) );
		}
		if ( $request->get_param( 'content_id' ) ) {
			$filters['content_id'] = (int) $request->get_param( 'content_id' );
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
	 * Update issue.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function update_issue( WP_REST_Request $request ): \WP_REST_Response {
		$repo   = $this->container->get( IssueRepository::class );
		$issue  = $repo->find( (int) $request->get_param( 'id' ) );

		if ( ! $issue ) {
			return $this->error( __( 'Issue not found.', 'citeoryx' ), 404 );
		}

		$params = $request->get_json_params();

		if ( isset( $params['status'] ) ) {
			$issue->status = sanitize_text_field( $params['status'] );
			if ( 'resolved' === $issue->status ) {
				$issue->resolved_at = current_time( 'mysql' );
			}
		}
		if ( isset( $params['assigned_user_id'] ) ) {
			$issue->assigned_user_id = (int) $params['assigned_user_id'];
		}
		if ( isset( $params['ignored_until'] ) ) {
			$issue->ignored_until = sanitize_text_field( $params['ignored_until'] );
		}

		$repo->save( $issue );

		return $this->success( $issue->to_array() );
	}
}
