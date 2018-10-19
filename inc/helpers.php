<?php

/**
 * Returns the  translated page id.
 *
 * @param $ID
 *
 * @return false|int|null
 */
function wpidg_get_translated_page_id( $ID ) {
	if ( ! function_exists( 'pll_get_post' ) ) {
		return $ID;
	}

	$page_ID = pll_get_post( $ID );

	if ( $page_ID ) {
		return $page_ID;
	}

	return $ID;
}

/**
 * Returns associative array with the language/translations eg. array('MK' => 121, 'FR' => 129)
 *
 * @param $ID
 * @param bool $exclude_default
 *
 * @return mixed
 */
function wpidg_get_product_translations( $ID, $exclude_default = false ) {
	$IDS = PLL()->model->post->get_translations( $ID );
	if ( true === $exclude_default ) {
		unset( $IDS[ pll_default_language() ] );
	}

	return $IDS;
}


/**
 * Returns the product object of the current language
 *
 * @param WC_Product|int $product
 * @param string $slug
 *
 * @return false|null|WC_Product
 */
function wpidg_get_product( $product, $slug = '' ) {

	if ( is_numeric( $product ) ) {
		$product = wc_get_product( $product );
		if ( ! $product ) {
			return false;
		}
	}
	$product_id_translated = pll_get_post( $product->get_id(), $slug );

	if ( $product_id_translated ) {
		$product_translated = wc_get_product( $product_id_translated );

		if ( ! $product_translated ) {
			return $product;
		} else {
			return $product_translated;
		}
	}

	return $product;
}


/**
 * Returns the product parent id
 *
 * @param WC_Product variation
 *
 * @return integer id of variation parent post
 */
function wpidg_get_product_parent_id( WC_Product $product ) {
	if ( $product ) {
		return $product->get_parent_id();
	} else {
		return null;
	}
}

/**
 * Get product variation translation.
 *
 * Returns the product variation object for a given language.
 *
 * @param int $variation_id (required) Id of the variation to translate
 * @param string $lang (optional) 2-letters code of the language
 *                                  like Polylang
 *                                  language slugs, defaults to current language
 *
 * @return WC_Product_Variation|bool    Product variation object for the given
 *                                  language, false on error or if doesn't exists.
 */
function wpidg_get_variation( $variation_id, $lang = '' ) {
	$variation   = wc_get_product( $variation_id );
	$parent_id   = wpidg_get_product_parent_id( $variation );
	$_product_id = pll_get_post( $parent_id, $lang );
	$meta        = get_post_meta( $variation_id, '_point_to_variation', true );
	if ( $_product_id && $meta ) {
		$variation_post = get_posts( array(
			'meta_key'    => '_point_to_variation',
			'meta_value'  => $meta,
			'post_type'   => 'product_variation',
			'post_parent' => $_product_id
		) );

		if ( $variation_post && count( $variation_post ) == 1 ) {
			// return the variation translation
			return wc_get_product( $variation_post[0]->ID );
		}
	}

	return false;
}