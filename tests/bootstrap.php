<?php
/**
 * PHPUnit bootstrap file for DWT Management for WP tests.
 *
 * @package DWT\CoreTweaks
 */

declare(strict_types=1);

// Define plugin directory.
define( 'DWT_PLUGIN_DIR', dirname( __DIR__ ) );

// Load Composer autoloader.
require_once DWT_PLUGIN_DIR . '/vendor/autoload.php';

// Determine if we're running integration tests or unit tests.
$is_integration_test = getenv( 'WP_TESTS_DIR' ) !== false || ( isset( $GLOBALS['argv'] ) && in_array( 'integration', $GLOBALS['argv'], true ) );

// Load WordPress test suite for integration tests.
if ( $is_integration_test ) {
	// Check multiple possible locations for WordPress test suite.
	$_tests_dir = getenv( 'WP_TESTS_DIR' );

	if ( ! $_tests_dir ) {
		// Check wp-env location (inside Docker container).
		$_possible_locations = array(
			'/wordpress-phpunit/includes/functions.php',
			'/tmp/wordpress-tests-lib/includes/functions.php',
			'/var/www/html/wp-content/plugins/dwt-localfonts/wordpress-tests-lib/includes/functions.php',
		);

		foreach ( $_possible_locations as $_location ) {
			if ( file_exists( $_location ) ) {
				$_tests_dir = dirname( dirname( $_location ) );
				break;
			}
		}
	}

	if ( ! $_tests_dir || ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output for test bootstrap.
		echo "Could not find WordPress test suite.\n";
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output for test bootstrap.
		echo "Tried:\n";
		if ( getenv( 'WP_TESTS_DIR' ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output for test bootstrap.
			echo '  - ' . getenv( 'WP_TESTS_DIR' ) . "/includes/functions.php\n";
		}
		foreach ( $_possible_locations ?? array() as $_location ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output for test bootstrap.
			echo "  - {$_location}\n";
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output for test bootstrap.
		echo "\nPlease run: npm run wp-env:start\n";
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output for test bootstrap.
		echo "Then run integration tests with: composer test:integration\n";
		exit( 1 );
	}

	// Load PHPUnit Polyfills for WordPress test suite.
	require_once DWT_PLUGIN_DIR . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';

	// Load WordPress test suite.
	require_once "{$_tests_dir}/includes/functions.php";

	/**
	 * Manually load the plugin for tests.
	 */
	function _manually_load_plugin(): void {
		require DWT_PLUGIN_DIR . '/dwt-localfonts.php';
	}
	tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

	// Start up the WP testing environment.
	require "{$_tests_dir}/includes/bootstrap.php";
}

// Note: Brain Monkey initialization is handled in each test's setUp() method.
// DO NOT call Brain\Monkey\setUp() here as it causes test pollution issues.

// Define a global error_log mock to suppress module loading errors during tests.
if ( ! function_exists( 'error_log' ) ) {
	/**
	 * Mock error_log function for tests.
	 * Suppresses error messages to keep test output clean.
	 *
	 * @param string      $message            Error message.
	 * @param int         $message_type       Message type.
	 * @param string|null $destination        Destination.
	 * @param string|null $additional_headers Additional headers.
	 * @return bool Always returns true.
	 *
	 * phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
	 */
	function error_log( string $message, int $message_type = 0, ?string $destination = null, ?string $additional_headers = null ): bool {
		// Silently ignore error_log calls during tests.
		return true;
	}
	// phpcs:enable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
}

// Define printf mock to suppress module loading messages.
if ( ! function_exists( 'printf_suppress' ) ) {
	/**
	 * Capture and suppress printf output during tests.
	 */
	function printf_suppress(): void {
		// Intentionally empty to suppress output.
	}
}

// Define common WordPress functions for unit tests.
if ( ! function_exists( 'get_current_user_id' ) ) {
	/**
	 * Mock get_current_user_id function for tests.
	 *
	 * @return int User ID (always 0 in tests).
	 */
	function get_current_user_id(): int {
		return 0;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Mock sanitize_text_field function for tests.
	 *
	 * @param string $str String to sanitize.
	 * @return string Sanitized string.
	 */
	function sanitize_text_field( string $str ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Mock function for tests.
		return trim( strip_tags( $str ) );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	/**
	 * Mock wp_unslash function for tests.
	 *
	 * @param string|array $value Value to unslash.
	 * @return string|array Unslashed value.
	 */
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

// Note: get_option() and update_option() are not defined here because Brain\Monkey
// needs to be able to redefine them in unit tests. They are stubbed in each test's setUp().
// Similarly, __(), esc_html__(), and wp_json_encode() must not be defined here to allow Brain\Monkey to intercept them.

// Define WordPress constants that are expected by the plugin.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}

if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
}

if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
	define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );
}

if ( ! defined( 'WPINC' ) ) {
	define( 'WPINC', 'wp-includes' );
}

// Define plugin constants.
if ( ! defined( 'DWT_MANAGEMENT_VERSION' ) ) {
	define( 'DWT_MANAGEMENT_VERSION', '1.1.0' );
}

if ( ! defined( 'DWT_MANAGEMENT_PLUGIN_FILE' ) ) {
	define( 'DWT_MANAGEMENT_PLUGIN_FILE', DWT_PLUGIN_DIR . '/dwt-management-for-wp.php' );
}

// Define common WordPress constants.
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! defined( 'WEEK_IN_SECONDS' ) ) {
	define( 'WEEK_IN_SECONDS', 604800 );
}

if ( ! defined( 'MONTH_IN_SECONDS' ) ) {
	define( 'MONTH_IN_SECONDS', 2592000 );
}

if ( ! defined( 'YEAR_IN_SECONDS' ) ) {
	define( 'YEAR_IN_SECONDS', 31536000 );
}

// Create mock WordPress classes if they don't exist.
// phpcs:disable Universal.Files.SeparateFunctionsFromOO.Mixed, Generic.Files.OneObjectStructurePerFile.MultipleFound
if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Mock WP_Error class for testing.
	 */
	class WP_Error {
		/**
		 * Error code.
		 *
		 * @var string
		 */
		private string $code;

		/**
		 * Error message.
		 *
		 * @var string
		 */
		private string $message;

		/**
		 * Constructor.
		 *
		 * @param string $code    Error code.
		 * @param string $message Error message.
		 */
		public function __construct( string $code, string $message ) {
			$this->code    = $code;
			$this->message = $message;
		}

		/**
		 * Get error code.
		 *
		 * @return string Error code.
		 */
		public function get_error_code(): string {
			return $this->code;
		}

		/**
		 * Get error message.
		 *
		 * @return string Error message.
		 */
		public function get_error_message(): string {
			return $this->message;
		}
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	/**
	 * Mock WP_REST_Response class for testing.
	 */
	class WP_REST_Response {
		/**
		 * Response data.
		 *
		 * @var mixed
		 */
		private $data;

		/**
		 * HTTP status code.
		 *
		 * @var int
		 */
		private int $status;

		/**
		 * Constructor.
		 *
		 * @param mixed $data   Response data.
		 * @param int   $status HTTP status code.
		 */
		public function __construct( $data = null, int $status = 200 ) {
			$this->data   = $data;
			$this->status = $status;
		}

		/**
		 * Get response data.
		 *
		 * @return mixed Response data.
		 */
		public function get_data() {
			return $this->data;
		}

		/**
		 * Get HTTP status code.
		 *
		 * @return int HTTP status code.
		 */
		public function get_status(): int {
			return $this->status;
		}
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	/**
	 * Mock WP_REST_Request class for testing.
	 */
	class WP_REST_Request {
		/**
		 * Request parameters.
		 *
		 * @var array
		 */
		private array $params = array();

		/**
		 * Get a request parameter.
		 *
		 * @param string $key Parameter name.
		 * @return mixed Parameter value or null.
		 */
		public function get_param( string $key ) {
			return $this->params[ $key ] ?? null;
		}

		/**
		 * Get all JSON parameters.
		 *
		 * @return array JSON parameters.
		 */
		public function get_json_params(): array {
			return $this->params;
		}
	}
}
