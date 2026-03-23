<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://profiles.wordpress.org/orionaselite/
 * @since      1.0.0
 *
 * @package    Skroutz_Xml_Feed_For_Woocommerce
 * @subpackage Skroutz_Xml_Feed_For_Woocommerce/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Skroutz_Xml_Feed_For_Woocommerce
 * @subpackage Skroutz_Xml_Feed_For_Woocommerce/includes
 * @author     George Nicolaou <orionas.elite@gmail.com>
 */
class Skroutz_Xml_Feed_For_Woocommerce {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Skroutz_Xml_Feed_For_Woocommerce_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $skroutzXmlFeedForWoocommerce    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	protected $admin;

	protected $public;

	protected $logger;

	protected $generator;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'SKROUTZ_XML_FEED_FOR_WOOCOMMERCE_VERSION' ) ) {
			$this->version = SKROUTZ_XML_FEED_FOR_WOOCOMMERCE_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'skroutz-xml-feed-for-woocommerce';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_shared_hooks();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Skroutz_Xml_Feed_For_Woocommerce_Loader. Orchestrates the hooks of the plugin.
	 * - Skroutz_Xml_Feed_For_Woocommerce_i18n. Defines internationalization functionality.
	 * - Skroutz_Xml_Feed_For_Woocommerce_Admin. Defines all hooks for the admin area.
	 * - Skroutz_Xml_Feed_For_Woocommerce_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-skroutz-xml-feed-for-woocommerce-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-skroutz-xml-feed-for-woocommerce-i18n.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-skroutz-xml-feed-for-woocommerce-settings.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-skroutz-xml-feed-for-woocommerce-logger.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-skroutz-xml-feed-for-woocommerce-feed-generator.php';
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-skroutz-xml-feed-for-woocommerce-updater.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-skroutz-xml-feed-for-woocommerce-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-skroutz-xml-feed-for-woocommerce-public.php';

		$this->loader = new Skroutz_Xml_Feed_For_Woocommerce_Loader();
		$this->logger = new Skroutz_Xml_Feed_For_Woocommerce_Logger();
		$this->generator = new Skroutz_Xml_Feed_For_Woocommerce_Feed_Generator( $this->logger );
		$this->admin = new Skroutz_Xml_Feed_For_Woocommerce_Admin( $this->get_plugin_name(), $this->get_version(), $this->generator, $this->logger );
		$this->public = new Skroutz_Xml_Feed_For_Woocommerce_Public( $this->get_plugin_name(), $this->get_version(), $this->generator, $this->logger );

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Skroutz_Xml_Feed_For_Woocommerce_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Skroutz_Xml_Feed_For_Woocommerce_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	private function define_shared_hooks() {
		$updater = new Skroutz_Xml_Feed_For_Woocommerce_Updater( $this->logger );

		$this->loader->add_action( 'plugins_loaded', $updater, 'register', 5 );
		$this->loader->add_action( 'admin_notices', $this->admin, 'render_missing_woocommerce_notice' );
		$this->loader->add_action( 'save_post_product', $this->admin, 'invalidate_cache_for_post' );
		$this->loader->add_action( 'save_post_product_variation', $this->admin, 'invalidate_cache_for_post' );
		$this->loader->add_action( 'before_delete_post', $this->admin, 'invalidate_cache_for_post' );
		$this->loader->add_action( 'trashed_post', $this->admin, 'invalidate_cache_for_post' );
		$this->loader->add_action( 'untrashed_post', $this->admin, 'invalidate_cache_for_post' );
		$this->loader->add_action( 'update_option_' . Skroutz_Xml_Feed_For_Woocommerce_Settings::OPTION_KEY, $this->admin, 'invalidate_cache_after_settings_update', 10, 2 );
		$this->loader->add_action( 'woocommerce_product_set_stock', $this->admin, 'invalidate_cache_for_stock_change' );
		$this->loader->add_action( 'woocommerce_variation_set_stock', $this->admin, 'invalidate_cache_for_stock_change' );
		$this->loader->add_action( 'woocommerce_product_set_stock_status', $this->admin, 'invalidate_cache_for_stock_status_change', 10, 3 );
		$this->loader->add_action( 'woocommerce_variation_set_stock_status', $this->admin, 'invalidate_cache_for_stock_status_change', 10, 3 );
		$this->loader->add_action( 'woocommerce_product_object_updated_props', $this->admin, 'invalidate_cache_for_product_props', 10, 2 );
		$this->loader->add_action( 'woocommerce_scheduled_sales', $this->admin, 'invalidate_cache_for_scheduled_sales' );
		$this->loader->add_filter( 'plugin_action_links_' . SKROUTZ_XML_FEED_FOR_WOOCOMMERCE_PLUGIN_BASENAME, $this->admin, 'add_plugin_action_links' );
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {
		$this->loader->add_action( 'admin_menu', $this->admin, 'register_admin_menu' );
		$this->loader->add_action( 'admin_init', $this->admin, 'register_settings' );
		$this->loader->add_action( 'admin_enqueue_scripts', $this->admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $this->admin, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_post_sxffw_generate_feed', $this->admin, 'handle_generate_feed' );
		$this->loader->add_action( 'woocommerce_product_options_general_product_data', $this->admin, 'render_product_fields' );
		$this->loader->add_action( 'woocommerce_admin_process_product_object', $this->admin, 'save_product_fields' );
		$this->loader->add_action( 'woocommerce_variation_options_pricing', $this->admin, 'render_variation_fields', 10, 3 );
		$this->loader->add_action( 'woocommerce_save_product_variation', $this->admin, 'save_variation_fields', 10, 2 );

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {
		$this->loader->add_action( 'parse_request', $this->public, 'prevent_feed_caching' );
		$this->loader->add_action( 'init', $this->public, 'register_rewrite_rules' );
		$this->loader->add_filter( 'query_vars', $this->public, 'register_query_var' );
		$this->loader->add_action( 'template_redirect', $this->public, 'maybe_render_feed' );

	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Skroutz_Xml_Feed_For_Woocommerce_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
