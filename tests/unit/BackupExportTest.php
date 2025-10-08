<?php
/**
 * BackupExport Unit Tests
 *
 * @package DWT\LocalFonts
 */

declare(strict_types=1);

namespace DWT\LocalFonts\Tests;

use DWT\LocalFonts\Modules\BackupExport;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Brain\Monkey\Actions;
use Mockery;

/**
 * Unit tests for BackupExport module.
 */
final class BackupExportTest extends \PHPUnit\Framework\TestCase {

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Define constants if not already defined.
		if ( ! defined( 'DWT_LOCAL_FONTS_VERSION' ) ) {
			define( 'DWT_LOCAL_FONTS_VERSION', '0.1.0-test' );
		}

		if ( ! defined( 'WP_DEBUG' ) ) {
			define( 'WP_DEBUG', false );
		}

		// Stub common WordPress functions.
		Functions\when( '__' )->returnArg();
		Functions\when( 'get_option' )->justReturn( array() );
		Functions\when( 'update_option' )->justReturn( true );
		Functions\when( 'wp_upload_dir' )->justReturn(
			array(
				'basedir' => '/var/www/wp-content/uploads',
				'baseurl' => 'https://example.com/wp-content/uploads',
			)
		);
		Functions\when( 'get_site_url' )->justReturn( 'https://example.com' );
	}

	/**
	 * Tear down test environment.
	 */
	protected function tearDown(): void {
		Mockery::close();
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Test constructor adds action hook for REST API initialization.
	 */
	public function test_constructor_adds_rest_api_init_hook(): void {
		Actions\expectAdded( 'rest_api_init' )->once();

		$backup_export = new BackupExport();

		$this->assertInstanceOf( BackupExport::class, $backup_export );
	}

	/**
	 * Test register_rest_routes registers all three endpoints.
	 */
	public function test_register_rest_routes_registers_all_endpoints(): void {
		Functions\expect( 'register_rest_route' )->times( 3 );

		$backup_export = new BackupExport();
		$backup_export->register_rest_routes();

		$this->assertTrue( true ); // Assert that no exceptions were thrown.
	}

	/**
	 * Test export_configuration returns valid response structure.
	 */
	public function test_export_configuration_returns_valid_structure(): void {
		$fonts    = array( 'Roboto' => array( 'family' => 'Roboto' ) );
		$settings = array( 'auto_discover' => true );

		Functions\stubs(
			array(
				'get_option'         => function ( $option, $default = array() ) use ( $fonts, $settings ) {
					if ( 'dwt_local_fonts_list' === $option ) {
						return $fonts;
					}
					if ( 'dwt_local_fonts_settings' === $option ) {
						return $settings;
					}
					return $default;
				},
				'update_option'      => true,
				'get_site_url'       => 'https://example.com',
			)
		);

		$backup_export = new BackupExport();
		$response      = $backup_export->export_configuration();

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertArrayHasKey( 'version', $data );
		$this->assertArrayHasKey( 'exported_at', $data );
		$this->assertArrayHasKey( 'exported_by', $data );
		$this->assertArrayHasKey( 'site_url', $data );
		$this->assertArrayHasKey( 'fonts', $data );
		$this->assertArrayHasKey( 'settings', $data );
		$this->assertArrayHasKey( 'css_exists', $data );
		$this->assertArrayHasKey( 'css_size', $data );
		$this->assertArrayHasKey( 'font_count', $data );

		$this->assertEquals( DWT_LOCAL_FONTS_VERSION, $data['version'] );
		$this->assertEquals( 0, $data['exported_by'] ); // get_current_user_id() returns 0 in tests.
		$this->assertEquals( 'https://example.com', $data['site_url'] );
		$this->assertEquals( $fonts, $data['fonts'] );
		$this->assertEquals( $settings, $data['settings'] );
		$this->assertEquals( 1, $data['font_count'] );
	}

	/**
	 * Test export_configuration with no fonts.
	 */
	public function test_export_configuration_with_no_fonts(): void {
		$backup_export = new BackupExport();
		$response      = $backup_export->export_configuration();

		$data = $response->get_data();

		$this->assertEquals( 0, $data['font_count'] );
		$this->assertEmpty( $data['fonts'] );
	}

	/**
	 * Test export_configuration saves to backup history.
	 */
	public function test_export_configuration_saves_to_backup_history(): void {
		$history_saved = false;

		Functions\stubs(
			array(
				'get_option'    => function ( $option, $default = array() ) {
					if ( 'dwt_local_fonts_list' === $option ) {
						return array( 'Roboto' => array() );
					}
					return $default;
				},
				'update_option' => function ( $option, $value ) use ( &$history_saved ) {
					if ( 'dwt_local_fonts_backup_history' === $option ) {
						$history_saved = is_array( $value ) && count( $value ) === 1;
					}
					return true;
				},
			)
		);

		$backup_export = new BackupExport();
		$backup_export->export_configuration();

		$this->assertTrue( $history_saved );
	}

	/**
	 * Test backup history limits to 10 entries.
	 */
	public function test_backup_history_limits_to_10_entries(): void {
		// Create 11 existing backups.
		$existing_history = array();
		for ( $i = 0; $i < 11; $i++ ) {
			$existing_history[] = array(
				'exported_at' => "2025-01-0{$i} 12:00:00",
				'exported_by' => 1,
				'font_count'  => 1,
				'version'     => '0.1.0',
			);
		}

		$saved_history = null;

		Functions\stubs(
			array(
				'get_option'    => function ( $option, $default = array() ) use ( $existing_history ) {
					if ( 'dwt_local_fonts_backup_history' === $option ) {
						return $existing_history;
					}
					if ( 'dwt_local_fonts_list' === $option ) {
						return array( 'Roboto' => array() );
					}
					return $default;
				},
				'update_option' => function ( $option, $value ) use ( &$saved_history ) {
					if ( 'dwt_local_fonts_backup_history' === $option ) {
						$saved_history = $value;
					}
					return true;
				},
			)
		);

		$backup_export = new BackupExport();
		$backup_export->export_configuration();

		$this->assertIsArray( $saved_history );
		$this->assertCount( 10, $saved_history );
	}

	/**
	 * Test get_backup_history returns valid response.
	 */
	public function test_get_backup_history_returns_valid_response(): void {
		$history = array(
			array(
				'exported_at' => '2025-01-01 10:00:00',
				'exported_by' => 1,
				'font_count'  => 3,
				'version'     => '0.1.0',
			),
			array(
				'exported_at' => '2025-01-02 10:00:00',
				'exported_by' => 1,
				'font_count'  => 5,
				'version'     => '0.1.0',
			),
		);

		Functions\stubs(
			array(
				'get_option' => function ( $option, $default = array() ) use ( $history ) {
					if ( 'dwt_local_fonts_backup_history' === $option ) {
						return $history;
					}
					return $default;
				},
			)
		);

		$backup_export = new BackupExport();
		$response      = $backup_export->get_backup_history();

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertArrayHasKey( 'backups', $data );
		$this->assertArrayHasKey( 'count', $data );
		$this->assertEquals( 2, $data['count'] );

		// Should be in reverse order (newest first).
		$this->assertEquals( '2025-01-02 10:00:00', $data['backups'][0]['exported_at'] );
		$this->assertEquals( '2025-01-01 10:00:00', $data['backups'][1]['exported_at'] );
	}

	/**
	 * Test get_backup_history with empty history.
	 */
	public function test_get_backup_history_with_empty_history(): void {
		$backup_export = new BackupExport();
		$response      = $backup_export->get_backup_history();

		$data = $response->get_data();

		$this->assertEquals( 0, $data['count'] );
		$this->assertEmpty( $data['backups'] );
	}

	/**
	 * Test import_configuration rejects request without valid nonce.
	 */
	public function test_import_configuration_rejects_invalid_nonce(): void {
		$request = Mockery::mock( \WP_REST_Request::class );
		$request->shouldReceive( 'get_header' )
			->once()
			->with( 'X-WP-Nonce' )
			->andReturn( 'invalid_nonce' );

		$request->shouldReceive( 'get_route' )
			->andReturn( '/dwt-management/v1/backup/import' );

		Functions\expect( 'wp_verify_nonce' )
			->once()
			->with( 'invalid_nonce', 'wp_rest' )
			->andReturn( false );

		$backup_export = new BackupExport();
		$response      = $backup_export->import_configuration( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertEquals( 403, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'error', $data );
		$this->assertFalse( $data['success'] );
	}

	/**
	 * Test import_configuration rejects empty data.
	 */
	public function test_import_configuration_rejects_empty_data(): void {
		$request = Mockery::mock( \WP_REST_Request::class );
		$request->shouldReceive( 'get_header' )
			->once()
			->with( 'X-WP-Nonce' )
			->andReturn( 'valid_nonce' );

		$request->shouldReceive( 'get_route' )
			->andReturn( '/dwt-management/v1/backup/import' );

		$request->shouldReceive( 'get_json_params' )
			->once()
			->andReturn( array() );

		Functions\expect( 'wp_verify_nonce' )
			->once()
			->with( 'valid_nonce', 'wp_rest' )
			->andReturn( true );

		$backup_export = new BackupExport();
		$response      = $backup_export->import_configuration( $request );

		$this->assertEquals( 400, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'error', $data );
		$this->assertEquals( 'Invalid import data', $data['error'] );
	}

	/**
	 * Test import_configuration rejects data missing required fields.
	 */
	public function test_import_configuration_rejects_missing_fields(): void {
		$request = Mockery::mock( \WP_REST_Request::class );
		$request->shouldReceive( 'get_header' )
			->once()
			->with( 'X-WP-Nonce' )
			->andReturn( 'valid_nonce' );

		$request->shouldReceive( 'get_route' )
			->andReturn( '/dwt-management/v1/backup/import' );

		$request->shouldReceive( 'get_json_params' )
			->once()
			->andReturn( array( 'fonts' => array() ) ); // Missing 'settings'.

		Functions\expect( 'wp_verify_nonce' )
			->once()
			->with( 'valid_nonce', 'wp_rest' )
			->andReturn( true );

		$backup_export = new BackupExport();
		$response      = $backup_export->import_configuration( $request );

		$this->assertEquals( 400, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'error', $data );
		$this->assertStringContainsString( 'missing required fields', $data['error'] );
	}

	/**
	 * Test successful import with valid data.
	 */
	public function test_successful_import_with_valid_data(): void {
		$import_fonts    = array( 'Roboto' => array( 'family' => 'Roboto' ) );
		$import_settings = array( 'auto_discover' => true );
		$existing_fonts  = array( 'OldFont' => array() );

		$request = Mockery::mock( \WP_REST_Request::class );
		$request->shouldReceive( 'get_header' )
			->once()
			->with( 'X-WP-Nonce' )
			->andReturn( 'valid_nonce' );

		$request->shouldReceive( 'get_route' )
			->andReturn( '/dwt-management/v1/backup/import' );

		$request->shouldReceive( 'get_json_params' )
			->once()
			->andReturn(
				array(
					'fonts'    => $import_fonts,
					'settings' => $import_settings,
				)
			);

		Functions\expect( 'wp_verify_nonce' )
			->once()
			->with( 'valid_nonce', 'wp_rest' )
			->andReturn( true );

		Functions\stubs(
			array(
				'get_option'    => function ( $option, $default = array() ) use ( $existing_fonts ) {
					if ( 'dwt_local_fonts_list' === $option ) {
						return $existing_fonts;
					}
					return $default;
				},
				'update_option' => true,
			)
		);

		$backup_export = new BackupExport();
		$response      = $backup_export->import_configuration( $request );

		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();

		$this->assertTrue( $data['success'] );
		$this->assertEquals( 1, $data['imported_fonts'] );
		$this->assertTrue( $data['imported_settings'] );
		$this->assertTrue( $data['backup_created'] );
		$this->assertArrayHasKey( 'backup_data', $data );
	}

	/**
	 * Test import creates backup before importing.
	 */
	public function test_import_creates_backup_before_importing(): void {
		$existing_fonts = array( 'ExistingFont' => array() );

		$request = Mockery::mock( \WP_REST_Request::class );
		$request->shouldReceive( 'get_header' )
			->once()
			->with( 'X-WP-Nonce' )
			->andReturn( 'valid_nonce' );

		$request->shouldReceive( 'get_route' )
			->andReturn( '/dwt-management/v1/backup/import' );

		$request->shouldReceive( 'get_json_params' )
			->once()
			->andReturn(
				array(
					'fonts'    => array( 'NewFont' => array() ),
					'settings' => array(),
				)
			);

		Functions\expect( 'wp_verify_nonce' )
			->once()
			->andReturn( true );

		Functions\stubs(
			array(
				'get_option'    => function ( $option, $default = array() ) use ( $existing_fonts ) {
					if ( 'dwt_local_fonts_list' === $option ) {
						return $existing_fonts;
					}
					return $default;
				},
				'update_option' => true,
			)
		);

		$backup_export = new BackupExport();
		$response      = $backup_export->import_configuration( $request );

		$data = $response->get_data();

		$this->assertTrue( $data['backup_created'] );
		$this->assertArrayHasKey( 'backup_data', $data );
		$this->assertArrayHasKey( 'fonts', $data['backup_data'] );
		$this->assertArrayHasKey( 'ExistingFont', $data['backup_data']['fonts'] );
	}

	/**
	 * Test import with non-array fonts data.
	 */
	public function test_import_with_non_array_fonts_data(): void {
		$request = Mockery::mock( \WP_REST_Request::class );
		$request->shouldReceive( 'get_header' )
			->once()
			->with( 'X-WP-Nonce' )
			->andReturn( 'valid_nonce' );

		$request->shouldReceive( 'get_route' )
			->andReturn( '/dwt-management/v1/backup/import' );

		$request->shouldReceive( 'get_json_params' )
			->once()
			->andReturn(
				array(
					'fonts'    => 'not_an_array',
					'settings' => array(),
				)
			);

		Functions\expect( 'wp_verify_nonce' )
			->once()
			->andReturn( true );

		$backup_export = new BackupExport();
		$response      = $backup_export->import_configuration( $request );

		$data = $response->get_data();

		$this->assertEquals( 0, $data['imported_fonts'] );
		$this->assertTrue( $data['imported_settings'] );
	}

	/**
	 * Test import with non-array settings data.
	 */
	public function test_import_with_non_array_settings_data(): void {
		$request = Mockery::mock( \WP_REST_Request::class );
		$request->shouldReceive( 'get_header' )
			->once()
			->with( 'X-WP-Nonce' )
			->andReturn( 'valid_nonce' );

		$request->shouldReceive( 'get_route' )
			->andReturn( '/dwt-management/v1/backup/import' );

		$request->shouldReceive( 'get_json_params' )
			->once()
			->andReturn(
				array(
					'fonts'    => array( 'Roboto' => array() ),
					'settings' => 'not_an_array',
				)
			);

		Functions\expect( 'wp_verify_nonce' )
			->once()
			->andReturn( true );

		$backup_export = new BackupExport();
		$response      = $backup_export->import_configuration( $request );

		$data = $response->get_data();

		$this->assertEquals( 1, $data['imported_fonts'] );
		$this->assertFalse( $data['imported_settings'] );
	}
}
