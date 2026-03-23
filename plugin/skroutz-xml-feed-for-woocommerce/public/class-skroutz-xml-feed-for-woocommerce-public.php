<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://profiles.wordpress.org/orionaselite/
 * @since      1.0.0
 *
 * @package    Skroutz_Xml_Feed_For_Woocommerce
 * @subpackage Skroutz_Xml_Feed_For_Woocommerce/public
 */

/**
 * Handle the public feed endpoint.
 *
 * @package    Skroutz_Xml_Feed_For_Woocommerce
 * @subpackage Skroutz_Xml_Feed_For_Woocommerce/public
 * @author     George Nicolaou <orionas.elite@gmail.com>
 */
class Skroutz_Xml_Feed_For_Woocommerce_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $skroutzXmlFeedForWoocommerce    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	private $generator;

	private $logger;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param string $plugin_name The plugin name.
	 * @param string $version     The current plugin version.
	 */
	public function __construct( $plugin_name, $version, $generator, $logger ) {
		$this->plugin_name = $plugin_name;
		$this->version     = $version;
		$this->generator   = $generator;
		$this->logger      = $logger;
	}

	public function prevent_feed_caching( $wp ) {
		$request = isset( $wp->request ) ? $wp->request : '';

		if ( ! $this->is_feed_request( $request ) ) {
			return;
		}

		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( ! defined( 'DONOTCACHEOBJECT' ) ) {
			define( 'DONOTCACHEOBJECT', true );
		}
		if ( ! defined( 'DONOTCACHEDB' ) ) {
			define( 'DONOTCACHEDB', true );
		}
		if ( ! defined( 'DONOTMINIFY' ) ) {
			define( 'DONOTMINIFY', true );
		}
	}

	public function register_rewrite_rules() {
		add_rewrite_rule(
			'^' . preg_quote( Skroutz_Xml_Feed_For_Woocommerce_Settings::FEED_FILENAME, '/' ) . '/?$',
			'index.php?' . Skroutz_Xml_Feed_For_Woocommerce_Settings::FEED_QUERY_VAR . '=1',
			'top'
		);
	}

	public function register_query_var( $vars ) {
		$vars[] = Skroutz_Xml_Feed_For_Woocommerce_Settings::FEED_QUERY_VAR;

		return $vars;
	}

	public function maybe_render_feed() {
		$query_value = get_query_var( Skroutz_Xml_Feed_For_Woocommerce_Settings::FEED_QUERY_VAR );

		if ( '1' !== (string) $query_value ) {
			return;
		}

		if ( ! class_exists( 'WooCommerce' ) ) {
			status_header( 503 );
			header( 'Content-Type: text/plain; charset=' . get_bloginfo( 'charset' ) );
			echo esc_html__( 'WooCommerce must be active before the Skroutz feed can be generated.', 'skroutz-xml-feed-for-woocommerce' );
			exit;
		}

		try {
			$existing_report  = Skroutz_Xml_Feed_For_Woocommerce_Settings::get_report();
			$cache_was_fresh  = $this->generator->is_cache_fresh( $existing_report );
			$cache_state      = $cache_was_fresh ? 'hit' : 'regenerated';
			$report = $this->generator->maybe_generate();

			if ( empty( $report['xml_path'] ) || ! file_exists( $report['xml_path'] ) ) {
				throw new RuntimeException( 'The XML cache file was not created.' );
			}

			while ( ob_get_level() ) {
				ob_end_clean();
			}

			$this->send_feed_headers( $report, $cache_state );
			$this->logger->info(
				'Serving public feed response.',
				array(
					'cache_state'      => $cache_state,
					'generated_at_gmt' => isset( $report['generated_at_gmt'] ) ? $report['generated_at_gmt'] : '',
					'xml_path'         => $report['xml_path'],
				)
			);
			readfile( $report['xml_path'] );
		} catch ( Throwable $throwable ) {
			$this->logger->error( 'Failed to render public feed.', array( 'message' => $throwable->getMessage() ) );
			status_header( 500 );
			header( 'Content-Type: text/plain; charset=' . get_bloginfo( 'charset' ) );
			echo esc_html__( 'The Skroutz feed could not be generated. Check the plugin log for details.', 'skroutz-xml-feed-for-woocommerce' );
		}

		exit;
	}

	private function is_feed_request( $request = '' ) {
		if ( is_string( $request ) && Skroutz_Xml_Feed_For_Woocommerce_Settings::FEED_FILENAME === trim( $request, '/' ) ) {
			return true;
		}

		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$request_path = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
		$feed_path    = (string) wp_parse_url( home_url( '/' . Skroutz_Xml_Feed_For_Woocommerce_Settings::FEED_FILENAME ), PHP_URL_PATH );

		return '' !== $request_path && untrailingslashit( $request_path ) === untrailingslashit( $feed_path );
	}

	private function send_feed_headers( $report, $cache_state ) {
		status_header( 200 );
		nocache_headers();

		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'Content-Disposition: inline; filename="' . Skroutz_Xml_Feed_For_Woocommerce_Settings::FEED_FILENAME . '"' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0, s-maxage=0', true );
		header( 'CDN-Cache-Control: no-store, no-cache, max-age=0', true );
		header( 'Surrogate-Control: no-store', true );
		header( 'Pragma: no-cache', true );
		header( 'X-Accel-Expires: 0', true );
		header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
		header( 'X-SXFFW-Cache-State: ' . sanitize_key( $cache_state ), true );

		if ( ! empty( $report['generated_at_gmt'] ) ) {
			header( 'X-SXFFW-Generated-At: ' . sanitize_text_field( $report['generated_at_gmt'] ), true );
		}
	}

}
