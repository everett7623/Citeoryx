const { defineConfig, devices } = require( '@playwright/test' );

module.exports = defineConfig( {
	testDir: './e2e',
	fullyParallel: false,
	workers: 1,
	forbidOnly: Boolean( process.env.CI ),
	retries: process.env.CI ? 2 : 0,
	timeout: 45_000,
	expect: { timeout: 10_000 },
	globalSetup: require.resolve( './e2e/global-setup' ),
	outputDir: 'test-results/e2e',
	reporter: process.env.CI
		? [ [ 'line' ], [ 'html', { open: 'never' } ] ]
		: [ [ 'list' ], [ 'html', { open: 'never' } ] ],
	use: {
		baseURL: process.env.CITEORYX_E2E_BASE_URL || 'http://localhost:8889',
		storageState: 'e2e/.auth/admin.json',
		screenshot: 'only-on-failure',
		trace: 'on-first-retry',
		video: 'retain-on-failure',
	},
	projects: [
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
		},
	],
} );
