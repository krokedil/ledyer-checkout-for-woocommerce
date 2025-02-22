<?php
/**
 * Cart lines processor.
 *
 * @package Ledyer\Requests\Helpers
 */

namespace Ledyer\Requests\Helpers;

use WC_Tax;

defined( 'ABSPATH' ) || exit();

/**
 * Cart class.
 *
 * Class that formats WooCommerce cart contents for Ledyer API.
 */
class Cart {

	/**
	 * Formatted order lines.
	 *
	 * @var array $order_lines
	 */
	public $order_lines = array();

	/**
	 * Shop country.
	 *
	 * @var string
	 */
	public $shop_country;
	/**
	 * Cart customer.
	 *
	 * @var array
	 */
	public $customer;

	/**
	 * Cart constructor.
	 *
	 * @param bool|string $shop_country Shop country.
	 */
	public function __construct( $shop_country = null ) {
		if ( ! $shop_country ) {
			$base_location = wc_get_base_location();
			$shop_country  = $base_location['country'];
		}

		$this->shop_country = $shop_country;
	}

	/**
	 * Processes cart data
	 */
	public function process_data() {
		// Reset order lines.
		$this->order_lines = array();

		$this->process_cart();
		$this->process_shipping();
		$this->process_coupons();
		$this->process_fees();
		$this->process_customer();
	}

	/**
	 * Gets formatted order lines from WooCommerce cart.
	 *
	 * @return array
	 */
	public function get_order_lines() {
		return array_values( $this->order_lines );
	}

	/**
	 * Gets order amount for Ledyer API.
	 * Order amount saved in cart total
	 *
	 * @return int
	 */
	public function get_order_amount() {
		$order_amount = round( WC()->cart->total * 100 );

		if ( $order_amount < 0 ) {
			return 0;
		}

		return $order_amount;
	}

	/**
	 * Gets order total amount eligible for tax for Ledyer API
	 * Note that this excludes multipurpose vouchers/giftcards.
	 * Use get_order_amount for regular total amount
	 *
	 * @return int
	 */
	public function get_order_taxable_amount() {
		$total_amount = 0;
		foreach ( $this->order_lines as $order_line ) {
			$line_total            = $order_line['totalAmount'];
			$multi_purpose_voucher = 'giftCard' === $order_line['type'] && 0 === $order_line['vat'] && $line_total < 0;
			if ( ! $multi_purpose_voucher ) {
				$total_amount += $line_total;
			}
		}

		return round( $total_amount );
	}

	/**
	 * Gets order tax amount for Ledyer API.
	 *
	 * @param array $order_lines Order lines from cart.
	 *
	 * @return int
	 */
	public function get_order_tax_amount( $order_lines ) {
		$total_tax_amount = 0;
		foreach ( $order_lines as $order_line ) {
			$total_tax_amount += intval( $order_line['totalVatAmount'] );
		}
		return round( $total_tax_amount );
	}
	/**
	 * Gets order amount without total tax amount for Ledyer API.
	 *
	 * @param array $order_lines Order lines from cart.
	 *
	 * @return int
	 */
	public function get_order_amount_ex_tax( $order_lines ) {
		$total_amount     = $this->get_order_taxable_amount();
		$total_tax_amount = $this->get_order_tax_amount( $order_lines );

		return round( $total_amount - $total_tax_amount );
	}

	/**
	 * Process WooCommerce cart to Ledyer Payments order lines.
	 */
	public function process_cart() {
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( $cart_item['quantity'] ) {
				if ( $cart_item['variation_id'] ) {
					$product = wc_get_product( $cart_item['variation_id'] );
				} else {
					$product = wc_get_product( $cart_item['product_id'] );
				}

				$this->total_amount        = self::format_number( $cart_item['line_total'] );
				$this->subtotal_amount     = self::format_number( $cart_item['line_subtotal'] );
				$this->total_tax_amount    = self::format_number( array_sum( $cart_item['line_tax_data']['total'] ) );
				$this->subtotal_tax_amount = self::format_number( array_sum( $cart_item['line_tax_data']['subtotal'] ) );
				$this->quantity            = $cart_item['quantity'];

				$ledyer_item = array(
					'reference'      => $this->get_item_reference( $product ),
					'description'    => $this->get_item_name( $cart_item ),
					'quantity'       => $this->get_item_quantity( $cart_item ),
					'vat'            => $this->get_item_tax_rate( $cart_item, $product ),
					'totalAmount'    => $this->get_item_total_amount( $cart_item, $product ),
					'totalVatAmount' => $this->get_item_tax_amount( $cart_item, $product ),
				);

				// Product type.
				if ( $product->is_downloadable() || $product->is_virtual() ) {
					$ledyer_item['type'] = 'digital';
				} else {
					$ledyer_item['type'] = 'physical';
				}

				$this->order_lines[] = apply_filters( 'lco_wc_cart_line_item', $ledyer_item, $cart_item );
			}
		}
	}

	/**
	 * Process WooCommerce cart to Ledyer Payments customer data.
	 */
	public function process_customer() {
		if ( WC()->cart->get_customer() ) {
			$customer = WC()->cart->get_customer();

			$this->customer = array(
				'country'  => $customer->get_billing_country(),
				'currency' => get_woocommerce_currency(),
			);
		}
	}

	/**
	 * Get processed customer for Ledyer Payments
	 *
	 * @return array
	 */
	public function get_customer() {
		return $this->customer;
	}

	/**
	 * Process WooCommerce shipping to Ledyer Payments order lines.
	 */
	public function process_shipping() {
		if ( WC()->shipping->get_packages() && ! empty( WC()->session->get( 'chosen_shipping_methods' ) ) ) {
			$shipping            = array(
				'type'           => 'shippingFee',
				'reference'      => $this->get_shipping_reference(),
				'description'    => $this->get_shipping_name(),
				'quantity'       => 1,
				'vat'            => $this->get_shipping_tax_rate(),
				'totalAmount'    => $this->get_shipping_amount(),
				'totalVatAmount' => $this->get_shipping_tax_amount(),
			);
			$this->order_lines[] = $shipping;
		}
	}

	/**
	 * Process smart coupons.
	 */
	public function process_coupons() {
		if ( ! empty( WC()->cart->get_coupons() ) ) {
			foreach ( WC()->cart->get_coupons() as $coupon_key => $coupon ) {
				$coupon_description     = 'Gift card';
				$coupon_reference       = substr( (string) $coupon_key, 0, 64 );
				$coupon_amount          = 0;
				$coupon_discount_amount = 0;
				$coupon_tax_amount      = 0;

				// Smart coupons are processed as real line items, cart and product discounts sent for reference only.
				if ( 'smart_coupon' === $coupon->get_discount_type() ) {
					$apply_before_tax = get_option( 'woocommerce_smart_coupon_apply_before_tax', 'no' );
					// If Smart coupon is applied before tax calculation,
					// the sum is discounted from order lines so we send it as 0 for reference.
					if ( wc_tax_enabled() && 'yes' === $apply_before_tax ) {
						$coupon_amount      = 0;
						$coupon_description = __( 'Gift card', 'ledyer-checkout-for-woocommerce' ) . ' (amount: ' . WC()->cart->get_coupon_discount_amount( $coupon_key ) . ')';
					} else {
						$coupon_discount_amount = WC()->cart->get_coupon_discount_amount( $coupon_key ) * 100;
						$coupon_amount          = - WC()->cart->get_coupon_discount_amount( $coupon_key ) * 100;
						$coupon_description     = __( 'Discount', 'ledyer-checkout-for-woocommerce' );
					}
					$coupon_tax_amount = - WC()->cart->get_coupon_discount_tax_amount( $coupon_key ) * 100;
				}

				// Add separate discount line item, but only if it's a smart coupon or country is US.
				if ( 'smart_coupon' === $coupon->get_discount_type() ) {
					$discount            = array(
						'type'           => 'discount',
						'reference'      => $coupon_reference,
						'description'    => $coupon_description,
						'quantity'       => 1,
						'vat'            => 2500,
						'totalAmount'    => $coupon_amount,
						'totalVatAmount' => - self::format_number( $coupon_tax_amount ),
					);
					$this->order_lines[] = $discount;
				}
			}
		}

		/**
		 * WooCommerce Gift Cards compatibility.
		 */
		if ( class_exists( 'WC_GC_Gift_Cards' ) ) {
			/**
			 * Use the applied giftcards.
			 *
			 * @var WC_GC_Gift_Card_Data $wc_gc_gift_card_data
			 */
			$totals_before_giftcard = round( WC()->cart->get_subtotal() + WC()->cart->get_shipping_total() + WC()->cart->get_subtotal_tax() + WC()->cart->get_shipping_tax(), wc_get_price_decimals() );
			$giftcards              = WC_GC()->giftcards->get();
			$giftcards_used         = WC_GC()->giftcards->cover_balance( $totals_before_giftcard, WC_GC()->giftcards->get_applied_giftcards_from_session() );

			foreach ( WC_GC()->giftcards->get_applied_giftcards_from_session() as $wc_gc_gift_card_data ) {
				$gift_card_code   = $wc_gc_gift_card_data->get_data()['code'];
				$gift_card_amount = - $giftcards_used['total_amount'] * 100;

				$gift_card = array(
					'type'           => 'giftCard',
					'reference'      => $gift_card_code,
					'description'    => __( 'Gift card', 'ledyer-checkout-for-woocommerce' ),
					'quantity'       => 1,
					'vat'            => 0,
					'totalAmount'    => $gift_card_amount,
					'totalVatAmount' => 0,
				);

				$this->order_lines[] = $gift_card;

			}
		}

		// YITH Gift Cards.
		if ( ! empty( WC()->cart->applied_gift_cards ) ) {
			foreach ( WC()->cart->applied_gift_cards as $coupon_key => $code ) {
				$coupon_reference  = '';
				$coupon_amount     = isset( WC()->cart->applied_gift_cards_amounts[ $code ] ) ? - WC()->cart->applied_gift_cards_amounts[ $code ] * 100 : 0;
				$coupon_tax_amount = '';
				$label             = apply_filters( 'yith_ywgc_cart_totals_gift_card_label', esc_html( __( 'Gift card:', 'yith-woocommerce-gift-cards' ) . ' ' . $code ), $code );
				$giftcard_sku      = apply_filters( 'lco_yith_gift_card_sku', esc_html( __( 'giftcard', 'ledyer-checkout-for-woocommerce' ) ), $code );

				$gift_card           = array(
					'type'           => 'giftCard',
					'reference'      => $giftcard_sku,
					'description'    => $label,
					'quantity'       => 1,
					'vat'            => 0,
					'totalAmount'    => $coupon_amount,
					'totalVatAmount' => 0,
				);
				$this->order_lines[] = $gift_card;
			}
		}

		// PW Gift Cards.
		if ( ! empty( WC()->session->get( 'pw-gift-card-data' ) ) ) {
			$pw_gift_cards = WC()->session->get( 'pw-gift-card-data' );
			foreach ( $pw_gift_cards['gift_cards'] as $code => $value ) {
				$coupon_amount       = $value * 100 * - 1;
				$label               = esc_html__( 'Gift card', 'pw-woocommerce-gift-cards' ) . ' ' . $code;
				$gift_card_sku       = apply_filters( 'lco_pw_gift_card_sku', esc_html__( 'giftcard', 'ledyer-checkout-for-woocommerce' ), $code );
				$gift_card           = array(
					'type'           => 'giftCard',
					'reference'      => $gift_card_sku,
					'description'    => $label,
					'quantity'       => 1,
					'vat'            => 0,
					'totalAmount'    => $coupon_amount,
					'totalVatAmount' => 0,
				);
				$this->order_lines[] = $gift_card;
			}
		}
	}

	/**
	 * Process cart fees.
	 */
	public function process_fees() {
		if ( ! empty( WC()->cart->get_fees() ) ) {
			foreach ( WC()->cart->get_fees() as $fee_key => $fee ) {
				$fee_amount = number_format( $fee->amount + $fee->tax, wc_get_price_decimals(), '.', '' ) * 100;

				$_tax      = new WC_Tax();
				$tmp_rates = $_tax->get_rates( $fee->tax_class );
				$vat       = array_shift( $tmp_rates );

				if ( isset( $vat['rate'] ) ) {
					$fee_tax_rate = round( $vat['rate'] * 100 );
				} else {
					$fee_tax_rate = 0;
				}

				$fee_total_exluding_tax = $fee_amount / ( 1 + ( $fee_tax_rate / 10000 ) );
				$fee_tax_amount         = $fee_amount - $fee_total_exluding_tax;

				// Add separate discount line item, but only if it's a smart coupon or country is US.
				$fee_item            = array(
					'type'           => 'surcharge',
					'reference'      => substr( $fee->id, 0, 64 ),
					'description'    => $fee->name,
					'quantity'       => 1,
					'vat'            => $fee_tax_rate,
					'totalAmount'    => $fee_amount,
					'totalVatAmount' => $fee_tax_amount,
				);
				$this->order_lines[] = $fee_item;
			}
		}
	}

	// Helpers.

	/**
	 * Get cart item name.
	 *
	 * @param array $cart_item Cart item.
	 *
	 * @return string $item_name Cart item name.
	 * @since  1.0.0
	 * @access public
	 */
	public function get_item_name( $cart_item ) {
		$item_name = substr( $cart_item['data']->get_name(), 0, 254 );

		return wp_strip_all_tags( $item_name );
	}

	/**
	 * Calculate item tax percentage.
	 *
	 * @param array      $cart_item Cart item.
	 * @param WC_Product $product WooCommerce product.
	 *
	 * @return integer $item_tax_amount Item tax amount.
	 * @since  1.0.0
	 * @access public
	 */
	public function get_item_tax_amount( $cart_item, $product ) {
		$item_total_amount       = $this->get_item_total_amount( $cart_item, $product );
		$item_total_exluding_tax = $item_total_amount / ( 1 + ( $this->get_item_tax_rate( $cart_item, $product ) / 10000 ) );
		$item_tax_amount         = $item_total_amount - $item_total_exluding_tax;

		return round( $item_tax_amount );
	}

	/**
	 * Calculate item tax percentage.
	 *
	 * @param array  $cart_item Cart item.
	 * @param object $product Product object.
	 *
	 * @return integer $item_tax_rate Item tax percentage formatted for Ledyer.
	 * @since  1.0.0
	 * @access public
	 */
	public function get_item_tax_rate( $cart_item, $product ) {
		if ( $product->is_taxable() && $cart_item['line_subtotal_tax'] > 0 ) {
			// Calculate tax rate.
			$_tax      = new WC_Tax();
			$tmp_rates = $_tax->get_rates( $product->get_tax_class() );
			$vat       = array_shift( $tmp_rates );
			if ( isset( $vat['rate'] ) ) {
				$item_tax_rate = round( $vat['rate'] * 100 );
			} else {
				$item_tax_rate = 0;
			}
		} else {
			$item_tax_rate = 0;
		}

		return round( $item_tax_rate );
	}


	/**
	 * Get cart item quantity.
	 *
	 * @param array $cart_item Cart item.
	 *
	 * @return integer $item_quantity Cart item quantity.
	 * @since  1.0.0
	 * @access public
	 */
	public function get_item_quantity( $cart_item ) {
		return round( $cart_item['quantity'] );
	}

	/**
	 * Get cart item reference.
	 *
	 * Returns SKU or product ID.
	 *
	 * @param object $product Product object.
	 *
	 * @return string $item_reference Cart item reference.
	 * @since  1.0.0
	 * @access public
	 */
	public function get_item_reference( $product ) {
		if ( $product->get_sku() ) {
			$item_reference = $product->get_sku();
		} else {
			$item_reference = $product->get_id();
		}

		return substr( (string) $item_reference, 0, 64 );
	}

	/**
	 * Get cart item discount rate.
	 *
	 * @param array $cart_item Cart item.
	 *
	 * @return integer $item_discount_rate Cart item discount rate.
	 * @since  1.0.0
	 * @access public
	 */
	public function get_item_discount_rate( $cart_item ) {
		$item_discount_rate = ( 1 - ( $cart_item['line_total'] / $cart_item['line_subtotal'] ) ) * 100 * 100;

		return round( $item_discount_rate );
	}

	/**
	 * Get cart item total amount.
	 *
	 * @param array      $cart_item Cart item.
	 * @param WC_Product $product WooCommerce product.
	 *
	 * @return integer $item_total_amount Cart item total amount.
	 * @since  1.0.0
	 * @access public
	 */
	public function get_item_total_amount( $cart_item, $product ) {

		$item_total_amount = ( $this->total_amount + $this->total_tax_amount );

		return $item_total_amount;
	}

	/**
	 * Get shipping method name.
	 *
	 * @return string $shipping_name Name for selected shipping method.
	 * @since  1.0.0
	 * @access public
	 */
	public function get_shipping_name() {
		$shipping_packages = WC()->shipping->get_packages();
		foreach ( $shipping_packages as $i => $package ) {
			$chosen_method = isset( WC()->session->chosen_shipping_methods[ $i ] ) ? WC()->session->chosen_shipping_methods[ $i ] : '';
			if ( '' !== $chosen_method ) {
				$package_rates = $package['rates'];
				foreach ( $package_rates as $rate_key => $rate_value ) {
					if ( $rate_key === $chosen_method ) {
						$shipping_name = $rate_value->get_label();
					}
				}
			}
		}
		if ( ! isset( $shipping_name ) ) {
			$shipping_name = __( 'Shipping', 'ledyer-checkout-for-woocommerce' );
		}

		return (string) $shipping_name;
	}

	/**
	 * Get shipping reference.
	 *
	 * @return string $shipping_reference Reference for selected shipping method.
	 * @since  1.0.0
	 * @access public
	 */
	public function get_shipping_reference() {
		$shipping_packages = WC()->shipping->get_packages();
		foreach ( $shipping_packages as $i => $package ) {
			$chosen_method = isset( WC()->session->chosen_shipping_methods[ $i ] ) ? WC()->session->chosen_shipping_methods[ $i ] : '';
			if ( '' !== $chosen_method ) {
				$package_rates = $package['rates'];
				foreach ( $package_rates as $rate_key => $rate_value ) {
					if ( $rate_key === $chosen_method ) {
						$shipping_reference = $rate_value->id;
					}
				}
			}
		}
		if ( ! isset( $shipping_reference ) ) {
			$shipping_reference = __( 'Shipping', 'ledyer-checkout-for-woocommerce' );
		}

		return substr( (string) $shipping_reference, 0, 64 );
	}

	/**
	 * Get shipping method amount.
	 *
	 * @return integer $shipping_amount Amount for selected shipping method.
	 * @since  1.0.0
	 * @access public
	 */
	public function get_shipping_amount() {
		$shipping_amount = WC()->cart->shipping_total + WC()->cart->shipping_tax_total;

		return self::format_number( $shipping_amount );
	}

	/**
	 * Get shipping method tax rate.
	 *
	 * @return integer $shipping_tax_rate Tax rate for selected shipping method.
	 * @since  1.0.0
	 * @access public
	 */
	public function get_shipping_tax_rate() {

		if ( WC()->cart->shipping_tax_total > 0 ) {
			$shipping_rates = WC_Tax::get_shipping_tax_rates();
			$vat            = array_shift( $shipping_rates );
			if ( isset( $vat['rate'] ) ) {
				$shipping_tax_rate = round( $vat['rate'] * 100 );
			} else {
				$shipping_tax_rate = 0;
			}
		} else {
			$shipping_tax_rate = 0;
		}

		return intval( round( $shipping_tax_rate ) );
	}

	/**
	 * Get shipping method tax amount.
	 *
	 * @return integer $shipping_tax_amount Tax amount for selected shipping method.
	 * @since  1.0.0
	 * @access public
	 */
	public function get_shipping_tax_amount() {
		$shiping_total_amount        = $this->get_shipping_amount();
		$shipping_total_exluding_tax = $shiping_total_amount / ( 1 + ( $this->get_shipping_tax_rate() / 10000 ) );
		$shipping_tax_amount         = $shiping_total_amount - $shipping_total_exluding_tax;

		return intval( round( $shipping_tax_amount ) );
	}

	/**
	 * Format the value as needed for the Ledyer plugin.
	 *
	 * @param int|float $value The unformated value.
	 *
	 * @return int
	 */
	public static function format_number( $value ) {
		return intval( round( round( $value, wc_get_price_decimals() ) * 100 ) );
	}
}
