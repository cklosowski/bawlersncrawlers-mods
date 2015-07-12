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

		// Show the user profile fields
		add_action( 'show_user_profile', array( $this, 'profile_fields' ), 99, 1 );
		add_action( 'edit_user_profile', array( $this, 'profile_fields' ), 99, 1 );

		// Save the user profile fields
		add_action( 'personal_options_update', array( $this, 'save_profile_fields' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_profile_fields' ) );

		// Check if the person is charged co-op fees when fees are being calculated
		add_filter( 'wc_cat_fees_amount', array( $this, 'maybe_remove_fees' ), 10, 2 );
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

	/**
	 * Adds in the Site Specific feeds notice
	 * @param  object $user The User object being viewed
	 * @return void         Displays HTML
	 */
	public function profile_fields( $user ) {
		?>
		<h3><?php _e( 'Bawlers N Crawlers Settings', 'bnc-mods' ); ?></h3>
		<table class="form-table">
			<tr>
				<th><?php _e( 'No co-op fees', 'bnc-mods' ); ?></th>
				<td>
				<?php $no_fees = get_user_meta( $user->ID, '_bnc_no_coop_fees', true ); ?>
				<input type="checkbox" id="no-fees" name="bnc_no_coop_fees" value="1" <?php checked( true, $no_fees, true ); ?> /><label for="no-fees"><?php _e( 'User does not pay co-op fees.', 'bnc-mods' ); ?></label>
				<p class="description"><?php _e( 'When checked, the user will not pay co-op fees, but does pay service fees.', 'bnc-mods' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Saves the User Profile Settings
	 * @param  int $user_id The User ID being saved
	 * @return void         Saves to Usermeta
	 */
	public function save_profile_fields( $user_id ) {
		$no_fees = false;

		if ( ! empty( $_POST['bnc_no_coop_fees'] ) ) {
			$no_fees = true;
		}

		update_user_meta( $user_id, '_bnc_no_coop_fees', $no_fees );
	}

	/**
	 * Maybe avoid adding fees for specific users who have been labeled to not get fees
	 *
	 * @param  float $fee_amount The amount of the fee
	 * @param  int $fee_item     The ID of the item that is associated with the fee
	 * @return float             The fee amount
	 */
	public function maybe_remove_fees( $fee_amount, $fee_item ) {

		if ( empty( $fee_amount ) ) {
			return $fee_amount;
		}

		$user_id = get_current_user_id();
		$no_coop_fees = get_user_meta( $user_id, '_bnc_no_coop_fees', true );

		if ( ! empty( $no_coop_fees ) ) {
			return 0;
		}

		return $fee_amount;

	}


}

function load_bnc_mods() {
	return BNC_Mods::instance();
}
add_action( 'plugins_loaded', 'load_bnc_mods' );
