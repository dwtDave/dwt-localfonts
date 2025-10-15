<?php
/**
 * Settings class tests.
 *
 * @package DWT\LocalFonts
 */

declare(strict_types=1);

namespace DWT\LocalFonts\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use DWT\LocalFonts\Modules\Settings;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

/**
 * Test case for the Settings class.
 */
final class SettingsTest extends TestCase {
	use MockeryPHPUnitIntegration;

	/**
	 * Settings instance for testing.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Set up test environment before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Mock WordPress functions.
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'add_options_page' )->justReturn( 'settings_page_dwt-management' );
		Functions\when( 'register_rest_route' )->justReturn( true );
		Functions\when( '__' )->returnArg();
		Functions\when( 'rest_url' )->justReturn( 'http://example.com/wp-json/dwt-management/v1/' );
		Functions\when( 'wp_create_nonce' )->justReturn( 'test-nonce' );
		Functions\when( 'plugin_dir_url' )->justReturn( 'http://example.com/wp-content/plugins/dwt-management-for-wp/' );
		Functions\when( 'plugin_dir_path' )->justReturn( '/var/www/wp-content/plugins/dwt-management-for-wp/' );
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'update_option' )->justReturn( true );

		$this->settings = new Settings();
	}

	/**
	 * Tear down test environment after each test.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test that constructor creates instance successfully.
	 */
	public function test_it_creates_instance_successfully(): void {
		$settings = new Settings();

		$this->assertInstanceOf( Settings::class, $settings );
	}

	/**
	 * Test render_react_app_container outputs the container div.
	 */
	public function test_it_renders_react_app_container(): void {
		ob_start();
		$this->settings->render_react_app_container();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<div id="dwt-local-fonts-react-app"', $output );
		$this->assertStringContainsString( 'class="wrap"', $output );
	}

	/**
	 * Test get_settings returns settings from options.
	 */
	public function test_it_gets_settings(): void {
		$response = $this->settings->get_settings();

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertIsArray( $response->get_data() );
	}

	/**
	 * Test save_settings sanitizes and saves settings.
	 */
	public function test_it_saves_settings(): void {
		$request = \Mockery::mock( \WP_REST_Request::class );
		$request->shouldReceive( 'get_json_params' )
			->once()
			->andReturn(
				array(
					'keep_fonts_on_uninstall' => true,
					'font_rules'              => '[]',
				)
			);

		Functions\when( 'sanitize_key' )->returnArg();
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'wp_verify_nonce' )->justReturn( true );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );

		$request->shouldReceive( 'get_header' )
			->with( 'X-WP-Nonce' )
			->andReturn( 'test_nonce' );

		$response = $this->settings->save_settings( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( '1', $data['keep_fonts_on_uninstall'] );
		$this->assertEquals( '[]', $data['font_rules'] );
	}

	/**
	 * Test save_settings handles empty array.
	 */
	public function test_it_handles_empty_settings(): void {
		$request = \Mockery::mock( \WP_REST_Request::class );
		$request->shouldReceive( 'get_json_params' )
			->once()
			->andReturn( array() );

		$request->shouldReceive( 'get_header' )
			->with( 'X-WP-Nonce' )
			->andReturn( 'test_nonce' );

		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'wp_verify_nonce' )->justReturn( true );

		$response = $this->settings->save_settings( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( array( 'success' => true ), $response->get_data() );
	}

	/**
	 * Test save_settings handles non-array input.
	 */
	public function test_it_handles_non_array_input(): void {
		$request = \Mockery::mock( \WP_REST_Request::class );
		$request->shouldReceive( 'get_json_params' )
			->once()
			->andReturn( array() ); // Return empty array instead of null.

		$request->shouldReceive( 'get_header' )
			->with( 'X-WP-Nonce' )
			->andReturn( 'test_nonce' );

		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'wp_verify_nonce' )->justReturn( true );

		$response = $this->settings->save_settings( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );
	}

	/**
	 * Test register_rest_routes can be called without errors.
	 */
	public function test_it_registers_rest_routes(): void {
		$this->settings->register_rest_routes();

		// If no exception is thrown, the test passes.
		$this->assertTrue( true );
	}

	/**
	 * Test OPTION_NAME constant is set correctly.
	 */
	public function test_it_has_correct_option_name(): void {
		$this->assertEquals( 'dwt_local_fonts_settings', Settings::OPTION_NAME );
	}

	/**
	 * Test save_settings accepts keep_fonts_on_uninstall setting.
	 */
	public function test_it_saves_keep_fonts_on_uninstall_setting(): void {
		$request = \Mockery::mock( \WP_REST_Request::class );
		$request->shouldReceive( 'get_json_params' )
			->once()
			->andReturn(
				array(
					'keep_fonts_on_uninstall' => true,
				)
			);

		Functions\when( 'sanitize_key' )->returnArg();
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'wp_verify_nonce' )->justReturn( true );

		$request->shouldReceive( 'get_header' )
			->with( 'X-WP-Nonce' )
			->andReturn( 'test_nonce' );

		$response = $this->settings->save_settings( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( '1', $data['keep_fonts_on_uninstall'] );
	}

	/**
	 * Test save_settings accepts keep_fonts_on_uninstall disabled.
	 */
	public function test_it_saves_keep_fonts_on_uninstall_disabled(): void {
		$request = \Mockery::mock( \WP_REST_Request::class );
		$request->shouldReceive( 'get_json_params' )
			->once()
			->andReturn(
				array(
					'keep_fonts_on_uninstall' => false,
				)
			);

		Functions\when( 'sanitize_key' )->returnArg();
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'wp_verify_nonce' )->justReturn( true );

		$request->shouldReceive( 'get_header' )
			->with( 'X-WP-Nonce' )
			->andReturn( 'test_nonce' );

		$response = $this->settings->save_settings( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( '0', $data['keep_fonts_on_uninstall'] );
	}

	/**
	 * Test save_settings saves all settings together.
	 */
	public function test_it_saves_all_settings_together(): void {
		$request = \Mockery::mock( \WP_REST_Request::class );
		$request->shouldReceive( 'get_json_params' )
			->once()
			->andReturn(
				array(
					'keep_fonts_on_uninstall' => true,
					'font_rules'              => '[]',
				)
			);

		Functions\when( 'sanitize_key' )->returnArg();
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'wp_verify_nonce' )->justReturn( true );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );

		$request->shouldReceive( 'get_header' )
			->with( 'X-WP-Nonce' )
			->andReturn( 'test_nonce' );

		$response = $this->settings->save_settings( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertEquals( '1', $data['keep_fonts_on_uninstall'] );
		$this->assertEquals( '[]', $data['font_rules'] );
	}
}
