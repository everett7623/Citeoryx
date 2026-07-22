import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Notice,
	TextControl,
	ToggleControl,
} from '@wordpress/components';
import { getApiErrorMessage } from '../apiError';

const Status = ( { label, value } ) =>
	'never' !== value.status && (
		<p className="citeoryx-settings__status">
			{ label }：{ value.message }
			{ value.attempted_at ? ` ${ value.attempted_at }` : '' }
		</p>
	);

const NotificationSettings = ( {
	settings,
	onChange,
	loading,
	weeklyStatus,
	onWeeklyStatus,
	criticalStatus,
} ) => {
	const [ testLoading, setTestLoading ] = useState( false );
	const [ testResult, setTestResult ] = useState( null );

	const sendTest = () => {
		setTestLoading( true );
		setTestResult( null );
		apiFetch( {
			path: 'citeoryx/v1/notifications/test',
			method: 'POST',
			data: { email: settings.notification_email },
		} )
			.then( ( response ) => {
				onWeeklyStatus( response.data );
				setTestResult( {
					status: 'success',
					message: response.data.message,
				} );
			} )
			.catch( ( error ) =>
				setTestResult( {
					status: 'error',
					message: getApiErrorMessage(
						error,
						__( '测试邮件发送失败。', 'citeoryx' )
					),
				} )
			)
			.finally( () => setTestLoading( false ) );
	};

	const setValue = ( key, value ) =>
		onChange( { ...settings, [ key ]: value } );

	return (
		<Card>
			<CardHeader>{ __( '邮件通知', 'citeoryx' ) }</CardHeader>
			<CardBody>
				{ testResult && (
					<Notice
						status={ testResult.status }
						isDismissible={ false }
					>
						{ testResult.message }
					</Notice>
				) }
				<ToggleControl
					label={ __( '启用每周邮件周报', 'citeoryx' ) }
					checked={ settings.weekly_digest_enabled }
					onChange={ ( value ) =>
						setValue( 'weekly_digest_enabled', value )
					}
					__nextHasNoMarginBottom
				/>
				<ToggleControl
					label={ __( '扫描后通知严重问题', 'citeoryx' ) }
					help={ __(
						'汇总待处理的高严重度和关键问题；问题集合未变化时不会重复发送。',
						'citeoryx'
					) }
					checked={ settings.critical_alerts_enabled }
					onChange={ ( value ) =>
						setValue( 'critical_alerts_enabled', value )
					}
					__nextHasNoMarginBottom
				/>
				<TextControl
					label={ __( '收件邮箱', 'citeoryx' ) }
					type="email"
					value={ settings.notification_email }
					onChange={ ( value ) =>
						setValue( 'notification_email', value )
					}
					__nextHasNoMarginBottom
				/>
				<Button
					variant="secondary"
					onClick={ sendTest }
					disabled={
						loading || testLoading || ! settings.notification_email
					}
					isBusy={ testLoading }
				>
					{ testLoading
						? __( '发送中…', 'citeoryx' )
						: __( '发送测试邮件', 'citeoryx' ) }
				</Button>
				<Status
					label={ __( '周报状态', 'citeoryx' ) }
					value={ weeklyStatus }
				/>
				<Status
					label={ __( '严重问题通知状态', 'citeoryx' ) }
					value={ criticalStatus }
				/>
			</CardBody>
		</Card>
	);
};

export default NotificationSettings;
