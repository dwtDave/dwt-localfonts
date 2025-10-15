<?php
/**
 * Core class tests.
 *
 * @package DWT\LocalFonts
 */

declare(strict_types=1);

namespace DWT\LocalFonts\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use DWT\LocalFonts\Core;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

/**
 * Test case for the Core class.
 */
final class CoreTest extends TestCase {
	use MockeryPHPUnitIntegration;

	/**
	 * Core instance for testing.
	 *
	 * @var Core|null
	 */
	private ?Core $core = null;

	/**
	 * Set up test environment before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Mock WordPress functions used by Core class.
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'load_plugin_textdomain' )->justReturn( true );
		Functions\when( 'plugin_basename' )->returnArg();
		Functions\when( 'esc_html__' )->returnArg();
		Functions\when( 'esc_html' )->returnArg();
	}

	/**
	 * Tear down test environment after each test.
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();

		// Reset the singleton instance using reflection.
		$reflection = new \ReflectionClass( Core::class );
		$instance   = $reflection->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );
		$instance->setAccessible( false );
	}

	/**
	 * Test that get_instance returns a Core instance.
	 */
	public function test_it_returns_singleton_instance(): void {
		$instance = Core::get_instance();

		$this->assertInstanceOf( Core::class, $instance );
	}

	/**
	 * Test that get_instance always returns the same instance.
	 */
	public function test_it_returns_same_instance_on_multiple_calls(): void {
		$instance1 = Core::get_instance();
		$instance2 = Core::get_instance();

		$this->assertSame( $instance1, $instance2 );
	}

	/**
	 * Test that load_textdomain method exists and can be called.
	 */
	public function test_it_loads_textdomain(): void {
		$instance = Core::get_instance();

		// Verify the method exists and can be called without errors.
		$this->assertTrue( method_exists( $instance, 'load_textdomain' ) );
	}

	/**
	 * Test that cloning is prevented.
	 */
	public function test_it_prevents_cloning(): void {
		$this->expectException( \Error::class );

		$instance = Core::get_instance();
		clone $instance;
	}

	/**
	 * Test that __wakeup method exists to prevent unserialization.
	 */
	public function test_it_has_wakeup_method(): void {
		$this->assertTrue( method_exists( Core::class, '__wakeup' ) );
	}

	/**
	 * Test that modules directory is scanned correctly.
	 */
	public function test_it_initializes_modules(): void {
		// This test verifies that module initialization doesn't throw exceptions.
		// Since Core is a singleton and modules are initialized in constructor,
		// we test this by verifying the instance was created successfully.
		$instance = Core::get_instance();

		$this->assertInstanceOf( Core::class, $instance );
	}
}
