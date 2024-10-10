<?php
/**
 * Class file for LCO_Gateway class.
 *
 * @package Ledyer
 * @since 1.0.0
 */

namespace Ledyer;

use Ledyer\HPP;

\defined( 'ABSPATH' ) || die();

if ( class_exists( 'WC_Payment_Gateway' ) ) {
	/**
	 * LCO_Gateway class.
	 *
	 * @extends WC_Payment_Gateway
	 */
	class LCO_Gateway extends \WC_Payment_Gateway {

		public function __construct() {
			$this->id                 = 'lco';
			$this->method_title       = __( 'Ledyer Checkout', 'ledyer-checkout-for-woocommerce' );
			$this->method_description = __( 'The current Ledyer Checkout replaces standard WooCommerce checkout page.', 'ledyer-checkout-for-woocommerce' );
			$this->has_fields         = false;
			$this->supports           = apply_filters(
				'lco_wc_supports',
				array(
					'products',
					'subscriptions',
					'subscription_cancellation',
					'subscription_suspension',
					'subscription_reactivation',
					'subscription_amount_changes',
					'subscription_date_changes',
					'multiple_subscriptions',
					'subscription_payment_method_change_customer',
					'subscription_payment_method_change_admin',
				)
			);

			// Load the form fields.
			$this->init_form_fields();

			// Load the settings.
			$this->init_settings();

			$this->title       = $this->get_option( 'title' );
			$this->description = $this->get_option( 'description' );

			$this->enabled  = $this->get_option( 'enabled' );
			$this->testmode = 'yes' === $this->get_option( 'testmode' );
			$this->logging  = 'yes' === $this->get_option( 'logging' );

			\add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			\add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
			\add_filter( 'script_loader_tag', array( $this, 'add_data_attributes' ), 10, 2 );

			\add_action( 'woocommerce_checkout_order_processed', array( $this, 'wc_order_created' ), 10, 3 );
			\add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'show_thank_you_snippet' ) );
			\add_action( 'woocommerce_thankyou', 'lco_unset_sessions', 100, 1 );

			\add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'ledyer_order_billing_fields' ), 10, 1 );
			\add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'ledyer_order_shipping_fields' ), 10, 1 );

			// Validate shipping and billing custom fields
			\add_action( 'woocommerce_process_shop_order_meta', array( $this, 'lom_validate_lom_edit_ledyer_order' ), 45, 1 );
			// Save shipping and billing custom fields (higher priority than "lom_validate_lom_edit_ledyer_order" to make sure validation is done first)
			\add_action( 'woocommerce_process_shop_order_meta', array( $this, 'ledyer_order_save_custom_fields' ), 50, 1 );

			// Invalidate token cache when settings are updated
			\add_action( 'woocommerce_update_options', array( $this, 'on_ledyer_settings_save' ), 1 );
		}

		public function on_ledyer_settings_save() {
			// Clear the transient to ensure fresh data is fetched on the next request
			delete_transient( 'ledyer_token' );
			delete_transient( 'test_ledyer_token' );
		}

		/**
		 * Get gateway icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			$icon_src  = 'https://static.ledyer.com/images/logos/ledyer-darkgray.svg';
			$icon_html = '<img src="' . $icon_src . '" alt="Ledyer Checkout" style="width: 100px;border-radius:0px"/>';

			return apply_filters( 'wc_ledyer_checkout_icon_html', $icon_html );
		}

		/**
		 * Initialise settings fields.
		 */
		public function init_form_fields() {
			$this->form_fields = Fields::fields();
		}

		/**
		 * Enqueue payment scripts.
		 *
		 * @hook wp_enqueue_scripts
		 */
		public function enqueue_scripts() {
			if ( 'yes' !== ledyer()->get_setting( 'enabled' ) ) {
				return;
			}

			// Load the Ledyer Checkout for WooCommerce stylesheet.
			wp_register_style(
				'lco',
				plugins_url( 'build/ledyer-checkout-for-woocommerce.css', LCO_WC_MAIN_FILE ),
				array(),
				filemtime( plugin_dir_path( LCO_WC_MAIN_FILE ) . ( 'build/ledyer-checkout-for-woocommerce.css' ) )
			);
			wp_enqueue_style( 'lco' );

			if ( ! is_checkout() ) {
				return;
			}

			$scriptSrcUrl = 'https://checkout.live.ledyer.com/bootstrap.js';

			if ( $this->testmode ) {
				switch ( ledyer()->get_setting( 'development_test_environment' ) ) {
					case 'local':
					case 'local-fe':
						$scriptSrcUrl = 'http://localhost:1337/bootstrap.iife.js';
						break;
					case 'development':
						$scriptSrcUrl = 'https://checkout.dev.ledyer.com/bootstrap.js';
						break;
					default:
						$scriptSrcUrl = 'https://checkout.sandbox.ledyer.com/bootstrap.js';
						break;
				}
			}

			// Register iframe script
			wp_register_script(
				'lco-iframe',
				$scriptSrcUrl,
				array( 'jquery', 'wc-cart' ),
				LCO_WC_VERSION,
				true
			);

			if ( is_order_received_page() ) {

				if ( 'redirect' !== ( $this->settings['checkout_flow'] ?? 'embedded' ) ) {
					wp_enqueue_script( 'lco-iframe' );
				}

				return;
			}

			$pay_for_order = false;

			if ( is_wc_endpoint_url( 'order-pay' ) ) {
				$pay_for_order = true;
			}

			wp_register_script(
				'lco',
				plugins_url( 'assets/js/ledyer-checkout-for-woocommerce.js', LCO_WC_MAIN_FILE ),
				array( 'jquery', 'wc-cart', 'jquery-blockui' ),
				'73823712837218321',
				true
			);

			$email_exists = 'no';

			if ( null !== WC()->customer && method_exists( WC()->customer, 'get_billing_email' ) && ! empty( WC()->customer->get_billing_email() ) ) {
				if ( email_exists( WC()->customer->get_billing_email() ) ) {
					// Email exist in a user account.
					$email_exists = 'yes';
				}
			}

			$standard_woo_checkout_fields = apply_filters(
				'lco_ignored_checkout_fields',
				array(
					'billing_first_name',
					'billing_last_name',
					'billing_address_1',
					'billing_address_2',
					'billing_postcode',
					'billing_city',
					'billing_phone',
					'billing_email',
					'billing_state',
					'billing_country',
					'billing_company',
					'shipping_first_name',
					'shipping_last_name',
					'shipping_address_1',
					'shipping_address_2',
					'shipping_postcode',
					'shipping_city',
					'shipping_state',
					'shipping_country',
					'shipping_company',
					'terms',
					'terms-field',
					'_wp_http_referer',
					'ship_to_different_address',
				)
			);

			$checkout_localize_params = array(
				'update_cart_url'              => \WC_AJAX::get_endpoint( 'lco_wc_update_cart' ),
				'update_cart_nonce'            => wp_create_nonce( 'lco_wc_update_cart' ),
				'change_payment_method_url'    => \WC_AJAX::get_endpoint( 'lco_wc_change_payment_method' ),
				'change_payment_method_nonce'  => wp_create_nonce( 'lco_wc_change_payment_method' ),
				'get_ledyer_order_url'         => \WC_AJAX::get_endpoint( 'lco_wc_get_ledyer_order' ),
				'get_ledyer_order_nonce'       => wp_create_nonce( 'lco_wc_get_ledyer_order' ),
				'log_to_file_url'              => \WC_AJAX::get_endpoint( 'lco_wc_log_js' ),
				'log_to_file_nonce'            => wp_create_nonce( 'lco_wc_log_js' ),
				'submit_order'                 => \WC_AJAX::get_endpoint( 'checkout' ),
				'logging'                      => $this->logging,
				'standard_woo_checkout_fields' => $standard_woo_checkout_fields,
				'is_confirmation_page'         => ( is_lco_confirmation() ) ? 'yes' : 'no',
				'required_fields_text'         => __( 'Please fill in all required checkout fields.', 'ledyer-checkout-for-woocommerce' ),
				'email_exists'                 => $email_exists,
				'must_login_message'           => apply_filters( 'woocommerce_registration_error_email_exists', __( 'An account is already registered with your email address. Please log in.', 'woocommerce' ) ),
				'timeout_message'              => __( 'Please try again, something went wrong with processing your order.', 'ledyer-checkout-for-woocommerce' ),
				'timeout_time'                 => apply_filters( 'lco_checkout_timeout_duration', 20 ),
				'pay_for_order'                => $pay_for_order,
				'no_shipping_message'          => apply_filters( 'woocommerce_no_shipping_available_html', __( 'There are no shipping options available. Please ensure that your address has been entered correctly, or contact us if you need any help.', 'woocommerce' ) ),
			);

			$checkout_localize_params['force_update'] = true;

			wp_localize_script( 'lco', 'lco_params', $checkout_localize_params );

			wp_enqueue_script( 'lco-iframe' );

			if ( is_wc_endpoint_url( 'order-pay' ) || 'redirect' === ( $this->settings['checkout_flow'] ?? 'embedded' ) ) {
				return;
			}

			wp_enqueue_script( 'lco' );
		}

		/**
		 * Process the payment and return the result.
		 *
		 * @param int $order_id WooCommerce order ID.
		 *
		 * @return array
		 */
		public function process_payment( $order_id ) {
			$order = wc_get_order( $order_id );

			// HPP Redirect flow.
			if ( is_wc_endpoint_url( 'order-pay' ) || 'redirect' === ( $this->settings['checkout_flow'] ?? 'embedded' ) ) {

				// Run redirect.
				return $this->hpp_redirect_handler( $order );

			}

			// Regular purchase.
			// 1. Process the payment.
			// 2. Redirect to order received page.
			if ( $this->process_payment_handler( $order_id ) ) {
				// Base64 encoded timestamp to always have a fresh URL for on hash change event.
				return array(
					'result'   => 'success',
					'redirect' => add_query_arg(
						array(
							'lco_confirm' => 'yes',
						),
						$order->get_checkout_order_received_url()
					),
				);
			} else {
				return array(
					'result' => 'error',
				);
			}
		}

		/**
		 * Process the payment with information from Ledyer and return the result.
		 *
		 * @param int $order_id WooCommerce order ID.
		 *
		 * @return mixed
		 */
		public function process_payment_handler( $order_id ) {
			// Get the Ledyer order ID.
			$order = wc_get_order( $order_id );

			$ledyer_order_id = WC()->session->get( 'lco_wc_order_id' );

			$ledyer_order = ledyer()->api->get_order_session( $ledyer_order_id );

			if ( ! $ledyer_order || is_wp_error( $ledyer_order ) ) {
				return false;
			}

			if ( $order && $ledyer_order ) {
				$customer_billing  = isset( $ledyer_order['customer']['billingAddress'] ) ? $ledyer_order['customer']['billingAddress'] : false;
				$customer_shipping = isset( $ledyer_order['customer']['shippingAddress'] ) ? $ledyer_order['customer']['shippingAddress'] : false;

				$company_name = ! empty( $customer_billing['companyName'] ) ? $customer_billing['companyName'] : ( ! empty( $customer_shipping['companyName'] ) ? $customer_shipping['companyName'] : '' );
				// Set WC order transaction ID.
				$order->update_meta_data( '_wc_ledyer_order_id', $ledyer_order['orderId'] );

				$order->update_meta_data( '_wc_ledyer_session_id', $ledyer_order['id'] );

				$order->set_transaction_id( $ledyer_order['orderId'] );

				$order->update_meta_data( '_ledyer_company_id', $ledyer_order['customer']['companyId'] );

				$order->update_meta_data( '_ledyer_company_name', $company_name );

				$environment = $this->testmode ? 'sandbox' : 'production';
				$order->update_meta_data( '_wc_ledyer_environment', $environment );

				$ledyer_country = wc_get_base_location()['country'];
				$order->update_meta_data( '_wc_ledyer_country', $ledyer_country );

				// Set shipping meta
				if ( isset( $ledyer_order['customer']['shippingAddress'] ) ) {
					$order->update_meta_data( '_shipping_attention_name', sanitize_text_field( $ledyer_order['customer']['shippingAddress']['attentionName'] ) );
					$order->update_meta_data( '_shipping_care_of', sanitize_text_field( $ledyer_order['customer']['shippingAddress']['careOf'] ) );
				}
				// Set order recipient meta
				if ( isset( $ledyer_order['customer']['shippingAddress']['contact'] ) ) {
					$order->update_meta_data( '_shipping_first_name', sanitize_text_field( $ledyer_order['customer']['shippingAddress']['contact']['firstName'] ) );
					$order->update_meta_data( '_shipping_last_name', sanitize_text_field( $ledyer_order['customer']['shippingAddress']['contact']['lastName'] ) );
					$order->update_meta_data( '_shipping_phone', sanitize_text_field( $ledyer_order['customer']['shippingAddress']['contact']['phone'] ) );
					$order->update_meta_data( '_shipping_email', sanitize_text_field( $ledyer_order['customer']['shippingAddress']['contact']['email'] ) );
				}

				// Set billing meta
				if ( isset( $ledyer_order['customer']['billingAddress'] ) ) {
					$order->update_meta_data( '_billing_attention_name', sanitize_text_field( $ledyer_order['customer']['billingAddress']['attentionName'] ) );
					$order->update_meta_data( '_billing_care_of', sanitize_text_field( $ledyer_order['customer']['billingAddress']['careOf'] ) );
				}

				$order->save();

				// Let other plugins hook into this sequence.
				do_action( 'lco_wc_process_payment', $order_id, $ledyer_order );

				// Check that the transaction id got set correctly.
				if ( $order->get_meta( '_transaction_id', true ) === $ledyer_order_id ) {
					return true;
				}
			}

			// Return false if we get here. Something went wrong.
			return false;
		}

		/**
		 * Process the payment for HPP/redirect checkout flow.
		 *
		 * @param object $order The WooCommerce order.
		 *
		 * @return array|string[]
		 */
		protected function hpp_redirect_handler( $order ) {

			if ( empty( $order ) ) {

				return array(
					'result'   => 'error',
					'messages' => array( 'Failed to get order for HPP.' ),
				);
			}

			$data = \Ledyer\Requests\Helpers\Woocommerce_Bridge::get_order_data( $order );
			// Add confirmation URL to the order.
			$ledyer_order = ledyer()->api->create_order_session( $data );
			if ( is_wp_error( $ledyer_order ) ) {

				return array(
					'result'   => 'error',
					'messages' => array( 'Failed to create order session for HPP.' ),
				);
			}

			// Create a HPP url.
			$hpp          = new HPP();
			$hpp_redirect = $hpp->create_hpp_url( $ledyer_order['sessionId'] );

			// Set WC order transaction ID.
			$order->update_meta_data( '_wc_ledyer_order_id', $ledyer_order['orderId'] );
			$order->update_meta_data( '_wc_ledyer_session_id', $ledyer_order['sessionId'] );

			$order->set_transaction_id( $ledyer_order['orderId'] );

			$environment = $this->testmode ? 'sandbox' : 'production';
			$order->update_meta_data( '_wc_ledyer_environment', $environment );

			$ledyer_country = wc_get_base_location()['country'];
			$order->update_meta_data( '_wc_ledyer_country', $ledyer_country );

			$order->save();

			// All good. Redirect customer to ledyer Hosted payment page.
			$order->add_order_note( __( 'Customer redirected to Ledyer Hosted Payment Page.', 'ledyer-checkout-for-woocommerce' ) );

			return array(
				'result'   => 'success',
				'redirect' => $hpp_redirect,
			);
		}

		/**
		 * This plugin doesn't handle order management, but it allows Ledyer Order Management plugin to process refunds
		 * and then return true or false.
		 *
		 * @param int      $order_id WooCommerce order ID.
		 * @param null|int $amount Refund amount.
		 * @param string   $reason Reason for refund.
		 *
		 * @return bool
		 */
		public function process_refund( $order_id, $amount = null, $reason = '' ) {
			return apply_filters( 'wc_ledyer_checkout_process_refund', false, $order_id, $amount, $reason );
		}

		/**
		 * Displays Ledyer Checkout thank you iframe and clears Ledyer order ID value from WC session.
		 *
		 * @param int $order_id WooCommerce order ID.
		 */
		public function show_thank_you_snippet( $order_id = null ) {
			if ( $order_id ) {
				$order = wc_get_order( $order_id );

				if ( is_object( $order ) && $order->get_transaction_id() ) {
					?>
					<div id="lco-iframe">
						<?php do_action( 'lco_wc_thankyou_before_snippet' ); ?>
						<?php do_action( 'lco_wc_thankyou_after_snippet' ); ?>
					</div>

					<?php
				}
			}
		}

		public function add_data_attributes( $tag, $handle ) {
			if ( $handle == 'lco-iframe' ) {

				$env = 'production';

				if ( $this->testmode ) {
					switch ( ledyer()->get_setting( 'development_test_environment' ) ) {
						case 'local':
						case 'local-fe':
							$env = 'localhost';
							break;
						case 'development':
							$env = 'dev';
							break;
						default:
							$env = 'sandbox';
							break;
					}
				}

				$buy_button_color = ledyer()->get_setting( 'color_button' );
				$no_padding       = 'yes' === ledyer()->get_setting( 'iframe_padding' ) ? 'true' : 'false';
				$lco_order_id     = WC()->session->get( 'lco_wc_session_id' );

				if ( is_order_received_page() ) {
					$order_key = filter_input( INPUT_GET, 'key', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

					if ( empty( $order_key ) ) {
						return;
					}
					$order_id     = wc_get_order_id_by_order_key( $order_key );
					$order        = wc_get_order( $order_id );
					$lco_order_id = $order->get_meta( '_wc_ledyer_session_id', true );
				}

				return str_replace( '<script', '<script data-env="' . $env . '"  data-session-id="' . $lco_order_id . '" data-container-id="lco-iframe" data-buy-button-color="' . $buy_button_color . '" data-no-padding="' . $no_padding . '" ', $tag );
			}

			return $tag;
		}

		/**
		 * @param $order_id
		 * @param $posted_data
		 * @param $order
		 */
		public function wc_order_created( $order_id, $posted_data, $order ) {
		}

		/**
		 * Add additional billing fields on Edit Order Screen
		 *
		 * @param $order
		 *
		 * @return void
		 */
		public function ledyer_order_billing_fields( $order ) {

			if ( 'Automattic\WooCommerce\Admin\Overrides\Order' === get_class( $order ) ) {
				$order_id = $order->get_id();
			} else {
				$order_id = $order->id;
			}

			$attention_name = $order->get_meta( '_billing_attention_name', true );
			$care_of        = $order->get_meta( '_billing_care_of', true );

			?>
				<div class="address">
					<p
					<?php
					if ( ! $attention_name ) {
						echo ' class="none_set"'; }
					?>
					>
						<strong>Attention Name:</strong>
						<?php echo $attention_name ? esc_html( $attention_name ) : ''; ?>
					</p>
				</div>
				<div class="edit_address">
					<?php
						woocommerce_wp_text_input(
							array(
								'id'            => '_billing_attention_name',
								'label'         => 'Attention Name:',
								'value'         => $attention_name,
								'wrapper_class' => 'form-field-wide',
							)
						);
					?>
				</div>
				<div class="address">
					<p
					<?php
					if ( ! $care_of ) {
						echo ' class="none_set"'; }
					?>
					>
						<strong>Care Of:</strong>
						<?php echo $care_of ? esc_html( $care_of ) : ''; ?>
					</p>
				</div>
				<div class="edit_address">
					<?php
						woocommerce_wp_text_input(
							array(
								'id'            => '_billing_care_of',
								'label'         => 'Care Of:',
								'value'         => $care_of,
								'wrapper_class' => 'form-field-wide',
							)
						);
					?>
				</div>
			<?php
		}
		/**
		 * Add additional shipping fields on Edit Order Screen
		 *
		 * @param $order
		 *
		 * @return void
		 */
		public function ledyer_order_shipping_fields( $order ) {

			if ( 'Automattic\WooCommerce\Admin\Overrides\Order' === get_class( $order ) ) {
				$order_id = $order->get_id();
			} else {
				$order_id = $order->id;
			}

			$attention_name = $order->get_meta( '_shipping_attention_name', true );
			$care_of        = $order->get_meta( '_shipping_care_of', true );
			$first_name     = $order->get_meta( '_shipping_first_name', true );
			$last_name      = $order->get_meta( '_shipping_last_name', true );
			$phone          = $order->get_meta( '_shipping_phone', true );
			$email          = $order->get_meta( '_shipping_email', true );

			?>
				<div class="address">
					<p
					<?php
					if ( ! $attention_name ) {
						echo ' class="none_set"'; }
					?>
					>
						<strong>Attention Name:</strong>
						<?php echo $attention_name ? esc_html( $attention_name ) : ''; ?>
					</p>
				</div>
				<div class="edit_address">
					<?php
						woocommerce_wp_text_input(
							array(
								'id'            => '_shipping_attention_name',
								'label'         => 'Attention Name:',
								'value'         => $attention_name,
								'wrapper_class' => 'form-field-wide',
							)
						);
					?>
				</div>
				<div class="address">
					<p
					<?php
					if ( ! $care_of ) {
						echo ' class="none_set"'; }
					?>
					>
						<strong>Care Of:</strong>
						<?php echo $care_of ? esc_html( $care_of ) : ''; ?>
					</p>
				</div>
				<div class="edit_address">
					<?php
						woocommerce_wp_text_input(
							array(
								'id'            => '_shipping_care_of',
								'label'         => 'Care Of:',
								'value'         => $care_of,
								'wrapper_class' => 'form-field-wide',
							)
						);
					?>
				</div>
			<?php

			if ( ! empty( $first_name ) || ! empty( $last_name ) ) {
				?>
					<div class="address">
						<p>
							<strong>Order Recipient Name:</strong>
							<?php echo $first_name ? esc_html( $first_name ) : ''; ?>
							<?php echo $last_name ? esc_html( $last_name ) : ''; ?>
						</p>
					</div>
				<?php
			}

			if ( ! empty( $phone ) ) {
				?>
					<div class="address">
						<p>
							<strong>Order Recipient Phone:</strong>
							<?php echo $phone ? esc_html( $phone ) : ''; ?>
						</p>
					</div>
				<?php
			}
			?>
				<div class="edit_address">
					<?php
						woocommerce_wp_text_input(
							array(
								'id'            => '_shipping_phone',
								'label'         => 'Order Recipient Phone:',
								'value'         => $phone,
								'wrapper_class' => 'form-field-wide',
							)
						);
					?>
				</div>
			<?php

			if ( ! empty( $email ) ) {
				?>
					<div class="address">
						<p>
							<strong>Order Recipient Email:</strong>
							<?php echo $email ? esc_html( $email ) : ''; ?>
						</p>
					</div>
				<?php
			}
			?>
				<div class="edit_address">
					<?php
						woocommerce_wp_text_input(
							array(
								'id'            => '_shipping_email',
								'label'         => 'Order Recipient Email:',
								'value'         => $email,
								'wrapper_class' => 'form-field-wide',
							)
						);
					?>
				</div>
			<?php
		}

		public function ledyer_order_save_custom_fields( $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				return;
			}
			$order->update_meta_data( '_billing_attention_name', sanitize_text_field( $_POST['_billing_attention_name'] ) );
			$order->update_meta_data( '_billing_care_of', sanitize_text_field( $_POST['_billing_care_of'] ) );
			$order->update_meta_data( '_shipping_attention_name', sanitize_text_field( $_POST['_shipping_attention_name'] ) );
			$order->update_meta_data( '_shipping_care_of', sanitize_text_field( $_POST['_shipping_care_of'] ) );
			$order->update_meta_data( '_shipping_phone', sanitize_text_field( $_POST['_shipping_phone'] ) );
			$order->update_meta_data( '_shipping_email', sanitize_text_field( $_POST['_shipping_email'] ) );
			$order->save();
		}

		/**
		 * Validate edit Ledyer order.
		 *
		 * @param $order The woo order (must contain changes array)
		 */
		public function lom_validate_lom_edit_ledyer_order( $order_id ) {

			$order = wc_get_order( $order_id );

			if ( ! $this->lom_allow_editing( $order ) ) {
				return;
			}
				$this->lom_validate_customer_field( $order, '_billing_company', 0, 100 );
				$this->lom_validate_customer_field( $order, '_billing_address_1', 0, 100 );
				$this->lom_validate_customer_field( $order, '_billing_address_2', 0, 100 );
				$this->lom_validate_customer_field( $order, '_billing_postcode', 0, 10 );
				$this->lom_validate_customer_field( $order, '_billing_city', 0, 50 );
				$this->lom_validate_customer_field( $order, '_billing_country', 0, 50 );
				$this->lom_validate_customer_field( $order, '_billing_attention_name', 0, 100 );
				$this->lom_validate_customer_field( $order, '_billing_care_of', 0, 100 );

				$this->lom_validate_customer_field( $order, '_shipping_company', 0, 100 );
				$this->lom_validate_customer_field( $order, '_shipping_address_1', 0, 100 );
				$this->lom_validate_customer_field( $order, '_shipping_address_2', 0, 100 );
				$this->lom_validate_customer_field( $order, '_shipping_postcode', 0, 10 );
				$this->lom_validate_customer_field( $order, '_shipping_city', 0, 50 );
				$this->lom_validate_customer_field( $order, '_shipping_country', 0, 50 );
				$this->lom_validate_customer_field( $order, '_shipping_attention_name', 0, 100 );
				$this->lom_validate_customer_field( $order, '_shipping_care_of', 0, 100 );
				$this->lom_validate_customer_field( $order, '_shipping_first_name', 0, 200 );
				$this->lom_validate_customer_field( $order, '_shipping_last_name', 0, 200 );
				$this->lom_validate_customer_field( $order, '_shipping_phone', 9, 30 );
				$this->lom_validate_customer_email( $order, '_shipping_email' );
		}
		public function lom_validate_customer_field( $order, $fieldName, $min, $max ) {
			$value = sanitize_text_field( $_POST[ $fieldName ] );
			$valid = $this->lom_validate_field_length( $value, $min, $max );
			if ( ! $valid ) {
				$order->add_order_note( 'Ledyer customer data could not be updated. Invalid ' . $fieldName );
				wp_safe_redirect( wp_get_referer() );
				exit;
			}
		}
		public function lom_validate_field_length( $str, $min, $max ) {
			if ( ! $str ) {
				return true;
			}
			$len = strlen( $str );
			return ! ( $len < $min || $len > $max );
		}
		public function lom_validate_customer_email( $order, $fieldName ) {
			$value = sanitize_text_field( $_POST[ $fieldName ] );
			if ( ! $value ) {
				return true;
			}
			$valid = is_email( $value );
			if ( ! $valid ) {
				$order->add_order_note( 'Ledyer customer data could not be updated. Invalid ' . $fieldName );
				wp_safe_redirect( wp_get_referer() );
				exit;
			}
		}

		public function lom_allow_editing( $order ) {
			$is_ledyer_order = $this->lom_order_placed_with_ledyer( $order->get_payment_method() );
			if ( ! $is_ledyer_order ) {
				return false;
			}

			if ( $order->has_status( array( 'completed', 'refunded', 'cancelled' ) ) ) {
				return false;
			}

			return true;
		}

		public function lom_order_placed_with_ledyer( $payment_method ) {
			if ( in_array( $payment_method, array( 'ledyer_payments', 'lco' ) ) ) {
				return true;
			}
			return false;
		}
	}
}
