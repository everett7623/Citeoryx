import { __ } from '@wordpress/i18n';

export const providerOptions = [
	{ label: __( '不使用 AI', 'citeoryx' ), value: 'none' },
	{ label: 'OpenAI', value: 'openai' },
	{ label: 'Anthropic (Claude)', value: 'anthropic' },
	{ label: 'OpenAI 兼容 API', value: 'openai_compatible' },
	{
		label: 'OpenAI Responses API（Sub2API / Codex）',
		value: 'openai_responses',
	},
	{ label: 'Anthropic 兼容 API', value: 'anthropic_compatible' },
	{ label: 'DeepSeek', value: 'deepseek' },
];

export const defaultModels = {
	openai: 'gpt-4o-mini',
	anthropic: 'claude-haiku-4-5-20251001',
	openai_compatible: '',
	openai_responses: 'gpt-4o-mini',
	anthropic_compatible: '',
	deepseek: 'deepseek-chat',
};

export const keyStateNames = {
	openai: 'has_openai_key',
	anthropic: 'has_anthropic_key',
	openai_compatible: 'has_openai_compatible_key',
	openai_responses: 'has_openai_responses_key',
	anthropic_compatible: 'has_anthropic_compatible_key',
	deepseek: 'has_deepseek_key',
};

export const isCompatible = ( provider ) =>
	provider === 'openai_compatible' ||
	provider === 'openai_responses' ||
	provider === 'anthropic_compatible';

export const providerName = ( provider ) =>
	providerOptions.find( ( option ) => option.value === provider )?.label ||
	__( 'AI 提供商', 'citeoryx' );

export const getProviderSettings = ( ai, provider ) =>
	ai?.provider_settings?.[ provider ] ||
	( ai?.provider === provider ? ai?.settings : null ) ||
	{};

export const canTestSavedProvider = ( ai, provider ) =>
	provider !== 'none' &&
	ai?.provider === provider &&
	Boolean( ai?.[ keyStateNames[ provider ] ] );

export const getEndpointField = ( provider ) =>
	provider === 'openai_responses'
		? {
				label: __( 'API 服务根地址', 'citeoryx' ),
				help: __(
					'例如 https://sub2.uukk.de；系统会按 Responses 协议请求 /v1/responses。',
					'citeoryx'
				),
		  }
		: {
				label: __( 'API 请求地址', 'citeoryx' ),
				help: __(
					'填写服务商提供的完整 URL；系统会原样使用，不追加任何路径。',
					'citeoryx'
				),
		  };
