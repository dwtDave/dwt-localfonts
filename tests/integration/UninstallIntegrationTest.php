<?php
/**
 * Uninstall integration tests.
 *
 * @package DWT\LocalFonts
 */

declare(strict_types=1);

namespace DWT\LocalFonts\Tests\Integration;

use WP_UnitTestCase;

/**
 * Integration test case for uninstall functionality.
 */
final class UninstallIntegrationTest extends WP_UnitTestCase {

	/**
	 * Test font directory path.
	 *
	 * @var string
	 */
	private string $font_dir;

	/**
	 * Test font file path.
	 *
	 * @var string
	 */
	private string $test_font_file;

	/**
	 * Set up test environment.
	 */
	public function set_up(): void {
		parent::set_up();

		$upload_dir           = wp_upload_dir();
		$this->font_dir       = trailingslashit( $upload_dir['basedir'] ) . 'dwt-local-fonts';
		$this->test_font_file = $this->font_dir . '/test-font.woff2';

		// Create font directory and test file.
		if ( ! file_exists( $this->font_dir ) ) {
			wp_mkdir_p( $this->font_dir );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test file creation.
		file_put_contents( $this->test_font_file, 'test font data' );
	}

	/**
	 * Tear down test environment.
	 */
	public function tear_down(): void {
		// Clean up test files.
		if ( file_exists( $this->test_font_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Test file cleanup.
			unlink( $this->test_font_file );
		}

		if ( is_dir( $this->font_dir ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Test cleanup.
			rmdir( $this->font_dir );
		}

		// Clean up options.
		delete_option( 'dwt_local_fonts_settings' );
		delete_option( 'dwt_local_fonts_list' );

		parent::tear_down();
	}

	/**
	 * Test fonts are deleted when keep_fonts_on_uninstall is disabled.
	 */
	public function test_fonts_deleted_when_setting_disabled(): void {
		// Set keep_fonts_on_uninstall to disabled.
		update_option(
			'dwt_local_fonts_settings',
			array(
				'keep_fonts_on_uninstall' => '0',
			)
		);

		// Verify font file exists before uninstall.
		$this->assertFileExists( $this->test_font_file, 'Test font file should exist before uninstall' );

		// Load and execute the uninstall script.
		$this->simulate_uninstall();

		// Verify font files are deleted.
		$this->assertFileDoesNotExist( $this->test_font_file, 'Font file should be deleted when setting is disabled' );
		$this->assertDirectoryDoesNotExist( $this->font_dir, 'Font directory should be deleted when setting is disabled' );
	}

	/**
	 * Test fonts are preserved when keep_fonts_on_uninstall is enabled.
	 */
	public function test_fonts_preserved_when_setting_enabled(): void {
		// Set keep_fonts_on_uninstall to enabled.
		update_option(
			'dwt_local_fonts_settings',
			array(
				'keep_fonts_on_uninstall' => '1',
			)
		);

		// Verify font file exists before uninstall.
		$this->assertFileExists( $this->test_font_file, 'Test font file should exist before uninstall' );

		// Load and execute the uninstall script.
		$this->simulate_uninstall();

		// Verify font files are preserved.
		$this->assertFileExists( $this->test_font_file, 'Font file should be preserved when setting is enabled' );
		$this->assertDirectoryExists( $this->font_dir, 'Font directory should be preserved when setting is enabled' );
	}

	/**
	 * Test fonts are deleted when keep_fonts_on_uninstall is not set (default behavior).
	 */
	public function test_fonts_deleted_when_setting_not_set(): void {
		// Don't set keep_fonts_on_uninstall (default behavior).
		update_option( 'dwt_local_fonts_settings', array() );

		// Verify font file exists before uninstall.
		$this->assertFileExists( $this->test_font_file, 'Test font file should exist before uninstall' );

		// Load and execute the uninstall script.
		$this->simulate_uninstall();

		// Verify font files are deleted (default behavior).
		$this->assertFileDoesNotExist( $this->test_font_file, 'Font file should be deleted by default' );
		$this->assertDirectoryDoesNotExist( $this->font_dir, 'Font directory should be deleted by default' );
	}

	/**
	 * Test options are always deleted regardless of keep_fonts_on_uninstall setting.
	 */
	public function test_options_always_deleted(): void {
		// Set up test options.
		update_option(
			'dwt_local_fonts_settings',
			array(
				'keep_fonts_on_uninstall' => '1',
				'font_display_swap'       => '1',
			)
		);
		update_option( 'dwt_local_fonts_list', array( 'Roboto', 'Open Sans' ) );

		// Verify options exist before uninstall.
		$this->assertNotFalse( get_option( 'dwt_local_fonts_settings' ), 'Settings should exist before uninstall' );
		$this->assertNotFalse( get_option( 'dwt_local_fonts_list' ), 'Font list should exist before uninstall' );

		// Load and execute the uninstall script.
		$this->simulate_uninstall();

		// Verify options are deleted even when keep_fonts_on_uninstall is enabled.
		$this->assertFalse( get_option( 'dwt_local_fonts_settings' ), 'Settings should be deleted' );
		$this->assertFalse( get_option( 'dwt_local_fonts_list' ), 'Font list should be deleted' );
	}

	/**
	 * Simulate the uninstall process by loading and executing the uninstall script.
	 */
	private function simulate_uninstall(): void {
		// Define the WP_UNINSTALL_PLUGIN constant required by uninstall.php.
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'dwt-localfonts/dwt-localfonts.php' );
		}

		// Load the uninstall script.
		$uninstall_file = dirname( DWT_LOCAL_FONTS_PLUGIN_FILE ) . '/uninstall.php';
		if ( file_exists( $uninstall_file ) ) {
			require $uninstall_file;
		}
	}
}
