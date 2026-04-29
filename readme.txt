=== Content Expiry Scheduler ===
Contributors: Himanshu Sindhal
Tags: expiry, schedule, unpublish, posts, content
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Set an expiry date on any post, page, or custom post type. Auto-draft,
redirect, or show a custom message when content expires.

== Description ==

Content Expiry Scheduler lets you set an expiry date and time on any
post, page, or custom post type. When the expiry time is reached, the
plugin automatically takes one of three actions you choose per post:

* Auto-draft – quietly unpublish the content
* Redirect – send visitors to another URL (301)
* Show a message – replace post content with a custom notice

Key features:
* Works with all public post types including custom post types
* Choose which post types show the expiry meta box (Settings page)
* Per-post action override: each post can have its own expiry action
* Expiry log with one-click re-publish (undo)
* Zero external dependencies — uses only WordPress core APIs
* Works with both Classic Editor and Block Editor (Gutenberg)
* No data sent externally. No tracking. No accounts required.

== Installation ==

1. Upload the plugin folder to /wp-content/plugins/
2. Activate through the Plugins menu in WordPress
3. Go to Settings → Content Expiry to choose which post types to enable
4. Edit any post — find the Content Expiry meta box and set your expiry date

== Frequently Asked Questions ==

= Does this work with custom post types? =
Yes. All registered public post types are detected automatically and
shown in Settings.

= What happens if I don't set a redirect URL? =
Falls back to the global redirect URL in Settings. If that's also empty,
content is auto-drafted instead.

= Will this slow down my site? =
No. Expiry checks run via WP-Cron in the background, not on every page load.

= Is data removed if I uninstall the plugin? =
Yes. All plugin options and post meta are removed on uninstall via uninstall.php.

== Screenshots ==

1. Settings page — choose which post types to enable
2. Per-post meta box — set expiry date and action
3. Expiry log — view and undo expired content

== Changelog ==

= 1.0.0 =
* Initial release

== Upgrade Notice ==

= 1.0.0 =
Initial release.
