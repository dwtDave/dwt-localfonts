<?php

declare(strict_types=1);

namespace DWT\LocalFonts\Tests\Fixtures;

/**
 * Test fixtures for error scenarios
 *
 * Provides mock responses for testing error handling and failure scenarios.
 */
class ErrorScenarios {

	/**
	 * WP_Error for network timeout
	 */
	public static function getNetworkTimeoutError(): \WP_Error {
		return new \WP_Error(
			'http_request_failed',
			'Operation timed out after 15000 milliseconds with 0 out of 0 bytes received'
		);
	}

	/**
	 * wp_remote_get response with 404 status
	 */
	public static function getHTTP404Response(): array {
		return array(
			'response' => array(
				'code'    => 404,
				'message' => 'Not Found',
			),
			'body'     => '<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>Not Found</h1><p>The requested URL was not found on this server.</p></body></html>',
			'headers'  => array(),
			'cookies'  => array(),
		);
	}

	/**
	 * wp_remote_get response with 500 internal server error
	 */
	public static function getHTTP500Response(): array {
		return array(
			'response' => array(
				'code'    => 500,
				'message' => 'Internal Server Error',
			),
			'body'     => '<!DOCTYPE html><html><head><title>500 Internal Server Error</title></head><body><h1>Internal Server Error</h1></body></html>',
			'headers'  => array(),
			'cookies'  => array(),
		);
	}

	/**
	 * wp_remote_get response with 403 forbidden
	 */
	public static function getHTTP403Response(): array {
		return array(
			'response' => array(
				'code'    => 403,
				'message' => 'Forbidden',
			),
			'body'     => '<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>Forbidden</h1><p>You don\'t have permission to access this resource.</p></body></html>',
			'headers'  => array(),
			'cookies'  => array(),
		);
	}

	/**
	 * Simulated disk full error
	 */
	public static function getDiskFullError(): array {
		return array(
			'error_code' => 'disk_full',
			'message'    => 'No space left on device',
			'errno'      => 28, // ENOSPC
		);
	}

	/**
	 * Simulated write permission error
	 */
	public static function getPermissionDeniedError(): array {
		return array(
			'error_code' => 'permission_denied',
			'message'    => 'Permission denied',
			'errno'      => 13, // EACCES
		);
	}

	/**
	 * disk_free_space() returns < 10MB (5MB)
	 */
	public static function getLowDiskSpace(): int {
		return 5242880; // 5MB (below 10MB threshold)
	}

	/**
	 * disk_free_space() returns exactly 10MB (boundary)
	 */
	public static function getExactly10MBDiskSpace(): int {
		return 10485760; // Exactly 10MB
	}

	/**
	 * disk_free_space() returns just under 10MB
	 */
	public static function getJustUnder10MBDiskSpace(): int {
		return 10485759; // 10MB - 1 byte (should fail)
	}

	/**
	 * disk_free_space() returns ample space
	 */
	public static function getAmpleDiskSpace(): int {
		return 1073741824; // 1GB
	}

	/**
	 * is_writable() returns false (directory not writable)
	 */
	public static function getUnwritableDirectory(): bool {
		return false;
	}

	/**
	 * WP_Error for DNS resolution failure
	 */
	public static function getDNSResolutionError(): \WP_Error {
		return new \WP_Error(
			'http_request_failed',
			'Could not resolve host: fonts.gstatic.com'
		);
	}

	/**
	 * WP_Error for SSL certificate error
	 */
	public static function getSSLCertificateError(): \WP_Error {
		return new \WP_Error(
			'http_request_failed',
			'SSL certificate problem: unable to get local issuer certificate'
		);
	}

	/**
	 * wp_remote_get response with 429 Too Many Requests
	 */
	public static function getHTTP429Response(): array {
		return array(
			'response' => array(
				'code'    => 429,
				'message' => 'Too Many Requests',
			),
			'body'     => '{"error": "Rate limit exceeded. Please try again later."}',
			'headers'  => array(
				'retry-after' => '60',
			),
			'cookies'  => array(),
		);
	}

	/**
	 * wp_remote_get response with 503 Service Unavailable
	 */
	public static function getHTTP503Response(): array {
		return array(
			'response' => array(
				'code'    => 503,
				'message' => 'Service Unavailable',
			),
			'body'     => '<!DOCTYPE html><html><head><title>503 Service Unavailable</title></head><body><h1>Service Unavailable</h1><p>The server is temporarily unable to service your request.</p></body></html>',
			'headers'  => array(
				'retry-after' => '120',
			),
			'cookies'  => array(),
		);
	}

	/**
	 * wp_remote_get response with oversized Content-Length header
	 */
	public static function getOversizedContentLengthResponse(): array {
		return array(
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'headers'  => array(
				'content-length' => '2621440', // 2.5MB (exceeds 2MB limit)
				'content-type'   => 'font/woff2',
			),
			'body'     => '', // Body would be large, but we check headers first
			'cookies'  => array(),
		);
	}

	/**
	 * wp_remote_get response with missing Content-Length header
	 */
	public static function getMissingContentLengthResponse(): array {
		return array(
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'headers'  => array(
				'content-type' => 'font/woff2',
				// No content-length header
			),
			'body'     => str_repeat( 'X', 1024 ), // Some data
			'cookies'  => array(),
		);
	}

	/**
	 * WP_Error for connection refused
	 */
	public static function getConnectionRefusedError(): \WP_Error {
		return new \WP_Error(
			'http_request_failed',
			'Connection refused'
		);
	}

	/**
	 * WP_Error for connection reset
	 */
	public static function getConnectionResetError(): \WP_Error {
		return new \WP_Error(
			'http_request_failed',
			'Connection reset by peer'
		);
	}

	/**
	 * Simulated file write failure (generic I/O error)
	 */
	public static function getFileWriteError(): array {
		return array(
			'error_code' => 'file_write_failed',
			'message'    => 'Failed to write data to file',
			'errno'      => 5, // EIO
		);
	}

	/**
	 * Simulated directory creation failure
	 */
	public static function getDirectoryCreationError(): array {
		return array(
			'error_code' => 'mkdir_failed',
			'message'    => 'Failed to create directory',
			'errno'      => 13, // EACCES
		);
	}

	/**
	 * WP_Error for invalid URL
	 */
	public static function getInvalidURLError(): \WP_Error {
		return new \WP_Error(
			'http_request_failed',
			'A valid URL was not provided.'
		);
	}

	/**
	 * Simulated max_execution_time exceeded
	 */
	public static function getMaxExecutionTimeError(): array {
		return array(
			'error_code' => 'max_execution_time',
			'message'    => 'Maximum execution time of 30 seconds exceeded',
			'errno'      => 0,
		);
	}

	/**
	 * Simulated memory limit exceeded
	 */
	public static function getMemoryLimitError(): array {
		return array(
			'error_code' => 'memory_limit',
			'message'    => 'Allowed memory size of 134217728 bytes exhausted',
			'errno'      => 0,
		);
	}

	/**
	 * wp_remote_get response with redirect (301/302)
	 */
	public static function getHTTPRedirectResponse(): array {
		return array(
			'response' => array(
				'code'    => 301,
				'message' => 'Moved Permanently',
			),
			'headers'  => array(
				'location' => 'https://new-cdn.example.com/font.woff2',
			),
			'body'     => '',
			'cookies'  => array(),
		);
	}

	/**
	 * Successful wp_remote_get response (for comparison/baseline)
	 */
	public static function getSuccessfulHTTPResponse( string $body = 'test data' ): array {
		return array(
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'headers'  => array(
				'content-type'   => 'font/woff2',
				'content-length' => (string) strlen( $body ),
			),
			'body'     => $body,
			'cookies'  => array(),
		);
	}

	/**
	 * WP_Error for generic cURL error
	 */
	public static function getCurlError( int $curlErrorCode = 7 ): \WP_Error {
		$messages = array(
			6  => 'Could not resolve host',
			7  => 'Failed to connect to host',
			28 => 'Operation timed out',
			35 => 'SSL connect error',
			51 => 'SSL peer certificate or SSH remote key was not OK',
			52 => 'Server returned nothing (no headers, no data)',
			56 => 'Failure in receiving network data',
		);

		$message = $messages[ $curlErrorCode ] ?? "cURL error $curlErrorCode";

		return new \WP_Error( 'http_request_failed', $message );
	}
}
