<?php
/**
 * Abstract REST Controller
 *
 * @package DWT\LocalFonts
 */

declare(strict_types=1);

namespace DWT\LocalFonts\Abstracts;

use DWT\LocalFonts\Logger;

/**
 * Base class for REST API controllers with nonce verification.
 */
abstract class RestController {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	protected const REST_NAMESPACE = 'dwt-management/v1';

	/**
	 * Verifies REST API nonce from request headers.
	 *
	 * @param \WP_REST_Request<array<string, mixed>> $request REST request object.
	 * @return bool True if nonce is valid.
	 */
	protected function verify_rest_nonce( \WP_REST_Request $request ): bool {
		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( ! $nonce ) {
			Logger::warning( 'REST API request missing nonce', array( 'route' => $request->get_route() ) );
			return false;
		}

		$valid = (bool) \wp_verify_nonce( $nonce, 'wp_rest' );

		if ( ! $valid ) {
			Logger::warning( 'REST API nonce verification failed', array( 'route' => $request->get_route() ) );
		}

		return $valid;
	}

	/**
	 * Create error response.
	 *
	 * @param string $message Error message.
	 * @param int    $status  HTTP status code.
	 * @return \WP_REST_Response Error response.
	 */
	protected function error_response( string $message, int $status = 400 ): \WP_REST_Response {
		Logger::info(
			'REST API error response',
			array(
				'message' => $message,
				'status'  => $status,
			)
		);

		return new \WP_REST_Response(
			array(
				'error'   => $message,
				'success' => false,
			),
			$status
		);
	}

	/**
	 * Create success response.
	 *
	 * @param array<string, mixed> $data   Response data.
	 * @param int                  $status HTTP status code.
	 * @return \WP_REST_Response Success response.
	 */
	protected function success_response( array $data = array(), int $status = 200 ): \WP_REST_Response {
		$data['success'] = true;

		return new \WP_REST_Response( $data, $status );
	}

	/**
	 * Register REST API routes.
	 * This method should be implemented by child classes.
	 *
	 * @return void
	 */
	abstract public function register_rest_routes(): void;
}
