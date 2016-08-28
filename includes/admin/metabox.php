<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


function bnc_add_custom_product_tab( $tabs ) {
	$tabs['bawlersncralwers'] = array(
		'label'  => 'Bawlers n Crawlers',
		'target' => 'bnc_data',
		'class'  => array(),
	);

	return $tabs;
}
add_filter( 'woocommerce_product_data_tabs', 'bnc_add_custom_product_tab', PHP_INT_MAX, 1 );

function bnc_product_settings_panel() {
	global $post;

	?>
	<div id="bnc_data" class="fee_panel panel woocommerce_options_panel wc-metaboxes-wrapper">
		<div class="options_group">

			<?php
			woocommerce_wp_checkbox(
				array(
					'id'          => 'bnc_custom_enabled',
					'label'       => 'Allow customization',
					'desc_tip'    => 'true',
					'description' => 'Allow users to supply a custom string at checkout.',
					'cbvalue'     => 1,
				)
			);
			?>

			<?php
			woocommerce_wp_text_input(
				array(
					'id'          => 'bnc_custom_name',
					'label'       => 'Note Label',
					'data_type'   => 'text',
					'placeholder' => 'Notes',
					'desc_tip'    => 'true',
					'description' => 'Provide some context to what should be entered, like "Name(s)" or "Message"',
				)
			);
			?>

			<?php
			woocommerce_wp_textarea_input(
				array(
					'id'          => 'bnc_custom_desc',
					'label'       => 'Note Description',
					'placeholder' => '',
					'description' => 'Add any additional information to assist the customer.',
				)
			);
			?>

		</div>
	</div>
	<?php
}
add_action( 'woocommerce_product_data_panels', 'bnc_product_settings_panel' );

function bnc_save_product_details( $post_id ){

	$enabled = ! empty( $_POST['bnc_custom_enabled'] ) ? 1 : 0;
	if ( empty( $enabled ) ) {
		delete_post_meta( $post_id, 'bnc_custom_enabled' );
	} else {
		update_post_meta( $post_id, 'bnc_custom_enabled', $enabled );
	}

	$custom_name = $_POST['bnc_custom_name'];
	update_post_meta( $post_id, 'bnc_custom_name', esc_attr( $custom_name ) );

	$custom_desc = $_POST['bnc_custom_desc'];
	update_post_meta( $post_id, 'bnc_custom_desc', wp_kses( $custom_desc ) );

}
add_action( 'woocommerce_process_product_meta', 'bnc_save_product_details' );
