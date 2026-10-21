<?php
/**
 * Plugin Name:       Frontierline AIO Toolkit
 * Plugin URI:        https://frontierline.co.jp/plugins/aio-toolkit/
 * Description:       Optimize your site for LLMs and AI search: auto-generate llms.txt and llms-full.txt, allow major AI crawlers in robots.txt, and inject JSON-LD structured data.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Frontier Line Co., Ltd.
 * Author URI:        https://frontierline.co.jp/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       frontierline-aio-toolkit
 * Domain Path:       /languages
 *
 * @package FrontierLine\AIO
 */

namespace FrontierLine\AIO;

defined( 'ABSPATH' ) || exit;

define( 'FLAIO_VERSION', '0.1.0' );
define( 'FLAIO_FILE', __FILE__ );
define( 'FLAIO_DIR', plugin_dir_path( __FILE__ ) );
define( 'FLAIO_URL', plugin_dir_url( __FILE__ ) );
define( 'FLAIO_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Lightweight runtime autoloader (no external dependencies).
 * FrontierLine\AIO\Llms_Generator => includes/class-llms-generator.php
 */
spl_autoload_register(
	static function ( $class ) {
		$prefix = __NAMESPACE__ . '\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$relative = strtolower( str_replace( array( '\\', '_' ), array( '/', '-' ), $relative ) );
		$file     = FLAIO_DIR . 'includes/class-' . $relative . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

/**
 * Activation: register rewrite rules then flush. Multisite-aware.
 *
 * @param bool $network_wide Whether activation is network-wide.
 */
function activate( $network_wide = false ) {
	if ( is_multisite() && $network_wide ) {
		// On very large networks, looping every site would time out. Each site
		// registers its rules on init and flushes them on its own activation.
		if ( wp_is_large_network() ) {
			return;
		}
		/*
		 * Do not call flush_rewrite_rules() inside switch_to_blog(): $wp_rewrite
		 * is not re-initialised per site, so every site would be written the
		 * permalink structure of the site the request runs on. Deleting the
		 * option instead lets each site rebuild its own rules - including ours,
		 * registered on init - on its next request.
		 */
		$sites = get_sites( array( 'number' => 0, 'fields' => 'ids' ) );
		foreach ( $sites as $site_id ) {
			switch_to_blog( (int) $site_id );
			delete_option( 'rewrite_rules' );
			restore_current_blog();
		}
		return;
	}
	Rewrite::add_rules();
	flush_rewrite_rules();
}

/**
 * Deactivation: drop our rules, then flush so the endpoints really disappear.
 *
 * @param bool $network_wide Whether deactivation is network-wide.
 */
function deactivate( $network_wide = false ) {
	if ( is_multisite() && $network_wide ) {
		if ( wp_is_large_network() ) {
			return;
		}
		// See activate(): rebuild lazily per site. The plugin is inactive on the
		// next request, so the regenerated rules no longer contain our endpoints.
		$sites = get_sites( array( 'number' => 0, 'fields' => 'ids' ) );
		foreach ( $sites as $site_id ) {
			switch_to_blog( (int) $site_id );
			delete_option( 'rewrite_rules' );
			restore_current_blog();
		}
		return;
	}
	Rewrite::remove_rules();
	flush_rewrite_rules();
}

register_activation_hook( __FILE__, __NAMESPACE__ . '\\activate' );
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\\deactivate' );

// Boot.
add_action(
	'plugins_loaded',
	static function () {
		Plugin::instance();
	}
);
