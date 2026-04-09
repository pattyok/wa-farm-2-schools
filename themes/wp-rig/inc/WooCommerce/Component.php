<?php
/**
 * WP_Rig\WP_Rig\AMP\Component class
 *
 * @package wp_rig
 */

namespace WP_Rig\WP_Rig\WooCommerce;

use Tribe__Template;
use WP_Rig\WP_Rig\Component_Interface;
use WP_Rig\WP_Rig\Templating_Component_Interface;

/**
 * Class for template helpers
 *
 * Exposes template tags:
 * * `wp_rig()->get_social_links()`
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
		return 'woocommerce';
	}

	/**
	 * Need this function even though its empty.
	 */
	public function initialize() {
		add_filter( 'wp_rig_js_files', array( $this, 'woocommerce_js_file' ) );
		add_filter( 'body_class', array( $this, 'woocommerce_body_classes' ) );
		add_action( 'widgets_init', array( $this, 'woocommerce_register_sidebars' ) );

		add_action( 'woocommerce_single_product_summary', array( $this, 'woocommerce_product_title' ), 9 ); // somehow this is missing.
		// add_filter( 'woocommerce_short_description', array( $this, 'rig_woocommerce_short_description' ), 12 );

		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );

		remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10 );
		remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15 );
		remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );
		add_action( 'woocommerce_after_single_product_summary', array( $this, 'rig_wc_product_description' ), 10 );

		add_filter( 'woocommerce_output_related_products_args', array( $this, 'woocommerce_related_args' ) );
		add_filter( 'woocommerce_product_related_products_heading', array( $this, 'woocommerce_related_title' ) );
		add_filter( 'woocommerce_product_loop_end', array( $this, 'woocommerce_loop_end' ) );

		add_action( 'woocommerce_after_shop_loop', array( $this, 'woocommerce_add_shop_footer' ), 40 );

		// Dont show heading in description box on product page.
		add_filter( 'woocommerce_product_description_heading', '__return_false' );

		// Qty box on cart.
		add_filter( 'woocommerce_cart_item_quantity', array( $this, 'woocommerce_cart_qty' ) );

		add_filter( 'the_content', array( $this, 'disable_wp_auto_p' ), 0 );

		// add_action( 'pre_get_posts', array( $this, 'hide_tickets_from_search' ), 200, 2 );

		add_action( 'woocommerce_product_query', array( $this, 'custom_pre_get_posts_query' ) );

		add_filter( 'walker_nav_menu_start_el', array( $this, 'woocommerce_nav_menu_walker' ), 10, 4 );

		add_filter( 'woocommerce_add_to_cart_fragments', array( $this, 'woocommerce_add_to_cart_fragment' ), 10, 1 );
		// Support for the block editor on products
		add_filter( 'use_block_editor_for_post_type', array( $this, 'use_block_editor_for_products' ), 10, 2 );
		add_filter( 'woocommerce_taxonomy_args_product_cat', array( $this, 'enable_taxonomy_rest' ) );
		add_filter( 'woocommerce_taxonomy_args_product_tag', array( $this, 'enable_taxonomy_rest' ) );
	}

	// enable gutenberg for woocommerce
	function use_block_editor_for_products( $can_edit, $post_type ) {
		if ( $post_type == 'product' ) {
				$can_edit = true;
		}
			return $can_edit;
	}

	// enable taxonomy fields for woocommerce with gutenberg on
	function enable_taxonomy_rest( $args ) {
		$args['show_in_rest'] = true;
		return $args;
	}

	// Prevent WP from adding <p> tags on the cart page, this was messing with our qty box.
	function disable_wp_auto_p( $content ) {
		if ( is_page( 'cart' ) || is_cart() ) {
			remove_filter( 'the_content', 'wpautop' );
		}
		return $content;
	}

	/**
	 * Gets template tags to expose as methods on the Template_Tags class instance, accessible through `wp_rig()`.
	 *
	 * @return array Associative array of $method_name => $callback_info pairs. Each $callback_info must either be
	 *               a callable or an array with key 'callable'. This approach is used to reserve the possibility of
	 *               adding support for further arguments in the future.
	 */
	public function template_tags(): array {
		return array();
	}

	/** WooCommerce shop page - add body classes
	 *
	 * @param array $classes Array of current body classes.
	 */
	public function woocommerce_body_classes( $classes ) {
		if ( is_shop() ) {
			return array_merge( $classes, array( 'woocommerce-shop' ) );
		} else {
			return $classes;
		}
	}

	/** Place the Title within the WC Product content */
	public function woocommerce_product_title() {
		echo the_title( '<h1 class="product_title entry-title">', '</h1>' ); // phpcs:ignore
	}

	/** Add our Js File only on WC pages.
	 *
	 * @param array $js_files Array of existing js files to load.
	 */
	public function woocommerce_js_file( $js_files ) {
		if ( is_shop() || is_product_category() || is_product() || is_cart() ) {
			$js_files['wp-rig-woocommerce'] = array(
				'file'         => 'woo.min.js',
				'dependencies' => array( 'jquery' ),
				'in_footer'    => true,
			);
		}
		return $js_files;
	}

	/** Change related products to 3
	 *
	 * @param array $args Array a current query args.
	 */
	public function woocommerce_related_args( $args ) {
		$args = array(
			'posts_per_page' => 3,
			'columns'        => 3,
			'orderby'        => 'rand', // @codingStandardsIgnoreLine.
		);

		return $args;
	}

	/** Header on Related Products
	 *
	 * @param string $title Current Title.
	 */
	public function woocommerce_related_title( $title ) {
		$title = 'More in Store';
		return $title;
	}

	/** End of product loop add link to store */
	public function woocommerce_loop_end() {
		if ( is_shop() ) {
			return '</ul>';
		} else {
			$shop_page_url = wc_get_page_permalink( 'shop' );
			return '</ul><div class="woocommerce-loop-end"><a class="button is-style-cta" href="' . $shop_page_url . '">See all items</a></div>';
		}
	}

	/** Add sidebar to use on shop */
	public function woocommerce_register_sidebars() {
		register_sidebar(
			array(
				'name'          => esc_html__( 'Shop Page Footer', 'wp-rig' ),
				'id'            => 'shop-page-footer',
				'description'   => esc_html__( 'Displayed on the shop page', 'wp-rig' ),
				'before_widget' => '<div id="%1$s" class="shop-footer-widget widget %2$s">',
				'after_widget'  => '</div>',
				'before_title'  => '<h2 class="widget-title">',
				'after_title'   => '</h2>',
			)
		);
	}

	/** Add shop footer sidebar */
	public function woocommerce_add_shop_footer() {
		dynamic_sidebar( 'shop-page-footer' );
	}

	/** Set up qty on cart - doing it here to handle ajax
	 *
	 * @param string $quantity Html passed from template.
	 */
	public function woocommerce_cart_qty( $quantity ) {
		if ( strpos( $quantity, 'hidden' ) !== false ) {
			return '<div class="number-input number-input-fixed">' . $quantity . '</div>';
		} else {
			$quant = '<div class="number-input number-input-variable"><button class="quantity-button minus">-<span class="screen-reader-text">Subtract 1</span></button>' . trim( $quantity ) . '<button class="quantity-button plus">+<span class="screen-reader-text">Add 1</span></button></div>';
			return $quant;
		}
	}


	/** Set the description as the short description */
	public function rig_woocommerce_short_description( $excerpt ) {
		global $post;
		if ( ! is_product() ) {
			return $excerpt;
		}
		$short_description = apply_filters( 'the_content', $post->post_content );
		return $short_description;
	}

	/**
	 * Hide Tickets from search
	 * Tickets automatically get set to hidden when created, we need to make sure that hidden items do not show up in search.
	 *
	 * @param object $query current query for the search.
	 */
	public function hide_tickets_from_search( $query = false ) {
		if ( ! is_admin() && is_search() ) {
			$query->set(
				'tax_query',
				array(
					array(
						'taxonomy' => 'product_visibility',
						'terms'    => array( 'exclude-from-search' ),
						'field'    => 'slug',
						'operator' => 'NOT IN',
					),
				)
			);
		}
	}

	/**
	 * Exclude products from a particular category on the shop page
	 */
	function custom_pre_get_posts_query( $q ) {

		$tax_query = (array) $q->get( 'tax_query' );

		$tax_query[] = array(
			'taxonomy' => 'product_cat',
			'field'    => 'slug',
			'terms'    => array( 'not-for-sale' ), // Don't display products not-for-sale on the shop page.
			'operator' => 'NOT IN',
		);

		$q->set( 'tax_query', $tax_query );
	}

	/** Product description - get the product description from the content */
	public function rig_wc_product_description() {

		echo '<div class="product-description">' . get_the_content() . '</div>';
	}

	/**
	 * some fancy work to support replacing some items with icons
	 * Put the fa classes in the title attribute
	 * Also adding count to the cart
	 */
	function woocommerce_nav_menu_walker( $item_output, $item, $depth, $args ) {
		if ( $item->url == wc_get_cart_url() ) {
			$count       = WC()->cart->get_cart_contents_count();
			$span        = $count > 0 ? '</i><span class="cart-count">' . $count . '</span>' : '</i>';
			$item_output = str_replace( '</i>', $span, $item_output );
			$item_output = str_replace( '<a ', '<a class="menu-cart-link" ', $item_output );
		}
		return $item_output;
	}

	/**
	 * Ensure cart contents update when products are added to the cart via AJAX
	 */
	function woocommerce_add_to_cart_fragment( $fragments ) {

		ob_start();
		$count = WC()->cart->cart_contents_count;
		?>
		<a href="<?php echo wc_get_cart_url(); ?>" class="menu-cart-link">
		<i class="fa fa-shopping-cart"></i>
		<?php
		if ( $count > 0 ) {
			?>
		<span class="cart-count"><?php echo esc_html( $count ); ?></span>
			<?php
		}
		?>
		<span class="screen-reader-text">Shopping Cart</span></a>
		<?php

		$fragments['a.menu-cart-link'] = ob_get_clean();

		return $fragments;
	}
}


