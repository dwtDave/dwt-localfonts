<?php
/**
 * Deletion result value object.
 *
 * @package DWT\LocalFonts
 */

declare(strict_types=1);

namespace DWT\LocalFonts\ValueObjects;

/**
 * Deletion operation result.
 *
 * Result object containing success/failure details for file deletion operations.
 */
class DeletionResult {
	/**
	 * Array of error details for failed deletions.
	 *
	 * @var array<DeletionError>
	 */
	public readonly array $errors;

	/**
	 * Constructor.
	 *
	 * @param int                  $deleted     Number of files successfully deleted.
	 * @param int                  $failed      Number of files that failed to delete.
	 * @param int                  $freed_bytes Total bytes freed by deletion.
	 * @param array<DeletionError> $errors      Array of error details for failed deletions.
	 *
	 * @throws \InvalidArgumentException If error count doesn't match failed count.
	 */
	public function __construct(
		public readonly int $deleted,
		public readonly int $failed,
		public readonly int $freed_bytes,
		array $errors
	) {
		if ( count( $errors ) !== $failed ) {
			throw new \InvalidArgumentException( 'Error count must match failed count' );
		}

		$this->errors = $errors;
	}

	/**
	 * Convert to array representation.
	 *
	 * @return array{deleted: int, failed: int, freed_bytes: int, freed_formatted: string, errors: array<int, array{filename: string, reason: string}>}
	 */
	public function to_array(): array {
		return array(
			'deleted'         => $this->deleted,
			'failed'          => $this->failed,
			'freed_bytes'     => $this->freed_bytes,
			'freed_formatted' => size_format( $this->freed_bytes ),
			'errors'          => array_map( fn( DeletionError $err ): array => $err->to_array(), $this->errors ),
		);
	}

	/**
	 * Check if deletion was completely successful.
	 *
	 * @return bool True if all files were deleted successfully.
	 */
	public function is_success(): bool {
		return 0 === $this->failed;
	}

	/**
	 * Check if deletion was partially successful.
	 *
	 * @return bool True if some files were deleted but some failed.
	 */
	public function is_partial_success(): bool {
		return $this->deleted > 0 && $this->failed > 0;
	}
}
