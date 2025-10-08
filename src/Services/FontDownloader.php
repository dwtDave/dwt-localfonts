<?php
/**
 * Font Downloader Service
 *
 * @package DWT\LocalFonts
 */

declare(strict_types=1);

namespace DWT\LocalFonts\Services;

use DWT\LocalFonts\Logger;

/**
 * Handles downloading fonts from remote sources.
 *
 * Note: Not marked as final to allow mocking in unit tests.
 */
class FontDownloader {

	/**
	 * Maximum file size for font downloads (2MB).
	 *
	 * @var int
	 */
	private const MAX_FILE_SIZE = 2097152; // 2MB in bytes.

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
	 * Constructor.
	 *
	 * @param FontValidator|null $validator Optional validator instance.
	 * @param FontStorage|null   $storage   Optional storage instance.
	 */
	public function __construct( ?FontValidator $validator = null, ?FontStorage $storage = null ) {
		$this->validator = $validator ?? new FontValidator();
		$this->storage   = $storage ?? new FontStorage( $this->validator );
	}

	/**
	 * Download font from URL.
	 *
	 * @param string $font_url Font CSS URL from Google Fonts or Fontsource.
	 * @return array{success: bool, families: array<string>, css: string, message: string} Download result.
	 */
	public function download_font( string $font_url ): array {
		// Validate URL.
		if ( ! $this->validator->is_valid_font_url( $font_url ) ) {
			Logger::warning( 'Invalid font URL', array( 'url' => $font_url ) );
			return array(
				'success'  => false,
				'families' => array(),
				'css'      => '',
				'message'  => 'Invalid font URL',
			);
		}

		// Fetch CSS from remote URL.
		$css_content = $this->fetch_font_css( $font_url );
		if ( false === $css_content ) {
			Logger::error( 'Failed to fetch font CSS', array( 'url' => $font_url ) );
			return array(
				'success'  => false,
				'families' => array(),
				'css'      => '',
				'message'  => 'Failed to fetch font CSS',
			);
		}

		return $this->process_font_css( $css_content );
	}

	/**
	 * Download font from CSS content directly.
	 *
	 * This method is used when the CSS is generated client-side (e.g., Fontsource).
	 *
	 * @param string $css_content Font CSS content with @font-face rules.
	 * @return array{success: bool, families: array<string>, css: string, message: string} Download result.
	 */
	public function download_font_from_css( string $css_content ): array {
		if ( empty( $css_content ) ) {
			Logger::warning( 'Empty CSS content provided' );
			return array(
				'success'  => false,
				'families' => array(),
				'css'      => '',
				'message'  => 'CSS content is required',
			);
		}

		return $this->process_font_css( $css_content );
	}

	/**
	 * Process font CSS and download font files.
	 *
	 * @param string $css_content Font CSS content.
	 * @return array{success: bool, families: array<string>, css: string, message: string} Download result.
	 */
	private function process_font_css( string $css_content ): array {
		// Parse font URLs and families from CSS.
		$parsed_data = $this->parse_font_css( $css_content );
		if ( empty( $parsed_data['font_urls'] ) ) {
			Logger::warning( 'No font files found in CSS' );
			return array(
				'success'  => false,
				'families' => array(),
				'css'      => '',
				'message'  => 'No font files found',
			);
		}

		// Download each font file and replace URLs.
		$downloaded_count = 0;
		foreach ( $parsed_data['font_urls'] as $remote_font_url ) {
			$filename = basename( $remote_font_url );

			// Always replace remote URL with local URL in CSS (even if file exists).
			$local_url   = $this->storage->get_font_dir_url() . '/' . $filename;
			$css_content = \str_replace( $remote_font_url, $local_url, $css_content );

			// Skip download if file already exists.
			if ( $this->storage->font_file_exists( $filename ) ) {
				Logger::info( 'Font file already exists, skipping download', array( 'filename' => $filename ) );
				continue;
			}

			// Download font file.
			$font_content = $this->download_font_file( $remote_font_url );
			if ( false === $font_content ) {
				Logger::warning( 'Failed to download font file', array( 'url' => $remote_font_url ) );
				continue;
			}

			// Validate font content.
			if ( ! $this->validator->is_valid_font_content( $font_content ) ) {
				Logger::warning( 'Invalid font content', array( 'url' => $remote_font_url ) );
				continue;
			}

			// Save font file.
			if ( $this->storage->save_font_file( $filename, $font_content ) ) {
				++$downloaded_count;
			}
		}

		Logger::info(
			'Font download completed',
			array(
				'families'         => $parsed_data['font_families'],
				'downloaded_files' => $downloaded_count,
			)
		);

		return array(
			'success'  => true,
			'families' => $parsed_data['font_families'],
			'css'      => $css_content,
			'message'  => sprintf( 'Successfully downloaded %d file(s)', $downloaded_count ),
		);
	}

	/**
	 * Fetch font CSS from remote URL.
	 *
	 * @param string $url Font CSS URL.
	 * @return string|false CSS content or false on failure.
	 */
	private function fetch_font_css( string $url ) {
		$response = \wp_remote_get(
			$url,
			array(
				'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/108.0.0.0 Safari/537.36',
				'timeout'    => 15,
			)
		);

		if ( \is_wp_error( $response ) ) {
			Logger::error( 'HTTP request failed', array( 'error' => $response->get_error_message() ) );
			return false;
		}

		if ( 200 !== \wp_remote_retrieve_response_code( $response ) ) {
			Logger::error( 'HTTP request returned non-200 status', array( 'status' => \wp_remote_retrieve_response_code( $response ) ) );
			return false;
		}

		return \wp_remote_retrieve_body( $response );
	}

	/**
	 * Parse font URLs and families from CSS content.
	 *
	 * @param string $css_content CSS content.
	 * @return array{font_urls: array<string>, font_families: array<string>} Parsed data.
	 */
	private function parse_font_css( string $css_content ): array {
		// Find all font URLs (Google Fonts, Bunny Fonts, etc.).
		// Pattern handles both quoted and unquoted URLs: url("..."), url('...'), or url(...).
		\preg_match_all( '/url\(["\']?(https:\/\/(?:fonts\.gstatic\.com|fonts\.bunny\.net)\/[^"\'\)]+)["\']?\)/', $css_content, $url_matches );
		$font_urls = $url_matches[1];

		// Find all font-family names.
		\preg_match_all( '/font-family: \'([^\']+)\';/', $css_content, $family_matches );
		$font_families = \array_unique( $family_matches[1] );

		return array(
			'font_urls'     => $font_urls,
			'font_families' => array_values( $font_families ),
		);
	}

	/**
	 * Download a single font file.
	 *
	 * @param string $url Font file URL.
	 * @return string|false Font file content or false on failure.
	 */
	private function download_font_file( string $url ) {
		$response = \wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Range' => 'bytes=0-' . self::MAX_FILE_SIZE,
				),
			)
		);

		if ( \is_wp_error( $response ) ) {
			Logger::error( 'Font file download failed', array( 'error' => $response->get_error_message() ) );
			return false;
		}

		$status_code = \wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code && 206 !== $status_code ) {
			Logger::warning(
				'Font file download returned unexpected status',
				array(
					'url'    => $url,
					'status' => $status_code,
				)
			);
			return false;
		}

		$content = \wp_remote_retrieve_body( $response );

		// Verify content size.
		if ( ! $content || strlen( $content ) > self::MAX_FILE_SIZE ) {
			Logger::warning( 'Font file exceeds maximum size', array( 'size' => strlen( $content ) ) );
			return false;
		}

		return $content;
	}
}
