<?php
/**
 * WP_Rig\WP_Rig\Customizer\Component class
 *
 * @package wa_farm_2_schools
 */

namespace WP_Rig\WP_Rig\Customizer;

use WP_Rig\WP_Rig\Component_Interface;
use function WP_Rig\WP_Rig\wa_farm_2_schools;
use WP_Customize_Manager;
use function add_action;
use function bloginfo;
use function wp_enqueue_script;
use function get_theme_file_uri;

/**
 * Class for managing Customizer integration.
 */
class Component implements Component_Interface {

	/**
	 * Gets the unique identifier for the theme component.
	 *
	 * @return string Component slug.
	 */
	public function get_slug(): string {
		return 'customizer';
	}

	/**
	 * Adds the action and filter hooks to integrate with WordPress.
	 */
	public function initialize() {
		add_action( 'customize_register', array( $this, 'action_customize_register' ) );
		add_action( 'customize_preview_init', array( $this, 'action_enqueue_customize_preview_js' ) );
	}

	/**
	 * Adds postMessage support for site title and description, plus a custom Theme Options section.
	 *
	 * @param WP_Customize_Manager $wp_customize Customizer manager instance.
	 */
	public function action_customize_register( WP_Customize_Manager $wp_customize ) {
		$wp_customize->get_setting( 'blogname' )->transport         = 'postMessage';
		$wp_customize->get_setting( 'blogdescription' )->transport  = 'postMessage';
		$wp_customize->get_setting( 'header_textcolor' )->transport = 'postMessage';

		if ( isset( $wp_customize->selective_refresh ) ) {
			$wp_customize->selective_refresh->add_partial(
				'blogname',
				array(
					'selector'        => '.site-title a',
					'render_callback' => function () {
						bloginfo( 'name' );
					},
				)
			);
			$wp_customize->selective_refresh->add_partial(
				'blogdescription',
				array(
					'selector'        => '.site-description',
					'render_callback' => function () {
						bloginfo( 'description' );
					},
				)
			);
		}

		// replace title_tagline with multiple options.
		$wp_customize->remove_control( 'display_header_text' );

		$wp_customize->add_setting(
			'title_tagline_display',
			array(
				'type'      => 'theme_mod',
				'default'   => 'title_tagline',
				'transport' => 'postMessage',
			)
		);

		$wp_customize->add_control(
			'title_tagline_display',
			array(
				'type'    => 'radio',
				'section' => 'title_tagline',
				'choices' => array(
					'title_tagline' => __( 'Display Title & Tagline', 'wa-farm-2-schools' ),
					'title_only'    => __( 'Display Title Only', 'wa-farm-2-schools' ),
					'tagline_only'  => __( 'Display Tagline Only', 'wa-farm-2-schools' ),
					'none'          => __( 'Hide Title & Tagline', 'wa-farm-2-schools' ),
				),
			)
		);

		/**
		 * Theme options.
		 */
		$wp_customize->add_section(
			'theme_options',
			array(
				'title'    => __( 'Theme Options', 'wa-farm-2-schools' ),
				'priority' => 130, // Before Additional CSS.
			)
		);

		// Blog Header Settings.
		$wp_customize->add_section(
			'blog_customizer',
			array(
				'title'    => __( 'Blog', 'wa-farm-2-schools' ),
				'priority' => 50,
			)
		);
		// Add Settings.
		$wp_customize->add_setting(
			'blog_header_image',
			array(
				'transport' => 'refresh',
				'height'    => 325,
			)
		);

		$wp_customize->add_setting(
			'blog_header_intro',
			array(
				'sanitize_callback' => 'wp_kses_post',
			)
		);

		// Add Controls.
		$wp_customize->add_control(
			new \WP_Customize_Media_Control(
				$wp_customize,
				'blog_header_image_control',
				array(
					'label'    => __( 'Blog Header Image', 'wa-farm-2-schools' ),
					'section'  => 'blog_customizer',
					'settings' => 'blog_header_image',
				)
			)
		);

		$wp_customize->add_control(
			'blog_header_intro',
			array(
				'label'   => esc_html__( 'Intro Text', 'wa-farm-2-schools' ),
				'section' => 'blog_customizer',
				'type'    => 'textarea',
			)
		);

		$wp_customize->selective_refresh->add_partial(
			'blog_header_intro',
			array(
				'selector' => '.blog-intro', // You can also select a css class.
			)
		);
	}

	/**
	 * Enqueues JavaScript to make Customizer preview reload changes asynchronously.
	 */
	public function action_enqueue_customize_preview_js() {
		wp_enqueue_script(
			'wa-farm-2-schools-customizer',
			get_theme_file_uri( '/assets/js/customizer.min.js' ),
			array( 'customize-preview' ),
			wa_farm_2_schools()->get_asset_version( get_theme_file_path( '/assets/js/customizer.min.js' ) ),
			true
		);
	}
}
