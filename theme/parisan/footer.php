<?php
/**
 * Site footer.
 *
 * @package Parisan
 */

defined( 'ABSPATH' ) || exit;

$parisan_phone     = parisan_public_setting( 'parisan_contact_phone' );
$parisan_email     = parisan_public_setting( 'parisan_contact_email' );
$parisan_instagram = parisan_public_setting( 'parisan_social_instagram' );
$parisan_whatsapp  = parisan_public_setting( 'parisan_social_whatsapp' );
$parisan_address   = parisan_public_setting( 'parisan_contact_address' );
$parisan_has_links = '' !== $parisan_phone || '' !== $parisan_email || '' !== $parisan_instagram || '' !== $parisan_whatsapp;
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
		<?php if ( $parisan_has_links ) : ?>
			<div class="footer-contact">
				<?php if ( $parisan_phone ) : ?><a href="<?php echo esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $parisan_phone ) ); ?>"><?php echo esc_html( $parisan_phone ); ?></a><?php endif; ?>
				<?php if ( $parisan_email ) : ?><a href="<?php echo esc_url( 'mailto:' . sanitize_email( $parisan_email ) ); ?>"><?php echo esc_html( $parisan_email ); ?></a><?php endif; ?>
				<?php if ( $parisan_instagram ) : ?><a href="<?php echo esc_url( $parisan_instagram ); ?>" rel="me noopener noreferrer"><?php esc_html_e( 'اینستاگرام', 'parisan' ); ?></a><?php endif; ?>
				<?php if ( $parisan_whatsapp ) : ?><a href="<?php echo esc_url( $parisan_whatsapp ); ?>" rel="noopener noreferrer"><?php esc_html_e( 'واتساپ', 'parisan' ); ?></a><?php endif; ?>
			</div>
		<?php endif; ?>
		<?php if ( $parisan_address ) : ?>
			<div class="footer-contact"><span><?php echo esc_html( $parisan_address ); ?></span></div>
		<?php endif; ?>
		<p class="copyright">&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?></p>
	</div>
</footer>
<a class="parisan-mobile-booking" href="<?php echo esc_url( home_url( '/#booking' ) ); ?>"><?php echo esc_html( function_exists( 'parisan_core_public_booking_enabled' ) && parisan_core_public_booking_enabled() ? 'رزرو نوبت' : 'تماس با سالن' ); ?> <span aria-hidden="true">←</span></a>
<?php wp_footer(); ?>
</body>
</html>
