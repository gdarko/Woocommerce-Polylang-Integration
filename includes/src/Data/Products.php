<?php

namespace CodeVerve\WCPLL\Data;

/**
 * Class Products
 * @package CodeVerve\WCPLL\Data
 */
class Products extends TranslatedPost {

	/**
	 * Returns legacy meta keys
	 *
	 * @param bool $keys_only
	 *
	 * @return string[]
	 */
	public static function get_legacy_meta_keys( $keys_only = false ) {
		$keys = array(
			'_backorders'            => 'backorders',
			'_children'              => 'children',
			'_crosssell_ids'         => 'cross_sell_ids',
			'_default_attributes'    => 'default_attributes',
			'_download_expiry'       => 'download_expiry',
			'_download_limit'        => 'download_limit',
			'_downloadable'          => 'downloadable',
			'_downloadable_files'    => 'downloads',
			'_featured'              => 'featured',
			'_height'                => 'height',
			'_length'                => 'length',
			'_low_stock_amount'      => 'low_stock_amount',
			'_manage_stock'          => 'manage_stock',
			'_price'                 => 'price',
			'_product_attributes'    => 'attributes',
			'_product_image_gallery' => 'gallery_image_ids',
			'_regular_price'         => 'regular_price',
			'_sale_price'            => 'sale_price',
			'_sale_price_dates_from' => 'date_on_sale_from',
			'_sale_price_dates_to'   => 'date_on_sale_to',
			'_sku'                   => 'sku',
			'_sold_individually'     => 'sold_individually',
			'_stock'                 => 'stock_quantity',
			'_stock_status'          => 'stock_status',
			'_tax_class'             => 'tax_class',
			'_tax_status'            => 'tax_status',
			'_thumbnail_id'          => 'image_id',
			'_upsell_ids'            => 'upsell_ids',
			'_virtual'               => 'virtual',
			'_weight'                => 'weight',
			'_width'                 => 'width',
			'_button_text'           => 'button_text',
			'_product_url'           => 'product_url',
			'_purchase_note'         => 'purchase_note',
			'_variation_description' => 'description',
		);

		if ( $keys_only ) {
			$keys = array_keys( $keys );
		}

		return $keys;
	}


	/**
	 * Check if two products are synschornized
	 *
	 * @param $id
	 * @param $other_id
	 *
	 * @return bool
	 */
	public function are_synchronized( $id, $other_id ) {
		if ( isset( PLL()->sync_post->sync_model ) ) {
			return PLL()->sync_post->sync_model->are_synchronized( $id, $other_id );
		} else {
			return PLL()->sync_post->are_synchronized( $id, $other_id );
		}
	}

	/**
	 * Determines whether texts should be copied depending on duplicate and synchronization options.
	 *
	 * @param int $from
	 * @param int $to
	 * @param bool $sync
	 *
	 * @return bool
	 *
	 */
	public function should_copy_texts( $from, $to, $sync ) {
		if ( ! $sync ) {
			$duplicate_options = get_user_meta( get_current_user_id(), 'pll_duplicate_content', true );
			if ( ! empty( $duplicate_options ) && ! empty( $duplicate_options['product'] ) ) {
				return true;
			}
		}

		if ( ! isset( PLL()->sync_post ) ) {
			return false;
		}

		$from = wc_get_product( $from );
		$to   = wc_get_product( $to );

		if ( ! empty( $from ) && ! empty( $to ) ) {
			if ( 'variation' === $from->get_type() ) {
				return $this->are_synchronized( $from->get_parent_id(), $to->get_parent_id() );
			} else {
				return $this->are_synchronized( $from->get_id(), $to->get_id() );
			}
		}

		return false;
	}

	/**
	 * Attempt to translate product property
	 *
	 * @param mixed $value
	 * @param string $prop
	 * @param string $lang
	 *
	 * @return mixed
	 */
	public function translate_product_property( $value, $prop, $lang = '' ) {
		$translated = $value;

		switch ( $prop ) {
			case 'image_id':
				if ( PLL()->options['media_support'] ) {
					$translated = pll_get_post( $value, $lang );
					if ( empty( $translated ) ) {
						$translated = PLL()->posts->create_media_translation( $value, $lang );
					}
				}
				break;

			case 'gallery_image_ids':
				if ( PLL()->options['media_support'] ) {
					$translated = array();
					foreach ( explode( ',', $value ) as $post_id ) {
						$tr_id = pll_get_post( $post_id, $lang );
						if ( empty( $tr_id ) ) {
							$tr_id = PLL()->posts->create_media_translation( $post_id, $lang );
						}
						$translated[] = $tr_id;
					}
					$translated = implode( ',', $translated );
				}
				break;

			case 'children':
			case 'upsell_ids':
			case 'cross_sell_ids':
				$translated = array();
				foreach ( $value as $id ) {
					if ( $tr_id = $this->get( $id, $lang ) ) {
						$translated[] = $tr_id;
					}
				}
				break;

			case 'default_attributes':
			case 'attributes':
				if ( is_array( $value ) ) {
					$translated = array();
					foreach ( $value as $k => $v ) {
						$translated[ $k ] = $v;

						switch ( gettype( $v ) ) {
							case 'string':
								if ( taxonomy_exists( $k ) ) {
									$terms = get_terms( array(
										'taxonomy'   => $k,
										'slug'       => $v,
										'hide_empty' => false,
										'lang'       => ''
									) );
									if ( is_array( $terms ) && ( $term = reset( $terms ) ) && $tr_id = pll_get_term( $term->term_id, $lang ) ) {
										$term             = get_term( $tr_id, $k );
										$translated[ $k ] = $term->slug;
									}
								}
								break;

							case 'object':
								if ( $v->is_taxonomy() && $terms = $v->get_terms() ) {
									$tr_ids = array();
									foreach ( $terms as $term ) {
										$tr_ids[] = pll_get_term( $term->term_id, $lang );
									}
									$v->set_options( $tr_ids );
								}
								break;
						}
					}
				}
				break;
		}

		return apply_filters( 'wpi_translate_product_property', $translated, $prop, $lang );
	}

	/**
	 * Copy/Create or sync variation
	 *
	 * @param $id
	 * @param $tr_parent
	 * @param $lang
	 */
	public function copy_variation( $id, $tr_parent, $lang ) {

		static $has_run = false;

		if ( $has_run ) {
			return;
		}

		$translated_product_id = $this->get( $id, $lang );

		if ( $translated_product_id === $id ) {
			return;
		}

		if ( ! $translated_product_id ) {
			// If the product variation is untranslated, attempt to find a translation based on the attribute.
			$tr_product = wc_get_product( $tr_parent );

			if ( is_a( $tr_product, '\WC_Product_Variable' ) ) {
				$tr_attributes = $tr_product->get_variation_attributes();

				if ( ! empty( $tr_attributes ) && $variation = wc_get_product( $id ) ) {
					// At least one translated variation was manually created.
					$attributes = $variation->get_attributes();
					if ( ! in_array( '', $attributes ) ) {
						$attributes = $this->translate_product_property( $attributes, $lang );
						foreach ( $tr_product->get_children() as $_tr_id ) {
							$translated_variation = wc_get_product( $_tr_id );
							if ( $translated_variation && $attributes === $translated_variation->get_attributes() && empty( $this->get( $translated_variation->get_id(), $this->get_language( $id ) ) ) ) {

								$translated_product_id = $translated_variation->get_id();
								break;
							}
						}
					}
				}
			}

			if ( ! $translated_product_id ) {
				// Creates the translated product variation if it does not exist yet.
				$has_run = true;

				$translated_variation = new \WC_Product_Variation();
				$translated_variation->set_parent_id( $tr_parent );
				$translated_product_id = $translated_variation->save();

				$has_run = false;
			}

			$this->copy_taxonomies_and_metadata( $id, $translated_product_id, $lang );
			$this->set_language( $translated_product_id, $lang );
			$translations                               = $this->get_translations( $id );
			$translations[ $this->get_language( $id ) ] = $id; // In case this is the first translation created.
			$translations[ $lang ]                      = $translated_product_id;
			$this->save_translations( $translations );
		} else {
			// Reset the parent
			$translated_variation = new \WC_Product_Variation( $translated_product_id );
			if ( $translated_variation->get_parent_id() !== $tr_parent ) {
				$has_run = true;

				$translated_variation->set_parent_id( $tr_parent );
				$translated_product_id = $translated_variation->save();

				$has_run = false;
			}

			// Sync the taxonomies nad metadata
			$this->copy_taxonomies_and_metadata( $id, $translated_product_id, $lang, true );
		}
	}

	/**
	 * Copy taxonomies and metdata from specific product to a target product.
	 *
	 * @param $from
	 * @param $to
	 * @param $lang
	 * @param false $sync
	 */
	public function copy_taxonomies_and_metadata( $from, $to, $lang, $sync = false ) {

		global $wpdb;

		// Synchronize the status for the variations.
		$post = get_post( $from );

		if ( 'product_variation' === $post->post_type ) {
			$wpdb->update( $wpdb->posts, array( 'post_status' => $post->post_status ), array( 'ID' => $to ) );
		}

		if ( ! $sync ) {
			PLL()->sync->taxonomies->copy( $from, $to, $lang );
		}

		PLL()->sync->post_metas->copy( $from, $to, $lang, $sync );

		// Since WC 3.6 we also need to update the lookup table.
		$this->update_lookup_table( $to, 'wc_product_meta_lookup' );
	}

	/**
	 * Update a lookup table for an object.
	 *
	 * @param $id
	 * @param $table
	 */
	public function update_lookup_table( $id, $table ) {
		//$this->data_store->wc_update_lookup_table( $id, $table );
		// TODO: Implement
	}

	/**
	 * Check if sku exists in specific language for specific product id.
	 *
	 * @param $product_id
	 * @param $sku
	 * @param $language
	 *
	 * @return bool
	 */
	public function sku_exists( $product_id, $sku, $language ) {
		global $wpdb;

		$lang = PLL()->model->get_language( $language );

		if ( version_compare( WC()->version, '3.6', '<' ) ) {
			return (bool) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT $wpdb->posts.ID
					FROM $wpdb->posts
					LEFT JOIN $wpdb->postmeta ON ( $wpdb->posts.ID = $wpdb->postmeta.post_id )
					INNER JOIN $wpdb->term_relationships AS pll_tr ON pll_tr.object_id = $wpdb->posts.ID
					WHERE $wpdb->posts.post_type IN ( 'product', 'product_variation' )
						AND $wpdb->posts.post_status != 'trash'
						AND $wpdb->postmeta.meta_key = '_sku' AND $wpdb->postmeta.meta_value = %s
						AND $wpdb->postmeta.post_id <> %d
						AND pll_tr.term_taxonomy_id = %d
					LIMIT 1",
					wp_slash( $sku ),
					$product_id,
					$lang->term_taxonomy_id
				)
			);
		}

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT posts.ID
				FROM {$wpdb->posts} AS posts
				INNER JOIN {$wpdb->wc_product_meta_lookup} AS lookup ON posts.ID = lookup.product_id
				INNER JOIN {$wpdb->term_relationships} AS pll_tr ON pll_tr.object_id = posts.ID
				WHERE posts.post_type IN ( 'product', 'product_variation' )
					AND posts.post_status != 'trash'
					AND lookup.sku = %s
					AND lookup.product_id <> %d
					AND pll_tr.term_taxonomy_id = %d
				LIMIT 1",
				wp_slash( $sku ),
				$product_id,
				$lang->term_taxonomy_id
			)
		);
	}
}