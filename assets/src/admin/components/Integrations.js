import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { getApiErrorMessage } from '../apiError';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Notice,
	SelectControl,
	Spinner,
	TextControl,
} from '@wordpress/components';

const Integrations = () => {
	const [ gsc, setGsc ] = useState( null );
	const [ bing, setBing ] = useState( null );
	const [ ai, setAi ] = useState( null );
	const [ clientId, setClientId ] = useState( '' );
	const [ clientSecret, setClientSecret ] = useState( '' );
	const [ bingApiKey, setBingApiKey ] = useState( '' );
	const [ aiProvider, setAiProvider ] = useState( 'none' );
	const [ apiKey, setApiKey ] = useState( '' );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	const loadStatus = () => {
		setLoading( true );
		Promise.all( [
			apiFetch( { path: 'citeoryx/v1/integrations/gsc' } ),
			apiFetch( { path: 'citeoryx/v1/integrations/bing' } ),
			apiFetch( { path: 'citeoryx/v1/integrations/ai' } ),
		] )
			.then( ( [ gscResponse, bingResponse, aiResponse ] ) => {
				setGsc( gscResponse.data );
				setBing( bingResponse.data );
				setAi( aiResponse.data );
				setAiProvider( aiResponse.data.provider || 'none' );
				if ( gscResponse.data.connection_result === 'connected' ) {
					setNotice( {
						status: 'success',
						text: __(
							'Google Search Console 已连接。',
							'citeoryx'
						),
					} );
				} else if ( gscResponse.data.connection_result === 'failed' ) {
					setNotice( {
						status: 'error',
						text: __(
							'Google Search Console 授权失败，请检查凭据后重试。',
							'citeoryx'
						),
					} );
				}
			} )
			.catch( () =>
				setNotice( {
					status: 'error',
					text: __( '无法加载集成状态。', 'citeoryx' ),
				} )
			)
			.finally( () => setLoading( false ) );
	};

	useEffect( () => {
		loadStatus();
	}, [] );

	const saveGsc = () => {
		if ( ! clientId || ! clientSecret ) {
			setNotice( {
				status: 'error',
				text: __(
					'请填写 Google OAuth Client ID 和 Client Secret。',
					'citeoryx'
				),
			} );
			return;
		}
		setSaving( true );
		apiFetch( {
			path: 'citeoryx/v1/integrations/gsc/client',
			method: 'POST',
			data: { client_id: clientId, client_secret: clientSecret },
		} )
			.then( ( response ) => {
				window.location.assign( response.data.auth_url );
			} )
			.catch( ( error ) =>
				setNotice( {
					status: 'error',
					text: getApiErrorMessage(
						error,
						__( '无法保存 Google 凭据。', 'citeoryx' )
					),
				} )
			)
			.finally( () => setSaving( false ) );
	};

	const connectGsc = () => {
		if ( gsc?.auth_url ) {
			window.location.assign( gsc.auth_url );
		}
	};

	const disconnectGsc = () => {
		setSaving( true );
		apiFetch( {
			path: 'citeoryx/v1/integrations/gsc/disconnect',
			method: 'POST',
		} )
			.then( () => {
				setNotice( {
					status: 'success',
					text: __( 'Google Search Console 已断开。', 'citeoryx' ),
				} );
				loadStatus();
			} )
			.catch( ( error ) =>
				setNotice( {
					status: 'error',
					text: getApiErrorMessage(
						error,
						__( '无法断开连接。', 'citeoryx' )
					),
				} )
			)
			.finally( () => setSaving( false ) );
	};

	const saveBing = () => {
		if ( ! bingApiKey ) {
			setNotice( {
				status: 'error',
				text: __( '请填写 Bing Webmaster Tools API Key。', 'citeoryx' ),
			} );
			return;
		}
		setSaving( true );
		apiFetch( {
			path: 'citeoryx/v1/integrations/bing/settings',
			method: 'POST',
			data: { api_key: bingApiKey },
		} )
			.then( () => {
				setBingApiKey( '' );
				setNotice( {
					status: 'success',
					text: __( 'Bing Webmaster Tools 已连接。', 'citeoryx' ),
				} );
				loadStatus();
			} )
			.catch( ( error ) =>
				setNotice( {
					status: 'error',
					text: getApiErrorMessage(
						error,
						__( '无法保存 Bing API Key。', 'citeoryx' )
					),
				} )
			)
			.finally( () => setSaving( false ) );
	};

	const disconnectBing = () => {
		setSaving( true );
		apiFetch( {
			path: 'citeoryx/v1/integrations/bing/disconnect',
			method: 'POST',
		} )
			.then( () => {
				setNotice( {
					status: 'success',
					text: __( 'Bing Webmaster Tools 已断开。', 'citeoryx' ),
				} );
				loadStatus();
			} )
			.catch( ( error ) =>
				setNotice( {
					status: 'error',
					text: getApiErrorMessage(
						error,
						__( '无法断开 Bing 连接。', 'citeoryx' )
					),
				} )
			)
			.finally( () => setSaving( false ) );
	};

	const saveAi = () => {
		if ( aiProvider === 'openai' && ! apiKey && ! ai?.has_openai_key ) {
			setNotice( {
				status: 'error',
				text: __( '请填写 OpenAI API Key。', 'citeoryx' ),
			} );
			return;
		}
		if ( aiProvider === 'deepseek' && ! apiKey && ! ai?.has_deepseek_key ) {
			setNotice( {
				status: 'error',
				text: __( '请填写 DeepSeek API Key。', 'citeoryx' ),
			} );
			return;
		}
		setSaving( true );
		apiFetch( {
			path: 'citeoryx/v1/integrations/ai/settings',
			method: 'POST',
			data: { provider: aiProvider, api_key: apiKey },
		} )
			.then( () => {
				setApiKey( '' );
				setNotice( {
					status: 'success',
					text: __( 'AI 提供商设置已保存。', 'citeoryx' ),
				} );
				loadStatus();
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

	if ( loading ) {
		return <Spinner />;
	}

	return (
		<div className="citeoryx-integrations">
			{ notice && (
				<Notice
					status={ notice.status }
					onRemove={ () => setNotice( null ) }
				>
					{ notice.text }
				</Notice>
			) }
			<Card>
				<CardHeader>
					{ __( 'Google Search Console', 'citeoryx' ) }
				</CardHeader>
				<CardBody>
					<p>
						{ gsc?.connected
							? __( '状态：已连接', 'citeoryx' )
							: __( '状态：未连接', 'citeoryx' ) }
					</p>
					<p>
						{ __(
							'在 Google Cloud Console 中将以下地址添加为授权重定向 URI：',
							'citeoryx'
						) }
					</p>
					<code>{ gsc?.redirect_uri }</code>
					{ ! gsc?.has_credentials && (
						<>
							<TextControl
								label={ __( 'OAuth Client ID', 'citeoryx' ) }
								value={ clientId }
								onChange={ setClientId }
							/>
							<TextControl
								label={ __(
									'OAuth Client Secret',
									'citeoryx'
								) }
								type="password"
								value={ clientSecret }
								onChange={ setClientSecret }
							/>
							<Button
								variant="primary"
								onClick={ saveGsc }
								disabled={ saving }
							>
								{ __( '保存并连接 Google', 'citeoryx' ) }
							</Button>
						</>
					) }
					{ gsc?.has_credentials && ! gsc?.connected && (
						<Button
							variant="primary"
							onClick={ connectGsc }
							disabled={ saving }
						>
							{ __( '连接 Google Search Console', 'citeoryx' ) }
						</Button>
					) }
					{ gsc?.connected && (
						<Button
							variant="secondary"
							onClick={ disconnectGsc }
							disabled={ saving }
						>
							{ __( '断开连接', 'citeoryx' ) }
						</Button>
					) }
				</CardBody>
			</Card>

			<Card>
				<CardHeader>
					{ __( 'Bing Webmaster Tools', 'citeoryx' ) }
				</CardHeader>
				<CardBody>
					<p>
						{ bing?.connected
							? __( '状态：已连接', 'citeoryx' )
							: __( '状态：未连接', 'citeoryx' ) }
					</p>
					{ ! bing?.connected && (
						<>
							<TextControl
								label={ __( 'API Key', 'citeoryx' ) }
								type="password"
								value={ bingApiKey }
								onChange={ setBingApiKey }
							/>
							<Button
								variant="primary"
								onClick={ saveBing }
								disabled={ saving }
							>
								{ __( '保存并连接 Bing', 'citeoryx' ) }
							</Button>
						</>
					) }
					{ bing?.connected && (
						<Button
							variant="secondary"
							onClick={ disconnectBing }
							disabled={ saving }
						>
							{ __( '断开连接', 'citeoryx' ) }
						</Button>
					) }
				</CardBody>
			</Card>

			<Card>
				<CardHeader>{ __( 'AI 内容分析', 'citeoryx' ) }</CardHeader>
				<CardBody>
					<SelectControl
						label={ __( 'AI 提供商', 'citeoryx' ) }
						value={ aiProvider }
						options={ [
							{
								label: __( '不使用 AI', 'citeoryx' ),
								value: 'none',
							},
							{ label: 'OpenAI', value: 'openai' },
							{ label: 'DeepSeek', value: 'deepseek' },
						] }
						onChange={ setAiProvider }
					/>
					{ aiProvider === 'openai' && (
						<TextControl
							label={
								ai?.has_openai_key
									? __(
											'替换 OpenAI API Key（可选）',
											'citeoryx'
									  )
									: __( 'OpenAI API Key', 'citeoryx' )
							}
							type="password"
							value={ apiKey }
							onChange={ setApiKey }
						/>
					) }
					{ aiProvider === 'deepseek' && (
						<TextControl
							label={
								ai?.has_deepseek_key
									? __(
											'替换 DeepSeek API Key（可选）',
											'citeoryx'
									  )
									: __( 'DeepSeek API Key', 'citeoryx' )
							}
							type="password"
							value={ apiKey }
							onChange={ setApiKey }
						/>
					) }
					<Button
						variant="primary"
						onClick={ saveAi }
						disabled={ saving }
					>
						{ __( '保存 AI 设置', 'citeoryx' ) }
					</Button>
				</CardBody>
			</Card>
		</div>
	);
};

export default Integrations;
