<?php
/**
 * Class that formats metchant URLs for Ledyer API.
 *
 * @package Ledyer
 */

namespace Ledyer;

\defined( 'ABSPATH' ) || die();

/**
 * Merchant_URLs class.
 *
 * Class that formats gets merchant URLs Ledyer API.
 */
class Merchant_URLs {

	/**
	 * Gets formatted merchant URLs array.
	 *
	 * @param string $order_id The WooCommerce order id.
	 *
	 * @return array
	 */
	public function get_urls( $order_id = null ) {
		$merchant_urls = array(
			'terms'        => $this->get_terms_url(),                   // Required.
			'privacy'      => $this->get_privacy_url(),
			'checkout'     => $this->get_checkout_url(),                // Required.
			'confirmation' => $this->get_confirmation_url( $order_id ), // Required.
		);

		return apply_filters( 'lco_wc_merchant_urls', $merchant_urls );
	}

	/**
	 * Terms URL.
	 *
	 * Required. URL of merchant terms and conditions. Should be different than checkout, confirmation and push URLs.
	 *
	 * @return string
	 */
	private function get_terms_url() {

		$terms_url = get_permalink( wc_get_page_id( 'terms' ) );

		if ( ! empty( ledyer()->get_setting( 'terms_url' ) ) ) {
			$terms_url = ledyer()->get_setting( 'terms_url' );
		}

		return apply_filters( 'lco_wc_terms_url', $terms_url );
	}

	/**
	 * Privacy Policy URL.
	 *
	 * Required. URL of merchant privacy policy. Should be different than checkout, confirmation and push URLs.
	 *
	 * @return string
	 */
	private function get_privacy_url() {
		$privacy_url = get_privacy_policy_url();

		if ( ! empty( ledyer()->get_setting( 'privacy_url' ) ) ) {
			$privacy_url = ledyer()->get_setting( 'privacy_url' );
		}

		return apply_filters( 'lco_wc_privacy_url', $privacy_url );
	}

	/**
	 * Checkout URL.
	 *
	 * Required. URL of merchant checkout page. Should be different than terms, confirmation and push URLs.
	 *
	 * @return string
	 */
	private function get_checkout_url() {
		$checkout_url = wc_get_checkout_url();

		return apply_filters( 'lco_wc_checkout_url', $checkout_url );
	}

	/**
	 * Confirmation URL.
	 *
	 * Required. URL of merchant confirmation page. Should be different than checkout and confirmation URLs.
	 *
	 * @param string $order_id The WooCommerce order id.
	 *
	 * @return string
	 */
	private function get_confirmation_url( $order_id ) {

		if ( empty( $order_id ) ) {
			$confirmation_url = add_query_arg(
				array(
					'lco_confirm' => 'yes',
				),
				wc_get_checkout_url()
			);
		} else {
			$order = wc_get_order( $order_id );

			$confirmation_url = add_query_arg(
				array(
					'lco_confirm' => 'yes',
				),
				$order->get_checkout_order_received_url()
			);
			// If HPP is enabled or is order pay, add needed parameters
			$settings = get_option( 'woocommerce_lco_settings', array() );
			if ( is_wc_endpoint_url( 'order-pay' ) || 'redirect' === ( $settings['checkout_flow'] ?? 'embedded' ) ) {
				$confirmation_url = add_query_arg(
					array(
						'lco_confirm' => 'yes',
						'session_id'  => '{session.id}',
						'ledyer_id'   => '{order.id}',
					),
					$order->get_checkout_order_received_url()
				);
			}
		}

		return apply_filters( 'lco_wc_confirmation_url', $confirmation_url );
	}

	/**
	 * Get session ID.
	 *
	 * @return string
	 */
	private function get_session_id() {
		foreach ( $_COOKIE as $key => $value ) {
			if ( strpos( $key, 'wp_woocommerce_session_' ) !== false ) {
				$session_id = explode( '||', $value );

				return $session_id[0];
			}
		}
	}
}
