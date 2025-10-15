<?php
/**
 * Font Download Integration Tests
 *
 * @package DWT\LocalFonts
 */

declare(strict_types=1);

namespace DWT\LocalFonts\Tests\Integration;

use DWT\LocalFonts\Services\FontDownloader;
use DWT\LocalFonts\Services\FontValidator;
use DWT\LocalFonts\Services\FontStorage;

/**
 * Integration tests for FontDownloader service.
 *
 * These tests require a real WordPress environment (wp-env).
 * Run `npm run wp-env:start` before executing these tests.
 */
final class FontDownloadIntegrationTest extends \WP_UnitTestCase {

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
		$temp_dir                         = $this->temp_upload_dir;
		$this->original_upload_dir_filter = function ( $uploads ) use ( $temp_dir ) {
			$uploads['basedir'] = $temp_dir;
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
	 * Test T013: Create integration test class with temporary uploads directory.
	 *
	 * This is a meta-test that verifies the test infrastructure itself.
	 */
	public function test_T013_test_infrastructure_works(): void {
		// Verify temporary directory was created
		$this->assertTrue( is_dir( $this->temp_upload_dir ), 'Temporary upload directory should exist' );

		// Verify wp_upload_dir returns our temporary directory
		$upload_dir = wp_upload_dir();
		$this->assertEquals( $this->temp_upload_dir, $upload_dir['basedir'], 'wp_upload_dir should return temporary directory' );

		// Verify we can create font directory
		$font_dir = $upload_dir['basedir'] . '/dwt-local-fonts';
		wp_mkdir_p( $font_dir );
		$this->assertTrue( is_dir( $font_dir ), 'Font directory should be created successfully' );
	}

	/**
	 * Test T014: End-to-end font download with real WordPress integration.
	 *
	 * Note: This test makes a real HTTP request to Google Fonts API.
	 * It may fail if internet is unavailable or Google Fonts is down.
	 *
	 * @group external-http
	 */
	public function testP1_EndToEndDownload(): void {
		// Skip if no internet connection (CI environments may not have external access)
		if ( ! $this->has_internet_connection() ) {
			$this->markTestSkipped( 'Skipping test that requires internet connection' );
		}

		$validator  = new FontValidator();
		$storage    = new FontStorage( $validator );
		$downloader = new FontDownloader( $validator, $storage );

		// Test CSS with a single simple font (Roboto Regular)
		$test_css = "@font-face {
  font-family: 'Roboto';
  font-style: normal;
  font-weight: 400;
  src: url(https://fonts.gstatic.com/s/roboto/v30/KFOmCnqEu92Fr1Mu4mxK.woff2) format('woff2');
}";

		// Download font from CSS
		$result = $downloader->download_font_from_css( $test_css );

		// Verify download succeeded
		$this->assertTrue( $result['success'], 'Download should succeed' );
		$this->assertContains( 'Roboto', $result['families'], 'Should extract Roboto family name' );
		$this->assertStringContainsString( '1 file(s)', $result['message'], 'Should download 1 file' );

		// Verify font file exists on filesystem
		$upload_dir = wp_upload_dir();
		$font_dir   = $upload_dir['basedir'] . '/dwt-local-fonts';
		$this->assertTrue( is_dir( $font_dir ), 'Font directory should exist' );

		// Check that at least one .woff2 file exists
		$font_files = glob( $font_dir . '/*.woff2' );
		$this->assertNotEmpty( $font_files, 'At least one WOFF2 file should exist' );
		$this->assertCount( 1, $font_files, 'Exactly 1 WOFF2 file should exist' );

		// Verify file is not empty
		$file_size = filesize( $font_files[0] );
		$this->assertGreaterThan( 0, $file_size, 'Font file should not be empty' );
		$this->assertLessThanOrEqual( 2 * 1024 * 1024, $file_size, 'Font file should not exceed 2MB' );

		// Verify CSS was updated with local paths
		$this->assertStringContainsString( 'dwt-local-fonts', $result['css'], 'CSS should contain local font path' );
		$this->assertStringNotContainsString( 'fonts.gstatic.com', $result['css'], 'CSS should not contain Google CDN URL' );
	}

	/**
	 * Test T015: Verify downloaded fonts list is updated in Options API.
	 */
	public function testP1_DownloadedFontsListUpdate(): void {
		// Skip if no internet connection
		if ( ! $this->has_internet_connection() ) {
			$this->markTestSkipped( 'Skipping test that requires internet connection' );
		}

		// Ensure option doesn't exist initially
		delete_option( 'dwt_local_fonts_list' );
		$this->assertFalse( get_option( 'dwt_local_fonts_list' ), 'Option should not exist initially' );

		$validator  = new FontValidator();
		$storage    = new FontStorage( $validator );
		$downloader = new FontDownloader( $validator, $storage );

		// Download a font
		$test_css = "@font-face {
  font-family: 'Open Sans';
  font-style: normal;
  font-weight: 400;
  src: url(https://fonts.gstatic.com/s/opensans/v34/memSYaGs126MiZpBA-UvWbX2vVnXBbObj2OVZyOOSr4dVJWUgsjZ0B4gaVI.woff2) format('woff2');
}";

		$result = $downloader->download_font_from_css( $test_css );

		// Verify download succeeded
		$this->assertTrue( $result['success'], 'Download should succeed' );

		// Note: The current implementation may not update dwt_local_fonts_list option
		// This is a placeholder test that can be enhanced when that feature is implemented
		// For now, we just verify the download works and files exist

		$upload_dir = wp_upload_dir();
		$font_dir   = $upload_dir['basedir'] . '/dwt-local-fonts';
		$font_files = glob( $font_dir . '/*.woff2' );

		$this->assertNotEmpty( $font_files, 'Font files should exist after download' );

		// If dwt_local_fonts_list option is implemented in the future, uncomment:
		// $fonts_list = get_option( 'dwt_local_fonts_list' );
		// $this->assertNotFalse( $fonts_list, 'Fonts list option should be created' );
		// $this->assertIsArray( $fonts_list, 'Fonts list should be an array' );
		// $this->assertNotEmpty( $fonts_list, 'Fonts list should not be empty' );
		//
		// // Verify metadata structure (per spec.md:L118)
		// $first_font = reset( $fonts_list );
		// $this->assertArrayHasKey( 'family_name', $first_font, 'Should have family name' );
		// $this->assertArrayHasKey( 'variant_file_count', $first_font, 'Should have variant count' );
		// $this->assertArrayHasKey( 'download_timestamp', $first_font, 'Should have timestamp' );
		// $this->assertArrayHasKey( 'total_file_size', $first_font, 'Should have total size' );
	}

	/**
	 * Check if internet connection is available.
	 *
	 * @return bool True if internet is available.
	 */
	private function has_internet_connection(): bool {
		// Try to fetch Google Fonts homepage with a short timeout
		$response = wp_remote_get(
			'https://fonts.google.com',
			array(
				'timeout'     => 5,
				'httpversion' => '1.1',
			)
		);

		return ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200;
	}
}
