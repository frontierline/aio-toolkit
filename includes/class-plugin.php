<?php
/**
 * Core plugin bootstrap (singleton).
 *
 * @package FrontierLine\AIO
 */

namespace FrontierLine\AIO;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin container.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Settings component.
	 *
	 * @var Settings
	 */
	public $settings;

	/**
	 * Rewrite component.
	 *
	 * @var Rewrite
	 */
	public $rewrite;

	/**
	 * llms.txt generator.
	 *
	 * @var Llms_Generator
	 */
	public $llms;

	/**
	 * robots.txt handler.
	 *
	 * @var Robots_Handler
	 */
	public $robots;

	/**
	 * JSON-LD injector.
	 *
	 * @var Schema_Injector
	 */
	public $schema;

	/**
	 * Get singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {}

	/**
	 * Wire up components and hooks.
	 */
	private function boot() {
		// Translations load automatically for WordPress.org-hosted plugins
		// (load_plugin_textdomain() unnecessary since WP 4.6).
		$this->settings = new Settings();
		$this->rewrite  = new Rewrite();
		$this->llms     = new Llms_Generator();
		$this->robots   = new Robots_Handler();
		$this->schema   = new Schema_Injector();

		$this->settings->hooks();
		$this->rewrite->hooks();
		$this->llms->hooks();
		$this->robots->hooks();
		$this->schema->hooks();
	}

	/**
	 * Read a single option value with default.
	 *
	 * @param string $key     Option key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function option( $key, $default = '' ) {
		$options = get_option( 'flaio_options', array() );
		if ( is_array( $options ) && array_key_exists( $key, $options ) ) {
			return $options[ $key ];
		}
		return $default;
	}
}
