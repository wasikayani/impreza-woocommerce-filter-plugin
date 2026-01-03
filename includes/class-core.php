<?php
/**
 * Core Filter Class
 *
 * Handles all core filtering functionality including AJAX handlers
 * for product filtering, price range, sorting, categories, and taxonomies.
 *
 * @package Impreza_WooCommerce_Filter
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Impreza_WooCommerce_Filter_Core {

	/**
	 * Instance of this class.
	 *
	 * @var Impreza_WooCommerce_Filter_Core
	 */
	private static $instance = null;

	/**
	 * Get instance of this class.
	 *
	 * @return Impreza_WooCommerce_Filter_Core
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks
	 */
	private function init_hooks() {
		// AJAX handlers
		add_action( 'wp_ajax_iwf_filter_products', array( $this, 'ajax_filter_products' ) );
		add_action( 'wp_ajax_nopriv_iwf_filter_products', array( $this, 'ajax_filter_products' ) );

		add_action( 'wp_ajax_iwf_filter_by_price', array( $this, 'ajax_filter_by_price' ) );
		add_action( 'wp_ajax_nopriv_iwf_filter_by_price', array( $this, 'ajax_filter_by_price' ) );

		add_action( 'wp_ajax_iwf_filter_by_category', array( $this, 'ajax_filter_by_category' ) );
		add_action( 'wp_ajax_nopriv_iwf_filter_by_category', array( $this, 'ajax_filter_by_category' ) );

		add_action( 'wp_ajax_iwf_filter_by_intention', array( $this, 'ajax_filter_by_intention' ) );
		add_action( 'wp_ajax_nopriv_iwf_filter_by_intention', array( $this, 'ajax_filter_by_intention' ) );

		add_action( 'wp_ajax_iwf_sort_products', array( $this, 'ajax_sort_products' ) );
		add_action( 'wp_ajax_nopriv_iwf_sort_products', array( $this, 'ajax_sort_products' ) );

		add_action( 'wp_ajax_iwf_get_price_range', array( $this, 'ajax_get_price_range' ) );
		add_action( 'wp_ajax_nopriv_iwf_get_price_range', array( $this, 'ajax_get_price_range' ) );

		add_action( 'wp_ajax_iwf_reset_filters', array( $this, 'ajax_reset_filters' ) );
		add_action( 'wp_ajax_nopriv_iwf_reset_filters', array( $this, 'ajax_reset_filters' ) );
	}

	/**
	 * AJAX: Filter products based on multiple criteria
	 *
	 * @return void
	 */
	public function ajax_filter_products() {
		check_ajax_referer( 'iwf_nonce', 'nonce' );

		$categories = isset( $_POST['categories'] ) ? array_map( 'sanitize_text_field', (array) $_POST['categories'] ) : array();
		$intentions = isset( $_POST['intentions'] ) ? array_map( 'sanitize_text_field', (array) $_POST['intentions'] ) : array();
		$min_price = isset( $_POST['min_price'] ) ? floatval( $_POST['min_price'] ) : 0;
		$max_price = isset( $_POST['max_price'] ) ? floatval( $_POST['max_price'] ) : PHP_INT_MAX;
		$search = isset( $_POST['search'] ) ? sanitize_text_field( $_POST['search'] ) : '';
		$page = isset( $_POST['page'] ) ? intval( $_POST['page'] ) : 1;
		$per_page = isset( $_POST['per_page'] ) ? intval( $_POST['per_page'] ) : get_option( 'posts_per_page' );

		$args = array(
			'post_type'      => 'product',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			's'              => $search,
			'meta_query'     => array(),
			'tax_query'      => array(
				'relation' => 'AND',
			),
		);

		// Add price filter
		if ( $min_price > 0 || $max_price < PHP_INT_MAX ) {
			$args['meta_query'][] = array(
				'key'     => '_price',
				'value'   => array( $min_price, $max_price ),
				'compare' => 'BETWEEN',
				'type'    => 'NUMERIC',
			);
		}

		// Add category filter
		if ( ! empty( $categories ) ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => $categories,
				'operator' => 'IN',
			);
		}

		// Add intention taxonomy filter
		if ( ! empty( $intentions ) ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'intention',
				'field'    => 'slug',
				'terms'    => $intentions,
				'operator' => 'IN',
			);
		}

		$query = new WP_Query( $args );

		if ( $query->have_posts() ) {
			ob_start();
			while ( $query->have_posts() ) {
				$query->the_post();
				wc_get_template_part( 'content', 'product' );
			}
			$products_html = ob_get_clean();

			wp_send_json_success( array(
				'html'       => $products_html,
				'count'      => $query->found_posts,
				'max_pages'  => $query->max_num_pages,
				'current'    => $page,
			) );
		} else {
			wp_send_json_success( array(
				'html'      => '<p>' . esc_html__( 'No products found.', 'impreza-woocommerce-filter' ) . '</p>',
				'count'     => 0,
				'max_pages' => 0,
				'current'   => $page,
			) );
		}

		wp_reset_postdata();
		wp_die();
	}

	/**
	 * AJAX: Filter products by price range
	 *
	 * @return void
	 */
	public function ajax_filter_by_price() {
		check_ajax_referer( 'iwf_nonce', 'nonce' );

		$min_price = isset( $_POST['min_price'] ) ? floatval( $_POST['min_price'] ) : 0;
		$max_price = isset( $_POST['max_price'] ) ? floatval( $_POST['max_price'] ) : PHP_INT_MAX;
		$page = isset( $_POST['page'] ) ? intval( $_POST['page'] ) : 1;
		$per_page = get_option( 'posts_per_page' );

		$args = array(
			'post_type'      => 'product',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'meta_query'     => array(
				array(
					'key'     => '_price',
					'value'   => array( $min_price, $max_price ),
					'compare' => 'BETWEEN',
					'type'    => 'NUMERIC',
				),
			),
		);

		$query = new WP_Query( $args );

		if ( $query->have_posts() ) {
			ob_start();
			while ( $query->have_posts() ) {
				$query->the_post();
				wc_get_template_part( 'content', 'product' );
			}
			$products_html = ob_get_clean();

			wp_send_json_success( array(
				'html'       => $products_html,
				'count'      => $query->found_posts,
				'max_pages'  => $query->max_num_pages,
				'min'        => $min_price,
				'max'        => $max_price,
			) );
		} else {
			wp_send_json_success( array(
				'html'       => '<p>' . esc_html__( 'No products found in this price range.', 'impreza-woocommerce-filter' ) . '</p>',
				'count'      => 0,
				'max_pages'  => 0,
				'min'        => $min_price,
				'max'        => $max_price,
			) );
		}

		wp_reset_postdata();
		wp_die();
	}

	/**
	 * AJAX: Filter products by category
	 *
	 * @return void
	 */
	public function ajax_filter_by_category() {
		check_ajax_referer( 'iwf_nonce', 'nonce' );

		$categories = isset( $_POST['categories'] ) ? array_map( 'sanitize_text_field', (array) $_POST['categories'] ) : array();
		$page = isset( $_POST['page'] ) ? intval( $_POST['page'] ) : 1;
		$per_page = get_option( 'posts_per_page' );

		$args = array(
			'post_type'      => 'product',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'tax_query'      => array(
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'slug',
					'terms'    => $categories,
					'operator' => 'IN',
				),
			),
		);

		$query = new WP_Query( $args );

		if ( $query->have_posts() ) {
			ob_start();
			while ( $query->have_posts() ) {
				$query->the_post();
				wc_get_template_part( 'content', 'product' );
			}
			$products_html = ob_get_clean();

			wp_send_json_success( array(
				'html'       => $products_html,
				'count'      => $query->found_posts,
				'max_pages'  => $query->max_num_pages,
				'categories' => $categories,
			) );
		} else {
			wp_send_json_success( array(
				'html'       => '<p>' . esc_html__( 'No products found in selected categories.', 'impreza-woocommerce-filter' ) . '</p>',
				'count'      => 0,
				'max_pages'  => 0,
				'categories' => $categories,
			) );
		}

		wp_reset_postdata();
		wp_die();
	}

	/**
	 * AJAX: Filter products by intention taxonomy
	 *
	 * @return void
	 */
	public function ajax_filter_by_intention() {
		check_ajax_referer( 'iwf_nonce', 'nonce' );

		$intentions = isset( $_POST['intentions'] ) ? array_map( 'sanitize_text_field', (array) $_POST['intentions'] ) : array();
		$page = isset( $_POST['page'] ) ? intval( $_POST['page'] ) : 1;
		$per_page = get_option( 'posts_per_page' );

		$args = array(
			'post_type'      => 'product',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'tax_query'      => array(
				array(
					'taxonomy' => 'intention',
					'field'    => 'slug',
					'terms'    => $intentions,
					'operator' => 'IN',
				),
			),
		);

		$query = new WP_Query( $args );

		if ( $query->have_posts() ) {
			ob_start();
			while ( $query->have_posts() ) {
				$query->the_post();
				wc_get_template_part( 'content', 'product' );
			}
			$products_html = ob_get_clean();

			wp_send_json_success( array(
				'html'       => $products_html,
				'count'      => $query->found_posts,
				'max_pages'  => $query->max_num_pages,
				'intentions' => $intentions,
			) );
		} else {
			wp_send_json_success( array(
				'html'       => '<p>' . esc_html__( 'No products found with selected intentions.', 'impreza-woocommerce-filter' ) . '</p>',
				'count'      => 0,
				'max_pages'  => 0,
				'intentions' => $intentions,
			) );
		}

		wp_reset_postdata();
		wp_die();
	}

	/**
	 * AJAX: Sort products
	 *
	 * @return void
	 */
	public function ajax_sort_products() {
		check_ajax_referer( 'iwf_nonce', 'nonce' );

		$sort_by = isset( $_POST['sort_by'] ) ? sanitize_text_field( $_POST['sort_by'] ) : 'default';
		$page = isset( $_POST['page'] ) ? intval( $_POST['page'] ) : 1;
		$per_page = get_option( 'posts_per_page' );

		$args = array(
			'post_type'      => 'product',
			'posts_per_page' => $per_page,
			'paged'          => $page,
		);

		// Apply sorting logic
		switch ( $sort_by ) {
			case 'price_asc':
				$args['meta_key'] = '_price';
				$args['orderby'] = 'meta_value_num';
				$args['order'] = 'ASC';
				break;
			case 'price_desc':
				$args['meta_key'] = '_price';
				$args['orderby'] = 'meta_value_num';
				$args['order'] = 'DESC';
				break;
			case 'newest':
				$args['orderby'] = 'date';
				$args['order'] = 'DESC';
				break;
			case 'oldest':
				$args['orderby'] = 'date';
				$args['order'] = 'ASC';
				break;
			case 'best_selling':
				$args['meta_key'] = 'total_sales';
				$args['orderby'] = 'meta_value_num';
				$args['order'] = 'DESC';
				break;
			case 'rating':
				$args['orderby'] = 'meta_value_num';
				$args['meta_key'] = '_wc_average_rating';
				$args['order'] = 'DESC';
				break;
			case 'alphabetical':
			default:
				$args['orderby'] = 'title';
				$args['order'] = 'ASC';
				break;
		}

		$query = new WP_Query( $args );

		if ( $query->have_posts() ) {
			ob_start();
			while ( $query->have_posts() ) {
				$query->the_post();
				wc_get_template_part( 'content', 'product' );
			}
			$products_html = ob_get_clean();

			wp_send_json_success( array(
				'html'       => $products_html,
				'count'      => $query->found_posts,
				'max_pages'  => $query->max_num_pages,
				'sort_by'    => $sort_by,
			) );
		} else {
			wp_send_json_success( array(
				'html'       => '<p>' . esc_html__( 'No products found.', 'impreza-woocommerce-filter' ) . '</p>',
				'count'      => 0,
				'max_pages'  => 0,
				'sort_by'    => $sort_by,
			) );
		}

		wp_reset_postdata();
		wp_die();
	}

	/**
	 * AJAX: Get price range for products
	 *
	 * @return void
	 */
	public function ajax_get_price_range() {
		check_ajax_referer( 'iwf_nonce', 'nonce' );

		global $wpdb;

		// Get min and max prices from products
		$results = $wpdb->get_row(
			"SELECT MIN(meta_value) as min_price, MAX(meta_value) as max_price
			FROM {$wpdb->postmeta}
			WHERE meta_key = '_price'
			AND post_id IN (
				SELECT ID FROM {$wpdb->posts}
				WHERE post_type = 'product'
				AND post_status = 'publish'
			)"
		);

		$min_price = $results && $results->min_price ? floatval( $results->min_price ) : 0;
		$max_price = $results && $results->max_price ? floatval( $results->max_price ) : 1000;

		wp_send_json_success( array(
			'min' => $min_price,
			'max' => $max_price,
		) );

		wp_die();
	}

	/**
	 * AJAX: Reset all filters
	 *
	 * @return void
	 */
	public function ajax_reset_filters() {
		check_ajax_referer( 'iwf_nonce', 'nonce' );

		$page = isset( $_POST['page'] ) ? intval( $_POST['page'] ) : 1;
		$per_page = get_option( 'posts_per_page' );

		$args = array(
			'post_type'      => 'product',
			'posts_per_page' => $per_page,
			'paged'          => $page,
		);

		$query = new WP_Query( $args );

		if ( $query->have_posts() ) {
			ob_start();
			while ( $query->have_posts() ) {
				$query->the_post();
				wc_get_template_part( 'content', 'product' );
			}
			$products_html = ob_get_clean();

			wp_send_json_success( array(
				'html'       => $products_html,
				'count'      => $query->found_posts,
				'max_pages'  => $query->max_num_pages,
				'message'    => esc_html__( 'Filters reset successfully.', 'impreza-woocommerce-filter' ),
			) );
		} else {
			wp_send_json_error( array(
				'message' => esc_html__( 'No products available.', 'impreza-woocommerce-filter' ),
			) );
		}

		wp_reset_postdata();
		wp_die();
	}

	/**
	 * Get all product categories with counts
	 *
	 * @return array
	 */
	public function get_product_categories() {
		$categories = get_terms( array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => true,
		) );

		if ( is_wp_error( $categories ) ) {
			return array();
		}

		return $categories;
	}

	/**
	 * Get all intention terms with counts
	 *
	 * @return array
	 */
	public function get_intention_terms() {
		$intentions = get_terms( array(
			'taxonomy'   => 'intention',
			'hide_empty' => true,
		) );

		if ( is_wp_error( $intentions ) ) {
			return array();
		}

		return $intentions;
	}

	/**
	 * Build product query with filters
	 *
	 * @param array $filters Array of filter parameters.
	 * @return WP_Query
	 */
	public function build_product_query( $filters = array() ) {
		$defaults = array(
			'categories' => array(),
			'intentions' => array(),
			'min_price'  => 0,
			'max_price'  => PHP_INT_MAX,
			'search'     => '',
			'page'       => 1,
			'per_page'   => get_option( 'posts_per_page' ),
			'sort_by'    => 'default',
		);

		$filters = wp_parse_args( $filters, $defaults );

		$args = array(
			'post_type'      => 'product',
			'posts_per_page' => $filters['per_page'],
			'paged'          => $filters['page'],
			's'              => $filters['search'],
			'meta_query'     => array(),
			'tax_query'      => array(
				'relation' => 'AND',
			),
		);

		// Add price filter
		if ( $filters['min_price'] > 0 || $filters['max_price'] < PHP_INT_MAX ) {
			$args['meta_query'][] = array(
				'key'     => '_price',
				'value'   => array( $filters['min_price'], $filters['max_price'] ),
				'compare' => 'BETWEEN',
				'type'    => 'NUMERIC',
			);
		}

		// Add category filter
		if ( ! empty( $filters['categories'] ) ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'slug',
				'terms'    => $filters['categories'],
				'operator' => 'IN',
			);
		}

		// Add intention filter
		if ( ! empty( $filters['intentions'] ) ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'intention',
				'field'    => 'slug',
				'terms'    => $filters['intentions'],
				'operator' => 'IN',
			);
		}

		// Apply sorting
		$this->apply_sorting( $args, $filters['sort_by'] );

		return new WP_Query( $args );
	}

	/**
	 * Apply sorting to query arguments
	 *
	 * @param array  $args Query arguments.
	 * @param string $sort_by Sorting option.
	 * @return void
	 */
	private function apply_sorting( &$args, $sort_by = 'default' ) {
		switch ( $sort_by ) {
			case 'price_asc':
				$args['meta_key'] = '_price';
				$args['orderby'] = 'meta_value_num';
				$args['order'] = 'ASC';
				break;
			case 'price_desc':
				$args['meta_key'] = '_price';
				$args['orderby'] = 'meta_value_num';
				$args['order'] = 'DESC';
				break;
			case 'newest':
				$args['orderby'] = 'date';
				$args['order'] = 'DESC';
				break;
			case 'oldest':
				$args['orderby'] = 'date';
				$args['order'] = 'ASC';
				break;
			case 'best_selling':
				$args['meta_key'] = 'total_sales';
				$args['orderby'] = 'meta_value_num';
				$args['order'] = 'DESC';
				break;
			case 'rating':
				$args['meta_key'] = '_wc_average_rating';
				$args['orderby'] = 'meta_value_num';
				$args['order'] = 'DESC';
				break;
			case 'alphabetical':
			default:
				$args['orderby'] = 'title';
				$args['order'] = 'ASC';
				break;
		}
	}

	/**
	 * Get products as HTML
	 *
	 * @param WP_Query $query Product query.
	 * @return string
	 */
	public function get_products_html( $query ) {
		if ( ! $query->have_posts() ) {
			return '<p>' . esc_html__( 'No products found.', 'impreza-woocommerce-filter' ) . '</p>';
		}

		ob_start();
		while ( $query->have_posts() ) {
			$query->the_post();
			wc_get_template_part( 'content', 'product' );
		}
		wp_reset_postdata();

		return ob_get_clean();
	}
}
