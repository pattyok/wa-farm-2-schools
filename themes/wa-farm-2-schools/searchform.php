<?php
/**
 * Search form template.
 *
 * @package wa_farm_2_schools
 */

?>
<form id="searchform" role="search" class="search-form" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
<label for="s" class="screen-reader-text"><?php echo esc_html( __( 'Search', 'wa-farm-2-schools' ) ); ?></label>
<input type="text" class="search-field" id="s" name="s" placeholder="<?php echo esc_attr( __( 'Site Search', 'wa-farm-2-schools' ) ); ?>" value="<?php echo get_search_query(); ?>">
	<button type="submit" class="search-submit"><span class="screen-reader-text"><?php echo esc_html( __( 'Submit', 'wa-farm-2-schools' ) ); ?></span><i class="fa-solid fa-magnifying-glass"></i></button>
</form>
