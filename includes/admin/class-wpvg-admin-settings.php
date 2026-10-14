<?php
/**
 * Nexura Product Video & Media Gallery Settings Page
 *
 * @package Nexura_Product_Video_Media_Gallery_For_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPVG_Admin_Settings' ) ) {
	class WPVG_Admin_Settings {

		/**
		 * Constructor
		 */
		public function __construct() {
			add_action( 'admin_menu', array( $this, 'register_settings_menu' ) );
			add_action( 'admin_init', array( $this, 'save_settings' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_settings_assets' ) );

			if ( defined( 'WPVG_PLUGIN_BASENAME' ) ) {
				add_filter( 'plugin_action_links_' . WPVG_PLUGIN_BASENAME, array( $this, 'add_action_links' ) );
			}
		}

		/**
		 * Add quick Settings link to Plugins page
		 *
		 * @param array $links Array of plugin action links.
		 * @return array
		 */
		public function add_action_links( $links ) {
			$settings_url  = admin_url( 'edit.php?post_type=product&page=wpvg-video-gallery-settings' );
			$settings_link = sprintf(
				'<a href="%s">%s</a>',
				esc_url( $settings_url ),
				esc_html__( 'Settings', 'nexura-product-video-media-gallery-for-woocommerce' )
			);
			array_unshift( $links, $settings_link );
			return $links;
		}

		/**
		 * Register Submenu under WooCommerce Products
		 */
		public function register_settings_menu() {
			add_submenu_page(
				'edit.php?post_type=product',
				__( 'Nexura Product Video Media Gallery Settings', 'nexura-product-video-media-gallery-for-woocommerce' ),
				__( 'Video Gallery', 'nexura-product-video-media-gallery-for-woocommerce' ),
				'manage_woocommerce',
				'wpvg-video-gallery-settings',
				array( $this, 'render_settings_page' )
			);
		}

		/**
		 * Enqueue admin scripts & styles on settings page
		 *
		 * @param string $hook Screen hook.
		 */
		public function enqueue_settings_assets( $hook ) {
			if ( 'product_page_wpvg-video-gallery-settings' !== $hook ) {
				return;
			}

			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wp-color-picker' );

			// Professional CSS editor (WordPress core CodeMirror).
			$css_editor_settings = false;
			if ( function_exists( 'wp_enqueue_code_editor' ) ) {
				$css_editor_settings = wp_enqueue_code_editor(
					array(
						'type'       => 'text/css',
						'codemirror' => array(
							'indentUnit'       => 2,
							'tabSize'          => 2,
							'lineNumbers'      => true,
							'lineWrapping'     => true,
							'autoCloseBrackets'=> true,
							'matchBrackets'    => true,
							'styleActiveLine'  => true,
						),
					)
				);
			}

			wp_enqueue_style(
				'wpvg-admin-css',
				plugins_url( 'assets/css/wpvg-admin.css', dirname( __DIR__ ) ),
				array(),
				WPVG_VERSION
			);

			$js_deps = array( 'jquery', 'wp-color-picker' );
			if ( false !== $css_editor_settings ) {
				$js_deps[] = 'code-editor';
			}

			$admin_js_path = plugin_dir_path( dirname( __DIR__ ) ) . 'assets/js/wpvg-admin.js';
			$js_ver        = defined( 'WPVG_VERSION' ) ? WPVG_VERSION : '1.0.0';
			if ( is_readable( $admin_js_path ) ) {
				$mtime = filemtime( $admin_js_path );
				if ( false !== $mtime ) {
					$js_ver .= '.' . (int) $mtime;
				}
			}
			wp_enqueue_script(
				'wpvg-admin-js',
				plugins_url( 'assets/js/wpvg-admin.js', dirname( __DIR__ ) ),
				$js_deps,
				$js_ver,
				true
			);

			$pro_active      = class_exists( 'WPVG_Pro' ) || (bool) apply_filters( 'wpvg_is_pro_active', false );
			$allows_multiple = function_exists( 'wpvg_allows_multiple_videos' ) ? wpvg_allows_multiple_videos() : $pro_active;
			wp_localize_script( 'wpvg-admin-js', 'wpvg_admin_data', array(
				'is_pro'                 => $pro_active,
				// Treat as free UI unless Pro modules are actually loaded (Guideline 5 — no locked tabs).
				'is_premium_package'     => $pro_active,
				'allows_multiple_videos' => $allows_multiple,
				'show_pro_locks'         => false,
				'upgrade_url'            => WPVG_Freemius::get_upgrade_url(),
				'ajax_url'               => admin_url( 'admin-ajax.php' ),
				'css_editor'             => $css_editor_settings,
			) );
		}

		/**
		 * Sanitize admin Custom CSS for safe inline output.
		 *
		 * @param string $css Raw CSS from settings form.
		 * @return string
		 */
		private function sanitize_custom_css( $css ) {
			$css = (string) $css;
			$css = wp_strip_all_tags( $css );
			$css = str_ireplace( array( '</style>', '<style', '<?', '?>', 'javascript:' ), '', $css );
			// Cap size to avoid oversized options.
			if ( strlen( $css ) > 50000 ) {
				$css = substr( $css, 0, 50000 );
			}
			return trim( $css );
		}

		/**
		 * Save Settings
		 */
		public function save_settings() {
			if ( ! isset( $_POST['wpvg_settings_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpvg_settings_nonce'] ) ), 'wpvg_save_settings_action' ) ) {
				return;
			}

			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				return;
			}

			// Sanitize & Save Options
			$layout        = isset( $_POST['wpvg_slider_layout'] ) ? sanitize_text_field( wp_unslash( $_POST['wpvg_slider_layout'] ) ) : 'vertical_left';
			$thumbs_count  = isset( $_POST['wpvg_thumbnails_to_show'] ) ? absint( $_POST['wpvg_thumbnails_to_show'] ) : 4;
			$autoplay      = isset( $_POST['wpvg_slider_autoplay'] ) ? 'yes' : 'no';
			$lightbox      = isset( $_POST['wpvg_show_lightbox'] ) ? 'yes' : 'no';
			$zoom          = isset( $_POST['wpvg_show_zoom'] ) ? 'yes' : 'no';
			// Zoom type validated later against wpvg_registered_zooms (includes free "disabled").
			$zoom_type     = isset( $_POST['wpvg_zoom_type'] ) ? sanitize_text_field( wp_unslash( $_POST['wpvg_zoom_type'] ) ) : 'inner';
			$zoom_mode     = isset( $_POST['wpvg_zoom_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['wpvg_zoom_mode'] ) ) : 'both';
			if ( ! in_array( $zoom_mode, array( 'both', 'hover', 'click' ), true ) ) {
				$zoom_mode = 'both';
			}
			$zoom_level    = isset( $_POST['wpvg_zoom_level'] ) ? floatval( $_POST['wpvg_zoom_level'] ) : 2.2;
			if ( $zoom_level < 1.1 || $zoom_level > 5.0 ) {
				$zoom_level = 2.2;
			}
			$lens_shape    = isset( $_POST['wpvg_lens_shape'] ) ? sanitize_text_field( wp_unslash( $_POST['wpvg_lens_shape'] ) ) : 'circle';
			if ( ! in_array( $lens_shape, array( 'circle', 'square' ), true ) ) {
				$lens_shape = 'circle';
			}
			$lens_size     = isset( $_POST['wpvg_lens_size'] ) ? absint( $_POST['wpvg_lens_size'] ) : 180;
			if ( $lens_size < 100 || $lens_size > 350 ) {
				$lens_size = 180;
			}
			$window_pos    = isset( $_POST['wpvg_zoom_window_pos'] ) ? sanitize_text_field( wp_unslash( $_POST['wpvg_zoom_window_pos'] ) ) : 'right';
			if ( ! in_array( $window_pos, array( 'right', 'left' ), true ) ) {
				$window_pos = 'right';
			}
			$window_width  = isset( $_POST['wpvg_zoom_window_width'] ) ? absint( $_POST['wpvg_zoom_window_width'] ) : 450;
			if ( $window_width < 250 || $window_width > 700 ) {
				$window_width = 450;
			}
			$window_height = isset( $_POST['wpvg_zoom_window_height'] ) ? absint( $_POST['wpvg_zoom_window_height'] ) : 450;
			if ( $window_height < 250 || $window_height > 700 ) {
				$window_height = 450;
			}
			$modal_pattern = isset( $_POST['wpvg_modal_pattern'] ) ? sanitize_text_field( wp_unslash( $_POST['wpvg_modal_pattern'] ) ) : 'mosaic_2_1';
			$modal_pos     = isset( $_POST['wpvg_modal_position'] ) ? sanitize_text_field( wp_unslash( $_POST['wpvg_modal_position'] ) ) : 'right';
			// sanitize_hex_color() returns null for invalid input — never store null on live hosts.
			$arrow_color   = isset( $_POST['wpvg_arrow_color'] ) ? sanitize_hex_color( wp_unslash( $_POST['wpvg_arrow_color'] ) ) : '#1e293b';
			$arrow_bg      = isset( $_POST['wpvg_arrow_bg'] ) ? sanitize_hex_color( wp_unslash( $_POST['wpvg_arrow_bg'] ) ) : '#ffffff';
			if ( empty( $arrow_color ) ) {
				$arrow_color = '#1e293b';
			}
			if ( empty( $arrow_bg ) ) {
				$arrow_bg = '#ffffff';
			}
			$video_place    = isset( $_POST['wpvg_video_placement'] ) ? sanitize_text_field( wp_unslash( $_POST['wpvg_video_placement'] ) ) : 'second';
			$video_autoplay = isset( $_POST['wpvg_video_autoplay'] ) ? sanitize_text_field( wp_unslash( $_POST['wpvg_video_autoplay'] ) ) : 'no';
			$gallery_ratio  = isset( $_POST['wpvg_gallery_ratio'] ) ? sanitize_text_field( wp_unslash( $_POST['wpvg_gallery_ratio'] ) ) : '4_5';
			$slider_speed   = isset( $_POST['wpvg_slider_speed'] ) ? floatval( $_POST['wpvg_slider_speed'] ) : 3.5;
			if ( $slider_speed < 1.0 || $slider_speed > 30.0 ) {
				$slider_speed = 3.5;
			}
			$video_loop     = isset( $_POST['wpvg_video_loop'] ) ? sanitize_text_field( wp_unslash( $_POST['wpvg_video_loop'] ) ) : 'no';
			$video_mute     = isset( $_POST['wpvg_video_mute'] ) ? sanitize_text_field( wp_unslash( $_POST['wpvg_video_mute'] ) ) : 'unmuted';
			$video_schema   = isset( $_POST['wpvg_video_schema'] ) ? 'yes' : 'no';

			// Mobile & Responsive Settings
			$mobile_layout       = isset( $_POST['wpvg_mobile_layout'] ) ? sanitize_text_field( wp_unslash( $_POST['wpvg_mobile_layout'] ) ) : 'bottom_horizontal';
			$mobile_breakpoint   = isset( $_POST['wpvg_mobile_breakpoint'] ) ? absint( $_POST['wpvg_mobile_breakpoint'] ) : 768;
			if ( $mobile_breakpoint < 320 || $mobile_breakpoint > 1200 ) {
				$mobile_breakpoint = 768;
			}
			$mobile_thumbs_count = isset( $_POST['wpvg_mobile_thumbs_count'] ) ? absint( $_POST['wpvg_mobile_thumbs_count'] ) : 4;
			if ( $mobile_thumbs_count < 2 || $mobile_thumbs_count > 6 ) {
				$mobile_thumbs_count = 4;
			}
			$mobile_hide_arrows  = isset( $_POST['wpvg_mobile_hide_arrows'] ) ? sanitize_text_field( wp_unslash( $_POST['wpvg_mobile_hide_arrows'] ) ) : 'no';
			$mobile_show_dots    = isset( $_POST['wpvg_mobile_show_dots'] ) ? sanitize_text_field( wp_unslash( $_POST['wpvg_mobile_show_dots'] ) ) : 'yes';
			$mobile_disable_zoom = isset( $_POST['wpvg_mobile_disable_zoom'] ) ? sanitize_text_field( wp_unslash( $_POST['wpvg_mobile_disable_zoom'] ) ) : 'yes';

			// Shop Archive & Catalog Video Settings [PRO]
			$archive_enable    = isset( $_POST['wpvg_archive_video_enable'] ) ? sanitize_text_field( wp_unslash( $_POST['wpvg_archive_video_enable'] ) ) : 'no';
			$archive_style     = isset( $_POST['wpvg_archive_video_style'] ) ? sanitize_text_field( wp_unslash( $_POST['wpvg_archive_video_style'] ) ) : 'hover_autoplay';
			$archive_scope     = isset( $_POST['wpvg_archive_video_scope'] ) ? sanitize_text_field( wp_unslash( $_POST['wpvg_archive_video_scope'] ) ) : 'single';
			$archive_badge_pos = isset( $_POST['wpvg_archive_badge_pos'] ) ? sanitize_text_field( wp_unslash( $_POST['wpvg_archive_badge_pos'] ) ) : 'bottom_right';

			// Validate requested layout, ratio, zoom, and placement against registered registry
			$registered_layouts    = apply_filters( 'wpvg_registered_layouts', array( 'vertical_left', 'horizontal' ) );
			$registered_ratios     = apply_filters( 'wpvg_registered_ratios', array( '4_5', '1_1' ) );
			$registered_zooms      = apply_filters( 'wpvg_registered_zooms', array( 'inner', 'disabled' ) );
			$registered_placements = apply_filters( 'wpvg_registered_video_placements', array( 'first', 'second' ) );
			$registered_mobiles    = apply_filters( 'wpvg_registered_mobile_layouts', array( 'bottom_horizontal', 'keep_desktop' ) );
			$registered_modals     = apply_filters( 'wpvg_registered_modal_patterns', array( 'mosaic_2_1', 'grid_2col' ) );
			$registered_modal_pos  = apply_filters( 'wpvg_registered_modal_positions', array( 'right' ) );

			if ( ! in_array( $layout, $registered_layouts, true ) ) {
				$layout = 'vertical_left';
			}
			if ( ! in_array( $gallery_ratio, $registered_ratios, true ) ) {
				$gallery_ratio = '4_5';
			}
			if ( ! in_array( $zoom_type, $registered_zooms, true ) ) {
				$zoom_type = 'inner';
			}
			if ( ! in_array( $video_place, $registered_placements, true ) ) {
				$video_place = 'second';
			}
			if ( ! in_array( $mobile_layout, $registered_mobiles, true ) ) {
				$mobile_layout = 'bottom_horizontal';
			}
			if ( ! in_array( $modal_pattern, $registered_modals, true ) ) {
				$modal_pattern = 'mosaic_2_1';
			}
			if ( ! in_array( $modal_pos, $registered_modal_pos, true ) ) {
				$modal_pos = 'right';
			}
			// Archive showcase lives in /pro/; free/.org ZIP has no WPVG_Pro class.
			if ( ! class_exists( 'WPVG_Pro' ) ) {
				$archive_enable = 'no';
			}

			// Update clean options
			update_option( 'wpvg_slider_layout', $layout );
			update_option( 'wpvg_gallery_ratio', $gallery_ratio );
			update_option( 'wpvg_modal_pattern', $modal_pattern );
			update_option( 'wpvg_modal_position', $modal_pos );
			$modal_thumb_size = isset( $_POST['wpvg_modal_thumb_size'] ) ? absint( $_POST['wpvg_modal_thumb_size'] ) : 48;
			if ( $modal_thumb_size < 28 || $modal_thumb_size > 120 ) {
				$modal_thumb_size = 48;
			}
			update_option( 'wpvg_modal_thumb_size', $modal_thumb_size );
			update_option( 'wpvg_thumbnails_to_show', $thumbs_count );
			update_option( 'wpvg_slider_autoplay', $autoplay );
			update_option( 'wpvg_slider_speed', $slider_speed );
			update_option( 'wpvg_video_autoplay', $video_autoplay );
			update_option( 'wpvg_video_loop', $video_loop );
			update_option( 'wpvg_video_mute', $video_mute );
			update_option( 'wpvg_video_schema', $video_schema );
			update_option( 'wpvg_show_lightbox', $lightbox );
			update_option( 'wpvg_show_zoom', $zoom );
			update_option( 'wpvg_zoom_type', $zoom_type );
			update_option( 'wpvg_zoom_mode', $zoom_mode );
			update_option( 'wpvg_zoom_level', $zoom_level );
			update_option( 'wpvg_lens_shape', $lens_shape );
			update_option( 'wpvg_lens_size', $lens_size );
			update_option( 'wpvg_zoom_window_pos', $window_pos );
			update_option( 'wpvg_zoom_window_width', $window_width );
			update_option( 'wpvg_zoom_window_height', $window_height );
			update_option( 'wpvg_arrow_color', $arrow_color );
			update_option( 'wpvg_arrow_bg', $arrow_bg );
			update_option( 'wpvg_video_placement', $video_place );

			// Custom CSS (admin-only; stripped of HTML / style breakouts).
			$custom_css = '';
			if ( isset( $_POST['wpvg_custom_css'] ) ) {
				$custom_css = $this->sanitize_custom_css(
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- CSS passed through sanitize_custom_css().
					wp_unslash( $_POST['wpvg_custom_css'] )
				);
			}
			update_option( 'wpvg_custom_css', $custom_css, false );

			// Save Mobile Options
			update_option( 'wpvg_mobile_layout', $mobile_layout );
			update_option( 'wpvg_mobile_breakpoint', $mobile_breakpoint );
			update_option( 'wpvg_mobile_thumbs_count', $mobile_thumbs_count );
			update_option( 'wpvg_mobile_hide_arrows', $mobile_hide_arrows );
			update_option( 'wpvg_mobile_show_dots', $mobile_show_dots );
			update_option( 'wpvg_mobile_disable_zoom', $mobile_disable_zoom );

			// Archive + 3D options only persist when Pro modules are loaded (not on free/.org ZIP).
			if ( class_exists( 'WPVG_Pro' ) ) {
				update_option( 'wpvg_archive_video_enable', $archive_enable );
				update_option( 'wpvg_archive_video_style', $archive_style );
				update_option( 'wpvg_archive_video_scope', $archive_scope );
				update_option( 'wpvg_archive_badge_pos', $archive_badge_pos );

				$d3_enable_global = isset( $_POST['wpvg_3d_enable_global'] ) ? sanitize_text_field( wp_unslash( $_POST['wpvg_3d_enable_global'] ) ) : 'per_product';
				if ( ! in_array( $d3_enable_global, array( 'per_product', 'all_products', 'disabled' ), true ) ) {
					$d3_enable_global = 'per_product';
				}
				$d3_source_type   = isset( $_POST['wpvg_3d_source_type'] ) ? sanitize_text_field( wp_unslash( $_POST['wpvg_3d_source_type'] ) ) : 'auto';
				if ( ! in_array( $d3_source_type, array( 'auto', 'gallery_360', 'model_file' ), true ) ) {
					$d3_source_type = 'auto';
				}
				$d3_view_mode     = isset( $_POST['wpvg_3d_view_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['wpvg_3d_view_mode'] ) ) : 'switcher';
				if ( ! in_array( $d3_view_mode, array( 'switcher', '3d_only', '2d_only' ), true ) ) {
					$d3_view_mode = 'switcher';
				}
				$d3_show_switcher = ( 'switcher' === $d3_view_mode ) ? 'yes' : 'no';
				$d3_default_view  = isset( $_POST['wpvg_3d_default_view'] ) ? sanitize_text_field( wp_unslash( $_POST['wpvg_3d_default_view'] ) ) : '3d';
				if ( ! in_array( $d3_default_view, array( '3d', '2d' ), true ) ) {
					$d3_default_view = '3d';
				}
				$d3_spin_speed    = isset( $_POST['wpvg_3d_spin_speed'] ) ? sanitize_text_field( wp_unslash( $_POST['wpvg_3d_spin_speed'] ) ) : 'normal';
				if ( ! in_array( $d3_spin_speed, array( 'fast', 'normal', 'smooth' ), true ) ) {
					$d3_spin_speed = 'normal';
				}
				$d3_spin_sens     = isset( $_POST['wpvg_3d_spin_sensitivity'] ) ? sanitize_text_field( wp_unslash( $_POST['wpvg_3d_spin_sensitivity'] ) ) : 'normal';
				if ( ! in_array( $d3_spin_sens, array( 'high', 'normal', 'smooth' ), true ) ) {
					$d3_spin_sens = 'normal';
				}
				$d3_spin_dir      = isset( $_POST['wpvg_3d_spin_direction'] ) ? sanitize_text_field( wp_unslash( $_POST['wpvg_3d_spin_direction'] ) ) : 'normal';
				if ( ! in_array( $d3_spin_dir, array( 'normal', 'reverse' ), true ) ) {
					$d3_spin_dir = 'normal';
				}
				$d3_load_mode     = isset( $_POST['wpvg_3d_load_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['wpvg_3d_load_mode'] ) ) : 'on_demand';
				if ( ! in_array( $d3_load_mode, array( 'on_demand', 'instant' ), true ) ) {
					$d3_load_mode = 'on_demand';
				}
				$d3_overlay_text  = isset( $_POST['wpvg_3d_overlay_text'] ) ? sanitize_text_field( wp_unslash( $_POST['wpvg_3d_overlay_text'] ) ) : __( 'Click for interactive 3D', 'nexura-product-video-media-gallery-for-woocommerce' );
				$d3_auto_rotate   = isset( $_POST['wpvg_3d_auto_rotate'] ) ? 'yes' : 'no';
				$d3_rotate_delay  = isset( $_POST['wpvg_3d_auto_rotate_delay'] ) ? absint( $_POST['wpvg_3d_auto_rotate_delay'] ) : 3;
				$d3_controls      = isset( $_POST['wpvg_3d_camera_controls'] ) ? 'yes' : 'no';
				$d3_show_badge    = isset( $_POST['wpvg_3d_show_badge'] ) ? 'yes' : 'no';
				$d3_in_lightbox   = isset( $_POST['wpvg_3d_in_lightbox'] ) ? 'yes' : 'no';
				$d3_bg_color      = isset( $_POST['wpvg_3d_bg_color'] ) ? sanitize_text_field( wp_unslash( $_POST['wpvg_3d_bg_color'] ) ) : 'transparent';
				$d3_default_model = isset( $_POST['wpvg_3d_default_model_url'] ) ? esc_url_raw( wp_unslash( $_POST['wpvg_3d_default_model_url'] ) ) : '';
				$d3_slider_autoplay = isset( $_POST['wpvg_3d_slider_autoplay'] ) ? 'yes' : 'no';
				$d3_modal_thumb_size = isset( $_POST['wpvg_3d_modal_thumb_size'] ) ? absint( $_POST['wpvg_3d_modal_thumb_size'] ) : 0;

				update_option( 'wpvg_3d_enable_global', $d3_enable_global );
				update_option( 'wpvg_3d_source_type', $d3_source_type );
				update_option( 'wpvg_3d_view_mode', $d3_view_mode );
				update_option( 'wpvg_3d_show_switcher', $d3_show_switcher );
				update_option( 'wpvg_3d_default_view', $d3_default_view );
				update_option( 'wpvg_3d_spin_speed', $d3_spin_speed );
				update_option( 'wpvg_3d_spin_sensitivity', $d3_spin_sens );
				update_option( 'wpvg_3d_spin_direction', $d3_spin_dir );
				update_option( 'wpvg_3d_load_mode', $d3_load_mode );
				update_option( 'wpvg_3d_overlay_text', $d3_overlay_text );
				update_option( 'wpvg_3d_auto_rotate', $d3_auto_rotate );
				update_option( 'wpvg_3d_auto_rotate_delay', $d3_rotate_delay );
				update_option( 'wpvg_3d_camera_controls', $d3_controls );
				update_option( 'wpvg_3d_show_badge', $d3_show_badge );
				update_option( 'wpvg_3d_in_lightbox', $d3_in_lightbox );
				update_option( 'wpvg_3d_bg_color', $d3_bg_color );
				update_option( 'wpvg_3d_default_model_url', $d3_default_model );
				update_option( 'wpvg_3d_slider_autoplay', $d3_slider_autoplay );
				update_option( 'wpvg_3d_modal_thumb_size', $d3_modal_thumb_size );
			}

			add_settings_error(
				'wpvg_settings_notices',
				'wpvg_settings_updated',
				__( 'Settings saved successfully!', 'nexura-product-video-media-gallery-for-woocommerce' ),
				'updated'
			);
		}

		/**
		 * Render Settings Page HTML
		 */
		public function render_settings_page() {
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				return;
			}

			$layout              = get_option( 'wpvg_slider_layout', 'vertical_left' );
			$gallery_ratio       = get_option( 'wpvg_gallery_ratio', '4_5' );
			$modal_pattern       = get_option( 'wpvg_modal_pattern', 'mosaic_2_1' );
			$modal_pos           = get_option( 'wpvg_modal_position', 'right' );
			$modal_thumb_size    = absint( get_option( 'wpvg_modal_thumb_size', 48 ) );
			$thumbs_count        = get_option( 'wpvg_thumbnails_to_show', 4 );
			$autoplay            = get_option( 'wpvg_slider_autoplay', 'no' );
			$slider_speed        = get_option( 'wpvg_slider_speed', 3.5 );
			$video_autoplay      = get_option( 'wpvg_video_autoplay', 'no' );
			$video_loop          = get_option( 'wpvg_video_loop', 'no' );
			$video_mute          = get_option( 'wpvg_video_mute', 'unmuted' );
			$video_schema        = get_option( 'wpvg_video_schema', 'yes' );
			$lightbox            = get_option( 'wpvg_show_lightbox', 'yes' );
			$zoom                = get_option( 'wpvg_show_zoom', 'yes' );
			$zoom_type           = get_option( 'wpvg_zoom_type', 'inner' );
			$zoom_mode           = get_option( 'wpvg_zoom_mode', 'both' );
			$zoom_level          = get_option( 'wpvg_zoom_level', 2.2 );
			$lens_shape          = get_option( 'wpvg_lens_shape', 'circle' );
			$lens_size           = get_option( 'wpvg_lens_size', 180 );
			$window_pos          = get_option( 'wpvg_zoom_window_pos', 'right' );
			$window_width        = get_option( 'wpvg_zoom_window_width', 450 );
			$window_height       = get_option( 'wpvg_zoom_window_height', 450 );
			$arrow_color         = get_option( 'wpvg_arrow_color', '#1e293b' );
			$arrow_bg            = get_option( 'wpvg_arrow_bg', '#ffffff' );
			$custom_css_setting  = get_option( 'wpvg_custom_css', '' );
			$video_place         = get_option( 'wpvg_video_placement', 'second' );

			// Mobile Options
			$mobile_layout       = get_option( 'wpvg_mobile_layout', 'bottom_horizontal' );
			$mobile_breakpoint   = get_option( 'wpvg_mobile_breakpoint', 768 );
			$mobile_thumbs_count = get_option( 'wpvg_mobile_thumbs_count', 4 );
			$mobile_hide_arrows  = get_option( 'wpvg_mobile_hide_arrows', 'no' );
			$mobile_show_dots    = get_option( 'wpvg_mobile_show_dots', 'yes' );
			$mobile_disable_zoom = get_option( 'wpvg_mobile_disable_zoom', 'yes' );

			// Shop Archive Options [PRO]
			$archive_enable      = get_option( 'wpvg_archive_video_enable', 'no' );
			$archive_style       = get_option( 'wpvg_archive_video_style', 'hover_autoplay' );
			$archive_scope       = get_option( 'wpvg_archive_video_scope', 'single' );
			$archive_badge_pos   = get_option( 'wpvg_archive_badge_pos', 'bottom_right' );

			// 3D Viewer Options [PRO]
			$d3_enable_global    = get_option( 'wpvg_3d_enable_global', 'per_product' );
			$d3_source_type      = get_option( 'wpvg_3d_source_type', 'auto' );
			$d3_view_mode        = get_option( 'wpvg_3d_view_mode', '' );
			if ( empty( $d3_view_mode ) ) {
				$d3_view_mode = ( 'no' === get_option( 'wpvg_3d_show_switcher', 'yes' ) ) ? '3d_only' : 'switcher';
			}
			$d3_show_switcher    = ( 'switcher' === $d3_view_mode ) ? 'yes' : 'no';
			$d3_default_view     = get_option( 'wpvg_3d_default_view', '3d' );
			$d3_spin_speed       = get_option( 'wpvg_3d_spin_speed', 'normal' );
			$d3_spin_sens        = get_option( 'wpvg_3d_spin_sensitivity', 'normal' );
			$d3_spin_dir         = get_option( 'wpvg_3d_spin_direction', 'normal' );
			$d3_load_mode        = get_option( 'wpvg_3d_load_mode', 'on_demand' );
			$d3_overlay_text     = get_option( 'wpvg_3d_overlay_text', __( 'Click for interactive 3D', 'nexura-product-video-media-gallery-for-woocommerce' ) );
			$d3_auto_rotate      = get_option( 'wpvg_3d_auto_rotate', 'yes' );
			$d3_auto_rotate_delay= absint( get_option( 'wpvg_3d_auto_rotate_delay', 3 ) );
			$d3_camera_controls  = get_option( 'wpvg_3d_camera_controls', 'yes' );
			$d3_show_badge       = get_option( 'wpvg_3d_show_badge', 'yes' );
			$d3_in_lightbox      = get_option( 'wpvg_3d_in_lightbox', 'yes' );
			$d3_bg_color         = get_option( 'wpvg_3d_bg_color', 'transparent' );
			$d3_default_model    = get_option( 'wpvg_3d_default_model_url', '' );
			$d3_slider_autoplay  = get_option( 'wpvg_3d_slider_autoplay', 'no' );
			$d3_modal_thumb_size = absint( get_option( 'wpvg_3d_modal_thumb_size', 0 ) );

			$g_is_disabled       = ( 'disabled' === $d3_enable_global );
			$g_is_2d_only        = ( '2d_only' === $d3_view_mode );
			$g_is_common_active  = ( ! $g_is_disabled && ! $g_is_2d_only );
			$g_is_switcher       = ( $g_is_common_active && 'switcher' === $d3_view_mode );
			$g_is_360_active     = ( $g_is_common_active && ( 'gallery_360' === $d3_source_type || ( 'model_file' !== $d3_source_type && 'switcher' === $d3_view_mode ) ) );
			$g_is_model_active   = ( $g_is_common_active && ! $g_is_360_active );

			$force_free      = defined( 'WPVG_FORCE_FREE' ) && WPVG_FORCE_FREE;
			$is_pro          = ! $force_free && ( class_exists( 'WPVG_Pro' ) || (bool) apply_filters( 'wpvg_is_pro_active', false ) );
			// Pro tabs/options only when Pro modules are loaded — never locked teasers (Guideline 5).
			$show_locks      = false;
			$show_pro_ui     = $is_pro;
			$pro_lock        = '';
			$account_url     = admin_url( 'edit.php?post_type=product&page=wpvg-video-gallery-settings-account' );
			if ( function_exists( 'wpvg_fs' ) ) {
				$fs = wpvg_fs();
				if ( is_object( $fs ) && method_exists( $fs, 'get_account_url' ) ) {
					$account_url = $fs->get_account_url();
				}
			}
			?>
			<div class="wrap wpvg-settings-wrap">
				<?php
				/*
				 * WordPress moves admin notices to the first heading in .wrap.
				 * Keep that heading outside the flex header so the Freemius opt-in
				 * notice stays full width above the card.
				 */
				?>
				<h1 class="wpvg-notice-anchor screen-reader-text"><?php esc_html_e( 'Nexura Product Video & Media Gallery', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></h1>
				<!-- Header Section -->
				<div class="wpvg-settings-header">
					<div class="wpvg-header-left">
						<div class="wpvg-header-title-row">
							<span class="dashicons dashicons-video-alt3" style="font-size: 28px; width: 28px; height: 28px; color: #2271b1;"></span>
							<p class="wpvg-page-title"><?php esc_html_e( 'Nexura Product Video & Media Gallery', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></p>
							<span class="wpvg-version-badge">v<?php echo esc_html( WPVG_VERSION ); ?></span>
							<?php if ( $is_pro ) : ?>
								<span class="wpvg-plan-badge wpvg-plan-pro"><span class="dashicons dashicons-saved"></span> <?php esc_html_e( 'PRO Active', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></span>
							<?php else : ?>
								<span class="wpvg-plan-badge wpvg-plan-free"><?php esc_html_e( 'Free Edition', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></span>
							<?php endif; ?>
						</div>
						<p><?php esc_html_e( 'Configure your gallery layout, image magnifier, video showcase, and mobile shopping experience.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></p>
						<?php if ( $is_pro ) : ?>
							<p class="wpvg-license-note">
								<?php esc_html_e( 'Your Pro license is active.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
							</p>
						<?php endif; ?>
					</div>
					<div class="wpvg-header-actions">
						<?php if ( $is_pro ) : ?>
							<a href="<?php echo esc_url( $account_url ); ?>" class="wpvg-btn-secondary">
								<?php esc_html_e( 'Account', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
							</a>
						<?php else : ?>
							<a href="<?php echo esc_url( WPVG_Freemius::get_upgrade_url() ); ?>" class="wpvg-btn-upgrade" target="_blank">
								<span class="dashicons dashicons-superhero"></span> <?php esc_html_e( 'Upgrade to Pro', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
							</a>
						<?php endif; ?>
					</div>
				</div>

				<?php settings_errors( 'wpvg_settings_notices' ); ?>

				<!-- Modern Navigation Tabs -->
				<nav class="wpvg-nav-tabs">
					<a href="#tab-general" class="wpvg-nav-tab active"><span class="dashicons dashicons-format-gallery"></span> <?php esc_html_e( 'Gallery Layout', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></a>
					<a href="#tab-zoom" class="wpvg-nav-tab"><span class="dashicons dashicons-search"></span> <?php esc_html_e( 'Zoom & Magnifier', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></a>
					<a href="#tab-video" class="wpvg-nav-tab"><span class="dashicons dashicons-format-video"></span> <?php esc_html_e( 'Video Options', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></a>
					<a href="#tab-responsive" class="wpvg-nav-tab"><span class="dashicons dashicons-smartphone"></span> <?php esc_html_e( 'Mobile & Touch', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></a>
					<?php if ( $show_pro_ui ) : ?>
						<a href="#tab-archive" class="wpvg-nav-tab"><span class="dashicons dashicons-grid-view"></span> <?php esc_html_e( 'Shop Archive', 'nexura-product-video-media-gallery-for-woocommerce' ); ?><?php if ( $show_locks ) : ?> <span class="wpvg-pro-badge" style="margin-left: 2px;">PRO</span><?php endif; ?></a>
						<a href="#tab-3d" class="wpvg-nav-tab"><span class="dashicons dashicons-visibility"></span> <?php esc_html_e( '3D Product Viewer', 'nexura-product-video-media-gallery-for-woocommerce' ); ?><?php if ( $show_locks ) : ?> <span class="wpvg-pro-badge" style="margin-left: 2px;">PRO</span><?php endif; ?></a>
					<?php endif; ?>
				</nav>

				<form method="post" action="" novalidate>
					<?php wp_nonce_field( 'wpvg_save_settings_action', 'wpvg_settings_nonce' ); ?>

					<!-- ======================================================================
					     Tab 1: Gallery Layout & Carousel Settings
					     ====================================================================== -->
					<div id="tab-general" class="wpvg-tab-content">
						<div class="wpvg-settings-card">
							<h2><?php esc_html_e( 'Gallery Layout & Display', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></h2>
							<p class="wpvg-section-intro"><?php esc_html_e( 'Select the primary gallery presentation format, aspect ratio, and thumbnail display.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></p>
							
							<table class="form-table">
								<tr>
									<th scope="row">
										<label for="wpvg_slider_layout"><?php esc_html_e( 'Gallery Layout', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
									</th>
									<td>
										<select name="wpvg_slider_layout" id="wpvg_slider_layout" class="<?php echo $show_locks ? 'wpvg-select-with-pro' : ''; ?>">
											<optgroup label="<?php esc_attr_e( 'Gallery Layouts', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>">
												<option value="vertical_left" <?php selected( $layout, 'vertical_left' ); ?>><?php esc_html_e( 'Vertical Left (Thumbnails on Left) - Recommended', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
												<option value="horizontal" <?php selected( $layout, 'horizontal' ); ?>><?php esc_html_e( 'Horizontal (Thumbnails at Bottom)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
											</optgroup>
											<?php if ( $show_pro_ui ) : ?>
											<optgroup label="<?php esc_attr_e( 'Additional Layouts', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>">
												<option value="vertical_right" data-pro="true" <?php selected( $layout, 'vertical_right' ); ?>><?php esc_html_e( 'Vertical Right (Thumbnails on Right)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?><?php echo esc_html( $pro_lock ); ?></option>
												<option value="grid_mosaic" data-pro="true" <?php selected( $layout, 'grid_mosaic' ); ?>><?php esc_html_e( 'Mosaic Grid (2 - 1 - 2 - 1 Alternating Pattern)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?><?php echo esc_html( $pro_lock ); ?></option>
												<option value="grid_2col" data-pro="true" <?php selected( $layout, 'grid_2col' ); ?>><?php esc_html_e( '2-Column Grid (Hero + 2-Col Grid)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?><?php echo esc_html( $pro_lock ); ?></option>
												<option value="grid_1col" data-pro="true" <?php selected( $layout, 'grid_1col' ); ?>><?php esc_html_e( '1-Column Stacked Grid', 'nexura-product-video-media-gallery-for-woocommerce' ); ?><?php echo esc_html( $pro_lock ); ?></option>
											</optgroup>
											<?php endif; ?>
										</select>
										<p class="description"><?php esc_html_e( 'Choose how thumbnails and the main image appear on your product page.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="wpvg_gallery_ratio"><?php esc_html_e( 'Gallery Aspect Ratio', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
									</th>
									<td>
										<select name="wpvg_gallery_ratio" id="wpvg_gallery_ratio" class="<?php echo $show_locks ? 'wpvg-select-with-pro' : ''; ?>">
											<optgroup label="<?php esc_attr_e( 'Standard Aspect Ratios', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>">
												<option value="4_5" <?php selected( $gallery_ratio, '4_5' ); ?>><?php esc_html_e( '4:5 Portrait (Standard eCommerce - Recommended)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
												<option value="1_1" <?php selected( $gallery_ratio, '1_1' ); ?>><?php esc_html_e( '1:1 Square (Modern / Instagram)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
											</optgroup>
											<?php if ( $show_pro_ui ) : ?>
											<optgroup label="<?php esc_attr_e( 'Additional Aspect Ratios', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>">
												<option value="16_9" data-pro="true" <?php selected( $gallery_ratio, '16_9' ); ?>><?php esc_html_e( '16:9 Widescreen (Landscape)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?><?php echo esc_html( $pro_lock ); ?></option>
												<option value="9_16" data-pro="true" <?php selected( $gallery_ratio, '9_16' ); ?>><?php esc_html_e( '9:16 Vertical Reels / TikTok (Full Smartphone Vertical)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?><?php echo esc_html( $pro_lock ); ?></option>
											</optgroup>
											<?php endif; ?>
										</select>
										<p class="description"><?php esc_html_e( 'Select 9:16 for modern TikTok / Instagram Reels vertical video showcasing.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="wpvg_thumbnails_to_show"><?php esc_html_e( 'Thumbnails in Viewport', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
									</th>
									<td>
										<input type="number" min="2" max="10" name="wpvg_thumbnails_to_show" id="wpvg_thumbnails_to_show" value="<?php echo esc_attr( $thumbs_count ); ?>" class="small-text">
										<p class="description"><?php esc_html_e( 'Number of thumbnails visible at once before scrolling/sliding (default: 4).', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php esc_html_e( 'Autoplay Slider', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></th>
									<td>
										<label>
											<input type="checkbox" name="wpvg_slider_autoplay" value="yes" <?php checked( $autoplay, 'yes' ); ?>>
											<?php esc_html_e( 'Enable automatic image slide transition', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
										</label>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="wpvg_slider_speed"><?php esc_html_e( 'Slider Rotation Delay', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
									</th>
									<td>
										<input type="number" min="1.5" max="15" step="0.1" name="wpvg_slider_speed" id="wpvg_slider_speed" value="<?php echo esc_attr( $slider_speed ); ?>" class="small-text">
										<span><?php esc_html_e( 'seconds', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></span>
										<p class="description"><?php esc_html_e( 'Seconds between automatic slide transitions (Default: 3.5 seconds).', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row"><?php esc_html_e( 'Slider Nav Arrow Colors', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></th>
									<td>
										<div style="display: flex; gap: 20px; align-items: center;">
											<div>
												<label style="display: block; font-size: 12px; margin-bottom: 4px;"><?php esc_html_e( 'Arrow Icon Color', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
												<input type="text" name="wpvg_arrow_color" value="<?php echo esc_attr( $arrow_color ); ?>" class="wpvg-color-picker">
											</div>
											<div>
												<label style="display: block; font-size: 12px; margin-bottom: 4px;"><?php esc_html_e( 'Background Color', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
												<input type="text" name="wpvg_arrow_bg" value="<?php echo esc_attr( $arrow_bg ); ?>" class="wpvg-color-picker">
											</div>
										</div>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="wpvg_custom_css"><?php esc_html_e( 'Custom CSS', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
									</th>
									<td>
										<div class="wpvg-custom-css-editor">
											<textarea name="wpvg_custom_css" id="wpvg_custom_css" rows="14" class="large-text code wpvg-custom-css-textarea"><?php echo esc_textarea( $custom_css_setting ); ?></textarea>
										</div>
										<p class="description"><?php esc_html_e( 'Optional CSS for product gallery pages (syntax highlighting editor). Prefer selectors under .wpvg-gallery-container. Paste CSS rules only — no style HTML tags.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></p>
									</td>
								</tr>
							</table>
						</div>

						<div class="wpvg-settings-card" style="margin-top: 20px; background: #f8fafc; border: 1px solid #e2e8f0;">
							<h3 style="margin-top: 0; display: flex; align-items: center; gap: 8px;">
								<span class="dashicons dashicons-layout" style="color: #2271b1;"></span>
								<?php esc_html_e( 'Page Builder & Universal Shortcode Support', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
							</h3>
							<p><?php esc_html_e( 'Use this shortcode in any Page Builder (Elementor, Divi, WPBakery, Bricks, Oxygen, Beaver Builder, or Gutenberg Blocks) to display the video gallery anywhere:', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></p>
							<div style="background: #ffffff; padding: 12px 16px; border: 1px solid #cbd5e1; border-radius: 6px; font-family: monospace; font-size: 13px; display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px;">
								<code>[nexura_product_video_gallery]</code>
								<span style="color: #64748b; font-size: 12px;"><?php esc_html_e( 'Auto-detects current product', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></span>
							</div>
							<p class="description">
								<strong><?php esc_html_e( 'Supported Attributes:', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></strong>
								<code>id="123"</code> |
								<code>layout="vertical_left|horizontal"</code> |
								<code>ratio="4_5|1_1"</code> |
								<code>zoom="inner|disabled"</code> |
								<code>thumbs_count="4"</code> |
								<code>autoplay="yes|no"</code> |
								<code>loop="yes|no"</code> |
								<code>mute="muted|unmuted"</code> |
								<code>width="100%"</code>
							</p>
						</div>
					</div>

					<!-- ======================================================================
					     Tab 2: Image Magnifier & Zoom Effects
					     ====================================================================== -->
					<div id="tab-zoom" class="wpvg-tab-content" style="display: none;">
						<div class="wpvg-settings-card">
							<h2><?php esc_html_e( 'Image Magnifier & Zoom Engine', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></h2>
							<p class="wpvg-section-intro"><?php esc_html_e( 'Configure high-resolution image inspection: Inner Pan-Zoom, Magnifying Lens, or Amazon-Style Side Window.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></p>

							<table class="form-table">
								<tr>
									<th scope="row"><?php esc_html_e( 'Enable Image Zoom', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></th>
									<td>
										<label>
											<input type="checkbox" name="wpvg_show_zoom" id="wpvg_show_zoom" value="yes" <?php checked( $zoom, 'yes' ); ?>>
											<strong><?php esc_html_e( 'Enable interactive image magnification on product images', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></strong>
										</label>
										<p class="description"><?php esc_html_e( 'Allows customers to inspect fine fabric, textures, and details.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></p>
									</td>
								</tr>
								<tr class="wpvg-zoom-sub-setting">
									<th scope="row">
										<label for="wpvg_zoom_type"><?php esc_html_e( 'Magnifier Mode', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
									</th>
									<td>
										<select name="wpvg_zoom_type" id="wpvg_zoom_type" class="<?php echo $show_locks ? 'wpvg-select-with-pro' : ''; ?>">
											<option value="inner" <?php selected( $zoom_type, 'inner' ); ?>><?php esc_html_e( 'Inner Zoom (Smooth Pan-Zoom inside image box - Clean & Minimal)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
											<?php if ( $show_pro_ui ) : ?>
											<option value="lens" data-pro="true" <?php selected( $zoom_type, 'lens' ); ?>><?php esc_html_e( 'Lens Zoom (Magnifying Glass Lens follows cursor - Great for Jewelry & Watches)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?><?php echo esc_html( $pro_lock ); ?></option>
											<option value="window" data-pro="true" <?php selected( $zoom_type, 'window' ); ?>><?php esc_html_e( 'Side Window Zoom (Amazon-Style separate high-res popup window)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?><?php echo esc_html( $pro_lock ); ?></option>
											<?php endif; ?>
										</select>
										<p class="description"><?php echo $show_pro_ui ? esc_html__( 'Choose Inner, Lens, or Side Window magnification.', 'nexura-product-video-media-gallery-for-woocommerce' ) : esc_html__( 'Smooth pan-zoom inside the main image area.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></p>
									</td>
								</tr>
								<tr class="wpvg-zoom-sub-setting">
									<th scope="row">
										<label for="wpvg_zoom_level"><?php esc_html_e( 'Zoom Magnification Level', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
									</th>
									<td>
										<input type="number" min="1.2" max="4.5" step="0.1" name="wpvg_zoom_level" id="wpvg_zoom_level" value="<?php echo esc_attr( $zoom_level ); ?>" class="small-text">
										<span>x</span>
										<p class="description"><?php esc_html_e( 'Magnification factor for zoom (Default: 2.2x). Recommended: 2.0x to 3.0x.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></p>
									</td>
								</tr>
								<tr class="wpvg-zoom-sub-setting">
									<th scope="row">
										<label for="wpvg_zoom_mode"><?php esc_html_e( 'Zoom Trigger Action', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
									</th>
									<td>
										<select name="wpvg_zoom_mode" id="wpvg_zoom_mode">
											<option value="both" <?php selected( $zoom_mode, 'both' ); ?>><?php esc_html_e( 'Mouse Hover & Click to Zoom (Both Enabled)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
											<option value="hover" <?php selected( $zoom_mode, 'hover' ); ?>><?php esc_html_e( 'Mouse Hover Only (Move mouse over image to inspect)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
											<option value="click" <?php selected( $zoom_mode, 'click' ); ?>><?php esc_html_e( 'Click to Zoom Only (Click image/icon to toggle zoom in & out)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
										</select>
									</td>
								</tr>

								<?php if ( $show_pro_ui ) : ?>
								<!-- Lens Specific Options (Pro package) -->
								<tr class="wpvg-zoom-sub-setting wpvg-lens-row <?php echo $show_locks ? 'wpvg-pro-locked-row' : ''; ?>">
									<th scope="row">
										<label for="wpvg_lens_shape">
											<?php esc_html_e( 'Lens Shape', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
											<?php if ( $show_locks ) : ?><span class="wpvg-pro-badge">PRO</span><?php endif; ?>
										</label>
									</th>
									<td>
										<select name="wpvg_lens_shape" id="wpvg_lens_shape" class="<?php echo $show_locks ? 'wpvg-select-with-pro' : ''; ?>">
											<option value="circle" <?php echo $show_locks ? 'data-pro="true" data-feature="' . esc_attr__( 'Circular Lens Magnifier', 'nexura-product-video-media-gallery-for-woocommerce' ) . '"' : ''; ?> <?php selected( $lens_shape, 'circle' ); ?>><?php esc_html_e( 'Circular Lens (Classic round magnifying glass)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?><?php echo esc_html( $pro_lock ); ?></option>
											<option value="square" <?php echo $show_locks ? 'data-pro="true" data-feature="' . esc_attr__( 'Square Lens Magnifier', 'nexura-product-video-media-gallery-for-woocommerce' ) . '"' : ''; ?> <?php selected( $lens_shape, 'square' ); ?>><?php esc_html_e( 'Square Lens (Modern square inspection box)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?><?php echo esc_html( $pro_lock ); ?></option>
										</select>
									</td>
								</tr>
								<tr class="wpvg-zoom-sub-setting wpvg-lens-row <?php echo $show_locks ? 'wpvg-pro-locked-row' : ''; ?>">
									<th scope="row">
										<label for="wpvg_lens_size">
											<?php esc_html_e( 'Lens Size (Diameter)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
											<?php if ( $show_locks ) : ?><span class="wpvg-pro-badge">PRO</span><?php endif; ?>
										</label>
									</th>
									<td>
										<input type="number" min="100" max="350" step="1" name="wpvg_lens_size" id="wpvg_lens_size" value="<?php echo esc_attr( $lens_size ); ?>" class="small-text<?php echo $show_locks ? ' wpvg-input-with-pro' : ''; ?>" <?php echo $show_locks ? 'data-pro="true" data-feature="' . esc_attr__( 'Lens Size Diameter Control', 'nexura-product-video-media-gallery-for-woocommerce' ) . '"' : ''; ?>>
										<span>px</span>
										<p class="description"><?php esc_html_e( 'Width & height of the lens in pixels (Default: 180px).', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></p>
									</td>
								</tr>

								<!-- Side Window Specific Options (Pro package) -->
								<tr class="wpvg-zoom-sub-setting wpvg-window-row <?php echo $show_locks ? 'wpvg-pro-locked-row' : ''; ?>">
									<th scope="row">
										<label for="wpvg_zoom_window_pos">
											<?php esc_html_e( 'Side Window Position', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
											<?php if ( $show_locks ) : ?><span class="wpvg-pro-badge">PRO</span><?php endif; ?>
										</label>
									</th>
									<td>
										<select name="wpvg_zoom_window_pos" id="wpvg_zoom_window_pos" class="<?php echo $show_locks ? 'wpvg-select-with-pro' : ''; ?>">
											<option value="right" <?php echo $show_locks ? 'data-pro="true" data-feature="' . esc_attr__( 'Side Window Zoom', 'nexura-product-video-media-gallery-for-woocommerce' ) . '"' : ''; ?> <?php selected( $window_pos, 'right' ); ?>><?php esc_html_e( 'Right Side (Amazon Standard - Recommended)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?><?php echo esc_html( $pro_lock ); ?></option>
											<option value="left" <?php echo $show_locks ? 'data-pro="true" data-feature="' . esc_attr__( 'Side Window Zoom', 'nexura-product-video-media-gallery-for-woocommerce' ) . '"' : ''; ?> <?php selected( $window_pos, 'left' ); ?>><?php esc_html_e( 'Left Side', 'nexura-product-video-media-gallery-for-woocommerce' ); ?><?php echo esc_html( $pro_lock ); ?></option>
										</select>
									</td>
								</tr>
								<tr class="wpvg-zoom-sub-setting wpvg-window-row <?php echo $show_locks ? 'wpvg-pro-locked-row' : ''; ?>">
									<th scope="row">
										<label for="wpvg_zoom_window_width">
											<?php esc_html_e( 'Side Window Width', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
											<?php if ( $show_locks ) : ?><span class="wpvg-pro-badge">PRO</span><?php endif; ?>
										</label>
									</th>
									<td>
										<input type="number" min="250" max="700" step="1" name="wpvg_zoom_window_width" id="wpvg_zoom_window_width" value="<?php echo esc_attr( $window_width ); ?>" class="small-text<?php echo $show_locks ? ' wpvg-input-with-pro' : ''; ?>" <?php echo $show_locks ? 'data-pro="true" data-feature="' . esc_attr__( 'Side Window Width Control', 'nexura-product-video-media-gallery-for-woocommerce' ) . '"' : ''; ?>>
										<span>px</span>
									</td>
								</tr>
								<tr class="wpvg-zoom-sub-setting wpvg-window-row <?php echo $show_locks ? 'wpvg-pro-locked-row' : ''; ?>">
									<th scope="row">
										<label for="wpvg_zoom_window_height">
											<?php esc_html_e( 'Side Window Height', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
											<?php if ( $show_locks ) : ?><span class="wpvg-pro-badge">PRO</span><?php endif; ?>
										</label>
									</th>
									<td>
										<input type="number" min="250" max="700" step="1" name="wpvg_zoom_window_height" id="wpvg_zoom_window_height" value="<?php echo esc_attr( $window_height ); ?>" class="small-text<?php echo $show_locks ? ' wpvg-input-with-pro' : ''; ?>" <?php echo $show_locks ? 'data-pro="true" data-feature="' . esc_attr__( 'Side Window Height Control', 'nexura-product-video-media-gallery-for-woocommerce' ) . '"' : ''; ?>>
										<span>px</span>
									</td>
								</tr>
								<?php endif; // $show_pro_ui lens/window. ?>
							</table>
						</div>

						<!-- Fullscreen Lightbox Modal Settings -->
						<div class="wpvg-settings-card" style="margin-top: 24px;">
							<h2><?php esc_html_e( 'Fullscreen Lightbox Modal (Etsy-Style Split View)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></h2>
							<table class="form-table">
								<tr>
									<th scope="row"><?php esc_html_e( 'Fullscreen Lightbox', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></th>
									<td>
										<label>
											<input type="checkbox" name="wpvg_show_lightbox" value="yes" <?php checked( $lightbox, 'yes' ); ?>>
											<?php esc_html_e( 'Enable fullscreen modal popup on image click or zoom trigger', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
										</label>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="wpvg_modal_pattern">
											<?php esc_html_e( 'Lightbox Photo Pattern', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
										</label>
									</th>
									<td>
										<select name="wpvg_modal_pattern" id="wpvg_modal_pattern" class="<?php echo $show_locks ? 'wpvg-select-with-pro' : ''; ?>">
											<option value="mosaic_2_1" <?php selected( $modal_pattern, 'mosaic_2_1' ); ?>><?php esc_html_e( 'Mosaic Grid (2 - 1 - 2 - 1 Alternating Pattern)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
											<option value="grid_2col" <?php selected( $modal_pattern, 'grid_2col' ); ?>><?php esc_html_e( '2-Column Grid (2 - 2 - 2 Pattern)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
											<?php if ( $show_pro_ui ) : ?>
												<option value="grid_1col" <?php echo $show_locks ? 'data-pro="true" data-feature="' . esc_attr__( '1-Column Stacked Lightbox Pattern', 'nexura-product-video-media-gallery-for-woocommerce' ) . '"' : ''; ?> <?php selected( $modal_pattern, 'grid_1col' ); ?>><?php esc_html_e( '1-Column Stacked (1 - 1 - 1 Pattern)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?><?php echo esc_html( $pro_lock ); ?></option>
												<option value="hero_2col" <?php echo $show_locks ? 'data-pro="true" data-feature="' . esc_attr__( 'Hero Top Lightbox Pattern', 'nexura-product-video-media-gallery-for-woocommerce' ) . '"' : ''; ?> <?php selected( $modal_pattern, 'hero_2col' ); ?>><?php esc_html_e( 'Hero Top + 2-Column Grid (1 - 2 - 2 Pattern)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?><?php echo esc_html( $pro_lock ); ?></option>
											<?php endif; ?>
										</select>
										<p class="description"><?php esc_html_e( 'Choose the photo gallery grid pattern in the zoom lightbox side panel.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></p>
									</td>
								</tr>
								<?php if ( $show_pro_ui ) : ?>
								<tr class="<?php echo $show_locks ? 'wpvg-pro-locked-row' : ''; ?>">
									<th scope="row">
										<label for="wpvg_modal_position">
											<?php esc_html_e( 'Lightbox Panel Position', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
											<?php if ( $show_locks ) : ?><span class="wpvg-pro-badge">PRO</span><?php endif; ?>
										</label>
									</th>
									<td>
										<select name="wpvg_modal_position" id="wpvg_modal_position" class="<?php echo $show_locks ? 'wpvg-select-with-pro' : ''; ?>">
											<option value="right" <?php selected( $modal_pos, 'right' ); ?>><?php esc_html_e( 'Right Side (Recommended)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
											<option value="left" <?php echo $show_locks ? 'data-pro="true" data-feature="' . esc_attr__( 'Lightbox Panel Position', 'nexura-product-video-media-gallery-for-woocommerce' ) . '"' : ''; ?> <?php selected( $modal_pos, 'left' ); ?>><?php esc_html_e( 'Left Side', 'nexura-product-video-media-gallery-for-woocommerce' ); ?><?php echo esc_html( $pro_lock ); ?></option>
											<option value="bottom" <?php echo $show_locks ? 'data-pro="true" data-feature="' . esc_attr__( 'Lightbox Panel Position', 'nexura-product-video-media-gallery-for-woocommerce' ) . '"' : ''; ?> <?php selected( $modal_pos, 'bottom' ); ?>><?php esc_html_e( 'Bottom Side', 'nexura-product-video-media-gallery-for-woocommerce' ); ?><?php echo esc_html( $pro_lock ); ?></option>
										</select>
										<p class="description"><?php esc_html_e( 'Choose where the lightbox photo panel appears relative to the main preview.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></p>
									</td>
								</tr>
								<?php endif; ?>
								<tr>
									<th scope="row">
										<label for="wpvg_modal_thumb_size">
											<?php esc_html_e( 'Lightbox Thumbnail Size', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
										</label>
									</th>
									<td>
										<input type="number" min="30" max="120" step="2" name="wpvg_modal_thumb_size" id="wpvg_modal_thumb_size" value="<?php echo esc_attr( $modal_thumb_size ); ?>" class="small-text">
										<span><?php esc_html_e( 'px', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></span>
										<p class="description"><?php esc_html_e( 'Width & height in pixels for the thumbnails in the fullscreen lightbox modal bottom panel (default: 48px). Increase to make thumbnails bigger or decrease to make them smaller.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></p>
									</td>
								</tr>
							</table>
						</div>
					</div>

					<!-- ======================================================================
					     Tab 3: Video Player & Display Settings
					     ====================================================================== -->
					<div id="tab-video" class="wpvg-tab-content" style="display: none;">
						<div class="wpvg-settings-card">
							<h2><?php esc_html_e( 'Video Playback & Placement', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></h2>
							<p class="wpvg-section-intro"><?php esc_html_e( 'Configure video playback, display position in gallery, audio defaults, and rich SEO snippets.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></p>

							<table class="form-table">
								<tr>
									<th scope="row">
										<label for="wpvg_video_placement"><?php esc_html_e( 'Video Display Position', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
									</th>
									<td>
										<select name="wpvg_video_placement" id="wpvg_video_placement" class="<?php echo $show_locks ? 'wpvg-select-with-pro' : ''; ?>">
											<optgroup label="<?php esc_attr_e( 'Placement Positions', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>">
												<option value="second" <?php selected( $video_place, 'second' ); ?>><?php esc_html_e( '2nd Position (After Featured Image - Recommended)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
												<option value="first" <?php selected( $video_place, 'first' ); ?>><?php esc_html_e( '1st Position (Show Video First)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
											</optgroup>
											<?php if ( $show_pro_ui ) : ?>
											<optgroup label="<?php esc_attr_e( 'Additional Positions', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>">
												<option value="third" <?php selected( $video_place, 'third' ); ?>><?php esc_html_e( '3rd Position (After 2nd Image)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
												<option value="last" <?php selected( $video_place, 'last' ); ?>><?php esc_html_e( 'Last Position (At End of All Photos)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
											</optgroup>
											<?php endif; ?>
										</select>
										<p class="description"><?php esc_html_e( 'Choose which slide position the video appears in relative to product photos.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="wpvg_video_autoplay"><?php esc_html_e( 'Autoplay Video on Click', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
									</th>
									<td>
										<select name="wpvg_video_autoplay" id="wpvg_video_autoplay">
											<option value="no" <?php selected( $video_autoplay, 'no' ); ?>><?php esc_html_e( 'Disabled (Off - Click play button on video)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
											<option value="yes" <?php selected( $video_autoplay, 'yes' ); ?>><?php esc_html_e( 'Enabled (On - Autoplay immediately when selected)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="wpvg_video_mute"><?php esc_html_e( 'Initial Video Audio', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
									</th>
									<td>
										<select name="wpvg_video_mute" id="wpvg_video_mute">
											<option value="unmuted" <?php selected( $video_mute, 'unmuted' ); ?>><?php esc_html_e( 'Sound On (Browser fallback to Muted if blocked)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
											<option value="muted" <?php selected( $video_mute, 'muted' ); ?>><?php esc_html_e( 'Always Start Muted (Recommended for silent preview)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
										</select>
										<p class="description"><?php esc_html_e( 'Browsers may require muted audio for automatic video playback.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="wpvg_video_loop">
											<?php esc_html_e( 'Loop Video Playback', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
										</label>
									</th>
									<td>
										<select name="wpvg_video_loop" id="wpvg_video_loop">
											<option value="no" <?php selected( $video_loop, 'no' ); ?>><?php esc_html_e( 'Disabled (Play once & stop)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
											<option value="yes" <?php selected( $video_loop, 'yes' ); ?>><?php esc_html_e( 'Enabled (Loop continuously)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
										</select>
										<p class="description"><?php esc_html_e( 'Continuously repeat product videos once they finish playing.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<?php esc_html_e( 'Video SEO Schema', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
									</th>
									<td>
										<label>
											<input type="checkbox" name="wpvg_video_schema" value="yes" <?php checked( 'yes', $video_schema ); ?>>
											<?php esc_html_e( 'Automatically generate Schema.org VideoObject JSON-LD for Google Search ranking', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
										</label>
									</td>
								</tr>
							</table>
						</div>
					</div>

					<!-- ======================================================================
					     Tab 4: Mobile & Touch Settings
					     ====================================================================== -->
					<div id="tab-responsive" class="wpvg-tab-content" style="display: none;">
						<div class="wpvg-settings-card">
							<h2><?php esc_html_e( 'Mobile & Touch Responsiveness', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></h2>
							<p class="wpvg-section-intro"><?php esc_html_e( 'Fine-tune mobile layout formats, swipe behaviors, and touch safeguards.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></p>

							<table class="form-table">
								<tr>
									<th scope="row">
										<label for="wpvg_mobile_layout"><?php esc_html_e( 'Mobile Gallery Layout', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
									</th>
									<td>
										<select name="wpvg_mobile_layout" id="wpvg_mobile_layout" class="<?php echo $show_locks ? 'wpvg-select-with-pro' : ''; ?>">
											<option value="bottom_horizontal" <?php selected( $mobile_layout, 'bottom_horizontal' ); ?>><?php esc_html_e( 'Horizontal Thumbnails at Bottom (Default - Recommended)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
											<option value="keep_desktop" <?php selected( $mobile_layout, 'keep_desktop' ); ?>><?php esc_html_e( 'Keep Desktop Layout (Do not reorder thumbnails)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
											<?php if ( $show_pro_ui ) : ?>
											<option value="dots_only" <?php selected( $mobile_layout, 'dots_only' ); ?>><?php esc_html_e( 'Dots Only (Hide Thumbnails on Mobile, Show Swipe Dots Only)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
											<?php endif; ?>
										</select>
										<p class="description"><?php esc_html_e( 'Choose how the gallery adapts on small screens.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="wpvg_mobile_breakpoint"><?php esc_html_e( 'Mobile Breakpoint (px)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
									</th>
									<td>
										<input type="number" name="wpvg_mobile_breakpoint" id="wpvg_mobile_breakpoint" value="<?php echo esc_attr( $mobile_breakpoint ); ?>" min="320" max="1200" step="1" class="small-text"> px
										<p class="description"><?php esc_html_e( 'Screen width in pixels where mobile optimizations trigger (default: 768px for tablet/mobile).', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="wpvg_mobile_disable_zoom"><?php esc_html_e( 'Mobile Image Zoom', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
									</th>
									<td>
										<select name="wpvg_mobile_disable_zoom" id="wpvg_mobile_disable_zoom">
											<option value="yes" <?php selected( $mobile_disable_zoom, 'yes' ); ?>><?php esc_html_e( 'Disable Pan-Zoom on Mobile (Prevents Accidental Zooming While Scrolling - Recommended)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
											<option value="no" <?php selected( $mobile_disable_zoom, 'no' ); ?>><?php esc_html_e( 'Enable Touch Pan-Zoom on Mobile', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
										</select>
										<p class="description"><?php esc_html_e( 'Disabling on-page pan-zoom on mobile allows natural page scrolling. Customers can still tap to view the full resolution in the Fullscreen Lightbox.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="wpvg_mobile_thumbs_count">
											<?php esc_html_e( 'Mobile Thumbnails to Show', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
										</label>
									</th>
									<td>
										<input type="number" name="wpvg_mobile_thumbs_count" id="wpvg_mobile_thumbs_count" value="<?php echo esc_attr( $mobile_thumbs_count ); ?>" min="2" max="6" step="1" class="small-text">
										<p class="description"><?php esc_html_e( 'Number of thumbnails visible at once in the mobile bottom strip (default: 4).', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="wpvg_mobile_hide_arrows">
											<?php esc_html_e( 'Mobile Nav Arrows', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
										</label>
									</th>
									<td>
										<select name="wpvg_mobile_hide_arrows" id="wpvg_mobile_hide_arrows">
											<option value="no" <?php selected( $mobile_hide_arrows, 'no' ); ?>><?php esc_html_e( 'Show Prev / Next Arrows on Mobile', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
											<option value="yes" <?php selected( $mobile_hide_arrows, 'yes' ); ?>><?php esc_html_e( 'Hide Arrows on Mobile (Clean Swipe UI - Recommended)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
										</select>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="wpvg_mobile_show_dots">
											<?php esc_html_e( 'Mobile Pagination Dots', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
										</label>
									</th>
									<td>
										<select name="wpvg_mobile_show_dots" id="wpvg_mobile_show_dots">
											<option value="yes" <?php selected( $mobile_show_dots, 'yes' ); ?>><?php esc_html_e( 'Show Pagination Dots on Mobile (Recommended)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
											<option value="no" <?php selected( $mobile_show_dots, 'no' ); ?>><?php esc_html_e( 'Hide Pagination Dots on Mobile', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
										</select>
									</td>
								</tr>
							</table>
						</div>
					</div>

					<?php if ( $show_pro_ui ) : ?>
					<!-- ======================================================================
					     Tab 5: Shop Archive & Product Listing Video [PRO]
					     ====================================================================== -->
					<div id="tab-archive" class="wpvg-tab-content" style="display: none;">
						<div class="wpvg-settings-card">
							<h2><?php esc_html_e( 'Shop Archive & Product Listing Videos', 'nexura-product-video-media-gallery-for-woocommerce' ); ?><?php echo esc_html( $pro_lock ); ?></h2>
							<p class="wpvg-section-intro"><?php esc_html_e( 'Showcase interactive product videos directly on your shop catalog, category archives, and related product loops to skyrocket click-through rates and conversions.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></p>

							<table class="form-table">
								<tr>
									<th scope="row">
										<label for="wpvg_archive_video_enable">
											<?php esc_html_e( 'Enable Archive Videos', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
											<?php if ( $show_locks ) : ?><span class="wpvg-pro-badge">PRO</span><?php endif; ?>
										</label>
									</th>
									<td>
										<select name="wpvg_archive_video_enable" id="wpvg_archive_video_enable" class="wpvg-select-with-pro">
											<option value="no" <?php selected( $archive_enable, 'no' ); ?>><?php esc_html_e( 'Disabled (Standard Product Images Only)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
											<option value="yes" data-pro="true" data-feature="<?php esc_attr_e( 'Shop Archive Video Showcase', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>" <?php selected( $archive_enable, 'yes' ); ?>><?php esc_html_e( 'Enabled (Showcase Videos in Shop Catalog)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?><?php echo esc_html( $pro_lock ); ?></option>
										</select>
										<p class="description"><?php esc_html_e( 'Activate video previews across product catalog grids.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="wpvg_archive_video_style">
											<?php esc_html_e( 'Archive Display Style', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
											<?php if ( $show_locks ) : ?><span class="wpvg-pro-badge">PRO</span><?php endif; ?>
										</label>
									</th>
									<td>
										<select name="wpvg_archive_video_style" id="wpvg_archive_video_style" class="wpvg-select-with-pro">
											<option value="hover_autoplay" data-pro="true" data-feature="<?php esc_attr_e( 'Hover Autoplay Video Style', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>" <?php selected( $archive_style, 'hover_autoplay' ); ?>><?php esc_html_e( 'Style 1: Autoplay on Hover (Smooth Muted Video Preview)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?><?php echo esc_html( $pro_lock ); ?></option>
											<option value="click_popup" data-pro="true" data-feature="<?php esc_attr_e( 'Floating Play Badge with Lightbox', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>" <?php selected( $archive_style, 'click_popup' ); ?>><?php esc_html_e( 'Style 2: Floating Play Badge with Quick Lightbox Modal', 'nexura-product-video-media-gallery-for-woocommerce' ); ?><?php echo esc_html( $pro_lock ); ?></option>
											<option value="inline_player" data-pro="true" data-feature="<?php esc_attr_e( 'Inline Card Video Player Style', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>" <?php selected( $archive_style, 'inline_player' ); ?>><?php esc_html_e( 'Style 3: Inline Video Card (Embedded in Catalog Grid)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?><?php echo esc_html( $pro_lock ); ?></option>
										</select>
										<p class="description"><?php esc_html_e( 'Choose how customers interact with product videos in the shop catalog.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="wpvg_archive_video_scope">
											<?php esc_html_e( 'Video Scope (Single / Multiple)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
											<?php if ( $show_locks ) : ?><span class="wpvg-pro-badge">PRO</span><?php endif; ?>
										</label>
									</th>
									<td>
										<select name="wpvg_archive_video_scope" id="wpvg_archive_video_scope" class="wpvg-select-with-pro">
											<option value="single" data-pro="true" data-feature="<?php esc_attr_e( 'Single Archive Video Showcase', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>" <?php selected( $archive_scope, 'single' ); ?>><?php esc_html_e( 'Single Video (Showcase 1st Product Video)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?><?php echo esc_html( $pro_lock ); ?></option>
											<option value="multiple" data-pro="true" data-feature="<?php esc_attr_e( 'Multiple Archive Videos Carousel', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>" <?php selected( $archive_scope, 'multiple' ); ?>><?php esc_html_e( 'Multiple Videos (Cycle through all product videos with swipe dots)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?><?php echo esc_html( $pro_lock ); ?></option>
										</select>
										<p class="description"><?php esc_html_e( 'Display either the main product video or allow customers to cycle through multiple videos directly on the catalog card.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></p>
									</td>
								</tr>
								<tr>
									<th scope="row">
										<label for="wpvg_archive_badge_pos">
											<?php esc_html_e( 'Play Badge Position (Style 2)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
											<?php if ( $show_locks ) : ?><span class="wpvg-pro-badge">PRO</span><?php endif; ?>
										</label>
									</th>
									<td>
										<select name="wpvg_archive_badge_pos" id="wpvg_archive_badge_pos" class="wpvg-select-with-pro">
											<option value="bottom_right" data-pro="true" data-feature="<?php esc_attr_e( 'Badge Position', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>" <?php selected( $archive_badge_pos, 'bottom_right' ); ?>><?php esc_html_e( 'Bottom Right (Recommended)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?><?php echo esc_html( $pro_lock ); ?></option>
											<option value="bottom_left" data-pro="true" data-feature="<?php esc_attr_e( 'Badge Position', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>" <?php selected( $archive_badge_pos, 'bottom_left' ); ?>><?php esc_html_e( 'Bottom Left', 'nexura-product-video-media-gallery-for-woocommerce' ); ?><?php echo esc_html( $pro_lock ); ?></option>
											<option value="center" data-pro="true" data-feature="<?php esc_attr_e( 'Badge Position', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>" <?php selected( $archive_badge_pos, 'center' ); ?>><?php esc_html_e( 'Center of Thumbnail', 'nexura-product-video-media-gallery-for-woocommerce' ); ?><?php echo esc_html( $pro_lock ); ?></option>
											<option value="top_right" data-pro="true" data-feature="<?php esc_attr_e( 'Badge Position', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>" <?php selected( $archive_badge_pos, 'top_right' ); ?>><?php esc_html_e( 'Top Right', 'nexura-product-video-media-gallery-for-woocommerce' ); ?><?php echo esc_html( $pro_lock ); ?></option>
										</select>
									</td>
								</tr>
							</table>
						</div>
					</div>

					<!-- ======================================================================
					     Tab: 3D Product Model Viewer [PRO]
					     ====================================================================== -->
					<div id="tab-3d" class="wpvg-tab-content" style="display: none;">
						<div class="wpvg-settings-card">
							<div class="wpvg-section-header">
								<div>
									<h2><?php esc_html_e( '3D Product Model Viewer', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></h2>
									<p class="wpvg-section-intro"><?php esc_html_e( 'Enable ultra-realistic, interactive 360° 3D product inspection. Supports Google <model-viewer> engine, .glb and .gltf files, touch/mouse orbit controls, auto-rotate, and on-demand performance loading.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></p>
								</div>
								<?php if ( ! $is_pro ) : ?>
									<span class="wpvg-plan-badge wpvg-plan-pro"><span class="dashicons dashicons-lock"></span> <?php esc_html_e( 'PRO Feature', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></span>
								<?php endif; ?>
							</div>

							<?php if ( ! $is_pro ) : ?>
								<div class="wpvg-inline-pro-tip" style="margin-bottom: 24px;">
									<span><strong><?php esc_html_e( 'Pro Exclusive Feature:', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></strong> <?php esc_html_e( '3D Interactive Product View, circular 3D thumbnail badges, and fullscreen 3D modal inspection require Nexura Product Video Media Gallery Pro.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></span>
									<a href="<?php echo esc_url( WPVG_Freemius::get_upgrade_url() ); ?>" target="_blank"><?php esc_html_e( 'Unlock 3D Showcase', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></a>
								</div>
							<?php endif; ?>

							<!-- Notice: 3D Standalone Mode & Script Conflict Prevention -->
							<div class="wpvg-admin-info-callout" style="background: #eff6ff; border-left: 4px solid #2563eb; border-radius: 6px; padding: 16px 20px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
								<div style="display: flex; gap: 12px; align-items: flex-start;">
									<span class="dashicons dashicons-info" style="color: #2563eb; font-size: 24px; width: 24px; height: 24px; margin-top: 2px;"></span>
									<div>
										<strong style="color: #1e3a8a; font-size: 14.5px; display: block; margin-bottom: 5px;">
											<?php esc_html_e( 'Notice: Standalone 3D Engine Architecture', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
										</strong>
										<p style="margin: 0 0 6px 0; color: #1e40af; font-size: 13px; line-height: 1.55;">
											<?php esc_html_e( 'When 3D Product Model Viewer is enabled, Product Videos, Custom Gallery Style, and Shop Archive Video stay OFF for those products. 2D/3D Gallery View Mode (switcher) and Live 360° Gallery Spin keep .glb/.gltf Model File OFF on the frontend. Only the dedicated 3D/360 scripts load for maximum smoothness and zero conflicts.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
										</p>
										<p style="margin: 0; color: #2563eb; font-size: 12px; line-height: 1.4; font-weight: 500;">
											<em><?php esc_html_e( '💡 Summary: Default gallery scripts are automatically suppressed when 3D Model Viewer is active to ensure flawless 3D performance and zero script conflicts.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></em>
										</p>
									</div>
								</div>
							</div>

							<table class="form-table wpvg-form-table">
								<!-- 1. Enable Mode -->
								<tr class="wpvg-3d-global-enable-row">
									<th scope="row">
										<label for="wpvg_3d_enable_global"><?php esc_html_e( '3D Viewer Scope', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
									</th>
									<td>
										<select name="wpvg_3d_enable_global" id="wpvg_3d_enable_global" class="wpvg-select-with-pro">
											<option value="per_product" <?php selected( $d3_enable_global, 'per_product' ); ?>><?php esc_html_e( 'Per-Product Only (Active when configured on single product)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
											<option value="all_products" <?php selected( $d3_enable_global, 'all_products' ); ?>><?php esc_html_e( 'Global: All Products (Auto-activates on products with gallery images or 3D model)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
											<option value="disabled" <?php selected( $d3_enable_global, 'disabled' ); ?>><?php esc_html_e( 'Disabled Completely', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
										</select>
										<p class="description"><?php esc_html_e( 'Select whether 3D viewer is configured on individual products or enabled globally across your store.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></p>
									</td>
								</tr>

								<!-- 1.1 3D Engine / Source Mode -->
								<tr class="wpvg-3d-global-source-row" style="<?php echo $g_is_disabled ? 'display:none;' : ''; ?>">
									<th scope="row">
										<label for="wpvg_3d_source_type"><?php esc_html_e( '3D Engine / Source Mode', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
									</th>
									<td>
										<select name="wpvg_3d_source_type" id="wpvg_3d_source_type">
											<option value="auto" <?php selected( $d3_source_type, 'auto' ); ?>><?php esc_html_e( 'Auto-Detect (Use 3D Model file if present, otherwise 360° Gallery Spin)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
											<option value="gallery_360" <?php selected( $d3_source_type, 'gallery_360' ); ?>><?php esc_html_e( '360° Gallery Image Spin (Rotate WooCommerce Product Gallery photos via JavaScript)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
											<option value="model_file" <?php selected( $d3_source_type, 'model_file' ); ?>><?php esc_html_e( '3D Model File (.glb / .gltf with Google Model Viewer)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
										</select>
										<p class="description"><?php esc_html_e( '360° Gallery Image Spin automatically turns multiple gallery photos (shot from different angles) into an interactive 360° 3D turntable using pure JavaScript.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></p>
									</td>
								</tr>

								<!-- 1.2 2D / 3D Gallery View Mode -->
								<tr class="wpvg-3d-global-view-row" style="<?php echo $g_is_disabled ? 'display:none;' : ''; ?>">
									<th scope="row">
										<label for="wpvg_3d_view_mode"><?php esc_html_e( 'Gallery View Mode & Switcher', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
									</th>
									<td>
										<select name="wpvg_3d_view_mode" id="wpvg_3d_view_mode">
											<option value="switcher" <?php selected( $d3_view_mode, 'switcher' ); ?>><?php esc_html_e( '🔄 2D & 3D View Switcher (Show [ 360° 3D View | 2D Photos ] Pill)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
											<option value="3d_only" <?php selected( $d3_view_mode, '3d_only' ); ?>><?php esc_html_e( '🧊 3D View Only (Dedicated 3D Turntable / Model Only, No Switcher)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
											<option value="2d_only" <?php selected( $d3_view_mode, '2d_only' ); ?>><?php esc_html_e( '🖼️ 2D View Only (Standard 2D Photos Gallery Only, No 3D)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
										</select>
										<p class="description"><?php esc_html_e( '2D & 3D View Switcher uses Live 360° gallery frames and keeps .glb / .gltf Model File OFF on the frontend.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></p>
									</td>
								</tr>

								<!-- 1.3 Initial Default View Mode -->
								<tr class="wpvg-3d-global-default-view-row" style="<?php echo $g_is_switcher ? '' : 'display:none;'; ?>">
									<th scope="row">
										<label for="wpvg_3d_default_view"><?php esc_html_e( 'Initial Default View', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
									</th>
									<td>
										<select name="wpvg_3d_default_view" id="wpvg_3d_default_view">
											<option value="3d" <?php selected( $d3_default_view, '3d' ); ?>><?php esc_html_e( '360° 3D View First (Loads with interactive turntable ready)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
											<option value="2d" <?php selected( $d3_default_view, '2d' ); ?>><?php esc_html_e( '2D Photo Gallery First (Loads with standard photos, switcher pill enables 3D)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
										</select>
										<p class="description"><?php esc_html_e( 'Which mode is displayed when the product page first opens.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></p>
									</td>
								</tr>

								<!-- 1.4 360 Spin Speed & Sensitivity -->
								<tr class="wpvg-3d-global-360-row" style="<?php echo $g_is_360_active ? '' : 'display:none;'; ?>">
									<th scope="row">
										<label for="wpvg_3d_spin_speed"><?php esc_html_e( '360° Turntable Auto-Spin Speed', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
									</th>
									<td>
										<select name="wpvg_3d_spin_speed" id="wpvg_3d_spin_speed">
											<option value="fast" <?php selected( $d3_spin_speed, 'fast' ); ?>><?php esc_html_e( 'Fast (140ms per frame)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
											<option value="normal" <?php selected( $d3_spin_speed, 'normal' ); ?>><?php esc_html_e( 'Normal (220ms per frame) - Recommended', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
											<option value="smooth" <?php selected( $d3_spin_speed, 'smooth' ); ?>><?php esc_html_e( 'Smooth / Cinematic (320ms per frame)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
										</select>
										<p class="description"><?php esc_html_e( 'Global speed limit for Live 360° auto-spin. Per-product overrides are available in the product metabox.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></p>
									</td>
								</tr>

								<tr class="wpvg-3d-global-360-row" style="<?php echo $g_is_360_active ? '' : 'display:none;'; ?>">
									<th scope="row">
										<label for="wpvg_3d_spin_sensitivity"><?php esc_html_e( 'Drag / Swipe Sensitivity', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
									</th>
									<td>
										<select name="wpvg_3d_spin_sensitivity" id="wpvg_3d_spin_sensitivity">
											<option value="high" <?php selected( $d3_spin_sens, 'high' ); ?>><?php esc_html_e( 'High Sensitivity (18px per frame)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
											<option value="normal" <?php selected( $d3_spin_sens, 'normal' ); ?>><?php esc_html_e( 'Normal (30px per frame) - Recommended', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
											<option value="smooth" <?php selected( $d3_spin_sens, 'smooth' ); ?>><?php esc_html_e( 'Smooth (44px per frame)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
										</select>
									</td>
								</tr>

								<tr class="wpvg-3d-global-360-row" style="<?php echo $g_is_360_active ? '' : 'display:none;'; ?>">
									<th scope="row">
										<label for="wpvg_3d_spin_direction"><?php esc_html_e( '360° Rotation Drag Direction', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
									</th>
									<td>
										<select name="wpvg_3d_spin_direction" id="wpvg_3d_spin_direction">
											<option value="normal" <?php selected( $d3_spin_dir, 'normal' ); ?>><?php esc_html_e( 'Normal (Drag left rotates left)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
											<option value="reverse" <?php selected( $d3_spin_dir, 'reverse' ); ?>><?php esc_html_e( 'Reverse (Drag left rotates right)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
										</select>
									</td>
								</tr>

								<!-- 2. Loading Mode (On-Demand vs Instant) -->
								<tr class="wpvg-3d-global-common-row" style="<?php echo $g_is_common_active ? '' : 'display:none;'; ?>">
									<th scope="row">
										<label for="wpvg_3d_load_mode"><?php esc_html_e( '3D Loading Experience', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
									</th>
									<td>
										<select name="wpvg_3d_load_mode" id="wpvg_3d_load_mode">
											<option value="on_demand" <?php selected( $d3_load_mode, 'on_demand' ); ?>><?php esc_html_e( '⚡ On-Demand: Click for interactive 3D (Fastest Page Speed)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
											<option value="instant" <?php selected( $d3_load_mode, 'instant' ); ?>><?php esc_html_e( 'Instant: Auto-load 3D Model immediately when slide is shown', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
										</select>
										<p class="description"><?php esc_html_e( 'On-Demand displays a high-res poster image with a centered "Click for interactive 3D" button overlay. Zero 3D memory usage until clicked!', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></p>
									</td>
								</tr>

								<!-- 3. Prompt Button Text -->
								<tr class="wpvg-3d-global-common-row" style="<?php echo $g_is_common_active ? '' : 'display:none;'; ?>">
									<th scope="row">
										<label for="wpvg_3d_overlay_text"><?php esc_html_e( 'Overlay Button Text', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
									</th>
									<td>
										<input type="text" name="wpvg_3d_overlay_text" id="wpvg_3d_overlay_text" class="regular-text" value="<?php echo esc_attr( $d3_overlay_text ); ?>" placeholder="<?php esc_attr_e( 'Click for interactive 3D', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>">
										<p class="description"><?php esc_html_e( 'Text displayed on the centered frosted glass overlay button (default: "Click for interactive 3D").', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></p>
									</td>
								</tr>

								<!-- 4. Auto-Rotate -->
								<tr class="wpvg-3d-global-common-row" style="<?php echo $g_is_common_active ? '' : 'display:none;'; ?>">
									<th scope="row"><?php esc_html_e( 'Auto-Rotate 3D Model', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></th>
									<td>
										<label class="wpvg-switch">
											<input type="checkbox" name="wpvg_3d_auto_rotate" value="yes" <?php checked( $d3_auto_rotate, 'yes' ); ?>>
											<span class="wpvg-slider"></span>
										</label>
										<p class="description"><?php esc_html_e( 'Automatically rotates the 3D model smoothly around its turntable axis when viewed.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></p>
									</td>
								</tr>

								<!-- 5. Camera Controls -->
								<tr class="wpvg-3d-global-common-row" style="<?php echo $g_is_common_active ? '' : 'display:none;'; ?>">
									<th scope="row"><?php esc_html_e( 'Interactive Camera Orbit', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></th>
									<td>
										<label class="wpvg-switch">
											<input type="checkbox" name="wpvg_3d_camera_controls" value="yes" <?php checked( $d3_camera_controls, 'yes' ); ?>>
											<span class="wpvg-slider"></span>
										</label>
										<p class="description"><?php esc_html_e( 'Allow customers to rotate 360° with mouse drag / touch swipe, and zoom in/out with scroll wheel / pinch.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></p>
									</td>
								</tr>

								<!-- 6. Thumbnail 3D Badge -->
								<tr class="wpvg-3d-global-common-row" style="<?php echo $g_is_common_active ? '' : 'display:none;'; ?>">
									<th scope="row"><?php esc_html_e( 'Thumbnail 3D Badge', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></th>
									<td>
										<label class="wpvg-switch">
											<input type="checkbox" name="wpvg_3d_show_badge" value="yes" <?php checked( $d3_show_badge, 'yes' ); ?>>
											<span class="wpvg-slider"></span>
										</label>
										<p class="description"><?php esc_html_e( 'Display the circular rotating 3D icon badge on the gallery thumbnail.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></p>
									</td>
								</tr>

								<!-- 7. Lightbox Modal 3D -->
								<tr class="wpvg-3d-global-common-row" style="<?php echo $g_is_common_active ? '' : 'display:none;'; ?>">
									<th scope="row"><?php esc_html_e( '3D in Fullscreen Lightbox', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></th>
									<td>
										<label class="wpvg-switch">
											<input type="checkbox" name="wpvg_3d_in_lightbox" value="yes" <?php checked( $d3_in_lightbox, 'yes' ); ?>>
											<span class="wpvg-slider"></span>
										</label>
										<p class="description"><?php esc_html_e( 'Enable full-screen interactive 3D model view inside the lightbox popup modal.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></p>
									</td>
								</tr>

								<!-- 8. Background Color -->
								<tr class="wpvg-3d-global-common-row" style="<?php echo $g_is_common_active ? '' : 'display:none;'; ?>">
									<th scope="row">
										<label for="wpvg_3d_bg_color"><?php esc_html_e( 'Viewer Background', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
									</th>
									<td>
										<input type="text" name="wpvg_3d_bg_color" id="wpvg_3d_bg_color" class="wpvg-color-field" value="<?php echo esc_attr( $d3_bg_color ); ?>" data-default-color="transparent">
										<p class="description"><?php esc_html_e( 'Background color behind the 3D model canvas (leave transparent or enter a hex color e.g. #ffffff).', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></p>
									</td>
								</tr>

								<!-- 9. Thumbnails Scroll & Autoplay -->
								<tr class="wpvg-3d-global-common-row" style="<?php echo $g_is_common_active ? '' : 'display:none;'; ?>">
									<th scope="row"><?php esc_html_e( 'Thumbnails Scroll & Autoplay', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></th>
									<td>
										<label class="wpvg-switch">
											<input type="checkbox" name="wpvg_3d_slider_autoplay" value="yes" <?php checked( $d3_slider_autoplay, 'yes' ); ?>>
											<span class="wpvg-slider"></span>
										</label>
										<p class="description"><?php esc_html_e( 'Enable automatic slide transitions and thumbnail scrolling when 3D product view is active (Default: Off to let customers freely interact with 3D).', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></p>
									</td>
								</tr>

								<!-- 10. Lightbox Thumbnail Size for 3D Viewer -->
								<tr class="wpvg-3d-global-common-row" style="<?php echo $g_is_common_active ? '' : 'display:none;'; ?>">
									<th scope="row">
										<label for="wpvg_3d_modal_thumb_size"><?php esc_html_e( 'Lightbox Thumbnail Size', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
									</th>
									<td>
										<input type="number" min="30" max="120" step="2" name="wpvg_3d_modal_thumb_size" id="wpvg_3d_modal_thumb_size" value="<?php echo esc_attr( $d3_modal_thumb_size ? $d3_modal_thumb_size : '' ); ?>" placeholder="48" class="small-text">
										<span><?php esc_html_e( 'px', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></span>
										<p class="description"><?php esc_html_e( 'Adjust thumbnail size in the lightbox modal bottom strip for 3D products (leave blank to follow global Lightbox size e.g. 48px). Increase to make bigger, decrease to make smaller.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></p>
									</td>
								</tr>

								<!-- 11. Global Fallback 3D Model URL (hidden when Live 360 / 2D↔3D switcher) -->
								<tr class="wpvg-3d-global-model-row" style="<?php echo $g_is_model_active ? '' : 'display:none;'; ?>">
									<th scope="row">
										<label for="wpvg_3d_default_model_url"><?php esc_html_e( 'Default 3D Model URL (.glb / .gltf)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
									</th>
									<td>
										<div style="display: flex; gap: 8px; align-items: center; max-width: 650px;">
											<input type="url" name="wpvg_3d_default_model_url" id="wpvg_3d_default_model_url" class="regular-text" style="flex: 1;" value="<?php echo esc_url( $d3_default_model ); ?>" placeholder="https://example.com/models/sample.glb">
											<button type="button" class="button wpvg-btn-use-sample-model" data-sample-url="<?php echo esc_url( class_exists( 'WPVG_3D_Viewer' ) && method_exists( 'WPVG_3D_Viewer', 'get_sample_model_url' ) ? WPVG_3D_Viewer::get_sample_model_url() : plugins_url( 'assets/models/DamagedHelmet.glb', dirname( __DIR__ ) ) ); ?>" onclick="
												var url = this.getAttribute('data-sample-url');
												var inp = document.getElementById('wpvg_3d_default_model_url') || document.querySelector('input[name=\'wpvg_3d_default_model_url\']');
												if (inp) {
													inp.value = url;
													if (window.jQuery) { jQuery(inp).trigger('input').trigger('change'); }
												}
											">
												<span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Load Demo Model', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
											</button>
										</div>
										<p class="description"><?php esc_html_e( 'Only used when Source Mode is 3D Model File. Hidden/ignored for Live 360° Gallery Spin and 2D/3D Gallery View Mode.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></p>
									</td>
								</tr>
							</table>
						</div>
					</div>
					<?php endif; // $show_pro_ui archive + 3D tabs. ?>

					<p class="submit wpvg-settings-submit" style="margin-top: 20px;">
						<input type="submit" name="wpvg_save_settings" id="submit" class="button button-primary button-large" value="<?php esc_attr_e( 'Save Changes', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>">
					</p>
				</form>
			</div>
			<?php
		}
	}
}
