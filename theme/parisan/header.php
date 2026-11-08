<?php
/**
 * Site header.
 *
 * @package Parisan
 */

defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#main-content"><?php esc_html_e( 'رفتن به محتوای اصلی', 'parisan' ); ?></a>
<header class="site-header">
	<div class="container header-inner">
		<div class="site-branding">
			<?php if ( has_custom_logo() ) : ?>
				<?php the_custom_logo(); ?>
			<?php else : ?>
				<a class="site-title" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
					<?php bloginfo( 'name' ); ?>
				</a>
			<?php endif; ?>
		</div>
		<nav class="primary-navigation" aria-label="<?php esc_attr_e( 'منوی اصلی', 'parisan' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'menu-list',
					'fallback_cb'    => 'parisan_primary_menu_fallback',
				)
			);
			?>
		</nav>
		<a class="button button-small header-booking" href="<?php echo esc_url( home_url( '/#booking' ) ); ?>"><?php esc_html_e( 'دریافت نوبت', 'parisan' ); ?></a>
	</div>
</header>
<?php if ( function_exists( 'parisan_core_render_breadcrumbs' ) ) : ?>
	<?php parisan_core_render_breadcrumbs(); ?>
<?php endif; ?>
