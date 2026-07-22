import { __ } from '@wordpress/i18n';
import { buildPdfFromJpegPages } from './reportPdfDocument';
import {
	renderReportPages,
	PAGE_HEIGHT,
	PAGE_WIDTH,
} from './reportPdfRenderer';

const validateReport = ( data ) => {
	const requiredArrays = [
		data?.content?.status_counts,
		data?.issues?.severity_counts,
		data?.issues?.category_counts,
		data?.issues?.top_items,
		data?.scans?.recent,
	];
	if (
		! data?.generated_at ||
		! data?.content ||
		! data?.issues ||
		! data?.scans ||
		typeof data.content.total !== 'number' ||
		typeof data.issues.open_total !== 'number' ||
		requiredArrays.some( ( value ) => ! Array.isArray( value ) )
	) {
		throw new Error( __( '报告数据不完整，无法生成 PDF。', 'citeoryx' ) );
	}
};

const canvasPage = ( canvas ) =>
	new Promise( ( resolve, reject ) => {
		canvas.toBlob(
			async ( blob ) => {
				if ( ! blob ) {
					reject(
						new Error( __( 'PDF 页面渲染失败。', 'citeoryx' ) )
					);
					return;
				}
				resolve( {
					bytes: new Uint8Array( await blob.arrayBuffer() ),
					width: PAGE_WIDTH,
					height: PAGE_HEIGHT,
				} );
			},
			'image/jpeg',
			0.92
		);
	} );

export const createReportPdfBytes = async ( data, siteName = '' ) => {
	validateReport( data );
	if ( document.fonts?.ready ) {
		await document.fonts.ready;
	}
	const pages = renderReportPages( data, {
		siteName: siteName || __( 'WordPress 站点', 'citeoryx' ),
		generatedAt: data.generated_at,
		version: data.plugin?.version || '',
	} );
	const images = await Promise.all( pages.map( canvasPage ) );
	return buildPdfFromJpegPages( images );
};

export const exportReportPdf = async ( data ) => {
	const bytes = await createReportPdfBytes(
		data,
		window.citeoryxAdmin?.siteName
	);
	const blob = new Blob( [ bytes ], { type: 'application/pdf' } );
	const url = URL.createObjectURL( blob );
	const link = document.createElement( 'a' );
	link.href = url;
	link.download = `citeoryx-report-${ data.generated_at.slice( 0, 10 ) }.pdf`;
	document.body.appendChild( link );
	link.click();
	link.remove();
	window.setTimeout( () => URL.revokeObjectURL( url ), 0 );
};
