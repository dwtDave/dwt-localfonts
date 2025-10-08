<?php
/**
 * Font Storage Service
 *
 * @package DWT\LocalFonts
 */

declare(strict_types=1);

namespace DWT\LocalFonts\Services;

use DWT\LocalFonts\Logger;

/**
 * Handles file system operations for font files.
 *
 * Note: Not marked as final to allow mocking in unit tests.
 */
class FontStorage {

	/**
	 * Font directory name.
	 *
	 * @var string
	 */
	private const FONT_DIR_NAME = 'dwt-local-fonts';

	/**
	 * WP_Filesystem instance.
	 *
	 * @var \WP_Filesystem_Base|null
	 */
	private $filesystem = null;

	/**
	 * Font validator instance.
	 *
	 * @var FontValidator
	 */
	private FontValidator $validator;

	/**
	 * Constructor.
	 *
	 * @param FontValidator|null $validator Optional validator instance.
	 */
	public function __construct( ?FontValidator $validator = null ) {
		$this->validator = $validator ?? new FontValidator();
	}

	/**
	 * Get WP_Filesystem instance.
	 *
	 * @return \WP_Filesystem_Base|false WP_Filesystem instance or false on failure.
	 */
	private function get_filesystem() {
		if ( null !== $this->filesystem ) {
			return $this->filesystem;
		}

		global $wp_filesystem;

		// Check if we're in a unit test environment (Brain\Monkey, not integration tests).
		// Integration tests have WordPress loaded, so WP_Filesystem should work.
		$is_unit_test = defined( 'PHPUNIT_RUNNING' ) && PHPUNIT_RUNNING && ! class_exists( 'WP_UnitTestCase' );

		if ( ! defined( 'ABSPATH' ) || $is_unit_test ) {
			return false;
		}

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			$file_path = ABSPATH . 'wp-admin/includes/file.php';
			if ( ! file_exists( $file_path ) ) {
				Logger::error( 'WP_Filesystem file not found', array( 'path' => $file_path ) );
				return false;
			}
			// @phpstan-ignore-next-line Runtime path based on ABSPATH in WordPress context.
			require_once $file_path;
		}

		// Initialize WP_Filesystem.
		if ( ! WP_Filesystem() ) {
			Logger::error( 'Failed to initialize WP_Filesystem' );
			return false;
		}

		$this->filesystem = $wp_filesystem;
		return $this->filesystem;
	}

	/**
	 * Get font directory path.
	 *
	 * @return string Font directory path.
	 */
	public function get_font_dir_path(): string {
		$upload_dir = \wp_upload_dir();
		return $upload_dir['basedir'] . '/' . self::FONT_DIR_NAME;
	}

	/**
	 * Get font directory URL.
	 *
	 * @return string Font directory URL.
	 */
	public function get_font_dir_url(): string {
		$upload_dir = \wp_upload_dir();
		return $upload_dir['baseurl'] . '/' . self::FONT_DIR_NAME;
	}

	/**
	 * Save font content to file.
	 *
	 * @param string $filename Font filename.
	 * @param string $content  Font file content.
	 * @return bool True on success, false on failure.
	 */
	public function save_font_file( string $filename, string $content ): bool {
		$filesystem = $this->get_filesystem();
		if ( ! $filesystem ) {
			Logger::error( 'Cannot save font file: WP_Filesystem not available' );
			return false;
		}

		$sanitized_filename = $this->validator->sanitize_filename( $filename );
		if ( false === $sanitized_filename ) {
			Logger::warning( 'Invalid font filename', array( 'filename' => $filename ) );
			return false;
		}

		$file_path = $this->get_font_dir_path() . '/' . $sanitized_filename;

		// Ensure directory exists.
		\wp_mkdir_p( $this->get_font_dir_path() );

		$result = $filesystem->put_contents( $file_path, $content, FS_CHMOD_FILE );

		if ( $result ) {
			Logger::info( 'Font file saved successfully', array( 'filename' => $sanitized_filename ) );
		} else {
			Logger::error( 'Failed to save font file', array( 'filename' => $sanitized_filename ) );
		}

		return (bool) $result;
	}

	/**
	 * Check if font file exists.
	 *
	 * @param string $filename Font filename.
	 * @return bool True if file exists.
	 */
	public function font_file_exists( string $filename ): bool {
		$filesystem = $this->get_filesystem();
		if ( ! $filesystem ) {
			return false;
		}

		$sanitized_filename = $this->validator->sanitize_filename( $filename );
		if ( false === $sanitized_filename ) {
			return false;
		}

		$file_path = $this->get_font_dir_path() . '/' . $sanitized_filename;
		return $filesystem->exists( $file_path );
	}

	/**
	 * Delete font files by their filenames.
	 *
	 * @param array<string> $filenames Array of font filenames to delete.
	 * @return int Number of files deleted.
	 */
	public function delete_font_files( array $filenames ): int {
		$deleted_count = 0;
		$filesystem    = $this->get_filesystem();

		if ( ! $filesystem ) {
			Logger::error( 'Cannot delete font files: WP_Filesystem not available' );
			return $deleted_count;
		}

		foreach ( $filenames as $filename ) {
			$sanitized_filename = $this->validator->sanitize_filename( $filename );

			if ( false === $sanitized_filename ) {
				continue;
			}

			$file_path = $this->get_font_dir_path() . '/' . $sanitized_filename;

			// Delete file if it exists.
			if ( $filesystem->exists( $file_path ) ) {
				if ( $filesystem->delete( $file_path ) ) {
					++$deleted_count;
					Logger::info( 'Font file deleted', array( 'filename' => $sanitized_filename ) );
				} else {
					Logger::warning( 'Failed to delete font file', array( 'filename' => $sanitized_filename ) );
				}
			}
		}

		return $deleted_count;
	}

	/**
	 * Get list of all font files in the font directory.
	 *
	 * @return array<string> Array of font filenames.
	 */
	public function get_all_font_files(): array {
		$font_dir = $this->get_font_dir_path();

		if ( ! is_dir( $font_dir ) ) {
			return array();
		}

		// GLOB_BRACE is not available on Alpine Linux, so we use multiple glob calls.
		$extensions = array( 'woff', 'woff2', 'ttf', 'otf', 'eot' );
		$all_files  = array();

		foreach ( $extensions as $ext ) {
			$files = glob( $font_dir . '/*.' . $ext );
			if ( false !== $files ) {
				$all_files = array_merge( $all_files, $files );
			}
		}

		// Return just the filenames, not full paths.
		return array_map( 'basename', $all_files );
	}

	/**
	 * Validate if font files exist in the filesystem.
	 *
	 * @param array<string> $filenames Array of font filenames to validate.
	 * @return array{existing: array<string>, missing: array<string>} Validation result.
	 */
	public function validate_font_files_exist( array $filenames ): array {
		$existing = array();
		$missing  = array();

		foreach ( $filenames as $filename ) {
			if ( $this->font_file_exists( $filename ) ) {
				$existing[] = $filename;
			} else {
				$missing[] = $filename;
			}
		}

		return array(
			'existing' => $existing,
			'missing'  => $missing,
		);
	}
}
