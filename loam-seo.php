<?php
/**
 * Plugin Name:       Loam SEO
 * Description:       Companion to Loam's SEO Manager. Adds administrator-only REST endpoints for SEO title and description, canonical URL, JSON-LD schema, robots.txt rules and redirects, and outputs what an administrator approved in Loam.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Loam
 * Author URI:        https://getloam.co
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       loam-seo
 *
 * @package LoamSEO
 */

defined( 'ABSPATH' ) || exit;

define( 'LOAMWP_VERSION', '0.1.0' );
define( 'LOAMWP_DIR', plugin_dir_path( __FILE__ ) );

require_once LOAMWP_DIR . 'includes/helpers.php';
require_once LOAMWP_DIR . 'includes/meta.php';
require_once LOAMWP_DIR . 'includes/rest.php';
require_once LOAMWP_DIR . 'includes/frontend.php';
require_once LOAMWP_DIR . 'includes/redirects.php';
