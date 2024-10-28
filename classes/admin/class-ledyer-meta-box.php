<?php
/**
 * Meta box
 *
 * Handles the functionality for the LCO meta box.
 *
 * @package Ledyer
 * @since   1.0.0
 */
namespace Ledyer\Admin;

\defined( 'ABSPATH' ) || die();

/**
 * Meta_Box class.
 *
 * Handles the meta box for LCO
 */
class Meta_Box {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
	}

	/**
	 * Adds meta box to the side of a LCO order.
	 *
	 * @param string $post_type The WordPress post type.
	 * @return void
	 */
	public function add_meta_boxes( $post_type ) {
		if ( 'shop_order' === $post_type || 'woocommerce_page_wc-orders' === $post_type ) {
			$order_id = get_the_ID();
			$order    = wc_get_order( $order_id );
			if ( in_array( $order->get_payment_method(), array( 'lco', 'ledyer_payments' ), true ) ) {
				add_meta_box( 'lco_meta_box', __( 'Ledyer Order Info', 'ledyer-checkout-for-woocommerce' ), array( $this, 'meta_box_content' ), wc_get_page_screen_id( 'shop-order' ), 'side', 'core' );
			}
		}
	}

	/**
	 * Adds content for the Ledyer meta box.
	 *
	 * @return void
	 */
	public function meta_box_content() {
		$order_id = get_the_ID();

		// False if automatic settings are enabled, true if not. If true then show the option.
		if ( ! empty( $order->get_meta( '_transaction_id', true ) ) && ! empty( $order->get_meta( '_wc_ledyer_order_id', true ) ) ) {
			$ledyer_order = ledyer()->api->get_order( $order->get_meta( '_wc_ledyer_order_id', true ) );

				$ledyer_session = ledyer()->api->get_order_session( $order->get_meta( '_wc_ledyer_order_id', true ) );
				if ( is_wp_error( $ledyer_session ) ) {
					$this->print_error_content( __( 'Failed to retrieve the session from Ledyer.', 'ledyer-checkout-for-woocommerce' ) );
					return;
				}
				$this->print_session_content( $ledyer_session );
				$this->print_error_content( __( 'Failed to retrieve the order from Ledyer. The customer may not have been able to complete the checkout flow', 'ledyer-checkout-for-woocommerce' ) );
				return;
			} else {
				$this->print_order_content( $ledyer_order );
			}
		}
	}

	/**
	 * Get test environment display name
	 *
	 * @return string
	 */
	protected function get_test_environment_name() {
			case 'local':
			case 'development':
			case 'local-fe':
			default:
		}
	}


	/**
	 * Get ledyer merchant portal test url
	 *
	 * @return string
	 */
	protected function get_ledyer_merchant_portal_test_url() {
			case 'local':
				return 'http://host.docker.internal:2000/orders/';
			case 'development':
			case 'local-fe':
				return 'https://merchant.dev.ledyer.com/orders/';
			default:
				return 'https://merchant.sandbox.ledyer.com/orders/';
		}
	}

	/**
	 * Prints the standard order content for the Metabox
	 *
	 * @param object $ledyer_order The Ledyer order object.
	 * @return void
	 */
	private function print_order_content( $ledyer_order ) {
		?>
		<div class="lco-meta-box-content">
			<?php if ( $ledyer_order ) { ?>
				<?php
					$environment = 'yes' === ledyer()->get_setting('testmode') ? $this->get_test_environment_name() : 'Live';
					$ledyer_order_url = 'yes' === ledyer()->get_setting('testmode') ?
						$this->get_ledyer_merchant_portal_test_url() . $ledyer_order['id'] :
						'https://merchant.live.ledyer.com/orders/' . $ledyer_order['id'];
					?>
					<strong><?php esc_html_e( 'Environment: ', 'ledyer-checkout-for-woocommerce' ); ?> </strong><?php echo esc_html( $environment ); ?><br/>
				<?php } ?>
				<strong><?php esc_html_e( 'Company: ', 'ledyer-checkout-for-woocommerce' ); ?> </strong> <?php echo esc_html( $ledyer_order['customer']['companyName'] ); ?><br/>
				<strong><?php esc_html_e( 'Company ID: ', 'ledyer-checkout-for-woocommerce' ); ?> </strong> <?php echo esc_html( $ledyer_order['customer']['companyId'] ); ?><br/>
					<strong><?php esc_html_e( 'Name: ', 'ledyer-checkout-for-woocommerce' ); ?> </strong> <?php echo esc_html( $ledyer_order['customer']['firstName'] . ' ' . $ledyer_order['customer']['lastName'] ); ?><br/>
				<?php endif; ?>
				<strong><?php esc_html_e( 'Ledyer ID: ', 'ledyer-checkout-for-woocommerce' ); ?></strong> <a href="<?php echo esc_url( $ledyer_order_url ); ?>" target="_blank"><?php echo esc_html( $ledyer_order['orderReference'] ); ?></a><br/>
				<strong><?php esc_html_e( 'Payment method: ', 'ledyer-checkout-for-woocommerce' ); ?> </strong> <?php echo esc_html( $ledyer_order['paymentMethod']['type'] ); ?><br/>
					<strong><?php esc_html_e( 'Invoice: ', 'ledyer-checkout-for-woocommerce' ); ?> </strong> <?php echo esc_html( $ledyer_order['customer']['email']); ?><br/>
					<strong><?php esc_html_e( 'Invoice copy: ', 'ledyer-checkout-for-woocommerce' ); ?> </strong> <?php echo esc_html( $ledyer_order['customer']['invoiceChannel']['details'] ); ?><br/>
				<?php endif; ?>
					<strong><?php esc_html_e( 'GLN: ', 'ledyer-checkout-for-woocommerce' ); ?> </strong> <?php echo esc_html( $ledyer_order['customer']['invoiceChannel']['details'] ); ?><br/>
				<?php endif; ?>
				<?php if( $ledyer_order['customer']['reference1'] ) : ?>
					<strong><?php esc_html_e( 'Invoice reference (e.g. order number): ', 'ledyer-checkout-for-woocommerce' ); ?> </strong> <?php echo esc_html( $ledyer_order['customer']['reference1'] ); ?><br/>
				<?php endif; ?>
					<strong><?php esc_html_e( 'Optional reference (e.g. cost center): ', 'ledyer-checkout-for-woocommerce' ); ?> </strong> <?php echo esc_html( $ledyer_order['customer']['reference2'] ); ?><br/>
				<?php endif; ?>
					<strong><?php esc_html_e( 'Manual review: ', 'ledyer-checkout-for-woocommerce' ); ?> </strong> <span style="color:#a00;"> advised</span><br/>
				<?php endif; ?>
					<strong><?php esc_html_e( 'Risk profile: ', 'ledyer-checkout-for-woocommerce' ); ?> </strong> <?php echo esc_html( implode(', ', $ledyer_order['riskProfile']['tags']) ); ?><br/>
				<?php endif; ?>
			<?php } ?>
		</div>
		<?php
	}

	/**
	 * Prints the standard session content for the Metabox
	 *
	 * @param object $ledyer_session The Ledyer session object.
	 * @return void
	 */
	private function print_session_content( $ledyer_session ) {
		?>
		<div class="lco-meta-box-content">
			<?php if ( $ledyer_session ) { ?>
				<?php
					$environment = 'yes' === ledyer()->get_setting('testmode') ? $this->get_test_environment_name() : 'Live';
					?>
					<strong><?php esc_html_e( 'Environment: ', 'ledyer-checkout-for-woocommerce' ); ?> </strong><?php echo esc_html( $environment ); ?><br/>
				<?php } ?>
				<strong><?php esc_html_e( 'Company ID: ', 'ledyer-checkout-for-woocommerce' ); ?> </strong> <?php echo esc_html( $ledyer_session['customer']['companyId'] ); ?><br/>
					<strong><?php esc_html_e( 'Name: ', 'ledyer-checkout-for-woocommerce' ); ?> </strong> <?php echo esc_html( $ledyer_session['customer']['firstName'] . ' ' . $ledyer_session['customer']['lastName'] ); ?><br/>
				<?php endif; ?>
				<strong><?php esc_html_e( 'Payment method: ', 'ledyer-checkout-for-woocommerce' ); ?> </strong> <?php echo esc_html( $ledyer_session['customer']['paymentMethod']['type'] ); ?><br/>
					<strong><?php esc_html_e( 'Invoice copy: ', 'ledyer-checkout-for-woocommerce' ); ?> </strong> <?php echo esc_html( $ledyer_session['customer']['invoiceChannel']['details'] ); ?><br/>
				<?php endif; ?>
					<strong><?php esc_html_e( 'GLN: ', 'ledyer-checkout-for-woocommerce' ); ?> </strong> <?php echo esc_html( $ledyer_session['customer']['invoiceChannel']['details'] ); ?><br/>
				<?php endif; ?>
				<strong><?php esc_html_e( 'State: ', 'ledyer-checkout-for-woocommerce' ); ?> </strong> <?php echo esc_html( $ledyer_session['state'] ); ?><br/>
					<strong><?php esc_html_e( 'Invoice reference (e.g. order number): ', 'ledyer-checkout-for-woocommerce' ); ?> </strong> <?php echo esc_html( $ledyer_session['customer']['reference1'] ); ?><br/>
				<?php endif; ?>
					<strong><?php esc_html_e( 'Optional reference (e.g. cost center): ', 'ledyer-checkout-for-woocommerce' ); ?> </strong> <?php echo esc_html( $ledyer_session['customer']['reference2'] ); ?><br/>
				<?php endif; ?>
			<?php } ?>
		</div>
		<?php
	}

	/**
	 * Prints an error message for the Ledyer Metabox
	 *
	 * @param string $message The error message.
	 * @return void
	 */
	public function print_error_content( $message ) {
		?>
            <p><?php echo esc_html( $message ); ?></p>
        </div>
		<?php
	}
}
