<?php

/**
 * GitHub-powered plugin updater integration.
 *
 * @package Skroutz_Xml_Feed_For_Woocommerce
 */

class Skroutz_Xml_Feed_For_Woocommerce_Updater {

	/**
	 * Logger instance.
	 *
	 * @var Skroutz_Xml_Feed_For_Woocommerce_Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param Skroutz_Xml_Feed_For_Woocommerce_Logger $logger Logger.
	 */
	public function __construct( $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Register the update checker.
	 *
	 * @return void
	 */
	public function register() {
		$autoload_path = SKROUTZ_XML_FEED_FOR_WOOCOMMERCE_PLUGIN_DIR . 'vendor/autoload.php';

		if ( ! file_exists( $autoload_path ) ) {
			$this->logger->warning( 'Updater autoloader was not found.', array( 'path' => $autoload_path ) );
			return;
		}

		require_once $autoload_path;

		if ( ! class_exists( '\YahnisElsts\PluginUpdateChecker\v5p6\PucFactory' ) ) {
			$this->logger->warning( 'Plugin Update Checker is installed but the expected factory class was not found.' );
			return;
		}

		$update_checker = \YahnisElsts\PluginUpdateChecker\v5p6\PucFactory::buildUpdateChecker(
			'https://github.com/GeorgeWebDevCy/woocommerce-csv-to-skrouz-xml/',
			SKROUTZ_XML_FEED_FOR_WOOCOMMERCE_PLUGIN_FILE,
			'skroutz-xml-feed-for-woocommerce'
		);

		$update_checker->setBranch( 'main' );
		$update_checker->getVcsApi()->enableReleaseAssets( '/skroutz-xml-feed-for-woocommerce(?:-[0-9A-Za-z.\-]+)?\.zip($|[?&#])/i' );

		$this->logger->info( 'Plugin update checker registered.' );
	}
}
