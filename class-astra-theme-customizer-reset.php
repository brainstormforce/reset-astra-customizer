<?php
/**
 * Plugin Name: Astra Customizer Reset
 * Plugin URI: https://wpastra.com/
 * Description: Reset the Astra theme customizer options from customizer interface.
 * Version: 1.1.0
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

			// Delete Astra's 4.0.0 admin setting options.
			delete_option( 'astra_admin_settings' );
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

				// Reset the site logo (stored as a theme mod, not in Astra settings).
				remove_theme_mod( 'custom_logo' );
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

			// Enqueue CSS and JS.
			wp_enqueue_style( 'astra-theme-customizer-reset', ASTRA_THEME_CUSTOMIZER_RESET_URI . 'assets/css/customizer-reset.css', array( 'dashicons' ), ASTRA_CUSTOMIZER_VERSION );
			wp_enqueue_script( 'astra-theme-customizer-reset', ASTRA_THEME_CUSTOMIZER_RESET_URI . 'assets/js/customizer-reset.js', array( 'wp-a11y', 'customize-controls', 'astra-customizer-controls-toggle-js' ), ASTRA_CUSTOMIZER_VERSION, true );

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
								'stringConfirm'     => sprintf( __( 'All %s theme customizer settings will be removed and saved right away. Visitors will see the default settings at once. This cannot be undone. Continue?', 'astra-customizer-reset' ), esc_html( $theme_name ) ),
								'stringReset'       => __( 'Reset All', 'astra-customizer-reset' ),
								'stringMenuLabel'   => __( 'Reset options', 'astra-customizer-reset' ),
								'stringPreview'     => __( 'Reset in Preview', 'astra-customizer-reset' ),
								'stringPreviewHelp' => __( 'See the default settings first. Click Publish to save them, or close or reload the Customizer to cancel.', 'astra-customizer-reset' ),
								'stringPublish'     => __( 'Reset and Publish Now', 'astra-customizer-reset' ),
								'stringPublishHelp' => __( 'Removes all settings and saves right away. Visitors see the default settings at once.', 'astra-customizer-reset' ),
								/* translators: %s: Theme name. */
								'stringNotice'      => sprintf( __( '%s settings are reset in the preview only. Click Publish to save the reset, or close or reload the Customizer to cancel it.', 'astra-customizer-reset' ), esc_html( $theme_name ) ),
								'nonce'             => wp_create_nonce( 'astra-theme-customizer-reset' ),
								'defaults'          => class_exists( 'Astra_Theme_Options' ) ? Astra_Theme_Options::defaults() : array(),
								'paletteDefaults'   => class_exists( 'Astra_Global_Palette' ) ? Astra_Global_Palette::get_default_color_palette() : array(),
								'backup'            => $this->get_backup_nudge(),
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
				$nudge['help'] = sprintf( __( 'Export a backup from %s > Settings. You can import it on the same screen later to undo a reset.', 'astra-customizer-reset' ), esc_html( $theme_name ) );
				$nudge['url']  = add_query_arg(
					array(
						'page' => $page_slug,
						'path' => 'settings',
					),
					admin_url( 'admin.php' )
				);

				return $nudge;
			}

			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}

			// Installed but inactive: link to activate it.
			if ( array_key_exists( $plugin_file, get_plugins() ) ) {

				if ( ! current_user_can( 'activate_plugin', $plugin_file ) ) {
					return null;
				}

				/* translators: %s: Plugin name. */
				$nudge['help'] = sprintf( __( 'Activate %s to export a backup you can import later.', 'astra-customizer-reset' ), $plugin_name );
				// Not wp_nonce_url(): it escapes "&" to "&amp;", which breaks the URL in JS.
				$nudge['url'] = add_query_arg(
					array(
						'action'   => 'activate',
						'plugin'   => rawurlencode( $plugin_file ),
						'_wpnonce' => wp_create_nonce( 'activate-plugin_' . $plugin_file ),
					),
					self_admin_url( 'plugins.php' )
				);

				return $nudge;
			}

			// Not installed: link to its install screen.
			if ( ! current_user_can( 'install_plugins' ) ) {
				return null;
			}

			/* translators: %s: Plugin name. */
			$nudge['help'] = sprintf( __( 'Install %s to export a backup you can import later.', 'astra-customizer-reset' ), $plugin_name );
			$nudge['url']  = add_query_arg(
				array(
					'tab'    => 'plugin-information',
					'plugin' => dirname( $plugin_file ),
				),
				self_admin_url( 'plugin-install.php' )
			);

			return $nudge;
		}
	}

endif;

/**
 * Kicking this off by calling 'get_instance()' method
 */
Astra_Theme_Customizer_Reset::get_instance();
