<?php
/**
 * BackupMetadata Value Object
 *
 * Immutable metadata about plugin backup for rollback capability.
 *
 * @package DWT\LocalFonts
 * @since 1.1.0
 */

declare(strict_types=1);

namespace DWT\LocalFonts\ValueObjects;

use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use WP_Error;

/**
 * Value object representing backup metadata
 *
 * @since 1.1.0
 */
final class BackupMetadata
{
    /**
     * Absolute filesystem path to backup ZIP file
     *
     * @var string
     */
    public readonly string $backupPath;

    /**
     * Plugin version that was backed up
     *
     * @var string
     */
    public readonly string $pluginVersion;

    /**
     * Backup creation timestamp
     *
     * @var DateTimeImmutable
     */
    public readonly DateTimeImmutable $createdAt;

    /**
     * Backup file size in bytes
     *
     * @var int
     */
    public readonly int $backupSize;

    /**
     * Construct BackupMetadata
     *
     * @param string $backupPath Absolute path to backup ZIP file
     * @param string $pluginVersion Plugin version backed up (semantic versioning)
     * @param DateTimeImmutable $createdAt Backup creation timestamp
     * @param int $backupSize Backup file size in bytes
     *
     * @throws InvalidArgumentException If validation fails
     */
    public function __construct(
        string $backupPath,
        string $pluginVersion,
        DateTimeImmutable $createdAt,
        int $backupSize
    ) {
        // Validate backup path
        if ($backupPath === '') {
            throw new InvalidArgumentException('Backup path cannot be empty');
        }

        // Validate plugin version format (semantic versioning)
        if (!preg_match('/^[0-9]+\.[0-9]+\.[0-9]+(-[a-zA-Z0-9.]+)?$/', $pluginVersion)) {
            throw new InvalidArgumentException('Invalid plugin version format (must match semantic versioning: X.Y.Z or X.Y.Z-suffix)');
        }

        // Validate backup size
        if ($backupSize <= 0) {
            throw new InvalidArgumentException('Backup size must be positive');
        }

        $this->backupPath = $backupPath;
        $this->pluginVersion = $pluginVersion;
        $this->createdAt = $createdAt;
        $this->backupSize = $backupSize;
    }

    /**
     * Create from WordPress Options API data
     *
     * @param array<string, mixed> $optionData Decoded JSON from Options API
     *
     * @return self|WP_Error Metadata instance or error
     */
    public static function fromOptionData(array $optionData): self|WP_Error
    {
        // Check required fields
        $requiredFields = ['backup_path', 'plugin_version', 'created_at', 'backup_size'];
        foreach ($requiredFields as $field) {
            if (!isset($optionData[$field])) {
                return new WP_Error(
                    'missing_required_field',
                    sprintf('Missing required field: %s', $field),
                    ['field' => $field]
                );
            }
        }

        // Validate backup file exists
        if (!file_exists($optionData['backup_path'])) {
            return new WP_Error(
                'backup_file_not_found',
                sprintf('Backup file not found: %s', $optionData['backup_path']),
                ['path' => $optionData['backup_path']]
            );
        }

        // Validate backup file is readable
        if (!is_readable($optionData['backup_path'])) {
            return new WP_Error(
                'backup_file_not_readable',
                sprintf('Backup file is not readable: %s', $optionData['backup_path']),
                ['path' => $optionData['backup_path']]
            );
        }

        // Validate plugin version format
        if (!preg_match('/^[0-9]+\.[0-9]+\.[0-9]+(-[a-zA-Z0-9.]+)?$/', $optionData['plugin_version'])) {
            return new WP_Error(
                'invalid_plugin_version',
                sprintf('Invalid plugin version format: %s', $optionData['plugin_version']),
                ['version' => $optionData['plugin_version']]
            );
        }

        // Parse created_at datetime
        try {
            $createdAt = new DateTimeImmutable($optionData['created_at']);
        } catch (Exception $e) {
            return new WP_Error(
                'invalid_created_at',
                sprintf('Invalid created_at datetime: %s', $optionData['created_at']),
                ['exception' => $e->getMessage()]
            );
        }

        // Construct and return
        try {
            return new self(
                backupPath: $optionData['backup_path'],
                pluginVersion: $optionData['plugin_version'],
                createdAt: $createdAt,
                backupSize: (int) $optionData['backup_size']
            );
        } catch (InvalidArgumentException $e) {
            return new WP_Error(
                'invalid_backup_metadata',
                sprintf('Invalid backup metadata: %s', $e->getMessage()),
                ['exception' => $e->getMessage()]
            );
        }
    }

    /**
     * Convert to Options API storage format
     *
     * @return array<string, mixed> Associative array for JSON encoding
     */
    public function toOptionData(): array
    {
        return [
            'backup_path' => $this->backupPath,
            'plugin_version' => $this->pluginVersion,
            'created_at' => $this->createdAt->format(DateTimeImmutable::ATOM), // ISO 8601
            'backup_size' => $this->backupSize,
        ];
    }

    /**
     * Verify backup file integrity
     *
     * Checks that:
     * 1. Backup file exists
     * 2. File size matches metadata
     * 3. ZIP is extractable (optional, requires ZipArchive)
     *
     * @return true|WP_Error True if valid, WP_Error if invalid
     */
    public function verifyIntegrity(): true|WP_Error
    {
        // Check file exists
        if (!file_exists($this->backupPath)) {
            return new WP_Error(
                'backup_file_missing',
                sprintf('Backup file no longer exists: %s', $this->backupPath),
                ['path' => $this->backupPath]
            );
        }

        // Check file size matches
        $actualSize = filesize($this->backupPath);
        if ($actualSize !== $this->backupSize) {
            return new WP_Error(
                'backup_size_mismatch',
                sprintf(
                    'Backup file size mismatch: expected %d bytes, got %d bytes',
                    $this->backupSize,
                    $actualSize
                ),
                [
                    'expected_size' => $this->backupSize,
                    'actual_size' => $actualSize,
                ]
            );
        }

        // Optional: Verify ZIP is extractable (if ZipArchive available)
        if (class_exists('ZipArchive')) {
            $zip = new \ZipArchive();
            $opened = $zip->open($this->backupPath, \ZipArchive::RDONLY);

            if ($opened !== true) {
                return new WP_Error(
                    'backup_zip_corrupted',
                    sprintf('Backup ZIP file is corrupted or cannot be opened: %s', $this->backupPath),
                    ['path' => $this->backupPath, 'zip_error_code' => $opened]
                );
            }

            $zip->close();
        }

        return true;
    }
}
