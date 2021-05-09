<?php

namespace CodeVerve\WCPLL\Data;

/**
 * Class BasePost
 * @package CodeVerve\WCPLL\Data
 */
abstract class BasePost {

	/**
	 * Set language for specific post
	 *
	 * @param $ID
	 * @param $language
	 */
	public function set_language( $ID, $language ) {
		pll_set_post_language( $ID, $language );
	}

	/**
	 * Returns the post language
	 *
	 * @param $ID
	 * @param $field
	 *
	 * @return bool|string
	 */
	public function get_language( $ID, $field = 'slug' ) {
		return pll_get_post_language( $ID, $field );
	}

	/**
	 * Requres a join clause for the queries that are used for filtering posts by language
	 *
	 * @param $alias
	 *
	 * @return string
	 */
	public function get_join_clause( $alias = '' ) {
		return PLL()->model->post->join_clause( $alias );
	}

	/**
	 * Requres a where clause for the queries that are used for filtering posts by language
	 *
	 * @param $language
	 *
	 * @return string
	 */
	public function get_where_clause( $language ) {
		return PLL()->model->post->where_clause( $language );
	}
}