<?php

/**
 * Simple file logger for feed generation.
 *
 * @package Skroutz_Xml_Feed_For_Woocommerce
 */

class Skroutz_Xml_Feed_For_Woocommerce_Logger {

	/**
	 * Absolute path to the log file.
	 *
	 * @var string
	 */
	private $log_path;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->log_path = $this->determine_log_path();
	}

	/**
	 * Log an informational message.
	 *
	 * @param string               $message Message text.
	 * @param array<string, mixed> $context Optional context.
	 * @return void
	 */
	public function info( $message, $context = array() ) {
		$this->write( 'INFO', $message, $context );
	}

	/**
	 * Log a warning.
	 *
	 * @param string               $message Message text.
	 * @param array<string, mixed> $context Optional context.
	 * @return void
	 */
	public function warning( $message, $context = array() ) {
		$this->write( 'WARNING', $message, $context );
	}

	/**
	 * Log an error.
	 *
	 * @param string               $message Message text.
	 * @param array<string, mixed> $context Optional context.
	 * @return void
	 */
	public function error( $message, $context = array() ) {
		$this->write( 'ERROR', $message, $context );
	}

	/**
	 * Get the log file path.
	 *
	 * @return string
	 */
	public function get_log_path() {
		return $this->log_path;
	}

	/**
	 * Write a line to disk.
	 *
	 * @param string               $level   Log level.
	 * @param string               $message Message text.
	 * @param array<string, mixed> $context Optional context.
	 * @return void
	 */
	private function write( $level, $message, $context = array() ) {
		if ( ! $this->should_log( $level ) ) {
			return;
		}

		$directory = dirname( $this->log_path );

		if ( ! is_dir( $directory ) ) {
			wp_mkdir_p( $directory );
		}

		$line = sprintf( '[%s] %s %s', gmdate( 'Y-m-d H:i:s' ), $level, $message );

		if ( ! empty( $context ) ) {
			$line .= ' ' . wp_json_encode( $context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		}

		$line .= PHP_EOL;

		file_put_contents( $this->log_path, $line, FILE_APPEND | LOCK_EX );
	}

	/**
	 * Determine if the logger should write the current level.
	 *
	 * @param string $level Log level.
	 * @return bool
	 */
	private function should_log( $level ) {
		if ( in_array( $level, array( 'WARNING', 'ERROR' ), true ) ) {
			return true;
		}

		return ! empty( Skroutz_Xml_Feed_For_Woocommerce_Settings::get( 'enable_logging' ) );
	}

	/**
	 * Resolve the log file path.
	 *
	 * @return string
	 */
	private function determine_log_path() {
		$uploads = wp_upload_dir();

		if ( empty( $uploads['error'] ) && ! empty( $uploads['basedir'] ) ) {
			return trailingslashit( $uploads['basedir'] ) . 'skroutz-xml-feed/logs/skroutz-feed.log';
		}

		return trailingslashit( WP_CONTENT_DIR ) . 'uploads/skroutz-xml-feed/logs/skroutz-feed.log';
	}
}
