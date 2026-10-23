=== Loam SEO ===
Contributors: acrelane
Tags: seo
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lets Loam's SEO Manager apply the SEO fixes an administrator approved: titles, descriptions, canonical URLs, schema, robots.txt and redirects.

== Description ==

Loam's SEO Manager audits your site and proposes fixes. You approve each fix in Loam. WordPress core has no REST API for robots.txt, structured data (JSON-LD), canonical URLs, redirects or SEO titles and descriptions, so without this plugin Loam can only hand you those fixes to apply by hand.

This plugin adds that missing surface, and nothing else:

* It adds REST API endpoints under `/wp-json/loam/v1/`. Every endpoint requires an administrator account (the `manage_options` capability). Loam connects with an application password that you create for an administrator.
* It adds one read-only field, `loam_seo` (SEO title and description), to published posts and pages in the REST API, for sites that render WordPress content on another front end.
* It outputs only what an administrator approved in Loam: the SEO title, meta description, canonical link and JSON-LD for a post or page, the robots.txt rules, and redirects.
* When Yoast SEO or Rank Math is active, SEO titles, descriptions and canonical URLs are written into that plugin's own fields, so your SEO plugin stays the single source of those tags.

The plugin has no settings screen, makes no outbound requests, loads no scripts or styles, and collects no data.

= What it changes on your site =

* SEO title, meta description, canonical link: on single posts and pages, only when no SEO plugin is active.
* JSON-LD: one `application/ld+json` script on single posts and pages that have one.
* robots.txt: the stored rules replace the generated robots.txt. If you tick "Discourage search engines from indexing this site", WordPress's own robots.txt always wins.
* Redirects: exact-path 301 or 302 redirects to pages on your own site. The homepage, admin, login and REST paths can never be redirected.

= Undo =

Every change can be reversed. Send an empty value to the same endpoint to clear a title, description, canonical, schema or the robots.txt rules. Remove a redirect with `DELETE /wp-json/loam/v1/redirects`. Deactivating the plugin stops all output at once; deleting it removes everything it stored.

== Installation ==

1. Install and activate the plugin, or let Loam's SEO Manager install it when you connect your site.
2. In Users → Profile, create an application password for an administrator account.
3. Connect your site in Loam with that username and application password.

== Frequently Asked Questions ==

= Does it work with Yoast SEO or Rank Math? =

Yes. With either one active, titles, descriptions and canonical URLs are written into that plugin's fields and that plugin outputs them.

= Why did my robots.txt not change? =

WordPress only serves robots.txt when there is no physical `robots.txt` file in the site root, the site is installed at the domain root, and search engines are not discouraged. The endpoint refuses to store rules in any other case, and says why.

== Changelog ==

= 0.1.0 =
* First release: status, robots.txt, SEO title and description, canonical, JSON-LD and redirect endpoints.
