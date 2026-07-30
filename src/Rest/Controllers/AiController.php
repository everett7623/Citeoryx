<?php
/**
 * AI provider REST controller.
 *
 * @package Citeoryx\Rest\Controllers
 */

namespace Citeoryx\Rest\Controllers;

use Citeoryx\Core\Capabilities;
use Citeoryx\Integrations\AiProviders\AiProviderFactory;
use Citeoryx\Integrations\AiProviders\AiProviderInterface;
use Citeoryx\Integrations\AiProviders\AnthropicCompatibleProvider;
use Citeoryx\Integrations\AiProviders\AnthropicProvider;
use Citeoryx\Integrations\AiProviders\DeepSeekProvider;
use Citeoryx\Integrations\AiProviders\OpenAiCompatibleProvider;
use Citeoryx\Integrations\AiProviders\OpenAiProvider;
use Citeoryx\Integrations\AiProviders\OpenAiResponsesProvider;
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
							'enum'     => AiProviderFactory::PROVIDERS,
						),
						'api_key'  => array(
							'required' => false,
							'type'     => 'string',
						),
						'model'    => array(
							'required' => false,
							'type'     => 'string',
						),
						'base_url' => array(
							'required' => false,
							'type'     => 'string',
						),
						'enabled'  => array(
							'required' => false,
							'type'     => 'boolean',
						),
						'timeout'  => array(
							'required' => false,
							'type'     => 'integer',
							'minimum'  => AiProviderFactory::MIN_TIMEOUT,
							'maximum'  => AiProviderFactory::MAX_TIMEOUT,
						),
					),
				),
			)
		);

		register_rest_route(
			$namespace,
			'/integrations/ai/validate',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'validate_connection' ),
					'permission_callback' => array( $this, 'check_permission' ),
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
	 * Get AI provider status.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_status(): \WP_REST_Response {
		$provider_name     = (string) get_option( AiProviderFactory::OPTION_PROVIDER, 'none' );
		$factory           = new AiProviderFactory();
		$provider          = $factory->make_selected();
		$provider_settings = array();
		foreach ( AiProviderFactory::PROVIDERS as $provider_key ) {
			if ( 'none' !== $provider_key ) {
				$provider_settings[ $provider_key ] = AiProviderFactory::get_provider_settings( $provider_key );
			}
		}

		return $this->success(
			array(
				'provider'                     => $provider_name,
				'enabled'                      => AiProviderFactory::is_enabled(),
				'timeout'                      => AiProviderFactory::get_timeout(),
				'configured'                   => $provider->is_configured(),
				'has_openai_key'               => OpenAiProvider::has_api_key(),
				'has_anthropic_key'            => AnthropicProvider::has_api_key(),
				'has_openai_compatible_key'    => OpenAiCompatibleProvider::has_api_key(),
				'has_openai_responses_key'     => OpenAiResponsesProvider::has_api_key(),
				'has_anthropic_compatible_key' => AnthropicCompatibleProvider::has_api_key(),
				'has_deepseek_key'             => DeepSeekProvider::has_api_key(),
				'settings'                     => AiProviderFactory::get_provider_settings( $provider_name ),
				'provider_settings'            => $provider_settings,
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
		$provider = sanitize_key( (string) $request->get_param( 'provider' ) );
		$api_key  = trim( (string) $request->get_param( 'api_key' ) );
		$model    = sanitize_text_field( (string) $request->get_param( 'model' ) );
		$base_url = esc_url_raw( trim( (string) $request->get_param( 'base_url' ) ) );
		if ( $request->has_param( 'enabled' ) ) {
			$enabled = rest_sanitize_boolean( $request->get_param( 'enabled' ) );
		} else {
			$stored_enabled = get_option( AiProviderFactory::OPTION_ENABLED, null );
			$enabled        = null === $stored_enabled ? 'none' !== $provider : (bool) $stored_enabled;
		}
		$timeout = $request->has_param( 'timeout' )
			? absint( $request->get_param( 'timeout' ) )
			: AiProviderFactory::get_timeout();

		if ( ! AiProviderFactory::is_supported_provider( $provider ) ) {
			return $this->error( __( 'Unsupported AI provider.', 'citeoryx' ), 400 );
		}
		if ( $timeout < AiProviderFactory::MIN_TIMEOUT || $timeout > AiProviderFactory::MAX_TIMEOUT ) {
			return $this->error( __( 'AI request timeout must be between 10 and 180 seconds.', 'citeoryx' ), 400 );
		}

		if ( 'none' === $provider ) {
			update_option( AiProviderFactory::OPTION_PROVIDER, $provider );
			AiProviderFactory::save_runtime_settings( false, $timeout );
			return $this->success(
				array(
					'saved'    => true,
					'provider' => $provider,
					'enabled'  => false,
					'timeout'  => $timeout,
				)
			);
		}

		if ( AiProviderFactory::is_compatible_provider( $provider ) && ! AiProviderFactory::is_valid_base_url( $base_url ) ) {
			return $this->error(
				__( 'A valid HTTPS API request URL is required for compatible providers.', 'citeoryx' ),
				400
			);
		}

		if ( '' === $model && AiProviderFactory::is_compatible_provider( $provider ) ) {
			return $this->error( __( 'A model identifier is required for compatible providers.', 'citeoryx' ), 400 );
		}

		$model = $model ?: AiProviderFactory::default_model( $provider );
		if ( strlen( $model ) > 120 ) {
			return $this->error( __( 'The model identifier is too long.', 'citeoryx' ), 400 );
		}

		$provider_instance = $this->provider_for_settings( $provider, $model, $base_url );
		if ( $enabled && '' === $api_key && ! $provider_instance->is_configured() ) {
			return $this->error( __( 'An API key is required for this AI provider.', 'citeoryx' ), 400 );
		}

		if ( '' !== $api_key && ! $this->save_api_key( $provider, $api_key ) ) {
			return $this->error( __( 'Unable to store the AI API key securely.', 'citeoryx' ), 500 );
		}

		AiProviderFactory::save_provider_settings( $provider, $model, $base_url );
		update_option( AiProviderFactory::OPTION_PROVIDER, $provider );
		AiProviderFactory::save_runtime_settings( $enabled, $timeout );

		return $this->success(
			array(
				'saved'    => true,
				'provider' => $provider,
				'enabled'  => $enabled,
				'timeout'  => $timeout,
				'settings' => AiProviderFactory::get_provider_settings( $provider ),
			)
		);
	}

	/**
	 * Validate the active AI provider with a real minimal request.
	 *
	 * @return \WP_REST_Response
	 */
	public function validate_connection(): \WP_REST_Response {
		$provider = ( new AiProviderFactory() )->make_selected();

		if ( ! $provider->is_configured() ) {
			return $this->success(
				array(
					'valid'   => false,
					'message' => __( '请先保存 AI 提供商和 API Key。', 'citeoryx' ),
				)
			);
		}

		return $this->success( $provider->test_connection() );
	}

	/**
	 * Build a provider instance without changing the active provider option.
	 *
	 * @param string $provider Provider key.
	 * @param string $model    Model identifier.
	 * @param string $base_url Custom API request URL.
	 * @return AiProviderInterface
	 */
	private function provider_for_settings(
		string $provider,
		string $model,
		string $base_url
	): AiProviderInterface {
		switch ( $provider ) {
			case 'openai':
				return new OpenAiProvider( $model );
			case 'anthropic':
				return new AnthropicProvider( $model );
			case 'openai_compatible':
				return new OpenAiCompatibleProvider( $base_url, $model );
			case 'openai_responses':
				return new OpenAiResponsesProvider( $base_url, $model );
			case 'anthropic_compatible':
				return new AnthropicCompatibleProvider( $base_url, $model );
			default:
				return new DeepSeekProvider( $model );
		}
	}

	/**
	 * Save a provider key with its dedicated encrypted storage name.
	 *
	 * @param string $provider Provider key.
	 * @param string $api_key  API key.
	 * @return bool
	 */
	private function save_api_key( string $provider, string $api_key ): bool {
		switch ( $provider ) {
			case 'openai':
				return OpenAiProvider::save_api_key( $api_key );
			case 'anthropic':
				return AnthropicProvider::save_api_key( $api_key );
			case 'openai_compatible':
				return OpenAiCompatibleProvider::save_api_key( $api_key );
			case 'openai_responses':
				return OpenAiResponsesProvider::save_api_key( $api_key );
			case 'anthropic_compatible':
				return AnthropicCompatibleProvider::save_api_key( $api_key );
			default:
				return DeepSeekProvider::save_api_key( $api_key );
		}
	}
}
