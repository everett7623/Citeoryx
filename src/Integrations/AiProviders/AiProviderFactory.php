<?php
/**
 * AI provider factory.
 *
 * @package Citeoryx\Integrations\AiProviders
 */

namespace Citeoryx\Integrations\AiProviders;

/**
 * Resolves and persists active AI provider configuration.
 */
class AiProviderFactory {

	public const OPTION_PROVIDER = 'citeoryx_ai_provider';
	public const OPTION_SETTINGS = 'citeoryx_ai_provider_settings';
	public const OPTION_ENABLED  = 'citeoryx_ai_enabled';
	public const OPTION_TIMEOUT  = 'citeoryx_ai_timeout';
	public const DEFAULT_TIMEOUT = 60;
	public const MIN_TIMEOUT     = 10;
	public const MAX_TIMEOUT     = 180;

	/**
	 * Supported provider keys.
	 *
	 * @var array<int, string>
	 */
	public const PROVIDERS = array(
		'openai',
		'anthropic',
		'openai_compatible',
		'openai_responses',
		'anthropic_compatible',
		'deepseek',
		'none',
	);

	/**
	 * Get the active AI provider.
	 *
	 * @return AiProviderInterface
	 */
	public function make(): AiProviderInterface {
		if ( ! self::is_enabled() ) {
			return new NullAiProvider();
		}

		return $this->make_selected();
	}

	/**
	 * Get the selected provider even when AI analysis is disabled.
	 *
	 * @return AiProviderInterface
	 */
	public function make_selected(): AiProviderInterface {
		$provider = (string) get_option( self::OPTION_PROVIDER, 'none' );
		$settings = self::get_provider_settings( $provider );
		$model    = $settings['model'] ?: self::default_model( $provider );

		switch ( $provider ) {
			case 'openai':
				return new OpenAiProvider( $model );
			case 'anthropic':
				return new AnthropicProvider( $model );
			case 'openai_compatible':
				return new OpenAiCompatibleProvider(
					$settings['base_url'],
					$model
				);
			case 'openai_responses':
				return new OpenAiResponsesProvider( $settings['base_url'], $model );
			case 'anthropic_compatible':
				return new AnthropicCompatibleProvider(
					$settings['base_url'],
					$model
				);
			case 'deepseek':
				return new DeepSeekProvider( $model );
			default:
				return new NullAiProvider();
		}
	}

	/**
	 * Check whether AI analysis is enabled without breaking existing installs.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		$stored = get_option( self::OPTION_ENABLED, null );
		if ( null === $stored ) {
			return 'none' !== (string) get_option( self::OPTION_PROVIDER, 'none' );
		}

		return (bool) $stored;
	}

	/**
	 * Get the bounded remote request timeout.
	 *
	 * @return int
	 */
	public static function get_timeout(): int {
		$timeout = absint( get_option( self::OPTION_TIMEOUT, self::DEFAULT_TIMEOUT ) );
		return max( self::MIN_TIMEOUT, min( self::MAX_TIMEOUT, $timeout ) );
	}

	/**
	 * Save analysis runtime settings.
	 *
	 * @param bool $enabled Whether AI analysis is enabled.
	 * @param int  $timeout Remote request timeout in seconds.
	 * @return void
	 */
	public static function save_runtime_settings( bool $enabled, int $timeout ): void {
		update_option( self::OPTION_ENABLED, $enabled, false );
		update_option(
			self::OPTION_TIMEOUT,
			max( self::MIN_TIMEOUT, min( self::MAX_TIMEOUT, $timeout ) ),
			false
		);
	}

	/**
	 * Check a provider key is supported.
	 *
	 * @param string $provider Provider key.
	 * @return bool
	 */
	public static function is_supported_provider( string $provider ): bool {
		return in_array( $provider, self::PROVIDERS, true );
	}

	/**
	 * Check whether a provider needs a custom endpoint.
	 *
	 * @param string $provider Provider key.
	 * @return bool
	 */
	public static function is_compatible_provider( string $provider ): bool {
		return in_array( $provider, array( 'openai_compatible', 'openai_responses', 'anthropic_compatible' ), true );
	}

	/**
	 * Get sanitized non-secret settings for one provider.
	 *
	 * @param string $provider Provider key.
	 * @return array{model: string, base_url: string}
	 */
	public static function get_provider_settings( string $provider ): array {
		$all      = get_option( self::OPTION_SETTINGS, array() );
		$settings = is_array( $all ) && isset( $all[ $provider ] ) && is_array( $all[ $provider ] )
			? $all[ $provider ]
			: array();

		return array(
			'model'    => isset( $settings['model'] ) && is_string( $settings['model'] ) ? sanitize_text_field( $settings['model'] ) : '',
			'base_url' => isset( $settings['base_url'] ) && is_string( $settings['base_url'] ) ? esc_url_raw( $settings['base_url'] ) : '',
		);
	}

	/**
	 * Save non-secret settings for one provider.
	 *
	 * @param string $provider Provider key.
	 * @param string $model    Model identifier.
	 * @param string $base_url Custom API base URL.
	 * @return void
	 */
	public static function save_provider_settings( string $provider, string $model, string $base_url = '' ): void {
		$all = get_option( self::OPTION_SETTINGS, array() );
		$all = is_array( $all ) ? $all : array();

		$all[ $provider ] = array(
			'model'    => $model,
			'base_url' => $base_url,
		);

		update_option( self::OPTION_SETTINGS, $all, false );
	}

	/**
	 * Validate a custom HTTPS API request URL.
	 *
	 * @param string $base_url Custom API request URL.
	 * @return bool
	 */
	public static function is_valid_base_url( string $base_url ): bool {
		$parts = wp_parse_url( $base_url );
		if ( ! is_array( $parts ) || 'https' !== ( $parts['scheme'] ?? '' ) || empty( $parts['host'] ) ) {
			return false;
		}

		if ( isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['fragment'] ) ) {
			return false;
		}

		return false !== wp_http_validate_url( $base_url );
	}

	/**
	 * Get the default model for a provider.
	 *
	 * @param string $provider Provider key.
	 * @return string
	 */
	public static function default_model( string $provider ): string {
		switch ( $provider ) {
			case 'anthropic':
			case 'anthropic_compatible':
				return AnthropicCompatibleProvider::DEFAULT_MODEL;
			case 'openai_responses':
				return OpenAiResponsesProvider::DEFAULT_MODEL;
			case 'deepseek':
				return DeepSeekProvider::DEFAULT_MODEL;
			default:
				return OpenAiCompatibleProvider::DEFAULT_MODEL;
		}
	}
}
