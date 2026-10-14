<?php
/**
 * Nexura Product Video Media Gallery for WooCommerce - Universal Shortcode
 * Enables embedding the interactive video gallery anywhere via Page Builders
 * (Elementor, Divi, WPBakery, Bricks, Oxygen, Beaver Builder, Gutenberg blocks, etc.)
 *
 * @package Nexura_Product_Video_Media_Gallery_For_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPVG_Shortcode' ) ) {
	class WPVG_Shortcode {

		/**
		 * Singleton instance
		 *
		 * @var WPVG_Shortcode|null
		 */
		private static $instance = null;

		/**
		 * Get instance
		 *
		 * @return WPVG_Shortcode
		 */
		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Constructor - Register single unified shortcode tag
		 */
		private function __construct() {
			add_shortcode( 'nexura_product_video_gallery', array( $this, 'render_shortcode' ) );
		}

		/**
		 * Render Shortcode Output
		 *
		 * Supports all gallery settings as attributes:
		 * [nexura_product_video_gallery
		 *     id="123"
		 *     layout="vertical_left|horizontal|vertical_right|grid_mosaic|grid_2col|grid_1col"
		 *     ratio="4_5|1_1|16_9|9_16"
		 *     zoom="inner|lens|window|disabled"
		 *     zoom_level="2.2"
		 *     show_zoom="yes|no"
		 *     thumbs_count="4"
		 *     autoplay="yes|no"
		 *     slider_speed="3.5"
		 *     video_autoplay="yes|no"
		 *     video_loop="yes|no"
		 *     video_mute="muted|unmuted"
		 *     video_pos="first|second|third|last"
		 *     lightbox="yes|no"
		 *     mobile_layout="bottom_horizontal|keep_desktop|dots_only"
		 *     mobile_hide_arrows="yes|no"
		 *     mobile_show_dots="yes|no"
		 *     width="650px"
		 *     class="custom-gallery-class"]
		 *
		 * @param array  $atts Shortcode attributes.
		 * @param string $content Enclosed content.
		 * @return string HTML output.
		 */
		public function render_shortcode( $atts, $content = null ) {
			$raw_atts = is_array( $atts ) ? $atts : array();
			$atts     = shortcode_atts(
				array(
					'id'                  => 0,
					'product_id'          => 0,
					'layout'              => '',
					'ratio'               => '',
					'class'               => '',
					'width'               => '',
					'zoom'                => '',
					'zoom_type'           => '',
					'show_zoom'           => '',
					'zoom_level'          => '',
					'zoom_mode'           => '',
					'thumbs_count'        => '',
					'columns'             => '',
					'thumbnails'          => '',
					'autoplay'            => '',
					'slider_autoplay'     => '',
					'slider_speed'        => '',
					'speed'               => '',
					'video_autoplay'      => '',
					'video_loop'          => '',
					'loop'                => '',
					'video_mute'          => '',
					'mute'                => '',
					'video_pos'           => '',
					'video_placement'     => '',
					'lightbox'            => '',
					'show_lightbox'       => '',
					'modal_pattern'       => '',
					'modal_pos'           => '',
					'modal_position'      => '',
					'modal_thumb_size'    => '',
					'mobile_layout'       => '',
					'mobile_breakpoint'   => '',
					'mobile_thumbs_count' => '',
					'mobile_hide_arrows'  => '',
					'mobile_show_dots'    => '',
					'mobile_disable_zoom' => '',
				),
				$atts,
				'nexura_product_video_gallery'
			);

			// Resolve Product ID
			$product_id = absint( $atts['product_id'] ? $atts['product_id'] : $atts['id'] );
			if ( ! $product_id ) {
				global $product;
				if ( $product && is_a( $product, 'WC_Product' ) ) {
					$product_id = $product->get_id();
				} else {
					$product_id = get_the_ID();
				}
			}

			if ( ! $product_id ) {
				return '';
			}

			$product_obj = wc_get_product( $product_id );
			if ( ! $product_obj || ! is_a( $product_obj, 'WC_Product' ) ) {
				return '';
			}

			// Ensure scripts and styles are forced to load on pages using shortcodes (e.g. landing pages)
			if ( class_exists( 'WPVG_Gallery' ) ) {
				WPVG_Gallery::instance()->enqueue_frontend_assets( true );
			}

			// Mapping of shortcode attributes to filter keys
			$applied_filters = array();

			// Layout
			if ( ! empty( $atts['layout'] ) ) {
				$val = sanitize_key( $atts['layout'] );
				$cb  = function() use ( $val ) { return $val; };
				add_filter( 'wpvg_resolved_layout', $cb, 999 );
				$applied_filters['layout'] = $cb;
			}

			// Aspect Ratio
			if ( ! empty( $atts['ratio'] ) ) {
				$val = sanitize_key( $atts['ratio'] );
				$cb  = function() use ( $val ) { return $val; };
				add_filter( 'wpvg_resolved_ratio', $cb, 999 );
				$applied_filters['ratio'] = $cb;
			}

			// Zoom Type
			$zoom_val = ! empty( $atts['zoom_type'] ) ? $atts['zoom_type'] : $atts['zoom'];
			if ( ! empty( $zoom_val ) ) {
				$val = sanitize_key( $zoom_val );
				$cb  = function() use ( $val ) { return $val; };
				add_filter( 'wpvg_resolved_zoom_type', $cb, 999 );
				$applied_filters['zoom_type'] = $cb;
			}

			// Show Zoom
			if ( ! empty( $atts['show_zoom'] ) ) {
				$val = 'no' === strtolower( $atts['show_zoom'] ) ? 'no' : 'yes';
				$cb  = function() use ( $val ) { return $val; };
				add_filter( 'wpvg_resolved_show_zoom', $cb, 999 );
				$applied_filters['show_zoom'] = $cb;
			}

			// Zoom Level
			if ( ! empty( $atts['zoom_level'] ) ) {
				$val = floatval( $atts['zoom_level'] );
				$cb  = function() use ( $val ) { return $val; };
				add_filter( 'wpvg_resolved_zoom_level', $cb, 999 );
				$applied_filters['zoom_level'] = $cb;
			}

			// Zoom Mode
			if ( ! empty( $atts['zoom_mode'] ) ) {
				$val = sanitize_key( $atts['zoom_mode'] );
				$cb  = function() use ( $val ) { return $val; };
				add_filter( 'wpvg_resolved_zoom_mode', $cb, 999 );
				$applied_filters['zoom_mode'] = $cb;
			}

			// Thumbnails Count / Columns
			$thumbs_val = ! empty( $atts['thumbs_count'] ) ? $atts['thumbs_count'] : ( ! empty( $atts['columns'] ) ? $atts['columns'] : $atts['thumbnails'] );
			if ( ! empty( $thumbs_val ) ) {
				$val = absint( $thumbs_val );
				$cb  = function() use ( $val ) { return $val; };
				add_filter( 'wpvg_resolved_thumbs_count', $cb, 999 );
				$applied_filters['thumbs_count'] = $cb;
			}

			// Slider Autoplay
			$auto_val = ! empty( $atts['slider_autoplay'] ) ? $atts['slider_autoplay'] : $atts['autoplay'];
			if ( ! empty( $auto_val ) ) {
				$val = ( 'yes' === strtolower( $auto_val ) || 'true' === strtolower( $auto_val ) || '1' === (string) $auto_val ) ? 'yes' : 'no';
				$cb  = function() use ( $val ) { return $val; };
				add_filter( 'wpvg_resolved_slider_autoplay', $cb, 999 );
				$applied_filters['slider_autoplay'] = $cb;
			}

			// Slider Speed
			$speed_val = ! empty( $atts['slider_speed'] ) ? $atts['slider_speed'] : $atts['speed'];
			if ( ! empty( $speed_val ) ) {
				$val = floatval( $speed_val );
				$cb  = function() use ( $val ) { return $val; };
				add_filter( 'wpvg_resolved_slider_speed', $cb, 999 );
				$applied_filters['slider_speed'] = $cb;
			}

			// Video Autoplay
			if ( ! empty( $atts['video_autoplay'] ) ) {
				$val = ( 'yes' === strtolower( $atts['video_autoplay'] ) || 'true' === strtolower( $atts['video_autoplay'] ) || '1' === (string) $atts['video_autoplay'] ) ? 'yes' : 'no';
				$cb  = function() use ( $val ) { return $val; };
				add_filter( 'wpvg_resolved_video_autoplay', $cb, 999 );
				$applied_filters['video_autoplay'] = $cb;
			}

			// Video Loop
			$loop_val = ! empty( $atts['video_loop'] ) ? $atts['video_loop'] : $atts['loop'];
			if ( ! empty( $loop_val ) ) {
				$val = ( 'yes' === strtolower( $loop_val ) || 'true' === strtolower( $loop_val ) || '1' === (string) $loop_val ) ? 'yes' : 'no';
				$cb  = function() use ( $val ) { return $val; };
				add_filter( 'wpvg_resolved_video_loop', $cb, 999 );
				$applied_filters['video_loop'] = $cb;
			}

			// Video Mute
			$mute_val = ! empty( $atts['video_mute'] ) ? $atts['video_mute'] : $atts['mute'];
			if ( ! empty( $mute_val ) ) {
				$val = ( 'muted' === strtolower( $mute_val ) || 'yes' === strtolower( $mute_val ) || 'true' === strtolower( $mute_val ) || '1' === (string) $mute_val ) ? 'muted' : 'unmuted';
				$cb  = function() use ( $val ) { return $val; };
				add_filter( 'wpvg_resolved_video_mute', $cb, 999 );
				$applied_filters['video_mute'] = $cb;
			}

			// Video Placement
			$vpos_val = ! empty( $atts['video_pos'] ) ? $atts['video_pos'] : $atts['video_placement'];
			if ( ! empty( $vpos_val ) ) {
				$val = sanitize_key( $vpos_val );
				$cb  = function() use ( $val ) { return $val; };
				add_filter( 'wpvg_resolved_video_pos', $cb, 999 );
				$applied_filters['video_pos'] = $cb;
			}

			// Lightbox
			$lb_val = ! empty( $atts['show_lightbox'] ) ? $atts['show_lightbox'] : $atts['lightbox'];
			if ( ! empty( $lb_val ) ) {
				$val = ( 'no' === strtolower( $lb_val ) || 'false' === strtolower( $lb_val ) || '0' === (string) $lb_val ) ? 'no' : 'yes';
				$cb  = function() use ( $val ) { return $val; };
				add_filter( 'wpvg_resolved_show_lightbox', $cb, 999 );
				$applied_filters['show_lightbox'] = $cb;
			}

			// Modal Pattern & Position
			if ( ! empty( $atts['modal_pattern'] ) ) {
				$val = sanitize_key( $atts['modal_pattern'] );
				$cb  = function() use ( $val ) { return $val; };
				add_filter( 'wpvg_resolved_modal_pattern', $cb, 999 );
				$applied_filters['modal_pattern'] = $cb;
			}
			$mpos_val = ! empty( $atts['modal_pos'] ) ? $atts['modal_pos'] : $atts['modal_position'];
			if ( ! empty( $mpos_val ) ) {
				$val = sanitize_key( $mpos_val );
				$cb  = function() use ( $val ) { return $val; };
				add_filter( 'wpvg_resolved_modal_position', $cb, 999 );
				$applied_filters['modal_position'] = $cb;
			}
			if ( ! empty( $atts['modal_thumb_size'] ) ) {
				$val = absint( $atts['modal_thumb_size'] );
				$cb  = function() use ( $val ) { return $val; };
				add_filter( 'wpvg_resolved_modal_thumb_size', $cb, 999 );
				$applied_filters['modal_thumb_size'] = $cb;
			}

			// Mobile responsive options
			if ( ! empty( $atts['mobile_layout'] ) ) {
				$val = sanitize_key( $atts['mobile_layout'] );
				$cb  = function() use ( $val ) { return $val; };
				add_filter( 'wpvg_resolved_mobile_layout', $cb, 999 );
				$applied_filters['mobile_layout'] = $cb;
			}
			if ( ! empty( $atts['mobile_breakpoint'] ) ) {
				$val = absint( $atts['mobile_breakpoint'] );
				$cb  = function() use ( $val ) { return $val; };
				add_filter( 'wpvg_resolved_mobile_breakpoint', $cb, 999 );
				$applied_filters['mobile_breakpoint'] = $cb;
			}
			if ( ! empty( $atts['mobile_thumbs_count'] ) ) {
				$val = absint( $atts['mobile_thumbs_count'] );
				$cb  = function() use ( $val ) { return $val; };
				add_filter( 'wpvg_resolved_mobile_thumbs_count', $cb, 999 );
				$applied_filters['mobile_thumbs_count'] = $cb;
			}
			if ( ! empty( $atts['mobile_hide_arrows'] ) ) {
				$val = ( 'yes' === strtolower( $atts['mobile_hide_arrows'] ) || '1' === (string) $atts['mobile_hide_arrows'] ) ? 'yes' : 'no';
				$cb  = function() use ( $val ) { return $val; };
				add_filter( 'wpvg_resolved_mobile_hide_arrows', $cb, 999 );
				$applied_filters['mobile_hide_arrows'] = $cb;
			}
			if ( ! empty( $atts['mobile_show_dots'] ) ) {
				$val = ( 'no' === strtolower( $atts['mobile_show_dots'] ) || '0' === (string) $atts['mobile_show_dots'] ) ? 'no' : 'yes';
				$cb  = function() use ( $val ) { return $val; };
				add_filter( 'wpvg_resolved_mobile_show_dots', $cb, 999 );
				$applied_filters['mobile_show_dots'] = $cb;
			}
			if ( ! empty( $atts['mobile_disable_zoom'] ) ) {
				$val = ( 'no' === strtolower( $atts['mobile_disable_zoom'] ) || '0' === (string) $atts['mobile_disable_zoom'] ) ? 'no' : 'yes';
				$cb  = function() use ( $val ) { return $val; };
				add_filter( 'wpvg_resolved_mobile_disable_zoom', $cb, 999 );
				$applied_filters['mobile_disable_zoom'] = $cb;
			}

			ob_start();

			$wrapper_classes = array(
				'woocommerce',
				'woocommerce-product-gallery',
				'images',
				'wpvg-gallery-wrapper',
				'wpvg-shortcode-wrapper',
			);

			if ( ! empty( $atts['class'] ) ) {
				$wrapper_classes[] = sanitize_html_class( $atts['class'] );
			}

			$inline_style = '';
			if ( ! empty( $atts['width'] ) ) {
				$inline_style = 'max-width:' . esc_attr( $atts['width'] ) . ';width:100%;margin:0 auto;';
			}

			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
			$wpvg_columns = apply_filters( 'woocommerce_product_thumbnails_columns', 4 );
			?>
			<div class="<?php echo esc_attr( implode( ' ', array_map( 'sanitize_html_class', $wrapper_classes ) ) ); ?>" data-columns="<?php echo esc_attr( $wpvg_columns ); ?>"<?php echo $inline_style ? ' style="' . esc_attr( $inline_style ) . '"' : ''; ?>>
				<?php
				if ( class_exists( 'WPVG_Gallery' ) ) {
					WPVG_Gallery::instance()->render_gallery_content( $product_obj );
				}
				?>
			</div>
			<?php

			// Remove all temporary shortcode filters
			foreach ( $applied_filters as $key => $callback ) {
				remove_filter( 'wpvg_resolved_' . $key, $callback, 999 );
			}

			return ob_get_clean();
		}
	}
}
