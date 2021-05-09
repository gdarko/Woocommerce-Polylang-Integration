<?php

namespace CodeVerve\WCPLL\Data;

/**
 * Class TranslatedPost
 * @package CodeVerve\WCPLL\Services
 */
abstract class TranslatedPost extends BasePost {

	/**
	 * The translation gorup name
	 */
	const TRANSLATION_GROUP = 'post_translations';

	/**
	 * Save post translations
	 *
	 * @param array $arr An associative array of translations with language code as key and product id as value.
	 *
	 * @return void
	 */
	public function save_translations( $arr ) {
		pll_save_post_translations( $arr );
	}

	/**
	 * Returns an associative array of translations with language code as key and translation post_id as value
	 *
	 * @param $id
	 *
	 * @return array
	 */
	public function get_translations( $id ) {
		return pll_get_post_translations( $id );
	}

	/**
	 * Returns the post in the current language by the ID or specified lang
	 *
	 * @param $id
	 * @param string $lang
	 *
	 * @return false|int|null
	 */
	public function get( $id, $lang = '' ) {
		return pll_get_post( $id, $lang );
	}
}