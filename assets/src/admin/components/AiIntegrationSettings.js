import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Card,
	CardBody,
	CardHeader,
	SelectControl,
	TextControl,
} from '@wordpress/components';
import { getApiErrorMessage } from '../apiError';
import AiConnectionActions from './AiConnectionActions';
import {
	canTestSavedProvider,
	defaultModels,
	getEndpointField,
	getProviderSettings,
	isCompatible,
	keyStateNames,
	providerName,
	providerOptions,
} from './aiProviderConfig';

const AiIntegrationSettings = ( { ai, onSaved, setNotice } ) => {
	const [ provider, setProvider ] = useState( 'none' );
	const [ apiKey, setApiKey ] = useState( '' );
	const [ model, setModel ] = useState( '' );
	const [ baseUrl, setBaseUrl ] = useState( '' );
	const [ saving, setSaving ] = useState( false );
	const [ testing, setTesting ] = useState( false );

	useEffect( () => {
		const activeProvider = ai?.provider || 'none';
		const settings = getProviderSettings( ai, activeProvider );
		setProvider( activeProvider );
		setApiKey( '' );
		setModel( settings.model || defaultModels[ activeProvider ] || '' );
		setBaseUrl( settings.base_url || '' );
	}, [ ai ] );

	const hasStoredKey = Boolean( ai?.[ keyStateNames[ provider ] ] );
	const canTest = canTestSavedProvider( ai, provider );
	const endpointField = getEndpointField( provider );

	const changeProvider = ( value ) => {
		const settings = getProviderSettings( ai, value );
		setProvider( value );
		setApiKey( '' );
		setModel( settings.model || defaultModels[ value ] || '' );
		setBaseUrl( settings.base_url || '' );
	};

	const save = () => {
		if ( provider !== 'none' && ! apiKey && ! hasStoredKey ) {
			setNotice( {
				status: 'error',
				text: __( '请填写该 AI 提供商的 API Key。', 'citeoryx' ),
			} );
			return;
		}

		if ( isCompatible( provider ) && ( ! baseUrl || ! model ) ) {
			setNotice( {
				status: 'error',
				text: __(
					'兼容 API 必须填写完整 HTTPS 请求地址和模型标识。',
					'citeoryx'
				),
			} );
			return;
		}

		setSaving( true );
		apiFetch( {
			path: 'citeoryx/v1/integrations/ai/settings',
			method: 'POST',
			data: {
				provider,
				api_key: apiKey,
				model,
				base_url: baseUrl,
			},
		} )
			.then( () => {
				setApiKey( '' );
				setNotice( {
					status: 'success',
					text: __( 'AI 提供商设置已保存。', 'citeoryx' ),
				} );
				onSaved();
			} )
			.catch( ( error ) =>
				setNotice( {
					status: 'error',
					text: getApiErrorMessage(
						error,
						__( '无法保存 AI 提供商设置。', 'citeoryx' )
					),
				} )
			)
			.finally( () => setSaving( false ) );
	};

	const testConnection = () => {
		setTesting( true );
		apiFetch( {
			path: 'citeoryx/v1/integrations/ai/validate',
			method: 'POST',
		} )
			.then( ( response ) => {
				const result = response?.data || {};
				setNotice( {
					status: result.valid ? 'success' : 'error',
					text:
						result.message ||
						__( 'AI API 未返回有效的连接结果。', 'citeoryx' ),
				} );
			} )
			.catch( ( error ) =>
				setNotice( {
					status: 'error',
					text: getApiErrorMessage(
						error,
						__( '无法测试 AI API 连接。', 'citeoryx' )
					),
				} )
			)
			.finally( () => setTesting( false ) );
	};

	return (
		<Card className="citeoryx-integrations__ai-card">
			<CardHeader>{ __( 'AI 内容分析', 'citeoryx' ) }</CardHeader>
			<CardBody>
				<div className="citeoryx-ai-settings__grid">
					<SelectControl
						label={ __( 'AI 提供商', 'citeoryx' ) }
						value={ provider }
						options={ providerOptions }
						onChange={ changeProvider }
					/>
					{ provider !== 'none' && (
						<TextControl
							label={
								hasStoredKey
									? `${ providerName( provider ) } ${ __(
											'API Key（留空则保留）',
											'citeoryx'
									  ) }`
									: `${ providerName( provider ) } API Key`
							}
							type="password"
							autoComplete="new-password"
							name={ `citeoryx-${ provider }-api-key` }
							value={ apiKey }
							onChange={ setApiKey }
						/>
					) }
					{ provider !== 'none' && (
						<TextControl
							label={ __( '模型标识', 'citeoryx' ) }
							help={
								isCompatible( provider )
									? __(
											'填写第三方服务提供的模型 ID。',
											'citeoryx'
									  )
									: __(
											'可按账户可用模型调整，留空时使用默认模型。',
											'citeoryx'
									  )
							}
							value={ model }
							onChange={ setModel }
						/>
					) }
					{ isCompatible( provider ) && (
						<TextControl
							className="citeoryx-ai-settings__endpoint"
							label={ endpointField.label }
							help={ endpointField.help }
							type="url"
							value={ baseUrl }
							onChange={ setBaseUrl }
						/>
					) }
				</div>
				<AiConnectionActions
					canTest={ canTest }
					onSave={ save }
					onTest={ testConnection }
					saving={ saving }
					showTest={ provider !== 'none' }
					testing={ testing }
				/>
			</CardBody>
		</Card>
	);
};

export default AiIntegrationSettings;
