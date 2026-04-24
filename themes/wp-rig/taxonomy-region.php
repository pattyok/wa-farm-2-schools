<?php
/**
 * Template for the Regional Taxonomy Archive
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package wp_rig
 */

namespace WP_Rig\WP_Rig;

get_header();


?>

	<main id="primary" class="site-main">
		<?php

			get_template_part( 'template-parts/content/page-header', get_post_type(), array( 'post_type' => 'network_member' ) );
		?>
				<div class="region-archive page-content">

				<?php

				$current_term = get_queried_object();

				// 1. Get sub-terms of the current term
				$sub_terms = get_terms(
					array(
						'taxonomy'   => $current_term->taxonomy,
						'parent'     => $current_term->term_id,
						'hide_empty' => true,
					)
				);

				if ( ! empty( $sub_terms ) ) {
					?>
					<div class="region-archive__subterms">
						<div class="region-archive__subterms-title">Counties: </div>
						<ul class="no-bullets">
							<?php
							foreach ( $sub_terms as $sub_term ) {
								echo '<li><a href="' . esc_url( '#' . $sub_term->slug ) . '">' . esc_html( $sub_term->name ) . '</a></li>';
							}
							?>
						</ul>
					</div>
					<?php

					foreach ( $sub_terms as $sub_term ) {
						?>
						<div class="region-archive__subterm" id="<?php echo esc_attr( $sub_term->slug ); ?>">
						<h2><?php echo esc_html( $sub_term->name ); ?> County</h2>
						<div class="region-archive__subterm-header">
							<div class="region-archive__subterm-header-title">Member Organization</div>
							<div class="region-archive__subterm-header-areas">Area of Work</div>
						</div>
						<?php
						// 2. Query posts for each sub-term
						$args      = array(
							'post_type' => 'network_member', // Replace with your CPT if needed
							'tax_query' => array(
								array(
									'taxonomy' => $current_term->taxonomy,
									'field'    => 'term_id',
									'terms'    => $sub_term->term_id,
								),
							),
						);
						$sub_query = new \WP_Query( $args );

						if ( $sub_query->have_posts() ) {
							while ( $sub_query->have_posts() ) {
								$sub_query->the_post();
								// Output post content here (e.g., the_title())
								get_template_part( 'template-parts/content/entry-summary', get_post_type() );
							}
							wp_reset_postdata();
						}
						?>
						</div>
						<?php
					}
				}
				?>
				</div>

	</main><!-- #primary -->
<?php

get_footer();