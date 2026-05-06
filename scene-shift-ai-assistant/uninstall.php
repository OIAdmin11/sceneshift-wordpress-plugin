<?php
/**
 * Uninstall hook — removes the option blob so reinstalling the plugin starts
 * from a clean slate. The remote install record is *not* deleted automatically;
 * use the portal to revoke it if you want to fully detach the site.
 *
 * @package SceneShift\AiAssistant
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

delete_option('scene_shift_ai_assistant_settings');

wp_clear_scheduled_hook('scene_shift_refresh_config');

// Best-effort cleanup of per-user notice transients. We can't enumerate all
// users efficiently here without risking timeouts on large sites, so we just
// clear the timeout entries for currently logged-in admins; orphaned
// transients (if any) self-expire after MINUTE_IN_SECONDS anyway.
$current_user_id = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
if ($current_user_id > 0) {
	delete_transient('scene_shift_notice_' . $current_user_id);
}
