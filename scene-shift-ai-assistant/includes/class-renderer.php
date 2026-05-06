<?php
/**
 * Frontend rendering for the WordPress plugin. Handles both the phone block
 * (shortcode + Gutenberg-friendly HTML output) and the floating chat widget
 * injection. Visitor-side rendering reads only from the cached config in
 * options — no live API calls per page view.
 *
 * @package SceneShift\AiAssistant
 */

namespace SceneShift\AiAssistant;

if (!defined('ABSPATH')) {
	exit;
}

final class Renderer {
	const PHONE_SHORTCODE = 'scene_shift_phone';
	const CHAT_SHORTCODE = 'scene_shift_chat';

	/** @var SettingsStore */
	private $settings;

	public function __construct(SettingsStore $settings) {
		$this->settings = $settings;
	}

	public function register_shortcodes(): void {
		add_shortcode(self::PHONE_SHORTCODE, [$this, 'render_phone_shortcode']);
		add_shortcode(self::CHAT_SHORTCODE, [$this, 'render_chat_shortcode']);
	}

	public function enqueue_assets(): void {
		$config = $this->settings->config();
		if (!is_array($config)) return;

		wp_register_script(
			'scene-shift-phone-switcher',
			SCENE_SHIFT_AI_ASSISTANT_URL . 'assets/phone-switcher.js',
			[],
			SCENE_SHIFT_AI_ASSISTANT_VERSION,
			true,
		);

		wp_register_style(
			'scene-shift-phone',
			SCENE_SHIFT_AI_ASSISTANT_URL . 'assets/phone.css',
			[],
			SCENE_SHIFT_AI_ASSISTANT_VERSION,
		);
	}

	/**
	 * @param array<string,mixed> $atts
	 */
	public function render_phone_shortcode($atts): string {
		$config = $this->settings->config();
		if (!is_array($config)) {
			return '';
		}

		$phone = isset($config['phone']) && is_array($config['phone']) ? $config['phone'] : [];
		$enabled = isset($phone['enabled']) ? (bool) $phone['enabled'] : true;
		if (!$enabled) return '';

		$ai_number = isset($config['assistantPhoneNumber']) ? (string) $config['assistantPhoneNumber'] : '';
		$alt_number = isset($phone['alternativeNumber']) ? (string) $phone['alternativeNumber'] : '';
		if ($ai_number === '' && $alt_number === '') {
			return '';
		}

		$ai_label = isset($phone['aiLabel']) ? (string) $phone['aiLabel'] : 'AI assistant';
		$alt_label = isset($phone['alternativeLabel']) ? (string) $phone['alternativeLabel'] : 'Live agent';

		$schedule = [
			'allDay' => isset($phone['allDay']) ? (bool) $phone['allDay'] : true,
			'startMinute' => isset($phone['startMinute']) ? (int) $phone['startMinute'] : 540,
			'endMinute' => isset($phone['endMinute']) ? (int) $phone['endMinute'] : 1080,
			'timezone' => isset($phone['timezone']) ? (string) $phone['timezone'] : 'UTC',
			'days' => isset($phone['days']) && is_array($phone['days']) ? array_map('intval', $phone['days']) : [],
			'aiNumber' => $ai_number,
			'alternativeNumber' => $alt_number,
			'aiLabel' => $ai_label,
			'alternativeLabel' => $alt_label,
		];

		$initial = $this->pick_initial_phone($schedule);
		if ($initial === null) {
			return '';
		}

		wp_enqueue_script('scene-shift-phone-switcher');
		wp_enqueue_style('scene-shift-phone');

		$class = isset($atts['class']) ? sanitize_html_class($atts['class']) : 'scene-shift-phone';
		$tel_href = 'tel:' . preg_replace('/[^0-9+]/', '', $initial['number']);
		$schedule_json = wp_json_encode($schedule);

		ob_start();
		?>
		<div class="<?php echo esc_attr($class); ?>" data-scene-shift-phone='<?php echo esc_attr($schedule_json); ?>'>
			<span class="scene-shift-phone__label" data-scene-shift-phone-label><?php echo esc_html($initial['label']); ?></span>
			<a class="scene-shift-phone__number" data-scene-shift-phone-link href="<?php echo esc_attr($tel_href); ?>">
				<span data-scene-shift-phone-number><?php echo esc_html($initial['number']); ?></span>
			</a>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * @param array<string,mixed> $atts
	 */
	public function render_chat_shortcode($atts): string {
		// The chat widget mounts itself globally via wp_footer; this shortcode
		// is just a placeholder anchor for documentation/marketing pages.
		return '<div class="scene-shift-chat-anchor" aria-hidden="true"></div>';
	}

	public function render_footer_chat(): void {
		$config = $this->settings->config();
		if (!is_array($config)) return;

		$chat = isset($config['chat']) && is_array($config['chat']) ? $config['chat'] : [];
		if (isset($chat['enabled']) && $chat['enabled'] === false) return;

		$assignment_id = isset($config['assignmentId']) ? (string) $config['assignmentId'] : '';
		$webchat_key = isset($config['publishableWebchatKey']) ? (string) $config['publishableWebchatKey'] : '';
		if ($assignment_id === '' || $webchat_key === '') return;

		$attrs = [
			'src' => esc_url(SCENE_SHIFT_AI_ASSISTANT_API_BASE . '/embed/widget.js'),
			'async' => 'async',
			'type' => 'text/javascript',
			'data-webchat-key' => $webchat_key,
			'data-assignment-id' => $assignment_id,
			'data-mode' => 'chat',
			'data-position' => isset($chat['position']) ? (string) $chat['position'] : 'bottom-right',
			'data-theme' => isset($chat['theme']) ? (string) $chat['theme'] : 'light',
			'data-radius' => isset($chat['radius']) ? (string) $chat['radius'] : 'medium',
			'data-main-label' => isset($chat['launcherLabel']) ? (string) $chat['launcherLabel'] : 'Chat with us',
			'data-empty-chat-message' => isset($chat['emptyMessage']) ? (string) $chat['emptyMessage'] : 'Ask a question to get started.',
			'data-accent-color' => isset($chat['accentColor']) ? (string) $chat['accentColor'] : '',
			'data-surface-color' => isset($chat['surfaceColor']) ? (string) $chat['surfaceColor'] : '',
			'data-text-color' => isset($chat['textColor']) ? (string) $chat['textColor'] : '',
			'data-user-bubble-color' => isset($chat['userBubbleColor']) ? (string) $chat['userBubbleColor'] : '',
			'data-launcher-color' => isset($chat['launcherColor']) ? (string) $chat['launcherColor'] : '',
			'data-source' => 'scene-shift-wp',
			'data-source-version' => SCENE_SHIFT_AI_ASSISTANT_VERSION,
		];

		echo '<script';
		foreach ($attrs as $key => $value) {
			if ($value === '' || $value === null) continue;
			if ($key === 'async') {
				echo ' async';
				continue;
			}
			echo ' ' . esc_attr($key) . '="' . esc_attr((string) $value) . '"';
		}
		echo "></script>\n";
	}

	/**
	 * Server-side preview of which phone number to render initially. The
	 * client-side `phone-switcher.js` re-evaluates the schedule once the page
	 * is hydrated to handle visitor timezone correctly.
	 *
	 * @param array{
	 *  allDay:bool,
	 *  startMinute:int,
	 *  endMinute:int,
	 *  timezone:string,
	 *  days:int[],
	 *  aiNumber:string,
	 *  alternativeNumber:string,
	 *  aiLabel:string,
	 *  alternativeLabel:string
	 * } $schedule
	 * @return array{number:string,label:string}|null
	 */
	private function pick_initial_phone(array $schedule): ?array {
		$ai = trim($schedule['aiNumber']);
		$alt = trim($schedule['alternativeNumber']);

		$show_ai = ($schedule['allDay'] && $ai !== '') || ($ai !== '' && $this->is_in_window($schedule));
		if ($show_ai) {
			return ['number' => $ai, 'label' => $schedule['aiLabel']];
		}
		if ($alt !== '') {
			return ['number' => $alt, 'label' => $schedule['alternativeLabel']];
		}
		return null;
	}

	/**
	 * @param array{startMinute:int,endMinute:int,timezone:string,days:int[]} $schedule
	 */
	private function is_in_window(array $schedule): bool {
		$timezone = $schedule['timezone'] !== '' ? $schedule['timezone'] : 'UTC';
		try {
			$now = new \DateTime('now', new \DateTimeZone($timezone));
		} catch (\Throwable $e) {
			$now = new \DateTime('now', new \DateTimeZone('UTC'));
		}
		$weekday = (int) $now->format('w');
		if (!empty($schedule['days']) && !in_array($weekday, $schedule['days'], true)) {
			return false;
		}
		$minute = ((int) $now->format('G') * 60) + (int) $now->format('i');
		$start = (int) $schedule['startMinute'];
		$end = (int) $schedule['endMinute'];
		if ($start === $end) return false;
		if ($start < $end) return $minute >= $start && $minute < $end;
		return $minute >= $start || $minute < $end;
	}
}
