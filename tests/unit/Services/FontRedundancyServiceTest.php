<?php
declare(strict_types=1);

namespace DWT\LocalFonts\Tests\Unit\Services;

use Brain\Monkey;
use Brain\Monkey\Functions;
use DWT\LocalFonts\Services\FontRedundancyService;
use DWT\LocalFonts\Services\FontStorage;
use DWT\LocalFonts\Services\FontValidator;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for FontRedundancyService.
 *
 * @covers \DWT\LocalFonts\Services\FontRedundancyService
 */
class FontRedundancyServiceTest extends TestCase {
	private FontRedundancyService $service;
	private FontStorage $storage;
	private FontValidator $validator;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->storage   = $this->createMock( FontStorage::class );
		$this->validator = $this->createMock( FontValidator::class );
		$this->service   = new FontRedundancyService( $this->storage, $this->validator );

		// Mock WordPress functions.
		Functions\when( 'get_option' )->justReturn( array( 'Lato', 'Roboto' ) );
		Functions\when( 'size_format' )->returnArg();
		Functions\when( 'date_i18n' )->returnArg();
		Functions\when( 'get_option' )->alias(
			function ( $option, $default = false ) {
				if ( 'date_format' === $option ) {
					return 'F j, Y';
				}
				if ( 'dwt_local_fonts_list' === $option ) {
					return array( 'Lato', 'Roboto' );
				}
				return $default;
			}
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test extract_family_from_filename with single-word family.
	 *
	 * @test
	 */
	public function test_extract_family_from_filename_returns_lowercase_prefix(): void {
		$filename = 'lato-latin-400-normal.woff2';
		$family   = $this->service->extract_family_from_filename( $filename );

		$this->assertEquals( 'lato', $family );
	}

	/**
	 * Test extract_family_from_filename with multi-word family.
	 *
	 * @test
	 */
	public function test_extract_family_handles_multi_word_family_names(): void {
		$filename = 'open-sans-latin-400-normal.woff2';
		$family   = $this->service->extract_family_from_filename( $filename );

		// For "open-sans", the first part before hyphen is "open".
		// This is expected behavior per research.md pattern matching strategy.
		$this->assertEquals( 'open', $family );
	}

	/**
	 * Test extract_family_from_filename with uppercase.
	 *
	 * @test
	 */
	public function test_extract_family_handles_case_insensitivity(): void {
		$filename = 'Roboto-Cyrillic-300-normal.woff2';
		$family   = $this->service->extract_family_from_filename( $filename );

		$this->assertEquals( 'roboto', $family );
	}

	/**
	 * Test is_file_orphaned with tracked family.
	 *
	 * @test
	 */
	public function test_is_file_orphaned_returns_false_for_tracked_font(): void {
		$tracked_families = array( 'Lato', 'Roboto' );
		$is_orphaned      = $this->service->is_file_orphaned( 'lato-latin-400-normal.woff2', $tracked_families );

		$this->assertFalse( $is_orphaned );
	}

	/**
	 * Test is_file_orphaned with untracked family.
	 *
	 * @test
	 */
	public function test_is_file_orphaned_returns_true_for_untracked_font(): void {
		$tracked_families = array( 'Lato', 'Roboto' );
		$is_orphaned      = $this->service->is_file_orphaned( 'inter-latin-300-normal.woff', $tracked_families );

		$this->assertTrue( $is_orphaned );
	}

	/**
	 * Test is_file_orphaned with case-insensitive matching.
	 *
	 * @test
	 */
	public function test_is_file_orphaned_is_case_insensitive(): void {
		$tracked_families = array( 'Lato', 'Roboto' );
		$is_orphaned      = $this->service->is_file_orphaned( 'LATO-latin-400-normal.woff2', $tracked_families );

		$this->assertFalse( $is_orphaned, 'Should match case-insensitively' );
	}

	/**
	 * Test generate_report identifies orphaned files.
	 *
	 * @test
	 */
	public function test_generate_report_identifies_orphaned_files(): void {
		// Mock filesystem with 3 files, only 2 families tracked (Lato, Roboto).
		$this->storage->method( 'get_all_font_files' )
			->willReturn(
				array(
					'lato-latin-400-normal.woff2',
					'roboto-greek-700-italic.woff',
					'inter-latin-300-normal.woff', // Orphaned (Inter not tracked).
				)
			);

		$this->storage->method( 'get_font_dir_path' )
			->willReturn( '/path/to/fonts' );

		// Mock file metadata functions.
		Functions\when( 'filesize' )->justReturn( 24000 );
		Functions\when( 'filemtime' )->justReturn( 1697462400 );

		$report = $this->service->generate_report();

		$this->assertIsArray( $report );
		$this->assertArrayHasKey( 'orphaned_files', $report );
		$this->assertArrayHasKey( 'total_count', $report );
		$this->assertCount( 1, $report['orphaned_files'] );
		$this->assertEquals( 'inter-latin-300-normal.woff', $report['orphaned_files'][0]['filename'] );
	}

	/**
	 * Test generate_report excludes CSS file.
	 *
	 * @test
	 */
	public function test_generate_report_excludes_css_file(): void {
		$this->storage->method( 'get_all_font_files' )
			->willReturn(
				array(
					'dwt-local-fonts.css',
					'lato-latin-400-normal.woff2',
				)
			);

		$this->storage->method( 'get_font_dir_path' )
			->willReturn( '/path/to/fonts' );

		Functions\when( 'filesize' )->justReturn( 24000 );
		Functions\when( 'filemtime' )->justReturn( 1697462400 );

		$report = $this->service->generate_report();

		// CSS file should be filtered out even before orphan check.
		$this->assertCount( 0, $report['orphaned_files'] );
	}

	/**
	 * Test generate_report handles empty directory.
	 *
	 * @test
	 */
	public function test_generate_report_handles_empty_directory(): void {
		$this->storage->method( 'get_all_font_files' )
			->willReturn( array() );

		$this->storage->method( 'get_font_dir_path' )
			->willReturn( '/path/to/fonts' );

		$report = $this->service->generate_report();

		$this->assertCount( 0, $report['orphaned_files'] );
		$this->assertEquals( 0, $report['total_count'] );
		$this->assertEquals( 0, $report['total_size_bytes'] );
	}

	/**
	 * Test generate_report sorts by modified timestamp descending.
	 *
	 * @test
	 */
	public function test_generate_report_sorts_by_modified_timestamp_descending(): void {
		$this->storage->method( 'get_all_font_files' )
			->willReturn(
				array(
					'inter-latin-300-normal.woff',
					'montserrat-latin-400-normal.woff2',
				)
			);

		$this->storage->method( 'get_font_dir_path' )
			->willReturn( '/path/to/fonts' );

		// First file older, second file newer.
		Functions\when( 'filesize' )->justReturn( 24000 );
		Functions\when( 'filemtime' )->alias(
			function ( $path ) {
				if ( strpos( $path, 'inter' ) !== false ) {
					return 1697376000; // Older.
				}
				return 1697462400; // Newer.
			}
		);

		$report = $this->service->generate_report();

		$this->assertCount( 2, $report['orphaned_files'] );
		// Newest first.
		$this->assertStringContainsString( 'montserrat', $report['orphaned_files'][0]['filename'] );
		$this->assertStringContainsString( 'inter', $report['orphaned_files'][1]['filename'] );
	}

	/**
	 * Test generate_report calculates total size correctly.
	 *
	 * @test
	 */
	public function test_generate_report_calculates_total_size(): void {
		$this->storage->method( 'get_all_font_files' )
			->willReturn(
				array(
					'inter-latin-300-normal.woff',
					'montserrat-latin-400-normal.woff2',
				)
			);

		$this->storage->method( 'get_font_dir_path' )
			->willReturn( '/path/to/fonts' );

		Functions\when( 'filemtime' )->justReturn( 1697462400 );
		Functions\when( 'filesize' )->alias(
			function ( $path ) {
				if ( strpos( $path, 'inter' ) !== false ) {
					return 18500;
				}
				return 24000;
			}
		);

		$report = $this->service->generate_report();

		$this->assertEquals( 42500, $report['total_size_bytes'] );
	}
}
