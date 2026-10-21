<?php
/**
 * Rewrite rules for the dynamic llms.txt / llms-full.txt endpoints.
 *
 * @package FrontierLine\AIO
 */

namespace FrontierLine\AIO;

defined( 'ABSPATH' ) || exit;

/**
 * Registers virtual endpoints and dispatches them.
 */
class Rewrite {

	/**
	 * Query var used to flag our endpoints.
	 */
	const QUERY_VAR = 'flaio_feed';

	/**
	 * Register hooks.
	 */
	public function hooks() {
		add_action( 'init', array( __CLASS__, 'add_rules' ) );
		add_filter( 'query_vars', array( $this, 'register_query_var' ) );
		// Priority 0: run before core redirect_canonical() so /llms.txt is served as-is.
		add_action( 'template_redirect', array( $this, 'dispatch' ), 0 );
		add_filter( 'redirect_canonical', array( $this, 'block_canonical_redirect' ), 10, 2 );
	}

	/**
	 * Rewrite patterns for the two endpoints, keyed by feed name.
	 *
	 * @return array Pattern => feed name.
	 */
	private static function patterns() {
		return array(
			'^llms\.txt/?$'      => 'llms',
			'^llms-full\.txt/?$' => 'llms_full',
		);
	}

	/**
	 * Add rewrite rules for the endpoints.
	 */
	public static function add_rules() {
		foreach ( self::patterns() as $pattern => $feed ) {
			add_rewrite_rule( $pattern, 'index.php?' . self::QUERY_VAR . '=' . $feed, 'top' );
		}
	}

	/**
	 * Drop our rules from the in-memory rewrite object.
	 *
	 * Deactivation runs after init, so add_rules() has already put the rules
	 * into WP_Rewrite for this request. Flushing without removing them first
	 * would write them straight back to the database, leaving /llms.txt/
	 * resolving to the front page after the plugin is switched off.
	 */
	public static function remove_rules() {
		global $wp_rewrite;
		if ( ! $wp_rewrite instanceof \WP_Rewrite ) {
			return;
		}
		foreach ( array_keys( self::patterns() ) as $pattern ) {
			unset( $wp_rewrite->extra_rules_top[ $pattern ] );
		}
	}

	/**
	 * Register the query var.
	 *
	 * @param array $vars Existing query vars.
	 * @return array
	 */
	public function register_query_var( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Never let WordPress append a trailing slash to our feed URLs.
	 *
	 * @param string|false $redirect_url  Proposed redirect URL.
	 * @param string       $requested_url Originally requested URL.
	 * @return string|false
	 */
	public function block_canonical_redirect( $redirect_url, $requested_url = '' ) {
		if ( '' !== (string) get_query_var( self::QUERY_VAR ) ) {
			return false;
		}
		return $redirect_url;
	}

	/**
	 * Dispatch matching requests to the generator.
	 */
	public function dispatch() {
		$feed = get_query_var( self::QUERY_VAR );
		if ( empty( $feed ) ) {
			return;
		}

		$generator = Plugin::instance()->llms;

		if ( 'llms' === $feed && '1' === (string) Plugin::option( 'llms_enabled', '1' ) ) {
			$generator->output_llms_txt();
			exit;
		}

		if ( 'llms_full' === $feed && '1' === (string) Plugin::option( 'llms_full_enabled', '1' ) ) {
			$generator->output_llms_full_txt();
			exit;
		}

		// Feature disabled: return a clean 404.
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}
}
