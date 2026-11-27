<?php
/**
 * Reset and Publish Now: removes the Astra settings right away.
 *
 * @package Astra Customizer Reset
 * @since 1.1.0
 */

if ( ! class_exists( 'Astra_Theme_Customizer_Reset_Publish_Now' ) ) :

	/**
	 * Reset and Publish Now: removes the Astra settings right away.
	 */
	class Astra_Theme_Customizer_Reset_Publish_Now {

		/**
		 * WordPress Customizer Object
		 *
		 * @since 1.0.0
		 * @var $wp_customize
		 */
		private $wp_customize;

		/**
		 * Instance.
		 *
		 * @since 1.1.0
		 * @var self
		 */
		private static $instance;

		/**
		 * Initiator.
		 *
		 * @since 1.1.0
		 * @return self
		 */
		public static function get_instance() {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Constructor.
		 *
		 * @since 1.1.0
		 */
		public function __construct() {
			add_action( 'wp_ajax_astra_theme_customizer_reset', array( $this, 'ajax_customizer_reset' ) );
			add_action( 'customize_register', array( $this, 'customize_register' ) );
		}

		/**
		 * Keep the Customizer manager, to check the request is a preview.
		 *
		 * @since 1.1.0
		 * @param WP_Customize_Manager $wp_customize Customizer manager.
		 * @return void
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

			// Validate nonce first.
			check_ajax_referer( 'astra-theme-customizer-reset', 'nonce' );

			if ( ! current_user_can( 'edit_theme_options' ) ) {
				wp_send_json_error( 'fail', 403 );
			}

			// Request come from a customizer preview? Without `wp_customize=on`
			// the Customizer is not loaded and the property is not set.
			if ( ! $this->wp_customize instanceof WP_Customize_Manager || ! $this->wp_customize->is_preview() ) {
				wp_send_json_error( 'fail', 400 );
			}

			// Reset option 'astra-settings'.
			if ( defined( 'ASTRA_THEME_SETTINGS' ) ) {

				$default_setting = Astra_Theme_Customizer_Reset::settings_as_reset();

				if ( ! empty( $default_setting ) ) {
					update_option( ASTRA_THEME_SETTINGS, $default_setting );
				} else {
					delete_option( ASTRA_THEME_SETTINGS );
				}

				// Delete Global Color Palette and Typography Preset options.
				delete_option( 'astra-typography-presets' );
				delete_option( 'astra-color-palettes' );

				// Delete Astra's 4.0.0 admin setting options.
				Astra_Theme_Customizer_Reset::delete_admin_settings();

				// Reset the site logo (stored as a theme mod, not in Astra settings).
				remove_theme_mod( 'custom_logo' );

				$this->refresh_astra_caches();
			}

			// 'pass' is kept as the data for scripts that check it.
			wp_send_json_success( 'pass' );
		}

		/**
		 * Run Astra's refresh after the options are written directly.
		 *
		 * "Reset and Publish Now" does not save through the Customizer, so
		 * `customize_save_after` never fires. Call the same Astra routines it
		 * runs: refresh the options cache, regenerate logo sizes, fire
		 * `astra_customizer_save`, and clear the cached partials config.
		 *
		 * @since 1.1.0
		 * @return void
		 */
		private function refresh_astra_caches() {
			if ( ! class_exists( 'Astra_Customizer' ) || ! is_callable( array( 'Astra_Customizer', 'get_instance' ) ) ) {
				return;
			}

			$astra_customizer = Astra_Customizer::get_instance();

			if ( is_callable( array( $astra_customizer, 'customize_save' ) ) ) {
				$astra_customizer->customize_save();
			}

			if ( is_callable( array( $astra_customizer, 'delete_cached_partials' ) ) ) {
				$astra_customizer->delete_cached_partials();
			}
		}
	}

endif;
