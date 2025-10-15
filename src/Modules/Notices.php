<?php
/**
 * Admin Notices Module
 *
 * @package DWT\LocalFonts
 */

declare(strict_types=1);

namespace DWT\LocalFonts\Modules;

/**
 * Handles admin notices for user feedback.
 */
final class Notices {

	private const OPTION_NAME = 'dwt_local_fonts_notices';

	/**
	 * Constructor - Initializes hooks.
	 */
	public function __construct() {
		\add_action( 'admin_notices', array( $this, 'display_notices' ) );
		\add_action( 'admin_init', array( $this, 'clear_old_notices' ) );
	}

	/**
	 * Add a success notice.
	 *
	 * @param string $message Notice message.
	 * @param bool   $dismissible Whether notice is dismissible.
	 */
	public static function add_success( string $message, bool $dismissible = true ): void {
		self::add_notice( $message, 'success', $dismissible );
	}

	/**
	 * Add an error notice.
	 *
	 * @param string $message Notice message.
	 * @param bool   $dismissible Whether notice is dismissible.
	 */
	public static function add_error( string $message, bool $dismissible = true ): void {
		self::add_notice( $message, 'error', $dismissible );
	}

	/**
	 * Add a warning notice.
	 *
	 * @param string $message Notice message.
	 * @param bool   $dismissible Whether notice is dismissible.
	 */
	public static function add_warning( string $message, bool $dismissible = true ): void {
		self::add_notice( $message, 'warning', $dismissible );
	}

	/**
	 * Add an info notice.
	 *
	 * @param string $message Notice message.
	 * @param bool   $dismissible Whether notice is dismissible.
	 */
	public static function add_info( string $message, bool $dismissible = true ): void {
		self::add_notice( $message, 'info', $dismissible );
	}

	/**
	 * Add a notice to the queue.
	 *
	 * @param string $message Notice message.
	 * @param string $type Notice type (success, error, warning, info).
	 * @param bool   $dismissible Whether notice is dismissible.
	 */
	private static function add_notice( string $message, string $type = 'info', bool $dismissible = true ): void {
		$notices = (array) \get_option( self::OPTION_NAME, array() );

		$notices[] = array(
			'message'     => $message,
			'type'        => $type,
			'dismissible' => $dismissible,
			'timestamp'   => time(),
		);

		\update_option( self::OPTION_NAME, $notices );
	}

	/**
	 * Display all queued notices.
	 */
	public function display_notices(): void {
		$notices = (array) \get_option( self::OPTION_NAME, array() );

		if ( empty( $notices ) ) {
			return;
		}

		foreach ( $notices as $notice ) {
			$this->render_notice( $notice );
		}

		// Clear notices after displaying.
		\delete_option( self::OPTION_NAME );
	}

	/**
	 * Render a single notice.
	 *
	 * @param array<string, mixed> $notice Notice data.
	 */
	private function render_notice( array $notice ): void {
		$type        = $notice['type'] ?? 'info';
		$message     = $notice['message'] ?? '';
		$dismissible = $notice['dismissible'] ?? true;

		if ( empty( $message ) ) {
			return;
		}

		$classes = array( 'notice', "notice-{$type}" );

		if ( $dismissible ) {
			$classes[] = 'is-dismissible';
		}

		printf(
			'<div class="%s"><p><strong>%s:</strong> %s</p></div>',
			esc_attr( implode( ' ', $classes ) ),
			esc_html__( 'Local Font Manager', 'dwt-local-fonts' ),
			esc_html( $message )
		);
	}

	/**
	 * Clear old notices (older than 1 hour).
	 * Prevents accumulation if notices aren't displayed.
	 */
	public function clear_old_notices(): void {
		$notices = (array) \get_option( self::OPTION_NAME, array() );

		if ( empty( $notices ) ) {
			return;
		}

		$current_time = time();
		$max_age      = HOUR_IN_SECONDS;

		$valid_notices = array_filter(
			$notices,
			function ( $notice ) use ( $current_time, $max_age ) {
				$timestamp = $notice['timestamp'] ?? 0;
				return ( $current_time - $timestamp ) < $max_age;
			}
		);

		if ( count( $valid_notices ) !== count( $notices ) ) {
			if ( empty( $valid_notices ) ) {
				\delete_option( self::OPTION_NAME );
			} else {
				\update_option( self::OPTION_NAME, array_values( $valid_notices ) );
			}
		}
	}
}
