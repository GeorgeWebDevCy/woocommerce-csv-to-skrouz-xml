<?php

/**
 * Define the internationalization functionality
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @link       https://profiles.wordpress.org/orionaselite/
 * @since      1.0.0
 *
 * @package    Skroutz_Xml_Feed_For_Woocommerce
 * @subpackage Skroutz_Xml_Feed_For_Woocommerce/includes
 */

/**
 * Define the internationalization functionality.
 *
 * Loads and defines the internationalization files for this plugin
 * so that it is ready for translation.
 *
 * @since      1.0.0
 * @package    Skroutz_Xml_Feed_For_Woocommerce
 * @subpackage Skroutz_Xml_Feed_For_Woocommerce/includes
 * @author     George Nicolaou <orionas.elite@gmail.com>
 */
class Skroutz_Xml_Feed_For_Woocommerce_i18n {


	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'skroutz-xml-feed-for-woocommerce',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

	}



}
