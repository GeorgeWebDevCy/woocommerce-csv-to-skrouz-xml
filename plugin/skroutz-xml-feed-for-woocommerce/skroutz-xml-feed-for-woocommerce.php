<?php

/**
 * The plugin bootstrap file.
 *
 * @link              https://profiles.wordpress.org/orionaselite/
 * @since             1.0.0
 * @package           Skroutz_Xml_Feed_For_Woocommerce
 *
 * @wordpress-plugin
 * Plugin Name:       Skroutz XML Feed for WooCommerce
 * Plugin URI:        https://github.com/GeorgeWebDevCy/woocommerce-csv-to-skrouz-xml
 * Description:       Generate a Skroutz-compatible WooCommerce XML feed with validation, overrides, and a public feed endpoint.
 * Version:           1.0.5
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            George Nicolaou
 * Author URI:        https://profiles.wordpress.org/orionaselite/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://github.com/GeorgeWebDevCy/woocommerce-csv-to-skrouz-xml
 * Text Domain:       skroutz-xml-feed-for-woocommerce
 * Domain Path:       /languages
 * Requires Plugins:  woocommerce
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'SKROUTZ_XML_FEED_FOR_WOOCOMMERCE_VERSION', '1.0.5' );
define( 'SKROUTZ_XML_FEED_FOR_WOOCOMMERCE_PLUGIN_FILE', __FILE__ );
define( 'SKROUTZ_XML_FEED_FOR_WOOCOMMERCE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'SKROUTZ_XML_FEED_FOR_WOOCOMMERCE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SKROUTZ_XML_FEED_FOR_WOOCOMMERCE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SKROUTZ_XML_FEED_FOR_WOOCOMMERCE_FEED_FILENAME', 'skroutz-feed.xml' );

/**
 * Check whether the current request targets the public feed endpoint.
 *
 * @return bool
 */
function skroutz_xml_feed_for_woocommerce_is_feed_request_uri() {
	$request_uri  = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	$request_path = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
	$feed_path    = (string) wp_parse_url( home_url( '/' . SKROUTZ_XML_FEED_FOR_WOOCOMMERCE_FEED_FILENAME ), PHP_URL_PATH );

	return '' !== $request_path && untrailingslashit( $request_path ) === untrailingslashit( $feed_path );
}

if ( skroutz_xml_feed_for_woocommerce_is_feed_request_uri() ) {
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

function activate_skroutz_xml_feed_for_woocommerce() {
	require_once SKROUTZ_XML_FEED_FOR_WOOCOMMERCE_PLUGIN_DIR . 'includes/class-skroutz-xml-feed-for-woocommerce-activator.php';
	Skroutz_Xml_Feed_For_Woocommerce_Activator::activate();
}

function deactivate_skroutz_xml_feed_for_woocommerce() {
	require_once SKROUTZ_XML_FEED_FOR_WOOCOMMERCE_PLUGIN_DIR . 'includes/class-skroutz-xml-feed-for-woocommerce-deactivator.php';
	Skroutz_Xml_Feed_For_Woocommerce_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_skroutz_xml_feed_for_woocommerce' );
register_deactivation_hook( __FILE__, 'deactivate_skroutz_xml_feed_for_woocommerce' );

require SKROUTZ_XML_FEED_FOR_WOOCOMMERCE_PLUGIN_DIR . 'includes/class-skroutz-xml-feed-for-woocommerce.php';

function run_skroutz_xml_feed_for_woocommerce() {
	$plugin = new Skroutz_Xml_Feed_For_Woocommerce();
	$plugin->run();
}

run_skroutz_xml_feed_for_woocommerce();
