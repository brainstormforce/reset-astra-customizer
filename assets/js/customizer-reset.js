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
            button        = jQuery('<input type="submit" name="astra-reset" id="astra-reset">')
                .attr('value', astraThemeCustomizerReset.customizer.reset.stringReset)
                .addClass('button button-secondary save')
                .css({
                    'float':         isRTL ? 'right' : 'left',
                    'margin-top':    '0',
                    'margin-right':  isRTL ? '0'   : '4px',
                    'margin-left':   isRTL ? '4px' : '0',
                    'border-radius': '3px',
                    'box-shadow':    'none',
                    'line-height':   1,
                    'min-height':    '32px',
                });

        var prefix     = 'astra-settings[',
            noticeCode = 'astra_customizer_reset_pending';

        /**
         * Reset the Astra settings in the Customizer preview only.
         *
         * Nothing is saved to the database here. The values are saved
         * when the user clicks the Publish button.
         */
        function resetInPreview() {

            var defaults = astraThemeCustomizerReset.customizer.reset.defaults || {};

            wp.customize.each(function(setting) {

                // Notice boxes only show a message and hold no value.
                if (0 !== setting.id.indexOf(prefix) || -1 !== setting.id.indexOf('ast-callback-notice')) {
                    return;
                }

                var key = setting.id.slice(prefix.length, -1);

                // Settings without a default (for example logos) are emptied.
                var value = defaults.hasOwnProperty(key) ? defaults[key] : '';

                // Copy the value so the defaults list is never changed by reference.
                setting.set(JSON.parse(JSON.stringify(value)));
            });

            // Typography presets are removed by the original reset, so empty them here too.
            if (wp.customize.has('astra-typography-presets')) {
                wp.customize('astra-typography-presets').set('');
            }

            // The site logo is a theme mod and has no registered default.
            if (wp.customize.has('custom_logo')) {
                wp.customize('custom_logo').set('');
            }

            // Tell the server to also reset the Astra admin settings on Publish.
            if (wp.customize.has('astra-customizer-reset-pending')) {
                wp.customize('astra-customizer-reset-pending').set('1');
            }
        }

        // Process on click.
        button.on('click', function(event) {
            event.preventDefault();

            // Reset all confirm?
            if (confirm(astraThemeCustomizerReset.customizer.reset.stringConfirm)) {
                resetInPreview();

                // Show that the reset is only in the preview until Publish is clicked.
                button.attr('disabled', 'disabled');

                wp.customize.notifications.add(
                    new wp.customize.Notification(noticeCode, {
                        type: 'info',
                        message: astraThemeCustomizerReset.customizer.reset.stringNotice,
                        dismissible: true
                    })
                );
                wp.a11y.speak(astraThemeCustomizerReset.customizer.reset.stringNotice);
            }
        });

        // After Publish the reset is saved, so the button can be used again.
        wp.customize.bind('saved', function() {
            button.removeAttr('disabled');
            wp.customize.notifications.remove(noticeCode);
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
