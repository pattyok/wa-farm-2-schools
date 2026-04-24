<?php
/**
 * Template part for displaying the header navigation menu
 *
 * @package wp_rig
 */

namespace WP_Rig\WP_Rig;

if ( ! wp_rig()->is_primary_nav_menu_active() ) {
	return;
}

?>

<nav id="site-navigation" class="main-navigation nav--toggle-sub nav--toggle-small" aria-label="<?php esc_attr_e( 'Main menu', 'wp-rig' ); ?>">


	<button class="header-toggle menu-toggle hamurger hamburger--spring" aria-label="<?php esc_attr_e( 'Open menu', 'wp-rig' ); ?>" aria-controls="primary-menu" aria-expanded="false">
		<span class="hamburger-box">
			<span class="hamburger-inner"></span>
		</span>
		<span class="menu-toggle-label menu-closed"><?php esc_html_e( 'Menu', 'wp-rig' ); ?></span>
		<span class="menu-toggle-label menu-open"><?php esc_html_e( 'Close', 'wp-rig' ); ?></span>

	</button>


	<div class="primary-menu-container" id="primary-menu-container">
		<?php wp_rig()->display_primary_nav_menu( array( 'menu_id' => 'primary-menu' ) ); ?>

		</div><!-- .primary-menu-container -->
</nav><!-- #site-navigation -->
