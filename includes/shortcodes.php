<?php

function bnc_product_pages( $atts ) {
	if ( empty( $atts ) ) {
		return '';
	}

	if ( ! isset( $atts['ids'] ) ) {
		return '';
	}

	$ids = array_map( 'trim', explode( ',', $atts['ids'] ) );

	if ( empty( $ids ) ) {
		return '';
	}

	ob_start();


	foreach ( $ids as $id ) {
		$product = new WC_Product( $id );

		if ( ! $product->is_visible() ) {
			continue;
		}

		echo WC_Shortcodes::product_page( array( 'id' => $id ) );

	}


	return ob_get_clean();
}
add_shortcode( 'product_pages', 'bnc_product_pages' );