<?php

namespace CodeVerve\WCPLL\Core;

use CodeVerve\WCPLL\Utils\StringUtils;

/**
 * Class WC_Hooks
 * @package CodeVerve\WCPLL\Core
 */
final class WC_Hooks extends Base {

	/**
	 * Hooks constructor.
	 */
	public function __construct() {

		parent::__construct();

		$this->translate_products();
		$this->translate_taxonomies();
		$this->translate_emails();

		if ( did_action( 'pll_language_defined' ) ) {
			$this->translate_pages();
		} else {
			add_action( 'pll_language_defined', array( $this, 'translate_pages' ) );
		}

		$this->translate_myaccount();
		$this->translate_breadcrumbs();
		$this->translate_coupons();
		$this->translate_cart();
		$this->translate_orders();
		$this->translate_strings();
		$this->init_system_status_report();
	}

	/**
	 * Translate the products and variations along with stock syncrhonization.
	 */
	protected function translate_products() {

		add_action( 'set_object_terms', array( $this, 'set_object_terms' ), 10, 4 );

		add_filter( 'woocommerce_register_post_type_product', array( $this, 'register_post_type_product' ) );
		add_filter( 'woocommerce_variable_children_args', array( $this, 'variable_children_args' ) );
		add_action( 'woocommerce_product_object_updated_props', array( $this, 'product_object_updated_props' ), 10, 2 );

		remove_action( 'edit_term', array( '\WC_Post_Data', 'edit_term' ), 10 );
		remove_action( 'edited_term', array( '\WC_Post_Data', 'edited_term' ), 10 );
		add_action( 'edit_term', array( $this, 'edit_term' ), 10, 3 );
		add_action( 'edited_term', array( $this, 'edited_term' ), 10, 3 );

		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 5, 2 );
		add_action( 'woocommerce_update_product', array( $this, 'save_product' ) );
		add_action( 'woocommerce_new_product_variation', array( $this, 'save_variation' ) );
		add_action( 'woocommerce_update_product_variation', array( $this, 'save_variation' ) );

		if ( version_compare( WC()->version, '3.4', '<' ) ) {
			add_action( 'before_delete_post', array( $this, 'delete_post' ) );
		} else {
			add_action( 'woocommerce_before_delete_product_variation', array( $this, 'delete_variation' ) );
		}
		add_filter( 'wc_product_has_unique_sku', array( $this, 'product_has_unique_sku' ), 10, 3 );
		add_filter( 'pll_filter_query_excluded_query_vars', array( $this, 'filter_query_excluded_query_vars' ), 10, 2 );

		// Stock sync
		if ( version_compare( WC()->version, '3.6', '<' ) ) {
			add_action( 'woocommerce_product_set_stock', array( $this, 'product_set_stock' ) );
			add_action( 'woocommerce_variation_set_stock', array( $this, 'product_set_stock' ) );
		}

		add_filter( 'woocommerce_update_product_stock_query', array(
			$this,
			'update_product_stock_query'
		), 10, 2 ); // WC 3.6+
		add_action( 'woocommerce_updated_product_stock', array( $this, 'updated_product_stock' ) ); // WC 3.6.
		add_action( 'woocommerce_product_set_stock_status', array( $this, 'set_stock_status' ), 10, 2 );
		add_action( 'woocommerce_variation_set_stock_status', array( $this, 'set_stock_status' ), 10, 2 );
		add_filter( 'woocommerce_query_for_reserved_stock', array( $this, 'query_for_reserved_stock' ), 10, 3 );

		// Clear transient cache
		add_action( 'woocommerce_delete_product_transients', array( $this, 'delete_product_transients' ) );

		// Admin: Product duplication
		add_filter( 'woocommerce_duplicate_product_exclude_children', '__return_true' );
		add_action( 'admin_action_duplicate_product', array( $this, 'action_duplicate_product' ), 5 );
		add_action( 'woocommerce_product_duplicate', array( $this, 'product_duplicate' ), 10, 2 );

		// Admin: Handles Product order.
		add_action( 'woocommerce_after_product_ordering', array( $this, 'sync_product_order' ), 10, 2 );

		// Admin: Filters the products in ajax search
		add_filter( 'woocommerce_json_search_found_products', array( $this, 'json_search_found_products' ), 0 );
		add_filter( 'woocommerce_json_search_found_grouped_products', array( $this, 'json_search_found_products' ), 0 );
	}

	/**
	 * Translate taxonomies
	 */
	protected function translate_taxonomies() {

		// Front end.
		add_filter( 'woocommerce_taxonomy_args_product_cat', array( $this, 'taxonomy_args_product_cat' ), 10, 1 );
		add_filter( 'woocommerce_taxonomy_args_product_tag', array( $this, 'taxonomy_args_product_tag' ), 10, 1 );

		// Back end.
		add_filter( 'pll_copy_term_metas', array( $this, 'taxonomies_copy_term_metas' ), 10, 5 );
		add_filter( 'get_terms_args', array( $this, 'taxonomies_get_terms_args' ), 5, 1 );
		if ( isset( PLL()->options['media_support'] ) && PLL()->options['media_support'] ) {
			add_filter( 'pll_translate_term_meta', array( $this, 'taxonomies_translate_term_meta' ), 10, 3 );
			add_action( 'created_product_cat', array( $this, 'stored_product_cat' ), 500 );
			add_action( 'edited_product_cat', array( $this, 'stored_product_cat' ), 500 );
		}
		add_action( 'create_term', array( $this, 'taxonomies_create_term' ), 10, 3 );
		$this->remove_anonymous_object_filter( 'product_cat_add_form_fields', array(
			'\WC_Admin_Taxonomies',
			'add_category_fields'
		) );
		add_action( 'product_cat_add_form_fields', array( $this, 'taxonomies_add_category_fields' ) );
		add_action( 'admin_print_footer_scripts', array( $this, 'taxonomies_admin_print_footer_scripts' ), 1000 );
	}

	/**
	 * Translates orders
	 */
	protected function translate_orders() {
		add_action( 'wp_loaded', array( $this, 'orders_list_columns' ), 15 );
		add_action( 'add_meta_boxes', array( $this, 'order_language_metabox' ), 30 );
		add_filter( 'woocommerce_admin_order_actions', array( $this, 'orders_admin_order_actions' ) );
		add_filter( 'woocommerce_admin_order_preview_actions', array( $this, 'orders_admin_order_actions' ) );
	}

	/**
	 * Translate emails
	 */
	protected function translate_emails() {

		// Disable WooCommerce locale management.
		add_filter( 'woocommerce_email_setup_locale', '__return_false' );
		add_filter( 'woocommerce_email_restore_locale', '__return_false' );

		// Save prefereed user locale during checkout or account creation.
		add_action( 'woocommerce_created_customer', array( $this, 'created_customer' ), 5 );
		add_action( 'woocommerce_thankyou', array( $this, 'thankyou' ) );

		// Set the order notifications locale
		foreach ( $this->get_order_email_notification_actions() as $action ) {
			add_action( $action, array( $this, 'switch_order_locale' ), 1 );
			add_action( $action, array( $this, 'restore_locale' ), 9999 );
		}

		// Set the email notifications locale
		foreach ( $this->get_user_email_notification_actions() as $action ) {
			add_action( $action, array( $this, 'switch_user_locale' ), 1 );
			add_action( $action, array( $this, 'restore_locale' ), 9999 );
		}

		// Admin resend action
		add_action( 'woocommerce_before_resend_order_emails', array( $this, 'switch_order_locale' ) );
		add_action( 'woocommerce_after_resend_order_email', array( $this, 'restore_locale' ) );

		// TODO: Site titles, etc.

	}


	/**
	 * Translate the WooCommerce pages
	 */
	public function translate_pages() {

		// Warning: This should only run on the front-end.
		if ( ! $this->is_pll_frontend() ) {
			return;
		}

		// Translate pages
		foreach ( array( 'myaccount', 'shop', 'cart', 'checkout', 'terms' ) as $slug ) {
			add_filter( sprintf( 'option_woocommerce_%s_page_id', $slug ), 'pll_get_post' );
		}

		// Translate the WooCommercer saerch form
		add_filter( 'get_product_search_form', array( PLL()->filters_search, 'get_search_form' ), 99 );

		if ( ! PLL()->options['force_lang'] ) {
			// Add language field to forms
			foreach ( $this->get_form_filters() as $action ) {
				add_action( $action, array( $this, 'add_language_hidden_field' ) );
			}
			// Add language field to the cart remove url
			add_filter( 'woocommerce_get_remove_url', array( $this, 'add_language_query_arg' ) );
		}

		// Filter the products tax query
		add_filter( 'woocommerce_product_query_tax_query', array( $this, 'product_query_tax_query' ) );

		// Add compatibility with wp_cache_*
		add_filter( 'woocommerce_shortcode_products_query', array( $this, 'shortcode_products_query' ) );
		add_filter( 'woocommerce_get_product_subcategories_cache_key', array(
			$this,
			'get_product_subcategories_cache_key'
		) );

		// Translate the ajax get_endpoint
		add_filter( 'woocommerce_ajax_get_endpoint', array( $this, 'ajax_get_endpoint' ), 10, 2 );

		// Translate the order-received link. Make it in same language as the order.
		add_filter( 'woocommerce_get_checkout_order_received_url', array(
			$this,
			'get_checkout_order_received_url'
		), 10, 2 );

		// Translates the endpoints and
		add_filter( 'pll_translation_url', array( $this, 'pages_handle_endpoints' ), 10 );
		add_filter( 'pll_translation_url', array( $this, 'pages_handle_layered_nav' ), 10, 2 );

	}

	/**
	 * Translate account parts
	 */
	public function translate_myaccount() {

		// Warning: This should only run on the front-end.
		if ( ! $this->is_pll_frontend() ) {
			return;
		}

		add_action( 'parse_query', array( $this, 'parse_query' ), 3 );
		add_filter( 'woocommerce_order_item_name', array( $this, 'order_item_name' ), 10, 3 );
	}

	/**
	 * Translates the woocommerce breadcrumbs
	 */
	public function translate_breadcrumbs() {
		add_filter( 'woocommerce_breadcrumb_home_url', 'pll_home_url' );
		add_filter( 'option_woocommerce_permalinks', array( $this, 'option_woocommerce_permalinks' ) );
	}

	/**
	 * Translate coupons
	 */
	public function translate_coupons() {

		// In the front-end
		if ( $this->is_pll_frontend() ) {
			add_action( 'woocommerce_coupon_loaded', array( $this, 'coupon_loaded' ) );
		}
		// In the back-end
		add_filter( 'get_terms_args', array( $this, 'filter_coupon_product_categories_dropdown' ), 10, 2 );
	}

	/**
	 * Translate cart
	 */
	public function translate_cart() {

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_cart_scripts' ), 100 );
		add_action( 'woocommerce_cart_loaded_from_session', array( $this, 'cart_loaded_from_session' ) );
		if ( version_compare( WC()->version, '3.6', '>=' ) ) {
			add_filter( 'woocommerce_cart_hash', array( $this, 'cart_hash' ), 10, 2 );
		} else {
			add_filter( 'woocommerce_add_to_cart_hash', array( $this, 'cart_hash' ), 10, 2 );
		}
		add_filter( 'woocommerce_cart_item_data_to_validate', array( $this, 'cart_item_data_to_validate' ), 10, 2 );
	}

	/**
	 * Prints out the javascript for reseting the cart cache
	 */
	public function enqueue_cart_scripts() {

		if ( ! isset( $_COOKIE[ PLL_COOKIE ] ) || pll_current_language() == $_COOKIE[ PLL_COOKIE ] ) {
			return;
		}

		if ( ! function_exists( 'wp_add_inline_script' ) ) {
			return;
		}

		$this->recalculate_shipping();

		$is_ltwc34 = version_compare( WC()->version, '3.4', '<' );

		if ( $is_ltwc34 ) {
			$hash          = md5( get_current_blog_id() . '_' . get_site_url( get_current_blog_id(), '/' ) );
			$cart_hash_key = 'wc_cart_hash';
			$fragment_name = apply_filters( 'woocommerce_cart_fragment_name', 'wc_fragments_' . $hash );
		} else {
			$hash          = md5( get_current_blog_id() . '_' . get_site_url( get_current_blog_id(), '/' ) . get_template() );
			$cart_hash_key = apply_filters( 'woocommerce_cart_hash_key', 'wc_cart_hash_' . $hash );
			$fragment_name = apply_filters( 'woocommerce_cart_fragment_name', 'wc_fragments_' . $hash );
		}

		$cart_hash_key = esc_js( $cart_hash_key );
		$fragment_name = esc_js( $fragment_name );

		wp_add_inline_script(
			'wc-cart-fragments',
			'var handleCartCache = function(){
                sessionStorage.removeItem( "' . $cart_hash_key . '" );
                sessionStorage.removeItem( "' . $fragment_name . '" );
            };
            if (
                document.readyState === "complete" ||
                (document.readyState !== "loading" && !document.documentElement.doScroll)
            ) {
                handleCartCache();
            } else {
                document.addEventListener("DOMContentLoaded", handleCartCache);
            }',
			'before'
		);
	}

	/**
	 * Replace the cart contents with translated.
	 */
	public function cart_loaded_from_session() {
		$this->replace_cart_contents_with_translated();
	}

	/**
	 * Cart hash override for WC < 3.6
	 *
	 * @param $hash
	 * @param $contents
	 *
	 * @return string
	 */
	public function cart_hash( $hash, $contents ) {
		if ( empty( $contents ) ) {
			return $hash;
		}
		$contents = $this->get_translated_cart_contents( $contents, pll_default_language() );
		$hash     = md5( wp_json_encode( $contents ) . WC()->cart->get_total( 'edit' ) );

		return $hash;
	}

	/**
	 * Set the translated product attributes on cart validation
	 *
	 * @param $data
	 * @param \WC_Product $product
	 *
	 * @return mixed
	 */
	public function cart_item_data_to_validate( $data, $product ) {

		if ( empty( $data['attributes'] ) ) {
			return $data;
		}

		$lang = pll_default_language();

		$translated_product_id = $this->products->get( $product->get_id(), $lang );

		if ( $translated_product_id ) {
			$translated_product = wc_get_product( $translated_product_id );
			if ( $translated_product ) {
				$data['attributes'] = $translated_product->get_variation_attributes();
			}
		}

		return $data;
	}

	/**
	 * Translate the coupons
	 *
	 * @param $coupons
	 */
	public function coupon_loaded( $coupons ) {
		if ( ! pll_current_language() ) {
			return;
		}
		$coupons->set_product_ids( array_map( array( $this, 'get_translated_product' ), $coupons->get_product_ids() ) );
		$coupons->set_excluded_product_ids( array_map( array(
			$this,
			'get_translated_product'
		), $coupons->get_excluded_product_ids() ) );
		$coupons->set_product_categories( array_map( array(
			$this,
			'get_translated_term'
		), $coupons->get_product_categories() ) );
		$coupons->set_excluded_product_categories( array_map( array(
			$this,
			'get_translated_term'
		), $coupons->get_excluded_product_categories() ) );
	}

	/**
	 * Filters the product categories dropdown in coupon edit.
	 *
	 * @param $args
	 * @param $taxonomies
	 *
	 * @return mixed
	 */
	public function filter_coupon_product_categories_dropdown( $args, $taxonomies ) {

		global $post_type;
		if ( 'shop_coupon' === $post_type && in_array( 'product_cat', $taxonomies ) ) {
			$args['lang'] = $this->get_current_dashboard_language();
		}

		return $args;
	}


	/**
	 * Make the order received url in the same language as the order
	 *
	 * @param $url
	 * @param $order
	 *
	 * @return string
	 */
	public function get_checkout_order_received_url( $url, $order ) {

		$lang = $this->orders->get_language( $order->get_id() );

		if ( empty( $lang ) ) {
			return $url;
		}

		$prev_curlang  = PLL()->curlang;
		PLL()->curlang = PLL()->model->get_language( $lang );
		$url           = wc_get_endpoint_url( 'order-received', $order->get_id(), wc_get_checkout_url() );
		$url           = add_query_arg( 'key', $order->get_order_key(), $url );
		PLL()->curlang = $prev_curlang;

		return $url;
	}

	/**
	 * Used to translate the WooCommerce endpoints when switching the language
	 * of the My Account order page, order view link, etc.
	 *
	 * @param $url
	 *
	 * @return string
	 */
	public function pages_handle_endpoints( $url ) {

		$endpoint = WC()->query->get_current_endpoint();
		if ( ! $endpoint ) {
			return $url;
		}
		global $wp;
		$value = wc_edit_address_i18n( $wp->query_vars[ $endpoint ], true );
		$url   = wc_get_endpoint_url( $endpoint, $value, $url );
		if ( PLL()->links_model->using_permalinks ) {
			$url = trailingslashit( $url );
		}
		if ( 'order-received' === $endpoint ) {
			$order = wc_get_order( $value );
			if ( $order ) {
				$url = add_query_arg( 'key', $order->get_order_key(), $url );
			}
		} else if ( 'order-pay' === $endpoint ) {
			$order = wc_get_order( $value );
			if ( $order ) {
				$url = add_query_arg( array( 'pay_for_order' => 'true', 'key' => $order->get_order_key() ), $url );
			}
		}

		return $url;
	}

	/**
	 * Used to translate the pages produced by the Layered nav filters.
	 *
	 * @param $url
	 * @param $lang
	 *
	 * @return false|mixed|string|\WP_Error
	 */
	public function pages_handle_layered_nav( $url, $lang ) {

		if ( is_shop() && ! is_search() ) {

			$translated_shop_page_id = pll_get_post( wc_get_page_id( 'shop' ), $lang );
			if ( ! $translated_shop_page_id ) {
				return $url;
			}
			$url = get_permalink( $translated_shop_page_id );
			foreach ( wc_get_attribute_taxonomies() as $taxonomy ) {
				$attribute_name = 'filter_' . $taxonomy->attribute_name;
				if ( empty( $_GET[ $attribute_name ] ) ) {
					continue;
				}
				$translated_term_id = pll_get_term( (int) $_GET[ $attribute_name ], $lang );
				if ( ! $translated_term_id ) {
					continue;
				}
				$url = add_query_arg( $attribute_name, $translated_term_id, $url );
			}
		}

		return $url;
	}

	/**
	 * Fix the Shop page url in the breadcrumbs
	 *
	 * @param $value
	 *
	 * @return mixed
	 */
	public function option_woocommerce_permalinks( $value ) {
		if ( ! $this->is_pll_frontend() ) {
			return $value;
		}

		if ( ! isset( $value['product_base'] ) || ! did_action( 'pll_language_defined' ) ) {
			return $value;
		}

		$slugs = $this->get_shop_page_slugs();
		$lang  = pll_current_language();

		if ( count( $slugs ) > 1 && ! empty( $slugs[ $lang ] ) ) {
			$pattern                    = '#(' . implode( '|', $slugs ) . ')#';
			$permalinks['product_base'] = preg_replace( $pattern, $slugs[ $lang ], $value['product_base'] );
		}

		return $value;
	}

	/**
	 * Skip translating the product slug, does not work well with WooCommerce.
	 *
	 * @param array $args
	 *
	 * @return array
	 */
	public function register_post_type_product( $args = array() ) {
		$permalinks        = get_option( 'woocommerce_permalinks' );
		$product_permalink = ! empty( $permalinks['product_base'] ) ? $permalinks['product_base'] : 'product';
		if ( $product_permalink ) {
			$args['rewrite'] = array(
				'slug'       => untrailingslashit( $product_permalink ),
				'with_front' => false,
				'feeds'      => true
			);
		} else {
			$args['rewrite'] = false;
		}

		return $args;
	}

	/**
	 * Skip language filtering for the variable product children
	 *
	 * @param array $args
	 *
	 * @return array
	 */
	public function variable_children_args( $args ) {
		return array_merge( $args, array( 'lang' => '' ) );
	}

	/**
	 * Purge caches when assigning a product type to specific product
	 *
	 * @param int $object_id
	 * @param array $terms
	 * @param array $tt_ids
	 * @param string $taxonomy
	 */
	public function set_object_terms( $object_id, $terms, $tt_ids, $taxonomy ) {
		if ( 'product_type' !== $taxonomy ) {
			return;
		}
		$key = \WC_Cache_Helper::get_cache_prefix( 'product_' . $object_id ) . '_type_' . $object_id;
		wp_cache_delete( $key, 'products' );
	}

	/**
	 * Fires actions and update look tables of translated products after properties have been synchronized.
	 *
	 * @param object $product
	 * @param array $updated_props
	 *
	 */
	public function product_object_updated_props( $product, $updated_props ) {
		static $has_run = false;

		if ( $has_run || ! $product ) {
			return;
		}

		$has_run = true;

		$product_id = $product->get_id();

		foreach ( $this->products->get_translations( $product_id ) as $translated_id ) {
			$translated_product = null;

			if ( $product_id === $translated_id ) {
				continue;
			}

			$translated_product = wc_get_product( $translated_id );

			if ( ! $translated_product ) {
				continue;
			}

			if ( in_array( 'stock_quantity', $updated_props, true ) ) {
				if ( $translated_product->is_type( 'variation' ) ) {
					do_action( 'woocommerce_variation_set_stock', $translated_product );
				} else {
					do_action( 'woocommerce_product_set_stock', $translated_product );
				}
			}

			if ( in_array( 'stock_status', $updated_props, true ) ) {
				if ( $translated_product->is_type( 'variation' ) ) {
					do_action( 'woocommerce_variation_set_stock_status', $translated_product->get_id(), $translated_product->get_stock_status(), $translated_product );
				} else {
					do_action( 'woocommerce_product_set_stock_status', $translated_product->get_id(), $translated_product->get_stock_status(), $translated_product );
				}
			}

			if ( array_intersect( $updated_props, array(
				'sku',
				'regular_price',
				'sale_price',
				'date_on_sale_from',
				'date_on_sale_to',
				'total_sales',
				'average_rating',
				'stock_quantity',
				'stock_status',
				'manage_stock',
				'downloadable',
				'virtual',
				'tax_status',
				'tax_class'
			) ) ) {
				$this->products->update_lookup_table( $translated_product->get_id(), 'wc_product_meta_lookup' );
			}

			do_action( 'woocommerce_product_object_updated_props', $translated_product, $updated_props );
		}

		$has_run = false;
	}

	/**
	 * Store the current term that is being edited.
	 *
	 * @param $term_id
	 * @param $translated_term_id
	 * @param $taxonomy
	 */
	public function edit_term( $term_id, $translated_term_id, $taxonomy ) {
		if ( ! StringUtils::starts_with( $taxonomy, 'pa_' ) ) {
			return;
		}
		$current_term = get_term_by( 'id', $term_id, $taxonomy );
		$this->set_tmp_value( 'current_term', $current_term );
	}

	/**
	 * Modified version of WC_Post_Data::edited_term().
	 * Language is added to the query to take into account updates of attributes sharing the same slug.
	 *
	 * @param int $term_id
	 * @param int $translated_term_id
	 * @param string $taxonomy
	 */
	public function edited_term( $term_id, $translated_term_id, $taxonomy ) {

		$current_term = $this->get_tmp_value( 'current_term' );

		if ( is_null( $current_term ) || ! StringUtils::starts_with( $taxonomy, 'pa_' ) ) {
			return;
		}

		$edited_term = get_term_by( 'id', $term_id, $taxonomy );

		if ( $edited_term->slug === $current_term->slug ) {
			return;
		}

		global $wpdb;
		$language = $this->terms->get_language( $term_id, 'term_taxonomy_id' );
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} AS pm
						INNER JOIN {$wpdb->term_relationships} AS pll_tr ON pll_tr.object_id = pm.post_id
						SET meta_value = %s
						WHERE meta_key = %s
						AND meta_value = %s
						AND pll_tr.term_taxonomy_id = %d",
				$edited_term->slug,
				'attribute_' . sanitize_title( $taxonomy ),
				$current_term->slug,
				$language
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->postmeta} AS pm
						INNER JOIN {$wpdb->term_relationships} AS pll_tr ON pll_tr.object_id = pm.post_id
						SET meta_value = REPLACE( meta_value, %s, %s )
						WHERE meta_key = '_default_attributes'
						AND pll_tr.term_taxonomy_id = %d",
				serialize( $current_term->taxonomy ) . serialize( $current_term->slug ),
				serialize( $edited_term->taxonomy ) . serialize( $edited_term->slug ),
				$language
			)
		);

	}


	/**
	 * When user uses "Add new" attempt to copy variations and meta data
	 *
	 * @param $post_type
	 * @param $post
	 */
	public function add_meta_boxes( $post_type, $post ) {
		if ( ! ( 'post-new.php' === $GLOBALS['pagenow'] && isset( $_GET['from_post'], $_GET['new_lang'] ) && 'product' === $post_type ) ) {
			return;
		}

		check_admin_referer( 'new-post-translation' );

		$lang = PLL()->model->get_language( sanitize_key( $_GET['new_lang'] ) ); // Make sure we have a valid language.
		$this->copy_variations( (int) $_GET['from_post'], $post->ID, $lang->slug );

		do_action( 'wpi_copy_product', (int) $_GET['from_post'], $post->ID, $lang->slug );
	}


	/**
	 * Fired when the product is saved. Used to syncrhonize the data.
	 *
	 * @param int $id
	 */
	public function save_product( $id ) {
		$translations = $this->products->get_translations( $id );
		foreach ( $translations as $lang => $translated_id ) {
			if ( $id == $translated_id ) {
				continue;
			}
			// Do not copy variations if they were updated before.
			if ( ! did_action( 'woocommerce_update_product_variation' ) && ! did_action( 'woocommerce_new_product_variation' ) ) {
				$this->copy_variations( $id, $translated_id, $lang, true );
			}
			do_action( 'wpi_copy_product', $id, $translated_id, $lang, true );
		}
	}

	/**
	 * Sets the variation language and synchronizes it with its translations.
	 *
	 * @param int $id
	 */
	public function save_variation( $id ) {

		static $has_run = false;

		if ( ! doing_action( 'woocommerce_product_duplicate' ) && ! doing_action( 'wp_ajax_woocommerce_do_ajax_product_import' ) && ! $has_run ) {
			$has_run = true;

			if ( $variation = wc_get_product( $id ) ) {
				$pid      = $variation->get_parent_id();
				$language = $this->products->get_language( $pid );
				$this->products->set_language( $id, $language );

				foreach ( $this->products->get_translations( $pid ) as $lang => $tr_pid ) {
					if ( $tr_pid !== $pid ) {
						$this->products->copy_variation( $id, $tr_pid, $lang );
					}
				}
			}
		}
		$has_run = false;
	}

	/**
	 * Copy or synchronize variations.
	 *
	 * @param int $from
	 * @param int $to
	 * @param string $lang
	 * @param bool $sync
	 *
	 */
	public function copy_variations( $from, $to, $lang, $sync = false ) {
		$product = wc_get_product( $from );

		if ( ! is_a( $product, '\WC_Product_Variable' ) ) {
			return; // bail if wrong product.
		}

		$language   = $this->products->get_language( $from );
		$variations = $product->get_children();
		remove_action( 'woocommerce_new_product_variation', array( $this, 'save_variation' ) );
		foreach ( $variations as $id ) {
			$this->products->set_language( $id, $language );
			$this->products->copy_variation( $id, $to, $lang );
		}
		add_action( 'woocommerce_new_product_variation', array( $this, 'save_variation' ) );
	}

	/**
	 * Synchronizes variation deletion (Legacy)
	 *
	 * @param int $post_id
	 */
	public function delete_post( $post_id ) {

		static $avoid_parent = 0;
		static $avoid_delete = array();
		$post_type = get_post_type( $post_id );
		if ( 'product' === $post_type ) {
			$avoid_parent = $post_id;
		}

		if ( 'product_variation' !== $post_type || in_array( $post_id, $avoid_delete ) ) {
			return;
		}

		$post = get_post( $post_id );
		if ( $post->post_parent == $avoid_parent ) {
			return;
		}
		$translated_ids = $this->products->get_translations( $post_id );
		$avoid_delete   = array_merge( $avoid_delete, array_values( $translated_ids ) ); // To avoid deleting a post two times.
		foreach ( $translated_ids as $translated_id ) {
			wp_delete_post( $translated_id );
		}
	}

	/**
	 * Synchronizes variations deletion (WC 3.4+)
	 *
	 * @param int $id
	 */
	public function delete_variation( $id ) {
		static $value = array();
		if ( doing_action( 'delete_post' ) || in_array( $id, $value ) ) {
			return;
		}
		$translated_ids = $this->products->get_translations( $id );
		$value          = array_merge( $value, array_values( $translated_ids ) );
		foreach ( $translated_ids as $translated_id ) {
			if ( $variation = wc_get_product( $translated_id ) ) {
				$variation->delete( true );
			}
		}
	}

	/**
	 * Filters wc_product_has_unique_sku to query by language.
	 *
	 * @param bool $sku_found
	 * @param int $product_id
	 * @param string $sku
	 *
	 * @return bool
	 */
	public function product_has_unique_sku( $sku_found, $product_id, $sku ) {

		if ( ! $sku_found ) {
			return $sku_found;
		}

		$language = $this->products->get_language( $product_id );
		$language = apply_filters( 'wpi_language_for_unique_sku', $language, $product_id );

		if ( $language ) {
			return $this->products->sku_exists( $product_id, $sku, $language );
		}

		return $sku_found;
	}

	/**
	 * Modifies the query vars for filtering the products
	 * - Filter the on sale product block by language.
	 * - Fixes the search in Porducts list table
	 *
	 * @param array $excludes
	 * @param object $query
	 *
	 * @return array
	 */
	public function filter_query_excluded_query_vars( $excludes, $query ) {
		if ( ! empty( $query->query['product_search'] ) ) {
			// Filter the query vars to fix the search in the Products list table.
			$excludes = array_diff( $excludes, array( 'post__in' ) );
		} else if ( isset( $query->query['post_type'], $query->query['post__in'] )
		            && 'product' === $query->query['post_type']
		            && array_merge( array( 0 ), wc_get_product_ids_on_sale() ) === $query->query['post__in'] ) {
			// Fiilter the query vars for the product block by language
			$excludes = array_diff( $excludes, array( 'post__in' ) );
		}

		return $excludes;
	}

	/**
	 * Synchronizes the stock across the product translations.
	 *
	 * @param \WC_Product $product
	 *
	 */
	public function product_set_stock( $product ) {

		static $has_run = array();

		$id  = $product->get_id();
		$qty = $product->get_stock_quantity();


		if ( ! empty( $has_run[ $id ][ $qty ] ) ) {
			return;
		}

		$tr_ids = $this->products->get_translations( $id );

		foreach ( $tr_ids as $tr_id ) {
			if ( $tr_id === $id ) {
				continue;
			}
			$has_run[ $id ][ $qty ] = true;
			wc_update_product_stock( $tr_id, $qty );
		}
	}

	/**
	 * Synchronize the stock across the product translations.
	 *
	 * @param string $sql
	 * @param int $product_id
	 *
	 * @return string
	 *
	 */
	public function update_product_stock_query( $sql, $product_id ) {
		global $wpdb;

		$tr_ids = $this->products->get_translations( $product_id );

		return str_replace(
			$wpdb->prepare( 'post_id = %d', $product_id ),
			sprintf( 'post_id IN ( %s )', implode( ',', array_map( 'absint', $tr_ids ) ) ),
			$sql
		);
	}

	/**
	 * Deletes the cache and updates the stock status for all the translations.
	 *
	 * @param int $id
	 */
	public function updated_product_stock( $id ) {

		foreach ( $this->products->get_translations( $id ) as $tr_id ) {
			if ( $tr_id === $id ) {
				continue;
			}
			$product = wc_get_product( $tr_id );
			if ( ! $product ) {
				continue;
			}
			$product_id_with_stock = $product->get_stock_managed_by_id();
			wp_cache_delete( $product_id_with_stock, 'post_meta' );
			$this->products->update_lookup_table( $tr_id, 'wc_product_meta_lookup' );
		}
	}

	/**
	 * Synchronizes the stock status across the product translations.
	 *
	 * @param int $id Product id.
	 * @param string $status Stock status.
	 *
	 */
	public function set_stock_status( $id, $status ) {

		static $has_run = array();

		if ( ! empty( $has_run[ $id ][ $status ] ) ) {
			return;
		}

		$tr_ids = $this->products->get_translations( $id );

		foreach ( $tr_ids as $tr_id ) {
			if ( $tr_id === $id ) {
				continue;
			}
			$has_run[ $id ][ $status ] = true;
			wc_update_product_stock_status( $tr_id, $status );
		}
	}

	/**
	 * Synchronizes reserve_stock_for_product accross translations
	 *
	 * @param string $query
	 * @param int $product_id
	 * @param int $exclude_order_id
	 *
	 * @return string|void
	 *
	 */
	public function query_for_reserved_stock( $query, $product_id, $exclude_order_id ) {
		global $wpdb;
		$product_ids = $this->products->get_translations( $product_id );

		return $wpdb->prepare(
			"
			SELECT COALESCE( SUM( stock_table.`stock_quantity` ), 0 ) FROM $wpdb->wc_reserved_stock stock_table
			LEFT JOIN $wpdb->posts posts ON stock_table.`order_id` = posts.ID
			WHERE posts.post_status IN ( 'wc-checkout-draft', 'wc-pending' )
			AND stock_table.`expires` > NOW()
			AND stock_table.`product_id` IN ( %s )
			AND stock_table.`order_id` != %d
			",
			implode( ',', $product_ids ),
			$exclude_order_id
		);
	}

	/**
	 * Clear cached transients for the translated products
	 * when WooCommerce clears the transients for the original product.
	 *
	 * @param $product_id
	 */
	public function delete_product_transients( $product_id ) {

		static $product_ids;

		if ( ! is_array( $product_ids ) ) {
			$product_ids = array();
		}

		array_push( $product_ids, $product_id );

		foreach ( $this->products->get_translations( $product_id ) as $translation_product_id ) {
			if ( in_array( $translation_product_id, $product_ids ) ) {
				continue;
			}
			wc_delete_product_transients( $translation_product_id );
		}
	}

	/**
	 * Disables the term language check when duplicating the products.
	 */
	public function action_duplicate_product() {
		remove_action( 'set_object_terms', array( PLL()->posts, 'set_object_terms' ) );
	}

	/**
	 * Function to create the duplicate of the product.
	 *
	 * @param \WC_Product $duplicate
	 * @param \WC_Product $product
	 *
	 * @throws \WC_Data_Exception
	 */
	public function product_duplicate( $duplicate, $product ) {

		$translated_ids = $this->products->get_translations( $product->get_id() );

		$meta_to_exclude = array_filter(
			apply_filters(
				'woocommerce_duplicate_product_exclude_meta',
				array(),
				array_map(
					function ( $datum ) {
						return $datum->key;
					},
					$product->get_meta_data()
				)
			)
		);

		// Set the language on the duplicated product
		$language           = $this->products->get_language( $product->get_id() );
		$new_translated_ids = array( $language => $duplicate->get_id() );
		$this->products->set_language( $new_translated_ids[ $language ], $language );

		// Duplicate the translations.
		foreach ( $translated_ids as $lang => $translated_product_id ) {
			if ( $product->get_id() === $translated_product_id ) {
				continue;
			}
			$tr_product = wc_get_product( $translated_product_id );
			if ( empty( $tr_product ) ) {
				continue;
			}
			$translated_product = clone $tr_product;
			$translated_product->set_id( 0 );
			$translated_product->set_name( sprintf( __( '%s (Copy)', 'woocommerce-polylang-integration' ), $translated_product->get_name() ) );
			$translated_product->set_total_sales( 0 );
			$translated_product->set_status( 'draft' );
			$translated_product->set_date_created( null );
			$translated_product->set_slug( '' );
			$translated_product->set_rating_counts( 0 );
			$translated_product->set_average_rating( 0 );
			$translated_product->set_review_count( 0 );
			foreach ( $meta_to_exclude as $meta_key ) {
				$translated_product->delete_meta_data( $meta_key );
			}
			do_action( 'woocommerce_product_duplicate_before_save', $translated_product, $tr_product );
			$translated_product->save();
			$new_translated_ids[ $lang ] = $translated_product->get_id();
			$this->products->set_language( $new_translated_ids[ $lang ], $lang );
			// Set the SKU of the translated product if there is one.
			if ( '' !== $duplicate->get_sku( 'edit' ) ) {
				$translated_product->set_sku( $duplicate->get_sku( 'edit' ) );
				$translated_product->save();
			}
		}

		// Save duplicated translations
		$this->products->save_translations( $new_translated_ids );

		// Translate variation duplicates (if variable product)
		if ( $product->is_type( 'variable' ) ) {
			foreach ( $product->get_children() as $child_id ) {
				$translated_ids = $this->products->get_translations( $child_id );
				if ( empty( $translated_ids ) ) {
					continue;
				}
				$child = wc_get_product( $child_id );
				if ( empty( $child ) ) {
					continue;
				}
				$new_translated_child_ids    = array();
				$translated_child_duplicates = array();
				$sku                         = wc_product_generate_unique_sku( 0, $child->get_sku( 'edit' ) );
				foreach ( $translated_ids as $lang => $tr_id ) {
					$translated_child = wc_get_product( $tr_id );
					if ( $translated_child ) {
						$translated_child_duplicates[ $lang ] = clone $translated_child;
						$translated_child_duplicates[ $lang ]->set_parent_id( $this->products->get( $duplicate->get_id(), $lang ) );
						$translated_child_duplicates[ $lang ]->set_id( 0 );
						$translated_child_duplicates[ $lang ]->set_date_created( null );
						if ( '' !== $child->get_sku( 'edit' ) ) {
							$translated_child_duplicates[ $lang ]->set_sku( $sku );
						}
						$this->generate_unique_slug( $translated_child_duplicates[ $lang ] );
						foreach ( $meta_to_exclude as $meta_key ) {
							$translated_child_duplicates[ $lang ]->delete_meta_data( $meta_key );
						}
						do_action( 'woocommerce_product_duplicate_before_save', $translated_child_duplicates[ $lang ], $translated_child );
					}
				}
				foreach ( $translated_ids as $lang => $tr_id ) {
					$translated_child_duplicates[ $lang ]->save();
					$new_translated_child_ids[ $lang ] = $translated_child_duplicates[ $lang ]->get_id();
					$this->products->set_language( $new_translated_child_ids[ $lang ], $lang );
				}
				$this->products->save_translations( $new_translated_child_ids );
			}
		}
	}


	/**
	 * Sync product order
	 *
	 * @param $sorting_id
	 * @param $menu_orders
	 */
	public function sync_product_order( $sorting_id, $menu_orders ) {

		if ( ! in_array( 'menu_order', PLL()->options['sync'] ) ) {
			return;
		}

		if ( empty( $menu_orders ) ) {
			return;
		}

		$language = $this->products->get_language( $sorting_id );

		if ( empty( $language ) ) {
			return;
		}

		global $wpdb;
		foreach ( $menu_orders as $id => $order ) {
			if ( $this->products->get_language( $id ) === $language ) {
				foreach ( $this->products->get_translations( $id ) as $translated_product_id ) {
					if ( $id !== $translated_product_id ) {
						$wpdb->update( $wpdb->posts, array( 'menu_order' => $order ), array( 'ID' => $id ) );
					}
				}
			}
		}
	}

	/**
	 * Filter the ajax search results for products that does not belong to the language that is used at the moment.
	 *
	 * @param $products
	 *
	 * @return array
	 */
	public function json_search_found_products( $products ) {

		$post_id = isset( $_REQUEST['pll_post_id'] ) ? (int) $_REQUEST['pll_post_id'] : 0;
		$lang    = $this->products->get_language( $post_id );

		if ( ! $post_id || ! $lang ) {
			$lang = $this->get_current_dashboard_language();
		}

		$filtered = array();
		foreach ( $products as $id => $product ) {
			if ( $this->products->get_language( $id ) === $lang ) {
				$filtered[ $id ] = $product;
			}
		}

		return $filtered;
	}

	/**
	 * Restore the WooCommerce permalinks, disable translations on product_cat taxonomy.
	 *
	 * @param array $args
	 *
	 * @return mixed
	 */
	public function taxonomy_args_product_cat( $args ) {
		$permalinks            = $this->get_permalinks();
		$args['category_base'] = 'product-category';
		if ( isset( $permalinks['category_base'] ) && ! empty( $permalinks['category_base'] ) ) {
			$args['rewrite']['slug'] = $permalinks['category_base'];
		}

		return $args;
	}

	/**
	 * Restore the WooCommerce permalinks, disable translations product_tag taxonomy.
	 *
	 * @param array $args
	 *
	 * @return mixed
	 */
	public function taxonomy_args_product_tag( $args ) {
		$permalinks            = $this->get_permalinks();
		$args['category_base'] = 'product-tag';
		if ( isset( $permalinks['tag_base'] ) && ! empty( $permalinks['tag_base'] ) ) {
			$args['rewrite']['slug'] = $permalinks['tag_base'];
		}

		return $args;
	}

	/**
	 * Set the preferred customer locale after the customer account was created.
	 *
	 * @param $user_id
	 */
	public function created_customer( $user_id ) {
		$locale = $this->get_locale();
		update_user_meta( $user_id, 'locale', $locale );
	}

	/**
	 * Set or override the preferred user language after checkout process.
	 *
	 * @param $order_id
	 */
	public function thankyou( $order_id ) {

		if ( empty( $order_id ) ) {
			return;
		}
		$order   = wc_get_order( $order_id );
		$user_id = $order->get_user_id();
		if ( empty( $user_id ) ) {
			return;
		}
		$order_locale = $this->products->get_language( $order_id, 'locale' );
		$user_locale  = get_user_meta( $user_id, 'locale', true );
		if ( ! empty( $order_locale ) && $order_locale !== $user_locale ) {
			update_user_meta( $user_id, 'locale', $order_locale );
		}
	}

	/**
	 * Sets the email notification language and store the previous one.
	 *
	 * @param \PLL_Language $language
	 */
	public function set_email_notification_language( $language ) {

		$this->set_tmp_value( 'new_locale', switch_to_locale( $language->locale ) );

		$old_lang = empty( PLL()->curlang ) ? null : PLL()->curlang;
		$this->set_tmp_value( 'old_lang', $old_lang );

		PLL()->curlang = $language;

		// Translate pages
		foreach ( array( 'myaccount', 'shop', 'cart', 'checkout', 'terms' ) as $page ) {
			add_filter( sprintf( 'option_woocommerce_%s_page_id', $page ), 'pll_get_post' );
		}

		do_action( 'wpi_set_email_notification_language', $language );
	}


	/**
	 * Switch the locale by order
	 *
	 * @param $order
	 */
	public function switch_order_locale( $order ) {
		$order_id = $this->get_order_id( $order );
		if ( empty( $order_id ) ) {
			return;
		}
		$_language = $this->products->get_language( $order_id );
		$language  = PLL()->model->get_language( $_language );
		if ( empty( $language ) ) {
			return;
		}
		$this->set_email_notification_language( $language );
	}

	/**
	 * Switch the locale by user
	 *
	 * @param $user
	 */
	public function switch_user_locale( $user ) {

		$user_id = $this->get_user_id( $user );
		$locale  = $this->get_user_locale( $user_id );
		if ( empty( $locale ) ) {
			$locale = $this->get_locale();
		}
		$language = PLL()->model->get_language( $locale );
		if ( empty( $language ) ) {
			return;
		}
		$this->set_email_notification_language( $language );
	}

	/**
	 * Restore the current locale
	 */
	public function restore_locale() {

		$is_new_lang = $this->get_tmp_value( 'new_lang' );
		if ( $is_new_lang ) {
			unset( $this->switched_locale );
			restore_previous_locale();
		}

		// Restore the current language.
		PLL()->curlang = $this->get_tmp_value( 'old_lang' );
		foreach ( array( 'myaccount', 'shop', 'cart', 'checkout', 'terms' ) as $page ) {
			remove_filter( sprintf( 'option_woocommerce_%s_page_id', $page ), 'pll_get_post' );
		}
	}

	/**
	 * Print the language field
	 */
	public function add_language_hidden_field() {
		printf( '<input type="hidden" name="lang" value="%s" />', esc_attr( pll_current_language() ) );
	}


	/**
	 * Returns all the form filters from WooCommerce
	 * @return string[]
	 */
	private function get_form_filters() {
		return array(
			'woocommerce_login_form_start',
			'woocommerce_register_form_start',
			'woocommerce_before_cart_table',
			'woocommerce_before_add_to_cart_button',
			'woocommerce_lostpassword_form',
		);
	}

	/**
	 * Fix the tax query relations if shared slugs are in the array. Use only the current language slugs.
	 *
	 * @param array $tax_query
	 *
	 * @return mixed
	 */
	public function product_query_tax_query( $tax_query ) {

		foreach ( $tax_query as $i => $relation ) {
			if ( ! ( is_array( $relation ) && 'slug' === $relation['field'] ) ) {
				continue;
			}

			$taxonomy = isset( $relation['taxonomy'] ) ? $relation['taxonomy'] : '';
			$terms    = isset( $relation['terms'] ) ? $relation['terms'] : '';

			if ( empty( $taxonomy ) || empty( $terms ) ) {
				continue;
			}

			$filtered_terms = $this->get_terms( $taxonomy, array( 'slug' => $terms ) );

			$tax_query[ $i ]['terms'] = wp_list_pluck( $filtered_terms, 'term_taxonomy_id' );
			$tax_query[ $i ]['field'] = 'term_taxonomy_id';
		}

		return $tax_query;
	}

	/**
	 * Add language to the products shortcode query args.
	 *
	 * NOTE: Required for WooCommerce 3.0.
	 *
	 * @param array $args WP_Query arguments.
	 *
	 * @return array
	 */
	public function shortcode_products_query( $args ) {

		if ( ! isset( $args['tax_query'] ) ) {
			$args['tax_query'] = array();
		}

		array_push( $args['tax_query'], array(
			'taxonomy' => 'language',
			'field'    => 'term_taxonomy_id',
			'terms'    => PLL()->curlang->term_taxonomy_id,
			'operator' => 'IN',
		) );

		return $args;
	}

	/**
	 * Add language to the current language to the product subcategories cache key
	 *
	 * @param $key
	 *
	 * @return string
	 */
	public function get_product_subcategories_cache_key( $key ) {
		return sprintf( '%s-%s', $key, pll_current_language() );
	}

	/***
	 * Add language to the ajax endpoints
	 *
	 * NOTE: Required as of WooCommerce 3.2
	 *
	 * @param string $url
	 * @param string $request
	 *
	 * @return string
	 */
	public function ajax_get_endpoint( $url, $request ) {
		$url = remove_query_arg( 'wc-ajax', $url );
		$url = PLL()->links_model->switch_language_in_link( $url, PLL()->curlang );

		return add_query_arg( 'wc-ajax', $request, $url );
	}

	/**
	 * Display all orders without limiting those by language.
	 *
	 * @param \WP_Query $query
	 */
	public function parse_query( $query ) {
		$query_vars = $query->query_vars;

		if ( isset( $query_vars['lang'] ) ) {
			return;
		}

		$post_types = isset( $query_vars['post_type'] ) ? (array) $query_vars['post_type'] : array();

		if ( ! in_array( 'shop_order', $post_types ) ) {
			return;
		}

		$query->set( 'lang', 0 );
	}

	/**
	 * Translates the product name (order item name) in the order list table.
	 *
	 * @param $item_name
	 * @param $item
	 * @param $is_visible
	 *
	 * @return string
	 */
	public function order_item_name( $item_name, $item, $is_visible ) {

		$product_id = $item->get_variation_id();

		if ( ! $product_id ) {
			$product_id = $item->get_product_id();
		}

		$translation_id = $this->products->get( $product_id );

		if ( $translation_id && $translation_id !== $product_id ) {
			$translated = wc_get_product( $translation_id );
			if ( $translated ) {

				if ( $is_visible ) {
					$permalink = get_permalink( $translated->get_id() );
					$item_name = sprintf( '<a href="%s">%s</a>', $permalink, $translated->get_name() );
				} else {
					$item_name = $translated->get_name();
				}
			}
		}

		return $item_name;
	}


	/**
	 * Remove the default polylang columns and add custom ones for WooCommerce orders
	 */
	public function orders_list_columns() {

		if ( PLL() instanceof \PLL_Admin ) {
			remove_filter( 'manage_edit-shop_order_columns', array( PLL()->filters_columns, 'add_post_column' ), 100 );
			remove_action( 'manage_shop_order_posts_custom_column', array( PLL()->filters_columns, 'post_column' ) );
		}

		add_filter( 'manage_edit-shop_order_columns', array( $this, 'orders_add_column' ), 100 );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'orders_column_value' ), 10, 2 );
	}

	/**
	 * Add metabox in the orders page in admin
	 *
	 * @param $post_type
	 */
	public function order_language_metabox( $post_type ) {

		// Bail if not the correct post types.
		if ( 'shop_order' !== $post_type ) {
			return;
		}

		// Add custom metabox
		add_meta_box(
			'wip_order_metabox',
			__( 'Language', 'woocommerce-polylang-integration' ),
			function () {

				global $post;

				$order    = wc_get_order( $post->ID );
				$order_id = $order->get_id();

				$lang     = $this->orders->get_language( $order_id );
				$lang     = $lang ? $lang : pll_default_language();
				$dropdown = new \PLL_Walker_Dropdown();

				$args = array(
					'name'     => 'post_lang_choice',
					'class'    => 'post_lang_choice tags-input',
					'selected' => $lang,
					'flag'     => true,
				);

				if ( version_compare( POLYLANG_VERSION, '2.6.7', '<' ) ) {
					$dropdown_html = $dropdown->walk( PLL()->model->get_languages_list(), $args );
				} else {
					$dropdown_html = $dropdown->walk( PLL()->model->get_languages_list(), - 1, $args );
				}

				wp_nonce_field( 'pll_language', '_pll_nonce' );

				printf(
					'<p><strong>%1$s</strong></p><label class="screen-reader-text" for="post_lang_choice">%1$s</label><div id="select-post-language">%2$s</div>',
					esc_html__( 'Language', 'woocommerce-polylang-integration' ),
					$dropdown_html
				);
			},
			$post_type,
			'side',
			'high'
		);

		// Remove old metabox
		remove_meta_box( 'ml_box', $post_type, 'side' );

		// Translates the checkout url in order lang for the public customer
		// link for payment that is included in pending order details
		add_filter( 'option_woocommerce_checkout_page_id', 'pll_get_post' );

	}

	/**
	 * Add custom 'Language' column
	 *
	 * @param $columns
	 *
	 * @return mixed
	 */
	public function orders_add_column( $columns ) {
		$columns['language'] = __( 'Language', 'woocommerce-polylang-integration' );

		return $columns;
	}

	/**
	 * Set value for the 'Language' column
	 *
	 * @param $column
	 * @param $post_id
	 */
	public function orders_column_value( $column, $post_id ) {

		switch ( $column ) {
			case 'language':
				$lang = $this->orders->get_language( $post_id );
				$lang = PLL()->model->get_language( $lang );
				$text = isset( $lang->flag ) && $lang->flag ? $lang->flag : $lang->slug;
				printf( '%s', $text );
				break;
		}
	}

	/**
	 * Append 'pll_ajax_backend' to the ajax request urls.
	 * Polylang uses this property to identify if the request is coming from frontend or backend.
	 *
	 * @param $list
	 *
	 * @return array|mixed
	 */
	public function orders_admin_order_actions( $list ) {

		/**if ( isset( $list['status']['actions'] ) ) {
		 * $list = $list['status']['actions'];
		 * }*/

		foreach ( $list as $key => $action ) {

			if ( ! StringUtils::contains( $action['url'], 'admin-ajax.php' ) ) {
				continue;
			}

			$list[ $key ]['url'] = add_query_arg( 'pll_ajax_backend', 1, $action['url'] );
		}

		return $list;

	}


	/**
	 * Create a list of meta keys to be copied / syncrhonized.
	 *
	 * @param $meta_keys
	 * @param $sync
	 * @param $from
	 * @param $to
	 * @param $lang
	 *
	 * @return mixed|void
	 */
	public function taxonomies_copy_term_metas( $meta_keys, $sync, $from, $to, $lang ) {
		$term = get_term( $from );

		// Product categories.
		if ( 'product_cat' === $term->taxonomy ) {
			$_to_copy = array(
				'display_type',
				'thumbnail_id',
			);

			if ( ! $sync ) {
				$_to_copy[] = 'order';
			}

			$meta_keys = array_merge( $meta_keys, $_to_copy );
		}

		if ( ! $sync && StringUtils::starts_with( $term->taxonomy, 'pa_' ) ) {
			$metas = get_term_meta( $from );
			if ( ! empty( $metas ) ) {
				foreach ( array_keys( $metas ) as $key ) {
					if ( StringUtils::starts_with( $key, 'order_' ) ) {
						$meta_keys[] = $key;
					}
				}
			}
		}

		return apply_filters( 'wpi_copy_term_metas', $meta_keys, $sync, $from, $to, $lang );
	}

	/**
	 * Prevent the language filter as WooCommerce is setting orderby to meta_value_num in wc_change_pre_get_terms. This will cause conflicts.
	 *
	 * @param array $args
	 *
	 * @return array
	 */
	public function taxonomies_get_terms_args( $args ) {
		if ( 'all' === $args['get'] && 'meta_value_num' === $args['orderby'] && 'id=>parent' === $args['fields'] ) {
			$args['lang'] = '';
		}

		return $args;
	}

	/**
	 * Translates the thumbnail id.
	 *
	 * @param $value
	 * @param $key
	 * @param $lang
	 *
	 * @return int|mixed
	 */
	public function taxonomies_translate_term_meta( $value, $key, $lang ) {
		switch ( $key ) {
			case 'thumbnail_id':
				$translated = pll_get_post( $value, $lang );
				$value      = $translated ? $translated : $value;
				break;
		}

		return $value;
	}

	/**
	 * Fixes the product category thumbnail in case when the thumbnail
	 * was just uploaded and was assigned the preferred language instead of the current.
	 *
	 * @param $term_id
	 */
	public function stored_product_cat( $term_id ) {
		$thumbnail_id = get_term_meta( $term_id, 'thumbnail_id', true );
		if ( $thumbnail_id ) {
			$lang = $this->terms->get_language( $term_id );
			if ( pll_get_post_language( $thumbnail_id ) !== $lang ) {
				$translations = pll_get_post_translations( $thumbnail_id );
				if ( ! empty( $translations[ $lang ] ) ) {
					update_term_meta( $term_id, 'thumbnail_id', $translations[ $lang ] );  // Set the thumbnail in the correct language.
				} else {
					pll_set_post_language( $thumbnail_id, $lang );
				}
			}
		}
	}

	/**
	 * Save the attribute language when the term is created from the product metabox.
	 *
	 * @param $term_id
	 * @param $tt_id
	 * @param $taxonomy
	 */
	public function taxonomies_create_term( $term_id, $tt_id, $taxonomy ) {

		if ( ! doing_action( 'wp_ajax_woocommerce_add_new_attribute' ) ) {
			// Bail if no wp action.
			return;
		}
		if ( empty( $_POST['pll_post_id'] ) ) {
			// Bail if not polylang?
			return;
		}
		if ( ! StringUtils::starts_with( $taxonomy, 'pa_' ) ) {
			// Bail If not product attribute.
			return;
		}
		check_ajax_referer( 'add-attribute', 'security' );
		pll_set_term_language( $term_id, $this->products->get_language( (int) $_POST['pll_post_id'] ) );
	}

	/**
	 * Replace add_category_fields with this function to populate the meta when creating new translation.
	 */
	public function taxonomies_add_category_fields() {

		if ( isset( $_GET['taxonomy'], $_GET['from_tag'], $_GET['new_lang'] ) ) {

			if ( method_exists( '\WC_Admin_Taxonomies', 'get_instance' ) ) {
				$wc_admin_tax = \WC_Admin_Taxonomies::get_instance();
			} else {
				$wc_admin_tax = $this->get_anonymous_object_from_filter( 'product_cat_edit_form_fields', array(
					'\WC_Admin_Taxonomies',
					'edit_category_fields'
				), 10 );
			}
			if ( empty( $wc_admin_tax ) ) {
				return;
			}
			$term = get_term( (int) $_GET['from_tag'], 'product_cat', OBJECT );
			if ( is_a( $term, 'WP_Term' ) ) {
				$wc_admin_tax->edit_category_fields( $term );
			} else {
				$wc_admin_tax->add_category_fields();
			}
		}
	}

	/**
	 * Filter media library items when adding image to product category
	 */
	public function taxonomies_admin_print_footer_scripts() {
		$screen = get_current_screen();
		if ( empty( $screen ) ) {
			return;
		}
		if ( ! isset( $screen->base ) || ! in_array( $screen->base, array( 'edit-tags', 'term' ) ) ) {
			return;
		}
		if ( 'product_cat' !== $screen->taxonomy ) {
			return;
		}
		?>
        <script type="text/javascript">
            if (typeof jQuery != 'undefined') {
                (function ($) {
                    $.ajaxPrefilter(function (options) {
                        if (options.data.indexOf('action=query-attachments') > 0) {
                            options.data = 'lang=' + $('#term_lang_choice').val() + '&' + options.data;
                        }
                    });
                })(jQuery)
            }
        </script>
		<?php
	}

	/**
	 * Registers string in Polylang settings
	 */
	public function translate_strings() {

		if ( $this->is_pll_settings() ) {
			add_action( 'init', array( $this, 'handle_string_register' ), 120 );
			add_filter( 'pll_sanitize_string_translation', array( $this, 'handle_sanitize_string_translation' ), 5, 3 );
		}
		if ( ! $this->is_pll_frontend() ) {
			add_filter( 'woocommerce_attribute_label', array(
				$this,
				'handle_attribute_labels_translation_admin'
			), 10, 3 );
		}
		add_filter( 'woocommerce_package_rates', array( $this, 'handle_shipping_methods_translation' ) );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'handle_language_switching' ) );
		add_filter( 'woocommerce_attribute_taxonomies', array( $this, 'handle_attribute_labels_translation' ) );
		add_filter( 'woocommerce_find_rates', array( $this, 'handle_tax_rate_labels_translation' ) );
		add_action( 'woocommerce_email_before_order_table', array( $this, 'handle_instructions_translation' ), 5 );
		add_action( 'woocommerce_before_thankyou', array( $this, 'handle_instructions_translation' ) );
		foreach ( $this->get_translatable_string_filters() as $filter => $options ) {
			$filter = $options['setting'] ? 'option_' . $filter : $filter;
			add_filter( $filter, 'pll__' );
		}

		add_action( 'wpi_set_email_notification_language', array( $this, 'handle_email_strings_translation' ) );
	}

	/**
	 * List of translatable eamil notification settings
	 * @return string[]
	 */
	protected function get_translatable_email_settings() {
		return array( 'heading', 'subject', 'additional_content' );
	}

	/**
	 * Listo of translatable string filters
	 * @return array[]
	 */
	protected function get_translatable_string_filters() {
		return array(
			'woocommerce_gateway_title'                               => array(
				'setting'  => 0,
				'textarea' => false
			),
			'woocommerce_gateway_description'                         => array(
				'setting'  => 0,
				'textarea' => false
			),
			'woocommerce_shipping_rate_label'                         => array(
				'setting'  => 0,
				'textarea' => false
			),
			'woocommerce_attribute_label'                             => array(
				'setting'  => 0,
				'textarea' => false
			),
			'woocommerce_rate_label'                                  => array(
				'setting'  => 0,
				'textarea' => false
			),
			'woocommerce_email_footer_text'                           => array(
				'setting'  => 1,
				'textarea' => true,
				'name'     => 'Email Footer Text'
			),
			'woocommerce_demo_store_notice'                           => array(
				'setting'  => 1,
				'textarea' => true,
				'name'     => 'Demo Store Notice'
			),
			'woocommerce_price_display_suffix'                        => array(
				'setting'  => 1,
				'textarea' => false,
				'name'     => 'Price Display Suffix'
			),
			'woocommerce_currency_pos'                                => array(
				'setting'  => 1,
				'textarea' => false,
				'name'     => 'Currency Position'
			),
			'woocommerce_price_thousand_sep'                          => array(
				'setting'  => 1,
				'textarea' => false,
				'name'     => 'Thousand Separator'
			),
			'woocommerce_price_decimal_sep'                           => array(
				'setting'  => 1,
				'textarea' => false,
				'name'     => 'Decimal Separator'
			),
			'woocommerce_registration_privacy_policy_text'            => array(
				'setting'  => 1,
				'textarea' => true,
				'name'     => 'Registration Privacy Policy Text'
			),
			'woocommerce_checkout_privacy_policy_text'                => array(
				'setting'  => 1,
				'textarea' => true,
				'name'     => 'Checkout Privacy Policy Text'
			),
			'woocommerce_checkout_terms_and_conditions_checkbox_text' => array(
				'setting'  => 1,
				'textarea' => false,
				'name'     => 'Terms and Conditions Checkbox Text'
			),
		);
	}

	/**
	 * Translate email strings.
	 *
	 * @param $language
	 */
	public function handle_email_strings_translation( $language ) {
		add_filter( 'woocommerce_email_get_option', array( $this, 'handle_email_settings_translation' ), 10, 4 );

		// Filter the WP Options.
		$wp_options = array( 'option_blogname', 'option_blogdescription', 'option_date_format', 'option_time_format' );
		foreach ( $wp_options as $filter ) {
			add_filter( $filter, 'pll__', 1 );
		}

		// Add the other translations
		$this->translate_strings();

		// Reset email settings when bulk emails are sent.
		foreach ( \WC_Emails::instance()->get_emails() as $email ) {
			$email->init_settings();
		}
	}

	/**
	 * Translate the email notification settings.
	 *
	 * @param $value
	 * @param $email
	 * @param $_value
	 * @param $key
	 *
	 * @return mixed|string
	 */
	public function handle_email_settings_translation( $value, $email, $_value, $key ) {
		if ( in_array( $key, $this->get_translatable_email_settings() ) ) {
			$value = pll__( $value );
		}

		return $value;
	}

	/**
	 * Registers string for translations
	 */
	public function handle_string_register() {

		/**
		 * Register Email settings
		 */
		$emails = \WC_Emails::instance()->get_emails();
		foreach ( $emails as $obj ) {
			if ( 'yes' === $obj->enabled ) {
				foreach ( $obj->settings as $prop => $value ) {
					if ( in_array( $prop, $this->get_translatable_email_settings() ) ) {
						pll_register_string( sprintf( '%s_%s', $prop, $obj->id ), $obj->settings[ $prop ], 'WooCommerce', false );
					}
				}
			}
		}

		/**
		 * Register Payment gateways
		 */
		$gateways = \WC_Payment_Gateways::instance()->payment_gateways();
		foreach ( $gateways as $obj ) {
			if ( 'yes' === $obj->enabled ) {
				foreach ( $obj->settings as $prop => $value ) {
					if ( in_array( $prop, array( 'title', 'description', 'instructions' ) ) ) {
						$is_multiline = $prop !== 'title';
						pll_register_string( sprintf( '%s_%s', $prop, $obj->id ), $obj->settings[ $prop ], 'WooCommerce', $is_multiline );
					}
				}
			}
		}

		/**
		 * Register Shipping Zone related strings
		 */
		$zone = new \WC_Shipping_Zone( 0 );
		foreach ( $zone->get_shipping_methods() as $method ) {
			pll_register_string( 'title_0_' . $method->id, $method->title, 'WooCommerce' );
		}
		foreach ( \WC_Shipping_Zones::get_zones() as $zone ) {
			foreach ( $zone['shipping_methods'] as $method ) {
				pll_register_string( sprintf( 'title_%s_%s', $zone['zone_id'], $method->id ), $method->title, 'WooCommerce' );
			}
		}

		/**
		 * Register various WooCommerce options
		 */
		foreach ( $this->get_translatable_string_filters() as $key => $args ) {
			if ( ! isset( $args['setting'] ) || ! $args['setting'] ) {
				continue;
			}
			$value = get_option( $key );
			$multi = ! empty( $args['textarea'] );
			pll_register_string( $args['name'], $value, 'WooCommerce', $multi );
		}

		/**
		 * Register attribute labels
		 */
		foreach ( wc_get_attribute_taxonomies() as $attr ) {
			pll_register_string( __( 'Attribute', 'woocommerce-polylang-integration' ), $attr->attribute_label, 'WooCommerce' );
		}

		/**
		 * Register the tax rate labels
		 */
		foreach ( $this->get_tax_rate_labels() as $label ) {
			pll_register_string( __( 'Tax rate', 'woocommerce-polylang-integration' ), $label, 'WooCommerce' );
		}
	}

	/**
	 * Sanitize the values before saving
	 *
	 * @param $translation
	 * @param $name
	 * @param $context
	 *
	 * @return array|false|mixed|string|void
	 */
	public function handle_sanitize_string_translation( $translation, $name, $context ) {

		if ( 'WooCommerce' !== $translation ) {
			return $translation;
		}

		// Sanitize the multiline translations
		$multiline = array( 'description', 'instructions' );
		foreach ( $this->get_translatable_string_filters() as $filter => $option ) {
			if ( isset( $option['setting'] ) && $option['setting'] && $option['textarea'] ) {
				array_push( $multiline, $option['name'] );
			}
		}
		if ( in_array( $name, $multiline ) ) {
			$translation = wp_kses_post( trim( $translation ) );
		}

		// Sanitize currency position
		if ( 'Currency Position' === $name ) {
			if ( in_array( $translation, array( 'left', 'right', 'left_space', 'right_space' ) ) ) {
				$translation = get_option( 'woocommerce_currency_pos', 'left' );
			}
		}

		// Sanitize attribute labels
		if ( __( 'Attribute', 'woocommerce-polylang-integration' ) === $name ) {
			$translation = wc_clean( $translation );
		}

		return $translation;
	}

	/**
	 * Reset cart session shipping when user switched the language.
	 */
	public function handle_language_switching() {
		if ( isset( $_COOKIE[ PLL_COOKIE ] ) && pll_current_language() !== $_COOKIE[ PLL_COOKIE ] ) {
			if ( isset( WC()->session->shipping_for_package ) ) {
				unset( WC()->session->shipping_for_package );
			}
		}
	}

	/**
	 * Translates the gateway instructions in thank you and checkout pages.
	 * @return void
	 */
	public function handle_instructions_translation() {
		$payment_gateways = \WC_Payment_Gateways::instance()->get_available_payment_gateways();
		if ( empty( $payment_gateways ) ) {
			return;
		}
		foreach ( $payment_gateways as $key => $payment_gateway ) {
			if ( isset( $payment_gateway->instructions ) ) {
				continue;
			}
			$payment_gateways[ $key ]->instructions = pll__( $payment_gateway->instructions );
		}
	}

	/**
	 * Translates the shipping methods
	 *
	 * @param $list
	 *
	 * @return mixed
	 */
	public function handle_shipping_methods_translation( $list ) {
		foreach ( $list as $key => $rate ) {
			$list[ $key ]->label = pll__( $rate->label );
		}

		return $list;
	}

	/**
	 * Translates the attribute labels
	 *
	 * @param array $list
	 *
	 * @return array
	 */
	public function handle_attribute_labels_translation( $list ) {
		foreach ( $list as $key => $attr ) {
			$list[ $key ]->attribute_label = pll__( $attr->attribute_label );
		}

		return $list;
	}


	/**
	 * Translate attribute label in admin
	 *
	 * @param $label
	 * @param $name
	 * @param $product
	 *
	 * @return mixed|string
	 */
	public function handle_attribute_labels_translation_admin( $label, $name, $product ) {

		global $wp_current_filter;

		if ( empty( $product ) ) {
			return $label;
		}

		if ( ! empty( $wp_current_filter ) && in_array( 'wp_ajax_woocommerce_do_ajax_product_export', $wp_current_filter, true ) ) {
			$lang     = $this->products->get_language( $product->get_id() );
			$language = PLL()->model->get_language( $lang );
			$mo       = new \PLL_MO();
			$mo->import_from_db( $language );
			$label = $mo->translate( $label );
		}

		return $label;
	}

	/**
	 * Translates the tax rates labels
	 *
	 * @param array $list
	 *
	 * @return array
	 */
	public function handle_tax_rate_labels_translation( $list ) {
		foreach ( $list as $key => $rate ) {
			$list[ $key ]['label'] = pll__( $rate['label'] );
		}

		return $list;
	}


	/**
	 * Init system status report
	 */
	public function init_system_status_report() {
		add_action( 'woocommerce_system_status_report', array( $this, 'view_system_status_report' ) );
	}

	/**
	 * Print the system status report
	 */
	public function view_system_status_report() {
	    $report = $this->get_woocommerce_pages_report();
		include trailingslashit( WPIDG_PATH ) . 'templates/system-status-report.php';
	}
}