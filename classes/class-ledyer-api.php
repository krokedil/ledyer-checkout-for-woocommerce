<?php
/**
 * API Class file.
 *
 * @package Ledyer
 */

namespace Ledyer;

\defined( 'ABSPATH' ) || die();

use Ledyer\Requests\Order\Management\Update_Order_Reference;
use Ledyer\Requests\Order\Session\Create_Order;
use Ledyer\Requests\Order\Session\Update_Order;
use Ledyer\Requests\Order\Session\Get_Order;

/**
 * API class.
 *
 * Class that has functions for the ledyer communication.
 */
class API {
	/**
	 * Gets an order session from Ledyer.
	 *
	 * @param string $order_id The order ID to get the session for.
	 * @return mixed|\WP_Error The order session data or WP_Error on failure.
	 */
	public function get_order_session( $order_id ) {
		return ( new Get_Order( array( 'orderId' => $order_id ) ) )->request();
	}
	/**
	 * Creates an order session in Ledyer.
	 *
	 * @param array $data The order session data.
	 *
	 * @return mixed|\WP_Error The created order session data or WP_Error on failure.
	 */
	public function create_order_session( $data ) {
		return ( new Create_Order( compact( 'data' ) ) )->request();
	}
	/**
	 * Updates an order session in Ledyer.
	 *
	 * @param string $order_id The order ID to update the session for.
	 * @param array  $data The order session data.
	 * @return mixed|\WP_Error The updated order session data or WP_Error on failure.
	 */
	public function update_order_session( $order_id, $data ) {
		return ( new Update_Order(
			array(
				'orderId' => $order_id,
				'data'    => $data,
			)
		) )->request();
	}
	/**
	 * Gets an order from Ledyer.
	 *
	 * @param string $order_id The order ID to get.
	 * @return mixed|\WP_Error The order data or WP_Error on failure.
	 */
	public function get_order( $order_id ) {
		return ( new \Ledyer\Requests\Order\Management\Get_Order( array( 'orderId' => $order_id ) ) )->request();
	}
	/**
	 * Updates an order reference in Ledyer.
	 *
	 * @param string $order_id The order ID to update the reference for.
	 * @param array  $data The order reference data.
	 * @return mixed|\WP_Error The updated order reference data or WP_Error on failure.
	 */
	public function update_order_reference( $order_id, $data ) {
		return ( new Update_Order_Reference(
			array(
				'orderId' => $order_id,
				'data'    => $data,
			)
		) )->request();
	}
	/**
	 * Acknowledges an order in Ledyer.
	 *
	 * @param string $order_id The order ID to acknowledge.
	 * @return mixed|\WP_Error The acknowledgement response or WP_Error on failure.
	 */
	public function acknowledge_order( $order_id ) {
		return ( new \Ledyer\Requests\Order\Management\Acknowledge_Order(
			array(
				'orderId' => $order_id,
				'data'    => array(),
			)
		) )->request();
	}
	/**
	 * Get payment status for an order.
	 *
	 * @param string $order_id The order ID to get payment status for.
	 *
	 * @return mixed|\WP_Error
	 */
	public function get_payment_status( $order_id ) {
		return ( new \Ledyer\Requests\Order\Management\Get_Payment_Status( array( 'orderId' => $order_id ) ) )->request();
	}
}
