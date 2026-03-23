<?php

class Skroutz_Xml_Feed_For_Woocommerce_Feed_Generator {

	private $logger;

	public function __construct( $logger ) {
		$this->logger = $logger;
	}

	public function maybe_generate( $force = false ) {
		$report = Skroutz_Xml_Feed_For_Woocommerce_Settings::get_report();

		if ( ! $force && $this->is_cache_fresh( $report ) ) {
			return $report;
		}

		return $this->generate_feed();
	}

	public function generate_feed() {
		$settings = Skroutz_Xml_Feed_For_Woocommerce_Settings::get_all();
		$started  = microtime( true );
		$entries  = array();

		$this->logger->info( 'Starting Skroutz feed generation.', array( 'settings' => $settings ) );

		foreach ( $this->get_products_to_process() as $item ) {
			$entries[] = $this->resolve_product( $item['product'], $item['parent'], $settings );
		}

		$summary    = $this->count_statuses( $entries );
		$exportable = array_values(
			array_filter(
				$entries,
				static function ( $entry ) {
					return ! empty( $entry['included'] ) && 0 === (int) $entry['error_count'];
				}
			)
		);
		$xml_path   = $this->get_xml_path();
		$xml_url    = $this->get_xml_url();
		$duration   = (int) round( ( microtime( true ) - $started ) * 1000 );

		$problem_rows  = array();
		$excluded_rows = array();

		foreach ( $entries as $entry ) {
			if ( ! empty( $entry['included'] ) && ( $entry['error_count'] > 0 || $entry['warning_count'] > 0 ) ) {
				$problem_rows[] = $this->prepare_entry_for_report( $entry );
			}

			if ( empty( $entry['included'] ) ) {
				$excluded_rows[] = $this->prepare_entry_for_report( $entry );
			}
		}

		$this->write_xml( $exportable, $xml_path, $settings['root_element'] );

		$report = array(
			'generated_at'      => current_time( 'mysql' ),
			'generated_at_gmt'  => gmdate( 'c' ),
			'duration_ms'       => $duration,
			'xml_path'          => $xml_path,
			'xml_url'           => $xml_url,
			'feed_url'          => $this->get_endpoint_url(),
			'log_path'          => $this->logger->get_log_path(),
			'is_stale'          => false,
			'summary'           => array_merge( $summary, array( 'exported' => count( $exportable ) ) ),
			'problem_products'  => array_slice( $problem_rows, 0, 200 ),
			'excluded_products' => array_slice( $excluded_rows, 0, 100 ),
		);

		Skroutz_Xml_Feed_For_Woocommerce_Settings::update_report( $report );

		$this->logger->info(
			'Finished Skroutz feed generation.',
			array(
				'summary'     => $report['summary'],
				'duration_ms' => $duration,
				'xml_path'    => $xml_path,
			)
		);

		return $report;
	}

	public function invalidate_cache( $reason = '' ) {
		$report = Skroutz_Xml_Feed_For_Woocommerce_Settings::get_report();

		if ( file_exists( $this->get_xml_path() ) ) {
			unlink( $this->get_xml_path() );
		}

		if ( ! empty( $report ) ) {
			$report['is_stale']       = true;
			$report['stale_reason']   = $reason;
			$report['invalidated_at'] = current_time( 'mysql' );
			Skroutz_Xml_Feed_For_Woocommerce_Settings::update_report( $report );
		}

		$this->logger->info( 'Feed cache invalidated.', array( 'reason' => $reason ) );
	}

	public function is_cache_fresh( $report = null ) {
		$report = is_array( $report ) ? $report : Skroutz_Xml_Feed_For_Woocommerce_Settings::get_report();

		if ( empty( $report ) || ! empty( $report['is_stale'] ) ) {
			return false;
		}

		$path = $this->get_xml_path();

		if ( ! file_exists( $path ) ) {
			return false;
		}

		$ttl_minutes = (int) Skroutz_Xml_Feed_For_Woocommerce_Settings::get( 'cache_ttl_minutes' );
		$ttl_seconds = max( 5, $ttl_minutes ) * MINUTE_IN_SECONDS;

		return ( time() - filemtime( $path ) ) < $ttl_seconds;
	}

	public function get_endpoint_url() {
		return home_url( '/' . Skroutz_Xml_Feed_For_Woocommerce_Settings::FEED_FILENAME );
	}

	public function get_xml_path() {
		$uploads = wp_upload_dir();
		$base    = empty( $uploads['error'] ) && ! empty( $uploads['basedir'] ) ? $uploads['basedir'] : trailingslashit( WP_CONTENT_DIR ) . 'uploads';

		return trailingslashit( $base ) . 'skroutz-xml-feed/' . Skroutz_Xml_Feed_For_Woocommerce_Settings::FEED_FILENAME;
	}

	public function get_xml_url() {
		$uploads = wp_upload_dir();
		$base    = empty( $uploads['error'] ) && ! empty( $uploads['baseurl'] ) ? $uploads['baseurl'] : content_url( 'uploads' );

		return trailingslashit( $base ) . 'skroutz-xml-feed/' . Skroutz_Xml_Feed_For_Woocommerce_Settings::FEED_FILENAME;
	}

	private function get_products_to_process() {
		$product_ids = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => array( 'publish', 'private', 'draft', 'pending' ),
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		$items       = array();

		foreach ( $product_ids as $product_id ) {
			$product = wc_get_product( $product_id );

			if ( ! $product instanceof WC_Product ) {
				continue;
			}

			$items[] = array( 'product' => $product, 'parent' => null );

			if ( $product->is_type( 'variable' ) ) {
				foreach ( $product->get_children() as $variation_id ) {
					$variation = wc_get_product( $variation_id );

					if ( $variation instanceof WC_Product_Variation ) {
						$items[] = array( 'product' => $variation, 'parent' => $product );
					}
				}
			}
		}

		return $items;
	}

	private function resolve_product( $product, $parent, $settings ) {
		$row_type             = $product->is_type( 'variation' ) ? 'variation' : $product->get_type();
		$has_children         = $product->is_type( 'variable' ) && ! empty( $product->get_children() );
		$published            = 'publish' === $product->get_status() && ( ! $parent || 'publish' === $parent->get_status() );
		$visibility           = $this->resolve_visibility( $product, $parent );
		$supported            = in_array( $row_type, array( 'simple', 'variation', 'variable' ), true );
		$excluded_by_override = $this->is_meta_truthy( $product, 'exclude_from_feed', $parent );
		$hidden_from_catalog  = in_array( $visibility, array( 'hidden', 'search' ), true );
		$included             = ! $excluded_by_override
			&& $supported
			&& $published
			&& ( ! $has_children || 'variable' !== $row_type )
			&& ( ! $hidden_from_catalog || ! empty( $settings['include_hidden_products'] ) );
		$effective_sku        = $this->first_non_empty( $product->get_sku(), $parent ? $parent->get_sku() : '' );
		$quantity             = $this->resolve_quantity( $product, $parent );
		$name                 = $this->first_non_empty( $this->get_override_value( $product, 'custom_name', $parent ), $this->strip_html( $product->get_name() ) );
		$link                 = $this->first_non_empty( $this->get_override_value( $product, 'custom_link', $parent ), $product->get_permalink() );
		$image                = $this->resolve_main_image( $product, $parent );
		$category             = $this->first_non_empty( $this->get_override_value( $product, 'category', $parent ), $this->resolve_category_path( $product, $parent ) );
		$price                = $this->resolve_price_with_vat( $product );
		$vat                  = $this->resolve_vat_rate( $product, $settings );
		$manufacturer         = $this->first_non_empty( $this->get_override_value( $product, 'manufacturer', $parent ), $this->detect_manufacturer( $product, $parent ), $settings['default_manufacturer'] );
		$mpn                  = $this->first_non_empty( $this->get_override_value( $product, 'mpn', $parent ), $effective_sku, (string) $product->get_id() );
		$ean_override         = $this->get_override_value( $product, 'ean', $parent );
		$ean                  = '' !== $ean_override ? $this->normalize_ean( $ean_override ) : $this->resolve_ean_value( $this->detect_gtin( $product, $parent ), $effective_sku );
		$availability         = $this->first_non_empty( $this->get_override_value( $product, 'availability', $parent ), $quantity > 0 ? $settings['in_stock_availability'] : $settings['out_of_stock_availability'] );
		$weight               = $this->first_non_empty( $this->resolve_weight_override( $this->get_override_value( $product, 'weight', $parent ) ), $this->resolve_weight( $product, $parent ) );
		$color                = $this->first_non_empty( $this->get_override_value( $product, 'color', $parent ), $this->detect_attribute_value( $product, $parent, array( 'color', 'colour', 'χρώμα', 'xroma' ) ) );
		$size                 = $this->first_non_empty( $this->get_override_value( $product, 'size', $parent ), $this->detect_attribute_value( $product, $parent, array( 'size', 'μέγεθος', 'megethos' ) ) );
		$description          = $this->first_non_empty( $this->get_override_value( $product, 'custom_description', $parent ), $this->resolve_description( $product, $parent ) );
		$entry                = array(
			'source_id'         => (string) $product->get_id(),
			'row_type'          => $row_type,
			'parent_id'         => $parent ? (string) $parent->get_id() : '',
			'included'          => $included,
			'published'         => $published,
			'visibility'        => $visibility,
			'name'              => $name,
			'link'              => $this->clean_text( $link ),
			'image'             => $this->clean_text( $image ),
			'additional_images' => $this->resolve_additional_images( $product, $parent, $image ),
			'category'          => $category,
			'price'             => $price,
			'vat'               => $vat,
			'manufacturer'      => $manufacturer,
			'mpn'               => $this->clean_text( $mpn ),
			'ean'               => $ean,
			'availability'      => $this->clean_text( $availability ),
			'weight'            => $weight,
			'color'             => $this->clean_text( $color ),
			'size'              => $this->clean_text( $size ),
			'description'       => $description,
			'quantity'          => (string) $quantity,
		);

		$entry['issues']        = $this->validate_product( $entry, array( 'has_children' => $has_children, 'supported' => $supported, 'excluded_by_override' => $excluded_by_override ) );
		$entry['error_count']   = $this->count_issues( $entry['issues'], 'error' );
		$entry['warning_count'] = $this->count_issues( $entry['issues'], 'warning' );
		$entry['status']        = $this->determine_status( $entry );

		if ( $entry['error_count'] > 0 ) {
			$this->logger->warning( 'Feed item has blocking issues.', array( 'product_id' => $entry['source_id'], 'status' => $entry['status'], 'issues' => $entry['issues'] ) );
		}

		return $entry;
	}

	private function validate_product( $entry, $context ) {
		$issues = array();

		if ( ! empty( $context['excluded_by_override'] ) ) {
			$issues[] = $this->make_issue( 'warning', 'included', 'This product is manually excluded from the feed.' );
		}
		if ( ! empty( $context['has_children'] ) && 'variable' === $entry['row_type'] ) {
			$issues[] = $this->make_issue( 'warning', 'included', 'Variable parents are exported through child variation rows.' );
		}
		if ( empty( $context['supported'] ) ) {
			$issues[] = $this->make_issue( 'warning', 'included', sprintf( 'Unsupported WooCommerce product type "%s".', $entry['row_type'] ) );
		}
		if ( ! $entry['published'] ) {
			$issues[] = $this->make_issue( 'warning', 'included', 'This product is not published in WooCommerce.' );
		}
		if ( in_array( $entry['visibility'], array( 'hidden', 'search' ), true ) ) {
			$issues[] = $this->make_issue( 'warning', 'included', sprintf( 'This product visibility is "%s".', $entry['visibility'] ) );
		}
		if ( '' === $entry['name'] ) {
			$issues[] = $this->make_issue( 'error', 'name', 'Name is required.' );
		} elseif ( $this->text_length( $entry['name'] ) > 300 ) {
			$issues[] = $this->make_issue( 'error', 'name', 'Name exceeds 300 characters.' );
		}
		if ( '' === $entry['link'] ) {
			$issues[] = $this->make_issue( 'error', 'link', 'Product link is required.' );
		} elseif ( $this->text_length( $entry['link'] ) > 1000 || ! $this->looks_like_https_url( $entry['link'] ) ) {
			$issues[] = $this->make_issue( 'error', 'link', 'Product link must be a valid HTTPS URL.' );
		}
		if ( '' === $entry['image'] ) {
			$issues[] = $this->make_issue( 'warning', 'image', 'Main image is empty.' );
		} elseif ( $this->text_length( $entry['image'] ) > 400 || ! $this->looks_like_https_url( $entry['image'] ) ) {
			$issues[] = $this->make_issue( 'error', 'image', 'Main image must be a valid HTTPS URL up to 400 characters.' );
		}
		foreach ( $entry['additional_images'] as $additional_image ) {
			if ( $this->text_length( $additional_image ) > 400 || ! $this->looks_like_https_url( $additional_image ) ) {
				$issues[] = $this->make_issue( 'error', 'additional_images', 'Additional images must be valid HTTPS URLs up to 400 characters.' );
				break;
			}
		}
		if ( '' === $entry['category'] ) {
			$issues[] = $this->make_issue( 'error', 'category', 'Category path is required.' );
		} elseif ( $this->text_length( $entry['category'] ) > 250 ) {
			$issues[] = $this->make_issue( 'error', 'category', 'Category exceeds 250 characters.' );
		}
		if ( '' === $entry['price'] || ! is_numeric( str_replace( ',', '.', $entry['price'] ) ) ) {
			$issues[] = $this->make_issue( 'error', 'price', 'Price with VAT is required.' );
		} elseif ( (float) $entry['price'] < 0 ) {
			$issues[] = $this->make_issue( 'error', 'price', 'Price cannot be negative.' );
		}
		if ( '' === $entry['vat'] || ! is_numeric( str_replace( ',', '.', $entry['vat'] ) ) ) {
			$issues[] = $this->make_issue( 'error', 'vat', 'VAT is required.' );
		} elseif ( (float) $entry['vat'] < 0 || (float) $entry['vat'] > 100 ) {
			$issues[] = $this->make_issue( 'error', 'vat', 'VAT must be between 0 and 100.' );
		}
		if ( '' === $entry['manufacturer'] ) {
			$issues[] = $this->make_issue( 'error', 'manufacturer', 'Manufacturer is required.' );
		} elseif ( $this->text_length( $entry['manufacturer'] ) > 100 ) {
			$issues[] = $this->make_issue( 'error', 'manufacturer', 'Manufacturer exceeds 100 characters.' );
		}
		if ( '' === $entry['mpn'] ) {
			$issues[] = $this->make_issue( 'error', 'mpn', 'MPN is required.' );
		} elseif ( $this->text_length( $entry['mpn'] ) > 80 ) {
			$issues[] = $this->make_issue( 'error', 'mpn', 'MPN exceeds 80 characters.' );
		}
		if ( '' === $entry['ean'] ) {
			$issues[] = $this->make_issue( 'error', 'ean', 'EAN / barcode is required for a compliant Skroutz feed.' );
		} elseif ( ! preg_match( '/^\d{13}$/', $entry['ean'] ) ) {
			$issues[] = $this->make_issue( 'error', 'ean', 'EAN must contain exactly 13 digits.' );
		}
		if ( '' === $entry['availability'] ) {
			$issues[] = $this->make_issue( 'error', 'availability', 'Availability is required.' );
		} elseif ( ! in_array( $entry['availability'], Skroutz_Xml_Feed_For_Woocommerce_Settings::availability_options(), true ) ) {
			$issues[] = $this->make_issue( 'warning', 'availability', 'Availability does not match Skroutz standard labels.' );
		}
		if ( '' === $entry['description'] ) {
			$issues[] = $this->make_issue( 'error', 'description', 'Description is required.' );
		} elseif ( $this->text_length( $entry['description'] ) > 10000 ) {
			$issues[] = $this->make_issue( 'error', 'description', 'Description exceeds 10000 characters.' );
		} elseif ( false !== strpos( $entry['description'], '<' ) || false !== strpos( $entry['description'], '>' ) ) {
			$issues[] = $this->make_issue( 'error', 'description', 'Description cannot contain HTML.' );
		}
		if ( ! preg_match( '/^\d+$/', (string) $entry['quantity'] ) ) {
			$issues[] = $this->make_issue( 'error', 'quantity', 'Quantity must be a whole number.' );
		} elseif ( (int) $entry['quantity'] > 10000000 ) {
			$issues[] = $this->make_issue( 'error', 'quantity', 'Quantity exceeds Skroutz maximum value.' );
		}
		if ( '' !== $entry['weight'] && ! preg_match( '/^\d+$/', (string) $entry['weight'] ) ) {
			$issues[] = $this->make_issue( 'error', 'weight', 'Weight must be numeric.' );
		}

		return $issues;
	}

	private function resolve_visibility( $product, $parent ) {
		if ( $product->is_type( 'variation' ) && $parent ) {
			return (string) $parent->get_catalog_visibility();
		}

		return (string) $product->get_catalog_visibility();
	}

	private function resolve_quantity( $product, $parent ) {
		$quantity = $product->get_stock_quantity();

		if ( null === $quantity && $product->is_type( 'variation' ) && $parent instanceof WC_Product && $parent->managing_stock() ) {
			$quantity = $parent->get_stock_quantity();
		}

		if ( null !== $quantity ) {
			return max( 0, (int) $quantity );
		}

		return $product->is_in_stock() ? 1 : 0;
	}

	private function resolve_price_with_vat( $product ) {
		$price = $product->get_regular_price();
		$sale  = $product->get_sale_price();

		if ( '' !== $sale && (float) $sale > 0 ) {
			$price = $sale;
		}
		if ( '' === $price ) {
			$price = $product->get_price();
		}
		if ( '' === $price ) {
			return '';
		}

		return $this->format_decimal( wc_get_price_including_tax( $product, array( 'price' => (float) $price ) ) );
	}

	private function resolve_vat_rate( $product, $settings ) {
		if ( 'taxable' !== $product->get_tax_status() ) {
			return '0.00';
		}

		$rates = WC_Tax::get_base_tax_rates( $product->get_tax_class() );
		$total = 0.0;

		foreach ( $rates as $rate ) {
			if ( isset( $rate['rate'] ) ) {
				$total += (float) $rate['rate'];
			}
		}

		if ( $total > 0 ) {
			return $this->format_decimal( $total );
		}

		return $this->format_decimal( $settings['default_vat_rate'] );
	}

	private function resolve_main_image( $product, $parent ) {
		$override = $this->get_override_value( $product, 'custom_image', $parent );

		if ( '' !== $override ) {
			return $this->clean_text( $override );
		}

		$image_id = $product->get_image_id();
		if ( ! $image_id && $parent ) {
			$image_id = $parent->get_image_id();
		}
		if ( ! $image_id ) {
			return '';
		}

		$image_url = wp_get_attachment_image_url( $image_id, 'full' );
		return $image_url ? $image_url : '';
	}

	private function resolve_additional_images( $product, $parent, $main_image ) {
		$override = $this->get_override_value( $product, 'additional_images', $parent );

		if ( '' !== $override ) {
			return array_slice( $this->parse_image_list( $override ), 0, 15 );
		}

		$image_ids = $product->get_gallery_image_ids();
		if ( empty( $image_ids ) && $parent ) {
			$image_ids = $parent->get_gallery_image_ids();
		}

		$images = array();
		foreach ( $image_ids as $image_id ) {
			$image_url = wp_get_attachment_image_url( $image_id, 'full' );
			if ( $image_url ) {
				$images[] = $image_url;
			}
		}

		$images = array_values( array_unique( array_filter( $images ) ) );
		if ( '' !== $main_image ) {
			$images = array_values( array_filter( $images, static function ( $image_url ) use ( $main_image ) { return $image_url !== $main_image; } ) );
		}

		return array_slice( $images, 0, 15 );
	}

	private function resolve_category_path( $product, $parent ) {
		$target = $parent instanceof WC_Product ? $parent : $product;
		$terms  = get_the_terms( $target->get_id(), 'product_cat' );

		if ( ! is_array( $terms ) || empty( $terms ) ) {
			return '';
		}

		$primary_term_id = (int) get_post_meta( $target->get_id(), '_yoast_wpseo_primary_product_cat', true );
		if ( $primary_term_id > 0 ) {
			foreach ( $terms as $term ) {
				if ( $primary_term_id === (int) $term->term_id ) {
					return $this->build_term_path( $term );
				}
			}
		}

		usort( $terms, static function ( $left, $right ) { return count( get_ancestors( $right->term_id, 'product_cat', 'taxonomy' ) ) <=> count( get_ancestors( $left->term_id, 'product_cat', 'taxonomy' ) ); } );
		return $this->build_term_path( $terms[0] );
	}

	private function build_term_path( $term ) {
		$ancestors = array_reverse( get_ancestors( $term->term_id, 'product_cat', 'taxonomy' ) );
		$parts     = array();

		foreach ( $ancestors as $ancestor_id ) {
			$ancestor = get_term( $ancestor_id, 'product_cat' );
			if ( $ancestor && ! is_wp_error( $ancestor ) ) {
				$parts[] = $ancestor->name;
			}
		}

		$parts[] = $term->name;
		return implode( ' > ', array_map( array( $this, 'clean_text' ), $parts ) );
	}

	private function detect_manufacturer( $product, $parent ) {
		$candidates = array_filter( array( $product, $parent ) );
		foreach ( array( 'product_brand', 'pwb-brand', 'yith_product_brand', 'brand' ) as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			foreach ( $candidates as $candidate ) {
				$terms = wp_get_post_terms( $candidate->get_id(), $taxonomy, array( 'fields' => 'names' ) );
				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
					return $this->clean_text( $terms[0] );
				}
			}
		}

		$from_attributes = $this->detect_attribute_value( $product, $parent, array( 'brand', 'manufacturer', 'μάρκα', 'marka' ) );
		if ( '' !== $from_attributes ) {
			return $from_attributes;
		}

		return $this->detect_common_meta_value( $product, $parent, array( '_brand', 'brand', '_manufacturer', 'manufacturer' ) );
	}

	private function detect_gtin( $product, $parent ) {
		foreach ( array_filter( array( $product, $parent ) ) as $candidate ) {
			if ( is_callable( array( $candidate, 'get_global_unique_id' ) ) ) {
				$global_unique_id = $candidate->get_global_unique_id();
				if ( '' !== $global_unique_id ) {
					return $this->clean_text( $global_unique_id );
				}
			}
		}

		return $this->detect_common_meta_value( $product, $parent, array( '_global_unique_id', '_alg_ean', '_alg_barcode', '_wpm_gtin_code', '_barcode', '_ean', 'ean', 'gtin' ) );
	}

	private function detect_common_meta_value( $product, $parent, $keys ) {
		foreach ( array_filter( array( $product, $parent ) ) as $candidate ) {
			foreach ( $keys as $key ) {
				$value = get_post_meta( $candidate->get_id(), $key, true );
				if ( '' !== $value ) {
					return $this->clean_text( $value );
				}
			}
		}

		return '';
	}

	private function detect_attribute_value( $product, $parent, $keywords ) {
		if ( $product->is_type( 'variation' ) ) {
			foreach ( $product->get_variation_attributes() as $attribute_name => $attribute_value ) {
				$taxonomy = str_replace( 'attribute_', '', $attribute_name );
				$label    = wc_attribute_label( $taxonomy );

				if ( $this->label_matches_keywords( $label, $keywords ) ) {
					return $this->humanize_attribute_value( $taxonomy, $attribute_value );
				}
			}
		}

		foreach ( array_filter( array( $product, $parent ) ) as $candidate ) {
			foreach ( $candidate->get_attributes() as $attribute ) {
				$attribute_name = $attribute instanceof WC_Product_Attribute ? $attribute->get_name() : '';
				$label          = wc_attribute_label( $attribute_name );
				if ( ! $this->label_matches_keywords( $label, $keywords ) ) {
					continue;
				}
				if ( $attribute instanceof WC_Product_Attribute && $attribute->is_taxonomy() ) {
					$terms = wc_get_product_terms( $candidate->get_id(), $attribute_name, array( 'fields' => 'names' ) );
					if ( ! empty( $terms ) ) {
						return $this->clean_text( $terms[0] );
					}
				}
				if ( $attribute instanceof WC_Product_Attribute ) {
					$options = $attribute->get_options();
					if ( 1 === count( $options ) ) {
						return $this->clean_text( (string) $options[0] );
					}
				}
			}
		}

		return '';
	}

	private function resolve_description( $product, $parent ) {
		$sources = array( $product->get_description(), $product->get_short_description() );
		if ( $parent ) {
			$sources[] = $parent->get_description();
			$sources[] = $parent->get_short_description();
		}
		foreach ( $sources as $source ) {
			$clean = $this->strip_html( $source );
			if ( '' !== $clean ) {
				return $clean;
			}
		}

		return '';
	}

	private function resolve_weight_override( $weight ) {
		$weight = $this->clean_text( $weight );
		return '' === $weight ? '' : preg_replace( '/\D+/', '', $weight );
	}

	private function resolve_weight( $product, $parent ) {
		$weight = $this->first_non_empty( $product->get_weight(), $parent ? $parent->get_weight() : '' );
		return '' === $weight ? '' : (string) max( 0, (int) round( (float) wc_get_weight( $weight, 'g' ) ) );
	}

	private function get_override_value( $product, $field, $parent ) {
		$meta_key = Skroutz_Xml_Feed_For_Woocommerce_Settings::meta_key( $field );
		$value    = get_post_meta( $product->get_id(), $meta_key, true );

		if ( '' !== $value && null !== $value ) {
			return $this->clean_text( $value );
		}
		if ( $product->is_type( 'variation' ) && $parent ) {
			$parent_value = get_post_meta( $parent->get_id(), $meta_key, true );
			if ( '' !== $parent_value && null !== $parent_value ) {
				return $this->clean_text( $parent_value );
			}
		}

		return '';
	}

	private function is_meta_truthy( $product, $field, $parent ) {
		return in_array( $this->get_override_value( $product, $field, $parent ), array( '1', 'yes', 'true', 'on' ), true );
	}

	private function write_xml( $entries, $path, $root_element ) {
		$directory = dirname( $path );
		if ( ! is_dir( $directory ) && ! wp_mkdir_p( $directory ) ) {
			throw new RuntimeException( 'Unable to create the Skroutz feed directory.' );
		}

		$document               = new DOMDocument( '1.0', 'UTF-8' );
		$document->formatOutput = true;
		$root_name              = preg_replace( '/[^A-Za-z0-9_]+/', '_', $this->clean_text( $root_element ) );
		$root_name              = ! empty( $root_name ) ? $root_name : 'mywebstore';
		$root                   = $document->createElement( $root_name );
		$products_node          = $document->createElement( 'products' );

		$document->appendChild( $root );
		$root->appendChild( $document->createElement( 'created_at', wp_date( 'Y-m-d H:i' ) ) );
		$root->appendChild( $products_node );

		foreach ( $entries as $entry ) {
			$product_node = $document->createElement( 'product' );
			$products_node->appendChild( $product_node );
			$this->append_text_node( $document, $product_node, 'id', $entry['source_id'] );
			$this->append_text_node( $document, $product_node, 'name', $entry['name'] );
			$this->append_text_node( $document, $product_node, 'link', $entry['link'] );
			$this->append_text_node( $document, $product_node, 'image', $entry['image'] );
			foreach ( array_slice( $entry['additional_images'], 0, 15 ) as $additional_image ) {
				$this->append_text_node( $document, $product_node, 'additionalimage', $additional_image );
			}
			$this->append_text_node( $document, $product_node, 'category', $entry['category'] );
			$this->append_text_node( $document, $product_node, 'price_with_vat', $entry['price'] );
			$this->append_text_node( $document, $product_node, 'vat', $entry['vat'] );
			$this->append_text_node( $document, $product_node, 'manufacturer', $entry['manufacturer'] );
			$this->append_text_node( $document, $product_node, 'mpn', $entry['mpn'] );
			$this->append_text_node( $document, $product_node, 'ean', $entry['ean'] );
			$this->append_text_node( $document, $product_node, 'availability', $entry['availability'] );
			if ( '' !== $entry['size'] ) {
				$this->append_text_node( $document, $product_node, 'size', $entry['size'] );
			}
			if ( '' !== $entry['weight'] ) {
				$this->append_text_node( $document, $product_node, 'weight', $entry['weight'] );
			}
			if ( '' !== $entry['color'] ) {
				$this->append_text_node( $document, $product_node, 'color', $entry['color'] );
			}
			$this->append_text_node( $document, $product_node, 'description', $entry['description'] );
			$this->append_text_node( $document, $product_node, 'quantity', $entry['quantity'] );
		}

		if ( false === $document->save( $path ) ) {
			throw new RuntimeException( 'Unable to write the Skroutz XML feed file.' );
		}
	}

	private function append_text_node( $document, $parent, $name, $value ) {
		$node = $document->createElement( $name );
		$node->appendChild( $document->createTextNode( (string) $value ) );
		$parent->appendChild( $node );
	}

	private function count_statuses( $entries ) {
		$summary = array(
			'total'       => count( $entries ),
			'included'    => 0,
			'ready'       => 0,
			'review'      => 0,
			'needs_fixes' => 0,
			'excluded'    => 0,
		);

		foreach ( $entries as $entry ) {
			if ( ! empty( $entry['included'] ) ) {
				++$summary['included'];
			} else {
				++$summary['excluded'];
			}

			switch ( $entry['status'] ) {
				case 'Ready':
					++$summary['ready'];
					break;
				case 'Review':
					++$summary['review'];
					break;
				case 'Needs fixes':
					++$summary['needs_fixes'];
					break;
			}
		}

		return $summary;
	}

	private function determine_status( $entry ) {
		if ( empty( $entry['included'] ) ) {
			return 'Excluded';
		}
		if ( (int) $entry['error_count'] > 0 ) {
			return 'Needs fixes';
		}
		if ( (int) $entry['warning_count'] > 0 ) {
			return 'Review';
		}

		return 'Ready';
	}

	private function prepare_entry_for_report( $entry ) {
		return array(
			'source_id'     => $entry['source_id'],
			'row_type'      => $entry['row_type'],
			'parent_id'     => $entry['parent_id'],
			'name'          => $entry['name'],
			'status'        => $entry['status'],
			'manufacturer'  => $entry['manufacturer'],
			'mpn'           => $entry['mpn'],
			'ean'           => $entry['ean'],
			'category'      => $entry['category'],
			'error_count'   => $entry['error_count'],
			'warning_count' => $entry['warning_count'],
			'issues'        => $entry['issues'],
		);
	}

	private function parse_image_list( $value ) {
		$parts = preg_split( '/[\r\n,]+/', $value );
		return array_values( array_filter( array_map( array( $this, 'clean_text' ), is_array( $parts ) ? $parts : array() ) ) );
	}

	private function strip_html( $value ) {
		$value = str_replace( array( '<br>', '<br/>', '<br />', '</p>', '</div>', '</li>' ), "\n", (string) $value );
		$value = html_entity_decode( wp_strip_all_tags( $value, true ), ENT_QUOTES | ENT_HTML5, get_bloginfo( 'charset' ) );
		$lines = preg_split( '/\r\n|\r|\n/', $value );
		$clean = array();
		foreach ( is_array( $lines ) ? $lines : array() as $line ) {
			$line = trim( preg_replace( '/\s+/u', ' ', $line ) );
			if ( '' !== $line ) {
				$clean[] = $line;
			}
		}
		return implode( "\n", $clean );
	}

	private function clean_text( $value ) {
		return trim( (string) $value );
	}

	private function first_non_empty( ...$values ) {
		foreach ( $values as $value ) {
			$clean = $this->clean_text( $value );
			if ( '' !== $clean ) {
				return $clean;
			}
		}
		return '';
	}

	private function format_decimal( $value ) {
		if ( '' === $value || null === $value || ! is_numeric( str_replace( ',', '.', (string) $value ) ) ) {
			return '';
		}
		return number_format( (float) str_replace( ',', '.', (string) $value ), 2, '.', '' );
	}

	private function normalize_ean( $value ) {
		return preg_replace( '/\D+/', '', $this->clean_text( $value ) );
	}

	private function resolve_ean_value( $gtin, $sku ) {
		$normalized_gtin = $this->normalize_ean( $gtin );
		if ( 13 === strlen( $normalized_gtin ) ) {
			return $normalized_gtin;
		}
		$normalized_sku = $this->normalize_ean( $sku );
		if ( 13 === strlen( $normalized_sku ) ) {
			return $normalized_sku;
		}
		return ! empty( $normalized_gtin ) ? $normalized_gtin : $normalized_sku;
	}

	private function looks_like_https_url( $value ) {
		$value = $this->clean_text( $value );
		if ( '' === $value || ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
			return false;
		}
		$parts = wp_parse_url( $value );
		return ! empty( $parts['scheme'] ) && 'https' === strtolower( $parts['scheme'] ) && ! empty( $parts['host'] );
	}

	private function label_matches_keywords( $label, $keywords ) {
		$label = function_exists( 'mb_strtolower' ) ? mb_strtolower( wp_strip_all_tags( (string) $label ) ) : strtolower( wp_strip_all_tags( (string) $label ) );
		foreach ( $keywords as $keyword ) {
			$needle = function_exists( 'mb_strtolower' ) ? mb_strtolower( $keyword ) : strtolower( $keyword );
			if ( false !== strpos( $label, $needle ) ) {
				return true;
			}
		}
		return false;
	}

	private function humanize_attribute_value( $taxonomy, $value ) {
		$value = $this->clean_text( $value );
		if ( '' === $value ) {
			return '';
		}
		if ( taxonomy_exists( $taxonomy ) ) {
			$term = get_term_by( 'slug', $value, $taxonomy );
			if ( $term && ! is_wp_error( $term ) ) {
				return $this->clean_text( $term->name );
			}
		}
		return $value;
	}

	private function make_issue( $severity, $field, $message ) {
		return array( 'severity' => $severity, 'field' => $field, 'message' => $message );
	}

	private function count_issues( $issues, $severity ) {
		$count = 0;
		foreach ( $issues as $issue ) {
			if ( isset( $issue['severity'] ) && $severity === $issue['severity'] ) {
				++$count;
			}
		}
		return $count;
	}

	private function text_length( $value ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
	}
}
