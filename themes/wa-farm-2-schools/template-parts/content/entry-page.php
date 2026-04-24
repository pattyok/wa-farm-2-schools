<?php
/**
 * Template part for displaying a post
 *
 * @package wa_farm_2_schools
 */

namespace WP_Rig\WP_Rig;

?>

<article id="post-<?php the_ID(); ?>" <?php post_class( 'entry page-content is-layout-constrained' ); ?>>

	<?php the_content(); ?>

</article><!-- #post-<?php the_ID(); ?> -->
