<?php
/**
 * Class Callback.
 *
 * Handles callbacks (also known as "notifications") from Ledyer.
 */

namespace Ledyer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Callback.
 */
class Callback {

	/**
	 * REST API namespace and endpoint for Ledyer callbacks.
	 *
	 * This is used to register the REST API route for handling Ledyer notifications.
	 */
	public const REST_API_NAMESPACE = 'ledyer/v1';
	public const REST_API_ENDPOINT  = '/notifications';
	public const API_ENDPOINT       = 'wp-json/' . self::REST_API_NAMESPACE . self::REST_API_ENDPOINT;

	/**
	 * Singleton instance.
	 *
	 * @var Callback|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance of the Callback class.
	 *
	 * @return Callback
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Callback constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'schedule_process_notification', array( $this, 'process_notification' ), 10, 2 );
	}

	/**
	 * Register the REST API route(s).
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::REST_API_NAMESPACE,
			self::REST_API_ENDPOINT,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_notification' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Handles notification callbacks
	 *
	 * @param \WP_REST_Request $request The incoming request object.
	 * @return \WP_REST_Response
	 */
	public function handle_notification( \WP_REST_Request $request ) {
		$request_body = json_decode( $request->get_body() );
		$response     = new \WP_REST_Response();

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			Logger::log( "Request body isn't valid JSON string." );
			$response->set_status( 400 );
			return $response;
		}

		$ledyer_event_type = $request_body->{'eventType'};
		$ledyer_order_id   = $request_body->{'orderId'};

		if ( ! isset( $ledyer_event_type, $ledyer_order_id ) ) {
			Logger::log( "Request body doesn't hold orderId and eventType data." );
			$response->set_status( 400 );
			return $response;
		}

		$scheduleId = as_schedule_single_action( time() + 60, 'schedule_process_notification', array( $ledyer_order_id, $ledyer_event_type ) );

		if ( 0 === $scheduleId ) {
			Logger::log( "[CALLBACK]: Couldn't schedule process_notification for order: $ledyer_order_id and type: $ledyer_event_type" );
			$response->set_status( 500 );
			return $response;
		}

		Logger::log( "Enqueued notification: $ledyer_event_type, schedule-id: $scheduleId" );
		$response->set_status( 200 );
		return $response;
	}

	/**
	 * Processes the notification from Ledyer.
	 *
	 * This function is called by the scheduled action and processes the notification
	 * by updating the WooCommerce order based on the Ledyer event type and order ID.
	 *
	 * @param string $ledyer_order_id The Ledyer order ID.
	 * @param string $ledyer_event_type The type of event from Ledyer.
	 */
	public function process_notification( $ledyer_order_id, $ledyer_event_type ) {
		Logger::log( "process notification: $ledyer_order_id" );

		$orders = wc_get_orders(
			array(
				'meta_key'     => '_wc_ledyer_order_id',
				'meta_value'   => $ledyer_order_id,
				'meta_compare' => '=',
				'date_created' => '>' . ( time() - MONTH_IN_SECONDS ),
			),
		);

		$order_id = isset( $orders[0] ) ? $orders[0]->get_id() : null;
		$order    = wc_get_order( $order_id );

		Logger::log( "Order to process: $order_id" );

		if ( ! is_object( $order ) ) {
			Logger::log( "Could not find woo order with ledyer id: $ledyer_order_id" );
			return;
		}

		if ( 'com.ledyer.order.ready_for_capture' === $ledyer_event_type ) {
			$order->update_meta_data( '_ledyer_ready_for_capture', true );
			$order->save();
			return;
		}

		$ledyer_payment_status = ledyer()->api->get_payment_status( $ledyer_order_id );
		if ( is_wp_error( $ledyer_payment_status ) ) {
			\Ledyer\Logger::log( "Could not get ledyer payment status $ledyer_order_id" );
			return;
		}

		$ledyer_payment_method = $ledyer_payment_status['paymentMethod'];
		if ( ! empty( $ledyer_payment_status['paymentMethod'] ) ) {
			$ledyer_payment_provider = sanitize_text_field( $ledyer_payment_method['provider'] );
			$ledyer_payment_type     = sanitize_text_field( $ledyer_payment_method['type'] );

			$order->update_meta_data( 'ledyer_payment_type', $ledyer_payment_type );
			$order->update_meta_data( 'ledyer_payment_method', $ledyer_payment_provider );

			switch ( $ledyer_payment_type ) {
				case 'invoice':
					$method_title = __( 'Invoice', 'ledyer-checkout-for-woocommerce' );
					break;
				case 'advanceInvoice':
					$method_title = __( 'Advance Invoice', 'ledyer-checkout-for-woocommerce' );
					break;
				case 'card':
					$method_title = __( 'Card', 'ledyer-checkout-for-woocommerce' );
					break;
				case 'bankTransfer':
					$method_title = __( 'Direct Debit', 'ledyer-checkout-for-woocommerce' );
					break;
				case 'partPayment':
					$method_title = __( 'Part Payment', 'ledyer-checkout-for-woocommerce' );
					break;
			}

			$order->set_payment_method_title( sprintf( '%s (Ledyer)', $method_title ) );
			$order->save();
		}

		$ack_order = false;

		switch ( $ledyer_payment_status['status'] ) {
			case \LedyerPaymentStatus::ORDER_PENDING:
				if ( ! $order->has_status( array( 'on-hold', 'processing', 'completed' ) ) ) {
					$note = sprintf(
						__(
							'New session created in Ledyer with Payment ID %1$s. %2$s',
							'ledyer-checkout-for-woocommerce'
						),
						$ledyer_order_id,
						$ledyer_payment_status['note']
					);
					$order->update_status( 'on-hold', $note );
				}
				break;
			case \LedyerPaymentStatus::PAYMENT_PENDING:
				if ( ! $order->has_status( array( 'on-hold', 'processing', 'completed' ) ) ) {
					$note = sprintf(
						__(
							'New payment created in Ledyer with Payment ID %1$s. %2$s',
							'ledyer-checkout-for-woocommerce'
						),
						$ledyer_order_id,
						$ledyer_payment_status['note']
					);
					$order->update_status( 'on-hold', $note );
					$ack_order = true;
				}
				break;
			case \LedyerPaymentStatus::PAYMENT_CONFIRMED:
				if ( ! $order->has_status( array( 'processing', 'completed' ) ) ) {
					$note = sprintf(
						__(
							'New payment created in Ledyer with Payment ID %1$s. %2$s',
							'ledyer-checkout-for-woocommerce'
						),
						$ledyer_order_id,
						$ledyer_payment_status['note']
					);
					$order->add_order_note( $note );
					$order->payment_complete( $ledyer_order_id );
					$ack_order = true;
				}
				break;
			case \LedyerPaymentStatus::ORDER_CAPTURED:
				$new_status = 'completed';

				$settings = get_option( 'woocommerce_lco_settings' );

				// Check if we should keep card payments in processing status.
				if (
					isset( $settings['keep_cards_processing'] )
					&& 'yes' === $settings['keep_cards_processing']
					&& isset( $ledyer_payment_status['paymentMethod'] )
					&& isset( $ledyer_payment_status['paymentMethod']['type'] )
					&& 'card' === $ledyer_payment_status['paymentMethod']['type']
				) {
					$new_status = 'processing';
				}

				$new_status = apply_filters( 'lco_captured_update_status', $new_status, $ledyer_payment_status );
				$order->update_status( $new_status );
				break;
			case \LedyerPaymentStatus::ORDER_REFUNDED:
				$order->update_status( 'refunded' );
				break;
			case \LedyerPaymentStatus::ORDER_CANCELLED:
				$order->update_status( 'cancelled' );
				break;
		}

		if ( $ack_order ) {
			$response = ledyer()->api->acknowledge_order( $ledyer_order_id );
			if ( is_wp_error( $response ) ) {
				\Ledyer\Logger::log( "Couldn't acknowledge order $ledyer_order_id" );
				return;
			}
			$ledyer_update_order = ledyer()->api->update_order_reference( $ledyer_order_id, array( 'reference' => $order->get_order_number() ) );
			if ( is_wp_error( $ledyer_update_order ) ) {
				\Ledyer\Logger::log( "Couldn't set merchant reference {$order->get_order_number()}" );
				return;
			}
		}
	}
}
