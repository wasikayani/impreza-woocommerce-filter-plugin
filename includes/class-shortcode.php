<?php
/**
 * Impreza WooCommerce Filter Plugin - Shortcode Handler
 *
 * @package Impreza_WooCommerce_Filter
 * @subpackage Includes
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Shortcode Class
 *
 * Handles the [impreza_filter] shortcode functionality.
 */
class Impreza_Shortcode {

	/**
	 * Constructor
	 *
	 * Initialize the shortcode handler.
	 */
	public function __construct() {
		add_shortcode( 'impreza_filter', array( $this, 'render_filter' ) );
	}

	/**
	 * Render the filter shortcode
	 *
	 * Callback function for the [impreza_filter] shortcode.
	 *
	 * @param array  $atts    Shortcode attributes.
	 * @param string $content Shortcode content.
	 * @return string HTML output of the filter.
	 */
	public function render_filter( $atts = array(), $content = '' ) {
		// Parse shortcode attributes with defaults.
		$atts = shortcode_atts(
			array(
				'title'         => '',
				'category'      => '',
				'product_count' => 12,
				'columns'       => 3,
				'orderby'       => 'title',
				'order'         => 'ASC',
			),
			$atts,
			'impreza_filter'
		);

		// Validate attributes.
		$atts = $this->validate_attributes( $atts );

		// Enqueue necessary scripts and styles.
		$this->enqueue_assets();

		// Start output buffering.
		ob_start();

		// Include the filter template.
		$this->display_filter_template( $atts );

		// Get the buffered output.
		$output = ob_get_clean();

		return $output;
	}

	/**
	 * Validate and sanitize shortcode attributes
	 *
	 * @param array $atts Shortcode attributes.
	 * @return array Validated attributes.
	 */
	private function validate_attributes( $atts ) {
		// Sanitize string attributes.
		if ( ! empty( $atts['title'] ) ) {
			$atts['title'] = sanitize_text_field( $atts['title'] );
		}

		if ( ! empty( $atts['category'] ) ) {
			$atts['category'] = sanitize_text_field( $atts['category'] );
		}

		// Validate and convert numeric attributes.
		$atts['product_count'] = absint( $atts['product_count'] );
		$atts['columns']       = absint( $atts['columns'] );

		// Validate orderby parameter.
		$allowed_orderby = array( 'title', 'date', 'price', 'popularity', 'rating' );
		if ( ! in_array( $atts['orderby'], $allowed_orderby, true ) ) {
			$atts['orderby'] = 'title';
		}

		// Validate order parameter.
		$atts['order'] = strtoupper( $atts['order'] );
		if ( ! in_array( $atts['order'], array( 'ASC', 'DESC' ), true ) ) {
			$atts['order'] = 'ASC';
		}

		return $atts;
	}

	/**
	 * Enqueue required scripts and styles
	 */
	private function enqueue_assets() {
		// Enqueue WooCommerce scripts if not already loaded.
		wp_enqueue_script( 'wc-add-to-cart' );
	}

	/**
	 * Display the filter template
	 *
	 * @param array $atts Validated shortcode attributes.
	 */
	private function display_filter_template( $atts ) {
		// Build WooCommerce product query args.
		$args = $this->build_query_args( $atts );

		// Get products.
		$products = wc_get_products( $args );

		// Display the filter section.
		?>
		<div class="impreza-filter-wrapper">
			<?php if ( ! empty( $atts['title'] ) ) : ?>
				<h2 class="impreza-filter-title"><?php echo esc_html( $atts['title'] ); ?></h2>
			<?php endif; ?>

			<div class="impreza-filter-container">
				<?php if ( ! empty( $products ) ) : ?>
					<div class="impreza-filter-products" data-columns="<?php echo esc_attr( $atts['columns'] ); ?>">
						<?php foreach ( $products as $product ) : ?>
							<div class="impreza-filter-product">
								<?php
									// Display product.
									wc_get_template_part(
										'content',
										'product'
									);
								?>
							</div>
						<?php endforeach; ?>
					</div>
				<?php else : ?>
					<p class="impreza-filter-no-products">
						<?php esc_html_e( 'No products found.', 'impreza-woocommerce-filter' ); ?>
					</p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Build WooCommerce product query arguments
	 *
	 * @param array $atts Validated shortcode attributes.
	 * @return array Query arguments for wc_get_products().
	 */
	private function build_query_args( $atts ) {
		$args = array(
			'limit'   => $atts['product_count'],
			'orderby' => $atts['orderby'],
			'order'   => $atts['order'],
			'status'  => 'publish',
		);

		// Add category filter if specified.
		if ( ! empty( $atts['category'] ) ) {
			$category = get_term_by( 'slug', $atts['category'], 'product_cat' );
			if ( $category ) {
				$args['category'] = array( $category->term_id );
			}
		}

		return apply_filters( 'impreza_filter_query_args', $args, $atts );
	}
}

// Initialize the shortcode.
new Impreza_Shortcode();
