<?php
/**
 * Mobilize API client — handles authentication, pagination, and HTTP error handling.
 *
 * Authentication: HTTP Basic Auth with separate API key and secret.
 * The Authorization header is built as: Basic base64(api_key:api_secret).
 * Both values are stored raw and encoded at request time.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wa_F2s_Api_Client {

	const API_BASE = 'https://api.mobilize.io/v1';

	public function __construct(
		private readonly string $api_key,
		private readonly string $api_secret,
		private readonly int $group_id
	) {}

	/**
	 * Fetches all accepted members across all pages.
	 *
	 * @return array|WP_Error Full member array or WP_Error on any page failure.
	 */
	public function get_all_members(): array|WP_Error {
		$all_members = array();
		$offset      = 0;
		$limit       = 50;

		do {
			$page = $this->get_members_page( $offset, $limit );

			if ( is_wp_error( $page ) ) {
				return $page; // Abort — caller must not run trash pass on partial data.
			}

			$all_members = array_merge( $all_members, $page );
			$offset     += $limit;

		} while ( count( $page ) === $limit );

		return $all_members;
	}

	/**
	 * Fetches a single page of members.
	 *
	 * @return array|WP_Error Array of member objects or WP_Error.
	 */
	private function get_members_page( int $offset, int $limit = 50 ): array|WP_Error {
		$url = add_query_arg(
			array(
				'include_fields' => 'true',
				'limit'          => $limit,
				'offset'         => $offset,
			),
			self::API_BASE . '/groups/' . $this->group_id . '/members/'
		);

		$response = wp_remote_get(
			$url,
			array(
				'headers'   => array( 'Authorization' => $this->auth_header() ),
				'timeout'   => 30,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== (int) $code ) {
			return new WP_Error(
				'api_error',
				sprintf( 'Mobilize API returned HTTP %d', $code )
			);
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( null === $data ) {
			return new WP_Error( 'invalid_json', 'Mobilize API response was not valid JSON' );
		}

		// Mobilize wraps results in a 'results' key (Django REST Framework convention).
		if ( isset( $data['results'] ) && is_array( $data['results'] ) ) {
			return $data['results'];
		}

		// Fallback: response is a direct array of members.
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Tests API connectivity with a minimal request. Used by WP-CLI test-connection.
	 *
	 * @return array|WP_Error Response data or WP_Error.
	 */
	public function test_connection(): array|WP_Error {
		$url = add_query_arg(
			array(
				'include_fields' => 'false',
				'limit'          => 1,
			),
			self::API_BASE . '/groups/' . $this->group_id . '/members/'
		);

		$response = wp_remote_get(
			$url,
			array(
				'headers'   => array( 'Authorization' => $this->auth_header() ),
				'timeout'   => 15,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== (int) $code ) {
			return new WP_Error(
				'api_error',
				sprintf( 'Mobilize API returned HTTP %d', $code )
			);
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $data ) ? $data : array();
	}

	private function auth_header(): string {
		return 'Basic ' . base64_encode( $this->api_key . ':' . $this->api_secret );
	}
}
