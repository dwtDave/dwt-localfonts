<?php
/**
 * FontRestController class tests.
 *
 * @package DWT\LocalFonts
 */

declare(strict_types=1);

namespace DWT\LocalFonts\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use DWT\LocalFonts\Controllers\FontRestController;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

/**
 * Test case for the FontRestController class.
 */
final class FontRestControllerTest extends TestCase {
	use MockeryPHPUnitIntegration;

	/**
	 * FontRestController instance for testing.
	 *
	 * @var FontRestController
	 */
	private FontRestController $rest_controller;

	/**
	 * Mock options for testing.
	 *
	 * @var array<string, mixed>
	 */
	private array $mock_options = array();

	/**
	 * Mock storage instance.
	 *
	 * @var \Mockery\MockInterface
	 */
	private $mock_storage;

	/**
	 * Mock downloader instance.
	 *
	 * @var \Mockery\MockInterface
	 */
	private $mock_downloader;

	/**
	 * Mock validator instance.
	 *
	 * @var \Mockery\MockInterface
	 */
	private $mock_validator;

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
		Functions\when( 'delete_option' )->justReturn( true );
		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_html__' )->returnArg();
		Functions\when( 'wp_upload_dir' )->justReturn(
			array(
				'basedir' => '/tmp/wp-content/uploads',
				'baseurl' => 'http://example.com/wp-content/uploads',
			)
		);

		// Create mock dependencies to avoid calling real WordPress functions.
		$this->mock_validator  = \Mockery::mock( 'DWT\LocalFonts\Services\FontValidator' )->shouldIgnoreMissing();
		$this->mock_storage    = \Mockery::mock( 'DWT\LocalFonts\Services\FontStorage' )->shouldIgnoreMissing();
		$this->mock_downloader = \Mockery::mock( 'DWT\LocalFonts\Services\FontDownloader' )->shouldIgnoreMissing();

		// Set up mock storage methods that are commonly used.
		$this->mock_storage->shouldReceive( 'delete_font_files' )->andReturn( 5 )->byDefault();
		$this->mock_storage->shouldReceive( 'get_font_dir_path' )->andReturn( '/tmp/fonts' )->byDefault();
		$this->mock_storage->shouldReceive( 'get_all_font_files' )->andReturn( array() )->byDefault();

		$this->rest_controller = new FontRestController( $this->mock_validator, $this->mock_storage, $this->mock_downloader );
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
		$controller = new FontRestController();

		$this->assertInstanceOf( FontRestController::class, $controller );
	}

	/**
	 * Test get_popular_fonts returns an array of fonts.
	 */
	public function test_it_returns_popular_fonts(): void {
		$reflection = new \ReflectionClass( $this->rest_controller );
		$method     = $reflection->getMethod( 'get_popular_fonts' );
		$method->setAccessible( true );

		$fonts = $method->invoke( $this->rest_controller );

		$this->assertIsArray( $fonts );
		$this->assertNotEmpty( $fonts );
		$this->assertArrayHasKey( 'family', $fonts[0] );
		$this->assertArrayHasKey( 'category', $fonts[0] );
		$this->assertArrayHasKey( 'variants', $fonts[0] );
	}

	/**
	 * Test get_google_fonts returns popular fonts when no API key is provided.
	 */
	public function test_it_returns_popular_fonts_when_no_api_key(): void {
		Functions\when( 'apply_filters' )->justReturn( '' );

		$response = $this->rest_controller->get_google_fonts();

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertNotEmpty( $data );
	}

	/**
	 * Test filter_fonts_locally filters by search term.
	 */
	public function test_it_filters_fonts_by_search_term(): void {
		$fonts = array(
			array(
				'family'   => 'Open Sans',
				'category' => 'sans-serif',
			),
			array(
				'family'   => 'Roboto',
				'category' => 'sans-serif',
			),
			array(
				'family'   => 'Playfair Display',
				'category' => 'serif',
			),
		);

		$reflection = new \ReflectionClass( $this->rest_controller );
		$method     = $reflection->getMethod( 'filter_fonts_locally' );
		$method->setAccessible( true );

		$filtered = $method->invoke( $this->rest_controller, $fonts, 'open', '' );

		$this->assertCount( 1, $filtered );
		$this->assertEquals( 'Open Sans', reset( $filtered )['family'] );
	}

	/**
	 * Test filter_fonts_locally filters by category.
	 */
	public function test_it_filters_fonts_by_category(): void {
		$fonts = array(
			array(
				'family'   => 'Open Sans',
				'category' => 'sans-serif',
			),
			array(
				'family'   => 'Roboto',
				'category' => 'sans-serif',
			),
			array(
				'family'   => 'Playfair Display',
				'category' => 'serif',
			),
		);

		$reflection = new \ReflectionClass( $this->rest_controller );
		$method     = $reflection->getMethod( 'filter_fonts_locally' );
		$method->setAccessible( true );

		$filtered = $method->invoke( $this->rest_controller, $fonts, '', 'serif' );

		$this->assertCount( 1, $filtered );
		$this->assertEquals( 'Playfair Display', reset( $filtered )['family'] );
	}

	/**
	 * Test filter_fonts_locally filters by both search and category.
	 */
	public function test_it_filters_fonts_by_search_and_category(): void {
		$fonts = array(
			array(
				'family'   => 'Open Sans',
				'category' => 'sans-serif',
			),
			array(
				'family'   => 'Roboto',
				'category' => 'sans-serif',
			),
			array(
				'family'   => 'Playfair Display',
				'category' => 'serif',
			),
		);

		$reflection = new \ReflectionClass( $this->rest_controller );
		$method     = $reflection->getMethod( 'filter_fonts_locally' );
		$method->setAccessible( true );

		$filtered = $method->invoke( $this->rest_controller, $fonts, 'sans', 'sans-serif' );

		$this->assertCount( 1, $filtered );
		$this->assertEquals( 'Open Sans', reset( $filtered )['family'] );
	}

	/**
	 * Test get_local_fonts returns response object.
	 */
	public function test_it_returns_local_fonts(): void {
		$response = $this->rest_controller->get_local_fonts();

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
	}

	/**
	 * Test delete_font_api returns error when font_family is missing.
	 */
	public function test_it_returns_error_when_deleting_font_without_family(): void {
		$request = \Mockery::mock( \WP_REST_Request::class );
		$request->shouldReceive( 'get_param' )
			->with( 'font_family' )
			->andReturn( '' );

		$request->shouldReceive( 'get_header' )
			->with( 'X-WP-Nonce' )
			->andReturn( 'test_nonce' );

		$request->shouldReceive( 'get_route' )
			->andReturn( '/fonts/delete' );

		Functions\when( 'wp_verify_nonce' )->justReturn( true );

		$response = $this->rest_controller->delete_font_api( $request );

		$this->assertEquals( 400, $response->get_status() );
		$this->assertArrayHasKey( 'error', $response->get_data() );
	}

	/**
	 * Test delete_font_api removes font from the list.
	 */
	public function test_it_deletes_font_successfully(): void {
		// Set up fonts in the mock options.
		$this->mock_options['dwt_local_fonts_list'] = array( 'Roboto', 'Open Sans' );

		Functions\when( 'wp_verify_nonce' )->justReturn( true );

		$request = \Mockery::mock( \WP_REST_Request::class );
		$request->shouldReceive( 'get_param' )
			->with( 'font_family' )
			->andReturn( 'Roboto' );

		$request->shouldReceive( 'get_header' )
			->with( 'X-WP-Nonce' )
			->andReturn( 'test_nonce' );

		$request->shouldReceive( 'get_route' )
			->andReturn( '/fonts/delete' );

		$response = $this->rest_controller->delete_font_api( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'downloaded_fonts', $data );
	}

	/**
	 * Test download_font_api returns error when nonce is invalid.
	 */
	public function test_it_returns_error_when_nonce_is_invalid(): void {
		$request = \Mockery::mock( \WP_REST_Request::class );
		$request->shouldReceive( 'get_header' )
			->with( 'X-WP-Nonce' )
			->andReturn( 'invalid_nonce' );

		$request->shouldReceive( 'get_route' )
			->andReturn( '/fonts/download' );

		Functions\when( 'wp_verify_nonce' )->justReturn( false );

		$response = $this->rest_controller->download_font_api( $request );

		$this->assertEquals( 403, $response->get_status() );
		$this->assertArrayHasKey( 'error', $response->get_data() );
	}

	/**
	 * Test download_font_api returns error when font_url and font_css are missing.
	 */
	public function test_it_returns_error_when_downloading_font_without_url_or_css(): void {
		$request = \Mockery::mock( \WP_REST_Request::class );
		$request->shouldReceive( 'get_header' )
			->with( 'X-WP-Nonce' )
			->andReturn( 'test_nonce' );

		$request->shouldReceive( 'get_param' )
			->andReturn( '' );

		$request->shouldReceive( 'get_route' )
			->andReturn( '/fonts/download' );

		Functions\when( 'wp_verify_nonce' )->justReturn( true );
		Functions\when( 'apply_filters' )->justReturn( true );

		$response = $this->rest_controller->download_font_api( $request );

		$this->assertEquals( 400, $response->get_status() );
		$this->assertArrayHasKey( 'error', $response->get_data() );
	}

	/**
	 * Test batch_download_fonts_api returns error when fonts array is missing.
	 */
	public function test_it_returns_error_when_batch_downloading_without_fonts_array(): void {
		$request = \Mockery::mock( \WP_REST_Request::class );
		$request->shouldReceive( 'get_header' )
			->with( 'X-WP-Nonce' )
			->andReturn( 'test_nonce' );

		$request->shouldReceive( 'get_param' )
			->with( 'fonts' )
			->andReturn( null );

		$request->shouldReceive( 'get_route' )
			->andReturn( '/fonts/batch-download' );

		Functions\when( 'wp_verify_nonce' )->justReturn( true );

		$response = $this->rest_controller->batch_download_fonts_api( $request );

		$this->assertEquals( 400, $response->get_status() );
		$this->assertArrayHasKey( 'error', $response->get_data() );
	}

	/**
	 * Test batch_delete_fonts_api returns error when font_families array is missing.
	 */
	public function test_it_returns_error_when_batch_deleting_without_families_array(): void {
		$request = \Mockery::mock( \WP_REST_Request::class );
		$request->shouldReceive( 'get_header' )
			->with( 'X-WP-Nonce' )
			->andReturn( 'test_nonce' );

		$request->shouldReceive( 'get_param' )
			->with( 'font_families' )
			->andReturn( null );

		$request->shouldReceive( 'get_route' )
			->andReturn( '/fonts/batch-delete' );

		Functions\when( 'wp_verify_nonce' )->justReturn( true );

		$response = $this->rest_controller->batch_delete_fonts_api( $request );

		$this->assertEquals( 400, $response->get_status() );
		$this->assertArrayHasKey( 'error', $response->get_data() );
	}

	/**
	 * Test discover_fonts_api returns success response.
	 */
	public function test_it_discovers_fonts_successfully(): void {
		$request = \Mockery::mock( \WP_REST_Request::class );
		$request->shouldReceive( 'get_header' )
			->with( 'X-WP-Nonce' )
			->andReturn( 'test_nonce' );

		$request->shouldReceive( 'get_route' )
			->andReturn( '/fonts/discover' );

		Functions\when( 'wp_verify_nonce' )->justReturn( true );

		$response = $this->rest_controller->discover_fonts_api( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'success', $data );
		$this->assertTrue( $data['success'] );
	}

	/**
	 * Test delete_font_api returns error when font is not found.
	 */
	public function test_it_returns_error_when_deleting_nonexistent_font(): void {
		// Set up fonts in the mock options (but not the one we're trying to delete).
		$this->mock_options['dwt_local_fonts_list'] = array( 'Roboto', 'Open Sans' );

		Functions\when( 'wp_verify_nonce' )->justReturn( true );

		$request = \Mockery::mock( \WP_REST_Request::class );
		$request->shouldReceive( 'get_param' )
			->with( 'font_family' )
			->andReturn( 'NonExistent Font' );

		$request->shouldReceive( 'get_header' )
			->with( 'X-WP-Nonce' )
			->andReturn( 'test_nonce' );

		$request->shouldReceive( 'get_route' )
			->andReturn( '/fonts/delete' );

		$response = $this->rest_controller->delete_font_api( $request );

		$this->assertEquals( 404, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'error', $data );
		$this->assertEquals( 'Font not found', $data['error'] );
	}

	/**
	 * Test download_font_api successfully downloads font from URL.
	 */
	public function test_it_downloads_font_from_url_successfully(): void {
		Functions\when( 'wp_verify_nonce' )->justReturn( true );
		Functions\when( 'apply_filters' )->justReturn( true );

		$this->mock_downloader->shouldReceive( 'download_font' )
			->with( 'https://fonts.googleapis.com/css2?family=Roboto' )
			->once()
			->andReturn(
				array(
					'success'  => true,
					'message'  => 'Font downloaded successfully',
					'families' => array( 'Roboto' ),
					'css'      => '/* Generated CSS */',
				)
			);

		$request = \Mockery::mock( \WP_REST_Request::class );
		$request->shouldReceive( 'get_header' )
			->with( 'X-WP-Nonce' )
			->andReturn( 'test_nonce' );

		$request->shouldReceive( 'get_param' )
			->with( 'font_url' )
			->andReturn( 'https://fonts.googleapis.com/css2?family=Roboto' );

		$request->shouldReceive( 'get_param' )
			->with( 'font_css' )
			->andReturn( '' );

		$request->shouldReceive( 'get_param' )
			->with( 'font_name' )
			->andReturn( 'Roboto' );

		$request->shouldReceive( 'get_route' )
			->andReturn( '/fonts/download' );

		$response = $this->rest_controller->download_font_api( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'downloaded_fonts', $data );
	}

	/**
	 * Test download_font_api successfully downloads font from CSS.
	 */
	public function test_it_downloads_font_from_css_successfully(): void {
		Functions\when( 'wp_verify_nonce' )->justReturn( true );
		Functions\when( 'apply_filters' )->justReturn( true );

		$css_content = '@font-face { font-family: "Roboto"; src: url("roboto.woff2"); }';

		$this->mock_downloader->shouldReceive( 'download_font_from_css' )
			->with( $css_content )
			->once()
			->andReturn(
				array(
					'success'  => true,
					'message'  => 'Font downloaded successfully',
					'families' => array( 'Roboto' ),
					'css'      => $css_content,
				)
			);

		$request = \Mockery::mock( \WP_REST_Request::class );
		$request->shouldReceive( 'get_header' )
			->with( 'X-WP-Nonce' )
			->andReturn( 'test_nonce' );

		$request->shouldReceive( 'get_param' )
			->with( 'font_url' )
			->andReturn( '' );

		$request->shouldReceive( 'get_param' )
			->with( 'font_css' )
			->andReturn( $css_content );

		$request->shouldReceive( 'get_param' )
			->with( 'font_name' )
			->andReturn( 'Roboto' );

		$request->shouldReceive( 'get_route' )
			->andReturn( '/fonts/download' );

		$response = $this->rest_controller->download_font_api( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
	}

	/**
	 * Test download_font_api returns error when download fails.
	 */
	public function test_it_returns_error_when_font_download_fails(): void {
		Functions\when( 'wp_verify_nonce' )->justReturn( true );
		Functions\when( 'apply_filters' )->justReturn( true );

		$this->mock_downloader->shouldReceive( 'download_font' )
			->once()
			->andReturn(
				array(
					'success' => false,
					'message' => 'Download failed: Invalid URL',
				)
			);

		$request = \Mockery::mock( \WP_REST_Request::class );
		$request->shouldReceive( 'get_header' )
			->with( 'X-WP-Nonce' )
			->andReturn( 'test_nonce' );

		$request->shouldReceive( 'get_param' )
			->with( 'font_url' )
			->andReturn( 'https://invalid.url/font.css' );

		$request->shouldReceive( 'get_param' )
			->with( 'font_css' )
			->andReturn( '' );

		$request->shouldReceive( 'get_param' )
			->with( 'font_name' )
			->andReturn( '' );

		$request->shouldReceive( 'get_route' )
			->andReturn( '/fonts/download' );

		$response = $this->rest_controller->download_font_api( $request );

		$this->assertEquals( 400, $response->get_status() );
		$data = $response->get_data();
		$this->assertFalse( $data['success'] );
		$this->assertArrayHasKey( 'message', $data );
	}

	/**
	 * Test download_font_api returns error when rate limit is exceeded.
	 */
	public function test_it_returns_error_when_rate_limit_exceeded(): void {
		Functions\when( 'wp_verify_nonce' )->justReturn( true );
		Functions\when( 'apply_filters' )->justReturn( false ); // Rate limit exceeded.

		$request = \Mockery::mock( \WP_REST_Request::class );
		$request->shouldReceive( 'get_header' )
			->with( 'X-WP-Nonce' )
			->andReturn( 'test_nonce' );

		$request->shouldReceive( 'get_route' )
			->andReturn( '/fonts/download' );

		$response = $this->rest_controller->download_font_api( $request );

		$this->assertEquals( 429, $response->get_status() );
		$data = $response->get_data();
		$this->assertArrayHasKey( 'error', $data );
		$this->assertEquals( 'Rate limit exceeded', $data['error'] );
	}

	/**
	 * Test batch_download_fonts_api successfully downloads multiple fonts.
	 */
	public function test_it_batch_downloads_fonts_successfully(): void {
		Functions\when( 'wp_verify_nonce' )->justReturn( true );

		$fonts = array(
			array(
				'url'  => 'https://fonts.googleapis.com/css2?family=Roboto',
				'name' => 'Roboto',
			),
			array(
				'url'  => 'https://fonts.googleapis.com/css2?family=Open+Sans',
				'name' => 'Open Sans',
			),
		);

		$this->mock_downloader->shouldReceive( 'download_font' )
			->twice()
			->andReturn(
				array(
					'success'  => true,
					'message'  => 'Downloaded successfully',
					'families' => array( 'Roboto' ),
				),
				array(
					'success'  => true,
					'message'  => 'Downloaded successfully',
					'families' => array( 'Open Sans' ),
				)
			);

		$request = \Mockery::mock( \WP_REST_Request::class );
		$request->shouldReceive( 'get_header' )
			->with( 'X-WP-Nonce' )
			->andReturn( 'test_nonce' );

		$request->shouldReceive( 'get_param' )
			->with( 'fonts' )
			->andReturn( $fonts );

		$request->shouldReceive( 'get_route' )
			->andReturn( '/fonts/batch-download' );

		$response = $this->rest_controller->batch_download_fonts_api( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertEquals( 2, $data['success_count'] );
		$this->assertEquals( 0, $data['failed_count'] );
		$this->assertCount( 2, $data['results'] );
	}

	/**
	 * Test batch_download_fonts_api handles partial failures.
	 */
	public function test_it_batch_downloads_fonts_with_partial_failures(): void {
		Functions\when( 'wp_verify_nonce' )->justReturn( true );

		$fonts = array(
			array(
				'url'  => 'https://fonts.googleapis.com/css2?family=Roboto',
				'name' => 'Roboto',
			),
			array(
				'url'  => '',
				'name' => 'Invalid Font',
			),
			array(
				'url'  => 'https://fonts.googleapis.com/css2?family=Open+Sans',
				'name' => 'Open Sans',
			),
		);

		$this->mock_downloader->shouldReceive( 'download_font' )
			->twice()
			->andReturn(
				array(
					'success'  => true,
					'message'  => 'Downloaded successfully',
					'families' => array( 'Roboto' ),
				),
				array(
					'success' => false,
					'message' => 'Download failed',
				)
			);

		$request = \Mockery::mock( \WP_REST_Request::class );
		$request->shouldReceive( 'get_header' )
			->with( 'X-WP-Nonce' )
			->andReturn( 'test_nonce' );

		$request->shouldReceive( 'get_param' )
			->with( 'fonts' )
			->andReturn( $fonts );

		$request->shouldReceive( 'get_route' )
			->andReturn( '/fonts/batch-download' );

		$response = $this->rest_controller->batch_download_fonts_api( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertEquals( 1, $data['success_count'] );
		$this->assertEquals( 2, $data['failed_count'] );
	}

	/**
	 * Test batch_delete_fonts_api successfully deletes multiple fonts.
	 */
	public function test_it_batch_deletes_fonts_successfully(): void {
		$this->mock_options['dwt_local_fonts_list'] = array( 'Roboto', 'Open Sans', 'Lato' );

		Functions\when( 'wp_verify_nonce' )->justReturn( true );

		$this->mock_storage->shouldReceive( 'delete_font_files' )
			->twice()
			->andReturn( 5, 3 );

		$request = \Mockery::mock( \WP_REST_Request::class );
		$request->shouldReceive( 'get_header' )
			->with( 'X-WP-Nonce' )
			->andReturn( 'test_nonce' );

		$request->shouldReceive( 'get_param' )
			->with( 'font_families' )
			->andReturn( array( 'Roboto', 'Open Sans' ) );

		$request->shouldReceive( 'get_route' )
			->andReturn( '/fonts/batch-delete' );

		$response = $this->rest_controller->batch_delete_fonts_api( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertEquals( 2, $data['success_count'] );
		$this->assertEquals( 0, $data['failed_count'] );
		$this->assertEquals( 8, $data['total_files_deleted'] );
	}

	/**
	 * Test batch_delete_fonts_api handles partial failures.
	 */
	public function test_it_batch_deletes_fonts_with_partial_failures(): void {
		$this->mock_options['dwt_local_fonts_list'] = array( 'Roboto', 'Open Sans' );

		Functions\when( 'wp_verify_nonce' )->justReturn( true );

		$this->mock_storage->shouldReceive( 'delete_font_files' )
			->once()
			->andReturn( 5 );

		$request = \Mockery::mock( \WP_REST_Request::class );
		$request->shouldReceive( 'get_header' )
			->with( 'X-WP-Nonce' )
			->andReturn( 'test_nonce' );

		$request->shouldReceive( 'get_param' )
			->with( 'font_families' )
			->andReturn( array( 'Roboto', 'NonExistent Font' ) );

		$request->shouldReceive( 'get_route' )
			->andReturn( '/fonts/batch-delete' );

		$response = $this->rest_controller->batch_delete_fonts_api( $request );

		$this->assertEquals( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertEquals( 1, $data['success_count'] );
		$this->assertEquals( 1, $data['failed_count'] );
	}

	/**
	 * Test get_local_fonts with existing font data.
	 */
	public function test_it_returns_local_fonts_with_data(): void {
		$this->mock_options['dwt_local_fonts_list'] = array( 'Roboto', 'Open Sans' );

		$this->mock_storage->shouldReceive( 'get_all_font_files' )
			->once()
			->andReturn( array( 'roboto-regular.woff2', 'roboto-bold.woff2', 'open-sans-regular.woff2' ) );

		$response = $this->rest_controller->get_local_fonts();

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertCount( 2, $data );
		$this->assertEquals( 'Roboto', $data[0]['family'] );
		$this->assertEquals( 'downloaded', $data[0]['status'] );
		$this->assertArrayHasKey( 'font_files', $data[0] );
		$this->assertArrayHasKey( 'file_count', $data[0] );
		$this->assertCount( 2, $data[0]['font_files'] );
	}

	/**
	 * Test get_local_fonts when font files are missing.
	 */
	public function test_it_returns_local_fonts_when_files_missing(): void {
		$this->mock_options['dwt_local_fonts_list'] = array( 'Roboto' );

		$this->mock_storage->shouldReceive( 'get_all_font_files' )
			->once()
			->andReturn( array() ); // No font files found.

		Functions\when( 'update_option' )->justReturn( true );

		$response = $this->rest_controller->get_local_fonts();

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		// Font should be auto-removed when files are missing.
		$this->assertCount( 0, $data );
	}

	/**
	 * Test get_google_fonts with cached data.
	 */
	public function test_it_returns_cached_google_fonts(): void {
		$cached_fonts = array(
			array(
				'family'   => 'Roboto',
				'category' => 'sans-serif',
			),
		);

		Functions\when( 'apply_filters' )->justReturn( 'test_api_key' );
		Functions\when( 'get_transient' )->justReturn( $cached_fonts );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );

		$request = \Mockery::mock( \WP_REST_Request::class );
		$request->shouldReceive( 'get_param' )->andReturn( '' );

		$response = $this->rest_controller->get_google_fonts( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );
		$this->assertEquals( $cached_fonts, $response->get_data() );
	}

	/**
	 * Test get_google_fonts with API call.
	 */
	public function test_it_fetches_google_fonts_from_api(): void {
		$api_response_body = json_encode(
			array(
				'items' => array(
					array(
						'family'   => 'Roboto',
						'category' => 'sans-serif',
						'variants' => array( '400', '700' ),
					),
					array(
						'family'   => 'Open Sans',
						'category' => 'sans-serif',
						'variants' => array( '400', '700' ),
					),
				),
			)
		);

		Functions\when( 'apply_filters' )->justReturn( 'test_api_key' );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'wp_remote_get' )->justReturn( array( 'response' => array( 'code' => 200 ) ) );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( $api_response_body );

		$request = \Mockery::mock( \WP_REST_Request::class );
		$request->shouldReceive( 'get_param' )->andReturn( '' );

		$response = $this->rest_controller->get_google_fonts( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertCount( 2, $data );
	}

	/**
	 * Test get_google_fonts falls back to popular fonts on API error.
	 */
	public function test_it_falls_back_to_popular_fonts_on_api_error(): void {
		Functions\when( 'apply_filters' )->justReturn( 'test_api_key' );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'wp_remote_get' )->justReturn( new \WP_Error( 'http_error', 'HTTP error' ) );
		Functions\when( 'is_wp_error' )->justReturn( true );

		$request = \Mockery::mock( \WP_REST_Request::class );
		$request->shouldReceive( 'get_param' )->andReturn( '' );

		$response = $this->rest_controller->get_google_fonts( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertNotEmpty( $data );
	}

	/**
	 * Test get_google_fonts with invalid API response.
	 */
	public function test_it_falls_back_to_popular_fonts_on_invalid_api_response(): void {
		Functions\when( 'apply_filters' )->justReturn( 'test_api_key' );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( 'wp_remote_get' )->justReturn( array( 'response' => array( 'code' => 200 ) ) );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( '{"invalid": "response"}' );

		$request = \Mockery::mock( \WP_REST_Request::class );
		$request->shouldReceive( 'get_param' )->andReturn( '' );

		$response = $this->rest_controller->get_google_fonts( $request );

		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertNotEmpty( $data ); // Should be popular fonts.
	}

	/**
	 * Test register_rest_routes registers all endpoints.
	 */
	public function test_it_registers_rest_routes(): void {
		$registered_routes = array();

		Functions\when( 'register_rest_route' )->alias(
			function ( $namespace, $route, $args ) use ( &$registered_routes ) {
				$registered_routes[] = array(
					'namespace' => $namespace,
					'route'     => $route,
					'args'      => $args,
				);
				return true;
			}
		);

		$controller = new FontRestController( $this->mock_validator, $this->mock_storage, $this->mock_downloader );
		$controller->register_rest_routes();

		$this->assertCount( 7, $registered_routes );

		$routes = array_column( $registered_routes, 'route' );
		$this->assertContains( '/fonts/google', $routes );
		$this->assertContains( '/fonts/local', $routes );
		$this->assertContains( '/fonts/download', $routes );
		$this->assertContains( '/fonts/delete', $routes );
		$this->assertContains( '/fonts/batch-download', $routes );
		$this->assertContains( '/fonts/batch-delete', $routes );
		$this->assertContains( '/fonts/discover', $routes );
	}
}
