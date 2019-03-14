<?php
/**
 * Filter woocommerce_ajax_get_endpoint URL - replace the path part with the correct relative home URL
 * according to the current language and append the query string
 *
 * @param string $url WC AJAX endpoint URL to filter
 * @param string $request
 *
 * @return string filtered WC AJAX endpoint URL
 */
function wpidg_woocommerce_ajax_get_endpoint( $url, $request ) {
	global $polylang;
	return parse_url( $polylang->filters_links->links->get_home_url( $polylang->curlang ), PHP_URL_PATH ) . '?' . parse_url( $url, PHP_URL_QUERY );
}
add_filter( 'woocommerce_ajax_get_endpoint', 'wpidg_woocommerce_ajax_get_endpoint', 15, 2 );