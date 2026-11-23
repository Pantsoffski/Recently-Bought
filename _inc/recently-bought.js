( function ( $ ) {
	'use strict';

	$( function () {
		var template = document.getElementById( 'recently-bought-notifications' );
		var closed = document.cookie.split( ';' ).some( function ( cookie ) {
			return /^\s*justBought\s*=\s*\S/.test( cookie );
		} );
		if ( ! template || ! template.content.children.length || closed ) {
			return;
		}

		var settings = JSON.parse( template.dataset.settings );
		var entries = template.content.children;
		var corners = [ 'right top', 'right bottom', 'left bottom', 'left top' ];
		var $dialog = $( '#justBought' );
		var $items = $( '#itemsToShow' );
		var index = 0;
		var rotation;
		var manualClose = true;

		$dialog.dialog( {
			position: { my: corners[ settings.corner ], at: corners[ settings.corner ], of: window },
			dialogClass: 'fixed-dialog',
			autoOpen: false,
			draggable: false,
			resizable: false,
			show: { effect: 'fade', duration: 1000 },
			hide: { effect: 'fade', duration: 1000 },
			beforeClose: function () {
				window.clearInterval( rotation );
				$items.stop( true );
				var expires = new Date( Date.now() + ( manualClose ? 600 : 15 ) * 1000 );
				document.cookie = 'justBought=closed; expires=' + expires.toUTCString() + '; path=/';
			},
			close: function () {
				$dialog.dialog( 'destroy' ).remove();
			}
		} );

		$items[ 0 ].appendChild( entries[ 0 ].cloneNode( true ) );
		window.setTimeout( function () {
			$dialog.dialog( 'open' );
			rotation = window.setInterval( function () {
				index += 1;
				if ( index === entries.length ) {
					manualClose = false;
					$dialog.dialog( 'close' );
					return;
				}
				var entry = entries[ index ];
				$items.fadeOut( 500, function () {
					this.replaceChildren( entry.cloneNode( true ) );
					$items.fadeIn( 500 );
				} );
			}, 5000 );
		}, settings.delay * 1000 );
	} );
} )( jQuery );
