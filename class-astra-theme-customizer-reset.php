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
		 * Astra settings option (ASTRA_THEME_SETTINGS is not defined yet at plugin load).
		 *
		 * @since 1.1.0
		 * @var string
		 */
		const SETTINGS_OPTION = 'astra-settings';

		/**
		 * AJAX action that returns the defaults of a reset site.
		 *
		 * @since 1.1.0
		 * @var string
		 */
		const DEFAULTS_ACTION = 'astra_customizer_reset_defaults';

		/**
		 * Astra settings a reset keeps.
		 *
		 * @since 1.1.0
		 * @var array
		 */
		private static $keep_settings = array( 'theme-auto-version', 'astra-addon-auto-version' );

		/**
		 * Whether a reset is published in this request.
		 *
		 * @since 1.1.0
		 * @var bool
		 */
		private $reset_published = false;

		/**
		 * Astra setting keys the preview of a pending reset keeps.
		 *
		 * @since 1.1.0
		 * @var array
		 */
		private $preview_keep = array();

		/**
		 * Keys without a default changed before the published reset.
		 *
		 * @since 1.1.0
		 * @var array
		 */
		private $publish_stale = array();

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
			add_action( 'wp_ajax_astra_customizer_reset_activate_import_export', array( $this, 'ajax_activate_import_export' ) );
			add_action( 'wp_ajax_' . self::DEFAULTS_ACTION, array( $this, 'ajax_reset_defaults' ) );

			// Astra's defaults depend on the saved settings, so read them as reset.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Request detection only, the handler checks the nonce.
			if ( wp_doing_ajax() && isset( $_POST['action'] ) && self::DEFAULTS_ACTION === $_POST['action'] ) {
				add_filter( 'pre_option_' . self::SETTINGS_OPTION, array( $this, 'settings_as_reset' ) );
				add_filter( 'pre_update_option_' . self::SETTINGS_OPTION, array( $this, 'keep_stored_settings' ), 10, 2 );
			}
			add_action( 'customize_controls_enqueue_scripts', array( $this, 'controls_scripts' ) );
			add_action( 'customize_register', array( $this, 'customize_register' ) );
			add_action( 'customize_update_' . self::PENDING_SETTING_TYPE, array( $this, 'reset_on_publish' ) );

			// Before Astra's own handlers at priority 10.
			add_action( 'customize_preview_init', array( $this, 'preview_pending_reset' ), 5 );
			add_action( 'customize_save_after', array( $this, 'publish_pending_reset' ), 5 );
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
					'transport'         => 'refresh',
					'capability'        => 'edit_theme_options',
					'sanitize_callback' => array( $this, 'sanitize_pending' ),
				)
			);
		}

		/**
		 * Sanitize the "reset pending" flag.
		 *
		 * Format: "{timestamp}" or "{timestamp}|{key},{key}". The timestamp makes
		 * each reset a new value. The keys are settings without a default changed
		 * before the reset, which the reset must remove.
		 *
		 * @param  mixed $value Value sent by the Customizer.
		 * @return string
		 * @since 1.1.0
		 */
		public function sanitize_pending( $value ) {
			$value = (string) $value;
			return preg_match( '/^\d{1,20}(\|[A-Za-z0-9_-]+(,[A-Za-z0-9_-]+)*)?$/', $value ) ? $value : '';
		}

		/**
		 * Setting keys listed in the "reset pending" flag.
		 *
		 * @since 1.1.0
		 * @param string $value Flag value.
		 * @return array
		 */
		private function stale_settings( $value ) {
			$parts = explode( '|', (string) $value, 2 );
			return isset( $parts[1] ) ? explode( ',', $parts[1] ) : array();
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
			$this->reset_published = true;
			$this->publish_stale   = $this->stale_settings( $value );
		}

		/**
		 * Astra settings as "Reset and Publish Now" leaves them.
		 *
		 * @since 1.1.0
		 * @return array
		 */
		public function settings_as_reset() {
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
		 * Block saving the Astra settings while they are read as reset.
		 *
		 * @since 1.1.0
		 * @param mixed $value     New value.
		 * @param mixed $old_value Current value.
		 * @return mixed
		 */
		public function keep_stored_settings( $value, $old_value ) {
			return $old_value;
		}

		/**
		 * AJAX: Astra defaults of a reset site, for "Reset in Preview".
		 *
		 * @since 1.1.0
		 * @return void
		 */
		public function ajax_reset_defaults() {

			check_ajax_referer( 'astra-theme-customizer-reset', 'nonce' );

			if ( ! current_user_can( 'edit_theme_options' ) ) {
				wp_send_json_error( null, 403 );
			}

			$defaults = array();

			if ( class_exists( 'Astra_Theme_Options' ) ) {
				// Computes the defaults in the site language, as Astra does.
				Astra_Theme_Options::refresh();
				$defaults = array_diff_key( Astra_Theme_Options::get_options(), array_flip( self::$keep_settings ) );
			}

			wp_send_json_success(
				array(
					'defaults'        => $defaults,
					'paletteDefaults' => class_exists( 'Astra_Global_Palette' ) ? Astra_Global_Palette::get_default_color_palette() : array(),
					'stale'           => $this->changeset_settings_without_default( $defaults ),
				)
			);
		}

		/**
		 * Astra settings without a default saved in this session's changeset or
		 * its autosave, that is, changed before the reset.
		 *
		 * @since 1.1.0
		 * @param array $defaults Astra defaults.
		 * @return array Setting keys.
		 */
		private function changeset_settings_without_default( $defaults ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked in ajax_reset_defaults().
			$uuid = isset( $_POST['changeset_uuid'] ) ? sanitize_text_field( wp_unslash( $_POST['changeset_uuid'] ) ) : '';

			if ( ! wp_is_uuid( $uuid ) ) {
				return array();
			}

			$post_ids = get_posts(
				array(
					'name'             => $uuid,
					'post_type'        => 'customize_changeset',
					'post_status'      => get_post_stati(),
					'posts_per_page'   => 1,
					'fields'           => 'ids',
					'no_found_rows'    => true,
					'suppress_filters' => false,
				)
			);

			if ( empty( $post_ids ) || ! current_user_can( get_post_type_object( 'customize_changeset' )->cap->edit_post, $post_ids[0] ) ) {
				return array();
			}

			$contents = array( get_post_field( 'post_content', $post_ids[0] ) );
			$autosave = wp_get_post_autosave( $post_ids[0], get_current_user_id() );

			if ( $autosave ) {
				$contents[] = $autosave->post_content;
			}

			$keys = array();

			foreach ( $contents as $content ) {
				$data = json_decode( $content, true );

				foreach ( is_array( $data ) ? array_keys( $data ) : array() as $setting_id ) {
					$key = $this->settings_key( $setting_id );

					if ( null !== $key && ! array_key_exists( $key, $defaults ) ) {
						$keys[] = $key;
					}
				}
			}

			return array_values( array_unique( $keys ) );
		}

		/**
		 * Preview a pending reset like "Reset and Publish Now".
		 *
		 * @since 1.1.0
		 * @param WP_Customize_Manager $manager Customizer manager.
		 * @return void
		 */
		public function preview_pending_reset( $manager ) {

			$setting = $manager->get_setting( self::PENDING_SETTING );

			if ( ! $setting || '' === (string) $setting->post_value( '' ) ) {
				return;
			}

			$keep               = array_diff( $this->changed_settings( $manager ), $this->stale_settings( $setting->post_value( '' ) ) );
			$this->preview_keep = array_flip( array_merge( self::$keep_settings, $keep ) );

			add_filter( 'option_' . self::SETTINGS_OPTION, array( $this, 'filter_preview_settings' ), 100 );
		}

		/**
		 * Filter the Astra settings in the preview of a pending reset.
		 *
		 * @since 1.1.0
		 * @param mixed $settings Astra settings.
		 * @return mixed
		 */
		public function filter_preview_settings( $settings ) {
			return is_array( $settings ) ? array_intersect_key( $settings, $this->preview_keep ) : $settings;
		}

		/**
		 * Remove the Astra settings the reset could not set, once it is published.
		 *
		 * @since 1.1.0
		 * @param WP_Customize_Manager $manager Customizer manager.
		 * @return void
		 */
		public function publish_pending_reset( $manager ) {

			if ( ! $this->reset_published ) {
				return;
			}

			$this->reset_published = false;

			$settings = get_option( self::SETTINGS_OPTION, array() );
			$keep     = array_diff( $this->changed_settings( $manager ), $this->publish_stale );
			$cleaned  = $this->settings_after_reset( $settings, $keep );

			if ( $cleaned !== $settings ) {
				update_option( self::SETTINGS_OPTION, $cleaned );
			}
		}

		/**
		 * Keep only the versions and the settings in the changeset.
		 *
		 * @since 1.1.0
		 * @param mixed $settings Astra settings.
		 * @param array $changed  Setting keys to keep.
		 * @return mixed
		 */
		private function settings_after_reset( $settings, $changed ) {

			if ( ! is_array( $settings ) ) {
				return $settings;
			}

			return array_intersect_key( $settings, array_flip( array_merge( self::$keep_settings, $changed ) ) );
		}

		/**
		 * Astra setting keys in the changeset.
		 *
		 * @since 1.1.0
		 * @param WP_Customize_Manager $manager Customizer manager.
		 * @return array
		 */
		private function changed_settings( $manager ) {
			$keys = array();

			foreach ( array_keys( $manager->unsanitized_post_values() ) as $setting_id ) {
				$key = $this->settings_key( $setting_id );

				if ( null !== $key ) {
					$keys[] = $key;
				}
			}

			return $keys;
		}

		/**
		 * Astra setting key of a Customizer setting ID, like "astra-settings[key]".
		 *
		 * @since 1.1.0
		 * @param string $setting_id Customizer setting ID.
		 * @return string|null Key, or null for other settings.
		 */
		private function settings_key( $setting_id ) {
			$pattern = '/^' . preg_quote( self::SETTINGS_OPTION, '/' ) . '\\[([^\\]]+)\\]$/';
			return preg_match( $pattern, (string) $setting_id, $match ) ? $match[1] : null;
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
								'defaultsAction'     => self::DEFAULTS_ACTION,
								'backup'             => $this->get_backup_nudge(),
							),
						),
					)
				)
			);
		}

		/**
		 * Activate the Import / Export Customizer Settings plugin from the Reset All menu.
		 *
		 * Sends back the new data for the menu row, so it can export right away.
		 *
		 * @since 1.1.0
		 * @return void
		 */
		public function ajax_activate_import_export() {

			check_ajax_referer( 'astra-customizer-reset-backup', 'nonce' );

			if ( ! current_user_can( 'activate_plugin', self::IMPORT_EXPORT_PLUGIN ) || ! $this->can_activate_on_site() ) {
				wp_send_json_error( null, 403 );
			}

			$result = activate_plugin( self::IMPORT_EXPORT_PLUGIN );

			if ( is_wp_error( $result ) ) {
				wp_send_json_error( null, 500 );
			}

			wp_send_json_success( $this->get_backup_nudge( true ) );
		}

		/**
		 * Whether the Import / Export plugin can be activated on this site.
		 *
		 * `activate_plugin()` does not check this itself. Core's Plugins screen
		 * only allows a network-only plugin to be activated in the network admin.
		 *
		 * @since 1.1.0
		 * @return bool
		 */
		private function can_activate_on_site() {
			return ! ( is_multisite() && ! is_network_admin() && is_network_only_plugin( self::IMPORT_EXPORT_PLUGIN ) );
		}

		/**
		 * Data for the "back up first" row in the Reset All menu.
		 *
		 * Uses the Import / Export Customizer Settings plugin:
		 * - Active: the row downloads the export file right there.
		 * - Installed but inactive: the row activates it, then exports.
		 * - Not installed: the row installs and activates it, then exports.
		 * When WordPress needs FTP details to install plugins, the row links to the
		 * plugin install screen in a new tab instead.
		 *
		 * @since 1.1.0
		 * @param bool|null $is_active Whether the plugin is active. Null to check.
		 * @return array|null Row data, or null when the user can not act on it.
		 */
		public function get_backup_nudge( $is_active = null ) {

			$plugin_file = self::IMPORT_EXPORT_PLUGIN;
			$plugin_name = __( 'Import / Export Customizer Settings', 'astra-customizer-reset' );
			$theme_name  = apply_filters( 'astra_page_title', __( 'Astra', 'astra-customizer-reset' ) );
			$nudge       = array(
				'label' => __( 'Back Up Settings First', 'astra-customizer-reset' ),
			);

			// Every state ends with an export, which needs the same capability as
			// the export in the Import / Export plugin.
			if ( ! current_user_can( 'manage_options' ) ) {
				return null;
			}

			if ( null === $is_active ) {
				$is_active = class_exists( 'Astra_Import_Export_Loader' );
			}

			// Active: download the export file from the menu.
			if ( $is_active ) {

				$page_slug    = is_callable( 'Astra_Menu::get_theme_page_slug' ) ? Astra_Menu::get_theme_page_slug() : apply_filters( 'astra_theme_page_slug', 'astra' );
				$settings_url = add_query_arg(
					array(
						'page' => rawurlencode( $page_slug ),
						'path' => 'settings',
					),
					admin_url( 'admin.php' )
				);
				/* translators: %s: Theme name. */
				$settings_link = '<a href="' . esc_url( $settings_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( sprintf( __( '%s > Settings', 'astra-customizer-reset' ), $theme_name ) ) . '</a>';

				return array(
					'action'  => 'export',
					'label'   => __( 'Export Settings First', 'astra-customizer-reset' ),
					/* translators: %s: Theme name. */
					'help'    => sprintf( __( 'Downloads your published settings as a file. To restore them, import the file in %s > Settings. The site logo is not included.', 'astra-customizer-reset' ), $theme_name ),
					'busy'    => __( 'Exporting…', 'astra-customizer-reset' ),
					// The export is handled by the Import / Export plugin on `admin_init`.
					'postUrl' => esc_url_raw( admin_url( 'admin-post.php' ) ),
					'nonce'   => wp_create_nonce( 'astra_export_nonce' ),
					/* translators: %s: Link to the theme settings page. */
					'done'    => sprintf( esc_html__( 'Settings exported. To restore them later, import the file in %s.', 'astra-customizer-reset' ), $settings_link ),
					/* translators: %s: Link to the theme settings page. */
					'error'   => sprintf( esc_html__( 'The settings could not be exported. Please export them from %s.', 'astra-customizer-reset' ), $settings_link ),
				);
			}

			$plugins_link = '<a href="' . esc_url( self_admin_url( 'plugins.php' ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Plugins', 'astra-customizer-reset' ) . '</a>';

			// Installed but inactive: activate it from the menu.
			if ( file_exists( WP_PLUGIN_DIR . '/' . $plugin_file ) ) {

				if ( ! current_user_can( 'activate_plugin', $plugin_file ) || ! $this->can_activate_on_site() ) {
					return null;
				}

				$nudge['action'] = 'activate';
				/* translators: %s: Plugin name. */
				$nudge['help'] = sprintf( __( 'Activates the %s plugin, then downloads your published settings as a file.', 'astra-customizer-reset' ), $plugin_name );
				$nudge['busy'] = __( 'Activating…', 'astra-customizer-reset' );
				/* translators: %s: Plugin name. */
				$nudge['confirm'] = sprintf( __( 'This will activate the %s plugin on your site, then download your settings. Continue?', 'astra-customizer-reset' ), $plugin_name );
				$nudge['nonce']   = wp_create_nonce( 'astra-customizer-reset-backup' );
				/* translators: %s: Link to the Plugins screen. */
				$nudge['error'] = sprintf( esc_html__( 'The Import / Export plugin could not be activated. Please activate it from %s.', 'astra-customizer-reset' ), $plugins_link );

				return $nudge;
			}

			// Not installed. Installing also activates, so both are needed.
			if ( ! current_user_can( 'install_plugins' ) || ! current_user_can( 'activate_plugins' ) ) {
				return null;
			}

			$install_url = esc_url_raw(
				add_query_arg(
					array(
						'tab'    => 'plugin-information',
						'plugin' => dirname( $plugin_file ),
					),
					// On multisite, plugins are installed in the network admin. The
					// subsite screen redirects there and drops the plugin details.
					is_multisite() ? network_admin_url( 'plugin-install.php' ) : admin_url( 'plugin-install.php' )
				)
			);

			if ( ! function_exists( 'get_filesystem_method' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
			}

			// WordPress needs FTP details to install plugins: link to the install screen.
			if ( 'direct' !== get_filesystem_method( array(), WP_PLUGIN_DIR ) ) {
				$nudge['action'] = 'link';
				/* translators: %s: Plugin name. */
				$nudge['help']   = sprintf( __( 'Install %s to export your Customizer settings and import them later.', 'astra-customizer-reset' ), $plugin_name );
				$nudge['url']    = $install_url;
				$nudge['newTab'] = __( '(opens in a new tab)', 'astra-customizer-reset' );

				return $nudge;
			}

			$nudge['action'] = 'install';
			/* translators: %s: Plugin name. */
			$nudge['help'] = sprintf( __( 'Installs and activates the free %s plugin, then downloads your published settings as a file.', 'astra-customizer-reset' ), $plugin_name );
			$nudge['busy'] = __( 'Installing…', 'astra-customizer-reset' );
			/* translators: %s: Plugin name. */
			$nudge['confirm']      = sprintf( __( 'This will install the free %s plugin from WordPress.org, activate it on your site, then download your settings. Continue?', 'astra-customizer-reset' ), $plugin_name );
			$nudge['busyNext']     = __( 'Activating…', 'astra-customizer-reset' );
			$nudge['slug']         = dirname( $plugin_file );
			$nudge['installNonce'] = wp_create_nonce( 'updates' );
			$nudge['nonce']        = wp_create_nonce( 'astra-customizer-reset-backup' );
			/* translators: %s: Link to the plugin install screen. */
			$nudge['error'] = sprintf( esc_html__( 'The Import / Export plugin could not be installed. Please install it from %s.', 'astra-customizer-reset' ), '<a href="' . esc_url( $install_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Plugins', 'astra-customizer-reset' ) . '</a>' );

			return $nudge;
		}
	}

endif;

/**
 * Kicking this off by calling 'get_instance()' method
 */
Astra_Theme_Customizer_Reset::get_instance();
