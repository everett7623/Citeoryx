const { execFileSync } = require( 'node:child_process' );
const path = require( 'node:path' );

const runWp = ( args ) => {
	const npx = process.platform === 'win32' ? 'npx.cmd' : 'npx';
	execFileSync(
		npx,
		[ '--yes', '@wordpress/env@11.11.0', 'run', 'cli', 'wp', ...args ],
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

module.exports = { completeSiteProfile, runWp, updateSiteProfile };
