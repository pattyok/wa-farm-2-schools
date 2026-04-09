<?php
/**
 * The template for displaying the footer
 *
 * Contains the closing of the #content div and all content after.
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package wa_farm_2_schools
 */

namespace WP_Rig\WP_Rig;

?>


	<?php get_template_part( 'template-parts/footer/info' ); ?>

</div><!-- #page -->
<?php get_template_part( 'template-parts/footer/print' ); ?>
<?php wp_footer(); ?>

</body>
</html>
