<?php

namespace CodeVerve\WCPLL\Utils;

/**
 * Class ArrayUtils
 * @package CodeVerve\WCPLL\Utils
 */
class ArrayUtils {

	/**
	 * @param $arr
	 *
	 * @return mixed
	 */
	public static function first( $arr ) {
		return array_shift( $arr );
	}
}