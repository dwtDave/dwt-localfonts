<?php
/**
 * Redundancy report value object.
 *
 * @package DWT\LocalFonts
 */

declare(strict_types=1);

namespace DWT\LocalFonts\ValueObjects;

/**
 * Redundancy report aggregate.
 *
 * Collection of orphaned font files with aggregated statistics.
 */
class RedundancyReport {
	/**
	 * Array of orphaned file info objects.
	 *
	 * @var array<OrphanedFileInfo>
	 */
	public readonly array $orphaned_files;

	/**
	 * Total count of orphaned files.
	 *
	 * @var int
	 */
	public readonly int $total_count;

	/**
	 * Total size of orphaned files in bytes.
	 *
	 * @var int
	 */
	public readonly int $total_size_bytes;

	/**
	 * Unix timestamp when report was generated.
	 *
	 * @var int
	 */
	public readonly int $generated_at;

	/**
	 * Constructor.
	 *
	 * @param array<OrphanedFileInfo> $orphaned_files Array of orphaned file info objects.
	 */
	public function __construct( array $orphaned_files ) {
		// Sort by modified timestamp (newest first).
		usort(
			$orphaned_files,
			fn( OrphanedFileInfo $a, OrphanedFileInfo $b ): int => $b->modified_timestamp <=> $a->modified_timestamp
		);

		$this->orphaned_files   = $orphaned_files;
		$this->total_count      = count( $orphaned_files );
		$this->total_size_bytes = array_sum(
			array_map(
				fn( OrphanedFileInfo $file ): int => $file->size_bytes,
				$orphaned_files
			)
		);
		$this->generated_at     = time();
	}

	/**
	 * Convert to array representation.
	 *
	 * @return array{orphaned_files: array<int, array{filename: string, size_bytes: int, size_formatted: string, modified_timestamp: int, modified_date: string, file_path: string}>, total_count: int, total_size_bytes: int, total_size_formatted: string, generated_at: int}
	 */
	public function to_array(): array {
		return array(
			'orphaned_files'       => array_map( fn( OrphanedFileInfo $file ): array => $file->to_array(), $this->orphaned_files ),
			'total_count'          => $this->total_count,
			'total_size_bytes'     => $this->total_size_bytes,
			'total_size_formatted' => size_format( $this->total_size_bytes ),
			'generated_at'         => $this->generated_at,
		);
	}
}
