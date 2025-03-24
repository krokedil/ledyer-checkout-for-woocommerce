=== Ledyer Checkout for WooCommerce ===
Contributors: ledyerdevelopment
Tags: woocommerce, ledyer, ecommerce, e-commerce, checkout
Donate link: https://ledyer.com
Requires at least: 5.0
Tested up to: 6.6.2
Requires PHP: 7.4
WC requires at least: 5.6.0
WC tested up to: 9.3.3
Stable tag: 1.11.0
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

== Changelog ==
= 2025.03.10    - version 1.11.1 =
* Fix           - Added fallbacks to ensure the locale sent from WooCommerce always follows the BCP47 standard, addressing inconsistencies caused by certain plugins.

= 2025.01.30    - version 1.11.0 =
* Feature       - Setting for card when autocapture is activated

= 2025.01.30    - version 1.10.2 =
* Fix           - Edge case where notification is targeting wrong order

= 2025.01.03    - version 1.10.1 =
* Fix           - Improve accuracy of status update when order is captured.

= 2025.01.02    - version 1.10.0 =
* Feature       - Added possibility to change the checkout layout to horizontal, making the checkout appear next to shipping.
* Fix           - Correct the default padding option, it was doing the opposite of what it said.

= 2024.10.22    - version 1.9.0 =
* Feature       - Added support for order pay (pay for order). The customer will be redirected to Ledyer's hosted payment page to finalize their purchase.

= 2024.10.01    - version 1.8.1 =
* Fix           - Update deprecated sanitization.
* Fix           - Confirm that the current Ledyer payment id is set as transaction id in WooCommerce after purchase.
