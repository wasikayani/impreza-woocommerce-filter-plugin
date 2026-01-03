<?php
/**
 * Impreza WooCommerce Filter Plugin - Helper Functions
 *
 * @package Impreza_WooCommerce_Filter
 * @version 1.0.0
 * @author Wasik Ayani
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get all product categories
 *
 * @param array $args Optional. Arguments to pass to get_terms().
 * @return array Array of category objects.
 */
function iwf_get_product_categories( $args = array() ) {
	$default_args = array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => false,
	);
	
	$args = wp_parse_args( $args, $default_args );
	
	return get_terms( $args );
}

/**
 * Get product category by ID
 *
 * @param int $category_id The category ID.
 * @return object|false The category object or false if not found.
 */
function iwf_get_product_category( $category_id ) {
	return get_term( $category_id, 'product_cat' );
}

/**
 * Get all product taxonomies
 *
 * @return array Array of taxonomy names.
 */
function iwf_get_product_taxonomies() {
	$taxonomies = get_object_taxonomies( 'product', 'names' );
	
	// Filter out standard taxonomies if needed
	$exclude = array( 'product_type', 'product_shipping_class' );
	$taxonomies = array_diff( $taxonomies, $exclude );
	
	return apply_filters( 'iwf_product_taxonomies', $taxonomies );
}

/**
 * Get taxonomy terms for a specific taxonomy
 *
 * @param string $taxonomy The taxonomy name.
 * @param array  $args Optional. Additional arguments for get_terms().
 * @return array Array of term objects.
 */
function iwf_get_taxonomy_terms( $taxonomy, $args = array() ) {
	if ( ! taxonomy_exists( $taxonomy ) ) {
		return array();
	}
	
	$default_args = array(
		'taxonomy'   => $taxonomy,
		'hide_empty' => true,
	);
	
	$args = wp_parse_args( $args, $default_args );
	
	return get_terms( $args );
}

/**
 * Get product price range
 *
 * @return array Array with 'min' and 'max' price keys.
 */
function iwf_get_price_range() {
	global $wpdb;
	
	$cache_key = 'iwf_price_range';
	$price_range = wp_cache_get( $cache_key );
	
	if ( false === $price_range ) {
		$prices = $wpdb->get_results(
			"SELECT MIN(CAST(meta_value AS DECIMAL(10,2))) as min_price,
					MAX(CAST(meta_value AS DECIMAL(10,2))) as max_price
			FROM {$wpdb->postmeta}
			WHERE meta_key = '_price'
			AND post_id IN (
				SELECT ID FROM {$wpdb->posts}
				WHERE post_type = 'product'
				AND post_status = 'publish'
			)"
		);
		
		$price_range = array(
			'min' => isset( $prices[0]->min_price ) ? (float) $prices[0]->min_price : 0,
			'max' => isset( $prices[0]->max_price ) ? (float) $prices[0]->max_price : 0,
		);
		
		wp_cache_set( $cache_key, $price_range, '', 12 * HOUR_IN_SECONDS );
	}
	
	return apply_filters( 'iwf_price_range', $price_range );
}

/**
 * Format price for display
 *
 * @param float $price The price to format.
 * @return string Formatted price string.
 */
function iwf_format_price( $price ) {
	return wc_price( $price );
}

/**
 * Format price for database storage
 *
 * @param float $price The price to format.
 * @return float Sanitized price.
 */
function iwf_sanitize_price( $price ) {
	return floatval( str_replace( ',', '.', $price ) );
}

/**
 * Get filtered products by criteria
 *
 * @param array $filter_args Array of filter arguments.
 * @return array Array of product IDs.
 */
function iwf_get_filtered_products( $filter_args = array() ) {
	$args = array(
		'post_type'      => 'product',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
	);
	
	// Add tax query if categories are specified
	if ( ! empty( $filter_args['categories'] ) ) {
		$args['tax_query'] = array(
			array(
				'taxonomy' => 'product_cat',
				'field'    => 'term_id',
				'terms'    => (array) $filter_args['categories'],
				'operator' => 'IN',
			),
		);
	}
	
	// Add meta query for price range
	if ( ! empty( $filter_args['min_price'] ) || ! empty( $filter_args['max_price'] ) ) {
		$args['meta_query'] = array();
		
		if ( ! empty( $filter_args['min_price'] ) ) {
			$args['meta_query'][] = array(
				'key'     => '_price',
				'value'   => iwf_sanitize_price( $filter_args['min_price'] ),
				'compare' => '>=',
				'type'    => 'DECIMAL(10,2)',
			);
		}
		
		if ( ! empty( $filter_args['max_price'] ) ) {
			$args['meta_query'][] = array(
				'key'     => '_price',
				'value'   => iwf_sanitize_price( $filter_args['max_price'] ),
				'compare' => '<=',
				'type'    => 'DECIMAL(10,2)',
			);
		}
		
		if ( count( $args['meta_query'] ) > 1 ) {
			$args['meta_query']['relation'] = 'AND';
		}
	}
	
	// Add search term if specified
	if ( ! empty( $filter_args['search'] ) ) {
		$args['s'] = sanitize_text_field( $filter_args['search'] );
	}
	
	// Add custom tax query for other taxonomies
	if ( ! empty( $filter_args['taxonomies'] ) && is_array( $filter_args['taxonomies'] ) ) {
		if ( empty( $args['tax_query'] ) ) {
			$args['tax_query'] = array();
		}
		
		foreach ( $filter_args['taxonomies'] as $taxonomy => $terms ) {
			if ( ! empty( $terms ) ) {
				$args['tax_query'][] = array(
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => (array) $terms,
					'operator' => 'IN',
				);
			}
		}
		
		if ( count( $args['tax_query'] ) > 1 ) {
			$args['tax_query']['relation'] = 'AND';
		}
	}
	
	$query = new WP_Query( $args );
	
	return $query->posts;
}

/**
 * Get product count by category
 *
 * @param int $category_id The category ID.
 * @return int Product count.
 */
function iwf_get_category_product_count( $category_id ) {
	$term = get_term( $category_id, 'product_cat' );
	
	if ( ! $term ) {
		return 0;
	}
	
	return $term->count;
}

/**
 * Get products by category
 *
 * @param int   $category_id The category ID.
 * @param array $args Optional. Additional WP_Query arguments.
 * @return array Array of product objects.
 */
function iwf_get_products_by_category( $category_id, $args = array() ) {
	$default_args = array(
		'post_type'      => 'product',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'tax_query'      => array(
			array(
				'taxonomy' => 'product_cat',
				'field'    => 'term_id',
				'terms'    => $category_id,
			),
		),
	);
	
	$args = wp_parse_args( $args, $default_args );
	$query = new WP_Query( $args );
	
	return $query->posts;
}

/**
 * Get attribute terms for a product attribute
 *
 * @param string $attribute The attribute name (without 'pa_' prefix).
 * @param array  $args Optional. Additional get_terms() arguments.
 * @return array Array of term objects.
 */
function iwf_get_attribute_terms( $attribute, $args = array() ) {
	$taxonomy = 'pa_' . sanitize_title( $attribute );
	
	if ( ! taxonomy_exists( $taxonomy ) ) {
		return array();
	}
	
	$default_args = array(
		'hide_empty' => true,
	);
	
	$args = wp_parse_args( $args, $default_args );
	
	return get_terms( $taxonomy, $args );
}

/**
 * Check if WooCommerce is active
 *
 * @return bool True if WooCommerce is active, false otherwise.
 */
function iwf_is_woocommerce_active() {
	return class_exists( 'WooCommerce' );
}

/**
 * Get product filter URL
 *
 * @param array $filters Array of filters to apply.
 * @return string Filter URL.
 */
function iwf_get_filter_url( $filters = array() ) {
	$url = wc_get_page_permalink( 'shop' );
	
	if ( empty( $filters ) ) {
		return $url;
	}
	
	$query_string = http_build_query( $filters );
	
	return add_query_arg( $filters, $url );
}

/**
 * Sanitize filter input
 *
 * @param mixed $input The input to sanitize.
 * @return mixed Sanitized input.
 */
function iwf_sanitize_filter_input( $input ) {
	if ( is_array( $input ) ) {
		return array_map( 'iwf_sanitize_filter_input', $input );
	}
	
	return sanitize_text_field( $input );
}

/**
 * Get active filters from request
 *
 * @return array Array of active filters.
 */
function iwf_get_active_filters() {
	$filters = array();
	
	// Get category filter
	if ( isset( $_GET['product_cat'] ) ) {
		$filters['categories'] = array_map( 'absint', (array) $_GET['product_cat'] );
	}
	
	// Get price filter
	if ( isset( $_GET['min_price'] ) ) {
		$filters['min_price'] = iwf_sanitize_price( $_GET['min_price'] );
	}
	
	if ( isset( $_GET['max_price'] ) ) {
		$filters['max_price'] = iwf_sanitize_price( $_GET['max_price'] );
	}
	
	return apply_filters( 'iwf_active_filters', $filters );
}

/**
 * Clear product price range cache
 *
 * @return void
 */
function iwf_clear_price_range_cache() {
	wp_cache_delete( 'iwf_price_range' );
}

/**
 * Get filter button text
 *
 * @param string $context Optional. Context for the button text.
 * @return string Button text.
 */
function iwf_get_filter_button_text( $context = 'default' ) {
	$texts = array(
		'default' => __( 'Filter', 'impreza-woocommerce-filter' ),
		'clear'   => __( 'Clear Filters', 'impreza-woocommerce-filter' ),
		'apply'   => __( 'Apply Filters', 'impreza-woocommerce-filter' ),
	);
	
	return apply_filters( 'iwf_filter_button_text', isset( $texts[ $context ] ) ? $texts[ $context ] : $texts['default'], $context );
}

/**
 * Log debug information
 *
 * @param mixed  $message The message to log.
 * @param string $context Optional. Context for the log.
 * @return void
 */
function iwf_log_debug( $message, $context = 'impreza-filter' ) {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
		error_log( sprintf( '[%s] %s', $context, print_r( $message, true ) ) );
	}
}
