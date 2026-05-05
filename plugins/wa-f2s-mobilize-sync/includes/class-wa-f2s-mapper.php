<?php
/**
 * Maps raw Mobilize member data to WordPress-ready values.
 *
 * Extracted from the sync class for testability — this class has no WordPress
 * dependencies and can be unit-tested with plain PHP arrays.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wa_F2s_Mapper {

	/**
	 * Derives the post title from a Mobilize member.
	 *
	 * Fallback chain: company_name → first_name + last_name → "Member {id}".
	 */
	public function title( array $member ): string {
		$fields = $member['fields'] ?? array();

		if ( ! empty( $fields['company_name'] ) ) {
			return sanitize_text_field( $fields['company_name'] );
		}

		$name = trim(
			( $member['first_name'] ?? '' ) . ' ' . ( $member['last_name'] ?? '' )
		);
		if ( '' !== $name ) {
			return sanitize_text_field( $name );
		}

		return 'Member ' . $member['id'];
	}

	/**
	 * Returns the county names from a Mobilize member.
	 *
	 * @return string[] Array of county name strings (may be empty).
	 */
	public function counties( array $member ): array {
		$counties = $member['fields']['county'] ?? array();
		return is_array( $counties ) ? $counties : array();
	}

	/**
	 * Returns the f2s_core_components values from a Mobilize member.
	 *
	 * @return string[] Array of component name strings (may be empty).
	 */
	public function network_areas( array $member ): array {
		$areas = $member['fields']['f2s_core_components'] ?? array();
		return is_array( $areas ) ? $areas : array();
	}

	/**
	 * Returns a sanitized website URL from a Mobilize member.
	 */
	public function website( array $member ): string {
		return esc_url_raw( $member['fields']['website'] ?? '' );
	}

	/**
	 * Returns a sanitized description from a Mobilize member.
	 */
	public function description( array $member ): string {
		return sanitize_textarea_field( $member['fields']['description'] ?? '' );
	}

	/**
	 * Returns true if the member has 'accepted' status in the given group.
	 */
	public function is_accepted( array $member, int $group_id ): bool {
		foreach ( $member['groups'] ?? array() as $group ) {
			if ( (int) $group['id'] === $group_id && 'accepted' === ( $group['status'] ?? '' ) ) {
				return true;
			}
		}
		return false;
	}
}
