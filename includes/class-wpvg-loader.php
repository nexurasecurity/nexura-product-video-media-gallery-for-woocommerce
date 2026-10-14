<?php
/**
 * Main Plugin Orchestrator / Loader
 *
 * @package Nexura_Product_Video_Media_Gallery_For_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPVG_Loader' ) ) {
	class WPVG_Loader {

		/**
		 * Singleton instance
		 *
		 * @var WPVG_Loader|null
		 */
		private static $instance = null;

		/**
		 * Get instance
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Constructor
		 */
		private function __construct() {
			$this->includes();
			$this->init_hooks();
		}

		/**
		 * Include required files
		 */
		private function includes() {
			require_once WPVG_PLUGIN_DIR . 'includes/wpvg-helpers.php';
			require_once WPVG_PLUGIN_DIR . 'includes/class-wpvg-freemius.php';

			// Premium-only bridge (present in Freemius Pro package; optional in free).
			if ( file_exists( WPVG_PLUGIN_DIR . 'includes/class-wpvg-3d-viewer.php' ) ) {
				require_once WPVG_PLUGIN_DIR . 'includes/class-wpvg-3d-viewer.php';
			}

			if ( is_admin() ) {
				// Multi-video metabox UI — Freemius strips this file from the free ZIP.
				$premium_videos = WPVG_PLUGIN_DIR . 'includes/admin/wpvg-premium-videos-tab.php';
				if ( is_readable( $premium_videos ) ) {
					require_once $premium_videos;
				}
				require_once WPVG_PLUGIN_DIR . 'includes/admin/class-wpvg-product-metabox.php';
				require_once WPVG_PLUGIN_DIR . 'includes/admin/class-wpvg-admin-settings.php';
			}

			require_once WPVG_PLUGIN_DIR . 'includes/frontend/class-wpvg-gallery.php';
			require_once WPVG_PLUGIN_DIR . 'includes/frontend/class-wpvg-shortcode.php';
			require_once WPVG_PLUGIN_DIR . 'includes/frontend/class-wpvg-page-builders.php';
		}

		/**
		 * Hook into WordPress & WooCommerce
		 */
		private function init_hooks() {
			WPVG_Freemius::init();

			add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
			add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );

			// Pro modules: Freemius premium package only + valid license.
			add_action( 'plugins_loaded', array( $this, 'load_pro_modules' ), 5 );

			if ( is_admin() ) {
				new WPVG_Product_Metabox();
				new WPVG_Admin_Settings();
			}

			WPVG_Gallery::instance();
			WPVG_Shortcode::instance();
			WPVG_Page_Builders::instance();
		}

		/**
		 * Load Freemius Pro modules from /pro.
		 *
		 * Wrapped in is__premium_only() so Freemius Deploy REMOVES this block
		 * (and @fs_premium_only /pro/) from the free WordPress.org ZIP.
		 * Buyers of Pro get the full premium ZIP with this code intact.
		 */
		public function load_pro_modules() {
			// Local free UI test: define( 'WPVG_FORCE_FREE', true ); in wp-config.php
			if ( defined( 'WPVG_FORCE_FREE' ) && WPVG_FORCE_FREE ) {
				return;
			}

			// Freemius preprocessor: entire premium-only block stripped from free ZIP.
			if ( ! function_exists( 'pvgfw_fs' ) || ! pvgfw_fs()->is__premium_only() ) {
				return;
			}

			// Premium ZIP: require active Freemius license / trial.
			$is_pro = function_exists( 'wpvg_can_use_pro' ) ? wpvg_can_use_pro() : false;
			$is_pro = (bool) apply_filters( 'wpvg_enable_pro_modules', $is_pro );

			if ( ! $is_pro ) {
				return;
			}

			// Load Pro Features Engine
			$pro_features_file = WPVG_PLUGIN_DIR . 'pro/class-wpvg-pro.php';
			if ( file_exists( $pro_features_file ) ) {
				require_once $pro_features_file;
				if ( class_exists( 'WPVG_Pro' ) ) {
					WPVG_Pro::instance();
				}
			}

			$pro_3d_file = WPVG_PLUGIN_DIR . 'pro/3d-product-model-viewer/3d-product-model-viewer.php';

			if ( file_exists( $pro_3d_file ) ) {
				require_once $pro_3d_file;
				do_action( 'wpvg_3d_product_model_viewer_loaded' );
			}

			do_action( 'wpvg_pro_modules_loaded' );
		}

		/**
		 * Check WooCommerce dependency
		 */
		public function on_plugins_loaded() {
			if ( ! class_exists( 'WooCommerce' ) ) {
				add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			}
		}

		/**
		 * Admin notice when WooCommerce is inactive
		 */
		public function woocommerce_missing_notice() {
			?>
			<div class="notice notice-error is-dismissible">
				<p>
					<strong><?php esc_html_e( 'Nexura Product Video Media Gallery for WooCommerce', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></strong>
					<?php esc_html_e( 'requires WooCommerce to be installed and active.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
				</p>
			</div>
			<?php
		}

		/**
		 * Declare WooCommerce HPOS Compatibility
		 */
		public function declare_hpos_compatibility() {
			if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', WPVG_PLUGIN_FILE, true );
				\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', WPVG_PLUGIN_FILE, true );
			}
		}

		/**
		 * Plugin Activation callback
		 */
		public static function activate() {
			if ( empty( get_option( 'wpvg_slider_layout' ) ) ) {
				update_option( 'wpvg_slider_layout', 'vertical_left' );
				update_option( 'wpvg_gallery_ratio', '4_5' );
				update_option( 'wpvg_thumbnails_to_show', 4 );
				update_option( 'wpvg_slider_autoplay', 'no' );
				update_option( 'wpvg_show_lightbox', 'yes' );
				update_option( 'wpvg_show_zoom', 'yes' );
				update_option( 'wpvg_zoom_type', 'inner' );
				update_option( 'wpvg_modal_pattern', 'mosaic_2_1' );
				update_option( 'wpvg_modal_position', 'right' );
				update_option( 'wpvg_arrow_color', '#1e293b' );
				update_option( 'wpvg_arrow_bg', '#ffffff' );
				update_option( 'wpvg_video_placement', 'second' );
				update_option( 'wpvg_mobile_layout', 'bottom_horizontal' );
				update_option( 'wpvg_video_schema', 'yes' );
			}
		}
	}
}
