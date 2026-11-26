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
		 * ID of the Customizer setting that marks a reset made in the preview.
		 *
		 * @since 1.1.0
		 * @var string
		 */
		const PENDING_SETTING = 'astra-customizer-reset-pending';

		/**
		 * Setting type of the flag. It has no storage of its own.
		 *
		 * @since 1.1.0
		 * @var string
		 */
		const PENDING_SETTING_TYPE = 'astra_customizer_reset';

		/**
		 * Plugin file of Import / Export Customizer Settings, used for the backup nudge.
		 *
		 * @since 1.1.0
		 * @var string
		 */
		const IMPORT_EXPORT_PLUGIN = 'astra-import-export/astra-import-export.php';

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
			add_action( 'customize_update_' . self::PENDING_SETTING_TYPE, array( $this, 'reset_on_publish' ) );
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

			// Flag set by "Reset in Preview". It is not stored; it only tells us to
			// reset the options that are not Customizer settings when the user publishes.
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
		 * The flag holds a timestamp, so each reset is a new value and is always
		 * sent to the server, even after an earlier reset in the same session.
		 *
		 * @param  mixed $value Value sent by the Customizer.
		 * @return string
		 * @since 1.1.0
		 */
		public function sanitize_pending( $value ) {
			$value = (string) $value;
			return preg_match( '/^\d{1,20}$/', $value ) ? $value : '';
		}

		/**
		 * Reset the options that are not Customizer settings.
		 *
		 * Runs when the user publishes after "Reset in Preview".
		 *
		 * @param  string $value Value of the "reset pending" flag.
		 * @return void
		 * @since 1.1.0
		 */
		public function reset_on_publish( $value ) {

			if ( '' === (string) $value || ! current_user_can( 'edit_theme_options' ) ) {
				return;
			}

			$this->delete_admin_settings();
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
		private function delete_admin_settings() {
			if ( current_user_can( 'manage_options' ) ) {
				delete_option( 'astra_admin_settings' );
			}
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
				$this->delete_admin_settings();

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
								'defaults'           => class_exists( 'Astra_Theme_Options' ) ? Astra_Theme_Options::defaults() : array(),
								'paletteDefaults'    => class_exists( 'Astra_Global_Palette' ) ? Astra_Global_Palette::get_default_color_palette() : array(),
								'backup'             => $this->get_backup_nudge(),
							),
						),
					)
				)
			);
		}

		/**
		 * Data for the "back up first" link in the Reset All menu.
		 *
		 * Points to the Import / Export Customizer Settings plugin. When it is active,
		 * the link opens Astra > Settings, where the user exports the settings and
		 * also sees where to import them later. Otherwise it links to activate or
		 * install the plugin. The link always opens in a new tab.
		 *
		 * @since 1.1.0
		 * @return array|null Link data, or null when the user can not act on it.
		 */
		public function get_backup_nudge() {

			$plugin_file = self::IMPORT_EXPORT_PLUGIN;
			$plugin_name = __( 'Import / Export Customizer Settings', 'astra-customizer-reset' );
			$theme_name  = apply_filters( 'astra_page_title', __( 'Astra', 'astra-customizer-reset' ) );
			$nudge       = array(
				'label'  => __( 'Back Up Settings First', 'astra-customizer-reset' ),
				'newTab' => __( '(opens in a new tab)', 'astra-customizer-reset' ),
			);

			// Active: open the Astra dashboard settings, where Export and Import are.
			if ( class_exists( 'Astra_Import_Export_Loader' ) ) {

				// Same capability as the export in the Import / Export plugin.
				if ( ! current_user_can( 'manage_options' ) ) {
					return null;
				}

				$page_slug = is_callable( 'Astra_Menu::get_theme_page_slug' ) ? Astra_Menu::get_theme_page_slug() : apply_filters( 'astra_theme_page_slug', 'astra' );

				$nudge['label'] = __( 'Export Settings First', 'astra-customizer-reset' );
				/* translators: %s: Theme name. */
				$nudge['help'] = sprintf( __( 'Export your Customizer settings from %s > Settings. You can import them there later. The site logo is not included.', 'astra-customizer-reset' ), $theme_name );
				$nudge['url']  = esc_url_raw(
					add_query_arg(
						array(
							'page' => rawurlencode( $page_slug ),
							'path' => 'settings',
						),
						admin_url( 'admin.php' )
					)
				);

				return $nudge;
			}

			// Installed but inactive: link to activate it.
			if ( file_exists( WP_PLUGIN_DIR . '/' . $plugin_file ) ) {

				if ( ! current_user_can( 'activate_plugin', $plugin_file ) ) {
					return null;
				}

				/* translators: %s: Plugin name. */
				$nudge['help'] = sprintf( __( 'Activate %s to export your Customizer settings and import them later.', 'astra-customizer-reset' ), $plugin_name );
				// Not wp_nonce_url(): it escapes "&" to "&amp;", which breaks the URL in JS.
				$nudge['url'] = esc_url_raw(
					add_query_arg(
						array(
							'action'   => 'activate',
							'plugin'   => rawurlencode( $plugin_file ),
							'_wpnonce' => wp_create_nonce( 'activate-plugin_' . $plugin_file ),
						),
						self_admin_url( 'plugins.php' )
					)
				);

				return $nudge;
			}

			// Not installed: link to its install screen.
			if ( ! current_user_can( 'install_plugins' ) ) {
				return null;
			}

			/* translators: %s: Plugin name. */
			$nudge['help'] = sprintf( __( 'Install %s to export your Customizer settings and import them later.', 'astra-customizer-reset' ), $plugin_name );
			$nudge['url']  = esc_url_raw(
				add_query_arg(
					array(
						'tab'    => 'plugin-information',
						'plugin' => dirname( $plugin_file ),
					),
					self_admin_url( 'plugin-install.php' )
				)
			);

			return $nudge;
		}
	}

endif;

/**
 * Kicking this off by calling 'get_instance()' method
 */
Astra_Theme_Customizer_Reset::get_instance();
