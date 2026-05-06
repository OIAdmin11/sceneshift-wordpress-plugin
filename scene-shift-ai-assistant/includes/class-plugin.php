<?php
/**
 * Plugin bootstrap. Wires up admin pages, shortcodes, and the public-facing
 * footer chat widget. Keeps the entry file thin so each concern has its own
 * class and is independently unit-testable.
 *
 * @package SceneShift\AiAssistant
 */

namespace SceneShift\AiAssistant;

if (!defined('ABSPATH')) {
	exit;
}

final class Plugin {
	/** @var Plugin|null */
	private static $instance = null;

	/** @var SettingsStore */
	private $settings;

	/** @var PortalClient */
	private $portal;

	/** @var Renderer */
	private $renderer;

	/** @var AdminPage */
	private $admin;

	public static function instance(): Plugin {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->settings = new SettingsStore(SCENE_SHIFT_AI_ASSISTANT_OPTION_KEY);
		$this->portal = new PortalClient(SCENE_SHIFT_AI_ASSISTANT_API_BASE);
		$this->renderer = new Renderer($this->settings);
		$this->admin = new AdminPage($this->settings, $this->portal, $this->renderer);
	}

	public function register(): void {
		add_action('init', [$this->renderer, 'register_shortcodes']);
		add_action('wp_enqueue_scripts', [$this->renderer, 'enqueue_assets']);
		add_action('wp_footer', [$this->renderer, 'render_footer_chat'], 99);

		if (is_admin()) {
			add_action('admin_menu', [$this->admin, 'register_menu']);
			add_action('admin_init', [$this->admin, 'register_settings']);
			add_action('admin_enqueue_scripts', [$this->admin, 'enqueue_assets']);
			add_action('admin_post_scene_shift_save_settings', [$this->admin, 'handle_save']);
			add_action('admin_post_scene_shift_connect', [$this->admin, 'handle_connect']);
			add_action('admin_post_scene_shift_disconnect', [$this->admin, 'handle_disconnect']);
			add_action('admin_post_scene_shift_sync', [$this->admin, 'handle_sync']);
		}

		add_action('scene_shift_refresh_config', [$this->admin, 'sync_config_silently']);
	}

	public static function on_activate(): void {
		if (!wp_next_scheduled('scene_shift_refresh_config')) {
			wp_schedule_event(time() + HOUR_IN_SECONDS, 'twicedaily', 'scene_shift_refresh_config');
		}
	}

	public static function on_deactivate(): void {
		$timestamp = wp_next_scheduled('scene_shift_refresh_config');
		if ($timestamp) {
			wp_unschedule_event($timestamp, 'scene_shift_refresh_config');
		}
	}

	public static function on_uninstall(): void {
		delete_option(SCENE_SHIFT_AI_ASSISTANT_OPTION_KEY);
		$timestamp = wp_next_scheduled('scene_shift_refresh_config');
		if ($timestamp) {
			wp_unschedule_event($timestamp, 'scene_shift_refresh_config');
		}
	}
}
