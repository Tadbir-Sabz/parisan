<?php
/**
 * Site footer.
 *
 * @package Parisan
 */

defined( 'ABSPATH' ) || exit;
?>
<footer class="site-footer">
	<div class="container footer-inner">
		<div>
			<p class="footer-brand"><?php bloginfo( 'name' ); ?></p>
			<p class="footer-description"><?php echo esc_html( parisan_public_setting( 'parisan_footer_description' ) ? parisan_public_setting( 'parisan_footer_description' ) : __( 'زیبایی، با آرامش و ظرافت.', 'parisan' ) ); ?></p>
		</div>
		<nav aria-label="<?php esc_attr_e( 'منوی پابرگ', 'parisan' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'footer',
					'container'      => false,
					'menu_class'     => 'footer-menu',
					'fallback_cb'    => 'parisan_footer_menu_fallback',
				)
			);
			?>
		</nav>
		<div class="footer-contact">
			<?php if ( parisan_public_setting( 'parisan_contact_phone' ) ) : ?><a href="<?php echo esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', parisan_public_setting( 'parisan_contact_phone' ) ) ); ?>"><?php echo esc_html( parisan_public_setting( 'parisan_contact_phone' ) ); ?></a><?php endif; ?>
			<?php if ( parisan_public_setting( 'parisan_contact_email' ) ) : ?><a href="<?php echo esc_url( 'mailto:' . sanitize_email( parisan_public_setting( 'parisan_contact_email' ) ) ); ?>"><?php echo esc_html( parisan_public_setting( 'parisan_contact_email' ) ); ?></a><?php endif; ?>
			<?php if ( parisan_public_setting( 'parisan_social_instagram' ) ) : ?><a href="<?php echo esc_url( parisan_public_setting( 'parisan_social_instagram' ) ); ?>" rel="me noopener noreferrer"><?php esc_html_e( 'اینستاگرام', 'parisan' ); ?></a><?php endif; ?>
			<?php if ( parisan_public_setting( 'parisan_social_whatsapp' ) ) : ?><a href="<?php echo esc_url( parisan_public_setting( 'parisan_social_whatsapp' ) ); ?>" rel="noopener noreferrer"><?php esc_html_e( 'واتساپ', 'parisan' ); ?></a><?php endif; ?>
		</div>
		<div class="footer-contact"><?php if ( parisan_public_setting( 'parisan_contact_address' ) ) : ?><span><?php echo esc_html( parisan_public_setting( 'parisan_contact_address' ) ); ?></span><?php endif; ?></div>
		<p class="copyright">&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?></p>
	</div>
</footer>
<a class="parisan-mobile-booking" href="<?php echo esc_url( home_url( '/#booking' ) ); ?>"><?php echo esc_html( function_exists( 'parisan_core_public_booking_enabled' ) && parisan_core_public_booking_enabled() ? 'رزرو نوبت' : 'تماس با سالن' ); ?> <span aria-hidden="true">←</span></a>
<?php wp_footer(); ?>
</body>
</html>
