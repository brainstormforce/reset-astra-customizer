<?php
/**
 * Backup row of the Reset All menu: export with the Import / Export plugin.
 *
 * @package Astra Customizer Reset
 * @since 1.1.0
 */

if ( ! class_exists( 'Astra_Theme_Customizer_Reset_Backup' ) ) :

	/**
	 * Backup row of the Reset All menu: export with the Import / Export plugin.
	 */
	class Astra_Theme_Customizer_Reset_Backup {

		/**
		 * Plugin file of Import / Export Customizer Settings, used for the backup nudge.
		 *
		 * @since 1.1.0
		 * @var string
		 */
		const IMPORT_EXPORT_PLUGIN = 'astra-import-export/astra-import-export.php';

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
			add_action( 'wp_ajax_astra_customizer_reset_activate_import_export', array( $this, 'ajax_activate_import_export' ) );
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
