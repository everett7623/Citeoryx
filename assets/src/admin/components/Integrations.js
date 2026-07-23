import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { getApiErrorMessage } from '../apiError';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Notice,
	Spinner,
	TextControl,
} from '@wordpress/components';
import AiIntegrationSettings from './AiIntegrationSettings';

const renderIntegrationHealth = ( health ) => {
	if ( ! health || health.status === 'unknown' ) {
		return null;
	}

	return (
		<Notice
			status={ health.status === 'healthy' ? 'success' : 'error' }
			isDismissible={ false }
		>
			<strong>
				{ health.status === 'healthy'
					? __( '连接正常。', 'citeoryx' )
					: __( '连接需要处理。', 'citeoryx' ) }
			</strong>{ ' ' }
			{ health.message }
			{ health.status === 'error' && health.consecutive_failures > 1 && (
				<span>
					{ ' ' }
					{ sprintf(
						/* translators: %d: consecutive failure count. */
						__( '连续失败：%d 次。', 'citeoryx' ),
						health.consecutive_failures
					) }
				</span>
			) }
		</Notice>
	);
};

const Integrations = () => {
	const [ gsc, setGsc ] = useState( null );
	const [ bing, setBing ] = useState( null );
	const [ ai, setAi ] = useState( null );
	const [ clientId, setClientId ] = useState( '' );
	const [ clientSecret, setClientSecret ] = useState( '' );
	const [ bingApiKey, setBingApiKey ] = useState( '' );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ validating, setValidating ] = useState( null );
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

	const validateSearchConnection = ( provider ) => {
		const isGoogle = provider === 'gsc';
		setValidating( provider );
		apiFetch( {
			path: `citeoryx/v1/integrations/${ provider }/validate`,
			method: 'POST',
		} )
			.then( ( response ) => {
				const result = response.data;
				if ( isGoogle ) {
					setGsc( ( current ) => ( {
						...current,
						health: result.health,
					} ) );
				} else {
					setBing( ( current ) => ( {
						...current,
						health: result.health,
					} ) );
				}
				setNotice( {
					status: result.valid ? 'success' : 'error',
					text: result.message,
				} );
			} )
			.catch( ( error ) =>
				setNotice( {
					status: 'error',
					text: getApiErrorMessage(
						error,
						__( '无法验证连接。', 'citeoryx' )
					),
				} )
			)
			.finally( () => setValidating( null ) );
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
					{ renderIntegrationHealth( gsc?.health ) }
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
							variant="primary"
							onClick={ () => validateSearchConnection( 'gsc' ) }
							disabled={ saving || validating !== null }
							isBusy={ validating === 'gsc' }
						>
							{ __( '验证连接', 'citeoryx' ) }
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
					{ renderIntegrationHealth( bing?.health ) }
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
							variant="primary"
							onClick={ () => validateSearchConnection( 'bing' ) }
							disabled={ saving || validating !== null }
							isBusy={ validating === 'bing' }
						>
							{ __( '验证连接', 'citeoryx' ) }
						</Button>
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

			<AiIntegrationSettings
				ai={ ai }
				onSaved={ loadStatus }
				setNotice={ setNotice }
			/>
		</div>
	);
};

export default Integrations;
