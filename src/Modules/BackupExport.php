<?php
/**
 * Backup and Export Module
 *
 * @package DWT\LocalFonts
 */

declare(strict_types=1);

namespace DWT\LocalFonts\Modules;

use DWT\LocalFonts\Abstracts\RestController;

/**
 * Handles font configuration backup and export/import.
 */
final class BackupExport extends RestController {

	/**
	 * Constructor - Initializes REST API routes.
	 */
	public function __construct() {
		\add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Register REST API endpoints.
	 */
	public function register_rest_routes(): void {
		// Export configuration.
		\register_rest_route(
			'dwt-management/v1',
			'/backup/export',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'export_configuration' ),
				'permission_callback' => fn() => \current_user_can( 'manage_options' ),
			)
		);

		// Import configuration.
		\register_rest_route(
			'dwt-management/v1',
			'/backup/import',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'import_configuration' ),
				'permission_callback' => fn() => \current_user_can( 'manage_options' ),
			)
		);

		// Get backup history.
		\register_rest_route(
			'dwt-management/v1',
			'/backup/history',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_backup_history' ),
				'permission_callback' => fn() => \current_user_can( 'manage_options' ),
			)
		);
	}

	/**
	 * Export configuration as JSON.
	 *
	 * @phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint  
	 *
	 * @return \WP_REST_Response REST response with export data.
	 */
	public function export_configuration(): \WP_REST_Response {
		$fonts    = (array) \get_option( 'dwt_local_fonts_list', array() );
		$settings = (array) \get_option( 'dwt_local_fonts_settings', array() );

		// Get font file information.
		$upload_dir = \wp_upload_dir();
		$font_dir   = $upload_dir['basedir'] . '/dwt-local-fonts';
		$css_file   = $font_dir . '/dwt-local-fonts.css';

		$export_data = array(
			'version'     => DWT_LOCAL_FONTS_VERSION,
			'exported_at' => gmdate( 'Y-m-d H:i:s' ),
			'exported_by' => get_current_user_id(),
			'site_url'    => get_site_url(),
			'fonts'       => $fonts,
			'settings'    => $settings,
			'css_exists'  => file_exists( $css_file ),
			'css_size'    => file_exists( $css_file ) ? filesize( $css_file ) : 0,
			'font_count'  => count( $fonts ),
		);

		// Save to backup history.
		$this->save_to_history( $export_data );

		return new \WP_REST_Response( $export_data, 200 );
	}

	/**
	 * Import configuration from JSON.
	 *
	 * @param \WP_REST_Request<array<string, mixed>> $request REST request object.
	 * @return \WP_REST_Response REST response.
	 */
	public function import_configuration( \WP_REST_Request $request ): \WP_REST_Response {
		// Verify REST API nonce for additional security.
		if ( ! $this->verify_rest_nonce( $request ) ) {
			Notices::add_error( \__( 'Security check failed. Please refresh and try again.', 'dwt-local-fonts' ) );
			return $this->error_response( 'Invalid nonce', 403 );
		}

		$import_data = (array) $request->get_json_params();

		if ( empty( $import_data ) ) {
			return $this->error_response( 'Invalid import data', 400 );
		}

		// Validate import data structure.
		if ( ! isset( $import_data['fonts'], $import_data['settings'] ) ) {
			return $this->error_response( 'Import data missing required fields (fonts, settings)', 400 );
		}

		// Backup current configuration before importing.
		$backup = $this->export_configuration()->get_data();

		// Import fonts list.
		$imported_fonts = 0;
		if ( is_array( $import_data['fonts'] ) ) {
			\update_option( 'dwt_local_fonts_list', $import_data['fonts'] );
			$imported_fonts = count( $import_data['fonts'] );
		}

		// Import settings.
		$imported_settings = false;
		if ( is_array( $import_data['settings'] ) ) {
			\update_option( 'dwt_local_fonts_settings', $import_data['settings'] );
			$imported_settings = true;
		}

		Notices::add_success(
			sprintf(
				/* translators: %d: number of imported fonts */
				\__( 'Configuration imported successfully. %d font(s) restored.', 'dwt-local-fonts' ),
				$imported_fonts
			)
		);

		return $this->success_response(
			array(
				'imported_fonts'    => $imported_fonts,
				'imported_settings' => $imported_settings,
				'backup_created'    => true,
				'backup_data'       => $backup,
			)
		);
	}

	/**
	 * Get backup history.
	 *
	 * @phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint  
	 *
	 * @return \WP_REST_Response REST response with backup history.
	 */
	public function get_backup_history(): \WP_REST_Response {
		$history = (array) \get_option( 'dwt_local_fonts_backup_history', array() );

		return new \WP_REST_Response(
			array(
				'backups' => array_reverse( $history ), // Newest first.
				'count'   => count( $history ),
			),
			200
		);
	}

	/**
	 * Save export to backup history.
	 *
	 * @param array<string, mixed> $export_data Export data.
	 */
	private function save_to_history( array $export_data ): void {
		$history = (array) \get_option( 'dwt_local_fonts_backup_history', array() );

		// Add to history.
		$history[] = array(
			'exported_at' => $export_data['exported_at'],
			'exported_by' => $export_data['exported_by'],
			'font_count'  => $export_data['font_count'],
			'version'     => $export_data['version'],
		);

		// Keep only last 10 backups.
		if ( count( $history ) > 10 ) {
			$history = array_slice( $history, -10 );
		}

		\update_option( 'dwt_local_fonts_backup_history', $history, false );
	}
}
