const textBytes = ( value ) => {
	const bytes = [];
	for ( const character of value ) {
		const code = character.codePointAt( 0 );
		if ( code <= 0x7f ) {
			bytes.push( code );
		} else if ( code <= 0x7ff ) {
			bytes.push( 0xc0 + Math.floor( code / 64 ), 0x80 + ( code % 64 ) );
		} else if ( code <= 0xffff ) {
			bytes.push(
				0xe0 + Math.floor( code / 4096 ),
				0x80 + ( Math.floor( code / 64 ) % 64 ),
				0x80 + ( code % 64 )
			);
		} else {
			bytes.push(
				0xf0 + Math.floor( code / 262144 ),
				0x80 + ( Math.floor( code / 4096 ) % 64 ),
				0x80 + ( Math.floor( code / 64 ) % 64 ),
				0x80 + ( code % 64 )
			);
		}
	}
	return Uint8Array.from( bytes );
};

const concatenate = ( parts ) => {
	const length = parts.reduce( ( total, part ) => total + part.length, 0 );
	const output = new Uint8Array( length );
	let offset = 0;
	parts.forEach( ( part ) => {
		output.set( part, offset );
		offset += part.length;
	} );
	return output;
};

const objectBytes = ( id, body ) =>
	concatenate( [
		textBytes( `${ id } 0 obj\n` ),
		...( Array.isArray( body ) ? body : [ textBytes( body ) ] ),
		textBytes( '\nendobj\n' ),
	] );

export const buildPdfFromJpegPages = ( pages ) => {
	if ( ! Array.isArray( pages ) || pages.length === 0 ) {
		throw new Error( 'PDF requires at least one page.' );
	}

	const pageWidth = 595.28;
	const pageHeight = 841.89;
	const objects = new Map();
	const pageIds = pages.map( ( page, index ) => 3 + index * 3 );
	objects.set( 1, objectBytes( 1, '<< /Type /Catalog /Pages 2 0 R >>' ) );
	objects.set(
		2,
		objectBytes(
			2,
			`<< /Type /Pages /Count ${ pages.length } /Kids [${ pageIds
				.map( ( id ) => `${ id } 0 R` )
				.join( ' ' ) }] >>`
		)
	);

	pages.forEach( ( page, index ) => {
		if (
			! ( page.bytes instanceof Uint8Array ) ||
			! page.width ||
			! page.height
		) {
			throw new Error( 'PDF page image is invalid.' );
		}
		const pageId = pageIds[ index ];
		const imageId = pageId + 1;
		const contentId = pageId + 2;
		const content = `q\n${ pageWidth } 0 0 ${ pageHeight } 0 0 cm\n/Im${ index } Do\nQ`;
		objects.set(
			pageId,
			objectBytes(
				pageId,
				`<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ${ pageWidth } ${ pageHeight }] /Resources << /XObject << /Im${ index } ${ imageId } 0 R >> >> /Contents ${ contentId } 0 R >>`
			)
		);
		objects.set(
			imageId,
			objectBytes( imageId, [
				textBytes(
					`<< /Type /XObject /Subtype /Image /Width ${ page.width } /Height ${ page.height } /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ${ page.bytes.length } >>\nstream\n`
				),
				page.bytes,
				textBytes( '\nendstream' ),
			] )
		);
		objects.set(
			contentId,
			objectBytes(
				contentId,
				`<< /Length ${
					textBytes( content ).length
				} >>\nstream\n${ content }\nendstream`
			)
		);
	} );

	const header = textBytes( '%PDF-1.4\n%\xE2\xE3\xCF\xD3\n' );
	const parts = [ header ];
	const offsets = [ 0 ];
	let length = header.length;
	const maxId = 2 + pages.length * 3;
	for ( let id = 1; id <= maxId; id++ ) {
		offsets[ id ] = length;
		parts.push( objects.get( id ) );
		length += objects.get( id ).length;
	}
	const xrefOffset = length;
	const xref = [ `xref\n0 ${ maxId + 1 }\n`, '0000000000 65535 f \n' ];
	for ( let id = 1; id <= maxId; id++ ) {
		xref.push(
			`${ String( offsets[ id ] ).padStart( 10, '0' ) } 00000 n \n`
		);
	}
	parts.push(
		textBytes(
			`${ xref.join( '' ) }trailer\n<< /Size ${
				maxId + 1
			} /Root 1 0 R >>\nstartxref\n${ xrefOffset }\n%%EOF\n`
		)
	);
	return concatenate( parts );
};
