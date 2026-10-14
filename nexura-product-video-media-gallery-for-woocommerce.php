<?php

/**
 * Plugin Name: Nexura Product Video Media Gallery for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/nexura-product-video-media-gallery-for-woocommerce/
 * Description: Add product videos and a responsive media gallery to WooCommerce product pages, with shortcode and page-builder support.
 * Version: 1.0.0
 * Author: Nexura Security
 * Author URI: https://nexurasecurity.com/
 * Text Domain: nexura-product-video-media-gallery-for-woocommerce
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 5.0
 * WC tested up to: 10.0
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * Freemius Deploy: upload THIS full codebase once. Freemius auto-builds:
 *   - Premium ZIP (buyers) = all code including /pro
 *   - Free ZIP (WordPress.org) = premium folders/files removed
 *
 *
 * @package Nexura_Product_Video_Media_Gallery_For_WooCommerce
 */
if ( !defined( 'ABSPATH' ) ) {
    exit;
}
/*
 * Freemius dual-version: only ONE set_basename( true ) so Deploy can flip it to false in free ZIP.
 * Do not add a second set_basename on wpvg_fs — Freemius only rewrites one occurrence.
 */
if ( function_exists( 'pvgfw_fs' ) ) {
    pvgfw_fs()->set_basename( false, __FILE__ );
} else {
    if ( !function_exists( 'pvgfw_fs' ) ) {
        /**
         * Freemius SDK helper.
         *
         * @return Freemius
         */
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
        function pvgfw_fs() {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
            global $pvgfw_fs;
            if ( !isset( $pvgfw_fs ) ) {
                require_once dirname( __FILE__ ) . '/vendor/freemius/start.php';
                // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
                $pvgfw_fs = fs_dynamic_init( array(
                    'id'               => '40581',
                    'slug'             => 'nexura-product-video-media-gallery-for-woocommerce',
                    'premium_slug'     => 'nexura-product-video-media-gallery-for-woocommerce',
                    'type'             => 'plugin',
                    'public_key'       => 'pk_7905f5ab675187e74c949d0861f80',
                    'is_premium'       => false,
                    'premium_suffix'   => 'premium',
                    'has_addons'       => false,
                    'has_paid_plans'   => true,
                    'is_org_compliant' => true,
                    'trial'            => array(
                        'days'               => 7,
                        'is_require_payment' => true,
                    ),
                    'menu'             => array(
                        'slug'       => 'wpvg-video-gallery-settings',
                        'first-path' => 'edit.php?post_type=product&page=wpvg-video-gallery-settings',
                        'parent'     => array(
                            'slug' => 'edit.php?post_type=product',
                        ),
                        'account'    => true,
                        'contact'    => true,
                        'support'    => false,
                        'pricing'    => true,
                    ),
                    'is_live'          => true,
                ) );
            }
            return $pvgfw_fs;
        }

        pvgfw_fs();
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
        do_action( 'pvgfw_fs_loaded' );
    }
    /*
     * Sync from Freemius SDK flag (Deploy flips is_premium in free ZIP).
     * Do not hardcode true — Freemius does not rewrite custom constants.
     * Override in wp-config.php only for local testing if needed.
     */
    if ( !defined( 'WPVG_IS_PREMIUM' ) ) {
        define( 'WPVG_IS_PREMIUM', pvgfw_fs()->is_premium() );
    }
    if ( !function_exists( 'wpvg_fs' ) ) {
        /**
         * Backward-compatible Freemius accessor (alias only — no set_basename).
         *
         * @return Freemius
         */
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
        function wpvg_fs() {
            return pvgfw_fs();
        }

        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
        do_action( 'wpvg_fs_loaded' );
    }
    /**
     * Clean up plugin settings on uninstall via Freemius after_uninstall hook.
     * Product video meta is preserved so a reinstall does not wipe store content.
     */
    function wpvg_fs_uninstall_cleanup() {
        $wpvg_options = array(
            'wpvg_slider_layout',
            'wpvg_gallery_ratio',
            'wpvg_modal_pattern',
            'wpvg_modal_position',
            'wpvg_thumbnails_to_show',
            'wpvg_slider_autoplay',
            'wpvg_slider_speed',
            'wpvg_video_autoplay',
            'wpvg_video_loop',
            'wpvg_video_mute',
            'wpvg_show_lightbox',
            'wpvg_show_zoom',
            'wpvg_zoom_type',
            'wpvg_zoom_mode',
            'wpvg_zoom_level',
            'wpvg_lens_shape',
            'wpvg_lens_size',
            'wpvg_zoom_window_pos',
            'wpvg_zoom_window_width',
            'wpvg_zoom_window_height',
            'wpvg_arrow_color',
            'wpvg_arrow_bg',
            'wpvg_custom_css',
            'wpvg_video_placement',
            'wpvg_mobile_layout',
            'wpvg_mobile_breakpoint',
            'wpvg_mobile_thumbs_count',
            'wpvg_mobile_hide_arrows',
            'wpvg_mobile_show_dots',
            'wpvg_mobile_disable_zoom',
            'wpvg_archive_video_enable',
            'wpvg_archive_video_style',
            'wpvg_archive_video_scope',
            'wpvg_archive_badge_pos',
            'wpvg_pro_license_active',
            'wpvg_fs_id',
            'wpvg_fs_public_key'
        );
        foreach ( $wpvg_options as $wpvg_option ) {
            delete_option( $wpvg_option );
        }
    }

    pvgfw_fs()->add_action( 'after_uninstall', 'wpvg_fs_uninstall_cleanup' );
    define( 'WPVG_VERSION', '1.0.0' );
    define( 'WPVG_PLUGIN_FILE', __FILE__ );
    define( 'WPVG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
    define( 'WPVG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
    define( 'WPVG_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
    require_once WPVG_PLUGIN_DIR . 'includes/class-wpvg-loader.php';
    register_activation_hook( __FILE__, array('WPVG_Loader', 'activate') );
    WPVG_Loader::get_instance();
}