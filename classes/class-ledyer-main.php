<?php
/**
 * Class file for Ledyer_Checkout_For_WooCommerce class.
 *
 * @package Ledyer
 * @since 1.0.0
 */

namespace Ledyer;

use Ledyer\Admin\Meta_Box;

\defined( 'ABSPATH' ) || die();

/**
 * Ledyer_Checkout_For_WooCommerce class.
 *
 * Init class
 */
class Ledyer_Checkout_For_WooCommerce {
	use Singleton;

	/**
	 * Notices (array)
	 *
	 * @var array
	 */
	public $notices = array();
	/**
	 * Reference to credentials class.
	 *
	 * @var Credentials $credentials
	 */
	public $credentials;
	/**
	 * Reference to merchant URLs class.
	 *
	 * @var Merchant_URLs $merchant_urls
	 */
	public $merchant_urls;
	/**
	 * Reference to Ledyer API
	 *
	 * @var API $api
	 */
	public $api;
	/**
	 * Reference to Ledyer Checkout
	 *
	 * @var Checkout $checkout
	 */
	public $checkout;

	const VERSION  = '1.8.1';
	const SLUG     = 'ledyer-checkout-for-woocommerce';
	const SETTINGS = 'ledyer_checkout_for_woocommerce_settings';

	public function actions() {
		\add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
		\add_action( 'admin_init', array( $this, 'on_admin_init' ) );

		add_action(
			'rest_api_init',
			function () {
				register_rest_route(
					'ledyer/v1',
					'/notifications/',
					array(
						'methods'             => 'POST',
						'callback'            => array( $this, 'handle_notification' ),
						'permission_callback' => '__return_true',
					)
				);
			}
		);

		add_action(
			'woocommerce_checkout_fields',
			array(
				$this,
				'modify_checkout_fields',
			),
			20,
			1,
		);

		add_action( 'schedule_process_notification', array( $this, 'process_notification' ), 10, 1 );
	}

	/**
	 * Handles notification callbacks
	 *
	 * @param $request
	 *
	 * @return \WP_REST_Response
	 */
	public function handle_notification( $request ) {
		$request_body = json_decode( $request->get_body() );
		$response     = new \WP_REST_Response();

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			Logger::log( 'Request body isn\'t valid JSON string.' );
			$response->set_status( 400 );
			return $response;
		}

		$ledyer_event_type = $request_body->{'eventType'};
		$ledyer_order_id   = $request_body->{'orderId'};

		if ( $ledyer_event_type === null || $ledyer_order_id === null ) {
			Logger::log( 'Request body doesn\'t hold orderId and eventType data.' );
			$response->set_status( 400 );
			return $response;
		}

		$scheduleId = as_schedule_single_action( time() + 120, 'schedule_process_notification', array( $ledyer_order_id ) );
		Logger::log( 'Enqueued notification: ' . $ledyer_event_type . ', schedule-id:' . $scheduleId );
		$response->set_status( 200 );
		return $response;
	}

	public function process_notification( $ledyer_order_id ) {
		Logger::log( 'process notification: ' . $ledyer_order_id );

		$orders = wc_get_orders(
			array(
				'meta_query'   => array(
					array(
						'key'     => '_wc_ledyer_order_id',
						'value'   => $ledyer_order_id,
						'compare' => '=',
					),
				),
				'date_created' => '>' . ( time() - MONTH_IN_SECONDS ),
			)
		);

		$order_id = isset( $orders[0] ) ? $orders[0]->get_id() : null;
		$order    = wc_get_order( $order_id );

		if ( ! is_object( $order ) ) {
			Logger::log( 'Could not find woo order with ledyer id: ' . $ledyer_order_id );
			return;
		}

		$ledyer_payment_status = ledyer()->api->get_payment_status( $ledyer_order_id );
		if ( is_wp_error( $ledyer_payment_status ) ) {
			\Ledyer\Logger::log( 'Could not get ledyer payment status ' . $ledyer_order_id );
			return;
		}

		$ackOrder = false;

		switch ( $ledyer_payment_status['status'] ) {
			case \LedyerPaymentStatus::paymentPending:
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
					$ackOrder = true;
				}
				break;
			case \LedyerPaymentStatus::paymentConfirmed:
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
					$ackOrder = true;
				}
				break;
			case \LedyerPaymentStatus::orderCaptured:
				$order->update_status( 'completed' );
				break;
			case \LedyerPaymentStatus::orderRefunded:
				$order->update_status( 'refunded' );
				break;
			case \LedyerPaymentStatus::orderCancelled:
				$order->update_status( 'cancelled' );
				break;
		}

		if ( $ackOrder ) {
			$response = ledyer()->api->acknowledge_order( $ledyer_order_id );
			if ( is_wp_error( $response ) ) {
				\Ledyer\Logger::log( 'Couldn\'t acknowledge order ' . $ledyer_order_id );
				return;
			}
			$ledyer_update_order = ledyer()->api->update_order_reference( $ledyer_order_id, array( 'reference' => $order->get_order_number() ) );
			if ( is_wp_error( $ledyer_update_order ) ) {
				\Ledyer\Logger::log( 'Couldn\'t set merchant reference ' . $order->get_order_number() );
				return;
			}
		}
	}


	/**
	 * Initialize the gateway. Called very early - in the context of the plugins_loaded action
	 *
	 * @since 1.0.0
	 */
	public function on_plugins_loaded() {
		if ( ! defined( 'WC_VERSION' ) ) {
			return;
		}

		$this->include_files();
		$this->set_settings();

		AJAX::init();
		Templates::instance();
		Confirmation::instance();
		Checkout::instance();
		// Set class variables.
		$this->credentials   = Credentials::instance();
		$this->merchant_urls = new Merchant_URLs();
		$this->api           = new API();

		load_plugin_textdomain( 'ledyer-checkout-for-woocommerce', false, LCO_WC_PLUGIN_NAME . '/languages' );

		add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );
		add_filter(
			'plugin_action_links_' . plugin_basename( LCO_WC_MAIN_FILE ),
			array(
				$this,
				'plugin_action_links',
			)
		);
	}

	/**
	 * Autoload classes
	 */
	public function include_files() {
		include_once LCO_WC_PLUGIN_PATH . '/includes/lco-functions.php';
		include_once LCO_WC_PLUGIN_PATH . '/includes/lco-types.php';
		include_once LCO_WC_PLUGIN_PATH . '/classes/class-ledyer-singleton.php';
		include_once LCO_WC_PLUGIN_PATH . '/classes/class-ledyer-logger.php';
		include_once LCO_WC_PLUGIN_PATH . '/classes/admin/class-ledyer-meta-box.php';
		include_once LCO_WC_PLUGIN_PATH . '/classes/requests/class-ledyer-request.php';
		include_once LCO_WC_PLUGIN_PATH . '/classes/requests/order/class-ledyer-request-order.php';
		include_once LCO_WC_PLUGIN_PATH . '/classes/requests/order/session/class-ledyer-create-order.php';
		include_once LCO_WC_PLUGIN_PATH . '/classes/requests/order/session/class-ledyer-get-order.php';
		include_once LCO_WC_PLUGIN_PATH . '/classes/requests/order/session/class-ledyer-update-order.php';
		include_once LCO_WC_PLUGIN_PATH . '/classes/requests/order/management/class-ledyer-acknowledge-order.php';
		include_once LCO_WC_PLUGIN_PATH . '/classes/requests/order/management/class-ledyer-get-order.php';
		include_once LCO_WC_PLUGIN_PATH . '/classes/requests/order/management/class-ledyer-get-payment-status.php';
		include_once LCO_WC_PLUGIN_PATH . '/classes/requests/order/management/class-ledyer-update-order-reference.php';
		include_once LCO_WC_PLUGIN_PATH . '/classes/requests/helpers/class-ledyer-cart.php';
		include_once LCO_WC_PLUGIN_PATH . '/classes/requests/helpers/class-ledyer-woocommerce-bridge.php';
		include_once LCO_WC_PLUGIN_PATH . '/classes/class-ledyer-ajax.php';
		include_once LCO_WC_PLUGIN_PATH . '/classes/class-ledyer-api.php';
		include_once LCO_WC_PLUGIN_PATH . '/classes/class-ledyer-checkout.php';
		include_once LCO_WC_PLUGIN_PATH . '/classes/class-ledyer-confirmation.php';
		include_once LCO_WC_PLUGIN_PATH . '/classes/class-ledyer-credentials.php';
		include_once LCO_WC_PLUGIN_PATH . '/classes/class-ledyer-fields.php';
		include_once LCO_WC_PLUGIN_PATH . '/classes/class-ledyer-lco-gateway.php';
		include_once LCO_WC_PLUGIN_PATH . '/classes/class-ledyer-merchant-urls.php';
		include_once LCO_WC_PLUGIN_PATH . '/classes/class-ledyer-templates.php';
		include_once LCO_WC_PLUGIN_PATH . '/classes/class-ledyer-hpp.php';
		include_once LCO_WC_PLUGIN_PATH . '/classes/requests/helpers/class-ledyer-order.php';
	}

	/**
	 * Get setting link.
	 *
	 * @since 1.0.0
	 *
	 * @return string Setting link
	 */
	public function get_setting_link() {
		$section_slug = 'lco';

		$params = array(
			'page'    => 'wc-settings',
			'tab'     => 'checkout',
			'section' => $section_slug,
		);

		$admin_url = add_query_arg( $params, 'admin.php' );
		return $admin_url;
	}

	/**
	 * Set LCO settings.
	 */
	public function set_settings() {
		self::$settings = get_option( 'woocommerce_lco_settings' );
	}

	/**
	 * Get LCO setting by name
	 *
	 * @param string $key
	 *
	 * @return mixed
	 */
	public function get_setting( $key ) {
		return self::$settings[ $key ];
	}

	/**
	 * Add the gateways to WooCommerce
	 *
	 * @param  array $methods Payment methods.
	 *
	 * @return array $methods Payment methods.
	 * @since  1.0.0
	 */
	public function add_gateways( $methods ) {
		$methods[] = 'Ledyer\LCO_Gateway';

		return $methods;
	}

	/**
	 * Adds plugin action links
	 *
	 * @param array $links Plugin action link before filtering.
	 *
	 * @return array Filtered links.
	 */
	public function plugin_action_links( $links ) {
		$setting_link = $this->get_setting_link();
		$plugin_links = array(
			'<a href="' . $setting_link . '">' . __( 'Settings', 'ledyer-checkout-for-woocommerce' ) . '</a>',
		);

		return array_merge( $plugin_links, $links );
	}

	/**
	 * Init meta box on admin hook.
	 */
	public function on_admin_init() {
		new Meta_Box();
	}

	/**
	 * Modify checkout fields
	 *
	 * @access public
	 *
	 * @param array $checkout_fields
	 *
	 * @return array $checkout_fields
	 */
	public function modify_checkout_fields( $checkout_fields ) {

		if ( ! is_checkout() ) {
			return $checkout_fields;
		}

		if ( ! isset( WC()->session ) || empty( WC()->session->get( 'chosen_payment_method' ) ) ) {
			return $checkout_fields;
		}

		if ( 'lco' === WC()->session->get( 'chosen_payment_method' ) ) {
			foreach ( $checkout_fields['billing'] as $key => $field ) {
				if ( false !== stripos( $key, 'first_name' ) || false !== stripos( $key, 'last_name' ) ) {
					$checkout_fields['billing'][ $key ]['required'] = false;
				}
			}

			foreach ( $checkout_fields['shipping'] as $key => $field ) {
				if ( false !== stripos( $key, 'first_name' ) || false !== stripos( $key, 'last_name' ) ) {
					$checkout_fields['shipping'][ $key ]['required'] = false;
				}
			}

			$checkout_fields['billing']['lco_shipping_data'] = array(
				'type'    => 'hidden',
				'class'   => array( 'lco_shipping_data' ),
				'default' => '',
			);
		}

		return $checkout_fields;
	}
}
