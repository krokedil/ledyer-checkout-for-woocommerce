<?php
/**
 * Class for Ledyer Checkout gateway settings.
 *
 * @package Ledyer
 * @since 1.0.0
 */

namespace Ledyer;

\defined( 'ABSPATH' ) || die();

/**
 * Fields class.
 *
 * Ledyer Checkout for WooCommerce settings fields.
 */
class Fields {

	/**
	 * Returns the fields.
	 *
	 * @return array $settings
	 */
	public static function fields() {
		$settings = array(
			'enabled'                    => array(
				'title'       => __( 'Enable/Disable', 'ledyer-checkout-for-woocommerce' ),
				'label'       => __( 'Enable Ledyer Checkout', 'ledyer-checkout-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			),
			'title'                      => array(
				'title'       => __( 'Title', 'ledyer-checkout-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Payment method title.', 'ledyer-checkout-for-woocommerce' ),
				'default'     => 'Ledyer',
				'desc_tip'    => true,
			),
			'description'                => array(
				'title'       => __( 'Description', 'ledyer-checkout-for-woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description.', 'ledyer-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'select_another_method_text' => array(
				'title'             => __( 'Other payment method button text', 'ledyer-checkout-for-woocommerce' ),
				'type'              => 'text',
				'description'       => __( 'Customize the <em>Select another payment method</em> button text that is displayed in checkout if using other payment methods than Ledyer Checkout. Leave blank to use the default (and translatable) text.', 'ledyer-checkout-for-woocommerce' ),
				'default'           => '',
				'desc_tip'          => true,
				'custom_attributes' => array(
					'autocomplete' => 'off',
				),
			),
			'testmode'                   => array(
				'title'       => __( 'Test mode', 'ledyer-checkout-for-woocommerce' ),
				'label'       => __( 'Enable Test Mode', 'ledyer-checkout-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Place the payment gateway in test mode using test API keys.', 'ledyer-checkout-for-woocommerce' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'logging'                    => array(
				'title'       => __( 'Logging', 'ledyer-checkout-for-woocommerce' ),
				'label'       => __( 'Log debug messages', 'ledyer-checkout-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Save debug messages to the WooCommerce System Status log.', 'ledyer-checkout-for-woocommerce' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'checkout_flow'                  => array(
				'title'       => __( 'Checkout flow', 'ledyer-checkout-for-woocommerce' ),
				'type'        => 'select',
				'options'     => array(
					'embedded' => __( 'Embedded', 'ledyer-checkout-for-woocommerce' ),
					'redirect' => __( 'Redirect', 'ledyer-checkout-for-woocommerce' ),
				),
				'description' => __( '<strong>Embedded:</strong> the checkout is embedded in the WooCommerce checkout page and partially replaces the checkout form. <strong>Redirect:</strong> the customer is redirected to a payment page hosted by Ledyer.', 'ledyer-checkout-for-woocommerce' ),
				'default'     => 'embedded',
				'desc_tip'    => true,
			),
			'merchant_id'                    => array(
				'title'             => __( 'Production Ledyer client ID', 'ledyer-checkout-for-woocommerce' ),
				'type'              => 'text',
				'description'       => __( 'Generate credentials (client ID and secret) in the Ledyer Merchant Portal under Settings -> API credentials.', 'ledyer-checkout-for-woocommerce' ),
				'default'           => '',
				'desc_tip'          => false,
				'custom_attributes' => array(
					'autocomplete' => 'off',
				),
			),
			'store_id'             => array(
				'title'             => __( 'Production Ledyer Store Id', 'ledyer-checkout-for-woocommerce' ),
				'type'              => 'text',
				'description'       => __( 'Optional. If you have multiple stores set in Ledyer account. Paste corresponding store ID found in Ledyer Merchant Portal.', 'ledyer-checkout-for-woocommerce' ),
				'default'           => '',
				'desc_tip'          => false,
				'custom_attributes' => array(
					'autocomplete' => 'off',
				),
			),
			'shared_secret'           => array(
				'title'             => __( 'Production Ledyer client secret', 'ledyer-checkout-for-woocommerce' ),
				'type'              => 'password',
				'description'       => __( 'Generate credentials (client ID and secret) in the Ledyer Merchant Portal under Settings -> API credentials.', 'ledyer-checkout-for-woocommerce' ),
				'default'           => '',
				'desc_tip'          => false,
				'custom_attributes' => array(
					'autocomplete' => 'new-password',
				),
			),
			'test_merchant_id'        => array(
				'title'             => __( 'Sandbox Ledyer client ID', 'ledyer-checkout-for-woocommerce' ),
				'type'              => 'text',
				'description'       => __( 'Generate credentials (client ID and secret) in the Ledyer Merchant Portal under Settings -> API credentials.', 'ledyer-checkout-for-woocommerce' ),
				'default'           => '',
				'desc_tip'          => false,
				'custom_attributes' => array(
					'autocomplete' => 'off',
				),
			),
			'test_store_id'             => array(
				'title'             => __( 'Sandbox Ledyer Store Id', 'ledyer-checkout-for-woocommerce' ),
				'type'              => 'text',
				'description'       => __( 'Optional. If you have multiple stores set in Ledyer account. Paste corresponding store ID found in Ledyer Merchant Portal.', 'ledyer-checkout-for-woocommerce' ),
				'default'           => '',
				'desc_tip'          => false,
				'custom_attributes' => array(
					'autocomplete' => 'off',
				),
			),
			'test_shared_secret'      => array(
				'title'             => __( 'Sandbox Ledyer client secret', 'ledyer-checkout-for-woocommerce' ),
				'type'              => 'password',
				'description'       => __( 'Generate credentials (client ID and secret) in the Ledyer Merchant Portal under Settings -> API credentials.', 'ledyer-checkout-for-woocommerce' ),
				'default'           => '',
				'desc_tip'          => false,
				'custom_attributes' => array(
					'autocomplete' => 'new-password',
				),
			),
			'notifications_endpoint_section'           => array(
				'title' => __( 'Ledyer notifications endpoint', 'ledyer-checkout-for-woocommerce' ),
				'type'  => 'title',
			),
			'notifications_endpoint_link'           => array(
				'title' => __( get_home_url() . '/wp-json/ledyer/v1/notifications/', 'ledyer-checkout-for-woocommerce' ),
				'type'  => 'title',
				'description' => __( 'Use this url when setting up notifications in the settings panel in Ledyer merchant portal', 'ledyer-checkout-for-woocommerce' ),
			),

			// Checkout.
			'checkout_section'           => array(
				'title' => __( 'Checkout settings', 'ledyer-checkout-for-woocommerce' ),
				'type'  => 'title',
			),
			'allow_custom_shipping'   => array(
				'title'       => __( 'Allow shipping address', 'ledyer-checkout-for-woocommerce' ),
				'label'       => __( 'Allow customer to enter different shipping address', 'ledyer-checkout-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'If checked, the customer will be able to enter different shipping address in checkout iframe.', 'ledyer-checkout-for-woocommerce' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'show_shipping_address_contact'   => array(
				'title'       => __( 'Show custom contact details', 'ledyer-checkout-for-woocommerce' ),
				'label'       => __( 'Allow customers to enter different contact details for the order recipient', 'ledyer-checkout-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'If checked, the customer will be able to enter different shipping address recipient details in the checkout iframe.', 'ledyer-checkout-for-woocommerce' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'customer_show_name_fields'   => array(
				'title'       => __( 'Show name fields', 'ledyer-checkout-for-woocommerce' ),
				'label'       => __( 'Allow customer to enter name', 'ledyer-checkout-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'If checked, name fields will be shown in iframe.', 'ledyer-checkout-for-woocommerce' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'security_level'     => array(
				'title'       => __( 'Strong Customer Authentication', 'ledyer-checkout-for-woocommerce' ),
				'type'        => 'select',
				'options'     => array(
					'100' => '100',
					'110' => '110',
					'120' => '120',
					'200' => '200',
					'210' => '210',
					'220' => '220',
					'300' => '300',
				),
				'description' => __( 'Each level is described in <a href="https://static.ledyer.com/docs/en-US/ledyer-security_levels.pdf" target="_blank">this document</a>', 'ledyer-checkout-for-woocommerce' ),
				'default'     => '100',
				'desc_tip'    => false,
			),
			'terms_url'   => array(
				'title'       => __( 'Terms & Conditions Url', 'ledyer-checkout-for-woocommerce' ),
				'label'       => __( 'Paste published terms and conditions page link', 'ledyer-checkout-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Paste published terms and conditions page link. If not set woocommerce terms and conditions page will be used: <a href="' . get_admin_url(null, 'admin.php?page=wc-settings&tab=advanced') . '" > Advanced Settings </a>', 'ledyer-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => false,
			),
			'privacy_url'   => array(
				'title'       => __( 'Privacy Url', 'ledyer-checkout-for-woocommerce' ),
				'label'       => __( 'Paste published privacy page link', 'ledyer-checkout-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Paste published privacy page link. If not set woocommerce privacy page will be used: <a href="' . get_admin_url(null, 'options-privacy.php') . '" > Privacy Settings </a>', 'ledyer-checkout-for-woocommerce' ),
				'default'     => '',
				'desc_tip'    => false,
			),
			// Checkout iframe settings.
			'iframe_settings_title'       => array(
				'title' => __( 'Iframe Settings', 'ledyer-checkout-for-woocommerce' ),
				'type'  => 'title',
			),
			'vertical_layout' => array(
				'title'       => __( 'Vertical layout', 'ledyer-checkout-for-woocommerce' ),
				'label'       => __( 'Enable vertical layout', 'ledyer-checkout-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Make ledyer checkout appear below shipping', 'ledyer-checkout-for-woocommerce' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'iframe_padding'   => array(
				'title'       => __( 'Iframe padding', 'ledyer-checkout-for-woocommerce' ),
				'label'       => __( 'Use default padding', 'ledyer-checkout-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Ledyer checkout comes with a wrapping padding of 12px by default.', 'ledyer-checkout-for-woocommerce' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'color_button'               => array(
				'title'       => __( 'Checkout button color', 'ledyer-checkout-for-woocommerce' ),
				'type'        => 'color',
				'description' => __( 'Enter a color hex value to change the background color for the buy button, depending on the color provided the button text will be set to black or white.', 'ledyer-checkout-for-woocommerce' ),
				'default'     => '#000000',
				'desc_tip'    => true,
			),
			// Payment settings
			'payment_settings_title'       => array(
				'title' => __( 'Payment Settings', 'ledyer-checkout-for-woocommerce' ),
				'type'  => 'title',
			),
			'keep_cards_processing'   => array(
				'title'       => __( 'Card Payment Status', 'ledyer-checkout-for-woocommerce' ),
				'label'       => __( 'Keep card payments in processing status (when autocomplete is active)', 'ledyer-checkout-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'If checked, orders paid with card will stay in processing status instead of being automatically marked as completed.', 'ledyer-checkout-for-woocommerce' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
		);

		$devmode = 'local' === wp_get_environment_type() || 'development' === wp_get_environment_type();

		if ( $devmode ) {
			$dev_settings = array(
				'development_section'           => array(
					'title' => __( 'Development settings', 'ledyer-checkout-for-woocommerce' ),
					'type'  => 'title',
				),
				'development_test_environment'     => array(
					'title'       => __( 'Test Environment', 'ledyer-checkout-for-woocommerce' ),
					'type'        => 'select',
					'options'     => array(
						'local' => 'Local (FE+BE)',
						'local-fe' => 'Local (FE)',
						'development' => 'Development',
						'sandbox' => 'Sandbox',
					),
					'default'     => 'sandbox',
					'desc_tip'    => false,
				),
			);
			$settings = array_merge($settings, $dev_settings);
		}

		return apply_filters( 'lco_wc_gateway_settings', $settings );
	}
}
