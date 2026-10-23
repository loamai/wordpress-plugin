<?php
/**
 * Front-end output: document title, meta description, canonical, JSON-LD,
 * and robots.txt.
 *
 * Title, description and canonical are printed only when no SEO plugin is
 * active; with Yoast or Rank Math the REST routes write into that plugin's
 * own fields and the plugin prints them.
 *
 * @package LoamSEO
 */

defined( 'ABSPATH' ) || exit;

/**
 * The queried post ID when the view is a singular post of a managed type.
 *
 * @return int 0 when not applicable.
 */
function loamwp_current_post_id() {
	if ( is_admin() || ! is_singular( loamwp_post_types() ) ) {
		return 0;
	}
	return (int) get_queried_object_id();
}

/**
 * A stored Loam meta value as a string.
 *
 * @param int    $post_id Post ID.
 * @param string $key     Meta key.
 * @return string
 */
function loamwp_get_meta( $post_id, $key ) {
	$value = get_post_meta( $post_id, $key, true );
	return is_string( $value ) ? $value : '';
}

/**
 * Replace the document title with `_loam_seo_title`.
 *
 * @param string $title Title from earlier filters.
 * @return string
 */
function loamwp_document_title( $title ) {
	if ( null !== loamwp_seo_plugin() ) {
		return $title;
	}
	$post_id = loamwp_current_post_id();
	if ( ! $post_id ) {
		return $title;
	}
	$loam_title = loamwp_get_meta( $post_id, '_loam_seo_title' );
	// wp_get_document_title() returns a pre-filtered title without escaping it.
	return '' !== $loam_title ? esc_html( $loam_title ) : $title;
}
add_filter( 'pre_get_document_title', 'loamwp_document_title', 20 );

/**
 * Remove core's canonical link when a Loam canonical replaces it.
 *
 * @return void
 */
function loamwp_prepare_head() {
	if ( null !== loamwp_seo_plugin() ) {
		return;
	}
	$post_id = loamwp_current_post_id();
	if ( $post_id && '' !== loamwp_get_meta( $post_id, '_loam_canonical' ) ) {
		remove_action( 'wp_head', 'rel_canonical' );
	}
}
add_action( 'wp', 'loamwp_prepare_head' );

/**
 * Print the meta description, canonical link and JSON-LD.
 *
 * @return void
 */
function loamwp_print_head() {
	$post_id = loamwp_current_post_id();
	if ( ! $post_id ) {
		return;
	}

	if ( null === loamwp_seo_plugin() ) {
		$description = loamwp_get_meta( $post_id, '_loam_seo_description' );
		if ( '' !== $description ) {
			printf( '<meta name="description" content="%s" />' . "\n", esc_attr( $description ) );
		}

		$canonical = loamwp_get_meta( $post_id, '_loam_canonical' );
		if ( '' !== $canonical && loamwp_is_http_url( $canonical ) ) {
			printf( '<link rel="canonical" href="%s" />' . "\n", esc_url( $canonical, array( 'http', 'https' ) ) );
		}
	}

	$schema = loamwp_decode_jsonld( loamwp_get_meta( $post_id, '_loam_schema_jsonld' ) );
	if ( null !== $schema && ! empty( (array) $schema ) ) {
		// JSON_HEX_TAG encodes "<" and ">", so the content cannot close the script element.
		$json = wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP );
		if ( is_string( $json ) ) {
			echo '<script type="application/ld+json">' . $json . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON re-encoded with JSON_HEX_TAG; HTML escaping would corrupt it.
		}
	}
}
add_action( 'wp_head', 'loamwp_print_head', 1 );

/**
 * Serve the stored robots.txt rules in place of the generated body.
 *
 * Left untouched when the site discourages search engines: that setting
 * always wins over rules stored from Loam.
 *
 * @param string     $output Generated robots.txt.
 * @param string|int $public The blog_public option.
 * @return string
 */
function loamwp_robots_txt( $output, $public ) {
	if ( '0' === (string) $public ) {
		return $output;
	}
	$rules = get_option( LOAMWP_OPTION_ROBOTS, '' );
	if ( ! is_string( $rules ) || '' === trim( $rules ) ) {
		return $output;
	}
	return $rules . "\n";
}
// Late priority so the stored body replaces what SEO plugins add.
add_filter( 'robots_txt', 'loamwp_robots_txt', 999999, 2 );
