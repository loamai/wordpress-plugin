<?php
/**
 * Shared helpers: SEO plugin detection, post types, sanitizers, storage.
 *
 * @package LoamSEO
 */

defined( 'ABSPATH' ) || exit;

/** Option holding the stored robots.txt rules (string). */
const LOAMWP_OPTION_ROBOTS = 'loam_robots_rules';

/** Option holding the stored redirects (list of {from, to, status}). */
const LOAMWP_OPTION_REDIRECTS = 'loam_redirects';

/** Upper bound on stored redirects; the option is autoloaded on every request. */
const LOAMWP_MAX_REDIRECTS = 1000;

/** Field limits shared by the REST args and the registered meta. */
const LOAMWP_MAX_TITLE       = 70;
const LOAMWP_MAX_DESCRIPTION = 200;
const LOAMWP_MAX_URL         = 2048;
const LOAMWP_MAX_ROBOTS      = 10000;
const LOAMWP_MAX_JSONLD      = 65535;

/**
 * Which SEO plugin owns the head tags on this site.
 *
 * @return string|null 'yoast', 'rank-math', or null when neither is active.
 */
function loamwp_seo_plugin() {
	if ( defined( 'WPSEO_VERSION' ) ) {
		return 'yoast';
	}
	if ( defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath' ) ) {
		return 'rank-math';
	}
	return null;
}

/**
 * Post types the plugin manages. Filterable with `loamwp_post_types`.
 *
 * @return string[]
 */
function loamwp_post_types() {
	$types = apply_filters( 'loamwp_post_types', array( 'post', 'page' ) );
	return is_array( $types ) ? array_values( array_filter( $types, 'is_string' ) ) : array( 'post', 'page' );
}

/**
 * Multibyte-safe truncation.
 *
 * @param string $value Value.
 * @param int    $max   Maximum characters.
 * @return string
 */
function loamwp_truncate( $value, $max ) {
	if ( function_exists( 'mb_substr' ) ) {
		return mb_substr( $value, 0, $max );
	}
	return substr( $value, 0, $max );
}

/**
 * Plain single-line text for an SEO title or description.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function loamwp_sanitize_seo_text( $value ) {
	return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
}

/**
 * Registered-meta sanitizer for `_loam_seo_title`.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function loamwp_sanitize_title_meta( $value ) {
	return loamwp_truncate( loamwp_sanitize_seo_text( $value ), LOAMWP_MAX_TITLE );
}

/**
 * Registered-meta sanitizer for `_loam_seo_description`.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function loamwp_sanitize_description_meta( $value ) {
	return loamwp_truncate( loamwp_sanitize_seo_text( $value ), LOAMWP_MAX_DESCRIPTION );
}

/**
 * True when the value is an absolute http(s) URL with a host.
 *
 * @param mixed $value Raw value.
 * @return bool
 */
function loamwp_is_http_url( $value ) {
	if ( ! is_string( $value ) || '' === $value ) {
		return false;
	}
	$scheme = wp_parse_url( $value, PHP_URL_SCHEME );
	$host   = wp_parse_url( $value, PHP_URL_HOST );
	return is_string( $scheme ) && in_array( strtolower( $scheme ), array( 'http', 'https' ), true ) && is_string( $host ) && '' !== $host;
}

/**
 * Canonical URL: an absolute http(s) URL, or '' (clears).
 *
 * @param mixed $value Raw value.
 * @return string
 */
function loamwp_sanitize_canonical( $value ) {
	if ( ! is_string( $value ) ) {
		return '';
	}
	$value = trim( $value );
	if ( ! loamwp_is_http_url( $value ) ) {
		return '';
	}
	return esc_url_raw( $value, array( 'http', 'https' ) );
}

/**
 * Parse a JSON-LD string. Returns the decoded object/array, or null when it
 * is not a JSON object or array.
 *
 * @param mixed $value Raw value.
 * @return object|array|null
 */
function loamwp_decode_jsonld( $value ) {
	if ( ! is_string( $value ) ) {
		return null;
	}
	$value = trim( $value );
	if ( '' === $value ) {
		return null;
	}
	$decoded = json_decode( $value, false, 64 );
	if ( JSON_ERROR_NONE !== json_last_error() ) {
		return null;
	}
	return ( is_object( $decoded ) || is_array( $decoded ) ) ? $decoded : null;
}

/**
 * Registered-meta sanitizer for `_loam_schema_jsonld`: stores normalized JSON,
 * or '' when the value is empty or not a JSON object/array.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function loamwp_sanitize_jsonld_meta( $value ) {
	$decoded = loamwp_decode_jsonld( $value );
	if ( null === $decoded ) {
		return '';
	}
	$json = wp_json_encode( $decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	return is_string( $json ) ? $json : '';
}

/**
 * Robots.txt rules: plain text, LF line endings, no control characters or tags.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function loamwp_sanitize_robots_rules( $value ) {
	if ( ! is_string( $value ) ) {
		return '';
	}
	$value = wp_check_invalid_utf8( $value );
	$value = str_replace( array( "\r\n", "\r" ), "\n", $value );
	$value = preg_replace( '/[\x00-\x08\x0B-\x1F\x7F]/', '', $value );
	$value = wp_strip_all_tags( (string) $value );
	$lines = array_map( 'rtrim', explode( "\n", $value ) );
	return trim( implode( "\n", $lines ) );
}

/**
 * Remove control characters and surrounding whitespace from a path or URL.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function loamwp_clean_location( $value ) {
	if ( ! is_string( $value ) ) {
		return '';
	}
	return trim( (string) preg_replace( '/[\x00-\x1F\x7F]/', '', $value ) );
}

/**
 * Write one string post meta value; '' deletes it. Returns true when the
 * stored value now matches what was asked for.
 *
 * @param int    $post_id Post ID.
 * @param string $key     Meta key.
 * @param string $value   Value ('' clears).
 * @return bool
 */
function loamwp_write_post_meta( $post_id, $key, $value ) {
	if ( '' === $value ) {
		delete_post_meta( $post_id, $key );
		return '' === (string) get_post_meta( $post_id, $key, true );
	}
	// update_post_meta() unslashes its input, so slash it first or JSON
	// backslash escapes are lost.
	update_post_meta( $post_id, $key, wp_slash( $value ) );
	return '' !== (string) get_post_meta( $post_id, $key, true );
}

/**
 * The stored redirects, normalized.
 *
 * @return array<int, array{from: string, to: string, status: int}>
 */
function loamwp_get_redirects() {
	$stored = get_option( LOAMWP_OPTION_REDIRECTS, array() );
	if ( ! is_array( $stored ) ) {
		return array();
	}
	$out = array();
	foreach ( $stored as $row ) {
		if ( ! is_array( $row ) || ! isset( $row['from'], $row['to'] ) ) {
			continue;
		}
		$status = isset( $row['status'] ) ? (int) $row['status'] : 301;
		$out[]  = array(
			'from'   => (string) $row['from'],
			'to'     => (string) $row['to'],
			'status' => in_array( $status, array( 301, 302 ), true ) ? $status : 301,
		);
	}
	return $out;
}

/**
 * Whether WordPress itself serves /robots.txt with the stored rules: no
 * physical robots.txt file, WordPress answers at the domain root, and the
 * site does not discourage search engines (that setting always wins).
 *
 * @return bool
 */
function loamwp_robots_txt_writable() {
	if ( '0' === (string) get_option( 'blog_public' ) ) {
		return false;
	}
	$home_path = wp_parse_url( home_url(), PHP_URL_PATH );
	if ( is_string( $home_path ) && '' !== trim( $home_path, '/' ) ) {
		return false;
	}
	if ( ! function_exists( 'get_home_path' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	return ! file_exists( trailingslashit( get_home_path() ) . 'robots.txt' );
}

/**
 * Best-known sitemap index URL for this site.
 *
 * @return string|null
 */
function loamwp_sitemap_url() {
	if ( null !== loamwp_seo_plugin() ) {
		// Yoast and Rank Math both replace core sitemaps with this index.
		return home_url( '/sitemap_index.xml' );
	}
	if ( function_exists( 'wp_sitemaps_get_server' ) ) {
		$server = wp_sitemaps_get_server();
		if ( $server && method_exists( $server, 'sitemaps_enabled' ) && ! $server->sitemaps_enabled() ) {
			return null;
		}
	}
	return home_url( '/wp-sitemap.xml' );
}
