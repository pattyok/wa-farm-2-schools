<?php
/**
 * WP-Cron integration — schedules the nightly sync.
 *
 * Anchor time: 02:00 local server time (converted to UTC via gmt_offset).
 * Called from register_activation_hook / register_deactivation_hook in the
 * main plugin file so __FILE__ resolves correctly.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wa_F2s_Cron {

	const HOOK = 'wa_f2s_daily_sync';

	/**
	 * Schedules the daily sync event on plugin activation.
	 * Uses wp_next_scheduled() to avoid duplicate events.
	 */
	public static function schedule(): void {
		if ( wp_next_scheduled( self::HOOK ) ) {
			return;
		}

		// Anchor at 02:00 local time, converted to UTC timestamp.
		$gmt_offset  = (float) get_option( 'gmt_offset', 0 );
		$local_2am   = strtotime( 'today 02:00:00' ) - (int) ( $gmt_offset * HOUR_IN_SECONDS );

		// If 2am has already passed today, schedule for tomorrow.
		if ( $local_2am <= time() ) {
			$local_2am += DAY_IN_SECONDS;
		}

		wp_schedule_event( $local_2am, 'daily', self::HOOK );
	}

	/**
	 * Removes all scheduled sync events on plugin deactivation.
	 */
	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}

	/**
	 * Registers the cron callback.
	 * Called from init() in the main plugin class.
	 */
	public static function register_callback(): void {
		add_action( self::HOOK, array( static::class, 'run' ) );
	}

	/**
	 * Executes the sync. Runs under WP-Cron — no output, no exit.
	 */
	public static function run(): void {
		if ( ! post_type_exists( 'network_member' ) ) {
			return;
		}

		$settings = wa_f2s_get_settings();

		if ( empty( $settings['api_key'] ) || empty( $settings['api_secret'] ) ) {
			return;
		}

		$client = new Wa_F2s_Api_Client( $settings['api_key'], $settings['api_secret'], (int) $settings['group_id'] );
		$mapper = new Wa_F2s_Mapper();
		$sync   = new Wa_F2s_Sync( $client, $mapper, (int) $settings['group_id'] );
		$sync->run_full_sync();
	}
}
