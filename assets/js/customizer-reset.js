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

        var container = jQuery('#customize-header-actions'),
            button = jQuery('<input type="submit" name="astra-reset" id="astra-reset" class="button-secondary button">')
            .attr('value', astraThemeCustomizerReset.customizer.reset.stringReset)
            .css({
                'float': 'right',
                'margin-top': '9px'
            });

        // Process on click.
        button.on('click', function(event) {
            event.preventDefault();

            // Reset all confirm?
            if (confirm(astraThemeCustomizerReset.customizer.reset.stringConfirm)) {

                // Enable loader.
                container.find('.spinner').addClass('is-active');

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

        container.append(button);
    });

})(jQuery);
