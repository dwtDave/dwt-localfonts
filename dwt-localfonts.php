<?php
/**
 * Plugin Name:       Local Font Manager for WP
 * Plugin URI:        https://dwt.ie/
 * Description:       Manage and locally host Google Fonts for better performance and privacy compliance.
 * Version:           1.0.2
 * Author:            DWT
 * Author URI:        https://dwt.ie/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       dwt-local-fonts
 * Domain Path:       /languages
 * Requires at least: 6.4
 * Requires PHP:      8.2
 *
 * @package DWT\LocalFonts
 */

declare(strict_types=1);

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'DWT_LOCAL_FONTS_VERSION', '1.0.2' );
define( 'DWT_LOCAL_FONTS_PLUGIN_FILE', __FILE__ );

// Check for Composer's autoloader.
$autoloader = __DIR__ . '/vendor/autoload.php';
if ( ! file_exists( $autoloader ) ) {
	add_action(
		'admin_notices',
		function (): void {
			$message = esc_html__( 'Local Font Manager for WP plugin is missing its autoloader. Please run `composer install` in the plugin directory.', 'dwt-local-fonts' );
			echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
		}
	);
	return;
}

require_once $autoloader;

/**
 * Plugin activation hook.
 *
 * @return void
 */
function dwt_local_fonts_activate(): void {
	// Create the fonts directory.
	$upload_dir    = wp_upload_dir();
	$font_dir_path = $upload_dir['basedir'] . '/dwt-local-fonts';

	if ( ! file_exists( $font_dir_path ) ) {
		wp_mkdir_p( $font_dir_path );

		// Create an index.php file to prevent directory listing.
		$index_file = $font_dir_path . '/index.php';
		if ( ! file_exists( $index_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $index_file, "<?php\n// Silence is golden.\n" );
		}
	}

	// Initialize default options if they don't exist.
	if ( false === get_option( 'dwt_local_fonts_list' ) ) {
		add_option( 'dwt_local_fonts_list', array(), '', false );
	}

	if ( false === get_option( 'dwt_local_fonts_settings' ) ) {
		add_option(
			'dwt_local_fonts_settings',
			array(
				'font_display_swap' => '1',
			),
			'',
			false
		);
	}

	// Discover existing fonts from directory (useful after reinstall).
	if ( file_exists( $font_dir_path ) && is_dir( $font_dir_path ) ) {
		$font_discovery   = new \DWT\LocalFonts\Services\FontDiscovery();
		$discovered_fonts = $font_discovery->discover_fonts();

		if ( ! empty( $discovered_fonts ) ) {
			// Merge discovered fonts with existing list.
			$existing_fonts = (array) get_option( 'dwt_local_fonts_list', array() );
			$merged_fonts   = array_unique( array_merge( $existing_fonts, $discovered_fonts ) );
			update_option( 'dwt_local_fonts_list', $merged_fonts );
		}
	}

	// Set activation timestamp.
	update_option( 'dwt_local_fonts_activated', time(), false );
}
register_activation_hook( __FILE__, 'dwt_local_fonts_activate' );

/**
 * Plugin deactivation hook.
 *
 * @return void
 */
function dwt_local_fonts_deactivate(): void {
	// Clear all plugin transients.
	global $wpdb;

	// Delete all transients with our prefix.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_dwt_google_fonts_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_dwt_google_fonts_' ) . '%'
		)
	);

	// Clear object cache.
	wp_cache_flush();
}
register_deactivation_hook( __FILE__, 'dwt_local_fonts_deactivate' );

// Initialize GitHub update checker.
if ( class_exists( \YahnisElsts\PluginUpdateChecker\v5\PucFactory::class ) ) {
	/**
	 * Filter the GitHub repository URL for plugin updates.
	 *
	 * To enable automatic updates from GitHub, add this filter to your theme's functions.php
	 * or a custom plugin:
	 *
	 * add_filter( 'dwt_local_fonts_github_repo', function() {
	 *     return 'https://github.com/your-username/dwt-localfonts/';
	 * } );
	 *
	 * @since 1.0.0
	 * @param string $repo_url The GitHub repository URL. Empty by default.
	 */
	$github_repo = apply_filters( 'dwt_local_fonts_github_repo', '' );

	if ( ! empty( $github_repo ) ) {
		$update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			$github_repo,
			__FILE__,
			'dwt-localfonts'
		);

		// Set the branch that contains the stable release.
		$update_checker->setBranch( 'main' );

		/**
		 * Filter the GitHub authentication token for private repositories.
		 *
		 * @since 1.0.0
		 * @param string $token The GitHub personal access token. Empty by default.
		 */
		$github_token = apply_filters( 'dwt_local_fonts_github_token', '' );

		if ( ! empty( $github_token ) ) {
			$update_checker->setAuthentication( $github_token );
		}
	}
}

// Initialize the plugin.
\DWT\LocalFonts\Core::get_instance();
