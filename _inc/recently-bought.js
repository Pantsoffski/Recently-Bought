( function ( $ ) {
	'use strict';

	const corners = [ 'right top', 'right bottom', 'left bottom', 'left top' ];
	const bottomBars = '.storefront-handheld-footer-bar, .wd-toolbar, .botiga-mobile-dock, .mobile-bottom-nav, .bottom-navigation';
	const blockingOverlays = '[aria-modal="true"], [role="alertdialog"], dialog[open], [popover], #onetrust-banner-sdk, #onetrust-pc-sdk, #CybotCookiebotDialog, .cmplz-cookiebanner, .storefront-handheld-footer-bar .site-search';

	function overlaps( first, second ) {
		return first.top < second.bottom && first.bottom > second.top && first.left < second.right && first.right > second.left;
	}

	function storefrontPalette() {
		const canvas = document.createElement( 'canvas' );
		canvas.width = canvas.height = 1;
		const context = canvas.getContext( '2d', { willReadFrequently: true } );
		if ( ! context ) {
			return 'light';
		}
		context.fillStyle = '#fff';
		context.fillRect( 0, 0, 1, 1 );
		for ( const element of [ document.documentElement, document.body ] ) {
			context.fillStyle = getComputedStyle( element ).backgroundColor;
			context.fillRect( 0, 0, 1, 1 );
		}
		const [ red, green, blue ] = Array.from( context.getImageData( 0, 0, 1, 1 ).data, ( channel ) => {
			const value = channel / 255;
			return value <= 0.04045 ? value / 12.92 : ( ( value + 0.055 ) / 1.055 ) ** 2.4;
		} );
		return 0.2126 * red + 0.7152 * green + 0.0722 * blue < 0.179 ? 'dark' : 'light';
	}

	function watchObstacles( panel, avoidBars, schedule ) {
		const selector = avoidBars ? bottomBars + ', nav, [role="navigation"], ' + blockingOverlays : blockingOverlays;
		const candidates = new Set();
		const ancestors = new Set();
		let targets = new Map();
		let dirty = true;
		const sizes = typeof ResizeObserver === 'function' ? new ResizeObserver( schedule ) : null;

		function discover( root ) {
			if ( root !== document.body && root.matches( selector ) ) {
				candidates.add( root );
				dirty = true;
			}
			for ( const element of root.querySelectorAll( selector ) ) {
				if ( ! panel.contains( element ) ) {
					candidates.add( element );
					dirty = true;
				}
			}
		}

		function positionedTargets() {
			const next = new Map();
			const positions = new Map();
			ancestors.clear();
			for ( const candidate of candidates ) {
				if ( ! candidate.isConnected || ! candidate.matches( selector ) ) {
					candidates.delete( candidate );
					continue;
				}
				const overlay = candidate.matches( blockingOverlays );
				const known = candidate.matches( bottomBars );
				let positioned;
				for ( let element = candidate; element; element = element.parentElement ) {
					ancestors.add( element );
					if ( overlay || positioned || element === document.body || element === document.documentElement ) {
						continue;
					}
					if ( ! positions.has( element ) ) {
						const style = getComputedStyle( element );
						positions.set( element, style.position === 'fixed' || ( style.position === 'sticky' && style.bottom !== 'auto' ) );
					}
					if ( positions.get( element ) ) {
						positioned = element;
					}
				}
				if ( overlay || positioned ) {
					const element = overlay ? candidate : positioned;
					next.set( element, { overlay: overlay || next.get( element )?.overlay, known: known || next.get( element )?.known } );
				}
			}
			for ( const element of targets.keys() ) {
				if ( ! next.has( element ) ) {
					sizes?.unobserve( element );
				}
			}
			for ( const element of next.keys() ) {
				if ( ! targets.has( element ) ) {
					sizes?.observe( element );
				}
			}
			targets = next;
			dirty = false;
		}

		const changes = new MutationObserver( ( records ) => {
			for ( const { target, type, addedNodes, removedNodes } of records ) {
				if ( panel.contains( target ) ) {
					continue;
				}
				if ( type === 'attributes' ) {
					if ( target.matches( selector ) ) {
						candidates.add( target );
						dirty = true;
					} else if ( candidates.has( target ) || ancestors.has( target ) ) {
						dirty = true;
					}
					continue;
				}
				for ( const node of addedNodes ) {
					if ( node.nodeType === Node.ELEMENT_NODE && node !== panel ) {
						discover( node );
					}
				}
				if ( removedNodes.length && Array.from( candidates ).some( ( element ) => ! element.isConnected ) ) {
					dirty = true;
				}
			}
			if ( dirty ) {
				schedule();
			}
		} );

		// CSS transitions can move a bar without changing its border-box size.
		function onTransition( event ) {
			if ( ancestors.has( event.target ) ) {
				schedule();
			}
		}

		discover( document.body );
		changes.observe( document.documentElement, {
			childList: true, subtree: true, attributes: true,
			attributeFilter: [ 'class', 'style', 'id', 'hidden', 'aria-hidden', 'inert', 'role', 'open', 'aria-modal', 'popover' ]
		} );
		$( document ).on( 'transitionend.rbtNotice animationend.rbtNotice toggle.rbtNotice', onTransition );

		return {
			invalidate() {
				dirty = true;
			},
			measure( view, widget ) {
				if ( dirty ) {
					positionedTargets();
				}
				const bars = [];
				const overlays = [];
				for ( const [ element, { overlay, known } ] of targets ) {
					const rect = element.getBoundingClientRect();
					if ( ! rect.width || ! rect.height || ! overlaps( view, rect ) ||
						element.closest( '[hidden], [aria-hidden="true"], [inert]' ) ||
						( element.checkVisibility ? ! element.checkVisibility( { opacityProperty: true, visibilityProperty: true } ) :
							getComputedStyle( element ).visibility !== 'visible' || Number( getComputedStyle( element ).opacity ) === 0 ) ) {
						continue;
					}
					if ( overlay ) {
						const modal = element.matches( '[aria-modal="true"], [role="alertdialog"], :modal, .storefront-handheld-footer-bar .search.active .site-search' );
						overlays.push( { rect, modal } );
					} else if ( rect.left < widget.right && rect.right > widget.left &&
						rect.height <= Math.max( 144, view.height / 2 ) &&
						rect.width >= Math.min( view.width * ( known ? 0.30 : 0.5 ), known ? 160 : 260 ) ) {
						bars.push( rect );
					}
				}
				return { bars, overlays };
			},
			stop() {
				changes.disconnect();
				sizes?.disconnect();
			}
		};
	}

	function occupiedBottom( view, bars, toastHeight, gap, safeBottom ) {
		let top = view.bottom;
		const normalBottom = view.bottom - Math.max( gap, safeBottom );
		bars.sort( ( first, second ) => second.bottom - first.bottom );
		for ( const bar of bars ) {
			const toastBottom = Math.min( normalBottom, top - gap );
			// Connect stacked bars by their actual edges, including docks inset from the edge.
			if ( bar.bottom >= top - Math.max( 32, safeBottom + gap ) &&
				bar.bottom > toastBottom - toastHeight && bar.top < toastBottom + gap ) {
				top = bar.top;
			}
		}
		return view.bottom - Math.max( view.top, top );
	}

	// Keep Dialog's wrapper and lifecycle without its automatic focus and Tab loop.
	$.widget( 'recentlyBought.notice', $.ui.dialog, {
		_focusTabbable: $.noop,
		_position: $.noop,
		_createWrapper: function () {
			this._super();
			this._off( this.uiDialog, 'keydown' );
			this.uiDialog.removeAttr( 'aria-describedby' );
			this._on( this.uiDialog, {
				keydown: function ( event ) {
					if ( event.key === 'Escape' && ! event.isDefaultPrevented() ) {
						event.preventDefault();
						this.close( event );
					}
				}
			} );
		}
	} );

	function isDismissed( name ) {
		try {
			return document.cookie.split( ';' ).some( ( cookie ) => cookie.trim() === name + '=closed' );
		} catch {
			return false;
		}
	}

	function rememberDismissal( settings, seconds ) {
		const path = /[;\u0000-\u001f\u007f]/.test( settings.cookiePath ) ? '/' : settings.cookiePath;
		const secure = window.location.protocol === 'https:' ? '; Secure' : '';
		try {
			document.cookie = settings.cookieName + '=closed; Max-Age=' + seconds + '; Path=' + path + '; SameSite=Lax' + secure;
		} catch {
			// Dismissal still works for this page when storage is blocked.
		}
	}

	function init() {
		const template = document.getElementById( 'recently-bought-notifications' );
		const $dialog = $( '#justBought' );
		if ( ! template || ! $dialog.length || template.dataset.initialized || ! template.content.children.length ) {
			return;
		}

		const settings = JSON.parse( template.dataset.settings );
		if ( isDismissed( settings.cookieName ) ) {
			return;
		}
		template.dataset.initialized = 'true';

		const entries = Array.from( template.content.children );
		if ( settings.randomize ) {
			for ( let index = entries.length - 1; index > 0; --index ) {
				const other = Math.floor( Math.random() * ( index + 1 ) );
				[ entries[ index ], entries[ other ] ] = [ entries[ other ], entries[ index ] ];
			}
		}

		const $items = $( '#itemsToShow' );
		const motion = window.matchMedia( '(prefers-reduced-motion: reduce)' );
		const colorMode = window.matchMedia( '(prefers-color-scheme: dark)' );
		const viewport = window.visualViewport;
		const bottom = settings.corner === 1 || settings.corner === 2;
		const avoidBars = bottom && ( ! settings.bottomSpacing || settings.bottomSpacing === 'automatic' );
		let timer;
		let frame;
		let obstacles;
		let paletteDirty = true;
		let index = 0;
		let hovered = false;
		let hidden = false;
		let automatic = false;
		let disposed = false;
		let lastOutsideFocus = document.activeElement;

		$dialog.notice( {
			position: { my: corners[ settings.corner ], at: corners[ settings.corner ], of: window },
			classes: { 'ui-dialog': 'fixed-dialog recently-bought-dialog' },
			autoOpen: false,
			draggable: false,
			resizable: false,
			minHeight: 0,
			minWidth: 0,
			closeText: settings.closeText,
			beforeClose: function () {
				const active = document.activeElement;
				$dialog.notice( 'instance' ).opener = $( panel.contains( active ) ? lastOutsideFocus : active );
				$dialog.notice( 'option', 'hide', motion.matches ?
					false : { effect: 'fadeOut', duration: 150 } );
				stop();
				if ( ! automatic || ! isDismissed( settings.cookieName ) ) {
					rememberDismissal( settings, automatic ? 15 : 600 );
				}
			},
			close: function () {
				$dialog.notice( 'destroy' ).remove();
			}
		} );

		const $panel = $dialog.notice( 'widget' );
		const panel = $panel[ 0 ];
		panel.dataset.corner = settings.corner;
		if ( bottom && settings.bottomSpacing === 'manual' ) {
			panel.style.setProperty( '--rbt-manual', settings.bottomOffset + 'px' );
		}
		const paletteObserver = new MutationObserver( refreshPalette );
		$panel.find( '.ui-dialog-titlebar-close' ).attr( 'aria-label', settings.closeText ).text( '×' );
		$items[ 0 ].replaceChildren( entries[ index ].cloneNode( true ) );

		function stop() {
			disposed = true;
			window.clearTimeout( timer );
			window.cancelAnimationFrame( frame );
			paletteObserver.disconnect();
			obstacles?.stop();
			$items.stop( true );
			$( document ).off( '.rbtNotice' );
			$( window ).off( '.rbtNotice' );
			$( viewport ).off( '.rbtNotice' );
			$( colorMode ).off( '.rbtNotice' );
			$panel.off( '.rbtNotice' );
			document.removeEventListener( 'scroll', scheduleLayout, true );
			panel.removeEventListener( 'load', scheduleLayout, true );
		}

		function updateRotation() {
			if ( disposed || ! $dialog.notice( 'isOpen' ) ) {
				return;
			}
			window.clearTimeout( timer );
			if ( hovered || hidden || panel.contains( document.activeElement ) ) {
				$items.stop( true ).css( { opacity: '', display: '' } );
				return;
			}
			timer = window.setTimeout( function () {
				if ( index + 1 === entries.length ) {
					automatic = true;
					$dialog.notice( 'close' );
					return;
				}

				const replace = function () {
					++index;
					this.replaceChildren( entries[ index ].cloneNode( true ) );
					if ( ! motion.matches ) {
						$items.fadeIn( 150 );
					}
					layout();
					updateRotation();
				};
				if ( motion.matches ) {
					replace.call( $items[ 0 ] );
				} else {
					$items.fadeOut( 150, replace );
				}
			}, 5000 );
		}

		function layout() {
			frame = undefined;
			if ( paletteDirty ) {
				panel.dataset.palette = storefrontPalette();
				paletteDirty = false;
			}
			const root = document.documentElement;
			const left = viewport?.offsetLeft || 0;
			const top = viewport?.offsetTop || 0;
			const width = viewport?.width || root.clientWidth;
			const height = viewport?.height || root.clientHeight;
			const view = { left, top, right: left + width, bottom: top + height, width, height };
			if ( viewport ) {
				setPixels( '--rbt-offset-top', top );
				setPixels( '--rbt-offset-left', left );
				setPixels( '--rbt-offset-right', Math.max( 0, root.clientWidth - view.right ) );
				setPixels( '--rbt-offset-bottom', Math.max( 0, root.clientHeight - view.bottom ) );
			}

			let widget = panel.getBoundingClientRect();
			const { bars, overlays } = obstacles.measure( view, widget );
			const content = $dialog[ 0 ];
			let occupied = 0;
			if ( bars.length ) {
				const style = getComputedStyle( panel );
				const gap = Number.parseFloat( style.getPropertyValue( '--rbt-gap' ) );
				const safeBottom = Number.parseFloat( style.getPropertyValue( '--rbt-safe-bottom' ) ) || 0;
				occupied = occupiedBottom( view, bars, content.scrollHeight + 2, gap, safeBottom );
			}
			if ( avoidBars && setPixels( '--rbt-occupied', occupied ) ) {
				widget = panel.getBoundingClientRect();
			}
			const noRoom = widget.width < 80 || widget.height < Math.min( 56, content.scrollHeight + 2 ) ||
				( occupied > 0 && content.scrollHeight > content.clientHeight + 1 );
			const blocked = overlays.some( ( { rect, modal } ) => modal || overlaps( widget, rect ) );
			const active = document.activeElement;
			const covered = active && active !== document.body && ! panel.contains( active ) && overlaps( widget, active.getBoundingClientRect() );
			const shouldHide = Boolean( noRoom || blocked || covered );
			if ( shouldHide !== hidden ) {
				hidden = shouldHide;
				panel.classList.toggle( 'recently-bought-hidden', hidden );
				if ( hidden ) {
					hovered = false;
				}
				updateRotation();
			}
		}

		function setPixels( property, pixels ) {
			const value = pixels + 'px';
			if ( panel.style.getPropertyValue( property ) === value ) {
				return false;
			}
			panel.style.setProperty( property, value );
			return true;
		}

		function scheduleLayout() {
			if ( ! disposed && frame === undefined ) {
				frame = window.requestAnimationFrame( layout );
			}
		}

		function refreshPalette() {
			paletteDirty = true;
			scheduleLayout();
		}

		function refreshViewport() {
			obstacles.invalidate();
			refreshPalette();
		}

		$panel.on( 'pointerenter.rbtNotice pointerleave.rbtNotice', function ( event ) {
			hovered = event.type === 'pointerenter';
			updateRotation();
		} );

		$( window ).one( 'pagehide.rbtNotice', function () {
			stop();
			$dialog.notice( 'destroy' );
			$items.empty();
			delete template.dataset.initialized;
		} );

		timer = window.setTimeout( function () {
			if ( isDismissed( settings.cookieName ) ) {
				stop();
				$dialog.notice( 'destroy' ).remove();
				return;
			}

			$( document ).on( 'focusin.rbtNotice focusout.rbtNotice', function ( event ) {
				if ( event.type === 'focusin' && ! panel.contains( event.target ) ) {
					lastOutsideFocus = event.target;
				}
				queueMicrotask( updateRotation );
				scheduleLayout();
			} );
			obstacles = watchObstacles( panel, avoidBars, scheduleLayout );
			for ( const element of [ document.documentElement, document.body ] ) {
				paletteObserver.observe( element, { attributes: true, attributeFilter: [ 'class', 'style', 'data-theme', 'data-color-scheme' ] } );
			}
			$( colorMode ).on( 'change.rbtNotice', refreshPalette );
			$( window ).on( 'resize.rbtNotice', refreshViewport );
			$( viewport ).on( 'resize.rbtNotice scroll.rbtNotice', scheduleLayout );
			document.addEventListener( 'scroll', scheduleLayout, true );
			panel.addEventListener( 'load', scheduleLayout, true );

			$dialog.notice( 'option', 'show', motion.matches ? false : { effect: 'fadeIn', duration: 150 } );
			$dialog.notice( 'open' );
			layout();
			updateRotation();
		}, settings.delay * 1000 );
	}

	$( init );
	$( window ).on( 'pageshow.recentlyBought', function ( event ) {
		if ( event.originalEvent.persisted ) {
			init();
		}
	} );
} )( jQuery );
