<?php
/**
 * Single Product Image & Video Gallery Template Override
 *
 * @package Nexura_Product_Video_Media_Gallery_For_WooCommerce
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
	return;
}

$wpvg_post_thumbnail_id = $product->get_image_id();
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
$wpvg_columns           = apply_filters( 'woocommerce_product_thumbnails_columns', 4 );

$wpvg_wrapper_classes = apply_filters(
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
	'woocommerce_single_product_image_gallery_classes',
	array(
		'woocommerce-product-gallery',
		'woocommerce-product-gallery--' . ( $wpvg_post_thumbnail_id ? 'with-images' : 'without-images' ),
		'woocommerce-product-gallery--columns-' . absint( $wpvg_columns ),
		'images',
		'wpvg-gallery-wrapper',
	)
);
?>
<div class="<?php echo esc_attr( implode( ' ', array_map( 'sanitize_html_class', $wpvg_wrapper_classes ) ) ); ?>" data-columns="<?php echo esc_attr( $wpvg_columns ); ?>">
	<?php
	if ( class_exists( 'WPVG_Gallery' ) ) {
		WPVG_Gallery::instance()->render_gallery_content( $product );
	}
	?>
</div>
