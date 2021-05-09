<?php

/**
 * Translate the WooCommerce page ids.
 *
 * @param $ID
 *
 * @return false|int|null
 */
function wpidg_translated_page_id( $ID ) {
	return wpidg_get_translated_page_id( $ID );
}

foreach ( array( 'cart', 'checkout', 'myaccount', 'shop', 'terms_and_conditions', 'terms' ) as $slug ) {
	add_filter( 'woocommerce_get_' . $slug . '_page_id', 'wpidg_translated_page_id', 25, 1 );
	add_filter( 'option_woocommerce_' . $slug . '_page_id', 'wpidg_translated_page_id' );
}
foreach ( array( 'privacy_policy' ) as $slug ) {
	add_filter( 'wc_' . $slug . '_page_id', 'wpidg_translated_page_id', 25, 1 );
}

/**
 * Get the translated checkout url.
 *
 * @param $checkout_url
 *
 * @return mixed
 */
function wpidg_woocommerce_get_checkout_url( $checkout_url ) {
	$checkout_page_id = wc_get_page_id( 'checkout' );

	if ( $checkout_page_id ) {
		$checkout_url = get_the_permalink( $checkout_page_id );
	}

	return $checkout_url;
}
add_filter( 'woocommerce_get_checkout_url', 'wpidg_woocommerce_get_checkout_url', 15, 1 );

/**
 * Returns the correct archive url depending of the language selected
 *
 * @param $url
 * @param $lang
 *
 * @return mixed
 */
function wpidg_pll_get_archive_url( $url, $lang ) {
	if ( ! is_post_type_archive( 'product' ) ) {
		return $url;
	}
	$shop_page = get_post( get_option( 'woocommerce_shop_page_id' ) );
	if ( $shop_page ) {
		$shop_page_translation = get_post( pll_get_post( $shop_page->ID, $lang ) );

		if ( $shop_page_translation ) {
			return str_replace( $shop_page->post_name, $shop_page_translation->post_name, $url );
		}
	}

	return $url;
}
add_filter( 'pll_get_archive_url', 'wpidg_pll_get_archive_url', 15, 2 );

/**
 * @param WP $wp
 */
function wpidg_parse_request( \WP $wp ) {

	// Bail if admin
	if ( is_admin() ) {
		return;
	}
	$shop_page_id     = wc_get_page_id( 'shop' );
	$is_shop_on_front = ( 'page' === get_option( 'show_on_front' ) ) && in_array( get_option( 'page_on_front' ), PLL()->model->post->get_translations( $shop_page_id ) );
	foreach ( array( 'pagename', 'page', 'name' ) as $var ) {
		if ( isset( $wp->query_vars[ $var ] ) ) {
			$is_shop_on_front = false;
			break;
		}
	}
	if ( ! $is_shop_on_front ) {
		if ( ! empty( $wp->query_vars['pagename'] ) ) {
			$shop_page = get_post( $shop_page_id );
			$page      = explode( '/', $wp->query_vars['pagename'] );
			if ( isset( $shop_page->post_name ) && $shop_page->post_name == $page[ count( $page ) - 1 ] ) {
				unset( $wp->query_vars['page'] );
				unset( $wp->query_vars['pagename'] );
				$wp->query_vars['post_type'] = 'product';
			}
		}
	}
}
add_action( 'parse_request', 'wpidg_parse_request', 10, 1 );

/**
 * If ids are passed to the WC product query when using shortcode to display the products
 * find the translated product ids instead.
 *
 * @param array $query_args
 * @param array $atts
 *
 * @return array
 */
function wpidg_woocommerce_shortcode_products_query( $query_args, $atts ) {
	if ( isset( $atts['ids'] ) && strlen( $atts['ids'] ) ) {
		$ids            = explode( ',', $atts['ids'] );
		$translated_ids = array();
		foreach ( $ids as $id ) {
			$translated_id = pll_get_post( $id );
			if ( is_numeric( $translated_id ) ) {
				array_push( $translated_ids, $translated_id );
			}
		}
		$atts['ids']            = implode( $translated_ids, ',' );
		$query_args['post__in'] = $translated_ids;
	} else {
		$query_args['lang'] = isset( $query_args['lang'] ) ? $query_args['lang'] : pll_current_language();
	}

	return $query_args;
}
add_filter( 'woocommerce_shortcode_products_query', 'wpidg_woocommerce_shortcode_products_query', 10, 2 );

/**
 * Redirect to correct page
 *
 * @param string $to redirect url
 *
 * @return string redirect url
 */
function wpidg_woocommerce_login_redirect( $to ) {
	$ID      = url_to_postid( $to );
	$page_ID = wpidg_get_translated_page_id( $ID );
	if ( $page_ID ) {
		return get_permalink( $page_ID );
	}
	return $to;
}
add_filter( 'woocommerce_login_redirect', 'wpidg_woocommerce_login_redirect', 10, 2 );