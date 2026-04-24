<?php
/**
 * Template part for displaying the header navigation menu
 *
 * @package wa_farm_2_schools
 */

namespace WP_Rig\WP_Rig;

if ( ! wa_farm_2_schools()->is_primary_nav_menu_active() ) {
	return;
}

?>

<nav id="site-navigation" class="main-navigation nav--toggle-sub nav--toggle-small" aria-label="<?php esc_attr_e( 'Main menu', 'wa-farm-2-schools' ); ?>">


	<button class="header-toggle menu-toggle hamurger hamburger--spring" aria-label="<?php esc_attr_e( 'Open menu', 'wa-farm-2-schools' ); ?>" aria-controls="primary-menu" aria-expanded="false">
		<span class="hamburger-box">
			<span class="hamburger-inner"></span>
		</span>
		<span class="menu-toggle-label menu-closed"><?php esc_html_e( 'Menu', 'wa-farm-2-schools' ); ?></span>
		<span class="menu-toggle-label menu-open"><?php esc_html_e( 'Close', 'wa-farm-2-schools' ); ?></span>

	</button>


	<div class="primary-menu-container" id="primary-menu-container">
		<?php wa_farm_2_schools()->display_primary_nav_menu( array( 'menu_id' => 'primary-menu' ) ); ?>

		</div><!-- .primary-menu-container -->
</nav><!-- #site-navigation -->
