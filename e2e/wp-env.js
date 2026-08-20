const { execFileSync } = require( 'node:child_process' );
const { existsSync } = require( 'node:fs' );
const path = require( 'node:path' );

const getNpxInvocation = () => {
	if ( process.platform !== 'win32' ) {
		return { command: 'npx', args: [] };
	}

	const candidates = [
		process.env.npm_execpath &&
			path.join( path.dirname( process.env.npm_execpath ), 'npx-cli.js' ),
		path.join(
			path.dirname( process.execPath ),
			'node_modules',
			'npm',
			'bin',
			'npx-cli.js'
		),
	].filter( Boolean );
	const npxCli = candidates.find( existsSync );

	if ( ! npxCli ) {
		throw new Error( 'Unable to locate the npm npx CLI.' );
	}

	return { command: process.execPath, args: [ npxCli ] };
};

const getWpEnvTarget = () => {
	const target = process.env.CITEORYX_E2E_WP_ENV || 'cli';
	if ( ! [ 'cli', 'tests-cli' ].includes( target ) ) {
		throw new Error( `Unsupported wp-env target: ${ target }` );
	}

	return target;
};

const runWp = ( args ) => {
	const npx = getNpxInvocation();
	execFileSync(
		npx.command,
		[
			...npx.args,
			'--yes',
			'@wordpress/env@11.11.0',
			'run',
			getWpEnvTarget(),
			'wp',
			...args,
		],
		{
			cwd: path.resolve( __dirname, '..' ),
			stdio: 'inherit',
		}
	);
};

const updateSiteProfile = ( profile ) => {
	runWp( [
		'option',
		'update',
		'citeoryx_site_profile',
		JSON.stringify( profile ),
		'--format=json',
	] );
};

const getSiteProfileUpdateStatement = ( profile ) => {
	const encodedProfile = Buffer.from( JSON.stringify( profile ) ).toString(
		'base64'
	);
	return `update_option( 'citeoryx_site_profile', json_decode( base64_decode( '${ encodedProfile }' ), true ) );`;
};

const prepareScanJourney = ( profile ) => {
	const script = [
		getSiteProfileUpdateStatement( profile ),
		'global $wpdb;',
		'$table = $wpdb->prefix . CITEORYX_TABLE_SCAN_RUNS;',
		"if ( function_exists( 'as_unschedule_all_actions' ) ) { as_unschedule_all_actions( 'citeoryx_run_scan', array(), 'citeoryx' ); }",
		"$wpdb->query( $wpdb->prepare( \"UPDATE %i SET status = 'cancelled', finished_at = %s WHERE status IN ('queued', 'running')\", $table, current_time( 'mysql' ) ) );",
		"wp_clear_scheduled_hook( 'citeoryx_run_scan' );",
		'\\Citeoryx\\Infrastructure\\Cache\\RestResponseCache::invalidate();',
	].join( ' ' );

	runWp( [ 'eval', script ] );
};

const prepareIssueJourney = ( profile ) => {
	const script = [
		getSiteProfileUpdateStatement( profile ),
		'global $wpdb;',
		'$table = $wpdb->prefix . CITEORYX_TABLE_ISSUES;',
		"$wpdb->delete( $table, array( 'issue_code' => 'CX_E2E_VISIBLE_ISSUE' ), array( '%s' ) );",
		'$issue = new \\Citeoryx\\Domain\\Issue\\Issue();',
		"$issue->issue_code = 'CX_E2E_VISIBLE_ISSUE';",
		"$issue->category = 'content';",
		"$issue->severity = 'high';",
		"$issue->confidence = 'high';",
		'$issue->impact_score = 90;',
		'$issue->effort_score = 20;',
		'$issue->priority_score = 88.5;',
		"$issue->title = 'E2E visible issue';",
		"$issue->recommendation = 'Review this deterministic test issue.';",
		'( new \\Citeoryx\\Domain\\Issue\\IssueRepository() )->save( $issue );',
	].join( ' ' );

	runWp( [ 'eval', script ] );
};

const prepareOptimizerJourney = ( profile ) => {
	const script = [
		getSiteProfileUpdateStatement( profile ),
		'global $wpdb;',
		"$existing = get_page_by_path( 'citeoryx-e2e-optimizer', OBJECT, 'post' );",
		'if ( $existing ) {',
		'$repo = new \\Citeoryx\\Domain\\Content\\ContentRepository();',
		"$old_item = $repo->find_by_object( 'post', $existing->ID );",
		'if ( $old_item ) {',
		"$wpdb->delete( $wpdb->prefix . CITEORYX_TABLE_ISSUES, array( 'content_id' => $old_item->id ), array( '%d' ) );",
		"$wpdb->delete( $wpdb->prefix . CITEORYX_TABLE_LINKS, array( 'source_content_id' => $old_item->id ), array( '%d' ) );",
		"$wpdb->delete( $wpdb->prefix . CITEORYX_TABLE_LINKS, array( 'target_content_id' => $old_item->id ), array( '%d' ) );",
		"$wpdb->delete( $wpdb->prefix . CITEORYX_TABLE_METRICS_DAILY, array( 'content_id' => $old_item->id ), array( '%d' ) );",
		"$wpdb->delete( $wpdb->prefix . CITEORYX_TABLE_QUERY_PAGES, array( 'content_id' => $old_item->id ), array( '%d' ) );",
		"$wpdb->delete( $wpdb->prefix . CITEORYX_TABLE_CONTENT_ITEMS, array( 'id' => $old_item->id ), array( '%d' ) );",
		'}',
		'wp_delete_post( $existing->ID, true );',
		'}',
		'$post_id = wp_insert_post( array(',
		"'post_title' => 'Citeoryx E2E Optimizer Source',",
		"'post_name' => 'citeoryx-e2e-optimizer',",
		"'post_content' => '<p>Brief optimizer fixture.</p>',",
		"'post_excerpt' => 'Original E2E excerpt.',",
		"'post_status' => 'publish',",
		"'post_type' => 'post',",
		"'post_author' => 1,",
		'), true );',
		'if ( is_wp_error( $post_id ) ) { throw new \\RuntimeException( $post_id->get_error_message() ); }',
		'$container = new \\Citeoryx\\Core\\Container();',
		"$item = $container->get( \\Citeoryx\\Application\\Scan\\ContentScanner::class )->scan_post( $post_id, 'post' );",
		"if ( ! $item ) { throw new \\RuntimeException( 'Unable to create optimizer fixture.' ); }",
		'$container->get( \\Citeoryx\\Application\\Analyze\\IssueEngine::class )->analyze( $item );',
	].join( ' ' );

	runWp( [ 'eval', script ] );
};

const publishOptimizerRevision = ( contentId, revisionId ) => {
	const script = [
		`$revision_id = ${ revisionId };`,
		'$revision = wp_get_post_revision( $revision_id );',
		"if ( ! $revision ) { throw new \\RuntimeException( 'Optimizer revision not found.' ); }",
		'$updated = wp_update_post( array(',
		"'ID' => $revision->post_parent,",
		"'post_title' => $revision->post_title,",
		"'post_content' => $revision->post_content,",
		"'post_excerpt' => $revision->post_excerpt,",
		"'post_status' => 'publish',",
		'), true );',
		'if ( is_wp_error( $updated ) ) { throw new \\RuntimeException( $updated->get_error_message() ); }',
		"$published = current_datetime()->modify( '-8 days' );",
		"$published_at = $published->format( 'Y-m-d H:i:s' );",
		'global $wpdb;',
		"$wpdb->update( $wpdb->posts, array( 'post_modified' => $published_at, 'post_modified_gmt' => get_gmt_from_date( $published_at ) ), array( 'ID' => $revision->post_parent ), array( '%s', '%s' ), array( '%d' ) );",
		'clean_post_cache( $revision->post_parent );',
		`$content_id = ${ contentId };`,
		"$baseline_day = $published->modify( '-1 day' )->format( 'Y-m-d' );",
		"$current_day = $published->format( 'Y-m-d' );",
		'$metrics = new \\Citeoryx\\Domain\\Metrics\\MetricsRepository();',
		"$metrics->save( $content_id, $baseline_day, 'google_search_console', array( 'impressions' => 100, 'clicks' => 10, 'position_avg' => 4 ) );",
		"$metrics->save( $content_id, $current_day, 'google_search_console', array( 'impressions' => 200, 'clicks' => 30, 'position_avg' => 2 ) );",
		"$metrics->save( $content_id, $baseline_day, 'bing_webmaster_tools', array( 'impressions' => 40, 'clicks' => 4, 'position_avg' => 7 ) );",
		"$metrics->save( $content_id, $current_day, 'bing_webmaster_tools', array( 'impressions' => 60, 'clicks' => 3, 'position_avg' => 8 ) );",
	].join( ' ' );

	runWp( [ 'eval', script ] );
};

const completeSiteProfile = {
	site_type: 'blog',
	primary_goal: 'traffic',
	core_content_types: [ 'post', 'page' ],
	main_language: 'zh_CN',
	main_region: '全球',
	update_rhythm: 'monthly',
	risk_level: 'standard',
	review_cycle_days: 90,
};

module.exports = {
	completeSiteProfile,
	prepareIssueJourney,
	prepareOptimizerJourney,
	prepareScanJourney,
	publishOptimizerRevision,
	runWp,
	updateSiteProfile,
};
