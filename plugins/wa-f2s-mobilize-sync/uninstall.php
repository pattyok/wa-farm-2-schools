<?php
/**
 * Runs on plugin uninstall (not deactivation).
 *
 * Deletes all options and transients created by this plugin.
 * Post data (network_member posts) and taxonomy terms are intentionally
 * preserved — the user may want to keep their member directory even after
 * removing the sync plugin.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wa_f2s_mobilize_settings' );
delete_option( 'wa_f2s_mobilize_last_sync' );
delete_option( 'wa_f2s_mobilize_last_sync_summary' );
delete_option( 'wa_f2s_mobilize_last_error' );
delete_transient( '_wa_f2s_sync_running' );
