<?php
/**
 * Font Manager Module
 *
 * @package DWT\LocalFonts
 */

declare(strict_types=1);

namespace DWT\LocalFonts\Modules;

use DWT\LocalFonts\Controllers\FontRestController;
use DWT\LocalFonts\Logger;
use DWT\LocalFonts\Services\FontDownloader;
use DWT\LocalFonts\Services\FontStorage;
use DWT\LocalFonts\Services\FontValidator;

/**
 * Handles downloading Google Fonts to be served locally.
 */
class FontManager {

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
	 * Constructor - Initializes hooks and filters.
	 *
	 * @param FontValidator|null  $validator  Optional validator instance.
	 * @param FontStorage|null    $storage    Optional storage instance.
	 * @param FontDownloader|null $downloader Optional downloader instance.
	 */
	public function __construct( ?FontValidator $validator = null, ?FontStorage $storage = null, ?FontDownloader $downloader = null ) {
		$this->validator  = $validator ?? new FontValidator();
		$this->storage    = $storage ?? new FontStorage( $this->validator );
		$this->downloader = $downloader ?? new FontDownloader( $this->validator, $this->storage );

		// Initialize REST controller (instantiation registers hooks).
		new FontRestController( $this->validator, $this->storage, $this->downloader );

		// Handle the font download form submission.
		if ( isset( $_POST['dwt_download_font'], $_POST['dwt_font_nonce'] ) && \wp_verify_nonce( \sanitize_text_field( \wp_unslash( $_POST['dwt_font_nonce'] ) ), 'dwt_download_font' ) && \current_user_can( 'manage_options' ) ) {
			$this->handle_font_download();
		}
	}

	/**
	 * Processes the font download request from the settings page.
	 *
	 * @param string|null $font_url_override Optional font URL to override POST data.
	 */
	private function handle_font_download( ?string $font_url_override = null ): void {
		// Get font URL from override or POST data.
		if ( null !== $font_url_override ) {
			$font_url = $font_url_override;
		} else {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified in constructor.
			if ( empty( $_POST['google_font_url'] ) ) {
				Logger::warning( 'Font download attempted without URL' );
				return;
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified in constructor.
			$font_url = \esc_url_raw( \wp_unslash( $_POST['google_font_url'] ) );
		}

		// Download font using the downloader service.
		$result = $this->downloader->download_font( $font_url );

		if ( ! $result['success'] ) {
			Notices::add_error( \__( 'Font download failed. Please try again.', 'dwt-local-fonts' ) );
			return;
		}

		// Update the option in the database with the list of downloaded font families.
		$existing_fonts = (array) \get_option( self::OPTION_NAME, array() );
		$new_fonts      = \array_unique( \array_merge( $existing_fonts, $result['families'] ) );
		\update_option( self::OPTION_NAME, $new_fonts );

		// Add success notice.
		if ( ! empty( $result['families'] ) ) {
			$font_list = implode( ', ', $result['families'] );
			Notices::add_success(
				sprintf(
					/* translators: %s: comma-separated list of font families */
					\__( 'Successfully downloaded: %s', 'dwt-local-fonts' ),
					$font_list
				)
			);
		}
	}
}
