<?php
/**
 * Confirmation Class file.
 *
 * @package Ledyer
 */

namespace Ledyer;

\defined( 'ABSPATH' ) || die();

/**
 * Confirmation class.
 *
 * @since 1.0.0
 *
 * Class that handles confirmation of order and redirect to Thank you page.
 */
class Confirmation {

	use Singleton;

	/**
	 * Confirmation constructor.
	 */
	public function actions() {
		add_action( 'init', array( $this, 'confirm_order' ), 10, 2 );
	}

	/**
	 * Confirm the order in Woo
	 */
	public function confirm_order() {
		$ledyer_confirm  = filter_input( INPUT_GET, 'lco_confirm', FILTER_SANITIZE_URL );
		$order_key       = filter_input( INPUT_GET, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		$ledyer_order_id = filter_input( INPUT_GET, 'ledyer_id', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

		if ( empty( $ledyer_confirm ) || empty( $order_key ) ) {
			return;
		}
		$order_id = wc_get_order_id_by_order_key( $order_key );

		if ( empty( $order_id ) ) {
			\Ledyer\Logger::log( 'Could not get the WooCommerce order id from order key ' . $order_key );
			return;
		}

		$order = wc_get_order( $order_id );
		if ( empty( $order ) ) {
			\Ledyer\Logger::log( 'Could not get the WooCommerce order with the id ' . $order_id );
			return;
		}

		// Check if is HPP.
		if ( ! empty( $ledyer_order_id ) ) {
			$ledyer_order = ledyer()->api->get_order( $ledyer_order_id );
			if ( is_wp_error( $ledyer_order ) ) {
				\Ledyer\Logger::log( 'Could not get the order from Ledyer with the id ' . $ledyer_order_id );
				return;
			}

			do_action( 'ledyer_process_payment', $order_id, $ledyer_order );

			$order->update_meta_data( '_ledyer_date_paid', gmdate( 'Y-m-d H:i:s' ) );
			$order->save();

			$response = ledyer()->api->acknowledge_order( $ledyer_order_id );
			if ( is_wp_error( $response ) ) {
				\Ledyer\Logger::log( 'Could not acknowledge the order from Ledyer with the id ' . $ledyer_order_id );
				return;
			}

			$ledyer_update_order = ledyer()->api->update_order_reference( $ledyer_order_id, array( 'reference' => $order->get_order_number() ) );
			if ( is_wp_error( $ledyer_update_order ) ) {
				\Ledyer\Logger::log( 'Could not set the merchant reference for the WooCommerce order number ' . $order->get_order_number() );
			}
		}

		Logger::log( $order_id . ': Confirm the Ledyer order from the confirmation page.' );
		wc_ledyer_confirm_ledyer_order( $order_id );
		lco_unset_sessions();
	}
}
