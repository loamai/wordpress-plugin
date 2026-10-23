<?php
/**
 * Per-post meta registration.
 *
 * @package LoamSEO
 */

defined( 'ABSPATH' ) || exit;

/**
 * The four Loam meta keys and their sanitizers.
 *
 * @return array<string, string>
 */
function loamwp_meta_fields() {
	return array(
		'_loam_seo_title'       => 'loamwp_sanitize_title_meta',
		'_loam_seo_description' => 'loamwp_sanitize_description_meta',
		'_loam_canonical'       => 'loamwp_sanitize_canonical',
		'_loam_schema_jsonld'   => 'loamwp_sanitize_jsonld_meta',
	);
}

/**
 * Meta auth: the same gate as the loam/v1 routes (administrator) plus the
 * per-post edit check. Canonical and JSON-LD reach every visitor's page, so
 * an author who can edit one post must not be able to set them.
 *
 * @param bool   $allowed  Whether the user can edit (unused).
 * @param string $meta_key Meta key (unused).
 * @param int    $post_id  Post ID.
 * @return bool
 */
function loamwp_meta_auth( $allowed, $meta_key, $post_id ) {
	return current_user_can( 'manage_options' ) && current_user_can( 'edit_post', (int) $post_id );
}

/**
 * Register the meta for every managed post type.
 *
 * @return void
 */
function loamwp_register_meta() {
	foreach ( loamwp_post_types() as $post_type ) {
		foreach ( loamwp_meta_fields() as $key => $sanitize ) {
			register_post_meta(
				$post_type,
				$key,
				array(
					'type'              => 'string',
					'single'            => true,
					'default'           => '',
					// Written only through the admin-gated loam/v1/meta route.
					'show_in_rest'      => false,
					'sanitize_callback' => $sanitize,
					'auth_callback'     => 'loamwp_meta_auth',
				)
			);
		}
	}
}
add_action( 'init', 'loamwp_register_meta' );

/**
 * Meta keys used for title/description/canonical, by the SEO plugin that owns them.
 *
 * @param string|null $seo_plugin Result of loamwp_seo_plugin().
 * @return array{title: string, description: string, canonical: string}
 */
function loamwp_target_keys( $seo_plugin ) {
	if ( 'yoast' === $seo_plugin ) {
		return array(
			'title'       => '_yoast_wpseo_title',
			'description' => '_yoast_wpseo_metadesc',
			'canonical'   => '_yoast_wpseo_canonical',
		);
	}
	if ( 'rank-math' === $seo_plugin ) {
		return array(
			'title'       => 'rank_math_title',
			'description' => 'rank_math_description',
			'canonical'   => 'rank_math_canonical_url',
		);
	}
	return array(
		'title'       => '_loam_seo_title',
		'description' => '_loam_seo_description',
		'canonical'   => '_loam_canonical',
	);
}

/**
 * Read-only `loam_seo` REST field: the SEO title and description a headless
 * front end (getloam.co/blog) puts in the page head. Values come from the SEO
 * plugin that owns them (Yoast, Rank Math or Loam's own meta), falling back
 * to the post title and excerpt. There is no update_callback: writes go only
 * through the admin-gated loam/v1/meta route.
 *
 * @return void
 */
function loamwp_register_seo_rest_field() {
	register_rest_field(
		loamwp_post_types(),
		'loam_seo',
		array(
			'get_callback' => 'loamwp_seo_rest_value',
			'schema'       => array(
				'description' => 'SEO title and description for this post (read-only).',
				'type'        => 'object',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
				'properties'  => array(
					'title'       => array( 'type' => 'string' ),
					'description' => array( 'type' => 'string' ),
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'loamwp_register_seo_rest_field' );

/**
 * Value of the `loam_seo` field for one post.
 *
 * @param array $post_arr Prepared post data (needs 'id').
 * @return array{title: string, description: string}
 */
function loamwp_seo_rest_value( $post_arr ) {
	$post_id = isset( $post_arr['id'] ) ? (int) $post_arr['id'] : 0;
	$post    = $post_id ? get_post( $post_id ) : null;
	if ( ! $post || post_password_required( $post ) ) {
		return array(
			'title'       => '',
			'description' => '',
		);
	}
	$keys        = loamwp_target_keys( loamwp_seo_plugin() );
	$title       = (string) get_post_meta( $post_id, $keys['title'], true );
	$description = (string) get_post_meta( $post_id, $keys['description'], true );
	// Yoast and Rank Math store templates such as "%%title%%" by default;
	// a headless head cannot expand them, so treat a template as unset.
	if ( false !== strpos( $title, '%%' ) || false !== strpos( $title, '%title%' ) ) {
		$title = '';
	}
	if ( false !== strpos( $description, '%%' ) || false !== strpos( $description, '%excerpt%' ) ) {
		$description = '';
	}
	if ( '' === $title ) {
		$title = wp_strip_all_tags( get_the_title( $post ) );
	}
	if ( '' === $description ) {
		$description = wp_strip_all_tags( get_the_excerpt( $post ) );
	}
	return array(
		'title'       => $title,
		'description' => $description,
	);
}
