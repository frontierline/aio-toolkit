<?php
/**
 * Uninstall handler: remove all plugin data.
 *
 * @package FrontierLine\AIO
 */

// Exit if not called by WordPress uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete plugin data for a single site.
 */
function flaio_uninstall_site() {
	delete_option( 'flaio_options' );
	// Remove cached llms-full.txt fragments.
	delete_transient( 'flaio_llms_full' );
}

if ( is_multisite() && ! wp_is_large_network() ) {
	$flaio_sites = get_sites( array( 'number' => 0, 'fields' => 'ids' ) );
	foreach ( $flaio_sites as $flaio_site_id ) {
		switch_to_blog( (int) $flaio_site_id );
		flaio_uninstall_site();
		restore_current_blog();
	}
} else {
	// Single site, or a network too large to walk: clean the current site.
	flaio_uninstall_site();
}
