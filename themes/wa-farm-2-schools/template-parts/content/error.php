<?php
/**
 * Template part for displaying the page content when an error has occurred
 *
 * @package wa_farm_2_schools
 */

namespace WP_Rig\WP_Rig;

?>
<section class="error">
	<?php get_template_part( 'template-parts/content/page-header' ); ?>

	<div class="page-content">
		<?php
		if ( is_home() && current_user_can( 'publish_posts' ) ) {
			?>
			<p>
				<?php
				printf(
					wp_kses(
						/* translators: 1: link to WP admin new post page. */
						__( 'Ready to publish your first post? <a href="%1$s">Get started here</a>.', 'wa-farm-2-schools' ),
						array(
							'a' => array(
								'href' => array(),
							),
						)
					),
					esc_url( admin_url( 'post-new.php' ) )
				);
				?>
			</p>
			<?php
		} elseif ( is_search() ) {
			?>
			<p>
				<?php esc_html_e( 'Sorry, but nothing matched your search terms. Please try again with some different keywords.', 'wa-farm-2-schools' ); ?>
			</p>
			<?php
		} else {
			?>
			<p>
				<?php esc_html_e( 'It seems we can&rsquo;t find what you&rsquo;re looking for. Perhaps searching can help.', 'wa-farm-2-schools' ); ?>
			</p>
			<?php
		}
		?>
		<div class="page-content-search">
		<?php
		get_search_form();
		?>
		</div>
	</div><!-- .page-content -->
</section><!-- .error -->
