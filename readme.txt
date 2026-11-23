=== Recently Bought This for WooCommerce ===
Contributors: piotr-pesta
Tags: purchase notifications, ecommerce, storefront, hpos
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.html

Show linked product notifications for confirmed WooCommerce orders without revealing buyer details.

== Description ==

Recently Bought This displays compact purchase notifications on your WooCommerce storefront. Each notification links to the public product in your catalog and can show its image and an approximate order time.

* Choose one of four screen corners.
* Show 2 to 10 notifications, with an optional random order.
* Use the default purchase message or enter your own plain-text message.
* Set an opening delay of 0 to 30 seconds.
* Show approximate order times or leave them off, which is the default.
* Use the widget with either legacy order storage or High-Performance Order Storage (HPOS), with or without synchronization.

The dialog supports keyboard dismissal and reduced motion. Rotation pauses while a visitor interacts with it.

== Installation ==

1. Install WooCommerce 11.2 or newer on WordPress 7.0 or newer with PHP 8.2 or newer.
2. Install and activate Recently Bought This for WooCommerce, or upload the plugin ZIP under Plugins → Add Plugin.
3. Open **Recently Bought** in the WordPress admin to configure the storefront notification.

== Frequently Asked Questions ==

= Which purchases qualify? =

The plugin considers processing and completed orders. Cash on delivery can qualify before the courier collects payment. Bank transfers and cheques qualify after the store confirms them by moving the order to processing or completed. Zero-total orders can qualify without a transaction. Online gateways must record both a payment date and a transaction ID in WooCommerce. Integrations that do not populate both fields can be omitted.

= How do refunds affect notifications? =

Fully refunded orders, fully refunded first items and refunds with no reliable item allocation are excluded. A partially refunded purchase may still qualify when the displayed item has a remaining purchase.

= How is order time shown? =

The optional label uses the order creation date in the store's timezone. Labels include today, yesterday, the last 7 days and the last 30 days. Older orders use a neutral purchase message. The notification never publishes an exact order timestamp.

= Which product name is shown? =

The notification uses the current public catalog title, image and link. For variations it uses the parent product. This keeps private names entered during order-item personalization out of the notification.

= Does it support HPOS? =

Yes. WooCommerce's order API is used for legacy storage and HPOS, including HPOS without synchronization to the posts table.

= What is shown publicly and what does the cookie do? =

The widget publishes product information and, if enabled, a coarse time label. Buyer names, contact details, order IDs and exact order timestamps are excluded from the notification. Check that your public catalog titles and media are suitable for public display. A storefront-scoped cookie remembers manual dismissal for 10 minutes and automatic completion for 15 seconds.

= What happens with a full-page cache or CDN? =

A cached page can retain older products and time labels. Clear or expire the affected pages when order or product changes must appear promptly. After upgrading the original 0.4.0, purge older storefront HTML from your page cache and CDN because it may contain buyer names.

== Screenshots ==

1. Purchase notification on a WooCommerce storefront.
2. Notification settings in the WordPress admin.
3. A storefront notification showing an approximate order time.
4. A purchase notification on a mobile storefront.

== Changelog ==

= 1.0.0 =
* Support WooCommerce HPOS, with and without synchronization, and select confirmed purchases through the WooCommerce order API.
* Remove buyer details and use public catalog product names instead of personalized order item names.
* Add optional approximate order times and improve keyboard navigation, responsive layout and dismissal behavior.
* Modernize settings validation, translations and WooCommerce dependency metadata.

= 0.4.0 =
* Added a configurable opening delay.

= 0.3.0 =
* Added corner selection and a settings link.

= 0.2.5 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Requires WordPress 7.0, WooCommerce 11.2 and PHP 8.2. Purge storefront page and CDN caches after upgrading from 0.4.0 to remove HTML that could contain buyer names.
