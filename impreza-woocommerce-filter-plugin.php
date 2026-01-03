<?php
/**
 * Plugin Name: Impreza WooCommerce Filter Plugin
 * Plugin URI: https://github.com/wasikayani/impreza-woocommerce-filter-plugin
 * Description: Advanced filtering capabilities for WooCommerce products with multiple filter options and enhanced user experience.
 * Version: 1.0.0
 * Author: Wasik Ayani
 * Author URI: https://github.com/wasikayani
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: impreza-wc-filter
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Define plugin constants
 */
define( 'IMPREZA_WC_FILTER_VERSION', '1.0.0' );
define( 'IMPREZA_WC_FILTER_PLUGIN_FILE', __FILE__ );
define( 'IMPREZA_WC_FILTER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'IMPREZA_WC_FILTER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'IMPREZA_WC_FILTER_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Check if WooCommerce is active
 */
function impreza_wc_filter_is_woocommerce_active() {
	return in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) );
}

/**
 * Display admin notice if WooCommerce is not active
 */
function impreza_wc_filter_missing_woocommerce() {
	if ( current_user_can( 'manage_options' ) ) {
		?>
		<div class="notice notice-error is-dismissible">
			<p><?php esc_html_e( 'Impreza WooCommerce Filter Plugin requires WooCommerce to be installed and activated.', 'impreza-wc-filter' ); ?></p>
		</div>
		<?php
	}
}

/**
 * Main Plugin Class
 */
class Impreza_WC_Filter {

	/**
	 * Instance of the class
	 *
	 * @var Impreza_WC_Filter
	 */
	private static $instance = null;

	/**
	 * Get singleton instance
	 *
	 * @return Impreza_WC_Filter
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
		// Check WooCommerce dependency
		if ( ! impreza_wc_filter_is_woocommerce_active() ) {
			add_action( 'admin_notices', 'impreza_wc_filter_missing_woocommerce' );
			return;
		}

		// Initialize plugin
		$this->init();
	}

	/**
	 * Initialize plugin
	 */
	private function init() {
		// Load text domain
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

		// Include required files
		$this->load_files();

		// Hook plugin activation
		register_activation_hook( IMPREZA_WC_FILTER_PLUGIN_FILE, array( $this, 'activate' ) );

		// Hook plugin deactivation
		register_deactivation_hook( IMPREZA_WC_FILTER_PLUGIN_FILE, array( $this, 'deactivate' ) );

		// Enqueue scripts and styles
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * Load text domain for translations
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'impreza-wc-filter',
			false,
			dirname( IMPREZA_WC_FILTER_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Load required plugin files
	 */
	private function load_files() {
		// Core functionality
		require_once IMPREZA_WC_FILTER_PLUGIN_DIR . 'includes/class-core.php';

		// Admin functionality
		if ( is_admin() ) {
			require_once IMPREZA_WC_FILTER_PLUGIN_DIR . 'includes/class-admin.php';
		}

		// Frontend functionality
		require_once IMPREZA_WC_FILTER_PLUGIN_DIR . 'includes/class-frontend.php';

		// Utilities
		require_once IMPREZA_WC_FILTER_PLUGIN_DIR . 'includes/functions.php';
	}

	/**
	 * Plugin activation hook
	 */
	public function activate() {
		// Check WooCommerce dependency
		if ( ! impreza_wc_filter_is_woocommerce_active() ) {
			wp_die( esc_html__( 'This plugin requires WooCommerce to be installed and activated.', 'impreza-wc-filter' ) );
		}

		// Create database tables if needed
		$this->create_tables();

		// Set default options
		$this->set_default_options();

		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation hook
	 */
	public function deactivate() {
		// Flush rewrite rules
		flush_rewrite_rules();

		// Clean up transients if needed
		// Add deactivation cleanup code here
	}

	/**
	 * Create database tables
	 */
	private function create_tables() {
		// Add table creation logic here if needed
	}

	/**
	 * Set default plugin options
	 */
	private function set_default_options() {
		// Set default options if not already set
		$default_options = array(
			'impreza_wc_filter_enabled' => 1,
			'impreza_wc_filter_position' => 'sidebar',
			'impreza_wc_filter_display_type' => 'checkbox',
		);

		foreach ( $default_options as $option_name => $option_value ) {
			if ( ! get_option( $option_name ) ) {
				update_option( $option_name, $option_value );
			}
		}
	}

	/**
	 * Enqueue frontend scripts and styles
	 */
	public function enqueue_frontend_scripts() {
		// Only enqueue on shop and product pages
		if ( is_shop() || is_product_category() || is_product_tag() ) {
			// CSS
			wp_enqueue_style(
				'impreza-wc-filter-frontend',
				IMPREZA_WC_FILTER_PLUGIN_URL . 'assets/css/frontend.css',
				array(),
				IMPREZA_WC_FILTER_VERSION
			);

			// JS
			wp_enqueue_script(
				'impreza-wc-filter-frontend',
				IMPREZA_WC_FILTER_PLUGIN_URL . 'assets/js/frontend.js',
				array( 'jquery' ),
				IMPREZA_WC_FILTER_VERSION,
				true
			);

			// Localize script with AJAX URL
			wp_localize_script(
				'impreza-wc-filter-frontend',
				'impreza_wc_filter',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'nonce'    => wp_create_nonce( 'impreza_wc_filter_nonce' ),
				)
			);
		}
	}

	/**
	 * Enqueue admin scripts and styles
	 */
	public function enqueue_admin_scripts() {
		// CSS
		wp_enqueue_style(
			'impreza-wc-filter-admin',
			IMPREZA_WC_FILTER_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			IMPREZA_WC_FILTER_VERSION
		);

		// JS
		wp_enqueue_script(
			'impreza-wc-filter-admin',
			IMPREZA_WC_FILTER_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			IMPREZA_WC_FILTER_VERSION,
			true
		);
	}

	/**
	 * Get plugin version
	 *
	 * @return string
	 */
	public function get_version() {
		return IMPREZA_WC_FILTER_VERSION;
	}

	/**
	 * Get plugin directory path
	 *
	 * @return string
	 */
	public function get_plugin_dir() {
		return IMPREZA_WC_FILTER_PLUGIN_DIR;
	}

	/**
	 * Get plugin URL
	 *
	 * @return string
	 */
	public function get_plugin_url() {
		return IMPREZA_WC_FILTER_PLUGIN_URL;
	}
}

/**
 * Initialize the plugin
 */
function impreza_wc_filter() {
	return Impreza_WC_Filter::get_instance();
}

// Start the plugin
impreza_wc_filter();
