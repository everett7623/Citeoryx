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
		"$wpdb->query( $wpdb->prepare( \"UPDATE %i SET status = 'cancelled', finished_at = %s WHERE status IN ('queued', 'running')\", $table, current_time( 'mysql' ) ) );",
		"wp_clear_scheduled_hook( 'citeoryx_run_scan' );",
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
	prepareScanJourney,
	runWp,
	updateSiteProfile,
};
