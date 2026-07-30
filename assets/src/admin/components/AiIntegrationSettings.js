import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Notice,
} from '@wordpress/components';
import { getApiErrorMessage } from '../apiError';
import AiConnectionActions from './AiConnectionActions';
import AiProviderFields from './AiProviderFields';
import AiSettingsHeader from './AiSettingsHeader';
import {
	canTestSavedProvider,
	defaultModels,
	getProviderSettings,
	isCompatible,
	isSub2ApiServiceRoot,
	isValidTimeout,
	keyStateNames,
} from './aiProviderConfig';

const AiIntegrationSettings = ( { ai, onSaved, setNotice } ) => {
	const [ provider, setProvider ] = useState( 'none' );
	const [ enabled, setEnabled ] = useState( false );
	const [ apiKey, setApiKey ] = useState( '' );
	const [ model, setModel ] = useState( '' );
	const [ baseUrl, setBaseUrl ] = useState( '' );
	const [ timeout, setTimeoutValue ] = useState( '60' );
	const [ saving, setSaving ] = useState( false );
	const [ testing, setTesting ] = useState( false );

	useEffect( () => {
		const activeProvider = ai?.provider || 'none';
		const settings = getProviderSettings( ai, activeProvider );
		setProvider( activeProvider );
		setEnabled( Boolean( ai?.enabled ) && activeProvider !== 'none' );
		setApiKey( '' );
		setModel( settings.model || defaultModels[ activeProvider ] || '' );
		setBaseUrl( settings.base_url || '' );
		setTimeoutValue( String( ai?.timeout || 60 ) );
	}, [ ai ] );

	const hasStoredKey = Boolean( ai?.[ keyStateNames[ provider ] ] );
	const canTest = canTestSavedProvider( ai, provider );
	const sub2ApiRoot = isSub2ApiServiceRoot( provider, baseUrl );

	const changeProvider = ( value ) => {
		const settings = getProviderSettings( ai, value );
		setProvider( value );
		setApiKey( '' );
		setModel( settings.model || defaultModels[ value ] || '' );
		setBaseUrl( settings.base_url || '' );
		setEnabled( value !== 'none' );
	};

	const switchToResponses = () => {
		const serviceRoot = baseUrl;
		const currentModel = model;
		changeProvider( 'openai_responses' );
		setBaseUrl( serviceRoot );
		setModel( currentModel || defaultModels.openai_responses );
	};

	const save = () => {
		if ( enabled && provider !== 'none' && ! apiKey && ! hasStoredKey ) {
			setNotice( {
				status: 'error',
				text: __(
					'启用 AI 前必须填写该提供商的 API Key。',
					'citeoryx'
				),
			} );
			return;
		}
		if ( isCompatible( provider ) && ( ! baseUrl || ! model ) ) {
			setNotice( {
				status: 'error',
				text: __( '兼容 API 必须填写请求地址和模型标识。', 'citeoryx' ),
			} );
			return;
		}
		if ( ! isValidTimeout( timeout ) ) {
			setNotice( {
				status: 'error',
				text: __( '请求超时必须是 10–180 秒之间的整数。', 'citeoryx' ),
			} );
			return;
		}

		setSaving( true );
		apiFetch( {
			path: 'citeoryx/v1/integrations/ai/settings',
			method: 'POST',
			data: {
				provider,
				enabled: provider !== 'none' && enabled,
				api_key: apiKey,
				model,
				base_url: baseUrl,
				timeout: Number( timeout ),
			},
		} )
			.then( () => {
				setApiKey( '' );
				setNotice( {
					status: 'success',
					text: __( 'AI 设置已保存。现在可以测试连接。', 'citeoryx' ),
				} );
				onSaved();
			} )
			.catch( ( error ) =>
				setNotice( {
					status: 'error',
					text: getApiErrorMessage(
						error,
						__( '无法保存 AI 设置。', 'citeoryx' )
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
						__( 'AI API 未返回有效结果。', 'citeoryx' ),
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
			<CardHeader>
				<AiSettingsHeader
					enabled={ enabled }
					onToggle={ setEnabled }
					provider={ provider }
				/>
			</CardHeader>
			<CardBody>
				<AiProviderFields
					apiKey={ apiKey }
					baseUrl={ baseUrl }
					hasStoredKey={ hasStoredKey }
					model={ model }
					onApiKeyChange={ setApiKey }
					onBaseUrlChange={ setBaseUrl }
					onModelChange={ setModel }
					onProviderChange={ changeProvider }
					onTimeoutChange={ setTimeoutValue }
					provider={ provider }
					timeout={ timeout }
				/>
				{ sub2ApiRoot && (
					<Notice status="warning" isDismissible={ false }>
						<p>
							{ __(
								'Sub2API 服务根地址需要使用 Responses 协议；普通 OpenAI 兼容模式只会原样请求当前 URL。',
								'citeoryx'
							) }
						</p>
						<Button
							variant="secondary"
							onClick={ switchToResponses }
						>
							{ __(
								'改用 Sub2API / Responses 模式',
								'citeoryx'
							) }
						</Button>
					</Notice>
				) }
				{ provider !== 'none' && (
					<Notice status="warning" isDismissible={ false }>
						{ __(
							'AI 分析会向所选服务商发送页面标题、URL、内容片段、问题摘要和评分；不会发送 WordPress 密码或已保存的 API Key。',
							'citeoryx'
						) }
					</Notice>
				) }
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
