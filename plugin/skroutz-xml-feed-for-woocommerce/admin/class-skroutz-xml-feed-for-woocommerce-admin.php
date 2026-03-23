<?php

class Skroutz_Xml_Feed_For_Woocommerce_Admin {

	const PAGE_SLUG = 'sxffw';
	const DEFAULT_MANUFACTURER_MARKER_META_KEY = '_sxffw_default_manufacturer_applied';
	const DETECTED_MANUFACTURER_MARKER_META_KEY = '_sxffw_detected_manufacturer_applied';
	const BACKFILL_NOTICE_TRANSIENT_KEY        = 'sxffw_backfill_notice';

	private $plugin_name;
	private $version;
	private $generator;
	private $logger;

	public function __construct( $plugin_name, $version, $generator, $logger ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->generator   = $generator;
		$this->logger      = $logger;
	}

	public function render_missing_woocommerce_notice() {
		if ( class_exists( 'WooCommerce' ) || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		?>
		<div class="notice notice-warning">
			<p><?php esc_html_e( 'Skroutz XML Feed for WooCommerce requires WooCommerce to be installed and active.', 'skroutz-xml-feed-for-woocommerce' ); ?></p>
		</div>
		<?php
	}

	public function add_plugin_action_links( $links ) {
		array_unshift(
			$links,
			sprintf( '<a href="%s">%s</a>', esc_url( $this->get_page_url() ), esc_html__( 'Settings', 'skroutz-xml-feed-for-woocommerce' ) )
		);

		return $links;
	}

	public function register_admin_menu() {
		if ( class_exists( 'WooCommerce' ) ) {
			add_submenu_page( 'woocommerce', __( 'Skroutz XML Feed', 'skroutz-xml-feed-for-woocommerce' ), __( 'Skroutz XML Feed', 'skroutz-xml-feed-for-woocommerce' ), 'manage_woocommerce', self::PAGE_SLUG, array( $this, 'render_settings_page' ) );
			return;
		}

		add_management_page( __( 'Skroutz XML Feed', 'skroutz-xml-feed-for-woocommerce' ), __( 'Skroutz XML Feed', 'skroutz-xml-feed-for-woocommerce' ), 'manage_options', self::PAGE_SLUG, array( $this, 'render_settings_page' ) );
	}

	public function register_settings() {
		register_setting(
			'sxffw_settings',
			Skroutz_Xml_Feed_For_Woocommerce_Settings::OPTION_KEY,
			array(
				'sanitize_callback' => array( 'Skroutz_Xml_Feed_For_Woocommerce_Settings', 'sanitize' ),
				'default'           => Skroutz_Xml_Feed_For_Woocommerce_Settings::defaults(),
			)
		);
	}

	public function enqueue_styles( $hook_suffix = '' ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $this->should_enqueue_assets( $screen ) ) {
			return;
		}

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/skroutz-xml-feed-for-woocommerce-admin.css', array(), $this->version );
	}

	public function enqueue_scripts( $hook_suffix = '' ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $this->should_enqueue_assets( $screen ) ) {
			return;
		}

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/skroutz-xml-feed-for-woocommerce-admin.js', array( 'jquery' ), $this->version, true );
		wp_localize_script(
			$this->plugin_name,
			'SXFFWAdmin',
			array(
				'copySuccess' => __( 'Feed URL copied.', 'skroutz-xml-feed-for-woocommerce' ),
				'copyFallback' => __( 'Copy the feed URL manually.', 'skroutz-xml-feed-for-woocommerce' ),
			)
		);
	}

	public function handle_generate_feed() {
		if ( ! current_user_can( $this->get_required_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to generate the feed.', 'skroutz-xml-feed-for-woocommerce' ) );
		}

		check_admin_referer( 'sxffw_generate_feed' );

		if ( ! class_exists( 'WooCommerce' ) ) {
			wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'sxffw_notice' => 'error', 'sxffw_message' => __( 'WooCommerce must be active before the feed can be generated.', 'skroutz-xml-feed-for-woocommerce' ) ), $this->get_base_admin_url() ) );
			exit;
		}

		try {
			$this->generator->generate_feed();
			wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'sxffw_notice' => 'generated' ), $this->get_base_admin_url() ) );
		} catch ( Throwable $throwable ) {
			$this->logger->error( 'Manual feed generation failed.', array( 'message' => $throwable->getMessage() ) );
			wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'sxffw_notice' => 'error', 'sxffw_message' => $throwable->getMessage() ), $this->get_base_admin_url() ) );
		}

		exit;
	}

	public function handle_clear_log() {
		if ( ! current_user_can( $this->get_required_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to clear the log.', 'skroutz-xml-feed-for-woocommerce' ) );
		}

		check_admin_referer( 'sxffw_clear_log' );

		$log_cleared = $this->logger->clear();
		Skroutz_Xml_Feed_For_Woocommerce_Settings::update_report( array() );
		$notice = $log_cleared ? 'diagnostics_cleared' : 'report_cleared_log_failed';

		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'sxffw_notice' => $notice ), $this->get_base_admin_url() ) );
		exit;
	}

	public function render_settings_page() {
		if ( ! current_user_can( $this->get_required_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'skroutz-xml-feed-for-woocommerce' ) );
		}

		$settings            = Skroutz_Xml_Feed_For_Woocommerce_Settings::get_all();
		$report              = Skroutz_Xml_Feed_For_Woocommerce_Settings::get_report();
		$notice              = $this->get_notice();
		$backfill_notice     = get_transient( self::BACKFILL_NOTICE_TRANSIENT_KEY );
		$report_state        = empty( $report ) ? 'missing' : ( $this->generator->is_cache_fresh( $report ) ? 'fresh' : 'stale' );
		$settings_action_url = admin_url( 'options.php' );
		$generate_action_url = wp_nonce_url( admin_url( 'admin-post.php?action=sxffw_generate_feed' ), 'sxffw_generate_feed' );
		$clear_log_action_url = wp_nonce_url( admin_url( 'admin-post.php?action=sxffw_clear_log' ), 'sxffw_clear_log' );
		$feed_url            = $this->generator->get_endpoint_url();
		$xml_url             = ! empty( $report['xml_url'] ) ? $report['xml_url'] : $this->generator->get_xml_url();
		$log_path            = $this->logger->get_log_path();

		if ( $backfill_notice ) {
			delete_transient( self::BACKFILL_NOTICE_TRANSIENT_KEY );
		}

		include plugin_dir_path( __FILE__ ) . 'partials/skroutz-xml-feed-for-woocommerce-admin-display.php';
	}

	public function invalidate_cache_for_post( $post_id ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$post_type = get_post_type( $post_id );

		if ( ! in_array( $post_type, array( 'product', 'product_variation' ), true ) ) {
			return;
		}

		if ( 'product' === $post_type ) {
			$this->sync_single_product_manufacturer_meta( $post_id );
		} elseif ( 'product_variation' === $post_type ) {
			$parent_id = (int) wp_get_post_parent_id( $post_id );
			if ( $parent_id > 0 ) {
				$this->sync_single_product_manufacturer_meta( $parent_id );
			}
		}

		$this->generator->invalidate_cache( 'Product content changed.' );
	}

	public function invalidate_cache_after_settings_update( $old_value = null, $new_value = null ) {
		$old_value = is_array( $old_value ) ? $old_value : array();
		$new_value = is_array( $new_value ) ? $new_value : array();
		$old_default_manufacturer = isset( $old_value['default_manufacturer'] ) ? sanitize_text_field( (string) $old_value['default_manufacturer'] ) : '';
		$new_default_manufacturer = isset( $new_value['default_manufacturer'] ) ? sanitize_text_field( (string) $new_value['default_manufacturer'] ) : '';

		$this->sync_manufacturer_meta_to_database( $old_default_manufacturer, $new_default_manufacturer );

		$this->generator->invalidate_cache( 'Plugin settings changed.' );

		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		try {
			$this->generator->generate_feed();
		} catch ( Throwable $throwable ) {
			$this->logger->error( 'Feed regeneration after settings save failed.', array( 'message' => $throwable->getMessage() ) );
		}
	}

	public function invalidate_cache_for_stock_change( $product ) {
		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$this->generator->invalidate_cache(
			sprintf(
				/* translators: %d: product ID */
				__( 'WooCommerce stock quantity changed for product #%d.', 'skroutz-xml-feed-for-woocommerce' ),
				$product->get_id()
			)
		);
	}

	public function invalidate_cache_for_stock_status_change( $product_id, $stock_status, $product ) {
		if ( ! $product instanceof WC_Product ) {
			$product = wc_get_product( $product_id );
		}

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$this->generator->invalidate_cache(
			sprintf(
				/* translators: 1: product ID, 2: stock status */
				__( 'WooCommerce stock status changed for product #%1$d to %2$s.', 'skroutz-xml-feed-for-woocommerce' ),
				$product->get_id(),
				$stock_status
			)
		);
	}

	public function invalidate_cache_for_product_props( $product, $updated_props ) {
		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$watched_props = array(
			'name',
			'slug',
			'status',
			'short_description',
			'description',
			'sku',
			'global_unique_id',
			'regular_price',
			'sale_price',
			'date_on_sale_from',
			'date_on_sale_to',
			'stock_quantity',
			'stock_status',
			'manage_stock',
			'tax_status',
			'tax_class',
			'catalog_visibility',
			'weight',
			'image_id',
			'gallery_image_ids',
			'product_type',
		);
		$updated_props = is_array( $updated_props ) ? $updated_props : array();

		if ( empty( array_intersect( $watched_props, $updated_props ) ) ) {
			return;
		}

		$this->generator->invalidate_cache(
			sprintf(
				/* translators: %d: product ID */
				__( 'WooCommerce product properties changed for product #%d.', 'skroutz-xml-feed-for-woocommerce' ),
				$product->get_id()
			)
		);
	}

	public function invalidate_cache_for_scheduled_sales() {
		$this->generator->invalidate_cache( __( 'WooCommerce scheduled sales updated product pricing.', 'skroutz-xml-feed-for-woocommerce' ) );
	}

	public function render_product_fields() {
		if ( ! function_exists( 'woocommerce_wp_text_input' ) ) {
			return;
		}

		echo '<div class="options_group">';
		echo '<p class="form-field"><strong>' . esc_html__( 'Skroutz Feed Overrides', 'skroutz-xml-feed-for-woocommerce' ) . '</strong></p>';

		foreach ( $this->product_fields() as $field => $config ) {
			$meta_key = Skroutz_Xml_Feed_For_Woocommerce_Settings::meta_key( $field );
			$value    = get_post_meta( get_the_ID(), $meta_key, true );
			$this->render_wc_field( $meta_key, $config, $value );
		}

		echo '</div>';
	}

	public function save_product_fields( $product ) {
		foreach ( $this->product_fields() as $field => $config ) {
			$this->save_meta_value( $product->get_id(), $field, $config, isset( $_POST[ Skroutz_Xml_Feed_For_Woocommerce_Settings::meta_key( $field ) ] ) ? wp_unslash( $_POST[ Skroutz_Xml_Feed_For_Woocommerce_Settings::meta_key( $field ) ] ) : '' );
		}
	}

	public function render_variation_fields( $loop, $variation_data, $variation ) {
		if ( ! function_exists( 'woocommerce_wp_text_input' ) ) {
			return;
		}

		echo '<div class="form-row form-row-full sxffw-variation-fields">';
		echo '<p><strong>' . esc_html__( 'Skroutz Feed Overrides', 'skroutz-xml-feed-for-woocommerce' ) . '</strong></p>';

		foreach ( $this->variation_fields() as $field => $config ) {
			$meta_key = Skroutz_Xml_Feed_For_Woocommerce_Settings::meta_key( $field ) . '[' . $loop . ']';
			$value    = get_post_meta( $variation->ID, Skroutz_Xml_Feed_For_Woocommerce_Settings::meta_key( $field ), true );
			$this->render_wc_field( $meta_key, $config, $value );
		}

		echo '</div>';
	}

	public function save_variation_fields( $variation_id, $loop ) {
		foreach ( $this->variation_fields() as $field => $config ) {
			$meta_key = Skroutz_Xml_Feed_For_Woocommerce_Settings::meta_key( $field );
			$value    = isset( $_POST[ $meta_key ][ $loop ] ) ? wp_unslash( $_POST[ $meta_key ][ $loop ] ) : '';
			$this->save_meta_value( $variation_id, $field, $config, $value );
		}
	}

	private function product_fields() {
		return array(
			'exclude_from_feed'  => array( 'type' => 'checkbox', 'label' => __( 'Exclude from Skroutz feed', 'skroutz-xml-feed-for-woocommerce' ), 'description' => __( 'Skip this product from the exported feed.', 'skroutz-xml-feed-for-woocommerce' ) ),
			'custom_name'        => array( 'type' => 'text', 'label' => __( 'Custom feed name', 'skroutz-xml-feed-for-woocommerce' ) ),
			'custom_link'        => array( 'type' => 'url', 'label' => __( 'Custom product URL', 'skroutz-xml-feed-for-woocommerce' ) ),
			'custom_image'       => array( 'type' => 'url', 'label' => __( 'Custom main image URL', 'skroutz-xml-feed-for-woocommerce' ) ),
			'additional_images'  => array( 'type' => 'text', 'label' => __( 'Additional image URLs', 'skroutz-xml-feed-for-woocommerce' ), 'description' => __( 'Comma-separated HTTPS URLs.', 'skroutz-xml-feed-for-woocommerce' ) ),
			'category'           => array( 'type' => 'text', 'label' => __( 'Custom category path', 'skroutz-xml-feed-for-woocommerce' ) ),
			'manufacturer'       => array( 'type' => 'text', 'label' => __( 'Manufacturer', 'skroutz-xml-feed-for-woocommerce' ) ),
			'mpn'                => array( 'type' => 'text', 'label' => __( 'MPN', 'skroutz-xml-feed-for-woocommerce' ) ),
			'ean'                => array( 'type' => 'text', 'label' => __( 'EAN', 'skroutz-xml-feed-for-woocommerce' ) ),
			'availability'       => array( 'type' => 'text', 'label' => __( 'Availability label', 'skroutz-xml-feed-for-woocommerce' ) ),
			'weight'             => array( 'type' => 'text', 'label' => __( 'Weight in grams', 'skroutz-xml-feed-for-woocommerce' ) ),
			'color'              => array( 'type' => 'text', 'label' => __( 'Color', 'skroutz-xml-feed-for-woocommerce' ) ),
			'size'               => array( 'type' => 'text', 'label' => __( 'Size', 'skroutz-xml-feed-for-woocommerce' ) ),
			'custom_description' => array( 'type' => 'textarea', 'label' => __( 'Custom feed description', 'skroutz-xml-feed-for-woocommerce' ) ),
		);
	}

	private function variation_fields() {
		return array(
			'exclude_from_feed' => array( 'type' => 'checkbox', 'label' => __( 'Exclude from Skroutz feed', 'skroutz-xml-feed-for-woocommerce' ) ),
			'custom_name'       => array( 'type' => 'text', 'label' => __( 'Custom feed name', 'skroutz-xml-feed-for-woocommerce' ) ),
			'manufacturer'      => array( 'type' => 'text', 'label' => __( 'Manufacturer', 'skroutz-xml-feed-for-woocommerce' ) ),
			'mpn'               => array( 'type' => 'text', 'label' => __( 'MPN', 'skroutz-xml-feed-for-woocommerce' ) ),
			'ean'               => array( 'type' => 'text', 'label' => __( 'EAN', 'skroutz-xml-feed-for-woocommerce' ) ),
			'availability'      => array( 'type' => 'text', 'label' => __( 'Availability label', 'skroutz-xml-feed-for-woocommerce' ) ),
			'color'             => array( 'type' => 'text', 'label' => __( 'Color', 'skroutz-xml-feed-for-woocommerce' ) ),
			'size'              => array( 'type' => 'text', 'label' => __( 'Size', 'skroutz-xml-feed-for-woocommerce' ) ),
		);
	}

	private function render_wc_field( $id, $config, $value ) {
		$args = array(
			'id'          => $id,
			'label'       => $config['label'],
			'description' => isset( $config['description'] ) ? $config['description'] : '',
			'value'       => $value,
			'desc_tip'    => ! empty( $config['description'] ),
		);

		if ( 'checkbox' === $config['type'] ) {
			$args['cbvalue'] = 'yes';
			woocommerce_wp_checkbox( $args );
			return;
		}

		if ( 'textarea' === $config['type'] ) {
			woocommerce_wp_textarea_input( $args );
			return;
		}

		$args['type'] = $config['type'];
		woocommerce_wp_text_input( $args );
	}

	private function save_meta_value( $post_id, $field, $config, $raw_value ) {
		$meta_key = Skroutz_Xml_Feed_For_Woocommerce_Settings::meta_key( $field );

		if ( 'checkbox' === $config['type'] ) {
			if ( 'yes' === $raw_value ) {
				update_post_meta( $post_id, $meta_key, 'yes' );
			} else {
				delete_post_meta( $post_id, $meta_key );
			}
			return;
		}

		$value = $this->sanitize_meta_value( $field, $raw_value );

		if ( '' === $value ) {
			delete_post_meta( $post_id, $meta_key );
		} else {
			update_post_meta( $post_id, $meta_key, $value );
		}

		if ( 'manufacturer' === $field ) {
			delete_post_meta( $post_id, self::DEFAULT_MANUFACTURER_MARKER_META_KEY );
			delete_post_meta( $post_id, self::DETECTED_MANUFACTURER_MARKER_META_KEY );
		}
	}

	private function sanitize_meta_value( $field, $raw_value ) {
		$raw_value = is_string( $raw_value ) ? $raw_value : '';

		switch ( $field ) {
			case 'custom_link':
			case 'custom_image':
				return esc_url_raw( trim( $raw_value ) );
			case 'additional_images':
				$parts = preg_split( '/[\r\n,]+/', $raw_value );
				$parts = is_array( $parts ) ? $parts : array();
				return implode( ', ', array_filter( array_map( 'esc_url_raw', array_map( 'trim', $parts ) ) ) );
			case 'custom_description':
				return sanitize_textarea_field( $raw_value );
			default:
				return sanitize_text_field( $raw_value );
		}
	}

	private function should_enqueue_assets( $screen ) {
		if ( ! $screen ) {
			return false;
		}

		if ( isset( $_GET['page'] ) && self::PAGE_SLUG === sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			return true;
		}

		return 'product' === $screen->post_type || 'product_variation' === $screen->post_type;
	}

	private function get_notice() {
		if ( empty( $_GET['sxffw_notice'] ) ) {
			return null;
		}

		$notice = sanitize_key( wp_unslash( $_GET['sxffw_notice'] ) );

		if ( 'generated' === $notice ) {
			return array( 'type' => 'success', 'message' => __( 'The Skroutz XML feed was regenerated successfully.', 'skroutz-xml-feed-for-woocommerce' ) );
		}
		if ( 'diagnostics_cleared' === $notice ) {
			return array( 'type' => 'success', 'message' => __( 'The plugin log and last validation report were cleared successfully.', 'skroutz-xml-feed-for-woocommerce' ) );
		}
		if ( 'report_cleared_log_failed' === $notice ) {
			return array( 'type' => 'error', 'message' => __( 'The validation report was cleared, but the plugin log could not be cleared.', 'skroutz-xml-feed-for-woocommerce' ) );
		}

		$message = ! empty( $_GET['sxffw_message'] ) ? sanitize_text_field( wp_unslash( $_GET['sxffw_message'] ) ) : __( 'The requested action could not be completed.', 'skroutz-xml-feed-for-woocommerce' );
		return array( 'type' => 'error', 'message' => $message );
	}

	private function get_required_capability() {
		return class_exists( 'WooCommerce' ) ? 'manage_woocommerce' : 'manage_options';
	}

	private function get_base_admin_url() {
		return class_exists( 'WooCommerce' ) ? admin_url( 'admin.php' ) : admin_url( 'tools.php' );
	}

	private function get_page_url() {
		return add_query_arg( array( 'page' => self::PAGE_SLUG ), $this->get_base_admin_url() );
	}

	private function sync_manufacturer_meta_to_database( $old_manufacturer, $new_manufacturer ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return;
		}

		$product_ids = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => array( 'publish', 'private', 'draft', 'pending', 'future' ),
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);

		$summary = array(
			'detected_added'   => 0,
			'detected_updated' => 0,
			'default_added'    => 0,
			'default_updated'  => 0,
			'cleared'          => 0,
		);

		foreach ( $product_ids as $product_id ) {
			$outcome = $this->sync_single_product_manufacturer_meta( $product_id, $new_manufacturer );

			if ( isset( $summary[ $outcome ] ) ) {
				++$summary[ $outcome ];
			}
		}

		$message = sprintf(
			/* translators: 1: detected added count, 2: detected updated count, 3: default added count, 4: default updated count, 5: cleared count */
			__( 'Manufacturer sync complete. WooCommerce data added: %1$d, updated: %2$d. Default manufacturer added: %3$d, updated: %4$d. Cleared: %5$d.', 'skroutz-xml-feed-for-woocommerce' ),
			$summary['detected_added'],
			$summary['detected_updated'],
			$summary['default_added'],
			$summary['default_updated'],
			$summary['cleared']
		);

		set_transient(
			self::BACKFILL_NOTICE_TRANSIENT_KEY,
			array(
				'type'    => 'success',
				'message' => $message,
			),
			MINUTE_IN_SECONDS * 10
		);

		$this->logger->info(
			'Manufacturer sources synced to product meta.',
			array(
				'old_default_manufacturer' => $old_manufacturer,
				'new_default_manufacturer' => $new_manufacturer,
				'summary'                  => $summary,
			)
		);
	}

	private function sync_single_product_manufacturer_meta( $product_id, $default_manufacturer = null ) {
		$product = wc_get_product( $product_id );
		if ( ! $product instanceof WC_Product ) {
			return '';
		}

		$default_manufacturer = null === $default_manufacturer
			? sanitize_text_field( (string) Skroutz_Xml_Feed_For_Woocommerce_Settings::get( 'default_manufacturer' ) )
			: sanitize_text_field( (string) $default_manufacturer );
		$meta_key             = Skroutz_Xml_Feed_For_Woocommerce_Settings::meta_key( 'manufacturer' );
		$current_override     = trim( (string) get_post_meta( $product_id, $meta_key, true ) );
		$is_default_auto      = 'yes' === get_post_meta( $product_id, self::DEFAULT_MANUFACTURER_MARKER_META_KEY, true );
		$is_detected_auto     = 'yes' === get_post_meta( $product_id, self::DETECTED_MANUFACTURER_MARKER_META_KEY, true );
		$detected_value       = trim( (string) $this->generator->detect_manufacturer_value( $product ) );

		if ( '' !== $detected_value ) {
			if ( $is_detected_auto ) {
				delete_post_meta( $product_id, self::DEFAULT_MANUFACTURER_MARKER_META_KEY );

				if ( $current_override !== $detected_value ) {
					update_post_meta( $product_id, $meta_key, $detected_value );
					return 'detected_updated';
				}

				return '';
			}

			if ( $is_default_auto || '' === $current_override ) {
				update_post_meta( $product_id, $meta_key, $detected_value );
				update_post_meta( $product_id, self::DETECTED_MANUFACTURER_MARKER_META_KEY, 'yes' );
				delete_post_meta( $product_id, self::DEFAULT_MANUFACTURER_MARKER_META_KEY );
				return $is_default_auto ? 'detected_updated' : 'detected_added';
			}

			return '';
		}

		if ( $is_detected_auto ) {
			delete_post_meta( $product_id, $meta_key );
			delete_post_meta( $product_id, self::DETECTED_MANUFACTURER_MARKER_META_KEY );
			delete_post_meta( $product_id, self::DEFAULT_MANUFACTURER_MARKER_META_KEY );
			$current_override = '';
		}

		if ( $is_default_auto ) {
			if ( '' === $default_manufacturer ) {
				delete_post_meta( $product_id, $meta_key );
				delete_post_meta( $product_id, self::DEFAULT_MANUFACTURER_MARKER_META_KEY );
				return 'cleared';
			}

			if ( $current_override !== $default_manufacturer ) {
				update_post_meta( $product_id, $meta_key, $default_manufacturer );
				delete_post_meta( $product_id, self::DETECTED_MANUFACTURER_MARKER_META_KEY );
				return 'default_updated';
			}

			delete_post_meta( $product_id, self::DETECTED_MANUFACTURER_MARKER_META_KEY );
			return '';
		}

		if ( '' !== $current_override || '' === $default_manufacturer ) {
			return '';
		}

		update_post_meta( $product_id, $meta_key, $default_manufacturer );
		update_post_meta( $product_id, self::DEFAULT_MANUFACTURER_MARKER_META_KEY, 'yes' );
		delete_post_meta( $product_id, self::DETECTED_MANUFACTURER_MARKER_META_KEY );
		return 'default_added';
	}
}
