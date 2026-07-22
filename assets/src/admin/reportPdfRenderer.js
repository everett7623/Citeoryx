import { __ } from '@wordpress/i18n';
import { buildReportSections } from './reportPdfData';

export const PAGE_WIDTH = 1190;
export const PAGE_HEIGHT = 1684;

const MARGIN = 76;
const CONTENT_WIDTH = PAGE_WIDTH - MARGIN * 2;
const BOTTOM = PAGE_HEIGHT - 112;
const FONT =
	'"Microsoft YaHei", "PingFang SC", "Noto Sans CJK SC", Arial, sans-serif';
const COLORS = {
	navy: '#163b65',
	blue: '#246fa8',
	cyan: '#26a6b5',
	ink: '#1f2937',
	muted: '#64748b',
	line: '#dbe5ee',
	soft: '#f3f7fa',
	white: '#ffffff',
};

const setFont = ( context, size, weight = 400 ) => {
	context.font = `${ weight } ${ size }px ${ FONT }`;
};

const text = ( value ) => String( value ?? '' );

const truncate = ( context, value, width ) => {
	const source = text( value );
	if ( context.measureText( source ).width <= width ) {
		return source;
	}
	let output = '';
	for ( const character of Array.from( source ) ) {
		if (
			context.measureText( `${ output }${ character }…` ).width > width
		) {
			break;
		}
		output += character;
	}
	return `${ output }…`;
};

const wrap = ( context, value, width, maxLines = 2 ) => {
	const source = text( value );
	const characters = Array.from( source );
	const lines = [];
	let line = '';
	for ( const [ index, character ] of characters.entries() ) {
		if (
			line &&
			context.measureText( `${ line }${ character }` ).width > width
		) {
			lines.push( line );
			line = character;
			if ( lines.length === maxLines ) {
				lines[ maxLines - 1 ] = truncate(
					context,
					`${ lines[ maxLines - 1 ] }${ characters
						.slice( index )
						.join( '' ) }`,
					width
				);
				return lines;
			}
		} else {
			line += character;
		}
	}
	if ( line || lines.length === 0 ) {
		lines.push( line );
	}
	return lines;
};

const paintPageHeader = ( context, meta, first ) => {
	context.fillStyle = COLORS.white;
	context.fillRect( 0, 0, PAGE_WIDTH, PAGE_HEIGHT );
	context.fillStyle = COLORS.navy;
	context.fillRect( 0, 0, PAGE_WIDTH, first ? 230 : 105 );
	context.fillStyle = COLORS.cyan;
	context.fillRect( MARGIN, first ? 62 : 36, 58, 8 );
	setFont( context, first ? 46 : 28, 700 );
	context.fillStyle = COLORS.white;
	context.fillText(
		__( 'Citeoryx 内容健康报告', 'citeoryx' ),
		MARGIN,
		first ? 130 : 68
	);
	setFont( context, first ? 24 : 18, 400 );
	context.fillStyle = '#dceaf5';
	context.fillText(
		truncate( context, meta.siteName, CONTENT_WIDTH ),
		MARGIN,
		first ? 174 : 92
	);
	if ( first ) {
		setFont( context, 18, 400 );
		context.fillText(
			`${ __( '生成时间：', 'citeoryx' ) }${ meta.generatedAt }`,
			MARGIN,
			207
		);
	}
};

const paintFooters = ( pages, meta ) => {
	pages.forEach( ( canvas, index ) => {
		const context = canvas.getContext( '2d' );
		context.strokeStyle = COLORS.line;
		context.beginPath();
		context.moveTo( MARGIN, PAGE_HEIGHT - 76 );
		context.lineTo( PAGE_WIDTH - MARGIN, PAGE_HEIGHT - 76 );
		context.stroke();
		setFont( context, 16, 400 );
		context.fillStyle = COLORS.muted;
		context.fillText(
			`Citeoryx ${ meta.version || '' }`,
			MARGIN,
			PAGE_HEIGHT - 42
		);
		context.textAlign = 'right';
		context.fillText(
			`${ index + 1 } / ${ pages.length }`,
			PAGE_WIDTH - MARGIN,
			PAGE_HEIGHT - 42
		);
		context.textAlign = 'left';
	} );
};

const sectionTitle = ( state, title, continued = false ) => {
	setFont( state.context, 27, 700 );
	state.context.fillStyle = COLORS.navy;
	state.context.fillText(
		continued ? `${ title } (${ __( '续', 'citeoryx' ) })` : title,
		MARGIN,
		state.y + 30
	);
	state.context.fillStyle = COLORS.cyan;
	state.context.fillRect( MARGIN, state.y + 45, 52, 5 );
	state.y += 68;
};

const newPage = ( state ) => {
	const canvas = state.createCanvas();
	canvas.width = PAGE_WIDTH;
	canvas.height = PAGE_HEIGHT;
	state.pages.push( canvas );
	state.context = canvas.getContext( '2d' );
	paintPageHeader( state.context, state.meta, state.pages.length === 1 );
	state.y = state.pages.length === 1 ? 280 : 145;
};

const ensureSpace = ( state, height ) => {
	if ( state.y + height <= BOTTOM ) {
		return false;
	}
	newPage( state );
	return true;
};

const drawMetrics = ( state, section ) => {
	ensureSpace( state, 390 );
	sectionTitle( state, section.title );
	const gap = 22;
	const width = ( CONTENT_WIDTH - gap * 2 ) / 3;
	section.items.forEach( ( item, index ) => {
		const column = index % 3;
		const row = Math.floor( index / 3 );
		const x = MARGIN + column * ( width + gap );
		const y = state.y + row * 132;
		state.context.fillStyle = COLORS.soft;
		state.context.fillRect( x, y, width, 108 );
		state.context.fillStyle = COLORS.blue;
		state.context.fillRect( x, y, 7, 108 );
		setFont( state.context, 31, 700 );
		state.context.fillStyle = COLORS.ink;
		state.context.fillText(
			truncate( state.context, item.value, width - 38 ),
			x + 24,
			y + 45
		);
		setFont( state.context, 17, 400 );
		state.context.fillStyle = COLORS.muted;
		state.context.fillText(
			truncate( state.context, item.label, width - 38 ),
			x + 24,
			y + 79
		);
	} );
	state.y += 286;
};

const columnPositions = ( columns ) => {
	let x = MARGIN;
	return columns.map( ( column ) => {
		const width = CONTENT_WIDTH * column.width;
		const result = { ...column, x, width };
		x += width;
		return result;
	} );
};

const tableHeader = ( state, columns ) => {
	state.context.fillStyle = COLORS.navy;
	state.context.fillRect( MARGIN, state.y, CONTENT_WIDTH, 48 );
	setFont( state.context, 17, 700 );
	state.context.fillStyle = COLORS.white;
	columns.forEach( ( column ) => {
		state.context.fillText( column.label, column.x + 14, state.y + 31 );
	} );
	state.y += 48;
};

const drawTable = ( state, section ) => {
	const columns = columnPositions( section.columns );
	ensureSpace( state, 210 );
	sectionTitle( state, section.title );
	tableHeader( state, columns );
	if ( section.rows.length === 0 ) {
		state.context.fillStyle = COLORS.soft;
		state.context.fillRect( MARGIN, state.y, CONTENT_WIDTH, 58 );
		setFont( state.context, 17, 400 );
		state.context.fillStyle = COLORS.muted;
		state.context.fillText(
			section.emptyMessage,
			MARGIN + 14,
			state.y + 36
		);
		state.y += 86;
		return;
	}

	section.rows.forEach( ( row, index ) => {
		setFont( state.context, 17, 400 );
		const cells = columns.map( ( column ) => ( {
			...column,
			lines: wrap( state.context, row[ column.key ], column.width - 28 ),
		} ) );
		const lineCount = Math.max(
			...cells.map( ( cell ) => cell.lines.length )
		);
		const rowHeight = Math.max( 52, lineCount * 25 + 22 );
		if ( ensureSpace( state, rowHeight ) ) {
			sectionTitle( state, section.title, true );
			tableHeader( state, columns );
		}
		setFont( state.context, 17, 400 );
		state.context.fillStyle = index % 2 === 0 ? COLORS.white : COLORS.soft;
		state.context.fillRect( MARGIN, state.y, CONTENT_WIDTH, rowHeight );
		state.context.strokeStyle = COLORS.line;
		state.context.strokeRect( MARGIN, state.y, CONTENT_WIDTH, rowHeight );
		state.context.fillStyle = COLORS.ink;
		cells.forEach( ( cell ) => {
			cell.lines.forEach( ( line, lineIndex ) => {
				state.context.fillText(
					line,
					cell.x + 14,
					state.y + 25 + lineIndex * 25
				);
			} );
		} );
		state.y += rowHeight;
	} );
	state.y += 34;
};

export const renderReportPages = (
	data,
	meta,
	createCanvas = () => document.createElement( 'canvas' )
) => {
	const state = { pages: [], context: null, y: 0, meta, createCanvas };
	newPage( state );
	buildReportSections( data ).forEach( ( section ) => {
		if ( 'metrics' === section.type ) {
			drawMetrics( state, section );
		} else {
			drawTable( state, section );
		}
	} );
	paintFooters( state.pages, meta );
	return state.pages;
};
