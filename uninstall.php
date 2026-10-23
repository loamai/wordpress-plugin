<?php
/**
 * Uninstall: remove the plugin's options and its own post meta.
 *
 * Values written into Yoast or Rank Math fields belong to those plugins and
 * are left in place.
 *
 * @package LoamSEO
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Delete this site's Loam data.
 *
 * @return void
 */
function loamwp_uninstall_site() {
	delete_option( 'loam_robots_rules' );
	delete_option( 'loam_redirects' );
	foreach ( array( '_loam_seo_title', '_loam_seo_description', '_loam_canonical', '_loam_schema_jsonld' ) as $loamwp_key ) {
		delete_post_meta_by_key( $loamwp_key );
	}
}

if ( is_multisite() ) {
	$loamwp_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $loamwp_site_ids as $loamwp_site_id ) {
		switch_to_blog( (int) $loamwp_site_id );
		loamwp_uninstall_site();
		restore_current_blog();
	}
} else {
	loamwp_uninstall_site();
}
