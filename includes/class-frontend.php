<?php
/**
 * Frontend Class for Impreza WooCommerce Filter Plugin
 *
 * Handles all frontend functionality including filter panel display,
 * AJAX requests, and filter logic for WooCommerce products.
 *
 * @package Impreza_WooCommerce_Filter_Plugin
 * @subpackage Includes
 * @since 1.0.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Impreza Frontend Class
 *
 * Manages frontend display of filter panel and handles product filtering functionality.
 *
 * @class Impreza_Frontend
 * @since 1.0.0
 */
class Impreza_Frontend {

	/**
	 * Constructor
	 *
	 * Initialize frontend hooks and filters
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks
	 *
	 * @since 1.0.0
	 */
	public function init_hooks() {
		// Display filter panel on shop pages
		add_action( 'woocommerce_before_shop_loop', array( $this, 'display_filter_panel' ), 5 );
		add_action( 'woocommerce_before_main_content', array( $this, 'display_filter_panel' ), 5 );

		// Enqueue frontend scripts and styles
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// AJAX handlers for filtering
		add_action( 'wp_ajax_nopriv_impreza_filter_products', array( $this, 'ajax_filter_products' ) );
		add_action( 'wp_ajax_impreza_filter_products', array( $this, 'ajax_filter_products' ) );

		// Handle filter reset
		add_action( 'wp_ajax_nopriv_impreza_reset_filters', array( $this, 'ajax_reset_filters' ) );
		add_action( 'wp_ajax_impreza_reset_filters', array( $this, 'ajax_reset_filters' ) );
	}

	/**
	 * Enqueue frontend scripts and styles
	 *
	 * @since 1.0.0
	 */
	public function enqueue_assets() {
		// Only enqueue on shop and product category pages
		if ( ! ( is_shop() || is_product_category() || is_product_tag() ) ) {
			return;
		}

		// Enqueue CSS
		wp_enqueue_style(
			'impreza-filter-frontend',
			IMPREZA_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			IMPREZA_PLUGIN_VERSION,
			'all'
		);

		// Enqueue JavaScript
		wp_enqueue_script(
			'impreza-filter-frontend',
			IMPREZA_PLUGIN_URL . 'assets/js/frontend.js',
			array( 'jquery', 'jquery-ui-slider' ),
			IMPREZA_PLUGIN_VERSION,
			true
		);

		// Localize script
		wp_localize_script(
			'impreza-filter-frontend',
			'impreza_filter_config',
			array(
				'ajax_url'       => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'impreza_filter_nonce' ),
				'shop_url'       => wc_get_page_permalink( 'shop' ),
				'price_format'   => get_woocommerce_price_format(),
				'currency_symbol' => get_woocommerce_currency_symbol(),
			)
		);
	}

	/**
	 * Display filter panel
	 *
	 * Renders the filter panel HTML on shop pages
	 *
	 * @since 1.0.0
	 */
	public function display_filter_panel() {
		// Only display on shop pages once
		if ( ! ( is_shop() || is_product_category() || is_product_tag() ) ) {
			return;
		}

		// Check if filter panel is enabled in settings
		$filter_enabled = get_option( 'impreza_filter_enable', true );
		if ( ! $filter_enabled ) {
			return;
		}

		// Get filter data
		$filters = $this->get_available_filters();

		// Load template
		$this->load_template( 'filter-panel.php', array( 'filters' => $filters ) );
	}

	/**
	 * Get available filters
	 *
	 * Retrieves filter categories and values from settings
	 *
	 * @since 1.0.0
	 * @return array Array of available filters
	 */
	public function get_available_filters() {
		$filters = array();

		// Get enabled filter types from options
		$enabled_filters = get_option( 'impreza_filter_types', array( 'category', 'price', 'attribute' ) );

		if ( in_array( 'category', $enabled_filters, true ) ) {
			$filters['category'] = $this->get_category_filters();
		}

		if ( in_array( 'price', $enabled_filters, true ) ) {
			$filters['price'] = $this->get_price_filter();
		}

		if ( in_array( 'attribute', $enabled_filters, true ) ) {
			$filters['attributes'] = $this->get_attribute_filters();
		}

		if ( in_array( 'rating', $enabled_filters, true ) ) {
			$filters['rating'] = $this->get_rating_filter();
		}

		if ( in_array( 'stock', $enabled_filters, true ) ) {
			$filters['stock'] = $this->get_stock_filter();
		}

		return $filters;
	}

	/**
	 * Get category filters
	 *
	 * @since 1.0.0
	 * @return array Category filter data
	 */
	private function get_category_filters() {
		$categories = get_terms( array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => true,
		) );

		return array(
			'name'   => __( 'Categories', 'impreza-woocommerce-filter' ),
			'type'   => 'category',
			'values' => $categories,
		);
	}

	/**
	 * Get price filter data
	 *
	 * @since 1.0.0
	 * @return array Price filter data
	 */
	private function get_price_filter() {
		global $wpdb;

		// Get min and max prices
		$min_price = get_option( 'impreza_filter_min_price', 0 );
		$max_price = get_option( 'impreza_filter_max_price', 0 );

		// If not set, calculate from products
		if ( 0 === (int) $max_price ) {
			$result = $wpdb->get_row(
				"SELECT MIN(meta_value) as min_price, MAX(meta_value) as max_price 
				FROM {$wpdb->postmeta} 
				WHERE meta_key = '_price' 
				AND meta_value != ''"
			);

			if ( $result ) {
				$min_price = floor( (float) $result->min_price );
				$max_price = ceil( (float) $result->max_price );
			}
		}

		return array(
			'name'     => __( 'Price', 'impreza-woocommerce-filter' ),
			'type'     => 'price',
			'min'      => $min_price,
			'max'      => $max_price,
			'current'  => array(
				'min' => isset( $_GET['min_price'] ) ? intval( $_GET['min_price'] ) : $min_price,
				'max' => isset( $_GET['max_price'] ) ? intval( $_GET['max_price'] ) : $max_price,
			),
		);
	}

	/**
	 * Get product attribute filters
	 *
	 * @since 1.0.0
	 * @return array Attribute filter data
	 */
	private function get_attribute_filters() {
		$attributes = array();
		$product_attributes = wc_get_attribute_taxonomy_names();

		foreach ( $product_attributes as $attribute ) {
			$attr_terms = get_terms( array(
				'taxonomy'   => $attribute,
				'hide_empty' => true,
			) );

			if ( ! is_wp_error( $attr_terms ) && ! empty( $attr_terms ) ) {
				$attr_name = wc_attribute_label( $attribute );
				$attributes[ $attribute ] = array(
					'name'   => $attr_name,
					'type'   => 'attribute',
					'values' => $attr_terms,
				);
			}
		}

		return $attributes;
	}

	/**
	 * Get rating filter
		 *
	 * @since 1.0.0
	 * @return array Rating filter data
	 */
	private function get_rating_filter() {
		return array(
			'name'   => __( 'Rating', 'impreza-woocommerce-filter' ),
			'type'   => 'rating',
			'values' => array(
				array( 'value' => 5, 'label' => __( '5 Stars', 'impreza-woocommerce-filter' ) ),
				array( 'value' => 4, 'label' => __( '4 Stars & Up', 'impreza-woocommerce-filter' ) ),
				array( 'value' => 3, 'label' => __( '3 Stars & Up', 'impreza-woocommerce-filter' ) ),
				array( 'value' => 2, 'label' => __( '2 Stars & Up', 'impreza-woocommerce-filter' ) ),
				array( 'value' => 1, 'label' => __( '1 Star & Up', 'impreza-woocommerce-filter' ) ),
			),
		);
	}

	/**
	 * Get stock status filter
	 *
	 * @since 1.0.0
	 * @return array Stock filter data
	 */
	private function get_stock_filter() {
		return array(
			'name'   => __( 'Stock Status', 'impreza-woocommerce-filter' ),
			'type'   => 'stock',
			'values' => array(
				array( 'value' => 'instock', 'label' => __( 'In Stock', 'impreza-woocommerce-filter' ) ),
				array( 'value' => 'outofstock', 'label' => __( 'Out of Stock', 'impreza-woocommerce-filter' ) ),
			),
		);
	}

	/**
	 * AJAX handler for filtering products
	 *
	 * Processes filter requests and returns filtered products
	 *
	 * @since 1.0.0
	 */
	public function ajax_filter_products() {
		// Verify nonce
		check_ajax_referer( 'impreza_filter_nonce', 'nonce' );

		// Get filter parameters
		$filters = isset( $_POST['filters'] ) ? wp_parse_args( $_POST['filters'], array() ) : array();

		// Build WP_Query arguments
		$args = $this->build_query_args( $filters );

		// Execute query
		$products = wc_get_products( $args );

		// Prepare response
		$response = array(
			'success'  => true,
			'products' => array(),
			'count'    => count( $products ),
		);

		// Render product HTML
		foreach ( $products as $product ) {
			ob_start();
			wc_get_template_part( 'content', 'product' );
			$response['products'][] = ob_get_clean();
		}

		// Send JSON response
		wp_send_json( $response );
	}

	/**
	 * Build WP_Query arguments from filters
	 *
	 * @since 1.0.0
	 * @param array $filters Filter parameters
	 * @return array Query arguments for wc_get_products()
	 */
	private function build_query_args( $filters ) {
		$args = array(
			'status'  => 'publish',
			'limit'   => get_option( 'posts_per_page', 12 ),
			'orderby' => isset( $filters['orderby'] ) ? sanitize_text_field( $filters['orderby'] ) : 'date',
			'order'   => isset( $filters['order'] ) ? sanitize_text_field( $filters['order'] ) : 'DESC',
		);

		// Category filter
		if ( ! empty( $filters['categories'] ) ) {
			$args['category'] = array_map( 'intval', (array) $filters['categories'] );
		}

		// Price filter
		if ( ! empty( $filters['min_price'] ) || ! empty( $filters['max_price'] ) ) {
			$args['price'] = array(
				'min' => isset( $filters['min_price'] ) ? floatval( $filters['min_price'] ) : 0,
				'max' => isset( $filters['max_price'] ) ? floatval( $filters['max_price'] ) : PHP_INT_MAX,
			);
		}

		// Attribute filters
		if ( ! empty( $filters['attributes'] ) ) {
			$args['attribute'] = array();
			foreach ( $filters['attributes'] as $attribute => $values ) {
				$args['attribute'][ $attribute ] = array_map( 'sanitize_text_field', (array) $values );
			}
		}

		// Rating filter
		if ( ! empty( $filters['rating'] ) ) {
			$args['rating'] = intval( $filters['rating'] );
		}

		// Stock filter
		if ( ! empty( $filters['stock_status'] ) ) {
			$args['stock_status'] = sanitize_text_field( $filters['stock_status'] );
		}

		/**
		 * Filter query arguments before execution
		 *
		 * @since 1.0.0
		 * @param array $args Query arguments
		 * @param array $filters Original filter parameters
		 */
		return apply_filters( 'impreza_filter_query_args', $args, $filters );
	}

	/**
	 * AJAX handler for resetting filters
	 *
	 * @since 1.0.0
	 */
	public function ajax_reset_filters() {
		// Verify nonce
		check_ajax_referer( 'impreza_filter_nonce', 'nonce' );

		// Get all products
		$args = array(
			'status' => 'publish',
			'limit'  => get_option( 'posts_per_page', 12 ),
		);

		$products = wc_get_products( $args );

		// Prepare response
		$response = array(
			'success'  => true,
			'products' => array(),
			'count'    => count( $products ),
		);

		// Render product HTML
		foreach ( $products as $product ) {
			ob_start();
			wc_get_template_part( 'content', 'product' );
			$response['products'][] = ob_get_clean();
		}

		wp_send_json( $response );
	}

	/**
	 * Load template file
	 *
	 * @since 1.0.0
	 * @param string $template Template file name
	 * @param array  $data Data to pass to template
	 */
	private function load_template( $template, $data = array() ) {
		$file = IMPREZA_PLUGIN_DIR . 'templates/' . $template;

		if ( file_exists( $file ) ) {
			extract( $data );
			include $file;
		}
	}

	/**
	 * Get active filters from request
	 *
	 * Retrieves currently active filters from GET parameters
	 *
	 * @since 1.0.0
	 * @return array Active filters
	 */
	public function get_active_filters() {
		$active = array();

		// Categories
		if ( ! empty( $_GET['filter_cat'] ) ) {
			$active['categories'] = array_map( 'intval', (array) $_GET['filter_cat'] );
		}

		// Price range
		if ( ! empty( $_GET['min_price'] ) || ! empty( $_GET['max_price'] ) ) {
			$active['price'] = array(
				'min' => isset( $_GET['min_price'] ) ? floatval( $_GET['min_price'] ) : 0,
				'max' => isset( $_GET['max_price'] ) ? floatval( $_GET['max_price'] ) : PHP_INT_MAX,
			);
		}

		// Attributes
		foreach ( $_GET as $key => $value ) {
			if ( strpos( $key, 'filter_' ) === 0 && $key !== 'filter_cat' ) {
				$attr = str_replace( 'filter_', '', $key );
				$active['attributes'][ $attr ] = array_map( 'sanitize_text_field', (array) $value );
			}
		}

		return $active;
	}
}
