<?php
/**
 * Settings integration tests with WordPress.
 *
 * @package DWT\LocalFonts
 */

declare(strict_types=1);

namespace DWT\LocalFonts\Tests\Integration;

use DWT\LocalFonts\Modules\Settings;
use WP_REST_Request;
use WP_REST_Server;
use WP_UnitTestCase;

/**
 * Integration test case for Settings with WordPress APIs.
 */
final class SettingsIntegrationTest extends WP_UnitTestCase {

	/**
	 * Settings instance.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	private int $admin_user_id;

	/**
	 * Set up test environment.
	 */
	public function set_up(): void {
		parent::set_up();

		// Create admin user.
		$this->admin_user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_user_id );

		// Initialize Settings.
		$this->settings = new Settings();

		// Clean up any existing settings.
		delete_option( Settings::OPTION_NAME );
	}

	/**
	 * Tear down test environment.
	 */
	public function tear_down(): void {
		delete_option( Settings::OPTION_NAME );
		parent::tear_down();
	}

	/**
	 * Test settings page is added to admin menu.
	 */
	public function test_settings_page_is_registered(): void {
		global $submenu;

		// Trigger admin_menu hook.
		do_action( 'admin_menu' );

		// Check if settings page exists under options menu.
		$this->assertArrayHasKey( 'options-general.php', $submenu );

		$found = false;
		foreach ( $submenu['options-general.php'] as $item ) {
			if ( 'dwt-local-fonts' === $item[2] ) {
				$found = true;
				break;
			}
		}

		$this->assertTrue( $found, 'Settings page not found in admin menu' );
	}

	/**
	 * Test REST route registration.
	 */
	public function test_rest_routes_are_registered(): void {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/dwt-management/v1/settings', $routes );

		$route = $routes['/dwt-management/v1/settings'];

		// Should have at least 2 endpoints (GET and POST, possibly OPTIONS for CORS).
		$this->assertGreaterThanOrEqual( 2, count( $route ), 'Settings route should have at least 2 endpoints' );

		// Check that we have the expected callbacks.
		$has_get_callback  = false;
		$has_post_callback = false;

		foreach ( $route as $endpoint ) {
			if ( isset( $endpoint['callback'] ) && is_array( $endpoint['callback'] ) ) {
				$method_name = $endpoint['callback'][1] ?? '';
				if ( 'get_settings' === $method_name ) {
					$has_get_callback = true;
				}
				if ( 'save_settings' === $method_name ) {
					$has_post_callback = true;
				}
			}
		}

		$this->assertTrue( $has_get_callback, 'Route should have get_settings callback' );
		$this->assertTrue( $has_post_callback, 'Route should have save_settings callback' );
	}

	/**
	 * Test get settings endpoint returns empty by default.
	 */
	public function test_get_settings_returns_empty_by_default(): void {
		$response = $this->settings->get_settings();

		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertIsArray( $response->get_data() );
		$this->assertEmpty( $response->get_data() );
	}

	/**
	 * Test save settings with valid data.
	 */
	public function test_save_settings_with_valid_data(): void {
		$request = new WP_REST_Request( 'POST', '/dwt-management/v1/settings' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'keep_fonts_on_uninstall' => true,
					'font_rules'              => wp_json_encode(
						array(
							array(
								'selector'   => 'h1',
								'fontFamily' => 'Roboto',
								'fontWeight' => '700',
							),
						)
					),
				)
			)
		);

		$response = $this->settings->save_settings( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( '1', $data['keep_fonts_on_uninstall'] );
		$this->assertNotEmpty( $data['font_rules'] );
	}

	/**
	 * Test save settings sanitizes font rules.
	 */
	public function test_save_settings_sanitizes_font_rules(): void {
		$request = new WP_REST_Request( 'POST', '/dwt-management/v1/settings' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_header( 'Content-Type', 'application/json' );

		// Include malicious script in selector.
		$request->set_body(
			wp_json_encode(
				array(
					'font_rules' => wp_json_encode(
						array(
							array(
								'selector'   => '<script>alert("xss")</script>',
								'fontFamily' => 'Roboto',
							),
						)
					),
				)
			)
		);

		$response = $this->settings->save_settings( $request );
		$data     = $response->get_data();

		$rules = json_decode( $data['font_rules'], true );

		// Malicious rule should be filtered out.
		$this->assertEmpty( $rules );
	}

	/**
	 * Test save settings only accepts allowed keys.
	 */
	public function test_save_settings_filters_disallowed_keys(): void {
		$request = new WP_REST_Request( 'POST', '/dwt-management/v1/settings' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'keep_fonts_on_uninstall' => true,
					'malicious_key'           => 'evil_value',
					'another_bad_key'         => 'bad_value',
				)
			)
		);

		$response = $this->settings->save_settings( $request );
		$data     = $response->get_data();

		$this->assertArrayHasKey( 'keep_fonts_on_uninstall', $data );
		$this->assertArrayNotHasKey( 'malicious_key', $data );
		$this->assertArrayNotHasKey( 'another_bad_key', $data );
	}

	/**
	 * Test save settings requires valid nonce.
	 */
	public function test_save_settings_requires_valid_nonce(): void {
		$request = new WP_REST_Request( 'POST', '/dwt-management/v1/settings' );
		$request->set_header( 'X-WP-Nonce', 'invalid_nonce' );
		$request->set_body( wp_json_encode( array( 'keep_fonts_on_uninstall' => true ) ) );

		$response = $this->settings->save_settings( $request );

		$this->assertEquals( 403, $response->get_status() );
		$this->assertArrayHasKey( 'error', $response->get_data() );
	}

	/**
	 * Test settings endpoint requires admin capabilities.
	 */
	public function test_settings_endpoints_require_admin_capabilities(): void {
		// Switch to non-admin user.
		$subscriber_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber_id );

		$request  = new WP_REST_Request( 'GET', '/dwt-management/v1/settings' );
		$response = rest_do_request( $request );

		$this->assertEquals( 403, $response->get_status() );
	}

	/**
	 * Test save settings accepts keep_fonts_on_uninstall setting.
	 */
	public function test_save_settings_accepts_keep_fonts_on_uninstall(): void {
		$request = new WP_REST_Request( 'POST', '/dwt-management/v1/settings' );
		$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'keep_fonts_on_uninstall' => true,
				)
			)
		);

		$response = $this->settings->save_settings( $request );
		$data     = $response->get_data();

		$this->assertEquals( 200, $response->get_status() );
		$this->assertArrayHasKey( 'keep_fonts_on_uninstall', $data );
		$this->assertEquals( '1', $data['keep_fonts_on_uninstall'] );

		// Test disabling it.
		$request->set_body(
			wp_json_encode(
				array(
					'keep_fonts_on_uninstall' => false,
				)
			)
		);

		$response = $this->settings->save_settings( $request );
		$data     = $response->get_data();

		$this->assertEquals( '0', $data['keep_fonts_on_uninstall'] );
	}

	/**
	 * Test font rules with valid CSS selectors are accepted.
	 */
	public function test_valid_css_selectors_are_accepted(): void {
		$valid_selectors = array(
			'h1',
			'.my-class',
			'#my-id',
			'body > p',
			'a:hover',
			'[data-attr="value"]',
			'.class1, .class2',
		);

		foreach ( $valid_selectors as $selector ) {
			$request = new WP_REST_Request( 'POST', '/dwt-management/v1/settings' );
			$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body(
				wp_json_encode(
					array(
						'font_rules' => wp_json_encode(
							array(
								array(
									'selector'   => $selector,
									'fontFamily' => 'Roboto',
								),
							)
						),
					)
				)
			);

			$response = $this->settings->save_settings( $request );
			$this->assertEquals( 200, $response->get_status(), "Selector '{$selector}' request should succeed" );

			$data = $response->get_data();
			$this->assertArrayHasKey( 'font_rules', $data, "Response should contain font_rules for selector '{$selector}'" );

			$rules = json_decode( $data['font_rules'], true );
			$this->assertNotEmpty( $rules, "Selector '{$selector}' should be valid" );
		}
	}
}
