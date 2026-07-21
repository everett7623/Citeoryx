import { getApiErrorMessage } from './apiError';

describe( 'getApiErrorMessage', () => {
	it( 'keeps a plain API error message', () => {
		expect(
			getApiErrorMessage(
				{ message: 'Nonce validation failed.' },
				'失败'
			)
		).toBe( 'Nonce validation failed.' );
	} );

	it( 'replaces a WordPress critical error HTML response', () => {
		const error = {
			message: '<p>There has been a critical error on this website.</p>',
		};

		expect( getApiErrorMessage( error, '无法加载插件设置。' ) ).toBe(
			'无法加载插件设置。'
		);
	} );

	it( 'uses the fallback when the error has no message', () => {
		expect( getApiErrorMessage( null, '网络异常。' ) ).toBe( '网络异常。' );
	} );
} );
