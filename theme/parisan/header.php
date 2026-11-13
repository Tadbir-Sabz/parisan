<?php
/**
 * Site header.
 *
 * @package Parisan
 */

defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html <?php language_attributes(); ?> class="no-js">
<head>
	<script>document.documentElement.className = document.documentElement.className.replace('no-js', 'js');</script>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#main-content"><?php esc_html_e( 'رفتن به محتوای اصلی', 'parisan' ); ?></a>
<?php $announcement = parisan_public_setting( 'parisan_announcement_text' ); ?>
<?php if ( is_front_page() ) : ?><div class="parisan-front-masthead"><?php endif; ?>
<?php if ( $announcement ) : ?><aside class="parisan-announcement" aria-label="<?php esc_attr_e( 'اعلان سالن', 'parisan' ); ?>"><?php if ( parisan_public_setting( 'parisan_announcement_url' ) ) : ?><a href="<?php echo esc_url( parisan_public_setting( 'parisan_announcement_url' ) ); ?>"><?php echo esc_html( $announcement ); ?></a><?php else : ?><?php echo esc_html( $announcement ); ?><?php endif; ?></aside><?php endif; ?>
<header class="site-header">
	<div class="container header-inner">
		<div class="site-branding">
			<?php if ( has_custom_logo() ) : ?>
				<?php the_custom_logo(); ?>
			<?php else : ?>
				<a class="site-title parisan-brand-lockup" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
					<span class="parisan-brand-name">Parisan</span>
					<svg class="parisan-brand-mark" viewBox="0 0 52 48" aria-hidden="true" focusable="false">
						<path d="M26 43C18 34 17 24 26 5C35 24 34 34 26 43Z M26 43C9 39 4 29 7 18C19 20 25 29 26 43Z M26 43C43 39 48 29 45 18C33 20 27 29 26 43Z M26 43C15 31 14 21 17 12C26 19 29 29 26 43Z M26 43C37 31 38 21 35 12C26 19 23 29 26 43Z" />
						<path d="M13 44H39" />
					</svg>
				</a>
			<?php endif; ?>
		</div>
		<button class="parisan-mobile-menu-toggle" type="button" aria-controls="primary-navigation" aria-expanded="false" hidden><?php esc_html_e( 'باز کردن منو', 'parisan' ); ?></button>
		<nav id="primary-navigation" class="primary-navigation" aria-label="<?php esc_attr_e( 'منوی اصلی', 'parisan' ); ?>">
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
		<a class="button button-small header-booking" href="<?php echo esc_url( parisan_booking_cta_url() ); ?>"><?php echo esc_html( parisan_booking_cta_label() ); ?></a>
		<button class="parisan-theme-toggle" type="button" aria-label="<?php esc_attr_e( 'فعال‌سازی حالت تیره', 'parisan' ); ?>" aria-pressed="false" hidden><?php esc_html_e( 'حالت تیره', 'parisan' ); ?></button>
	</div>
</header>
<?php if ( is_front_page() ) : ?></div><?php endif; ?>
<?php if ( function_exists( 'parisan_core_render_breadcrumbs' ) ) : ?>
	<?php parisan_core_render_breadcrumbs(); ?>
<?php endif; ?>
