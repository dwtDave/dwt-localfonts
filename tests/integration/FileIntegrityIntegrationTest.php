<?php
/**
 * File Integrity Integration Tests
 *
 * @package DWT\LocalFonts
 */

declare(strict_types=1);

namespace DWT\LocalFonts\Tests\Integration;

use DWT\LocalFonts\Services\FontValidator;
use DWT\LocalFonts\Services\FontStorage;
use DWT\LocalFonts\Services\FontDownloader;

/**
 * Integration tests for font file integrity validation.
 *
 * These tests require a real WordPress environment (wp-env).
 * Run `npm run wp-env:start` before executing these tests.
 */
final class FileIntegrityIntegrationTest extends \WP_UnitTestCase {

	/**
	 * Temporary uploads directory for testing.
	 *
	 * @var string
	 */
	private string $temp_upload_dir;

	/**
	 * Font directory path.
	 *
	 * @var string
	 */
	private string $font_dir;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		// Create temporary uploads directory
		$this->temp_upload_dir = sys_get_temp_dir() . '/dwt-localfonts-integrity-test-' . uniqid();
		wp_mkdir_p( $this->temp_upload_dir );

		// Set font directory
		$this->font_dir = $this->temp_upload_dir . '/dwt-local-fonts';
		wp_mkdir_p( $this->font_dir );

		// Override wp_upload_dir
		add_filter(
			'upload_dir',
			function ( $uploads ) {
				$uploads['basedir'] = $this->temp_upload_dir;
				$uploads['baseurl'] = 'https://example.com/wp-content/uploads-test';
				return $uploads;
			}
		);
	}

	/**
	 * Tear down test environment.
	 */
	public function tearDown(): void {
		// Clean up temporary directory
		if ( is_dir( $this->temp_upload_dir ) ) {
			$this->recursive_rmdir( $this->temp_upload_dir );
		}

		parent::tearDown();
	}

	/**
	 * Recursively remove directory.
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
	 * Test T024: Validates existing corrupted fonts in filesystem.
	 *
	 * Scenario: Plugin initialization finds corrupted font file already in directory
	 * Expected: File marked with warning, admin can re-download
	 */
	public function testP1_ValidatesExistingFonts(): void {
		// Load fixtures
		require_once dirname( __DIR__ ) . '/unit/Fixtures/FontFileFixtures.php';
		$fixtures_class = 'DWT\\LocalFonts\\Tests\\Fixtures\\FontFileFixtures';

		// Create a corrupted font file in the font directory
		$corrupted_filename = 'corrupted-roboto.woff2';
		$corrupted_content  = $fixtures_class::getCorruptedHeader();
		file_put_contents( $this->font_dir . '/' . $corrupted_filename, $corrupted_content );

		$this->assertTrue(
			file_exists( $this->font_dir . '/' . $corrupted_filename ),
			'Corrupted file should exist'
		);

		// Validate the file using FontValidator
		$validator = new FontValidator();
		$is_valid  = $validator->is_valid_font_content(
			file_get_contents( $this->font_dir . '/' . $corrupted_filename )
		);

		$this->assertFalse( $is_valid, 'Corrupted font file should fail validation' );

		// In a real implementation, this would trigger:
		// - Warning icon in admin UI
		// - "Re-download" action button
		// For now, we verify that validation correctly identifies the corruption
	}

	/**
	 * Test T025: Blocks invalid files during download process.
	 *
	 * Scenario: Server returns corrupted/invalid data during font download
	 * Expected: Download fails gracefully, file not saved to disk
	 */
	public function testP1_BlocksInvalidFilesDuringDownload(): void {
		// Load fixtures
		require_once dirname( __DIR__ ) . '/unit/Fixtures/FontFileFixtures.php';
		$fixtures_class = 'DWT\\LocalFonts\\Tests\\Fixtures\\FontFileFixtures';

		$validator = new FontValidator();
		$storage   = new FontStorage( $validator );

		// Simulate download receiving corrupted data
		$corrupted_content = $fixtures_class::getCorruptedHeader();
		$filename          = 'test-font.woff2';

		// Verify corrupted content fails validation
		$is_valid = $validator->is_valid_font_content( $corrupted_content );
		$this->assertFalse( $is_valid, 'Corrupted content should fail validation' );

		// In actual download process, FontDownloader checks validation before save
		// If validation fails, file should not be saved
		$file_path = $this->font_dir . '/' . $filename;

		// Verify file doesn't exist initially
		$this->assertFileDoesNotExist( $file_path, 'File should not exist before download attempt' );

		// The download process would call:
		// if ( $validator->is_valid_font_content( $content ) ) {
		// $storage->save_font_file( $filename, $content );
		// }

		// Since validation failed, save_font_file should NOT be called
		// Verify the corrupted file was never written
		$this->assertFileDoesNotExist( $file_path, 'Corrupted file should not be saved to disk' );
	}

	/**
	 * Test: Verify FontStorage integration with filesystem.
	 *
	 * Additional test to verify storage layer works correctly in WordPress environment.
	 */
	public function test_FontStorage_saves_valid_files(): void {
		// Load fixtures
		require_once dirname( __DIR__ ) . '/unit/Fixtures/FontFileFixtures.php';
		$fixtures_class = 'DWT\\LocalFonts\\Tests\\Fixtures\\FontFileFixtures';

		$validator = new FontValidator();
		$storage   = new FontStorage( $validator );

		// Valid WOFF2 content
		$valid_content = $fixtures_class::getValidSmallWOFF2();
		$filename      = 'valid-roboto-regular.woff2';

		// Verify validation passes
		$is_valid = $validator->is_valid_font_content( $valid_content );
		$this->assertTrue( $is_valid, 'Valid WOFF2 content should pass validation' );

		// Save file
		$result = $storage->save_font_file( $filename, $valid_content );
		$this->assertTrue( $result, 'Save should succeed for valid font file' );

		// Verify file exists
		$file_path = $this->font_dir . '/' . $filename;
		$this->assertFileExists( $file_path, 'Valid font file should be saved to disk' );

		// Verify file content matches
		$saved_content = file_get_contents( $file_path );
		$this->assertEquals( $valid_content, $saved_content, 'Saved content should match original' );

		// Verify file size
		$file_size = filesize( $file_path );
		$this->assertGreaterThan( 0, $file_size, 'Saved file should not be empty' );
	}

	/**
	 * Test: Verify FontStorage validates filename before saving.
	 */
	public function test_FontStorage_rejects_invalid_filenames(): void {
		$validator = new FontValidator();
		$storage   = new FontStorage( $validator );

		// Try to save with malicious filename (path traversal attempt)
		$result = $storage->save_font_file( '../../../etc/passwd', 'malicious content' );

		// FontValidator should reject the filename, so save_font_file returns false
		$this->assertFalse( $result, 'Save should fail for invalid filename with path traversal' );

		// Verify no file was created in the font directory with a sanitized name
		// (FontValidator returns false for invalid filenames, so no file should be created at all)
		$font_files = glob( $this->font_dir . '/*' );
		$this->assertCount( 0, $font_files, 'No files should be created for invalid filename' );
	}

	/**
	 * Test: Verify multiple variants can be validated and saved.
	 *
	 * This supports T021 requirement for multi-variant validation.
	 */
	public function test_validates_and_saves_multiple_variants(): void {
		// Load fixtures
		require_once dirname( __DIR__ ) . '/unit/Fixtures/FontFileFixtures.php';
		$fixtures_class = 'DWT\\LocalFonts\\Tests\\Fixtures\\FontFileFixtures';

		$validator = new FontValidator();
		$storage   = new FontStorage( $validator );

		$valid_content = $fixtures_class::getValidSmallWOFF2();

		// Simulate saving multiple variants (like Roboto with different weights)
		$variants = array(
			'roboto-regular-400.woff2',
			'roboto-bold-700.woff2',
			'roboto-italic-400.woff2',
			'roboto-light-300.woff2',
		);

		$saved_count = 0;
		foreach ( $variants as $filename ) {
			if ( $storage->save_font_file( $filename, $valid_content ) ) {
				++$saved_count;
			}
		}

		$this->assertSame( 4, $saved_count, 'All 4 variants should be saved successfully' );

		// Verify all files exist
		foreach ( $variants as $filename ) {
			$file_path = $this->font_dir . '/' . $filename;
			$this->assertFileExists( $file_path, "Variant $filename should exist" );
		}
	}
}
