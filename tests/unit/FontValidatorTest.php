<?php
/**
 * Tests for FontValidator
 *
 * @package DWT\LocalFonts\Tests
 */

declare(strict_types=1);

namespace DWT\LocalFonts\Tests\Unit;

use DWT\LocalFonts\Services\FontValidator;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;
use Brain\Monkey\Functions;

/**
 * Test case for FontValidator service.
 */
final class FontValidatorTest extends TestCase {

	/**
	 * FontValidator instance.
	 *
	 * @var FontValidator
	 */
	private FontValidator $validator;

	/**
	 * Set up test environment.
	 */
	protected function set_up(): void {
		parent::set_up();
		\Brain\Monkey\setUp();
		$this->validator = new FontValidator();
	}

	/**
	 * Tear down test environment.
	 */
	protected function tear_down(): void {
		\Brain\Monkey\tearDown();
		parent::tear_down();
	}

	/**
	 * Test valid Google Fonts URL.
	 */
	public function test_is_valid_font_url_accepts_google_fonts(): void {
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );

		$url    = 'https://fonts.googleapis.com/css2?family=Roboto:wght@400;700';
		$result = $this->validator->is_valid_font_url( $url );

		$this->assertTrue( $result );
	}

	/**
	 * Test valid Fontsource GitHub URL.
	 */
	public function test_is_valid_font_url_accepts_fontsource_github(): void {
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );

		$url    = 'https://raw.githubusercontent.com/fontsource/font-files/main/fonts/roboto/roboto-400-normal.woff2';
		$result = $this->validator->is_valid_font_url( $url );

		$this->assertTrue( $result );
	}

	/**
	 * Test invalid GitHub path (not fontsource).
	 */
	public function test_is_valid_font_url_rejects_non_fontsource_github(): void {
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );

		$url    = 'https://raw.githubusercontent.com/malicious/repo/main/font.woff2';
		$result = $this->validator->is_valid_font_url( $url );

		$this->assertFalse( $result );
	}

	/**
	 * Test HTTP URL rejection (must be HTTPS).
	 */
	public function test_is_valid_font_url_rejects_http(): void {
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );

		$url    = 'http://fonts.googleapis.com/css2?family=Roboto';
		$result = $this->validator->is_valid_font_url( $url );

		$this->assertFalse( $result );
	}

	/**
	 * Test invalid domain rejection.
	 */
	public function test_is_valid_font_url_rejects_invalid_domain(): void {
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );

		$url    = 'https://malicious-site.com/fonts/roboto.woff2';
		$result = $this->validator->is_valid_font_url( $url );

		$this->assertFalse( $result );
	}

	/**
	 * Test malformed URL rejection.
	 */
	public function test_is_valid_font_url_rejects_malformed_url(): void {
		Functions\when( 'wp_parse_url' )->returnArg();

		$url    = 'not-a-valid-url';
		$result = $this->validator->is_valid_font_url( $url );

		$this->assertFalse( $result );
	}

	/**
	 * Test sanitize filename with valid woff2 file.
	 */
	public function test_sanitize_filename_accepts_valid_woff2(): void {
		$filename = 'roboto-regular.woff2';
		$result   = $this->validator->sanitize_filename( $filename );

		$this->assertSame( 'roboto-regular.woff2', $result );
	}

	/**
	 * Test sanitize filename with valid ttf file.
	 */
	public function test_sanitize_filename_accepts_valid_ttf(): void {
		$filename = 'OpenSans-Bold.ttf';
		$result   = $this->validator->sanitize_filename( $filename );

		$this->assertSame( 'OpenSans-Bold.ttf', $result );
	}

	/**
	 * Test sanitize filename removes directory traversal.
	 */
	public function test_sanitize_filename_rejects_directory_traversal(): void {
		$filename = '../../etc/passwd';
		$result   = $this->validator->sanitize_filename( $filename );

		$this->assertFalse( $result );
	}

	/**
	 * Test sanitize filename rejects invalid extension.
	 */
	public function test_sanitize_filename_rejects_invalid_extension(): void {
		$filename = 'malicious.exe';
		$result   = $this->validator->sanitize_filename( $filename );

		$this->assertFalse( $result );
	}

	/**
	 * Test sanitize filename rejects PHP files.
	 */
	public function test_sanitize_filename_rejects_php_extension(): void {
		$filename = 'shell.php';
		$result   = $this->validator->sanitize_filename( $filename );

		$this->assertFalse( $result );
	}

	/**
	 * Test sanitize filename removes null bytes and accepts result.
	 */
	public function test_sanitize_filename_removes_null_bytes(): void {
		$filename = "font\x00.woff2";
		$result   = $this->validator->sanitize_filename( $filename );

		// After removing null byte, becomes valid "font.woff2"
		$this->assertSame( 'font.woff2', $result );
	}

	/**
	 * Test sanitize filename with special characters.
	 */
	public function test_sanitize_filename_rejects_special_characters(): void {
		$filename = 'font<script>.woff2';
		$result   = $this->validator->sanitize_filename( $filename );

		$this->assertFalse( $result );
	}

	/**
	 * Test sanitize filename with overly long name.
	 */
	public function test_sanitize_filename_rejects_too_long(): void {
		$filename = str_repeat( 'a', 300 ) . '.woff2';
		$result   = $this->validator->sanitize_filename( $filename );

		$this->assertFalse( $result );
	}

	/**
	 * Test sanitize filename accepts all allowed extensions.
	 */
	public function test_sanitize_filename_accepts_all_allowed_extensions(): void {
		$extensions = array( 'woff', 'woff2', 'ttf', 'eot', 'otf' );

		foreach ( $extensions as $ext ) {
			$filename = "font-file.{$ext}";
			$result   = $this->validator->sanitize_filename( $filename );

			$this->assertSame( $filename, $result, "Extension {$ext} should be allowed" );
		}
	}

	/**
	 * Test valid WOFF font content.
	 */
	public function test_is_valid_font_content_accepts_woff(): void {
		$content = "\x77\x4F\x46\x46" . str_repeat( 'x', 100 );
		$result  = $this->validator->is_valid_font_content( $content );

		$this->assertTrue( $result );
	}

	/**
	 * Test valid WOFF2 font content.
	 */
	public function test_is_valid_font_content_accepts_woff2(): void {
		$content = "\x77\x4F\x46\x32" . str_repeat( 'x', 100 );
		$result  = $this->validator->is_valid_font_content( $content );

		$this->assertTrue( $result );
	}

	/**
	 * Test valid TrueType font content.
	 */
	public function test_is_valid_font_content_accepts_truetype(): void {
		$content = "\x00\x01\x00\x00" . str_repeat( 'x', 100 );
		$result  = $this->validator->is_valid_font_content( $content );

		$this->assertTrue( $result );
	}

	/**
	 * Test valid OpenType font content.
	 */
	public function test_is_valid_font_content_accepts_opentype(): void {
		$content = "\x4F\x54\x54\x4F" . str_repeat( 'x', 100 );
		$result  = $this->validator->is_valid_font_content( $content );

		$this->assertTrue( $result );
	}

	/**
	 * Test valid TrueType Collection font content.
	 */
	public function test_is_valid_font_content_accepts_ttc(): void {
		$content = "\x74\x74\x63\x66" . str_repeat( 'x', 100 );
		$result  = $this->validator->is_valid_font_content( $content );

		$this->assertTrue( $result );
	}

	/**
	 * Test invalid font content rejection.
	 */
	public function test_is_valid_font_content_rejects_invalid(): void {
		$content = 'This is not a font file';
		$result  = $this->validator->is_valid_font_content( $content );

		$this->assertFalse( $result );
	}

	/**
	 * Test empty content rejection.
	 */
	public function test_is_valid_font_content_rejects_empty(): void {
		$content = '';
		$result  = $this->validator->is_valid_font_content( $content );

		$this->assertFalse( $result );
	}

	/**
	 * Test too short content rejection.
	 */
	public function test_is_valid_font_content_rejects_too_short(): void {
		$content = 'abc';
		$result  = $this->validator->is_valid_font_content( $content );

		$this->assertFalse( $result );
	}

	/**
	 * Test sanitize filename with path in filename.
	 */
	public function test_sanitize_filename_strips_path(): void {
		$filename = '/var/www/html/fonts/roboto.woff2';
		$result   = $this->validator->sanitize_filename( $filename );

		$this->assertSame( 'roboto.woff2', $result );
	}

	/**
	 * Test sanitize filename rejects Windows path with special characters.
	 */
	public function test_sanitize_filename_rejects_windows_path(): void {
		// On Unix systems, backslashes aren't directory separators
		// so this fails the regex validation
		$filename = 'C:\\Windows\\Fonts\\arial.ttf';
		$result   = $this->validator->sanitize_filename( $filename );

		$this->assertFalse( $result );
	}

	/**
	 * Test sanitize filename with uppercase extension.
	 */
	public function test_sanitize_filename_accepts_uppercase_extension(): void {
		$filename = 'Font-File.WOFF2';
		$result   = $this->validator->sanitize_filename( $filename );

		$this->assertSame( 'Font-File.WOFF2', $result );
	}
}
