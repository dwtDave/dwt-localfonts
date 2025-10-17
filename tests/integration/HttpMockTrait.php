<?php
/**
 * HTTP Mock Trait for Integration Tests
 *
 * @package DWT\LocalFonts
 */

declare(strict_types=1);

namespace DWT\LocalFonts\Tests\Integration;

/**
 * Trait to provide HTTP request mocking capabilities for integration tests.
 *
 * This trait eliminates 404 errors in test output by intercepting HTTP requests
 * and providing appropriate mock responses.
 */
trait HttpMockTrait {

	/**
	 * Mock HTTP requests to prevent real network calls.
	 *
	 * @param array<string, callable> $url_handlers Map of URL patterns to response handlers.
	 * @param bool                    $suppress_logs Whether to suppress error_log output.
	 */
	protected function mockHttpRequests( array $url_handlers = [], bool $suppress_logs = true ): void {
		add_filter(
			'pre_http_request',
			function ( $preempt, $parsed_args, $url ) use ( $url_handlers ) {
				// Check for custom handlers
				foreach ( $url_handlers as $pattern => $handler ) {
					if ( strpos( $url, $pattern ) !== false ) {
						return $handler( $url, $parsed_args );
					}
				}

				// Default handlers for common test URLs
				return $this->getDefaultMockResponse( $url, $parsed_args );
			},
			10,
			3
		);

		// Optionally suppress error logging during tests for cleaner output
		if ( $suppress_logs ) {
			$this->suppressErrorLogging();
		}
	}

	/**
	 * Suppress error_log output during tests.
	 *
	 * This makes test output cleaner by hiding expected error logs
	 * (like 404s from testing error handling).
	 */
	private function suppressErrorLogging(): void {
		// Override error_log to suppress test noise
		add_filter(
			'wp_php_error_message',
			function () {
				return ''; // Suppress PHP error messages
			},
			10,
			0
		);

		// Suppress WordPress debug logging during tests
		if ( ! defined( 'WP_DEBUG_LOG' ) ) {
			define( 'WP_DEBUG_LOG', false );
		}
	}

	/**
	 * Get default mock response for common test URLs.
	 *
	 * @param string $url URL being requested.
	 * @param array  $parsed_args Request arguments.
	 * @return array|false Mock response array or false to proceed with real request.
	 */
	private function getDefaultMockResponse( string $url, array $parsed_args ) {
		// Mock GitHub API calls to test repositories
		if ( strpos( $url, 'api.github.com/repos/test-owner/test-repo' ) !== false ) {
			return [
				'headers'  => [],
				'body'     => wp_json_encode(
					[
						'message'             => 'Not Found',
						'documentation_url'   => 'https://docs.github.com/rest',
						'status'              => '404',
					]
				),
				'response' => [
					'code'    => 404,
					'message' => 'Not Found',
				],
				'cookies'  => [],
				'filename' => null,
			];
		}

		// Mock invalid font file downloads (test URLs)
		if ( strpos( $url, 'fonts.gstatic.com/s/test.woff2' ) !== false ) {
			return [
				'headers'  => [],
				'body'     => '',
				'response' => [
					'code'    => 404,
					'message' => 'Not Found',
				],
				'cookies'  => [],
				'filename' => null,
			];
		}

		// Mock GitHub release asset downloads for test repos
		if ( strpos( $url, 'github.com/test/test/releases/download' ) !== false ) {
			return [
				'headers'  => [],
				'body'     => '',
				'response' => [
					'code'    => 404,
					'message' => 'Not Found',
				],
				'cookies'  => [],
				'filename' => null,
			];
		}

		// Allow real HTTP requests for other URLs (e.g., actual Google Fonts)
		return false;
	}

	/**
	 * Remove all HTTP request mocks.
	 */
	protected function removeHttpMocks(): void {
		remove_all_filters( 'pre_http_request' );
	}

	/**
	 * Create a successful mock response for font file downloads.
	 *
	 * @param string $content Font file content (default: valid WOFF2 header).
	 * @return array Mock response array.
	 */
	protected function createSuccessFontResponse( string $content = '' ): array {
		if ( empty( $content ) ) {
			// Valid WOFF2 file header + padding
			$content = "\x77\x4F\x46\x32" . str_repeat( "\x00", 100 );
		}

		return [
			'headers'  => [
				'content-type' => 'font/woff2',
			],
			'body'     => $content,
			'response' => [
				'code'    => 200,
				'message' => 'OK',
			],
			'cookies'  => [],
			'filename' => null,
		];
	}

	/**
	 * Create a 404 mock response.
	 *
	 * @return array Mock response array.
	 */
	protected function create404Response(): array {
		return [
			'headers'  => [],
			'body'     => '',
			'response' => [
				'code'    => 404,
				'message' => 'Not Found',
			],
			'cookies'  => [],
			'filename' => null,
		];
	}
}
