import { buildPdfFromJpegPages } from './reportPdfDocument';

const jpeg = Uint8Array.from( [
	0xff, 0xd8, 0xff, 0xe0, 0x00, 0x10, 0x4a, 0x46, 0x49, 0x46, 0xff, 0xd9,
] );

describe( 'buildPdfFromJpegPages', () => {
	it( 'builds a structurally complete multi-page PDF', () => {
		const bytes = buildPdfFromJpegPages( [
			{ bytes: jpeg, width: 10, height: 10 },
			{ bytes: jpeg, width: 10, height: 10 },
		] );
		const text = String.fromCharCode( ...bytes );

		expect( text.startsWith( '%PDF-1.4' ) ).toBe( true );
		expect( text ).toContain( '/Count 2' );
		expect( text ).toContain( '/Filter /DCTDecode' );
		expect( text ).toContain( 'xref\n0 9' );
		expect( text.endsWith( '%%EOF\n' ) ).toBe( true );
	} );

	it( 'rejects empty or invalid pages before download', () => {
		expect( () => buildPdfFromJpegPages( [] ) ).toThrow(
			'PDF requires at least one page.'
		);
		expect( () =>
			buildPdfFromJpegPages( [
				{ bytes: 'not-bytes', width: 10, height: 10 },
			] )
		).toThrow( 'PDF page image is invalid.' );
	} );
} );
