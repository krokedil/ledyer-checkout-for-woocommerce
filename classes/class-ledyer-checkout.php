<?php
/**
 * Checkout
 *
 * Updates Ledyer order after WC calculate totals.
 *
 * @package Ledyer
 * @since   1.0.0
 */

namespace Ledyer;

\defined( 'ABSPATH' ) || die();

/**
 * Checkout class.
 */
class Checkout {
	use Singleton;

	/**
	 * Class constructor.
	 */
	public function actions() {
		add_action( 'woocommerce_after_calculate_totals', array( $this, 'update_ledyer_order' ), 9999 );
	}
	/**
	 * Update the Ledyer order after calculations from WooCommerce has run.
	 *
	 * @return void
	 */
	public function update_ledyer_order() {
		if ( ! is_checkout() ) {
			return;
		}

		if ( 'lco' !== WC()->session->get( 'chosen_payment_method' ) ) {
			return;
		}
		$ledyer_order_id = WC()->session->get( 'lco_wc_order_id' );

		if ( empty( $ledyer_order_id ) ) {
			return;
		}

		$ledyer_order = ledyer()->api->get_order_session( $ledyer_order_id );

		if ( $ledyer_order ) {
			$data         = \Ledyer\Requests\Helpers\Woocommerce_Bridge::get_updated_cart_data();
			$ledyer_order = ledyer()->api->update_order_session( $ledyer_order_id, $data );
		}
	}
}
