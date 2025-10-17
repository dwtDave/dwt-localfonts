<?php
/**
 * Unit tests for BackupMetadata value object
 *
 * Tests backup metadata tracking, integrity verification, and Options API serialization.
 *
 * @package DWT\LocalFonts
 * @since 1.1.0
 */

declare(strict_types=1);

namespace DWT\LocalFonts\Tests\Unit\ValueObjects;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use DWT\LocalFonts\ValueObjects\BackupMetadata;
use PHPUnit\Framework\TestCase;
use WP_Error;

final class BackupMetadataTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();
    }

    protected function tearDown(): void
    {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    public function test_it_constructs_with_valid_parameters(): void
    {
        $createdAt = new DateTimeImmutable('2025-10-15 10:30:00');

        $metadata = new BackupMetadata(
            backupPath: '/path/to/wp-content/uploads/plugin-backups/dwt-localfonts-1.0.0.zip',
            pluginVersion: '1.0.0',
            createdAt: $createdAt,
            backupSize: 1048576
        );

        $this->assertSame('/path/to/wp-content/uploads/plugin-backups/dwt-localfonts-1.0.0.zip', $metadata->backupPath);
        $this->assertSame('1.0.0', $metadata->pluginVersion);
        $this->assertSame($createdAt, $metadata->createdAt);
        $this->assertSame(1048576, $metadata->backupSize);
    }

    public function test_it_accepts_valid_semantic_versions(): void
    {
        $validVersions = ['1.0.0', '2.3.15', '10.20.30', '1.0.0-beta', '2.0.0-rc.1'];

        foreach ($validVersions as $version) {
            $metadata = new BackupMetadata(
                backupPath: '/path/to/backup.zip',
                pluginVersion: $version,
                createdAt: new DateTimeImmutable(),
                backupSize: 100
            );

            $this->assertSame($version, $metadata->pluginVersion);
        }
    }

    public function test_it_rejects_invalid_version_format(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid plugin version format');

        new BackupMetadata(
            backupPath: '/path/to/backup.zip',
            pluginVersion: 'v1.0.0', // Leading 'v' not allowed
            createdAt: new DateTimeImmutable(),
            backupSize: 100
        );
    }

    public function test_it_rejects_empty_backup_path(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Backup path cannot be empty');

        new BackupMetadata(
            backupPath: '', // Empty
            pluginVersion: '1.0.0',
            createdAt: new DateTimeImmutable(),
            backupSize: 100
        );
    }

    public function test_it_rejects_zero_backup_size(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Backup size must be positive');

        new BackupMetadata(
            backupPath: '/path/to/backup.zip',
            pluginVersion: '1.0.0',
            createdAt: new DateTimeImmutable(),
            backupSize: 0 // Zero
        );
    }

    public function test_it_rejects_negative_backup_size(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Backup size must be positive');

        new BackupMetadata(
            backupPath: '/path/to/backup.zip',
            pluginVersion: '1.0.0',
            createdAt: new DateTimeImmutable(),
            backupSize: -100 // Negative
        );
    }

    public function test_it_converts_to_option_data(): void
    {
        $createdAt = new DateTimeImmutable('2025-10-15 10:30:00');

        $metadata = new BackupMetadata(
            backupPath: '/path/to/wp-content/uploads/plugin-backups/dwt-localfonts-1.0.0.zip',
            pluginVersion: '1.0.0',
            createdAt: $createdAt,
            backupSize: 1048576
        );

        $data = $metadata->toOptionData();

        $this->assertIsArray($data);
        $this->assertSame('/path/to/wp-content/uploads/plugin-backups/dwt-localfonts-1.0.0.zip', $data['backup_path']);
        $this->assertSame('1.0.0', $data['plugin_version']);
        $this->assertSame('2025-10-15T10:30:00+00:00', $data['created_at']); // ISO 8601 format
        $this->assertSame(1048576, $data['backup_size']);
    }

    public function test_it_creates_from_option_data(): void
    {
        Functions\expect('file_exists')
            ->once()
            ->with('/path/to/wp-content/uploads/plugin-backups/dwt-localfonts-1.0.0.zip')
            ->andReturn(true);

        Functions\expect('is_readable')
            ->once()
            ->with('/path/to/wp-content/uploads/plugin-backups/dwt-localfonts-1.0.0.zip')
            ->andReturn(true);

        $data = [
            'backup_path' => '/path/to/wp-content/uploads/plugin-backups/dwt-localfonts-1.0.0.zip',
            'plugin_version' => '1.0.0',
            'created_at' => '2025-10-15T10:30:00+00:00',
            'backup_size' => 1048576,
        ];

        $metadata = BackupMetadata::fromOptionData($data);

        $this->assertInstanceOf(BackupMetadata::class, $metadata);
        $this->assertSame('/path/to/wp-content/uploads/plugin-backups/dwt-localfonts-1.0.0.zip', $metadata->backupPath);
        $this->assertSame('1.0.0', $metadata->pluginVersion);
        $this->assertInstanceOf(DateTimeImmutable::class, $metadata->createdAt);
        $this->assertSame(1048576, $metadata->backupSize);
    }

    public function test_it_returns_wp_error_for_missing_required_fields(): void
    {
        $data = [
            'backup_path' => '/path/to/backup.zip',
            // Missing plugin_version, created_at, backup_size
        ];

        $result = BackupMetadata::fromOptionData($data);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('missing_required_field', $result->get_error_code());
    }

    public function test_it_returns_wp_error_for_non_existent_backup_file(): void
    {
        Functions\expect('file_exists')
            ->once()
            ->with('/path/to/nonexistent.zip')
            ->andReturn(false);

        $data = [
            'backup_path' => '/path/to/nonexistent.zip',
            'plugin_version' => '1.0.0',
            'created_at' => '2025-10-15T10:30:00+00:00',
            'backup_size' => 1048576,
        ];

        $result = BackupMetadata::fromOptionData($data);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('backup_file_not_found', $result->get_error_code());
    }

    public function test_it_returns_wp_error_for_unreadable_backup_file(): void
    {
        Functions\expect('file_exists')
            ->once()
            ->with('/path/to/unreadable.zip')
            ->andReturn(true);

        Functions\expect('is_readable')
            ->once()
            ->with('/path/to/unreadable.zip')
            ->andReturn(false);

        $data = [
            'backup_path' => '/path/to/unreadable.zip',
            'plugin_version' => '1.0.0',
            'created_at' => '2025-10-15T10:30:00+00:00',
            'backup_size' => 1048576,
        ];

        $result = BackupMetadata::fromOptionData($data);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('backup_file_not_readable', $result->get_error_code());
    }

    public function test_it_returns_wp_error_for_invalid_datetime_format(): void
    {
        Functions\expect('file_exists')->once()->andReturn(true);
        Functions\expect('is_readable')->once()->andReturn(true);

        $data = [
            'backup_path' => '/path/to/backup.zip',
            'plugin_version' => '1.0.0',
            'created_at' => 'invalid-datetime',
            'backup_size' => 1048576,
        ];

        $result = BackupMetadata::fromOptionData($data);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('invalid_created_at', $result->get_error_code());
    }

    public function test_it_returns_wp_error_for_invalid_version_format(): void
    {
        Functions\expect('file_exists')->once()->andReturn(true);
        Functions\expect('is_readable')->once()->andReturn(true);

        $data = [
            'backup_path' => '/path/to/backup.zip',
            'plugin_version' => 'invalid-version',
            'created_at' => '2025-10-15T10:30:00+00:00',
            'backup_size' => 1048576,
        ];

        $result = BackupMetadata::fromOptionData($data);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('invalid_plugin_version', $result->get_error_code());
    }

    public function test_it_verifies_backup_integrity(): void
    {
        $createdAt = new DateTimeImmutable('2025-10-15 10:30:00');

        $metadata = new BackupMetadata(
            backupPath: '/path/to/backup.zip',
            pluginVersion: '1.0.0',
            createdAt: $createdAt,
            backupSize: 1048576
        );

        // Mock file_exists
        Functions\expect('file_exists')
            ->once()
            ->with('/path/to/backup.zip')
            ->andReturn(true);

        // Mock filesize
        Functions\expect('filesize')
            ->once()
            ->with('/path/to/backup.zip')
            ->andReturn(1048576);

        // Mock class_exists to skip ZIP verification (unit test doesn't need actual ZIP)
        Functions\expect('class_exists')
            ->once()
            ->with('ZipArchive')
            ->andReturn(false);

        $result = $metadata->verifyIntegrity();

        $this->assertTrue($result);
    }

    public function test_it_returns_wp_error_when_backup_file_missing_during_verification(): void
    {
        $metadata = new BackupMetadata(
            backupPath: '/path/to/backup.zip',
            pluginVersion: '1.0.0',
            createdAt: new DateTimeImmutable(),
            backupSize: 1048576
        );

        Functions\expect('file_exists')
            ->once()
            ->with('/path/to/backup.zip')
            ->andReturn(false);

        $result = $metadata->verifyIntegrity();

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('backup_file_missing', $result->get_error_code());
    }

    public function test_it_returns_wp_error_when_backup_size_mismatch(): void
    {
        $metadata = new BackupMetadata(
            backupPath: '/path/to/backup.zip',
            pluginVersion: '1.0.0',
            createdAt: new DateTimeImmutable(),
            backupSize: 1048576
        );

        Functions\expect('file_exists')
            ->once()
            ->with('/path/to/backup.zip')
            ->andReturn(true);

        Functions\expect('filesize')
            ->once()
            ->with('/path/to/backup.zip')
            ->andReturn(500000); // Different size

        $result = $metadata->verifyIntegrity();

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('backup_size_mismatch', $result->get_error_code());
    }

    public function test_it_accepts_absolute_paths(): void
    {
        $metadata = new BackupMetadata(
            backupPath: '/var/www/wp-content/uploads/plugin-backups/dwt-localfonts-1.0.0.zip',
            pluginVersion: '1.0.0',
            createdAt: new DateTimeImmutable(),
            backupSize: 1048576
        );

        $this->assertSame('/var/www/wp-content/uploads/plugin-backups/dwt-localfonts-1.0.0.zip', $metadata->backupPath);
    }
}
