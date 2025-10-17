<?php

declare(strict_types=1);

namespace DWT\LocalFonts\Services;

/**
 * Update Logger Service
 *
 * Logs update check and installation events to WordPress Options API.
 * Maintains a FIFO queue of last 10 update attempts for troubleshooting.
 *
 * @package DWT\LocalFonts\Services
 */
final class UpdateLogger
{
    private const OPTION_KEY = 'dwt_localfonts_update_log';
    private const MAX_ENTRIES = 10;

    /**
     * Log update check event
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     * @return void
     */
    public function logUpdateCheck(string $message, array $context = []): void
    {
        $this->addLogEntry('update_check', $message, $context);
    }

    /**
     * Log successful update installation
     *
     * @param string $fromVersion Previous version
     * @param string $toVersion New version
     * @return void
     */
    public function logUpdateSuccess(string $fromVersion, string $toVersion): void
    {
        $this->addLogEntry('success', 'Update completed successfully', [
            'from_version' => $fromVersion,
            'to_version' => $toVersion,
        ]);
    }

    /**
     * Log failed update installation
     *
     * @param string $fromVersion Previous version
     * @param string $toVersion Target version
     * @param string $errorCode Error code
     * @param string $errorMessage Error message
     * @return void
     */
    public function logUpdateFailure(
        string $fromVersion,
        string $toVersion,
        string $errorCode,
        string $errorMessage
    ): void {
        $this->addLogEntry('failure', $errorMessage, [
            'from_version' => $fromVersion,
            'to_version' => $toVersion,
            'error_code' => $errorCode,
        ]);
    }

    /**
     * Log rollback event
     *
     * @param string $targetVersion Version to rollback to
     * @param string $reason Reason for rollback
     * @return void
     */
    public function logRollback(string $targetVersion, string $reason): void
    {
        $this->addLogEntry('rollback', $reason, [
            'target_version' => $targetVersion,
        ]);
    }

    /**
     * Log rate limit hit
     *
     * @param string $retryAfter When to retry (timestamp or duration)
     * @return void
     */
    public function logRateLimit(string $retryAfter): void
    {
        $this->addLogEntry('rate_limit', 'GitHub API rate limit exceeded', [
            'retry_after' => $retryAfter,
        ]);
    }

    /**
     * Get all log entries
     *
     * @return array<array{timestamp: string, status: string, message: string, context: array<string, mixed>}>
     */
    public function getLogEntries(): array
    {
        return get_option(self::OPTION_KEY, []);
    }

    /**
     * Clear all log entries
     *
     * @return void
     */
    public function clearLog(): void
    {
        delete_option(self::OPTION_KEY);
    }

    /**
     * Add log entry to options
     *
     * @param string $status Log status (update_check, success, failure, rollback, rate_limit)
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     * @return void
     */
    private function addLogEntry(string $status, string $message, array $context = []): void
    {
        $log = $this->getLogEntries();

        // Add new entry
        $log[] = [
            'timestamp' => current_time('mysql'),
            'status' => $status,
            'message' => $message,
            'context' => $context,
        ];

        // Keep only last 10 entries (FIFO queue)
        if (count($log) > self::MAX_ENTRIES) {
            $log = array_slice($log, -self::MAX_ENTRIES);
        }

        // Save to options
        update_option(self::OPTION_KEY, $log);

        // Also log to error_log if WP_DEBUG_LOG is enabled
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log(sprintf(
                '[DWT LocalFonts Update] %s: %s %s',
                strtoupper($status),
                $message,
                !empty($context) ? json_encode($context) : ''
            ));
        }
    }
}
