<?php
/**
 * Templates class for Ledyer checkout.
 *
 * @package  Ledyer
 */

namespace Ledyer;

\defined( 'ABSPATH' ) || die();

/**
 * Templates class.
 */
class Templates {

	/**
	 * The reference the *Singleton* instance of this class.
	 */
	use Singleton;

	/**
	 * Add filters.
	 *
	 * @return void
	 */
	public function filters() {
		// Override template if Ledyer Checkout page.

		$settings = get_option( 'woocommerce_lco_settings', array() );

		if ( 'redirect' !== ( $settings['checkout_flow'] ?? 'embedded' ) ) {
			\add_filter( 'wc_get_template', array( $this, 'override_template' ), 999, 2 );
		}
		// Unrequire WooCommerce Billing State field.
		\add_filter( 'woocommerce_billing_fields', array( $this, 'unrequire_wc_billing_state_field' ) );
		// Unrequire WooCommerce Shipping State field.
		\add_filter( 'woocommerce_shipping_fields', array( $this, 'unrequire_wc_shipping_state_field' ) );
		// Change admin shipping fields in edit order admin panel.
		\add_filter( 'woocommerce_admin_shipping_fields', array( $this, 'change_admin_shipping_fields' ), 10, 1 );
	}

	/**
	 * Add actions.
	 *
	 * @return void
	 */
	public function actions() {
		\add_action( 'wp_footer', array( $this, 'check_that_lco_template_has_loaded' ) );

		// Template hooks.
		\add_action( 'lco_wc_after_order_review', 'lco_wc_add_extra_checkout_fields', 10 );
		add_action( 'lco_wc_after_order_review', 'lco_wc_show_another_gateway_button', 20 );
		add_action( 'lco_wc_before_snippet', array( $this, 'add_wc_form' ), 10 );
	}

	/**
	 * Override checkout form template if Ledyer Checkout is the selected payment method.
	 *
	 * @param string $template Template.
	 * @param string $template_name Template name.
	 *
	 * @return string
	 */
	public function override_template( $template, $template_name ) {

		if ( is_checkout() ) {
			$confirm = filter_input( INPUT_GET, 'confirm', FILTER_SANITIZE_URL );
			// Don't display LCO template if we have a cart that doesn't needs payment.
			if ( apply_filters( 'lco_check_if_needs_payment', true ) && ! is_wc_endpoint_url( 'order-pay' ) ) {
				if ( ! WC()->cart->needs_payment() ) {
					return $template;
				}
			}

			// Ledyer Checkout.
			if ( 'checkout/form-checkout.php' === $template_name ) {
				$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();

				if ( locate_template( 'woocommerce/ledyer-checkout.php' ) ) {
					$ledyer_checkout_template = locate_template( 'woocommerce/ledyer-checkout.php' );
				} else {
					$ledyer_checkout_template = apply_filters( 'lco_locate_checkout_template', LCO_WC_PLUGIN_PATH . '/templates/ledyer-checkout.php', $template_name );
				}

				// Ledyer checkout page.
				if ( array_key_exists( 'lco', $available_gateways ) ) {
					// If chosen payment method exists.
					if ( 'lco' === WC()->session->get( 'chosen_payment_method' ) ) {
						if ( empty( $confirm ) ) {
							$template = $ledyer_checkout_template;
						}
					}

					// If chosen payment method does not exist and LCO is the first gateway.
					if ( null === WC()->session->get( 'chosen_payment_method' ) || '' === WC()->session->get( 'chosen_payment_method' ) ) {
						reset( $available_gateways );

						if ( 'lco' === key( $available_gateways ) ) {
							if ( empty( $confirm ) ) {
								$template = $ledyer_checkout_template;
							}
						}
					}

					// If another gateway is saved in session, but has since become unavailable.
					if ( WC()->session->get( 'chosen_payment_method' ) ) {
						if ( ! array_key_exists( WC()->session->get( 'chosen_payment_method' ), $available_gateways ) ) {
							reset( $available_gateways );

							if ( 'lco' === key( $available_gateways ) ) {
								if ( empty( $confirm ) ) {
									$template = $ledyer_checkout_template;
								}
							}
						}
					}
				}
			}
		}

		return $template;
	}

	/**
	 * Redirect customer to cart page if Ledyer Checkout is the selected (or first)
	 * payment method but the LCO template file hasn't been loaded.
	 */
	public function check_that_lco_template_has_loaded() {

		if ( is_checkout() && array_key_exists( 'lco', WC()->payment_gateways->get_available_payment_gateways() ) && 'lco' === lco_wc_get_selected_payment_method() && ( method_exists( WC()->cart, 'needs_payment' ) && WC()->cart->needs_payment() ) ) {

			// Get checkout object.
			$checkout = WC()->checkout();
			$enabled  = ledyer()->get_setting( 'enabled' );

			// Bail if this is LCO confirmation page, order received page, LCO page (lco-iframe enqueued), user is not logged and registration is disabled or if woocommerce_cart_has_errors has run.
			if ( is_lco_confirmation()
				|| is_wc_endpoint_url( 'order-received' )
				|| ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() )
				|| wp_script_is( 'lco-iframe', 'enqueued' )
				|| did_action( 'woocommerce_cart_has_errors' )
				 || isset( $_GET['change_payment_method'] ) // phpcs:ignore
				|| ! $enabled ) {
				return;
			}

			$url = add_query_arg(
				array(
					'lco-order' => 'error',
					'reason'    => base64_encode( __( 'Failed to load Ledyer Checkout template file.', 'ledyer-checkout-for-woocommerce' ) ),
						// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
				),
				wc_get_cart_url()
			);
			wp_safe_redirect( $url );
			exit;
		}
	}

	/**
	 * Adds the WC form and other fields to the checkout page.
	 *
	 * @return void
	 */
	public function add_wc_form() {
		?>
		<div aria-hidden="true" id="lco-wc-form" style="position:absolute; top:-99999px; left:-99999px;">
			<?php do_action( 'woocommerce_checkout_billing' ); ?>
			<?php do_action( 'woocommerce_checkout_shipping' ); ?>
			<div id="lco-nonce-wrapper">
				<?php
				if ( version_compare( WOOCOMMERCE_VERSION, '3.4', '<' ) ) {
					wp_nonce_field( 'woocommerce-process_checkout' );
				} else {
					wp_nonce_field( 'woocommerce-process_checkout', 'woocommerce-process-checkout-nonce' );
				}
				wc_get_template( 'checkout/terms.php' );
				?>
			</div>
			<input id="payment_method_lco" type="radio" class="input-radio" name="payment_method" value="lco"
					checked="checked"/></div>
		<?php
	}

	/**
	 * Unrequire WC billing state field.
	 *
	 * @param array $fields WC billing fields.
	 *
	 * @return array $fields WC billing fields.
	 */
	public function unrequire_wc_billing_state_field( $fields ) {
		// Unrequire if chosen payment method is Ledyer Checkout.
		if ( null !== WC()->session && method_exists( WC()->session, 'get' ) &&
			WC()->session->get( 'chosen_payment_method' ) &&
			'lco' === WC()->session->get( 'chosen_payment_method' )
		) {
			$fields['billing_state']['required'] = false;
		}

		return $fields;
	}

	/**
	 * Unrequire WC shipping state field.
	 *
	 * @param array $fields WC shipping fields.
	 *
	 * @return array $fields WC shipping fields.
	 */
	public function unrequire_wc_shipping_state_field( $fields ) {
		// Unrequire if chosen payment method is Ledyer Checkout.
		if ( null !== WC()->session && method_exists( WC()->session, 'get' ) &&
			WC()->session->get( 'chosen_payment_method' ) &&
			'lco' === WC()->session->get( 'chosen_payment_method' )
		) {
			$fields['shipping_state']['required'] = false;
		}

		return $fields;
	}

	/**
	 * Change Shipping fields in edit order page
	 *
	 * @param array $fields The shipping fields
	 *
	 * @return mixed
	 */
	public function change_admin_shipping_fields( $fields ) {
		global $post;

		if ( ! is_object( $post ) ) {
			$order = wc_get_order( get_the_ID() );
		} else {
			$order = wc_get_order( $post->ID );
		}

		if ( 'lco' === $order->get_payment_method() ) {
			// Unset shipping phone.
			unset( $fields['phone'] );
		}

		return $fields;
	}
}
