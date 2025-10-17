<?php

declare(strict_types=1);

namespace DWT\LocalFonts\Services;

use DWT\LocalFonts\ValueObjects\GitHubRelease;
use DWT\LocalFonts\ValueObjects\BackupMetadata;
use WP_Error;
use ZipArchive;

/**
 * Update Installer Service
 *
 * Handles plugin update installation with backup and rollback capabilities.
 * Implements file locking for concurrent update prevention.
 *
 * @package DWT\LocalFonts\Services
 */
final class UpdateInstaller
{
    private const LOCK_FILE_SUFFIX = '-update.lock';
    private const BACKUP_DIR = 'plugin-backups';

    /**
     * Constructor
     *
     * @param string $pluginSlug Plugin slug
     * @param UpdateLogger|null $logger Update logger service (optional)
     */
    public function __construct(
        private readonly string $pluginSlug,
        private readonly ?UpdateLogger $logger = null
    ) {
    }

    /**
     * Install plugin update
     *
     * Process:
     * 1. Verify permissions and acquire lock
     * 2. Download package from GitHub
     * 3. Verify package integrity
     * 4. Create backup of current version
     * 5. Extract and install new version
     * 6. Delete previous backup
     * 7. Release lock
     *
     * On failure: Automatically rollback to backup
     *
     * @param GitHubRelease $release GitHub release to install
     * @return true|WP_Error True on success, WP_Error on failure
     */
    public function installUpdate(GitHubRelease $release): true|WP_Error
    {
        // 1. Verify permissions
        if (!current_user_can('update_plugins')) {
            return new WP_Error(
                'insufficient_permissions',
                'You do not have permission to update plugins',
                ['required_capability' => 'update_plugins']
            );
        }

        if (!wp_is_file_mod_allowed('plugin_update')) {
            return new WP_Error(
                'file_mod_disabled',
                'File modifications are disabled on this site',
                ['constant' => 'DISALLOW_FILE_MODS']
            );
        }

        // Verify package URL exists
        if ($release->zipAssetUrl === null) {
            return new WP_Error(
                'no_package_url',
                'No download package URL found in release',
                ['version' => $release->version]
            );
        }

        // 2. Acquire file lock
        $lockFile = $this->getLockFilePath();
        $lockHandle = fopen($lockFile, 'c');

        if ($lockHandle === false) {
            return new WP_Error(
                'lock_failed',
                'Failed to create lock file',
                ['lock_file' => $lockFile]
            );
        }

        // Try to acquire exclusive lock (non-blocking)
        if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
            fclose($lockHandle);
            return new WP_Error(
                'update_in_progress',
                'Another update is already in progress',
                ['lock_file' => $lockFile]
            );
        }

        // Get current plugin version for logging
        $currentVersion = $this->getCurrentPluginVersion();

        try {
            // 3. Download package
            $packagePath = $this->downloadPackage($release);
            if (is_wp_error($packagePath)) {
                throw new \Exception($packagePath->get_error_message());
            }

            // 4. Verify package integrity
            $integrityCheck = $this->verifyPackageIntegrity($packagePath, $release->zipAssetSize);
            if (is_wp_error($integrityCheck)) {
                unlink($packagePath);
                throw new \Exception($integrityCheck->get_error_message());
            }

            // 5. Create backup
            $pluginPath = WP_PLUGIN_DIR . '/' . $this->pluginSlug;
            $backup = $this->createBackup($pluginPath, $currentVersion);
            if (is_wp_error($backup)) {
                unlink($packagePath);
                throw new \Exception($backup->get_error_message());
            }

            // 6. Extract and install
            $installResult = $this->extractAndInstall($packagePath, $pluginPath);
            if (is_wp_error($installResult)) {
                // Rollback on failure
                $rollbackResult = $this->rollbackFromBackup($backup);
                unlink($packagePath);

                if (is_wp_error($rollbackResult)) {
                    // Rollback failed - critical error
                    $this->logger?->logUpdateFailure(
                        $currentVersion,
                        $release->version,
                        'rollback_failed',
                        'Installation failed and rollback also failed: ' . $rollbackResult->get_error_message()
                    );

                    throw new \Exception(
                        'Installation failed and rollback also failed: ' . $rollbackResult->get_error_message()
                    );
                }

                // Rollback succeeded
                $this->logger?->logRollback($currentVersion, 'Installation failed: ' . $installResult->get_error_message());
                throw new \Exception($installResult->get_error_message());
            }

            // 7. Delete previous backup (keep only most recent)
            $this->deletePreviousBackups($backup);

            // 8. Cleanup
            unlink($packagePath);

            // 9. Log success
            $this->logger?->logUpdateSuccess($currentVersion, $release->version);

            return true;

        } catch (\Exception $e) {
            // Log failure
            $this->logger?->logUpdateFailure(
                $currentVersion,
                $release->version,
                'installation_failed',
                $e->getMessage()
            );

            return new WP_Error(
                'installation_failed',
                $e->getMessage(),
                ['from_version' => $currentVersion, 'to_version' => $release->version]
            );

        } finally {
            // Always release lock
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
            if (file_exists($lockFile)) {
                unlink($lockFile);
            }
        }
    }

    /**
     * Rollback to previous version
     *
     * Public method for manual rollback.
     *
     * @return true|WP_Error
     */
    public function rollbackToPreviousVersion(): true|WP_Error
    {
        // Verify permissions
        if (!current_user_can('update_plugins')) {
            return new WP_Error(
                'insufficient_permissions',
                'You do not have permission to rollback plugins'
            );
        }

        // Get backup metadata
        $backupMetadata = get_option('dwt_localfonts_backup_metadata');
        if (!$backupMetadata || !is_array($backupMetadata)) {
            return new WP_Error(
                'no_backup_found',
                'No backup available for rollback'
            );
        }

        try {
            $backup = new BackupMetadata(
                backupPath: $backupMetadata['backup_path'],
                pluginVersion: $backupMetadata['plugin_version'],
                createdAt: new \DateTimeImmutable($backupMetadata['created_at']),
                backupSize: $backupMetadata['backup_size']
            );

            $result = $this->rollbackFromBackup($backup);
            if (is_wp_error($result)) {
                return $result;
            }

            $this->logger?->logRollback($backup->pluginVersion, 'Manual rollback requested');

            return true;

        } catch (\Exception $e) {
            return new WP_Error(
                'rollback_failed',
                $e->getMessage()
            );
        }
    }

    /**
     * Download package from GitHub
     *
     * @param GitHubRelease $release Release with package URL
     * @return string|WP_Error Path to downloaded file or error
     */
    private function downloadPackage(GitHubRelease $release): string|WP_Error
    {
        $tempFile = wp_tempnam($this->pluginSlug . '-update');

        $response = wp_remote_get($release->zipAssetUrl, [
            'timeout' => 300, // 5 minutes
            'stream' => true,
            'filename' => $tempFile,
        ]);

        if (is_wp_error($response)) {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            return $response;
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        if ($statusCode !== 200) {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
            return new WP_Error(
                'download_failed',
                sprintf('Failed to download package: HTTP %d', $statusCode),
                ['status_code' => $statusCode, 'url' => $release->zipAssetUrl]
            );
        }

        return $tempFile;
    }

    /**
     * Verify package integrity
     *
     * @param string $packagePath Path to downloaded package
     * @param int $expectedSize Expected file size in bytes
     * @return true|WP_Error
     */
    private function verifyPackageIntegrity(string $packagePath, int $expectedSize): true|WP_Error
    {
        // Verify file exists
        if (!file_exists($packagePath)) {
            return new WP_Error(
                'package_not_found',
                'Downloaded package file not found'
            );
        }

        // Verify file size matches expected size
        $actualSize = filesize($packagePath);
        if ($actualSize !== $expectedSize) {
            return new WP_Error(
                'size_mismatch',
                sprintf('Package size mismatch: expected %d bytes, got %d bytes', $expectedSize, $actualSize),
                ['expected' => $expectedSize, 'actual' => $actualSize]
            );
        }

        // Test ZIP extraction
        $zip = new ZipArchive();
        $opened = $zip->open($packagePath, ZipArchive::CHECKCONS);

        if ($opened !== true) {
            return new WP_Error(
                'invalid_zip',
                'Package is not a valid ZIP archive or is corrupted',
                ['zip_error_code' => $opened]
            );
        }

        $zip->close();

        return true;
    }

    /**
     * Create backup of current plugin
     *
     * @param string $pluginPath Path to plugin directory
     * @param string $version Current plugin version
     * @return BackupMetadata|WP_Error
     */
    private function createBackup(string $pluginPath, string $version): BackupMetadata|WP_Error
    {
        // Ensure backup directory exists
        $backupDir = WP_CONTENT_DIR . '/uploads/' . self::BACKUP_DIR;
        if (!file_exists($backupDir)) {
            wp_mkdir_p($backupDir);
        }

        // Create backup ZIP
        $backupFilename = sprintf('%s-%s.zip', $this->pluginSlug, $version);
        $backupPath = $backupDir . '/' . $backupFilename;

        $zip = new ZipArchive();
        $opened = $zip->open($backupPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($opened !== true) {
            return new WP_Error(
                'backup_failed',
                'Failed to create backup ZIP file',
                ['zip_error_code' => $opened, 'backup_path' => $backupPath]
            );
        }

        // Add plugin files to ZIP
        $this->addDirectoryToZip($zip, $pluginPath, $this->pluginSlug);
        $zip->close();

        // Create metadata
        $metadata = new BackupMetadata(
            backupPath: $backupPath,
            pluginVersion: $version,
            createdAt: new \DateTimeImmutable(),
            backupSize: filesize($backupPath)
        );

        // Save metadata to options
        update_option('dwt_localfonts_backup_metadata', [
            'backup_path' => $metadata->backupPath,
            'plugin_version' => $metadata->pluginVersion,
            'created_at' => $metadata->createdAt->format('c'),
            'backup_size' => $metadata->backupSize,
        ]);

        return $metadata;
    }

    /**
     * Extract and install package
     *
     * @param string $packagePath Path to ZIP package
     * @param string $pluginPath Path to plugin directory
     * @return true|WP_Error
     */
    private function extractAndInstall(string $packagePath, string $pluginPath): true|WP_Error
    {
        // Delete existing plugin directory
        $this->deleteDirectory($pluginPath);

        // Extract ZIP
        $zip = new ZipArchive();
        $opened = $zip->open($packagePath);

        if ($opened !== true) {
            return new WP_Error(
                'extraction_failed',
                'Failed to open package for extraction',
                ['zip_error_code' => $opened]
            );
        }

        // Extract to plugin directory
        $extracted = $zip->extractTo(dirname($pluginPath));
        $zip->close();

        if (!$extracted) {
            return new WP_Error(
                'extraction_failed',
                'Failed to extract package to plugin directory'
            );
        }

        // Verify plugin directory exists after extraction
        if (!file_exists($pluginPath)) {
            return new WP_Error(
                'plugin_dir_not_found',
                'Plugin directory not found after extraction'
            );
        }

        return true;
    }

    /**
     * Rollback from backup
     *
     * @param BackupMetadata $backup Backup metadata
     * @return true|WP_Error
     */
    private function rollbackFromBackup(BackupMetadata $backup): true|WP_Error
    {
        // Verify backup file exists
        if (!file_exists($backup->backupPath)) {
            return new WP_Error(
                'backup_not_found',
                'Backup file not found',
                ['backup_path' => $backup->backupPath]
            );
        }

        $pluginPath = WP_PLUGIN_DIR . '/' . $this->pluginSlug;

        // Delete current (failed) installation
        $this->deleteDirectory($pluginPath);

        // Extract backup
        $zip = new ZipArchive();
        $opened = $zip->open($backup->backupPath);

        if ($opened !== true) {
            return new WP_Error(
                'rollback_failed',
                'Failed to open backup file',
                ['zip_error_code' => $opened]
            );
        }

        $extracted = $zip->extractTo(dirname($pluginPath));
        $zip->close();

        if (!$extracted) {
            return new WP_Error(
                'rollback_failed',
                'Failed to extract backup to plugin directory'
            );
        }

        return true;
    }

    /**
     * Delete previous backups (keep only most recent)
     *
     * @param BackupMetadata $currentBackup Current backup to keep
     * @return void
     */
    private function deletePreviousBackups(BackupMetadata $currentBackup): void
    {
        $backupDir = WP_CONTENT_DIR . '/uploads/' . self::BACKUP_DIR;
        $pattern = $backupDir . '/' . $this->pluginSlug . '-*.zip';

        $backupFiles = glob($pattern);
        if ($backupFiles === false) {
            return;
        }

        foreach ($backupFiles as $file) {
            // Don't delete the current backup
            if ($file === $currentBackup->backupPath) {
                continue;
            }

            // Delete old backup
            unlink($file);
        }
    }

    /**
     * Add directory contents to ZIP recursively
     *
     * @param ZipArchive $zip ZIP archive
     * @param string $dirPath Directory path
     * @param string $zipPath Path in ZIP
     * @return void
     */
    private function addDirectoryToZip(ZipArchive $zip, string $dirPath, string $zipPath): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dirPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $itemPath = $item->getPathname();
            $relativePath = $zipPath . '/' . substr($itemPath, strlen($dirPath) + 1);

            if ($item->isDir()) {
                $zip->addEmptyDir($relativePath);
            } else {
                $zip->addFile($itemPath, $relativePath);
            }
        }
    }

    /**
     * Delete directory recursively
     *
     * @param string $dirPath Directory path
     * @return void
     */
    private function deleteDirectory(string $dirPath): void
    {
        if (!file_exists($dirPath)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dirPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dirPath);
    }

    /**
     * Get lock file path
     *
     * @return string
     */
    private function getLockFilePath(): string
    {
        $backupDir = WP_CONTENT_DIR . '/uploads/' . self::BACKUP_DIR;
        if (!file_exists($backupDir)) {
            wp_mkdir_p($backupDir);
        }

        return $backupDir . '/.' . $this->pluginSlug . self::LOCK_FILE_SUFFIX;
    }

    /**
     * Get current plugin version
     *
     * @return string
     */
    private function getCurrentPluginVersion(): string
    {
        if (defined('DWT_LOCALFONTS_VERSION')) {
            return DWT_LOCALFONTS_VERSION;
        }

        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $pluginFile = WP_PLUGIN_DIR . '/' . $this->pluginSlug . '/' . $this->pluginSlug . '.php';
        if (file_exists($pluginFile)) {
            $pluginData = get_plugin_data($pluginFile);
            return $pluginData['Version'] ?? '0.0.0';
        }

        return '0.0.0';
    }
}
