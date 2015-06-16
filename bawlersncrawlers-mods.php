<?php

/**
 * Plugin Name: Bawlers N Crawlers Site Mods
 * Plugin URI:  https://bawlersncrawlers.com
 * Description: Custom code for the Bwelers N Crawlers site
 * Version:     0.3
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

class BNC_Mods {

	protected static $_instance = null;

	public function __construct() {
		$this->hooks_and_filters();
	}

	public static function instance() {

		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	public function hooks_and_filters() {
		add_action( 'woocommerce_cart_calculate_fees',array( $this, 'add_paypal_fees_to_cart' ), 999 );
		add_filter( 'woocommerce_product_is_visible', array( $this, 'show_backorders' ), 10, 2 );
		remove_action( 'woocommerce_product_on_backorder_notification', array( 'WC_Emails', 'backorder' ) );
	}

	public function add_paypal_fees_to_cart() {
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


	public function show_backorders( $is_visible, $id ) {
		$product = new wC_Product( $id );

		if ( ! $product->is_in_stock() && ! $product->backorders_allowed() ) {
			$is_visible = false;
		}

		return $is_visible;

	}


}

function load_bnc_mods() {
	return BNC_Mods::instance();
}
add_action( 'plugins_loaded', 'load_bnc_mods' );
