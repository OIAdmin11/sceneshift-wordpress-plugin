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
$timestamp = wp_next_scheduled('scene_shift_refresh_config');
if ($timestamp) {
	wp_unschedule_event($timestamp, 'scene_shift_refresh_config');
}
