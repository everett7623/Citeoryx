const fs = require( 'node:fs/promises' );
const { expect, test } = require( '@playwright/test' );
const {
	expectHealthyAdminPage,
	watchBrowserErrors,
} = require( './browser-health' );
const { installAiAnalysisMock } = require( './ai-analysis-mock' );
const {
	completeSiteProfile,
	prepareIssueJourney,
	prepareOptimizerJourney,
	prepareScanJourney,
	publishOptimizerRevision,
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
		test.setTimeout( 75_000 );
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
		test.setTimeout( 75_000 );
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

	test( '管理员可生成建议并创建安全 Revision', async ( { page } ) => {
		test.setTimeout( 120_000 );
		prepareOptimizerJourney( completeSiteProfile );
		const errors = watchBrowserErrors( page );
		const contentResponsePromise = page.waitForResponse( ( response ) => {
			const url = decodeURIComponent( response.url() );
			return (
				'GET' === response.request().method() &&
				url.includes( '/citeoryx/v1/content' ) &&
				url.includes( 'per_page=20' )
			);
		} );
		await page.goto( `${ adminUrl }#/optimizer` );
		await expect(
			page.getByRole( 'heading', { name: '内容优化器' } )
		).toBeVisible();

		const contentResponse = await contentResponsePromise;
		const contentPayload = await contentResponse.json();
		expect( contentResponse.status() ).toBe( 200 );
		const fixture = contentPayload.data.items.find(
			( item ) => item.metadata?.title === 'Citeoryx E2E Optimizer Source'
		);
		expect( fixture ).toEqual(
			expect.objectContaining( { id: expect.any( Number ) } )
		);
		const contentId = fixture.id.toString();
		const contentSelect = page.getByLabel( '选择内容' );
		await contentSelect.selectOption( contentId );
		await expect( contentSelect ).toHaveValue( contentId );

		const analyzeResponsePromise = page.waitForResponse(
			( response ) =>
				'GET' === response.request().method() &&
				decodeURIComponent( response.url() ).includes(
					`/citeoryx/v1/optimizer/${ contentId }`
				)
		);
		await page.getByRole( 'button', { name: '生成优化建议' } ).click();
		const analyzeResponse = await analyzeResponsePromise;
		const analyzePayload = await analyzeResponse.json();
		expect( analyzeResponse.status() ).toBe( 200 );
		expect( analyzePayload.success ).toBe( true );
		expect( analyzePayload.data.content.id ).toBe( Number( contentId ) );
		expect( analyzePayload.data.editor.available ).toBe( true );
		expect( analyzePayload.data.editor.revisions_enabled ).toBe( true );
		expect(
			analyzePayload.data.recommendations.map( ( item ) => item.title )
		).toEqual(
			expect.arrayContaining( [
				'扩展内容深度',
				'增加章节标题',
				'添加内链',
			] )
		);
		await expect(
			page.getByText( '创建安全修订', { exact: true } )
		).toBeVisible();

		await page
			.getByLabel( '拟议标题' )
			.fill( 'Citeoryx E2E Optimized Proposal' );
		await page
			.getByLabel( '拟议正文' )
			.fill(
				'<p>Expanded optimizer proposal with verified evidence.</p>'
			);
		await page
			.getByLabel( '更新说明（可选）' )
			.fill( 'Playwright optimizer workflow.' );
		await expect(
			page.getByText( '拟议版本', { exact: true } )
		).toHaveCount( 2 );

		const revisionResponsePromise = page.waitForResponse(
			( response ) =>
				'POST' === response.request().method() &&
				decodeURIComponent( response.url() ).includes(
					'/citeoryx/v1/recommendations/apply'
				)
		);
		await page.getByRole( 'button', { name: '创建 Revision' } ).click();
		const revisionResponse = await revisionResponsePromise;
		const revisionPayload = await revisionResponse.json();
		expect( revisionResponse.status() ).toBe( 201 );
		expect( revisionPayload.success ).toBe( true );
		expect( revisionPayload.data.revision.created ).toBe( true );
		expect( revisionPayload.data.revision.id ).toEqual(
			expect.any( Number )
		);
		expect( revisionPayload.data.revision.parent_id ).toBe(
			analyzePayload.data.content.object_id
		);
		expect( revisionPayload.data.workflow.state ).toBe( 'awaiting_review' );
		expect( revisionPayload.data.workflow.published ).toBe( false );
		await expect(
			page.getByText( /修订已创建，可在 WordPress 中比较并审核/ )
		).toBeVisible();

		const refreshResponsePromise = page.waitForResponse(
			( response ) =>
				'GET' === response.request().method() &&
				decodeURIComponent( response.url() ).includes(
					`/citeoryx/v1/optimizer/${ contentId }`
				)
		);
		await page.getByRole( 'button', { name: '刷新状态' } ).click();
		const refreshResponse = await refreshResponsePromise;
		const refreshPayload = await refreshResponse.json();
		expect( refreshResponse.status() ).toBe( 200 );
		expect( refreshPayload.data.editor.title ).toBe(
			'Citeoryx E2E Optimizer Source'
		);
		expect( refreshPayload.data.editor.workflow.state ).toBe(
			'awaiting_review'
		);

		publishOptimizerRevision(
			Number( contentId ),
			revisionPayload.data.revision.id
		);
		const publishedResponsePromise = page.waitForResponse(
			( response ) =>
				'GET' === response.request().method() &&
				decodeURIComponent( response.url() ).includes(
					`/citeoryx/v1/optimizer/${ contentId }`
				)
		);
		await page.getByRole( 'button', { name: '刷新状态' } ).click();
		const publishedResponse = await publishedResponsePromise;
		const publishedPayload = await publishedResponse.json();
		expect( publishedPayload.data.editor.workflow.state ).toBe(
			'published_pending_scan'
		);
		expect( publishedPayload.data.editor.workflow.can_verify ).toBe( true );
		await expect(
			page.getByText( /提案已应用到公开内容，等待重新扫描验证/ )
		).toBeVisible();

		const scanResponsePromise = page.waitForResponse(
			( response ) =>
				'POST' === response.request().method() &&
				decodeURIComponent( response.url() ).includes(
					`/citeoryx/v1/content/${ contentId }/scan`
				)
		);
		const verifiedResponsePromise = page.waitForResponse(
			( response ) =>
				'GET' === response.request().method() &&
				decodeURIComponent( response.url() ).includes(
					`/citeoryx/v1/optimizer/${ contentId }`
				)
		);
		await page.getByRole( 'button', { name: '重新扫描并验证' } ).click();
		const scanResponse = await scanResponsePromise;
		const verifiedResponse = await verifiedResponsePromise;
		const verifiedPayload = await verifiedResponse.json();
		expect( scanResponse.status() ).toBe( 200 );
		expect( verifiedPayload.data.editor.workflow.state ).toBe( 'verified' );
		expect( verifiedPayload.data.revision_performance.available ).toBe(
			true
		);
		expect(
			verifiedPayload.data.revision_performance.windows[ 0 ].state
		).toBe( 'ready' );
		await expect(
			page
				.locator( '.citeoryx-revision-workflow' )
				.getByText( /提案已发布，并通过最新内容扫描验证/ )
		).toBeVisible();

		const performance = page.locator( '.citeoryx-revision-performance' );
		const sevenDayWindow = performance
			.locator( '.citeoryx-revision-performance__window' )
			.filter( { hasText: '7 天对比' } );
		const google = sevenDayWindow
			.locator( '.citeoryx-revision-performance__source' )
			.filter( { hasText: 'Google Search Console' } );
		const bing = sevenDayWindow
			.locator( '.citeoryx-revision-performance__source' )
			.filter( { hasText: 'Bing Webmaster Tools' } );
		await expect( performance.getByText( '发布后效果' ) ).toBeVisible();
		await expect(
			sevenDayWindow.getByText( '已收集完整对比周期。' )
		).toBeVisible();
		await expect(
			google.getByRole( 'row' ).filter( { hasText: '点击' } )
		).toContainText( '+20' );
		await expect(
			bing.getByRole( 'row' ).filter( { hasText: '点击' } )
		).toContainText( '-1' );
		await expectHealthyAdminPage( page, errors );
	} );

	test( '管理员可启动并读取 AI 深度分析结果', async ( { page } ) => {
		test.setTimeout( 75_000 );
		prepareOptimizerJourney( completeSiteProfile );
		const aiMock = await installAiAnalysisMock( page );
		const errors = watchBrowserErrors( page );
		const contentResponsePromise = page.waitForResponse( ( response ) => {
			const url = decodeURIComponent( response.url() );
			return (
				'GET' === response.request().method() &&
				url.includes( '/citeoryx/v1/content' ) &&
				url.includes( 'per_page=20' )
			);
		} );

		await page.goto( `${ adminUrl }#/optimizer` );
		const contentPayload = await ( await contentResponsePromise ).json();
		const fixture = contentPayload.data.items.find(
			( item ) => item.metadata?.title === 'Citeoryx E2E Optimizer Source'
		);
		expect( fixture ).toEqual(
			expect.objectContaining( { id: expect.any( Number ) } )
		);
		const contentId = fixture.id.toString();
		await page.getByLabel( '选择内容' ).selectOption( contentId );
		await page.getByRole( 'button', { name: '生成优化建议' } ).click();

		const panel = page.locator( '.citeoryx-ai-analysis' );
		await expect(
			panel.getByRole( 'button', { name: '开始 AI 深度分析' } )
		).toBeVisible();
		const triggerResponsePromise = page.waitForResponse(
			( response ) =>
				'POST' === response.request().method() &&
				decodeURIComponent( response.url() ).includes(
					`/citeoryx/v1/integrations/ai/analyze/${ contentId }`
				)
		);
		await panel.getByRole( 'button', { name: '开始 AI 深度分析' } ).click();

		const triggerResponse = await triggerResponsePromise;
		const triggerPayload = await triggerResponse.json();
		expect( triggerResponse.status() ).toBe( 202 );
		expect( triggerPayload.data.task_id ).toBe( aiMock.taskId );
		expect( triggerPayload.data.status ).toBe( 'queued' );
		await expect( panel.getByText( /AI 正在后台分析/ ) ).toBeVisible();
		await expect(
			panel.getByRole( 'button', { name: '后台分析中…' } )
		).toBeDisabled();

		await expect(
			panel.getByText( 'E2E AI analysis completed.' )
		).toBeVisible();
		await expect( panel.getByText( '84', { exact: true } ) ).toBeVisible();
		await expect( panel.getByText( 'Add cited evidence' ) ).toBeVisible();
		await expect(
			panel.getByRole( 'button', { name: '重新分析' } )
		).toBeEnabled();
		expect( aiMock.getPollCount() ).toBeGreaterThanOrEqual( 1 );
		await expectHealthyAdminPage( page, errors );
	} );
} );
