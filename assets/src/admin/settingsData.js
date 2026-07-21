import { __ } from '@wordpress/i18n';

const isObject = ( value ) =>
	value !== null && typeof value === 'object' && ! Array.isArray( value );

export const getSettingsData = ( response ) => {
	const data = response?.data;
	const options = data?.profile_options;
	const notificationStatus = data?.notification_status;

	if (
		! isObject( data ) ||
		! isObject( data.settings ) ||
		typeof data.settings.auto_scan !== 'boolean' ||
		typeof data.settings.remove_data_on_uninstall !== 'boolean' ||
		typeof data.settings.weekly_digest_enabled !== 'boolean' ||
		typeof data.settings.notification_email !== 'string' ||
		! isObject( data.profile ) ||
		typeof data.profile_complete !== 'boolean' ||
		! isObject( options ) ||
		! isObject( options.defaults ) ||
		! Array.isArray( options.content_types ) ||
		! isObject( notificationStatus ) ||
		! [ 'never', 'sent', 'failed', 'skipped' ].includes(
			notificationStatus.status
		)
	) {
		throw new Error( __( '设置响应格式无效。', 'citeoryx' ) );
	}

	return data;
};
