<?php

namespace CodeVerve\WCPLL\Data;

/**
 * Class Terms
 * @package CodeVerve\WCPLL\Data
 */
class Terms {

	/**
	 * Returns the term language
	 *
	 * @param $term_id
	 * @param string $field
	 *
	 * @return int
	 */
	public function get_language( $term_id, $field = 'slug' ) {

		if ( is_object( $term_id ) && property_exists( $term_id, 'term_id' ) ) {
			$term_id = $term_id->term_id;
		}

		return absint( pll_get_term_language( $term_id, $field ) );
	}

}