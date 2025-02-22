<?php
/**
 * Trait Singleton
 *
 * @package Ledyer;
 * @since 1.0.0
 */
namespace Ledyer;

\defined( 'ABSPATH' ) || die();

/**
 * Trait Singleton
 *
 * Creates Singleton class
 */
trait Singleton {

	/**
	 * Instance of the singleton class
	 *
	 * @var self
	 */
	private static $instance = null;

	/**
	 * Settings array for the singleton class
	 *
	 * @var array
	 */
	private static $settings = array();

	/**
	 * Instantiate the class.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Construct the class
	 *
	 * @return void
	 */
	private function __construct() {
		$this->set_settings();
		$this->actions();
		$this->filters();
	}

	/**
	 * Different add_actions is added here
	 *
	 * @return void
	 */
	public function actions(): void {
	}

	/**
	 * Different add_filters is added here
	 *
	 * @return void
	 */
	public function filters(): void {
	}
	/**
	 * Set settings
	 *
	 * @return void
	 */
	public function set_settings(): void {
	}
}
