<?php
/**
 * Shared helpers (video types, safe HTML escaping).
 *
 * @package Nexura_Product_Video_Media_Gallery_For_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wpvg_default_video_types' ) ) {
	/**
	 * Built-in video source types (fully functional in free edition).
	 *
	 * @return string[]
	 */
	function wpvg_default_video_types() {
		// All built-in sources are free (Guideline 5 — no locked providers).
		return array( 'youtube', 'vimeo', 'tiktok', 'facebook', 'self_hosted', 'iframe' );
	}
}

if ( ! function_exists( 'wpvg_allows_multiple_videos' ) ) {
	/**
	 * Whether the multi-video admin UI (Add Video) is available.
	 *
	 * True when Pro modules are loaded. Free ZIP has no Add Video control
	 * (@fs_premium_only wpvg-admin-pro.js) — not a runtime usage quota.
	 *
	 * @return bool
	 */
	function wpvg_allows_multiple_videos() {
		// Local test: define( 'WPVG_FORCE_FREE', true ); in wp-config.php
		if ( defined( 'WPVG_FORCE_FREE' ) && WPVG_FORCE_FREE ) {
			return false;
		}

		$is_pro = class_exists( 'WPVG_Pro' ) || (bool) apply_filters( 'wpvg_is_pro_active', false );
		/**
		 * Filter whether multi-video admin UI is available.
		 *
		 * @param bool $allowed Whether multi-video UI is available.
		 */
		return (bool) apply_filters( 'wpvg_allows_multiple_videos', $is_pro );
	}
}

if ( ! function_exists( 'wpvg_video_source_labels' ) ) {
	/**
	 * Labels for video source radios / selects.
	 *
	 * @return array<string,string>
	 */
	function wpvg_video_source_labels() {
		return array(
			'youtube'     => __( 'YouTube', 'nexura-product-video-media-gallery-for-woocommerce' ),
			'tiktok'      => __( 'TikTok', 'nexura-product-video-media-gallery-for-woocommerce' ),
			'facebook'    => __( 'Facebook', 'nexura-product-video-media-gallery-for-woocommerce' ),
			'vimeo'       => __( 'Vimeo', 'nexura-product-video-media-gallery-for-woocommerce' ),
			'self_hosted' => __( 'Self-Hosted (MP4 / WebM)', 'nexura-product-video-media-gallery-for-woocommerce' ),
			'iframe'      => __( 'Custom Embed / Iframe', 'nexura-product-video-media-gallery-for-woocommerce' ),
		);
	}
}

if ( ! function_exists( 'wpvg_allowed_svg_html' ) ) {
	/**
	 * Allowed tags/attrs for plugin-generated SVG and gallery markup fragments.
	 *
	 * @return array
	 */
	function wpvg_allowed_svg_html() {
		$svg_attrs = array(
			'class'               => true,
			'id'                  => true,
			'viewbox'             => true,
			'viewBox'             => true,
			'xmlns'               => true,
			'fill'                => true,
			'stroke'              => true,
			'stroke-width'        => true,
			'stroke-linecap'      => true,
			'stroke-linejoin'     => true,
			'width'               => true,
			'height'              => true,
			'aria-label'          => true,
			'aria-hidden'         => true,
			'aria-selected'       => true,
			'role'                => true,
			'focusable'           => true,
			'style'               => true,
		);

		$path_attrs = array(
			'd'              => true,
			'fill'           => true,
			'stroke'         => true,
			'stroke-width'   => true,
			'stroke-linecap' => true,
			'stroke-linejoin'=> true,
			'class'          => true,
			'points'         => true,
			'cx'             => true,
			'cy'             => true,
			'r'              => true,
			'x'              => true,
			'y'              => true,
			'x1'             => true,
			'y1'             => true,
			'x2'             => true,
			'y2'             => true,
			'width'          => true,
			'height'         => true,
			'rx'             => true,
			'ry'             => true,
			'transform'      => true,
			'style'          => true,
		);

		return array(
			'svg'      => $svg_attrs,
			'g'        => $path_attrs,
			'path'     => $path_attrs,
			'circle'   => $path_attrs,
			'polygon'  => $path_attrs,
			'polyline' => $path_attrs,
			'line'     => $path_attrs,
			'rect'     => $path_attrs,
			'text'     => array_merge(
				$path_attrs,
				array(
					'text-anchor'       => true,
					'dominant-baseline' => true,
					'font-family'       => true,
					'font-size'         => true,
					'font-weight'       => true,
					'letter-spacing'    => true,
				)
			),
			'div'      => array(
				'class'          => true,
				'id'             => true,
				'style'          => true,
				'role'           => true,
				'aria-label'     => true,
				'aria-hidden'    => true,
				'aria-selected'  => true,
				'data-mode'      => true,
				'data-total-frames' => true,
				'data-spin-speed' => true,
				'data-spin-sensitivity' => true,
				'data-spin-direction' => true,
				'data-auto-spin' => true,
				'data-product-id' => true,
				'data-load-mode' => true,
				'data-model-url' => true,
				'data-poster-url' => true,
				'data-src'       => true,
				'data-provider'  => true,
				'data-orientation'=> true,
				'data-index'     => true,
				'data-type'      => true,
				'data-video-url' => true,
				'data-embed'     => true,
				'title'          => true,
			),
			'span'     => array(
				'class' => true,
				'style' => true,
			),
			'button'   => array(
				'type'           => true,
				'class'          => true,
				'style'          => true,
				'role'           => true,
				'aria-label'     => true,
				'aria-selected'  => true,
				'data-mode'      => true,
				'title'          => true,
				'disabled'       => true,
			),
			'img'      => array(
				'class'          => true,
				'src'            => true,
				'alt'            => true,
				'style'          => true,
				'width'          => true,
				'height'         => true,
				'draggable'      => true,
				'decoding'       => true,
				'fetchpriority'  => true,
				'data-frame-index' => true,
				'loading'        => true,
			),
			'iframe'   => array(
				'class'           => true,
				'src'             => true,
				'title'           => true,
				'width'           => true,
				'height'          => true,
				'frameborder'     => true,
				'allow'           => true,
				'allowfullscreen' => true,
				'loading'         => true,
				'style'           => true,
				'referrerpolicy'  => true,
			),
			'video'    => array(
				'class'    => true,
				'src'      => true,
				'poster'   => true,
				'controls' => true,
				'autoplay' => true,
				'loop'     => true,
				'muted'    => true,
				'playsinline' => true,
				'preload'  => true,
				'style'    => true,
				'width'    => true,
				'height'   => true,
			),
			'source'   => array(
				'src'  => true,
				'type' => true,
			),
			'model-viewer' => array(
				'class'            => true,
				'src'              => true,
				'poster'           => true,
				'alt'              => true,
				'style'            => true,
				'camera-controls'  => true,
				'auto-rotate'      => true,
				'auto-rotate-delay'=> true,
				'shadow-intensity' => true,
				'exposure'         => true,
				'ar'               => true,
				'ar-modes'         => true,
				'touch-action'     => true,
				'interaction-prompt' => true,
				'loading'          => true,
				'reveal'           => true,
			),
		);
	}
}

if ( ! function_exists( 'wpvg_kses_gallery_html' ) ) {
	/**
	 * Escape late for plugin-built gallery / 3D HTML fragments.
	 *
	 * Prefer echoing with wp_kses( $html, wpvg_allowed_svg_html() ) so Plugin Check
	 * recognizes the core escaping function. This helper remains for callers that return HTML.
	 *
	 * @param string $html Raw HTML from trusted render helpers.
	 * @return string
	 */
	function wpvg_kses_gallery_html( $html ) {
		if ( ! is_string( $html ) || '' === $html ) {
			return '';
		}
		return wp_kses( $html, wpvg_allowed_svg_html() );
	}
}
