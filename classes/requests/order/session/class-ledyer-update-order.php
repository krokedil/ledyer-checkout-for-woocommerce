<?php
/**
 * Update Order Request
 *
 * @package Ledyer\Requests\Order\Session
 */
namespace Ledyer\Requests\Order\Session;

use Ledyer\Requests\Order\Request_Order;

defined( 'ABSPATH' ) || exit();

/**
 * Class Update_Order
 *
 * @package Ledyer\Requests\Order\Session
 */
class Update_Order extends Request_Order {
	/*
	 * Set entrypoint
	 */
	protected $method = 'POST';
	/*
	 * Request method
	 */
	protected function set_url() {
		$this->url = sprintf( 'v1/sessions/%s', $this->arguments['orderId'] );

		parent::get_request_url();
	}

	/**
	 * Create request args.
	 *
	 * @return array
	 */
	protected function get_request_args() {
		$request_args = parent::get_request_args();

		$body = json_decode( $request_args['body'], true );
		if ( ! empty( $body ) ) {
			// Remove the settings.urls from the body to avoid 400 error.
			if ( isset( $body['settings']['urls'] ) ) {
				unset( $body['settings']['urls'] );
			}

			$request_args['body'] = wp_json_encode( $body );
		}

		return $request_args;
	}
}
