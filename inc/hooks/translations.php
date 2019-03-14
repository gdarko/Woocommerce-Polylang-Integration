<?php
/**
 * Created by PhpStorm.
 * User: dg
 * Date: 10/19/2018
 * Time: 7:39 PM
 */

function wpidg_maybe_download_translation() {
	if ( ! isset( $_REQUEST['pll_action'] ) || 'add' !== esc_attr( $_REQUEST['pll_action'] ) ) {
		return false;
	}
	$name   = esc_attr( $_REQUEST['name'] );
	$locale = esc_attr( $_REQUEST['locale'] );
	if ( 'en_us' === strtolower( $locale ) ) {
		return true;
	}
	$translation_downloader = new WPIDG_Translation_Downloader( 'woocommerce', WC()->version, 'plugin' );
	try {
		return $translation_downloader->download( $locale, $name, true );
	} catch ( \RuntimeException $ex ) {
		add_settings_error( 'general', $ex->getCode(), $ex->getMessage() );

		return false;
	}
}
add_action( 'load-settings_page_mlang', 'wpidg_maybe_download_translation' );