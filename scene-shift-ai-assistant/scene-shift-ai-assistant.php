<?php
/**
 * Plugin Name:       Scene Shift AI Assistant
 * Plugin URI:        https://sceneshift.org/
 * Description:       Display your AI assistant phone number on a schedule and embed the Scene Shift web chat with brand-aware theming.
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Scene Shift
 * Author URI:        https://sceneshift.org/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       scene-shift-ai-assistant
 *
 * @package SceneShift\AiAssistant
 */

if (!defined('ABSPATH')) {
	exit;
}

define('SCENE_SHIFT_AI_ASSISTANT_VERSION', '0.1.0');
define('SCENE_SHIFT_AI_ASSISTANT_FILE', __FILE__);
define('SCENE_SHIFT_AI_ASSISTANT_DIR', plugin_dir_path(__FILE__));
define('SCENE_SHIFT_AI_ASSISTANT_URL', plugin_dir_url(__FILE__));
define('SCENE_SHIFT_AI_ASSISTANT_OPTION_KEY', 'scene_shift_ai_assistant_settings');
define('SCENE_SHIFT_AI_ASSISTANT_API_BASE', 'https://login.sceneshift.org');

require_once SCENE_SHIFT_AI_ASSISTANT_DIR . 'includes/class-portal-client.php';
require_once SCENE_SHIFT_AI_ASSISTANT_DIR . 'includes/class-settings-store.php';
require_once SCENE_SHIFT_AI_ASSISTANT_DIR . 'includes/class-renderer.php';
require_once SCENE_SHIFT_AI_ASSISTANT_DIR . 'includes/class-admin-page.php';
require_once SCENE_SHIFT_AI_ASSISTANT_DIR . 'includes/class-plugin.php';

\SceneShift\AiAssistant\Plugin::instance()->register();

register_activation_hook(__FILE__, [\SceneShift\AiAssistant\Plugin::class, 'on_activate']);
register_deactivation_hook(__FILE__, [\SceneShift\AiAssistant\Plugin::class, 'on_deactivate']);
register_uninstall_hook(__FILE__, [\SceneShift\AiAssistant\Plugin::class, 'on_uninstall']);
