<?php
/**
 * Plugin Name: WooCommerce Polylang Integration
 * Plugin URI: http://darkog.com/plugins/woocommerce-polylang/
 * Description: Integrates Polylang with Woocommerce, checkout, cart, product pages and links are translated
 * Version: 0.2
 * Author: Darko Gjorgjijoski
 * Author URI: http://darkog.com/
 * License: GPLv2
 */

// No direct access
defined('ABSPATH') or die("No script kiddies please!");

// Only initialize the plugin if woocommerce is active!
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) 
{
	if (function_exists('pll_get_post')) // is Polylang activated?
	{ 
	        add_filter('woocommerce_get_checkout_url', 'pll_woocommerce_get_page_id');
	        add_filter('woocommerce_get_checkout_page_id', 'pll_woocommerce_get_page_id');
	        add_filter('woocommerce_get_cart_page_id', 'pll_woocommerce_get_page_id');
	        add_filter('woocommerce_get_myaccount_page_id', 'pll_woocommerce_get_page_id');
	        add_filter('woocommerce_get_edit_address_page_id', 'pll_woocommerce_get_page_id');
	        add_filter('woocommerce_get_view_order_page_id', 'pll_woocommerce_get_page_id');
	        add_filter('woocommerce_get_change_password_page_id', 'pll_woocommerce_get_page_id');
	        add_filter('woocommerce_get_thanks_page_id', 'pll_woocommerce_get_page_id');
	        add_filter('woocommerce_get_shop_page_id', 'pll_woocommerce_get_page_id');
	        add_filter('woocommerce_get_terms_page_id', 'pll_woocommerce_get_page_id');
	        add_filter('woocommerce_get_pay_page_id', 'pll_woocommerce_get_page_id');

		function pll_woocommerce_get_page_id($id) 
		{
			return pll_get_post($id); // translate the page to current language
		}
	}
}

?>
