<?php

/**
 * Plugin Name: Bawlers N Crawlers Site Mods
 * Plugin URI:  https://bawlersncrawlers.com
 * Description: Custom code for the Bwelers N Crawlers site
 * Version:     0.5
 * Author:      Filament Studios
 * Author URI:  https://filament-studios.com
 * License:     GPL-2.0+
 */

class BNC_Mods {

	protected static $_instance = null;

	public function __construct() {
		$this->constants();
		$this->includes();
		$this->hooks_and_filters();
	}

	public static function instance() {

		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	private function constants() {
		// Plugin version.
		if ( ! defined( 'BNC_VERSION' ) ) {
			define( 'BNC_VERSION', '0.5' );
		}

		// Plugin Folder Path.
		if ( ! defined( 'BNC_PLUGIN_DIR' ) ) {
			define( 'BNC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
		}

		// Plugin Folder URL.
		if ( ! defined( 'BNC_PLUGIN_URL' ) ) {
			define( 'BNC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
		}

		// Plugin Root File.
		if ( ! defined( 'BNC_PLUGIN_FILE' ) ) {
			define( 'BNC_PLUGIN_FILE', __FILE__ );
		}
	}

	private function includes() {
		include BNC_PLUGIN_DIR . '/widgets.php';
		include BNC_PLUGIN_DIR . '/includes/custom-fields.php';
		include BNC_PLUGIN_DIR . '/includes/shortcodes.php';

		if ( is_admin() ) {
			include BNC_PLUGIN_DIR . '/includes/admin/metabox.php';
		}
	}

	private function hooks_and_filters() {
		add_action( 'widgets_init', array( $this, 'widgets' ) );
		add_action( 'wp', array( $this, 'remove_hooks' ) );

		add_action( 'woocommerce_cart_calculate_fees',array( $this, 'add_paypal_fees_to_cart' ), 999 );
		//add_filter( 'woocommerce_product_is_visible', array( $this, 'show_backorders' ), 10, 2 );
		add_filter( 'woocommerce_product_categories_widget_args', array( $this, 'exclude_categories_from_widget' ), 10, 1 );
		remove_action( 'woocommerce_product_on_backorder_notification', array( 'WC_Emails', 'backorder' ) );

		// Show the user profile fields
		add_action( 'show_user_profile', array( $this, 'profile_fields' ), 99, 1 );
		add_action( 'edit_user_profile', array( $this, 'profile_fields' ), 99, 1 );

		// Save the user profile fields
		add_action( 'personal_options_update', array( $this, 'save_profile_fields' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_profile_fields' ) );

		// Check if the person is charged co-op fees when fees are being calculated
		add_filter( 'wc_cat_fees_amount', array( $this, 'maybe_remove_fees' ), 10, 2 );

		// PayPal Fees
		add_action( 'product_cat_add_form_fields', array( $this, 'add_paypal_fee_new' ) );
		add_action( 'product_cat_edit_form_fields', array( $this, 'add_paypal_fee_edit' ) );

		add_action( 'product_cat_add_form_fields', array( $this, 'add_checkout_restriction_new' ) );
		add_action( 'product_cat_edit_form_fields', array( $this, 'add_checkout_restriction_edit' ) );

		add_action( 'created_term', array( $this, 'save_category_fields' ), 10, 3 );
		add_action( 'edit_term'   , array( $this, 'save_category_fields' ), 10, 3 );

		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'maybe_restrict_cart' ), 10, 3 );

		remove_action( 'wp_footer', 'woocommerce_demo_store' );
		add_action( 'estore_before_header', 'woocommerce_demo_store' );

		add_action( 'admin_print_styles', array( $this, 'hide_nags' ) );


	}

	public function widgets() {
		register_widget( 'bnc_woocommerce_full_width_promo_widget' );
	}

	public function remove_hooks() {
		if ( is_page() ) {
			remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10 );
			remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15 );
			remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );
		}
	}

	/**
	 * Add a 2.9% + $.30 surcharge to your cart / checkout to cover PayPal Fees
	 * change the $percentage to set the surcharge to a value to suit
	 * Uses the WooCommerce fees API
	 */
	public function add_paypal_fees_to_cart() {
		global $woocommerce;

		if ( is_admin() && ! defined( 'DOING_AJAX' ) )
			return;

		$percentage    = 0.029;
		$existing_fees = 0.00;

		foreach ( $woocommerce->cart->fees as $fee ) {
			$existing_fees += $fee->amount;
		}

		$cart_contents_total = 0.00;
		foreach ( $woocommerce->cart->cart_contents as $cart_item ) {
			$item_categories = get_the_terms( $cart_item['product_id'], 'product_cat' );

			if ( ! empty( $item_categories ) ) {

				foreach ( $item_categories as $category ) {

					$exclude_fees = get_woocommerce_term_meta( $category->term_id, 'exclude_paypal_fees', true );

					if ( ! empty( $exclude_fees ) ) {
						continue 2;
					}

				}

			}

			$cart_contents_total += $cart_item['line_total'];
		}

		if ( $cart_contents_total > 0 ) {
			$surcharge     = ( ( $cart_contents_total + $woocommerce->cart->shipping_total + $existing_fees ) * $percentage ) + 0.30;
			$surcharge     = round( $surcharge, 2 );

			$woocommerce->cart->add_fee( 'Service Fees', $surcharge, true, '' );
		}
	}


	/**
	 * Add checks for if a product is not in stock and doesn't have backorders
	 *
	 * @param  bool   $is_visible If the product is visible by default
	 * @param  int    $id         The product ID
	 * @return bool               If the product should be visible
	 */
	public function show_backorders( $is_visible, $id ) {
		$product = new wC_Product( $id );

		if ( ! $product->is_in_stock() && ! $product->backorders_allowed() ) {
			$is_visible = false;
		}

		return $is_visible;

	}

	/**
	 * Exclude categories with no visible products from the category list
	 *
	 * @since  0.4
	 * @param  array $category_list_args Array of wp_list_categories parameters
	 * @return array                     Addition of exclude if items are not visible
	 */
	public function exclude_categories_from_widget( $category_list_args ) {

		$args = array(
			'hide_empty' => false,
			'hierarchical' => true,
		);

		$product_categories = get_terms( 'product_cat', $args );

		$exclude = array();
		foreach ( $product_categories as $category ) {

			$posts         = get_posts( array( 'post_type' => 'product', 'posts_per_page' => -1, 'product_cat' => $category->slug, 'fields' => 'ids' ) );
			$show_category = false;

			foreach ( $posts as $post ) {

				$product         = new wC_Product( $post );
				$visible_product = $product->is_visible();

				if ( true === $visible_product ) {
					$show_category = true;
					break;
				}

			}

			if ( false === $show_category ) {
				$exclude[] = $category->term_id;
			}

		}

		if ( ! empty( $exclude ) ) {
			$category_list_args['exclude'] = implode( ',', $exclude );
			unset( $category_list_args['include'] );
		}

		return $category_list_args;

	}

	/**
	 * Adds in the Site Specific feeds notice
	 *
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
	 *
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

	/**
	 * Category Exclude from PayPal Fees
	 *
	 * @since 1.0
	 */
	public function add_paypal_fee_new() {
		?>
		<div class="form-field">
			<label for="exclude-paypal-fees"><?php _e( 'Exclude from PayPal Fees', 'bnc-mods' ); ?></label>
			<input type="checkbox" id="exclude-paypal-fees" name="exclude_paypal_fees" value="1" />
		</div>
		<?php
	}

	/**
	 * Edit category PayPal Fields
	 *
	 * @since  1.0
	 * @param mixed $term Term (category) being edited
	 */
	public function add_paypal_fee_edit( $term ) {

		$exclude_paypal_fees = get_woocommerce_term_meta( $term->term_id, 'exclude_paypal_fees', true );
		?>
		<tr class="form-field">
			<th scope="row" valign="top"><label><?php _e( 'Exclude from PayPal Fees', 'bnc-mods' ); ?></label></th>
			<td>
				<input type="checkbox" id="exclude-paypal-fees" name="exclude_paypal_fees" value="1" <?php checked( '1', $exclude_paypal_fees, true ); ?> />
			</td>
		</tr>
		<?php
	}

	/**
	 * Add Category restrict checkout
	 *
	 * @since 1.0
	 */
	public function add_checkout_restriction_new() {
		?>
		<div class="form-field">
			<label for="restrict-checkout"><?php _e( 'Restrict Checkout to only this Category', 'bnc-mods' ); ?></label>
			<input type="checkbox" id="restrict-checkout" name="restrict_checkout" value="1" />
		</div>
		<?php
	}

	/**
	 * Edit category Restrict Checkout
	 *
	 * @since  1.0
	 * @param mixed $term Term (category) being edited
	 */
	public function add_checkout_restriction_edit( $term ) {

		$restrict_checkout = get_woocommerce_term_meta( $term->term_id, 'restrict_checkout', true );
		?>
		<tr class="form-field">
			<th scope="row" valign="top"><label><?php _e( 'Restrict Checkout to only this Category', 'bnc-mods' ); ?></label></th>
			<td>
				<input type="checkbox" id="restrict-checkout" name="restrict_checkout" value="1" <?php checked( '1', $restrict_checkout, true ); ?> />
			</td>
		</tr>
		<?php
	}

	/**
	 * save_category_fields function.
	 *
	 * @since  1.0
	 * @param mixed $term_id Term ID being saved
	 */
	public function save_category_fields( $term_id, $tt_id = '', $taxonomy = '' ) {
		$exclude_paypal_fees = isset( $_POST['exclude_paypal_fees'] ) ? '1' : '0';

		if ( 'product_cat' === $taxonomy ) {
			update_woocommerce_term_meta( $term_id, 'exclude_paypal_fees', $exclude_paypal_fees );
		}

		$restrict_checkout = isset( $_POST['restrict_checkout'] ) ? '1' : '0';

		if ( 'product_cat' === $taxonomy ) {
			update_woocommerce_term_meta( $term_id, 'restrict_checkout', $restrict_checkout );
		}

	}

	public function maybe_restrict_cart( $cart_item_data, $product_id, $variation_id ) {
		global $woocommerce;

		// If the item being added has restrictions, empty the cart of any items not in this category
		$item_categories      = get_the_terms( $product_id, 'product_cat' );
		$restrict_to_category = 0;

		if ( ! empty( $item_categories ) ) {

			foreach ( $item_categories as $category ) {

				$restrict_checkout = get_woocommerce_term_meta( $category->term_id, 'restrict_checkout', true );
				$restrict_checkout = ! empty( (int) $restrict_checkout ) ? true : false;

				if ( false === $restrict_checkout ) {
					continue;
				}

				$restrict_to_category = $category->term_id;
				break;
			}

		}

		foreach ( $woocommerce->cart->cart_contents as $cart_item_key => $cart_item ) {

			$item_categories = get_the_terms( $cart_item['product_id'], 'product_cat' );

			if ( ! empty( $restrict_to_category ) ) {

				if ( ! empty( $item_categories ) ) {

					$category_ids = array();

					foreach ( $item_categories as $category ) {

						$category_ids[] = $category->term_id;

					}

					if ( ! empty( $restrict_to_category ) && ! in_array( $restrict_to_category, $category_ids ) ) {
						unset( $woocommerce->cart->cart_contents[ $cart_item_key ] );
					}

				} else if ( ! empty( $restrict_to_category ) ) {

					unset( $woocommerce->cart->cart_contents[ $cart_item_key ] );

				}

			} else {

				foreach ( $item_categories as $category ) {

					$restrict_cart = get_woocommerce_term_meta( $category->term_id, 'restrict_checkout', true );

					if ( ! empty( $restrict_cart ) ) {

						unset( $woocommerce->cart->cart_contents[ $cart_item_key ] );
						continue 2;
					}

				}

			}

		}

		$woocommerce->cart->calculate_totals();

		return $cart_item_data;
	}

	// A bunch of styles to hide stupid "Go Pro" notices.
	public function hide_nags() {
		?>
		<style>
			.thwcfd-notice { display: none; }
			.frash-notice-rate { display: none !important; }
		</style>
		<?php
	}

}

function load_bnc_mods() {
	return BNC_Mods::instance();
}
add_action( 'plugins_loaded', 'load_bnc_mods' );

if ( !function_exists( 'wp_password_change_notification' ) ) {
    function wp_password_change_notification( $user ) {
    	return;
    }
}
