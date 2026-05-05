<?php
/**
 * WP-CLI commands for WA F2S Mobilize Sync.
 *
 * Usage:
 *   wp wa-f2s-mobilize test-connection
 *   wp wa-f2s-mobilize sync
 *
 * These commands are the safe alternative to a URL-based debug endpoint.
 * They can only be triggered from the server command line.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Manages the Mobilize member sync.
 */
class Wa_F2s_CLI_Command extends WP_CLI_Command {

	/**
	 * Tests connectivity to the Mobilize API.
	 *
	 * ## EXAMPLES
	 *
	 *   wp wa-f2s-mobilize test-connection
	 *
	 * @when after_wp_load
	 */
	public function test_connection(): void {
		$settings = wa_f2s_get_settings();

		if ( empty( $settings['api_key'] ) || empty( $settings['api_secret'] ) ) {
			WP_CLI::error( 'API key or secret is not configured. Set them on the Mobilize Sync settings page or define WA_F2S_MOBILIZE_API_KEY / WA_F2S_MOBILIZE_API_SECRET in wp-config.php.' );
		}

		WP_CLI::log( sprintf( 'Testing connection to group ID %d…', $settings['group_id'] ) );

		$client = new Wa_F2s_Api_Client( $settings['api_key'], $settings['api_secret'], (int) $settings['group_id'] );
		$result = $client->test_connection();

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		WP_CLI::success( sprintf(
			'Connection successful. Group ID: %d',
			$settings['group_id']
		) );

		if ( isset( $result['count'] ) ) {
			WP_CLI::log( sprintf( 'Total members in group: %d', $result['count'] ) );
		}
	}

	/**
	 * Runs a full sync of Mobilize members to WordPress.
	 *
	 * ## EXAMPLES
	 *
	 *   wp wa-f2s-mobilize sync
	 *
	 * @when after_wp_load
	 */
	public function sync(): void {
		if ( ! post_type_exists( 'network_member' ) ) {
			WP_CLI::error( 'The network_member post type is not registered. Ensure the active theme is correct.' );
		}

		$settings = wa_f2s_get_settings();

		if ( empty( $settings['api_key'] ) || empty( $settings['api_secret'] ) ) {
			WP_CLI::error( 'API key or secret is not configured.' );
		}

		WP_CLI::log( 'Starting full sync…' );

		$client = new Wa_F2s_Api_Client( $settings['api_key'], $settings['api_secret'], (int) $settings['group_id'] );
		$mapper = new Wa_F2s_Mapper();
		$sync   = new Wa_F2s_Sync( $client, $mapper, (int) $settings['group_id'] );
		$result = $sync->run_full_sync();

		if ( isset( $result['error'] ) ) {
			WP_CLI::error( $result['error'] );
		}

		WP_CLI::success( sprintf(
			'Sync complete — Created: %d | Updated: %d | Skipped: %d | Trashed: %d',
			(int) ( $result['created'] ?? 0 ),
			(int) ( $result['updated'] ?? 0 ),
			(int) ( $result['skipped'] ?? 0 ),
			(int) ( $result['trashed'] ?? 0 )
		) );

		if ( ! empty( $result['errors'] ) ) {
			WP_CLI::log( 'Warnings:' );
			foreach ( $result['errors'] as $err ) {
				WP_CLI::warning( esc_html( $err ) );
			}
		}
	}
}
