<?php
/**
 * Plugin Name: Astra Customizer Reset
 * Plugin URI: https://wpastra.com/
 * Description: Reset the Astra theme customizer options from customizer interface.
 * Version: 1.0.7
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
define( 'ASTRA_CUSTOMIZER_VERSION', '1.0.7' );
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
		 * ID of the Customizer setting used as the "reset pending" flag.
		 *
		 * @since x.x.x
		 * @var string
		 */
		const PENDING_SETTING = 'astra-customizer-reset-pending';

		/**
		 * Setting type of the flag. It has no storage of its own.
		 *
		 * @since x.x.x
		 * @var string
		 */
		const PENDING_SETTING_TYPE = 'astra_customizer_reset';

		/**
		 * Member Variable
		 *
		 * @since 1.0.0
		 * @var $instance
		 */
		private static $instance;

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

			add_action( 'customize_controls_enqueue_scripts', array( $this, 'controls_scripts' ) );
			add_action( 'customize_register', array( $this, 'customize_register' ) );
			add_action( 'customize_update_' . self::PENDING_SETTING_TYPE, array( $this, 'reset_on_publish' ) );
		}

		/**
		 * Register the "reset pending" flag.
		 *
		 * The flag is only a signal from the Reset All button. It is not stored.
		 * When it is published, the options that are not Customizer settings are reset.
		 *
		 * @param  WP_Customize_Manager $wp_customize Object of WordPress customizer.
		 * @return void
		 * @since 1.0.0
		 */
		public function customize_register( $wp_customize ) {
			$wp_customize->add_setting(
				self::PENDING_SETTING,
				array(
					'type'              => self::PENDING_SETTING_TYPE,
					'default'           => '',
					'transport'         => 'postMessage',
					'capability'        => 'edit_theme_options',
					'sanitize_callback' => array( $this, 'sanitize_pending' ),
				)
			);
		}

		/**
		 * Sanitize the "reset pending" flag.
		 *
		 * @param  mixed $value Value sent by the Customizer.
		 * @return string
		 * @since x.x.x
		 */
		public function sanitize_pending( $value ) {
			return '1' === (string) $value ? '1' : '';
		}

		/**
		 * Reset the options that are not Customizer settings.
		 *
		 * Runs when the user clicks Publish after using Reset All.
		 *
		 * @param  string $value Value of the "reset pending" flag.
		 * @return void
		 * @since x.x.x
		 */
		public function reset_on_publish( $value ) {

			if ( '1' !== $value || ! current_user_can( 'edit_theme_options' ) ) {
				return;
			}

			// Delete Astra's 4.0.0 admin setting options.
			delete_option( 'astra_admin_settings' );
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
			wp_enqueue_script( 'astra-theme-customizer-reset', ASTRA_THEME_CUSTOMIZER_RESET_URI . 'assets/js/customizer-reset.js', array( 'jquery', 'wp-a11y', 'customize-controls', 'astra-customizer-controls-toggle-js' ), ASTRA_CUSTOMIZER_VERSION, true );

			// Add localize JS.
			wp_localize_script(
				'astra-theme-customizer-reset',
				'astraThemeCustomizerReset',
				apply_filters(
					'astra_theme_customizer_reset_js_localize',
					array(
						'customizer' => array(
							'reset' => array(
								'stringConfirm' => __( 'This will reset all the ' . esc_html( $theme_name ) . ' theme customizer settings in the preview. Click Publish to save the reset, or leave the Customizer to cancel it.', 'astra-customizer-reset' ), // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
								'stringReset'   => __( 'Reset All', 'astra-customizer-reset' ),
								'stringNotice'  => __( 'Astra settings are reset in the preview only. Click Publish to save the reset, or close the Customizer to cancel it.', 'astra-customizer-reset' ),
								'defaults'      => class_exists( 'Astra_Theme_Options' ) ? Astra_Theme_Options::defaults() : array(),
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
