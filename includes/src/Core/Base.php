<?php

namespace CodeVerve\WCPLL\Core;

use CodeVerve\WCPLL\Data\Orders;
use CodeVerve\WCPLL\Data\Products;
use CodeVerve\WCPLL\Data\Terms;
use CodeVerve\WCPLL\Utils\StringUtils;

/**
 * Class Base
 * @package CodeVerve\WCPLL\Core
 */
class Base {

	/**
	 * Temporary data store
	 * @var array
	 */
	protected $store = array();

	/**
	 * The products repository
	 * @var Products
	 */
	protected $products;

	/**
	 * The orders repository
	 * @var Orders
	 */
	protected $orders;

	/**
	 * The Terms instance
	 * @var Terms
	 */
	protected $terms;

	/**
	 * Base constructor.
	 */
	public function __construct() {
		$this->products = new Products();
		$this->orders   = new Orders();
		$this->terms    = new Terms();
	}

	/**
	 * Returns the WooCommerce permalinks
	 *
	 * @return false|mixed|void
	 */
	protected function get_permalinks() {
		return get_option( 'woocommerce_permalinks' );
	}

	/**
	 * Check if pretty permalinks are enabled.
	 * @return false|mixed|void
	 */
	protected function pretty_permalinks_enabled() {
		return get_option( 'permalink_structure' );
	}

	/**
	 * Returns list of taxonomies that are translated by Polylang
	 * Note: This list includes WooCommerce Native ones + variation ones
	 *
	 * @retun array
	 */
	protected function get_translated_taxonomies() {
		$woo_taxonomies = wp_list_pluck( wc_get_attribute_taxonomies(), 'attribute_name' );
		foreach ( $woo_taxonomies as $key => $tax ) {
			$woo_taxonomies[ $key ] = 'pa_' . $tax;
		}

		return array_merge( array( 'product_cat', 'product_tag' ), $woo_taxonomies );
	}

	/**
	 * Return the current locale
	 * @return string
	 */
	protected function get_locale() {
		return \get_locale();
	}

	/**
	 * Returns the order id
	 *
	 * @param $order
	 *
	 * @return int|mixed|string|null
	 */
	protected function get_order_id( $order ) {
		if ( is_numeric( $order ) ) {
			return $order;
		} else if ( is_object( $order ) && method_exists( $order, 'get_id' ) ) {
			return $order->get_id();
		} else if ( is_array( $order ) && isset( $order['order_id'] ) ) {
			return $order['order_id'];
		} else {
			return null;
		}
	}

	/**
	 * Returns the order id
	 *
	 * @param $product
	 *
	 * @return int|mixed|string|null
	 */
	protected function get_product_id( $product ) {
		if ( is_numeric( $product ) ) {
			return $product;
		} else if ( is_object( $product ) && method_exists( $product, 'get_id' ) ) {
			return $product->get_id();
		} else if ( is_array( $product ) && isset( $product['product_id'] ) ) {
			return $product['product_id'];
		} else {
			return null;
		}
	}

	/**
	 * Returns the order id
	 *
	 * @param $user
	 *
	 * @return int|mixed|string|null
	 */
	protected function get_user_id( $user ) {
		if ( is_numeric( $user ) ) {
			return $user;
		} else if ( is_object( $user ) && isset( $user->ID ) ) {
			return $user->ID;
		} else if ( is_array( $user ) && isset( $user['ID'] ) ) {
			return $user['ID'];
		} else if ( is_array( $user ) && isset( $user['user_id'] ) ) {
			return $user['user_id'];
		} else {
			return null;
		}
	}

	/**
	 * Returns the user locale
	 *
	 * @param $user_id
	 *
	 * @return mixed
	 */
	public function get_user_locale( $user_id ) {
		return get_post_meta( $user_id, 'locale', true );
	}

	/**
	 * Update the user locale
	 *
	 * @param $user_id
	 * @param $locale
	 */
	public function set_user_locale( $user_id, $locale ) {
		update_post_meta( $user_id, 'locale', $locale );
	}

	/**
	 * Is front end?
	 * @return bool
	 */
	protected function is_pll_frontend() {
		return PLL() instanceof \PLL_Frontend;
	}

	/**
	 * Is Polylang settins?
	 * @return bool
	 */
	protected function is_pll_settings() {
		return PLL() instanceof \PLL_Settings;
	}

	/**
	 * After the language has been defined
	 */
	protected function language_defined() {
		return did_action( 'pll_language_defined' );
	}

	/**
	 * Add lang query parameter to link
	 *
	 * @param $url
	 *
	 * @return string
	 */
	public function add_language_query_arg( $url ) {
		return add_query_arg( 'lang', pll_current_language(), $url );
	}

	/**
	 * Set tmp value
	 *
	 * @param $key
	 * @param $value
	 */
	protected function set_tmp_value( $key, $value ) {
		$this->store[ $key ] = $value;
	}

	/**
	 * Return tmp value
	 *
	 * @param $key
	 * @param null $defualt
	 *
	 * @return mixed|null
	 */
	protected function get_tmp_value( $key, $defualt = null ) {
		return isset( $this->store[ $key ] ) ? $this->store[ $key ] : $defualt;
	}


	/**
	 * Returns the terms for specific taxonomy
	 *
	 * @param $taxonomy
	 * @param $args
	 *
	 * @return int|\WP_Error|\WP_Term[]
	 */
	protected function get_terms( $taxonomy, $args ) {
		return \get_terms( $taxonomy, $args );
	}


	/**
	 * Return the shop page slugs (along with the ones from the translations)
	 *
	 * @return array
	 */
	protected function get_shop_page_slugs() {

		$list         = array();
		$id           = wc_get_page_id( 'shop' );
		$translations = pll_get_post_translations( $id );

		foreach ( $translations as $lang => $id ) {
			$list[ $lang ] = get_page_uri( $id );
		}

		return $list;
	}

	/**
	 * Returns the translated product id if translated or the current product id if not translated
	 *
	 * @param $product_id
	 *
	 * @return int
	 */
	protected function get_translated_product( $product_id ) {
		$translated_product_id = $this->products->get( $product_id );
		if ( $translated_product_id ) {
			return $translated_product_id;
		} else {
			return $product_id;
		}
	}

	/**
	 * Returns the translated term id if translated or the current term id if not translated
	 *
	 * @param int $term_id
	 *
	 * @return int
	 */
	protected function get_translated_term( $term_id ) {
		$translated_term_id = pll_get_term( $term_id );
		if ( $translated_term_id ) {
			return $translated_term_id;
		} else {
			return $term_id;
		}
	}


	/**
	 * Returns the translated cart item.
	 *
	 * @param $item
	 * @param $lang
	 *
	 * @return mixed|void
	 */
	protected function get_translated_cart_item( $item, $lang ) {

		$orig_lang = $this->products->get_language( $item['product_id'] );

		$item['product_id'] = $this->products->get( $item['product_id'], $lang );

		// Variable product.
		$translated_variation_id = ! empty( $item['variation_id'] ) ? $this->products->get( $item['variation_id'], $lang ) : '';
		if ( $translated_variation_id ) {

			// Translate variation product
			$item['variation_id'] = $translated_variation_id;
			if ( ! empty( $item['data'] ) ) {
				$item['data'] = wc_get_product( $item['variation_id'] );
			}

			// Translate variation attributes
			if ( ! empty( $item['variation'] ) ) {
				foreach ( $item['variation'] as $name => $value ) {
					if ( '' === $value ) {
						continue;
					}
					$taxonomy = wc_attribute_taxonomy_name( str_replace( 'attribute_pa_', '', urldecode( $name ) ) );
					if ( ! taxonomy_exists( $taxonomy ) ) {
						continue;
					}
					$terms = get_terms( $taxonomy, array( 'slug' => $value, 'lang' => $orig_lang ) );
					if ( empty( $terms ) || ! is_array( $terms ) ) {
						continue;
					}
					$term    = reset( $terms );
					$term_id = pll_get_term( $term->term_id, $lang );
					if ( $term_id ) {
						$term                       = get_term( $term_id, $taxonomy );
						$item['variation'][ $name ] = $term->slug;
					}
				}
			}
		} elseif ( ! empty( $item['data'] ) ) {
			// Simple product.
			$item['data'] = wc_get_product( $item['product_id'] );
		}

		$item           = apply_filters( 'wpi_cart_item_translated', $item, $lang );
		$cart_item_data = (array) apply_filters( 'wpi_add_cart_item_data', array(), $item );

		$item['key'] = WC()->cart->generate_cart_id( $item['product_id'], $item['variation_id'], $item['variation'], $cart_item_data );

		return $item;
	}

	/**
	 * Returns the translated cart contents
	 *
	 * @param $contents
	 * @param string $lang
	 *
	 * @return mixed|void
	 */
	protected function get_translated_cart_contents( $contents, $lang = '' ) {
		if ( empty( $lang ) ) {
			$lang = pll_current_language();
		}
		foreach ( $contents as $key => $item ) {
			if( $item['product_id'] ) {
				$translated_product_id = $this->products->get( $item['product_id'], $lang );
				if ( $translated_product_id && $translated_product_id !== $item['product_id'] ) {
					unset( $contents[ $key ] );
					$item                     = $this->get_translated_cart_item( $item, $lang );
					$contents[ $item['key'] ] = $item;
					do_action( 'wpi_cart_item_translated', $item, $key );
				}
			}
		}
		return apply_filters( 'wpi_cart_contents_translated', $contents, $lang );
	}

	/**
	 * Translate the cart contents
	 * @return void
	 */
	protected function replace_cart_contents_with_translated() {
		WC()->cart->cart_contents         = $this->get_translated_cart_contents( WC()->cart->cart_contents );
		WC()->cart->removed_cart_contents = $this->get_translated_cart_contents( WC()->cart->removed_cart_contents );
	}

	/**
	 * Recalculate the shipping
	 */
	protected function recalculate_shipping() {
		WC()->shipping()->calculate_shipping( WC()->cart->get_shipping_packages() );
	}

	/**
	 * Get the queried page id.
	 *
	 * @param $query
	 *
	 * @return int|mixed
	 */
	protected function get_queried_page_id( $query ) {
		if ( ! empty( $query->query_vars['pagename'] ) && isset( $query->queried_object_id ) ) {
			return $query->queried_object_id;
		}

		if ( isset( $query->query_vars['page_id'] ) ) {
			return $query->query_vars['page_id'];
		}

		return 0;
	}

	/**
	 * Return the order notification actions that accept order in parameter
	 * @return array
	 */
	protected function get_order_email_notification_actions() {

		// From WC_Emails::init_transactional_emails()
		$actions                     = array(
			'woocommerce_order_status_pending_to_processing',
			'woocommerce_order_status_pending_to_completed',
			'woocommerce_order_status_processing_to_cancelled',
			'woocommerce_order_status_pending_to_failed',
			'woocommerce_order_status_pending_to_on-hold',
			'woocommerce_order_status_failed_to_processing',
			'woocommerce_order_status_failed_to_completed',
			'woocommerce_order_status_failed_to_on-hold',
			'woocommerce_order_status_cancelled_to_processing',
			'woocommerce_order_status_cancelled_to_completed',
			'woocommerce_order_status_cancelled_to_on-hold',
			'woocommerce_order_status_on-hold_to_processing',
			'woocommerce_order_status_on-hold_to_cancelled',
			'woocommerce_order_status_on-hold_to_failed',
			'woocommerce_order_status_completed',
			'woocommerce_order_fully_refunded',
			'woocommerce_order_partially_refunded',
		);
		$email_notifications_actions = array();
		foreach ( $actions as $action ) {
			$email_notifications_actions[] = sprintf( '%s_notification', $action );
		}

		return $email_notifications_actions;
	}

	/**
	 * Return the user email notification actions that accept user in parameter
	 * @return array
	 */
	protected function get_user_email_notification_actions() {
		$actions                     = array(
			'woocommerce_created_customer',
			'woocommerce_reset_password',
			// other...
		);
		$email_notifications_actions = array();
		foreach ( $actions as $action ) {
			$email_notifications_actions[] = sprintf( '%s_notification', $action );
		}

		return $email_notifications_actions;
	}

	/**
	 * Return the current language
	 * @return bool|\PLL_Language|string
	 */
	public function get_current_dashboard_language() {
		if ( ! empty( PLL()->curlang ) ) {
			$current_lang = PLL()->curlang->slug; // lang filter
		} else if ( $curlang = PLL()->model->get_language( get_user_locale() ) ) {
			$current_lang = $curlang->slug; // current adin lang
		} else {
			$current_lang = pll_default_language();
		}

		return $current_lang;

	}

	/**
	 *  Remove anonymous object filter
	 * @see http://wordpress.stackexchange.com/questions/57079/how-to-remove-a-filter-that-is-an-anonymous-object/57088#57088
	 *
	 * @param $tag
	 * @param $method
	 * @param int $priority
	 * @param int $accepted_args
	 */
	protected function remove_anonymous_object_filter( $tag, $method, $priority = 10, $accepted_args = 1 ) {
		if ( ! empty( $GLOBALS['wp_filter'][ $tag ][ $priority ] ) ) {
			foreach ( $GLOBALS['wp_filter'][ $tag ][ $priority ] as $function ) {
				if ( is_array( $function ) && is_array( $function['function'] ) && is_a( $function['function'][0], $method[0] ) && $method[1] === $function['function'][1] ) {
					remove_filter( $tag, array( $function['function'][0], $method[1] ), $priority );
				}
			}
		}
	}

	/**
	 * Retrieve anonymous object from filter.
	 * @see https://wordpress.stackexchange.com/questions/57079/how-to-remove-a-filter-that-is-an-anonymous-object/57088#57088
	 *
	 * @param $tag
	 * @param $method
	 * @param int $priority
	 * @param int $accepted_args
	 *
	 * @return object|null
	 */
	protected function get_anonymous_object_from_filter( $tag, $method, $priority = 10, $accepted_args = 1 ) {
		if ( ! empty( $GLOBALS['wp_filter'][ $tag ][ $priority ] ) ) {
			foreach ( $GLOBALS['wp_filter'][ $tag ][ $priority ] as $function ) {
				if ( is_array( $function ) && is_array( $function['function'] ) && is_a( $function['function'][0], $method[0] ) && $method[1] === $function['function'][1] ) {
					return $function['function'][0];
				}
			}
		}

		return null;
	}


	/**
	 * Generates a unique slug for a given product. We do this so that we can override the
	 * behavior of wp_unique_post_slug(). The normal slug generation will run single
	 * select queries on every non-unique slug, resulting in very bad performance.
	 *
	 * @param \WC_Product $product The product to generate a slug for.
	 *
	 * @since 3.9.0
	 */
	protected function generate_unique_slug( $product ) {
		global $wpdb;

		// We want to remove the suffix from the slug so that we can find the maximum suffix using this root slug.
		// This will allow us to find the next-highest suffix that is unique. While this does not support gap
		// filling, this shouldn't matter for our use-case.
		$root_slug = preg_replace( '/-[0-9]+$/', '', $product->get_slug() );

		$results = $wpdb->get_results(
			$wpdb->prepare( "SELECT post_name FROM $wpdb->posts WHERE post_name LIKE %s AND post_type IN ( 'product', 'product_variation' )", $root_slug . '%' )
		);

		// The slug is already unique!
		if ( empty( $results ) ) {
			return;
		}

		// Find the maximum suffix so we can ensure uniqueness.
		$max_suffix = 1;
		foreach ( $results as $result ) {
			// Pull a numerical suffix off the slug after the last hyphen.
			$suffix = intval( substr( $result->post_name, strrpos( $result->post_name, '-' ) + 1 ) );
			if ( $suffix > $max_suffix ) {
				$max_suffix = $suffix;
			}
		}

		$product->set_slug( $root_slug . '-' . ( $max_suffix + 1 ) );
	}


	/**
	 * Returns woocommerce pages ids.
	 *
	 * @param $keys
	 *
	 * @return array
	 */
	protected function get_woocommerce_page_ids( $keys ) {
		$ids = array();
		foreach ( $keys as $key ) {
			$id = wc_get_page_id( $key );
			if ( ! empty( $id ) ) {
				array_push( $ids, $id );
			}
		}

		return $ids;
	}

	/**
	 * Return tax rate labels
	 * @return array
	 */
	protected function get_tax_rate_labels() {
		global $wpdb;

		return (array) $wpdb->get_col( "SELECT tax_rate_name FROM {$wpdb->prefix}woocommerce_tax_rates" );
	}


	/**
	 * Detect problem with the Polylang setup.
	 *
	 * @return array
	 */
	public function get_woocommerce_pages_report() {

		$pages = array(
			array(
				'name'      => __( 'Shop', 'woocommerce-polylang-integration' ),
				'option'    => 'woocommerce_shop_page_id',
				'shortcode' => '',
			),
			array(
				'name'      => __( 'Cart', 'woocommerce-polylang-integration' ),
				'option'    => 'woocommerce_cart_page_id',
				'shortcode' => apply_filters( 'woocommerce_cart_shortcode_tag', 'woocommerce_cart' )
			),
			array(
				'name'      => __( 'Checkout', 'woocommerce-polylang-integration' ),
				'option'    => 'woocommerce_checkout_page_id',
				'shortcode' => apply_filters( 'woocommerce_checkout_shortcode_tag', 'woocommerce_checkout' )
			),
			array(
				'name'      => __( 'My Account', 'woocommerce-polylang-integration' ),
				'option'    => 'woocommerce_myaccount_page_id',
				'shortcode' => apply_filters( 'woocommerce_my_account_shortcode_tag', 'woocommerce_my_account' )
			),
			array(
				'name'      => __( 'Terms & Conditions', 'woocommerce-polylang-integration' ),
				'option'    => 'woocommerce_terms_page_id',
				'shortcode' => '',
			)
		);

		$report    = array();
		$languages = pll_languages_list();

		foreach ( $pages as $page ) {
			$page_id = get_option( $page['option'] );

			$page_report = array(
				'is_error'  => false,
				'message'   => '',
				'page_id'   => $page_id,
				'page_name' => $page['name'],
				'help'      => sprintf( __( 'The status of your WooCommerce shop\'s "%s" page translations.', 'woocommerce-polylang-integration' ), $page['name'] ),
			);

			if ( ! $page_id ) {
				$page_report['is_error'] = true;
				$page_report['message']  = __( 'Page missing', 'woocommerce-polylang-integration' );
			} else {
				$translations         = pll_get_post_translations( $page_id );
				$missing_translations = array_diff( $languages, array_keys( $translations ) );
				if ( ! empty( $missing_translations ) ) {
					$missing_translations_names = array();
					foreach ( $missing_translations as $key => $slug ) {
						$missing_lang = PLL()->model->get_language( $slug );
						if ( $missing_lang ) {
							array_push( $missing_translations_names, $missing_lang->name );
						}
						$page_report['is_error'] = true;
						$page_report['message']  = sprintf(
							_n( 'Missing translation: %s', 'Missing translations: %s', count( $missing_translations_names ), 'woocommerce-polylang-integration' ),
							implode( ', ', $missing_translations_names )
						);
					}
				} else {
					$invalid_translations = array();
					foreach ( $translations as $lang => $translation_id ) {
						$content = get_post_field( 'post_content', $translation_id );
						if ( ! empty( $page['shortcode'] ) ) {
							$shortcode = sprintf( '[%s]', $page['shortcode'] );
							if ( ! StringUtils::contains( $content, $shortcode ) ) {
								$language               = PLL()->model->get_language( $lang );
								$language               = ! empty( $language->name ) ? $language->name : $lang;
								$invalid_translations[] = $language;
							}
						}
					}
					if ( ! empty( $invalid_translations ) ) {
						$page_report['is_error'] = true;
						$page_report['message']  = sprintf(
							_n( 'Missing shortcode for translation: %s', 'Missing shortcode for translations: %s', count( $invalid_translations ), 'woocommerce-polylang-integration' ),
							implode( ', ', $invalid_translations )
						);
					}
				}
			}
			$report[ $page['name'] ] = $page_report;
		}

		return $report;
	}

}