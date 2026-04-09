<?php
/**
 * WP_Rig\WP_Rig\Blocks\Component class
 *
 * @package wp_rig
 */

namespace WP_Rig\WP_Rig\Blocks;

use WP_Rig\WP_Rig\Component_Interface;
use function WP_Rig\WP_Rig\wp_rig;
use function add_action;


/**
 * Class for adding custom block styles
 *
 * @link https://developer.wordpress.org/block-editor/developers/filters/block-filters/
 */
class Component implements Component_Interface {

	/**
	 * Gets the unique identifier for the theme component.
	 *
	 * @return string Component slug.
	 */
	public function get_slug(): string {
		return 'blocks';
	}

	/**
	 * Style overrides for blocks
	 */
	public function initialize() {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_scripts' ) );
	}


	/**
	 * Register custom custom scripts for blocks
	 */
	public function enqueue_editor_scripts() {
		$js_files = array(
			'wp-rig-editor-js' => array(
				'file'         => 'editor.min.js',
				'dependencies' => array( 'wp-blocks', 'wp-dom-ready' ),
				'in_footer'    => true,
			),
		);

		$js_uri = get_theme_file_uri( '/assets/js/' );
		$js_dir = get_theme_file_path( '/assets/js/' );

		foreach ( $js_files as $handle => $data ) {
			// $src     = $css_uri . $data['file'];
			$version = wp_rig()->get_asset_version( $js_dir . $data['file'] );
			$asset   = $js_uri . wp_rig()->get_asset_path( $data['file'] );

			/*
			 * Enqueue global scripts
			 */
			wp_enqueue_script( $handle, $asset, $data['dependencies'], $version, $data['in_footer'] );
		}
	}
}
