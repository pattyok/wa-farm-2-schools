<?php
/**
 * Template part for displaying a post's content
 *
 * @package wa_farm_2_schools
 */

namespace WP_Rig\WP_Rig;

?>

		<?php
		the_content(
			sprintf(
				wp_kses(
					/* translators: %s: Name of current post. Only visible to screen readers */
					__( 'Continue reading<span class="screen-reader-text"> "%s"</span>', 'wa-farm-2-schools' ),
					array(
						'span' => array(
							'class' => array(),
						),
					)
				),
				get_the_title()
			)
		);

