<?php
/**
 * FontStorage Unit Tests
 *
 * @package DWT\LocalFonts
 */

declare(strict_types=1);

namespace DWT\LocalFonts\Tests;

use DWT\LocalFonts\Services\FontStorage;
use DWT\LocalFonts\Services\FontValidator;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;

/**
 * Unit tests for FontStorage service.
 */
final class FontStorageTest extends \PHPUnit\Framework\TestCase {

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\when( 'wp_upload_dir' )->justReturn(
			array(
				'basedir' => '/var/www/wp-content/uploads',
				'baseurl' => 'https://example.com/wp-content/uploads',
			)
		);

		// Mock get_option and update_option for Logger.
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'update_option' )->justReturn( true );
	}

	/**
	 * Tear down test environment.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		Mockery::close();
		parent::tearDown();
	}

	/**
	 * Test constructor accepts validator.
	 */
	public function test_constructor_accepts_validator(): void {
		$validator = Mockery::mock( FontValidator::class );
		$storage   = new FontStorage( $validator );

		$this->assertInstanceOf( FontStorage::class, $storage );
	}

	/**
	 * Test constructor creates default validator.
	 */
	public function test_constructor_creates_default_validator(): void {
		$storage = new FontStorage();

		$this->assertInstanceOf( FontStorage::class, $storage );
	}

	/**
	 * Test get_font_dir_path returns correct path.
	 */
	public function test_get_font_dir_path_returns_correct_path(): void {
		$storage = new FontStorage();
		$path    = $storage->get_font_dir_path();

		$this->assertSame( '/var/www/wp-content/uploads/dwt-local-fonts', $path );
	}

	/**
	 * Test get_font_dir_url returns correct URL.
	 */
	public function test_get_font_dir_url_returns_correct_url(): void {
		$storage = new FontStorage();
		$url     = $storage->get_font_dir_url();

		$this->assertSame( 'https://example.com/wp-content/uploads/dwt-local-fonts', $url );
	}

	/**
	 * Test font_file_exists method exists and accepts string.
	 */
	public function test_font_file_exists_method_exists(): void {
		$storage = new FontStorage();

		// WP_Filesystem not available in unit tests, should return false.
		$exists = $storage->font_file_exists( 'roboto-v30-latin-regular.woff2' );

		$this->assertIsBool( $exists );
	}

	/**
	 * Test save_font_file fails without filesystem.
	 */
	public function test_save_font_file_fails_without_filesystem(): void {
		$storage = new FontStorage();

		$result = $storage->save_font_file( 'test.woff2', 'font content' );

		$this->assertFalse( $result );
	}

	/**
	 * Test get_all_font_files returns array.
	 */
	public function test_get_all_font_files_returns_array(): void {
		$storage = new FontStorage();

		$files = $storage->get_all_font_files();

		$this->assertIsArray( $files );
	}

	/**
	 * Test delete_font_files accepts array and returns count.
	 */
	public function test_delete_font_files_returns_count(): void {
		$storage = new FontStorage();

		$count = $storage->delete_font_files( array( 'test.woff2', 'test2.woff2' ) );

		$this->assertIsInt( $count );
		$this->assertSame( 0, $count ); // No filesystem available in unit test.
	}

	/**
	 * Test validate_font_files_exist returns expected structure.
	 */
	public function test_validate_font_files_exist_returns_expected_structure(): void {
		$storage = new FontStorage();

		$result = $storage->validate_font_files_exist( array( 'test.woff2' ) );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'existing', $result );
		$this->assertArrayHasKey( 'missing', $result );
		$this->assertIsArray( $result['existing'] );
		$this->assertIsArray( $result['missing'] );
	}

	/**
	 * Test validate_font_files_exist with multiple files.
	 */
	public function test_validate_font_files_exist_handles_multiple_files(): void {
		$storage = new FontStorage();

		$result = $storage->validate_font_files_exist( array( 'font1.woff2', 'font2.woff2', 'font3.woff2' ) );

		$this->assertIsArray( $result );
		$this->assertCount( 0, $result['existing'] ); // No filesystem in unit tests
		$this->assertCount( 3, $result['missing'] );
	}

	/**
	 * Test validate_font_files_exist with empty array.
	 */
	public function test_validate_font_files_exist_with_empty_array(): void {
		$storage = new FontStorage();

		$result = $storage->validate_font_files_exist( array() );

		$this->assertIsArray( $result );
		$this->assertCount( 0, $result['existing'] );
		$this->assertCount( 0, $result['missing'] );
	}

	/**
	 * Test save_font_file fails without filesystem.
	 *
	 * Note: Filename validation logic requires integration tests with real WordPress.
	 */
	public function test_save_font_file_with_various_filenames(): void {
		$storage = new FontStorage();

		// All should fail without filesystem in unit tests
		$this->assertFalse( $storage->save_font_file( 'valid-font.woff2', 'content' ) );
		$this->assertFalse( $storage->save_font_file( '../invalid.woff2', 'content' ) );
	}

	/**
	 * Test font_file_exists returns false without filesystem.
	 *
	 * Note: File existence checks require integration tests with real WordPress.
	 */
	public function test_font_file_exists_with_various_filenames(): void {
		$storage = new FontStorage();

		// All should return false without filesystem in unit tests
		$this->assertFalse( $storage->font_file_exists( 'valid-font.woff2' ) );
		$this->assertFalse( $storage->font_file_exists( '../invalid.woff2' ) );
	}

	/**
	 * Test delete_font_files with empty array.
	 */
	public function test_delete_font_files_with_empty_array(): void {
		$storage = new FontStorage();

		$count = $storage->delete_font_files( array() );

		$this->assertSame( 0, $count );
	}

	/**
	 * Test delete_font_files returns zero when filesystem unavailable.
	 *
	 * Note: Actual file deletion logic requires integration tests with real WordPress.
	 */
	public function test_delete_font_files_returns_zero_without_filesystem(): void {
		$storage = new FontStorage();

		$count = $storage->delete_font_files( array( 'font1.woff2', 'font2.woff2' ) );

		// Without WP_Filesystem (in unit tests), this returns 0
		$this->assertSame( 0, $count );
	}

	/**
	 * Test get_all_font_files returns array (basic test).
	 *
	 * Note: Full directory scanning logic requires integration tests with real filesystem.
	 */
	public function test_get_all_font_files_basic_functionality(): void {
		$storage = new FontStorage();

		// This will return empty array in unit tests since directory likely doesn't exist
		$files = $storage->get_all_font_files();

		$this->assertIsArray( $files );
		// Don't assert count since it depends on actual filesystem state
	}
}
