<?php
/**
 * Get Order Request
 *
 * @package Ledyer\Requests\Order\Session
 */
namespace Ledyer\Requests\Order\Session;

use Ledyer\Requests\Order\Request_Order;

defined( 'ABSPATH' ) || exit();

/**
 * Class Get_Order
 *
 * @package Ledyer\Requests\Order\Session
 */
class Get_Order extends Request_Order {
	/**
	 * Request method
	 *
	 * @var string
	 */
	protected $method = 'GET';
	/**
	 * Set entrypoint
	 */
	protected function set_url(): void {
		$this->url = sprintf( 'v1/sessions/%s', $this->arguments['orderId'] );

		parent::get_request_url();
	}
}
