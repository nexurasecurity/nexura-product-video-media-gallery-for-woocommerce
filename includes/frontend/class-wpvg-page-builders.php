<?php
/**
 * Nexura Product Video Media Gallery for WooCommerce - Page Builders Compatibility Layer
 * Provides seamless integration with:
 * - Elementor & Elementor Pro (Theme Builder single product templates & Live Preview)
 * - Divi Builder (Woo Product Gallery Module & Visual Builder)
 * - WPBakery Page Builder (Frontend Editor & Shortcodes)
 * - Bricks Builder (Single Product Template & Canvas)
 * - Oxygen Builder
 * - Beaver Builder
 * - Gutenberg / Full Site Editing (FSE) & WooCommerce Blocks
 *
 * @package Nexura_Product_Video_Media_Gallery_For_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPVG_Page_Builders' ) ) {
	class WPVG_Page_Builders {

		/**
		 * Singleton instance
		 *
		 * @var WPVG_Page_Builders|null
		 */
		private static $instance = null;

		/**
		 * Get instance
		 *
		 * @return WPVG_Page_Builders
		 */
		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Constructor
		 */
		private function __construct() {
			// Hook into script enqueue for builder live preview detection
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_in_builder_previews' ), 20 );

			// Elementor specific hooks
			add_action( 'elementor/editor/after_enqueue_scripts', array( $this, 'enqueue_builder_assets' ) );
			add_action( 'elementor/preview/enqueue_styles', array( $this, 'enqueue_builder_assets' ) );

			// Divi specific hooks
			add_action( 'et_builder_ready', array( $this, 'on_divi_ready' ) );

			// Register block filter only on product / builder views (avoids scanning every page).
			add_action( 'wp', array( $this, 'maybe_register_block_filter' ) );

			// Action hook fallback for builders that bypass template overrides
			add_action( 'init', array( $this, 'setup_action_fallbacks' ) );
		}

		/**
		 * Attach render_block only where Woo product gallery blocks can appear.
		 */
		public function maybe_register_block_filter() {
			if ( is_admin() && ! wp_doing_ajax() ) {
				return;
			}
			$need = ( function_exists( 'is_product' ) && is_product() )
				|| is_singular( 'product' )
				|| $this->is_any_builder_active();
			if ( $need ) {
				add_filter( 'render_block', array( $this, 'filter_woocommerce_blocks' ), 10, 2 );
			}
		}

		/**
		 * Check if current request is inside ANY page builder editor or preview
		 *
		 * @return bool
		 */
		public function is_any_builder_active() {
			// Elementor
			if ( did_action( 'elementor/loaded' ) ) {
				if ( ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->editor ) && \Elementor\Plugin::$instance->editor->is_edit_mode() ) ||
					( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->preview ) && \Elementor\Plugin::$instance->preview->is_preview_mode() ) ) {
					return true;
				}
			}

			// Divi Visual Builder
			if ( function_exists( 'et_core_is_fb_enabled' ) && et_core_is_fb_enabled() ) {
				return true;
			}
			if ( isset( $_GET['et_fb'] ) && '1' === $_GET['et_fb'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return true;
			}

			// WPBakery Page Builder (Visual Composer)
			if ( function_exists( 'vc_is_inline' ) && vc_is_inline() ) {
				return true;
			}

			// Bricks Builder
			if ( function_exists( 'bricks_is_builder_main' ) && bricks_is_builder_main() ) {
				return true;
			}

			// Oxygen Builder
			if ( defined( 'SHOW_CT_BUILDER' ) && SHOW_CT_BUILDER ) {
				return true;
			}

			// Beaver Builder
			if ( class_exists( 'FLBuilderModel' ) && method_exists( 'FLBuilderModel', 'is_builder_active' ) && FLBuilderModel::is_builder_active() ) {
				return true;
			}

			// Gutenberg Block Editor in admin or iframe
			if ( is_admin() && function_exists( 'get_current_screen' ) ) {
				$screen = get_current_screen();
				if ( $screen && $screen->is_block_editor() ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Force enqueue frontend assets when inside any page builder preview
		 */
		public function enqueue_in_builder_previews() {
			if ( $this->is_any_builder_active() ) {
				$this->enqueue_builder_assets();
			}
		}

		/**
		 * Safely load frontend assets inside builders
		 */
		public function enqueue_builder_assets() {
			if ( class_exists( 'WPVG_Gallery' ) ) {
				WPVG_Gallery::instance()->enqueue_frontend_assets( true );
			}
		}

		/**
		 * Divi Theme Builder compatibility — assets only inside Visual Builder / preview.
		 */
		public function on_divi_ready() {
			if ( $this->is_any_builder_active() ) {
				$this->enqueue_builder_assets();
			}
		}

		/**
		 * Gutenberg & WooCommerce Blocks (FSE themes like Twenty Twenty-Four)
		 * Intercepts WooCommerce core Product Image/Gallery blocks if present
		 *
		 * @param string $block_content Rendered block HTML.
		 * @param array  $block         Parsed block array.
		 * @return string
		 */
		public function filter_woocommerce_blocks( $block_content, $block ) {
			if ( empty( $block['blockName'] ) ) {
				return $block_content;
			}

			// Fast bail: only product image/gallery blocks.
			if ( 'woocommerce/product-image' !== $block['blockName'] && 'woocommerce/product-gallery' !== $block['blockName'] ) {
				return $block_content;
			}

			// Skip non-product contexts to avoid scanning every page's blocks.
			if ( ! is_singular( 'product' ) && ! ( function_exists( 'is_product' ) && is_product() ) && ! $this->is_any_builder_active() ) {
				return $block_content;
			}

			$product_id = ! empty( $block['attrs']['productId'] ) ? absint( $block['attrs']['productId'] ) : get_the_ID();
			if ( ! $product_id ) {
				return $block_content;
			}

			// On single product pages, only replace the gallery for the primary product being viewed.
			// Do NOT replace related products, up-sells, or cross-sell cards in Gutenberg loops!
			if ( ( is_singular( 'product' ) || ( function_exists( 'is_product' ) && is_product() ) ) && ! $this->is_any_builder_active() ) {
				$queried_id = get_queried_object_id();
				if ( $queried_id && $product_id !== $queried_id ) {
					return $block_content;
				}
			}

			$video_urls = get_post_meta( $product_id, '_wpvg_video_urls', true );
			if ( empty( $video_urls ) || ! is_array( $video_urls ) ) {
				return $block_content;
			}

			$product = wc_get_product( $product_id );
			if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
				return $block_content;
			}

			$this->enqueue_builder_assets();
			ob_start();
			?>
			<div class="woocommerce woocommerce-product-gallery images wpvg-gallery-wrapper wpvg-block-wrapper">
				<?php WPVG_Gallery::instance()->render_gallery_content( $product ); ?>
			</div>
			<?php
			return ob_get_clean();
		}

		/**
		 * Setup action fallback for custom page builders or child themes
		 * that execute woocommerce_before_single_product_summary without calling wc_get_template
		 */
		public function setup_action_fallbacks() {
			// Ensure high priority hook fallback if wc_get_template is bypassed by builder
			add_action( 'wpvg_render_product_gallery', array( $this, 'render_direct_gallery' ), 10, 1 );
		}

		/**
		 * Direct render helper callable from any builder code module or template
		 *
		 * @param int|WC_Product|null $prod_param Product or Product ID.
		 */
		public function render_direct_gallery( $prod_param = null ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
			global $product;
			$target_product = $prod_param ? $prod_param : $product;
			if ( is_numeric( $target_product ) ) {
				$target_product = wc_get_product( $target_product );
			}
			if ( $target_product && is_a( $target_product, 'WC_Product' ) && class_exists( 'WPVG_Gallery' ) ) {
				$this->enqueue_builder_assets();
				echo '<div class="woocommerce woocommerce-product-gallery images wpvg-gallery-wrapper">';
				WPVG_Gallery::instance()->render_gallery_content( $target_product );
				echo '</div>';
			}
		}
	}
}
