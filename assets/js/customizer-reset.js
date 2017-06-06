/**
 * Astra Theme Customizer Reset
 *
 * @package Astra Customizer Reset
 * @since  1.0.0
 */

(function( $ ) {

	/**
	 * Theme Customizer enhancements for a better user experience.
	 *
	 * Contains handlers to make Theme Customizer preview reload changes asynchronously.
	 *
	 * => Contents
	 *
	 * 	-	Site title and description.
	 * 	-	Colors
	 */

	/**
	 * Reset "Astra Theme" Customizer Options
	 */
	jQuery( document ).ready(function($) {

	    var container = jQuery( '#customize-header-actions' );

	    var button = jQuery( '<input type="submit" name="astra-reset" id="astra-reset" class="button-secondary button">' )
	        .attr( 'value', astraCustomizer.customizer.reset.stringReset )
	        .css({
	            'float': 'right',
	            'margin-top': '9px'
	        });

	    button.on('click', function (event) {
	        event.preventDefault();

	        // Reset all confirm?
	        if ( confirm( astraCustomizer.customizer.reset.stringConfirm ) ) {

		        // Enable loader.
		    	container.find( '.spinner' ).addClass( 'is-active' );

		        var data = {
		            wp_customize: 'on',
		            action: 'astra_customizer_reset',
		            nonce: astraCustomizer.customizer.reset.nonce
		        };

		        button.attr( 'disabled', 'disabled' );

		        jQuery.post( ajaxurl, data, function( result ) {
		        	wp.customize.state( 'saved' ).set( true );

		        	var data = {
			            action: 'astra_regenerate_assets',
			        };

			        jQuery.post( ajaxurl, data, function( result ) {
		        		var Url = window.location.href;
							Url = Url.split( "?" )[0]
							window.location.href = Url;
		        	});
		        });
	        }
	    });

	    container.append( button );
	});
	
})( jQuery );