<?php
/**
 * Reset in Preview: shows the reset in the preview and saves it on Publish.
 *
 * @package Astra Customizer Reset
 * @since 1.1.0
 */

if ( ! class_exists( 'Astra_Theme_Customizer_Reset_Preview' ) ) :

	/**
	 * Reset in Preview: shows the reset in the preview and saves it on Publish.
	 */
	class Astra_Theme_Customizer_Reset_Preview {

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
		 * AJAX action that returns the defaults of a reset site.
		 *
		 * @since 1.1.0
		 * @var string
		 */
		const DEFAULTS_ACTION = 'astra_customizer_reset_defaults';

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
		 * Constructor. Runs at plugin load, before the theme.
		 *
		 * @since 1.1.0
		 */
		public function __construct() {
			add_action( 'wp_ajax_' . self::DEFAULTS_ACTION, array( $this, 'ajax_reset_defaults' ) );

			// Astra's defaults depend on the saved settings, so read them as reset.
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Request detection only, the handler checks the nonce.
			if ( wp_doing_ajax() && isset( $_POST['action'] ) && self::DEFAULTS_ACTION === $_POST['action'] ) {
				add_filter( 'pre_option_' . Astra_Theme_Customizer_Reset::SETTINGS_OPTION, array( 'Astra_Theme_Customizer_Reset', 'settings_as_reset' ) );
				add_filter( 'pre_update_option_' . Astra_Theme_Customizer_Reset::SETTINGS_OPTION, array( $this, 'keep_stored_settings' ), 10, 2 );
			}

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

			Astra_Theme_Customizer_Reset::delete_admin_settings();
			$this->reset_published = true;
			$this->publish_stale   = $this->stale_settings( $value );
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
				$defaults = array_diff_key( Astra_Theme_Options::get_options(), array_flip( Astra_Theme_Customizer_Reset::kept_settings() ) );
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
			$this->preview_keep = array_flip( array_merge( Astra_Theme_Customizer_Reset::kept_settings(), $keep ) );

			add_filter( 'option_' . Astra_Theme_Customizer_Reset::SETTINGS_OPTION, array( $this, 'filter_preview_settings' ), 100 );
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

			$settings = get_option( Astra_Theme_Customizer_Reset::SETTINGS_OPTION, array() );
			$keep     = array_diff( $this->changed_settings( $manager ), $this->publish_stale );
			$cleaned  = $this->settings_after_reset( $settings, $keep );

			if ( $cleaned !== $settings ) {
				update_option( Astra_Theme_Customizer_Reset::SETTINGS_OPTION, $cleaned );
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

			return array_intersect_key( $settings, array_flip( array_merge( Astra_Theme_Customizer_Reset::kept_settings(), $changed ) ) );
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
			$pattern = '/^' . preg_quote( Astra_Theme_Customizer_Reset::SETTINGS_OPTION, '/' ) . '\\[([^\\]]+)\\]$/';
			return preg_match( $pattern, (string) $setting_id, $match ) ? $match[1] : null;
		}
	}

endif;
