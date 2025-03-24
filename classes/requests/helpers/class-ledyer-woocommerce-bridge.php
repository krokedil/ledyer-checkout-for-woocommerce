<?php
/**
 * Data processor fro Ledyer Order Requests.
 *
 * @package Ledyer\Requests\Helpers
 */
namespace Ledyer\Requests\Helpers;

defined( 'ABSPATH' ) || exit();

/**
 * Class Woocommerce_Bridge
 *
 * Class that formats WooCommerce data for Ledyer API.
 *
 * @package Ledyer\Requests\Helpers
 */
class Woocommerce_Bridge {

	/**
	 * Formatted Ledyer settings.
	 *
	 * @var array $ledyer_settings
	 */
	private static $ledyer_settings;

	/**
	 * Get formatted cart data for Ledyer API Requests
	 *
	 * @return array[]
	 */
	public static function get_cart_data() {

		$is_test = ledyer()->get_setting( 'testmode' );
		$cart    = new Cart();
		$cart->process_data();

		$data = array(
			'locale'                  => self::get_bcp47_locale(),
			'metadata'                => null,
			'orderLines'              => $cart->get_order_lines(),
			'reference'               => null,
			'settings'                => self::get_order_settings( true ),
			'storeId'                 => ledyer()->get_setting( ( 'yes' === $is_test ? 'test_' : '' ) . 'store_id' ),
			'totalOrderAmount'        => $cart->get_order_amount(),
			'totalOrderAmountExclVat' => $cart->get_order_amount_ex_tax( $cart->get_order_lines() ),
			'totalOrderVatAmount'     => $cart->get_order_tax_amount( $cart->get_order_lines() ),
		);

		$data = array_merge( $cart->get_customer(), $data );

		return apply_filters( 'lco_' . __FUNCTION__, $data );
	}

	/**
	 * Get formatted cart data for Ledyer API Requests
	 *
	 * @param \WC_Order $order WooCommerce order object.
	 *
	 * @return array[]
	 */
	public static function get_order_data( $order ) {

		$is_test      = ledyer()->get_setting( 'testmode' );
		$order_helper = new Order();
		$order_helper->process_data( $order );

		$merchant_urls = ledyer()->merchant_urls->get_urls( $order->get_id() );

		$data = array(
			'country'                 => $order->get_billing_country(),
			'currency'                => $order->get_currency(),
			'locale'                  => self::get_bcp47_locale(),
			'metadata'                => null,
			'orderLines'              => $order_helper->get_order_lines(),
			'reference'               => null,
			'settings'                => array(
				'security' => array(
					'level'                   => intval( ledyer()->get_setting( 'security_level' ) ),
					'requireClientValidation' => true,
				),
				'customer' => array(
					'showNameFields'             => 'yes' === ledyer()->get_setting( 'customer_show_name_fields' ),
					'allowShippingAddress'       => 'yes' === ledyer()->get_setting( 'allow_custom_shipping' ),
					'showShippingAddressContact' => 'yes' === ledyer()->get_setting( 'show_shipping_address_contact' ),
				),
				'urls'     => array(
					'terms'        => $merchant_urls['terms'],
					'privacy'      => $merchant_urls['privacy'],
					'confirmation' => $merchant_urls['confirmation'],
				),
			),
			'storeId'                 => ledyer()->get_setting( ( 'yes' === $is_test ? 'test_' : '' ) . 'store_id' ),
			'totalOrderAmount'        => $order_helper->get_order_amount(),
			'totalOrderAmountExclVat' => $order_helper->get_order_amount_ex_tax(),
			'totalOrderVatAmount'     => $order_helper->get_order_tax_amount(),
		);

		return apply_filters( 'lco_' . __FUNCTION__, $data );
	}

	/**
	 * Get formatted cart data for Ledyer API Requests -- update
	 *
	 * @return array[]
	 */
	public static function get_updated_cart_data() {
		$cart = new Cart();
		$cart->process_data();

		$data = array(
			'locale'                  => self::get_bcp47_locale(),
			'orderLines'              => $cart->get_order_lines(),
			'settings'                => self::get_order_settings( false ),
			'totalOrderAmount'        => $cart->get_order_amount(),
			'totalOrderAmountExclVat' => $cart->get_order_amount_ex_tax( $cart->get_order_lines() ),
			'totalOrderVatAmount'     => $cart->get_order_tax_amount( $cart->get_order_lines() ),
		);

		$customer_cart_data = $cart->get_customer();

		$data = array_merge(
			array(
				'country'  => $customer_cart_data['country'],
				'currency' => $customer_cart_data['currency'],
			),
			$data
		);

		return apply_filters( 'lco_' . __FUNCTION__, $data );
	}

	/**
	 * Get locale in BCP 47 format. usually get_locale() returns xx_XX format
	 * however in some causes (due to plugins) it might come as xx.
	 *
	 * @return string
	 */
	private static function get_bcp47_locale() {
		$locale = \get_locale();
		if ( 'en' === $locale ) {
			$locale = 'en-US';
		} elseif ( 'sv' === $locale ) {
			$locale = 'sv-SE';
		} elseif ( 'nb' === $locale || 'no' === $locale ) {
			$locale = 'no-NO';
		} elseif ( 'fi' === $locale ) {
			$locale = 'fi-FI';
		} elseif ( 'da' === $locale ) {
			$locale = 'da-DK';
		} elseif ( preg_match( '/^[a-z]{2}_[A-Z]{2}$/', $locale ) ) {
			$locale = str_replace( '_', '-', $locale );
		}

		\Ledyer\Logger::log( 'Using locale: ' . $locale );
		return $locale;
	}

	/**
	 * Creates formatted Ledyer settings for Ledyer API
	 *
	 * @param bool          $full Whether to get full settings or not.
	 * @param WC_Order|null $order Optional WooCommerce order object.
	 */
	private static function set_order_settings( $full = true, $order = null ) {
		$order_id      = $order ? $order->get_id() : null;
		$merchant_urls = ledyer()->merchant_urls->get_urls( $order_id );

		self::$ledyer_settings = array(
			'security' => array(
				'level'                   => intval( ledyer()->get_setting( 'security_level' ) ),
				'requireClientValidation' => true,
			),
		);

		if ( $full ) {
			self::$ledyer_settings = array(
				'security' => array(
					'level'                   => intval( ledyer()->get_setting( 'security_level' ) ),
					'requireClientValidation' => true,
				),
				'customer' => array(
					'showNameFields'             => 'yes' === ledyer()->get_setting( 'customer_show_name_fields' ),
					'allowShippingAddress'       => 'yes' === ledyer()->get_setting( 'allow_custom_shipping' ),
					'showShippingAddressContact' => 'yes' === ledyer()->get_setting( 'show_shipping_address_contact' ),
				),
				'urls'     => array(
					'terms'        => $merchant_urls['terms'],
					'privacy'      => $merchant_urls['privacy'],
					'confirmation' => '',
					'validate'     => null,
				),
			);
		}
	}

	/**
	 * Get formatted Ledyer settings for Ledyer API
	 *
	 * @param bool $full Whether to get full settings or not.
	 * @return array
	 */
	public static function get_order_settings( $full = true ) {
		self::set_order_settings( $full );
		return self::$ledyer_settings;
	}
}
