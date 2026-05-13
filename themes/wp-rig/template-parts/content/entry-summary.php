<?php
/**
 * Template part for displaying a post's summary
 *
 * @package wp_rig
 */

namespace WP_Rig\WP_Rig;

?>

<div class="entry-summary">
	<?php echo wp_kses_post( wp_rig()->get_custom_excerpt( 30, false ) ); ?>
</div><!-- .entry-summary -->
