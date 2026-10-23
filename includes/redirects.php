<?php
/**
 * Serve stored redirects on the front end.
 *
 * @package LoamSEO
 */

defined( 'ABSPATH' ) || exit;

/**
 * Redirect when the request path exactly matches a stored `from`.
 *
 * Only GET and HEAD requests are redirected. Targets go through
 * wp_validate_redirect(), so only this site's own host (or a host allowed by
 * the `allowed_redirect_hosts` filter) is ever used.
 *
 * @return void
 */
function loamwp_maybe_redirect() {
	$redirects = loamwp_get_redirects();
	if ( empty( $redirects ) ) {
		return;
	}

	$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
	if ( 'GET' !== $method && 'HEAD' !== $method ) {
		return;
	}

	// Only compared for equality against stored paths and never output, so
	// percent-encoding is kept intact rather than stripped by sanitize_text_field().
	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? loamwp_clean_location( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized by loamwp_clean_location().
	$path = wp_parse_url( $uri, PHP_URL_PATH );
	if ( ! is_string( $path ) || '' === $path ) {
		return;
	}

	foreach ( $redirects as $redirect ) {
		if ( $redirect['from'] !== $path ) {
			continue;
		}
		$location = wp_validate_redirect( $redirect['to'], '' );
		if ( '' === $location ) {
			return;
		}
		wp_safe_redirect( $location, $redirect['status'], 'Loam SEO' );
		exit;
	}
}
add_action( 'template_redirect', 'loamwp_maybe_redirect', 1 );
