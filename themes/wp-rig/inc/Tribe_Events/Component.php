<?php
/**
 * WP_Rig\WP_Rig\AMP\Component class
 *
 * @package wp_rig
 */

namespace WP_Rig\WP_Rig\Tribe_Events;

use Tribe__Template;
use WP_Rig\WP_Rig\Component_Interface;
use WP_Rig\WP_Rig\Templating_Component_Interface;

/**
 * Class for template helpers
 *
 * Exposes template tags:
 * * `wp_rig()->get_social_links()`
 *
 * @link https://wordpress.org/plugins/amp/
 */
class Component implements Component_Interface, Templating_Component_Interface {

	/**
	 * Gets the unique identifier for the theme component.
	 *
	 * @return string Component slug.
	 */
	public function get_slug(): string {
		return 'tribe_events';
	}

	/**
	 * Need this function even though its empty.
	 */
	public function initialize() {
		add_filter( 'tribe_events_editor_default_template', array( $this, 'default_blocks' ), 11 );
		/**
		 * Remove "Events" menu from WordPress admin bar
		 *
		 * @see https://theeventscalendar.com/knowledgebase/k/remove-events-from-the-wordpress-admin-bar/
		 */
		define( 'TRIBE_DISABLE_TOOLBAR_ITEMS', true );
	}

	/**
	 * Gets template tags to expose as methods on the Template_Tags class instance, accessible through `wp_rig()`.
	 *
	 * @return array Associative array of $method_name => $callback_info pairs. Each $callback_info must either be
	 *               a callable or an array with key 'callable'. This approach is used to reserve the possibility of
	 *               adding support for further arguments in the future.
	 */
	public function template_tags(): array {
		return array(
			'is_tribe_view' => array( $this, 'is_tribe_view' ),
		);
	}

	/**
	 * Useful helper function to detect Tribe Events pages in your theme
	 *
	 * Modified slightly from the link
	 *
	 * Usage: \MRW\TEC\is_tribe_view()
	 *
	 * @link https://gist.github.com/samkent/b98bd3c9b28426b8461bc1417adf7b5d
	 */
	public function is_tribe_view() {

		return (
			function_exists( 'tribe_is_event' ) &&
			tribe_is_event()
			) ||
			(
			function_exists( 'tribe_is_event_category' ) &&
			tribe_is_event_category()
			) ||
			(
			function_exists( 'tribe_is_in_main_loop' ) &&
			tribe_is_in_main_loop()
			) ||
			(
			function_exists( 'tec_is_view' ) &&
			tec_is_view()
			) ||
			(
			function_exists( 'tribe_is_venue' ) &&
			tribe_is_venue()
			) ||
			(
			function_exists( 'tribe_is_organizer' ) &&
			tribe_is_organizer()
			) ||
			'tribe_events' === get_post_type() ||
			is_singular( 'tribe_events' );
	}


	/**
	 * Change default blocks when creating a new event with the Block Editor
	 *
	 * @see https://support.theeventscalendar.com/807454-Change-the-Default-Event-Template-in-Block-Editor
	 */
	public function default_blocks( $template ) {
		$template = array(
			array( 'tribe/event-datetime' ),
			array(
				'core/columns',
				array(),
				array(
					array(
						'core/column',
						array(),
						array(
							array(
								'core/paragraph',
								array(
									'placeholder' => __( 'Add Description…', 'the-events-calendar' ),
								),
							),
						),
					),
					array(
						'core/column',
						array(),
						array(
							array( 'carkeek-blocks/featured-image' ),
						),
					),
				),
			),
			array( 'tribe/event-website' ),
			array( 'tribe/event-venue' ),
		);
		return $template;
	}
}
