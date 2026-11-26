const assert = require( 'node:assert/strict' );
const { execFileSync } = require( 'node:child_process' );
const { resolve } = require( 'node:path' );
const { chromium } = require( 'playwright' );

const origin = process.env.RBT_TEST_ORIGIN;
const container = process.env.RBT_TEST_CONTAINER;
const password = process.env.RBT_STAGE_ADMIN_PASSWORD;
const output = process.env.RBT_SCREENSHOTS;
const subsite = process.env.RBT_TEST_SUBSITE;
assert.match( origin || '', /^http:\/\/127\.0\.0\.1:\d+$/ );
assert( container && password );
const settings = '/wp-admin/admin.php?page=recently-bought-settings';
const notice = '#recently-bought-review';
const review = 'https://wordpress.org/support/plugin/recently-bought-this-for-woocommerce/reviews/#new-post';
const field = 'recently_bought_dismiss_review';
const week = 7 * 86400;
const user = 'stageadmin';

function wp( ...args ) {
	return execFileSync( 'docker', [ 'exec', container, 'wp', '--allow-root', '--no-color', ...args ], { encoding: 'utf8' } ).trim();
}

function state( mode, ages = [], site = '', login = user ) {
	const result = wp( '--url=' + origin + site, 'eval-file', '/rbt-source/tests/review-prompt-state.php', login, mode, ...ages.map( String ) );
	return mode === 'state' ? JSON.parse( result ) : undefined;
}

async function login( browser, { name = user, javaScriptEnabled = true, width = 1280, height = 900 } = {} ) {
	const context = await browser.newContext( { baseURL: origin, javaScriptEnabled, viewport: { width, height } } );
	await context.route( '**/*', ( route ) => {
		const url = new URL( route.request().url() );
		return url.origin === origin ? route.continue() : route.abort();
	} );
	const page = await context.newPage();
	await page.goto( '/wp-login.php' );
	await page.locator( '#user_login' ).fill( name );
	await page.locator( '#user_pass' ).fill( password );
	await page.locator( '#wp-submit' ).click();
	await page.waitForURL( '**/wp-admin/**' );
	return { context, page };
}

async function settingsHTML( context, site = '' ) {
	const response = await context.request.get( site + settings );
	assert.equal( response.status(), 200 );
	return response.text();
}

async function run() {
	const browser = await chromium.launch();
	try {
		const { context, page } = await login( browser );
		if ( subsite ) {
			state( 'seed', [ week + 60, 10 ], subsite );
			state( 'seed', [ week + 60, 10 ] );
			await page.goto( subsite + settings );
			assert( await page.locator( notice ).isVisible() );
			assert.equal( state( 'state', [], subsite )[1], '1' );
			assert.equal( state( 'state' )[1], false );
			const nonce = await page.locator( 'input[name="' + field + '"]' ).inputValue();
			assert.equal( ( await context.request.post( settings, { form: { [field]: nonce }, maxRedirects: 0 } ) ).status(), 403 );
			assert.match( await settingsHTML( context ), /id="recently-bought-review"/ );
			assert.doesNotMatch( await settingsHTML( context, subsite ), /recently-bought-review/ );
			assert.doesNotMatch( await settingsHTML( context ), /recently-bought-review/ );
			console.log( 'PASS multisite isolation and site-bound nonce.' );
			return;
		}

		state( 'seed' );
		for ( const url of [ '/', '/wp-admin/index.php', '/wp-admin/plugins.php', '/?rest_route=/' ] ) {
			const response = await context.request.get( url );
			assert.equal( response.status(), 200 );
			assert.doesNotMatch( await response.text(), /recently-bought-review|recently_bought_dismiss_review/ );
		}
		assert.deepEqual( state( 'state' ), [ [], false ] );
		for ( let visit = 1; visit <= 4; ++visit ) {
			assert.doesNotMatch( await settingsHTML( context ), /recently-bought-review/ );
			assert.equal( state( 'state' )[0].length, Math.min( visit, 3 ) );
		}
		state( 'seed' );
		const firstVisits = await Promise.all( Array.from( { length: 3 }, () => settingsHTML( context ) ) );
		for ( const body of firstVisits ) {
			assert.doesNotMatch( body, /recently-bought-review/ );
		}
		assert.equal( state( 'state' )[0].length, 3 );
		state( 'seed', [ week - 60, 10, 5 ] );
		assert.doesNotMatch( await settingsHTML( context ), /recently-bought-review/ );
		state( 'seed', [ week + 60 ] );
		assert.doesNotMatch( await settingsHTML( context ), /recently-bought-review/ );
		assert.match( await settingsHTML( context ), /id="recently-bought-review"/ );
		assert.doesNotMatch( await settingsHTML( context ), /recently-bought-review/ );

		state( 'seed', [ week + 60, 10, 5 ] );
		const pages = await Promise.all( Array.from( { length: 12 }, () => context.newPage() ) );
		const responses = await Promise.all( pages.map( ( tab ) => tab.goto( settings ) ) );
		const bodies = await Promise.all( responses.map( ( response ) => response.text() ) );
		assert.equal( bodies.filter( ( body ) => body.includes( 'id="recently-bought-review"' ) ).length, 1 );
		assert.equal( state( 'state' )[1], '1' );
		await Promise.all( pages.map( ( tab ) => tab.close() ) );
		wp( 'plugin', 'deactivate', 'recently-bought-this-for-woocommerce' );
		wp( 'plugin', 'activate', 'recently-bought-this-for-woocommerce' );
		assert.doesNotMatch( await settingsHTML( context ), /recently-bought-review/ );
		for ( const value of [ 'invalid', null ] ) {
			const body = value ? field + '=' + value : field + '%5B%5D=bad';
			assert.equal( ( await context.request.post( settings, { data: body, headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, maxRedirects: 0 } ) ).status(), 403 );
		}

		const reader = await login( browser, { name: 'review-reader' } );
		assert.equal( ( await reader.context.request.get( settings ) ).status(), 403 );
		assert.equal( ( await reader.context.request.post( settings, { form: { [field]: 'invalid' } } ) ).status(), 403 );
		assert.deepEqual( state( 'state', [], '', 'review-reader' ), [ [], false ] );
		await reader.context.close();

		const otherAdmin = await login( browser, { name: 'review-admin' } );
		state( 'seed', [ week + 60, 10 ], '', 'review-admin' );
		assert.equal( state( 'state' )[1], '1' );
		assert.equal( state( 'state', [], '', 'review-admin' )[1], false );
		assert.match( await settingsHTML( otherAdmin.context ), /id="recently-bought-review"/ );
		assert.doesNotMatch( await settingsHTML( otherAdmin.context ), /recently-bought-review/ );
		await otherAdmin.context.close();
		await context.close();

		for ( const locale of [ 'en_US', 'pl_PL' ] ) {
			wp( 'user', 'meta', 'update', user, 'locale', locale );
			for ( const [ viewport, width, height ] of [ [ 'desktop', 1280, 900 ], [ 'mobile', 390, 844 ] ] ) {
				const { context, page } = await login( browser, { javaScriptEnabled: false, width, height } );
				state( 'seed', [ week + 60, 5 ] );
				await page.goto( settings );
				const panel = page.locator( notice );
				assert( await panel.isVisible() );
				assert.equal( await panel.getByRole( 'link' ).getAttribute( 'href' ), review );
				assert.match( await panel.getByRole( 'heading' ).innerText(), locale === 'pl_PL' ? /^Czy korzystasz/ : /^Have you tried/ );
				assert.equal( await panel.evaluate( ( element ) => element.getBoundingClientRect().right <= innerWidth ), true );
				if ( output ) {
					await page.screenshot( { path: resolve( output, 'review-' + locale + '-' + viewport + '.png' ) } );
				}
				const button = viewport === 'mobile' ? panel.locator( '.notice-dismiss' ) : panel.locator( '.button-link' );
				const redirect = page.waitForResponse( ( response ) => response.request().method() === 'POST' && response.url() === origin + settings );
				await button.click();
				assert.equal( ( await redirect ).status(), 303 );
				await page.waitForLoadState( 'domcontentloaded' );
				assert.equal( await panel.count(), 0 );
				assert.equal( state( 'state' )[1], '1' );
				await context.close();
			}
		}
		wp( 'user', 'meta', 'update', user, 'locale', 'en_US' );
		const reviewBrowser = await login( browser );
		await reviewBrowser.context.route( 'https://wordpress.org/**', ( route ) => route.fulfill( { body: 'Review destination fixture' } ) );
		state( 'seed', [ week + 60, 10 ] );
		await reviewBrowser.page.goto( settings );
		await reviewBrowser.page.locator( notice ).getByRole( 'link' ).click();
		await reviewBrowser.page.waitForURL( review );
		await reviewBrowser.page.goBack();
		assert.equal( await reviewBrowser.page.locator( notice ).isVisible(), false );
		assert.doesNotMatch( await settingsHTML( reviewBrowser.context ), /recently-bought-review/ );
		console.log( 'PASS thresholds, parallel tabs, permissions, nonces, no-JS dismissal, PL/EN, review link, reactivation and isolation by user and screen.' );
	} finally {
		await browser.close();
	}
}

run().catch( ( error ) => {
	console.error( error );
	process.exitCode = 1;
} );
