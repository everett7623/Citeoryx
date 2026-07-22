<?php
/**
 * Optimizer REST controller.
 *
 * @package Citeoryx\Rest\Controllers
 */

namespace Citeoryx\Rest\Controllers;

use Citeoryx\Application\Optimize\Optimizer;
use Citeoryx\Application\Optimize\RevisionDraftService;
use Citeoryx\Core\Container;
use Citeoryx\Core\Capabilities;
use Citeoryx\Domain\Content\ContentRepository;
use WP_Error;
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
					'permission_callback' => array( $this, 'can_view_recommendations' ),
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

		register_rest_route(
			$namespace,
			'/recommendations/apply',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'apply_revision' ),
					'permission_callback' => array( $this, 'can_apply_revision' ),
					'args'                => $this->revision_args(),
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
		$data['editor'] = $this->container->get( RevisionDraftService::class )->get_snapshot( $id );

		return $this->success( $data );
	}

	/**
	 * Create a reviewable WordPress revision.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function apply_revision( WP_REST_Request $request ): \WP_REST_Response {
		$proposal = $this->sanitize_revision_request( $request );
		if ( is_wp_error( $proposal ) ) {
			return $this->error( $proposal->get_error_message(), 400 );
		}

		$result = $this->container->get( RevisionDraftService::class )->create(
			(int) $request->get_param( 'content_id' ),
			$proposal
		);
		if ( is_wp_error( $result ) ) {
			$data   = $result->get_error_data();
			$status = is_array( $data ) ? (int) ( $data['status'] ?? 400 ) : 400;
			return $this->error( $result->get_error_message(), $status );
		}

		return $this->success( array( 'revision' => $result ), $result['created'] ? 201 : 200 );
	}

	/**
	 * Permission callback.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function can_view_recommendations( WP_REST_Request $request ): bool {
		return current_user_can( Capabilities::VIEW_CONTENT )
			&& $this->can_access_content_id( (int) $request->get_param( 'id' ) );
	}

	/**
	 * Require both plugin and WordPress object-level edit permissions.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function can_apply_revision( WP_REST_Request $request ): bool {
		if ( ! current_user_can( Capabilities::APPLY_CHANGES ) ) {
			return false;
		}

		$content_id = (int) $request->get_param( 'content_id' );
		$item       = $this->container->get( ContentRepository::class )->find( $content_id );
		if ( ! $item ) {
			return true;
		}
		return $this->can_access_content( $item )
			&& 'post' === $item->object_type
			&& $item->object_id
			&& current_user_can( 'edit_post', $item->object_id );
	}

	/**
	 * Define the public request contract.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function revision_args(): array {
		$string_arg = static fn ( $required = true ): array => array(
			'required'          => $required,
			'validate_callback' => static fn ( $value ) => is_string( $value ),
		);

		return array(
			'content_id'        => array(
				'required'          => true,
				'validate_callback' => static fn ( $value ) => is_numeric( $value ) && (int) $value > 0,
				'sanitize_callback' => 'absint',
			),
			'title'             => $string_arg(),
			'content'           => $string_arg(),
			'excerpt'           => $string_arg(),
			'base_content_hash' => array(
				'required'          => true,
				'validate_callback' => static fn ( $value ) => is_string( $value ) && 1 === preg_match( '/^[a-f0-9]{64}$/', $value ),
			),
			'summary'           => $string_arg( false ),
		);
	}

	/**
	 * Validate direct controller calls and sanitize editable HTML.
	 *
	 * @return array<string, string>|WP_Error
	 */
	private function sanitize_revision_request( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			return new WP_Error( 'citeoryx_revision_invalid', __( '修订请求格式无效。', 'citeoryx' ) );
		}
		foreach ( array( 'title', 'content', 'excerpt', 'base_content_hash' ) as $key ) {
			if ( ! array_key_exists( $key, $params ) || ! is_string( $params[ $key ] ) ) {
				return new WP_Error( 'citeoryx_revision_invalid', __( '修订请求缺少有效的完整内容字段。', 'citeoryx' ) );
			}
		}
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $params['base_content_hash'] ) ) {
			return new WP_Error( 'citeoryx_revision_invalid_hash', __( '基础内容版本标识无效。', 'citeoryx' ) );
		}
		if ( isset( $params['summary'] ) && ! is_string( $params['summary'] ) ) {
			return new WP_Error( 'citeoryx_revision_invalid_summary', __( '更新说明格式无效。', 'citeoryx' ) );
		}

		return array(
			'title'             => sanitize_text_field( $params['title'] ),
			'content'           => wp_kses_post( $params['content'] ),
			'excerpt'           => wp_kses_post( $params['excerpt'] ),
			'base_content_hash' => $params['base_content_hash'],
			'summary'           => sanitize_text_field( (string) ( $params['summary'] ?? '' ) ),
		);
	}
}
