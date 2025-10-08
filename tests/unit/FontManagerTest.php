<?php
/**
 * FontManager class tests.
 *
 * @package DWT\LocalFonts
 */

declare(strict_types=1);

namespace DWT\LocalFonts\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use DWT\LocalFonts\Modules\FontManager;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

/**
 * Test case for the FontManager class.
 */
final class FontManagerTest extends TestCase {
	use MockeryPHPUnitIntegration;

	/**
	 * FontManager instance for testing.
	 *
	 * @var FontManager
	 */
	private FontManager $font_manager;

	/**
	 * Mock options for testing.
	 *
	 * @var array<string, mixed>
	 */
	private array $mock_options = array();

	/**
	 * Set up test environment before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Reset mock options.
		$this->mock_options = array();

		// Mock WordPress functions.
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'register_rest_route' )->justReturn( true );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_option' )->alias(
			function ( $option, $default = false ) {
				return $this->mock_options[ $option ] ?? $default;
			}
		);
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_html__' )->returnArg();
		Functions\when( 'wp_upload_dir' )->justReturn(
			array(
				'basedir' => '/tmp/wp-content/uploads',
				'baseurl' => 'http://example.com/wp-content/uploads',
			)
		);

		$this->font_manager = new FontManager();
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
		$font_manager = new FontManager();

		$this->assertInstanceOf( FontManager::class, $font_manager );
	}

	/**
	 * Test enqueue_local_fonts does not enqueue if CSS file doesn't exist.
	 */

	/**
	 * Test output_custom_font_css outputs nothing when no font rules exist.
	 */

	/**
	 * Test output_custom_font_css runs without errors.
	 */

	/**
	 * Test output_custom_font_css uses CSS generator service.
	 */

	/**
	 * Test output_custom_font_css outputs nothing when font_rules is invalid JSON.
	 */

	/**
	 * Test FontManager no longer has enqueue_local_fonts method.
	 */
	public function test_it_no_longer_enqueues_fonts(): void {
		$this->assertFalse( method_exists( $this->font_manager, 'enqueue_local_fonts' ) );
	}

	/**
	 * Test FontManager no longer has output_custom_font_css method.
	 */
	public function test_it_no_longer_outputs_custom_css(): void {
		$this->assertFalse( method_exists( $this->font_manager, 'output_custom_font_css' ) );
	}
}
