<?php
/**
 * Font Validator Service
 *
 * @package DWT\LocalFonts
 */

declare(strict_types=1);

namespace DWT\LocalFonts\Services;

/**
 * Handles validation of font URLs, filenames, and content.
 *
 * Note: Not marked as final to allow mocking in unit tests.
 */
class FontValidator {

	/**
	 * Allowed font file extensions.
	 *
	 * @var array<string>
	 */
	private const ALLOWED_EXTENSIONS = array( 'woff', 'woff2', 'ttf', 'eot', 'otf' );

	/**
	 * Allowed font source hosts.
	 *
	 * @var array<string>
	 */
	private const ALLOWED_HOSTS = array(
		'fonts.googleapis.com',
		'fonts.google.com',
		'fonts.bunny.net',         // Bunny Fonts.
		'raw.githubusercontent.com', // Fontsource font files.
	);

	/**
	 * Font file magic bytes for validation.
	 *
	 * @var array<string, string>
	 */
	private const MAGIC_BYTES = array(
		'wOFF' => "\x77\x4F\x46\x46", // WOFF.
		'wOF2' => "\x77\x4F\x46\x32", // WOFF2.
		'ttf'  => "\x00\x01\x00\x00", // TrueType.
		'otf'  => "\x4F\x54\x54\x4F", // OpenType.
		'ttc'  => "\x74\x74\x63\x66", // TrueType Collection.
	);

	/**
	 * Maximum filename length.
	 *
	 * @var int
	 */
	private const MAX_FILENAME_LENGTH = 255;

	/**
	 * Validates that a URL is a legitimate font source URL.
	 *
	 * Supports Google Fonts, Bunny Fonts, and Fontsource (GitHub).
	 *
	 * @param string $url URL to validate.
	 * @return bool True if valid font source URL.
	 */
	public function is_valid_font_url( string $url ): bool {
		// Parse the URL.
		$parsed = wp_parse_url( $url );

		if ( ! $parsed || ! isset( $parsed['scheme'], $parsed['host'] ) ) {
			return false;
		}

		// Must be HTTPS.
		if ( 'https' !== $parsed['scheme'] ) {
			return false;
		}

		// Must be exact domain match (not subdomain).
		if ( ! in_array( $parsed['host'], self::ALLOWED_HOSTS, true ) ) {
			return false;
		}

		// Additional validation for Fontsource GitHub URLs.
		if ( 'raw.githubusercontent.com' === $parsed['host'] ) {
			// Must be from the fontsource/font-files repository.
			if ( ! isset( $parsed['path'] ) || ! str_starts_with( $parsed['path'], '/fontsource/font-files/' ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Sanitizes a font filename to prevent path traversal.
	 *
	 * @param string $filename Original filename.
	 * @return string|false Sanitized filename or false if invalid.
	 */
	public function sanitize_filename( string $filename ) {
		// Remove any directory separators.
		$filename = basename( $filename );

		// Remove any potential null bytes.
		$filename = str_replace( chr( 0 ), '', $filename );

		// Validate extension - only allow known font formats.
		$extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		if ( ! in_array( $extension, self::ALLOWED_EXTENSIONS, true ) ) {
			return false;
		}

		// Validate filename pattern - only alphanumeric, dash, underscore, dot.
		if ( ! preg_match( '/^[a-zA-Z0-9\-\_\.]+$/', $filename ) ) {
			return false;
		}

		// Limit filename length.
		if ( strlen( $filename ) > self::MAX_FILENAME_LENGTH ) {
			return false;
		}

		return $filename;
	}

	/**
	 * Validates font file content using magic bytes.
	 *
	 * @param string $content File content to validate.
	 * @return bool True if valid font file.
	 */
	public function is_valid_font_content( string $content ): bool {
		if ( empty( $content ) || strlen( $content ) < 4 ) {
			return false;
		}

		$header = substr( $content, 0, 4 );

		foreach ( self::MAGIC_BYTES as $signature ) {
			if ( $header === $signature ) {
				return true;
			}
		}

		return false;
	}
}
