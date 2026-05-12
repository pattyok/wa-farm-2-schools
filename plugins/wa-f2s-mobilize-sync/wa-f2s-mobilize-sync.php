<?php
/**
 * Plugin Name: WA F2S Mobilize Sync
 * Plugin URI:  https://carkeekstudios.com/
 * Description: Syncs members from the Mobilize API into WordPress as network_member posts for the WA Farm to School Network.
 * Author:      Carkeek Studios
 * Version:     1.0.0
 * Author URI:  https://carkeekstudios.com/
 * Text Domain: wa-f2s-mobilize-sync
 *
 * IMPORTANT: Mobilize is the source of truth. Manual WordPress edits to synced
 * fields (title, website, description, taxonomies) will be overwritten on the
 * next sync if the Mobilize record has been updated.
 *
 * Meta keys managed by this plugin (do NOT overwrite via WP All Import):
 *   _wa_f2s_mobilize_member_id
 *   _wa_f2s_mobilize_updated_at
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Activation/deactivation hooks must be registered from the main plugin file
// so __FILE__ resolves to the correct plugin path.
register_activation_hook( __FILE__, array( 'Wa_F2s_Mobilize_Sync', 'on_activation' ) );
register_deactivation_hook( __FILE__, array( 'Wa_F2s_Mobilize_Sync', 'on_deactivation' ) );

if ( ! class_exists( 'Wa_F2s_Mobilize_Sync' ) ) :

	/**
	 * Main plugin class — singleton.
	 */
	final class Wa_F2s_Mobilize_Sync {

		private static ?self $instance = null;

		/**
		 * Runs activation tasks.
		 */
		public static function on_activation(): void {
			require_once plugin_dir_path( __FILE__ ) . 'includes/class-wa-f2s-cron.php';
			Wa_F2s_Cron::sync_schedule_state();
		}

		/**
		 * Runs deactivation tasks.
		 */
		public static function on_deactivation(): void {
			require_once plugin_dir_path( __FILE__ ) . 'includes/class-wa-f2s-cron.php';
			Wa_F2s_Cron::unschedule();
		}

		public static function instance(): self {
			if ( null === self::$instance ) {
				self::$instance = new self();
				// Correct order: constants → includes → init.
				// (carkeek-site-blocks runs setup_constants after init — that is a bug we avoid here.)
				self::$instance->setup_constants();
				self::$instance->includes();
				self::$instance->init();
			}
			return self::$instance;
		}

		public function __clone() {
			_doing_it_wrong( __FUNCTION__, esc_html__( 'Cloning is forbidden.', 'wa-f2s-mobilize-sync' ), '1.0.0' );
		}

		public function __wakeup() {
			_doing_it_wrong( __FUNCTION__, esc_html__( 'Unserializing is forbidden.', 'wa-f2s-mobilize-sync' ), '1.0.0' );
		}

		private function setup_constants(): void {
			$this->define( 'WA_F2S_MOBILIZE_VERSION', '1.0.0' );
			$this->define( 'WA_F2S_MOBILIZE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
			$this->define( 'WA_F2S_MOBILIZE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
			$this->define( 'WA_F2S_MOBILIZE_PLUGIN_FILE', __FILE__ );
			$this->define( 'WA_F2S_MOBILIZE_PLUGIN_BASE', plugin_basename( __FILE__ ) );
		}

		private function define( string $name, $value ): void {
			if ( ! defined( $name ) ) {
				define( $name, $value );
			}
		}

		private function includes(): void {
			require_once WA_F2S_MOBILIZE_PLUGIN_DIR . 'includes/class-wa-f2s-api-client.php';
			require_once WA_F2S_MOBILIZE_PLUGIN_DIR . 'includes/class-wa-f2s-mapper.php';
			require_once WA_F2S_MOBILIZE_PLUGIN_DIR . 'includes/class-wa-f2s-sync.php';
			require_once WA_F2S_MOBILIZE_PLUGIN_DIR . 'includes/class-wa-f2s-admin.php';
			require_once WA_F2S_MOBILIZE_PLUGIN_DIR . 'includes/class-wa-f2s-cron.php';
			require_once WA_F2S_MOBILIZE_PLUGIN_DIR . 'includes/class-wa-f2s-cli.php';
		}

		private function init(): void {
			$admin = new Wa_F2s_Admin();
			$admin->register_hooks();

			Wa_F2s_Cron::register_callback();
			Wa_F2s_Cron::sync_schedule_state();

			if ( defined( 'WP_CLI' ) && WP_CLI ) {
				WP_CLI::add_command( 'wa-f2s-mobilize', 'Wa_F2s_CLI_Command' );
			}
		}
	}

endif;

/**
 * Returns the merged plugin settings with defaults applied.
 *
 * Constants in wp-config.php take precedence over database values:
 *   define( 'WA_F2S_MOBILIZE_API_KEY',    'your-api-key' );
 *   define( 'WA_F2S_MOBILIZE_API_SECRET', 'your-api-secret' );
 */
function wa_f2s_get_settings(): array {
	$defaults = array(
		'api_key'               => '',
		'api_secret'            => '',
		'group_id'              => 136728,
		'enable_wp_cron'        => 0,
		'trash_missing_members' => 0,
	);

	$raw      = get_option( 'wa_f2s_mobilize_settings', '{}' );
	$stored   = json_decode( $raw, true );
	$settings = wp_parse_args( is_array( $stored ) ? $stored : array(), $defaults );

	// wp-config.php constants take precedence over database values.
	if ( defined( 'WA_F2S_MOBILIZE_API_KEY' ) && WA_F2S_MOBILIZE_API_KEY ) {
		$settings['api_key'] = WA_F2S_MOBILIZE_API_KEY;
	}
	if ( defined( 'WA_F2S_MOBILIZE_API_SECRET' ) && WA_F2S_MOBILIZE_API_SECRET ) {
		$settings['api_secret'] = WA_F2S_MOBILIZE_API_SECRET;
	}

	return $settings;
}

/**
 * Runs a full sync and returns the summary payload.
 *
 * @return array
 */
function wa_f2s_run_sync_now(): array {
	if ( ! post_type_exists( 'network_member' ) ) {
		return array( 'error' => 'The network_member post type is not registered. Ensure the correct theme is active.' );
	}

	$settings = wa_f2s_get_settings();

	if ( empty( $settings['api_key'] ) || empty( $settings['api_secret'] ) ) {
		return array( 'error' => 'API key and secret are not configured.' );
	}

	$client = new Wa_F2s_Api_Client( $settings['api_key'], $settings['api_secret'], (int) $settings['group_id'] );
	$mapper = new Wa_F2s_Mapper();
	$sync   = new Wa_F2s_Sync( $client, $mapper, (int) $settings['group_id'] );

	return $sync->run_full_sync();
}

/**
 * Bootstrap — mirrors carkeek-site-blocks multisite pattern.
 */
function wa_f2s_mobilize_sync(): Wa_F2s_Mobilize_Sync {
	return Wa_F2s_Mobilize_Sync::instance();
}

if ( function_exists( 'is_multisite' ) && is_multisite() ) {
	add_action( 'plugins_loaded', 'wa_f2s_mobilize_sync' );
} else {
	wa_f2s_mobilize_sync();
}
