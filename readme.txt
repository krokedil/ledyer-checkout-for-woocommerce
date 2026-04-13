=== Ledyer Checkout for WooCommerce ===
Contributors: ledyerdevelopment
Tags: woocommerce, ledyer, ecommerce, e-commerce, checkout
Donate link: https://ledyer.com
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
WC requires at least: 5.6.0
WC tested up to: 10.6.2
Stable tag: 1.12.2
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

== Changelog ==
= 2026.01.28    - version 1.12.2 =
* Fix           - Resolved an issue where orders created via the redirect flow were not correctly updated to the "processing" status.

= 2025.10.13    - version 1.12.1 =
* Fix           - Fixes an issue that would cause Advance invoices to not leave the on-hold status when confirmed by Ledyer.
* Fix           - Fixed a fatal error that could happen on the thankyou page when using SCP 300 as the security level with Ledyer.

= 2025.10.09    - version 1.12.0 =
* Feature       - The plugin will now send callback urls to Ledyer when creating the order, this will remove the need for these to be set in the Ledyer portal going forward.
* Feature       - Added support for the Pay for order/redirect checkout flow.
* Feature       - The plugin will now send callback urls to Ledyer when creating the order, this will remove the need for these to be set in the Ledyer portal going forward.
* Enhancement   - Improved the logging in the plugin to make debugging easier. Each request has their own title, and the response is logged properly.
* Enhancement   - The Authentication header and access token is now removed from the logs to improve security.
* Tweak         - Orders that do not require processing are now set to on-hold until we receive the ready_for_capture event from Ledyer. This prevents orders from being set to Completed in WooCommerce before Ledyer has processed the payment.
* Fix           - The order metabox is now compatible with HPOS.
* Fix           - Resolved merchant reference not being set properly for virtual orders.
* Fix           - Resolved issue where the wrong order was processed by notification callbacks.
* Fix           - Removed declaration of support for subscriptions, since it has not been implemented yet.
* Fix           - Addressed various deprecation warnings from PHP 8.2 and 8.3.

= 2025.03.27    - version 1.11.2 =
* Chore         - Trigger a new release

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
