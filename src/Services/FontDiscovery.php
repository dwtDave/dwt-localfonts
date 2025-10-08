<?php
/**
 * Font Discovery Service
 *
 * @package DWT\LocalFonts
 */

declare(strict_types=1);

namespace DWT\LocalFonts\Services;

use DWT\LocalFonts\Logger;
use DWT\LocalFonts\Vendor\FontLib\Font;

/**
 * Discovers and identifies font files in the uploads directory.
 *
 * Note: Not marked as final to allow mocking in unit tests.
 */
class FontDiscovery {

	/**
	 * Font storage instance.
	 *
	 * @var FontStorage
	 */
	private FontStorage $storage;

	/**
	 * Supported font file extensions.
	 *
	 * @var array<string>
	 */
	private const SUPPORTED_EXTENSIONS = array( 'ttf', 'otf', 'woff', 'woff2' );

	/**
	 * Constructor.
	 *
	 * @param FontStorage|null $storage Optional storage instance.
	 */
	public function __construct( ?FontStorage $storage = null ) {
		$this->storage = $storage ?? new FontStorage();
	}

	/**
	 * Discover all font families from files in the fonts directory.
	 *
	 * @return array<string> Array of unique font family names.
	 */
	public function discover_fonts(): array {
		$font_dir = $this->storage->get_font_dir_path();

		if ( ! file_exists( $font_dir ) || ! is_dir( $font_dir ) ) {
			Logger::info( 'Font directory does not exist for discovery', array( 'path' => $font_dir ) );
			return array();
		}

		$font_families = array();
		$font_files    = $this->get_font_files( $font_dir );

		Logger::info( 'Discovering fonts from directory', array( 'file_count' => count( $font_files ) ) );

		foreach ( $font_files as $font_file ) {
			$font_family = $this->extract_font_family( $font_file );

			if ( null !== $font_family && '' !== $font_family ) {
				$font_families[] = $font_family;
			}
		}

		// Return unique font families.
		$unique_families = array_unique( $font_families );
		$unique_families = array_values( $unique_families ); // Re-index array.

		Logger::info( 'Font discovery completed', array( 'families_found' => count( $unique_families ) ) );

		return $unique_families;
	}

	/**
	 * Get all font files from the directory.
	 *
	 * @param string $directory Directory path to scan.
	 * @return array<string> Array of font file paths.
	 */
	private function get_font_files( string $directory ): array {
		$font_files = array();

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_readable -- Need to check directory accessibility.
		if ( ! is_readable( $directory ) ) {
			Logger::warning( 'Font directory not readable', array( 'path' => $directory ) );
			return array();
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Suppress warnings for file operations.
		$files = @scandir( $directory );

		if ( false === $files ) {
			Logger::error( 'Failed to scan font directory', array( 'path' => $directory ) );
			return array();
		}

		foreach ( $files as $file ) {
			if ( '.' === $file || '..' === $file ) {
				continue;
			}

			$file_path = trailingslashit( $directory ) . $file;

			// Skip directories and non-font files.
			if ( is_dir( $file_path ) ) {
				continue;
			}

			// Check if file has a supported font extension.
			$extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );

			if ( in_array( $extension, self::SUPPORTED_EXTENSIONS, true ) ) {
				$font_files[] = $file_path;
			}
		}

		return $font_files;
	}

	/**
	 * Extract font family name from a font file.
	 *
	 * @param string $file_path Path to the font file.
	 * @return string|null Font family name or null on failure.
	 */
	private function extract_font_family( string $file_path ): ?string {
		try {
			// Load font file using php-font-lib.
			$font = Font::load( $file_path );

			if ( ! $font ) {
				Logger::warning( 'Failed to load font file', array( 'file' => basename( $file_path ) ) );
				return null;
			}

			// Parse the font file.
			$font->parse();

			// Get font name (family name).
			$font_name = $font->getFontName();

			// Close font file.
			$font->close();

			if ( empty( $font_name ) ) {
				Logger::warning( 'Font file has no family name', array( 'file' => basename( $file_path ) ) );
				return null;
			}

			// Normalize the font family name to remove weight/style suffixes.
			$normalized_name = $this->normalize_font_family( $font_name );

			Logger::debug(
				'Extracted font family',
				array(
					'file'            => basename( $file_path ),
					'original_name'   => $font_name,
					'normalized_name' => $normalized_name,
				)
			);

			return $normalized_name;

		} catch ( \Exception $e ) {
			Logger::warning(
				'Error extracting font family from file',
				array(
					'file'  => basename( $file_path ),
					'error' => $e->getMessage(),
				)
			);
			return null;
		}
	}

	/**
	 * Normalize font family name by removing weight and style suffixes.
	 *
	 * This ensures that different weights/styles of the same font family
	 * (e.g., "Lato Light", "Lato Bold", "Lato Black Italic") are all
	 * grouped under the same base family name (e.g., "Lato").
	 *
	 * @param string $font_name The full font name from the font file.
	 * @return string The normalized base font family name.
	 */
	private function normalize_font_family( string $font_name ): string {
		// List of weight and style descriptors to remove (case-insensitive).
		$descriptors = array(
			// Numeric weights.
			'100',
			'200',
			'300',
			'400',
			'500',
			'600',
			'700',
			'800',
			'900',
			// Named weights.
			'Thin',
			'Hairline',
			'Extra Light',
			'ExtraLight',
			'Ultra Light',
			'UltraLight',
			'Light',
			'Regular',
			'Normal',
			'Medium',
			'Semi Bold',
			'SemiBold',
			'Demi Bold',
			'DemiBold',
			'Bold',
			'Extra Bold',
			'ExtraBold',
			'Ultra Bold',
			'UltraBold',
			'Black',
			'Heavy',
			'Extra Black',
			'ExtraBlack',
			'Ultra Black',
			'UltraBlack',
			// Styles.
			'Italic',
			'Oblique',
			'Inclined',
			// Widths.
			'Condensed',
			'Narrow',
			'Extended',
			'Expanded',
			'Wide',
		);

		// Build regex pattern to match any of the descriptors at word boundaries.
		$pattern = '/\b(' . implode( '|', array_map( 'preg_quote', $descriptors ) ) . ')\b/i';

		// Remove all matching descriptors.
		$normalized = preg_replace( $pattern, '', $font_name );

		// Clean up extra whitespace and trim.
		$normalized = preg_replace( '/\s+/', ' ', $normalized );
		$normalized = trim( $normalized );

		// If normalization resulted in an empty string, return the original name.
		if ( empty( $normalized ) ) {
			return $font_name;
		}

		return $normalized;
	}

	/**
	 * Check if font files exist in the directory.
	 *
	 * @return bool True if font files exist.
	 */
	public function has_font_files(): bool {
		$font_dir = $this->storage->get_font_dir_path();

		if ( ! file_exists( $font_dir ) || ! is_dir( $font_dir ) ) {
			return false;
		}

		$font_files = $this->get_font_files( $font_dir );

		return count( $font_files ) > 0;
	}
}
