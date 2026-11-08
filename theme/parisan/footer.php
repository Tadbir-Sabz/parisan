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
			<p class="footer-description"><?php esc_html_e( 'زیبایی، با آرامش و ظرافت.', 'parisan' ); ?></p>
		</div>
		<nav aria-label="<?php esc_attr_e( 'منوی پابرگ', 'parisan' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'footer',
					'container'      => false,
					'menu_class'     => 'footer-menu',
					'fallback_cb'    => false,
				)
			);
			?>
		</nav>
		<p class="copyright">&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?></p>
	</div>
</footer>
<a class="parisan-mobile-booking" href="<?php echo esc_url( home_url( '/#booking' ) ); ?>"><?php esc_html_e( 'رزرو نوبت', 'parisan' ); ?> <span aria-hidden="true">←</span></a>
<?php wp_footer(); ?>
</body>
</html>
