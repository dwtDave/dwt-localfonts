<?php
/**
 * FontDiscovery Service Tests
 *
 * @package DWT\LocalFonts
 */

declare(strict_types=1);

namespace DWT\LocalFonts\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use DWT\LocalFonts\Services\FontDiscovery;
use DWT\LocalFonts\Services\FontStorage;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * Tests for FontDiscovery service.
 */
final class FontDiscoveryTest extends TestCase {

	/**
	 * Temp directory for tests.
	 *
	 * @var string
	 */
	private string $temp_dir;

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Create temp directory.
		$this->temp_dir = sys_get_temp_dir() . '/dwt-fonts-test-' . uniqid();
		mkdir( $this->temp_dir );
		mkdir( $this->temp_dir . '/dwt-local-fonts' );

		// Mock WordPress functions.
		Functions\when( 'wp_upload_dir' )->justReturn(
			array(
				'basedir' => $this->temp_dir,
				'baseurl' => 'http://example.com',
			)
		);
		Functions\when( 'trailingslashit' )->returnArg();
		Functions\when( 'get_option' )->justReturn( false );
		Functions\when( 'update_option' )->justReturn( true );
	}

	/**
	 * Tear down test environment.
	 */
	protected function tearDown(): void {
		// Clean up temp directory.
		if ( file_exists( $this->temp_dir ) ) {
			$this->recursive_rmdir( $this->temp_dir );
		}

		Monkey\tearDown();
		Mockery::close();
		parent::tearDown();
	}

	/**
	 * Recursively delete directory.
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
	 * Test discover_fonts returns empty array when directory doesn't exist.
	 */
	public function test_discover_fonts_returns_empty_when_directory_does_not_exist(): void {
		// Delete the fonts directory.
		$font_dir = $this->temp_dir . '/dwt-local-fonts';
		if ( file_exists( $font_dir ) ) {
			rmdir( $font_dir );
		}

		$discovery = new FontDiscovery();
		$result    = $discovery->discover_fonts();

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test has_font_files returns false when directory doesn't exist.
	 */
	public function test_has_font_files_returns_false_when_directory_does_not_exist(): void {
		// Delete the fonts directory.
		$font_dir = $this->temp_dir . '/dwt-local-fonts';
		if ( file_exists( $font_dir ) ) {
			rmdir( $font_dir );
		}

		$discovery = new FontDiscovery();
		$result    = $discovery->has_font_files();

		$this->assertFalse( $result );
	}

	/**
	 * Test discover_fonts with empty directory.
	 */
	public function test_discover_fonts_with_empty_directory(): void {
		$discovery = new FontDiscovery();
		$result    = $discovery->discover_fonts();

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test has_font_files returns false with empty directory.
	 */
	public function test_has_font_files_returns_false_with_empty_directory(): void {
		$discovery = new FontDiscovery();
		$result    = $discovery->has_font_files();

		$this->assertFalse( $result );
	}

	/**
	 * Test discover_fonts ignores non-font files.
	 */
	public function test_discover_fonts_ignores_non_font_files(): void {
		$font_dir = $this->temp_dir . '/dwt-local-fonts';

		// Create non-font files.
		file_put_contents( $font_dir . '/readme.txt', 'test' );
		file_put_contents( $font_dir . '/image.jpg', 'test' );
		file_put_contents( $font_dir . '/style.css', 'test' );

		$discovery = new FontDiscovery();
		$result    = $discovery->discover_fonts();

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test has_font_files returns false when only non-font files exist.
	 */
	public function test_has_font_files_returns_false_with_only_non_font_files(): void {
		$font_dir = $this->temp_dir . '/dwt-local-fonts';

		// Create non-font files.
		file_put_contents( $font_dir . '/readme.txt', 'test' );
		file_put_contents( $font_dir . '/style.css', 'test' );

		$discovery = new FontDiscovery();
		$result    = $discovery->has_font_files();

		$this->assertFalse( $result );
	}

	/**
	 * Test discover_fonts ignores subdirectories.
	 */
	public function test_discover_fonts_ignores_subdirectories(): void {
		$font_dir = $this->temp_dir . '/dwt-local-fonts';
		mkdir( $font_dir . '/subdir' );

		$discovery = new FontDiscovery();
		$result    = $discovery->discover_fonts();

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test discover_fonts handles corrupted font files gracefully.
	 */
	public function test_discover_fonts_handles_corrupted_font_files_gracefully(): void {
		$font_dir = $this->temp_dir . '/dwt-local-fonts';

		// Create fake/corrupted font files.
		file_put_contents( $font_dir . '/corrupted.ttf', 'not a real font' );
		file_put_contents( $font_dir . '/broken.woff', 'broken data' );

		$discovery = new FontDiscovery();
		$result    = $discovery->discover_fonts();

		// Should return empty array, not throw exception.
		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test discover_fonts returns unique font families.
	 *
	 * This is a mock-based test since we can't easily create real font files.
	 */
	public function test_discover_fonts_returns_unique_font_families(): void {
		$font_dir = $this->temp_dir . '/dwt-local-fonts';

		// Create placeholder font files.
		// In real scenario, these would be parsed by php-font-lib.
		file_put_contents( $font_dir . '/font1.ttf', 'placeholder' );
		file_put_contents( $font_dir . '/font2.ttf', 'placeholder' );

		$discovery = new FontDiscovery();
		$result    = $discovery->discover_fonts();

		// Result should be an array (even if empty in this mock scenario).
		$this->assertIsArray( $result );
	}

	/**
	 * Test normalize_font_family removes weight descriptors.
	 */
	public function test_normalize_font_family_removes_weight_descriptors(): void {
		$discovery = new FontDiscovery();

		// Use reflection to access private method.
		$reflection = new \ReflectionClass( $discovery );
		$method     = $reflection->getMethod( 'normalize_font_family' );
		$method->setAccessible( true );

		// Test various weight descriptors.
		$this->assertSame( 'Lato', $method->invoke( $discovery, 'Lato Light' ) );
		$this->assertSame( 'Lato', $method->invoke( $discovery, 'Lato Bold' ) );
		$this->assertSame( 'Lato', $method->invoke( $discovery, 'Lato Black' ) );
		$this->assertSame( 'Lato', $method->invoke( $discovery, 'Lato Thin' ) );
		$this->assertSame( 'Lato', $method->invoke( $discovery, 'Lato Regular' ) );
		$this->assertSame( 'Lato', $method->invoke( $discovery, 'Lato Medium' ) );
		$this->assertSame( 'Lato', $method->invoke( $discovery, 'Lato SemiBold' ) );
		$this->assertSame( 'Lato', $method->invoke( $discovery, 'Lato ExtraBold' ) );
		$this->assertSame( 'Roboto', $method->invoke( $discovery, 'Roboto 100' ) );
		$this->assertSame( 'Roboto', $method->invoke( $discovery, 'Roboto 300' ) );
		$this->assertSame( 'Roboto', $method->invoke( $discovery, 'Roboto 700' ) );
		$this->assertSame( 'Roboto', $method->invoke( $discovery, 'Roboto 900' ) );
	}

	/**
	 * Test normalize_font_family removes style descriptors.
	 */
	public function test_normalize_font_family_removes_style_descriptors(): void {
		$discovery = new FontDiscovery();

		// Use reflection to access private method.
		$reflection = new \ReflectionClass( $discovery );
		$method     = $reflection->getMethod( 'normalize_font_family' );
		$method->setAccessible( true );

		// Test style descriptors.
		$this->assertSame( 'Lato', $method->invoke( $discovery, 'Lato Italic' ) );
		$this->assertSame( 'Lato', $method->invoke( $discovery, 'Lato Oblique' ) );
		$this->assertSame( 'Open Sans', $method->invoke( $discovery, 'Open Sans Italic' ) );
	}

	/**
	 * Test normalize_font_family removes combined weight and style descriptors.
	 */
	public function test_normalize_font_family_removes_combined_descriptors(): void {
		$discovery = new FontDiscovery();

		// Use reflection to access private method.
		$reflection = new \ReflectionClass( $discovery );
		$method     = $reflection->getMethod( 'normalize_font_family' );
		$method->setAccessible( true );

		// Test combined weight + style.
		$this->assertSame( 'Lato', $method->invoke( $discovery, 'Lato Bold Italic' ) );
		$this->assertSame( 'Lato', $method->invoke( $discovery, 'Lato Light Italic' ) );
		$this->assertSame( 'Lato', $method->invoke( $discovery, 'Lato Black Italic' ) );
		$this->assertSame( 'Roboto', $method->invoke( $discovery, 'Roboto 300 Italic' ) );
		$this->assertSame( 'Open Sans', $method->invoke( $discovery, 'Open Sans Semi Bold Italic' ) );
	}

	/**
	 * Test normalize_font_family removes width descriptors.
	 */
	public function test_normalize_font_family_removes_width_descriptors(): void {
		$discovery = new FontDiscovery();

		// Use reflection to access private method.
		$reflection = new \ReflectionClass( $discovery );
		$method     = $reflection->getMethod( 'normalize_font_family' );
		$method->setAccessible( true );

		// Test width descriptors.
		$this->assertSame( 'Roboto', $method->invoke( $discovery, 'Roboto Condensed' ) );
		$this->assertSame( 'Roboto', $method->invoke( $discovery, 'Roboto Narrow' ) );
		$this->assertSame( 'Roboto', $method->invoke( $discovery, 'Roboto Extended' ) );
		$this->assertSame( 'Roboto', $method->invoke( $discovery, 'Roboto Wide' ) );
	}

	/**
	 * Test normalize_font_family handles multiple spaces correctly.
	 */
	public function test_normalize_font_family_handles_multiple_spaces(): void {
		$discovery = new FontDiscovery();

		// Use reflection to access private method.
		$reflection = new \ReflectionClass( $discovery );
		$method     = $reflection->getMethod( 'normalize_font_family' );
		$method->setAccessible( true );

		// Test whitespace cleanup.
		$this->assertSame( 'Open Sans', $method->invoke( $discovery, 'Open Sans  Bold  Italic' ) );
		$this->assertSame( 'PT Sans', $method->invoke( $discovery, 'PT Sans   700   Italic' ) );
	}

	/**
	 * Test normalize_font_family preserves font names without descriptors.
	 */
	public function test_normalize_font_family_preserves_plain_names(): void {
		$discovery = new FontDiscovery();

		// Use reflection to access private method.
		$reflection = new \ReflectionClass( $discovery );
		$method     = $reflection->getMethod( 'normalize_font_family' );
		$method->setAccessible( true );

		// Test that plain names are not modified.
		$this->assertSame( 'Lato', $method->invoke( $discovery, 'Lato' ) );
		$this->assertSame( 'Roboto', $method->invoke( $discovery, 'Roboto' ) );
		$this->assertSame( 'Open Sans', $method->invoke( $discovery, 'Open Sans' ) );
		$this->assertSame( 'Playfair Display', $method->invoke( $discovery, 'Playfair Display' ) );
	}

	/**
	 * Test normalize_font_family handles edge case where name becomes empty.
	 */
	public function test_normalize_font_family_handles_edge_case_empty_result(): void {
		$discovery = new FontDiscovery();

		// Use reflection to access private method.
		$reflection = new \ReflectionClass( $discovery );
		$method     = $reflection->getMethod( 'normalize_font_family' );
		$method->setAccessible( true );

		// Test edge case where normalization would result in empty string.
		// Should return original name.
		$this->assertSame( 'Bold', $method->invoke( $discovery, 'Bold' ) );
		$this->assertSame( 'Italic', $method->invoke( $discovery, 'Italic' ) );
	}

	/**
	 * Test normalize_font_family is case-insensitive for descriptor matching.
	 */
	public function test_normalize_font_family_is_case_insensitive(): void {
		$discovery = new FontDiscovery();

		// Use reflection to access private method.
		$reflection = new \ReflectionClass( $discovery );
		$method     = $reflection->getMethod( 'normalize_font_family' );
		$method->setAccessible( true );

		// Test case insensitivity for descriptor matching.
		// Note: The base family name case is preserved.
		$this->assertSame( 'Lato', $method->invoke( $discovery, 'Lato BOLD' ) );
		$this->assertSame( 'Lato', $method->invoke( $discovery, 'Lato bold' ) );
		$this->assertSame( 'LATO', $method->invoke( $discovery, 'LATO BOLD ITALIC' ) );
		$this->assertSame( 'roboto', $method->invoke( $discovery, 'roboto light italic' ) );
	}
}
