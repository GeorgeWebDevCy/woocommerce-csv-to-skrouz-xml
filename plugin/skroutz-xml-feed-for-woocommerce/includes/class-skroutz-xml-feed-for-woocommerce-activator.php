<?php

/**
 * Fired during plugin activation
 *
 * @link       https://profiles.wordpress.org/orionaselite/
 * @since      1.0.0
 *
 * @package    Skroutz_Xml_Feed_For_Woocommerce
 * @subpackage Skroutz_Xml_Feed_For_Woocommerce/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Skroutz_Xml_Feed_For_Woocommerce
 * @subpackage Skroutz_Xml_Feed_For_Woocommerce/includes
 * @author     George Nicolaou <orionas.elite@gmail.com>
 */
class Skroutz_Xml_Feed_For_Woocommerce_Activator {

	/**
	 * Seed default settings and register the feed endpoint.
	 *
	 * @since 1.0.0
	 */
	public static function activate() {
		require_once SKROUTZ_XML_FEED_FOR_WOOCOMMERCE_PLUGIN_DIR . 'includes/class-skroutz-xml-feed-for-woocommerce-settings.php';

		if ( ! get_option( Skroutz_Xml_Feed_For_Woocommerce_Settings::OPTION_KEY ) ) {
			add_option( Skroutz_Xml_Feed_For_Woocommerce_Settings::OPTION_KEY, Skroutz_Xml_Feed_For_Woocommerce_Settings::defaults() );
		}

		add_rewrite_rule(
			'^' . preg_quote( Skroutz_Xml_Feed_For_Woocommerce_Settings::FEED_FILENAME, '/' ) . '/?$',
			'index.php?' . Skroutz_Xml_Feed_For_Woocommerce_Settings::FEED_QUERY_VAR . '=1',
			'top'
		);

		flush_rewrite_rules();
	}

}
