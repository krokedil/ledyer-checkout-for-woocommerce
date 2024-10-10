<?php
/**
 * File for HPP class.
 *
 * @package Ledyer
 */

namespace Ledyer;

defined( 'ABSPATH' ) || exit();

/**
 * Class HPP
 *
 * @package Ledyer
 */
class HPP {
	/**
	 * Creates the HPP URL.
	 *
	 * @param string $session_id The Ledyer Checkout session to use for the HPP request.
	 * @return string The HPP URL.
	 */
	public function create_hpp_url( $session_id ) {
		$mode = 'yes' === ledyer()->get_setting( 'testmode' ) ? 'sandbox' : 'live';
		return "https://pos.{$mode}.ledyer.com/?sessionId={$session_id}";
	}
}
