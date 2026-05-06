=== Scene Shift AI Assistant ===
Contributors: sceneshift
Tags: ai, voice, chat, phone, customer support
Requires at least: 6.2
Tested up to: 6.7
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

A Scene Shift account is required to use this plugin. The plugin is free; the underlying AI assistant service is provided by Scene Shift under its own terms.

== Source code ==

The full, human-readable source for this plugin (including the version shipped to WordPress.org) is developed in the open at:
https://github.com/OIAdmin11/sceneshift-wordpress-plugin

No build step is required. The files distributed in the `.zip` are the same files committed to the repository.

== External services ==

This plugin connects to Scene Shift cloud APIs (https://login.sceneshift.org) to provide configuration sync and the website chat widget. Connecting is opt-in: nothing leaves your site until an administrator pastes a setup code and explicitly confirms data sharing in the connect form.

Service endpoints contacted by the plugin:

* `POST https://login.sceneshift.org/voice/wordpress-installs/exchange` — exchanges a one-time setup code for a plugin token.
* `GET  https://login.sceneshift.org/voice/wordpress-installs/{installId}` — reads the published plugin configuration.
* `PUT  https://login.sceneshift.org/voice/wordpress-installs/{installId}` — pushes saved settings to the portal.
* `GET  https://login.sceneshift.org/embed/widget.js` — loaded by the visitor's browser when the chat widget is enabled.
* `GET  https://login.sceneshift.org/embed/config/{assignmentId}?key={webchatKey}` — runtime configuration for the chat widget.

Data sent:

* During connect: the setup code and the site URL.
* During sync / settings save: install ID, plugin token, site URL, and the plugin settings payload (phone schedule and chat appearance).
* During visitor page loads (only when chat is enabled): the publishable web chat key and the assigned assistant ID, sent from the visitor's browser to load the widget.

When data is sent:

* Only after an administrator explicitly connects the plugin to a Scene Shift workspace.
* On manual sync, the scheduled twice-daily sync, settings saves, and visitor page loads where chat is enabled.

Service terms and policies:

* Terms of Use: https://sceneshift.org/terms
* Privacy Policy: https://sceneshift.org/privacy

== Privacy ==

This plugin stores the following data on the WordPress site, in the `wp_options` table under the option key `scene_shift_ai_assistant_settings` (not autoloaded):

* The Scene Shift install ID returned during connect.
* The site-scoped plugin token returned during connect. This token is only sent to Scene Shift over HTTPS as a Bearer header. Scene Shift stores only its SHA-256 hash; you can revoke it at any time from the portal.
* A cached copy of the plugin configuration (phone schedule, chat appearance, the assigned assistant phone number, and the publishable web chat key).
* Last-sync timestamp and the most recent sync error message, if any.

This plugin does not set cookies, does not collect any personal data from your visitors directly, and does not contact analytics services. Visitor interactions inside the embedded chat widget are handled by Scene Shift under the policies linked above.

To remove all stored plugin data, click "Disconnect" in Settings → Scene Shift, then deactivate and delete the plugin. Uninstalling the plugin removes the option key automatically.

== Installation ==

1. Upload the `scene-shift-ai-assistant` folder to `/wp-content/plugins/`, or upload the zip in WordPress admin (Plugins → Add New → Upload Plugin).
2. Activate "Scene Shift AI Assistant" from the Plugins screen.
3. Visit Settings → Scene Shift, then connect your account using a setup code generated in the Scene Shift portal.

== Frequently Asked Questions ==

= Where do I get a setup code? =

Open https://login.sceneshift.org, go to Install → WordPress, and click "Generate plugin code".

= Does the plugin store my Scene Shift password? =

No. The plugin only stores a site-scoped plugin token. The token is hashed on the Scene Shift side; you can revoke it at any time from the portal.

= How do I add the phone number to a page? =

Use the shortcode `[scene_shift_phone]`. It will pick the AI number during the configured window and the alternative number outside it.

= Does the chat widget load on every page? =

Only when "Show chat bubble on every page" is enabled in Settings → Scene Shift. You can turn it off at any time without disconnecting.

= How do I uninstall completely? =

Click "Disconnect" in Settings → Scene Shift to clear the plugin token, then deactivate and delete the plugin from the Plugins screen. The plugin's `uninstall.php` removes the stored option and any scheduled cron events.

== Screenshots ==

1. Settings → Scene Shift connect form. Paste a setup code generated in the Scene Shift portal and confirm consent to start syncing.
2. Connected view: install metadata, plugin-token prefix + last 4, last sync timestamp, and Sync / Disconnect actions.
3. Phone display section: schedule window, AI label, alternative human-line number, and the `[scene_shift_phone]` shortcode helper.
4. Chat appearance section: theme, position, corner radius, and the five color pickers used for custom theming.
5. Live web chat bubble rendered in the corner of a public WordPress page once the chat is enabled.

== Changelog ==

= 0.1.0 =
* Initial release: scheduled phone display, custom-themed Scene Shift web chat, and one-time setup-code connection.
