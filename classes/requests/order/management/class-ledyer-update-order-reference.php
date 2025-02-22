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
 * Class Get_Order
 *
 * @package Ledyer\Requests\Order\Management
 */
class Update_Order_Reference extends Request_Order {
	/**
	 * Request method
	 *
	 * @var string
	 */
	protected $method = 'POST';

	/**
	 * Set entrypoint
	 */
	protected function set_url() {
		$this->url = sprintf( 'v1/orders/%s/reference', $this->arguments['orderId'] );

		parent::get_request_url();
	}
}
