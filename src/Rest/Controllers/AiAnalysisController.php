<?php
/**
 * AI content analysis REST controller.
 *
 * @package Citeoryx\Rest\Controllers
 */

namespace Citeoryx\Rest\Controllers;

use Citeoryx\Application\Analyze\AiContentAnalyzer;
use Citeoryx\Core\Capabilities;
use Citeoryx\Domain\Content\ContentRepository;
use Citeoryx\Infrastructure\Queue\AiAnalysisQueue;
use Citeoryx\Integrations\AiProviders\AiProviderFactory;
use WP_REST_Request;

/**
 * Exposes provider availability and owner-scoped background analysis tasks.
 */
class AiAnalysisController extends BaseController {

	/**
	 * Register analysis routes.
	 *
	 * @param string $namespace REST namespace.
	 * @return void
	 */
	public function register( string $namespace ): void {
		register_rest_route(
			$namespace,
			'/integrations/ai/availability',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_availability' ),
					'permission_callback' => array( $this, 'use_ai_permission' ),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/integrations/ai/analyze/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'analyze_content' ),
					'permission_callback' => array( $this, 'analyze_permission' ),
					'args'                => array(
						'id' => array(
							'required' => true,
							'type'     => 'integer',
							'minimum'  => 1,
						),
					),
				),
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_analysis' ),
					'permission_callback' => array( $this, 'analyze_permission' ),
					'args'                => array(
						'id'      => array(
							'required' => true,
							'type'     => 'integer',
							'minimum'  => 1,
						),
						'task_id' => array(
							'required' => false,
							'type'     => 'string',
							'pattern'  => '^[a-f0-9-]{36}$',
						),
					),
				),
			)
		);
	}

	/**
	 * Permission to inspect basic AI availability.
	 *
	 * @return bool
	 */
	public function use_ai_permission(): bool {
		return $this->check_cap( Capabilities::USE_AI );
	}

	/**
	 * Permission to use AI for an accessible content item.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function analyze_permission( WP_REST_Request $request ): bool {
		return $this->check_cap( Capabilities::USE_AI )
			&& $this->can_access_content_id( (int) $request->get_param( 'id' ) );
	}

	/**
	 * Return only the provider state needed by the optimizer.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_availability(): \WP_REST_Response {
		$provider_name = (string) get_option( AiProviderFactory::OPTION_PROVIDER, 'none' );
		$provider      = $this->container->get( AiProviderFactory::class )->make();

		return $this->success(
			array(
				'provider'   => $provider_name,
				'enabled'    => AiProviderFactory::is_enabled(),
				'configured' => $provider->is_configured(),
			)
		);
	}

	/**
	 * Queue an AI analysis task.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function analyze_content( WP_REST_Request $request ): \WP_REST_Response {
		$analyzer = $this->container->get( AiContentAnalyzer::class );
		if ( ! $analyzer->is_configured() ) {
			return $this->error( __( 'No AI provider is configured.', 'citeoryx' ), 400 );
		}

		$content_id = (int) $request->get_param( 'id' );
		if ( ! $this->container->get( ContentRepository::class )->find( $content_id ) ) {
			return $this->error( __( 'Content item not found.', 'citeoryx' ), 404 );
		}

		$queue = $this->container->get( AiAnalysisQueue::class );
		try {
			$entry = $queue->enqueue( $content_id, get_current_user_id() );
		} catch ( \Throwable ) {
			return $this->error( __( 'Unable to create the AI analysis task.', 'citeoryx' ), 500 );
		}

		if ( 'failed' === $entry['task']['status'] ) {
			return $this->error( (string) $entry['task']['error'], 500 );
		}

		return $this->success( $queue->to_response( $entry['task'], $entry['reused'] ), 202 );
	}

	/**
	 * Get the requested task, or the latest retained task for this content.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_analysis( WP_REST_Request $request ): \WP_REST_Response {
		$task_id = sanitize_text_field( (string) $request->get_param( 'task_id' ) );
		if ( '' !== $task_id && ! preg_match( '/^[a-f0-9-]{36}$/', $task_id ) ) {
			return $this->error( __( 'A valid AI analysis task ID is required.', 'citeoryx' ), 400 );
		}

		$content_id = (int) $request->get_param( 'id' );
		$queue      = $this->container->get( AiAnalysisQueue::class );
		$task       = '' !== $task_id
			? $queue->get_for_user( $task_id, $content_id, get_current_user_id() )
			: $queue->get_latest_for_user( $content_id, get_current_user_id() );
		if ( ! $task ) {
			if ( '' !== $task_id ) {
				return $this->error( __( 'AI analysis task not found.', 'citeoryx' ), 404 );
			}
			return $this->success( $this->idle_task( $content_id ) );
		}

		return $this->success( $queue->to_response( $task ) );
	}

	/**
	 * Build the stable empty task response.
	 *
	 * @param int $content_id Content item ID.
	 * @return array<string, mixed>
	 */
	private function idle_task( int $content_id ): array {
		return array(
			'task_id'    => '',
			'content_id' => $content_id,
			'status'     => 'idle',
			'created_at' => '',
			'updated_at' => '',
			'reused'     => false,
		);
	}
}
