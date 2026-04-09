<?php
/**
 * WP_Rig\WP_Rig\Jetpack\Component class
 *
 * @package wp_rig
 */

namespace WP_Rig\WP_Rig\Jetpack;

use WP_Rig\WP_Rig\Component_Interface;
use function WP_Rig\WP_Rig\wp_rig;
use function add_action;
use function add_theme_support;
use function have_posts;
use function the_post;
use function is_search;
use function get_template_part;
use function get_post_type;

/**
 * Class for adding Jetpack plugin support.
 */
class Component implements Component_Interface {

	/**
	 * Gets the unique identifier for the theme component.
	 *
	 * @return string Component slug.
	 */
	public function get_slug(): string {
		return 'jetpack';
	}

	/**
	 * Adds the action and filter hooks to integrate with WordPress.
	 */
	public function initialize() {
		add_action( 'after_setup_theme', array( $this, 'action_add_jetpack_support' ) );
		add_action( 'loop_start', array( $this, 'jptweak_remove_share' ) );
		add_filter( 'jetpack_images_get_images', array( $this, 'posts_custom_image' ), 10, 3 );
		add_filter( 'infinite_scroll_js_settings', array( $this, 'filter_jetpack_infinite_scroll_js_settings' ) );
	}

	/**
	 * Adds theme support for the Jetpack plugin.
	 *
	 * See: https://jetpack.com/support/infinite-scroll/
	 * See: https://jetpack.com/support/responsive-videos/
	 * See: https://jetpack.com/support/content-options/
	 */
	public function action_add_jetpack_support() {

		// Add theme support for Infinite Scroll.
		add_theme_support(
			'infinite-scroll',
			array(
				'container' => 'primary',
				'footer'    => 'page',
				'render'    => function () {
					while ( have_posts() ) {
						the_post();
						if ( is_search() ) {
							get_template_part( 'template-parts/content/entry', 'search' );
						} else {
							get_template_part( 'template-parts/content/entry', get_post_type() );
						}
					}
				},
			)
		);

		// Add theme support for Responsive Videos.
		add_theme_support( 'jetpack-responsive-videos' );

		// Add theme support for Content Options.
		add_theme_support(
			'jetpack-content-options',
			array(
				'post-details' => array(
					'stylesheet' => 'wp-rig-content',
					'date'       => '.posted-on',
					'categories' => '.category-links',
					'tags'       => '.tag-links',
					'author'     => '.posted-by',
					'comment'    => '.comments-link',
				),
			)
		);
	}

	/**
	 * Remove the jetpack share buttons that are autoplaced, We manually place them at the top of the page in the single template.
	 */
	public function jptweak_remove_share() {
		remove_filter( 'the_content', 'sharing_display', 19 );
		remove_filter( 'the_excerpt', 'sharing_display', 19 );
		if ( class_exists( 'Jetpack_Likes' ) ) {
			remove_filter( 'the_content', array( \Jetpack_Likes::init(), 'post_likes' ), 30, 1 );
		}
	}

	/**
	 * Get a random image if no image is found for jetpack functions
	 *
	 * @param array  $media url of current item.
	 * @param string $post_id current post.
	 * @param array  $args args object.
	 */
	public function posts_custom_image( $media, $post_id, $args ) {
		if ( $media ) {
			return $media;
		} else {
			$permalink = get_permalink( $post_id );
			$random    = wp_rig()->get_random_thumbnail();
			$url       = apply_filters( 'jetpack_photon_url', $random );

			return array(
				array(
					'type' => 'image',
					'from' => 'custom_fallback',
					'src'  => esc_url( $url ),
					'href' => $permalink,
				),
			);
		}
	}

	/**
	 * Update text on load more button.
	 *
	 * @param array $settings current settings object.
	 */
	public function filter_jetpack_infinite_scroll_js_settings( $settings ) {
		$settings['text'] = __( 'Load more<i class="icon-plus"></i>', 'wp-rig' );

		return $settings;
	}
}
