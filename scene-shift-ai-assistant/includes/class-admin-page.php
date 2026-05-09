<?php
/**
 * WordPress admin page. Three sections:
 *  1. Connect: paste a setup code from the portal to receive a plugin token.
 *  2. Phone display: pick AI vs alternative number and the schedule window.
 *  3. Chat appearance: theme, accent, position, launcher copy.
 *
 * All write actions go through `admin-post.php` with nonces. The plugin token
 * is stored only as a server-side option and never echoed back to the page.
 *
 * @package SceneShift\AiAssistant
 */

namespace SceneShift\AiAssistant;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AdminPage {
	const MENU_SLUG = 'scene-shift-ai-assistant';
	const NOTICE_TRANSIENT_PREFIX = 'scene_shift_notice_';

	/** @var SettingsStore */
	private $settings;

	/** @var PortalClient */
	private $portal;

	/** @var Renderer */
	private $renderer;

	public function __construct( SettingsStore $settings, PortalClient $portal, Renderer $renderer ) {
		$this->settings = $settings;
		$this->portal = $portal;
		$this->renderer = $renderer;
	}

	/**
	 * Per-form, per-action nonce action string. Using a unique action per
	 * form prevents a nonce captured for one action (e.g. "Sync now") from
	 * being replayed against another (e.g. "Disconnect").
	 */
	private static function nonce_action( string $form_action ): string {
		return 'scene_shift_' . $form_action;
	}

	public function register_menu(): void {
		add_options_page(
			__( 'Scene Shift AI Assistant', 'scene-shift-ai-assistant' ),
			__( 'Scene Shift', 'scene-shift-ai-assistant' ),
			'manage_options',
			self::MENU_SLUG,
			[ $this, 'render_page' ],
		);
	}

	public function register_settings(): void {
		// All settings persist via admin_post handlers; we don't use
		// register_setting / Settings API forms because the plugin token must
		// never round-trip through the rendered HTML.
	}

	public function enqueue_assets( $hook ): void {
		if ($hook !== 'settings_page_' . self::MENU_SLUG) return;
		wp_enqueue_style(
			'scene-shift-admin',
			SCENE_SHIFT_AI_ASSISTANT_URL . 'assets/admin.css',
			[],
			SCENE_SHIFT_AI_ASSISTANT_VERSION,
		);
		wp_enqueue_script(
			'scene-shift-admin',
			SCENE_SHIFT_AI_ASSISTANT_URL . 'assets/admin.js',
			[],
			SCENE_SHIFT_AI_ASSISTANT_VERSION,
			true,
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' )) return;

		$settings = $this->settings->all();
		$config = $this->settings->config();
		$is_connected = $this->settings->is_connected();
		$site_url = home_url();
		$last_synced = isset( $settings['last_synced_at'] ) ? (string) $settings['last_synced_at'] : '';
		$last_error = isset( $settings['last_sync_error'] ) ? (string) $settings['last_sync_error'] : '';
		$status_messages = $this->collect_admin_notices();

		?>
		<div class="wrap scene-shift-admin">
			<h1><?php esc_html_e( 'Scene Shift AI Assistant', 'scene-shift-ai-assistant' ); ?></h1>
			<p class="scene-shift-admin__lede"><?php esc_html_e( 'Show your AI phone number on a schedule, and add the Scene Shift web chat widget to your site. Connect once, then control everything from this page.', 'scene-shift-ai-assistant' ); ?></p>

			<?php foreach ( $status_messages as $notice ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice['kind'] ); ?>">
					<p><?php echo esc_html( $notice['message'] ); ?></p>
				</div>
			<?php endforeach; ?>

			<?php if ( ! $is_connected ) : ?>
				<?php $this->render_connect_form(); ?>
			<?php else : ?>
				<?php $this->render_connected_view( $settings, $config, $site_url, $last_synced, $last_error ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_connect_form(): void {
		?>
		<section class="scene-shift-card">
			<h2><?php esc_html_e( 'Connect your Scene Shift account', 'scene-shift-ai-assistant' ); ?></h2>
			<ol class="scene-shift-steps">
				<li>
				<?php
					echo wp_kses(
						sprintf(
							/* translators: %s: portal URL wrapped in a <code> element */
							__( 'Open the WordPress install page in the Scene Shift portal at %s.', 'scene-shift-ai-assistant' ),
							'<code>' . esc_html( rtrim( SCENE_SHIFT_AI_ASSISTANT_PORTAL_BASE, '/' ) . '/install/wordpress' ) . '</code>'
						),
						[ 'code' => [] ]
					);
				?>
					</li>
				<li><?php esc_html_e( 'Click "Generate plugin code" to issue a one-time setup code.', 'scene-shift-ai-assistant' ); ?></li>
				<li><?php esc_html_e( 'Paste the code below. The code expires after 15 minutes.', 'scene-shift-ai-assistant' ); ?></li>
			</ol>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="scene-shift-form">
				<?php wp_nonce_field( self::nonce_action( 'connect' ) ); ?>
				<input type="hidden" name="action" value="scene_shift_connect">
				<label class="scene-shift-field">
					<span class="scene-shift-field__label"><?php esc_html_e( 'Setup code', 'scene-shift-ai-assistant' ); ?></span>
					<input type="text" name="setup_code" autocomplete="off" required pattern="sswp_setup_[A-Za-z0-9_-]+">
				</label>
				<label class="scene-shift-field scene-shift-field--check">
					<input type="checkbox" name="consent_remote" value="1" required>
					<span><?php esc_html_e( 'I consent to this plugin connecting to Scene Shift services to exchange setup codes and sync plugin configuration.', 'scene-shift-ai-assistant' ); ?></span>
				</label>
				<p class="scene-shift-help">
					<?php
					printf(
						/* translators: 1: privacy policy URL, 2: terms URL. */
						wp_kses_post( __( 'Data handling details: <a href="%1$s" target="_blank" rel="noopener noreferrer">Privacy Policy</a> and <a href="%2$s" target="_blank" rel="noopener noreferrer">Terms of Use</a>.', 'scene-shift-ai-assistant' ) ),
						esc_url( 'https://sceneshift.org/privacy' ),
						esc_url( 'https://sceneshift.org/terms' ),
					);
					?>
				</p>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Connect site', 'scene-shift-ai-assistant' ); ?></button>
			</form>
		</section>
		<?php
	}

	private function render_connected_view( array $settings, ?array $config, string $site_url, string $last_synced, string $last_error ): void {
		$config = is_array( $config ) ? $config : [];
		$phone = isset( $config['phone'] ) && is_array( $config['phone'] ) ? $config['phone'] : [];
		$chat = isset( $config['chat'] ) && is_array( $config['chat'] ) ? $config['chat'] : [];
		$assistant_phone = isset( $config['assistantPhoneNumber'] ) ? (string) $config['assistantPhoneNumber'] : '';
		$site_name = isset( $config['siteName'] ) ? (string) $config['siteName'] : '';
		$assignment_id = isset( $config['assignmentId'] ) ? (string) $config['assignmentId'] : '';
		$webchat_key = isset( $config['publishableWebchatKey'] ) ? (string) $config['publishableWebchatKey'] : '';
		$token_prefix = isset( $settings['token_prefix'] ) ? (string) $settings['token_prefix'] : '';
		$token_last_four = isset( $settings['token_last_four'] ) ? (string) $settings['token_last_four'] : '';
		?>
		<section class="scene-shift-card">
			<h2><?php esc_html_e( 'Connected', 'scene-shift-ai-assistant' ); ?></h2>
			<dl class="scene-shift-info-grid">
				<dt><?php esc_html_e( 'Site', 'scene-shift-ai-assistant' ); ?></dt>
				<dd><?php echo esc_html( $site_name ?: $site_url ); ?></dd>
				<dt><?php esc_html_e( 'Plugin token', 'scene-shift-ai-assistant' ); ?></dt>
				<dd><code><?php echo esc_html( $token_prefix ); ?>&hellip;<?php echo esc_html( $token_last_four ); ?></code></dd>
				<dt><?php esc_html_e( 'Assistant phone', 'scene-shift-ai-assistant' ); ?></dt>
				<dd><?php echo esc_html( $assistant_phone ?: __( 'Not assigned yet', 'scene-shift-ai-assistant' ) ); ?></dd>
				<dt><?php esc_html_e( 'Web chat key', 'scene-shift-ai-assistant' ); ?></dt>
				<dd>
					<?php if ( $webchat_key !== '' ) : ?>
						<?php echo esc_html( substr( $webchat_key, 0, 14 ) . '…' ); ?>
					<?php else : ?>
						<?php esc_html_e( 'Not assigned yet', 'scene-shift-ai-assistant' ); ?>
					<?php endif; ?>
				</dd>
				<dt><?php esc_html_e( 'Last synced', 'scene-shift-ai-assistant' ); ?></dt>
				<dd>
					<?php if ( $last_synced !== '' ) : ?>
						<?php echo esc_html( $last_synced ); ?>
					<?php else : ?>
						<?php esc_html_e( 'Never', 'scene-shift-ai-assistant' ); ?>
					<?php endif; ?>
				</dd>
				<?php if ( $last_error !== '' ) : ?>
					<dt><?php esc_html_e( 'Last error', 'scene-shift-ai-assistant' ); ?></dt>
					<dd><span class="scene-shift-error"><?php echo esc_html( $last_error ); ?></span></dd>
				<?php endif; ?>
			</dl>
			<div class="scene-shift-row">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="scene-shift-inline-form">
					<?php wp_nonce_field( self::nonce_action( 'sync' ) ); ?>
					<input type="hidden" name="action" value="scene_shift_sync">
					<button type="submit" class="button"><?php esc_html_e( 'Sync now', 'scene-shift-ai-assistant' ); ?></button>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="scene-shift-inline-form" onsubmit="return confirm('<?php echo esc_js( __( 'Disconnect this WordPress site from Scene Shift?', 'scene-shift-ai-assistant' ) ); ?>');">
					<?php wp_nonce_field( self::nonce_action( 'disconnect' ) ); ?>
					<input type="hidden" name="action" value="scene_shift_disconnect">
					<button type="submit" class="button button-secondary"><?php esc_html_e( 'Disconnect', 'scene-shift-ai-assistant' ); ?></button>
				</form>
			</div>
		</section>

		<?php
		if ( $assignment_id === '' || $webchat_key === '' ) {
			?>
			<section class="scene-shift-card scene-shift-card--warning">
				<h2><?php esc_html_e( 'Finish setup in the portal', 'scene-shift-ai-assistant' ); ?></h2>
				<p>
					<?php
					if ( $assignment_id === '' ) {
						esc_html_e( 'Pick a Vapi assistant for this WordPress install in the portal so visitors see the AI phone number.', 'scene-shift-ai-assistant' );
					} else {
						esc_html_e( 'Add an active web chat key for this customer in the portal so the chat widget can mount.', 'scene-shift-ai-assistant' );
					}
					?>
				</p>
			</section>
			<?php
		}
		?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="scene-shift-form">
			<?php wp_nonce_field( self::nonce_action( 'save_settings' ) ); ?>
			<input type="hidden" name="action" value="scene_shift_save_settings">

			<section class="scene-shift-card">
				<h2><?php esc_html_e( 'Phone display', 'scene-shift-ai-assistant' ); ?></h2>
				<p class="scene-shift-card__lede"><?php esc_html_e( 'Show the AI assistant number during business hours, then fall back to a human line outside the window.', 'scene-shift-ai-assistant' ); ?></p>

				<label class="scene-shift-field scene-shift-field--check">
					<input type="checkbox" name="phone[enabled]" value="1" <?php checked( ! isset( $phone['enabled'] ) || $phone['enabled'] ); ?>>
					<span><?php esc_html_e( 'Enable phone block', 'scene-shift-ai-assistant' ); ?></span>
				</label>

				<label class="scene-shift-field scene-shift-field--check">
					<input type="checkbox" name="phone[allDay]" value="1" <?php checked( ! isset( $phone['allDay'] ) || $phone['allDay'] ); ?>>
					<span><?php esc_html_e( 'Show AI number all day', 'scene-shift-ai-assistant' ); ?></span>
				</label>

				<div class="scene-shift-row">
					<label class="scene-shift-field">
						<span class="scene-shift-field__label"><?php esc_html_e( 'AI start time', 'scene-shift-ai-assistant' ); ?></span>
						<input type="time" name="phone[startTime]" value="<?php echo esc_attr( $this->minutes_to_time( isset( $phone['startMinute'] ) ? (int) $phone['startMinute'] : 540 ) ); ?>">
					</label>
					<label class="scene-shift-field">
						<span class="scene-shift-field__label"><?php esc_html_e( 'AI end time', 'scene-shift-ai-assistant' ); ?></span>
						<input type="time" name="phone[endTime]" value="<?php echo esc_attr( $this->minutes_to_time( isset( $phone['endMinute'] ) ? (int) $phone['endMinute'] : 1080 ) ); ?>">
					</label>
					<label class="scene-shift-field">
						<span class="scene-shift-field__label"><?php esc_html_e( 'Timezone', 'scene-shift-ai-assistant' ); ?></span>
						<input type="text" name="phone[timezone]" value="<?php echo esc_attr( isset( $phone['timezone'] ) ? (string) $phone['timezone'] : wp_timezone_string() ); ?>" placeholder="America/New_York">
					</label>
				</div>

				<label class="scene-shift-field">
					<span class="scene-shift-field__label"><?php esc_html_e( 'AI label', 'scene-shift-ai-assistant' ); ?></span>
					<input type="text" name="phone[aiLabel]" value="<?php echo esc_attr( isset( $phone['aiLabel'] ) ? (string) $phone['aiLabel'] : 'AI assistant' ); ?>">
				</label>

				<label class="scene-shift-field">
					<span class="scene-shift-field__label"><?php esc_html_e( 'Alternative phone number (shown outside AI hours)', 'scene-shift-ai-assistant' ); ?></span>
					<input type="text" name="phone[alternativeNumber]" value="<?php echo esc_attr( isset( $phone['alternativeNumber'] ) && is_string( $phone['alternativeNumber'] ) ? $phone['alternativeNumber'] : '' ); ?>" placeholder="+1 555 123 4567">
				</label>

				<label class="scene-shift-field">
					<span class="scene-shift-field__label"><?php esc_html_e( 'Alternative label', 'scene-shift-ai-assistant' ); ?></span>
					<input type="text" name="phone[alternativeLabel]" value="<?php echo esc_attr( isset( $phone['alternativeLabel'] ) ? (string) $phone['alternativeLabel'] : 'Live agent' ); ?>">
				</label>

				<p class="scene-shift-help">
				<?php
					echo wp_kses(
						sprintf(
							/* translators: %s: shortcode example wrapped in a <code> element */
							__( 'Add the phone block anywhere on your site with the shortcode: %s', 'scene-shift-ai-assistant' ),
							'<code>[scene_shift_phone]</code>'
						),
						[ 'code' => [] ]
					);
				?>
											</p>
			</section>

			<section class="scene-shift-card">
				<h2><?php esc_html_e( 'Chat appearance', 'scene-shift-ai-assistant' ); ?></h2>

				<label class="scene-shift-field scene-shift-field--check">
					<input type="checkbox" name="chat[enabled]" value="1" <?php checked( ! isset( $chat['enabled'] ) || $chat['enabled'] ); ?>>
					<span><?php esc_html_e( 'Show chat bubble on every page', 'scene-shift-ai-assistant' ); ?></span>
				</label>

				<div class="scene-shift-row">
					<label class="scene-shift-field">
						<span class="scene-shift-field__label"><?php esc_html_e( 'Position', 'scene-shift-ai-assistant' ); ?></span>
						<select name="chat[position]">
							<option value="bottom-right" <?php selected( isset( $chat['position'] ) ? $chat['position'] : 'bottom-right', 'bottom-right' ); ?>><?php esc_html_e( 'Bottom right', 'scene-shift-ai-assistant' ); ?></option>
							<option value="bottom-left" <?php selected( isset( $chat['position'] ) ? $chat['position'] : 'bottom-right', 'bottom-left' ); ?>><?php esc_html_e( 'Bottom left', 'scene-shift-ai-assistant' ); ?></option>
						</select>
					</label>
					<label class="scene-shift-field">
						<span class="scene-shift-field__label"><?php esc_html_e( 'Theme', 'scene-shift-ai-assistant' ); ?></span>
						<select name="chat[theme]">
							<option value="light" <?php selected( isset( $chat['theme'] ) ? $chat['theme'] : 'light', 'light' ); ?>><?php esc_html_e( 'Light', 'scene-shift-ai-assistant' ); ?></option>
							<option value="dark" <?php selected( isset( $chat['theme'] ) ? $chat['theme'] : 'light', 'dark' ); ?>><?php esc_html_e( 'Dark', 'scene-shift-ai-assistant' ); ?></option>
							<option value="custom" <?php selected( isset( $chat['theme'] ) ? $chat['theme'] : 'light', 'custom' ); ?>><?php esc_html_e( 'Custom', 'scene-shift-ai-assistant' ); ?></option>
						</select>
					</label>
					<label class="scene-shift-field">
						<span class="scene-shift-field__label"><?php esc_html_e( 'Corner radius', 'scene-shift-ai-assistant' ); ?></span>
						<select name="chat[radius]">
							<option value="small" <?php selected( isset( $chat['radius'] ) ? $chat['radius'] : 'medium', 'small' ); ?>><?php esc_html_e( 'Small', 'scene-shift-ai-assistant' ); ?></option>
							<option value="medium" <?php selected( isset( $chat['radius'] ) ? $chat['radius'] : 'medium', 'medium' ); ?>><?php esc_html_e( 'Medium', 'scene-shift-ai-assistant' ); ?></option>
							<option value="large" <?php selected( isset( $chat['radius'] ) ? $chat['radius'] : 'medium', 'large' ); ?>><?php esc_html_e( 'Large', 'scene-shift-ai-assistant' ); ?></option>
						</select>
					</label>
				</div>

				<div class="scene-shift-row">
					<label class="scene-shift-field">
						<span class="scene-shift-field__label"><?php esc_html_e( 'Accent color', 'scene-shift-ai-assistant' ); ?></span>
						<input type="color" name="chat[accentColor]" value="<?php echo esc_attr( isset( $chat['accentColor'] ) ? (string) $chat['accentColor'] : '#4f46e5' ); ?>">
					</label>
					<label class="scene-shift-field">
						<span class="scene-shift-field__label"><?php esc_html_e( 'Surface color', 'scene-shift-ai-assistant' ); ?></span>
						<input type="color" name="chat[surfaceColor]" value="<?php echo esc_attr( isset( $chat['surfaceColor'] ) ? (string) $chat['surfaceColor'] : '#ffffff' ); ?>">
					</label>
					<label class="scene-shift-field">
						<span class="scene-shift-field__label"><?php esc_html_e( 'Text color', 'scene-shift-ai-assistant' ); ?></span>
						<input type="color" name="chat[textColor]" value="<?php echo esc_attr( isset( $chat['textColor'] ) ? (string) $chat['textColor'] : '#0a0a0a' ); ?>">
					</label>
					<label class="scene-shift-field">
						<span class="scene-shift-field__label"><?php esc_html_e( 'User bubble', 'scene-shift-ai-assistant' ); ?></span>
						<input type="color" name="chat[userBubbleColor]" value="<?php echo esc_attr( isset( $chat['userBubbleColor'] ) ? (string) $chat['userBubbleColor'] : '#4f46e5' ); ?>">
					</label>
					<label class="scene-shift-field">
						<span class="scene-shift-field__label"><?php esc_html_e( 'Launcher fill', 'scene-shift-ai-assistant' ); ?></span>
						<input type="color" name="chat[launcherColor]" value="<?php echo esc_attr( isset( $chat['launcherColor'] ) ? (string) $chat['launcherColor'] : '#ffffff' ); ?>">
					</label>
				</div>

				<label class="scene-shift-field">
					<span class="scene-shift-field__label"><?php esc_html_e( 'Launcher label', 'scene-shift-ai-assistant' ); ?></span>
					<input type="text" name="chat[launcherLabel]" value="<?php echo esc_attr( isset( $chat['launcherLabel'] ) ? (string) $chat['launcherLabel'] : 'Chat with us' ); ?>">
				</label>

				<label class="scene-shift-field">
					<span class="scene-shift-field__label"><?php esc_html_e( 'Empty-state message', 'scene-shift-ai-assistant' ); ?></span>
					<input type="text" name="chat[emptyMessage]" value="<?php echo esc_attr( isset( $chat['emptyMessage'] ) ? (string) $chat['emptyMessage'] : 'Ask a question to get started.' ); ?>">
				</label>
			</section>

			<p class="submit"><button type="submit" class="button button-primary"><?php esc_html_e( 'Save settings', 'scene-shift-ai-assistant' ); ?></button></p>
		</form>
		<?php
	}

	public function handle_connect(): void {
		$this->verify_admin_post( 'connect' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce checked via verify_admin_post() above.
		$setup_code = isset( $_POST['setup_code'] ) ? sanitize_text_field( wp_unslash( $_POST['setup_code'] ) ) : '';
		$site_url = home_url();
		if ( $setup_code === '' ) {
			$this->redirect_back( 'error', __( 'Please paste your setup code.', 'scene-shift-ai-assistant' ) );
			return;
		}
		if ( ! preg_match( '/^sswp_setup_[A-Za-z0-9_-]+$/', $setup_code ) ) {
			$this->redirect_back( 'error', __( 'Invalid setup code format.', 'scene-shift-ai-assistant' ) );
			return;
		}
		$consent_remote = ! empty( $_POST['consent_remote'] );
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( ! $consent_remote ) {
			$this->redirect_back( 'error', __( 'Please confirm data-sharing consent to connect Scene Shift services.', 'scene-shift-ai-assistant' ) );
			return;
		}
		$result = $this->portal->exchange_setup_code( $setup_code, $site_url );
		if ( is_wp_error( $result ) ) {
			$this->redirect_back( 'error', $result->get_error_message() );
			return;
		}
		if ( ! isset( $result['installId'], $result['pluginToken'] ) ) {
			$this->redirect_back( 'error', __( 'Unexpected response from Scene Shift.', 'scene-shift-ai-assistant' ) );
			return;
		}
		$this->settings->set(
			[
				'install_id' => (string) $result['installId'],
				'plugin_token' => (string) $result['pluginToken'],
				'token_prefix' => isset( $result['tokenPrefix'] ) ? (string) $result['tokenPrefix'] : '',
				'token_last_four' => isset( $result['tokenLastFour'] ) ? (string) $result['tokenLastFour'] : '',
				'config' => isset( $result['config'] ) && is_array( $result['config'] ) ? $result['config'] : null,
				'last_synced_at' => current_time( 'mysql' ),
				'last_sync_error' => null,
			]
		);
		Plugin::schedule_sync();
		$this->redirect_back( 'success', __( 'Connected. Configure your phone and chat below.', 'scene-shift-ai-assistant' ) );
	}

	public function handle_disconnect(): void {
		$this->verify_admin_post( 'disconnect' );
		$this->settings->clear();
		Plugin::unschedule_sync();
		$this->redirect_back( 'success', __( 'Disconnected from Scene Shift.', 'scene-shift-ai-assistant' ) );
	}

	public function handle_sync(): void {
		$this->verify_admin_post( 'sync' );
		$result = $this->sync_config_silently();
		if ( is_wp_error( $result ) ) {
			$this->redirect_back( 'error', $result->get_error_message() );
			return;
		}
		$this->redirect_back( 'success', __( 'Settings re-synced from Scene Shift.', 'scene-shift-ai-assistant' ) );
	}

	public function handle_save(): void {
		$this->verify_admin_post( 'save_settings' );
		if ( ! $this->settings->is_connected() ) {
			$this->redirect_back( 'error', __( 'Connect your account first.', 'scene-shift-ai-assistant' ) );
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce checked via verify_admin_post() above. Each sub-key is individually sanitized in build_phone_patch() / build_chat_patch().
		$phone_input = isset( $_POST['phone'] ) && is_array( $_POST['phone'] ) ? wp_unslash( $_POST['phone'] ) : [];
		$chat_input = isset( $_POST['chat'] ) && is_array( $_POST['chat'] ) ? wp_unslash( $_POST['chat'] ) : [];
		// phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$patch = [
			'phoneSettings' => $this->build_phone_patch( $phone_input ),
			'chatSettings' => $this->build_chat_patch( $chat_input ),
		];
		$result = $this->portal->update_config(
			(string) $this->settings->install_id(),
			(string) $this->settings->plugin_token(),
			home_url(),
			$patch,
		);
		if ( is_wp_error( $result ) ) {
			$this->redirect_back( 'error', $result->get_error_message() );
			return;
		}
		if ( isset( $result['config'] ) && is_array( $result['config'] ) ) {
			$this->settings->update_config( $result['config'], null );
		}
		$this->redirect_back( 'success', __( 'Settings saved and pushed to Scene Shift.', 'scene-shift-ai-assistant' ) );
	}

	/**
	 * Cron + manual sync entry point. Returns the fresh config or a WP_Error.
	 *
	 * @return array<string,mixed>|\WP_Error
	 */
	public function sync_config_silently() {
		if ( ! $this->settings->is_connected() ) {
			return new \WP_Error( 'not_connected', __( 'Plugin is not connected.', 'scene-shift-ai-assistant' ) );
		}
		$result = $this->portal->read_config(
			(string) $this->settings->install_id(),
			(string) $this->settings->plugin_token(),
			home_url(),
		);
		if ( is_wp_error( $result ) ) {
			$this->settings->record_sync_error( $result->get_error_message() );
			return $result;
		}
		if ( ! isset( $result['config'] ) || ! is_array( $result['config'] ) ) {
			return new \WP_Error( 'invalid_response', __( 'Invalid response from Scene Shift.', 'scene-shift-ai-assistant' ) );
		}
		$this->settings->update_config( $result['config'], null );
		return $result['config'];
	}

	/**
	 * @param array<string,mixed> $input
	 */
	private function build_phone_patch( array $input ): array {
		$enabled = ! empty( $input['enabled'] );
		$all_day = ! empty( $input['allDay'] );
		$start_time = isset( $input['startTime'] ) ? (string) $input['startTime'] : '09:00';
		$end_time = isset( $input['endTime'] ) ? (string) $input['endTime'] : '18:00';
		// Validate the timezone against PHP's known identifier list rather
		// than just sanitizing as text. Anything unknown falls back to UTC at
		// the bottom of this method, matching the renderer's runtime fallback.
		$tz_input = isset( $input['timezone'] ) ? sanitize_text_field( (string) $input['timezone'] ) : '';
		$timezone = ( $tz_input !== '' && in_array( $tz_input, timezone_identifiers_list(), true ) )
			? $tz_input
			: '';
		$days_input = isset( $input['days'] ) && is_array( $input['days'] ) ? $input['days'] : [];
		$days = array_values(
			array_filter(
				array_map(
					static function ( $value ) {
						$num = (int) $value;
						return ( $num >= 0 && $num <= 6 ) ? $num : null;
					},
					$days_input
				),
				static function ( $value ) {
					return $value !== null;
				}
			)
		);

		$ai_label = isset( $input['aiLabel'] ) ? sanitize_text_field( (string) $input['aiLabel'] ) : '';
		$alt_label = isset( $input['alternativeLabel'] ) ? sanitize_text_field( (string) $input['alternativeLabel'] ) : '';
		$alt_number = isset( $input['alternativeNumber'] ) ? sanitize_text_field( (string) $input['alternativeNumber'] ) : '';

		return [
			'enabled' => $enabled,
			'allDay' => $all_day,
			'startMinute' => $this->time_to_minutes( $start_time, 9 * 60 ),
			'endMinute' => $this->time_to_minutes( $end_time, 18 * 60 ),
			'timezone' => $timezone !== '' ? $timezone : 'UTC',
			'days' => $days,
			'aiLabel' => $ai_label !== '' ? $ai_label : 'AI assistant',
			'alternativeNumber' => $alt_number !== '' ? $alt_number : null,
			'alternativeLabel' => $alt_label !== '' ? $alt_label : 'Live agent',
		];
	}

	/**
	 * @param array<string,mixed> $input
	 */
	private function build_chat_patch( array $input ): array {
		$position = isset( $input['position'] ) && in_array( $input['position'], [ 'bottom-right', 'bottom-left' ], true )
			? $input['position']
			: 'bottom-right';
		$theme = isset( $input['theme'] ) && in_array( $input['theme'], [ 'light', 'dark', 'custom' ], true )
			? $input['theme']
			: 'light';
		$radius = isset( $input['radius'] ) && in_array( $input['radius'], [ 'small', 'medium', 'large' ], true )
			? $input['radius']
			: 'medium';
		// Use WordPress's bundled sanitize_hex_color() (loaded in admin
		// context where this handler runs). It accepts #RGB and #RRGGBB and
		// returns null for anything malformed — exactly the contract we want.
		$colors = [];
		foreach ( [ 'accentColor', 'surfaceColor', 'textColor', 'userBubbleColor', 'launcherColor' ] as $field ) {
			if ( ! isset( $input[ $field ] ) || ! is_string( $input[ $field ] )) continue;
			$value = sanitize_hex_color( trim( $input[ $field ] ) );
			if ( is_string( $value ) && $value !== '' ) {
				$colors[ $field ] = strtolower( $value );
			}
		}
		return array_merge(
			[
				'enabled' => ! empty( $input['enabled'] ),
				'position' => $position,
				'theme' => $theme,
				'radius' => $radius,
				'launcherLabel' => isset( $input['launcherLabel'] ) ? sanitize_text_field( (string) $input['launcherLabel'] ) : 'Chat with us',
				'emptyMessage' => isset( $input['emptyMessage'] ) ? sanitize_text_field( (string) $input['emptyMessage'] ) : 'Ask a question to get started.',
			],
			$colors
		);
	}

	private function verify_admin_post( string $form_action ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'scene-shift-ai-assistant' ), 403 );
		}
		check_admin_referer( self::nonce_action( $form_action ) );
	}

	/**
	 * Stash a notice in a short-lived, user-scoped transient and redirect back
	 * to the settings page. Using a transient (instead of a query-string
	 * payload) prevents anyone from crafting a URL that displays a fake
	 * success/error message in the admin.
	 */
	private function redirect_back( string $kind, string $message ): void {
		$user_id = get_current_user_id();
		if ( $user_id > 0 ) {
			set_transient(
				self::NOTICE_TRANSIENT_PREFIX . (int) $user_id,
				[
					'kind'    => $kind === 'success' ? 'success' : 'error',
					'message' => $message,
				],
				MINUTE_IN_SECONDS
			);
		}
		$url = add_query_arg( [ 'page' => self::MENU_SLUG ], admin_url( 'options-general.php' ) );
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * @return array<int, array{kind:string,message:string}>
	 */
	private function collect_admin_notices(): array {
		$user_id = get_current_user_id();
		if ($user_id <= 0) return [];
		$key = self::NOTICE_TRANSIENT_PREFIX . (int) $user_id;
		$notice = get_transient( $key );
		if ( ! is_array( $notice )) return [];
		delete_transient( $key );
		$kind = isset( $notice['kind'] ) && $notice['kind'] === 'success' ? 'success' : 'error';
		$message = isset( $notice['message'] ) ? (string) $notice['message'] : '';
		if ($message === '') return [];
		return [
			[
				'kind'    => $kind,
				'message' => $message,
			],
		];
	}

	private function minutes_to_time( int $minutes ): string {
		$minutes = max( 0, min( 1440, $minutes ) );
		return sprintf( '%02d:%02d', intdiv( $minutes, 60 ), $minutes % 60 );
	}

	private function time_to_minutes( string $value, int $fallback ): int {
		if ( ! preg_match( '/^(\d{1,2}):(\d{2})$/', trim( $value ), $matches )) return $fallback;
		$hour = (int) $matches[1];
		$minute = (int) $matches[2];
		if ($hour < 0 || $hour > 24 || $minute < 0 || $minute > 59) return $fallback;
		return min( 1440, $hour * 60 + $minute );
	}
}
