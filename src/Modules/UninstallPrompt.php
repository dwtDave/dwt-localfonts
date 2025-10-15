<?php
/**
 * Uninstall Prompt Module
 *
 * @package DWT\LocalFonts
 */

declare(strict_types=1);

namespace DWT\LocalFonts\Modules;

/**
 * Handles the uninstall prompt on the plugins page.
 */
final class UninstallPrompt {

	/**
	 * Constructor - Initializes hooks.
	 */
	public function __construct() {
		\add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		\add_action( 'wp_ajax_dwt_save_uninstall_preference', array( $this, 'save_uninstall_preference' ) );
	}

	/**
	 * Enqueue scripts and styles on the plugins page.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function enqueue_scripts( string $hook ): void {
		// Only load on the plugins page.
		if ( 'plugins.php' !== $hook ) {
			return;
		}

		$plugin_url = plugin_dir_url( DWT_LOCAL_FONTS_PLUGIN_FILE );

		// Enqueue CSS.
		\wp_enqueue_style(
			'dwt-uninstall-prompt',
			$plugin_url . 'assets/css/uninstall-prompt.css',
			array(),
			DWT_LOCAL_FONTS_VERSION
		);

		// Enqueue JavaScript.
		\wp_enqueue_script(
			'dwt-uninstall-prompt',
			$plugin_url . 'assets/js/uninstall-prompt.js',
			array( 'jquery' ),
			DWT_LOCAL_FONTS_VERSION,
			true
		);

		// Localize script with nonce.
		\wp_localize_script(
			'dwt-uninstall-prompt',
			'dwtUninstallPrompt',
			array(
				'nonce' => \wp_create_nonce( 'dwt_uninstall_preference' ),
			)
		);
	}

	/**
	 * AJAX handler to save the uninstall preference.
	 */
	public function save_uninstall_preference(): void {
		// Verify nonce.
		if ( ! isset( $_POST['nonce'] ) || ! \wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_POST['nonce'] ) ), 'dwt_uninstall_preference' ) ) {
			\wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
		}

		// Check user capabilities.
		if ( ! \current_user_can( 'delete_plugins' ) ) {
			\wp_send_json_error( array( 'message' => 'Insufficient permissions' ), 403 );
		}

		// Get the keep_fonts preference.
		$keep_fonts = isset( $_POST['keep_fonts'] ) ? \sanitize_text_field( \wp_unslash( $_POST['keep_fonts'] ) ) : '0';

		// Get existing settings.
		$settings = (array) \get_option( Settings::OPTION_NAME, array() );

		// Update the keep_fonts_on_uninstall setting.
		$settings['keep_fonts_on_uninstall'] = $keep_fonts;

		// Save settings.
		\update_option( Settings::OPTION_NAME, $settings );

		\wp_send_json_success(
			array(
				'message'    => 'Preference saved',
				'keep_fonts' => $keep_fonts,
			)
		);
	}
}
