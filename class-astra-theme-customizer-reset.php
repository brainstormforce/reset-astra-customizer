<?php
/**
 * Plugin Name: Astra Customizer Reset
 * Plugin URI: https://wpastra.com/
 * Description: Reset the Astra theme customizer options from customizer interface.
 * Version: 1.1.0
 * Author: Brainstorm Force
 * Author URI: http://www.brainstormforce.com
 * Text Domain: astra-customizer-reset
 * Requires at least: 5.3
 * Requires PHP: 5.6
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Astra Customizer Reset
 */

if ( 'astra' !== get_template() ) {
	return;
}

define( 'ASTRA_THEME_CUSTOMIZER_RESET_URI', plugins_url( '/', __FILE__ ) );
define( 'ASTRA_CUSTOMIZER_VERSION', '1.1.0' );
define( 'ASTRA_THEME_CUSTOMIZER_RESET_DIR', plugin_dir_path( __FILE__ ) );

require_once ASTRA_THEME_CUSTOMIZER_RESET_DIR . 'classes/class-astra-theme-customizer-reset.php';
require_once ASTRA_THEME_CUSTOMIZER_RESET_DIR . 'classes/class-astra-theme-customizer-reset-publish-now.php';
require_once ASTRA_THEME_CUSTOMIZER_RESET_DIR . 'classes/class-astra-theme-customizer-reset-preview.php';
require_once ASTRA_THEME_CUSTOMIZER_RESET_DIR . 'classes/class-astra-theme-customizer-reset-backup.php';

Astra_Theme_Customizer_Reset::get_instance();
