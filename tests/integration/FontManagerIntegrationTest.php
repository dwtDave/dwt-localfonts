<?php
/**
 * FontManager integration tests with WordPress.
 *
 * @package DWT\LocalFonts
 */

declare(strict_types=1);

namespace DWT\LocalFonts\Tests\Integration;

use DWT\LocalFonts\Modules\FontManager;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Integration test case for FontManager with WordPress APIs.
 */
final class FontManagerIntegrationTest extends WP_UnitTestCase {

	/**
	 * FontManager instance.
	 *
	 * @var FontManager
	 */
	private FontManager $font_manager;

	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	private int $admin_user_id;

	/**
	 * REST server instance.
	 *
	 * @var \WP_REST_Server
	 */
	private \WP_REST_Server $server;

	/**
	 * Set up test environment.
	 */
	public function set_up(): void {
		parent::set_up();

		// Create admin user.
		$this->admin_user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_user_id );

		// Initialize REST server.
		global $wp_rest_server;
		$this->server      = $wp_rest_server = new \WP_REST_Server();
		do_action( 'rest_api_init' );

		// Initialize FontManager.
		$this->font_manager = new FontManager();

		// Clean up any existing fonts.
		delete_option( 'dwt_local_fonts_list' );
	}

	/**
	 * Tear down test environment.
	 */
	public function tear_down(): void {
		// Clean up.
		delete_option( 'dwt_local_fonts_list' );
		$this->clean_font_directory();

		global $wp_rest_server;
		$wp_rest_server = null;

		parent::tear_down();
	}

	/**
	 * Test REST route registration.
	 */
	public function test_rest_routes_are_registered(): void {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/dwt-management/v1/fonts/google', $routes );
		$this->assertArrayHasKey( '/dwt-management/v1/fonts/local', $routes );
		$this->assertArrayHasKey( '/dwt-management/v1/fonts/download', $routes );
		$this->assertArrayHasKey( '/dwt-management/v1/fonts/delete', $routes );
	}

	/**
	 * Test get Google fonts endpoint.
	 */
	public function test_get_google_fonts_returns_popular_fonts(): void {
		$request = new WP_REST_Request( 'GET', '/dwt-management/v1/fonts/google' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$response = rest_do_request( $request );

		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertNotEmpty( $data );

		// Check structure of first font.
		$first_font = $data[0];
		$this->assertArrayHasKey( 'family', $first_font );
		$this->assertArrayHasKey( 'category', $first_font );
		$this->assertArrayHasKey( 'variants', $first_font );
	}

	/**
	 * Test get local fonts endpoint.
	 */
	public function test_get_local_fonts_returns_empty_initially(): void {
		$request = new WP_REST_Request( 'GET', '/dwt-management/v1/fonts/local' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$response = rest_do_request( $request );

		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertEmpty( $data );
	}

	/**
	 * Test adding a font to local list.
	 */
	public function test_font_list_option_is_created(): void {
		$fonts = array( 'Roboto', 'Open Sans' );
		update_option( 'dwt_local_fonts_list', $fonts );

		// Create font directory and files.
		$upload_dir    = wp_upload_dir();
		$font_dir      = $upload_dir['basedir'] . '/dwt-local-fonts';
		$css_file_path = $font_dir . '/dwt-local-fonts.css';

		wp_mkdir_p( $font_dir );

		// Create dummy font files.
		file_put_contents( $font_dir . '/roboto-regular.woff2', 'dummy font content' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		file_put_contents( $font_dir . '/open-sans-regular.woff2', 'dummy font content' ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		// Create CSS file with @font-face rules for both fonts.
		$css_content = <<<CSS
@font-face {
	font-family: 'Roboto';
	font-style: normal;
	font-weight: 400;
	src: url('roboto-regular.woff2') format('woff2');
}
@font-face {
	font-family: 'Open Sans';
	font-style: normal;
	font-weight: 400;
	src: url('open-sans-regular.woff2') format('woff2');
}
CSS;
		file_put_contents( $css_file_path, $css_content ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		$request = new WP_REST_Request( 'GET', '/dwt-management/v1/fonts/local' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertCount( 2, $data );
		$this->assertEquals( 'Roboto', $data[0]['family'] );
		$this->assertEquals( 'Open Sans', $data[1]['family'] );

		// Clean up.
		unlink( $css_file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		unlink( $font_dir . '/roboto-regular.woff2' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		unlink( $font_dir . '/open-sans-regular.woff2' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		rmdir( $font_dir );
	}

	/**
	 * Test permission callback for REST endpoints.
	 */
	public function test_rest_endpoints_require_manage_options_capability(): void {
		// Switch to non-admin user.
		$subscriber_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$request = new WP_REST_Request( 'GET', '/dwt-management/v1/fonts/google' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );

		$response = rest_do_request( $request );

		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * Test delete font API with valid font.
	 */
	public function test_delete_font_api_with_valid_font(): void {
		// Add font to list.
		update_option( 'dwt_local_fonts_list', array( 'Roboto', 'Open Sans' ) );

		$request = new WP_REST_Request( 'POST', '/dwt-management/v1/fonts/delete' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_param( 'font_family', 'Roboto' );

		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertTrue( $data['success'] );
		$this->assertCount( 1, $data['downloaded_fonts'] );
		$this->assertEquals( 'Open Sans', $data['downloaded_fonts'][0] );
	}

	/**
	 * Test delete font API with non-existent font.
	 */
	public function test_delete_font_api_with_nonexistent_font(): void {
		update_option( 'dwt_local_fonts_list', array( 'Roboto' ) );

		$request = new WP_REST_Request( 'POST', '/dwt-management/v1/fonts/delete' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_param( 'font_family', 'Nonexistent Font' );

		$response = rest_do_request( $request );

		$this->assertEquals( 404, $response->get_status() );
	}

	/**
	 * Test Google Fonts filtering by search term.
	 */
	public function test_google_fonts_search_filtering(): void {
		$request = new WP_REST_Request( 'GET', '/dwt-management/v1/fonts/google' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_param( 'search', 'Roboto' );

		$response = rest_do_request( $request );
		$data     = $response->get_data();

		// Should only return fonts matching "Roboto".
		foreach ( $data as $font ) {
			$this->assertStringContainsStringIgnoringCase( 'Roboto', $font['family'] );
		}
	}

	/**
	 * Test Google Fonts filtering by category.
	 */
	public function test_google_fonts_category_filtering(): void {
		$request = new WP_REST_Request( 'GET', '/dwt-management/v1/fonts/google' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_param( 'category', 'serif' );

		$response = rest_do_request( $request );
		$data     = $response->get_data();

		// All fonts should be serif.
		foreach ( $data as $font ) {
			$this->assertEquals( 'serif', $font['category'] );
		}
	}

	/**
	 * Clean up font directory after tests.
	 */
	private function clean_font_directory(): void {
		$upload_dir = wp_upload_dir();
		$font_dir   = $upload_dir['basedir'] . '/dwt-local-fonts';

		if ( is_dir( $font_dir ) ) {
			$files = glob( $font_dir . '/*' );
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				}
			}
			rmdir( $font_dir );
		}
	}
}
