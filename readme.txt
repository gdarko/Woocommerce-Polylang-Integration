=== Woocommerce Polylang Integration ===
Contributors: DarkoG
Tags: woocommerce, polylang, multilingual, translate, i18n
Requires at least: 4.2
Tested up to: 5.7
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Stable Tag: 1.2.2

Easily integrate your Woocommerce store with polylang.

== Description ==

Simple plugin that integrates WooCommerce with Polylang to add multiple languages on your WooCommerce based store.

Current progress:

- [√] Pages
- [√] Account Endpoints
  - [√] My account
  - [√] Orders
- [√] Products
  - [√] Variations
  - [√] Categories
  - [√] Tags
  - [√] Attributes
  - [√] Shipping Classes
  - [√] Price syncrhonization
  - [√] Featured images and Galleries
- [√] Orders
- [√] Stock Sync
- [√] Cart Sync
- [√] Coupon Sync
- [√] Emails
- [√] Reports
- [√] Strings (Gateway name, shipping name, etc)

New PRO version is coming soon. Initial features will be:

- [√] WooCommerce Subscriptions
- [√] WooCommerce Bookings
- [√] WooCommerce Composite Products
- [√] WooCommerce Product Bundles
- [√] WooCommerce Follow Ups
- [√] <a href="https://darkog.com/contact/?subject=Suggestion%20for%20WooCommerce%20Polylang%20Integration">Suggest your own</a>

== Installation ==

1. Install Polylang and WooCommerce
2. Install WooCommerce Polylang Integration
3. Check WooCommerce > Status > "WooCommerce Polylang Integration" box to see if anything is wrong.

Normally you will need to translate the "My Account", "Cart", "Checkout" pages and your Products in all the languages.

Optionally you can translate strings like payment gateway name, and many more in Languages > String Translations

That's all you need to do. The plugin is plug and play and you don't have to configure anything other then the things above.

== Frequently Asked Questions ==

= Can i use this plugin with other e-commerce plugins? =

No, this plugin is intended to work with WooCommerce only.

= Does this work with WPML plugin? =

No. This plugin only supports Polylang and you can only use one at a time.

= Do i need to change any settings after activation? =

No. The plugin is simply plug and play, except that you need to translate the My Account, Cart, Checkout pages and your products. Make sure you check WooCommerce > Status > WooCommerce Polylang Integration to verify if you are missing s something.

= How to translate Payment Gateway Name, Shipping method name and other text strings?

Navigate to "Languages" > "String Translations" and you will see all the WooCommerce related strings in the "WooCommerce" group.

= The plugin doesn't work on my site! =

If the plugin doesn't work on your site, please check WooCommerce > Status > WooCommerce Polylang Integration and see if you are missing a page for specific language. Please note that the pages listed here must be created in all languages and properly connected.


== Changelog ==

= Version 1.2.2 =
* Fix after-order redirect endpoint. Always load the correct language
* Fix cart persistence when switching between languages
* Fix a warning triggered by the hook that translates the breadcrumbs

= Version 1.2.1 =
* Fix shop page pagination

= Version 1.2.0 =
* Major rewrite
* Added complete support for products variable products, cart, checkout, emails, my account endpoints, order view endpoints, orders, front-pages, etc.

= Version 1.0.3 =
* Compatibility: WordPress 5.1+
* Bugfix: Missing woocommerce_add_to_cart_handler function

= Version 1.0.2 =
* Fixed shop page

= Version 1.0.1 =
* Added missing files

= Version 1.0.0 =
* Major version that fixes most of the bugs

= Version 0.1.1 =
* Some Minor Changes

= Version 0.1.0 =
* Everything is new