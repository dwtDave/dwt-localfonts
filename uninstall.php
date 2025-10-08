<?php
/**
 * Uninstall Script
 *
 * Handles complete cleanup when the plugin is uninstalled.
 *
 * @package DWT\LocalFonts
 */

declare(strict_types=1);

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Load Security class for path validation.
require_once __DIR__ . '/src/Modules/Security.php';

if ( ! function_exists( 'dwt_local_fonts_delete_options' ) ) {
	/**
	 * Delete all plugin options.
	 */
	function dwt_local_fonts_delete_options(): void {
		delete_option( 'dwt_local_fonts_list' );
		delete_option( 'dwt_local_fonts_settings' );
		delete_option( 'dwt_local_fonts_backup_history' );
		delete_option( 'dwt_local_fonts_logs' );
		delete_option( 'dwt_local_fonts_activated' );
	}
}

if ( ! function_exists( 'dwt_local_fonts_delete_transients' ) ) {
	/**
	 * Delete all plugin transients.
	 */
	function dwt_local_fonts_delete_transients(): void {
		global $wpdb;

		// Delete all transients with our prefix.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_dwt_google_fonts_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_dwt_google_fonts_' ) . '%'
			)
		);
	}
}

if ( ! function_exists( 'dwt_local_fonts_delete_uploads' ) ) {
	/**
	 * Delete all uploaded font files and directories.
	 *
	 * Checks user preference to keep fonts on uninstall before deleting.
	 */
	function dwt_local_fonts_delete_uploads(): void {
		// Check if user wants to keep fonts on uninstall.
		$settings   = (array) get_option( 'dwt_local_fonts_settings', array() );
		$keep_fonts = isset( $settings['keep_fonts_on_uninstall'] ) ? '0' !== (string) $settings['keep_fonts_on_uninstall'] : false;

		if ( $keep_fonts ) {
			// User wants to preserve downloaded fonts - skip deletion.
			return;
		}

		$upload_dir = wp_upload_dir();
		$font_dir   = trailingslashit( $upload_dir['basedir'] ) . 'dwt-local-fonts';

		if ( ! file_exists( $font_dir ) ) {
			return;
		}

		// Recursively delete the font directory and all its contents.
		dwt_local_fonts_recursive_delete( $font_dir );
	}
}

if ( ! function_exists( 'dwt_local_fonts_recursive_delete' ) ) {
	/**
	 * Recursively delete a directory and all its contents.
	 *
	 * @param string      $dir The directory to delete.
	 * @param string|null $base_path Base directory path for validation (optional, defaults to $dir on first call).
	 */
	function dwt_local_fonts_recursive_delete( string $dir, ?string $base_path = null ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		// On first call, set base path to the directory being deleted.
		if ( null === $base_path ) {
			$base_path = $dir;
		}

		// Validate that we're operating within the base directory.
		$validated_dir = \DWT\LocalFonts\Modules\Security::validate_file_path( $dir, $base_path );
		if ( false === $validated_dir ) {
			return; // Skip if path validation fails.
		}

		$files = array_diff( scandir( $validated_dir ), array( '.', '..' ) );

		foreach ( $files as $file ) {
			$path = trailingslashit( $validated_dir ) . $file;

			// Validate path is within base directory to prevent directory traversal.
			$validated_path = \DWT\LocalFonts\Modules\Security::validate_file_path( $path, $base_path );
			if ( false === $validated_path ) {
				continue; // Skip invalid paths.
			}

			if ( is_dir( $validated_path ) ) {
				dwt_local_fonts_recursive_delete( $validated_path, $base_path );
			} else {
				// SECURITY: Safe to use unlink here - $validated_path has been verified by Security::validate_file_path().
				// which uses realpath() to resolve symlinks and validates the path is within $base_path.
				// $base_path is set to the hardcoded font directory (wp-content/uploads/dwt-local-fonts).
				// No user input can influence this path. This is a false positive from static analysis.
				// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- WP_Filesystem not available during uninstall.
				unlink( $validated_path ); // nosemgrep.
			}
		}

		// Validate directory one more time before removal.
		$validated_dir = \DWT\LocalFonts\Modules\Security::validate_file_path( $dir, $base_path );
		if ( false !== $validated_dir ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- WP_Filesystem not available during uninstall.
			rmdir( $validated_dir );
		}
	}
}

// Execute uninstall.
// IMPORTANT: Delete uploads BEFORE deleting options, so we can read the keep_fonts_on_uninstall setting.
dwt_local_fonts_delete_uploads();
dwt_local_fonts_delete_transients();
dwt_local_fonts_delete_options();
