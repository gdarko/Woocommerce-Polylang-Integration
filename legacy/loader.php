<?php
/**
 *  Only initialize the plugin if woocommerce and polylang are active!
 */
function wpidg_init() {
	$is_woocommerce_active = in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) );
	$is_polylang_active    = defined( 'POLYLANG_BASENAME' );
	if ( $is_woocommerce_active && $is_polylang_active ):
		require_once WPIDG_PATH . DIRECTORY_SEPARATOR . 'legacy' . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'helpers.php';
		require_once WPIDG_PATH . DIRECTORY_SEPARATOR . 'legacy' . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'hooks' . DIRECTORY_SEPARATOR . 'ajax.php';
		require_once WPIDG_PATH . DIRECTORY_SEPARATOR . 'legacy' . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'hooks' . DIRECTORY_SEPARATOR . 'general.php';
		require_once WPIDG_PATH . DIRECTORY_SEPARATOR . 'legacy' . DIRECTORY_SEPARATOR . 'inc' . DIRECTORY_SEPARATOR . 'hooks' . DIRECTORY_SEPARATOR . 'cart.php';
	endif;
}

add_action( 'plugins_loaded', 'wpidg_init' );