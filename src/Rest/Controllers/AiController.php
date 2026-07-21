<?php
/**
 * AI provider REST controller.
 *
 * @package Citeoryx\Rest\Controllers
 */

namespace Citeoryx\Rest\Controllers;

use Citeoryx\Core\Capabilities;
use Citeoryx\Integrations\AiProviders\AiProviderFactory;
use Citeoryx\Integrations\AiProviders\OpenAiProvider;
use Citeoryx\Integrations\AiProviders\DeepSeekProvider;
use WP_REST_Request;

/**
 * Manages AI provider configuration and content analysis.
 */
class AiController extends BaseController {

	/**
	 * Register routes.
	 *
	 * @param string $namespace REST namespace.
	 * @return void
	 */
	public function register( string $namespace ): void {
		register_rest_route(
			$namespace,
			'/integrations/ai',
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
			'/integrations/ai/settings',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'save_settings' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'provider' => array(
							'required' => true,
							'type'     => 'string',
							'enum'     => array( 'openai', 'deepseek', 'none' ),
						),
						'api_key'  => array(
							'required' => false,
							'type'     => 'string',
						),
					),
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
			)
		);
	}

	/**
	 * Settings permission.
	 *
	 * @return bool
	 */
	public function check_permission(): bool {
		return $this->check_cap( Capabilities::MANAGE_INTEGRATIONS );
	}

	/**
	 * Analyze permission.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function analyze_permission( WP_REST_Request $request ): bool {
		return $this->check_cap( Capabilities::USE_AI )
			&& $this->can_access_content_id( (int) $request->get_param( 'id' ) );
	}

	/**
	 * Get AI provider status.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_status(): \WP_REST_Response {
		$provider_name = (string) get_option( AiProviderFactory::OPTION_PROVIDER, 'none' );
		$factory       = new AiProviderFactory();
		$provider      = $factory->make();

		return $this->success(
			array(
				'provider'         => $provider_name,
				'configured'       => $provider->is_configured(),
				'has_openai_key'   => ( new OpenAiProvider() )->is_configured(),
				'has_deepseek_key' => ( new DeepSeekProvider() )->is_configured(),
			)
		);
	}

	/**
	 * Save AI provider settings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function save_settings( WP_REST_Request $request ): \WP_REST_Response {
		$provider = sanitize_text_field( (string) $request->get_param( 'provider' ) );
		update_option( AiProviderFactory::OPTION_PROVIDER, $provider );

		if ( 'openai' === $provider && $request->get_param( 'api_key' ) ) {
			OpenAiProvider::save_api_key( trim( (string) $request->get_param( 'api_key' ) ) );
		}

		if ( 'deepseek' === $provider && $request->get_param( 'api_key' ) ) {
			DeepSeekProvider::save_api_key( trim( (string) $request->get_param( 'api_key' ) ) );
		}

		return $this->success(
			array(
				'saved'    => true,
				'provider' => $provider,
			)
		);
	}

	/**
	 * Analyze a content item with AI.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function analyze_content( WP_REST_Request $request ): \WP_REST_Response {
		$factory  = new AiProviderFactory();
		$provider = $factory->make();

		if ( ! $provider->is_configured() ) {
			return $this->error( __( 'No AI provider is configured.', 'citeoryx' ), 400 );
		}

		$content_id   = (int) $request->get_param( 'id' );
		$content_repo = $this->container->get( \Citeoryx\Domain\Content\ContentRepository::class );
		$issue_repo   = $this->container->get( \Citeoryx\Domain\Issue\IssueRepository::class );

		$item = $content_repo->find( $content_id );
		if ( ! $item ) {
			return $this->error( __( 'Content item not found.', 'citeoryx' ), 404 );
		}

		$issues_result = $issue_repo->list(
			array(
				'content_id' => $content_id,
				'status'     => 'open',
			),
			1,
			20
		);
		$issues        = array_map( static fn( $i ) => $i->to_array(), $issues_result['items'] );

		$post_content = '';
		if ( $item->object_id && 'post' === $item->object_type ) {
			$post = get_post( $item->object_id );
			if ( $post ) {
				$post_content = apply_filters( 'the_content', $post->post_content );
			}
		}

		$context = array(
			'title'  => $item->metadata['title'] ?? '',
			'url'    => $item->canonical_url,
			'issues' => $issues,
			'scores' => array(
				'health' => $item->health_score,
				'aeo'    => $item->ai_readiness_score,
			),
		);

		$suggestions     = $provider->suggest_improvements( $post_content, $context );
		$discoverability = $provider->analyze_discoverability( $post_content, $context );

		return $this->success(
			array(
				'content_id'      => $content_id,
				'suggestions'     => $suggestions,
				'discoverability' => $discoverability,
			)
		);
	}
}
