<?php
/**
 * Freemius package flags (fallback when SDK helpers are unavailable).
 *
 * Main bootstrap sets WPVG_IS_PREMIUM from pvgfw_fs()->is_premium() after SDK init,
 * so free ZIP tracks Freemius Deploy's rewritten is_premium flag.
 *
 * You may also define in wp-config.php for local Pro testing:
 *   define( 'WPVG_IS_PREMIUM', true );
 *
 * @package Nexura_Product_Video_Media_Gallery_For_WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'id'         => '40581',
	'public_key' => 'pk_7905f5ab675187e74c949d0861f80',
	// Prefer WPVG_IS_PREMIUM from main file (synced from SDK). Default false if unset.
	'is_premium' => ( defined( 'WPVG_IS_PREMIUM' ) && WPVG_IS_PREMIUM ),
	'is_live'    => true,
);
