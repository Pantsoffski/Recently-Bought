const assert = require( 'node:assert/strict' );
const { chromium } = require( 'playwright' );

const origin = process.env.RBT_TEST_ORIGIN || 'http://127.0.0.1:38989';
assert.match( origin, /^http:\/\/127\.0\.0\.1:\d+$/ );
const settingsPath = '/wp-admin/admin.php?page=recently-bought-settings';
const privateMarkers = [ 'TEST_PRIVATE_SURNAME_7391', 'TEST_PRIVATE_FAMILY_7391', 'TEST_PRIVATE_ADDRESS_7391', 'TEST_PRIVATE_LINE_7391', 'test-private-7391@example.test', '739100000001' ];

async function openLocalContext( browser, viewport ) {
	const context = await browser.newContext( { baseURL: origin, viewport } );
	await context.route( '**/*', ( route ) => new URL( route.request().url() ).origin === origin ? route.continue() : route.abort() );
	return context;
}

async function login( context ) {
	const page = await context.newPage();
	await page.goto( '/wp-login.php' );
	await page.locator( '#user_login' ).fill( 'stageadmin' );
	await page.locator( '#user_pass' ).fill( process.env.RBT_STAGE_ADMIN_PASSWORD );
	await page.locator( '#wp-submit' ).click();
	await page.waitForURL( '**/wp-admin/**' );
	return page;
}

function assertPrivate( markup ) {
	for ( const marker of privateMarkers ) {
		assert( ! markup.includes( marker ), `Private marker in public response: ${ marker }` );
	}
}

async function saveSettings( page, { corner, approximate, delay } ) {
	await page.goto( settingsPath );
	await page.locator( `input[name="recently_bought_settings_corner"][value="${ corner }"]` ).check();
	await page.locator( `input[name="recently_bought_settings_time_display"][value="${ approximate ? 'approximate' : 'none' }"]` ).check();
	await page.locator( '#recently-bought-delay' ).fill( String( delay ) );
	await page.locator( '#submit' ).click();
	await page.waitForURL( '**&settings-updated=true' );
}

async function checkNotice( admin, visitors ) {
	await admin.goto( '/wp-admin/plugins.php' );
	await admin.getByText( 'Recently Bought This for WooCommerce requires WooCommerce to be installed and active.' ).waitFor();
	const response = await visitors.request.get( '/' );
	assert.equal( response.status(), 200 );
	assert.doesNotMatch( await response.text(), /requires WooCommerce to be installed|WooCommerce not installed!!|id="justBought"/ );
	const rest = await visitors.request.get( '/?rest_route=/' );
	assert.equal( rest.status(), 200 );
	assert( ( await rest.json() ).namespaces.includes( 'wp/v2' ) );
	const ajax = await visitors.request.get( '/wp-admin/admin-ajax.php' );
	assert.equal( ajax.status(), 400 );
	assert.equal( ( await ajax.text() ).trim(), '0' );
	console.log( 'PASS missing WooCommerce: admin notice and clean storefront, REST and AJAX.' );
}

async function checkEditableTexts( admin, visitors ) {
	const fields = admin.locator( 'input[name^="recently_bought_settings_text_"]' );
	const hints = [ 'Ordered today', 'Ordered yesterday', 'Ordered within the last 7 days', 'Ordered within the last 30 days', 'Purchased in this store' ];
	const values = [ '<script></script><img onerror="">Dziś & včera', 'Objednáno včera', 'Objednáno v posledních 7 dnech', 'Objednáno v posledních 30 dnech', 'Zakoupeno v tomto obchodě' ];
	assert.equal( await fields.count(), 5 );
	for ( const [ index, hint ] of hints.entries() ) {
		assert.equal( await fields.nth( index ).getAttribute( 'placeholder' ), hint );
		await fields.nth( index ).fill( values[ index ] );
	}
	await admin.locator( '#recently-bought-text' ).fill( 'Někdo nedávno koupil' );
	await admin.locator( '#recently-bought-delay' ).fill( '0' );
	await admin.locator( 'input[name="recently_bought_settings_time_display"][value="approximate"]' ).check();
	await admin.locator( '#submit' ).click();
	await admin.waitForURL( '**&settings-updated=true' );
	assert.equal( await fields.nth( 0 ).inputValue(), 'Dziś & včera' );
	assert.equal( await fields.nth( 4 ).inputValue(), values[ 4 ] );
	const page = await visitors.newPage();
	await page.goto( '/' );
	const copy = page.locator( '.fixed-dialog .recently-bought-copy' );
	await copy.getByText( 'Někdo nedávno koupil' ).waitFor();
	assert.match( await copy.locator( 'small' ).textContent(), /^(Dziś & včera|Objednáno včera)$/ );
	assert.equal( await copy.locator( 'script, img, [onerror]' ).count(), 0 );
	assertPrivate( await page.content() );
	await page.close();
	for ( const field of await fields.all() ) {
		await field.fill( '' );
	}
	await admin.locator( '#recently-bought-text' ).fill( 'Someone bought: “fixture”; const sample = 1.' );
	await admin.locator( '#submit' ).click();
	await admin.waitForURL( '**&settings-updated=true' );
	console.log( 'PASS Settings API form, translated placeholders, custom Unicode text and escaped storefront DOM.' );
}

async function checkBottomSpacing( admin, visitors ) {
	const spacing = admin.getByRole( 'group', { name: 'Bottom spacing' } );
	const offset = spacing.locator( '#recently-bought-bottom-offset' );
	const previous = Number( await offset.inputValue() );
	for ( const [ mode, value ] of [ [ 'automatic', previous ], [ 'manual', 120 ], [ 'standard', 120 ], [ 'automatic', 120 ] ] ) {
		await spacing.locator( 'input[type=radio][value=' + mode + ']' ).check();
		assert.equal( await offset.isVisible(), mode === 'manual' );
		if ( mode === 'manual' ) {
			assert.equal( await spacing.getByRole( 'spinbutton', { name: 'Additional bottom spacing (px)' } ).count(), 1 );
			await offset.fill( String( value ) );
		}
		await admin.locator( '#submit' ).click();
		await admin.waitForURL( '**&settings-updated=true' );
		assert.equal( await spacing.locator( 'input:checked' ).inputValue(), mode );
		const response = await visitors.request.get( '/' );
		assert.equal( response.status(), 200 );
		const markup = await response.text();
		const settings = markup.match( /<template id="recently-bought-notifications" data-settings="([^"]+)"/ );
		assert( settings );
		const data = JSON.parse( settings[ 1 ].replaceAll( '&quot;', '"' ) );
		assert.equal( data.bottomSpacing, mode );
		assert.equal( data.bottomOffset, value );
	}
	console.log( 'PASS all three bottom spacing modes, conditional numeric field, manual persistence and storefront payload.' );
}

async function checkCorner( visitors, corner, approximate, delay ) {
	const page = await visitors.newPage();
	const errors = [];
	page.on( 'pageerror', ( error ) => errors.push( error.stack ) );
	const response = await page.goto( '/' );
	const source = await response.text();
	assertPrivate( source );
	assert( ! source.includes( '<strong>Fixture</strong>' ), 'Name must be escaped in source.' );
	const pending = page.locator( '.fixed-dialog' );
	if ( delay ) {
		assert.equal( await pending.isVisible(), false );
		await page.waitForTimeout( delay * 1000 - 2000 );
		assert.equal( await pending.isVisible(), false );
	}
	await pending.waitFor( { timeout: delay * 1000 + 4000 } );
	const info = await page.evaluate( () => {
		const link = document.querySelector( '#itemsToShow a' );
		const name = document.querySelector( '#productTitle' );
		const template = document.getElementById( 'recently-bought-notifications' );
		const image = link.querySelector( 'img' );
		return {
			position: jQuery( '#justBought' ).notice( 'option', 'position' ).at,
			url: link.href,
			name: name.textContent,
			nameChildren: name.children.length,
			text: link.textContent.trim(),
			time: link.querySelector( 'small' )?.textContent,
			imageUrl: image.currentSrc || image.src,
			imageHeight: image.style.height,
			imageClass: image.classList.contains( 'alignleft' ),
			imageAlt: image.alt,
			privateAttributes: template.content.querySelectorAll( '[data-order-id], [datetime], time, script' ).length,
			settings: JSON.parse( template.dataset.settings ),
		};
	} );
	assertPrivate( await page.content() );
	assert.equal( info.position, [ 'right top', 'right bottom', 'left bottom', 'left top' ][ corner ] );
	assert.equal( info.name, 'Stage A Synthetic storefront product' );
	assert.equal( info.nameChildren, 0 );
	assert.match( info.text, /^Someone bought: “fixture”; const sample = 1\./ );
	assert.equal( info.imageHeight, '80px' );
	assert.equal( info.imageClass, true );
	assert.equal( info.imageAlt, 'Synthetic product swatch' );
	assert.equal( ( await visitors.request.get( info.imageUrl ) ).status(), 200 );
	const productResponse = await visitors.request.get( info.url );
	assert.equal( productResponse.status(), 200 );
	assert.match( await productResponse.text(), /Stage A Synthetic storefront product/ );
	assert.equal( info.privateAttributes, 0 );
	assert.deepEqual( Object.keys( info.settings ).sort(), [ 'bottomOffset', 'bottomSpacing', 'closeText', 'cookieName', 'cookiePath', 'corner', 'delay', 'randomize' ] );
	assert.equal( info.settings.corner, corner );
	assert.equal( info.settings.delay, delay );
	if ( approximate ) {
		assert.match( info.time, /^Ordered (today|yesterday)$/ );
	} else {
		assert.equal( info.time, undefined );
	}
	const bounds = await pending.boundingBox();
	const viewport = page.viewportSize();
	assert( bounds.x >= -1 && bounds.y >= -1 && bounds.x + bounds.width <= viewport.width + 1 && bounds.y + bounds.height <= viewport.height + 1, 'Dialog must fit the smoke-test viewport.' );
	if ( corner === 0 ) {
		await pending.locator( '.ui-dialog-titlebar-close' ).click();
		await page.waitForTimeout( 10500 );
		const cookie = ( await visitors.cookies() ).find( ( value ) => value.name === info.settings.cookieName );
		assert( cookie && cookie.expires - Date.now() / 1000 > 550, 'Manual dismissal must keep the ten-minute cookie.' );
		await page.reload();
		await page.waitForTimeout( 1000 );
		assert.equal( await pending.count(), 0 );
	} else if ( corner === 3 ) {
		await page.locator( '#productTitle', { hasText: 'Stage A Synthetic second product' } ).waitFor( { timeout: 6500 } );
		await pending.waitFor( { state: 'detached', timeout: 6500 } );
		const cookie = ( await visitors.cookies() ).find( ( value ) => value.name === info.settings.cookieName );
		const remaining = cookie && cookie.expires - Date.now() / 1000;
		assert( remaining > 0 && remaining <= 15, 'Automatic dismissal must have a short cookie.' );
	}
	assert.deepEqual( errors, [] );
	await page.close();
}

async function run() {
	assert( process.env.RBT_STAGE_ADMIN_PASSWORD, 'Set the disposable local test password first.' );
	const browser = await chromium.launch();
	const adminContext = await openLocalContext( browser, { width: 1280, height: 900 } );
	try {
		const admin = await login( adminContext );
		if ( process.argv[ 2 ] === 'without-woo' || process.argv[ 2 ] === 'empty' ) {
			const visitors = await openLocalContext( browser, { width: 1280, height: 900 } );
			if ( process.argv[ 2 ] === 'without-woo' ) {
				await checkNotice( admin, visitors );
			} else {
				const response = await visitors.request.get( '/' );
				assert.equal( response.status(), 200 );
				assert.doesNotMatch( await response.text(), /id="justBought"|recently-bought\.js/ );
				console.log( 'PASS empty storefront: no notification markup or script.' );
			}
			return;
		}

		await admin.goto( settingsPath );
		await admin.getByText( 'Purchase time information', { exact: true } ).waitFor();
		const nonceCheck = await admin.request.post( '/wp-admin/options.php', { form: { option_page: 'recently_bought_settings_group', action: 'update', recently_bought_settings_text: 'Synthetic settings test' } } );
		assert.equal( nonceCheck.status(), 403 );
		const textVisitors = await openLocalContext( browser, { width: 1280, height: 900 } );
		await checkBottomSpacing( admin, textVisitors );
		await checkEditableTexts( admin, textVisitors );
		await textVisitors.close();
		for ( let corner = 0; corner < 4; ++corner ) {
			const approximate = corner % 2 === 1;
			const delay = corner === 3 ? 30 : 0;
			await saveSettings( admin, { corner, approximate, delay } );
			const visitors = await openLocalContext( browser, corner < 2 ? { width: 1280, height: 900 } : { width: 390, height: 844 } );
			await checkCorner( visitors, corner, approximate, delay );
			await visitors.close();
		}
		console.log( 'PASS Chromium: privacy, plain text, missing nonce, both time modes, four corners, desktop/mobile, product link and thumbnail, 30-second delay, rotation and dismissal cookies.' );
	} finally {
		await browser.close();
	}
}

run().catch( ( error ) => {
	console.error( error );
	process.exitCode = 1;
} );
