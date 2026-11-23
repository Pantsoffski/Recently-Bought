const fs = require( 'node:fs' );
const path = require( 'node:path' );
const { chromium } = require( 'playwright' );

const output = process.argv[ 2 ];
const demo = process.argv[ 3 ];
const targets = [
	[ 'banner.svg', 772, 250, output, 'banner-772x250.png' ],
	[ 'banner.svg', 1544, 500, output, 'banner-1544x500.png' ],
	[ 'icon.svg', 128, 128, output, 'icon-128x128.png' ],
	[ 'icon.svg', 256, 256, output, 'icon-256x256.png' ],
];
if ( demo ) {
	for ( const product of [ 'harbor-mug', 'dune-bowl', 'moss-vase' ] ) {
		targets.push( [ product + '.svg', 920, 760, demo, product + '.png' ] );
	}
}

( async () => {
	const browser = await chromium.launch();
	try {
		const page = await browser.newPage();
		for ( const [ source, width, height, directory, filename ] of targets ) {
			fs.mkdirSync( directory, { recursive: true } );
			await page.setViewportSize( { width, height } );
			const svg = fs.readFileSync( path.join( __dirname, source ), 'utf8' );
			await page.setContent( '<style>body{margin:0}svg{display:block;width:100vw;height:100vh}</style>' + svg );
			await page.evaluate( () => document.fonts.ready );
			await page.screenshot( { path: path.join( directory, filename ), omitBackground: true } );
		}
	} finally {
		await browser.close();
	}
} )().catch( ( error ) => {
	console.error( error );
	process.exitCode = 1;
} );
