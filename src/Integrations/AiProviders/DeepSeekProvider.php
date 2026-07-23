<?php
/**
 * DeepSeek provider adapter.
 *
 * @package Citeoryx\Integrations\AiProviders
 */

namespace Citeoryx\Integrations\AiProviders;

/**
 * Uses DeepSeek's OpenAI-compatible Chat Completions API.
 */
class DeepSeekProvider extends OpenAiCompatibleProvider {

	public const KEY_NAME      = 'deepseek_api_key';
	public const API_URL       = 'https://api.deepseek.com/chat/completions';
	public const DEFAULT_MODEL = 'deepseek-chat';

	/**
	 * @param string $model Model identifier.
	 */
	public function __construct( string $model = self::DEFAULT_MODEL ) {
		parent::__construct( self::API_URL, $model, self::KEY_NAME );
	}
}
