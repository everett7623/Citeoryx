import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Card,
	CardBody,
	CardHeader,
	Button,
	ToggleControl,
	Notice,
} from '@wordpress/components';
import SiteProfileFields from './SiteProfileFields';
import NotificationSettings from './NotificationSettings';
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
	const [ criticalAlertStatus, setCriticalAlertStatus ] = useState(
		initialData.critical_alert_status
	);

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
				setCriticalAlertStatus( data.critical_alert_status );
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

			<NotificationSettings
				settings={ settings }
				onChange={ setSettings }
				loading={ loading }
				weeklyStatus={ notificationStatus }
				onWeeklyStatus={ setNotificationStatus }
				criticalStatus={ criticalAlertStatus }
			/>

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
				disabled={ loading }
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
