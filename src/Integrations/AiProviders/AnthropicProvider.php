<?php
/**
 * Anthropic provider adapter.
 *
 * @package Citeoryx\Integrations\AiProviders
 */

namespace Citeoryx\Integrations\AiProviders;

/**
 * Uses Anthropic's hosted Messages API.
 */
class AnthropicProvider extends AnthropicCompatibleProvider {

	public const KEY_NAME      = 'anthropic_api_key';
	public const API_URL       = 'https://api.anthropic.com/v1/messages';
	public const DEFAULT_MODEL = 'claude-haiku-4-5-20251001';

	/**
	 * @param string $model Model identifier.
	 */
	public function __construct( string $model = self::DEFAULT_MODEL ) {
		parent::__construct( self::API_URL, $model, self::KEY_NAME );
	}
}
