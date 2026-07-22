const fs = require( 'node:fs/promises' );
const { expect, test } = require( '@playwright/test' );
const {
	expectHealthyAdminPage,
	watchBrowserErrors,
} = require( './browser-health' );
const { completeSiteProfile, updateSiteProfile } = require( './wp-env' );

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
} );
