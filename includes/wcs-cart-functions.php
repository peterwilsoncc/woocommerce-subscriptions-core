<?php
/**
 * WooCommerce Subscriptions Cart Functions
 *
 * Functions for cart specific things, based on wc-cart-functions.php but overloaded
 * for use with recurring carts.
 *
 * @author 		Prospress
 * @category 	Core
 * @package 	WooCommerce Subscriptions/Functions
 * @version     2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Display a recurring cart's subtotal
 *
 * @access public
 * @return string
 */
function wcs_cart_totals_subtotal_html( $cart ) {
	echo wp_kses_post( wcs_cart_price_string( $cart->get_cart_subtotal(), $cart ) );
}

/**
 * Get recurring shipping methods.
 *
 * @access public
 */
function wcs_cart_totals_shipping_html() {

	$initial_packages = WC()->shipping->get_packages();

	$recurring_cart_count = count( WC()->cart->recurring_carts );

	// Create new subscriptions for each subscription product in the cart (that is not a renewal)
	foreach ( WC()->cart->recurring_carts as $recurring_cart_key => $recurring_cart ) {

		// Create shipping packages for each subscription item
		if ( WC_Subscriptions_Cart::cart_contains_subscriptions_needing_shipping() ) {

			$packages = $recurring_cart->get_shipping_packages();

			// Don't remove any subscriptions with a free trial from the shipping packages
			foreach ( $packages as $i => $base_package ) {

				$product_names = array();

				$package = WC()->shipping->calculate_shipping_for_package( $base_package );

				foreach ( $package['contents'] as $item_id => $values ) {
					$product_names[] = $values['data']->get_title() . ' &times;' . $values['quantity'];
				}
				$package_details = implode( ', ', $product_names );

				$chosen_initial_method   = isset( WC()->session->chosen_shipping_methods[ $i ] ) ? WC()->session->chosen_shipping_methods[ $i ] : '';
				$chosen_recurring_method = isset( WC()->session->chosen_shipping_methods[ $recurring_cart_key . '_' . $i ] ) ? WC()->session->chosen_shipping_methods[ $recurring_cart_key . '_' . $i ] : $chosen_initial_method;

				if ( $package['rates'] == $initial_packages[ $i ]['rates'] && apply_filters( 'wcs_cart_totals_shipping_html_price_only', true, $package, $recurring_cart ) ) {
					$shipping_method = $package['rates'][ $chosen_initial_method ];
					// packages match, display shipping amounts only
					?>
					<tr class="shipping recurring-total">
						<th><?php echo esc_html( wcs_cart_price_string( sprintf( __( 'Shipping via %s', 'woocommerce-subscriptions' ), $shipping_method->label ), $recurring_cart ) ); ?></th>
						<td>
							<?php echo wp_kses_post( wcs_cart_totals_shipping_method( $shipping_method, $recurring_cart ) ); ?>
							<?php echo '<p class="woocommerce-shipping-contents"><small>' . esc_html( $package_details ) . '</small></p>'; ?>
						</td>
					</tr>
					<?php
				} else {
					// Display the options
					$product_names = array();

					$package_name = apply_filters( 'wcs_cart_shipping_package_name', apply_filters( 'woocommerce_shipping_package_name', wcs_cart_price_string( __( 'Shipping', 'woocommerce-subscriptions' ), $recurring_cart ), $i, $package ), $recurring_cart_count, $recurring_cart );

					wc_get_template( 'cart/cart-recurring-shipping.php', array(
							'package'              => $package,
							'available_methods'    => $package['rates'],
							'show_package_details' => true,
							'package_details'      => $package_details,
							'package_name'         => $package_name,
							'index'                => $i,
							'chosen_method'        => $chosen_recurring_method,
							'recurring_cart_key'   => $recurring_cart_key,
						),
						'',
						plugin_dir_path( WC_Subscriptions::$plugin_file ) . 'templates/'
					);
				}
			}
		}
	}
}

/**
 * Display a recurring shipping methods price
 * @param  object $method
 * @return string
 */
function wcs_cart_totals_shipping_method( $method, $cart ) {

	if ( $method->cost > 0 ) {

		if ( WC()->cart->tax_display_cart == 'excl' ) {
			$label = wcs_cart_price_string( $method->cost, $cart );
			if ( $method->get_shipping_tax() > 0 && $cart->prices_include_tax ) {
				$label .= ' <small>' . WC()->countries->ex_tax_or_vat() . '</small>';
			}
		} else {
			$label = wcs_cart_price_string( $method->cost + $method->get_shipping_tax(), $cart );
			if ( $method->get_shipping_tax() > 0 && ! $cart->prices_include_tax ) {
				$label .= ' <small>' . WC()->countries->inc_tax_or_vat() . '</small>';
			}
		}
	} else {
		$label = _x( 'Free', 'shipping method price', 'woocommerce-subscriptions' );
	}

	return apply_filters( 'wcs_cart_totals_shipping_method', $label, $method, $cart );
}

/**
 * Display recurring taxes total
 *
 * @access public
 * @return void
 */
function wcs_cart_totals_taxes_total_html( $cart ) {
	$value = apply_filters( 'woocommerce_cart_totals_taxes_total_html', $cart->get_taxes_total() );
	echo wp_kses_post( apply_filters( 'wcs_cart_totals_taxes_total_html', wcs_cart_price_string( $value, $cart ), $cart ) );
}

/**
 * Display a recurring coupon's value
 *
 * @access public
 * @param string $coupon
 * @return void
 */
function wcs_cart_totals_coupon_html( $coupon, $cart ) {
	if ( is_string( $coupon ) ) {
		$coupon = new WC_Coupon( $coupon );
	}

	$value  = array();

	if ( $amount = $cart->get_coupon_discount_amount( $coupon->code, $cart->display_cart_ex_tax ) ) {
		$discount_html = '-' . wc_price( $amount );
	} else {
		$discount_html = '';
	}

	$value[] = apply_filters( 'woocommerce_coupon_discount_amount_html', $discount_html, $coupon );

	if ( $coupon->enable_free_shipping() ) {
		$value[] = __( 'Free shipping coupon', 'woocommerce-subscriptions' );
	}

	// get rid of empty array elements
	$value = implode( ', ', array_filter( $value ) );

	// Apply WooCommerce core filter
	$value = apply_filters( 'woocommerce_cart_totals_coupon_html', $value, $coupon );

	echo wp_kses_post( apply_filters( 'wcs_cart_totals_coupon_html', wcs_cart_price_string( $value, $cart ), $coupon, $cart ) );
}

/**
 * Get recurring total html including inc tax if needed
 *
 * @access public
 * @return void
 */
function wcs_cart_totals_order_total_html( $cart ) {
	$value = '<strong>' . $cart->get_total() . '</strong> ';

	// If prices are tax inclusive, show taxes here
	if ( wc_tax_enabled() && $cart->tax_display_cart == 'incl' ) {
		$tax_string_array = array();

		if ( get_option( 'woocommerce_tax_total_display' ) == 'itemized' ) {
			foreach ( $cart->get_tax_totals() as $code => $tax ) {
				$tax_string_array[] = sprintf( '%s %s', $tax->formatted_amount, $tax->label );
			}
		} else {
			$tax_string_array[] = sprintf( '%s %s', wc_price( $cart->get_taxes_total( true, true ) ), WC()->countries->tax_or_vat() );
		}

		if ( ! empty( $tax_string_array ) ) {
			// translators: placeholder is price string, denotes tax included in cart/order total
			$value .= '<small class="includes_tax">' . sprintf( __( '(Includes %s)', 'woocommerce-subscriptions' ), implode( ', ', $tax_string_array ) ) . '</small>';
		}
	}

	// Apply WooCommerce core filter
	$value = apply_filters( 'woocommerce_cart_totals_order_total_html', $value );

	echo wp_kses_post( apply_filters( 'wcs_cart_totals_order_total_html', wcs_cart_price_string( $value, $cart ), $cart ) );
}

/**
 * Return a formatted price string for a given cart object
 *
 * @access public
 * @return void
 */
function wcs_cart_price_string( $recurring_amount, $cart ) {

	return wcs_price_string( apply_filters( 'woocommerce_cart_subscription_string_details', array(
		'recurring_amount'      => $recurring_amount,

		// Schedule details
		'subscription_interval' => wcs_cart_pluck( $cart, 'subscription_period_interval' ),
		'subscription_period'   => wcs_cart_pluck( $cart, 'subscription_period', '' ),
		'subscription_length'   => wcs_cart_pluck( $cart, 'subscription_length' ),
	) ) );
}

/**
 * Return a given piece of meta data from the cart
 *
 * The data can exist on the cart object, a cart item, or product data on a cart item.
 * The first piece of data with a matching key (in that order) will be returned if it
 * is found, otherwise, the value specified with $default, will be returned.
 *
 * @access public
 * @return string
 */
function wcs_cart_pluck( $cart, $field, $default = 0 ) {

	$value = $default;

	if ( isset( $cart->$field ) ) {
		$value = $cart->$field;
	} else {
		foreach ( $cart->get_cart() as $cart_item ) {
			if ( isset( $cart_item[ $field ] ) ) {
				$value = $cart_item[ $field ];
			} elseif ( $cart_item['data']->$field ) {
				$value = $cart_item['data']->$field;
			}
		}
	}

	return $value;
}

/**
 * Append the first renewal payment date to a string (which is the order total HTML string by default)
 *
 * @access public
 * @return string
 */
function wcs_add_cart_first_renewal_payment_date( $order_total_html, $cart ) {

	if ( 0 !== $cart->next_payment_date ) {
		$first_renewal_date = date_i18n( wc_date_format(), strtotime( get_date_from_gmt( $cart->next_payment_date ) ) );
		// translators: placeholder is a date
		$order_total_html  .= '<div class="first-payment-date"><small>' . sprintf( __( 'First renewal: %s', 'woocommerce-subscriptions' ), $first_renewal_date ) .  '</small></div>';
	}

	return $order_total_html;
}
add_filter( 'wcs_cart_totals_order_total_html', 'wcs_add_cart_first_renewal_payment_date', 10, 2 );

/**
 * Return the cart item name for specific cart item
 *
 * @access public
 * @return string
 */
function wcs_get_cart_item_name( $cart_item, $include = array() ) {

	$include = wp_parse_args( $include, array(
		'attributes' => false,
	) );

	$cart_item_name = $cart_item['data']->get_title();

	if ( $include['attributes'] ) {

		$attributes_string = WC()->cart->get_item_data( $cart_item, true );
		$attributes_string = implode( ', ', array_filter( explode( "\n", $attributes_string ) ) );

		if ( ! empty( $attributes_string ) ) {
			$cart_item_name = sprintf( '%s (%s)', $cart_item_name, $attributes_string );
		}
	}

	return $cart_item_name;
}
