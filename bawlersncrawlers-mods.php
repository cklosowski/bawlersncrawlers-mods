<?php

/**
 * Plugin Name: Bawlers N Crawlers Site Mods
 * Plugin URI:  https://bawlersncrawlers.com
 * Description: Custom code for the Bwelers N Crawlers site
 * Version:     0.1
 * Author:      Filament Studios
 * Author URI:  https://filament-studios.com
 * License:     GPL-2.0+
 */

/**
 * Add a 2.9% + $.30 surcharge to your cart / checkout to cover PayPal Fees
 * change the $percentage to set the surcharge to a value to suit
 * Uses the WooCommerce fees API
 *
 */
function bnc_add_paypal_fees_to_cart() {
	global $woocommerce;

	if ( is_admin() && ! defined( 'DOING_AJAX' ) )
		return;

	$percentage    = 0.029;
	$existing_fees = 0.00;
	foreach ( $woocommerce->cart->fees as $fee ) {
		$existing_fees += $fee->amount;
	}
	$surcharge     = ( ( $woocommerce->cart->cart_contents_total + $woocommerce->cart->shipping_total + $existing_fees ) * $percentage ) + 0.30;
	$surcharge     = round( $surcharge, 2 );

	$woocommerce->cart->add_fee( 'Service Fees', $surcharge, true, '' );

}
add_action( 'woocommerce_cart_calculate_fees','bnc_add_paypal_fees_to_cart', 999 );

