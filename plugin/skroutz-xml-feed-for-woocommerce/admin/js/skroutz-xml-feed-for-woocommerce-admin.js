( function( $ ) {
	'use strict';

	$( function() {
		$( document ).on( 'click', '.sxffw-copy-feed-url', function( event ) {
			var button = event.currentTarget;
			var url = button.getAttribute( 'data-url' );

			event.preventDefault();

			if ( ! url || ! navigator.clipboard ) {
				window.alert( SXFFWAdmin.copyFallback );
				return;
			}

			navigator.clipboard.writeText( url ).then( function() {
				window.alert( SXFFWAdmin.copySuccess );
			} ).catch( function() {
				window.alert( SXFFWAdmin.copyFallback );
			} );
		} );
	} );
}( jQuery ) );
