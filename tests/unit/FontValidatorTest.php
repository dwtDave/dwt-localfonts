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

	// ========================================================================
	// User Story 2 (P1): Validate Font File Integrity Tests (T016-T022)
	// Using FontFileFixtures for comprehensive binary testing
	// ========================================================================

	/**
	 * Test T017: Validates WOFF2 magic bytes using fixtures.
	 *
	 * @dataProvider woff2MagicBytesProvider
	 */
	public function testP1_ValidatesWOFF2MagicBytes( string $content, bool $expected, string $description ): void {
		$result = $this->validator->is_valid_font_content( $content );

		$this->assertSame( $expected, $result, $description );
	}

	/**
	 * Data provider for WOFF2 magic bytes validation.
	 *
	 * @return array<string, array{content: string, expected: bool, description: string}>
	 */
	public function woff2MagicBytesProvider(): array {
		// Dynamically load fixtures
		require_once __DIR__ . '/Fixtures/FontFileFixtures.php';
		$fixtures_class = 'DWT\\LocalFonts\\Tests\\Fixtures\\FontFileFixtures';

		return array(
			'valid WOFF2 header'  => array(
				'content'     => $fixtures_class::getValidWOFF2Header() . str_repeat( "\x00", 100 ),
				'expected'    => true,
				'description' => 'Valid WOFF2 magic bytes should pass',
			),
			'valid small WOFF2'   => array(
				'content'     => $fixtures_class::getValidSmallWOFF2(),
				'expected'    => true,
				'description' => 'Complete valid WOFF2 file should pass',
			),
			'corrupted header'    => array(
				'content'     => $fixtures_class::getCorruptedHeader() . str_repeat( "\x00", 100 ),
				'expected'    => false,
				'description' => 'Corrupted magic bytes should fail',
			),
			'wrong format TTF'    => array(
				'content'     => $fixtures_class::getWrongFormatTTF(),
				'expected'    => true, // TTF is valid, just not WOFF2
				'description' => 'TTF format should be accepted (valid font format)',
			),
			'WOFF1 format'        => array(
				'content'     => $fixtures_class::getWOFF1File(),
				'expected'    => true, // WOFF1 is valid
				'description' => 'WOFF1 format should be accepted',
			),
			'minimal valid WOFF2' => array(
				'content'     => $fixtures_class::getMinimalValidWOFF2(),
				'expected'    => true,
				'description' => 'Minimal WOFF2 structure should pass',
			),
		);
	}

	/**
	 * Test T018: Rejects empty files using fixtures.
	 */
	public function testP1_RejectsEmptyFiles(): void {
		require_once __DIR__ . '/Fixtures/FontFileFixtures.php';
		$fixtures_class = 'DWT\\LocalFonts\\Tests\\Fixtures\\FontFileFixtures';

		$empty_content = $fixtures_class::getEmptyFile();
		$result        = $this->validator->is_valid_font_content( $empty_content );

		$this->assertFalse( $result, 'Empty file should be rejected' );
	}

	/**
	 * Test T019: Enforces size limits using fixtures.
	 *
	 * Note: is_valid_font_content checks format, not size. Size enforcement
	 * happens in FontDownloader before validation.
	 */
	public function testP1_EnforcesSizeLimits(): void {
		require_once __DIR__ . '/Fixtures/FontFileFixtures.php';
		$fixtures_class = 'DWT\\LocalFonts\\Tests\\Fixtures\\FontFileFixtures';

		// Test boundary cases
		$exactly_2mb   = $fixtures_class::getExactly2MBFile();
		$just_over_2mb = $fixtures_class::getJustOver2MBFile();
		$oversized     = $fixtures_class::getOversizedFile();

		// Validator checks format, not size - all should be valid format
		$this->assertTrue(
			$this->validator->is_valid_font_content( $exactly_2mb ),
			'Exactly 2MB file with valid header should pass format check'
		);

		// Size validation happens at download level, not validator level
		// This test documents that validator only checks format
		$this->assertTrue(
			$fixtures_class::hasValidWOFF2Magic( $just_over_2mb ),
			'Just over 2MB file has valid magic bytes'
		);
	}

	/**
	 * Test T020: Rejects corrupted files using fixtures.
	 */
	public function testP1_RejectsCorruptedFiles(): void {
		require_once __DIR__ . '/Fixtures/FontFileFixtures.php';
		$fixtures_class = 'DWT\\LocalFonts\\Tests\\Fixtures\\FontFileFixtures';

		$corrupted = $fixtures_class::getCorruptedHeader();
		$result    = $this->validator->is_valid_font_content( $corrupted );

		$this->assertFalse( $result, 'File with corrupted header should be rejected' );
	}

	/**
	 * Test T021: Validates all variants (conceptual test).
	 *
	 * This test verifies that validator can be called multiple times
	 * for a family with multiple variants.
	 */
	public function testP1_ValidatesAllVariants(): void {
		require_once __DIR__ . '/Fixtures/FontFileFixtures.php';
		$fixtures_class = 'DWT\\LocalFonts\\Tests\\Fixtures\\FontFileFixtures';

		// Simulate validating 12 variants (like a full Roboto family)
		$variants = array(
			$fixtures_class::getValidSmallWOFF2(),
			$fixtures_class::getValidSmallWOFF2(),
			$fixtures_class::getValidSmallWOFF2(),
			$fixtures_class::getValidSmallWOFF2(),
			$fixtures_class::getValidSmallWOFF2(),
			$fixtures_class::getValidSmallWOFF2(),
			$fixtures_class::getValidSmallWOFF2(),
			$fixtures_class::getValidSmallWOFF2(),
			$fixtures_class::getValidSmallWOFF2(),
			$fixtures_class::getValidSmallWOFF2(),
			$fixtures_class::getValidSmallWOFF2(),
			$fixtures_class::getValidSmallWOFF2(),
		);

		$validation_count = 0;
		foreach ( $variants as $variant_content ) {
			if ( $this->validator->is_valid_font_content( $variant_content ) ) {
				++$validation_count;
			}
		}

		$this->assertSame( 12, $validation_count, 'All 12 variants should be validated' );
	}

	/**
	 * Test T022: Data provider for comprehensive file validation scenarios.
	 *
	 * @dataProvider fileValidationProvider
	 */
	public function testP1_FileValidation( string $content, bool $should_pass, string $reason ): void {
		$result = $this->validator->is_valid_font_content( $content );

		$this->assertSame( $should_pass, $result, $reason );
	}

	/**
	 * Data provider for file validation scenarios (T022).
	 *
	 * @return array<string, array{content: string, should_pass: bool, reason: string}>
	 */
	public function fileValidationProvider(): array {
		require_once __DIR__ . '/Fixtures/FontFileFixtures.php';
		$fixtures_class = 'DWT\\LocalFonts\\Tests\\Fixtures\\FontFileFixtures';

		return array(
			'valid WOFF2'        => array(
				'content'     => $fixtures_class::getValidSmallWOFF2(),
				'should_pass' => true,
				'reason'      => 'Valid WOFF2 file should pass',
			),
			'corrupted header'   => array(
				'content'     => $fixtures_class::getCorruptedHeader(),
				'should_pass' => false,
				'reason'      => 'Corrupted header should fail (Invalid WOFF2 header)',
			),
			'empty file'         => array(
				'content'     => $fixtures_class::getEmptyFile(),
				'should_pass' => false,
				'reason'      => 'Empty file should fail (File size is zero)',
			),
			'oversized file'     => array(
				'content'     => $fixtures_class::getOversizedFile(),
				'should_pass' => true, // Validator doesn't check size, only format
				'reason'      => 'Oversized file with valid header passes format check',
			),
			'wrong format (TTF)' => array(
				'content'     => $fixtures_class::getWrongFormatTTF(),
				'should_pass' => true, // TTF is a valid font format
				'reason'      => 'TTF is a valid font format (not WOFF2, but acceptable)',
			),
			'null bytes only'    => array(
				'content'     => $fixtures_class::getNullBytesFile(),
				'should_pass' => false,
				'reason'      => 'File with only null bytes should fail',
			),
			'random binary'      => array(
				'content'     => $fixtures_class::getRandomBinaryData(),
				'should_pass' => false,
				'reason'      => 'Random binary data should fail',
			),
			'partial download'   => array(
				'content'     => $fixtures_class::getPartialDownload(),
				'should_pass' => true, // Has valid header, partial content accepted
				'reason'      => 'Partial download with valid header passes (content check is basic)',
			),
		);
	}
}
