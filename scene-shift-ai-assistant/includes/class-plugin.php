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

	const CRON_HOOK = 'scene_shift_refresh_config';

	public function register(): void {
		add_action('init', [$this->renderer, 'register_shortcodes']);
		add_action('init', [$this, 'load_textdomain']);
		add_action('wp_enqueue_scripts', [$this->renderer, 'enqueue_assets']);
		add_action('wp_footer', [$this->renderer, 'render_footer_chat'], 99);

		if (is_admin()) {
			add_action('admin_menu', [$this->admin, 'register_menu']);
			add_action('admin_init', [$this->admin, 'register_settings']);
			add_action('admin_init', [$this, 'register_privacy_policy_content']);
			add_action('admin_enqueue_scripts', [$this->admin, 'enqueue_assets']);
			add_action('admin_post_scene_shift_save_settings', [$this->admin, 'handle_save']);
			add_action('admin_post_scene_shift_connect', [$this->admin, 'handle_connect']);
			add_action('admin_post_scene_shift_disconnect', [$this->admin, 'handle_disconnect']);
			add_action('admin_post_scene_shift_sync', [$this->admin, 'handle_sync']);
		}

		add_action(self::CRON_HOOK, [$this->admin, 'sync_config_silently']);
	}

	public function load_textdomain(): void {
		load_plugin_textdomain(
			'scene-shift-ai-assistant',
			false,
			dirname(plugin_basename(SCENE_SHIFT_AI_ASSISTANT_FILE)) . '/languages'
		);
	}

	/**
	 * Register suggested text that site administrators can paste into their
	 * own privacy policy. WordPress collects these from every active plugin
	 * and surfaces them on Settings → Privacy → Policy Guide.
	 *
	 * Called on `admin_init`. We disclose:
	 *  - what the plugin stores locally (install ID + plugin token + cached
	 *    config — all admin-side, no visitor PII);
	 *  - what the embedded chat widget transmits to Scene Shift on the
	 *    visitor's behalf (chat input + a publishable web chat key).
	 */
	public function register_privacy_policy_content(): void {
		if (!function_exists('wp_add_privacy_policy_content')) {
			return;
		}

		$content = sprintf(
			/* translators: 1: plugin name. */
			__(
				'%1$s connects this site to a Scene Shift workspace. The plugin itself stores a Scene Shift install ID, a site-scoped plugin token, and a cached copy of your plugin configuration in the WordPress options table. None of these contain personal data about your site visitors.

When you enable the chat widget on a public page, the visitor\'s browser loads the Scene Shift chat runtime and (if the visitor opens the widget and sends a message) transmits their messages to Scene Shift over HTTPS for AI processing and conversation history. The visitor\'s phone number is recorded only if they choose to share it inside the conversation.

Data handling is governed by the Scene Shift Privacy Policy: https://sceneshift.org/privacy

To remove all stored plugin data from this site, click "Disconnect" on Settings → Scene Shift, then deactivate and delete the plugin. To revoke or delete data on the Scene Shift side, contact privacy@sceneshift.org or use the data-export and deletion tools in the Scene Shift portal.',
				'scene-shift-ai-assistant'
			),
			'Scene Shift AI Assistant'
		);

		wp_add_privacy_policy_content(
			'Scene Shift AI Assistant',
			wp_kses_post(wpautop($content, false))
		);
	}

	/**
	 * Schedule the twice-daily config sync. Idempotent — safe to call from
	 * the connect handler on every reconnect. Activation does *not* schedule
	 * the event so we never contact the portal before the admin opts in.
	 */
	public static function schedule_sync(): void {
		if (!wp_next_scheduled(self::CRON_HOOK)) {
			wp_schedule_event(time() + HOUR_IN_SECONDS, 'twicedaily', self::CRON_HOOK);
		}
	}

	public static function unschedule_sync(): void {
		$timestamp = wp_next_scheduled(self::CRON_HOOK);
		if ($timestamp) {
			wp_unschedule_event($timestamp, self::CRON_HOOK);
		}
		wp_clear_scheduled_hook(self::CRON_HOOK);
	}

	public static function on_activate(): void {
		// Intentionally no-op: scheduling and remote calls only happen after
		// an administrator pastes a setup code and confirms the consent
		// checkbox in Settings → Scene Shift.
	}

	public static function on_deactivate(): void {
		self::unschedule_sync();
	}

	public static function on_uninstall(): void {
		delete_option(SCENE_SHIFT_AI_ASSISTANT_OPTION_KEY);
		self::unschedule_sync();
	}
}
