<?php
/**
 * Persistent settings store for the WordPress plugin. Keeps the plugin token
 * out of autoloaded options and provides typed getters/setters for the rest of
 * the configuration. Only this class touches `wp_options` directly.
 *
 * @package SceneShift\AiAssistant
 */

namespace SceneShift\AiAssistant;

if (!defined('ABSPATH')) {
	exit;
}

final class SettingsStore {
	/** @var string */
	private $option_key;

	/** @var array<string, mixed>|null */
	private $cache = null;

	public function __construct(string $option_key) {
		$this->option_key = $option_key;
	}

	/**
	 * @return array{
	 *  install_id:string|null,
	 *  plugin_token:string|null,
	 *  token_prefix:string|null,
	 *  token_last_four:string|null,
	 *  config:array<string,mixed>|null,
	 *  last_synced_at:string|null,
	 *  last_sync_error:string|null
	 * }
	 */
	public function all(): array {
		if ($this->cache === null) {
			$raw = get_option($this->option_key, []);
			$this->cache = is_array($raw) ? $raw : [];
		}
		return wp_parse_args($this->cache, [
			'install_id' => null,
			'plugin_token' => null,
			'token_prefix' => null,
			'token_last_four' => null,
			'config' => null,
			'last_synced_at' => null,
			'last_sync_error' => null,
		]);
	}

	public function set(array $values): void {
		$current = $this->all();
		$next = array_merge($current, $values);
		$this->cache = $next;
		// `false` for autoload — the plugin token should not be loaded on
		// every WordPress request just because the visitor is hitting any page.
		update_option($this->option_key, $next, false);
	}

	public function clear(): void {
		$this->cache = [];
		delete_option($this->option_key);
	}

	public function install_id(): ?string {
		$id = $this->all()['install_id'];
		return is_string($id) && $id !== '' ? $id : null;
	}

	public function plugin_token(): ?string {
		$token = $this->all()['plugin_token'];
		return is_string($token) && $token !== '' ? $token : null;
	}

	public function config(): ?array {
		$config = $this->all()['config'];
		return is_array($config) ? $config : null;
	}

	public function update_config(array $config, ?string $error = null): void {
		$this->set([
			'config' => $config,
			'last_synced_at' => current_time('mysql'),
			'last_sync_error' => $error,
		]);
	}

	public function record_sync_error(string $message): void {
		$this->set([
			'last_sync_error' => $message,
		]);
	}

	public function is_connected(): bool {
		return $this->install_id() !== null && $this->plugin_token() !== null;
	}
}
