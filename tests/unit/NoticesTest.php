<?php
/**
 * Notices class tests.
 *
 * @package DWT\LocalFonts
 */

declare(strict_types=1);

namespace DWT\LocalFonts\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use DWT\LocalFonts\Modules\Notices;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

/**
 * Test case for the Notices class.
 */
final class NoticesTest extends TestCase {
	use MockeryPHPUnitIntegration;

	/**
	 * Set up test environment before each test.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Mock WordPress translation function.
		Functions\when( 'esc_html__' )->returnArg();
		Functions\when( 'esc_html' )->returnArg();
		Functions\when( 'esc_attr' )->returnArg();
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
			->with( 'admin_notices', Mockery::type( 'array' ) );

		Functions\expect( 'add_action' )
			->once()
			->with( 'admin_init', Mockery::type( 'array' ) );

		new Notices();
	}

	/**
	 * Test add_success adds a success notice.
	 */
	public function test_add_success_adds_notice(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'dwt_local_fonts_notices', array() )
			->andReturn( array() );

		Functions\expect( 'update_option' )
			->once()
			->with(
				'dwt_local_fonts_notices',
				Mockery::on(
					function ( $notices ) {
						return is_array( $notices )
							&& count( $notices ) === 1
							&& $notices[0]['message'] === 'Test success message'
							&& $notices[0]['type'] === 'success'
							&& $notices[0]['dismissible'] === true
							&& isset( $notices[0]['timestamp'] );
					}
				)
			)
			->andReturn( true );

		Notices::add_success( 'Test success message' );
	}

	/**
	 * Test add_error adds an error notice.
	 */
	public function test_add_error_adds_notice(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'dwt_local_fonts_notices', array() )
			->andReturn( array() );

		Functions\expect( 'update_option' )
			->once()
			->with(
				'dwt_local_fonts_notices',
				Mockery::on(
					function ( $notices ) {
						return is_array( $notices )
							&& count( $notices ) === 1
							&& $notices[0]['message'] === 'Test error message'
							&& $notices[0]['type'] === 'error'
							&& $notices[0]['dismissible'] === true;
					}
				)
			)
			->andReturn( true );

		Notices::add_error( 'Test error message' );
	}

	/**
	 * Test add_warning adds a warning notice.
	 */
	public function test_add_warning_adds_notice(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'dwt_local_fonts_notices', array() )
			->andReturn( array() );

		Functions\expect( 'update_option' )
			->once()
			->with(
				'dwt_local_fonts_notices',
				Mockery::on(
					function ( $notices ) {
						return is_array( $notices )
							&& count( $notices ) === 1
							&& $notices[0]['message'] === 'Test warning message'
							&& $notices[0]['type'] === 'warning'
							&& $notices[0]['dismissible'] === true;
					}
				)
			)
			->andReturn( true );

		Notices::add_warning( 'Test warning message' );
	}

	/**
	 * Test add_info adds an info notice.
	 */
	public function test_add_info_adds_notice(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'dwt_local_fonts_notices', array() )
			->andReturn( array() );

		Functions\expect( 'update_option' )
			->once()
			->with(
				'dwt_local_fonts_notices',
				Mockery::on(
					function ( $notices ) {
						return is_array( $notices )
							&& count( $notices ) === 1
							&& $notices[0]['message'] === 'Test info message'
							&& $notices[0]['type'] === 'info'
							&& $notices[0]['dismissible'] === true;
					}
				)
			)
			->andReturn( true );

		Notices::add_info( 'Test info message' );
	}

	/**
	 * Test add_success with non-dismissible flag.
	 */
	public function test_add_success_non_dismissible(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'dwt_local_fonts_notices', array() )
			->andReturn( array() );

		Functions\expect( 'update_option' )
			->once()
			->with(
				'dwt_local_fonts_notices',
				Mockery::on(
					function ( $notices ) {
						return is_array( $notices )
							&& count( $notices ) === 1
							&& $notices[0]['dismissible'] === false;
					}
				)
			)
			->andReturn( true );

		Notices::add_success( 'Test message', false );
	}

	/**
	 * Test display_notices shows queued notices.
	 */
	public function test_display_notices_shows_queued_notices(): void {
		$notices = new Notices();

		$test_notices = array(
			array(
				'type'        => 'success',
				'message'     => 'Success message',
				'dismissible' => true,
				'timestamp'   => time(),
			),
			array(
				'type'        => 'error',
				'message'     => 'Error message',
				'dismissible' => false,
				'timestamp'   => time(),
			),
		);

		Functions\expect( 'get_option' )
			->once()
			->with( 'dwt_local_fonts_notices', array() )
			->andReturn( $test_notices );

		Functions\expect( 'delete_option' )
			->once()
			->with( 'dwt_local_fonts_notices' )
			->andReturn( true );

		// Capture output.
		ob_start();
		$notices->display_notices();
		$output = ob_get_clean();

		// Verify output contains expected elements.
		$this->assertStringContainsString( 'notice-success', $output );
		$this->assertStringContainsString( 'Success message', $output );
		$this->assertStringContainsString( 'is-dismissible', $output );
		$this->assertStringContainsString( 'notice-error', $output );
		$this->assertStringContainsString( 'Error message', $output );
		$this->assertStringNotContainsString( 'is-dismissible">.*Error', $output );
	}

	/**
	 * Test display_notices does nothing when no notices exist.
	 */
	public function test_display_notices_handles_empty_queue(): void {
		$notices = new Notices();

		Functions\expect( 'get_option' )
			->once()
			->with( 'dwt_local_fonts_notices', array() )
			->andReturn( array() );

		Functions\expect( 'delete_option' )->never();

		ob_start();
		$notices->display_notices();
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/**
	 * Test display_notices handles missing message gracefully.
	 */
	public function test_display_notices_skips_notice_with_empty_message(): void {
		$notices = new Notices();

		$test_notices = array(
			array(
				'type'        => 'success',
				'message'     => '',
				'dismissible' => true,
				'timestamp'   => time(),
			),
			array(
				'type'        => 'error',
				'message'     => 'Valid message',
				'dismissible' => true,
				'timestamp'   => time(),
			),
		);

		Functions\expect( 'get_option' )
			->once()
			->with( 'dwt_local_fonts_notices', array() )
			->andReturn( $test_notices );

		Functions\expect( 'delete_option' )
			->once()
			->with( 'dwt_local_fonts_notices' )
			->andReturn( true );

		ob_start();
		$notices->display_notices();
		$output = ob_get_clean();

		// Only the valid message should be shown.
		$this->assertStringContainsString( 'Valid message', $output );
		$this->assertStringContainsString( 'notice-error', $output );
	}

	/**
	 * Test clear_old_notices removes expired notices.
	 */
	public function test_clear_old_notices_removes_expired(): void {
		$notices = new Notices();

		$old_timestamp = time() - ( HOUR_IN_SECONDS + 100 );
		$new_timestamp = time() - 100;

		$test_notices = array(
			array(
				'type'        => 'success',
				'message'     => 'Old notice',
				'dismissible' => true,
				'timestamp'   => $old_timestamp,
			),
			array(
				'type'        => 'error',
				'message'     => 'New notice',
				'dismissible' => true,
				'timestamp'   => $new_timestamp,
			),
		);

		Functions\expect( 'get_option' )
			->once()
			->with( 'dwt_local_fonts_notices', array() )
			->andReturn( $test_notices );

		Functions\expect( 'update_option' )
			->once()
			->with(
				'dwt_local_fonts_notices',
				Mockery::on(
					function ( $notices ) use ( $new_timestamp ) {
						return is_array( $notices )
							&& count( $notices ) === 1
							&& $notices[0]['message'] === 'New notice'
							&& $notices[0]['timestamp'] === $new_timestamp;
					}
				)
			)
			->andReturn( true );

		$notices->clear_old_notices();
	}

	/**
	 * Test clear_old_notices deletes option when all notices are expired.
	 */
	public function test_clear_old_notices_deletes_when_all_expired(): void {
		$notices = new Notices();

		$old_timestamp = time() - ( HOUR_IN_SECONDS + 100 );

		$test_notices = array(
			array(
				'type'        => 'success',
				'message'     => 'Old notice 1',
				'dismissible' => true,
				'timestamp'   => $old_timestamp,
			),
			array(
				'type'        => 'error',
				'message'     => 'Old notice 2',
				'dismissible' => true,
				'timestamp'   => $old_timestamp,
			),
		);

		Functions\expect( 'get_option' )
			->once()
			->with( 'dwt_local_fonts_notices', array() )
			->andReturn( $test_notices );

		Functions\expect( 'delete_option' )
			->once()
			->with( 'dwt_local_fonts_notices' )
			->andReturn( true );

		Functions\expect( 'update_option' )->never();

		$notices->clear_old_notices();
	}

	/**
	 * Test clear_old_notices does nothing when no notices exist.
	 */
	public function test_clear_old_notices_handles_empty_queue(): void {
		$notices = new Notices();

		Functions\expect( 'get_option' )
			->once()
			->with( 'dwt_local_fonts_notices', array() )
			->andReturn( array() );

		Functions\expect( 'update_option' )->never();
		Functions\expect( 'delete_option' )->never();

		$notices->clear_old_notices();
	}

	/**
	 * Test clear_old_notices preserves all notices when none are expired.
	 */
	public function test_clear_old_notices_preserves_all_when_none_expired(): void {
		$notices = new Notices();

		$new_timestamp = time() - 100;

		$test_notices = array(
			array(
				'type'        => 'success',
				'message'     => 'New notice 1',
				'dismissible' => true,
				'timestamp'   => $new_timestamp,
			),
			array(
				'type'        => 'error',
				'message'     => 'New notice 2',
				'dismissible' => true,
				'timestamp'   => $new_timestamp,
			),
		);

		Functions\expect( 'get_option' )
			->once()
			->with( 'dwt_local_fonts_notices', array() )
			->andReturn( $test_notices );

		Functions\expect( 'update_option' )->never();
		Functions\expect( 'delete_option' )->never();

		$notices->clear_old_notices();
	}

	/**
	 * Test render_notice with default info type.
	 */
	public function test_render_notice_defaults_to_info(): void {
		$notices = new Notices();

		$test_notices = array(
			array(
				'message'     => 'Test message',
				'dismissible' => true,
				'timestamp'   => time(),
			),
		);

		Functions\expect( 'get_option' )
			->once()
			->with( 'dwt_local_fonts_notices', array() )
			->andReturn( $test_notices );

		Functions\expect( 'delete_option' )
			->once()
			->with( 'dwt_local_fonts_notices' )
			->andReturn( true );

		ob_start();
		$notices->display_notices();
		$output = ob_get_clean();

		// Should default to info type.
		$this->assertStringContainsString( 'notice-info', $output );
		$this->assertStringContainsString( 'Test message', $output );
	}

	/**
	 * Test notices are appended to existing queue.
	 */
	public function test_notices_appended_to_existing_queue(): void {
		Functions\expect( 'get_option' )
			->once()
			->with( 'dwt_local_fonts_notices', array() )
			->andReturn(
				array(
					array(
						'type'        => 'success',
						'message'     => 'Existing notice',
						'dismissible' => true,
						'timestamp'   => time(),
					),
				)
			);

		Functions\expect( 'update_option' )
			->once()
			->with(
				'dwt_local_fonts_notices',
				Mockery::on(
					function ( $notices ) {
						return is_array( $notices )
							&& count( $notices ) === 2
							&& $notices[0]['message'] === 'Existing notice'
							&& $notices[1]['message'] === 'New notice';
					}
				)
			)
			->andReturn( true );

		Notices::add_info( 'New notice' );
	}
}
