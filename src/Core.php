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
		$this->initialize_github_updates();
	}

	/**
	 * Adds WordPress hooks for the plugin's core functionality.
	 */
	private function add_hooks(): void {
		// IMPROVEMENT: Removed unnecessary function_exists check.
		// IMPROVEMENT: Switched to modern short array syntax.
		\add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		\add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
		\add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Register settings page
	 *
	 * @return void
	 */
	public function register_settings_page(): void {
		try {
			$settingsPage = new Admin\UpdateSettingsPage();
			$settingsPage->registerMenu();
		} catch ( \Throwable $e ) {
			if ( ! defined( 'PHPUNIT_RUNNING' ) || ! PHPUNIT_RUNNING ) {
				error_log(
					sprintf(
						'Failed to register settings page: %s',
						\esc_html( $e->getMessage() )
					)
				);
			}
		}
	}

	/**
	 * Register settings
	 *
	 * @return void
	 */
	public function register_settings(): void {
		try {
			$settingsPage = new Admin\UpdateSettingsPage();
			$settingsPage->registerSettings();
		} catch ( \Throwable $e ) {
			if ( ! defined( 'PHPUNIT_RUNNING' ) || ! PHPUNIT_RUNNING ) {
				error_log(
					sprintf(
						'Failed to register settings: %s',
						\esc_html( $e->getMessage() )
					)
				);
			}
		}
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
	 * Initialize GitHub update integration
	 *
	 * Registers hooks for checking plugin updates from GitHub releases.
	 *
	 * @return void
	 */
	private function initialize_github_updates(): void {
		try {
			// Load update configuration from options
			$configData = \get_option( 'dwt_localfonts_github_config', [] );

			// Use default configuration if not set
			if ( empty( $configData ) ) {
				// Set default configuration
				$configData = [
					'repository_owner'    => 'your-github-username',
					'repository_name'     => 'dwt-localfonts',
					'plugin_slug'         => 'dwt-localfonts',
					'cache_lifetime'      => 43200, // 12 hours
					'update_channel'      => 'stable',
					'auto_update_enabled' => false,
				];
				\update_option( 'dwt_localfonts_github_config', $configData );
			}

			// Create UpdateConfiguration value object
			$config = new ValueObjects\UpdateConfiguration(
				repositoryOwner: $configData['repository_owner'],
				repositoryName: $configData['repository_name'],
				pluginSlug: $configData['plugin_slug'],
				cacheLifetime: $configData['cache_lifetime'],
				updateChannel: $configData['update_channel'],
				autoUpdateEnabled: $configData['auto_update_enabled']
			);

			// Initialize services
			$assetResolver  = new Services\AssetResolver();
			$logger         = new Services\UpdateLogger();
			$updateService  = new Services\GitHubUpdateService( $config, $assetResolver, $logger );
			$installer      = new Services\UpdateInstaller( $configData['plugin_slug'], $logger );
			$integration    = new Services\PluginUpdateIntegration(
				$updateService,
				\plugin_basename( DWT_LOCAL_FONTS_PLUGIN_FILE ),
				$installer,
				$configData['auto_update_enabled'] ?? false
			);

			// Register WordPress hooks
			$integration->registerHooks();

		} catch ( \Throwable $e ) {
			// Skip error logging during tests
			if ( ! defined( 'PHPUNIT_RUNNING' ) || ! PHPUNIT_RUNNING ) {
				error_log(
					sprintf(
						'Failed to initialize GitHub updates: %s',
						\esc_html( $e->getMessage() )
					)
				); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
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
