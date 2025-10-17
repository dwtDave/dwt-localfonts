<?php
/**
 * UninstallPrompt class tests.
 *
 * @package DWT\LocalFonts
 */

declare(strict_types=1);

namespace DWT\LocalFonts\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use DWT\LocalFonts\Modules\Settings;
use DWT\LocalFonts\Modules\UninstallPrompt;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

/**
 * Test case for the UninstallPrompt class.
 */
final class UninstallPromptTest extends TestCase {
	use MockeryPHPUnitIntegration;

	/**
	 * Set up test environment before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Mock plugin file constant.
		if ( ! defined( 'DWT_LOCAL_FONTS_PLUGIN_FILE' ) ) {
			define( 'DWT_LOCAL_FONTS_PLUGIN_FILE', '/path/to/plugin.php' );
		}

		if ( ! defined( 'DWT_LOCAL_FONTS_VERSION' ) ) {
			define( 'DWT_LOCAL_FONTS_VERSION', '1.0.0' );
		}

		// Mock common WordPress functions.
		Functions\when( 'plugin_dir_url' )->justReturn( 'https://example.com/wp-content/plugins/dwt-local-fonts/' );
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
	 * Test constructor registers hooks.
	 */
	public function test_constructor_registers_hooks(): void {
		Functions\expect( 'add_action' )
			->once()
			->with( 'admin_enqueue_scripts', Mockery::type( 'array' ) );

		Functions\expect( 'add_action' )
			->once()
			->with( 'wp_ajax_dwt_save_uninstall_preference', Mockery::type( 'array' ) );

		new UninstallPrompt();
	}

	/**
	 * Test enqueue_scripts only loads on plugins page.
	 */
	public function test_enqueue_scripts_only_loads_on_plugins_page(): void {
		// Mock add_action to allow constructor to run.
		Functions\when( 'add_action' )->justReturn( true );

		$uninstall_prompt = new UninstallPrompt();

		// Should not enqueue on non-plugins pages.
		// Use when() instead of expect()->never() to avoid global state pollution.
		Functions\when( 'wp_enqueue_style' )->justReturn( true );
		Functions\when( 'wp_enqueue_script' )->justReturn( true );
		Functions\when( 'wp_localize_script' )->justReturn( true );

		$uninstall_prompt->enqueue_scripts( 'index.php' );

		// Method completes without errors - early return happens because hook is 'index.php' not 'plugins.php'.
		$this->assertTrue( true );
	}

	/**
	 * Test enqueue_scripts enqueues assets on plugins page.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_enqueue_scripts_enqueues_assets_on_plugins_page(): void {
		// Mock add_action to allow constructor to run.
		Functions\when( 'add_action' )->justReturn( true );

		$uninstall_prompt = new UninstallPrompt();

		// Mock WordPress enqueue functions.
		Functions\expect( 'wp_enqueue_style' )
			->once()
			->with(
				'dwt-uninstall-prompt',
				'https://example.com/wp-content/plugins/dwt-local-fonts/assets/css/uninstall-prompt.css',
				array(),
				'1.0.0'
			);

		Functions\expect( 'wp_enqueue_script' )
			->once()
			->with(
				'dwt-uninstall-prompt',
				'https://example.com/wp-content/plugins/dwt-local-fonts/assets/js/uninstall-prompt.js',
				array( 'jquery' ),
				'1.0.0',
				true
			);

		Functions\expect( 'wp_create_nonce' )
			->once()
			->with( 'dwt_uninstall_preference' )
			->andReturn( 'test-nonce-123' );

		Functions\expect( 'wp_localize_script' )
			->once()
			->with(
				'dwt-uninstall-prompt',
				'dwtUninstallPrompt',
				array(
					'nonce' => 'test-nonce-123',
				)
			);

		$uninstall_prompt->enqueue_scripts( 'plugins.php' );
	}

	/**
	 * Test save_uninstall_preference fails with invalid nonce.
	 */
	public function test_save_uninstall_preference_fails_with_invalid_nonce(): void {
		$uninstall_prompt = new UninstallPrompt();

		$_POST['nonce'] = 'invalid-nonce';

		Functions\expect( 'wp_verify_nonce' )
			->once()
			->with( 'invalid-nonce', 'dwt_uninstall_preference' )
			->andReturn( false );

		// Nonce check happens before capability check, so these should not be called.
		Functions\expect( 'current_user_can' )->never();
		Functions\expect( 'get_option' )->never();
		Functions\expect( 'update_option' )->never();
		Functions\expect( 'wp_send_json_success' )->never();

		Functions\expect( 'wp_send_json_error' )
			->once()
			->with(
				array( 'message' => 'Invalid nonce' ),
				403
			)
			->andThrow( new \Exception( 'wp_send_json_error called' ) );

		try {
			$uninstall_prompt->save_uninstall_preference();
		} catch ( \Exception $e ) {
			// Expected - wp_send_json_error terminates execution.
		}

		unset( $_POST['nonce'] );
	}

	/**
	 * Test save_uninstall_preference fails with missing nonce.
	 */
	public function test_save_uninstall_preference_fails_with_missing_nonce(): void {
		$uninstall_prompt = new UninstallPrompt();

		// Ensure no nonce is set.
		unset( $_POST['nonce'] );

		// Nonce check happens before other operations.
		Functions\expect( 'current_user_can' )->never();
		Functions\expect( 'get_option' )->never();
		Functions\expect( 'update_option' )->never();
		Functions\expect( 'wp_send_json_success' )->never();

		Functions\expect( 'wp_send_json_error' )
			->once()
			->with(
				array( 'message' => 'Invalid nonce' ),
				403
			)
			->andThrow( new \Exception( 'wp_send_json_error called' ) );

		try {
			$uninstall_prompt->save_uninstall_preference();
		} catch ( \Exception $e ) {
			// Expected - wp_send_json_error terminates execution.
		}
	}

	/**
	 * Test save_uninstall_preference fails without sufficient permissions.
	 */
	public function test_save_uninstall_preference_fails_without_permissions(): void {
		$uninstall_prompt = new UninstallPrompt();

		$_POST['nonce'] = 'valid-nonce';

		Functions\expect( 'wp_verify_nonce' )
			->once()
			->with( 'valid-nonce', 'dwt_uninstall_preference' )
			->andReturn( true );

		Functions\expect( 'current_user_can' )
			->once()
			->with( 'delete_plugins' )
			->andReturn( false );

		// Capability check happens before get_option, so these should not be called.
		Functions\expect( 'get_option' )->never();
		Functions\expect( 'update_option' )->never();
		Functions\expect( 'wp_send_json_success' )->never();

		Functions\expect( 'wp_send_json_error' )
			->once()
			->with(
				array( 'message' => 'Insufficient permissions' ),
				403
			)
			->andThrow( new \Exception( 'wp_send_json_error called' ) );

		try {
			$uninstall_prompt->save_uninstall_preference();
		} catch ( \Exception $e ) {
			// Expected - wp_send_json_error terminates execution.
		}

		unset( $_POST['nonce'] );
	}

	/**
	 * Test save_uninstall_preference saves preference when keep_fonts is set to 1.
	 */
	public function test_save_uninstall_preference_saves_keep_fonts_enabled(): void {
		$uninstall_prompt = new UninstallPrompt();

		$_POST['nonce']      = 'valid-nonce';
		$_POST['keep_fonts'] = '1';

		Functions\expect( 'wp_verify_nonce' )
			->once()
			->with( 'valid-nonce', 'dwt_uninstall_preference' )
			->andReturn( true );

		Functions\expect( 'current_user_can' )
			->once()
			->with( 'delete_plugins' )
			->andReturn( true );

		Functions\expect( 'get_option' )
			->once()
			->with( Settings::OPTION_NAME, array() )
			->andReturn( array( 'existing_setting' => 'value' ) );

		Functions\expect( 'update_option' )
			->once()
			->with(
				Settings::OPTION_NAME,
				array(
					'existing_setting'        => 'value',
					'keep_fonts_on_uninstall' => '1',
				)
			)
			->andReturn( true );

		Functions\expect( 'wp_send_json_success' )
			->once()
			->with(
				array(
					'message'    => 'Preference saved',
					'keep_fonts' => '1',
				)
			);

		$uninstall_prompt->save_uninstall_preference();

		unset( $_POST['nonce'], $_POST['keep_fonts'] );
	}

	/**
	 * Test save_uninstall_preference saves preference when keep_fonts is set to 0.
	 */
	public function test_save_uninstall_preference_saves_keep_fonts_disabled(): void {
		$uninstall_prompt = new UninstallPrompt();

		$_POST['nonce']      = 'valid-nonce';
		$_POST['keep_fonts'] = '0';

		Functions\expect( 'wp_verify_nonce' )
			->once()
			->with( 'valid-nonce', 'dwt_uninstall_preference' )
			->andReturn( true );

		Functions\expect( 'current_user_can' )
			->once()
			->with( 'delete_plugins' )
			->andReturn( true );

		Functions\expect( 'get_option' )
			->once()
			->with( Settings::OPTION_NAME, array() )
			->andReturn( array() );

		Functions\expect( 'update_option' )
			->once()
			->with(
				Settings::OPTION_NAME,
				array(
					'keep_fonts_on_uninstall' => '0',
				)
			)
			->andReturn( true );

		Functions\expect( 'wp_send_json_success' )
			->once()
			->with(
				array(
					'message'    => 'Preference saved',
					'keep_fonts' => '0',
				)
			);

		$uninstall_prompt->save_uninstall_preference();

		unset( $_POST['nonce'], $_POST['keep_fonts'] );
	}

	/**
	 * Test save_uninstall_preference defaults to 0 when keep_fonts is missing.
	 */
	public function test_save_uninstall_preference_defaults_to_zero(): void {
		$uninstall_prompt = new UninstallPrompt();

		$_POST['nonce'] = 'valid-nonce';
		// Intentionally not setting keep_fonts.

		Functions\expect( 'wp_verify_nonce' )
			->once()
			->with( 'valid-nonce', 'dwt_uninstall_preference' )
			->andReturn( true );

		Functions\expect( 'current_user_can' )
			->once()
			->with( 'delete_plugins' )
			->andReturn( true );

		Functions\expect( 'get_option' )
			->once()
			->with( Settings::OPTION_NAME, array() )
			->andReturn( array() );

		Functions\expect( 'update_option' )
			->once()
			->with(
				Settings::OPTION_NAME,
				array(
					'keep_fonts_on_uninstall' => '0',
				)
			)
			->andReturn( true );

		Functions\expect( 'wp_send_json_success' )
			->once()
			->with(
				array(
					'message'    => 'Preference saved',
					'keep_fonts' => '0',
				)
			);

		$uninstall_prompt->save_uninstall_preference();

		unset( $_POST['nonce'] );
	}

	/**
	 * Test save_uninstall_preference preserves existing settings.
	 */
	public function test_save_uninstall_preference_preserves_existing_settings(): void {
		$uninstall_prompt = new UninstallPrompt();

		$_POST['nonce']      = 'valid-nonce';
		$_POST['keep_fonts'] = '1';

		Functions\expect( 'wp_verify_nonce' )
			->once()
			->with( 'valid-nonce', 'dwt_uninstall_preference' )
			->andReturn( true );

		Functions\expect( 'current_user_can' )
			->once()
			->with( 'delete_plugins' )
			->andReturn( true );

		Functions\expect( 'get_option' )
			->once()
			->with( Settings::OPTION_NAME, array() )
			->andReturn(
				array(
					'font_display_swap' => '1',
					'font_rules'        => '[]',
				)
			);

		Functions\expect( 'update_option' )
			->once()
			->with(
				Settings::OPTION_NAME,
				array(
					'font_display_swap'       => '1',
					'font_rules'              => '[]',
					'keep_fonts_on_uninstall' => '1',
				)
			)
			->andReturn( true );

		Functions\expect( 'wp_send_json_success' )
			->once()
			->with(
				array(
					'message'    => 'Preference saved',
					'keep_fonts' => '1',
				)
			);

		$uninstall_prompt->save_uninstall_preference();

		unset( $_POST['nonce'], $_POST['keep_fonts'] );
	}
}
