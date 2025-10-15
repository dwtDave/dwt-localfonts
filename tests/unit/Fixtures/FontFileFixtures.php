<?php

declare(strict_types=1);

namespace DWT\LocalFonts\Tests\Fixtures;

/**
 * Test fixtures for font file binary data
 *
 * Provides WOFF2 binary fixtures for testing file validation and corruption detection.
 */
class FontFileFixtures {

	/**
	 * WOFF2 magic bytes: wOF2 (0x774F4632)
	 *
	 * Valid WOFF2 files must start with these 4 bytes
	 */
	public static function getValidWOFF2Header(): string {
		return "\x77\x4F\x46\x32"; // "wOF2" in hex
	}

	/**
	 * Complete minimal valid WOFF2 file (~50KB)
	 *
	 * This is a minimal but valid WOFF2 font file that can be used for testing
	 */
	public static function getValidSmallWOFF2(): string {
		// Start with valid WOFF2 header
		$header = self::getValidWOFF2Header();

		// Add minimal WOFF2 structure (simplified for testing)
		// Real WOFF2 files are more complex, but this is enough for validation testing
		$data  = $header;
		$data .= "\x00\x01\x00\x00"; // Flavor (TrueType)
		$data .= "\x00\x00\x00\x2C"; // Length (44 bytes for minimal header)
		$data .= "\x00\x00"; // numTables
		$data .= "\x00\x00"; // Reserved
		$data .= "\x00\x00\xC8\x00"; // totalSfntSize
		$data .= "\x00\x00\x03\xE8"; // totalCompressedSize (1000 bytes)
		$data .= "\x00\x01\x00\x00"; // majorVersion
		$data .= "\x00\x00\x00\x00"; // minorVersion
		$data .= "\x00\x00\x00\x00"; // metaOffset
		$data .= "\x00\x00\x00\x00"; // metaLength
		$data .= "\x00\x00\x00\x00"; // metaOrigLength
		$data .= "\x00\x00\x00\x00"; // privOffset
		$data .= "\x00\x00\x00\x00"; // privLength

		// Pad to make it ~50KB for realistic size
		$data .= str_repeat( "\x00", 51200 - strlen( $data ) );

		return $data;
	}

	/**
	 * WOFF2 file with corrupted/invalid magic bytes
	 */
	public static function getCorruptedHeader(): string {
		return "\x00\x00\x00\x00"; // Invalid magic bytes
	}

	/**
	 * Empty file (zero bytes)
	 */
	public static function getEmptyFile(): string {
		return '';
	}

	/**
	 * File exceeding 2MB limit (2,621,440 bytes = 2.5MB)
	 */
	public static function getOversizedFile(): string {
		// 2.5MB file (exceeds 2MB limit)
		$header = self::getValidWOFF2Header();
		return $header . str_repeat( 'X', 2621440 - strlen( $header ) );
	}

	/**
	 * Truncated WOFF2 file (partial download simulation)
	 */
	public static function getPartialDownload(): string {
		// Valid header but incomplete file (truncated at 100 bytes)
		$completeFile = self::getValidSmallWOFF2();
		return substr( $completeFile, 0, 100 );
	}

	/**
	 * TrueType font file (valid font, but wrong format - should be WOFF2)
	 */
	public static function getWrongFormatTTF(): string {
		// TTF magic bytes: 0x00010000 or "true" or "typ1"
		$ttfHeader = "\x00\x01\x00\x00";
		$data      = $ttfHeader;
		$data     .= "\x00\x10"; // numTables
		$data     .= "\x00\x80"; // searchRange
		$data     .= "\x00\x03"; // entrySelector
		$data     .= "\x00\x60"; // rangeShift

		// Pad to reasonable size
		$data .= str_repeat( "\x00", 10240 );

		return $data;
	}

	/**
	 * File with malicious payload attempt in metadata
	 */
	public static function getMaliciousPayload(): string {
		// Valid WOFF2 header but with suspicious embedded script-like content
		$header = self::getValidWOFF2Header();
		$data   = $header;

		// Add minimal structure
		$data .= "\x00\x01\x00\x00";
		$data .= "\x00\x00\x00\x2C";

		// Embed suspicious content (shouldn't be executed, but tests validation)
		$data .= "<script>alert('xss')</script>";
		$data .= "<?php system('rm -rf /'); ?>";

		// Pad to normal size
		$data .= str_repeat( "\x00", 10240 );

		return $data;
	}

	/**
	 * File at exactly 2MB (boundary test - should pass)
	 */
	public static function getExactly2MBFile(): string {
		// Exactly 2MB = 2,097,152 bytes (should pass validation)
		$header = self::getValidWOFF2Header();
		return $header . str_repeat( "\x00", 2097152 - strlen( $header ) );
	}

	/**
	 * File just over 2MB (boundary test - should fail)
	 */
	public static function getJustOver2MBFile(): string {
		// 2MB + 1 byte = 2,097,153 bytes (should fail validation)
		$header = self::getValidWOFF2Header();
		return $header . str_repeat( "\x00", 2097153 - strlen( $header ) );
	}

	/**
	 * WOFF (version 1) file - valid font but wrong WOFF version
	 */
	public static function getWOFF1File(): string {
		// WOFF1 magic bytes: wOFF (0x774F4646)
		return "\x77\x4F\x46\x46" . str_repeat( "\x00", 10240 );
	}

	/**
	 * File with null bytes only (no valid header)
	 */
	public static function getNullBytesFile(): string {
		return str_repeat( "\x00", 1024 );
	}

	/**
	 * File with random binary data (not a font)
	 */
	public static function getRandomBinaryData(): string {
		// Deterministic "random" data for consistent testing
		$data = '';
		for ( $i = 0; $i < 1024; $i++ ) {
			$data .= chr( ( $i * 37 ) % 256 ); // Pseudo-random but deterministic
		}
		return $data;
	}

	/**
	 * Valid WOFF2 header with minimal size (edge case - very small file)
	 */
	public static function getMinimalValidWOFF2(): string {
		// Absolute minimum WOFF2 structure (44 bytes header)
		$data  = self::getValidWOFF2Header();
		$data .= "\x00\x01\x00\x00"; // Flavor
		$data .= "\x00\x00\x00\x2C"; // Length (44 bytes)
		$data .= "\x00\x00"; // numTables
		$data .= "\x00\x00"; // Reserved
		$data .= "\x00\x00\x00\x00"; // totalSfntSize
		$data .= "\x00\x00\x00\x00"; // totalCompressedSize
		$data .= "\x00\x01\x00\x00"; // majorVersion
		$data .= "\x00\x00\x00\x00"; // minorVersion
		$data .= "\x00\x00\x00\x00"; // metaOffset
		$data .= "\x00\x00\x00\x00"; // metaLength
		$data .= "\x00\x00\x00\x00"; // metaOrigLength
		$data .= "\x00\x00\x00\x00"; // privOffset
		$data .= "\x00\x00\x00\x00"; // privLength

		return $data;
	}

	/**
	 * Get file size for a given fixture
	 *
	 * @param string $fixtureData Binary data from any fixture method
	 * @return int Size in bytes
	 */
	public static function getFileSize( string $fixtureData ): int {
		return strlen( $fixtureData );
	}

	/**
	 * Check if data has valid WOFF2 magic bytes
	 *
	 * @param string $data Binary data to check
	 * @return bool True if starts with wOF2
	 */
	public static function hasValidWOFF2Magic( string $data ): bool {
		if ( strlen( $data ) < 4 ) {
			return false;
		}

		return substr( $data, 0, 4 ) === self::getValidWOFF2Header();
	}
}
