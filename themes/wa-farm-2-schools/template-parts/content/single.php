<?php

/**
 * Template part for displaying a post
 *
 * @package wa_farm_2_schools
 */

namespace WP_Rig\WP_Rig;

?>


<article id="post-<?php the_ID(); ?>" <?php post_class( 'entry page-content single-entry' ); ?>>

	<?php if ( get_post_type() === 'post' ) : ?>
		<div class="entry-header">
			<div class="entry-title">
				<?php
				wa_farm_2_schools()->make_breadcrumbs( get_post_type() );
				?>
			</div>
			<div class="entry-meta">
				<div class="entry-details">
					<div class="entry-date"><?php the_date(); ?></div>
					<?php
					$contact  = array();
					$location = '';
					if ( function_exists( 'get_field' ) ) :
						if ( ! empty( get_field( 'contact_name' ) ) ) :
							$contact[] = get_field( 'contact_name' );
						endif;
						if ( ! empty( get_field( 'contact_organization' ) ) ) :
							$contact[] = get_field( 'contact_organization' );
						endif;
						if ( ! empty( get_field( 'contact_email' ) ) ) :
							$contact[] = '<a href="mailto:' . get_field( 'contact_email' ) . '">' . get_field( 'contact_email' ) . '</a>';
						endif;
						$location = get_field( 'story_location' );
					endif;
					?>
					<?php if ( ! empty( $location ) ) : ?>
						<div class="entry-location">

							<span class="meta-label">Location:</span>
							<?php echo $location; ?>

						</div>
					<?php endif; ?>
					<?php if ( ! empty( $contact ) ) : ?>
						<div class="entry-contact">

							<span class="meta-label">Contact:</span>
							<?php echo implode( ', ', $contact ); ?>
						</div>
					<?php endif; ?>

				</div>
				<?php wa_farm_2_schools()->make_social_share_links( true ); ?>

			</div>
		</div>
	<?php endif; ?>
	<?php
	get_template_part( 'template-parts/content/entry-content', get_post_type() );
	?>



</article><!-- #post-<?php the_ID(); ?> -->

<?php if ( get_post_type() === 'post' ) : ?>
	<?php get_template_part( 'template-parts/content/entry-footer', get_post_type() ); ?>
<?php endif; ?>
<?php if ( ! empty( block_template_part( get_post_type() . '-footer' ) ) ) : ?>

	<?php
	block_template_part( get_post_type() . '-footer' );
	?>

<?php endif; ?>
</div>