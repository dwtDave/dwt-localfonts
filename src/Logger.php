<?php
/**
 * Structured Logging System
 *
 * @package DWT\LocalFonts
 */

declare(strict_types=1);

namespace DWT\LocalFonts;

/**
 * PSR-3 compatible logger for plugin operations.
 */
final class Logger {

	private const LOG_OPTION = 'dwt_local_fonts_logs';
	private const MAX_LOGS   = 100; // Keep last 100 log entries.

	/**
	 * Runtime errors that do not require immediate action.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context data.
	 */
	public static function error( string $message, array $context = array() ): void {
		self::log( 'error', $message, $context );
	}

	/**
	 * Exceptional occurrences that are not errors.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context data.
	 */
	public static function warning( string $message, array $context = array() ): void {
		self::log( 'warning', $message, $context );
	}

	/**
	 * Interesting events.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context data.
	 */
	public static function info( string $message, array $context = array() ): void {
		self::log( 'info', $message, $context );
	}

	/**
	 * Detailed debug information.
	 *
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context data.
	 */
	public static function debug( string $message, array $context = array() ): void {
		// Only log debug messages when WP_DEBUG is enabled.
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		self::log( 'debug', $message, $context );
	}

	/**
	 * Log with an arbitrary level.
	 *
	 * @param string               $level   Log level.
	 * @param string               $message Log message.
	 * @param array<string, mixed> $context Additional context data.
	 */
	private static function log( string $level, string $message, array $context = array() ): void {
		$logs = (array) \get_option( self::LOG_OPTION, array() );

		// Create log entry.
		$entry = array(
			'timestamp' => time(),
			'datetime'  => gmdate( 'Y-m-d H:i:s' ),
			'level'     => $level,
			'message'   => $message,
			'context'   => $context,
			'user_id'   => \get_current_user_id(),
			'ip'        => self::get_client_ip(),
			'url'       => isset( $_SERVER['REQUEST_URI'] ) ? \sanitize_text_field( \wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
		);

		// Add to logs array.
		$logs[] = $entry;

		// Keep only last MAX_LOGS entries.
		if ( count( $logs ) > self::MAX_LOGS ) {
			$logs = array_slice( $logs, -self::MAX_LOGS );
		}

		// Save logs.
		\update_option( self::LOG_OPTION, $logs, false ); // autoload = false.

		// Also log to PHP error log in debug mode.
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			$formatted = self::format_log_entry( $entry );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( $formatted );
		}
	}

	/**
	 * Get client IP address.
	 *
	 * @return string Client IP address.
	 */
	private static function get_client_ip(): string {
		$ip = '';

		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = \sanitize_text_field( \wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = \sanitize_text_field( \wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = \sanitize_text_field( \wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}

		return $ip;
	}

	/**
	 * Format log entry for display.
	 *
	 * @param array<string, mixed> $entry Log entry.
	 * @return string Formatted log entry.
	 */
	private static function format_log_entry( array $entry ): string {
		$formatted = sprintf(
			'[%s] [%s] %s',
			$entry['datetime'],
			strtoupper( $entry['level'] ),
			$entry['message']
		);

		if ( ! empty( $entry['context'] ) ) {
			$formatted .= ' ' . \wp_json_encode( $entry['context'] );
		}

		return $formatted;
	}

	/**
	 * Get all logs.
	 *
	 * @param string|null $level Optional. Filter by log level.
	 * @return array<int, array<string, mixed>> Array of log entries.
	 */
	public static function get_logs( ?string $level = null ): array {
		$logs = (array) \get_option( self::LOG_OPTION, array() );

		if ( $level ) {
			$logs = array_filter(
				$logs,
				fn( $entry ) => $entry['level'] === $level
			);
		}

		// Return in reverse order (newest first).
		return array_reverse( $logs );
	}

	/**
	 * Clear all logs.
	 */
	public static function clear_logs(): void {
		\delete_option( self::LOG_OPTION );
	}

	/**
	 * Get logs count by level.
	 *
	 * @return array<string, int> Array of counts by level.
	 */
	public static function get_log_counts(): array {
		$logs = (array) \get_option( self::LOG_OPTION, array() );

		$counts = array(
			'emergency' => 0,
			'alert'     => 0,
			'critical'  => 0,
			'error'     => 0,
			'warning'   => 0,
			'notice'    => 0,
			'info'      => 0,
			'debug'     => 0,
		);

		foreach ( $logs as $entry ) {
			$level = $entry['level'] ?? 'info';
			if ( isset( $counts[ $level ] ) ) {
				++$counts[ $level ];
			}
		}

		return $counts;
	}
}
