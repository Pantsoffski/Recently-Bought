const assert = require( 'node:assert/strict' );
const path = require( 'node:path' );
const { chromium } = require( 'playwright' );

const origin = process.env.RBT_TEST_ORIGIN;
assert.match( origin || '', /^http:\/\/127\.0\.0\.1:\d+$/ );
const output = process.argv[ 2 ];
const settingsURL = '/wp-admin/admin.php?page=recently-bought-settings';

async function open( browser, viewport, colorScheme = 'light' ) {
	const context = await browser.newContext( { baseURL: origin, viewport, colorScheme, reducedMotion: 'reduce' } );
	await context.route( '**/*', ( route ) => new URL( route.request().url() ).origin === origin ? route.continue() : route.abort() );
	return context;
}

async function storefront( browser, filename, viewport, colorScheme, showTime ) {
	const context = await open( browser, viewport, colorScheme );
	try {
		const page = await context.newPage();
		await page.goto( '/' );
		await page.locator( '.morrow-collection .product' ).first().waitFor();
		const notice = page.getByRole( 'dialog', { name: 'Recent purchases' } );
		await notice.getByText( 'Harbor Mug' ).waitFor();
		assert.equal( await notice.locator( 'small' ).count(), Number( showTime ) );
		if ( showTime ) {
			assert.equal( await notice.locator( 'small' ).textContent(), 'Ordered yesterday' );
		}
		assert.doesNotMatch( await page.content(), /TEST_PRIVATE_|stage@example\.test/ );
		await page.evaluate( () => document.fonts.ready );
		await page.screenshot( { path: path.join( output, filename ) } );
	} finally {
		await context.close();
	}
}

( async () => {
	const browser = await chromium.launch();
	try {
		await storefront( browser, 'screenshot-1.png', { width: 1440, height: 1040 }, 'light', false );
		await storefront( browser, 'screenshot-4.png', { width: 390, height: 900 }, 'light', false );

		const admin = await open( browser, { width: 1280, height: 900 } );
		const page = await admin.newPage();
		await page.goto( '/wp-login.php' );
		await page.locator( '#user_login' ).fill( 'stageadmin' );
		await page.locator( '#user_pass' ).fill( process.env.RBT_STAGE_ADMIN_PASSWORD );
		await page.locator( '#wp-submit' ).click();
		await page.waitForURL( '**/wp-admin/**' );
		await page.goto( settingsURL );
		await page.getByText( 'Purchase time information', { exact: true } ).waitFor();
		await page.screenshot( { path: path.join( output, 'screenshot-2.png' ) } );

		await page.locator( 'input[name="recently_bought_settings_time_display"][value="approximate"]' ).check();
		await page.locator( '#submit' ).click();
		await page.waitForURL( '**&settings-updated=true' );
		await admin.close();
		await storefront( browser, 'screenshot-3.png', { width: 1440, height: 1040 }, 'dark', true );
		console.log( 'PASS four authentic screenshots of the installed candidate in WordPress and WooCommerce.' );
	} finally {
		await browser.close();
	}
} )().catch( ( error ) => {
	console.error( error );
	process.exitCode = 1;
} );
