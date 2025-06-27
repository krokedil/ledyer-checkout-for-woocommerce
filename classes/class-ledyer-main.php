<?php
/**
 * Class file for Ledyer_Checkout_For_WooCommerce class.
 *
 * @package Ledyer
 * @since 1.0.0
 */

namespace Ledyer;

use Ledyer\Admin\Meta_Box;
use WP_REST_Response;

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

	const SLUG     = 'ledyer-checkout-for-woocommerce';
	const VERSION  = '1.11.0';
	const SETTINGS = 'ledyer_checkout_for_woocommerce_settings';

	/**
	 * Register WordPress actions.
	 */
	public function actions() {
		\add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
		\add_action( 'admin_init', array( $this, 'on_admin_init' ) );

		add_action(
			'woocommerce_checkout_fields',
			array(
				$this,
				'modify_checkout_fields',
			),
			20,
			1,
		);
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
		Checkout::instance();
		Callback::instance();

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
		include_once LCO_WC_PLUGIN_PATH . '/classes/class-ledyer-credentials.php';
		include_once LCO_WC_PLUGIN_PATH . '/classes/class-ledyer-fields.php';
		include_once LCO_WC_PLUGIN_PATH . '/classes/class-ledyer-lco-gateway.php';
		include_once LCO_WC_PLUGIN_PATH . '/classes/class-ledyer-merchant-urls.php';
		include_once LCO_WC_PLUGIN_PATH . '/classes/class-ledyer-templates.php';
		include_once LCO_WC_PLUGIN_PATH . '/classes/class-ledyer-hpp.php';
		include_once LCO_WC_PLUGIN_PATH . '/classes/class-ledyer-callback.php';
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
	 * @param string $key The setting key to get.
	 * @return mixed The setting value.
	 */
	public function get_setting( $key ) {
		return self::$settings[ $key ] ?? null;
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
	public function on_admin_init(): void {
		new Meta_Box();
	}

	/**
	 * Modify checkout fields
	 *
	 * @access public
	 * @param array $checkout_fields The checkout fields.
	 * @return array $checkout_fields The modified checkout fields.
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
