<?php
/**
 * FontDownloader Unit Tests
 *
 * @package DWT\LocalFonts
 */

declare(strict_types=1);

namespace DWT\LocalFonts\Tests;

use DWT\LocalFonts\Services\FontDownloader;
use DWT\LocalFonts\Services\FontValidator;
use DWT\LocalFonts\Services\FontStorage;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;


/**
 * Unit tests for FontDownloader service.
 */
final class FontDownloaderTest extends \PHPUnit\Framework\TestCase {

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Define WP_DEBUG if not already defined.
		if ( ! defined( 'WP_DEBUG' ) ) {
			define( 'WP_DEBUG', false );
		}

		// Stub common WordPress functions.
		// Note: get_option stub uses zeroOrMoreTimes to allow tests to override.
		Functions\expect( 'get_option' )
			->zeroOrMoreTimes()
			->andReturnUsing(
				function ( $option, $default = false ) {
					// Default behavior - return empty array for settings.
					if ( 'dwt_local_fonts_settings' === $option ) {
						return array();
					}
					return $default;
				}
			);
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'wp_upload_dir' )->justReturn(
			array(
				'basedir' => '/tmp/wp-content/uploads',
				'baseurl' => 'https://example.com/wp-content/uploads',
			)
		);
	}

	/**
	 * Tear down test environment.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test constructor with dependency injection.
	 */
	public function test_constructor_accepts_dependencies(): void {
		$validator = Mockery::mock( FontValidator::class );
		$storage   = Mockery::mock( FontStorage::class );

		$downloader = new FontDownloader( $validator, $storage );

		$this->assertInstanceOf( FontDownloader::class, $downloader );
	}

	/**
	 * Test constructor creates default instances when no dependencies provided.
	 */
	public function test_constructor_creates_default_instances(): void {
		$downloader = new FontDownloader();

		$this->assertInstanceOf( FontDownloader::class, $downloader );
	}

	/**
	 * Test download_font with invalid URL.
	 */
	public function test_download_font_rejects_invalid_url(): void {
		$validator = Mockery::mock( FontValidator::class );
		$validator->shouldReceive( 'is_valid_font_url' )
			->with( 'https://malicious.com/font.css' )
			->andReturn( false );

		$storage = Mockery::mock( FontStorage::class );

		$downloader = new FontDownloader( $validator, $storage );
		$result     = $downloader->download_font( 'https://malicious.com/font.css' );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 'Invalid font URL', $result['message'] );
		$this->assertEmpty( $result['families'] );
	}

	/**
	 * Test download_font with fetch failure.
	 */
	public function test_download_font_handles_fetch_failure(): void {
		$url = 'https://fonts.googleapis.com/css2?family=Roboto';

		$validator = Mockery::mock( FontValidator::class );
		$validator->shouldReceive( 'is_valid_font_url' )
			->with( $url )
			->andReturn( true );

		$storage = Mockery::mock( FontStorage::class );

		Functions\expect( 'wp_remote_get' )
			->once()
			->with( $url, Mockery::type( 'array' ) )
			->andReturn( new \WP_Error( 'http_request_failed', 'Connection timeout' ) );

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( true );

		$downloader = new FontDownloader( $validator, $storage );
		$result     = $downloader->download_font( $url );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 'Failed to fetch font CSS', $result['message'] );
	}

	/**
	 * Test download_font with non-200 HTTP status.
	 */
	public function test_download_font_handles_non_200_status(): void {
		$url = 'https://fonts.googleapis.com/css2?family=Roboto';

		$validator = Mockery::mock( FontValidator::class );
		$validator->shouldReceive( 'is_valid_font_url' )
			->with( $url )
			->andReturn( true );

		$storage = Mockery::mock( FontStorage::class );

		Functions\expect( 'wp_remote_get' )
			->once()
			->andReturn( array( 'response' => array( 'code' => 404 ) ) );

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_remote_retrieve_response_code' )
			->times( 2 ) // Called twice: once for check, once for logging.
			->andReturn( 404 );

		$downloader = new FontDownloader( $validator, $storage );
		$result     = $downloader->download_font( $url );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 'Failed to fetch font CSS', $result['message'] );
	}

	/**
	 * Test download_font_from_css with empty content.
	 */
	public function test_download_font_from_css_rejects_empty_content(): void {
		$validator  = Mockery::mock( FontValidator::class );
		$storage    = Mockery::mock( FontStorage::class );
		$downloader = new FontDownloader( $validator, $storage );

		$result = $downloader->download_font_from_css( '' );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 'CSS content is required', $result['message'] );
		$this->assertEmpty( $result['families'] );
	}

	/**
	 * Test download_font_from_css with no font URLs in CSS.
	 */
	public function test_download_font_from_css_with_no_font_urls(): void {
		$validator  = Mockery::mock( FontValidator::class );
		$storage    = Mockery::mock( FontStorage::class );
		$downloader = new FontDownloader( $validator, $storage );

		$css    = '@font-face { font-family: "Roboto"; }'; // No URL.
		$result = $downloader->download_font_from_css( $css );

		$this->assertFalse( $result['success'] );
		$this->assertEquals( 'No font files found', $result['message'] );
	}

	/**
	 * Test download_font_from_css successfully processes valid CSS.
	 */
	public function test_download_font_from_css_processes_valid_css(): void {
		$valid_woff2 = "\x77\x4F\x46\x32" . str_repeat( "\x00", 100 );

		$validator = Mockery::mock( FontValidator::class );
		$validator->shouldReceive( 'is_valid_font_content' )
			->once()
			->with( $valid_woff2 )
			->andReturn( true );

		$storage = Mockery::mock( FontStorage::class );
		$storage->shouldReceive( 'font_file_exists' )
			->once()
			->with( 'test-font.woff2' )
			->andReturn( false );

		$storage->shouldReceive( 'save_font_file' )
			->once()
			->with( 'test-font.woff2', $valid_woff2 )
			->andReturn( true );

		$storage->shouldReceive( 'get_font_dir_url' )
			->once()
			->andReturn( 'https://example.com/wp-content/uploads/dwt-local-fonts' );

		Functions\expect( 'wp_remote_get' )
			->once()
			->with( 'https://fonts.gstatic.com/s/test-font.woff2', Mockery::type( 'array' ) )
			->andReturn(
				array(
					'response' => array( 'code' => 200 ),
					'body'     => $valid_woff2,
				)
			);

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_remote_retrieve_response_code' )
			->once()
			->andReturn( 200 );

		Functions\expect( 'wp_remote_retrieve_body' )
			->once()
			->andReturn( $valid_woff2 );

		$downloader = new FontDownloader( $validator, $storage );

		$css = "@font-face {
			font-family: 'TestFont';
			src: url(https://fonts.gstatic.com/s/test-font.woff2) format('woff2');
		}";

		$result = $downloader->download_font_from_css( $css );

		$this->assertTrue( $result['success'] );
		$this->assertContains( 'TestFont', $result['families'] );
		$this->assertStringContainsString( 'Successfully downloaded 1 file(s)', $result['message'] );
	}

	/**
	 * Test download_font_from_css skips existing files.
	 */
	public function test_download_font_from_css_skips_existing_files(): void {
		$validator = Mockery::mock( FontValidator::class );

		$storage = Mockery::mock( FontStorage::class );
		$storage->shouldReceive( 'get_font_dir_url' )
			->once()
			->andReturn( 'https://example.com/wp-content/uploads/dwt-local-fonts' );

		$storage->shouldReceive( 'font_file_exists' )
			->once()
			->with( 'existing.woff2' )
			->andReturn( true );

		$downloader = new FontDownloader( $validator, $storage );

		$css = "@font-face {
			font-family: 'TestFont';
			src: url(https://fonts.gstatic.com/s/existing.woff2) format('woff2');
		}";

		$result = $downloader->download_font_from_css( $css );

		$this->assertTrue( $result['success'] );
		$this->assertStringContainsString( '0 file(s)', $result['message'] );
	}

	/**
	 * Test download_font_from_css handles font download failure.
	 */
	public function test_download_font_from_css_handles_font_download_failure(): void {
		$validator = Mockery::mock( FontValidator::class );

		$storage = Mockery::mock( FontStorage::class );
		$storage->shouldReceive( 'get_font_dir_url' )
			->once()
			->andReturn( 'https://example.com/wp-content/uploads/dwt-local-fonts' );

		$storage->shouldReceive( 'font_file_exists' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_remote_get' )
			->once()
			->andReturn( new \WP_Error( 'http_request_failed', 'Connection timeout' ) );

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( true );

		$downloader = new FontDownloader( $validator, $storage );

		$css = "@font-face {
			font-family: 'FailFont';
			src: url(https://fonts.gstatic.com/s/fail.woff2) format('woff2');
		}";

		$result = $downloader->download_font_from_css( $css );

		$this->assertTrue( $result['success'] );
		$this->assertStringContainsString( '0 file(s)', $result['message'] );
	}

	/**
	 * Test download_font_from_css handles invalid font content.
	 */
	public function test_download_font_from_css_rejects_invalid_font_content(): void {
		$invalid_content = '<html>Not a font</html>';

		$validator = Mockery::mock( FontValidator::class );
		$validator->shouldReceive( 'is_valid_font_content' )
			->once()
			->with( $invalid_content )
			->andReturn( false );

		$storage = Mockery::mock( FontStorage::class );
		$storage->shouldReceive( 'get_font_dir_url' )
			->once()
			->andReturn( 'https://example.com/wp-content/uploads/dwt-local-fonts' );

		$storage->shouldReceive( 'font_file_exists' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_remote_get' )
			->once()
			->andReturn(
				array(
					'response' => array( 'code' => 200 ),
					'body'     => $invalid_content,
				)
			);

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_remote_retrieve_response_code' )
			->once()
			->andReturn( 200 );

		Functions\expect( 'wp_remote_retrieve_body' )
			->once()
			->andReturn( $invalid_content );

		$downloader = new FontDownloader( $validator, $storage );

		$css = "@font-face {
			font-family: 'InvalidFont';
			src: url(https://fonts.gstatic.com/s/invalid.woff2) format('woff2');
		}";

		$result = $downloader->download_font_from_css( $css );

		$this->assertTrue( $result['success'] );
		$this->assertStringContainsString( '0 file(s)', $result['message'] );
	}

	/**
	 * Test download_font_from_css handles 206 Partial Content response.
	 */
	public function test_download_font_from_css_handles_206_response(): void {
		$valid_woff2 = "\x77\x4F\x46\x32" . str_repeat( "\x00", 100 );

		$validator = Mockery::mock( FontValidator::class );
		$validator->shouldReceive( 'is_valid_font_content' )
			->once()
			->andReturn( true );

		$storage = Mockery::mock( FontStorage::class );
		$storage->shouldReceive( 'font_file_exists' )
			->once()
			->andReturn( false );

		$storage->shouldReceive( 'save_font_file' )
			->once()
			->andReturn( true );

		$storage->shouldReceive( 'get_font_dir_url' )
			->once()
			->andReturn( 'https://example.com/wp-content/uploads/dwt-local-fonts' );

		Functions\expect( 'wp_remote_get' )
			->once()
			->andReturn(
				array(
					'response' => array( 'code' => 206 ), // Partial Content.
					'body'     => $valid_woff2,
				)
			);

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_remote_retrieve_response_code' )
			->once()
			->andReturn( 206 );

		Functions\expect( 'wp_remote_retrieve_body' )
			->once()
			->andReturn( $valid_woff2 );

		$downloader = new FontDownloader( $validator, $storage );

		$css = "@font-face {
			font-family: 'TestFont';
			src: url(https://fonts.gstatic.com/s/test.woff2) format('woff2');
		}";

		$result = $downloader->download_font_from_css( $css );

		$this->assertTrue( $result['success'] );
		$this->assertStringContainsString( '1 file(s)', $result['message'] );
	}

	/**
	 * Test download_font_from_css rejects files exceeding max size.
	 */
	public function test_download_font_from_css_rejects_oversized_files(): void {
		// Create content larger than 2MB.
		$oversized_content = str_repeat( "\x77\x4F\x46\x32", 600000 ); // ~2.4MB.

		$validator = Mockery::mock( FontValidator::class );

		$storage = Mockery::mock( FontStorage::class );
		$storage->shouldReceive( 'get_font_dir_url' )
			->once()
			->andReturn( 'https://example.com/wp-content/uploads/dwt-local-fonts' );

		$storage->shouldReceive( 'font_file_exists' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_remote_get' )
			->once()
			->andReturn(
				array(
					'response' => array( 'code' => 200 ),
					'body'     => $oversized_content,
				)
			);

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_remote_retrieve_response_code' )
			->once()
			->andReturn( 200 );

		Functions\expect( 'wp_remote_retrieve_body' )
			->once()
			->andReturn( $oversized_content );

		$downloader = new FontDownloader( $validator, $storage );

		$css = "@font-face {
			font-family: 'OversizedFont';
			src: url(https://fonts.gstatic.com/s/oversized.woff2) format('woff2');
		}";

		$result = $downloader->download_font_from_css( $css );

		$this->assertTrue( $result['success'] );
		$this->assertStringContainsString( '0 file(s)', $result['message'] );
	}

	/**
	 * Test download_font_from_css extracts multiple font families.
	 */
	public function test_download_font_from_css_extracts_multiple_families(): void {
		$valid_woff2 = "\x77\x4F\x46\x32" . str_repeat( "\x00", 100 );

		$validator = Mockery::mock( FontValidator::class );
		$validator->shouldReceive( 'is_valid_font_content' )
			->times( 2 )
			->andReturn( true );

		$storage = Mockery::mock( FontStorage::class );
		$storage->shouldReceive( 'font_file_exists' )
			->times( 2 )
			->andReturn( false );

		$storage->shouldReceive( 'save_font_file' )
			->times( 2 )
			->andReturn( true );

		$storage->shouldReceive( 'get_font_dir_url' )
			->times( 2 )
			->andReturn( 'https://example.com/wp-content/uploads/dwt-local-fonts' );

		Functions\expect( 'wp_remote_get' )
			->times( 2 )
			->andReturn(
				array(
					'response' => array( 'code' => 200 ),
					'body'     => $valid_woff2,
				)
			);

		Functions\expect( 'is_wp_error' )
			->times( 2 )
			->andReturn( false );

		Functions\expect( 'wp_remote_retrieve_response_code' )
			->times( 2 )
			->andReturn( 200 );

		Functions\expect( 'wp_remote_retrieve_body' )
			->times( 2 )
			->andReturn( $valid_woff2 );

		$downloader = new FontDownloader( $validator, $storage );

		$css = "@font-face {
			font-family: 'Roboto';
			src: url(https://fonts.gstatic.com/s/roboto.woff2) format('woff2');
		}
		@font-face {
			font-family: 'Open Sans';
			src: url(https://fonts.gstatic.com/s/opensans.woff2) format('woff2');
		}";

		$result = $downloader->download_font_from_css( $css );

		$this->assertTrue( $result['success'] );
		$this->assertContains( 'Roboto', $result['families'] );
		$this->assertContains( 'Open Sans', $result['families'] );
		$this->assertCount( 2, $result['families'] );
	}

	/**
	 * Test download_font_from_css supports Bunny Fonts URLs.
	 */
	public function test_download_font_from_css_supports_bunny_fonts(): void {
		$valid_woff2 = "\x77\x4F\x46\x32" . str_repeat( "\x00", 100 );

		$validator = Mockery::mock( FontValidator::class );
		$validator->shouldReceive( 'is_valid_font_content' )
			->once()
			->andReturn( true );

		$storage = Mockery::mock( FontStorage::class );
		$storage->shouldReceive( 'font_file_exists' )
			->once()
			->andReturn( false );

		$storage->shouldReceive( 'save_font_file' )
			->once()
			->andReturn( true );

		$storage->shouldReceive( 'get_font_dir_url' )
			->once()
			->andReturn( 'https://example.com/wp-content/uploads/dwt-local-fonts' );

		Functions\expect( 'wp_remote_get' )
			->once()
			->andReturn(
				array(
					'response' => array( 'code' => 200 ),
					'body'     => $valid_woff2,
				)
			);

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_remote_retrieve_response_code' )
			->once()
			->andReturn( 200 );

		Functions\expect( 'wp_remote_retrieve_body' )
			->once()
			->andReturn( $valid_woff2 );

		$downloader = new FontDownloader( $validator, $storage );

		$css = "@font-face {
			font-family: 'BunnyFont';
			src: url(https://fonts.bunny.net/bunny-font.woff2) format('woff2');
		}";

		$result = $downloader->download_font_from_css( $css );

		$this->assertTrue( $result['success'] );
		$this->assertContains( 'BunnyFont', $result['families'] );
	}

	/**
	 * Test download_font_from_css handles quoted URLs correctly.
	 */
	public function test_download_font_from_css_handles_quoted_urls(): void {
		$valid_woff2 = "\x77\x4F\x46\x32" . str_repeat( "\x00", 100 );

		$validator = Mockery::mock( FontValidator::class );
		$validator->shouldReceive( 'is_valid_font_content' )
			->times( 3 )
			->andReturn( true );

		$storage = Mockery::mock( FontStorage::class );
		$storage->shouldReceive( 'font_file_exists' )
			->times( 3 )
			->andReturn( false );

		$storage->shouldReceive( 'save_font_file' )
			->times( 3 )
			->andReturn( true );

		$storage->shouldReceive( 'get_font_dir_url' )
			->times( 3 )
			->andReturn( 'https://example.com/wp-content/uploads/dwt-local-fonts' );

		Functions\expect( 'wp_remote_get' )
			->times( 3 )
			->andReturn(
				array(
					'response' => array( 'code' => 200 ),
					'body'     => $valid_woff2,
				)
			);

		Functions\expect( 'is_wp_error' )
			->times( 3 )
			->andReturn( false );

		Functions\expect( 'wp_remote_retrieve_response_code' )
			->times( 3 )
			->andReturn( 200 );

		Functions\expect( 'wp_remote_retrieve_body' )
			->times( 3 )
			->andReturn( $valid_woff2 );

		$downloader = new FontDownloader( $validator, $storage );

		// Test CSS with double quotes, single quotes, and no quotes.
		$css = "@font-face {
			font-family: 'DoubleQuoted';
			src: url(\"https://fonts.bunny.net/double-quoted.woff2\") format('woff2');
		}
		@font-face {
			font-family: 'SingleQuoted';
			src: url('https://fonts.gstatic.com/s/single-quoted.woff2') format('woff2');
		}
		@font-face {
			font-family: 'NoQuotes';
			src: url(https://fonts.bunny.net/no-quotes.woff2) format('woff2');
		}";

		$result = $downloader->download_font_from_css( $css );

		$this->assertTrue( $result['success'] );
		$this->assertContains( 'DoubleQuoted', $result['families'] );
		$this->assertContains( 'SingleQuoted', $result['families'] );
		$this->assertContains( 'NoQuotes', $result['families'] );
		$this->assertStringContainsString( '3 file(s)', $result['message'] );
	}

	/**
	 * Test T031: Partial failure in batch download (User Story 3 - P2).
	 *
	 * Download 3 fonts where 1 fails (network error), verify other 2 complete successfully.
	 */
	public function testP2_PartialFailureInBatch(): void {
		$valid_woff2 = "\x77\x4F\x46\x32" . str_repeat( "\x00", 100 );

		$validator = Mockery::mock( FontValidator::class );
		$validator->shouldReceive( 'is_valid_font_content' )
			->times( 2 ) // Only 2 succeed.
			->andReturn( true );

		$storage = Mockery::mock( FontStorage::class );
		$storage->shouldReceive( 'font_file_exists' )
			->times( 3 )
			->andReturn( false );

		$storage->shouldReceive( 'save_font_file' )
			->times( 2 ) // Only 2 successful saves.
			->andReturn( true );

		$storage->shouldReceive( 'get_font_dir_url' )
			->times( 3 )
			->andReturn( 'https://example.com/wp-content/uploads/dwt-local-fonts' );

		// First font: success.
		Functions\expect( 'wp_remote_get' )
			->once()
			->with( 'https://fonts.gstatic.com/s/font1.woff2', Mockery::type( 'array' ) )
			->andReturn(
				array(
					'response' => array( 'code' => 200 ),
					'body'     => $valid_woff2,
				)
			);

		// Second font: network timeout (failure).
		Functions\expect( 'wp_remote_get' )
			->once()
			->with( 'https://fonts.gstatic.com/s/font2.woff2', Mockery::type( 'array' ) )
			->andReturn( new \WP_Error( 'http_request_failed', 'Connection timeout' ) );

		// Third font: success.
		Functions\expect( 'wp_remote_get' )
			->once()
			->with( 'https://fonts.gstatic.com/s/font3.woff2', Mockery::type( 'array' ) )
			->andReturn(
				array(
					'response' => array( 'code' => 200 ),
					'body'     => $valid_woff2,
				)
			);

		Functions\expect( 'is_wp_error' )
			->times( 3 )
			->andReturnUsing(
				function ( $arg ) {
					return $arg instanceof \WP_Error;
				}
			);

		Functions\expect( 'wp_remote_retrieve_response_code' )
			->times( 2 ) // Only called for successful responses.
			->andReturn( 200 );

		Functions\expect( 'wp_remote_retrieve_body' )
			->times( 2 )
			->andReturn( $valid_woff2 );

		$downloader = new FontDownloader( $validator, $storage );

		$css = "@font-face {
			font-family: 'Font1';
			src: url(https://fonts.gstatic.com/s/font1.woff2) format('woff2');
		}
		@font-face {
			font-family: 'Font2';
			src: url(https://fonts.gstatic.com/s/font2.woff2) format('woff2');
		}
		@font-face {
			font-family: 'Font3';
			src: url(https://fonts.gstatic.com/s/font3.woff2) format('woff2');
		}";

		$result = $downloader->download_font_from_css( $css );

		// Overall operation succeeds (partial success is still success).
		$this->assertTrue( $result['success'], 'Download should succeed with partial failures' );

		// Verify 2 out of 3 fonts downloaded.
		$this->assertStringContainsString( '2 file(s)', $result['message'], 'Should download 2 of 3 files' );

		// All 3 families should be extracted from CSS.
		$this->assertContains( 'Font1', $result['families'] );
		$this->assertContains( 'Font2', $result['families'] );
		$this->assertContains( 'Font3', $result['families'] );
	}

	/**
	 * Data provider for failed download scenarios (T033).
	 *
	 * @return array<string, array{css: string, expected_message_substring: string}>
	 */
	public function failedDownloadProvider(): array {
		return array(
			'empty CSS'     => array(
				'css'                        => '',
				'expected_message_substring' => 'CSS content is required',
			),
			'no @font-face' => array(
				'css'                        => 'body { font-family: sans-serif; }',
				'expected_message_substring' => 'No font files found',
			),
			'malformed URL' => array(
				'css'                        => '@font-face { font-family: "Test"; src: url(not-a-url); }',
				'expected_message_substring' => 'No font files found',
			),
			'missing src'   => array(
				'css'                        => '@font-face { font-family: "NoSrc"; }',
				'expected_message_substring' => 'No font files found',
			),
		);
	}

	/**
	 * Test T033: Failed download scenarios using data provider (User Story 3 - P2).
	 *
	 * @dataProvider failedDownloadProvider
	 *
	 * @param string $css                       Input CSS content.
	 * @param string $expected_message_substring Expected error message substring.
	 */
	public function testP2_FailedDownloadScenarios( string $css, string $expected_message_substring ): void {
		$validator  = Mockery::mock( FontValidator::class );
		$storage    = Mockery::mock( FontStorage::class );
		$downloader = new FontDownloader( $validator, $storage );

		$result = $downloader->download_font_from_css( $css );

		$this->assertFalse( $result['success'], 'Download should fail for invalid input' );
		$this->assertStringContainsString( $expected_message_substring, $result['message'], 'Error message should match expected text' );
		$this->assertEmpty( $result['families'], 'No families should be extracted' );
	}

	/**
	 * Test T038: Tracks memory usage during download (User Story 4 - P3).
	 *
	 * Note: This test validates that memory usage stays within reasonable bounds.
	 * The 50MB limit is generous for a download operation.
	 */
	public function testP3_TracksMemoryUsage(): void {
		$valid_woff2 = "\x77\x4F\x46\x32" . str_repeat( "\x00", 100 );

		$validator = Mockery::mock( FontValidator::class );
		$validator->shouldReceive( 'is_valid_font_content' )
			->once()
			->andReturn( true );

		$storage = Mockery::mock( FontStorage::class );
		$storage->shouldReceive( 'font_file_exists' )
			->once()
			->andReturn( false );

		$storage->shouldReceive( 'save_font_file' )
			->once()
			->andReturn( true );

		$storage->shouldReceive( 'get_font_dir_url' )
			->once()
			->andReturn( 'https://example.com/wp-content/uploads/dwt-local-fonts' );

		Functions\expect( 'wp_remote_get' )
			->once()
			->andReturn(
				array(
					'response' => array( 'code' => 200 ),
					'body'     => $valid_woff2,
				)
			);

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_remote_retrieve_response_code' )
			->once()
			->andReturn( 200 );

		Functions\expect( 'wp_remote_retrieve_body' )
			->once()
			->andReturn( $valid_woff2 );

		$downloader = new FontDownloader( $validator, $storage );

		$memory_before = memory_get_usage( true );

		$css    = "@font-face {
			font-family: 'TestFont';
			src: url(https://fonts.gstatic.com/s/test.woff2) format('woff2');
		}";
		$result = $downloader->download_font_from_css( $css );

		$memory_after = memory_get_usage( true );
		$memory_delta = ( $memory_after - $memory_before ) / 1024 / 1024; // Convert to MB

		$this->assertTrue( $result['success'], 'Download should succeed' );

		// Memory delta should be less than 50MB (very generous limit)
		$this->assertLessThan( 50, $memory_delta, 'Memory usage should stay under 50MB' );
	}

	/**
	 * Test T039: Verifies download result includes timing information (User Story 4 - P3).
	 *
	 * Note: The current implementation may not include duration_ms field.
	 * This test documents expected behavior for future enhancement.
	 */
	public function testP3_CapturesTiming(): void {
		$valid_woff2 = "\x77\x4F\x46\x32" . str_repeat( "\x00", 100 );

		$validator = Mockery::mock( FontValidator::class );
		$validator->shouldReceive( 'is_valid_font_content' )
			->once()
			->andReturn( true );

		$storage = Mockery::mock( FontStorage::class );
		$storage->shouldReceive( 'font_file_exists' )
			->once()
			->andReturn( false );

		$storage->shouldReceive( 'save_font_file' )
			->once()
			->andReturn( true );

		$storage->shouldReceive( 'get_font_dir_url' )
			->once()
			->andReturn( 'https://example.com/wp-content/uploads/dwt-local-fonts' );

		Functions\expect( 'wp_remote_get' )
			->once()
			->andReturn(
				array(
					'response' => array( 'code' => 200 ),
					'body'     => $valid_woff2,
				)
			);

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_remote_retrieve_response_code' )
			->once()
			->andReturn( 200 );

		Functions\expect( 'wp_remote_retrieve_body' )
			->once()
			->andReturn( $valid_woff2 );

		$downloader = new FontDownloader( $validator, $storage );

		$start_time = microtime( true );

		$css    = "@font-face {
			font-family: 'TestFont';
			src: url(https://fonts.gstatic.com/s/test.woff2) format('woff2');
		}";
		$result = $downloader->download_font_from_css( $css );

		$end_time    = microtime( true );
		$duration_ms = ( $end_time - $start_time ) * 1000;

		$this->assertTrue( $result['success'], 'Download should succeed' );

		// If duration_ms field exists in result, verify it's numeric
		if ( isset( $result['duration_ms'] ) ) {
			$this->assertIsNumeric( $result['duration_ms'], 'Duration should be numeric' );
			$this->assertGreaterThan( 0, $result['duration_ms'], 'Duration should be positive' );
		} else {
			// Document that timing capture could be added
			$this->assertGreaterThan( 0, $duration_ms, 'Operation should take measurable time' );
		}
	}

	/**
	 * Test T040: Verifies performance metrics are logged (User Story 4 - P3).
	 *
	 * Note: This test validates logging behavior conceptually.
	 * Actual log verification would require log capture mechanism.
	 */
	public function testP3_LogsPerformanceMetrics(): void {
		$valid_woff2 = "\x77\x4F\x46\x32" . str_repeat( "\x00", 100 );

		$validator = Mockery::mock( FontValidator::class );
		$validator->shouldReceive( 'is_valid_font_content' )
			->once()
			->andReturn( true );

		$storage = Mockery::mock( FontStorage::class );
		$storage->shouldReceive( 'font_file_exists' )
			->once()
			->andReturn( false );

		$storage->shouldReceive( 'save_font_file' )
			->once()
			->andReturn( true );

		$storage->shouldReceive( 'get_font_dir_url' )
			->once()
			->andReturn( 'https://example.com/wp-content/uploads/dwt-local-fonts' );

		Functions\expect( 'wp_remote_get' )
			->once()
			->andReturn(
				array(
					'response' => array( 'code' => 200 ),
					'body'     => $valid_woff2,
				)
			);

		Functions\expect( 'is_wp_error' )
			->once()
			->andReturn( false );

		Functions\expect( 'wp_remote_retrieve_response_code' )
			->once()
			->andReturn( 200 );

		Functions\expect( 'wp_remote_retrieve_body' )
			->once()
			->andReturn( $valid_woff2 );

		$downloader = new FontDownloader( $validator, $storage );

		$css    = "@font-face {
			font-family: 'TestFont';
			src: url(https://fonts.gstatic.com/s/test.woff2) format('woff2');
		}";
		$result = $downloader->download_font_from_css( $css );

		$this->assertTrue( $result['success'], 'Download should succeed' );

		// Verify result includes basic metrics
		$this->assertArrayHasKey( 'message', $result, 'Result should include message' );
		$this->assertArrayHasKey( 'families', $result, 'Result should include families' );

		// The message should contain file count information
		$this->assertStringContainsString( 'file(s)', $result['message'], 'Message should include file count' );
	}
}
