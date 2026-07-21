import { getSettingsData } from './settingsData';

const validResponse = () => ( {
	data: {
		settings: {
			auto_scan: true,
			remove_data_on_uninstall: false,
			weekly_digest_enabled: false,
			notification_email: 'owner@example.com',
		},
		profile: {},
		profile_complete: false,
		profile_options: {
			content_types: [],
			defaults: {},
		},
		notification_status: {
			status: 'never',
			message: '',
			attempted_at: null,
			recipient: '',
		},
	},
} );

describe( 'getSettingsData', () => {
	it( 'returns a complete settings payload', () => {
		const response = validResponse();

		expect( getSettingsData( response ) ).toBe( response.data );
	} );

	it( 'rejects a response without profile options', () => {
		const response = validResponse();
		delete response.data.profile_options;

		expect( () => getSettingsData( response ) ).toThrow(
			'设置响应格式无效。'
		);
	} );

	it( 'rejects a non-boolean completeness flag', () => {
		const response = validResponse();
		response.data.profile_complete = 'false';

		expect( () => getSettingsData( response ) ).toThrow(
			'设置响应格式无效。'
		);
	} );

	it( 'rejects a response without notification status', () => {
		const response = validResponse();
		delete response.data.notification_status;

		expect( () => getSettingsData( response ) ).toThrow(
			'设置响应格式无效。'
		);
	} );
} );
