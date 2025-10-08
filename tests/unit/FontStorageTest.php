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
}
