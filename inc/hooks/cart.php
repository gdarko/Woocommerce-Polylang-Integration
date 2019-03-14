<?php

/**
 * Make sure that no duplicates from the other languages are in the cart.
 *
 * @param $ID
 *
 * @return mixed
 */
function wpidg_woocommerce_add_to_cart_product_id( $ID ) {
	$translations = wpidg_get_product_translations( $ID );
	foreach ( WC()->cart->get_cart() as $values ) {
		$product = $values['data'];
		/* @var WC_Product $product */
		if ( in_array( $product->get_id(), $translations ) ) {
			return $product->get_id();
		}
	}
	return $ID;
}
add_filter( 'woocommerce_add_to_cart_product_id', 'wpidg_woocommerce_add_to_cart_product_id', 15, 1 );


/**
 * Translate cart contents
 *
 * @param \WC_Product|\WC_Product_Variation $cart_item_data
 * @param $cart_item
 *
 * @return WC_Product|WC_Product_Variation
 */
function wpidg_woocommerce_cart_item_product( $cart_item_data, $cart_item ) {

	$cart_product_id   = isset( $cart_item['product_id'] ) ? $cart_item['product_id'] : 0;
	$cart_variation_id = isset( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : 0;

	$type = $cart_item_data->get_type();
	if ( 'simple' === $type ) {
		$product_translation = wpidg_get_product( $cart_product_id );
		if ( $product_translation ) {
			$cart_item_data = $product_translation;
		}
	} else if ( 'variation' === $type ) {
		$variation_translation = wpidg_get_variation( $cart_variation_id );
		if ( $variation_translation ) {
			$cart_item_data = $variation_translation;
		}
	}

	return $cart_item_data;
}
add_filter( 'woocommerce_cart_item_product', 'wpidg_woocommerce_cart_item_product', 15, 2 );

/**
 * Return the correct cart item product id
 *
 * @param $cart_product_id
 *
 * @return false|int|null
 */
function wpidg_woocommerce_cart_item_product_id( $cart_product_id ) {
	$translation_id = pll_get_post( $cart_product_id );
	if ( $translation_id ) {
		$cart_product_id = $translation_id;
	}

	return $cart_product_id;
}
add_filter( 'woocommerce_cart_item_product_id', 'wpidg_woocommerce_cart_item_product_id', 15, 1 );

/**
 * Return the correct cart item permalink
 *
 * @param $item_permalink
 * @param $cart_item
 *
 * @return string
 */
function wpidg_woocommerce_cart_item_permalink( $item_permalink, $cart_item ) {
	$cart_variation_id = isset( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : 0;
	if ( $cart_variation_id !== 0 ) {
		$variation_translation = wpidg_get_variation( $cart_variation_id );

		return $variation_translation ? $variation_translation->get_permalink() : $item_permalink;
	}

	return $item_permalink;
}
add_filter( 'woocommerce_cart_item_permalink', 'wpidg_woocommerce_cart_item_permalink', 15, 2 );


/**
 * Translate variation items
 *
 * NOTE: We don't translate the variation attributes if the product in the cart
 * is not a product variation, and in case of a product variation, it
 * doesn't have a translation in the current language.
 *
 * @param $item_data
 * @param $cart_item
 *
 * @return array
 */
function wpidg_woocommerce_get_item_data( $item_data, $cart_item ) {
	$cart_variation_id = isset( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : 0;
	if ( $cart_variation_id == 0 ) {
		// Not a variation product
		return $item_data;
	} elseif ( $cart_variation_id != 0 && false == wpidg_get_variation( $cart_variation_id ) ) {
		// Variation product without translation in current language
		return $item_data;
	}
	$item_data_translation = array();
	foreach ( $item_data as $data ) {
		$term_id = null;
		foreach ( $cart_item['variation'] as $tax => $term_slug ) {
			$tax  = str_replace( 'attribute_', '', $tax );
			$term = get_term_by( 'slug', $term_slug, $tax );
			if ( $term && $term->name === $data['value'] ) {
				$term_id = $term->term_id;
				break;
			}
		}
		if ( $term_id !== 0 && $term_id !== null ) {
			// Product attribute is a taxonomy term - check if Polylang has a translation
			$term_id_translation = pll_get_term( $term_id );
			if ( $term_id_translation == $term_id ) {
				// Already showing the attribute (term) in the correct language
				array_push( $item_data_translation, $data );
			} else {
				// Get term translation from id
				$term_translation = get_term( $term_id_translation );
				$error            = get_class( $term_translation ) == 'WP_Error';
				array_push( $item_data_translation, array(
					'key'   => $data['key'],
					'value' => ! $error ? $term_translation->name : $data['value']
				) );
			}
		} else {
			// Product attribute is post metadata and not translatable - return same
			array_push( $item_data_translation, $data );
		}
	}

	return ! empty( $item_data_translation ) ? $item_data_translation : $item_data;
}
add_filter( 'woocommerce_get_item_data', 'wpidg_woocommerce_get_item_data', 15, 2 );


/**
 * Replace woo fragments script with own customized version to make possible updating cart widget
 * when language is switched
 */
function wpidg_enqueue_scripts() {
	wp_deregister_script( 'wc-cart-fragments' );
	$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
	$file_name   = 'cart-fragments' . $suffix . '.js';
	wp_enqueue_script( 'wc-cart-fragments', WPIDG_URL . 'assets/js/'. $file_name, array(
		'jquery',
		'jquery-cookie'
	), WPIDG_VERSION, true );
}
add_action( 'wp_enqueue_scripts', 'wpidg_enqueue_scripts', 25, 0 );


/**
 * Replace the default WooCommerce handler for variable products.
 *
 * @param $product_type
 * @param $product
 *
 * @return string
 */
function wpidg_woocommerce_add_to_cart_handler( $product_type, $product ) {
	return 'variable' === $product_type ? 'wpidg_variable' : $product_type;
}
add_filter( 'woocommerce_add_to_cart_handler', 'wpidg_woocommerce_add_to_cart_handler', 10, 2 );

/**
 * Custom add to cart handler for variable products
 * Based on function add_to_cart_handler_variable( $product_id ) from woocommerce/includes/class-wc-form-handler.php
 * but using $url as argument. Therefore we use the initial bits from add_to_cart_action( $url ).
 *
 * @param $url
 *
 * @throws Exception
 */
function wpidg_woocommerce_add_to_cart_handler_wpidg_variable( $url ) {
	// From add_to_cart_action( $url )
	if ( empty( $_REQUEST['add-to-cart'] ) || ! is_numeric( $_REQUEST['add-to-cart'] ) ) {
		return;
	}

	$product_id        = apply_filters( 'woocommerce_add_to_cart_product_id', absint( $_REQUEST['add-to-cart'] ) );
	$was_added_to_cart = false;
	$adding_to_cart    = wc_get_product( $product_id );

	if ( ! $adding_to_cart ) {
		return;
	}
	// End: From add_to_cart_action( $url )

	// From add_to_cart_handler_variable( $product_id )
	$variation_id       = empty( $_REQUEST['variation_id'] ) ? '' : absint( $_REQUEST['variation_id'] );
	$quantity           = empty( $_REQUEST['quantity'] ) ? 1 : wc_stock_amount( $_REQUEST['quantity'] );
	$missing_attributes = array();
	$variations         = array();
	$attributes         = $adding_to_cart->get_attributes();

	// If no variation ID is set, attempt to get a variation ID from posted attributes.
	if ( empty( $variation_id ) ) {
		/* @var WC_Product_Data_Store_CPT $data_store */
		$data_store   = WC_Data_Store::load( 'product' );
		$variation_id = $data_store->find_matching_product_variation( $adding_to_cart, wp_unslash( $_POST ) );
	}
	/**
	 * Custom code to check if a translation of the product is already in the
	 * cart,* and in that case, replace the variation being added to the cart
	 * by the respective translation in the language of the product already
	 * in the cart.
	 * NOTE: The product_id is filtered by $this->add_to_cart() and holds the
	 * id of the product translation, if one exists in the cart.
	 */
	if ( $product_id != absint( $_REQUEST['add-to-cart'] ) ) {
		// There is a translation of the product already in the cart:
		// Get the language of the product in the cart
		$lang = pll_get_post_language( $product_id );

		// Get the respective variation in the language of the product in the cart
		$variation    = wpidg_get_variation( $variation_id, $lang );
		$variation_id = $variation->get_id();
	} else {
		$variation = wc_get_product( $variation_id );
		$lang      = '';
	}
	/**
	 * End of custom code.
	 */

	// Validate the attributes.
	try {
		if ( empty( $variation_id ) ) {
			return;
			//throw new \Exception( __( 'Please choose product options&hellip;', 'woocommerce' ) );
		}
		foreach ( $attributes as $attribute ) {
			if ( ! $attribute['is_variation'] ) {
				continue;
			}

			$taxonomy = 'attribute_' . sanitize_title( $attribute['name'] );

			if ( isset( $_REQUEST[ $taxonomy ] ) ) {
				// Get value from post data
				if ( $attribute['is_taxonomy'] ) {
					// Don't use wc_clean as it destroys sanitized characters
					$value = sanitize_title( stripslashes( $_REQUEST[ $taxonomy ] ) );

					/**
					 * Custom code to check if a translation of the product is already in the cart,
					 * and in that case, replace the variation attribute being added to the cart by
					 * the respective translation in the language of the product already in the cart
					 * NOTE: The product_id is filtered by $this->add_to_cart() and holds the id of
					 * the product translation, if one exists in the cart.
					 */
					if ( $product_id != absint( $_REQUEST['add-to-cart'] ) ) {
						// Get the translation of the term
						$term  = get_term_by( 'slug', $value, $attribute['name'] );
						$_term = get_term_by( 'id', pll_get_term( absint( $term->term_id ), $lang ), $attribute['name'] );

						if ( $_term ) {
							$value = $_term->slug;
						}
					}
					/**
					 * End of custom code.
					 */
				} else {
					$value = wc_clean( stripslashes( $_REQUEST[ $taxonomy ] ) );
				}

				// Get valid value from variation
				// change proposed by @theleemon
				$variation_data = wc_get_product_variation_attributes( $variation->get_id() );
				$valid_value    = isset( $variation_data[ $taxonomy ] ) ? $variation_data[ $taxonomy ] : '';

				// Allow if valid or show error.
				if ( '' === $valid_value || $valid_value === $value ) {
					$variations[ $taxonomy ] = $value;
				} else {
					throw new \Exception( sprintf( __( 'Invalid value posted for %s', 'woocommerce' ), wc_attribute_label( $attribute['name'] ) ) );
				}
			} else {
				$missing_attributes[] = wc_attribute_label( $attribute['name'] );
			}
		}
		if ( ! empty( $missing_attributes ) ) {
			throw new \Exception( sprintf( _n( '%s is a required field', '%s are required fields', sizeof( $missing_attributes ), 'woocommerce' ), wc_format_list_of_items( $missing_attributes ) ) );
		}
	} catch ( Exception $e ) {
		wc_add_notice( $e->getMessage(), 'error' );
		/**
		 * Custom code: We are doing an action, therefore no return needed. Instead we need to
		 * set $passed_validation to false, to ensure the product is not added to the card.
		 */
		add_filter( 'woocommerce_add_to_cart_validation', '__return_false' );
	}

	// Add to cart validation
	$passed_validation = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity, $variation_id, $variations );

	if ( $passed_validation && WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $variations ) !== false ) {
		wc_add_to_cart_message( array( $product_id => $quantity ), true );
		//return true;
		/**
		 * Custom code: We are doing an action, therefore no return needed. Intead we need to
		 * set $was_added_to_cart to true to trigger the optional redirect
		 */
		$was_added_to_cart = true;
	}

	/**
	 * Because this is a custom handler we need to take care of the rediret
	 * to the cart. Again we use the code from add_to_cart_action( $url )
	 *
	 * NOTE: From add_to_cart_action( $url )
	 * NOTE:  If we added the product to the cart we can now optionally do a redirect.
	 */
	if ( $was_added_to_cart && wc_notice_count( 'error' ) === 0 ) {
		// If has custom URL redirect there
		if ( $url = apply_filters( 'woocommerce_add_to_cart_redirect', $url ) ) {
			wp_safe_redirect( $url );
			exit;
		} elseif ( get_option( 'woocommerce_cart_redirect_after_add' ) === 'yes' ) {
			wp_safe_redirect( wc_get_cart_url() );
			exit;
		}
	}
}
add_action( 'woocommerce_add_to_cart_handler_wpidg_variable', 'wpidg_woocommerce_add_to_cart_handler_wpidg_variable', 10, 1 );