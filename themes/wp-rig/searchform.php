<?php
/**
 * Search form template.
 *
 * @package wp_rig
 */

?>
<form id="searchform" role="search" class="search-form" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
<label for="s" class="screen-reader-text"><?php echo esc_html( __( 'Search', 'wp-rig' ) ); ?></label>
<input type="text" class="search-field" id="s" name="s" placeholder="<?php echo esc_attr( __( 'Site Search', 'wp-rig' ) ); ?>" value="<?php echo get_search_query(); ?>">
	<button type="submit" class="search-submit"><span class="screen-reader-text"><?php echo esc_html( __( 'Submit', 'wp-rig' ) ); ?></span><i class="fa-solid fa-magnifying-glass"></i></button>
</form>
