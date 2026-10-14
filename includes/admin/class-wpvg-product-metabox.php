<?php
/**
 * Nexura Product Video Media Gallery for WooCommerce - Metabox Handler
 *
 * @package Nexura_Product_Video_Media_Gallery_For_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPVG_Product_Metabox' ) ) {
	class WPVG_Product_Metabox {

		/**
		 * Constructor
		 */
		public function __construct() {
			add_action( 'add_meta_boxes', array( $this, 'register_metabox' ) );
			add_action( 'save_post_product', array( $this, 'save_metabox' ), 10, 2 );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_metabox_assets' ) );

			// Product Variation Video Hooks
			add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'render_variation_fields' ), 10, 3 );
			add_action( 'woocommerce_save_product_variation', array( $this, 'save_variation_fields' ), 10, 2 );

			// Filter script tag to ensure model-viewer is loaded as an ES module
			add_filter( 'script_loader_tag', array( $this, 'filter_model_viewer_script_tag' ), 10, 3 );
		}

		/**
		 * Filter script tag to ensure model-viewer is loaded as an ES module in admin.
		 *
		 * @param string $tag    Script tag.
		 * @param string $handle Script handle.
		 * @param string $src    Script source.
		 * @return string
		 */
		public function filter_model_viewer_script_tag( $tag, $handle, $src ) {
			if ( 'wpvg-google-model-viewer' === $handle && false === strpos( $tag, 'type="module"' ) ) {
				$tag = str_replace( ' src', ' type="module" src', $tag );
			}
			return $tag;
		}

		/**
		 * Register Product Video Metabox
		 */
		public function register_metabox() {
			add_meta_box(
				'wpvg_product_videos',
				__( 'Nexura Product Video Media Gallery for WooCommerce', 'nexura-product-video-media-gallery-for-woocommerce' ),
				array( $this, 'render_metabox' ),
				'product',
				'normal',
				'default'
			);
		}

		/**
		 * Enqueue admin scripts & styles on product edit screen & category taxonomy
		 *
		 * @param string $hook Admin screen hook.
		 */
		public function enqueue_metabox_assets( $hook ) {
			$is_product_screen = ( 'post.php' === $hook || 'post-new.php' === $hook );
			$is_cat_screen     = ( 'edit-tags.php' === $hook || 'term.php' === $hook );

			if ( ! $is_product_screen && ! $is_cat_screen ) {
				return;
			}

			if ( $is_product_screen ) {
				global $post;
				if ( ! $post || 'product' !== $post->post_type ) {
					return;
				}
			}

			if ( $is_cat_screen ) {
				$screen = get_current_screen();
				if ( ! $screen || 'product_cat' !== $screen->taxonomy ) {
					return;
				}
			}

			wp_enqueue_media();
			wp_enqueue_style(
				'wpvg-admin-css',
				plugins_url( 'assets/css/wpvg-admin.css', dirname( __DIR__ ) ),
				array(),
				WPVG_VERSION
			);

			// Heavy model-viewer only when Pro 3D module is loaded.
			if ( class_exists( 'WPVG_3D_Viewer' ) ) {
				$model_viewer_url = method_exists( 'WPVG_3D_Viewer', 'get_model_viewer_script_url' )
					? WPVG_3D_Viewer::get_model_viewer_script_url()
					: plugins_url( 'assets/js/model-viewer.min.js', dirname( __DIR__ ) );

				wp_enqueue_script(
					'wpvg-google-model-viewer',
					$model_viewer_url,
					array(),
					'3.5.0',
					true
				);
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
				array( 'jquery' ),
				$js_ver,
				true
			);

			global $post;
			$pro_active      = class_exists( 'WPVG_Pro' ) || (bool) apply_filters( 'wpvg_is_pro_active', false );
			$allows_multiple = function_exists( 'wpvg_allows_multiple_videos' ) ? wpvg_allows_multiple_videos() : $pro_active;
			wp_localize_script(
				'wpvg-admin-js',
				'wpvg_admin_data',
				array(
					'is_pro'                 => $pro_active,
					'is_premium_package'     => $pro_active,
					'allows_multiple_videos' => $allows_multiple,
					'show_pro_locks'         => false,
					'upgrade_url'            => WPVG_Freemius::get_upgrade_url(),
					'ajax_url'               => admin_url( 'admin-ajax.php' ),
					'nonce'                  => wp_create_nonce( 'wpvg_admin_nonce' ),
					'post_id'                => $post ? $post->ID : 0,
				)
			);

			/*
			 * Multi-video admin JS — Freemius Deploy strips this file from free ZIP
			 * (@fs_premium_only /assets/js/wpvg-admin-pro.js). Enqueue only when Pro
			 * modules are active (not merely because the premium package is installed).
			 */
			if ( $allows_multiple && function_exists( 'pvgfw_fs' ) && pvgfw_fs()->is__premium_only() ) {
				$pro_js_path = plugin_dir_path( dirname( __DIR__ ) ) . 'assets/js/wpvg-admin-pro.js';
				$pro_js_ver  = $js_ver;
				if ( is_readable( $pro_js_path ) ) {
					$mtime = filemtime( $pro_js_path );
					if ( false !== $mtime ) {
						$pro_js_ver .= '.' . (int) $mtime;
					}
				}
				wp_enqueue_script(
					'wpvg-admin-pro-js',
					plugins_url( 'assets/js/wpvg-admin-pro.js', dirname( __DIR__ ) ),
					array( 'jquery', 'wpvg-admin-js' ),
					$pro_js_ver,
					true
				);
			}
		}

		/**
		 * Render Metabox HTML
		 *
		 * @param WP_Post $post Current post object.
		 */
		public function render_metabox( $post ) {
			wp_nonce_field( 'wpvg_save_metabox_action', 'wpvg_metabox_nonce' );

			// Get existing videos
			$video_urls  = get_post_meta( $post->ID, '_wpvg_video_urls', true );
			if ( ! is_array( $video_urls ) ) {
				$video_urls = ! empty( $video_urls ) ? array( $video_urls ) : array();
			}

			$video_types = get_post_meta( $post->ID, '_wpvg_video_types', true );
			if ( ! is_array( $video_types ) ) {
				$video_types = array();
			}

			$thumb_ids = get_post_meta( $post->ID, '_wpvg_thumb_ids', true );
			if ( ! is_array( $thumb_ids ) ) {
				$thumb_ids = array();
			}

			$video_ids = get_post_meta( $post->ID, '_wpvg_video_ids', true );
			if ( ! is_array( $video_ids ) ) {
				$video_ids = array();
			}

			// Custom Gallery Style Override Values
			$override_gallery = get_post_meta( $post->ID, '_wpvg_override_gallery', true );
			$override_gallery = ! empty( $override_gallery ) ? $override_gallery : 'default';

			$custom_layout = get_post_meta( $post->ID, '_wpvg_custom_layout', true );
			$custom_layout = ! empty( $custom_layout ) ? $custom_layout : 'default';

			$custom_ratio = get_post_meta( $post->ID, '_wpvg_custom_ratio', true );
			$custom_ratio = ! empty( $custom_ratio ) ? $custom_ratio : 'default';

			$custom_zoom_type = get_post_meta( $post->ID, '_wpvg_custom_zoom_type', true );
			$custom_zoom_type = ! empty( $custom_zoom_type ) ? $custom_zoom_type : 'default';

			$custom_video_pos = get_post_meta( $post->ID, '_wpvg_custom_video_pos', true );
			$custom_video_pos = ! empty( $custom_video_pos ) ? $custom_video_pos : 'default';

			$custom_slider_autoplay = get_post_meta( $post->ID, '_wpvg_custom_slider_autoplay', true );
			$custom_slider_autoplay = ! empty( $custom_slider_autoplay ) ? $custom_slider_autoplay : 'default';

			$custom_thumbs_count = get_post_meta( $post->ID, '_wpvg_custom_thumbs_count', true );
			$custom_thumbs_count = ! empty( $custom_thumbs_count ) ? $custom_thumbs_count : 'default';

			// Shop Archive Video Per-Product Override Values [PRO]
			$archive_video_override = get_post_meta( $post->ID, '_wpvg_archive_video_override', true );
			$archive_video_override = ! empty( $archive_video_override ) ? $archive_video_override : 'default';

			$archive_style_override = get_post_meta( $post->ID, '_wpvg_archive_style_override', true );
			$archive_style_override = ! empty( $archive_style_override ) ? $archive_style_override : 'default';

			$archive_scope_override = get_post_meta( $post->ID, '_wpvg_archive_scope_override', true );
			$archive_scope_override = ! empty( $archive_scope_override ) ? $archive_scope_override : 'default';

			// 3D Product Model Per-Product Values [PRO]
			$prod_3d_enable      = get_post_meta( $post->ID, '_wpvg_3d_enable', true );
			$prod_3d_enable      = ! empty( $prod_3d_enable ) ? $prod_3d_enable : 'default';
			$prod_3d_source_type = get_post_meta( $post->ID, '_wpvg_3d_source_type', true );
			$prod_3d_source_type = ! empty( $prod_3d_source_type ) ? $prod_3d_source_type : 'default';
			$prod_3d_view_mode   = get_post_meta( $post->ID, '_wpvg_3d_view_mode', true );
			if ( empty( $prod_3d_view_mode ) ) {
				$legacy_sw = get_post_meta( $post->ID, '_wpvg_3d_show_switcher', true );
				$prod_3d_view_mode = ( 'no' === $legacy_sw ) ? '3d_only' : ( ( 'yes' === $legacy_sw ) ? 'switcher' : 'default' );
			}
			$prod_3d_switcher    = ( 'switcher' === $prod_3d_view_mode ) ? 'yes' : ( ( '3d_only' === $prod_3d_view_mode || '2d_only' === $prod_3d_view_mode ) ? 'no' : 'default' );
			$prod_3d_def_view    = get_post_meta( $post->ID, '_wpvg_3d_default_view', true );
			$prod_3d_def_view    = ! empty( $prod_3d_def_view ) ? $prod_3d_def_view : 'default';
			$prod_3d_model_url   = get_post_meta( $post->ID, '_wpvg_3d_model_url', true );
			$prod_3d_model_id    = get_post_meta( $post->ID, '_wpvg_3d_model_id', true );
			$prod_3d_poster_id   = get_post_meta( $post->ID, '_wpvg_3d_poster_id', true );
			$prod_3d_poster_url  = $prod_3d_poster_id ? wp_get_attachment_image_url( $prod_3d_poster_id, 'medium' ) : '';
			$prod_3d_placement   = get_post_meta( $post->ID, '_wpvg_3d_placement', true );
			$prod_3d_placement   = ! empty( $prod_3d_placement ) ? $prod_3d_placement : 'default';
			$prod_3d_autorotate  = get_post_meta( $post->ID, '_wpvg_3d_auto_rotate', true );
			$prod_3d_autorotate  = ! empty( $prod_3d_autorotate ) ? $prod_3d_autorotate : 'default';
			$prod_3d_btn_text    = get_post_meta( $post->ID, '_wpvg_3d_overlay_text', true );
			$prod_3d_spin_speed  = get_post_meta( $post->ID, '_wpvg_3d_spin_speed', true );
			$prod_3d_spin_speed  = ! empty( $prod_3d_spin_speed ) ? $prod_3d_spin_speed : 'default';
			$prod_3d_spin_sens   = get_post_meta( $post->ID, '_wpvg_3d_spin_sensitivity', true );
			$prod_3d_spin_sens   = ! empty( $prod_3d_spin_sens ) ? $prod_3d_spin_sens : 'default';
			$prod_3d_spin_dir    = get_post_meta( $post->ID, '_wpvg_3d_spin_direction', true );
			$prod_3d_spin_dir    = ! empty( $prod_3d_spin_dir ) ? $prod_3d_spin_dir : 'default';
			$prod_3d_autoplay    = get_post_meta( $post->ID, '_wpvg_3d_slider_autoplay', true );
			$prod_3d_autoplay    = ! empty( $prod_3d_autoplay ) ? $prod_3d_autoplay : 'default';
			$prod_3d_modal_tsize = get_post_meta( $post->ID, '_wpvg_3d_modal_thumb_size', true );

			// Resolved admin preview spin timing (product override â†’ global)
			$admin_spin_speed_opt = ( 'default' !== $prod_3d_spin_speed ) ? $prod_3d_spin_speed : get_option( 'wpvg_3d_spin_speed', 'normal' );
			$admin_spin_sens_opt  = ( 'default' !== $prod_3d_spin_sens ) ? $prod_3d_spin_sens : get_option( 'wpvg_3d_spin_sensitivity', 'normal' );
			$admin_spin_dir_opt   = ( 'default' !== $prod_3d_spin_dir ) ? $prod_3d_spin_dir : get_option( 'wpvg_3d_spin_direction', 'normal' );
			$admin_spin_ms_map    = array( 'fast' => 140, 'normal' => 220, 'smooth' => 320 );
			$admin_spin_sens_map  = array( 'high' => 18, 'normal' => 30, 'smooth' => 44 );
			$admin_spin_ms        = isset( $admin_spin_ms_map[ $admin_spin_speed_opt ] ) ? $admin_spin_ms_map[ $admin_spin_speed_opt ] : 220;
			$admin_spin_sens_px   = isset( $admin_spin_sens_map[ $admin_spin_sens_opt ] ) ? $admin_spin_sens_map[ $admin_spin_sens_opt ] : 30;

			$global_view_mode_ui = get_option( 'wpvg_3d_view_mode', 'switcher' );
			$resolved_view_mode  = ( 'default' === $prod_3d_view_mode || empty( $prod_3d_view_mode ) ) ? $global_view_mode_ui : $prod_3d_view_mode;
			$global_source_ui    = get_option( 'wpvg_3d_source_type', 'auto' );
			$resolved_source_ui  = ( 'default' === $prod_3d_source_type || empty( $prod_3d_source_type ) ) ? $global_source_ui : $prod_3d_source_type;

			$is_3d_disabled      = ( 'no' === $prod_3d_enable );
			$is_2d_only          = ( '2d_only' === $resolved_view_mode );
			$is_switcher_active  = ( ! $is_3d_disabled && ! $is_2d_only && 'switcher' === $resolved_view_mode );
			$is_360_active       = ( ! $is_3d_disabled && ! $is_2d_only && 'model_file' !== $resolved_source_ui && ( 'gallery_360' === $resolved_source_ui || 'switcher' === $resolved_view_mode ) );
			$is_model_active     = ( ! $is_3d_disabled && ! $is_2d_only && ! $is_360_active && ( 'model_file' === $resolved_source_ui || 'auto' === $resolved_source_ui ) );
			$is_common_3d_active = ( ! $is_3d_disabled && ! $is_2d_only );
			$hide_glb_ui         = ! $is_model_active;

			// Gather gallery frames for admin live 360 preview
			$admin_360_frames = array();
			if ( function_exists( 'wc_get_product' ) ) {
				$admin_wc_prod = wc_get_product( $post->ID );
				if ( $admin_wc_prod ) {
					$feat_id = $admin_wc_prod->get_image_id();
					if ( $feat_id ) {
						$admin_360_frames[] = wp_get_attachment_image_url( $feat_id, 'large' );
					}
					$g_ids = $admin_wc_prod->get_gallery_image_ids();
					if ( is_array( $g_ids ) ) {
						foreach ( $g_ids as $gid ) {
							if ( (int) $gid !== (int) $feat_id ) {
								$url = wp_get_attachment_image_url( $gid, 'large' );
								if ( $url ) {
									$admin_360_frames[] = $url;
								}
							}
						}
					}
				}
			}
			$admin_360_frames = array_values( array_filter( $admin_360_frames ) );

			$force_free        = defined( 'WPVG_FORCE_FREE' ) && WPVG_FORCE_FREE;
			$is_pro            = ! $force_free && ( class_exists( 'WPVG_Pro' ) || (bool) apply_filters( 'wpvg_is_pro_active', false ) );
			$show_locks        = false;
			$show_pro_ui       = $is_pro;
			$allow_multiple    = function_exists( 'wpvg_allows_multiple_videos' ) ? wpvg_allows_multiple_videos() : $is_pro;
			$pro_tag           = '';
			$pro_bracket       = '';
			?>
			<div class="wpvg-metabox-container" data-allows-multiple="<?php echo $allow_multiple ? '1' : '0'; ?>">
				<!-- Metabox Navigation Tabs -->
				<div class="wpvg-metabox-tabs">
					<button type="button" class="wpvg-metabox-tab active" data-tab="wpvg-mb-tab-videos">
						<span class="dashicons dashicons-video-alt3"></span>
						<?php esc_html_e( 'Product Videos', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
					</button>
					<?php if ( $show_pro_ui ) : ?>
					<button type="button" class="wpvg-metabox-tab" data-tab="wpvg-mb-tab-style">
						<span class="dashicons dashicons-art"></span>
						<?php esc_html_e( 'Custom Gallery Style', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
						<?php if ( $show_locks ) : ?><span class="wpvg-pro-pill"><?php esc_html_e( 'PRO', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></span><?php endif; ?>
					</button>
					<button type="button" class="wpvg-metabox-tab" data-tab="wpvg-mb-tab-archive">
						<span class="dashicons dashicons-grid-view"></span>
						<?php esc_html_e( 'Shop Archive Video', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
						<?php if ( $show_locks ) : ?><span class="wpvg-pro-pill"><?php esc_html_e( 'PRO', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></span><?php endif; ?>
					</button>
					<button type="button" class="wpvg-metabox-tab" data-tab="wpvg-mb-tab-3d">
						<span class="dashicons dashicons-visibility"></span>
						<?php esc_html_e( '3D Product Model', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
						<?php if ( $show_locks ) : ?><span class="wpvg-pro-pill"><?php esc_html_e( 'PRO', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></span><?php endif; ?>
					</button>
					<?php endif; ?>
				</div>

				<!-- Tab 1: Product Videos — free radio UI; multi-video lives in @fs_premium_only file. -->
				<div id="wpvg-mb-tab-videos" class="wpvg-metabox-tab-content active">
					<?php
					if ( $allow_multiple && function_exists( 'wpvg_render_premium_videos_tab' ) ) {
						wpvg_render_premium_videos_tab( $video_urls, $video_types, $thumb_ids, $video_ids );
					} else {
						$this->render_free_videos_tab( $video_urls, $video_types, $thumb_ids, $video_ids );
					}
					?>
				</div>

				<?php if ( $show_pro_ui ) : ?>
				<!-- Tab 2: Custom Gallery Style Override [PRO] -->
				<div id="wpvg-mb-tab-style" class="wpvg-metabox-tab-content">
					<div class="wpvg-override-card">
						<p style="margin-top: 0; color: #475569; font-size: 13px;">
							<?php esc_html_e( 'Customize layout, aspect ratio, zoom style, and video position specifically for this individual product. Overrides global settings and category rules.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
						</p>

						<?php if ( $show_locks ) : ?>
							<div class="wpvg-inline-pro-tip" style="margin-bottom: 16px; max-width: 100%;">
								<span><span class="dashicons dashicons-lock" style="font-size:16px; width:16px; height:16px; vertical-align:text-bottom;"></span> <strong><?php esc_html_e( 'Pro Exclusive Feature:', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></strong> <?php esc_html_e( 'Per-product custom gallery styling requires Nexura Product Video Media Gallery Pro.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></span>
								<a href="<?php echo esc_url( WPVG_Freemius::get_upgrade_url() ); ?>" target="_blank"><?php esc_html_e( 'Upgrade to Pro', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></a>
							</div>
						<?php endif; ?>

						<div class="wpvg-override-row">
							<div class="wpvg-override-label">
								<label for="wpvg_override_gallery"><?php esc_html_e( 'Gallery Style Source', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
								<small><?php esc_html_e( 'Choose whether this product follows global settings or uses custom style.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></small>
							</div>
							<div class="wpvg-override-input">
								<select name="_wpvg_override_gallery" id="wpvg_override_gallery" class="wpvg-select-with-pro">
									<option value="default" <?php selected( $override_gallery, 'default' ); ?>><?php esc_html_e( 'Use Global / Category Settings (Default)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
									<option value="custom" data-pro="true" data-feature="<?php esc_attr_e( 'Per-Product Gallery Customization', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>" <?php selected( $override_gallery, 'custom' ); ?>><?php esc_html_e( 'Override with Custom Settings for this Product', 'nexura-product-video-media-gallery-for-woocommerce' ); ?><?php echo esc_html( $pro_bracket ); ?></option>
								</select>
							</div>
						</div>

						<div class="wpvg-product-override-fields" style="<?php echo ( 'custom' === $override_gallery ) ? '' : 'display: none;'; ?>">
							<div class="wpvg-override-row">
								<div class="wpvg-override-label">
									<label for="_wpvg_custom_layout"><?php esc_html_e( 'Gallery Layout', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
									<small><?php esc_html_e( 'Override slider/grid presentation for this product.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></small>
								</div>
								<div class="wpvg-override-input">
									<select name="_wpvg_custom_layout" id="_wpvg_custom_layout" class="wpvg-select-with-pro">
										<option value="default" <?php selected( $custom_layout, 'default' ); ?>><?php esc_html_e( 'Default (Inherit Global / Category)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
										<option value="vertical_left" <?php selected( $custom_layout, 'vertical_left' ); ?>><?php esc_html_e( 'Vertical Thumbnails (Left)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
										<option value="vertical_right" data-pro="true" data-feature="<?php esc_attr_e( 'Vertical Right Gallery Layout', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>" <?php selected( $custom_layout, 'vertical_right' ); ?>><?php esc_html_e( 'Vertical Thumbnails (Right)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?><?php echo esc_html( $pro_bracket ); ?></option>
										<option value="horizontal" <?php selected( $custom_layout, 'horizontal' ); ?>><?php esc_html_e( 'Horizontal Slider (Bottom)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
										<option value="grid_mosaic" data-pro="true" data-feature="<?php esc_attr_e( 'Mosaic Grid Layout', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>" <?php selected( $custom_layout, 'grid_mosaic' ); ?>><?php esc_html_e( 'Modern Mosaic Grid', 'nexura-product-video-media-gallery-for-woocommerce' ); ?><?php echo esc_html( $pro_bracket ); ?></option>
										<option value="grid_2col" data-pro="true" data-feature="<?php esc_attr_e( 'Two-Column Grid Layout', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>" <?php selected( $custom_layout, 'grid_2col' ); ?>><?php esc_html_e( 'Two-Column Grid', 'nexura-product-video-media-gallery-for-woocommerce' ); ?><?php echo esc_html( $pro_bracket ); ?></option>
										<option value="grid_1col" data-pro="true" data-feature="<?php esc_attr_e( 'Single Column Feed Layout', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>" <?php selected( $custom_layout, 'grid_1col' ); ?>><?php esc_html_e( 'Single Column Feed', 'nexura-product-video-media-gallery-for-woocommerce' ); ?><?php echo esc_html( $pro_bracket ); ?></option>
									</select>
								</div>
							</div>

							<div class="wpvg-override-row">
								<div class="wpvg-override-label">
									<label for="_wpvg_custom_ratio"><?php esc_html_e( 'Aspect Ratio', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
									<small><?php esc_html_e( 'Set image container aspect ratio (e.g. 9:16 for Reels/Clothing).', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></small>
								</div>
								<div class="wpvg-override-input">
									<select name="_wpvg_custom_ratio" id="_wpvg_custom_ratio" class="wpvg-select-with-pro">
										<option value="default" <?php selected( $custom_ratio, 'default' ); ?>><?php esc_html_e( 'Default (Inherit Global / Category)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
										<option value="4_5" <?php selected( $custom_ratio, '4_5' ); ?>><?php esc_html_e( '4:5 Portrait (Standard Fashion)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
										<option value="1_1" <?php selected( $custom_ratio, '1_1' ); ?>><?php esc_html_e( '1:1 Square (Modern eCommerce)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
										<option value="16_9" data-pro="true" data-feature="<?php esc_attr_e( '16:9 Widescreen Ratio', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>" <?php selected( $custom_ratio, '16_9' ); ?>><?php esc_html_e( '16:9 Landscape (Widescreen)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?><?php echo esc_html( $pro_bracket ); ?></option>
										<option value="9_16" data-pro="true" data-feature="<?php esc_attr_e( '9:16 Vertical Video Ratio', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>" <?php selected( $custom_ratio, '9_16' ); ?>><?php esc_html_e( '9:16 Vertical (Reels / TikTok Style)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?><?php echo esc_html( $pro_bracket ); ?></option>
									</select>
								</div>
							</div>

							<div class="wpvg-override-row">
								<div class="wpvg-override-label">
									<label for="_wpvg_custom_zoom_type"><?php esc_html_e( 'Image Magnifier & Zoom', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
									<small><?php esc_html_e( 'Select zoom magnifier style or disable it for this product.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></small>
								</div>
								<div class="wpvg-override-input">
									<select name="_wpvg_custom_zoom_type" id="_wpvg_custom_zoom_type" class="wpvg-select-with-pro">
										<option value="default" <?php selected( $custom_zoom_type, 'default' ); ?>><?php esc_html_e( 'Default (Inherit Global / Category)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
										<option value="inner" <?php selected( $custom_zoom_type, 'inner' ); ?>><?php esc_html_e( 'Inner Pan-Zoom', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
										<option value="lens" data-pro="true" data-feature="<?php esc_attr_e( 'Magnifying Glass Lens Zoom', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>" <?php selected( $custom_zoom_type, 'lens' ); ?>><?php esc_html_e( 'Magnifying Glass Lens', 'nexura-product-video-media-gallery-for-woocommerce' ); ?><?php echo esc_html( $pro_bracket ); ?></option>
										<option value="window" data-pro="true" data-feature="<?php esc_attr_e( 'Side Preview Window Zoom', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>" <?php selected( $custom_zoom_type, 'window' ); ?>><?php esc_html_e( 'Side Preview Window', 'nexura-product-video-media-gallery-for-woocommerce' ); ?><?php echo esc_html( $pro_bracket ); ?></option>
										<option value="disabled" <?php selected( $custom_zoom_type, 'disabled' ); ?>><?php esc_html_e( 'Disable Zoom for this Product', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
									</select>
								</div>
							</div>

							<div class="wpvg-override-row">
								<div class="wpvg-override-label">
									<label for="_wpvg_custom_video_pos"><?php esc_html_e( 'Video Placement in Gallery', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
									<small><?php esc_html_e( 'Position of the video item in relation to photos.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></small>
								</div>
								<div class="wpvg-override-input">
									<select name="_wpvg_custom_video_pos" id="_wpvg_custom_video_pos" class="wpvg-select-with-pro">
										<option value="default" <?php selected( $custom_video_pos, 'default' ); ?>><?php esc_html_e( 'Default (Inherit Global Settings)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
										<option value="first" <?php selected( $custom_video_pos, 'first' ); ?>><?php esc_html_e( '1st - Play First Before Photos', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
										<option value="second" <?php selected( $custom_video_pos, 'second' ); ?>><?php esc_html_e( '2nd - Follows Featured Image', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
										<option value="third" <?php selected( $custom_video_pos, 'third' ); ?>><?php esc_html_e( '3rd - Middle Position', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
										<option value="last" <?php selected( $custom_video_pos, 'last' ); ?>><?php esc_html_e( 'Last - At the End', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
									</select>
								</div>
							</div>

							<div class="wpvg-override-row">
								<div class="wpvg-override-label">
									<label for="_wpvg_custom_slider_autoplay"><?php esc_html_e( 'Autoplay Slider', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
									<small><?php esc_html_e( 'Enable or disable automatic slide transitions for this product.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></small>
								</div>
								<div class="wpvg-override-input">
									<select name="_wpvg_custom_slider_autoplay" id="_wpvg_custom_slider_autoplay" class="wpvg-select-with-pro">
										<option value="default" <?php selected( $custom_slider_autoplay, 'default' ); ?>><?php esc_html_e( 'Default (Inherit Global Settings)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
										<option value="yes" <?php selected( $custom_slider_autoplay, 'yes' ); ?>><?php esc_html_e( 'On (Enable Slider Autoplay)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
										<option value="no" <?php selected( $custom_slider_autoplay, 'no' ); ?>><?php esc_html_e( 'Off (Disable Slider Autoplay)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
									</select>
								</div>
							</div>

							<div class="wpvg-override-row">
								<div class="wpvg-override-label">
									<label for="_wpvg_custom_thumbs_count"><?php esc_html_e( 'Thumbnails in Viewport', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
									<small><?php esc_html_e( 'Number of visible thumbnail items before scrolling.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></small>
								</div>
								<div class="wpvg-override-input">
									<select name="_wpvg_custom_thumbs_count" id="_wpvg_custom_thumbs_count" class="wpvg-select-with-pro">
										<option value="default" <?php selected( $custom_thumbs_count, 'default' ); ?>><?php esc_html_e( 'Default (Inherit Global Settings)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
										<?php for ( $i = 2; $i <= 10; $i++ ) : ?>
											<option value="<?php echo esc_attr( $i ); ?>" <?php selected( $custom_thumbs_count, (string) $i ); ?>>
												<?php
												/* translators: %d: number of thumbnails */
												printf( esc_html__( '%d Thumbnails', 'nexura-product-video-media-gallery-for-woocommerce' ), esc_html( $i ) );
												?>
											</option>
										<?php endfor; ?>
									</select>
								</div>
							</div>
						</div>
					</div>
				</div>

				<!-- Tab 3: Shop Archive Video [PRO] -->
				<div id="wpvg-mb-tab-archive" class="wpvg-metabox-tab-content">
					<div class="wpvg-override-card">
						<p style="margin-top: 0; color: #475569; font-size: 13px;">
							<?php esc_html_e( 'Configure how this product displays video on shop catalog pages, category archives, and related product grids.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
						</p>

						<?php if ( $show_locks ) : ?>
							<div class="wpvg-inline-pro-tip" style="margin-bottom: 16px; max-width: 100%;">
								<span><span class="dashicons dashicons-lock" style="font-size:16px; width:16px; height:16px; vertical-align:text-bottom;"></span> <strong><?php esc_html_e( 'Pro Exclusive Feature:', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></strong> <?php esc_html_e( 'Shop Archive video showcase requires Nexura Product Video Media Gallery Pro.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></span>
								<a href="<?php echo esc_url( WPVG_Freemius::get_upgrade_url() ); ?>" target="_blank"><?php esc_html_e( 'Upgrade to Pro', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></a>
							</div>
						<?php endif; ?>

						<div class="wpvg-override-row">
							<div class="wpvg-override-label">
								<label for="_wpvg_archive_video_override"><?php esc_html_e( 'Archive Video Display', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
								<small><?php esc_html_e( 'Control whether this product shows a video preview on catalog listings.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></small>
							</div>
							<div class="wpvg-override-input">
								<select name="_wpvg_archive_video_override" id="_wpvg_archive_video_override" class="wpvg-select-with-pro">
									<option value="default" <?php selected( $archive_video_override, 'default' ); ?>><?php esc_html_e( 'Default (Inherit Global Settings)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
									<option value="enabled" data-pro="true" data-feature="<?php esc_attr_e( 'Force Enable Archive Video', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>" <?php selected( $archive_video_override, 'enabled' ); ?>><?php esc_html_e( 'Force Enable Video on Shop / Archive Card', 'nexura-product-video-media-gallery-for-woocommerce' ); ?><?php echo esc_html( $pro_bracket ); ?></option>
									<option value="disabled" data-pro="true" data-feature="<?php esc_attr_e( 'Force Disable Archive Video', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>" <?php selected( $archive_video_override, 'disabled' ); ?>><?php esc_html_e( 'Force Disable Video for this Product', 'nexura-product-video-media-gallery-for-woocommerce' ); ?><?php echo esc_html( $pro_bracket ); ?></option>
								</select>
							</div>
						</div>

						<div class="wpvg-override-row">
							<div class="wpvg-override-label">
								<label for="_wpvg_archive_style_override"><?php esc_html_e( 'Archive Video Style', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
								<small><?php esc_html_e( 'Choose interaction style for this product on catalog grids.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></small>
							</div>
							<div class="wpvg-override-input">
								<select name="_wpvg_archive_style_override" id="_wpvg_archive_style_override" class="wpvg-select-with-pro">
									<option value="default" <?php selected( $archive_style_override, 'default' ); ?>><?php esc_html_e( 'Default (Inherit Global Settings)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
									<option value="hover_autoplay" data-pro="true" data-feature="<?php esc_attr_e( 'Hover Autoplay Video Style', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>" <?php selected( $archive_style_override, 'hover_autoplay' ); ?>><?php esc_html_e( 'Style 1: Autoplay on Hover (Smooth Muted Preview)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?><?php echo esc_html( $pro_bracket ); ?></option>
									<option value="click_popup" data-pro="true" data-feature="<?php esc_attr_e( 'Floating Play Badge Modal Style', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>" <?php selected( $archive_style_override, 'click_popup' ); ?>><?php esc_html_e( 'Style 2: Floating Play Badge with Quick Lightbox', 'nexura-product-video-media-gallery-for-woocommerce' ); ?><?php echo esc_html( $pro_bracket ); ?></option>
									<option value="inline_player" data-pro="true" data-feature="<?php esc_attr_e( 'Inline Card Video Player Style', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>" <?php selected( $archive_style_override, 'inline_player' ); ?>><?php esc_html_e( 'Style 3: Inline Video Card (Embedded in Grid)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?><?php echo esc_html( $pro_bracket ); ?></option>
								</select>
							</div>
						</div>

						<div class="wpvg-override-row">
							<div class="wpvg-override-label">
								<label for="_wpvg_archive_scope_override"><?php esc_html_e( 'Archive Video Scope', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
								<small><?php esc_html_e( 'Showcase only the 1st video or allow cycling through multiple videos.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></small>
							</div>
							<div class="wpvg-override-input">
								<select name="_wpvg_archive_scope_override" id="_wpvg_archive_scope_override" class="wpvg-select-with-pro">
									<option value="default" <?php selected( $archive_scope_override, 'default' ); ?>><?php esc_html_e( 'Default (Inherit Global Settings)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
									<option value="single" data-pro="true" data-feature="<?php esc_attr_e( 'Single Video Showcase', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>" <?php selected( $archive_scope_override, 'single' ); ?>><?php esc_html_e( 'Single Video (Showcase 1st Video)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?><?php echo esc_html( $pro_bracket ); ?></option>
									<option value="multiple" data-pro="true" data-feature="<?php esc_attr_e( 'Multiple Archive Videos Carousel', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>" <?php selected( $archive_scope_override, 'multiple' ); ?>><?php esc_html_e( 'Multiple Videos (Cycle through all videos with swipe dots)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?><?php echo esc_html( $pro_bracket ); ?></option>
								</select>
							</div>
						</div>
					</div>
				</div>

				<!-- Tab 4: 3D Product Model [PRO] -->
				<div id="wpvg-mb-tab-3d" class="wpvg-metabox-tab-content">
					<div class="wpvg-override-card wpvg-3d-metabox-card" data-global-source="<?php echo esc_attr( $global_source_ui ); ?>" data-global-view="<?php echo esc_attr( $global_view_mode_ui ); ?>">
						<p style="margin-top: 0; color: #475569; font-size: 13px;">
							<?php esc_html_e( 'Display an interactive 360-degree 3D product view. Upload a .glb or .gltf 3D model, customize placement, and preview the live 3D view right here.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
						</p>

						<?php if ( $show_locks ) : ?>
							<div class="wpvg-inline-pro-tip" style="margin-bottom: 16px; max-width: 100%;">
								<span><span class="dashicons dashicons-lock" style="font-size:16px; width:16px; height:16px; vertical-align:text-bottom;"></span> <strong><?php esc_html_e( 'Pro Exclusive Feature:', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></strong> <?php esc_html_e( 'Interactive 3D Product Model Showcase requires Nexura Product Video Media Gallery Pro.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></span>
								<a href="<?php echo esc_url( WPVG_Freemius::get_upgrade_url() ); ?>" target="_blank"><?php esc_html_e( 'Upgrade to Pro', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></a>
							</div>
						<?php endif; ?>

						<!-- Notice: 3D Standalone Mode & Legacy Code Bypass -->
						<div class="wpvg-admin-info-callout" style="background: #eff6ff; border-left: 4px solid #2563eb; border-radius: 6px; padding: 14px 16px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
							<div style="display: flex; gap: 10px; align-items: flex-start;">
								<span class="dashicons dashicons-info" style="color: #2563eb; font-size: 20px; width: 20px; height: 20px; margin-top: 2px;"></span>
								<div>
									<strong style="color: #1e3a8a; font-size: 13.5px; display: block; margin-bottom: 4px;">
										<?php esc_html_e( 'Notice: Dedicated 3D Mode Compatibility', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
									</strong>
									<p style="margin: 0 0 6px 0; color: #1e40af; font-size: 12.5px; line-height: 1.5;">
										<?php esc_html_e( 'When 3D Product Model is enabled for this product, Product Videos, Custom Gallery Style, and Shop Archive Video are automatically turned OFF on the frontend (single product + shop card) to guarantee smooth 360-degree performance with zero script conflicts.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
									</p>
									<p style="margin: 0; color: #2563eb; font-size: 12px; line-height: 1.4; font-weight: 500;">
										<em><?php esc_html_e( '2D / 3D Gallery View Mode (switcher) or 360-degree Gallery Spin also keeps .glb / .gltf Model File OFF  -  Live 360-degree frames are used instead.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></em>
									</p>
								</div>
							</div>
						</div>

						<!-- 1. Enable 3D for this product -->
						<div class="wpvg-override-row wpvg-3d-row-enable">
							<div class="wpvg-override-label">
								<label for="_wpvg_3d_enable"><?php esc_html_e( '3D Viewer Status', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
								<small><?php esc_html_e( 'Choose whether 3D viewer is active for this product.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></small>
							</div>
							<div class="wpvg-override-input">
								<select name="_wpvg_3d_enable" id="_wpvg_3d_enable" class="wpvg-select-with-pro">
									<option value="default" <?php selected( $prod_3d_enable, 'default' ); ?>><?php esc_html_e( 'Default (Inherit Global Setting)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
									<option value="yes" data-pro="true" data-feature="<?php esc_attr_e( '3D Product Model', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>" <?php selected( $prod_3d_enable, 'yes' ); ?>><?php esc_html_e( 'Enabled for this product', 'nexura-product-video-media-gallery-for-woocommerce' ); ?><?php echo esc_html( $pro_bracket ); ?></option>
									<option value="no" data-pro="true" data-feature="<?php esc_attr_e( '3D Product Model', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>" <?php selected( $prod_3d_enable, 'no' ); ?>><?php esc_html_e( 'Disabled for this product', 'nexura-product-video-media-gallery-for-woocommerce' ); ?><?php echo esc_html( $pro_bracket ); ?></option>
								</select>
							</div>
						</div>

						<!-- 2. Source Engine: 360 Gallery Image Spin or 3D Model -->
						<div class="wpvg-override-row wpvg-3d-row-source" style="<?php echo $is_3d_disabled ? 'display:none;' : ''; ?>">
							<div class="wpvg-override-label">
								<label for="_wpvg_3d_source_type"><?php esc_html_e( '3D Source Engine', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
								<small><?php esc_html_e( 'Select whether to spin product gallery photos in 360-degree or load a 3D model file.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></small>
							</div>
							<div class="wpvg-override-input">
								<select name="_wpvg_3d_source_type" id="_wpvg_3d_source_type">
									<option value="default" <?php selected( $prod_3d_source_type, 'default' ); ?>><?php esc_html_e( 'Default (Inherit Global: Auto-Detect)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
									<option value="gallery_360" <?php selected( $prod_3d_source_type, 'gallery_360' ); ?>><?php esc_html_e( '360-degree Gallery Image Spin (JavaScript Turntable)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
									<option value="model_file" <?php selected( $prod_3d_source_type, 'model_file' ); ?>><?php esc_html_e( '3D Model File (.glb / .gltf Mesh via model-viewer)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
								</select>
							</div>
						</div>

						<!-- 3. 2D / 3D Gallery View Mode -->
						<div class="wpvg-override-row wpvg-3d-row-view-mode" style="<?php echo $is_3d_disabled ? 'display:none;' : ''; ?>">
							<div class="wpvg-override-label">
								<label for="_wpvg_3d_view_mode"><?php esc_html_e( '2D / 3D Gallery View Mode', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
								<small><?php esc_html_e( 'Choose 2D & 3D Switcher, 3D View only, or 2D View only.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></small>
							</div>
							<div class="wpvg-override-input">
								<select name="_wpvg_3d_view_mode" id="_wpvg_3d_view_mode">
									<option value="default" <?php selected( $prod_3d_view_mode, 'default' ); ?>><?php esc_html_e( 'Default (Inherit Global View Mode)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
									<option value="switcher" <?php selected( $prod_3d_view_mode, 'switcher' ); ?>><?php esc_html_e( '2D & 3D View Switcher (Show [ 360-degree 3D View | 2D Photos ] Pill)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
									<option value="3d_only" <?php selected( $prod_3d_view_mode, '3d_only' ); ?>><?php esc_html_e( '3D View Only (Dedicated 3D Turntable / Model Only, No Switcher)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
									<option value="2d_only" <?php selected( $prod_3d_view_mode, '2d_only' ); ?>><?php esc_html_e( '2D View Only (Standard 2D Photos Gallery Only, No 3D)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
								</select>
							</div>
						</div>

						<!-- 4. Default View on Page Load -->
						<div class="wpvg-override-row wpvg-3d-row-default-view" style="<?php echo $is_switcher_active ? '' : 'display:none;'; ?>">
							<div class="wpvg-override-label">
								<label for="_wpvg_3d_default_view"><?php esc_html_e( 'Default Active View', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
								<small><?php esc_html_e( 'Choose which view is shown first when the single product page opens.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></small>
							</div>
							<div class="wpvg-override-input">
								<select name="_wpvg_3d_default_view" id="_wpvg_3d_default_view">
									<option value="default" <?php selected( $prod_3d_def_view, 'default' ); ?>><?php esc_html_e( 'Default (Follow Global Setting)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
									<option value="3d" <?php selected( $prod_3d_def_view, '3d' ); ?>><?php esc_html_e( '360-degree 3D Turntable Active First', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
									<option value="2d" <?php selected( $prod_3d_def_view, '2d' ); ?>><?php esc_html_e( '2D Photo Gallery Active First', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
								</select>
							</div>
						</div>

						<!-- 5. Live 360-degree Gallery Image Turntable Preview -->
						<div class="wpvg-override-row wpvg-3d-360-preview-row" style="<?php echo $is_360_active ? '' : 'display:none;'; ?>">
							<div class="wpvg-override-label">
								<label><?php esc_html_e( '360-degree Gallery Image Turntable', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
								<small><?php esc_html_e( 'Preview interactive JavaScript spin created from your product gallery images.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></small>
							</div>
							<div class="wpvg-override-input">
								<?php if ( ! empty( $admin_360_frames ) && count( $admin_360_frames ) >= 2 ) : ?>
									<div class="wpvg-admin-360-preview-box" data-spin-ms="<?php echo esc_attr( $admin_spin_ms ); ?>" data-spin-sens="<?php echo esc_attr( $admin_spin_sens_px ); ?>" data-spin-dir="<?php echo esc_attr( $admin_spin_dir_opt ); ?>" data-frames="<?php echo esc_attr( wp_json_encode( $admin_360_frames ) ); ?>" style="border: 1px solid #cbd5e1; border-radius: 8px; background: #ffffff; padding: 14px; max-width: 480px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
										<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
											<strong style="color: #0f172a; font-size: 13px; display: flex; align-items: center; gap: 6px;">
												<span class="dashicons dashicons-image-rotate" style="color: #2563eb;"></span>
												<?php
												/* translators: %d: count of frames */
												printf( esc_html__( 'Live 360-degree Preview (%d Gallery Frames)', 'nexura-product-video-media-gallery-for-woocommerce' ), count( $admin_360_frames ) );
												?>
											</strong>
											<span style="font-size: 11px; color: #16a34a; background: #dcfce7; padding: 2px 8px; border-radius: 12px; font-weight: 600;">
												<?php esc_html_e( 'Ready for 360-degree Spin', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
											</span>
										</div>
										<div class="wpvg-admin-360-stage" style="width: 100%; height: 260px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden; cursor: grab; user-select: none; touch-action: none;">
											<img id="wpvg-admin-360-preview-img" src="<?php echo esc_url( $admin_360_frames[0] ); ?>" style="max-height: 90%; max-width: 90%; object-fit: contain; pointer-events: none; transition: none;">
											<div style="position: absolute; bottom: 8px; left: 50%; transform: translateX(-50%); background: rgba(15,23,42,0.75); color: #fff; padding: 3px 10px; border-radius: 20px; font-size: 11px; pointer-events: none; display: flex; align-items: center; gap: 4px; white-space: nowrap;">
												<span>â†” Drag to spin</span>  -  <span id="wpvg-admin-360-frame-indicator">1 / <?php echo count( $admin_360_frames ); ?></span>
											</div>
										</div>
										<div style="margin-top: 10px; display: flex; align-items: center; gap: 10px;">
											<input type="range" id="wpvg-admin-360-slider" min="0" max="<?php echo count( $admin_360_frames ) - 1; ?>" value="0" style="flex: 1; cursor: pointer;">
											<button type="button" class="button button-small" id="wpvg-admin-360-autoplay-btn">
												<span class="dashicons dashicons-controls-play" style="font-size: 14px; width: 14px; height: 14px; vertical-align: middle;"></span> Auto Spin
											</button>
										</div>
										<p style="margin: 8px 0 0 0; font-size: 11.5px; color: #64748b; line-height: 1.4;">
											<?php
											printf(
												/* translators: 1: ms per frame, 2: sensitivity px */
												esc_html__( 'Pro smooth spin with speed limit: %1$dms/frame, drag sensitivity %2$dpx/frame (product override or global 360-degree settings).', 'nexura-product-video-media-gallery-for-woocommerce' ),
												(int) $admin_spin_ms,
												(int) $admin_spin_sens_px
											);
											?>
										</p>
									</div>
								<?php else : ?>
									<div style="background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 6px; padding: 12px 14px; color: #64748b; font-size: 12px;">
										<span class="dashicons dashicons-info" style="color: #64748b; vertical-align: text-bottom;"></span>
										<?php esc_html_e( 'No multi-angle gallery images detected yet. Upload sequential product angle photos in the WooCommerce "Product gallery" box on the right sidebar, and they will automatically become an interactive 360-degree spin turntable!', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
									</div>
								<?php endif; ?>
							</div>
						</div>

						<!-- 5b. Per-product 360-degree Spin Speed Limits -->
						<div class="wpvg-override-row wpvg-3d-360-spin-settings" style="<?php echo $is_360_active ? '' : 'display:none;'; ?>">
							<div class="wpvg-override-label">
								<label for="_wpvg_3d_spin_speed"><?php esc_html_e( '360-degree Spin Speed Limit', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
								<small><?php esc_html_e( 'Professional auto-spin pacing for this product (overrides global).', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></small>
							</div>
							<div class="wpvg-override-input">
								<select name="_wpvg_3d_spin_speed" id="_wpvg_3d_spin_speed">
									<option value="default" <?php selected( $prod_3d_spin_speed, 'default' ); ?>><?php esc_html_e( 'Default (Follow Global Speed)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
									<option value="fast" <?php selected( $prod_3d_spin_speed, 'fast' ); ?>><?php esc_html_e( 'Fast (140ms per frame)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
									<option value="normal" <?php selected( $prod_3d_spin_speed, 'normal' ); ?>><?php esc_html_e( 'Normal (220ms per frame) - Recommended', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
									<option value="smooth" <?php selected( $prod_3d_spin_speed, 'smooth' ); ?>><?php esc_html_e( 'Smooth / Cinematic (320ms per frame)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
								</select>
							</div>
						</div>
						<div class="wpvg-override-row wpvg-3d-360-spin-settings" style="<?php echo $is_360_active ? '' : 'display:none;'; ?>">
							<div class="wpvg-override-label">
								<label for="_wpvg_3d_spin_sensitivity"><?php esc_html_e( 'Drag Sensitivity', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
								<small><?php esc_html_e( 'How far the customer must drag to advance one frame.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></small>
							</div>
							<div class="wpvg-override-input">
								<select name="_wpvg_3d_spin_sensitivity" id="_wpvg_3d_spin_sensitivity">
									<option value="default" <?php selected( $prod_3d_spin_sens, 'default' ); ?>><?php esc_html_e( 'Default (Follow Global)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
									<option value="high" <?php selected( $prod_3d_spin_sens, 'high' ); ?>><?php esc_html_e( 'High (18px per frame)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
									<option value="normal" <?php selected( $prod_3d_spin_sens, 'normal' ); ?>><?php esc_html_e( 'Normal (30px per frame) - Recommended', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
									<option value="smooth" <?php selected( $prod_3d_spin_sens, 'smooth' ); ?>><?php esc_html_e( 'Smooth (44px per frame)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
								</select>
							</div>
						</div>

						<!-- 6. 3D Model File (.glb / .gltf)  -  hidden for Live 360 / 2Dâ†”3D switcher -->
						<div class="wpvg-override-row wpvg-3d-model-file-row" style="<?php echo $is_model_active ? '' : 'display:none;'; ?>">
							<div class="wpvg-override-label">
								<label for="_wpvg_3d_model_url"><?php esc_html_e( '3D Model File (.glb / .gltf)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
								<small><?php esc_html_e( 'Only used when Source Engine is 3D Model File (not with 2D/3D Gallery View Mode or Live 360-degree).', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></small>
							</div>
							<div class="wpvg-override-input">
								<div style="display: flex; gap: 8px; align-items: center; margin-bottom: 8px;">
									<input type="url" name="_wpvg_3d_model_url" id="_wpvg_3d_model_url" class="regular-text wpvg-3d-model-input" value="<?php echo esc_url( $prod_3d_model_url ); ?>" placeholder="https://example.com/wp-content/uploads/model.glb" style="flex: 1;">
									<input type="hidden" name="_wpvg_3d_model_id" id="_wpvg_3d_model_id" value="<?php echo esc_attr( $prod_3d_model_id ); ?>">
								</div>
								<div style="display: flex; gap: 8px; flex-wrap: wrap;">
									<button type="button" class="button wpvg-btn-select-3d-file">
										<span class="dashicons dashicons-upload"></span> <?php esc_html_e( 'Upload / Select 3D File (.glb, .gltf)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
									</button>
									<button type="button" class="button wpvg-btn-sample-3d-product" data-sample-url="<?php echo esc_url( class_exists( 'WPVG_3D_Viewer' ) && method_exists( 'WPVG_3D_Viewer', 'get_sample_model_url' ) ? WPVG_3D_Viewer::get_sample_model_url() : plugins_url( 'assets/models/DamagedHelmet.glb', dirname( __DIR__ ) ) ); ?>" onclick="
										var url = this.getAttribute('data-sample-url');
										var input = document.getElementById('_wpvg_3d_model_url') || document.querySelector('input[name=\'_wpvg_3d_model_url\']');
										if (input) {
											input.value = url;
											if (window.jQuery) { jQuery(input).trigger('input').trigger('change'); }
										}
										var clearBtn = document.querySelector('.wpvg-btn-clear-3d-model');
										if (clearBtn) { clearBtn.style.display = 'inline-block'; }
										var pBox = document.querySelector('.wpvg-3d-admin-preview-box');
										var stage = document.querySelector('.wpvg-3d-admin-model-stage');
										if (stage) {
											stage.innerHTML = '<model-viewer src=\'' + url + '\' auto-rotate camera-controls shadow-intensity=\'1\' style=\'width: 100%; height: 100%;\' class=\'wpvg-admin-model-viewer\'></model-viewer>';
										}
										if (pBox) { pBox.style.display = 'block'; }
									">
										<span class="dashicons dashicons-admin-appearance"></span> <?php esc_html_e( 'Use Sample Demo Model', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
									</button>
									<button type="button" class="button wpvg-btn-clear-3d-model" style="<?php echo ! empty( $prod_3d_model_url ) ? '' : 'display: none;'; ?> color: #ef4444;" onclick="
										var input = document.getElementById('_wpvg_3d_model_url') || document.querySelector('input[name=\'_wpvg_3d_model_url\']');
										if (input) {
											input.value = '';
											if (window.jQuery) { jQuery(input).trigger('input').trigger('change'); }
										}
										var idInput = document.getElementById('_wpvg_3d_model_id') || document.querySelector('input[name=\'_wpvg_3d_model_id\']');
										if (idInput) { idInput.value = ''; }
										var pBox = document.querySelector('.wpvg-3d-admin-preview-box');
										var stage = document.querySelector('.wpvg-3d-admin-model-stage');
										if (stage) { stage.innerHTML = ''; }
										if (pBox) { pBox.style.display = 'none'; }
										this.style.display = 'none';
									">
										<?php esc_html_e( 'Clear Model', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
									</button>
								</div>
								<div class="wpvg-3d-admin-preview-box" style="<?php echo ! empty( $prod_3d_model_url ) ? '' : 'display: none;'; ?> margin-top: 14px; border: 1px solid #cbd5e1; border-radius: 8px; background: #f8fafc; overflow: hidden; padding: 12px;">
									<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
										<strong style="font-size: 12px; color: #1e293b;"><span class="dashicons dashicons-visibility" style="font-size: 16px; width: 16px; height: 16px; vertical-align: text-top; color: #2563eb;"></span> <?php esc_html_e( 'Live 3D Model Preview', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></strong>
										<span style="font-size: 11px; color: #64748b;"><?php esc_html_e( 'Drag to rotate 360-degree  -  Pinch / scroll to zoom', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></span>
									</div>
									<div class="wpvg-3d-admin-model-stage" style="height: 240px; width: 100%; position: relative; background: #ffffff; border-radius: 6px;">
										<?php if ( ! empty( $prod_3d_model_url ) ) : ?>
											<model-viewer src="<?php echo esc_url( $prod_3d_model_url ); ?>" auto-rotate camera-controls shadow-intensity="1" style="width: 100%; height: 100%;" class="wpvg-admin-model-viewer"></model-viewer>
										<?php endif; ?>
									</div>
									<div style="margin-top: 10px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
										<button type="button" class="button button-primary wpvg-btn-capture-3d-snapshot" style="display: inline-flex; align-items: center; gap: 6px; background: #2563eb; border-color: #2563eb; color: #ffffff;">
											<span class="dashicons dashicons-camera" style="font-size: 16px; width: 16px; height: 16px;"></span>
											<?php esc_html_e( 'Capture Current Angle as 3D Poster', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
										</button>
										<span class="wpvg-snapshot-status" style="font-size: 12px; color: #16a34a; font-weight: 600; display: none;"></span>
									</div>
								</div>
							</div>
						</div>

						<!-- 3. Custom 3D Poster / Thumbnail Image -->
						<div class="wpvg-override-row wpvg-3d-poster-row" style="<?php echo $is_model_active ? '' : 'display:none;'; ?>">
							<div class="wpvg-override-label">
								<label><?php esc_html_e( 'Custom 3D Poster Image', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
								<small><?php esc_html_e( 'Poster displayed before user activates interactive 3D view. If empty, uses WooCommerce Featured Image.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></small>
							</div>
							<div class="wpvg-override-input">
								<input type="hidden" name="_wpvg_3d_poster_id" id="_wpvg_3d_poster_id" value="<?php echo esc_attr( $prod_3d_poster_id ); ?>">
								<div class="wpvg-thumb-preview-box" style="margin-bottom: 6px;">
									<img src="<?php echo esc_url( $prod_3d_poster_url ); ?>" id="wpvg_3d_poster_preview" class="wpvg-thumb-img-preview" style="<?php echo ! empty( $prod_3d_poster_url ) ? 'max-width: 120px; border-radius: 6px;' : 'display: none;'; ?>">
								</div>
								<div style="display: flex; gap: 8px; flex-wrap: wrap;">
									<button type="button" class="button wpvg-btn-select-3d-poster">
										<?php esc_html_e( 'Choose Poster Image', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
									</button>
									<button type="button" class="button wpvg-btn-capture-3d-snapshot" style="display: inline-flex; align-items: center; gap: 4px;">
										<span class="dashicons dashicons-camera"></span> <?php esc_html_e( 'Capture from 3D Model', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
									</button>
									<button type="button" class="button wpvg-btn-remove-3d-poster" style="<?php echo ! empty( $prod_3d_poster_url ) ? '' : 'display: none;'; ?>">
										<?php esc_html_e( 'Remove Poster', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
									</button>
								</div>
							</div>
						</div>

						<!-- 4. Gallery Placement -->
						<div class="wpvg-override-row wpvg-3d-placement-row" style="<?php echo $is_common_3d_active ? '' : 'display:none;'; ?>">
							<div class="wpvg-override-label">
								<label for="_wpvg_3d_placement"><?php esc_html_e( 'Gallery Placement', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
								<small><?php esc_html_e( 'Position of the 3D model inside the product gallery.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></small>
							</div>
							<div class="wpvg-override-input">
								<select name="_wpvg_3d_placement" id="_wpvg_3d_placement">
									<option value="default" <?php selected( $prod_3d_placement, 'default' ); ?>><?php esc_html_e( 'Default (Inherit Global Placement)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
									<option value="first" <?php selected( $prod_3d_placement, 'first' ); ?>><?php esc_html_e( '1st Position - Before Images', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
									<option value="second" <?php selected( $prod_3d_placement, 'second' ); ?>><?php esc_html_e( '2nd Position - After Featured Image', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
									<option value="last" <?php selected( $prod_3d_placement, 'last' ); ?>><?php esc_html_e( 'Last Position - At End of Gallery', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
								</select>
							</div>
						</div>

						<!-- 5. Auto-Rotate Override -->
						<div class="wpvg-override-row wpvg-3d-autorotate-row" style="<?php echo $is_common_3d_active ? '' : 'display:none;'; ?>">
							<div class="wpvg-override-label">
								<label for="_wpvg_3d_auto_rotate"><?php esc_html_e( 'Auto-Rotate', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
								<small><?php esc_html_e( 'Turntable rotation behavior for this product.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></small>
							</div>
							<div class="wpvg-override-input">
								<select name="_wpvg_3d_auto_rotate" id="_wpvg_3d_auto_rotate">
									<option value="default" <?php selected( $prod_3d_autorotate, 'default' ); ?>><?php esc_html_e( 'Default (Follow Global Setting)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
									<option value="yes" <?php selected( $prod_3d_autorotate, 'yes' ); ?>><?php esc_html_e( 'Yes - Auto-Rotate Active', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
									<option value="no" <?php selected( $prod_3d_autorotate, 'no' ); ?>><?php esc_html_e( 'No - Static Orbit Only', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
								</select>
							</div>
						</div>

						<!-- 6. Custom Prompt Text -->
						<div class="wpvg-override-row wpvg-3d-btn-text-row" style="<?php echo $is_common_3d_active ? '' : 'display:none;'; ?>">
							<div class="wpvg-override-label">
								<label for="_wpvg_3d_custom_text"><?php esc_html_e( 'Button Text Override', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
								<small><?php esc_html_e( 'Optional custom overlay button text (leave blank for default).', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></small>
							</div>
							<div class="wpvg-override-input">
								<input type="text" name="_wpvg_3d_overlay_text" id="_wpvg_3d_custom_text" class="regular-text" value="<?php echo esc_attr( $prod_3d_btn_text ); ?>" placeholder="<?php esc_attr_e( 'Click for interactive 3D', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>">
							</div>
						</div>

						<!-- 7. Thumbnails Scroll & Autoplay -->
						<div class="wpvg-override-row wpvg-3d-autoplay-row" style="<?php echo $is_common_3d_active ? '' : 'display:none;'; ?>">
							<div class="wpvg-override-label">
								<label for="_wpvg_3d_slider_autoplay"><?php esc_html_e( 'Thumbnails Scroll & Autoplay', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
								<small><?php esc_html_e( 'Auto-scroll thumbnails and autoplay slides for this 3D product.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></small>
							</div>
							<div class="wpvg-override-input">
								<select name="_wpvg_3d_slider_autoplay" id="_wpvg_3d_slider_autoplay">
									<option value="default" <?php selected( $prod_3d_autoplay, 'default' ); ?>><?php esc_html_e( 'Default (Follow Global 3D Setting)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
									<option value="yes" <?php selected( $prod_3d_autoplay, 'yes' ); ?>><?php esc_html_e( 'On (Enable Thumbnails Scroll & Autoplay)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
									<option value="no" <?php selected( $prod_3d_autoplay, 'no' ); ?>><?php esc_html_e( 'Off (Disable Thumbnails Scroll & Autoplay)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
								</select>
							</div>
						</div>

						<!-- 8. Lightbox Modal Thumbnails Size -->
						<div class="wpvg-override-row wpvg-3d-modal-tsize-row" style="<?php echo $is_common_3d_active ? '' : 'display:none;'; ?>">
							<div class="wpvg-override-label">
								<label for="_wpvg_3d_modal_thumb_size"><?php esc_html_e( 'Modal Thumbnails Size (px)', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
								<small><?php esc_html_e( 'Customize thumbnail strip size in the lightbox modal popup (e.g. 48px). Leave blank to use global setting.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></small>
							</div>
							<div class="wpvg-override-input">
								<input type="number" name="_wpvg_3d_modal_thumb_size" id="_wpvg_3d_modal_thumb_size" class="small-text" min="30" max="120" step="2" value="<?php echo esc_attr( $prod_3d_modal_tsize ); ?>" placeholder="48" style="width: 80px;">
								<span class="description" style="margin-left: 6px;">px (<?php esc_html_e( 'Min: 30px, Max: 120px', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>)</span>
							</div>
						</div>
					</div>
				</div>
				<?php endif; // $show_pro_ui Pro metabox tabs. ?>
			</div>
			<?php
		}

		/**
		 * Free build: one video only (radio source). Multi-add lives in Pro ZIP only.
		 *
		 * @param array $video_urls   Video URLs.
		 * @param array $video_types  Video types.
		 * @param array $video_thumbs Thumb IDs.
		 * @param array $video_vids   Self-hosted video IDs.
		 */
		private function render_free_videos_tab( $video_urls, $video_types, $video_thumbs, $video_vids ) {
			$url       = isset( $video_urls[0] ) ? $video_urls[0] : '';
			$type      = isset( $video_types[0] ) ? $video_types[0] : 'youtube';
			$thumb     = isset( $video_thumbs[0] ) ? absint( $video_thumbs[0] ) : 0;
			$vid       = isset( $video_vids[0] ) ? absint( $video_vids[0] ) : 0;
			$thumb_url = $thumb ? wp_get_attachment_image_url( $thumb, 'thumbnail' ) : '';
			$labels    = wpvg_video_source_labels();
			?>
			<div class="wpvg-video-items-list">
				<div class="wpvg-video-item-card" data-index="0">
					<div class="wpvg-card-header">
						<span class="wpvg-card-title">
							<span class="dashicons dashicons-video-alt3"></span>
							<?php esc_html_e( 'Product Video', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
						</span>
					</div>
					<div class="wpvg-form-row">
						<label><?php esc_html_e( 'Video Source', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
						<div class="wpvg-form-field">
							<div class="wpvg-video-source-radios">
								<?php foreach ( $labels as $type_key => $type_label ) : ?>
									<label class="wpvg-video-source-radio<?php echo ( $type === $type_key ) ? ' is-selected' : ''; ?>">
										<input type="radio" name="wpvg_video_types[0]" value="<?php echo esc_attr( $type_key ); ?>" class="wpvg-video-type-radio" <?php checked( $type, $type_key ); ?>>
										<span><?php echo esc_html( $type_label ); ?></span>
									</label>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
					<div class="wpvg-form-row">
						<label><?php esc_html_e( 'Video URL / Embed', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
						<div class="wpvg-form-field">
							<input type="url" name="wpvg_video_urls[]" class="wpvg-video-url-input" value="<?php echo esc_url( $url ); ?>" placeholder="https://www.youtube.com/watch?v=...">
							<button type="button" class="button wpvg-btn-select-media" style="<?php echo ( 'self_hosted' === $type ) ? '' : 'display: none;'; ?> margin-top: 6px;"><?php esc_html_e( 'Select from Media Library', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></button>
							<button type="button" class="button wpvg-btn-preview-video"><span class="dashicons dashicons-visibility"></span> <?php esc_html_e( 'Preview Video', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></button>
							<input type="hidden" name="wpvg_video_ids[]" class="wpvg-video-id-input" value="<?php echo esc_attr( $vid ); ?>">
							<span class="wpvg-field-desc"><?php esc_html_e( 'Enter a YouTube, Vimeo, TikTok, or MP4 URL then click Preview to verify.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></span>
						</div>
					</div>
					<div class="wpvg-form-row">
						<label><?php esc_html_e( 'Custom Thumbnail', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
						<div class="wpvg-form-field">
							<input type="hidden" name="wpvg_thumb_ids[]" class="wpvg-thumb-id-input" value="<?php echo esc_attr( $thumb ); ?>">
							<div class="wpvg-thumb-preview-box">
								<img src="<?php echo esc_url( $thumb_url ? $thumb_url : '' ); ?>" class="wpvg-thumb-img-preview" style="<?php echo $thumb_url ? '' : 'display: none;'; ?>" alt="">
								<button type="button" class="button wpvg-btn-select-thumb"><?php esc_html_e( 'Choose Image', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></button>
								<button type="button" class="button wpvg-btn-remove-thumb" style="<?php echo $thumb_url ? '' : 'display: none;'; ?>"><?php esc_html_e( 'Remove', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></button>
							</div>
							<span class="wpvg-field-desc"><?php esc_html_e( 'Optional. Overrides the auto-generated video cover image.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></span>
						</div>
					</div>
				</div>
			</div>
			<?php
		}

		/**
		 * Save Metabox Data
		 *
		 * @param int     $post_id Post ID.
		 * @param WP_Post $post    Post object.
		 */
		public function save_metabox( $post_id, $post ) {
			if ( ! isset( $_POST['wpvg_metabox_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpvg_metabox_nonce'] ) ), 'wpvg_save_metabox_action' ) ) {
				return;
			}

			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return;
			}

			if ( ! current_user_can( 'edit_product', $post_id ) ) {
				return;
			}

			// Delegate Pro overrides saving via action hook (never pass raw $_POST).
			$posted_overrides = map_deep( wp_unslash( $_POST ), 'sanitize_text_field' );
			do_action( 'wpvg_save_product_custom_overrides', $post_id, $posted_overrides );

			if ( isset( $_POST['wpvg_video_urls'] ) && is_array( $_POST['wpvg_video_urls'] ) ) {
				$sanitized_urls   = array();
				$sanitized_types  = array();
				$sanitized_thumbs = array();
				$sanitized_vids   = array();

				$raw_video_urls = array_map( 'sanitize_text_field', wp_unslash( $_POST['wpvg_video_urls'] ) );
				// Radios may post as string or as wpvg_video_types[0]; selects post as a list.
				if ( isset( $_POST['wpvg_video_types'] ) && is_array( $_POST['wpvg_video_types'] ) ) {
					$raw_video_types = array_map( 'sanitize_text_field', wp_unslash( $_POST['wpvg_video_types'] ) );
				} elseif ( isset( $_POST['wpvg_video_types'] ) ) {
					$raw_video_types = array( sanitize_text_field( wp_unslash( $_POST['wpvg_video_types'] ) ) );
				} else {
					$raw_video_types = array();
				}
				$raw_thumb_ids = isset( $_POST['wpvg_thumb_ids'] ) && is_array( $_POST['wpvg_thumb_ids'] ) ? array_map( 'absint', wp_unslash( $_POST['wpvg_thumb_ids'] ) ) : array();
				$raw_video_ids = isset( $_POST['wpvg_video_ids'] ) && is_array( $_POST['wpvg_video_ids'] ) ? array_map( 'absint', wp_unslash( $_POST['wpvg_video_ids'] ) ) : array();
				// Re-index so radio key [0] aligns with urls[0].
				$raw_video_types = array_values( $raw_video_types );

				foreach ( $raw_video_urls as $i => $raw_url ) {
					$clean_url = esc_url_raw( trim( $raw_url ) );
					if ( empty( $clean_url ) ) {
						continue;
					}

					$type     = isset( $raw_video_types[ $i ] ) ? sanitize_text_field( $raw_video_types[ $i ] ) : 'youtube';
					$thumb_id = isset( $raw_thumb_ids[ $i ] ) ? absint( $raw_thumb_ids[ $i ] ) : 0;
					$vid_id   = isset( $raw_video_ids[ $i ] ) ? absint( $raw_video_ids[ $i ] ) : 0;

					$allowed_types = apply_filters( 'wpvg_allowed_video_types', wpvg_default_video_types() );
					if ( ! in_array( $type, $allowed_types, true ) ) {
						$type = 'youtube';
						if ( false === strpos( $clean_url, 'youtu' ) ) {
							continue;
						}
					}

					$sanitized_urls[]   = $clean_url;
					$sanitized_types[]  = $type;
					$sanitized_thumbs[] = $thumb_id;
					$sanitized_vids[]   = $vid_id;
				}

				if ( ! empty( $sanitized_urls ) ) {
					update_post_meta( $post_id, '_wpvg_video_urls', $sanitized_urls );
					update_post_meta( $post_id, '_wpvg_video_types', $sanitized_types );
					update_post_meta( $post_id, '_wpvg_thumb_ids', $sanitized_thumbs );
					update_post_meta( $post_id, '_wpvg_video_ids', $sanitized_vids );
				} else {
					$this->delete_meta( $post_id );
				}
			} else {
				$this->delete_meta( $post_id );
			}

			// Save 3D Product Model Settings [PRO only — free/.org ZIP has no WPVG_Pro / 3D module].
			if ( ! class_exists( 'WPVG_Pro' ) ) {
				return;
			}

			if ( isset( $_POST['_wpvg_3d_enable'] ) ) {
				$val = sanitize_text_field( wp_unslash( $_POST['_wpvg_3d_enable'] ) );
				if ( in_array( $val, array( 'default', 'yes', 'no' ), true ) ) {
					update_post_meta( $post_id, '_wpvg_3d_enable', $val );
				}
			}
			if ( isset( $_POST['_wpvg_3d_source_type'] ) ) {
				$st = sanitize_text_field( wp_unslash( $_POST['_wpvg_3d_source_type'] ) );
				if ( in_array( $st, array( 'default', 'gallery_360', 'model_file' ), true ) ) {
					update_post_meta( $post_id, '_wpvg_3d_source_type', $st );
				}
			}
			if ( isset( $_POST['_wpvg_3d_view_mode'] ) ) {
				$vm = sanitize_text_field( wp_unslash( $_POST['_wpvg_3d_view_mode'] ) );
				if ( in_array( $vm, array( 'default', 'switcher', '3d_only', '2d_only' ), true ) ) {
					update_post_meta( $post_id, '_wpvg_3d_view_mode', $vm );
					if ( 'switcher' === $vm ) {
						update_post_meta( $post_id, '_wpvg_3d_show_switcher', 'yes' );
					} elseif ( '3d_only' === $vm || '2d_only' === $vm ) {
						update_post_meta( $post_id, '_wpvg_3d_show_switcher', 'no' );
					} else {
						update_post_meta( $post_id, '_wpvg_3d_show_switcher', 'default' );
					}
				}
			} elseif ( isset( $_POST['_wpvg_3d_show_switcher'] ) ) {
				$sw = sanitize_text_field( wp_unslash( $_POST['_wpvg_3d_show_switcher'] ) );
				if ( in_array( $sw, array( 'default', 'yes', 'no' ), true ) ) {
					update_post_meta( $post_id, '_wpvg_3d_show_switcher', $sw );
				}
			}
			if ( isset( $_POST['_wpvg_3d_default_view'] ) ) {
				$dv = sanitize_text_field( wp_unslash( $_POST['_wpvg_3d_default_view'] ) );
				if ( in_array( $dv, array( 'default', '3d', '2d' ), true ) ) {
					update_post_meta( $post_id, '_wpvg_3d_default_view', $dv );
				}
			}
			if ( isset( $_POST['_wpvg_3d_model_url'] ) ) {
				$model_url = esc_url_raw( sanitize_text_field( wp_unslash( $_POST['_wpvg_3d_model_url'] ) ) );
				update_post_meta( $post_id, '_wpvg_3d_model_url', $model_url );
			}
			if ( isset( $_POST['_wpvg_3d_model_id'] ) ) {
				update_post_meta( $post_id, '_wpvg_3d_model_id', absint( $_POST['_wpvg_3d_model_id'] ) );
			}
			if ( isset( $_POST['_wpvg_3d_poster_id'] ) ) {
				update_post_meta( $post_id, '_wpvg_3d_poster_id', absint( $_POST['_wpvg_3d_poster_id'] ) );
			}
			if ( isset( $_POST['_wpvg_3d_placement'] ) ) {
				$placement = sanitize_text_field( wp_unslash( $_POST['_wpvg_3d_placement'] ) );
				if ( in_array( $placement, array( 'default', 'first', 'second', 'last' ), true ) ) {
					update_post_meta( $post_id, '_wpvg_3d_placement', $placement );
				}
			}
			if ( isset( $_POST['_wpvg_3d_auto_rotate'] ) ) {
				$ar = sanitize_text_field( wp_unslash( $_POST['_wpvg_3d_auto_rotate'] ) );
				if ( in_array( $ar, array( 'default', 'yes', 'no' ), true ) ) {
					update_post_meta( $post_id, '_wpvg_3d_auto_rotate', $ar );
				}
			}
			if ( isset( $_POST['_wpvg_3d_overlay_text'] ) ) {
				update_post_meta( $post_id, '_wpvg_3d_overlay_text', sanitize_text_field( wp_unslash( $_POST['_wpvg_3d_overlay_text'] ) ) );
			}
			if ( isset( $_POST['_wpvg_3d_spin_speed'] ) ) {
				$ss = sanitize_text_field( wp_unslash( $_POST['_wpvg_3d_spin_speed'] ) );
				if ( in_array( $ss, array( 'default', 'fast', 'normal', 'smooth' ), true ) ) {
					update_post_meta( $post_id, '_wpvg_3d_spin_speed', $ss );
				}
			}
			if ( isset( $_POST['_wpvg_3d_spin_sensitivity'] ) ) {
				$sse = sanitize_text_field( wp_unslash( $_POST['_wpvg_3d_spin_sensitivity'] ) );
				if ( in_array( $sse, array( 'default', 'high', 'normal', 'smooth' ), true ) ) {
					update_post_meta( $post_id, '_wpvg_3d_spin_sensitivity', $sse );
				}
			}
			if ( isset( $_POST['_wpvg_3d_spin_direction'] ) ) {
				$sd = sanitize_text_field( wp_unslash( $_POST['_wpvg_3d_spin_direction'] ) );
				if ( in_array( $sd, array( 'default', 'normal', 'reverse' ), true ) ) {
					update_post_meta( $post_id, '_wpvg_3d_spin_direction', $sd );
				}
			}
			if ( isset( $_POST['_wpvg_3d_slider_autoplay'] ) ) {
				$d3_ap = sanitize_text_field( wp_unslash( $_POST['_wpvg_3d_slider_autoplay'] ) );
				if ( in_array( $d3_ap, array( 'default', 'yes', 'no' ), true ) ) {
					update_post_meta( $post_id, '_wpvg_3d_slider_autoplay', $d3_ap );
				}
			}
			if ( isset( $_POST['_wpvg_3d_modal_thumb_size'] ) ) {
				$m_tsize = sanitize_text_field( wp_unslash( $_POST['_wpvg_3d_modal_thumb_size'] ) );
				if ( '' === $m_tsize ) {
					delete_post_meta( $post_id, '_wpvg_3d_modal_thumb_size' );
				} elseif ( is_numeric( $m_tsize ) ) {
					$m_tsize_int = absint( $m_tsize );
					$m_tsize_int = max( 30, min( 120, $m_tsize_int ) );
					update_post_meta( $post_id, '_wpvg_3d_modal_thumb_size', $m_tsize_int );
				}
			}

			// When Live 360 / 2Dâ†”3D switcher is active, clear GLB so frontend never shows model file
			$saved_source = get_post_meta( $post_id, '_wpvg_3d_source_type', true );
			$saved_view   = get_post_meta( $post_id, '_wpvg_3d_view_mode', true );
			$g_source     = get_option( 'wpvg_3d_source_type', 'auto' );
			$g_view       = get_option( 'wpvg_3d_view_mode', 'switcher' );
			$eff_source   = ( empty( $saved_source ) || 'default' === $saved_source ) ? $g_source : $saved_source;
			$eff_view     = ( empty( $saved_view ) || 'default' === $saved_view ) ? $g_view : $saved_view;
			if ( 'gallery_360' === $eff_source || 'switcher' === $eff_view ) {
				// Keep stored URL for later switching back, but frontend ignores it via get_3d_config().
				// No destructive delete  -  exclusivity is enforced at render time.
			}
		}

		/**
		 * Delete all video meta for a product
		 *
		 * @param int $post_id Post ID.
		 */
		private function delete_meta( $post_id ) {
			delete_post_meta( $post_id, '_wpvg_video_urls' );
			delete_post_meta( $post_id, '_wpvg_video_types' );
			delete_post_meta( $post_id, '_wpvg_thumb_ids' );
			delete_post_meta( $post_id, '_wpvg_video_ids' );
		}

		/**
		 * Render video input fields for product variation in WooCommerce
		 *
		 * @param int     $loop           Loop position.
		 * @param array   $variation_data Variation data.
		 * @param WP_Post|WC_Product_Variation $variation Variation object.
		 */
		public function render_variation_fields( $loop, $variation_data, $variation ) {
			$variation_id = is_object( $variation ) ? ( method_exists( $variation, 'get_id' ) ? $variation->get_id() : $variation->ID ) : absint( $variation );
			$video_url    = get_post_meta( $variation_id, '_wpvg_variation_video_url', true );
			$video_type   = get_post_meta( $variation_id, '_wpvg_variation_video_type', true );
			$video_type   = ! empty( $video_type ) ? $video_type : 'youtube';
			$allowed_types  = apply_filters( 'wpvg_allowed_video_types', wpvg_default_video_types() );
			if ( ! in_array( $video_type, $allowed_types, true ) ) {
				$video_type = 'youtube';
			}
			?>
			<div class="wpvg-variation-video-section form-row form-row-full" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px 14px; margin: 10px 0;">
				<h4 style="margin: 0 0 10px 0; font-size: 13px; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 6px;">
					<span class="dashicons dashicons-video-alt3" style="color: #6366f1;"></span>
					<?php esc_html_e( 'Variation Video Showcase', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
				</h4>
				<div style="display: flex; gap: 12px; flex-wrap: wrap;">
					<div style="flex: 0 0 180px;">
						<label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 12px;"><?php esc_html_e( 'Video Source', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
						<select name="wpvg_variation_video_type[<?php echo esc_attr( $loop ); ?>]" class="wpvg-variation-video-type-select" style="width: 100%;">
							<option value="youtube" <?php selected( $video_type, 'youtube' ); ?>><?php esc_html_e( 'YouTube', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
							<option value="vimeo" <?php selected( $video_type, 'vimeo' ); ?>><?php esc_html_e( 'Vimeo', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
							<option value="self_hosted" <?php selected( $video_type, 'self_hosted' ); ?>><?php esc_html_e( 'Self-Hosted MP4', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></option>
						</select>
					</div>
					<div style="flex: 1; min-width: 240px;">
						<label style="display: block; font-weight: 500; margin-bottom: 4px; font-size: 12px;"><?php esc_html_e( 'Video URL', 'nexura-product-video-media-gallery-for-woocommerce' ); ?></label>
						<input type="url" name="wpvg_variation_video_url[<?php echo esc_attr( $loop ); ?>]" class="wpvg-variation-video-url-input" value="<?php echo esc_url( $video_url ); ?>" placeholder="https://www.youtube.com/watch?v=..." style="width: 100%;">
						<button type="button" class="button wpvg-btn-select-variation-media" style="<?php echo 'self_hosted' === $video_type ? '' : 'display: none;'; ?> margin-top: 6px;">
							<?php esc_html_e( 'Select from Media Library', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
						</button>
					</div>
				</div>
				<p class="description" style="margin: 6px 0 0 0; font-size: 11px; color: #64748b;">
					<?php esc_html_e( 'When customer selects this variation, the gallery automatically showcases this video.', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>
				</p>
			</div>
			<?php
		}

		/**
		 * Save variation video fields
		 *
		 * @param int $variation_id Variation post ID.
		 * @param int $i            Index.
		 */
		public function save_variation_fields( $variation_id, $i ) {
			// Verify nonce (supports standard product edit post save, metabox save, or WooCommerce variations AJAX save)
			$has_valid_nonce = false;
			if ( isset( $_POST['wpvg_metabox_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpvg_metabox_nonce'] ) ), 'wpvg_save_metabox_action' ) ) {
				$has_valid_nonce = true;
			} elseif ( isset( $_POST['security'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['security'] ) ), 'save-variations' ) ) {
				$has_valid_nonce = true;
			} elseif ( isset( $_POST['woocommerce_meta_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ), 'woocommerce_save_data' ) ) {
				$has_valid_nonce = true;
			}

			if ( ! $has_valid_nonce ) {
				return;
			}

			if ( ! current_user_can( 'edit_product', $variation_id ) ) {
				return;
			}

			// phpcs:disable WordPress.Security.NonceVerification.Missing
			$raw_urls  = isset( $_POST['wpvg_variation_video_url'] ) && is_array( $_POST['wpvg_variation_video_url'] ) ? map_deep( wp_unslash( $_POST['wpvg_variation_video_url'] ), 'sanitize_text_field' ) : array();
			$raw_types = isset( $_POST['wpvg_variation_video_type'] ) && is_array( $_POST['wpvg_variation_video_type'] ) ? map_deep( wp_unslash( $_POST['wpvg_variation_video_type'] ), 'sanitize_text_field' ) : array();
			// phpcs:enable WordPress.Security.NonceVerification.Missing

			if ( isset( $raw_urls[ $i ] ) ) {
				$video_url = esc_url_raw( trim( $raw_urls[ $i ] ) );
				if ( ! empty( $video_url ) ) {
					update_post_meta( $variation_id, '_wpvg_variation_video_url', $video_url );
				} else {
					delete_post_meta( $variation_id, '_wpvg_variation_video_url' );
				}
			}

			if ( isset( $raw_types[ $i ] ) ) {
				$video_type    = sanitize_text_field( $raw_types[ $i ] );
				$allowed_types = apply_filters( 'wpvg_allowed_video_types', wpvg_default_video_types() );
				if ( ! in_array( $video_type, $allowed_types, true ) ) {
					$video_type = 'youtube';
				}
				update_post_meta( $variation_id, '_wpvg_variation_video_type', $video_type );
			}
		}
	}
}
