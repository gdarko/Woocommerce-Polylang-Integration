<?php
/**
 * Plugin Name: WooCommerce Polylang Integration
 * Plugin URI: http://darkog.com/plugins/woocommerce-polylang-integration/
 * Description: Integrates Polylang with Woocommerce, checkout, cart, product pages and links are translated
 * Version: 1.0.3
 * WC requires at least: 3.0.0
 * WC tested up to: 3.5.6
 * Author: Darko Gjorgjijoski
 * Author URI: https://darkog.com/
 * License: GPLv2
 */

// No direct access
defined( 'ABSPATH' ) or die( "No script kiddies please!" );

define( 'WPIDG_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPIDG_VERSION', '1.0.3' );
define( 'WPIDG_URL', plugin_dir_url( __FILE__ ) );

/**
 *  Only initialize the plugin if woocommerce and polylang are active!
 */
function wpidg_init() {
	$is_woocommerce_active = in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) );
	$is_polylang_active    = defined('POLYLANG_BASENAME');
	if ( $is_woocommerce_active && $is_polylang_active ):
		require_once WPIDG_PATH . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'helpers.php';
		require_once WPIDG_PATH . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . 'TranslationDownloader.php';
		require_once WPIDG_PATH . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'hooks' . DIRECTORY_SEPARATOR . 'ajax.php';
		require_once WPIDG_PATH . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'hooks' . DIRECTORY_SEPARATOR . 'general.php';
		require_once WPIDG_PATH . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'hooks' . DIRECTORY_SEPARATOR . 'cart.php';
		require_once WPIDG_PATH . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'hooks' . DIRECTORY_SEPARATOR . 'translations.php';
	endif;
}
add_action( 'plugins_loaded', 'wpidg_init' );