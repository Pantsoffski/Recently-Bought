const assert = require( 'node:assert/strict' );
const path = require( 'node:path' );
const { chromium, firefox, webkit } = require( 'playwright' );

const origin = process.env.RBT_TEST_ORIGIN || 'http://127.0.0.1:38989';
assert.match( origin, /^http:\/\/127\.0\.0\.1:\d+$/ );
const fixtureOrigin = 'https://storefront.example.test';
const selector = '.recently-bought-dialog';

async function captureFixture( browser ) {
	const context = await browser.newContext();
	const response = await context.request.get( origin + '/?rbt_corner=0&rbt_theme=twentytwentyfive' );
	assert.equal( response.status(), 200 );
	const html = await response.text();
	const template = html.match( /<template id="recently-bought-notifications"[\s\S]*?<\/template>\s*<div id="justBought"[^>]*><p id="itemsToShow"><\/p><\/div>/ );
	assert( template, 'WordPress must render a real notification template.' );
	const filenames = Array.from( html.matchAll( /<(?:script|link)\b[^>]*(?:src|href)=["']([^"']+)["'][^>]*>/g ) )
		.map( ( match ) => new URL( match[ 1 ], origin ) )
		.filter( ( url ) => url.origin === origin && /\/(?:wp-includes\/js\/jquery\/|_inc\/recently-bought)/.test( url.pathname ) );
	const assets = new Map();
	for ( const url of filenames ) {
		const resource = await context.request.get( url.href );
		assert.equal( resource.status(), 200 );
		assets.set( '/asset/' + path.basename( url.pathname ), { body: await resource.body(), contentType: resource.headers()[ 'content-type' ] } );
	}
	await context.close();
	assert( assets.has( '/asset/recently-bought.js' ) && assets.has( '/asset/recently-bought-style.css' ) );
	return { template: template[ 0 ], assets };
}

async function fixtureContext( browser, fixture, options = {} ) {
	const context = await browser.newContext( {
		reducedMotion: options.reducedMotion || 'reduce',
		colorScheme: options.colorScheme || 'light',
		viewport: options.viewport || { width: 800, height: 600 }
	} );
	await context.route( '**/*', async ( route ) => {
		const url = new URL( route.request().url() );
		if ( url.origin !== fixtureOrigin ) {
			return route.abort();
		}
		if ( fixture.assets.has( url.pathname ) ) {
			return route.fulfill( fixture.assets.get( url.pathname ) );
		}
		if ( route.request().resourceType() !== 'document' ) {
			return route.abort();
		}
		const configuration = { corner: 0, delay: 0, randomize: false, bottomSpacing: 'automatic', bottomOffset: 0, ...options.settings };
		configuration.cookiePath = url.pathname.startsWith( '/shop-b/' ) ? '/shop-b' : '/shop-a';
		configuration.cookieName = configuration.cookiePath === '/shop-b' ? 'recentlyBoughtClosed_b' : 'recentlyBoughtClosed_a';
		const inputs = '<input id="outside" aria-label="Test checkout" style="position:fixed;top:0;left:0;box-sizing:border-box;width:8px;padding:0" autofocus>';
		const prepare = 'const template = document.getElementById("recently-bought-notifications");'
			+ 'Object.assign(window.fixtureSettings = JSON.parse(template.dataset.settings), ' + JSON.stringify( configuration ) + ');'
			+ 'template.dataset.settings = JSON.stringify(window.fixtureSettings);'
			+ ( options.count ? 'const anchor = template.content.firstElementChild; template.content.replaceChildren();'
				+ 'for (let i=1;i<=' + Number( options.count ) + ';i++) { const entry=anchor.cloneNode(true); entry.querySelector("#productTitle").textContent="Synthetic item "+i; template.content.append(entry); }' : '' )
			+ ( options.long ? 'template.content.querySelector(".recently-bought-copy > span").textContent = "Someone recently bought";'
				+ 'template.content.querySelector("#productTitle").textContent = "LongUnbrokenSyntheticProduct".repeat(30);' : '' )
			+ ( options.time ? 'for (const item of template.content.querySelectorAll(".recently-bought-copy:not(:has(small))")) { const time = document.createElement("small"); time.textContent = "Ordered yesterday"; item.append(time); }' : '' );
		const tags = Array.from( fixture.assets.keys(), ( key ) => key.endsWith( '.css' )
			? '<link rel="stylesheet" href="' + key + '">'
			: '<script src="' + key + '"></script>' ).join( '' );
		return route.fulfill( { contentType: 'text/html', body: '<!doctype html><html lang="en"><meta name="viewport" content="width=device-width, initial-scale=1"><body class="woocommerce">' + inputs + fixture.template
			+ '<script>' + prepare + '</script>' + tags + '<style>' + ( options.css || '' ) + '</style>' + ( options.duplicate ? '<script src="/asset/recently-bought.js"></script>' : '' ) + '</body></html>' } );
	} );
	return context;
}

async function start( context, site = '/shop-a/', virtualTime = false ) {
	const page = await context.newPage();
	page.on( 'pageerror', ( error ) => { page.errors.push( error.stack ); } );
	page.errors = [];
	if ( virtualTime ) {
		await page.clock.install();
		await page.clock.pauseAt( new Date( Date.now() + 100 ) );
	}
	await page.goto( fixtureOrigin + site );
	return page;
}

async function checkTimerCycles( browser, fixture ) {
	for ( const [ delay, count ] of [ [ 0, 1 ], [ 1, 2 ], [ 5, 5 ], [ 30, 10 ] ] ) {
		const context = await fixtureContext( browser, fixture, { count, settings: { delay }, duplicate: true } );
		const page = await start( context, '/shop-a/', true );
		const dialog = page.locator( selector );
		if ( delay ) {
			await page.clock.runFor( delay * 1000 - 200 );
			assert.equal( await dialog.isVisible(), false );
		}
		await page.clock.runFor( 300 );
		assert.equal( await dialog.count(), 1 );
		assert.equal( await dialog.isVisible(), true );
		for ( let index = 1; index <= count; ++index ) {
			assert.equal( await page.locator( '#productTitle' ).textContent(), 'Synthetic item ' + index );
			await page.clock.runFor( 5000 );
		}
		assert.equal( await dialog.count(), 0 );
		assert.equal( await page.locator( '#outside' ).evaluate( ( input ) => input === document.activeElement ), true );
		const [ cookie ] = await context.cookies();
		assert( cookie.secure && cookie.sameSite === 'Lax' && cookie.path === '/shop-a' && cookie.value === 'closed' );
		assert( Math.abs( cookie.expires - Date.now() / 1000 - 15 ) < 3 );
		assert.deepEqual( page.errors, [] );
		await context.close();
	}
	console.log( 'PASS timers: delays 0/1/5/30 s, counts 1/2/5/10, reduced motion and 15-second Secure cookie.' );
}

async function checkInteraction( browser, fixture ) {
	const context = await fixtureContext( browser, fixture, { count: 2, settings: { delay: 1 }, reducedMotion: 'no-preference' } );
	const page = await start( context, '/shop-a/', true );
	await page.clock.runFor( 6100 );
	const dialog = page.locator( selector );
	await dialog.evaluate( ( panel ) => panel.dispatchEvent( new PointerEvent( 'pointerover', { bubbles: true } ) ) );
	await page.clock.runFor( 7000 );
	assert.equal( await page.locator( '#productTitle' ).textContent(), 'Synthetic item 1', 'Hover must suspend a running fade.' );
	await dialog.evaluate( ( panel ) => panel.dispatchEvent( new PointerEvent( 'pointerout', { bubbles: true } ) ) );
	await page.locator( '#itemsToShow a' ).focus();
	await page.clock.runFor( 7000 );
	assert.equal( await page.locator( '#productTitle' ).textContent(), 'Synthetic item 1', 'Keyboard focus must suspend rotation.' );
	await page.locator( '#outside' ).focus();
	await page.clock.runFor( 5100 );
	await dialog.locator( 'button' ).evaluate( ( button ) => button.click() );
	await page.clock.runFor( 16000 );
	assert.equal( await dialog.count(), 0 );
	const [ cookie ] = await context.cookies();
	assert( Math.abs( cookie.expires - Date.now() / 1000 - 600 ) < 3 );
	await page.goto( fixtureOrigin + '/shop-a/another-page' );
	await page.clock.runFor( 3000 );
	assert.equal( await dialog.count(), 0, 'Dismissal must survive a same-site navigation.' );
	await page.goto( fixtureOrigin + '/shop-b/' );
	await page.clock.runFor( 3000 );
	assert.equal( await dialog.isVisible(), true, 'Sibling storefront inherited a dismissal.' );
	assert.deepEqual( page.errors, [] );
	await context.close();

	const overlap = await fixtureContext( browser, fixture, { count: 2, viewport: { width: 320, height: 600 } } );
	const compact = await start( overlap, '/shop-a/', true );
	await compact.clock.runFor( 150 );
	await compact.evaluate( () => {
		const panel = document.querySelector( '.recently-bought-dialog' ).getBoundingClientRect();
		Object.assign( document.activeElement.style, { left: '90px', top: ( panel.bottom + 20 ) + 'px' } );
		document.querySelector( 'template' ).content.children[ 1 ].querySelector( '.recently-bought-copy > span' ).textContent =
			'A longer store message about the latest purchase. '.repeat( 8 );
	} );
	await compact.clock.runFor( 5000 );
	assert.equal( await compact.locator( selector ).isVisible(), false, 'A taller rotated item must yield to a previously focused field.' );
	assert.equal( await compact.locator( '#outside' ).evaluate( ( input ) => input === document.activeElement ), true );
	await overlap.close();

	const history = await fixtureContext( browser, fixture, { count: 2, settings: { delay: 5 }, duplicate: true } );
	const restored = await start( history, '/shop-a/', true );
	await restored.clock.runFor( 100 );
	await restored.evaluate( () => window.dispatchEvent( new PageTransitionEvent( 'pagehide', { persisted: true } ) ) );
	await restored.clock.runFor( 7000 );
	assert.equal( await restored.locator( selector ).count(), 0 );
	assert.equal( ( await history.cookies() ).length, 0 );
	await restored.evaluate( () => {
		window.dispatchEvent( new PageTransitionEvent( 'pageshow', { persisted: true } ) );
		window.dispatchEvent( new PageTransitionEvent( 'pageshow', { persisted: true } ) );
	} );
	await restored.clock.runFor( 5200 );
	assert.equal( await restored.locator( selector ).count(), 1 );
	assert.equal( await restored.locator( selector ).isVisible(), true );
	assert.deepEqual( restored.errors, [] );
	await history.close();
	console.log( 'PASS fade/close race, hover/focus pause, rotation overlap, 600-second cookie, site isolation and reinitialization.' );
}

async function checkCookiesAndCachedMarkup( browser, fixture ) {
	const tabs = await fixtureContext( browser, fixture, { count: 1 } );
	const first = await start( tabs, '/shop-a/', true );
	const other = await start( tabs, '/shop-a/', true );
	await first.clock.runFor( 300 );
	await other.clock.runFor( 300 );
	await first.locator( selector ).getByRole( 'button' ).evaluate( ( button ) => button.click() );
	await other.clock.runFor( 5200 );
	assert.equal( await other.locator( selector ).count(), 0 );
	const [ heldCookie ] = await tabs.cookies();
	assert( Math.abs( heldCookie.expires - Date.now() / 1000 - 600 ) < 3, 'Natural completion in another tab must preserve a manual dismissal.' );
	await tabs.close();

	const blocked = await fixtureContext( browser, fixture, { count: 2 } );
	await blocked.addInitScript( () => {
		Object.defineProperty( document, 'cookie', {
			get() { throw new DOMException( 'Storage blocked in the test' ); },
			set() { throw new DOMException( 'Storage blocked in the test' ); }
		} );
	} );
	const blockedPage = await start( blocked );
	await blockedPage.locator( selector ).getByRole( 'button' ).click();
	await blockedPage.locator( selector ).waitFor( { state: 'detached' } );
	assert.equal( ( await blocked.cookies() ).length, 0 );
	await blockedPage.reload();
	await blockedPage.locator( selector ).waitFor();
	assert.deepEqual( blockedPage.errors, [] );
	await blocked.close();

	const replay = await fixtureContext( browser, fixture, { count: 2, settings: { randomize: true } } );
	await replay.addCookies( [
		{ name: 'bad', value: '%E0%A4%A', domain: 'storefront.example.test', path: '/' },
		{ name: 'justBought', value: 'closed', domain: 'storefront.example.test', path: '/' }
	] );
	await replay.addInitScript( () => { Math.random = () => location.search ? 0 : 0.99; } );
	const page = await start( replay );
	await page.locator( selector ).waitFor();
	assert.equal( await page.locator( '#productTitle' ).textContent(), 'Synthetic item 1' );
	await page.goto( fixtureOrigin + '/shop-a/?repeat' );
	await page.locator( selector ).waitFor();
	assert.equal( await page.locator( '#productTitle' ).textContent(), 'Synthetic item 2' );
	assert.deepEqual( await page.evaluate( () => Array.from( document.querySelector( 'template' ).content.querySelectorAll( '#productTitle' ), ( item ) => item.textContent ) ), [ 'Synthetic item 1', 'Synthetic item 2' ] );
	assert.deepEqual( page.errors, [] );
	await replay.close();
	console.log( 'PASS denied storage, malformed and legacy cookies, client shuffle with identical cached markup.' );
}

function contrast( foreground, background ) {
	const luminance = ( rgb ) => rgb.match( /\d+/g ).slice( 0, 3 ).map( ( channel ) => {
		const value = Number( channel ) / 255;
		return value <= 0.04045 ? value / 12.92 : ( ( value + 0.055 ) / 1.055 ) ** 2.4;
	} ).reduce( ( total, value, index ) => total + value * [ 0.2126, 0.7152, 0.0722 ][ index ], 0 );
	const pair = [ luminance( foreground ), luminance( background ) ].sort( ( left, right ) => right - left );
	return ( pair[ 0 ] + 0.05 ) / ( pair[ 1 ] + 0.05 );
}

async function checkViewportAndKeyboard( browser, fixture ) {
	const context = await fixtureContext( browser, fixture, { long: true, count: 2, time: true, viewport: { width: 320, height: 568 } } );
	const page = await start( context );
	const dialog = page.getByRole( 'dialog', { name: 'Recent purchases' } );
	await dialog.waitFor();
	const close = dialog.getByRole( 'button', { name: 'Close notification' } );
	const product = dialog.getByRole( 'link' );
	assert.equal( await page.locator( '#outside' ).evaluate( ( input ) => input === document.activeElement ), true );
	await dialog.evaluate( ( panel ) => {
		const after = document.createElement( 'button' );
		after.id = 'after-notification';
		after.textContent = 'After notification';
		panel.after( after );
	} );
	const verifyGeometry = () => dialog.evaluate( ( panel ) => {
		const rect = panel.getBoundingClientRect();
		const button = panel.querySelector( 'button' ).getBoundingClientRect();
		const copy = panel.querySelector( '.recently-bought-copy' ).getBoundingClientRect();
		const img = panel.querySelector( 'img' ).getBoundingClientRect();
		const { offsetTop, offsetLeft, width, height } = visualViewport;
		const gap = Number.parseFloat( getComputedStyle( panel ).getPropertyValue( '--rbt-gap' ) ) - 1;
		return rect.left >= offsetLeft + gap && rect.top >= offsetTop + gap &&
			rect.right <= offsetLeft + width - gap && rect.bottom <= offsetTop + height - gap &&
			button.left >= rect.left && button.right <= rect.right && button.bottom <= rect.bottom &&
			img.right <= copy.left && document.documentElement.scrollWidth <= document.documentElement.clientWidth;
	} );
	assert.equal( await verifyGeometry(), true );
	for ( const size of [ { width: 640, height: 400 }, { width: 568, height: 320 }, { width: 320, height: 250 } ] ) {
		await page.setViewportSize( size );
		await page.waitForTimeout( 60 );
		assert.equal( await verifyGeometry(), true, 'Long word and thumbnail must fit after resize.' );
	}
	await page.setViewportSize( { width: 1440, height: 1000 } );
	const devtools = await context.newCDPSession( page );
	for ( const scale of [ 2, 4 ] ) {
		await devtools.send( 'Emulation.setPageScaleFactor', { pageScaleFactor: scale } );
		await page.waitForTimeout( 80 );
		assert.equal( await page.evaluate( () => visualViewport.scale ), scale );
		assert.equal( await verifyGeometry(), true, 'Widget must fit visual viewport at ' + scale * 100 + '%.' );
	}
	await devtools.send( 'Emulation.setPageScaleFactor', { pageScaleFactor: 1 } );

	for ( const colorScheme of [ 'light', 'dark' ] ) {
		await page.emulateMedia( { colorScheme } );
		const colors = await dialog.evaluate( ( panel ) => ( {
			background: getComputedStyle( panel ).backgroundColor,
			foregrounds: [ '#productTitle', 'small', 'button', '#itemsToShow a' ].map( ( selector ) => getComputedStyle( panel.querySelector( selector ) ).color )
		} ) );
		assert( colors.foregrounds.every( ( color ) => contrast( color, colors.background ) >= 4.5 ), colorScheme + ' contrast' );
	}
	await page.emulateMedia( { colorScheme: 'dark', forcedColors: 'active', contrast: 'more' } );
	assert.equal( await page.evaluate( () => matchMedia( '(forced-colors: active)' ).matches ), true );
	await page.keyboard.press( 'Tab' );
	assert.equal( await close.evaluate( ( element ) => element === document.activeElement ), true );
	assert.equal( await close.evaluate( ( element ) => getComputedStyle( element ).outlineWidth ), '2px' );
	await page.keyboard.press( 'Tab' );
	assert.equal( await product.evaluate( ( element ) => element === document.activeElement ), true );
	const outline = await product.evaluate( ( element ) => getComputedStyle( element ).outlineWidth );
	assert.equal( outline, '2px' );
	await page.keyboard.press( 'Tab' );
	assert.equal( await page.locator( '#after-notification' ).evaluate( ( element ) => element === document.activeElement ), true );
	await page.keyboard.press( 'Shift+Tab' );
	await page.keyboard.press( 'Shift+Tab' );
	assert.equal( await close.evaluate( ( element ) => element === document.activeElement ), true );
	await page.keyboard.press( 'Shift+Tab' );
	assert.equal( await page.locator( '#outside' ).evaluate( ( element ) => element === document.activeElement ), true );
	await page.keyboard.press( 'Tab' );
	await page.keyboard.press( 'Escape' );
	await dialog.waitFor( { state: 'detached' } );
	assert.equal( await page.locator( '#outside' ).evaluate( ( element ) => element === document.activeElement ), true );
	assert.deepEqual( page.errors, [] );
	await context.close();
	console.log( 'PASS resize, landscape, 320 px, 200%/400% zoom, focus order, Escape, contrast and forced colors.' );
}

async function checkCheckout( browser ) {
	const checkout = process.env.RBT_STAGE_B_CHECKOUT;
	const product = process.env.RBT_STAGE_B_PRODUCT;
	assert.match( checkout || '', /^\d+$/, 'Set the local synthetic WooCommerce checkout page ID.' );
	assert.match( product || '', /^\d+$/, 'Set the local synthetic WooCommerce product ID.' );
	const context = await browser.newContext( { baseURL: origin, viewport: { width: 390, height: 844 }, reducedMotion: 'reduce' } );
	await context.route( '**/*', ( route ) => new URL( route.request().url() ).origin === origin ? route.continue() : route.abort() );
	const page = await context.newPage();
	await page.goto( '/?add-to-cart=' + product + '&rbt_theme=storefront' );
	await page.goto( '/?page_id=' + checkout + '&rbt_corner=1&rbt_theme=storefront' );
	const editor = page.locator( 'input[autocomplete="email"], #billing_email, #email' ).first();
	await editor.fill( 'stage-b-checkout@example.test' );
	const dialog = page.locator( selector );
	await dialog.waitFor( { state: 'attached' } );
	await editor.evaluate( ( field ) => {
		const rect = document.querySelector( '.recently-bought-dialog' ).getBoundingClientRect();
		Object.assign( field.style, { position: 'fixed', top: ( rect.top + 5 ) + 'px', left: ( rect.left + 5 ) + 'px', width: '120px', zIndex: 2 } );
		window.dispatchEvent( new Event( 'resize' ) );
	} );
	await dialog.waitFor( { state: 'hidden' } );
	await editor.press( 'End' );
	await editor.press( 'x' );
	assert.equal( await editor.inputValue(), 'stage-b-checkout@example.testx' );
	assert.equal( await editor.evaluate( ( field ) => field === document.activeElement ), true );
	await editor.blur();
	await dialog.waitFor();
	await context.close();
	console.log( 'PASS WooCommerce checkout: focused field remains editable and the overlapping dialog yields.' );
}

async function assertPlacement( page, expected, edge = 'bottom' ) {
	await page.waitForFunction( ( { expected, edge } ) => {
		const toast = document.querySelector( '.recently-bought-dialog' );
		const rect = toast?.getBoundingClientRect();
		if ( ! rect?.height || getComputedStyle( toast ).visibility !== 'visible' ) {
			return false;
		}
		const viewport = window.visualViewport;
		const bottom = viewport.offsetTop + viewport.height;
		const right = viewport.offsetLeft + viewport.width;
		const distance = edge === 'bottom' ? bottom - rect.bottom : rect.top - viewport.offsetTop;
		return Math.abs( distance - expected ) <= 1 && rect.left >= viewport.offsetLeft && rect.right <= right + 1 &&
			rect.top >= viewport.offsetTop && rect.bottom <= bottom + 1 &&
			document.documentElement.scrollWidth <= document.documentElement.clientWidth;
	}, { expected, edge }, { timeout: 2500 } );
}

async function renderObstacles( page, html ) {
	await page.evaluate( ( html ) => {
		let container = document.querySelector( '#obstacles' );
		if ( ! container ) {
			container = document.createElement( 'div' );
			container.id = 'obstacles';
			document.body.prepend( container );
		}
		container.innerHTML = html;
	}, html );
}

const barControls = '<button type="button">Menu</button><button type="button">Search</button><button type="button">Cart</button>';
const navBar = ( height, extra = '' ) => '<nav aria-label="Shop" style="position:fixed;inset:auto 0 0;height:' +
	height + 'px;display:flex;justify-content:space-around;align-items:center;z-index:10;' + extra + '">' + barControls + '</nav>';

async function assertNavigationAvailable( page ) {
	for ( const button of await page.locator( '#obstacles nav button' ).all() ) {
		assert.equal( await button.evaluate( ( element ) => {
			const rect = element.getBoundingClientRect();
			return element.contains( document.elementFromPoint( rect.left + rect.width / 2, rect.top + rect.height / 2 ) );
		} ), true );
	}
}

async function checkBottomNavigation( browser, fixture, browserName ) {
	const context = await fixtureContext( browser, fixture, {
		viewport: { width: 390, height: 844 }, settings: { corner: 1, bottomSpacing: 'automatic' }, long: true,
		css: '.ui-dialog.ui-widget.recently-bought-dialog{--rbt-safe-bottom:var(--fixture-safe,env(safe-area-inset-bottom,0px))}#obstacles nav.responsive{display:flex}' +
			'@media(min-width:601px){#obstacles nav.responsive{height:52px}}@media(max-width:600px){#obstacles nav.responsive{height:80px}}' +
			'@media(min-width:901px){#obstacles nav.responsive{display:none}}'
	} );
	const page = await start( context );
	const dialog = page.locator( selector );
	await assertPlacement( page, 10 );
	const compact = await dialog.boundingBox();
	const close = await dialog.getByRole( 'button', { name: 'Close notification' } ).boundingBox();
	assert( compact.width <= 280 && compact.height <= 120 && close.width >= 44 && close.height >= 44,
		JSON.stringify( { compact, close } ) );
	assert( await dialog.locator( '#productTitle' ).evaluate( ( title ) =>
		title.getBoundingClientRect().height <= 2 * Number.parseFloat( getComputedStyle( title ).lineHeight ) + 1 ) );
	assert.match( await dialog.getByRole( 'link' ).ariaSnapshot(), /LongUnbrokenSyntheticProduct.{500}/ );
	await renderObstacles( page, '<section>' + navBar( 50 ) + '</section>' );
	for ( const height of [ 50, 80, 120 ] ) {
		await page.locator( '#obstacles nav' ).evaluate( ( nav, height ) => { nav.style.height = height + 'px'; }, height );
		await assertPlacement( page, height + 10 );
		await assertNavigationAvailable( page );
	}
	await page.locator( '#obstacles section' ).evaluate( ( element ) => { element.style.opacity = '0'; } );
	await assertPlacement( page, 10 );
	await renderObstacles( page, navBar( 80 ).replace( '<nav ', '<nav class="responsive" ' ).replace( 'height:80px;display:flex;', '' ) );
	for ( const [ width, height, offset ] of [
		[ 320, 550, 90 ], [ 360, 740, 90 ], [ 390, 844, 90 ], [ 412, 915, 90 ], [ 600, 960, 90 ],
		[ 768, 1024, 62 ], [ 820, 1180, 62 ], [ 844, 390, 62 ], [ 1024, 768, 12 ], [ 1180, 820, 12 ], [ 1440, 900, 12 ]
	] ) {
		await page.setViewportSize( { width, height } );
		await assertPlacement( page, offset );
		if ( offset > 12 ) {
			await assertNavigationAvailable( page );
		}
	}
	await page.setViewportSize( { width: 390, height: 844 } );
	await renderObstacles( page, '<section style="height:1800px"><div style="height:1000px"></div><nav style="position:sticky;bottom:0;height:50px">' + barControls + '</nav></section>' );
	await assertPlacement( page, 60 );
	await page.evaluate( () => window.scrollTo( 0, 500 ) );
	await assertPlacement( page, 10 );
	await page.evaluate( () => window.scrollTo( 0, 0 ) );
	await assertPlacement( page, 60 );
	await renderObstacles( page, '<button style="position:fixed;bottom:0;right:0">Cookies</button><button style="position:fixed;bottom:12px;left:3px">Chat</button><footer style="height:40px">Footer</footer>' +
		'<nav style="position:fixed;bottom:0;right:0;width:40px;height:80px">One</nav>' );
	await assertPlacement( page, 10 );
	await renderObstacles( page, navBar( 50 ) + navBar( 55, 'bottom:32px' ) );
	await assertPlacement( page, 97 );
	await page.setViewportSize( { width: 820, height: 1180 } );
	await renderObstacles( page, navBar( 80, 'width:280px' ) );
	await assertPlacement( page, 10 );
	await page.setViewportSize( { width: 390, height: 844 } );
	await renderObstacles( page, '<div id="onetrust-banner-sdk" style="position:fixed;inset:auto 0 0;height:95px"><button>Accept</button></div>' );
	await dialog.waitFor( { state: 'hidden' } );
	await renderObstacles( page, '<dialog><input aria-label="Modal input"></dialog>' );
	await page.locator( 'dialog' ).evaluate( ( element ) => element.showModal() );
	await dialog.waitFor( { state: 'hidden' } );
	await page.locator( 'dialog' ).evaluate( ( element ) => element.close() );
	await assertPlacement( page, 10 );
	if ( browserName === 'chromium' ) {
		const devtools = await context.newCDPSession( page );
		await devtools.send( 'Emulation.setSafeAreaInsetsOverride', { insets: { bottom: 34 } } );
	} else {
		await page.locator( 'html' ).evaluate( ( root ) => root.style.setProperty( '--fixture-safe', '34px' ) );
	}
	await assertPlacement( page, 34 );
	await renderObstacles( page, navBar( 52, 'padding-bottom:34px;box-sizing:content-box' ) );
	await assertPlacement( page, 96 );
	await page.setViewportSize( { width: 390, height: 125 } );
	await dialog.waitFor( { state: 'hidden' } );
	await page.setViewportSize( { width: 390, height: 844 } );
	await assertPlacement( page, 96 );
	await renderObstacles( page, '' );
	await assertPlacement( page, 34 );
	assert.deepEqual( page.errors, [] );
	await context.close();

	for ( const corner of [ 0, 1, 2, 3 ] ) {
		for ( const bottomSpacing of [ 'automatic', 'standard', 'manual' ] ) {
			const context = await fixtureContext( browser, fixture, {
				viewport: { width: 390, height: 844 }, settings: { corner, bottomSpacing, bottomOffset: 120 }
			} );
			const page = await start( context );
			await renderObstacles( page, navBar( 80 ) );
			const bottom = [ 1, 2 ].includes( corner );
			const expected = bottom ? { automatic: 90, standard: 10, manual: 130 }[ bottomSpacing ] : 10;
			await assertPlacement( page, expected, bottom ? 'bottom' : 'top' );
			const rect = await page.locator( selector ).boundingBox();
			assert.ok( Math.abs( ( [ 0, 1 ].includes( corner ) ? 390 - rect.x - rect.width : rect.x ) - 10 ) <= 1 );
			assert.deepEqual( page.errors, [] );
			await context.close();
		}
	}
	console.log( 'PASS ' + browserName + ': dynamic bars, sticky and responsive navigation, exclusions, overlays, safe-area, orientation, all widths, all corners and modes.' );
}

async function checkPalettes( browser, fixture, browserName ) {
	const context = await fixtureContext( browser, fixture, {
		colorScheme: 'dark', reducedMotion: 'no-preference', viewport: { width: 390, height: 844 }, count: 2, time: true,
		css: 'body{background:#fff}html.dark body{background:#182020;color:#ddd}' +
			'.woocommerce .ui-widget-content,.ui-dialog .ui-widget-content{background:#181818;color:#111}' +
			'.woocommerce a,.woocommerce small,.woocommerce a span,.woocommerce .ui-button{color:#171717;background:#333}'
	} );
	const page = await start( context );
	const dialog = page.locator( selector );
	await dialog.waitFor();
	await page.waitForFunction( () => getComputedStyle( document.querySelector( '.recently-bought-dialog' ) ).opacity === '1' );
	for ( const [ palette, background ] of [ [ 'light', 'rgb(255, 255, 255)' ], [ 'dark', 'rgb(32, 38, 43)' ] ] ) {
		await page.locator( 'html' ).evaluate( ( root, palette ) => root.classList.toggle( 'dark', palette === 'dark' ), palette );
		await page.waitForFunction( ( palette ) => document.querySelector( '.recently-bought-dialog' ).dataset.palette === palette, palette );
		const colors = await dialog.evaluate( ( panel ) => ( {
			background: getComputedStyle( panel ).backgroundColor,
			foregrounds: [ '#justBought', '#itemsToShow a', '.recently-bought-copy > span', '#productTitle', 'small', 'button' ]
				.map( ( selector ) => getComputedStyle( panel.querySelector( selector ) ).color )
		} ) );
		assert.equal( colors.background, background );
		const ratios = colors.foregrounds.map( ( color ) => contrast( color, colors.background ) );
		assert( ratios.every( ( ratio ) => ratio >= 4.5 ) );
		console.log( 'PASS ' + browserName + ' ' + palette + ' computed colors ' + JSON.stringify( { ...colors, ratios } ) );
	}
	if ( browserName === 'chromium' ) {
		await page.locator( 'html' ).evaluate( ( root ) => root.classList.remove( 'dark' ) );
		await page.waitForFunction( () => document.querySelector( '.recently-bought-dialog' ).dataset.palette === 'light' );
		const rect = await dialog.boundingBox();
		const widget = { x: Math.floor( rect.x + 8 ), y: Math.floor( rect.y + rect.height - 7 ), width: 2, height: 2 };
		const shop = { x: 20, y: 40, width: 2, height: 2 };
		const widgetBefore = await page.screenshot( { clip: widget } );
		const shopBefore = await page.screenshot( { clip: shop } );
		const devtools = await context.newCDPSession( page );
		await devtools.send( 'Emulation.setAutoDarkModeOverride', { enabled: true } );
		assert.notDeepEqual( await page.screenshot( { clip: shop } ), shopBefore );
		assert.deepEqual( await page.screenshot( { clip: widget } ), widgetBefore );
		console.log( 'PASS Chromium forced auto dark mode leaves widget pixels in the storefront palette.' );
	}
	assert.deepEqual( page.errors, [] );
	await context.close();
}

async function checkLayoutCost( browser, fixture ) {
	const context = await fixtureContext( browser, fixture, { settings: { corner: 1 }, viewport: { width: 390, height: 844 } } );
	const page = await start( context );
	await renderObstacles( page, '<main>' + '<p>Catalog item</p>'.repeat( 3000 ) + '</main>' + navBar( 80 ) );
	await assertPlacement( page, 90 );
	const costs = await page.evaluate( async () => {
		const originalFrame = window.requestAnimationFrame;
		const originalRect = Element.prototype.getBoundingClientRect;
		const originalQuery = Element.prototype.querySelectorAll;
		const timings = [];
		let rects = 0;
		let queries = 0;
		window.requestAnimationFrame = ( callback ) => originalFrame( ( time ) => {
			const start = performance.now();
			callback( time );
			timings.push( performance.now() - start );
		} );
		Element.prototype.getBoundingClientRect = function () {
			++rects;
			return originalRect.call( this );
		};
		Element.prototype.querySelectorAll = function ( ...args ) {
			++queries;
			return originalQuery.apply( this, args );
		};
		try {
			for ( let frame = 0; frame < 60; ++frame ) {
				for ( let event = 0; event < 10; ++event ) {
					document.dispatchEvent( new Event( 'scroll' ) );
				}
				await new Promise( ( resolve ) => originalFrame( resolve ) );
			}
		} finally {
			window.requestAnimationFrame = originalFrame;
			Element.prototype.getBoundingClientRect = originalRect;
			Element.prototype.querySelectorAll = originalQuery;
		}
		timings.sort( ( a, b ) => a - b );
		return { frames: timings.length, queries, rects, mean: timings.reduce( ( sum, time ) => sum + time, 0 ) / timings.length, p95: timings[ 56 ], max: timings.at( -1 ) };
	} );
	assert.equal( costs.frames, 60 );
	assert.equal( costs.queries, 0 );
	assert( costs.rects <= 180 );
	console.log( 'PASS Chromium layout cost with 3,000 catalog nodes, 600 scroll events ' + JSON.stringify( costs ) );
	await context.close();
}

async function checkStorefrontNavigation( browser ) {
	const context = await browser.newContext( { viewport: { width: 390, height: 844 }, reducedMotion: 'reduce' } );
	await context.route( '**/*', ( route ) => new URL( route.request().url() ).origin === origin ? route.continue() : route.abort() );
	const page = await context.newPage();
	await page.goto( origin + '/?rbt_corner=1&rbt_theme=storefront' );
	const nav = page.locator( '.storefront-handheld-footer-bar' );
	await nav.waitFor();
	await assertPlacement( page, ( await nav.boundingBox() ).height + 10 );
	const search = nav.locator( '.search > a' );
	const cart = nav.locator( '.cart > a' );
	await cart.click( { trial: true } );
	await search.click();
	await nav.locator( '.search.active' ).waitFor();
	await page.locator( selector ).waitFor( { state: 'hidden' } );
	await nav.locator( 'input[type=search]' ).fill( 'synthetic' );
	await context.close();
	console.log( 'PASS live local Storefront bar, cart hit target and expanded search.' );
}

( async () => {
	const browserName = process.env.RBT_BROWSER || 'chromium';
	const browser = await { chromium, firefox, webkit }[ browserName ].launch();
	try {
		const fixture = await captureFixture( browser );
		await checkBottomNavigation( browser, fixture, browserName );
		await checkPalettes( browser, fixture, browserName );
		if ( browserName !== 'chromium' || process.argv.includes( '--geometry-only' ) ) {
			return;
		}
		await checkLayoutCost( browser, fixture );
		await checkStorefrontNavigation( browser );
		await checkTimerCycles( browser, fixture );
		await checkInteraction( browser, fixture );
		await checkCookiesAndCachedMarkup( browser, fixture );
		await checkViewportAndKeyboard( browser, fixture );
		await checkCheckout( browser );
	} finally {
		await browser.close();
	}
} )().catch( ( error ) => {
	console.error( error );
	process.exitCode = 1;
} );
