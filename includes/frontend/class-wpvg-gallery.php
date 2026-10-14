<?php
/**
 * Nexura Product Video Media Gallery for WooCommerce - Frontend Renderer
 * Clean, lightweight, responsive, accessible (Interactive Pan-Zoom, Split Lightbox & Modern Mosaic Grid)
 *
 * @package Nexura_Product_Video_Media_Gallery_For_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPVG_Gallery' ) ) {
	class WPVG_Gallery {

		/**
		 * Singleton instance. A second constructor used to register every hook again.
		 *
		 * @var WPVG_Gallery|null
		 */
		private static $instance = null;

		/**
		 * Tracks whether any product in the current catalog loop has an archive video.
		 *
		 * @var bool
		 */
		private static $has_archive_videos = false;

		/**
		 * Get the gallery renderer.
		 *
		 * @return WPVG_Gallery
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
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
			add_action( 'wp_head', array( $this, 'output_video_schema' ) );
			add_action( 'wp_head', array( $this, 'output_resource_hints' ), 2 );
			add_filter( 'script_loader_tag', array( $this, 'defer_frontend_script' ), 10, 3 );

			// Filter the single product gallery template (supports Classic and Block themes)
			add_filter( 'wc_get_template', array( $this, 'override_product_image_template' ), 99, 5 );

			// Support variation videos on variable products
			add_filter( 'woocommerce_available_variation', array( $this, 'filter_available_variation' ), 10, 3 );

		}

		/**
		 * Defer frontend script execution to eliminate render-blocking resources
		 *
		 * @param string $tag    HTML script tag.
		 * @param string $handle Script handle.
		 * @param string $src    Script source.
		 * @return string
		 */
		public function defer_frontend_script( $tag, $handle, $src ) {
			if ( 'wpvg-frontend-js' === $handle && strpos( $tag, 'defer' ) === false ) {
				return str_replace( ' src', ' defer src', $tag );
			}
			return $tag;
		}

		/**
		 * Preconnect and DNS-prefetch to video CDNs for faster loading
		 */
		public function output_resource_hints() {
			if ( ! function_exists( 'is_product' ) || ! is_product() ) {
				return;
			}

			$product_id = get_queried_object_id();
			$video_urls = $product_id ? get_post_meta( $product_id, '_wpvg_video_urls', true ) : array();
			if ( empty( $video_urls ) || ! is_array( $video_urls ) ) {
				return;
			}

			$joined = implode( ' ', $video_urls );
			if ( false !== strpos( $joined, 'youtu' ) ) {
				echo '<link rel="dns-prefetch" href="//www.youtube.com">' . "\n";
				echo '<link rel="dns-prefetch" href="//i.ytimg.com">' . "\n";
			}
			if ( false !== strpos( $joined, 'vimeo' ) ) {
				echo '<link rel="dns-prefetch" href="//player.vimeo.com">' . "\n";
				echo '<link rel="dns-prefetch" href="//i.vimeocdn.com">' . "\n";
			}
		}

		/**
		 * Override WooCommerce single-product/product-image.php template
		 *
		 * @param string $located       Template file path.
		 * @param string $template_name Template name.
		 * @param array  $args          Template arguments.
		 * @param string $template_path Template path.
		 * @param string $default_path  Default path.
		 * @return string
		 */
		public function override_product_image_template( $located, $template_name, $args, $template_path, $default_path ) {
			if ( 'single-product/product-image.php' === $template_name ) {
				$custom_template = WPVG_PLUGIN_DIR . 'templates/product-image.php';
				if ( file_exists( $custom_template ) ) {
					return $custom_template;
				}
			}
			return $located;
		}

		/**
		 * Enqueue frontend scripts & styles on single product and catalog pages
		 */
		public function enqueue_frontend_assets( $force = false ) {
			// Requires WooCommerce conditional tags (fatal on WP < 6.5 if WooCommerce is inactive).
			if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'is_product' ) ) {
				return;
			}

			$is_product = is_product();
			// Only real WooCommerce catalog views — never load gallery CSS/JS sitewide (e.g. all front pages).
			$is_catalog = ( function_exists( 'is_shop' ) && is_shop() )
				|| ( function_exists( 'is_product_taxonomy' ) && is_product_taxonomy() )
				|| ( function_exists( 'is_post_type_archive' ) && is_post_type_archive( 'product' ) );

			// Homepage only if it is the WooCommerce shop page.
			if ( ! $is_catalog && function_exists( 'is_front_page' ) && is_front_page() && function_exists( 'wc_get_page_id' ) ) {
				$shop_id = (int) wc_get_page_id( 'shop' );
				$is_catalog = ( $shop_id > 0 && (int) get_queried_object_id() === $shop_id );
			}

			if ( ! $force && ! $is_product && ! $is_catalog ) {
				return;
			}

			// Catalog assets only when Pro archive showcase module is loaded and enabled.
			if ( ! $force && $is_catalog && ! $is_product ) {
				if ( ! class_exists( 'WPVG_Pro' ) || 'yes' !== get_option( 'wpvg_archive_video_enable', 'no' ) ) {
					return;
				}
			}

			wp_enqueue_style(
				'wpvg-frontend-css',
				plugins_url( 'assets/css/wpvg-frontend.css', dirname( __DIR__ ) ),
				array(),
				WPVG_VERSION
			);

			// Inject custom arrow colors, thumbnails viewport, and mobile responsive rules from settings
			$arrow_color         = get_option( 'wpvg_arrow_color', '#1e293b' );
			$arrow_bg            = get_option( 'wpvg_arrow_bg', '#ffffff' );
			if ( empty( $arrow_color ) || ! is_string( $arrow_color ) ) {
				$arrow_color = '#1e293b';
			}
			if ( empty( $arrow_bg ) || ! is_string( $arrow_bg ) ) {
				$arrow_bg = '#ffffff';
			}
			$thumbs_count        = absint( get_option( 'wpvg_thumbnails_to_show', 4 ) );
			$thumbs_count        = $thumbs_count > 0 ? $thumbs_count : 4;
			$mobile_breakpoint   = absint( get_option( 'wpvg_mobile_breakpoint', 768 ) );
			$mobile_thumbs_count = absint( get_option( 'wpvg_mobile_thumbs_count', 4 ) );
			$mobile_thumbs_count = $mobile_thumbs_count > 0 ? $mobile_thumbs_count : 4;

			$custom_css = "
				:root {
					--wpvg-arrow-color: " . esc_attr( $arrow_color ) . ";
					--wpvg-arrow-bg: " . esc_attr( $arrow_bg ) . ";
					--wpvg-thumbs-to-show: " . esc_attr( $thumbs_count ) . ";
					--wpvg-mobile-thumbs-to-show: " . esc_attr( $mobile_thumbs_count ) . ";
				}
				@media (max-width: " . absint( $mobile_breakpoint ) . "px) {
					.wpvg-layout-vertical-left:not(.wpvg-mobile-layout-keep-desktop),
					.wpvg-layout-vertical-right:not(.wpvg-mobile-layout-keep-desktop) {
						flex-direction: column;
					}
					.wpvg-layout-vertical-left:not(.wpvg-mobile-layout-keep-desktop) .wpvg-thumbnails-wrap,
					.wpvg-layout-vertical-right:not(.wpvg-mobile-layout-keep-desktop) .wpvg-thumbnails-wrap {
						width: 100%;
						order: 2;
					}
					.wpvg-layout-vertical-left:not(.wpvg-mobile-layout-keep-desktop) .wpvg-thumbnails-list,
					.wpvg-layout-vertical-right:not(.wpvg-mobile-layout-keep-desktop) .wpvg-thumbnails-list {
						flex-direction: row;
						overflow-x: auto;
						max-height: none;
					}
					.wpvg-gallery-container:not(.wpvg-mobile-layout-keep-desktop) .wpvg-thumb-item {
						flex: 0 0 calc((100% - (var(--wpvg-thumb-gap, 8px) * (var(--wpvg-mobile-thumbs-to-show, 4) - 1))) / var(--wpvg-mobile-thumbs-to-show, 4)) !important;
						width: calc((100% - (var(--wpvg-thumb-gap, 8px) * (var(--wpvg-mobile-thumbs-to-show, 4) - 1))) / var(--wpvg-mobile-thumbs-to-show, 4)) !important;
						min-width: 52px;
						height: auto !important;
					}
					.wpvg-mobile-hide-arrows .wpvg-nav-btn {
						display: none !important;
					}
					.wpvg-mobile-hide-dots .wpvg-mobile-dots {
						display: none !important;
					}
					.wpvg-mobile-layout-dots-only .wpvg-thumbnails-wrap {
						display: none !important;
					}
					.wpvg-mobile-layout-keep-desktop {
						flex-direction: row !important;
					}
					.wpvg-thumbnails-wrap {
						--wpvg-thumbs-to-show: " . esc_attr( $mobile_thumbs_count ) . " !important;
					}
					.wpvg-thumb-scroll-btn {
						display: none !important;
					}
					.wpvg-mobile-dots {
						display: flex;
					}
					.wpvg-nav-btn {
						width: 36px;
						height: 36px;
					}
					.wpvg-grid-2col,
					.wpvg-grid-mosaic {
						grid-template-columns: 1fr;
					}
					.wpvg-grid-2col .wpvg-grid-hero,
					.wpvg-grid-mosaic .wpvg-grid-item:nth-child(3n) {
						grid-column: span 1;
					}
					/* Fullscreen Lightbox Modal Mobile Responsive Rules */
					.wpvg-lightbox-modal:not(.wpvg-modal-card) .wpvg-modal-inner {
						display: flex !important;
						flex-direction: column !important;
						width: 100vw !important;
						height: 100% !important;
						height: 100dvh !important;
						overflow: hidden !important;
					}
					.wpvg-lightbox-modal:not(.wpvg-modal-card) .wpvg-modal-main {
						order: 1 !important;
						flex: 1 1 auto !important;
						width: 100% !important;
						height: calc(100% - 90px) !important;
						padding: 12px 6px !important;
						display: flex !important;
						align-items: center !important;
						justify-content: center !important;
						position: relative !important;
						box-sizing: border-box !important;
					}
					.wpvg-lightbox-modal:not(.wpvg-modal-card) .wpvg-modal-sidebar {
						order: 2 !important;
						width: 100% !important;
						height: 90px !important;
						max-height: 90px !important;
						min-height: 90px !important;
						flex: 0 0 90px !important;
						background: rgba(15, 23, 42, 0.88) !important;
						backdrop-filter: blur(14px) !important;
						-webkit-backdrop-filter: blur(14px) !important;
						border-top: 1px solid rgba(255, 255, 255, 0.12) !important;
						border-left: none !important;
						border-right: none !important;
						border-bottom: none !important;
						padding: 10px 14px !important;
						display: flex !important;
						align-items: center !important;
						justify-content: flex-start !important;
						box-sizing: border-box !important;
						z-index: 50 !important;
					}
					.wpvg-lightbox-modal:not(.wpvg-modal-card) .wpvg-modal-close-btn {
						top: 14px !important;
						right: 14px !important;
						width: 38px !important;
						height: 38px !important;
						background: rgba(255, 255, 255, 0.95) !important;
						color: #0f172a !important;
						border-radius: 50% !important;
						box-shadow: 0 2px 10px rgba(0, 0, 0, 0.4) !important;
						z-index: 10000005 !important;
					}
					.wpvg-lightbox-modal:not(.wpvg-modal-card) .wpvg-modal-nav-btn {
						width: 36px !important;
						height: 36px !important;
						background: rgba(255, 255, 255, 0.9) !important;
						color: #0f172a !important;
						box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3) !important;
					}
					.wpvg-modal-prev {
						left: 8px !important;
					}
					.wpvg-modal-next {
						right: 8px !important;
					}
					.wpvg-modal-stage {
						width: 100% !important;
						height: 100% !important;
						padding: 0 !important;
						display: flex !important;
						align-items: center !important;
						justify-content: center !important;
					}
					.wpvg-modal-zoom-container {
						max-width: 96% !important;
						max-height: 100% !important;
						width: 100% !important;
						height: 100% !important;
						display: flex !important;
						align-items: center !important;
						justify-content: center !important;
					}
					.wpvg-modal-zoom-container img {
						max-width: 96% !important;
						max-height: 100% !important;
						object-fit: contain !important;
						margin: 0 auto !important;
					}
					.wpvg-modal-video {
						width: 95vw !important;
						max-width: 100% !important;
						aspect-ratio: 16 / 9 !important;
						border-radius: 8px !important;
					}
					.wpvg-modal-thumbs-scroll {
						overflow-x: auto !important;
						overflow-y: hidden !important;
						width: 100% !important;
						height: 100% !important;
						padding: 0 !important;
						display: flex !important;
						align-items: center !important;
						-webkit-overflow-scrolling: touch !important;
						scrollbar-width: none !important;
					}
					.wpvg-modal-thumbs-scroll::-webkit-scrollbar {
						display: none !important;
					}
					.wpvg-modal-thumbs-grid {
						display: flex !important;
						flex-direction: row !important;
						gap: 8px !important;
						width: max-content !important;
						height: 100% !important;
						align-items: center !important;
						margin: 0 !important;
						padding: 0 4px !important;
					}
					.wpvg-modal-thumb-item {
						width: 66px !important;
						height: 66px !important;
						flex: 0 0 66px !important;
						aspect-ratio: 1 / 1 !important;
						grid-column: auto !important;
						border-radius: 8px !important;
						overflow: hidden !important;
						border: 2px solid transparent !important;
					}
					.wpvg-modal-thumb-item.wpvg-active-modal-thumb {
						border-color: #ffffff !important;
						transform: scale(1.05) !important;
						box-shadow: 0 0 10px rgba(255, 255, 255, 0.45) !important;
					}
				}
			";

			wp_add_inline_style( 'wpvg-frontend-css', $custom_css );

			// Admin Custom CSS from Settings → Gallery Layout.
			$user_custom_css = get_option( 'wpvg_custom_css', '' );
			if ( is_string( $user_custom_css ) && '' !== trim( $user_custom_css ) ) {
				$user_custom_css = wp_strip_all_tags( $user_custom_css );
				$user_custom_css = str_ireplace( array( '</style>', '<style', '<?', '?>', 'javascript:' ), '', $user_custom_css );
				if ( '' !== trim( $user_custom_css ) ) {
					wp_add_inline_style( 'wpvg-frontend-css', $user_custom_css );
				}
			}

			wp_enqueue_script(
				'wpvg-frontend-js',
				plugins_url( 'assets/js/wpvg-frontend.js', dirname( __DIR__ ) ),
				array( 'jquery' ),
				WPVG_VERSION,
				array(
					'strategy'  => 'defer',
					'in_footer' => true,
				)
			);
		}

		/**
		 * Helper to parse YouTube Video ID from any URL format
		 * (standard watch, shorts, embed, youtu.be, live, mobile)
		 *
		 * @param string $url Video URL.
		 * @return string Video ID (11 chars) or empty string.
		 */
		public function get_youtube_video_id( $url ) {
			if ( preg_match( '/[?&]v=([a-zA-Z0-9_-]{11})/', $url, $m ) ) {
				return $m[1];
			}
			if ( preg_match( '#(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:embed/|v/|shorts/|live/))([a-zA-Z0-9_-]{11})#i', $url, $m ) ) {
				return $m[1];
			}
			return '';
		}

		/**
		 * Whether a URL points to a YouTube Short (vertical source).
		 *
		 * @param string $url Video URL.
		 * @return bool
		 */
		public function is_youtube_shorts_url( $url ) {
			return false !== stripos( (string) $url, '/shorts/' );
		}

		/**
		 * Best-effort YouTube poster / thumb URL.
		 * Shorts hqdefault frames often include gray letterbox bars — prefer hq720/maxres.
		 *
		 * @param string $video_id YouTube video ID.
		 * @param string $url      Original URL (for Shorts detection).
		 * @return string
		 */
		public function get_youtube_thumbnail_url( $video_id, $url = '' ) {
			$video_id = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $video_id );
			if ( '' === $video_id ) {
				return '';
			}

			// hq720 is usually cleaner for Shorts than classic hqdefault (gray bar).
			$file = $this->is_youtube_shorts_url( $url ) ? 'hq720.jpg' : 'hqdefault.jpg';

			return apply_filters(
				'wpvg_youtube_thumbnail_url',
				'https://i.ytimg.com/vi/' . $video_id . '/' . $file,
				$video_id,
				$url
			);
		}

		/**
		 * Whether the video should render in a vertical (9:16) player frame.
		 *
		 * @param string $provider Detected provider.
		 * @param string $url      Video URL.
		 * @return bool
		 */
		public function is_vertical_video( $provider, $url ) {
			if ( 'tiktok' === $provider ) {
				return true;
			}
			if ( 'youtube' === $provider && $this->is_youtube_shorts_url( $url ) ) {
				return true;
			}
			return (bool) apply_filters( 'wpvg_is_vertical_video', false, $provider, $url );
		}

		/**
		 * Helper to parse and convert YouTube URL to embed URL with JS API enabled
		 *
		 * @param string $url Video URL.
		 * @return string Embed URL.
		 */
		public function get_embed_youtube_url( $url ) {
			$video_id = $this->get_youtube_video_id( $url );
			if ( ! empty( $video_id ) ) {
				$params = array(
					'enablejsapi'    => '1',
					'rel'            => '0',
					'modestbranding' => '1',
					'playsinline'    => '1',
					'iv_load_policy' => '3',
					'fs'             => '1',
					'controls'       => '1',
				);
				return 'https://www.youtube.com/embed/' . rawurlencode( $video_id ) . '?' . http_build_query( $params, '', '&' );
			}
			return $url;
		}

		/**
		 * Helper to parse Vimeo URL to embed URL
		 *
		 * @param string $url Video URL.
		 * @return string Embed URL.
		 */
		public function get_embed_vimeo_url( $url ) {
			if ( preg_match( '/(?:vimeo\.com\/(?:channels\/(?:\w+\/)?|groups\/[^\/]*\/videos\/|album\/\d+\/video\/|video\/|)|player\.vimeo\.com\/video\/)(\d+)/i', $url, $matches ) ) {
				return 'https://player.vimeo.com/video/' . $matches[1] . '?api=1';
			}
			if ( false !== strpos( $url, 'player.vimeo.com/video/' ) ) {
				return $url;
			}
			return $url;
		}

		/**
		 * Detect the video provider from an explicit type and the URL.
		 *
		 * @param string $type Stored type.
		 * @param string $url  Video URL.
		 * @return string youtube|vimeo|tiktok|facebook|self_hosted|external
		 */
		public function detect_video_provider( $type, $url ) {
			if ( false !== strpos( $url, 'youtu' ) ) {
				return 'youtube';
			}
			if ( false !== strpos( $url, 'vimeo' ) ) {
				return 'vimeo';
			}
			if ( 'tiktok' === $type || false !== strpos( $url, 'tiktok.com' ) ) {
				return 'tiktok';
			}
			if ( 'facebook' === $type || false !== strpos( $url, 'facebook.com' ) || false !== strpos( $url, 'fb.watch' ) ) {
				return 'facebook';
			}
			if ( 'self_hosted' === $type || preg_match( '/\.(mp4|webm|ogg|mov)($|\?)/i', $url ) ) {
				return 'self_hosted';
			}
			if ( in_array( $type, array( 'youtube', 'vimeo', 'tiktok', 'facebook', 'self_hosted' ), true ) ) {
				return $type;
			}
			return 'external';
		}

		/**
		 * Build TikTok embed URL from a video page URL.
		 *
		 * @param string $url TikTok video page URL.
		 * @return string Embed URL.
		 */
		public function get_embed_tiktok_url( $url ) {
			if ( preg_match( '/(?:tiktok\.com\/(?:@[^\/]+\/video\/|v\/|embed\/v2\/|embed\/)|video\/)(\d+)/i', $url, $matches ) ) {
				return 'https://www.tiktok.com/embed/v2/' . $matches[1];
			}
			// Already an embed URL
			if ( false !== strpos( $url, 'tiktok.com/embed' ) ) {
				return $url;
			}
			return $url;
		}

		/**
		 * Build Facebook embed URL from a video page URL.
		 *
		 * @param string $url Facebook video page URL.
		 * @return string Embed URL.
		 */
		public function get_embed_facebook_url( $url ) {
			// If already a plugin embed URL, return as-is
			if ( false !== strpos( $url, 'facebook.com/plugins/video' ) ) {
				return $url;
			}
			// Build standard Facebook embed from page URL
			return 'https://www.facebook.com/plugins/video.php?href=' . rawurlencode( $url ) . '&show_text=false&width=560&height=314';
		}

		/**
		 * Poster-first player markup. Remote iframes are not in the HTML until play.
		 *
		 * @param string $type      Video type.
		 * @param string $url       Video URL.
		 * @param bool   $loop      Loop playback.
		 * @param bool   $mute      Start muted.
		 * @param string $thumb_url Poster image.
		 * @return string
		 */
		public function build_video_player_html( $type, $url, $loop, $mute, $thumb_url = '', $autoplay = false ) {
			$provider   = $this->detect_video_provider( $type, $url );
			$vertical   = $this->is_vertical_video( $provider, $url );
			$orient_cls = $vertical ? ' wpvg-video-orient-vertical' : ' wpvg-video-orient-landscape';

			if ( 'youtube' === $provider ) {
				$embed_url = $this->get_embed_youtube_url( $url );
				if ( $loop ) {
					$yt_id      = $this->get_youtube_video_id( $url );
					$embed_url .= '&loop=1';
					if ( ! empty( $yt_id ) ) {
						$embed_url .= '&playlist=' . rawurlencode( $yt_id );
					}
				}
				if ( $autoplay ) {
					$embed_url .= '&autoplay=1&mute=1';
				} elseif ( $mute ) {
					$embed_url .= '&mute=1';
				}
				return '<iframe class="wpvg-native-video-iframe' . esc_attr( $orient_cls ) . '" src="' . esc_url( $embed_url ) . '" title="' . esc_attr__( 'YouTube video player', 'nexura-product-video-media-gallery-for-woocommerce' ) . '" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen loading="' . ( $autoplay ? 'eager' : 'lazy' ) . '"></iframe>';
			} elseif ( 'vimeo' === $provider ) {
				$embed_url = $this->get_embed_vimeo_url( $url );
				if ( $loop ) {
					$embed_url .= '&loop=1';
				}
				if ( $autoplay ) {
					$embed_url .= '&autoplay=1&muted=1';
				} elseif ( $mute ) {
					$embed_url .= '&muted=1';
				}
				return '<iframe class="wpvg-native-video-iframe' . esc_attr( $orient_cls ) . '" src="' . esc_url( $embed_url ) . '" title="' . esc_attr__( 'Vimeo video player', 'nexura-product-video-media-gallery-for-woocommerce' ) . '" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen loading="' . ( $autoplay ? 'eager' : 'lazy' ) . '"></iframe>';
			} elseif ( 'tiktok' === $provider ) {
				$embed_url = $this->get_embed_tiktok_url( $url );
				return '<iframe class="wpvg-native-video-iframe wpvg-video-orient-vertical" src="' . esc_url( $embed_url ) . '" title="' . esc_attr__( 'TikTok video player', 'nexura-product-video-media-gallery-for-woocommerce' ) . '" allow="autoplay; fullscreen; picture-in-picture; encrypted-media" allowfullscreen loading="' . ( $autoplay ? 'eager' : 'lazy' ) . '"></iframe>';
			} elseif ( 'facebook' === $provider ) {
				$embed_url = $this->get_embed_facebook_url( $url );
				if ( $autoplay ) {
					$embed_url .= '&autoplay=true';
				}
				return '<iframe class="wpvg-native-video-iframe' . esc_attr( $orient_cls ) . '" src="' . esc_url( $embed_url ) . '" title="' . esc_attr__( 'Facebook video player', 'nexura-product-video-media-gallery-for-woocommerce' ) . '" allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share" allowfullscreen loading="' . ( $autoplay ? 'eager' : 'lazy' ) . '"></iframe>';
			} elseif ( 'self_hosted' === $provider ) {
				$video_attrs = 'controls playsinline preload="metadata" class="' . ( $vertical ? 'wpvg-video-orient-vertical' : '' ) . '"';
				if ( $thumb_url ) {
					$video_attrs .= ' poster="' . esc_url( $thumb_url ) . '"';
				}
				if ( $loop ) {
					$video_attrs .= ' loop';
				}
				if ( $autoplay ) {
					$video_attrs .= ' autoplay muted';
				} elseif ( $mute ) {
					$video_attrs .= ' muted';
				}
				return '<video ' . $video_attrs . '><source src="' . esc_url( $url ) . '" type="video/mp4"></video>';
			} else {
				$custom_url = $url;
				if ( $autoplay ) {
					$delim = ( false !== strpos( $custom_url, '?' ) ) ? '&' : '?';
					$custom_url .= $delim . 'autoplay=1&mute=1';
				}
				return '<iframe class="wpvg-native-video-iframe' . esc_attr( $orient_cls ) . '" src="' . esc_url( $custom_url ) . '" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="' . ( $autoplay ? 'eager' : 'lazy' ) . '"></iframe>';
			}
		}

		/**
		 * Helper to resolve setting with priority:
		 * 1. Single Product custom override (if Pro enabled)
		 * 2. Product Category default setting (if Pro enabled)
		 * 3. Global plugin setting
		 *
		 * @param int    $product_id Product ID.
		 * @param string $key        Setting key ('layout', 'ratio', 'zoom_type', 'video_pos').
		 * @param string $default    Fallback default value.
		 * @return string Resolved setting value.
		 */
		public function get_resolved_setting( $product_id, $key, $default = '' ) {
			$overrides_active = apply_filters( 'wpvg_enable_custom_overrides', false );

			// 3D Product Model active → Custom Gallery Style overrides stay OFF (zero conflict)
			$d3_active = ( $product_id && class_exists( 'WPVG_3D_Viewer' ) && WPVG_3D_Viewer::is_3d_active_for_product( $product_id ) );

			$val = null;

			// 1. Check Product-Level Custom Override (Pro) — skipped when 3D viewer is active
			if ( $overrides_active && $product_id && ! $d3_active ) {
				$override_status = get_post_meta( $product_id, '_wpvg_override_gallery', true );
				if ( 'custom' === $override_status ) {
					$product_val = get_post_meta( $product_id, '_wpvg_custom_' . $key, true );
					if ( ! empty( $product_val ) && 'default' !== $product_val ) {
						$val = $product_val;
					}
				}
			}

			// 2. Check Category-Level Override (Pro)
			if ( null === $val && $overrides_active && $product_id ) {
				$term_ids = array();
				if ( function_exists( 'wc_get_product_term_ids' ) ) {
					$term_ids = wc_get_product_term_ids( $product_id, 'product_cat' );
				} else {
					$terms = get_the_terms( $product_id, 'product_cat' );
					if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
						$term_ids = wp_list_pluck( $terms, 'term_id' );
					}
				}

				if ( ! empty( $term_ids ) && is_array( $term_ids ) ) {
					foreach ( $term_ids as $term_id ) {
						$cat_val = get_term_meta( $term_id, 'wpvg_cat_' . $key, true );
						if ( ! empty( $cat_val ) && 'default' !== $cat_val ) {
							$val = $cat_val;
							break;
						}
					}
				}
			}

			// 3. Fallback to Global Settings
			if ( null === $val ) {
				$val = $default;
				switch ( $key ) {
					case 'layout':
						$val = get_option( 'wpvg_slider_layout', $default );
						break;
					case 'ratio':
						$val = get_option( 'wpvg_gallery_ratio', $default );
						break;
					case 'zoom_type':
						$val = get_option( 'wpvg_zoom_type', $default );
						break;
					case 'video_pos':
						$val = get_option( 'wpvg_video_placement', $default );
						break;
					case 'slider_autoplay':
						$val = get_option( 'wpvg_slider_autoplay', $default );
						break;
					case 'thumbs_count':
						$val = get_option( 'wpvg_thumbnails_to_show', $default );
						break;
					case 'modal_position':
					case 'modal_pos':
						$val = get_option( 'wpvg_modal_position', $default );
						break;
					default:
						$val = get_option( 'wpvg_' . $key, $default );
						break;
				}
			}

			return apply_filters( 'wpvg_resolved_' . $key, $val, $product_id );
		}

		/**
		 * Render Gallery Content inside the wrapper
		 *
		 * @param WC_Product $product WooCommerce Product object.
		 */
		public function render_gallery_content( $product ) {
			if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
				return;
			}

			$product_id = $product->get_id();

			// Read settings with smart hierarchy (Product Custom Override > Category Default > Global Setting > Shortcode Filter)
			$layout          = $this->get_resolved_setting( $product_id, 'layout', 'vertical_left' );
			$gallery_ratio   = $this->get_resolved_setting( $product_id, 'ratio', '4_5' );
			$modal_pattern   = $this->get_resolved_setting( $product_id, 'modal_pattern', 'mosaic_2_1' );
			$allowed_modals  = apply_filters( 'wpvg_registered_modal_patterns', array( 'mosaic_2_1', 'grid_2col' ) );
			if ( ! in_array( $modal_pattern, $allowed_modals, true ) ) {
				$modal_pattern = 'mosaic_2_1';
			}
			$modal_pos       = $this->get_resolved_setting( $product_id, 'modal_position', 'right' );
			$allowed_modal_pos = apply_filters( 'wpvg_registered_modal_positions', array( 'right' ) );
			if ( ! in_array( $modal_pos, $allowed_modal_pos, true ) ) {
				$modal_pos = 'right';
			}
			$modal_thumb_size = absint( $this->get_resolved_setting( $product_id, 'modal_thumb_size', 48 ) );
			$modal_thumb_size = max( 30, min( 120, $modal_thumb_size > 0 ? $modal_thumb_size : 48 ) );
			$video_placement = $this->get_resolved_setting( $product_id, 'video_pos', 'second' );
			$video_autoplay  = $this->get_resolved_setting( $product_id, 'video_autoplay', 'no' );
			$slider_autoplay = $this->get_resolved_setting( $product_id, 'slider_autoplay', 'no' );
			$slider_speed    = floatval( $this->get_resolved_setting( $product_id, 'slider_speed', 3.5 ) );
			$slider_speed    = $slider_speed > 0 ? $slider_speed : 3.5;
			$video_loop      = $this->get_resolved_setting( $product_id, 'video_loop', 'no' );
			$video_mute      = $this->get_resolved_setting( $product_id, 'video_mute', 'unmuted' );
			$thumbs_count    = absint( $this->get_resolved_setting( $product_id, 'thumbs_count', 4 ) );
			$thumbs_count    = $thumbs_count > 0 ? $thumbs_count : 4;
			$enable_zoom     = $this->get_resolved_setting( $product_id, 'show_zoom', 'yes' );
			$zoom_type       = $this->get_resolved_setting( $product_id, 'zoom_type', 'inner' );
			$zoom_mode       = $this->get_resolved_setting( $product_id, 'zoom_mode', 'both' );
			$zoom_level      = $this->get_resolved_setting( $product_id, 'zoom_level', 2.2 );
			$lens_shape      = $this->get_resolved_setting( $product_id, 'lens_shape', 'circle' );
			$lens_size       = absint( $this->get_resolved_setting( $product_id, 'lens_size', 180 ) );
			$window_pos      = $this->get_resolved_setting( $product_id, 'zoom_window_pos', 'right' );
			$window_width    = absint( $this->get_resolved_setting( $product_id, 'zoom_window_width', 450 ) );
			$window_height   = absint( $this->get_resolved_setting( $product_id, 'zoom_window_height', 450 ) );
			$lightbox_enabled = $this->get_resolved_setting( $product_id, 'show_lightbox', 'yes' );

			// Mobile responsive options
			$mobile_layout       = $this->get_resolved_setting( $product_id, 'mobile_layout', 'bottom_horizontal' );
			$mobile_breakpoint   = absint( $this->get_resolved_setting( $product_id, 'mobile_breakpoint', 768 ) );
			$mobile_thumbs_count = absint( $this->get_resolved_setting( $product_id, 'mobile_thumbs_count', 4 ) );
			$mobile_hide_arrows  = $this->get_resolved_setting( $product_id, 'mobile_hide_arrows', 'no' );
			$mobile_show_dots    = $this->get_resolved_setting( $product_id, 'mobile_show_dots', 'yes' );
			$mobile_disable_zoom = $this->get_resolved_setting( $product_id, 'mobile_disable_zoom', 'yes' );

			// Validate requested layout, ratio, zoom, and placement against registered registry
			$registered_layouts    = apply_filters( 'wpvg_registered_layouts', array( 'vertical_left', 'horizontal' ) );
			$registered_ratios     = apply_filters( 'wpvg_registered_ratios', array( '4_5', '1_1' ) );
			$registered_zooms      = apply_filters( 'wpvg_registered_zooms', array( 'inner', 'disabled' ) );
			$registered_placements = apply_filters( 'wpvg_registered_video_placements', array( 'first', 'second' ) );
			$registered_mobiles    = apply_filters( 'wpvg_registered_mobile_layouts', array( 'bottom_horizontal', 'keep_desktop' ) );

			if ( ! in_array( $layout, $registered_layouts, true ) ) {
				$layout = 'vertical_left';
			}
			if ( ! in_array( $gallery_ratio, $registered_ratios, true ) ) {
				$gallery_ratio = '4_5';
			}
			if ( ! in_array( $zoom_type, $registered_zooms, true ) ) {
				$zoom_type = 'inner';
			}
			if ( ! in_array( $video_placement, $registered_placements, true ) ) {
				$video_placement = 'second';
			}
			if ( ! in_array( $mobile_layout, $registered_mobiles, true ) ) {
				$mobile_layout = 'bottom_horizontal';
			}

			$is_grid       = ( 'grid_mosaic' === $layout || 'grid_2col' === $layout || 'grid_1col' === $layout );
			$layout_class  = 'wpvg-layout-' . str_replace( '_', '-', $layout );
			$ratio_class   = 'wpvg-ratio-' . str_replace( '_', '-', $gallery_ratio );
			if ( 'disabled' === $zoom_type ) {
				$enable_zoom = 'no';
			}

			$mobile_classes = array();
			$mobile_classes[] = 'wpvg-mobile-layout-' . str_replace( '_', '-', $mobile_layout );
			if ( 'yes' === $mobile_hide_arrows ) {
				$mobile_classes[] = 'wpvg-mobile-hide-arrows';
			}
			if ( 'no' === $mobile_show_dots ) {
				$mobile_classes[] = 'wpvg-mobile-hide-dots';
			}
			$mobile_class_str = implode( ' ', $mobile_classes );

			// 1. Gather Images
			$image_slides = array();
			$post_thumbnail_id = $product->get_image_id();

			if ( $post_thumbnail_id ) {
				$image_slides[] = array(
					'id'        => $post_thumbnail_id,
					'full_src'  => wp_get_attachment_image_url( $post_thumbnail_id, 'full' ),
					'large_src' => wp_get_attachment_image_url( $post_thumbnail_id, 'large' ),
					'thumb_src' => wp_get_attachment_image_url( $post_thumbnail_id, 'woocommerce_thumbnail' ),
					'alt'       => get_post_meta( $post_thumbnail_id, '_wp_attachment_image_alt', true ) ? get_post_meta( $post_thumbnail_id, '_wp_attachment_image_alt', true ) : get_the_title( $product_id ),
				);
			} else {
				$placeholder_url = wc_placeholder_img_src( 'woocommerce_single' );
				$image_slides[] = array(
					'id'        => 0,
					'full_src'  => $placeholder_url,
					'large_src' => $placeholder_url,
					'thumb_src' => wc_placeholder_img_src( 'woocommerce_thumbnail' ),
					'alt'       => get_the_title( $product_id ),
				);
			}

			$gallery_image_ids = $product->get_gallery_image_ids();
			if ( ! empty( $gallery_image_ids ) && is_array( $gallery_image_ids ) ) {
				foreach ( $gallery_image_ids as $attachment_id ) {
					if ( (int) $attachment_id === (int) $post_thumbnail_id ) {
						continue; // Avoid duplicating featured image
					}
					$image_slides[] = array(
						'id'        => $attachment_id,
						'full_src'  => wp_get_attachment_image_url( $attachment_id, 'full' ),
						'large_src' => wp_get_attachment_image_url( $attachment_id, 'large' ),
						'thumb_src' => wp_get_attachment_image_url( $attachment_id, 'woocommerce_thumbnail' ),
						'alt'       => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ? get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) : get_the_title( $product_id ),
					);
				}
			}

			// 2. Gather Videos (OFF when 3D Product Model is active on single product)
			$video_slides = array();
			$d3_active    = ( class_exists( 'WPVG_3D_Viewer' ) && WPVG_3D_Viewer::is_3d_active_for_product( $product_id ) );

			if ( ! $d3_active ) {
				$video_urls = get_post_meta( $product_id, '_wpvg_video_urls', true );
				if ( ! is_array( $video_urls ) ) {
					$video_urls = ! empty( $video_urls ) ? array( $video_urls ) : array();
				}

				$video_types = get_post_meta( $product_id, '_wpvg_video_types', true );
				if ( ! is_array( $video_types ) ) {
					$video_types = array();
				}

				$thumb_ids = get_post_meta( $product_id, '_wpvg_thumb_ids', true );
				if ( ! is_array( $thumb_ids ) ) {
					$thumb_ids = array();
				}

				if ( ! empty( $video_urls ) ) {
					$loop_param     = ( 'yes' === $video_loop );
					$mute_param     = ( 'muted' === $video_mute );
					$autoplay_param = ( 'yes' === $video_autoplay );

					foreach ( $video_urls as $v_idx => $v_url ) {
						if ( empty( $v_url ) ) {
							continue;
						}

						$v_type     = isset( $video_types[ $v_idx ] ) ? $video_types[ $v_idx ] : 'youtube';
						$v_thumb_id = isset( $thumb_ids[ $v_idx ] ) ? absint( $thumb_ids[ $v_idx ] ) : 0;

						$provider    = $this->detect_video_provider( $v_type, $v_url );
						$is_vertical = $this->is_vertical_video( $provider, $v_url );

						$v_thumb_url = $v_thumb_id ? wp_get_attachment_image_url( $v_thumb_id, 'woocommerce_thumbnail' ) : '';
						if ( empty( $v_thumb_url ) && 'youtube' === $provider ) {
							$yt_id = $this->get_youtube_video_id( $v_url );
							if ( ! empty( $yt_id ) ) {
								$v_thumb_url = $this->get_youtube_thumbnail_url( $yt_id, $v_url );
							}
						}
						if ( empty( $v_thumb_url ) && ! empty( $image_slides ) ) {
							$v_thumb_url = $image_slides[0]['thumb_src'];
						}

						// Autoplay on initial load only if video is placed at the first position (slide 0)
						$is_initial_slide = ( 0 === $v_idx && 'first' === $video_placement );
						$slide_autoplay   = ( $autoplay_param && $is_initial_slide );

						$video_embed_html = $this->build_video_player_html( $v_type, $v_url, $loop_param, $mute_param, $v_thumb_url, $slide_autoplay );
						if ( '' === $video_embed_html ) {
							continue;
						}

						$video_slides[] = array(
							'type'        => $provider,
							'url'         => $v_url,
							'embed_html'  => $video_embed_html,
							'thumb_src'   => $v_thumb_url,
							'is_vertical' => $is_vertical,
						);
					}
				}
			}

			// 2.5 Gather 3D Model / 360° Turntable Slide [PRO]
			$slide_3d = null;
			if ( class_exists( 'WPVG_3D_Viewer' ) && WPVG_3D_Viewer::is_3d_active_for_product( $product_id ) ) {
				$config_3d      = WPVG_3D_Viewer::get_3d_config( $product_id );
				$has_3d_content = ( 'gallery_360' === $config_3d['effective_source'] && ! empty( $config_3d['frames'] ) ) || ! empty( $config_3d['model_url'] );

				if ( $has_3d_content ) {
					// Ensure frontend 3D assets (model-viewer, styles, script) are loaded
					WPVG_3D_Viewer::instance()->enqueue_frontend_3d_assets( $product_id );

					$thumb_src = ! empty( $config_3d['poster_url'] ) ? $config_3d['poster_url'] : ( ! empty( $image_slides ) ? $image_slides[0]['thumb_src'] : '' );

					$slide_3d = array(
						'type' => '3d',
						'data' => array(
							'source_type' => $config_3d['effective_source'],
							'model_url'   => $config_3d['model_url'],
							'poster_url'  => $config_3d['poster_url'],
							'frames'      => $config_3d['frames'],
							'thumb_src'   => $thumb_src,
							'placement'   => $config_3d['placement'],
							'config'      => $config_3d,
						),
					);
				}
			}

			// 3. Merge Slides based on Placement Option
			$all_slides = array();
			if ( empty( $video_slides ) ) {
				// No videos, all images
				foreach ( $image_slides as $is ) {
					$all_slides[] = array( 'type' => 'image', 'data' => $is );
				}
			} elseif ( 'first' === $video_placement ) {
				// 1st Position: Videos first, then all images
				foreach ( $video_slides as $vs ) {
					$all_slides[] = array( 'type' => 'video', 'data' => $vs );
				}
				foreach ( $image_slides as $is ) {
					$all_slides[] = array( 'type' => 'image', 'data' => $is );
				}
			} elseif ( 'last' === $video_placement ) {
				// Last Position: All images first, then videos at the end
				foreach ( $image_slides as $is ) {
					$all_slides[] = array( 'type' => 'image', 'data' => $is );
				}
				foreach ( $video_slides as $vs ) {
					$all_slides[] = array( 'type' => 'video', 'data' => $vs );
				}
			} elseif ( 'third' === $video_placement ) {
				// 3rd Position: 1st & 2nd images, then video, then remaining images
				$first_two = array_slice( $image_slides, 0, 2 );
				$rest_imgs = array_slice( $image_slides, 2 );

				foreach ( $first_two as $is ) {
					$all_slides[] = array( 'type' => 'image', 'data' => $is );
				}
				foreach ( $video_slides as $vs ) {
					$all_slides[] = array( 'type' => 'video', 'data' => $vs );
				}
				foreach ( $rest_imgs as $is ) {
					$all_slides[] = array( 'type' => 'image', 'data' => $is );
				}
			} else {
				// Default & 'second': 2nd Position (Featured Image 1st, Video 2nd, then rest of images)
				$featured_img = array_slice( $image_slides, 0, 1 );
				$gallery_imgs = array_slice( $image_slides, 1 );

				foreach ( $featured_img as $is ) {
					$all_slides[] = array( 'type' => 'image', 'data' => $is );
				}
				foreach ( $video_slides as $vs ) {
					$all_slides[] = array( 'type' => 'video', 'data' => $vs );
				}
				foreach ( $gallery_imgs as $is ) {
					$all_slides[] = array( 'type' => 'image', 'data' => $is );
				}
			}

			// Merge 3D Model Slide based on placement (first, second, last)
			if ( ! empty( $slide_3d ) ) {
				$pos_3d = $slide_3d['data']['placement'];
				if ( 'last' === $pos_3d ) {
					$all_slides[] = $slide_3d;
				} elseif ( 'second' === $pos_3d && count( $all_slides ) > 0 ) {
					array_splice( $all_slides, 1, 0, array( $slide_3d ) );
				} else {
					// Default 'first': 1st position
					array_unshift( $all_slides, $slide_3d );
				}
				// 3D Active: Ensure bottom modal strip is used
				$modal_pos = 'bottom';

				// 3D Active: Apply 3D-specific thumbnails scroll & slider autoplay setting
				if ( isset( $slide_3d['data']['config']['slider_autoplay'] ) ) {
					$slider_autoplay = $slide_3d['data']['config']['slider_autoplay'] ? 'yes' : 'no';
				}
				if ( isset( $slide_3d['data']['config']['modal_thumb_size'] ) ) {
					$modal_thumb_size = absint( $slide_3d['data']['config']['modal_thumb_size'] );
				}
			}

			$slides_for_js = array();
			foreach ( $all_slides as $wpvg_slide ) {
				if ( 'image' === $wpvg_slide['type'] ) {
					$slides_for_js[] = array(
						'type' => 'image',
						'data' => array(
							'full_src'  => $wpvg_slide['data']['full_src'],
							'large_src' => $wpvg_slide['data']['large_src'],
							'thumb_src' => $wpvg_slide['data']['thumb_src'],
							'alt'       => $wpvg_slide['data']['alt'],
						),
					);
				} elseif ( '3d' === $wpvg_slide['type'] ) {
					$slides_for_js[] = array(
						'type' => '3d',
						'data' => array(
							'source_type' => isset( $wpvg_slide['data']['source_type'] ) ? $wpvg_slide['data']['source_type'] : 'model_file',
							'model_url'   => isset( $wpvg_slide['data']['model_url'] ) ? $wpvg_slide['data']['model_url'] : '',
							'poster_url'  => isset( $wpvg_slide['data']['poster_url'] ) ? $wpvg_slide['data']['poster_url'] : '',
							'frames'      => isset( $wpvg_slide['data']['frames'] ) ? $wpvg_slide['data']['frames'] : array(),
							'thumb_src'   => isset( $wpvg_slide['data']['thumb_src'] ) ? $wpvg_slide['data']['thumb_src'] : '',
						),
					);
				} else {
					$slides_for_js[] = array(
						'type' => 'video',
						'data' => array(
							'url'        => isset( $wpvg_slide['data']['url'] ) ? $wpvg_slide['data']['url'] : '',
							'thumb_src'  => isset( $wpvg_slide['data']['thumb_src'] ) ? $wpvg_slide['data']['thumb_src'] : '',
							'embed_html' => isset( $wpvg_slide['data']['embed_html'] ) ? $wpvg_slide['data']['embed_html'] : '',
						),
					);
				}
			}

			$has_3d_attr        = ! empty( $slide_3d ) ? 'yes' : 'no';
			$d3_source_attr     = ! empty( $slide_3d ) ? esc_attr( $slide_3d['data']['source_type'] ) : '';
			$d3_view_mode_attr  = ! empty( $slide_3d ) ? esc_attr( $slide_3d['data']['config']['view_mode'] ) : '2d_only';
			$d3_def_view_attr   = ! empty( $slide_3d ) ? esc_attr( $slide_3d['data']['config']['default_view'] ) : '2d';
			$d3_act_view_attr   = ( ! empty( $slide_3d ) && '3d' === $slide_3d['data']['config']['default_view'] ) ? '3d' : '2d';

			$play_icon_svg = '<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" fill="rgba(0,0,0,0.6)" stroke="#fff" stroke-width="1.5"/><polygon points="10,8 16,12 10,16" fill="#fff"/></svg>';
			$prev_arrow_svg = '<svg viewBox="0 0 24 24"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>';
			$next_arrow_svg = '<svg viewBox="0 0 24 24"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg>';
			$zoom_icon_svg = '<svg viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>';
			$close_icon_svg = '<svg viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>';
			$scroll_up_svg = '<svg viewBox="0 0 24 24"><path d="M7.41 15.41L12 10.83l4.59 4.58L18 14l-6-6-6 6z"/></svg>';
			$scroll_down_svg = '<svg viewBox="0 0 24 24"><path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6z"/></svg>';
			?>

			<div class="wpvg-gallery-container <?php echo esc_attr( $layout_class . ' ' . $ratio_class . ' ' . $mobile_class_str ); ?>" data-product-id="<?php echo esc_attr( $product_id ); ?>" data-layout="<?php echo esc_attr( $layout ); ?>" data-ratio="<?php echo esc_attr( $gallery_ratio ); ?>" data-thumbs-to-show="<?php echo esc_attr( $thumbs_count ); ?>" style="--wpvg-thumbs-to-show: <?php echo esc_attr( $thumbs_count ); ?>; --wpvg-mobile-thumbs-to-show: <?php echo esc_attr( $mobile_thumbs_count ); ?>;" data-mobile-breakpoint="<?php echo esc_attr( $mobile_breakpoint ); ?>" data-mobile-disable-zoom="<?php echo esc_attr( $mobile_disable_zoom ); ?>" data-zoom-enabled="<?php echo esc_attr( $enable_zoom ); ?>" data-zoom-type="<?php echo esc_attr( $zoom_type ); ?>" data-zoom-mode="<?php echo esc_attr( $zoom_mode ); ?>" data-zoom-level="<?php echo esc_attr( $zoom_level ); ?>" data-lens-shape="<?php echo esc_attr( $lens_shape ); ?>" data-lens-size="<?php echo esc_attr( $lens_size ); ?>" data-window-pos="<?php echo esc_attr( $window_pos ); ?>" data-window-width="<?php echo esc_attr( $window_width ); ?>" data-window-height="<?php echo esc_attr( $window_height ); ?>" data-lightbox-enabled="<?php echo esc_attr( $lightbox_enabled ); ?>" data-video-autoplay="<?php echo esc_attr( $video_autoplay ); ?>" data-slider-autoplay="<?php echo esc_attr( $slider_autoplay ); ?>" data-slider-speed="<?php echo esc_attr( round( $slider_speed * 1000 ) ); ?>" data-video-loop="<?php echo esc_attr( $video_loop ); ?>" data-video-mute="<?php echo esc_attr( $video_mute ); ?>" data-has-3d="<?php echo esc_attr( $has_3d_attr ); ?>" data-3d-source="<?php echo esc_attr( $d3_source_attr ); ?>" data-view-mode="<?php echo esc_attr( $d3_view_mode_attr ); ?>" data-default-view="<?php echo esc_attr( $d3_def_view_attr ); ?>" data-active-view="<?php echo esc_attr( $d3_act_view_attr ); ?>" data-slides="<?php echo esc_attr( wp_json_encode( $slides_for_js ) ); ?>">

				<?php
				if ( ! empty( $slide_3d ) && ! empty( $slide_3d['data']['config']['show_switcher'] ) ) {
					echo wp_kses( WPVG_3D_Viewer::render_view_mode_toggle_html( $slide_3d['data']['config'] ), wpvg_allowed_svg_html() );
				}
				?>

				<?php if ( $is_grid ) : ?>
					<!-- Modern Grid & Mosaic Layout -->
					<?php
					$grid_css = 'wpvg-grid-2col';
					if ( 'grid_mosaic' === $layout ) {
						$grid_css = 'wpvg-grid-mosaic';
					} elseif ( 'grid_1col' === $layout ) {
						$grid_css = 'wpvg-grid-1col';
					}
					?>
					<div class="wpvg-gallery-grid <?php echo esc_attr( $grid_css ); ?>">
						<?php foreach ( $all_slides as $idx => $slide ) : ?>
							<div class="wpvg-grid-item <?php echo ( '3d' === $slide['type'] ? 'wpvg-grid-3d ' : '' ) . ( ( 0 === $idx && 'grid_2col' === $layout ) ? 'wpvg-grid-hero' : '' ); ?>" data-index="<?php echo esc_attr( $idx ); ?>">
								<?php if ( '3d' === $slide['type'] ) : ?>
									<div class="wpvg-grid-3d-wrap" style="position:relative; width:100%; height:100%; min-height:350px;">
										<?php echo wp_kses( WPVG_3D_Viewer::render_3d_slide_html( $slide['data']['config'], $idx, false ), wpvg_allowed_svg_html() ); ?>
										<span class="wpvg-thumb-3d-badge"><?php echo wp_kses( WPVG_3D_Viewer::render_3d_badge_svg(), wpvg_allowed_svg_html() ); ?></span>
									</div>
								<?php elseif ( 'video' === $slide['type'] ) : ?>
									<div class="wpvg-grid-video-wrap">
										<?php echo wp_kses( $slide['data']['embed_html'], wpvg_allowed_svg_html() ); ?>
									</div>
								<?php else : ?>
									<div class="wpvg-zoomable-container">
										<?php if ( 0 === $idx ) : ?>
											<img src="<?php echo esc_url( $slide['data']['large_src'] ); ?>" data-full-src="<?php echo esc_url( $slide['data']['full_src'] ); ?>" alt="<?php echo esc_attr( $slide['data']['alt'] ); ?>" fetchpriority="high" decoding="async">
										<?php else : ?>
											<img src="<?php echo esc_url( $slide['data']['large_src'] ); ?>" data-full-src="<?php echo esc_url( $slide['data']['full_src'] ); ?>" alt="<?php echo esc_attr( $slide['data']['alt'] ); ?>" loading="lazy" decoding="async">
										<?php endif; ?>
										<button type="button" class="wpvg-zoom-trigger" aria-label="<?php esc_attr_e( 'Zoom image', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>">
											<?php echo wp_kses( $zoom_icon_svg, wpvg_allowed_svg_html() ); ?>
										</button>
									</div>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>

				<?php else : ?>
					<!-- Carousel / Slider Layout -->

					<!-- Thumbnails Navigation Strip -->
					<?php if ( count( $all_slides ) > 1 ) : ?>
						<div class="wpvg-thumbnails-wrap">
							<?php if ( 'wpvg-layout-vertical-left' === $layout_class || 'wpvg-layout-vertical-right' === $layout_class ) : ?>
								<button type="button" class="wpvg-thumb-scroll-btn wpvg-thumb-scroll-up" aria-label="<?php esc_attr_e( 'Scroll up', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>">
									<?php echo wp_kses( $scroll_up_svg, wpvg_allowed_svg_html() ); ?>
								</button>
							<?php elseif ( 'wpvg-layout-horizontal' === $layout_class ) : ?>
								<button type="button" class="wpvg-thumb-scroll-btn wpvg-thumb-scroll-left" aria-label="<?php esc_attr_e( 'Scroll left', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>">
									<?php echo wp_kses( $prev_arrow_svg, wpvg_allowed_svg_html() ); ?>
								</button>
							<?php endif; ?>

							<div class="wpvg-thumbnails-scroll-container">
								<ul class="wpvg-thumbnails-list">
									<?php foreach ( $all_slides as $idx => $slide ) : ?>
										<?php
										$thumb_extra = '';
										if ( '3d' === $slide['type'] ) {
											$thumb_extra = 'wpvg-thumb-3d';
										} elseif ( 'video' === $slide['type'] ) {
											$thumb_extra = 'wpvg-thumb-video';
											if ( ! empty( $slide['data']['is_vertical'] ) ) {
												$thumb_extra .= ' wpvg-thumb-vertical';
											}
										}
										if ( 0 === $idx ) {
											$thumb_extra .= ( $thumb_extra ? ' ' : '' ) . 'wpvg-active-thumb';
										}
										?>
										<li class="wpvg-thumb-item <?php echo esc_attr( $thumb_extra ); ?>" data-index="<?php echo esc_attr( $idx ); ?>" data-type="<?php echo esc_attr( $slide['type'] ); ?>">
											<?php if ( '3d' === $slide['type'] ) : ?>
												<img src="<?php echo esc_url( $slide['data']['thumb_src'] ); ?>" alt="<?php esc_attr_e( '3D Model Thumbnail', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>" loading="lazy" decoding="async">
												<span class="wpvg-thumb-3d-badge"><?php echo wp_kses( WPVG_3D_Viewer::render_3d_badge_svg(), wpvg_allowed_svg_html() ); ?></span>
											<?php elseif ( 'video' === $slide['type'] ) : ?>
												<img src="<?php echo esc_url( $slide['data']['thumb_src'] ); ?>" alt="<?php esc_attr_e( 'Video Thumbnail', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>" loading="lazy" decoding="async">
												<span class="wpvg-thumb-video-badge"><?php echo wp_kses( $play_icon_svg, wpvg_allowed_svg_html() ); ?></span>
											<?php else : ?>
												<img src="<?php echo esc_url( $slide['data']['thumb_src'] ); ?>" alt="<?php echo esc_attr( $slide['data']['alt'] ); ?>" loading="lazy" decoding="async">
											<?php endif; ?>
										</li>
									<?php endforeach; ?>
								</ul>
							</div>

							<?php if ( 'wpvg-layout-vertical-left' === $layout_class || 'wpvg-layout-vertical-right' === $layout_class ) : ?>
								<button type="button" class="wpvg-thumb-scroll-btn wpvg-thumb-scroll-down" aria-label="<?php esc_attr_e( 'Scroll down', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>">
									<?php echo wp_kses( $scroll_down_svg, wpvg_allowed_svg_html() ); ?>
								</button>
							<?php elseif ( 'wpvg-layout-horizontal' === $layout_class ) : ?>
								<button type="button" class="wpvg-thumb-scroll-btn wpvg-thumb-scroll-right" aria-label="<?php esc_attr_e( 'Scroll right', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>">
									<?php echo wp_kses( $next_arrow_svg, wpvg_allowed_svg_html() ); ?>
								</button>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<!-- Main Slider Box -->
					<div class="wpvg-main-slider-wrap">
						<div class="wpvg-slider-stage">
							<?php foreach ( $all_slides as $idx => $slide ) : ?>
								<?php if ( '3d' === $slide['type'] ) : ?>
									<?php echo wp_kses( WPVG_3D_Viewer::render_3d_slide_html( $slide['data']['config'], $idx, ( 0 === $idx ) ), wpvg_allowed_svg_html() ); ?>
								<?php elseif ( 'video' === $slide['type'] ) : ?>
									<div class="wpvg-slide-item wpvg-slide-type-video <?php echo 0 === $idx ? 'wpvg-active-slide' : ''; ?>" data-index="<?php echo esc_attr( $idx ); ?>">
										<div class="wpvg-slide-video">
											<?php echo wp_kses( $slide['data']['embed_html'], wpvg_allowed_svg_html() ); ?>
										</div>
									</div>
								<?php else : ?>
									<div class="wpvg-slide-item wpvg-slide-type-image <?php echo 0 === $idx ? 'wpvg-active-slide' : ''; ?>" data-index="<?php echo esc_attr( $idx ); ?>">
										<div class="wpvg-zoomable-container">
											<?php if ( 0 === $idx ) : ?>
												<img src="<?php echo esc_url( $slide['data']['large_src'] ); ?>" data-full-src="<?php echo esc_url( $slide['data']['full_src'] ); ?>" alt="<?php echo esc_attr( $slide['data']['alt'] ); ?>" fetchpriority="high" decoding="async">
											<?php else : ?>
												<img src="<?php echo esc_url( $slide['data']['large_src'] ); ?>" data-full-src="<?php echo esc_url( $slide['data']['full_src'] ); ?>" alt="<?php echo esc_attr( $slide['data']['alt'] ); ?>" loading="lazy" decoding="async">
											<?php endif; ?>
										</div>
									</div>
								<?php endif; ?>
							<?php endforeach; ?>

							<!-- Prev / Next Navigation Arrows -->
							<?php if ( count( $all_slides ) > 1 ) : ?>
								<button type="button" class="wpvg-nav-btn wpvg-nav-prev" aria-label="<?php esc_attr_e( 'Previous', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>">
									<?php echo wp_kses( $prev_arrow_svg, wpvg_allowed_svg_html() ); ?>
								</button>
								<button type="button" class="wpvg-nav-btn wpvg-nav-next" aria-label="<?php esc_attr_e( 'Next', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>">
									<?php echo wp_kses( $next_arrow_svg, wpvg_allowed_svg_html() ); ?>
								</button>
							<?php endif; ?>

							<!-- Corner Zoom Button -->
							<button type="button" class="wpvg-zoom-trigger" aria-label="<?php esc_attr_e( 'Zoom image', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>">
								<?php echo wp_kses( $zoom_icon_svg, wpvg_allowed_svg_html() ); ?>
							</button>
						</div>

						<!-- Mobile Pagination Dots -->
						<?php if ( count( $all_slides ) > 1 ) : ?>
							<div class="wpvg-mobile-dots">
								<?php foreach ( $all_slides as $idx => $s ) : ?>
									<span class="wpvg-dot <?php echo 0 === $idx ? 'wpvg-active-dot' : ''; ?>" data-index="<?php echo esc_attr( $idx ); ?>"></span>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>

			</div>

			<!-- Fullscreen Lightbox Modal (Centered Card Layout) -->
			<div id="wpvg-lightbox-modal" class="wpvg-lightbox-modal wpvg-modal-pos-<?php echo esc_attr( $modal_pos ); ?><?php echo ! empty( $slide_3d ) ? ' wpvg-modal-card' : ''; ?>" data-product-id="<?php echo esc_attr( $product_id ); ?>" style="--wpvg-modal-thumb-size: <?php echo esc_attr( $modal_thumb_size ); ?>px;" data-modal-pattern="<?php echo esc_attr( $modal_pattern ); ?>" data-zoom-mode="<?php echo esc_attr( $zoom_mode ); ?>" data-zoom-level="<?php echo esc_attr( $zoom_level ); ?>" data-video-autoplay="<?php echo esc_attr( $video_autoplay ); ?>" role="dialog" aria-modal="true" aria-hidden="true">
				<div class="wpvg-modal-inner">
					<!-- Close Button Top Right (Round Black Icon Button) -->
					<button type="button" class="wpvg-modal-close-btn" aria-label="<?php esc_attr_e( 'Close', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>">
						<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
							<line x1="18" y1="6" x2="6" y2="18"></line>
							<line x1="6" y1="6" x2="18" y2="18"></line>
						</svg>
					</button>

					<!-- Main Canvas Area (Center with Interactive Zoom) -->
					<main class="wpvg-modal-main">
						<!-- Prev / Next Controls (Clean bold black chevrons) -->
						<?php if ( count( $all_slides ) > 1 ) : ?>
							<button type="button" class="wpvg-modal-nav-btn wpvg-modal-prev" aria-label="<?php esc_attr_e( 'Previous', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>">
								<svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round">
									<polyline points="15 18 9 12 15 6"></polyline>
								</svg>
							</button>
							<button type="button" class="wpvg-modal-nav-btn wpvg-modal-next" aria-label="<?php esc_attr_e( 'Next', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>">
								<svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round">
									<polyline points="9 18 15 12 9 6"></polyline>
								</svg>
							</button>
						<?php endif; ?>

						<!-- Modal Zoom Stage -->
						<div class="wpvg-modal-stage">
							<div class="wpvg-modal-zoom-container">
								<button type="button" class="wpvg-zoom-trigger" aria-label="<?php esc_attr_e( 'Toggle Zoom', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>">
									<?php echo wp_kses( $zoom_icon_svg, wpvg_allowed_svg_html() ); ?>
								</button>
							</div>
						</div>
					</main>

					<!-- Bottom Panel with Horizontal Thumbnails Strip -->
					<?php if ( count( $all_slides ) > 1 ) : ?>
						<aside class="wpvg-modal-sidebar wpvg-pattern-<?php echo esc_attr( $modal_pattern ); ?>">
							<div class="wpvg-modal-thumbs-scroll">
								<div class="wpvg-modal-thumbs-grid">
									<?php foreach ( $all_slides as $idx => $slide ) : ?>
										<?php
										$full_img_url = ( 'image' === $slide['type'] ) ? ( ! empty( $slide['data']['full_src'] ) ? $slide['data']['full_src'] : $slide['data']['large_src'] ) : ( '3d' === $slide['type'] ? ( ! empty( $slide['data']['poster_url'] ) ? $slide['data']['poster_url'] : $slide['data']['thumb_src'] ) : '' );
										$alt_text     = ( 'image' === $slide['type'] ) ? $slide['data']['alt'] : ( '3d' === $slide['type'] ? __( '3D Product Model', 'nexura-product-video-media-gallery-for-woocommerce' ) : '' );
										$embed_code   = ( 'video' === $slide['type'] ) ? $slide['data']['embed_html'] : '';
										$model_3d_url = ( '3d' === $slide['type'] ) ? $slide['data']['model_url'] : '';
										$modal_extra  = '';
										if ( '3d' === $slide['type'] ) {
											$modal_extra = 'wpvg-modal-thumb-3d';
										} elseif ( 'video' === $slide['type'] ) {
											$modal_extra = 'wpvg-modal-thumb-video';
											if ( ! empty( $slide['data']['is_vertical'] ) ) {
												$modal_extra .= ' wpvg-thumb-vertical';
											}
										}
										if ( 0 === $idx ) {
											$modal_extra .= ( $modal_extra ? ' ' : '' ) . 'wpvg-active-modal-thumb';
										}
										?>
										<div class="wpvg-modal-thumb-item <?php echo esc_attr( $modal_extra ); ?>"
											 data-index="<?php echo esc_attr( $idx ); ?>"
											 data-type="<?php echo esc_attr( $slide['type'] ); ?>"
											 data-full-src="<?php echo esc_url( $full_img_url ); ?>"
											 data-alt="<?php echo esc_attr( $alt_text ); ?>"
											 <?php if ( ! empty( $model_3d_url ) ) : ?>data-model-url="<?php echo esc_url( $model_3d_url ); ?>"<?php endif; ?>
											 <?php if ( ! empty( $embed_code ) ) : ?>data-embed="<?php echo esc_attr( $embed_code ); ?>"<?php endif; ?>>
											<?php if ( '3d' === $slide['type'] ) : ?>
												<img src="<?php echo esc_url( $slide['data']['thumb_src'] ); ?>" alt="<?php esc_attr_e( '3D Model', 'nexura-product-video-media-gallery-for-woocommerce' ); ?>" loading="lazy">
												<span class="wpvg-modal-3d-badge"><?php echo wp_kses( WPVG_3D_Viewer::render_3d_badge_svg(), wpvg_allowed_svg_html() ); ?></span>
											<?php elseif ( 'video' === $slide['type'] ) : ?>
												<img src="<?php echo esc_url( $slide['data']['thumb_src'] ); ?>" alt="video thumb" loading="lazy">
												<span class="wpvg-modal-video-badge"><?php echo wp_kses( $play_icon_svg, wpvg_allowed_svg_html() ); ?></span>
											<?php else : ?>
												<img src="<?php echo esc_url( $slide['data']['thumb_src'] ); ?>" alt="<?php echo esc_attr( $slide['data']['alt'] ); ?>" loading="lazy">
											<?php endif; ?>
										</div>
									<?php endforeach; ?>
								</div>
							</div>
						</aside>
					<?php endif; ?>

				</div>
			</div>
			<?php
		}

		/**
		 * Generate Video Schema (JSON-LD) for SEO
		 */
		public function output_video_schema() {
			if ( ! function_exists( 'is_product' ) || ! is_product() ) {
				return;
			}

			$schema_enabled = ( 'yes' === get_option( 'wpvg_video_schema', 'yes' ) );
			/**
			 * Filter whether VideoObject JSON-LD is output on single product pages.
			 *
			 * @param bool $schema_enabled Whether schema is enabled in settings (default yes).
			 */
			if ( ! apply_filters( 'wpvg_enable_video_schema', $schema_enabled ) ) {
				return;
			}

			global $product;
			if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
				return;
			}

			$product_id = $product->get_id();
			$video_urls = get_post_meta( $product_id, '_wpvg_video_urls', true );
			if ( empty( $video_urls ) || ! is_array( $video_urls ) || empty( $video_urls[0] ) ) {
				return;
			}

			$video_types = get_post_meta( $product_id, '_wpvg_video_types', true );
			$video_type  = ( is_array( $video_types ) && ! empty( $video_types[0] ) ) ? $video_types[0] : 'youtube';
			$provider    = $this->detect_video_provider( $video_type, $video_urls[0] );
			$embed_url   = $video_urls[0];
			if ( 'youtube' === $provider ) {
				$embed_url = $this->get_embed_youtube_url( $video_urls[0] );
			} elseif ( 'vimeo' === $provider ) {
				$embed_url = $this->get_embed_vimeo_url( $video_urls[0] );
			}

			$thumb = wp_get_attachment_image_url( $product->get_image_id(), 'full' );
			$schema = array(
				'@context'    => 'https://schema.org',
				'@type'       => 'VideoObject',
				'name'        => get_the_title( $product_id ),
				'description' => wp_strip_all_tags( $product->get_short_description() ? $product->get_short_description() : get_the_title( $product_id ) ),
				'uploadDate'  => get_the_date( 'c', $product_id ),
				'contentUrl'  => $video_urls[0],
				'embedUrl'    => $embed_url,
			);
			if ( $thumb ) {
				$schema['thumbnailUrl'] = $thumb;
			}

			echo '<script type="application/ld+json">' . wp_json_encode( $schema ) . '</script>' . "\n";
		}

		/**
		 * Add variation video data to WooCommerce available variation payload
		 *
		 * @param array                $data      Variation data.
		 * @param WC_Product_Variable  $product   Parent variable product.
		 * @param WC_Product_Variation $variation Variation object.
		 * @return array
		 */
		public function filter_available_variation( $data, $product, $variation ) {
			if ( ! $variation ) {
				return $data;
			}

			$variation_id = is_object( $variation ) ? $variation->get_id() : absint( $variation );
			$video_url    = get_post_meta( $variation_id, '_wpvg_variation_video_url', true );

			if ( ! empty( $video_url ) ) {
				$video_type = get_post_meta( $variation_id, '_wpvg_variation_video_type', true );
				$video_type = ! empty( $video_type ) ? $video_type : 'youtube';

				$loop_param = ( 'yes' === get_option( 'wpvg_video_loop', 'no' ) );
				$mute_param = ( 'muted' === get_option( 'wpvg_video_mute', 'unmuted' ) );

				$thumb_id  = is_object( $variation ) ? $variation->get_image_id() : 0;
				$thumb_src = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'woocommerce_thumbnail' ) : ( isset( $data['image']['thumb_src'] ) ? $data['image']['thumb_src'] : '' );

				$video_embed_html = $this->build_video_player_html( $video_type, $video_url, $loop_param, $mute_param, $thumb_src );
				if ( '' === $video_embed_html ) {
					return $data;
				}

				$data['wpvg_video'] = array(
					'type'       => $this->detect_video_provider( $video_type, $video_url ),
					'url'        => $video_url,
					'embed_html' => $video_embed_html,
					'thumb_src'  => $thumb_src,
				);
			}

			return $data;
		}

		/**
		 * Check if product has active archive videos enabled
		 *
		 * @param int $product_id Product ID.
		 * @return bool
		 */
		public function has_active_archive_video( $product_id ) {
			if ( class_exists( 'WPVG_Pro' ) ) {
				return WPVG_Pro::instance()->has_active_archive_video( $product_id );
			}
			return false;
		}
	}
}
