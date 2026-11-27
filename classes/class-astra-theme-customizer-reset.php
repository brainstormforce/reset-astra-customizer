<?php
/**
 * Astra Customizer Reset: setup, assets and shared helpers.
 *
 * @package Astra Customizer Reset
 * @since 1.1.0
 */

if ( ! class_exists( 'Astra_Theme_Customizer_Reset' ) ) :

	/**
	 * Astra Customizer Reset: setup, assets and shared helpers.
	 */
	class Astra_Theme_Customizer_Reset {

		/**
		 * Astra settings option (ASTRA_THEME_SETTINGS is not defined yet at plugin load).
		 *
		 * @since 1.1.0
		 * @var string
		 */
		const SETTINGS_OPTION = 'astra-settings';

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
			Astra_Theme_Customizer_Reset_Publish_Now::get_instance();
			Astra_Theme_Customizer_Reset_Preview::get_instance();
			Astra_Theme_Customizer_Reset_Backup::get_instance();

			add_action( 'customize_controls_enqueue_scripts', array( $this, 'controls_scripts' ) );
		}

		/**
		 * Customizer Scripts
		 *
		 * @since 1.0.0
		 * @return void
		 */
		public function controls_scripts() {

			$theme_name = apply_filters( 'astra_page_title', __( 'Astra', 'astra-customizer-reset' ) );

			// Load the unminified files when SCRIPT_DEBUG is on, like the Astra theme.
			// Minified files are built with `npm run build` (grunt minify).
			$dir_name    = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? 'unminified' : 'minified';
			$file_prefix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
			$rtl_suffix  = is_rtl() ? '-rtl' : '';
			$assets_uri  = ASTRA_THEME_CUSTOMIZER_RESET_URI . 'assets/';

			// Enqueue CSS and JS.
			wp_enqueue_style( 'astra-theme-customizer-reset', $assets_uri . 'css/' . $dir_name . '/customizer-reset' . $file_prefix . $rtl_suffix . '.css', array( 'dashicons' ), ASTRA_CUSTOMIZER_VERSION );
			wp_enqueue_script( 'astra-theme-customizer-reset', $assets_uri . 'js/' . $dir_name . '/customizer-reset' . $file_prefix . '.js', array( 'wp-a11y', 'customize-controls', 'astra-customizer-controls-toggle-js' ), ASTRA_CUSTOMIZER_VERSION, true );

			// Add localize JS.
			wp_localize_script(
				'astra-theme-customizer-reset',
				'astraThemeCustomizerReset',
				apply_filters(
					'astra_theme_customizer_reset_js_localize',
					array(
						'customizer' => array(
							'reset' => array(
								/* translators: %s: Theme name. */
								'stringConfirm'      => sprintf( __( 'All %s theme customizer settings will be removed and saved right away. Visitors will see the default settings at once. This cannot be undone. Continue?', 'astra-customizer-reset' ), $theme_name ),
								'stringConfirmSaved' => __( 'This Customizer also has saved changes that are not published yet (a draft or a scheduled change). They will load again after the reset and will be applied when they are published.', 'astra-customizer-reset' ),
								'stringReset'        => __( 'Reset All', 'astra-customizer-reset' ),
								'stringMenuLabel'    => __( 'Reset options', 'astra-customizer-reset' ),
								'stringPreview'      => __( 'Reset in Preview', 'astra-customizer-reset' ),
								'stringPreviewHelp'  => __( 'See the default settings first. Click Publish to save them, or close or reload the Customizer to cancel.', 'astra-customizer-reset' ),
								'stringPublish'      => __( 'Reset and Publish Now', 'astra-customizer-reset' ),
								'stringPublishHelp'  => __( 'Removes all settings and saves right away. Visitors see the default settings at once.', 'astra-customizer-reset' ),
								/* translators: %s: Theme name. */
								'stringNotice'       => sprintf( esc_html__( '%s settings are reset in the preview only. Click Publish to save the reset, or close or reload the Customizer to cancel it.', 'astra-customizer-reset' ), esc_html( $theme_name ) ),
								'stringError'        => esc_html__( 'The settings could not be reset. Please reload the page and try again.', 'astra-customizer-reset' ),
								'nonce'              => wp_create_nonce( 'astra-theme-customizer-reset' ),
								'defaultsAction'     => Astra_Theme_Customizer_Reset_Preview::DEFAULTS_ACTION,
								'backup'             => Astra_Theme_Customizer_Reset_Backup::get_instance()->get_backup_nudge(),
							),
						),
					)
				)
			);
		}

		/**
		 * Astra settings keys a reset keeps.
		 *
		 * @since 1.1.0
		 * @return array
		 */
		public static function kept_settings() {
			return array( 'theme-auto-version', 'astra-addon-auto-version' );
		}

		/**
		 * Astra settings as "Reset and Publish Now" leaves them.
		 *
		 * @since 1.1.0
		 * @return array
		 */
		public static function settings_as_reset() {
			$settings = array();

			if ( defined( 'ASTRA_THEME_VERSION' ) ) {
				$settings['theme-auto-version'] = ASTRA_THEME_VERSION;
			}
			if ( defined( 'ASTRA_EXT_VER' ) ) {
				$settings['astra-addon-auto-version'] = ASTRA_EXT_VER;
			}

			return $settings;
		}

		/**
		 * Delete Astra's admin settings (added in Astra 4.0.0).
		 *
		 * These belong to the Astra dashboard, which needs `manage_options`, so a
		 * user who can only edit the theme options resets the look but keeps them.
		 *
		 * @since 1.1.0
		 * @return void
		 */
		public static function delete_admin_settings() {
			if ( current_user_can( 'manage_options' ) ) {
				delete_option( 'astra_admin_settings' );
			}
		}
	}

endif;
