( function ( $ ) {
	'use strict';

	const corners = [ 'right top', 'right bottom', 'left bottom', 'left top' ];

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
		const viewport = window.visualViewport;
		let timer;
		let frame;
		let index = 0;
		let hovered = false;
		let obscuresFocus = false;
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
					false : { effect: 'fadeOut', duration: 1000 } );
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
		const scheme = getComputedStyle( document.body ).colorScheme;
		if ( scheme === 'dark' || scheme === 'light' ) {
			panel.style.colorScheme = scheme;
		}
		$panel.find( '.ui-dialog-titlebar-close' ).attr( 'aria-label', settings.closeText ).text( '×' );
		$items[ 0 ].replaceChildren( entries[ index ].cloneNode( true ) );

		function stop() {
			disposed = true;
			window.clearTimeout( timer );
			window.cancelAnimationFrame( frame );
			$items.stop( true );
			$( document ).off( '.rbtNotice' );
			$( window ).off( '.rbtNotice' );
			$( viewport ).off( '.rbtNotice' );
			$panel.off( '.rbtNotice' );
			panel.removeEventListener( 'load', scheduleLayout, true );
		}

		function updateRotation() {
			if ( disposed || ! $dialog.notice( 'isOpen' ) ) {
				return;
			}
			window.clearTimeout( timer );
			if ( hovered || obscuresFocus || panel.contains( document.activeElement ) ) {
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
						$items.fadeIn( 500 );
					}
					layout();
					updateRotation();
				};
				if ( motion.matches ) {
					replace.call( $items[ 0 ] );
				} else {
					$items.fadeOut( 500, replace );
				}
			}, 5000 );
		}

		function layout() {
			if ( viewport ) {
				const root = document.documentElement;
				panel.style.setProperty( '--rbt-offset-top', viewport.offsetTop + 'px' );
				panel.style.setProperty( '--rbt-offset-left', viewport.offsetLeft + 'px' );
				panel.style.setProperty( '--rbt-offset-right', Math.max( 0, root.clientWidth - viewport.width - viewport.offsetLeft ) + 'px' );
				panel.style.setProperty( '--rbt-offset-bottom', Math.max( 0, root.clientHeight - viewport.height - viewport.offsetTop ) + 'px' );
			}

			const active = document.activeElement;
			let covered = false;
			if ( active && active !== document.body && ! panel.contains( active ) ) {
				const field = active.getBoundingClientRect();
				const widget = panel.getBoundingClientRect();
				covered = field.top < widget.bottom && field.bottom > widget.top &&
					field.left < widget.right && field.right > widget.left;
			}
			if ( covered !== obscuresFocus ) {
				obscuresFocus = covered;
				panel.classList.toggle( 'recently-bought-obscures-focus', covered );
				updateRotation();
			}
		}

		function scheduleLayout() {
			window.cancelAnimationFrame( frame );
			frame = window.requestAnimationFrame( layout );
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
			$( window ).on( 'resize.rbtNotice scroll.rbtNotice', scheduleLayout );
			$( viewport ).on( 'resize.rbtNotice scroll.rbtNotice', scheduleLayout );
			panel.addEventListener( 'load', scheduleLayout, true );

			$dialog.notice( 'option', 'show', motion.matches ? false : { effect: 'fadeIn', duration: 1000 } );
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
