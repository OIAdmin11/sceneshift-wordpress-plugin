<?php
/**
 * Lightweight HTTP client for the Scene Shift portal API. The plugin token is
 * sent as a Bearer header on every authenticated call. All responses are
 * parsed as JSON; transport-layer errors return a `WP_Error`.
 *
 * @package SceneShift\AiAssistant
 */

namespace SceneShift\AiAssistant;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PortalClient {
	/** @var string */
	private $api_base;

	public function __construct( string $api_base ) {
		$this->api_base = rtrim( $api_base, '/' );
	}

	/**
	 * Build the common header set for every portal call. The portal currently
	 * inspects the Origin header to bind requests to the connecting site URL,
	 * so we send it explicitly even though `wp_remote_*` is server-to-server.
	 *
	 * @return array<string,string>
	 */
	private function base_headers( string $site_url, ?string $plugin_token = null ): array {
		$headers = [
			'Accept'     => 'application/json',
			'Origin'     => $site_url,
			'User-Agent' => sprintf(
				'SceneShiftAiAssistant/%s; %s',
				SCENE_SHIFT_AI_ASSISTANT_VERSION,
				$site_url
			),
		];
		if ( is_string( $plugin_token ) && $plugin_token !== '' ) {
			$headers['Authorization'] = 'Bearer ' . $plugin_token;
		}
		return $headers;
	}

	/**
	 * Exchange a one-time setup code for a long-lived plugin token.
	 *
	 * @return array{installId:string, pluginToken:string, tokenPrefix:string, tokenLastFour:string, config:array<string,mixed>}|\WP_Error
	 */
	public function exchange_setup_code( string $setup_code, string $site_url ) {
		$response = wp_remote_post(
			$this->api_base . '/voice/wordpress-installs/exchange',
			[
				'timeout' => 15,
				'headers' => array_merge(
					$this->base_headers( $site_url ),
					[
						'Content-Type' => 'application/json',
					]
				),
				'body' => wp_json_encode(
					[
						'setupCode' => $setup_code,
						'siteUrl'   => $site_url,
					]
				),
			]
		);
		return $this->handle_response( $response, 'exchange_setup_code' );
	}

	/**
	 * Read the current published config for this install.
	 *
	 * @return array{config:array<string,mixed>}|\WP_Error
	 */
	public function read_config( string $install_id, string $plugin_token, string $site_url ) {
		$response = wp_remote_get(
			$this->api_base . '/voice/wordpress-installs/' . rawurlencode( $install_id ),
			[
				'timeout' => 15,
				'headers' => $this->base_headers( $site_url, $plugin_token ),
			],
		);
		return $this->handle_response( $response, 'read_config' );
	}

	/**
	 * Update plugin-managed settings on the portal (chat theme, schedule, etc.).
	 *
	 * @param array<string,mixed> $patch
	 * @return array{config:array<string,mixed>}|\WP_Error
	 */
	public function update_config( string $install_id, string $plugin_token, string $site_url, array $patch ) {
		$response = wp_remote_request(
			$this->api_base . '/voice/wordpress-installs/' . rawurlencode( $install_id ),
			[
				'method'  => 'PUT',
				'timeout' => 15,
				'headers' => array_merge(
					$this->base_headers( $site_url, $plugin_token ),
					[
						'Content-Type' => 'application/json',
					]
				),
				'body'    => wp_json_encode( $patch ),
			],
		);
		return $this->handle_response( $response, 'update_config' );
	}

	public function api_base(): string {
		return $this->api_base;
	}

	private function handle_response( $response, string $operation ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$status = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = $body !== '' ? json_decode( $body, true ) : null;
		if ( $status >= 200 && $status < 300 ) {
			return is_array( $data ) ? $data : [];
		}
		$message = '';
		if ( is_array( $data ) && isset( $data['error'] ) && is_string( $data['error'] ) ) {
			$message = $data['error'];
		}
		if ( $message === '' ) {
			$message = sprintf(
				/* translators: 1: HTTP status code, 2: operation label. */
				__( 'Scene Shift API request "%2$s" failed with status %1$d.', 'scene-shift-ai-assistant' ),
				$status,
				$operation,
			);
		}
		return new \WP_Error(
			'scene_shift_api_error',
			$message,
			[
				'status' => $status,
				'operation' => $operation,
			]
		);
	}
}
