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
					'permission_callback' => array( $this, 'list_permissions_check' ),
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
	public function list_permissions_check(): bool {
		return $this->check_cap( Capabilities::VIEW_CONTENT );
	}

	/**
	 * Manage permission check.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function manage_permissions_check( WP_REST_Request $request ): bool {
		if ( ! $this->check_cap( Capabilities::MANAGE_ISSUES ) ) {
			return false;
		}

		$issue = $this->container->get( IssueRepository::class )->find( (int) $request->get_param( 'id' ) );
		if ( ! $issue || null === $this->content_author_scope() ) {
			return true;
		}

		return $issue->content_id && $this->can_access_content_id( $issue->content_id );
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
		foreach ( array( 'status', 'category', 'severity' ) as $filter_key ) {
			$value = $request->get_param( $filter_key );
			if ( is_scalar( $value ) && '' !== (string) $value ) {
				$filters[ $filter_key ] = sanitize_text_field( (string) $value );
			}
		}
		$content_id = $request->get_param( 'content_id' );
		if ( is_scalar( $content_id ) && (int) $content_id > 0 ) {
			$filters['content_id'] = (int) $content_id;
		}
		$author_id = $this->content_author_scope();
		if ( null !== $author_id ) {
			$filters['author_id'] = $author_id;
		}

		$page_param     = $request->get_param( 'page' );
		$per_page_param = $request->get_param( 'per_page' );
		$page           = is_scalar( $page_param ) ? max( 1, (int) $page_param ) : 1;
		$per_page       = is_scalar( $per_page_param ) ? min( 100, max( 1, (int) $per_page_param ) ) : 20;

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
		$repo  = $this->container->get( IssueRepository::class );
		$issue = $repo->find( (int) $request->get_param( 'id' ) );

		if ( ! $issue ) {
			return $this->error( __( 'Issue not found.', 'citeoryx' ), 404 );
		}

		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			return $this->error( __( '问题更新请求格式无效。', 'citeoryx' ), 400 );
		}

		if ( array_key_exists( 'status', $params ) ) {
			if ( ! is_scalar( $params['status'] ) ) {
				return $this->error( __( '问题状态格式无效。', 'citeoryx' ), 400 );
			}
			$status = sanitize_key( (string) $params['status'] );
			if ( ! in_array( $status, array( 'open', 'resolved', 'ignored', 'in_progress' ), true ) ) {
				return $this->error( __( '问题状态无效。', 'citeoryx' ), 400 );
			}
			$issue->status = $status;
			if ( 'resolved' === $issue->status ) {
				$issue->resolved_at = current_time( 'mysql' );
			} else {
				$issue->resolved_at = null;
			}
		}
		if ( array_key_exists( 'assigned_user_id', $params ) ) {
			if ( null === $params['assigned_user_id'] ) {
				$issue->assigned_user_id = null;
				$assigned_user_id        = 0;
			} elseif ( is_int( $params['assigned_user_id'] ) || ( is_string( $params['assigned_user_id'] ) && ctype_digit( $params['assigned_user_id'] ) ) ) {
				$assigned_user_id = absint( $params['assigned_user_id'] );
			} else {
				return $this->error( __( '负责人格式无效。', 'citeoryx' ), 400 );
			}
			if ( $assigned_user_id && ! get_userdata( $assigned_user_id ) ) {
				return $this->error( __( '负责人不存在。', 'citeoryx' ), 400 );
			}
			if ( null !== $params['assigned_user_id'] ) {
				$issue->assigned_user_id = $assigned_user_id ?: null;
			}
		}
		if ( array_key_exists( 'ignored_until', $params ) ) {
			if ( null !== $params['ignored_until'] && ! is_scalar( $params['ignored_until'] ) ) {
				return $this->error( __( '忽略期限格式无效。', 'citeoryx' ), 400 );
			}
			$issue->ignored_until = null === $params['ignored_until'] ? null : sanitize_text_field( (string) $params['ignored_until'] );
		}

		$repo->save( $issue );

		return $this->success( $issue->to_array() );
	}
}
