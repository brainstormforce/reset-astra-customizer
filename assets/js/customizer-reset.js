/**
 * Astra Theme Customizer Reset
 *
 * @package Astra Customizer Reset
 * @since  1.0.0
 */

(function($) {

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
    jQuery(document).ready(function($) {

        var isRTL         = jQuery('body').hasClass('rtl'),
            headerActions = jQuery('#customize-header-actions'),
            saveWrapper   = jQuery('#customize-save-button-wrapper'),
            spinner       = headerActions.find('.spinner'),
            button        = jQuery('<input type="submit" name="astra-reset" id="astra-reset">')
                .attr('value', astraThemeCustomizerReset.customizer.reset.stringReset)
                .addClass('button button-secondary save')
                .css({
                    'float':         isRTL ? 'right' : 'left',
                    'margin-top':    '0',
                    'margin-right':  isRTL ? '0'   : '4px',
                    'margin-left':   isRTL ? '4px' : '0',
                    'border-radius': '3px',
                    'box-shadow':    'none'
                });

        // Process on click.
        button.on('click', function(event) {
            event.preventDefault();

            // Reset all confirm?
            if (confirm(astraThemeCustomizerReset.customizer.reset.stringConfirm)) {

                // Enable loader.
                spinner.addClass('is-active');

                var data = {
                    wp_customize: 'on',
                    action: 'astra_theme_customizer_reset',
                    nonce: astraThemeCustomizerReset.customizer.reset.nonce
                };

                // Disable button.
                button.attr('disabled', 'disabled');

                // Process AJAX.
                jQuery.post(ajaxurl, data, function(result) {

                    // If pass then trigger the state 'saved'.
                    if ('pass' === result.data) {
                        wp.customize.state('saved').set(true);
                    }

                    var Url = window.location.href;
                    Url = Url.split("?")[0];
                    window.location.href = Url;

                });
            }
        });

        // In both LTR and RTL, insert before the Publish button in DOM order.
        // LTR: float left  → visual [Reset All][Publish]
        // RTL: float right → visual [Publish][Reset All], reading right-to-left: Reset All first.
        if ( saveWrapper.length ) {
            saveWrapper.find('.save').first().before(button);
        } else {
            headerActions.append(button);
        }
    });

})(jQuery);
