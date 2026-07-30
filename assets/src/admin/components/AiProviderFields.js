import { SelectControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
	getEndpointField,
	getEndpointValue,
	isCompatible,
	providerName,
	providerOptions,
} from './aiProviderConfig';

const AiProviderFields = ( {
	apiKey,
	baseUrl,
	hasStoredKey,
	model,
	onApiKeyChange,
	onBaseUrlChange,
	onModelChange,
	onProviderChange,
	onTimeoutChange,
	provider,
	timeout,
} ) => {
	const endpoint = getEndpointField( provider );

	return (
		<div className="citeoryx-ai-settings__grid">
			<SelectControl
				label={ __( 'AI 提供商', 'citeoryx' ) }
				value={ provider }
				options={ providerOptions }
				onChange={ onProviderChange }
			/>
			{ provider !== 'none' && (
				<TextControl
					label={ __( '模型标识', 'citeoryx' ) }
					help={
						isCompatible( provider )
							? __( '填写服务商提供的模型 ID。', 'citeoryx' )
							: __( '可按账户可用模型调整。', 'citeoryx' )
					}
					value={ model }
					onChange={ onModelChange }
				/>
			) }
			{ provider !== 'none' && (
				<TextControl
					label={ endpoint.label }
					help={ endpoint.help }
					type="url"
					value={ getEndpointValue( provider, baseUrl ) }
					disabled={ ! endpoint.editable }
					onChange={ endpoint.editable ? onBaseUrlChange : () => {} }
				/>
			) }
			{ provider !== 'none' && (
				<TextControl
					label={ `${ providerName( provider ) } API Key` }
					help={
						hasStoredKey
							? __(
									'密钥已加密保存；留空将保留现有密钥。',
									'citeoryx'
							  )
							: __(
									'密钥仅加密保存在当前 WordPress 站点。',
									'citeoryx'
							  )
					}
					type="password"
					autoComplete="new-password"
					name={ `citeoryx-${ provider }-api-key` }
					value={ apiKey }
					onChange={ onApiKeyChange }
				/>
			) }
			{ provider !== 'none' && (
				<TextControl
					className="citeoryx-ai-settings__timeout"
					label={ __( '请求超时（秒）', 'citeoryx' ) }
					help={ __(
						'允许 10–180 秒，建议 60–120 秒。',
						'citeoryx'
					) }
					type="number"
					min="10"
					max="180"
					step="1"
					value={ timeout }
					onChange={ onTimeoutChange }
				/>
			) }
		</div>
	);
};

export default AiProviderFields;
