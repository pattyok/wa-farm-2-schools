<?php
/**
 * Sync engine — orchestrates the full member sync from Mobilize to WordPress.
 *
 * Design decisions:
 * - Pre-loads all existing post IDs in one WP_Query (avoids N+1 per-member queries).
 * - Trash pass only runs when API traversal completed AND seen_ids is non-empty
 *   (prevents trashing all members if the API fails mid-pagination).
 * - Empty county[] from Mobilize leaves existing region terms untouched.
 * - wp_defer_term_counting() batches all taxonomy count updates into one pass.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wa_F2s_Sync {

	private array $summary = array(
		'created' => 0,
		'updated' => 0,
		'skipped' => 0,
		'trashed' => 0,
		'errors'  => array(),
	);

	public function __construct(
		private readonly Wa_F2s_Api_Client $api,
		private readonly Wa_F2s_Mapper $mapper,
		private readonly int $group_id
	) {}

	/**
	 * Public entry point — wraps mutex and delegates to do_full_sync().
	 *
	 * @return array Sync summary with keys: created, updated, skipped, trashed, errors.
	 */
	public function run_full_sync(): array {
		if ( get_transient( '_wa_f2s_sync_running' ) ) {
			return array( 'error' => 'A sync is already in progress. Please wait and try again.' );
		}

		set_transient(
			'_wa_f2s_sync_running',
			array( 'pid' => getmypid(), 'started' => time() ),
			15 * MINUTE_IN_SECONDS
		);

		try {
			return $this->do_full_sync();
		} finally {
			delete_transient( '_wa_f2s_sync_running' );
		}
	}

	/**
	 * Main sync logic.
	 */
	private function do_full_sync(): array {
		// Reset summary for this run.
		$this->summary = array(
			'created' => 0,
			'updated' => 0,
			'skipped' => 0,
			'trashed' => 0,
			'errors'  => array(),
		);

		// Prevent PHP timeout on shared hosts.
		set_time_limit( 300 );

		// Guard: CPT must be registered (requires active theme).
		if ( ! post_type_exists( 'network_member' ) ) {
			return array( 'error' => 'The network_member post type is not registered. Ensure the correct theme is active.' );
		}

		// Phase 0: Detect and fix any duplicate posts from prior bugs or WP All Import.
		$this->detect_and_fix_duplicates();

		// Phase 1: Pre-load all existing Mobilize→WP post ID mappings in one query.
		list( $id_map, $updated_at_map ) = $this->build_id_map();

		// Phase 2: Fetch all members from Mobilize API (paginated).
		$members = $this->api->get_all_members();

		if ( is_wp_error( $members ) ) {
			$error_msg = $members->get_error_message();
			$this->summary['errors'][] = 'API error: ' . $error_msg;
			$this->store_summary();
			return $this->summary;
		}

		$api_complete = true;
		$seen_ids     = array();

		// Phase 3: Upsert each member — wrap in deferred term counting.
		wp_defer_term_counting( true );

		foreach ( $members as $member ) {
			// Only sync accepted members in our group.
			if ( ! $this->mapper->is_accepted( $member, $this->group_id ) ) {
				continue;
			}

			$mobilize_id = (int) $member['id'];
			$seen_ids[]  = $mobilize_id;

			$outcome = $this->upsert_member( $member, $id_map, $updated_at_map );

			if ( isset( $this->summary[ $outcome ] ) ) {
				++$this->summary[ $outcome ];
			}
		}

		wp_defer_term_counting( false );

		// Phase 4: Trash posts for members no longer in Mobilize.
		// Safety gates: only run if API traversal was complete AND we saw members.
		if ( $api_complete && ! empty( $seen_ids ) ) {
			$this->summary['trashed'] = $this->trash_removed_members( $id_map, $seen_ids );
		} elseif ( $api_complete && empty( $seen_ids ) ) {
			$this->summary['errors'][] = 'API returned zero members — trash pass skipped as a safety measure.';
		}

		$this->store_summary();
		return $this->summary;
	}

	/**
	 * Builds a lookup of [ mobilize_id => wp_post_id ] and [ mobilize_id => updated_at_ms ]
	 * from a single WP_Query — eliminates N+1 per-member dedup queries.
	 *
	 * @return array{0: array<int,int>, 1: array<int,int>}
	 */
	private function build_id_map(): array {
		$id_map        = array();
		$updated_at_map = array();

		$query = new WP_Query( array(
			'post_type'              => 'network_member',
			'post_status'            => 'any',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
			'meta_query'             => array(
				array(
					'key'     => '_wa_f2s_mobilize_member_id',
					'compare' => 'EXISTS',
				),
			),
		) );

		foreach ( $query->posts as $post_id ) {
			$mobilize_id = (int) get_post_meta( $post_id, '_wa_f2s_mobilize_member_id', true );
			$updated_at  = (int) get_post_meta( $post_id, '_wa_f2s_mobilize_updated_at', true );

			if ( $mobilize_id > 0 ) {
				$id_map[ $mobilize_id ]         = (int) $post_id;
				$updated_at_map[ $mobilize_id ] = $updated_at;
			}
		}

		return array( $id_map, $updated_at_map );
	}

	/**
	 * Creates or updates a single network_member post.
	 *
	 * @return string 'created'|'updated'|'skipped'|'error'
	 */
	private function upsert_member( array $member, array $id_map, array $updated_at_map ): string {
		$mobilize_id = (int) $member['id'];
		$api_updated = (int) $member['updated_at'];
		$post_id     = $id_map[ $mobilize_id ] ?? null;

		if ( null !== $post_id ) {
			// Existing post — skip if unchanged. Cast both sides to int (get_post_meta returns string).
			if ( (int) ( $updated_at_map[ $mobilize_id ] ?? 0 ) === $api_updated ) {
				return 'skipped';
			}

			// Mobilize record is newer — update.
			$result = wp_update_post(
				array(
					'ID'          => $post_id,
					'post_title'  => $this->mapper->title( $member ),
					'post_status' => 'publish',
				),
				true // Return WP_Error on failure.
			);

			if ( is_wp_error( $result ) || 0 === $result ) {
				$this->summary['errors'][] = sprintf(
					'Member %d: update failed — %s',
					$mobilize_id,
					is_wp_error( $result ) ? $result->get_error_message() : 'unknown error'
				);
				return 'error';
			}
		} else {
			// New member — create.
			$result = wp_insert_post(
				array(
					'post_title'  => $this->mapper->title( $member ),
					'post_type'   => 'network_member',
					'post_status' => 'publish',
				),
				true // Return WP_Error on failure.
			);

			if ( is_wp_error( $result ) || 0 === $result ) {
				$this->summary['errors'][] = sprintf(
					'Member %d: insert failed — %s',
					$mobilize_id,
					is_wp_error( $result ) ? $result->get_error_message() : 'unknown error'
				);
				return 'error';
			}

			$post_id = (int) $result;
		}

		// Write sync tracking meta.
		update_post_meta( $post_id, '_wa_f2s_mobilize_member_id', $mobilize_id );
		update_post_meta( $post_id, '_wa_f2s_mobilize_updated_at', $api_updated );

		// Write field data.
		$this->assign_region_terms( $post_id, $this->mapper->counties( $member ) );
		$this->assign_network_area_terms( $post_id, $this->mapper->network_areas( $member ) );
		$this->write_acf_field( $post_id, 'network_website', $this->mapper->website( $member ) );
		$this->write_description( $post_id, $this->mapper->description( $member ) );

		return ( isset( $id_map[ $mobilize_id ] ) ) ? 'updated' : 'created';
	}

	/**
	 * Assigns region taxonomy terms by matching county names to existing child terms.
	 *
	 * IMPORTANT: If $counties is empty, existing region terms are left untouched.
	 * Calling wp_set_object_terms() with an empty array would remove all region
	 * assignments, silently hiding the member from regional archive pages.
	 */
	private function assign_region_terms( int $post_id, array $counties ): void {
		if ( empty( $counties ) ) {
			return; // No county data — preserve existing terms.
		}

		$term_ids = array();

		foreach ( $counties as $county_name ) {
			// Use term IDs for hierarchical taxonomies (names can collide across parents).
			$term = get_term_by( 'name', $county_name, 'region' );
			if ( $term && ! is_wp_error( $term ) ) {
				$term_ids[] = (int) $term->term_id;
			} else {
				$this->summary['errors'][] = sprintf(
					'Unmatched county "%s" — no matching region term found.',
					$county_name
				);
			}
		}

		if ( ! empty( $term_ids ) ) {
			wp_set_object_terms( $post_id, $term_ids, 'region' );
		}
	}

	/**
	 * Assigns network_area taxonomy terms, creating new terms when no match exists.
	 * Term creation is idempotent — a 'term_exists' error returns the existing term ID.
	 */
	private function assign_network_area_terms( int $post_id, array $components ): void {
		if ( empty( $components ) ) {
			wp_set_object_terms( $post_id, array(), 'network_area' );
			return;
		}

		$term_ids = array();

		foreach ( $components as $component_name ) {
			// Case-insensitive name match via slug.
			$slug = sanitize_title( $component_name );
			$term = get_term_by( 'slug', $slug, 'network_area' );

			if ( ! $term ) {
				// Try by name (handles slugs that differ from sanitized label).
				$term = get_term_by( 'name', $component_name, 'network_area' );
			}

			if ( $term && ! is_wp_error( $term ) ) {
				$term_ids[] = (int) $term->term_id;
			} else {
				// Auto-create — term_exists error returns the existing term_id (idempotent).
				$result = wp_insert_term( $component_name, 'network_area' );

				if ( is_wp_error( $result ) ) {
					if ( 'term_exists' === $result->get_error_code() ) {
						// Race condition or prior existence — use the returned term ID.
						$existing_id = $result->get_error_data( 'term_exists' );
						if ( $existing_id ) {
							$term_ids[] = (int) $existing_id;
						}
					} else {
						$this->summary['errors'][] = sprintf(
							'Could not create network_area term "%s": %s',
							$component_name,
							$result->get_error_message()
						);
					}
				} else {
					$term_ids[] = (int) $result['term_id'];
				}
			}
		}

		if ( ! empty( $term_ids ) ) {
			wp_set_object_terms( $post_id, $term_ids, 'network_area' );
		}
	}

	/**
	 * Computes the trash set from the pre-loaded ID map — no database query needed.
	 *
	 * Only called when api_complete === true AND seen_ids is non-empty.
	 *
	 * @param array<int,int> $id_map         [ mobilize_id => wp_post_id ]
	 * @param int[]          $seen_mobilize_ids  IDs returned by the API in this sync run.
	 * @return int Number of posts trashed.
	 */
	private function trash_removed_members( array $id_map, array $seen_mobilize_ids ): int {
		$seen_set = array_flip( $seen_mobilize_ids ); // O(1) lookup.
		$trashed  = 0;

		foreach ( $id_map as $mobilize_id => $post_id ) {
			if ( ! isset( $seen_set[ $mobilize_id ] ) ) {
				wp_trash_post( $post_id );
				++$trashed;
			}
		}

		return $trashed;
	}

	/**
	 * Detects posts with duplicate _wa_f2s_mobilize_member_id values and trashes
	 * all but the canonical (oldest / lowest post ID) copy.
	 */
	private function detect_and_fix_duplicates(): void {
		global $wpdb;

		$dupes = $wpdb->get_results(
			"SELECT meta_value, COUNT(*) AS c, MIN(post_id) AS canonical
			 FROM {$wpdb->postmeta}
			 WHERE meta_key = '_wa_f2s_mobilize_member_id'
			 GROUP BY meta_value
			 HAVING c > 1"
		);

		foreach ( $dupes as $dupe ) {
			$extras = get_posts( array(
				'post_type'      => 'network_member',
				'post_status'    => 'any',
				'meta_key'       => '_wa_f2s_mobilize_member_id',
				'meta_value'     => $dupe->meta_value,
				'post__not_in'   => array( (int) $dupe->canonical ),
				'fields'         => 'ids',
				'no_found_rows'  => true,
			) );

			foreach ( $extras as $extra_id ) {
				wp_trash_post( $extra_id );
				$this->summary['errors'][] = sprintf(
					'Trashed duplicate post %d for Mobilize member ID %s.',
					$extra_id,
					$dupe->meta_value
				);
			}
		}
	}

	/**
	 * Writes an ACF field value, falling back to update_post_meta() if ACF is inactive.
	 */
	private function write_acf_field( int $post_id, string $field_name, mixed $value ): void {
		if ( function_exists( 'update_field' ) ) {
			update_field( $field_name, $value, $post_id );
		} else {
			update_post_meta( $post_id, $field_name, $value );
		}
	}

	/**
	 * Writes the network_description field.
	 *
	 * Uses update_post_meta() directly because the ACF field definition exists only
	 * in the wp-rig source theme, not yet in the active theme's acf-json/. Also saves
	 * the ACF shadow meta (field key reference) so the admin UI renders correctly once
	 * the ACF JSON is synced to the active theme.
	 *
	 * Field key: field_69f8ec4d88432 (from wp-rig/acf-json/group_69e7e69088665.json).
	 */
	private function write_description( int $post_id, string $description ): void {
		update_post_meta( $post_id, 'network_description', $description );
		// ACF shadow meta — tells ACF which field object governs this meta key.
		update_post_meta( $post_id, '_network_description', 'field_69f8ec4d88432' );
	}

	/**
	 * Persists the sync summary to wp_options as JSON (not PHP-serialized).
	 */
	private function store_summary(): void {
		update_option( 'wa_f2s_mobilize_last_sync', time(), 'no' );
		update_option(
			'wa_f2s_mobilize_last_sync_summary',
			wp_json_encode( $this->summary ),
			'no'
		);

		if ( ! empty( $this->summary['errors'] ) ) {
			update_option(
				'wa_f2s_mobilize_last_error',
				wp_json_encode( $this->summary['errors'] ),
				'no'
			);
		} else {
			delete_option( 'wa_f2s_mobilize_last_error' );
		}
	}
}
