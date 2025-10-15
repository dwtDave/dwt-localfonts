<?php
declare(strict_types=1);

namespace DWT\LocalFonts\Tests\Integration\Services;

use DWT\LocalFonts\Services\FontRedundancyService;
use DWT\LocalFonts\Services\FontStorage;
use DWT\LocalFonts\Services\FontValidator;
use WP_UnitTestCase;

/**
 * Integration tests for FontRedundancyService with real WordPress.
 *
 * @group integration
 * @covers \DWT\LocalFonts\Services\FontRedundancyService
 */
class FontRedundancyServiceIntegrationTest extends WP_UnitTestCase {
	private FontRedundancyService $service;
	private FontStorage $storage;
	private string $test_font_dir;

	public function set_up(): void {
		parent::set_up();

		$this->storage  = new FontStorage( new FontValidator() );
		$this->service  = new FontRedundancyService( $this->storage, new FontValidator() );
		$this->test_font_dir = $this->storage->get_font_dir_path();

		// Ensure font directory exists.
		if ( ! is_dir( $this->test_font_dir ) ) {
			wp_mkdir_p( $this->test_font_dir );
		}

		// Clean up any existing test files.
		$this->cleanup_test_files();
	}

	public function tear_down(): void {
		$this->cleanup_test_files();
		parent::tear_down();
	}

	/**
	 * Clean up test files.
	 */
	private function cleanup_test_files(): void {
		$test_files = glob( $this->test_font_dir . '/test-*' );
		if ( $test_files ) {
			foreach ( $test_files as $file ) {
				if ( is_file( $file ) ) {
					unlink( $file );
				}
			}
		}
	}

	/**
	 * Create test font file.
	 *
	 * @param string $filename Filename to create.
	 * @param int    $size     File size in bytes.
	 */
	private function create_test_font_file( string $filename, int $size = 1000 ): void {
		$file_path = $this->test_font_dir . '/' . $filename;
		file_put_contents( $file_path, str_repeat( 'a', $size ) );
		touch( $file_path );
	}

	/**
	 * Test generate_report with real filesystem.
	 *
	 * @test
	 */
	public function test_generate_report_with_real_filesystem(): void {
		// Create test orphaned files.
		$this->create_test_font_file( 'test-inter-latin-400-normal.woff2', 24000 );
		$this->create_test_font_file( 'test-montserrat-latin-700-normal.woff', 18500 );

		// Track only Lato and Roboto (not Inter or Montserrat).
		update_option( 'dwt_local_fonts_list', array( 'Lato', 'Roboto' ) );

		$report = $this->service->generate_report();

		$this->assertIsArray( $report );
		$this->assertArrayHasKey( 'orphaned_files', $report );
		$this->assertGreaterThanOrEqual( 2, $report['total_count'] );

		// Verify orphaned files are detected.
		$orphaned_filenames = array_column( $report['orphaned_files'], 'filename' );
		$this->assertContains( 'test-inter-latin-400-normal.woff2', $orphaned_filenames );
		$this->assertContains( 'test-montserrat-latin-700-normal.woff', $orphaned_filenames );
	}

	/**
	 * Test generate_report performance with many files.
	 *
	 * @test
	 */
	public function test_generate_report_meets_performance_requirement(): void {
		// Create 100 test files (scaled down from 1000 for faster test execution).
		for ( $i = 0; $i < 100; $i++ ) {
			$this->create_test_font_file( "test-font{$i}-latin-400-normal.woff2", 1000 );
		}

		update_option( 'dwt_local_fonts_list', array( 'Lato' ) );

		$start    = microtime( true );
		$report   = $this->service->generate_report();
		$duration = microtime( true ) - $start;

		// Should complete in reasonable time (< 3 seconds for 100 files).
		// Scaled from requirement: 30s for 1000 files = 0.03s per file * 100 = 3s.
		$this->assertLessThan( 3, $duration, 'Report generation exceeded 3s for 100 files' );
		$this->assertGreaterThanOrEqual( 100, $report['total_count'] );
	}

	/**
	 * Test report structure matches data model.
	 *
	 * @test
	 */
	public function test_report_structure_matches_data_model(): void {
		$this->create_test_font_file( 'test-inter-latin-400-normal.woff2', 5000 );
		update_option( 'dwt_local_fonts_list', array( 'Lato' ) );

		$report = $this->service->generate_report();

		// Verify top-level structure.
		$this->assertArrayHasKey( 'orphaned_files', $report );
		$this->assertArrayHasKey( 'total_count', $report );
		$this->assertArrayHasKey( 'total_size_bytes', $report );
		$this->assertArrayHasKey( 'total_size_formatted', $report );
		$this->assertArrayHasKey( 'generated_at', $report );

		// Verify orphaned file structure.
		if ( count( $report['orphaned_files'] ) > 0 ) {
			$file = $report['orphaned_files'][0];
			$this->assertArrayHasKey( 'filename', $file );
			$this->assertArrayHasKey( 'size_bytes', $file );
			$this->assertArrayHasKey( 'size_formatted', $file );
			$this->assertArrayHasKey( 'modified_timestamp', $file );
			$this->assertArrayHasKey( 'modified_date', $file );
			$this->assertArrayHasKey( 'file_path', $file );
		}
	}

	/**
	 * Test CSS file exclusion in real filesystem.
	 *
	 * @test
	 */
	public function test_css_file_excluded_in_real_scenario(): void {
		// Create CSS file.
		file_put_contents( $this->test_font_dir . '/dwt-local-fonts.css', '/* test */' );
		$this->create_test_font_file( 'test-inter-latin-400-normal.woff2', 1000 );

		update_option( 'dwt_local_fonts_list', array( 'Lato' ) );

		$report = $this->service->generate_report();

		// Verify CSS file is not in orphaned list.
		$orphaned_filenames = array_column( $report['orphaned_files'], 'filename' );
		$this->assertNotContains( 'dwt-local-fonts.css', $orphaned_filenames );
	}
}
