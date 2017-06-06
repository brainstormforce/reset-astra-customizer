<?php
/**
 * Plugin Name: Astra Customizer Reset
 */

define( 'ASTRA_THEME_CUSTOMIZER_RESET_URI', plugins_url( '/', __FILE__ ) );

/**
 * Astra_Theme_Customizer_Reset initial setup
 *
 * @since 1.0.0
 */
if( !class_exists('Astra_Theme_Customizer_Reset') ) {

	class Astra_Theme_Customizer_Reset {

		private static $instance;

		/**
		 *  Initiator
		 */
		public static function get_instance(){
			if ( ! isset( self::$instance ) ) {
				self::$instance = new self;
			}
			return self::$instance;
		}

		/**
		 *  Constructor
		 */
		public function __construct() {

			add_action( 'wp_ajax_astra_customizer_reset',  		array( $this, 'ajax_customizer_reset' ) );
			add_action( 'wp_ajax_nopriv_astra_customizer_reset',  array( $this, 'ajax_customizer_reset' ) );

			add_action( 'wp_ajax_astra_regenerate_assets', 		array( $this, 'regenerate_assets' ) );
			add_action( 'wp_ajax_nopriv_regenerate_assets', 	array( $this, 'regenerate_assets' ) );
			add_action( 'customize_controls_enqueue_scripts',   array( $this, 'controls_scripts' ) );
			
		}

		/**
		 * AJAX Customizer Reset
		 *
		 * @since 1.0
		 * @return void
		 */
		public function ajax_customizer_reset() {

			// Reset option 'ast-settings'.
			delete_option( ASTRA_THEME_SETTINGS );

			// // Reset option 'site_icon'.
			// delete_option( 'site_icon' );

			wp_die();
		}

		/**
		 * Generate assets
		 *
		 * @since 1.0
		 * @return void
		 */
		public function regenerate_assets() {

			// Update variables.
			if( class_exists( 'Astra_Theme_Options' ) ) {
				Astra_Theme_Options::refresh();
			}

			wp_die();
	
		}

		public function controls_scripts() {

			wp_enqueue_script( 'astra-theme-customizer-reset', ASTRA_THEME_CUSTOMIZER_RESET_URI . 'assets/js/customizer-reset.js', array( 'jquery', 'astra-customizer-controls-toggle-js' ), null, true );

			wp_localize_script( 'astra-theme-customizer-reset', 'astraCustomizer', apply_filters( 'astra_theme_customizer_reset_js_localize', array(
				'customizer' => array(
					'reset' => array(
						'stringConfirm' => __( 'Attention! This will remove all customizations ever made via customizer to this theme! This action is irreversible!', 'astra' ),
						'stringReset'   => __( 'Reset All', 'astra' ),
						'nonce'         => wp_create_nonce( 'ast-customizer-reset' ),
					),
				)
			) ) );
		}
	}
}

/**
*  Kicking this off by calling 'get_instance()' method
*/
$astra_theme_customizer_reset = Astra_Theme_Customizer_Reset::get_instance();
