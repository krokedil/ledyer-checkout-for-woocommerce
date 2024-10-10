<?php
/**
 * Abstract Request
 *
 * @package Ledyer\Requests
 */
namespace Ledyer\Requests;

use Ledyer\Credentials;
use Ledyer\Logger;

defined( 'ABSPATH' ) || exit();

/**
 * Class Request
 *
 * @package Ledyer\Requests
 */
abstract class Request {

	/**
	 * Request arguments
	 *
	 * @var array|mixed
	 */
	protected $arguments;
	/**
	 * Ledyer settings
	 *
	 * @var array
	 */
	protected $settings;
	/**
	 * Request method
	 *
	 * @var string
	 */
	protected $method = 'POST';
	/**
	 * Request endpoint
	 *
	 * @var string
	 */
	protected $url = '';
	/**
	 * Merchant Bearer token
	 *
	 * @var string
	 */
	private $access_token;
	/**
	 * Request entrypoint
	 *
	 * @var string
	 */
	protected $request_url;
	/*
	 * Requests Class constructor.
	 */
	public function __construct( $arguments = array() ) {
		$this->arguments    = $arguments;
		$this->access_token = $this->token();
		$this->set_request_url();
	}

	/**
	 * Sets request endpoint
	 *
	 * @return mixed
	 */
	abstract protected function set_request_url();

	/**
	 * Save merchant's bearer token in transient.
	 * Transient 'ledyer_token' expires in 3600s.
	 *
	 * @return mixed|string
	 */
	private function token() {
		$token_name = $this->is_test() ? 'test_ledyer_token' : 'ledyer_token';

		if ( get_transient( $token_name ) ) {
			return get_transient( $token_name );
		}

		$client_credentials = ledyer()->credentials->get_credentials_from_session();

		$api_auth_base = 'https://auth.live.ledyer.com/';

		if ( $this->is_test() ) {
			switch ( ledyer()->get_setting( 'development_test_environment' ) ) {
				case 'local':
					$api_auth_base = 'http://host.docker.internal:9001/';
					break;
				case 'development':
				case 'local-fe':
					$api_auth_base = 'https://auth.dev.ledyer.com/';
					break;
				default:
					$api_auth_base = 'https://auth.sandbox.ledyer.com/';
					break;
			}
		}

		$client = new \WP_Http();

		$headers = array(
			'Authorization' => 'Basic ' . base64_encode( $client_credentials['merchant_id'] . ':' . $client_credentials['shared_secret'] ),
		);

		$response = $client->post(
			$api_auth_base . 'oauth/token?grant_type=client_credentials',
			array(
				'headers' => $headers,
				'timeout' => 60,
			)
		);

		$body = $this->process_response( $response, array( 'grant_type' => 'client_credentials' ), $api_auth_base . 'oauth/token' );

		$is_wp_error = is_object( $body ) && false !== stripos( get_class( $body ), 'WP_Error' );

		if ( ! $is_wp_error && isset( $body['access_token'] ) ) {
			set_transient( $token_name, $body['access_token'], $body['expires_in'] );
			return get_transient( $token_name );
		}

		return '';
	}

	/**
	 * Make request.
	 *
	 * @return mixed|\WP_Error
	 */
	public function request() {
		$url  = $this->get_request_url();
		$args = $this->get_request_args();

		$response = wp_remote_request( $url, $args );

		return $this->process_response( $response, $args, $url );
	}

	/**
	 * Create request url.
	 *
	 * @return string
	 */
	protected function get_request_url() {
		$base = $this->request_url;
		$slug = trim( $this->url, '/' );

		return $base . $slug;
	}

	/**
	 * Create request args.
	 *
	 * @return array
	 */
	protected function get_request_args() {
		$request_args = array(
			'headers' => $this->get_request_headers(),
			'method'  => $this->method,
			'timeout' => apply_filters( 'ledyer_request_timeout', 10 ),
		);

		if ( 'POST' === $this->method && $this->arguments['data'] ) {
			$request_args['body'] = json_encode( $this->arguments['data'] );
		}

		return $request_args;
	}

	/**
	 * Create request headers.
	 *
	 * @return array
	 */
	protected function get_request_headers() {
		return array(
			'Authorization' => sprintf( 'Bearer %s', $this->token() ),
			'Content-Type'  => 'application/json',
		);
	}

	/**
	 * Check if test env is enabled.
	 *
	 * @return bool
	 */
	protected function is_test() {
		return 'yes' === ledyer()->get_setting( 'testmode' );
	}

	/**
	 * Process response. Return response body or error.
	 * Log errors.
	 *
	 * @param $response
	 * @param $request_args
	 * @param $request_url
	 *
	 * @return mixed|\WP_Error
	 */
	protected function process_response( $response, $request_args, $request_url ) {
		$code = wp_remote_retrieve_response_code( $response );

		$log = Logger::format_log( '', 'POST', 'Debugger', $request_args, json_decode( wp_remote_retrieve_body( $response ), true ), $code );

		Logger::log( $log );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		if ( $response_code < 200 || $response_code > 299 ) {
			$data          = 'URL: ' . $request_url . ' - ' . wp_json_encode( $request_args );
			$error_message = '';
			$errors        = json_decode( $response['body'], true );

			if ( ! empty( $errors ) && ! empty( $errors['errors'] ) ) {
				foreach ( $errors['errors'] as $error ) {
					$error_message .= ' ' . $error['message'];
				}
			}
			$return = new \WP_Error( $response_code, $error_message, $data );
		} else {
			$return = json_decode( wp_remote_retrieve_body( $response ), true );
		}

		return $return;
	}
}
