<?php
/**
 * REST routes under loam/v1. Every route requires manage_options.
 *
 * @package LoamSEO
 */

defined( 'ABSPATH' ) || exit;

/** REST namespace. */
const LOAMWP_REST_NAMESPACE = 'loam/v1';

/**
 * Permission check shared by every route.
 *
 * @return true|WP_Error
 */
function loamwp_rest_permission() {
	if ( current_user_can( 'manage_options' ) ) {
		return true;
	}
	return new WP_Error(
		'loam_forbidden',
		__( 'Loam SEO requires an administrator account.', 'loam-seo' ),
		array( 'status' => rest_authorization_required_code() )
	);
}

/**
 * A 400 error.
 *
 * @param string $code    Error code.
 * @param string $message Message.
 * @return WP_Error
 */
function loamwp_bad_request( $code, $message ) {
	return new WP_Error( $code, $message, array( 'status' => 400 ) );
}

/*
 * ---------------------------------------------------------------------------
 * Arg validators. A custom validate_callback replaces WordPress's schema
 * validation, so each one runs rest_validate_request_arg() first.
 * ---------------------------------------------------------------------------
 */

/**
 * PostId: an existing post of a managed type that the user can edit.
 *
 * @param mixed           $value   Value.
 * @param WP_REST_Request $request Request.
 * @param string          $param   Param name.
 * @return true|WP_Error
 */
function loamwp_validate_post_id( $value, $request, $param ) {
	$valid = rest_validate_request_arg( $value, $request, $param );
	if ( is_wp_error( $valid ) ) {
		return $valid;
	}
	$post = get_post( (int) $value );
	if ( ! $post || ! in_array( $post->post_type, loamwp_post_types(), true ) ) {
		return loamwp_bad_request( 'loam_invalid_post', __( 'postId is not a post or page on this site.', 'loam-seo' ) );
	}
	if ( ! current_user_can( 'edit_post', $post->ID ) ) {
		return new WP_Error( 'loam_forbidden', __( 'You cannot edit this post.', 'loam-seo' ), array( 'status' => 403 ) );
	}
	return true;
}

/**
 * Canonical url: '' (clears) or an absolute http(s) URL.
 *
 * @param mixed           $value   Value.
 * @param WP_REST_Request $request Request.
 * @param string          $param   Param name.
 * @return true|WP_Error
 */
function loamwp_validate_canonical( $value, $request, $param ) {
	$valid = rest_validate_request_arg( $value, $request, $param );
	if ( is_wp_error( $valid ) ) {
		return $valid;
	}
	$value = trim( (string) $value );
	if ( '' !== $value && ! loamwp_is_http_url( $value ) ) {
		return loamwp_bad_request( 'loam_invalid_url', __( 'url must be an absolute http or https URL.', 'loam-seo' ) );
	}
	return true;
}

/**
 * JSON-LD: '' (clears) or a JSON object or array.
 *
 * @param mixed           $value   Value.
 * @param WP_REST_Request $request Request.
 * @param string          $param   Param name.
 * @return true|WP_Error
 */
function loamwp_validate_jsonld( $value, $request, $param ) {
	$valid = rest_validate_request_arg( $value, $request, $param );
	if ( is_wp_error( $valid ) ) {
		return $valid;
	}
	if ( '' !== trim( (string) $value ) && null === loamwp_decode_jsonld( $value ) ) {
		return loamwp_bad_request( 'loam_invalid_jsonld', __( 'jsonld must be a JSON object or array.', 'loam-seo' ) );
	}
	return true;
}

/**
 * Redirect source: a site path starting with a single "/", no query or
 * fragment, not the homepage, and not an admin, login or REST path.
 *
 * @param mixed           $value   Value.
 * @param WP_REST_Request $request Request.
 * @param string          $param   Param name.
 * @return true|WP_Error
 */
function loamwp_validate_redirect_from( $value, $request, $param ) {
	$valid = rest_validate_request_arg( $value, $request, $param );
	if ( is_wp_error( $valid ) ) {
		return $valid;
	}
	$error = loamwp_redirect_from_error( loamwp_clean_location( $value ) );
	return null === $error ? true : $error;
}

/**
 * Why a redirect source is unsafe, or null when it is fine.
 *
 * @param string $from Cleaned path.
 * @return WP_Error|null
 */
function loamwp_redirect_from_error( $from ) {
	if ( '' === $from || '/' !== $from[0] || ( strlen( $from ) > 1 && '/' === $from[1] ) ) {
		return loamwp_bad_request( 'loam_invalid_from', __( 'from must be a path starting with a single "/".', 'loam-seo' ) );
	}
	if ( preg_match( '/[\s?#\\\\]/', $from ) ) {
		return loamwp_bad_request( 'loam_invalid_from', __( 'from must not contain spaces, backslashes, a query string or a fragment.', 'loam-seo' ) );
	}
	if ( '/' === $from ) {
		return loamwp_bad_request( 'loam_reserved_from', __( 'The homepage cannot be redirected.', 'loam-seo' ) );
	}
	$reserved = array( '/wp-admin', '/wp-login.php', '/wp-json', '/' . rest_get_url_prefix(), '/wp-content', '/wp-includes', '/xmlrpc.php' );
	$lower    = strtolower( $from );
	foreach ( $reserved as $prefix ) {
		if ( $lower === $prefix || 0 === strpos( $lower, $prefix . '/' ) || 0 === strpos( $lower, $prefix . '.' ) ) {
			return loamwp_bad_request( 'loam_reserved_from', __( 'Admin, login, REST and core paths cannot be redirected.', 'loam-seo' ) );
		}
	}
	return null;
}

/**
 * Redirect target: a same-site path ("/…") or an absolute http(s) URL on
 * this site's own host (or a host allowed by `allowed_redirect_hosts`).
 *
 * @param mixed           $value   Value.
 * @param WP_REST_Request $request Request.
 * @param string          $param   Param name.
 * @return true|WP_Error
 */
function loamwp_validate_redirect_to( $value, $request, $param ) {
	$valid = rest_validate_request_arg( $value, $request, $param );
	if ( is_wp_error( $valid ) ) {
		return $valid;
	}
	$to = loamwp_clean_location( $value );
	if ( '' === $to || preg_match( '/[\s\\\\]/', $to ) ) {
		return loamwp_bad_request( 'loam_invalid_to', __( 'to must be a path or an http(s) URL without spaces or backslashes.', 'loam-seo' ) );
	}
	$is_path = '/' === $to[0] && ( strlen( $to ) === 1 || '/' !== $to[1] );
	if ( ! $is_path && ! loamwp_is_http_url( $to ) ) {
		return loamwp_bad_request( 'loam_invalid_to', __( 'to must be a path starting with "/" or an absolute http(s) URL.', 'loam-seo' ) );
	}
	if ( ! $is_path && '' === wp_validate_redirect( $to, '' ) ) {
		return loamwp_bad_request( 'loam_external_to', __( 'to must stay on this site.', 'loam-seo' ) );
	}
	return true;
}

/**
 * Sanitize a redirect target.
 *
 * @param mixed $value Value.
 * @return string
 */
function loamwp_sanitize_redirect_to( $value ) {
	return esc_url_raw( loamwp_clean_location( $value ), array( 'http', 'https' ) );
}

/**
 * Sanitize a positive integer.
 *
 * @param mixed $value Value.
 * @return int
 */
function loamwp_sanitize_int( $value ) {
	return absint( $value );
}

/**
 * Sanitize a trimmed canonical URL ('' allowed).
 *
 * @param mixed $value Value.
 * @return string
 */
function loamwp_sanitize_canonical_arg( $value ) {
	return loamwp_sanitize_canonical( $value );
}

/**
 * Sanitize a JSON-LD arg to normalized JSON ('' allowed).
 *
 * @param mixed $value Value.
 * @return string
 */
function loamwp_sanitize_jsonld_arg( $value ) {
	return loamwp_sanitize_jsonld_meta( $value );
}

/**
 * The shared postId arg.
 *
 * @return array
 */
function loamwp_post_id_arg() {
	return array(
		'description'       => __( 'ID of the post or page.', 'loam-seo' ),
		'type'              => 'integer',
		'required'          => true,
		'minimum'           => 1,
		'validate_callback' => 'loamwp_validate_post_id',
		'sanitize_callback' => 'loamwp_sanitize_int',
	);
}

/**
 * The shared redirect `from` arg.
 *
 * @return array
 */
function loamwp_redirect_from_arg() {
	return array(
		'description'       => __( 'Request path to redirect, starting with "/".', 'loam-seo' ),
		'type'              => 'string',
		'required'          => true,
		'minLength'         => 2,
		'maxLength'         => LOAMWP_MAX_URL,
		'validate_callback' => 'loamwp_validate_redirect_from',
		'sanitize_callback' => 'loamwp_clean_location',
	);
}

/**
 * Register the routes.
 *
 * @return void
 */
function loamwp_register_routes() {
	register_rest_route(
		LOAMWP_REST_NAMESPACE,
		'/status',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'loamwp_rest_status',
			'permission_callback' => 'loamwp_rest_permission',
		)
	);

	register_rest_route(
		LOAMWP_REST_NAMESPACE,
		'/robots',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'loamwp_rest_robots',
			'permission_callback' => 'loamwp_rest_permission',
			'args'                => array(
				'rules' => array(
					'description'       => __( 'The full robots.txt body. Empty restores the WordPress default.', 'loam-seo' ),
					'type'              => 'string',
					'required'          => true,
					'maxLength'         => LOAMWP_MAX_ROBOTS,
					'validate_callback' => 'rest_validate_request_arg',
					'sanitize_callback' => 'loamwp_sanitize_robots_rules',
				),
			),
		)
	);

	register_rest_route(
		LOAMWP_REST_NAMESPACE,
		'/meta',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'loamwp_rest_meta',
			'permission_callback' => 'loamwp_rest_permission',
			'args'                => array(
				'postId'      => loamwp_post_id_arg(),
				'title'       => array(
					'description'       => __( 'SEO title. Empty clears it.', 'loam-seo' ),
					'type'              => 'string',
					'maxLength'         => LOAMWP_MAX_TITLE,
					'validate_callback' => 'rest_validate_request_arg',
					'sanitize_callback' => 'loamwp_sanitize_seo_text',
				),
				'description' => array(
					'description'       => __( 'Meta description. Empty clears it.', 'loam-seo' ),
					'type'              => 'string',
					'maxLength'         => LOAMWP_MAX_DESCRIPTION,
					'validate_callback' => 'rest_validate_request_arg',
					'sanitize_callback' => 'loamwp_sanitize_seo_text',
				),
			),
		)
	);

	register_rest_route(
		LOAMWP_REST_NAMESPACE,
		'/canonical',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'loamwp_rest_canonical',
			'permission_callback' => 'loamwp_rest_permission',
			'args'                => array(
				'postId' => loamwp_post_id_arg(),
				'url'    => array(
					'description'       => __( 'Absolute http(s) canonical URL. Empty clears it.', 'loam-seo' ),
					'type'              => 'string',
					'required'          => true,
					'maxLength'         => LOAMWP_MAX_URL,
					'validate_callback' => 'loamwp_validate_canonical',
					'sanitize_callback' => 'loamwp_sanitize_canonical_arg',
				),
			),
		)
	);

	register_rest_route(
		LOAMWP_REST_NAMESPACE,
		'/schema',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'loamwp_rest_schema',
			'permission_callback' => 'loamwp_rest_permission',
			'args'                => array(
				'postId' => loamwp_post_id_arg(),
				'jsonld' => array(
					'description'       => __( 'JSON-LD as a JSON object or array, encoded as a string. Empty clears it.', 'loam-seo' ),
					'type'              => 'string',
					'required'          => true,
					'maxLength'         => LOAMWP_MAX_JSONLD,
					'validate_callback' => 'loamwp_validate_jsonld',
					'sanitize_callback' => 'loamwp_sanitize_jsonld_arg',
				),
			),
		)
	);

	register_rest_route(
		LOAMWP_REST_NAMESPACE,
		'/redirects',
		array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'loamwp_rest_redirects_list',
				'permission_callback' => 'loamwp_rest_permission',
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'loamwp_rest_redirects_upsert',
				'permission_callback' => 'loamwp_rest_permission',
				'args'                => array(
					'from'   => loamwp_redirect_from_arg(),
					'to'     => array(
						'description'       => __( 'Target: a path starting with "/" or an http(s) URL on this site.', 'loam-seo' ),
						'type'              => 'string',
						'required'          => true,
						'maxLength'         => LOAMWP_MAX_URL,
						'validate_callback' => 'loamwp_validate_redirect_to',
						'sanitize_callback' => 'loamwp_sanitize_redirect_to',
					),
					'status' => array(
						'description'       => __( 'HTTP status: 301 or 302.', 'loam-seo' ),
						'type'              => 'integer',
						'enum'              => array( 301, 302 ),
						'default'           => 301,
						'validate_callback' => 'rest_validate_request_arg',
						'sanitize_callback' => 'loamwp_sanitize_int',
					),
				),
			),
			array(
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => 'loamwp_rest_redirects_delete',
				'permission_callback' => 'loamwp_rest_permission',
				'args'                => array(
					'from' => array(
						'description'       => __( 'Request path of the redirect to remove.', 'loam-seo' ),
						'type'              => 'string',
						'required'          => true,
						'minLength'         => 1,
						'maxLength'         => LOAMWP_MAX_URL,
						'validate_callback' => 'rest_validate_request_arg',
						'sanitize_callback' => 'loamwp_clean_location',
					),
				),
			),
		)
	);
}
add_action( 'rest_api_init', 'loamwp_register_routes' );

/*
 * ---------------------------------------------------------------------------
 * Callbacks.
 * ---------------------------------------------------------------------------
 */

/**
 * GET /loam/v1/status
 *
 * @return WP_REST_Response
 */
function loamwp_rest_status() {
	return rest_ensure_response(
		array(
			'version'           => LOAMWP_VERSION,
			'seoPlugin'         => loamwp_seo_plugin(),
			'sitemap'           => loamwp_sitemap_url(),
			'robotsTxtWritable' => loamwp_robots_txt_writable(),
		)
	);
}

/**
 * POST /loam/v1/robots
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function loamwp_rest_robots( WP_REST_Request $request ) {
	$rules = (string) $request['rules'];

	if ( '' === $rules ) {
		delete_option( LOAMWP_OPTION_ROBOTS );
		return rest_ensure_response( array( 'ok' => true ) );
	}

	// Never report success for rules no visitor would ever be served.
	if ( ! loamwp_robots_txt_writable() ) {
		return new WP_Error(
			'loam_robots_not_served',
			__( 'WordPress does not serve robots.txt on this site: a physical robots.txt file exists, WordPress is not at the domain root, or "Discourage search engines" is on.', 'loam-seo' ),
			array( 'status' => 409 )
		);
	}

	update_option( LOAMWP_OPTION_ROBOTS, $rules, false );
	return rest_ensure_response( array( 'ok' => true ) );
}

/**
 * POST /loam/v1/meta
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function loamwp_rest_meta( WP_REST_Request $request ) {
	$has_title       = $request->has_param( 'title' );
	$has_description = $request->has_param( 'description' );
	if ( ! $has_title && ! $has_description ) {
		return loamwp_bad_request( 'loam_nothing_to_write', __( 'Send title, description, or both.', 'loam-seo' ) );
	}

	$post_id = (int) $request['postId'];
	$target  = loamwp_seo_plugin();
	$keys    = loamwp_target_keys( $target );

	$ok = true;
	if ( $has_title ) {
		$ok = loamwp_write_post_meta( $post_id, $keys['title'], (string) $request['title'] ) && $ok;
	}
	if ( $has_description ) {
		$ok = loamwp_write_post_meta( $post_id, $keys['description'], (string) $request['description'] ) && $ok;
	}
	if ( ! $ok ) {
		return new WP_Error( 'loam_write_failed', __( 'The value was not stored.', 'loam-seo' ), array( 'status' => 500 ) );
	}

	return rest_ensure_response(
		array(
			'ok'      => true,
			'wroteTo' => null === $target ? 'loam' : $target,
		)
	);
}

/**
 * POST /loam/v1/canonical
 *
 * Written to the active SEO plugin's canonical field when there is one, so the
 * page never carries two canonical tags.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function loamwp_rest_canonical( WP_REST_Request $request ) {
	$post_id = (int) $request['postId'];
	$url     = (string) $request['url'];
	$target  = loamwp_seo_plugin();
	$keys    = loamwp_target_keys( $target );

	if ( ! loamwp_write_post_meta( $post_id, $keys['canonical'], $url ) ) {
		return new WP_Error( 'loam_write_failed', __( 'The value was not stored.', 'loam-seo' ), array( 'status' => 500 ) );
	}

	return rest_ensure_response(
		array(
			'ok'      => true,
			'wroteTo' => null === $target ? 'loam' : $target,
		)
	);
}

/**
 * POST /loam/v1/schema
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function loamwp_rest_schema( WP_REST_Request $request ) {
	$post_id = (int) $request['postId'];
	$json    = (string) $request['jsonld'];

	if ( ! loamwp_write_post_meta( $post_id, '_loam_schema_jsonld', $json ) ) {
		return new WP_Error( 'loam_write_failed', __( 'The value was not stored.', 'loam-seo' ), array( 'status' => 500 ) );
	}

	return rest_ensure_response( array( 'ok' => true ) );
}

/**
 * GET /loam/v1/redirects
 *
 * @return WP_REST_Response
 */
function loamwp_rest_redirects_list() {
	return rest_ensure_response( array( 'redirects' => loamwp_get_redirects() ) );
}

/**
 * POST /loam/v1/redirects — insert, or replace the row with the same `from`.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
function loamwp_rest_redirects_upsert( WP_REST_Request $request ) {
	$from   = (string) $request['from'];
	$to     = (string) $request['to'];
	$status = (int) $request['status'];

	if ( '' === $to ) {
		return loamwp_bad_request( 'loam_invalid_to', __( 'to is not a valid path or URL.', 'loam-seo' ) );
	}
	// The target is already validated as same-site, so comparing paths is enough.
	if ( (string) wp_parse_url( $to, PHP_URL_PATH ) === $from ) {
		return loamwp_bad_request( 'loam_redirect_loop', __( 'from and to point at the same path.', 'loam-seo' ) );
	}

	$row       = array(
		'from'   => $from,
		'to'     => $to,
		'status' => 302 === $status ? 302 : 301,
	);
	$redirects = loamwp_get_redirects();
	$created   = true;
	foreach ( $redirects as $i => $existing ) {
		if ( $existing['from'] === $from ) {
			$redirects[ $i ] = $row;
			$created         = false;
			break;
		}
	}
	if ( $created ) {
		if ( count( $redirects ) >= LOAMWP_MAX_REDIRECTS ) {
			return loamwp_bad_request( 'loam_redirect_limit', __( 'This site already has the maximum number of Loam redirects.', 'loam-seo' ) );
		}
		$redirects[] = $row;
	}

	update_option( LOAMWP_OPTION_REDIRECTS, $redirects, true );

	return rest_ensure_response(
		array(
			'ok'       => true,
			'created'  => $created,
			'redirect' => $row,
		)
	);
}

/**
 * DELETE /loam/v1/redirects — the site owner's undo. Idempotent.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function loamwp_rest_redirects_delete( WP_REST_Request $request ) {
	$from      = (string) $request['from'];
	$redirects = loamwp_get_redirects();
	$kept      = array();
	foreach ( $redirects as $existing ) {
		if ( $existing['from'] !== $from ) {
			$kept[] = $existing;
		}
	}
	$removed = count( $kept ) !== count( $redirects );
	if ( $removed ) {
		if ( empty( $kept ) ) {
			delete_option( LOAMWP_OPTION_REDIRECTS );
		} else {
			update_option( LOAMWP_OPTION_REDIRECTS, $kept, true );
		}
	}
	return rest_ensure_response(
		array(
			'ok'      => true,
			'removed' => $removed,
		)
	);
}
