<?php
/**
 * Orphaned file info value object.
 *
 * @package DWT\LocalFonts
 */

declare(strict_types=1);

namespace DWT\LocalFonts\ValueObjects;

/**
 * Orphaned font file metadata.
 *
 * Immutable value object representing metadata for a single orphaned font file.
 */
class OrphanedFileInfo {
	/**
	 * Constructor.
	 *
	 * @param string $filename           Base filename (e.g., "lato-latin-400-normal.woff2").
	 * @param int    $size_bytes         File size in bytes (>= 0).
	 * @param int    $modified_timestamp Unix timestamp of last modification.
	 * @param string $file_path          Absolute filesystem path.
	 *
	 * @throws \InvalidArgumentException If validation fails.
	 */
	public function __construct(
		public readonly string $filename,
		public readonly int $size_bytes,
		public readonly int $modified_timestamp,
		public readonly string $file_path
	) {
		$this->validate();
	}

	/**
	 * Validate value object properties.
	 *
	 * @throws \InvalidArgumentException If any property is invalid.
	 */
	private function validate(): void {
		$valid_extensions = array( 'woff', 'woff2', 'ttf', 'otf', 'eot' );
		$ext              = pathinfo( $this->filename, PATHINFO_EXTENSION );

		if ( ! in_array( $ext, $valid_extensions, true ) ) {
			throw new \InvalidArgumentException( 'Invalid font file extension: ' . esc_html( $ext ) );
		}

		if ( 'dwt-local-fonts.css' === $this->filename ) {
			throw new \InvalidArgumentException( 'CSS file cannot be orphaned' );
		}

		if ( $this->size_bytes < 0 ) {
			throw new \InvalidArgumentException( 'File size cannot be negative' );
		}

		if ( $this->modified_timestamp < 0 ) {
			throw new \InvalidArgumentException( 'Invalid timestamp' );
		}
	}

	/**
	 * Convert to array representation.
	 *
	 * @return array{filename: string, size_bytes: int, size_formatted: string, modified_timestamp: int, modified_date: string, file_path: string}
	 */
	public function to_array(): array {
		return array(
			'filename'           => $this->filename,
			'size_bytes'         => $this->size_bytes,
			'size_formatted'     => size_format( $this->size_bytes ),
			'modified_timestamp' => $this->modified_timestamp,
			'modified_date'      => date_i18n( get_option( 'date_format' ), $this->modified_timestamp ),
			'file_path'          => $this->file_path,
		);
	}
}
