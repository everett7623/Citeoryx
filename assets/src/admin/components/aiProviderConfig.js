import { __ } from '@wordpress/i18n';

export const providerOptions = [
	{ label: __( '不使用 AI', 'citeoryx' ), value: 'none' },
	{ label: 'OpenAI', value: 'openai' },
	{ label: 'Anthropic (Claude)', value: 'anthropic' },
	{
		label: 'Sub2API / OpenAI Responses（Codex）',
		value: 'openai_responses',
	},
	{ label: 'OpenAI 兼容 API', value: 'openai_compatible' },
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

export const fixedProviderEndpoints = {
	openai: 'https://api.openai.com/v1/chat/completions',
	anthropic: 'https://api.anthropic.com/v1/messages',
	deepseek: 'https://api.deepseek.com/chat/completions',
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

export const getEndpointField = ( provider ) => {
	if ( provider === 'openai_responses' ) {
		return {
			label: __( 'API 服务根地址', 'citeoryx' ),
			help: __(
				'例如 https://sub2.uukk.de；系统会按 Responses 协议请求 /v1/responses。',
				'citeoryx'
			),
			editable: true,
		};
	}

	if ( isCompatible( provider ) ) {
		return {
			label: __( 'API 请求地址', 'citeoryx' ),
			help: __(
				'填写服务商提供的完整 URL；系统会原样使用，不追加任何路径。',
				'citeoryx'
			),
			editable: true,
		};
	}

	return {
		label: __( 'API 请求地址', 'citeoryx' ),
		help: __( '官方服务商使用固定请求地址。', 'citeoryx' ),
		editable: false,
	};
};

export const getEndpointValue = ( provider, baseUrl ) =>
	isCompatible( provider )
		? baseUrl
		: fixedProviderEndpoints[ provider ] || '';

export const isValidTimeout = ( value ) => {
	const timeout = Number( value );
	return Number.isInteger( timeout ) && timeout >= 10 && timeout <= 180;
};

export const isSub2ApiServiceRoot = ( provider, baseUrl ) =>
	provider === 'openai_compatible' &&
	/^https:\/\/sub2\.uukk\.de\/?(?:\?.*)?$/i.test( baseUrl );
