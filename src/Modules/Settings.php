<?php
/**
 * Settings Module
 *
 * @package DWT\LocalFonts
 */

declare(strict_types=1);

namespace DWT\LocalFonts\Modules;

use DWT\LocalFonts\Abstracts\RestController;

/**
 * Handles the plugin's settings page, now powered by React.
 */
final class Settings extends RestController {

	public const OPTION_NAME = 'dwt_local_fonts_settings';
	private const PAGE_SLUG  = 'dwt-local-fonts';

	/**
	 * Constructor - Initializes hooks.
	 */
	public function __construct() {
		\add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		\add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Adds the plugin settings page and enqueues the React script.
	 */
	public function add_settings_page(): void {
		$hook = \add_options_page(
			\__( 'Font Manager', 'dwt-local-fonts' ),
			\__( 'Font Manager', 'dwt-local-fonts' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_react_app_container' )
		);
		\add_action( "load-{$hook}", array( $this, 'enqueue_react_app' ) );
	}

	/**
	 * Renders the root div for the React application.
	 */
	public function render_react_app_container(): void {
		echo '<div id="dwt-local-fonts-react-app" class="wrap"></div>';
	}

	/**
	 * Enqueues the compiled React script and its dependencies from Vite.
	 */
	public function enqueue_react_app(): void {
		$plugin_url = plugin_dir_url( DWT_LOCAL_FONTS_PLUGIN_FILE );
		$is_dev     = defined( 'WP_DEBUG' ) && WP_DEBUG && $this->is_vite_dev_server_running();

		if ( $is_dev ) {
			// Load from Vite dev server.
			\wp_enqueue_script(
				'vite-client',
				'http://localhost:5173/@vite/client',
				array(),
				DWT_LOCAL_FONTS_VERSION,
				true
			);

			\wp_enqueue_script(
				'dwt-local-fonts-app',
				'http://localhost:5173/admin/src/index.tsx',
				array(),
				DWT_LOCAL_FONTS_VERSION,
				true
			);

			// Add module type for ES modules.
			add_filter(
				'script_loader_tag',
				function ( $tag, $handle ) {
					if ( in_array( $handle, array( 'vite-client', 'dwt-local-fonts-app' ), true ) ) {
						return str_replace( '<script ', '<script type="module" ', $tag );
					}
					return $tag;
				},
				10,
				2
			);
		} else {
			// Load from the build directory for production.
			$manifest_path = plugin_dir_path( DWT_LOCAL_FONTS_PLUGIN_FILE ) . 'build/.vite/manifest.json';
			if ( ! file_exists( $manifest_path ) ) {
				return;
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading local manifest file.
			$manifest = json_decode( file_get_contents( $manifest_path ), true );
			$entry    = $manifest['admin/src/index.tsx'] ?? null;

			if ( ! $entry ) {
				return;
			}

			// Enqueue CSS if it exists.
			if ( ! empty( $entry['css'] ) ) {
				foreach ( $entry['css'] as $css_file ) {
					\wp_enqueue_style(
						'dwt-local-fonts-app-style',
						$plugin_url . 'build/' . $css_file,
						array( 'wp-admin', 'common', 'forms' ), // Load after WordPress admin styles.
						DWT_LOCAL_FONTS_VERSION
					);
				}
			}

			// Enqueue main JS.
			\wp_enqueue_script(
				'dwt-local-fonts-app',
				$plugin_url . 'build/' . $entry['file'],
				array(),
				DWT_LOCAL_FONTS_VERSION,
				true
			);

			// Add module type for ES modules.
			add_filter(
				'script_loader_tag',
				function ( $tag, $handle ) {
					if ( 'dwt-local-fonts-app' === $handle ) {
						return str_replace( '<script ', '<script type="module" ', $tag );
					}
					return $tag;
				},
				10,
				2
			);
		}

		// Pass WordPress data to React.
		$upload_dir = \wp_upload_dir();
		\wp_localize_script(
			'dwt-local-fonts-app',
			'dwtLocalFonts',
			array(
				'apiUrl'     => rest_url( 'dwt-management/v1/' ),
				'nonce'      => \wp_create_nonce( 'wp_rest' ),
				'uploadsUrl' => $upload_dir['baseurl'],
				'pluginUrl'  => $plugin_url,
			)
		);
	}

	/**
	 * Check if Vite dev server is running.
	 *
	 * @return bool True if dev server is running.
	 */
	private function is_vite_dev_server_running(): bool {
		$context = stream_context_create(
			array(
				'http' => array(
					'timeout'       => 1,
					'ignore_errors' => true,
				),
			)
		);

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Checking local dev server availability.
		return @file_get_contents( 'http://localhost:5173', false, $context ) !== false;
	}

	/**
	 * Registers the REST API endpoints for getting and setting options.
	 */
	public function register_rest_routes(): void {
		// IMPROVEMENT: Consolidated GET and POST methods into a single registration.
		\register_rest_route(
			'dwt-management/v1',
			'/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => fn() => \current_user_can( 'manage_options' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'save_settings' ),
					'permission_callback' => fn() => \current_user_can( 'manage_options' ),
				),
			)
		);
	}

	/**
	 * REST API callback to get settings.
	 */
	public function get_settings(): \WP_REST_Response {
		$options = (array) \get_option( self::OPTION_NAME, array() );
		return new \WP_REST_Response( $options, 200 );
	}

	/**
	 * REST API callback to save settings.
	 *
	 * @param \WP_REST_Request<array<string, mixed>> $request REST request object.
	 * @return \WP_REST_Response REST response.
	 */
	public function save_settings( \WP_REST_Request $request ): \WP_REST_Response {
		// Verify REST API nonce for additional security.
		if ( ! $this->verify_rest_nonce( $request ) ) {
			return $this->error_response( 'Invalid nonce', 403 );
		}

		// Get old settings to detect changes.
		$old_settings = (array) \get_option( self::OPTION_NAME, array() );

		$new_settings       = (array) $request->get_json_params();
		$sanitized_settings = array();

		// Allowlist of permitted setting keys.
		$allowed_keys = array(
			'font_rules', // Special handling below.
			'keep_fonts_on_uninstall',
		);

		foreach ( $new_settings as $key => $value ) {
				$sanitized_key = \sanitize_key( $key );

				// Only process keys in the allowlist.
			if ( ! in_array( $sanitized_key, $allowed_keys, true ) ) {
				continue;
			}

				// Special handling for font_rules (JSON string).
			if ( 'font_rules' === $sanitized_key ) {
				if ( is_string( $value ) ) {
					$sanitized_settings[ $sanitized_key ] = $this->sanitize_font_rules( $value );
				}
			} else {
				// Boolean settings - convert to '1' or '0'.
				$sanitized_settings[ $sanitized_key ] = $value ? '1' : '0';
			}
		}

		\update_option( self::OPTION_NAME, $sanitized_settings );

		return $this->success_response( $sanitized_settings );
	}

	/**
	 * Sanitizes font rules JSON string.
	 *
	 * @param string $json_string Font rules JSON string.
	 * @return string Sanitized JSON string.
	 */
	private function sanitize_font_rules( string $json_string ): string {
		$rules = json_decode( $json_string, true );

		if ( ! is_array( $rules ) ) {
			return '[]';
		}

		$sanitized_rules = array();

		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$sanitized_rule = array();

			// Sanitize CSS selector - allow only safe characters.
			if ( isset( $rule['selector'] ) ) {
				$selector = trim( $rule['selector'] );
				// Allow alphanumeric, spaces, dots, hashes, hyphens, underscores, commas, >, :, [, ], =, ", '.
				// These characters are safe for CSS selectors including attribute selectors like [data-attr="value"].
				if ( preg_match( '/^[a-zA-Z0-9\s\.\#\-\_\,\>\:\[\]\=\"\']+$/', $selector ) ) {
					$sanitized_rule['selector'] = $selector;
				}
			}

			// Sanitize font family - alphanumeric and spaces only.
			if ( isset( $rule['fontFamily'] ) ) {
				$font_family = trim( $rule['fontFamily'] );
				if ( preg_match( '/^[a-zA-Z0-9\s\-]+$/', $font_family ) ) {
					$sanitized_rule['fontFamily'] = $font_family;
				}
			}

			// Sanitize font weight - numeric values only.
			if ( isset( $rule['fontWeight'] ) ) {
				$weight = trim( $rule['fontWeight'] );
				if ( preg_match( '/^(100|200|300|400|500|600|700|800|900|normal|bold)$/', $weight ) ) {
					$sanitized_rule['fontWeight'] = $weight;
				}
			}

			// Sanitize font size - must include valid CSS unit.
			if ( isset( $rule['fontSize'] ) ) {
				$size = trim( $rule['fontSize'] );
				if ( preg_match( '/^[0-9]+(\.[0-9]+)?(px|em|rem|%|vh|vw)$/', $size ) ) {
					$sanitized_rule['fontSize'] = $size;
				}
			}

			// Sanitize line height - numeric or unitless.
			if ( isset( $rule['lineHeight'] ) ) {
				$height = trim( $rule['lineHeight'] );
				if ( preg_match( '/^[0-9]+(\.[0-9]+)?(px|em|rem|%)?$/', $height ) ) {
					$sanitized_rule['lineHeight'] = $height;
				}
			}

			// Only include rules that have both selector and fontFamily.
			if ( isset( $sanitized_rule['selector'], $sanitized_rule['fontFamily'] ) ) {
				$sanitized_rules[] = $sanitized_rule;
			}
		}

		return wp_json_encode( $sanitized_rules );
	}
}
