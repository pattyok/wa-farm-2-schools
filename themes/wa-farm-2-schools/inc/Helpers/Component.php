<?php
/**
 * WP_Rig\WP_Rig\Helpers\Component class
 *
 * @package wa_farm_2_schools
 */

namespace WP_Rig\WP_Rig\Helpers;

use WP_Rig\WP_Rig\Component_Interface;
use WP_Rig\WP_Rig\Templating_Component_Interface;

/**
 * Class for template helpers
 *
 * Exposes template tags:
 * * `wa_farm_2_schools()->get_social_links()`
 *
 * @link https://wordpress.org/plugins/amp/
 */
class Component implements Component_Interface, Templating_Component_Interface {

	/**
	 * Gets the unique identifier for the theme component.
	 *
	 * @return string Component slug.
	 */
	public function get_slug(): string {
		return 'helpers';
	}

	/**
	 * Need this function even though its empty
	 */
	public function initialize() {
		add_action( 'init', array( $this, 'sitefooter_add_custom_shortcode' ) );
		add_action( 'init', array( $this, 'register_template_layouts' ) );
		add_filter( 'excerpt_more', array( $this, 'my_theme_excerpt_more' ) );
		add_filter( 'excerpt_length', array( $this, 'my_theme_excerpt_length' ) );
		add_action( 'acf/init', array( $this, 'acf_google_maps_api' ) );

		add_filter( 'term_link', array( $this, 'update_term_link' ), 10, 3 );
		add_action( 'ck_custom_archive_layout_modal_dialog__after_title', array( $this, 'custom_archive_layout_modal_dialog_after_title' ) );
		add_action( 'ck_custom_archive_vol_event__meta_before_title', array( $this, 'custom_vol_event_archive_meta_before_title' ), 10, 2 );

		add_filter( 'carkeek_block_custom_post_layout_vol_event__query_args', array( $this, 'carkeek_block_event_archive_query' ), 10, 2 );

		add_filter( 'carkeek_events_location_display', array( $this, 'carkeek_events_block_location_display' ), 10, 2 );
		add_filter( 'carkeek_events_block_before_slots', array( $this, 'carkeek_events_block_before_slots' ), 10, 3 );
	}



	/**
	 * Customize excerpt more ending
	 *
	 * @param string $more current value.
	 */
	public function my_theme_excerpt_more( $more ) {
		return '&hellip;';
	}

	/**
	 * Modify the excerpt character length
	 *
	 * @param Integer $length given length.
	 *
	 * @return Integer
	 */
	public function my_theme_excerpt_length( $length ) {

		return 55;
	}


	/**
	 * Gets template tags to expose as methods on the Template_Tags class instance, accessible through `wa_farm_2_schools()`.
	 *
	 * @return array Associative array of $method_name => $callback_info pairs. Each $callback_info must either be
	 *               a callable or an array with key 'callable'. This approach is used to reserve the possibility of
	 *               adding support for further arguments in the future.
	 */
	public function template_tags(): array {
		return array(
			'get_social_links'        => array( $this, 'get_social_links' ),

			'get_random_images_array' => array( $this, 'get_random_images_array' ),
			'get_custom_excerpt'      => array( $this, 'get_custom_excerpt' ),
			'make_social_share_links' => array( $this, 'make_social_share_links' ),
			'make_breadcrumbs'        => array( $this, 'make_breadcrumbs' ),
			'has_page_thumbnail'      => array( $this, 'has_page_thumbnail' ),
			'get_top_level_parent'    => array( $this, 'get_top_level_parent' ),
		);
	}

	/**
	 * Get Social links as defined in the Theme options
	 *
	 * @param string $styles Optional. Css classes to add to component.
	 * @return string Whether the AMP plugin is active and the current request is for an AMP endpoint.
	 */
	public function get_social_links( $styles = null ) {
		if ( ! function_exists( 'get_field' ) ) {
			return;
		}
		$social = get_field( 'social_icons', 'option' );
		$html   = '';
		if ( ! empty( $social ) ) {
			$html = '<ul class="no-list social-links ' . $styles . '">';
			foreach ( $social as $soc ) {
				if ( ! empty( $soc['link'] ) ) {
					$html .= '<li><a href="' . $soc['link'] . '" title="' . $soc['link_title'] . '" target="_blank"><i class="icon-' . $soc['type'] . '"></i></a></li>';
				}
			}
			$html .= '</ul>';
		}
		return $html;
	}


	/** Get Random Images
	 *
	 * Can be used to generate a random from a group if needed.
	 */
	public function get_random_images_array() {
		if ( ! function_exists( 'get_field' ) ) {
			return;
		}
		$images = get_field( 'placeholder_images', 'options' );
		$nbrs   = range( 0, count( $images ) - 1 );
		shuffle( $nbrs );
		return $nbrs;
	}


	/**
	 * Customize the length of an excerpt
	 *
	 * @param integer $limit the number of words to return.
	 * @param boolean $read_more if we should include read more in the excerpt.
	 */
	public function get_custom_excerpt( $limit, $read_more = true ) {
		$excerpt = explode( ' ', get_the_excerpt(), $limit );
		if ( count( $excerpt ) >= $limit ) {
			array_pop( $excerpt );
			$excerpt = implode( ' ', $excerpt ) . '...';
		} else {
			$excerpt = implode( ' ', $excerpt );
		}
		$excerpt = preg_replace( '`[[^]]*]`', '', $excerpt );
		if ( true === $read_more ) {
			$excerpt .= '<a class="more-link arrow-link" href="' . get_the_permalink() . '">Read More</a>';
		}
		return $excerpt;
	}

	/**
	 * Make New Window Script
	 */
	private function make_new_window() {
		return "onclick=\"javascript:window.open(this.href, '_blank', 'menubar=no,toolbar=no,resizable=yes,scrollbars=yes,height=300,width=600');return false;\"";
	}

	/**
	 * Make FB Links
	 *
	 * @param string $text optional text before the icon.
	 */
	private function make_fb_button( $text = null ) {
		$url = get_the_permalink();

		$fb_link = '<a class="share-link" href="https://www.facebook.com/sharer/sharer.php?u=' . urlencode( $url ) . '"' . $this->make_new_window() . ' title="Share on Facebook"><i class="icon-facebook" aria-hidden="true"></i> ' . $text . '</a>'; // phpcs:ignore.
		return $fb_link;
	}

	/**
	 * Make Twttter Links
	 *
	 * @param string $text optional text before the icon.
	 */
	private function make_twitter_button( $text = null ) {
		$url   = get_the_permalink();
		$title = get_the_title();
		$tweet = '<a class="share-link" href="http://twitter.com/intent/tweet?text=' . $title . '&url=' . $url . '"' . $this->make_new_window() . ' title="Share on Twitter"><i class="icon-x-twitter" aria-hidden="true"></i>' . $text . '</a>';
		return $tweet;
	}

	/**
	 * Make Email Links
	 *
	 * @param string $text optional text before the icon.
	 */
	private function make_email_button( $text = null ) {
		$url   = get_the_permalink();
		$title = get_the_title();
		$email = '<a class="share-link" href="mailto:?subject=' . $title . '&body=' . urlencode( $url ) . '" title="Share Via Email"><i class="icon-mail"  aria-hidden="true"></i> ' . $text . '</a>'; // phpcs:ignore.
		return $email;
	}

	/**
	 * Make Email Links
	 *
	 * @param string $text optional text before the icon.
	 */
	private function make_linkedin_button( $text = null ) {
		$url   = get_the_permalink();
		$title = get_the_title();
		$tweet = '<a class="share-link" href="http://www.linkedin.com/shareArticle?mini=true&url=' . $title . '&url=' . $url . '" ' . $this->make_new_window() . ' title="Share on LinkedIn"><i class="icon-linkedin" aria-hidden="true"></i>' . $text . '</a>';
		return $tweet;
	}
	/**
	 * Make Bluesky Links
	 *
	 * @param string $text optional text before the icon.
	 */
	private function make_bluesky_button( $text = null ) {
		$url   = get_the_permalink();
		$title = get_the_title();
		$tweet = '<a class="share-link" href="https://bsky.app/intent/compose?text=' . $title . '&url=' . $url . '" ' . $this->make_new_window() . ' title="Share on Bluesky"><i class="icon-bluesky" aria-hidden="true"></i>' . $text . '</a>';
		return $tweet;
	}



	/**
	 * Make Print Link
	 *
	 * @param string $text optional text before the icon.
	 */
	private function make_print_button( $text = null ) {
		$email = '<a class="share-link print-this-js" href="#" title="Print this Page"><i class="icon-print" aria-hidden="true"></i> ' . $text . '</a>';
		return $email;
	}

	/**
	 * Make Social Links
	 *
	 * @param boolean $echo whether to echo or return the the html.
	 */
	public function make_social_share_links( $echo = false ) {
		$links = '<ul class="social-share-links list-inline">
		<li class="list-inline-item social-share-links__label label">Share: </li>
		<li class="list-inline-item">' . $this->make_fb_button() . '</li>
		<li class="list-inline-item">' . $this->make_twitter_button() . '</li>
		<li class="list-inline-item">' . $this->make_bluesky_button() . '</li>
		<li class="list-inline-item">' . $this->make_linkedin_button() . '</li>
		<li class="list-inline-item">' . $this->make_email_button() . '</li>
		<li class="list-inline-item">' . $this->make_print_button() . '</li>
	</ul>';
		if ( $echo ) {
			echo $links; // phpcs:ignore
		} else {
			return $links;
		}
	}

	/**
	 * Page has Thumbnail
	 * If current page does not have thumbnail, check up ancestor chain for one.
	 *
	 * @param integer $page_id  id of page.
	 * returns boolean.
	 */
	public function has_page_thumbnail( $page_id ) {

		if ( has_post_thumbnail( $page_id ) ) {
			return true;
		} else {
			$top = $this->get_top_level_parent( $page_id );
			if ( has_post_thumbnail( $top ) ) {
				return true;
			} else {
				return false;
			}
		}
	}

	/**
	 * Get Top Level Parent
	 * Find the top-level parent of a page
	 *
	 * @param integer $page_id  id of page.
	 * @return img tag to use from the parent in the chain.
	 */
	public function get_top_level_parent( $page_id ) {
		if ( 'post' === get_post_type( $page_id ) ) {
			$parent = get_option( '_news_archive_page' );
			return $parent;
		} else {
			$parent = wp_get_post_parent_id( $page_id );

			$top = $parent;
			while ( 0 !== $parent ) {
				$top    = $parent;
				$parent = wp_get_post_parent_id( $parent );
			}
			return $top;
		}
	}



	/**
	 * Put the @Copyright in a shortcode so we can put all footer copy in the widgets
	 * Optionally include the site name to override the default
	 *
	 * [site_copy][/site_copy]
	 */
	public function sitefooter_add_custom_shortcode() {
		add_shortcode( 'site_copy', array( $this, 'site_footer_do_custom_shortcode' ) );
	}

	/**
	 * Put the @Copyright in a shortcode so we can put all footer copy in the widgets
	 * Optionally include the site name to override the default
	 * [site_copy credits=true /]
	 *
	 * @param array  $atts attributes to pass - credits true or false default true.
	 * @param string $content Content will override site name.
	 */
	public function site_footer_do_custom_shortcode( $atts, $content ) {
		$atts    = shortcode_atts(
			array(
				'credits' => true,
			),
			$atts,
			'site_copy'
		);
		$content = empty( $content ) ? get_bloginfo( 'name' ) . ' ' : $content;
		$html    = '<div class="site-copy"><span class="site-info">&copy; ' . esc_attr( gmdate( 'Y' ) ) . ' ' . $content . '</span>';
		if ( 'true' == $atts['credits'] ) {
				$html .= ' <a class="info-popover" href="#" data-popover="site-credit-pop">Site Credits</a>
							<div class="gpopover no-list" id="site-credit-pop">
								<ul class="no-list">
										<li class="contact-info">Website Design: <a href="http://beansnrice.com" target="_blank">Beans n\' Rice</a></li>
										<li class="contact-info">Website Development: <a href="https://carkeekstudios.com"  target="_blank">Carkeek Studios</a></li>
									</ul>
							</div>';
		}
		$html .= '</div>';
		return $html;
	}



	/** Make custom header breadcrumb
	 *
	 * @param string $post_type - post type to check for landing page. If not set, will use current post type.
	 */
	public function make_breadcrumbs( $post_type = null ) {
		global $post;
		if ( empty( $post_type ) ) {
			$post_type = $post->post_type;
		}
		$post_types = array();
		if ( function_exists( 'get_field' ) ) {
			$landing_pages = get_field( 'acf_landing_page', 'options' ); // set this up as a repeater with post type and landing page.
			$post_types = wp_list_pluck( $landing_pages, 'landing_page', 'post_type' );
		}
		$parent     = 0; // default to no parent
		$title_id   = $post->ID;
		$is_h1      = true;
		if ( isset( $post_types[ $post_type ] ) ) {
			$parent      = $post_types[ $post_type ];
			$link_parent = true;
		}

		if ( 0 !== $parent && ! empty( $parent ) ) {
			if ( $link_parent ) {
				echo '<a class="entry-parent-link all-caps" href="' . esc_url( get_the_permalink( $parent ) ) . '">' . wp_kses_post( get_the_title( $parent ) ) . '</a>';
			} else {
				echo '<div class="entry-parent-link all-caps">' . wp_kses_post( get_the_title( $parent ) ) . '</div>';
			}
		}
		if ( $is_h1 ) {
			echo '<h1>' . esc_html( get_the_title( $title_id ) ) . '</h1>';
		} else {
			echo '<div class="h1"><a href="' . esc_url( get_the_permalink( $title_id ) ) . '">' . esc_html( get_the_title( $title_id ) ) . '</a></div>';
		}
	}

	/**
	 * Get the google maps api key from settings page if using.
	 */
	public function acf_google_maps_api() {
		$key = get_field( 'acf_google_maps_api_key', 'options' );
		if ( ! empty( $key ) ) {
			acf_update_setting( 'google_api_key', $key );
		}
	}


	public function update_term_link( $termlink, $term, $taxonomy ) {
		if ( 'services' === $taxonomy && is_object( $term ) && ! empty( $term->slug ) ) {
			$termlink = home_url( '/our-services/' . $term->slug );
		}
		return $termlink;
	}

	/** Add Job Title to Modal Dialog Content */
	public function custom_archive_layout_modal_dialog_after_title() {
		$job_title = get_field( 'job_title' );
		if ( ! empty( $job_title ) ) {
			echo '<p class="ck-modal-item-job-title">' . esc_html( $job_title ) . '</p>';
		}
		return;
	}

	/** Add Event Date before title for Volunteer Events */
	public function custom_vol_event_archive_meta_before_title( $meta_before, $data ) {
		$event_date  = get_field( 'event_start_date_time' );
		$meta_before = '';
		if ( has_term( 'featured', 'skgt_event_category' ) ) {
			$meta_before .= '<div class="ck-item-event-featured">Featured Volunteer Opportunity</div>';
		}
		if ( ! empty( $event_date ) ) {
			// Format date Day, Month Date
			$event_date = new \DateTime( $event_date );
			if ( $event_date ) {
				$meta_before .= '<div class="ck-item-event-date">' . esc_html( $event_date->format( 'l, M j' ) ) . '</div>';
			}
		}
		return $meta_before;
	}

	/** Add Featured Event Text before event if in list view */
	public function carkeek_events_block_before_slots( $content, $post_id, $data ) {
		if ( 'list' === $data['postLayout'] ) {
			if ( has_term( 'featured', 'carkeek_event_category', $post_id ) ) {
				$content = '<div class="ck-event-block-featured">Featured Event</div>';
			} elseif ( has_term( 'featured', 'skgt_event_category', $post_id ) ) {
				$content = '<div class="ck-event-block-featured">Featured Volunteer Opportunity</div>';
			}
		}
		return $content;
	}

	/** Limit Event query to Events with and end date in the future */
	public function carkeek_block_event_archive_query( $args, $data ) {
		$args['meta_key']   = 'event_start_date_time';
		$args['meta_query'] = array(
			'key'     => 'event_start_date_time',
			'value'   => current_time( 'Y-m-d H:i:s' ),
			'compare' => '>=',
			'type'    => 'DATETIME',
		);
		return $args;
	}

	// ** Customize Location Display for Events Block - add class arrow-link if the html contains a link */
	public function carkeek_events_block_location_display( $location_html, $post_id ) {
		if ( strpos( $location_html, '<a ' ) !== false ) {
			$location_html = str_replace( '<a ', '<a class="arrow-link" ', $location_html );
		}
		return $location_html;
	}

	/** Register template layouts for this component */
	public function register_template_layouts() {
		$post_type_object = get_post_type_object( 'carkeek_event' );
		if ( $post_type_object ) {
			$post_type_object->template = array(
				array( 'carkeek-blocks/featured-image', array( 'align' => 'right' ) ),
				array(
					'core/group',
					array(
						'layout' => array( 'type' => 'constrained' ),
					),
					array(
						array( 'carkeek-events/event-details' ),
						array(
							'core/buttons',
							array(),
							array(
								array(
									'core/button',
									array(
										'backgroundColor' => 'accent',
									),
								),
							),
						),
						array(
							'core/paragraph',
							array(
								'placeholder' => 'Add event description here...',
							),
						),
					),
				),

			);
		}
	}
}
