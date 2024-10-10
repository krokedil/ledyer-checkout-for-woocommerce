<?php
/**
 * Order lines processor.
 *
 * @package Ledyer\Requests\Helpers
 */

namespace Ledyer\Requests\Helpers;

use WC_Tax;

defined( 'ABSPATH' ) || exit();

/**
 * Order class.
 *
 * Class that formats WooCommerce order contents for Ledyer API.
 */
class Order {
	/**
	 * Formatted order lines.
	 *
	 * @var array[]
	 */
	private $order_lines;

	/**
	 * Order object.
	 *
	 * @var \WC_Order
	 */
	private $order;

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
	 * Order total amount.
	 *
	 * @var float
	 */
	public $total_order_amount;

	/**
	 * Order total VAT amount.
	 *
	 * @var float
	 */
	public $total_order_vat_amount;

	/**
	 * Process order data.
	 *
	 * @param \WC_Order $order WooCommerce order object.
	 *
	 * @return void
	 */
	public function process_data( $order ) {
		// Reset order lines.
		$this->order_lines = array();
		$this->order       = $order;

		$this->process_order_lines();
		$this->process_shipping();
		$this->process_fees();
	}

	/**
	 * Process order lines.
	 *
	 * @return void
	 */
	private function process_order_lines() {
		/**
		 * Loop order items and convert to Ledyer.
		 *
		 * @var \WC_Order_Item_Product $item
		 */
		foreach ( $this->order->get_items() as $item_id => $item ) {
			$product   = $item->get_product();
			$quantity  = $item->get_quantity();
			$total     = round( $item->get_total() * 100 );
			$total_tax = round( $item->get_total_tax() * 100 );

			// Add order line similar to cart data (without explicit rounding).
			$this->order_lines[] = array(
				'reference'      => $product->get_sku() ? $product->get_sku() : $product->get_id(),
				'description'    => $item->get_name(),
				'quantity'       => $quantity,
				'vat'            => self::get_order_line_tax_rate( $this->order, $item ),
				'totalAmount'    => $total + $total_tax,
				'totalVatAmount' => $total_tax,
				'type'           => $product->is_virtual() ? 'digital' : 'physical',
			);

			// Accumulate totals.
			$this->total_order_amount     += $total;
			$this->total_order_vat_amount += $total_tax;
		}
	}

	/**
	 * Process shipping.
	 *
	 * @return void
	 */
	private function process_shipping() {
		/**
		 * Loop order items and convert to Ledyer.
		 *
		 * @var \WC_Order_Item_Shipping $shipping_item
		 */
		foreach ( $this->order->get_shipping_methods() as $shipping_item_id => $shipping_item ) {
			$shipping_total     = round( $shipping_item->get_total() * 100 );
			$shipping_total_tax = round( $shipping_item->get_total_tax() * 100 );

			$this->order_lines[] = array(
				'type'           => 'shippingFee',
				'reference'      => $shipping_item->get_method_id() . ':' . $shipping_item->get_instance_id(),
				'description'    => $shipping_item->get_name(),
				'quantity'       => 1,
				'vat'            => self::get_order_line_tax_rate( $this->order, $shipping_item ),
				'totalAmount'    => $shipping_total + $shipping_total_tax,
				'totalVatAmount' => $shipping_total_tax,
			);

			// Add shipping totals.
			$this->total_order_amount     += $shipping_total;
			$this->total_order_vat_amount += $shipping_total_tax;
		}
	}

	/**
	 * Process the order fees.
	 *
	 * @return void
	 */
	private function process_fees() {
		/**
		 * Loop order fees and convert to Ledyer.
		 *
		 * @var \WC_Order_Item_Fee $fee_item
		 */
		foreach ( $this->order->get_fees() as $fee_item_id => $fee_item ) {
			$fee_total     = round( $fee_item->get_total() * 100 );
			$fee_total_tax = round( $fee_item->get_total_tax() * 100 );

			$this->order_lines[] = array(
				'type'           => 'fee',
				'reference'      => substr( $fee_item->get_id(), 0, 64 ),
				'description'    => $fee_item->get_name(),
				'quantity'       => 1,
				'vat'            => self::get_order_line_tax_rate( $this->order, $fee_item ),
				'totalAmount'    => $fee_total + $fee_total_tax,
				'totalVatAmount' => $fee_total_tax,
			);

			// Add fee totals.
			$this->total_order_amount     += $fee_total;
			$this->total_order_vat_amount += $fee_total_tax;
		}
	}

	/**
	 * Gets the order line tax rate.
	 *
	 * @param \WC_Order $order The WooCommerce order.
	 * @param mixed     $order_item If not false the WooCommerce order item WC_Order_Item.
	 * @return int
	 */
	public static function get_order_line_tax_rate( $order, $order_item = false ) {
		$tax_items = $order->get_items( 'tax' );
		foreach ( $tax_items as $tax_item ) {
			$rate_id = $tax_item->get_rate_id();
			foreach ( $order_item->get_taxes()['total'] as $key => $value ) {
				if ( '' !== $value ) {
					if ( $rate_id === $key ) {
						return round( \WC_Tax::_get_tax_rate( $rate_id )['tax_rate'] * 100 );
					}
				}
			}
		}
		// If we get here, there is no tax set for the order item. Return zero.
		return 0;
	}

	/**
	 * Get order lines.
	 *
	 * @return array[]
	 */
	public function get_order_lines() {
		return $this->order_lines;
	}

	/**
	 * Get order amount.
	 *
	 * @return float
	 */
	public function get_order_amount() {
		return $this->total_order_amount + $this->total_order_vat_amount;
	}

	/**
	 * Get order amount excluding tax.
	 *
	 * @return float
	 */
	public function get_order_amount_ex_tax() {
		return $this->total_order_amount;
	}

	/**
	 * Get order tax amount.
	 *
	 * @return float
	 */
	public function get_order_tax_amount() {
		return $this->total_order_vat_amount;
	}
}
