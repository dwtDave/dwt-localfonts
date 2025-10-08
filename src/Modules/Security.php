<?php
/**
 * Security Hardening Module
 *
 * @package DWT\LocalFonts
 */

declare(strict_types=1);

namespace DWT\LocalFonts\Modules;

/**
 * Handles security headers and hardening features.
 */
final class Security {

	/**
	 * Rate limiting transient prefix.
	 *
	 * @var string
	 */
	private const RATE_LIMIT_PREFIX = 'dwt_rate_limit_';

	/**
	 * Constructor - Initializes security hooks.
	 */
	public function __construct() {
		// Add security headers.
		\add_action( 'send_headers', array( $this, 'add_security_headers' ) );

		// Add CSP for admin pages.
		\add_action( 'admin_head', array( $this, 'add_admin_csp' ) );

		// Rate limiting for font downloads.
		\add_filter( 'dwt_local_fonts_can_download', array( $this, 'check_download_rate_limit' ), 10, 1 );
	}

	/**
	 * Add security headers to all plugin responses.
	 */
	public function add_security_headers(): void {
		// Only add headers for plugin admin pages.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Just checking page context.
		$page = isset( $_GET['page'] ) ? \sanitize_text_field( \wp_unslash( $_GET['page'] ) ) : '';
		if ( 'dwt-local-fonts' !== $page ) {
			return;
		}

		// Prevent MIME type sniffing.
		header( 'X-Content-Type-Options: nosniff' );

		// Enable XSS protection.
		header( 'X-XSS-Protection: 1; mode=block' );

		// Prevent clickjacking.
		header( 'X-Frame-Options: SAMEORIGIN' );

		// Referrer policy.
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );

		// Permissions policy - restrict access to sensitive features.
		header( 'Permissions-Policy: geolocation=(), microphone=(), camera=()' );
	}

	/**
	 * Add Content Security Policy for admin pages.
	 *
	 * @return void
	 */
	public function add_admin_csp(): void {
		$screen = \get_current_screen();
		if ( ! $screen || 'settings_page_dwt-local-fonts' !== $screen->id ) {
			return;
		}

		// Build CSP policy.
		$csp_directives = array(
			"default-src 'self'",
			"script-src 'self' 'unsafe-inline' 'unsafe-eval' http://localhost:5173", // unsafe-eval needed for React dev.
			"style-src 'self' 'unsafe-inline' http://localhost:5173",
			"img-src 'self' data:",
			"font-src 'self' data:",
			"connect-src 'self' http://localhost:5173 ws://localhost:5173 https://fonts.googleapis.com https://fonts.gstatic.com https://fonts.bunny.net https://raw.githubusercontent.com",
			"frame-ancestors 'self'",
			"base-uri 'self'",
			"form-action 'self'",
		);

		$csp = implode( '; ', $csp_directives );

		// Output as meta tag (works better in WP admin than header).
		echo '<meta http-equiv="Content-Security-Policy" content="' . \esc_attr( $csp ) . '">' . "\n";
	}

	/**
	 * Check rate limiting for font downloads.
	 *
	 * Limits downloads to 20 per user per hour to prevent abuse.
	 *
	 * @param bool $can_download Whether download is allowed.
	 * @return bool Whether download is allowed after rate limit check.
	 */
	public function check_download_rate_limit( bool $can_download ): bool {
		if ( ! $can_download ) {
			return false;
		}

		$user_id = \get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}

		$transient_key = self::RATE_LIMIT_PREFIX . $user_id;
		$downloads     = (int) \get_transient( $transient_key );

		// Allow 20 downloads per hour per user.
		if ( $downloads >= 20 ) {
			return false;
		}

		// Increment counter.
		\set_transient( $transient_key, $downloads + 1, HOUR_IN_SECONDS );

		return true;
	}

	/**
	 * Validate and sanitize file paths to prevent directory traversal.
	 *
	 * Ensures that a file path is within the allowed base directory by:
	 * 1. Resolving both paths to their real absolute paths (resolves symlinks)
	 * 2. Verifying the file path starts with the base path
	 * 3. Ensuring proper directory boundary (not just string prefix match)
	 *
	 * @param string $path Path to validate (must exist).
	 * @param string $base_path Base path that file must be within (must exist).
	 * @return string|false Sanitized real path or false if invalid.
	 */
	public static function validate_file_path( string $path, string $base_path ): string|false {
		// Resolve to real paths (resolves symlinks and relative paths).
		$real_path = realpath( $path );
		$real_base = realpath( $base_path );

		// Both paths must exist and be resolvable.
		if ( false === $real_path || false === $real_base ) {
			return false;
		}

		// Normalize paths with trailing slash for proper boundary checking.
		$real_base_normalized = trailingslashit( $real_base );
		$real_path_normalized = trailingslashit( $real_path );

		// Ensure path is within base directory.
		// For files, check if the file itself or its directory is within base.
		if ( is_file( $real_path ) ) {
			// For files, check if the file path starts with base path.
			if ( 0 !== strpos( $real_path, $real_base_normalized ) && rtrim( $real_base, '/' ) !== $real_path ) {
				return false;
			}
		} elseif ( 0 !== strpos( $real_path_normalized, $real_base_normalized ) && rtrim( $real_base, '/' ) !== $real_path ) {
			// For directories, check with normalized paths.
			return false;
		}

		return $real_path;
	}

	/**
	 * Generate cryptographically secure random string.
	 *
	 * @param int $length Length of random string.
	 * @return string Random string.
	 */
	public static function generate_secure_token( int $length = 32 ): string {
		return bin2hex( random_bytes( $length / 2 ) );
	}
}
