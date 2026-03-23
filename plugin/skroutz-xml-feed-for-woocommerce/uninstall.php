<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * When populating this file, consider the following flow
 * of control:
 *
 * - This method should be static
 * - Check if the $_REQUEST content actually is the plugin name
 * - Run an admin referrer check to make sure it goes through authentication
 * - Verify the output of $_GET makes sense
 * - Repeat with other user roles. Best directly by using the links/query string parameters.
 * - Repeat things for multisite. Once for a single site in the network, once sitewide.
 *
 * This file may be updated more in future version of the Boilerplate; however, this is the
 * general skeleton and outline for how the file should work.
 *
 * For more information, see the following discussion:
 * https://github.com/tommcfarlin/WordPress-Plugin-Boilerplate/pull/123#issuecomment-28541913
 *
 * @link       https://profiles.wordpress.org/orionaselite/
 * @since      1.0.0
 *
 * @package    Skroutz_Xml_Feed_For_Woocommerce
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'sxffw_settings' );
delete_option( 'sxffw_last_report' );

global $wpdb;

if ( isset( $wpdb->postmeta ) ) {
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
			$wpdb->esc_like( '_sxffw_' ) . '%'
		)
	);
}

$uploads = wp_upload_dir();
$base    = empty( $uploads['error'] ) && ! empty( $uploads['basedir'] ) ? $uploads['basedir'] : trailingslashit( WP_CONTENT_DIR ) . 'uploads';
$target  = trailingslashit( $base ) . 'skroutz-xml-feed';

if ( ! function_exists( 'sxffw_delete_directory' ) ) {
	/**
	 * Delete a directory tree created by the plugin.
	 *
	 * @param string $path Absolute path.
	 * @return void
	 */
	function sxffw_delete_directory( $path ) {
		if ( ! is_dir( $path ) ) {
			return;
		}

		$entries = scandir( $path );

		foreach ( is_array( $entries ) ? $entries : array() as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}

			$child_path = trailingslashit( $path ) . $entry;

			if ( is_dir( $child_path ) ) {
				sxffw_delete_directory( $child_path );
				continue;
			}

			wp_delete_file( $child_path );
		}

		rmdir( $path );
	}
}

sxffw_delete_directory( $target );
