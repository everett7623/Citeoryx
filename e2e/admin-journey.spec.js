const fs = require( 'node:fs/promises' );
const { expect, test } = require( '@playwright/test' );
const {
	expectHealthyAdminPage,
	watchBrowserErrors,
} = require( './browser-health' );
const {
	completeSiteProfile,
	prepareIssueJourney,
	prepareScanJourney,
	updateSiteProfile,
} = require( './wp-env' );

const adminUrl = '/wp-admin/admin.php?page=citeoryx-dashboard';

test.describe( 'Citeoryx 管理后台关键旅程', () => {
	test( '管理员完成首次站点画像', async ( { page } ) => {
		updateSiteProfile( [] );
		const errors = watchBrowserErrors( page );
		await page.goto( adminUrl );
		await expect(
			page.getByRole( 'heading', { name: '欢迎使用 Citeoryx' } )
		).toBeVisible();

		await page.getByLabel( '站点类型' ).selectOption( 'blog' );
		await page.getByLabel( '主要目标' ).selectOption( 'traffic' );
		await page.getByLabel( '主要语言' ).fill( 'zh_CN' );
		await page.getByLabel( '主要地区' ).fill( '全球' );
		await page.getByLabel( '更新节奏' ).selectOption( 'monthly' );
		await page.getByLabel( '内容风险等级' ).selectOption( 'standard' );
		await page.getByLabel( '默认审查周期' ).selectOption( '90' );
		await page.getByRole( 'button', { name: '开始使用' } ).click();

		await expect(
			page.getByRole( 'heading', { name: 'Citeoryx', exact: true } )
		).toBeVisible();
		await expectHealthyAdminPage( page, errors );
	} );

	test( '管理员可打开规划与报告页面', async ( { page } ) => {
		updateSiteProfile( completeSiteProfile );
		const errors = watchBrowserErrors( page );
		await page.goto( adminUrl );
		await page.getByRole( 'tab', { name: '内容规划' } ).click();
		await expect( page.getByLabel( '机会类型' ) ).toBeVisible();
		await expect( page ).toHaveURL( /#\/planning$/ );

		await page.getByRole( 'tab', { name: '报告' } ).click();
		await expect(
			page.getByText( '站点摘要', { exact: true } )
		).toBeVisible();
		await expect( page ).toHaveURL( /#\/reports$/ );
		await expectHealthyAdminPage( page, errors );
	} );

	test( '管理员可下载有效 PDF 报告', async ( { page }, testInfo ) => {
		updateSiteProfile( completeSiteProfile );
		const errors = watchBrowserErrors( page );
		await page.goto( `${ adminUrl }#/reports` );
		await expect(
			page.getByText( '站点摘要', { exact: true } )
		).toBeVisible();

		const downloadPromise = page.waitForEvent( 'download' );
		await page.getByRole( 'button', { name: '导出 PDF' } ).click();
		const download = await downloadPromise;
		const filePath = testInfo.outputPath( download.suggestedFilename() );
		await download.saveAs( filePath );
		const file = await fs.readFile( filePath );

		expect( download.suggestedFilename() ).toMatch( /\.pdf$/i );
		expect( file.subarray( 0, 5 ).toString() ).toBe( '%PDF-' );
		expect( file.byteLength ).toBeGreaterThan( 1_000 );
		await expectHealthyAdminPage( page, errors );
	} );

	test( '管理员可启动扫描并读取任务进度', async ( { page } ) => {
		test.setTimeout( 75_000 );
		prepareScanJourney( completeSiteProfile );
		const errors = watchBrowserErrors( page );
		await page.goto( adminUrl );

		const scanButton = page.getByRole( 'button', {
			name: /^(开始扫描|运行增量扫描)$/,
		} );
		await expect( scanButton ).toBeEnabled();

		const createResponsePromise = page.waitForResponse(
			( response ) =>
				'POST' === response.request().method() &&
				decodeURIComponent( response.url() ).includes(
					'/citeoryx/v1/scans'
				)
		);
		const progressResponsePromise = page.waitForResponse(
			( response ) =>
				'GET' === response.request().method() &&
				/\/citeoryx\/v1\/scans\/\d+/.test(
					decodeURIComponent( response.url() )
				)
		);

		await scanButton.click();
		const createResponse = await createResponsePromise;
		const createPayload = await createResponse.json();

		expect( createResponse.status() ).toBe( 202 );
		expect( createPayload.success ).toBe( true );
		expect( createPayload.data.id ).toEqual( expect.any( Number ) );
		expect( createPayload.data.id ).toBeGreaterThan( 0 );
		expect( createPayload.data.scan_type ).toBe( 'incremental' );
		expect( createPayload.data.trigger_type ).toBe( 'manual' );
		expect( [ 'queued', 'running' ] ).toContain(
			createPayload.data.status
		);

		const progressResponse = await progressResponsePromise;
		const progressPayload = await progressResponse.json();
		expect( progressResponse.status() ).toBe( 200 );
		expect( progressPayload.success ).toBe( true );
		expect( progressPayload.data.id ).toBe( createPayload.data.id );
		expect( [ 'queued', 'running', 'completed' ] ).toContain(
			progressPayload.data.status
		);
		await expectHealthyAdminPage( page, errors );
	} );

	test( '管理员可查看并解决内容问题', async ( { page } ) => {
		test.setTimeout( 75_000 );
		prepareIssueJourney( completeSiteProfile );
		const errors = watchBrowserErrors( page );
		await page.goto( adminUrl );
		await page.getByRole( 'tab', { name: '问题与机会' } ).click();
		await expect( page ).toHaveURL( /#\/issues$/ );

		const issueRow = page
			.getByRole( 'row' )
			.filter( { hasText: 'CX_E2E_VISIBLE_ISSUE' } );
		await expect( issueRow ).toContainText( 'E2E visible issue' );
		await expect( issueRow ).toContainText( 'high' );
		await expect( issueRow ).toContainText( '88.5' );

		const updateResponsePromise = page.waitForResponse(
			( response ) =>
				'POST' === response.request().method() &&
				/\/citeoryx\/v1\/issues\/\d+/.test(
					decodeURIComponent( response.url() )
				)
		);
		await issueRow.getByRole( 'button', { name: '解决' } ).click();
		const updateResponse = await updateResponsePromise;
		const updatePayload = await updateResponse.json();
		expect( updateResponse.status() ).toBe( 200 );
		expect(
			updateResponse.request().headers()[ 'x-http-method-override' ]
		).toBe( 'PATCH' );
		expect( updatePayload.success ).toBe( true );
		expect( updatePayload.data.issue_code ).toBe( 'CX_E2E_VISIBLE_ISSUE' );
		expect( updatePayload.data.status ).toBe( 'resolved' );
		await expect( issueRow ).toHaveCount( 0 );

		await page.getByLabel( '状态' ).selectOption( 'resolved' );
		const resolvedRow = page
			.getByRole( 'row' )
			.filter( { hasText: 'CX_E2E_VISIBLE_ISSUE' } );
		await expect( resolvedRow ).toContainText( 'E2E visible issue' );
		await expect(
			resolvedRow.getByRole( 'button', { name: '解决' } )
		).toHaveCount( 0 );
		await expectHealthyAdminPage( page, errors );
	} );
} );
