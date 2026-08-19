const fs = require( 'node:fs' );
const path = require( 'node:path' );
const { chromium } = require( '@playwright/test' );
const { runWp } = require( './wp-env' );

const authFile = path.join( __dirname, '.auth', 'admin.json' );

module.exports = async ( config ) => {
	runWp( [ 'plugin', 'activate', 'Citeoryx' ] );
	fs.mkdirSync( path.dirname( authFile ), { recursive: true } );
	const browser = await chromium.launch();
	const page = await browser.newPage();
	const baseURL = config.projects[ 0 ].use.baseURL;

	await page.goto( `${ baseURL }/wp-login.php` );
	await page.getByLabel( 'Username or Email Address' ).fill( 'admin' );
	await page.getByLabel( 'Password', { exact: true } ).fill( 'password' );
	await page.getByRole( 'button', { name: 'Log In' } ).click();
	await page.waitForURL( /\/wp-admin\// );
	await page.context().storageState( { path: authFile } );
	await browser.close();
};
