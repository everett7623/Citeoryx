import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join( dirname( fileURLToPath( import.meta.url ) ), '..' );
const read = ( file ) => readFileSync( join( root, file ), 'utf8' );
const failures = [];

const pluginSource = read( 'citeoryx.php' );
const readmeSource = read( 'readme.txt' );
const changelogSource = read( 'CHANGELOG.md' );
const progressSource = read( 'dev-progress.md' );
const packageData = JSON.parse( read( 'package.json' ) );
const packageLockData = JSON.parse( read( 'package-lock.json' ) );

const getMatch = ( source, pattern, label ) => {
	const match = source.match( pattern );
	if ( ! match ) {
		failures.push( `${ label } is missing.` );
		return null;
	}
	return match[ 1 ].trim();
};

const versions = {
	header: getMatch(
		pluginSource,
		/^\s*\*\s*Version:\s*([^\r\n]+)/m,
		'Plugin header version'
	),
	constant: getMatch(
		pluginSource,
		/define\(\s*'CITEORYX_VERSION',\s*'([^']+)'\s*\)/,
		'CITEORYX_VERSION'
	),
	stableTag: getMatch(
		readmeSource,
		/^Stable tag:\s*(\S+)/im,
		'readme.txt Stable tag'
	),
	readmeChangelog: getMatch(
		readmeSource,
		/== Changelog ==[\s\S]*?^=\s+([0-9]+\.[0-9]+\.[0-9]+)\s+=$/m,
		'readme.txt latest changelog version'
	),
	changelog: getMatch(
		changelogSource,
		/^##\s+([0-9]+\.[0-9]+\.[0-9]+)\b/m,
		'CHANGELOG.md latest version'
	),
	progress: getMatch(
		progressSource,
		/^> 当前发布版本：([0-9]+\.[0-9]+\.[0-9]+)\s*$/m,
		'dev-progress.md current version'
	),
	package: packageData.version,
	packageLock: packageLockData.version,
	packageLockRoot: packageLockData.packages?.[ '' ]?.version,
};

const expectedVersion = versions.header;
for ( const [ label, version ] of Object.entries( versions ) ) {
	if ( version && expectedVersion && version !== expectedVersion ) {
		failures.push(
			`Version mismatch: ${ label }=${ version }, expected ${ expectedVersion }.`
		);
	}
}

const generatedPaths = [ 'coverage', 'output' ];
const trackedGeneratedFiles = execFileSync(
	'git',
	[ 'ls-files', '--', ...generatedPaths ],
	{ cwd: root, encoding: 'utf8' }
)
	.trim()
	.split( /\r?\n/ )
	.filter( Boolean );

if ( trackedGeneratedFiles.length > 0 ) {
	failures.push(
		`Generated files are tracked:\n${ trackedGeneratedFiles
			.map( ( file ) => `  - ${ file }` )
			.join( '\n' ) }`
	);
}

if ( failures.length > 0 ) {
	process.stderr.write( `${ failures.join( '\n' ) }\n` );
	process.exitCode = 1;
} else {
	process.stdout.write(
		`Project consistency check passed (version ${ expectedVersion }, no tracked generated artifacts).\n`
	);
}
