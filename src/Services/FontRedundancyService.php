<?php
/**
 * Font redundancy service for identifying orphaned font files.
 *
 * @package DWT\LocalFonts
 */

declare(strict_types=1);

namespace DWT\LocalFonts\Services;

use DWT\LocalFonts\ValueObjects\OrphanedFileInfo;
use DWT\LocalFonts\ValueObjects\RedundancyReport;

/**
 * Service for identifying and managing orphaned font files.
 *
 * Orphaned files are font files that exist in the filesystem but are not
 * tracked in the database (dwt_local_fonts_list option).
 */
class FontRedundancyService {

	/**
	 * Constructor.
	 *
	 * @param FontStorage $storage Font storage service for filesystem operations.
	 */
	public function __construct(
		private readonly FontStorage $storage
	) {}

	/**
	 * Generate redundancy report with orphaned files.
	 *
	 * Scans the font directory, cross-references files with the database,
	 * and identifies orphaned files that are not tracked.
	 *
	 * @return array{orphaned_files: array<int, array{filename: string, size_bytes: int, size_formatted: string, modified_timestamp: int, modified_date: string, file_path: string}>, total_count: int, total_size_bytes: int, total_size_formatted: string, generated_at: int}
	 */
	public function generate_report(): array {
		// Get all font files from filesystem.
		$all_files = $this->storage->get_all_font_files();

		// Exclude CSS file (FR-004: dwt-local-fonts.css is a generated resource).
		$font_files = array_filter(
			$all_files,
			fn( string $filename ): bool => 'dwt-local-fonts.css' !== $filename
		);

		// Get tracked font families from database.
		$tracked_families = (array) \get_option( 'dwt_local_fonts_list', array() );

		// Identify orphaned files.
		$orphaned_info_objects = array();
		foreach ( $font_files as $filename ) {
			if ( $this->is_file_orphaned( $filename, $tracked_families ) ) {
				try {
					$orphaned_info_objects[] = $this->get_file_info( $filename );
				} catch ( \Exception $e ) {
					// Skip files that can't be read (permissions, etc.).
					continue;
				}
			}
		}

		// Build report using value object.
		$report = new RedundancyReport( $orphaned_info_objects );

		return $report->to_array();
	}

	/**
	 * Extract font family name from filename.
	 *
	 * Uses pattern-based matching: extracts the prefix before the first hyphen.
	 * Example: "lato-latin-400-normal.woff2" → "lato"
	 *
	 * @param string $filename Font filename.
	 * @return string Lowercase font family name.
	 */
	public function extract_family_from_filename( string $filename ): string {
		// Remove extension.
		$name_without_ext = pathinfo( $filename, PATHINFO_FILENAME );

		// Extract family name (everything before first hyphen).
		// Pattern: {family}-{subset}-{weight}-{style}.
		$parts = explode( '-', $name_without_ext );

		return strtolower( $parts[0] );
	}

	/**
	 * Check if a file is orphaned.
	 *
	 * A file is orphaned if its font family name is not in the tracked families list.
	 * Uses case-insensitive comparison.
	 *
	 * @param string        $filename         Font filename.
	 * @param array<string> $tracked_families Array of tracked font family names.
	 * @return bool True if file is orphaned.
	 */
	public function is_file_orphaned( string $filename, array $tracked_families ): bool {
		$family = $this->extract_family_from_filename( $filename );

		// Normalize tracked families to lowercase for case-insensitive comparison.
		$normalized_families = array_map( 'strtolower', $tracked_families );

		return ! in_array( $family, $normalized_families, true );
	}

	/**
	 * Get file information for an orphaned file.
	 *
	 * @param string $filename Font filename.
	 * @return OrphanedFileInfo File information value object.
	 * @throws \Exception If file cannot be read.
	 */
	private function get_file_info( string $filename ): OrphanedFileInfo {
		$file_path = $this->storage->get_font_dir_path() . '/' . $filename;

		// Get file metadata.
		$size_bytes = filesize( $file_path );
		$modified   = filemtime( $file_path );

		if ( false === $size_bytes || false === $modified ) {
			throw new \Exception( 'Cannot read file: ' . esc_html( $filename ) );
		}

		return new OrphanedFileInfo(
			$filename,
			$size_bytes,
			$modified,
			$file_path
		);
	}
}
