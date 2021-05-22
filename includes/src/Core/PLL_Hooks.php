<?php

namespace CodeVerve\WCPLL\Core;

use CodeVerve\WCPLL\Data\Products;
use CodeVerve\WCPLL\Data\Session;
use CodeVerve\WCPLL\Utils\ArrayUtils;
use CodeVerve\WCPLL\Utils\StringUtils;

/**
 * Class PLL_Hooks
 *
 * Polylang related hooks.
 *
 * @package CodeVerve\WCPLL\Core
 */
final class PLL_Hooks extends Base {

	/**
	 * Taxonomies constructor.
	 */
	public function __construct() {

		parent::__construct();

		add_filter( 'pll_copy_taxonomies', array( $this, 'copy_taxonomies' ), 10, 1 );
		add_filter( 'pll_get_taxonomies', array( $this, 'get_taxonomies' ), 10, 2 );
		add_filter( 'pll_get_post_types', array( $this, 'get_post_types' ), 10, 2 );
		add_filter( 'pll_copy_post_metas', array( $this, 'copy_post_metas' ), 5, 5 );
		add_filter( 'pll_translate_post_meta', array( $this, 'translate_post_meta' ), 5, 5 );
		add_filter( 'pll_modify_rewrite_rule', array( $this, 'modify_rewrite_rule' ), 10, 4 );
		add_filter( 'pll_set_language_from_query', array( $this, 'set_language_from_query' ), 5, 2 );

		if ( did_action( 'pll_language_defined' ) ) {
			$this->language_defined();
		} else {
			add_action( 'pll_language_defined', array( $this, 'language_defined' ), 1 );
			add_action( 'pll_translate_labels', array( $this, 'translate_labels' ) );

			if ( ! empty( $_REQUEST['lang'] ) && $lang = PLL()->model->get_language( sanitize_key( $_REQUEST['lang'] ) ) ) {
				PLL()->curlang             = $lang;
				$GLOBALS['text_direction'] = $lang->is_rtl ? 'rtl' : 'ltr';
				do_action( 'pll_language_defined', $lang->slug, $lang );
			}
		}

	}

	/**
	 * Fires after the language is defined.
	 */
	public function language_defined() {

		// If language is from content
		if ( ! PLL()->options['force_lang'] ) {
			if ( $this->pretty_permalinks_enabled() ) {
				// Pretty permalinks fixes.
				add_filter( 'post_type_archive_link', array( $this, 'post_type_archive_link' ), 99, 2 ); // After PLL.
			} else {
				// Plain permalinks fixes.
				add_filter( 'pll_check_canonical_url', array( $this, 'check_canonical_url' ) );
				add_filter( 'pll_translation_url', array( $this, 'translation_url_plain_permalinks' ), 10, 2 );
			}
		}

		// Widgets url
		add_filter( 'pll_home_url_white_list', array( $this, 'home_url_white_list' ) );

		// Widget price fitler fix
		if ( PLL()->options['force_lang'] > 1 ) {
			add_filter( 'home_url', array( $this, 'home_url' ), 10, 2 );
		}
	}

	/**
	 * Reset geo names when the language is set from the content.
	 */
	public function translate_labels() {
		unset( WC()->countries->countries );
		unset( WC()->countries->continents );
		unset( WC()->countries->states );
	}


	/**
	 * Modify the redirection url from shop page redirect to the product archive if plain permalinks are used to work.
	 *
	 * @param $redirect_url
	 *
	 * @return false|string
	 */
	public function check_canonical_url( $redirect_url ) {
		return is_post_type_archive( 'product' ) ? false : $redirect_url;
	}

	/**
	 * Fix for the translation urls of the shop page / product archive if plain permalinks are used.
	 *
	 * @param string $url
	 * @param string $lang
	 *
	 * @return string
	 */
	public function translation_url_plain_permalinks( $url, $lang ) {

		if ( ! is_post_type_archive( 'product' ) ) {
			return $url;
		}

		$lang = PLL()->model->get_language( $lang );

		$front = get_option( 'show_on_front' );

		if ( PLL()->options['hide_default'] && 'page' === $front && PLL()->options['default_lang'] === $lang->slug ) {
			$pages        = pll_languages_list( array( 'fields' => 'page_on_front' ) );
			$shop_page_id = wc_get_page_id( 'shop' );
			if ( in_array( $shop_page_id, $pages ) ) {
				return $lang->home_url;
			}
		}

		$url = get_post_type_archive_link( 'product' );
		$url = PLL()->links_model->switch_language_in_link( $url, $lang );
		$url = PLL()->links_model->remove_paged_from_link( $url );

		return $url;
	}


	/**
	 * Fixes the home url in widgets
	 *
	 * @param $arr
	 *
	 * @return mixed
	 */
	public function home_url_white_list( $arr ) {

		$files = array();

		if ( version_compare( WC()->version, '3.3', '<' ) ) {
			array_push( $files, 'class-wc-widget-layered-nav.php' );
			array_push( $files, 'class-wc-widget-layered-nav-filters.php' );
			array_push( $files, 'class-wc-widget-rating-filter.php' );
		} else {
			array_push( $files, 'abstract-wc-widget.php' );
		}

		if ( PLL()->options['force_lang'] > 0 ) {
			// If language is set from content, do not redirect.
			array_push( $files, 'class-wc-widget-product-categories.php' );
		} else if ( PLL()->options['force_lang'] > 1 ) {
			array_push( $files, 'class-wc-widget-price-filter.php' );
		}

		foreach ( $files as $file ) {
			array_push( $arr, array( 'file' => $file ) );
		}

		return $arr;
	}

	/**
	 * Modify form action url of the widget price filter for subdomaisn or multiple domains.
	 *
	 * @param $url
	 * @param $path
	 *
	 * @return mixed
	 */
	public function home_url( $url, $path ) {

		global $wp;

		$path    = trailingslashit( $path );
		$current = ! empty( $wp->request ) ? trailingslashit( $wp->request ) : '';
		if ( $path === $current ) {
			$url = PLL()->links_model->switch_language_in_link( $url, PLL()->curlang );
		}

		return $url;
	}


	/**
	 * Remove the variation
	 *
	 * @param $taxonomies
	 * @param $is_settings
	 *
	 * @return array
	 */
	public function get_taxonomies( $taxonomies, $is_settings ) {

		if ( isset( $taxonomies['product_shipping_class'] ) ) {
			unset( $taxonomies['product_shipping_class'] );
		}

		$translated = $this->get_translated_taxonomies();

		if ( $is_settings ) {
			$final = array_diff( $taxonomies, $translated );
		} else {
			$final = array_merge( $taxonomies, $translated );
		}

		return $final;
	}

	/**
	 * Add woocommerce taxonomies to the list of taxonomies that will be copied when creating translation of a product
	 *
	 * @param $taxonomies
	 *
	 * @return array
	 */
	public function copy_taxonomies( $taxonomies ) {

		$native = array(
			'product_type',
			'product_shipping_class',
			'product_visibility'
		);

		$translated = $this->get_translated_taxonomies();
		$taxonomies = array_merge( $taxonomies, $native );
		$taxonomies = array_merge( $taxonomies, $translated );

		return $taxonomies;
	}

	/**
	 * Add Products and Product Variations to the translated polylang post types.
	 *
	 * @param array $types
	 * @param bool $is_settings
	 *
	 * @return array
	 */
	public function get_post_types( $types, $is_settings ) {

		$native = array( 'product', 'product_variation', 'shop_order' );

		if ( $is_settings ) {
			$types = array_diff( $types, $native );
		} else {
			$types = array_merge( $types, $native );
		}

		return $types;
	}

	/**
	 * Returns list of custom fields names
	 *
	 * @param array $metas
	 * @param bool $sync
	 * @param int $from
	 * @param int $to
	 * @param string $lang
	 *
	 * @return array
	 *
	 */
	public function copy_post_metas( $metas, $sync, $from, $to, $lang ) {
		if ( ! in_array( get_post_type( $from ), array( 'product', 'product_variation' ) ) ) {
			// Bail if not product or product variation.
			return $metas;
		}

		$to_copy = Products::get_legacy_meta_keys( true );

		foreach ( array_keys( get_post_custom( $from ) ) as $key ) {
			if ( StringUtils::starts_with( $key, 'attribute_' ) ) {
				$to_copy[] = $key;
			}
		}

		if ( $this->products->should_copy_texts( $from, $to, $sync ) ) {
			$additional = array(
				'_button_text',
				'_product_url',
				'_purchase_note',
				'_variation_description',
			);
			$to_copy    = array_diff( $to_copy, $additional );
		}

		$combined = array_combine( $to_copy, $to_copy );
		$to_copy  = array_unique( apply_filters( 'wpi_copy_post_metas', $combined, $sync, $from, $to, $lang ) );
		$metas    = array_merge( $metas, $to_copy );

		return $metas;
	}


	/**
	 * Attempt to translate product meta data before it is copied or syncrhonized.
	 *
	 * @param mixed $value
	 * @param string $key
	 * @param string $lang
	 * @param int $from
	 * @param int $to
	 *
	 * @return mixed
	 *
	 */
	public function translate_post_meta( $value, $key, $lang, $from, $to ) {

		if ( ! in_array( get_post_type( $from ), array( 'product', 'product_variation' ) ) ) {
			return $value;
		}

		if ( StringUtils::starts_with( $key, 'attribute_' ) ) {
			$tax = substr( $key, 10 );
			if ( taxonomy_exists( $tax ) && $value ) {
				$terms = get_terms( $tax, array( 'slug' => $value, 'hide_empty' => false, 'lang' => '' ) );
				if ( is_array( $terms ) && ( $term = reset( $terms ) ) && $translated_id = pll_get_term( $term->term_id, $lang ) ) {
					$term  = get_term( $translated_id, $tax );
					$value = $term->slug;
				}
			}
		} else {
			$props = Products::get_legacy_meta_keys( false );
			if ( isset( $props[ $key ] ) ) {
				$value = $this->products->translate_product_property( $value, $props[ $key ], $lang );
			}
		}

		$value = apply_filters( 'wpi_translate_product_meta', $value, $key, $lang, $from, $to );

		return $value;
	}

	/**
	 * Prevents Polylang from modifying the rewrite rules.
	 *
	 * @needs_rewrite
	 *
	 * @param bool $modify
	 * @param array $rules
	 * @param string $filter - Current rules being modified
	 * @param string|bool $archive - Current archive or false if not a post type archive.
	 *
	 * @return bool
	 *
	 */
	public function modify_rewrite_rule( $modify, $rules, $filter, $archive ) {

		if ( empty( $rule ) ) {
			return $modify;
		}

		$rule = ArrayUtils::first( $rules );

		if ( 'root' === $filter && StringUtils::contains( $rule, 'wc-api=$matches[2]' ) ) {
			return false;
		} else if ( ! PLL()->options['force_lang'] && 'rewrite_rules_array' === $filter && 'product' === $archive ) {
			return false;
		}

		return $modify;
	}

	/**
	 * Fix the query vars on translated front page if front page is a WooCommerce page (shop/my-account/checkout)
	 *
	 * @needs_rewrite
	 *
	 * @param $lang
	 * @param $query
	 *
	 * @return mixed
	 */
	public function set_language_from_query( $lang, $query ) {

		$languages = PLL()->model->get_languages_list();
		$pages     = wp_list_pluck( $languages, 'page_on_front' );

		// Shop on front.
		if ( in_array( wc_get_page_id( 'shop' ), $pages ) ) {
			$lang = $this->set_language_from_query_on_front_page( $query, $pages, $languages, $lang );
		}
		// My Account and checkout endpoints.
		$pids = $this->get_woocommerce_page_ids( array( 'myaccount', 'checkout' ) );
		if ( array_intersect( $pids, $pages ) && array_intersect( array_keys( $query->query ), WC()->query->get_query_vars() ) ) {
			$lang = $this->set_language_from_query_on_myaccount_and_checkout_pages( $query, $lang );
		}

		return $lang;
	}

	/**
	 * Sets the language on the shop page from query. (inspired by polylang version)
	 *
	 * @param $query
	 * @param $pages
	 * @param $languages
	 * @param $lang
	 *
	 * @return mixed
	 */
	private function set_language_from_query_on_front_page( &$query, $pages, $languages, $lang ) {
		if ( ( PLL()->options['redirect_lang'] || PLL()->options['hide_default'] ) && $this->is_preview( $query ) && is_tax( 'language' ) ) {
			$lang                        = PLL()->model->get_language( get_query_var( 'lang' ) );
			$query->is_home              = false;
			$query->is_tax               = false;
			$query->is_page              = true;
			$query->is_post_type_archive = true;
			$query->set( 'page_id', $lang->page_on_front );
			$query->set( 'post_type', 'product' );
			unset( $query->query_vars['lang'], $query->queried_object ); // Reset queried object.
		} elseif ( ( $page_id = $this->get_queried_page_id( $query ) ) && in_array( $page_id, $pages ) ) {
			$pos                         = array_search( $page_id, $pages );
			$lang                        = $languages[ $pos ];
			$query->is_home              = false;
			$query->is_page              = true;
			$query->is_post_type_archive = true;
			$query->set( 'page_id', $page_id );
			$query->set( 'post_type', 'product' );
		} elseif ( is_post_type_archive( 'product' ) && ! empty( PLL()->curlang ) ) {
			$query->is_page = true;
			$query->set( 'page_id', PLL()->curlang->page_on_front );
		} elseif ( is_post_type_archive( 'product' ) && ! empty( $query->query_vars['lang'] ) ) {
			$lang = PLL()->model->get_language( $query->query_vars['lang'] );
			if ( ! empty( $lang ) ) {
				$query->is_page = true;
				$query->set( 'page_id', $lang->page_on_front );
			}
		}

		return $lang;
	}

	/**
	 * Set the language from query on the My Account and Checkout endpoints
	 *
	 * @param $query
	 * @param $lang
	 *
	 * @return mixed
	 */
	private function set_language_from_query_on_myaccount_and_checkout_pages( &$query, $lang ) {
		if ( ! $this->get_queried_page_id( $query ) ) {
			$lang = PLL()->model->get_language( get_query_var( 'lang' ) );
			if ( ! $lang ) {
				$lang = PLL()->model->get_language( PLL()->options['default_lang'] );
			}
			$query->is_home     = false;
			$query->is_tax      = false;
			$query->is_archive  = false;
			$query->is_page     = true;
			$query->is_singular = true;
			$query->set( 'page_id', $lang->page_on_front );
			unset( $query->queried_object );
		}
		add_filter( 'redirect_canonical', '__return_false' );
		add_filter( 'pll_check_canonical_url', '__return_false' );

		return $lang;
	}

	/**
	 * Is preview?
	 *
	 * @param $query
	 *
	 * @return bool
	 */
	private function is_preview( $query ) {
		return ( count( $query->query ) === 1
		         || ( ( is_preview() || is_paged() || ! empty( $query->query['page'] ) ) && count( $query->query ) === 2 )
		         || ( ( is_preview() && ( is_paged() || ! empty( $query->query['page'] ) ) ) && count( $query->query ) === 3 ) );
	}

}