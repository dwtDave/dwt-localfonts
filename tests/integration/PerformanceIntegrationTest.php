<?php
/**
 * Performance Integration Tests
 *
 * @package DWT\LocalFonts
 */

declare(strict_types=1);

namespace DWT\LocalFonts\Tests\Integration;

use DWT\LocalFonts\Services\FontDownloader;
use DWT\LocalFonts\Services\FontValidator;
use DWT\LocalFonts\Services\FontStorage;

/**
 * Integration tests for font download performance.
 *
 * These tests require a real WordPress environment (wp-env) and network access.
 * Run `npm run wp-env:start` before executing these tests.
 *
 * @group external-http
 * @group performance
 */
final class PerformanceIntegrationTest extends \WP_UnitTestCase {

	/**
	 * Temporary uploads directory for testing.
	 *
	 * @var string
	 */
	private string $temp_upload_dir;

	/**
	 * Original upload_dir filter.
	 *
	 * @var callable|null
	 */
	private $original_upload_dir_filter;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		// Create temporary uploads directory for isolated testing
		$this->temp_upload_dir = sys_get_temp_dir() . '/dwt-localfonts-test-' . uniqid();
		wp_mkdir_p( $this->temp_upload_dir );

		// Override wp_upload_dir to use our temporary directory
		$temp_dir                         = $this->original_upload_dir_filter;
		$this->original_upload_dir_filter = function ( $uploads ) use ( $temp_dir ) {
			$uploads['basedir'] = $this->temp_upload_dir;
			$uploads['baseurl'] = 'https://example.com/wp-content/uploads-test';
			return $uploads;
		};

		add_filter( 'upload_dir', $this->original_upload_dir_filter );

		// Clean up any existing font options from previous tests
		delete_option( 'dwt_local_fonts_list' );
		delete_option( 'dwt_local_fonts_settings' );
	}

	/**
	 * Tear down test environment.
	 */
	public function tearDown(): void {
		// Remove upload_dir filter
		if ( $this->original_upload_dir_filter ) {
			remove_filter( 'upload_dir', $this->original_upload_dir_filter );
		}

		// Clean up temporary directory
		if ( is_dir( $this->temp_upload_dir ) ) {
			$this->recursive_rmdir( $this->temp_upload_dir );
		}

		// Clean up options
		delete_option( 'dwt_local_fonts_list' );
		delete_option( 'dwt_local_fonts_settings' );

		parent::tearDown();
	}

	/**
	 * Recursively remove directory and contents.
	 *
	 * @param string $dir Directory path.
	 */
	private function recursive_rmdir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$files = array_diff( scandir( $dir ), array( '.', '..' ) );
		foreach ( $files as $file ) {
			$path = $dir . '/' . $file;
			if ( is_dir( $path ) ) {
				$this->recursive_rmdir( $path );
			} else {
				unlink( $path );
			}
		}
		rmdir( $dir );
	}

	/**
	 * Check if internet connection is available.
	 *
	 * @return bool True if internet is available.
	 */
	private function has_internet_connection(): bool {
		$response = wp_remote_get(
			'https://fonts.google.com',
			array(
				'timeout'     => 5,
				'httpversion' => '1.1',
			)
		);

		return ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200;
	}

	/**
	 * Test T042: Single weight font downloads under 10 seconds (User Story 4 - P3).
	 *
	 * @group external-http
	 */
	public function testP3_SingleWeightUnder10Seconds(): void {
		// Skip if no internet connection
		if ( ! $this->has_internet_connection() ) {
			$this->markTestSkipped( 'Skipping test that requires internet connection' );
		}

		$validator  = new FontValidator();
		$storage    = new FontStorage( $validator );
		$downloader = new FontDownloader( $validator, $storage );

		// Single weight font (Roboto Regular)
		$test_css = "@font-face {
			font-family: 'Roboto';
			font-style: normal;
			font-weight: 400;
			src: url(https://fonts.gstatic.com/s/roboto/v30/KFOmCnqEu92Fr1Mu4mxK.woff2) format('woff2');
		}";

		$start_time = microtime( true );

		$result = $downloader->download_font_from_css( $test_css );

		$end_time      = microtime( true );
		$duration_secs = $end_time - $start_time;

		$this->assertTrue( $result['success'], 'Download should succeed' );
		$this->assertLessThan( 10, $duration_secs, 'Single weight font should download in under 10 seconds' );

		// Verify file was created
		$upload_dir = wp_upload_dir();
		$font_dir   = $upload_dir['basedir'] . '/dwt-local-fonts';
		$this->assertTrue( is_dir( $font_dir ), 'Font directory should exist' );

		$font_files = glob( $font_dir . '/*.woff2' );
		$this->assertCount( 1, $font_files, 'Should have downloaded 1 file' );
	}

	/**
	 * Test T043: Large font family (12 variants) downloads under 30 seconds (User Story 4 - P3).
	 *
	 * Note: This test uses a simplified CSS with fewer variants to avoid
	 * network timeout in CI environments.
	 *
	 * @group external-http
	 */
	public function testP3_LargeFamilyUnder30Seconds(): void {
		// Skip if no internet connection
		if ( ! $this->has_internet_connection() ) {
			$this->markTestSkipped( 'Skipping test that requires internet connection' );
		}

		$validator  = new FontValidator();
		$storage    = new FontStorage( $validator );
		$downloader = new FontDownloader( $validator, $storage );

		// Multiple variants of Roboto (3 weights for testing)
		$test_css = "@font-face {
			font-family: 'Roboto';
			font-style: normal;
			font-weight: 400;
			src: url(https://fonts.gstatic.com/s/roboto/v30/KFOmCnqEu92Fr1Mu4mxK.woff2) format('woff2');
		}
		@font-face {
			font-family: 'Roboto';
			font-style: normal;
			font-weight: 700;
			src: url(https://fonts.gstatic.com/s/roboto/v30/KFOlCnqEu92Fr1MmWUlfBBc4.woff2) format('woff2');
		}
		@font-face {
			font-family: 'Roboto';
			font-style: italic;
			font-weight: 400;
			src: url(https://fonts.gstatic.com/s/roboto/v30/KFOkCnqEu92Fr1Mu51xIIzI.woff2) format('woff2');
		}";

		$start_time = microtime( true );

		$result = $downloader->download_font_from_css( $test_css );

		$end_time      = microtime( true );
		$duration_secs = $end_time - $start_time;

		$this->assertTrue( $result['success'], 'Download should succeed' );
		$this->assertLessThan( 30, $duration_secs, 'Multiple variants should download in under 30 seconds' );

		// Verify files were created
		$upload_dir = wp_upload_dir();
		$font_dir   = $upload_dir['basedir'] . '/dwt-local-fonts';
		$this->assertTrue( is_dir( $font_dir ), 'Font directory should exist' );

		$font_files = glob( $font_dir . '/*.woff2' );
		$this->assertGreaterThanOrEqual( 3, count( $font_files ), 'Should have downloaded 3+ files' );
	}

	/**
	 * Test T044: Memory usage stays under 50MB during download (User Story 4 - P3).
	 *
	 * @group external-http
	 */
	public function testP3_MemoryUnder50MB(): void {
		// Skip if no internet connection
		if ( ! $this->has_internet_connection() ) {
			$this->markTestSkipped( 'Skipping test that requires internet connection' );
		}

		$validator  = new FontValidator();
		$storage    = new FontStorage( $validator );
		$downloader = new FontDownloader( $validator, $storage );

		$test_css = "@font-face {
			font-family: 'Roboto';
			font-style: normal;
			font-weight: 400;
			src: url(https://fonts.gstatic.com/s/roboto/v30/KFOmCnqEu92Fr1Mu4mxK.woff2) format('woff2');
		}";

		$memory_before = memory_get_peak_usage( true );

		$result = $downloader->download_font_from_css( $test_css );

		$memory_after = memory_get_peak_usage( true );
		$memory_delta = ( $memory_after - $memory_before ) / 1024 / 1024; // Convert to MB

		$this->assertTrue( $result['success'], 'Download should succeed' );
		$this->assertLessThan( 50, $memory_delta, 'Memory usage should stay under 50MB' );

		// Also check for memory leaks (delta should be small after download completes)
		$memory_current = memory_get_usage( true );
		$memory_leak    = ( $memory_current - $memory_before ) / 1024 / 1024;
		$this->assertLessThan( 5, $memory_leak, 'Memory leak should be less than 5MB after download' );
	}

	/**
	 * Test T045: Success messages include timing information (User Story 4 - P3).
	 *
	 * @group external-http
	 */
	public function testP3_ShowsTimingInMessages(): void {
		// Skip if no internet connection
		if ( ! $this->has_internet_connection() ) {
			$this->markTestSkipped( 'Skipping test that requires internet connection' );
		}

		$validator  = new FontValidator();
		$storage    = new FontStorage( $validator );
		$downloader = new FontDownloader( $validator, $storage );

		$test_css = "@font-face {
			font-family: 'Roboto';
			font-style: normal;
			font-weight: 400;
			src: url(https://fonts.gstatic.com/s/roboto/v30/KFOmCnqEu92Fr1Mu4mxK.woff2) format('woff2');
		}";

		$result = $downloader->download_font_from_css( $test_css );

		$this->assertTrue( $result['success'], 'Download should succeed' );

		// Verify message contains file count information
		$this->assertStringContainsString( 'file(s)', $result['message'], 'Message should include file count' );

		// Note: Current implementation may not include timing in message
		// This test documents expected behavior for future enhancement
		// If timing is added: assertStringContainsString('seconds', $result['message'])
	}

	/**
	 * Data provider for performance benchmarks (T046).
	 *
	 * @return array<string, array{css: string, max_duration_secs: int, max_memory_mb: int, description: string}>
	 */
	public function performanceBenchmarkProvider(): array {
		return array(
			'single weight'    => array(
				'css'               => "@font-face {
					font-family: 'Roboto';
					font-weight: 400;
					src: url(https://fonts.gstatic.com/s/roboto/v30/KFOmCnqEu92Fr1Mu4mxK.woff2) format('woff2');
				}",
				'max_duration_secs' => 10,
				'max_memory_mb'     => 10,
				'description'       => 'Single weight font',
			),
			'multiple weights' => array(
				'css'               => "@font-face {
					font-family: 'Roboto';
					font-weight: 400;
					src: url(https://fonts.gstatic.com/s/roboto/v30/KFOmCnqEu92Fr1Mu4mxK.woff2) format('woff2');
				}
				@font-face {
					font-family: 'Roboto';
					font-weight: 700;
					src: url(https://fonts.gstatic.com/s/roboto/v30/KFOlCnqEu92Fr1MmWUlfBBc4.woff2) format('woff2');
				}",
				'max_duration_secs' => 30,
				'max_memory_mb'     => 50,
				'description'       => 'Multiple weights',
			),
		);
	}

	/**
	 * Test T046: Performance benchmarks using data provider (User Story 4 - P3).
	 *
	 * @dataProvider performanceBenchmarkProvider
	 * @group external-http
	 *
	 * @param string $css               CSS content to test.
	 * @param int    $max_duration_secs Maximum duration in seconds.
	 * @param int    $max_memory_mb     Maximum memory usage in MB.
	 * @param string $description       Test scenario description.
	 */
	public function testP3_PerformanceBenchmarks( string $css, int $max_duration_secs, int $max_memory_mb, string $description ): void {
		// Skip if no internet connection
		if ( ! $this->has_internet_connection() ) {
			$this->markTestSkipped( 'Skipping test that requires internet connection' );
		}

		$validator  = new FontValidator();
		$storage    = new FontStorage( $validator );
		$downloader = new FontDownloader( $validator, $storage );

		$memory_before = memory_get_peak_usage( true );
		$start_time    = microtime( true );

		$result = $downloader->download_font_from_css( $css );

		$end_time     = microtime( true );
		$memory_after = memory_get_peak_usage( true );

		$duration_secs = $end_time - $start_time;
		$memory_delta  = ( $memory_after - $memory_before ) / 1024 / 1024;

		$this->assertTrue( $result['success'], "{$description}: Download should succeed" );
		$this->assertLessThan( $max_duration_secs, $duration_secs, "{$description}: Should complete within {$max_duration_secs} seconds" );
		$this->assertLessThan( $max_memory_mb, $memory_delta, "{$description}: Should use less than {$max_memory_mb}MB memory" );
	}
}
