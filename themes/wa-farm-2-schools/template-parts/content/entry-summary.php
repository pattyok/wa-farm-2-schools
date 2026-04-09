<?php
/**
 * Template part for displaying a post's summary
 *
 * @package wa_farm_2_schools
 */

namespace WP_Rig\WP_Rig;

?>

<div class="entry-summary">
	<?php echo wp_kses_post( wa_farm_2_schools()->get_custom_excerpt( 30 ) ); ?>
</div><!-- .entry-summary -->
