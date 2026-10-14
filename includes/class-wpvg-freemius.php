<?php
/**
 * Freemius license helpers.
 *
 * Free (WordPress.org): free features work without a license. Pro modules stay unloaded.
 * Pro (Freemius premium package): Freemius can_use_premium_code() gates loading /pro modules only.
 *
 * @package Nexura_Product_Video_Media_Gallery_For_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WPVG_Freemius' ) ) {
	class WPVG_Freemius {

		const PRO_CACHE_OPTION = 'wpvg_pro_license_active';

		/**
		 * Freemius instance.
		 *
		 * @var object|null
		 */
		private static $fs = null;

		/**
		 * Whether init() already ran.
		 *
		 * @var bool
		 */
		private static $booted = false;

		/**
		 * Initialize Freemius when the SDK is available.
		 *
		 * @return object|null
		 */
		public static function init() {
			if ( self::$booted ) {
				return self::$fs;
			}

			self::$booted = true;
			self::$fs     = function_exists( 'pvgfw_fs' ) ? pvgfw_fs() : ( function_exists( 'wpvg_fs' ) ? wpvg_fs() : null );

			if ( ! is_object( self::$fs ) ) {
				self::$fs = null;
				return null;
			}

			add_action( 'admin_init', array( __CLASS__, 'sync_pro_cache' ) );

			return self::$fs;
		}

		/**
		 * True when the installed package is the Freemius premium ZIP.
		 * False for the generated free / WordPress.org ZIP.
		 *
		 * @return bool
		 */
		public static function is_premium_build() {
			$fs = self::get_fs();
			if ( is_object( $fs ) && method_exists( $fs, 'is_premium' ) ) {
				return (bool) $fs->is_premium();
			}

			if ( defined( 'WPVG_IS_PREMIUM' ) ) {
				return (bool) WPVG_IS_PREMIUM;
			}

			$config = self::read_config();
			return ! empty( $config['is_premium'] );
		}

		/**
		 * @return array
		 */
		private static function read_config() {
			static $config = null;
			if ( null !== $config ) {
				return $config;
			}
			$file = WPVG_PLUGIN_DIR . 'includes/freemius-config.php';
			if ( file_exists( $file ) ) {
				$loaded = include $file;
				$config = is_array( $loaded ) ? $loaded : array();
			} else {
				$config = array();
			}
			return $config;
		}

		/**
		 * @return object|null
		 */
		public static function get_fs() {
			if ( ! self::$booted ) {
				self::init();
			}
			return self::$fs;
		}

		/**
		 * Active Pro license via Freemius (premium package only).
		 *
		 * WordPress.org Guideline 5: the free/.org ZIP must not lock shipped
		 * functionality behind payment. License gates apply only to the separate
		 * Freemius premium package.
		 *
		 * @return bool
		 */
		public static function is_pro() {
			static $cached = null;
			if ( null !== $cached ) {
				return $cached;
			}

			$override = apply_filters( 'wpvg_is_pro_active', null );
			if ( null !== $override ) {
				$cached = (bool) $override;
				return $cached;
			}

			// Free WordPress.org package — never licensed Pro (no DB writes on front).
			if ( ! self::is_premium_build() ) {
				$cached = false;
				return false;
			}

			$on_front = ! is_admin() && ! wp_doing_ajax() && ! ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() );
			if ( $on_front ) {
				$cached = ( '1' === (string) get_option( self::PRO_CACHE_OPTION, '0' ) );
				return $cached;
			}

			$fs = self::get_fs();
			if ( $fs && method_exists( $fs, 'can_use_premium_code' ) ) {
				$is_pro = (bool) $fs->can_use_premium_code();
				$prev   = (string) get_option( self::PRO_CACHE_OPTION, '' );
				$next   = $is_pro ? '1' : '0';
				if ( $prev !== $next ) {
					update_option( self::PRO_CACHE_OPTION, $next, false );
				}
				$cached = $is_pro;
				return $cached;
			}

			$cached = ( '1' === (string) get_option( self::PRO_CACHE_OPTION, '0' ) );
			return $cached;
		}

		/**
		 * Whether the premium ZIP may load /pro modules (license/trial).
		 *
		 * Do not use this to lock free-shipped features (WordPress.org Guideline 5).
		 * Free-shipped code should use class_exists( 'WPVG_Pro' ) / capability filters.
		 *
		 * @return bool
		 */
		public static function can_use_pro() {
			return self::is_premium_build() && self::is_pro();
		}

		/**
		 * Whether admin may show license-gated Pro UI (premium package only).
		 * Free/.org builds must not present locked/disabled Pro controls.
		 *
		 * @return bool
		 */
		public static function show_pro_locks() {
			return self::is_premium_build();
		}

		/**
		 * Refresh the frontend license cache from Freemius.
		 */
		public static function sync_pro_cache() {
			if ( ! self::is_premium_build() ) {
				// Clear stale cache once if present — avoid write storms.
				if ( '0' !== (string) get_option( self::PRO_CACHE_OPTION, '0' ) ) {
					update_option( self::PRO_CACHE_OPTION, '0', false );
				}
				return;
			}

			$fs = self::get_fs();
			if ( $fs && method_exists( $fs, 'can_use_premium_code' ) ) {
				$next = $fs->can_use_premium_code() ? '1' : '0';
				if ( (string) get_option( self::PRO_CACHE_OPTION, '' ) !== $next ) {
					update_option( self::PRO_CACHE_OPTION, $next, false );
				}
			}
		}

		/**
		 * @return string
		 */
		public static function get_upgrade_url() {
			$fs = self::get_fs();
			if ( $fs && method_exists( $fs, 'get_upgrade_url' ) ) {
				if ( method_exists( $fs, 'is_activation_mode' ) && $fs->is_activation_mode() && method_exists( $fs, 'get_activation_url' ) ) {
					return $fs->get_activation_url();
				}
				return $fs->get_upgrade_url();
			}
			return admin_url( 'edit.php?post_type=product&page=wpvg-video-gallery-settings#tab-pro' );
		}

		/**
		 * @return bool
		 */
		public static function is_sdk_ready() {
			return ( function_exists( 'pvgfw_fs' ) && is_object( pvgfw_fs() ) ) || ( function_exists( 'wpvg_fs' ) && is_object( wpvg_fs() ) );
		}
	}
}

if ( ! function_exists( 'wpvg_is_pro' ) ) {
	/**
	 * Whether the current site may use Pro features (Freemius-controlled).
	 *
	 * @return bool
	 */
	function wpvg_is_pro() {
		return WPVG_Freemius::is_pro();
	}
}

if ( ! function_exists( 'wpvg_is_premium_package' ) ) {
	/**
	 * True for Freemius premium ZIP; false for WordPress.org free ZIP.
	 *
	 * @return bool
	 */
	function wpvg_is_premium_package() {
		return WPVG_Freemius::is_premium_build();
	}
}

if ( ! function_exists( 'wpvg_can_use_pro' ) ) {
	/**
	 * Premium package + active Freemius license/trial.
	 *
	 * @return bool
	 */
	function wpvg_can_use_pro() {
		return WPVG_Freemius::can_use_pro();
	}
}

if ( ! function_exists( 'wpvg_show_pro_locks' ) ) {
	/**
	 * Whether to render license-gated Pro UI (never on free/.org package).
	 *
	 * @return bool
	 */
	function wpvg_show_pro_locks() {
		return WPVG_Freemius::show_pro_locks();
	}
}
