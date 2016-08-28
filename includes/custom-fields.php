<?php
function bnc_add_custom_field_to_product() {
	global $post;

	if ( ! is_object( $post ) ) {
		return;
	}

	$custom_field_enabled = get_post_meta( $post->ID, 'bnc_custom_enabled', true );
	if ( empty( $custom_field_enabled ) ) {
		return;
	}

	$custom_field_name = get_post_meta( $post->ID, 'bnc_custom_name', true );
	$custom_field_name = empty( $custom_field_name ) ? 'Customize Your Order' : $custom_field_name;

	$custom_field_desc = get_post_meta( $post->ID, 'bnc_custom_desc', true );
	$custom_field_desc = empty( $custom_field_desc ) ? '' : '<br /><small><em>' . $custom_field_desc . '</em></small>';
	echo '<table class="variations" cellspacing="0">
		<tbody>
			<tr>
				<td class="label"><label for="color">' . $custom_field_name . '</label></td>
				<td class="value">
					<input type="text" name="' . sanitize_title_with_dashes( $custom_field_name ) . '" value="" />
					' . $custom_field_desc . '
				</td>
			</tr>
		</tbody>
	</table>';
}
add_action( 'woocommerce_before_add_to_cart_button', 'bnc_add_custom_field_to_product' );

function bnc_save_custom_entry( $cart_item_data, $product_id ) {
	$custom_field_name = get_post_meta( $product_id, 'bnc_custom_name', true );
	$custom_field_name = empty( $custom_field_name ) ? 'Customize Your Order' : $custom_field_name;
	$custom_field_name = sanitize_title_with_dashes( $custom_field_name );

	if( isset( $_REQUEST[ $custom_field_name ] ) ) {
		$cart_item_data['bnc_custom_order_data'] = sanitize_text_field( $_REQUEST[ $custom_field_name ] );
		/* below statement make sure every add to cart action as unique line item */
		$cart_item_data['unique_key'] = md5( microtime().rand() );
	}
	return $cart_item_data;
}
add_action( 'woocommerce_add_cart_item_data', 'bnc_save_custom_entry', 10, 2 );

function render_meta_on_cart_and_checkout( $cart_data, $cart_item = null ) {
	$custom_items = array();
	/* Woo 2.4.2 updates */

	if( !empty( $cart_data ) ) {
		$custom_items = $cart_data;
	}

	$custom_field_name = get_post_meta( $cart_item['product_id'], 'bnc_custom_name', true );
	$custom_field_name = empty( $custom_field_name ) ? 'Customize Your Order' : $custom_field_name;

	if( isset( $cart_item[ 'bnc_custom_order_data' ] ) ) {
		$custom_items[] = array( "name" => $custom_field_name, "value" => $cart_item['bnc_custom_order_data'] );
	}

	return $custom_items;
}
add_filter( 'woocommerce_get_item_data', 'render_meta_on_cart_and_checkout', 10, 2 );

function bnc_save_custom_fields( $item_id, $values ) {
	if( isset( $values[ 'bnc_custom_order_data' ] ) ) {
		wc_add_order_item_meta( $item_id, 'bnc_custom_order_data', $values[ 'bnc_custom_order_data' ] );
	}
}
add_action( 'woocommerce_add_order_item_meta', 'bnc_save_custom_fields', 10, 2 );

function bnc_modify_order_meta( $label, $name, $product ) {
	if ( $label !== 'bnc_custom_order_data' ) {
		return $label;
	}

	$product_id = $product->id;
	$custom_name = get_post_meta( $product_id, 'bnc_custom_name', true );

	if ( ! empty( $custom_name ) ) {
		$label = $custom_name;
	}


	return $label;
}
add_filter( 'woocommerce_attribute_label', 'bnc_modify_order_meta', 10, 3 );

