<?php
/**
 * OpenAI provider adapter.
 *
 * @package Citeoryx\Integrations\AiProviders
 */

namespace Citeoryx\Integrations\AiProviders;

/**
 * Uses OpenAI's hosted Chat Completions API.
 */
class OpenAiProvider extends OpenAiCompatibleProvider {

	public const KEY_NAME      = 'openai_api_key';
	public const API_URL       = 'https://api.openai.com/v1/chat/completions';
	public const DEFAULT_MODEL = 'gpt-4o-mini';

	/**
	 * @param string $model Model identifier.
	 */
	public function __construct( string $model = self::DEFAULT_MODEL ) {
		parent::__construct( self::API_URL, $model, self::KEY_NAME );
	}
}
