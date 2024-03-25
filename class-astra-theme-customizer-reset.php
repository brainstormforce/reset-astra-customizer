<?php
/**
 * Plugin Name: Astra Customizer Reset
 * Plugin URI: https://wpastra.com/
 * Description: Reset the Astra theme customizer options from customizer interface.
 * Version: 1.0.6
 * Author: Brainstorm Force
 * Author URI: http://www.brainstormforce.com
 * Text Domain: astra-customizer-reset
 *
 * @package Astra Customizer Reset
 */

if ( 'astra' !== get_template() ) {
	return;
}

define( 'ASTRA_THEME_CUSTOMIZER_RESET_URI', plugins_url( '/', __FILE__ ) );
define( 'ASTRA_CUSTOMIZER_VERSION', '1.0.6' );
/**
 * Astra Customizer Reset
 *
 * @since 1.0.0
 */
if ( ! class_exists( 'Astra_Theme_Customizer_Reset' ) ) :

	/**
	 * Astra Customizer Reset
	 */
	class Astra_Theme_Customizer_Reset {

		/**
		 * Member Variable
		 *
		 * @since 1.0.0
		 * @var $instance
		 */
		private static $instance;

		/**
		 * WordPress Customizer Object
		 *
		 * @since 1.0.0
		 * @var $wp_customize
		 */
		private $wp_customize;

		/**
		 * Initiator
		 *
		 * @since 1.0.0
		 */
		public static function get_instance() {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Constructor
		 *
		 * @since 1.0.0
		 */
		public function __construct() {

			add_action( 'wp_ajax_astra_theme_customizer_reset', array( $this, 'ajax_customizer_reset' ) );
			add_action( 'customize_controls_enqueue_scripts', array( $this, 'controls_scripts' ) );
			add_action( 'customize_register', array( $this, 'customize_register' ) );
		}

		/**
		 * Customize Register Description
		 *
		 * @param  object $wp_customize Object of WordPress customizer.
		 * @return void
		 * @since 1.0.0
		 */
		public function customize_register( $wp_customize ) {
			$this->wp_customize = $wp_customize;
		}

		/**
		 * AJAX Customizer Reset
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public function ajax_customizer_reset() {

			// Request come from a customizer preview?
			if ( ! $this->wp_customize->is_preview() ) {
				wp_send_json_error( 'fail' );
			}

			// Validate nonce.
			check_ajax_referer( 'astra-theme-customizer-reset', 'nonce' );

			// Reset option 'astra-settings'.
			if ( defined( 'ASTRA_THEME_SETTINGS' ) ) {

				$default_setting = array();
				if ( defined( 'ASTRA_THEME_VERSION' ) ) {
					$default_setting['theme-auto-version'] = ASTRA_THEME_VERSION;
				}
				if ( defined( 'ASTRA_EXT_VER' ) ) {
					$default_setting['astra-addon-auto-version'] = ASTRA_EXT_VER;
				}

				if ( ! empty( $default_setting ) ) {
					update_option( ASTRA_THEME_SETTINGS, $default_setting );
				} else {
					delete_option( ASTRA_THEME_SETTINGS );
				}

				// Delete Global Color Palette and Typography Preset options.
				delete_option( 'astra-typography-presets' );
				delete_option( 'astra-color-palettes' );

				// Delete Astra's 4.0.0 admin setting options.
				delete_option( 'astra_admin_settings' );
			}

			wp_send_json_error( 'pass' );

			wp_die();
		}

		/**
		 * Customizer Scripts
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public function controls_scripts() {

			$theme_name = apply_filters( 'astra_page_title', __( 'Astra', 'astra-customizer-reset' ) );

			// Enqueue JS.
			wp_enqueue_script( 'astra-theme-customizer-reset', ASTRA_THEME_CUSTOMIZER_RESET_URI . 'assets/js/customizer-reset.js', array( 'jquery', 'astra-customizer-controls-toggle-js' ), ASTRA_CUSTOMIZER_VERSION, true );

			// Add localize JS.
			wp_localize_script(
				'astra-theme-customizer-reset',
				'astraThemeCustomizerReset',
				apply_filters(
					'astra_theme_customizer_reset_js_localize',
					array(
						'customizer' => array(
							'reset' => array(
								'stringConfirm' => __( 'Warning! This will remove all the ' . esc_html( $theme_name ) . ' theme customizer settings!', 'astra-customizer-reset' ), // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
								'stringReset'   => __( 'Reset All', 'astra-customizer-reset' ),
								'nonce'         => wp_create_nonce( 'astra-theme-customizer-reset' ),
							),
						),
					)
				)
			);
		}
	}

endif;

/**
 * Kicking this off by calling 'get_instance()' method
 */
Astra_Theme_Customizer_Reset::get_instance();
