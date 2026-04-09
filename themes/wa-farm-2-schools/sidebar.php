<?php
/**
 * The sidebar containing the main widget area
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package wa_farm_2_schools
 */

namespace WP_Rig\WP_Rig;

if ( ! wa_farm_2_schools()->is_primary_sidebar_active() ) {
	return;
}


?>
<aside id="secondary" class="primary-sidebar widget-area">
	<?php wa_farm_2_schools()->display_primary_sidebar(); ?>
</aside><!-- #secondary -->
