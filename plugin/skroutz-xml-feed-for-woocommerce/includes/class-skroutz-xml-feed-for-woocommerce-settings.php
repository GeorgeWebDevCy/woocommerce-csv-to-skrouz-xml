<?php

/**
 * Settings helpers for the plugin.
 *
 * @package Skroutz_Xml_Feed_For_Woocommerce
 */

class Skroutz_Xml_Feed_For_Woocommerce_Settings {

	const OPTION_KEY         = 'sxffw_settings';
	const REPORT_OPTION_KEY  = 'sxffw_last_report';
	const META_PREFIX        = '_sxffw_';
	const FEED_QUERY_VAR     = 'sxffw_feed';
	const FEED_FILENAME      = 'skroutz-feed.xml';

	/**
	 * Get default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'root_element'              => 'mywebstore',
			'default_manufacturer'      => '',
			'default_vat_rate'          => '24.00',
			'in_stock_availability'     => 'In stock',
			'out_of_stock_availability' => 'Available up to 12 days',
			'include_hidden_products'   => 0,
			'cache_ttl_minutes'         => 60,
			'enable_logging'            => 1,
		);
	}

	/**
	 * Get supported Skroutz availability labels.
	 *
	 * @return array<int, string>
	 */
	public static function availability_options() {
		return array(
			'In stock',
			'Available from 1 to 3 days',
			'Available from 4 to 6 days',
			'Available up to 12 days',
		);
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_all() {
		$stored = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return wp_parse_args( $stored, self::defaults() );
	}

	/**
	 * Get a single setting value.
	 *
	 * @param string|null $key Setting key.
	 * @return mixed
	 */
	public static function get( $key = null ) {
		$settings = self::get_all();

		if ( null === $key ) {
			return $settings;
		}

		return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
	}

	/**
	 * Sanitize settings payload.
	 *
	 * @param array<string, mixed> $input Raw settings.
	 * @return array<string, mixed>
	 */
	public static function sanitize( $input ) {
		$defaults = self::defaults();
		$input    = is_array( $input ) ? $input : array();

		$root_element = isset( $input['root_element'] ) ? sanitize_text_field( wp_unslash( $input['root_element'] ) ) : $defaults['root_element'];
		$root_element = preg_replace( '/[^A-Za-z0-9_]+/', '_', $root_element );

		$cache_ttl = isset( $input['cache_ttl_minutes'] ) ? absint( $input['cache_ttl_minutes'] ) : (int) $defaults['cache_ttl_minutes'];
		$cache_ttl = max( 5, min( 1440, $cache_ttl ) );

		return array(
			'root_element'              => ! empty( $root_element ) ? $root_element : $defaults['root_element'],
			'default_manufacturer'      => isset( $input['default_manufacturer'] ) ? sanitize_text_field( wp_unslash( $input['default_manufacturer'] ) ) : $defaults['default_manufacturer'],
			'default_vat_rate'          => isset( $input['default_vat_rate'] ) ? self::sanitize_decimal( $input['default_vat_rate'], $defaults['default_vat_rate'] ) : $defaults['default_vat_rate'],
			'in_stock_availability'     => isset( $input['in_stock_availability'] ) ? sanitize_text_field( wp_unslash( $input['in_stock_availability'] ) ) : $defaults['in_stock_availability'],
			'out_of_stock_availability' => isset( $input['out_of_stock_availability'] ) ? sanitize_text_field( wp_unslash( $input['out_of_stock_availability'] ) ) : $defaults['out_of_stock_availability'],
			'include_hidden_products'   => ! empty( $input['include_hidden_products'] ) ? 1 : 0,
			'cache_ttl_minutes'         => $cache_ttl,
			'enable_logging'            => ! empty( $input['enable_logging'] ) ? 1 : 0,
		);
	}

	/**
	 * Build a meta key for a plugin field.
	 *
	 * @param string $field Meta field suffix.
	 * @return string
	 */
	public static function meta_key( $field ) {
		return self::META_PREFIX . $field;
	}

	/**
	 * Get the last build report.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_report() {
		$report = get_option( self::REPORT_OPTION_KEY, array() );

		return is_array( $report ) ? $report : array();
	}

	/**
	 * Persist the last build report.
	 *
	 * @param array<string, mixed> $report Report payload.
	 * @return void
	 */
	public static function update_report( $report ) {
		update_option( self::REPORT_OPTION_KEY, $report, false );
	}

	/**
	 * Normalize a decimal string.
	 *
	 * @param mixed  $value    Raw value.
	 * @param string $fallback Default value.
	 * @return string
	 */
	private static function sanitize_decimal( $value, $fallback ) {
		$normalized = str_replace( ',', '.', sanitize_text_field( wp_unslash( (string) $value ) ) );

		if ( '' === $normalized || ! is_numeric( $normalized ) ) {
			return $fallback;
		}

		return number_format( (float) $normalized, 2, '.', '' );
	}
}
