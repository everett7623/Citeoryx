import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Card,
	CardBody,
	CardHeader,
	Button,
	ToggleControl,
	TextControl,
	Notice,
} from '@wordpress/components';
import SiteProfileFields from './SiteProfileFields';
import { getSettingsData } from '../settingsData';
import { getApiErrorMessage } from '../apiError';

const Settings = ( { initialData, onSaved } ) => {
	const [ settings, setSettings ] = useState( initialData.settings );
	const [ profile, setProfile ] = useState( initialData.profile );
	const [ loading, setLoading ] = useState( false );
	const [ saved, setSaved ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ notificationStatus, setNotificationStatus ] = useState(
		initialData.notification_status
	);
	const [ testLoading, setTestLoading ] = useState( false );
	const [ testResult, setTestResult ] = useState( null );

	const save = () => {
		setError( null );
		setSaved( false );
		setLoading( true );
		apiFetch( {
			path: 'citeoryx/v1/settings',
			method: 'POST',
			data: { settings, profile },
		} )
			.then( ( response ) => {
				const data = getSettingsData( response );
				setSettings( data.settings );
				setProfile( data.profile );
				setNotificationStatus( data.notification_status );
				setSaved( true );
				if ( onSaved ) {
					onSaved( data );
				}
			} )
			.catch( ( err ) =>
				setError(
					getApiErrorMessage(
						err,
						__( '保存失败，请稍后重试。', 'citeoryx' )
					)
				)
			)
			.finally( () => setLoading( false ) );
	};

	const sendTest = () => {
		setTestLoading( true );
		setTestResult( null );
		apiFetch( {
			path: 'citeoryx/v1/notifications/test',
			method: 'POST',
			data: { email: settings.notification_email },
		} )
			.then( ( response ) => {
				setNotificationStatus( response.data );
				setTestResult( {
					status: 'success',
					message: response.data.message,
				} );
			} )
			.catch( ( err ) =>
				setTestResult( {
					status: 'error',
					message: getApiErrorMessage(
						err,
						__( '测试邮件发送失败。', 'citeoryx' )
					),
				} )
			)
			.finally( () => setTestLoading( false ) );
	};

	return (
		<div className="citeoryx-settings">
			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
			{ saved && (
				<Notice status="success" isDismissible={ false }>
					{ __( '设置已保存。', 'citeoryx' ) }
				</Notice>
			) }
			{ testResult && (
				<Notice status={ testResult.status } isDismissible={ false }>
					{ testResult.message }
				</Notice>
			) }

			<Card>
				<CardHeader>{ __( '站点画像', 'citeoryx' ) }</CardHeader>
				<CardBody>
					<SiteProfileFields
						profile={ profile }
						options={ initialData.profile_options }
						onChange={ setProfile }
					/>
				</CardBody>
			</Card>

			<Card>
				<CardHeader>{ __( '邮件周报', 'citeoryx' ) }</CardHeader>
				<CardBody>
					<ToggleControl
						label={ __( '启用每周邮件周报', 'citeoryx' ) }
						checked={ settings.weekly_digest_enabled }
						onChange={ ( value ) =>
							setSettings( {
								...settings,
								weekly_digest_enabled: value,
							} )
						}
						__nextHasNoMarginBottom
					/>
					<TextControl
						label={ __( '收件邮箱', 'citeoryx' ) }
						type="email"
						value={ settings.notification_email }
						onChange={ ( value ) =>
							setSettings( {
								...settings,
								notification_email: value,
							} )
						}
						__nextHasNoMarginBottom
					/>
					<Button
						variant="secondary"
						onClick={ sendTest }
						disabled={
							loading ||
							testLoading ||
							! settings.notification_email
						}
						isBusy={ testLoading }
					>
						{ testLoading
							? __( '发送中…', 'citeoryx' )
							: __( '发送测试邮件', 'citeoryx' ) }
					</Button>
					{ 'never' !== notificationStatus.status && (
						<p className="citeoryx-settings__status">
							{ notificationStatus.message }
							{ notificationStatus.attempted_at
								? ` ${ notificationStatus.attempted_at }`
								: '' }
						</p>
					) }
				</CardBody>
			</Card>

			<Card>
				<CardHeader>{ __( '通用设置', 'citeoryx' ) }</CardHeader>
				<CardBody>
					<ToggleControl
						label={ __( '启用自动扫描', 'citeoryx' ) }
						checked={ settings.auto_scan }
						onChange={ ( value ) =>
							setSettings( { ...settings, auto_scan: value } )
						}
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={ __( '卸载时删除数据', 'citeoryx' ) }
						checked={ settings.remove_data_on_uninstall }
						onChange={ ( value ) =>
							setSettings( {
								...settings,
								remove_data_on_uninstall: value,
							} )
						}
						__nextHasNoMarginBottom
					/>
				</CardBody>
			</Card>

			<Button
				variant="primary"
				onClick={ save }
				disabled={ loading || testLoading }
				isBusy={ loading }
			>
				{ loading
					? __( '保存中…', 'citeoryx' )
					: __( '保存设置', 'citeoryx' ) }
			</Button>
		</div>
	);
};

export default Settings;
