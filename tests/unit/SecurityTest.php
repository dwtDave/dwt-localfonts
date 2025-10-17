<?php
/**
 * Security class tests.
 *
 * @package DWT\LocalFonts
 */

declare(strict_types=1);

namespace DWT\LocalFonts\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use DWT\LocalFonts\Modules\Security;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

/**
 * Test case for the Security class.
 */
final class SecurityTest extends TestCase {
	use MockeryPHPUnitIntegration;

	/**
	 * Set up test environment before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Mock WordPress functions.
		Functions\when( 'trailingslashit' )->alias(
			function ( $string ) {
				return rtrim( $string, '/\\' ) . '/';
			}
		);
		Functions\when( 'sanitize_text_field' )->returnArg();
	}

	/**
	 * Tear down test environment after each test.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test validate_file_path with valid file within base directory.
	 */
	public function test_validate_file_path_with_valid_file(): void {
		// Create temporary directory structure.
		$temp_dir  = sys_get_temp_dir() . '/dwt_test_' . uniqid();
		$base_path = $temp_dir . '/fonts';
		mkdir( $base_path, 0777, true );

		// Create a test file.
		$test_file = $base_path . '/test.woff2';
		touch( $test_file );

		// Test validation.
		$result = Security::validate_file_path( $test_file, $base_path );

		$this->assertNotFalse( $result );
		$this->assertStringContainsString( 'test.woff2', $result );

		// Cleanup.
		unlink( $test_file );
		rmdir( $base_path );
		rmdir( $temp_dir );
	}

	/**
	 * Test validate_file_path with path traversal attempt using parent directory.
	 */
	public function test_validate_file_path_blocks_parent_directory_traversal(): void {
		// Create temporary directory structure.
		$temp_dir  = sys_get_temp_dir() . '/dwt_test_' . uniqid();
		$base_path = $temp_dir . '/fonts';
		mkdir( $base_path, 0777, true );

		// Create a file outside base directory.
		$outside_file = $temp_dir . '/outside.txt';
		touch( $outside_file );

		// Attempt to access file outside base using path traversal.
		$traversal_path = $base_path . '/../outside.txt';

		// Test validation.
		$result = Security::validate_file_path( $traversal_path, $base_path );

		// Should return false because resolved path is outside base.
		$this->assertFalse( $result );

		// Cleanup.
		unlink( $outside_file );
		rmdir( $base_path );
		rmdir( $temp_dir );
	}

	/**
	 * Test validate_file_path with non-existent file.
	 */
	public function test_validate_file_path_with_nonexistent_file(): void {
		$base_path   = sys_get_temp_dir() . '/dwt_fonts';
		$nonexistent = $base_path . '/nonexistent.woff2';

		// Test validation.
		$result = Security::validate_file_path( $nonexistent, $base_path );

		// Should return false because file doesn't exist.
		$this->assertFalse( $result );
	}

	/**
	 * Test validate_file_path with symlink pointing outside base directory.
	 */
	public function test_validate_file_path_blocks_symlink_traversal(): void {
		// Skip test on Windows where symlinks require special permissions.
		if ( DIRECTORY_SEPARATOR === '\\' ) {
			$this->markTestSkipped( 'Symlink tests skipped on Windows.' );
		}

		// Create temporary directory structure.
		$temp_dir  = sys_get_temp_dir() . '/dwt_test_' . uniqid();
		$base_path = $temp_dir . '/fonts';
		mkdir( $base_path, 0777, true );

		// Create a file outside base directory.
		$outside_file = $temp_dir . '/secret.txt';
		touch( $outside_file );

		// Create symlink inside base pointing to outside file.
		$symlink = $base_path . '/link.txt';
		symlink( $outside_file, $symlink );

		// Test validation.
		$result = Security::validate_file_path( $symlink, $base_path );

		// Should return false because symlink resolves to path outside base.
		$this->assertFalse( $result );

		// Cleanup.
		unlink( $symlink );
		unlink( $outside_file );
		rmdir( $base_path );
		rmdir( $temp_dir );
	}

	/**
	 * Test validate_file_path with valid subdirectory structure.
	 */
	public function test_validate_file_path_allows_valid_subdirectories(): void {
		// Create temporary directory structure.
		$temp_dir  = sys_get_temp_dir() . '/dwt_test_' . uniqid();
		$base_path = $temp_dir . '/fonts';
		$sub_dir   = $base_path . '/subset';
		mkdir( $sub_dir, 0777, true );

		// Create a test file in subdirectory.
		$test_file = $sub_dir . '/font.woff2';
		touch( $test_file );

		// Test validation.
		$result = Security::validate_file_path( $test_file, $base_path );

		$this->assertNotFalse( $result );
		$this->assertStringContainsString( 'font.woff2', $result );

		// Cleanup.
		unlink( $test_file );
		rmdir( $sub_dir );
		rmdir( $base_path );
		rmdir( $temp_dir );
	}

	/**
	 * Test validate_file_path with directory instead of file.
	 */
	public function test_validate_file_path_with_directory(): void {
		// Create temporary directory structure.
		$temp_dir  = sys_get_temp_dir() . '/dwt_test_' . uniqid();
		$base_path = $temp_dir . '/fonts';
		$sub_dir   = $base_path . '/subset';
		mkdir( $sub_dir, 0777, true );

		// Test validation with directory.
		$result = Security::validate_file_path( $sub_dir, $base_path );

		// Should return the real path of the directory.
		$this->assertNotFalse( $result );

		// Cleanup.
		rmdir( $sub_dir );
		rmdir( $base_path );
		rmdir( $temp_dir );
	}

	/**
	 * Test generate_secure_token produces expected length.
	 */
	public function test_generate_secure_token_length(): void {
		$token = Security::generate_secure_token( 32 );

		$this->assertSame( 32, strlen( $token ) );
	}

	/**
	 * Test generate_secure_token produces unique values.
	 */
	public function test_generate_secure_token_uniqueness(): void {
		$token1 = Security::generate_secure_token( 32 );
		$token2 = Security::generate_secure_token( 32 );

		$this->assertNotEquals( $token1, $token2 );
	}

	/**
	 * Test constructor registers hooks.
	 */
	public function test_constructor_registers_hooks(): void {
		Functions\expect( 'add_action' )
			->once()
			->with( 'send_headers', \Mockery::type( 'array' ) );

		Functions\expect( 'add_action' )
			->once()
			->with( 'admin_head', \Mockery::type( 'array' ) );

		Functions\expect( 'add_filter' )
			->once()
			->with( 'dwt_local_fonts_can_download', \Mockery::type( 'array' ), 10, 1 );

		new Security();
	}

	/**
	 * Test add_security_headers does nothing on non-plugin pages.
	 */
	public function test_add_security_headers_skips_non_plugin_pages(): void {
		$security = new Security();

		$_GET['page'] = 'other-page';

		// No headers should be sent.
		ob_start();
		$security->add_security_headers();
		$output = ob_get_clean();

		$this->assertEmpty( $output );

		unset( $_GET['page'] );
	}

	/**
	 * Test add_security_headers checks page parameter correctly.
	 *
	 * Note: We cannot test actual header() calls in PHPUnit because headers
	 * have already been sent. This test verifies the page check logic works.
	 */
	public function test_add_security_headers_checks_page_parameter(): void {
		$security = new Security();

		// Test with wrong page - method should return early without calling header().
		$_GET['page'] = 'other-page';

		ob_start();
		$security->add_security_headers();
		$output = ob_get_clean();

		unset( $_GET['page'] );

		// Method should have returned early, so no error.
		$this->assertEmpty( $output );

		// Note: Testing with correct page would call header() which fails in PHPUnit.
		// That functionality is better tested in integration tests.
	}

	/**
	 * Test add_admin_csp skips non-settings pages.
	 */
	public function test_add_admin_csp_skips_non_settings_pages(): void {
		$security = new Security();

		// Mock get_current_screen to return wrong screen.
		Functions\expect( 'get_current_screen' )
			->once()
			->andReturn( (object) array( 'id' => 'other-page' ) );

		ob_start();
		$security->add_admin_csp();
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/**
	 * Test add_admin_csp skips when no screen.
	 */
	public function test_add_admin_csp_skips_when_no_screen(): void {
		$security = new Security();

		Functions\expect( 'get_current_screen' )
			->once()
			->andReturn( false );

		ob_start();
		$security->add_admin_csp();
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/**
	 * Test add_admin_csp outputs CSP meta tag on settings page.
	 */
	public function test_add_admin_csp_outputs_meta_tag_on_settings_page(): void {
		$security = new Security();

		Functions\expect( 'get_current_screen' )
			->once()
			->andReturn( (object) array( 'id' => 'settings_page_dwt-local-fonts' ) );

		Functions\when( 'esc_attr' )->returnArg();

		ob_start();
		$security->add_admin_csp();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<meta http-equiv="Content-Security-Policy"', $output );
		$this->assertStringContainsString( "default-src 'self'", $output );
		$this->assertStringContainsString( 'https://fonts.googleapis.com', $output );
		$this->assertStringContainsString( 'http://localhost:5173', $output );
	}

	/**
	 * Test check_download_rate_limit respects existing false.
	 */
	public function test_check_download_rate_limit_respects_existing_false(): void {
		$security = new Security();

		Functions\expect( 'get_transient' )->never();

		$result = $security->check_download_rate_limit( false );

		$this->assertFalse( $result );
	}

	/**
	 * Test check_download_rate_limit blocks when no user.
	 *
	 * Note: get_current_user_id is stubbed in bootstrap to return 0,
	 * so this tests that behavior.
	 */
	public function test_check_download_rate_limit_blocks_when_no_user(): void {
		$security = new Security();

		Functions\expect( 'get_transient' )->never();

		// Bootstrap's get_current_user_id always returns 0.
		$result = $security->check_download_rate_limit( true );

		$this->assertFalse( $result );
	}

	/**
	 * Note: Some Security tests may be better covered by integration tests
	 * due to Brain\Monkey limitations with certain WordPress core functions.
	 */
}
