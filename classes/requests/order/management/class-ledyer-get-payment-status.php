<?php
/**
 * Get Order Request
 *
 * @package Ledyer\Requests\Order
 */
namespace Ledyer\Requests\Order\Management;

use Ledyer\Requests\Order\Request_Order;

defined( 'ABSPATH' ) || exit();

/**
 * Class Get_Payment_Status
 *
 * @package Ledyer\Requests\Order\Management
 */
class Get_Payment_Status extends Request_Order {
	/**
	 * Request method
	 *
	 * @var string
	 */

	protected $method = 'GET';
	/**
	 * Set entrypoint
	 */
	protected function set_url() {
		$this->url = sprintf( 'v1/orders/%s/paymentstatus', $this->arguments['orderId'] );

		parent::get_request_url();
	}
}
