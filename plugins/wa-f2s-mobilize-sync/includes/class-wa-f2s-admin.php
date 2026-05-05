<?php
/**
 * Admin settings page and manual sync trigger.
 *
 * Settings page lives under the network_member CPT menu so it stays
 * contextually grouped with the content it manages.
 *
 * Two separate admin_post handlers — one for settings, one for sync —
 * each with its own nonce, preventing a CSRF replay of a settings save
 * from accidentally triggering a sync.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wa_F2s_Admin {

	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_post_wa_f2s_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_wa_f2s_run_sync', array( $this, 'handle_run_sync' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_sync_notice' ) );
	}

	public function add_settings_page(): void {
		add_submenu_page(
			'edit.php?post_type=network_member',
			__( 'Mobilize Sync', 'wa-f2s-mobilize-sync' ),
			__( 'Mobilize Sync', 'wa-f2s-mobilize-sync' ),
			'manage_options',
			'wa-f2s-mobilize-sync',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Saves the API key, API secret, and group ID.
	 */
	public function handle_save_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'wa-f2s-mobilize-sync' ) );
		}

		check_admin_referer( 'wa_f2s_save_settings_action', 'wa_f2s_settings_nonce' );

		$existing = wa_f2s_get_settings();

		// Only update each secret field if the user typed a new value (blank = keep existing).
		$submitted_key    = sanitize_text_field( wp_unslash( $_POST['wa_f2s_api_key'] ?? '' ) );
		$submitted_secret = sanitize_text_field( wp_unslash( $_POST['wa_f2s_api_secret'] ?? '' ) );

		$api_key    = '' !== $submitted_key    ? $submitted_key    : $existing['api_key'];
		$api_secret = '' !== $submitted_secret ? $submitted_secret : $existing['api_secret'];

		$group_id = absint( $_POST['wa_f2s_group_id'] ?? $existing['group_id'] );

		$settings = array(
			'api_key'    => $api_key,
			'api_secret' => $api_secret,
			'group_id'   => $group_id ?: $existing['group_id'],
		);

		update_option( 'wa_f2s_mobilize_settings', wp_json_encode( $settings ), 'no' );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'      => 'wa-f2s-mobilize-sync',
					'post_type' => 'network_member',
					'updated'   => '1',
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * Triggers a full sync on demand.
	 */
	public function handle_run_sync(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'wa-f2s-mobilize-sync' ) );
		}

		check_admin_referer( 'wa_f2s_run_sync_action', 'wa_f2s_sync_nonce' );

		if ( ! post_type_exists( 'network_member' ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'      => 'wa-f2s-mobilize-sync',
						'post_type' => 'network_member',
						'sync'      => 'no_cpt',
					),
					admin_url( 'edit.php' )
				)
			);
			exit;
		}

		$settings = wa_f2s_get_settings();

		if ( empty( $settings['api_key'] ) || empty( $settings['api_secret'] ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'      => 'wa-f2s-mobilize-sync',
						'post_type' => 'network_member',
						'sync'      => 'no_credential',
					),
					admin_url( 'edit.php' )
				)
			);
			exit;
		}

		$client = new Wa_F2s_Api_Client( $settings['api_key'], $settings['api_secret'], (int) $settings['group_id'] );
		$mapper = new Wa_F2s_Mapper();
		$sync   = new Wa_F2s_Sync( $client, $mapper, (int) $settings['group_id'] );
		$result = $sync->run_full_sync();

		$status = isset( $result['error'] ) ? 'error' : 'done';

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'      => 'wa-f2s-mobilize-sync',
					'post_type' => 'network_member',
					'sync'      => $status,
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * Shows an admin notice after a settings save or sync run.
	 */
	public function maybe_show_sync_notice(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'network_member_page_wa-f2s-mobilize-sync' !== $screen->id ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['updated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'wa-f2s-mobilize-sync' ) . '</p></div>';
		}

		if ( isset( $_GET['sync'] ) ) {
			$sync = sanitize_key( $_GET['sync'] );

			if ( 'done' === $sync ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Sync complete. See results below.', 'wa-f2s-mobilize-sync' ) . '</p></div>';
			} elseif ( 'error' === $sync ) {
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Sync encountered an error. See results below.', 'wa-f2s-mobilize-sync' ) . '</p></div>';
			} elseif ( 'no_cpt' === $sync ) {
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Sync aborted: the network_member post type is not registered. Ensure the correct theme is active.', 'wa-f2s-mobilize-sync' ) . '</p></div>';
			} elseif ( 'no_credential' === $sync ) {
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Sync aborted: API key and secret are not configured.', 'wa-f2s-mobilize-sync' ) . '</p></div>';
			}
		}
		// phpcs:enable
	}

	/**
	 * Renders the settings page.
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings         = wa_f2s_get_settings();
		$last_sync        = get_option( 'wa_f2s_mobilize_last_sync', 0 );
		$last_summary_raw = get_option( 'wa_f2s_mobilize_last_sync_summary', '' );
		$last_summary     = $last_summary_raw ? json_decode( $last_summary_raw, true ) : null;
		$last_error_raw   = get_option( 'wa_f2s_mobilize_last_error', '' );
		$last_errors      = $last_error_raw ? json_decode( $last_error_raw, true ) : array();

		$has_api_key       = ! empty( $settings['api_key'] );
		$has_api_secret    = ! empty( $settings['api_secret'] );
		$key_from_const    = defined( 'WA_F2S_MOBILIZE_API_KEY' ) && WA_F2S_MOBILIZE_API_KEY;
		$secret_from_const = defined( 'WA_F2S_MOBILIZE_API_SECRET' ) && WA_F2S_MOBILIZE_API_SECRET;

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Mobilize Sync Settings', 'wa-f2s-mobilize-sync' ); ?></h1>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="wa_f2s_save_settings">
				<?php wp_nonce_field( 'wa_f2s_save_settings_action', 'wa_f2s_settings_nonce' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="wa_f2s_api_key"><?php esc_html_e( 'API Key', 'wa-f2s-mobilize-sync' ); ?></label>
						</th>
						<td>
							<input
								type="password"
								id="wa_f2s_api_key"
								name="wa_f2s_api_key"
								class="regular-text"
								value=""
								placeholder="<?php echo $has_api_key ? esc_attr( '••••••••' ) : ''; ?>"
								autocomplete="new-password"
							>
							<p class="description">
								<?php if ( $key_from_const ) : ?>
									<?php esc_html_e( 'Set via WA_F2S_MOBILIZE_API_KEY in wp-config.php (takes precedence).', 'wa-f2s-mobilize-sync' ); ?>
								<?php else : ?>
									<?php esc_html_e( 'Your Mobilize API key. Leave blank to keep the existing value.', 'wa-f2s-mobilize-sync' ); ?>
								<?php endif; ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wa_f2s_api_secret"><?php esc_html_e( 'API Secret', 'wa-f2s-mobilize-sync' ); ?></label>
						</th>
						<td>
							<input
								type="password"
								id="wa_f2s_api_secret"
								name="wa_f2s_api_secret"
								class="regular-text"
								value=""
								placeholder="<?php echo $has_api_secret ? esc_attr( '••••••••' ) : ''; ?>"
								autocomplete="new-password"
							>
							<p class="description">
								<?php if ( $secret_from_const ) : ?>
									<?php esc_html_e( 'Set via WA_F2S_MOBILIZE_API_SECRET in wp-config.php (takes precedence).', 'wa-f2s-mobilize-sync' ); ?>
								<?php else : ?>
									<?php esc_html_e( 'Your Mobilize API secret. Leave blank to keep the existing value.', 'wa-f2s-mobilize-sync' ); ?>
								<?php endif; ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wa_f2s_group_id"><?php esc_html_e( 'Mobilize Group ID', 'wa-f2s-mobilize-sync' ); ?></label>
						</th>
						<td>
							<input
								type="number"
								id="wa_f2s_group_id"
								name="wa_f2s_group_id"
								class="small-text"
								value="<?php echo esc_attr( (string) $settings['group_id'] ); ?>"
								min="1"
							>
							<p class="description"><?php esc_html_e( 'The Mobilize group ID to sync. Default: 136728 (WA Farm to School Network).', 'wa-f2s-mobilize-sync' ); ?></p>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Save Settings', 'wa-f2s-mobilize-sync' ) ); ?>
			</form>

			<hr>

			<h2><?php esc_html_e( 'Manual Sync', 'wa-f2s-mobilize-sync' ); ?></h2>

			<?php if ( $last_sync ) : ?>
				<p>
					<?php
					printf(
						/* translators: %s: human-readable time since last sync */
						esc_html__( 'Last sync: %s ago', 'wa-f2s-mobilize-sync' ),
						esc_html( human_time_diff( (int) $last_sync ) )
					);
					?>
				</p>
			<?php else : ?>
				<p><?php esc_html_e( 'No sync has been run yet.', 'wa-f2s-mobilize-sync' ); ?></p>
			<?php endif; ?>

			<?php if ( is_array( $last_summary ) ) : ?>
				<table class="widefat striped" style="max-width:400px;margin-bottom:1em;">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Metric', 'wa-f2s-mobilize-sync' ); ?></th>
							<th><?php esc_html_e( 'Count', 'wa-f2s-mobilize-sync' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr><td><?php esc_html_e( 'Created', 'wa-f2s-mobilize-sync' ); ?></td><td><?php echo esc_html( (string) ( $last_summary['created'] ?? 0 ) ); ?></td></tr>
						<tr><td><?php esc_html_e( 'Updated', 'wa-f2s-mobilize-sync' ); ?></td><td><?php echo esc_html( (string) ( $last_summary['updated'] ?? 0 ) ); ?></td></tr>
						<tr><td><?php esc_html_e( 'Skipped', 'wa-f2s-mobilize-sync' ); ?></td><td><?php echo esc_html( (string) ( $last_summary['skipped'] ?? 0 ) ); ?></td></tr>
						<tr><td><?php esc_html_e( 'Trashed', 'wa-f2s-mobilize-sync' ); ?></td><td><?php echo esc_html( (string) ( $last_summary['trashed'] ?? 0 ) ); ?></td></tr>
					</tbody>
				</table>
			<?php endif; ?>

			<?php if ( ! empty( $last_errors ) ) : ?>
				<div class="notice notice-warning inline">
					<p><strong><?php esc_html_e( 'Warnings from last sync:', 'wa-f2s-mobilize-sync' ); ?></strong></p>
					<ul style="list-style:disc;padding-left:1.5em;">
						<?php foreach ( (array) $last_errors as $err ) : ?>
							<li><?php echo esc_html( $err ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:1em;">
				<input type="hidden" name="action" value="wa_f2s_run_sync">
				<?php wp_nonce_field( 'wa_f2s_run_sync_action', 'wa_f2s_sync_nonce' ); ?>
				<?php submit_button( __( 'Run Full Sync Now', 'wa-f2s-mobilize-sync' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}
}
