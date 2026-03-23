<?php

/**
 * Fired during plugin deactivation
 *
 * @link       https://profiles.wordpress.org/orionaselite/
 * @since      1.0.0
 *
 * @package    Skroutz_Xml_Feed_For_Woocommerce
 * @subpackage Skroutz_Xml_Feed_For_Woocommerce/includes
 */

/**
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    Skroutz_Xml_Feed_For_Woocommerce
 * @subpackage Skroutz_Xml_Feed_For_Woocommerce/includes
 * @author     George Nicolaou <orionas.elite@gmail.com>
 */
class Skroutz_Xml_Feed_For_Woocommerce_Deactivator {

	/**
	 * Flush rewrite rules on deactivation.
	 *
	 * @since 1.0.0
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}

}
