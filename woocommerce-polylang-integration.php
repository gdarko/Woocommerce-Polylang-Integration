<?php
/**
 * Plugin Name: WooCommerce Polylang Integration
 * Plugin URI: http://darkog.com/plugins/woocommerce-polylang-integration/
 * Description: Integrates Polylang and WooCommerce. Plug and Play.
 * Version: 1.2.0
 * WC requires at least: 3.0
 * WC tested up to: 5.2
 * Author: Darko Gjorgjijoski
 * Author URI: https://darkog.com/
 * License: GPLv2
 */

// No direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPIDG_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPIDG_FILE', __FILE__ );
define( 'WPIDG_URL', plugin_dir_url( __FILE__ ) );
define( 'WPIDG_VERSION', '1.2.0' );
define( 'WPIDG_BASENAME', plugin_basename( WPIDG_PATH ) );
define( 'WPIDG_MIN_PLL_VERSION', '2.1' );

if ( ! file_exists( trailingslashit( WPIDG_PATH ) . 'vendor/autoload.php' ) ) {
	add_action( 'admin_notices', function () {
		?>
        <div class="notice notice-error is-dismissible">
            <p>
				<?php
				$message = 'You are using dev version. Please run <strong>composer install</strong> in the plugin folder in order to install all dependencies and autoloader to be able to use it.';
				_e( sprintf( '<strong>WooCommerce Polylang Integration</strong>: %s', $message ), 'woocommerce-polylang-integration' );
				?>
            </p>
        </div>
		<?php
	} );

	return;
} else {
	require_once trailingslashit( WPIDG_PATH ) . 'vendor/autoload.php';
}

/**
 * Check if it is the legacy version
 * @return mixed|void
 */
function wcpllint_is_legacy_version() {
	return apply_filters( 'wpi_force_legacy_version', false );
}

/**
 * The WooCommerce Polylang Integration instance.
 * @return \CodeVerve\WCPLL\Bootstrap|null
 */
function WCPLLINT() {
	if ( wcpllint_is_legacy_version() ) {
		require_once trailingslashit( WPIDG_PATH ) . 'legacy' . DIRECTORY_SEPARATOR . 'loader.php';

		return null;
	}
	return \CodeVerve\WCPLL\Bootstrap::get_instance();
}

WCPLLINT();
