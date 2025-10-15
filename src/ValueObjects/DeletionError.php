<?php
/**
 * Deletion error value object.
 *
 * @package DWT\LocalFonts
 */

declare(strict_types=1);

namespace DWT\LocalFonts\ValueObjects;

/**
 * Deletion error details.
 *
 * Represents details about a failed file deletion operation.
 */
class DeletionError {
	/**
	 * Constructor.
	 *
	 * @param string $filename Filename that failed to delete.
	 * @param string $reason   User-friendly error message.
	 */
	public function __construct(
		public readonly string $filename,
		public readonly string $reason
	) {}

	/**
	 * Convert to array representation.
	 *
	 * @return array{filename: string, reason: string}
	 */
	public function to_array(): array {
		return array(
			'filename' => $this->filename,
			'reason'   => $this->reason,
		);
	}
}
