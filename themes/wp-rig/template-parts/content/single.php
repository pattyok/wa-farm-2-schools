<?php
/**
 * Template part for displaying a post
 *
 * @package wp_rig
 */

namespace WP_Rig\WP_Rig;

?>


<article id="post-<?php the_ID(); ?>" <?php post_class( 'entry page-content single-entry' ); ?>>

	<?php if ( get_post_type() == 'post' || get_post_type() == 'acf_take_action' ) : ?>
		<div class="entry-header">
			<div class="entry-title">
				<?php
				wp_rig()->make_breadcrumbs( get_post_type() );
				?>
			</div>
			<div class="entry-meta">
				<div class="entry-date"><?php the_date(); ?></div>
				<?php wp_rig()->make_social_share_links( true ); ?>

			</div>
		</div>
	<?php endif; ?>

	<?php if ( get_post_type() == 'vol_event' ) : ?>
		<?php
			$block_content = '<!-- wp:carkeek-blocks/featured-image {"align":"right"} /-->
			<!-- wp:volunteer-event-management/event-shifts /-->';
			echo do_blocks( $block_content );
		?>
	<?php endif; ?>

	<?php
		get_template_part( 'template-parts/content/entry-content', get_post_type() );
	?>
	<?php if ( is_singular( 'skgt_location' ) ) : ?>
		<?php get_template_part( 'template-parts/content/related-posts' ); ?>
	<?php endif; ?>
	<?php if ( ! empty( block_template_part( get_post_type() . '-footer' ) ) ) : ?>
		<div class="single-footer page-content">
		<?php
		block_template_part( get_post_type() . '-footer' );
		?>
		</div>
	<?php endif; ?>
	</div>


</article><!-- #post-<?php the_ID(); ?> -->


