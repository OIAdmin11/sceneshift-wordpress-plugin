=== Scene Shift AI Assistant ===
Contributors: sceneshift
Tags: ai, voice, chat, vapi, phone
Requires at least: 6.2
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display your AI assistant phone number on a schedule and embed the Scene Shift web chat with brand-aware theming.

== Description ==

Scene Shift AI Assistant connects a WordPress site to a Scene Shift workspace. Once connected, you can:

* Show your AI assistant phone number during business hours, then fall back to a human line outside that window.
* Render the same number as a shortcode `[scene_shift_phone]` anywhere on your site.
* Mount the Scene Shift web chat widget on every page, with light/dark/custom theming.

Connection is one-time. Generate a setup code in the Scene Shift portal, paste it into the plugin, and the plugin receives a long-lived token. Settings round-trip to the portal so support can help with troubleshooting.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or upload the zip in WordPress admin.
2. Activate "Scene Shift AI Assistant" from Plugins.
3. Visit Settings → Scene Shift, then connect your account using a setup code from the portal.

== Frequently Asked Questions ==

= Where do I get a setup code? =

Open https://login.sceneshift.org, go to Install → WordPress, and click "Generate plugin code".

= Does the plugin store my Scene Shift password? =

No. The plugin only stores a site-scoped plugin token. The token is hashed on the Scene Shift side; you can revoke it at any time from the portal.

== Changelog ==

= 0.1.0 =
* Initial release: scheduled phone display, custom-themed Vapi web chat, and one-time setup-code connection.
