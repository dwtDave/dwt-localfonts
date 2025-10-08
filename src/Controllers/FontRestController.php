<?php
/**
 * Font REST API Controller
 *
 * @package DWT\LocalFonts
 */

declare(strict_types=1);

namespace DWT\LocalFonts\Controllers;

use DWT\LocalFonts\Abstracts\RestController;
use DWT\LocalFonts\Logger;
use DWT\LocalFonts\Modules\Notices;
use DWT\LocalFonts\Services\FontDownloader;
use DWT\LocalFonts\Services\FontStorage;
use DWT\LocalFonts\Services\FontValidator;

/**
 * Handles REST API endpoints for font management.
 */
class FontRestController extends RestController {

	private const OPTION_NAME = 'dwt_local_fonts_list';

	/**
	 * Font validator instance.
	 *
	 * @var FontValidator
	 */
	private FontValidator $validator;

	/**
	 * Font storage instance.
	 *
	 * @var FontStorage
	 */
	private FontStorage $storage;

	/**
	 * Font downloader instance.
	 *
	 * @var FontDownloader
	 */
	private FontDownloader $downloader;

	/**
	 * Constructor - Initialize services.
	 *
	 * @param FontValidator|null  $validator  Optional validator instance.
	 * @param FontStorage|null    $storage    Optional storage instance.
	 * @param FontDownloader|null $downloader Optional downloader instance.
	 */
	public function __construct( ?FontValidator $validator = null, ?FontStorage $storage = null, ?FontDownloader $downloader = null ) {
		$this->validator  = $validator ?? new FontValidator();
		$this->storage    = $storage ?? new FontStorage( $this->validator );
		$this->downloader = $downloader ?? new FontDownloader( $this->validator, $this->storage );

		// Register REST API endpoints.
		\add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Registers REST API endpoints for font management.
	 */
	public function register_rest_routes(): void {
		// Get available Google Fonts.
		\register_rest_route(
			self::REST_NAMESPACE,
			'/fonts/google',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_google_fonts' ),
				'permission_callback' => fn() => \current_user_can( 'manage_options' ),
				'args'                => array(
					'search'     => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
						'description'       => 'Search term for font family names',
					),
					'category'   => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
						'description'       => 'Filter by font category (serif, sans-serif, display, handwriting, monospace)',
					),
					'sort'       => array(
						'required'          => false,
						'default'           => 'popularity',
						'sanitize_callback' => 'sanitize_text_field',
						'description'       => 'Sort order (popularity, alpha, date, style, trending)',
					),
					'subset'     => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
						'description'       => 'Filter by subset (latin, latin-ext, etc.)',
					),
					'capability' => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
						'description'       => 'Filter by capability (VF for variable fonts)',
					),
				),
			)
		);

		// Get downloaded fonts.
		\register_rest_route(
			self::REST_NAMESPACE,
			'/fonts/local',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_local_fonts' ),
				'permission_callback' => fn() => \current_user_can( 'manage_options' ),
			)
		);

		// Download a font.
		\register_rest_route(
			self::REST_NAMESPACE,
			'/fonts/download',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'download_font_api' ),
				'permission_callback' => fn() => \current_user_can( 'manage_options' ),
				'args'                => array(
					'font_url'  => array(
						'required'          => false,
						'sanitize_callback' => 'esc_url_raw',
					),
					'font_css'  => array(
						'required'          => false,
						'sanitize_callback' => 'wp_kses_post',
					),
					'font_name' => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Delete a downloaded font.
		\register_rest_route(
			self::REST_NAMESPACE,
			'/fonts/delete',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'delete_font_api' ),
				'permission_callback' => fn() => \current_user_can( 'manage_options' ),
				'args'                => array(
					'font_family' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Batch download fonts.
		\register_rest_route(
			self::REST_NAMESPACE,
			'/fonts/batch-download',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'batch_download_fonts_api' ),
				'permission_callback' => fn() => \current_user_can( 'manage_options' ),
				'args'                => array(
					'fonts' => array(
						'required'          => true,
						'validate_callback' => fn( $param ) => is_array( $param ),
						'sanitize_callback' => fn( $fonts ) => array_map( 'esc_url_raw', $fonts ),
					),
				),
			)
		);

		// Batch delete fonts.
		\register_rest_route(
			self::REST_NAMESPACE,
			'/fonts/batch-delete',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'batch_delete_fonts_api' ),
				'permission_callback' => fn() => \current_user_can( 'manage_options' ),
				'args'                => array(
					'font_families' => array(
						'required'          => true,
						'validate_callback' => fn( $param ) => is_array( $param ),
						'sanitize_callback' => fn( $families ) => array_map( 'sanitize_text_field', $families ),
					),
				),
			)
		);

		// Discover fonts from directory.
		\register_rest_route(
			self::REST_NAMESPACE,
			'/fonts/discover',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'discover_fonts_api' ),
				'permission_callback' => fn() => \current_user_can( 'manage_options' ),
			)
		);
	}

	/**
	 * REST API callback to get Google Fonts list.
	 *
	 * @param \WP_REST_Request<array<string, mixed>>|null $request Optional REST request object.
	 * @return \WP_REST_Response REST response with fonts data.
	 */
	public function get_google_fonts( ?\WP_REST_Request $request = null ): \WP_REST_Response {
		$google_fonts_api_key = apply_filters( 'dwt_google_fonts_api_key', '' );

		// Get search parameters.
		$search     = $request ? $request->get_param( 'search' ) : '';
		$category   = $request ? $request->get_param( 'category' ) : '';
		$sort       = $request ? $request->get_param( 'sort' ) : 'popularity';
		$subset     = $request ? $request->get_param( 'subset' ) : '';
		$capability = $request ? $request->get_param( 'capability' ) : '';

		// If no API key, return filtered popular fonts.
		if ( empty( $google_fonts_api_key ) ) {
			$fonts = $this->get_popular_fonts();
			return new \WP_REST_Response( $this->filter_fonts_locally( $fonts, $search, $category ), 200 );
		}

		// Build cache key based on search parameters.
		$cache_params  = array(
			'search'     => $search,
			'category'   => $category,
			'sort'       => $sort,
			'subset'     => $subset,
			'capability' => $capability,
		);
		$transient_key = 'dwt_google_fonts_' . md5( wp_json_encode( $cache_params ) );
		$cached_fonts  = \get_transient( $transient_key );

		if ( false !== $cached_fonts ) {
			return new \WP_REST_Response( $cached_fonts, 200 );
		}

		// Build API URL with parameters.
		$api_url  = 'https://www.googleapis.com/webfonts/v1/webfonts?key=' . $google_fonts_api_key;
		$api_url .= '&sort=' . rawurlencode( $sort );

		if ( ! empty( $subset ) ) {
			$api_url .= '&subset=' . rawurlencode( $subset );
		}

		if ( ! empty( $capability ) ) {
			$api_url .= '&capability=' . rawurlencode( $capability );
		}

		$response = \wp_remote_get( $api_url, array( 'timeout' => 15 ) );

		if ( \is_wp_error( $response ) || \wp_remote_retrieve_response_code( $response ) !== 200 ) {
			$fonts = $this->get_popular_fonts();
			return new \WP_REST_Response( $this->filter_fonts_locally( $fonts, $search, $category ), 200 );
		}

		$body = \wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! isset( $data['items'] ) ) {
			$fonts = $this->get_popular_fonts();
			return new \WP_REST_Response( $this->filter_fonts_locally( $fonts, $search, $category ), 200 );
		}

		// Filter fonts based on search and category.
		$filtered_fonts = $this->filter_fonts_locally( $data['items'], $search, $category );

		// Cache for 1 hour (shorter cache for search results).
		\set_transient( $transient_key, $filtered_fonts, HOUR_IN_SECONDS );

		return new \WP_REST_Response( $filtered_fonts, 200 );
	}

	/**
	 * Filter fonts locally based on search term and category.
	 *
	 * @param array<int, array<string, mixed>> $fonts    Array of font data.
	 * @param string|null                      $search   Search term to filter by.
	 * @param string|null                      $category Category to filter by.
	 * @return array<int, array<string, mixed>> Filtered fonts array.
	 */
	private function filter_fonts_locally( array $fonts, ?string $search = null, ?string $category = null ): array {
		if ( empty( $search ) && empty( $category ) ) {
			return $fonts;
		}

		return array_filter(
			$fonts,
			function ( $font ) use ( $search, $category ) {
				$matches_search   = empty( $search ) || stripos( $font['family'], $search ) !== false;
				$matches_category = empty( $category ) || ( isset( $font['category'] ) && $font['category'] === $category );

				return $matches_search && $matches_category;
			}
		);
	}

	/**
	 * Get a list of popular Google Fonts as fallback.
	 *
	 * @return array<int, array<string, mixed>> List of popular fonts.
	 */
	private function get_popular_fonts(): array {
		return array(
			array(
				'family'   => 'Open Sans',
				'category' => 'sans-serif',
				'variants' => array( '300', '400', '600', '700', '800' ),
				'subsets'  => array( 'latin', 'latin-ext' ),
			),
			array(
				'family'   => 'Roboto',
				'category' => 'sans-serif',
				'variants' => array( '100', '300', '400', '500', '700', '900' ),
				'subsets'  => array( 'latin', 'latin-ext' ),
			),
			array(
				'family'   => 'Lato',
				'category' => 'sans-serif',
				'variants' => array( '100', '300', '400', '700', '900' ),
				'subsets'  => array( 'latin', 'latin-ext' ),
			),
			array(
				'family'   => 'Montserrat',
				'category' => 'sans-serif',
				'variants' => array( '100', '200', '300', '400', '500', '600', '700', '800', '900' ),
				'subsets'  => array( 'latin', 'latin-ext' ),
			),
			array(
				'family'   => 'Source Sans Pro',
				'category' => 'sans-serif',
				'variants' => array( '200', '300', '400', '600', '700', '900' ),
				'subsets'  => array( 'latin', 'latin-ext' ),
			),
			array(
				'family'   => 'Raleway',
				'category' => 'sans-serif',
				'variants' => array( '100', '200', '300', '400', '500', '600', '700', '800', '900' ),
				'subsets'  => array( 'latin', 'latin-ext' ),
			),
			array(
				'family'   => 'Poppins',
				'category' => 'sans-serif',
				'variants' => array( '100', '200', '300', '400', '500', '600', '700', '800', '900' ),
				'subsets'  => array( 'latin', 'latin-ext' ),
			),
			array(
				'family'   => 'Inter',
				'category' => 'sans-serif',
				'variants' => array( '100', '200', '300', '400', '500', '600', '700', '800', '900' ),
				'subsets'  => array( 'latin', 'latin-ext' ),
			),
			array(
				'family'   => 'Playfair Display',
				'category' => 'serif',
				'variants' => array( '400', '500', '600', '700', '800', '900' ),
				'subsets'  => array( 'latin', 'latin-ext' ),
			),
			array(
				'family'   => 'Merriweather',
				'category' => 'serif',
				'variants' => array( '300', '400', '700', '900' ),
				'subsets'  => array( 'latin', 'latin-ext' ),
			),
			array(
				'family'   => 'Dancing Script',
				'category' => 'handwriting',
				'variants' => array( '400', '500', '600', '700' ),
				'subsets'  => array( 'latin', 'latin-ext' ),
			),
			array(
				'family'   => 'Lobster',
				'category' => 'display',
				'variants' => array( '400' ),
				'subsets'  => array( 'latin', 'latin-ext' ),
			),
			array(
				'family'   => 'Fira Code',
				'category' => 'monospace',
				'variants' => array( '300', '400', '500', '600', '700' ),
				'subsets'  => array( 'latin', 'latin-ext' ),
			),
			array(
				'family'   => 'Nunito',
				'category' => 'sans-serif',
				'variants' => array( '200', '300', '400', '500', '600', '700', '800', '900' ),
				'subsets'  => array( 'latin', 'latin-ext' ),
			),
			array(
				'family'   => 'Work Sans',
				'category' => 'sans-serif',
				'variants' => array( '100', '200', '300', '400', '500', '600', '700', '800', '900' ),
				'subsets'  => array( 'latin', 'latin-ext' ),
			),
		);
	}

	/**
	 * REST API callback to get locally downloaded fonts.
	 *
	 * Validates file existence and automatically syncs database with filesystem state.
	 */
	public function get_local_fonts(): \WP_REST_Response {
		$downloaded_fonts = (array) \get_option( self::OPTION_NAME, array() );
		$all_font_files   = $this->storage->get_all_font_files();

		$local_fonts     = array();
		$fonts_to_remove = array(); // Track fonts that should be removed from database.

		foreach ( $downloaded_fonts as $font_family ) {
			// Find files matching this font family.
			$sanitized_family = strtolower( str_replace( ' ', '-', $font_family ) );
			$font_files       = array_filter(
				$all_font_files,
				function ( $filename ) use ( $sanitized_family ) {
					return stripos( $filename, $sanitized_family ) !== false;
				}
			);

			// Determine status based on file existence.
			$status = 'downloaded';
			if ( empty( $font_files ) ) {
				$status            = 'missing_all_files';
				$fonts_to_remove[] = $font_family;
			}

			$local_fonts[] = array(
				'family'     => $font_family,
				'status'     => $status,
				'path'       => $this->storage->get_font_dir_path(),
				'font_files' => array_values( $font_files ),
				'file_count' => count( $font_files ),
			);
		}

		// Auto-sync: Remove fonts from database that are completely missing.
		if ( ! empty( $fonts_to_remove ) ) {
			$updated_fonts = array_diff( $downloaded_fonts, $fonts_to_remove );
			$updated_fonts = array_values( $updated_fonts ); // Re-index array.
			\update_option( self::OPTION_NAME, $updated_fonts );

			Logger::warning(
				'Auto-synced database: removed orphaned font entries',
				array(
					'removed_fonts' => $fonts_to_remove,
					'count'         => count( $fonts_to_remove ),
				)
			);

			// Filter out removed fonts from response.
			$local_fonts = array_filter(
				$local_fonts,
				function ( $font ) use ( $fonts_to_remove ) {
					return ! in_array( $font['family'], $fonts_to_remove, true );
				}
			);
			$local_fonts = array_values( $local_fonts ); // Re-index array.
		}

		Logger::info(
			'Retrieved local fonts',
			array(
				'count'          => count( $local_fonts ),
				'synced_removed' => count( $fonts_to_remove ),
			)
		);

		return new \WP_REST_Response( $local_fonts, 200 );
	}

	/**
	 * REST API callback to download a font.
	 *
	 * Supports two methods:
	 * 1. Legacy: font_url (Google Fonts URL)
	 * 2. New: font_css (Fontsource CSS content)
	 *
	 * @param \WP_REST_Request<array<string, mixed>> $request REST request object.
	 * @return \WP_REST_Response REST response.
	 */
	public function download_font_api( \WP_REST_Request $request ): \WP_REST_Response {
		// Verify REST API nonce for additional security.
		if ( ! $this->verify_rest_nonce( $request ) ) {
			Notices::add_error( \__( 'Security check failed. Please refresh and try again.', 'dwt-local-fonts' ) );
			return new \WP_REST_Response( array( 'error' => 'Invalid nonce' ), 403 );
		}

		// Check rate limiting.
		$can_download = apply_filters( 'dwt_local_fonts_can_download', true );
		if ( ! $can_download ) {
			Notices::add_error( \__( 'Rate limit exceeded. Please try again later.', 'dwt-local-fonts' ) );
			return new \WP_REST_Response( array( 'error' => 'Rate limit exceeded' ), 429 );
		}

		$font_url  = $request->get_param( 'font_url' );
		$font_css  = $request->get_param( 'font_css' );
		$font_name = $request->get_param( 'font_name' );

		// Validate that either font_url or font_css is provided.
		if ( empty( $font_url ) && empty( $font_css ) ) {
			Notices::add_error( \__( 'Font URL or CSS content is required.', 'dwt-local-fonts' ) );
			return new \WP_REST_Response( array( 'error' => 'Font URL or CSS is required' ), 400 );
		}

		// Use downloader service directly for better control.
		if ( ! empty( $font_css ) ) {
			// New method: Download from CSS content (Fontsource).
			$result = $this->downloader->download_font_from_css( $font_css );
		} else {
			// Legacy method: Download from URL (Google Fonts).
			$result = $this->downloader->download_font( $font_url );
		}

		if ( ! $result['success'] ) {
			Notices::add_error( \__( 'Font download failed. Please try again.', 'dwt-local-fonts' ) );
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => $result['message'],
				),
				400
			);
		}

		// Update the option in the database with the list of downloaded font families.
		$existing_fonts = (array) \get_option( self::OPTION_NAME, array() );
		$new_fonts      = \array_unique( \array_merge( $existing_fonts, $result['families'] ) );
		\update_option( self::OPTION_NAME, $new_fonts );

		$message = $font_name
			? sprintf(
				/* translators: %s: font name */
				\__( 'Font "%s" downloaded successfully.', 'dwt-local-fonts' ),
				$font_name
			)
			: \__( 'Font downloaded successfully.', 'dwt-local-fonts' );

		Notices::add_success( $message );

		return new \WP_REST_Response(
			array(
				'success'          => true,
				'message'          => $message,
				'downloaded_fonts' => $new_fonts,
				'css'              => $result['css'],
				'families'         => $result['families'],
			),
			200
		);
	}

	/**
	 * REST API callback to delete a downloaded font.
	 *
	 * @param \WP_REST_Request<array<string, mixed>> $request REST request object.
	 * @return \WP_REST_Response REST response.
	 */
	public function delete_font_api( \WP_REST_Request $request ): \WP_REST_Response {
		// Verify REST API nonce for additional security.
		if ( ! $this->verify_rest_nonce( $request ) ) {
			Notices::add_error( \__( 'Security check failed. Please refresh and try again.', 'dwt-local-fonts' ) );
			return new \WP_REST_Response( array( 'error' => 'Invalid nonce' ), 403 );
		}

		$font_family = $request->get_param( 'font_family' );

		if ( empty( $font_family ) ) {
			Notices::add_error( \__( 'Font family is required for deletion.', 'dwt-local-fonts' ) );
			return new \WP_REST_Response( array( 'error' => 'Font family is required' ), 400 );
		}

		$downloaded_fonts = (array) \get_option( self::OPTION_NAME, array() );

		// Check if font exists.
		if ( ! in_array( $font_family, $downloaded_fonts, true ) ) {
			Notices::add_error(
				sprintf(
					/* translators: %s: font family name */
					\__( 'Font "%s" not found.', 'dwt-local-fonts' ),
					$font_family
				)
			);
			return new \WP_REST_Response( array( 'error' => 'Font not found' ), 404 );
		}

		// Get all font files and filter by font family name pattern.
		$all_files       = $this->storage->get_all_font_files();
		$files_to_delete = array_filter(
			$all_files,
			function ( $filename ) use ( $font_family ) {
				// Match files that contain the sanitized font family name.
				$sanitized_family = strtolower( str_replace( ' ', '-', $font_family ) );
				return stripos( $filename, $sanitized_family ) !== false;
			}
		);

		// Delete the matched font files.
		$deleted_files = $this->storage->delete_font_files( $files_to_delete );

		// Update font list.
		$updated_fonts = array_filter(
			$downloaded_fonts,
			function ( $font ) use ( $font_family ) {
				return $font !== $font_family;
			}
		);
		$updated_fonts = array_values( $updated_fonts ); // Re-index array.

		\update_option( self::OPTION_NAME, $updated_fonts );

		// Add success notice.
		Notices::add_success(
			sprintf(
				/* translators: 1: font family name, 2: number of files deleted */
				\__( 'Font "%1$s" deleted successfully. %2$d file(s) removed.', 'dwt-local-fonts' ),
				$font_family,
				$deleted_files
			)
		);

		return new \WP_REST_Response(
			array(
				'success'          => true,
				'message'          => sprintf( 'Font "%s" deleted successfully. %d file(s) removed.', $font_family, $deleted_files ),
				'downloaded_fonts' => $updated_fonts,
				'files_deleted'    => $deleted_files,
			),
			200
		);
	}

	/**
	 * REST API callback to batch download fonts.
	 *
	 * @param \WP_REST_Request<array<string, mixed>> $request REST request object.
	 * @return \WP_REST_Response REST response.
	 */
	public function batch_download_fonts_api( \WP_REST_Request $request ): \WP_REST_Response {
		// Verify REST API nonce for additional security.
		if ( ! $this->verify_rest_nonce( $request ) ) {
			Notices::add_error( \__( 'Security check failed. Please refresh and try again.', 'dwt-local-fonts' ) );
			return new \WP_REST_Response( array( 'error' => 'Invalid nonce' ), 403 );
		}

		$fonts = $request->get_param( 'fonts' );

		if ( empty( $fonts ) || ! is_array( $fonts ) ) {
			Notices::add_error( \__( 'Font list is required.', 'dwt-local-fonts' ) );
			return new \WP_REST_Response( array( 'error' => 'Fonts array is required' ), 400 );
		}

		$results       = array();
		$success_count = 0;
		$failed_count  = 0;

		foreach ( $fonts as $font_data ) {
			$font_url  = $font_data['url'] ?? '';
			$font_name = $font_data['name'] ?? 'Unknown';

			if ( empty( $font_url ) ) {
				$results[] = array(
					'name'    => $font_name,
					'success' => false,
					'message' => 'Missing font URL',
				);
				++$failed_count;
				continue;
			}

			// Download font.
			$result = $this->downloader->download_font( $font_url );

			if ( ! $result['success'] ) {
				$results[] = array(
					'name'    => $font_name,
					'success' => false,
					'message' => $result['message'],
				);
				++$failed_count;
				continue;
			}

			// Update the option in the database.
			$existing_fonts = (array) \get_option( self::OPTION_NAME, array() );
			$new_fonts      = \array_unique( \array_merge( $existing_fonts, $result['families'] ) );
			\update_option( self::OPTION_NAME, $new_fonts );

			$results[] = array(
				'name'    => $font_name,
				'success' => true,
				'message' => 'Downloaded successfully',
			);
			++$success_count;
		}

		// Add summary notice.
		if ( $success_count > 0 ) {
			Notices::add_success(
				sprintf(
					/* translators: 1: number of successful downloads, 2: total number of fonts */
					\__( 'Successfully downloaded %1$d of %2$d fonts.', 'dwt-local-fonts' ),
					$success_count,
					count( $fonts )
				)
			);
		}

		if ( $failed_count > 0 ) {
			Notices::add_warning(
				sprintf(
					/* translators: %d: number of failed downloads */
					\__( '%d font(s) failed to download or were already downloaded.', 'dwt-local-fonts' ),
					$failed_count
				)
			);
		}

		return new \WP_REST_Response(
			array(
				'success'          => $success_count > 0,
				'success_count'    => $success_count,
				'failed_count'     => $failed_count,
				'results'          => $results,
				'downloaded_fonts' => (array) \get_option( self::OPTION_NAME, array() ),
			),
			200
		);
	}

	/**
	 * REST API callback to batch delete fonts.
	 *
	 * @param \WP_REST_Request<array<string, mixed>> $request REST request object.
	 * @return \WP_REST_Response REST response.
	 */
	public function batch_delete_fonts_api( \WP_REST_Request $request ): \WP_REST_Response {
		// Verify REST API nonce for additional security.
		if ( ! $this->verify_rest_nonce( $request ) ) {
			Notices::add_error( \__( 'Security check failed. Please refresh and try again.', 'dwt-local-fonts' ) );
			return new \WP_REST_Response( array( 'error' => 'Invalid nonce' ), 403 );
		}

		$font_families = $request->get_param( 'font_families' );

		if ( empty( $font_families ) || ! is_array( $font_families ) ) {
			Notices::add_error( \__( 'Font families list is required.', 'dwt-local-fonts' ) );
			return new \WP_REST_Response( array( 'error' => 'Font families array is required' ), 400 );
		}

		$downloaded_fonts = (array) \get_option( self::OPTION_NAME, array() );

		$results             = array();
		$success_count       = 0;
		$failed_count        = 0;
		$total_files_deleted = 0;

		// Get all font files for finding family-specific files.
		$all_font_files = $this->storage->get_all_font_files();

		foreach ( $font_families as $font_family ) {
			if ( ! in_array( $font_family, $downloaded_fonts, true ) ) {
				$results[] = array(
					'family'  => $font_family,
					'success' => false,
					'message' => 'Font not found',
				);
				++$failed_count;
				continue;
			}

			// Find files matching this font family.
			$sanitized_family = strtolower( str_replace( ' ', '-', $font_family ) );
			$font_files       = array_filter(
				$all_font_files,
				function ( $filename ) use ( $sanitized_family ) {
					return stripos( $filename, $sanitized_family ) !== false;
				}
			);

			// Delete font files using storage service.
			$deleted_files = $this->storage->delete_font_files( array_values( $font_files ) );

			// Remove from list.
			$downloaded_fonts = array_filter(
				$downloaded_fonts,
				fn( $font ) => $font !== $font_family
			);

			$results[] = array(
				'family'        => $font_family,
				'success'       => true,
				'files_deleted' => $deleted_files,
				'message'       => 'Deleted successfully',
			);

			++$success_count;
			$total_files_deleted += $deleted_files;
		}

		// Update font list.
		$downloaded_fonts = array_values( $downloaded_fonts ); // Re-index array.
		\update_option( self::OPTION_NAME, $downloaded_fonts );

		// Add summary notice.
		if ( $success_count > 0 ) {
			Notices::add_success(
				sprintf(
					/* translators: 1: number of deleted fonts, 2: number of deleted files */
					\__( 'Successfully deleted %1$d font(s). %2$d file(s) removed.', 'dwt-local-fonts' ),
					$success_count,
					$total_files_deleted
				)
			);
		}

		if ( $failed_count > 0 ) {
			Notices::add_warning(
				sprintf(
					/* translators: %d: number of fonts that failed to delete */
					\__( '%d font(s) failed to delete or were not found.', 'dwt-local-fonts' ),
					$failed_count
				)
			);
		}

		return new \WP_REST_Response(
			array(
				'success'             => $success_count > 0,
				'success_count'       => $success_count,
				'failed_count'        => $failed_count,
				'total_files_deleted' => $total_files_deleted,
				'results'             => $results,
				'downloaded_fonts'    => $downloaded_fonts,
			),
			200
		);
	}

	/**
	 * REST API callback to discover fonts from the directory.
	 *
	 * @param \WP_REST_Request<array<string, mixed>> $request REST request object.
	 * @return \WP_REST_Response REST response.
	 */
	public function discover_fonts_api( \WP_REST_Request $request ): \WP_REST_Response {
		// Verify REST API nonce for additional security.
		if ( ! $this->verify_rest_nonce( $request ) ) {
			Notices::add_error( \__( 'Security check failed. Please refresh and try again.', 'dwt-local-fonts' ) );
			return new \WP_REST_Response( array( 'error' => 'Invalid nonce' ), 403 );
		}

		$font_discovery   = new \DWT\LocalFonts\Services\FontDiscovery( $this->storage );
		$discovered_fonts = $font_discovery->discover_fonts();

		if ( empty( $discovered_fonts ) ) {
			Notices::add_info( \__( 'No fonts found in the directory.', 'dwt-local-fonts' ) );
			return new \WP_REST_Response(
				array(
					'success'          => true,
					'discovered_fonts' => array(),
					'added_fonts'      => array(),
					'message'          => 'No fonts found in the directory.',
				),
				200
			);
		}

		// Merge with existing fonts.
		$existing_fonts = (array) \get_option( self::OPTION_NAME, array() );
		$new_fonts      = array_diff( $discovered_fonts, $existing_fonts );
		$merged_fonts   = \array_unique( \array_merge( $existing_fonts, $discovered_fonts ) );

		\update_option( self::OPTION_NAME, $merged_fonts );

		$message = sprintf(
			/* translators: 1: number of discovered fonts, 2: number of new fonts */
			\__( 'Discovered %1$d font(s), added %2$d new font(s).', 'dwt-local-fonts' ),
			count( $discovered_fonts ),
			count( $new_fonts )
		);

		Notices::add_success( $message );

		return new \WP_REST_Response(
			array(
				'success'          => true,
				'discovered_fonts' => $discovered_fonts,
				'added_fonts'      => array_values( $new_fonts ),
				'total_fonts'      => count( $merged_fonts ),
				'message'          => $message,
			),
			200
		);
	}
}
