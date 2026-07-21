<?php
/**
 * AI provider factory.
 *
 * @package Citeoryx\Integrations\AiProviders
 */

namespace Citeoryx\Integrations\AiProviders;

/**
 * Resolves the active AI provider from settings.
 */
class AiProviderFactory {

	const OPTION_PROVIDER = 'citeoryx_ai_provider';

	/**
	 * Get the active AI provider.
	 *
	 * @return AiProviderInterface
	 */
	public function make(): AiProviderInterface {
		$provider = (string) get_option( self::OPTION_PROVIDER, 'none' );

		switch ( $provider ) {
			case 'openai':
				return new OpenAiProvider();
			case 'deepseek':
				return new DeepSeekProvider();
			default:
				return new NullAiProvider();
		}
	}
}
