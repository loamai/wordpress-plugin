# Loam SEO

The WordPress companion plugin for [Loam](https://getloam.co)'s SEO Manager.

With it, the SEO fixes you approve in Loam go live on your WordPress site:
SEO titles and descriptions, canonical URLs, JSON-LD schema, robots.txt rules
and redirects. Without it, Loam still publishes new pages, and those fixes
arrive as drafts you apply by hand.

## Install

1. Download **[loam-seo.zip](https://github.com/loamai/wordpress-plugin/releases/latest/download/loam-seo.zip)** (the latest release).
2. In WordPress, go to **Plugins → Add New → Upload Plugin**, choose the file and press **Install Now**.
3. Go back to Loam and press **Try again** on the plugin step. Loam activates the plugin for you.

If your SEO Manager is already running, you do not need step 3: Loam finds the
plugin the next time it publishes.

Loam SEO is waiting for review on WordPress.org. When it is listed there, Loam
installs it for you and this download is no longer needed.

## Requirements

- WordPress 6.0 or later, PHP 7.4 or later
- Loam connected with an administrator's
  [application password](https://wordpress.org/documentation/article/application-passwords/)

## What it does, and what it does not

- Adds REST endpoints under `/wp-json/loam/v1/`. Only an administrator can use them.
- Writes SEO titles, descriptions and canonical URLs to Yoast SEO or Rank Math
  when one of them is active, and prints them itself when neither is.
- Makes no outbound requests, adds no tracking, and has no settings screen.
- Uninstalling it deletes everything it stored.

## Versions

Every release is built from this repository by
[`.github/workflows/release.yml`](.github/workflows/release.yml). The zip's top
folder is `loam-seo/`, the folder name WordPress expects.

## Licence

GPLv2 or later. See [LICENSE](LICENSE).
