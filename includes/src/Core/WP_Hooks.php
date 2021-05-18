<?php

namespace CodeVerve\WCPLL\Core;

use CodeVerve\WCPLL\Utils\StringUtils;

/**
 * Class WP_Hooks
 * @package CodeVerve\WCPLL\Core
 */
final class WP_Hooks extends Base {

	/**
	 * WP constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_action( 'change_locale', array( $this, 'change_locale' ), 1 );
		add_action( 'pre_option_rewrite_rules', array( $this, 'pre_option_rewrite_rules' ) );
	}

	/**
	 * Load the WooCommerce locale
	 * @return void
	 */
	public function change_locale() {
		if ( is_locale_switched() ) {
			if ( isset( PLL()->filters ) ) {
				remove_filter( 'locale', array( PLL()->filters, 'get_locale' ) );
			}
			add_filter( 'get_user_metadata', array( $this, 'get_user_metadata' ), 10, 5 );
		} else {
			if ( $this->is_pll_frontend() && isset( PLL()->filters ) ) {
				add_filter( 'locale', array( PLL()->filters, 'get_locale' ) );
			}
			remove_filter( 'get_user_metadata', array( $this, 'get_user_metadata' ), 10 );
		}
		WC()->load_plugin_textdomain();
	}

	/**
	 * @param $value
	 * @param $user_id
	 * @param $meta_key
	 *
	 * @return string
	 */
	public function get_user_metadata( $value, $user_id, $meta_key ) {
		if ( $meta_key === 'locale' ) {
			$value = $this->get_locale();
		}

		return $value;
	}

	/**
	 * Init the shop rewrite rules
	 * @inspired_by - The original Polyalng for WC implementation
	 */
	public function pre_option_rewrite_rules() {
		if ( has_filter( 'rewrite_rules_array', array( $this, 'rewrite_rules_array_before' ) ) ) {
			return;
		}
		//add_filter( 'rewrite_rules_array', array( $this, 'rewrite_rules_array_before' ), 5 );
		add_filter( 'rewrite_rules_array', array( $this, 'rewrite_rules_array_after' ), 20 );
	}

	/**
	 * Modify the WordPress rewrite rules for the Shop page to use the slugs from the translations
	 **
	 *
	 * @param $rules
	 *
	 * @return array
	 */
	public function rewrite_rules_array_before( $rules ) {

		$new_rules    = array();
		$ship_page_id = wc_get_page_id( 'shop' );

		if ( empty( $ship_page_id ) ) {
			return $rules;
		}

		$uri          = trailingslashit( get_page_uri( $ship_page_id ) );
		$translations = $this->get_shop_page_slugs();

		if ( count( $translations ) < 2 ) {
			return $rules;
		}

		if ( PLL()->options['force_lang'] > 0 ) {
			// The language is set from the directory, subdomain or domain.
			$translations = array_unique( $translations );
			$new_uri      = '(' . implode( '|', $translations ) . ')/';

			foreach ( $rules as $key => $rule ) {
				if ( ! StringUtils::starts_with( $key, $uri ) ) {
					continue;
				}

				$search  = array();
				$replace = array();

				for ( $i = 1; $i < 9; $i ++ ) {
					array_push( $search, '[' . $i . ']' );
					array_push( $replace, '[' . ( $i + 1 ) . ']' );
				}

				$new_rules[ str_replace( $uri, $new_uri, $key ) ] = str_replace( $search, $replace, $rule );

				unset( $rules[ $key ] );

			}

		} else {

			// Set one rewrite rule per language when language is set from content,
			// also avoid conflict with the product rewrite rules.
			foreach ( $rules as $key => $rule ) {
				if ( ! StringUtils::starts_with( $key, $uri ) ) {
					continue;
				}
				if ( ! StringUtils::contains( $rule, 'post_type=product' ) ) {
					continue;
				}
				foreach ( $translations as $lang => $new_uri ) {
					$new_rules[ str_replace( $uri, $new_uri . '/', $key ) ] = str_replace( '?', "?lang=$lang&", $rule );
				}
				unset( $rules[ $key ] );
			}
		}

		return array_merge( $new_rules, $rules );
	}

	/**
	 * Add rewrite rules for the shop subpages.
	 *
	 * Note: It must be done after wc_fix_rewrite_rules to override the rules created by WC.
	 *
	 * @param $rules
	 *
	 * @return array
	 */
	public function rewrite_rules_array_after( $rules ) {
		global $wp_rewrite;

		$permalinks = wc_get_permalink_structure();
		$new_rules  = array();

		$is_verbose_page_rules = isset( $permalinks['use_verbose_page_rules'] ) && $permalinks['use_verbose_page_rules'];
		$original_shop_page_id = wc_get_page_id( 'shop' );

		if ( ! $is_verbose_page_rules || empty( $original_shop_page_id ) ) {
			return $rules;
		}

		foreach ( pll_get_post_translations( $original_shop_page_id ) as $lang => $shop_page_id ) {

			$subpages = wc_get_page_children( $shop_page_id );

			foreach ( $subpages as $subpage ) {
				$uri = get_page_uri( $subpage );

				foreach ( $rules as $key => $rule ) {
					if ( StringUtils::contains( $rule, 'pagename=' . $uri ) ) {
						unset( $rules[ $key ] );
					}
				}

				if ( isset( PLL()->options['hide_default'] ) && PLL()->options['hide_default'] && PLL()->options['default_lang'] === $lang ) {
					$slug = $uri;
				} else {
					$slug = $lang . '/' . $uri;
				}

				$new_rules[ $slug . '/?$' ] = 'index.php?pagename=' . $uri;
				$wp_generated_rewrite_rules = $wp_rewrite->generate_rewrite_rules( $slug, EP_PAGES, true, true, false, false );
				foreach ( $wp_generated_rewrite_rules as $key => $value ) {
					$wp_generated_rewrite_rules[ $key ] = $value . '&pagename=' . $uri;
				}
				$new_rules = array_merge( $new_rules, $wp_generated_rewrite_rules );
			}
		}

		return array_merge( $new_rules, $rules );
	}

}