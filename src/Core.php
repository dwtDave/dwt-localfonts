<?php
/**
 * Core Plugin File
 *
 * @package DWT\LocalFonts
 */

declare(strict_types=1);

namespace DWT\LocalFonts;

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use FilesystemIterator;
use GlobIterator;

/**
 * Main plugin class responsible for initializing the plugin and loading all modules.
 * Implements the Singleton pattern to ensure only one instance exists.
 */
final class Core {

	/**
	 * The single instance of the class.
	 *
	 * @var Core|null
	 */
	private static ?Core $instance = null;

	/**
	 * Retrieves the single instance of this class.
	 *
	 * @return Core
	 */
	public static function get_instance(): Core {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {
		$this->add_hooks();
		$this->initialize_modules();
	}

	/**
	 * Adds WordPress hooks for the plugin's core functionality.
	 */
	private function add_hooks(): void {
		// IMPROVEMENT: Removed unnecessary function_exists check.
		// IMPROVEMENT: Switched to modern short array syntax.
		\add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Loads the plugin's text domain for internationalization.
	 */
	public function load_textdomain(): void {
		// IMPROVEMENT: Removed unnecessary function_exists checks.
		\load_plugin_textdomain(
			'dwt-local-fonts',
			false,
			dirname( \plugin_basename( DWT_LOCAL_FONTS_PLUGIN_FILE ) ) . '/languages/'
		);
	}

	/**
	 * Automatically discovers and initializes all modules.
	 */
	private function initialize_modules(): void {
		$modules_path      = __DIR__ . '/Modules/';
		$modules_namespace = __NAMESPACE__ . '\\Modules\\';
		$module_files      = new GlobIterator( $modules_path . '*.php', FilesystemIterator::KEY_AS_FILENAME );

		foreach ( $module_files as $filename => $file_info ) {
			$class_name = basename( $filename, '.php' );
			$fqcn       = $modules_namespace . $class_name;

			try {
				if ( class_exists( $fqcn ) ) {
					new $fqcn();
				}
			} catch ( \Throwable $e ) {
				// Skip error logging during tests to keep output clean.
				if ( ! defined( 'PHPUNIT_RUNNING' ) || ! PHPUNIT_RUNNING ) {
					// IMPROVEMENT: Simplified error logging, as esc_html functions are always available.
					$error_message = sprintf(
						/* translators: 1: FQCN, 2: Error message */
						\esc_html__( 'Failed to load module %1$s: %2$s', 'dwt-local-fonts' ),
						"<code>{$fqcn}</code>",
						\esc_html( $e->getMessage() )
					);
					error_log( $error_message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				}
			}
		}
	}

	/**
	 * Cloning is forbidden.
	 */
	private function __clone() {
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 */
	public function __wakeup() {
	}
}
