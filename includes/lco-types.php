<?php

\defined( 'ABSPATH' ) || die();

/**
 * Class containing Ledyer payment status constants.
 */
abstract class LedyerPaymentStatus {
	const ORDER_INITIATED   = 'orderInitiated';
	const ORDER_PENDING     = 'orderPending';
	const PAYMENT_PENDING   = 'paymentPending';
	const PAYMENT_CONFIRMED = 'paymentConfirmed';
	const ORDER_CAPTURED    = 'orderCaptured';
	const ORDER_REFUNDED    = 'orderRefunded';
	const ORDER_CANCELLED   = 'orderCancelled';
	const UNKNOWN           = 'unknown';
}
