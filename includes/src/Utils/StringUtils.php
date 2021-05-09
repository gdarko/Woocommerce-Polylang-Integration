<?php

namespace CodeVerve\WCPLL\Utils;

/**
 * Class StringUtils
 * @package CodeVerve\WCPLL\Utils
 */
class StringUtils {

	/**
	 * Check if str contains a substring
	 *
	 * @param $str
	 * @param $substr
	 *
	 * @return bool
	 */
	public static function contains( $str, $substr ) {

		if ( function_exists( 'str_contains' ) ) {
			return str_contains( $str, $substr );
		} else {
			return strpos( $str, $substr ) !== false;
		}

	}

	/**
	 * Check if string starts with other substring
	 *
	 * @param $str
	 * @param $substr
	 *
	 * @return bool
	 */
	public static function starts_with( $str, $substr ) {
		return strpos( $str, $substr ) === 0;
	}

}