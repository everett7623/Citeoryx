const { expect } = require( '@playwright/test' );

const errorOverlaySelector = [
	'[data-nextjs-dialog]',
	'.vite-error-overlay',
	'#webpack-dev-server-client-overlay',
].join( ', ' );

const watchBrowserErrors = ( page ) => {
	const errors = [];
	page.on( 'console', ( message ) => {
		if ( message.type() === 'error' ) {
			errors.push( `console.error: ${ message.text() }` );
		}
	} );
	page.on( 'pageerror', ( error ) => {
		errors.push( `pageerror: ${ error.message }` );
	} );
	return errors;
};

const expectHealthyAdminPage = async ( page, errors ) => {
	await expect( page.locator( 'body' ) ).not.toBeEmpty();
	await expect( page.locator( '.citeoryx-admin' ) ).toBeVisible();
	await expect( page.locator( errorOverlaySelector ) ).toHaveCount( 0 );
	expect( errors, errors.join( '\n' ) ).toEqual( [] );
};

module.exports = { expectHealthyAdminPage, watchBrowserErrors };
