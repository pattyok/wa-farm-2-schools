<?php
/**
 * Template part for displaying the page header of events
 *
 * @package wp_rig
 */

namespace WP_Rig\WP_Rig;

use WP_Rig\WP_Rig\Helpers;

$post_id        = get_the_ID();
$date_range     = \CarkeekEvents_Display::get_date_range_html( $post_id, '<br/>' );
$location_html  = \CarkeekEvents_Display::get_event_location_html( $post_id );
$organizer_html = \CarkeekEvents_Display::get_event_organizer_html( $post_id );
$event_link     = \CarkeekEvents_Display::get_event_link_html( $post_id );

?>
<div class="page-header-event">
	<div class="entry-title">
			<?php
			wp_rig()->make_breadcrumbs( get_post_type() );
			?>
	</div>
</div>