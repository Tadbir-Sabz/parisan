<?php
/**
 * Contact page template (page slug: contact).
 *
 * @package Parisan
 */
defined( 'ABSPATH' ) || exit;
get_header();
$phone = parisan_public_setting( 'parisan_contact_phone' );
$email = parisan_public_setting( 'parisan_contact_email' );
$address = parisan_public_setting( 'parisan_contact_address' );
$hours = parisan_public_setting( 'parisan_contact_hours' );
?>
<main id="main-content">
	<?php while ( have_posts() ) : the_post(); ?>
		<section class="parisan-page-hero"><div class="container"><p class="eyebrow"><?php esc_html_e( 'پاسخ‌گویی و ارتباط', 'parisan' ); ?></p><h1><?php the_title(); ?></h1><p><?php esc_html_e( 'برای پرسش، راهنمایی و هماهنگی مراجعه، از راه‌های ارتباطی زیر با ما در تماس باشید.', 'parisan' ); ?></p></div></section>
		<div class="container parisan-page-content">
			<div class="parisan-contact-grid">
				<?php if ( $phone ) : ?><section class="parisan-contact-card"><h2><?php esc_html_e( 'تلفن تماس', 'parisan' ); ?></h2><p><a href="<?php echo esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a></p></section><?php endif; ?>
				<?php if ( $email ) : ?><section class="parisan-contact-card"><h2><?php esc_html_e( 'ایمیل', 'parisan' ); ?></h2><p><a href="<?php echo esc_url( 'mailto:' . sanitize_email( $email ) ); ?>"><?php echo esc_html( $email ); ?></a></p></section><?php endif; ?>
				<?php if ( $address ) : ?><section class="parisan-contact-card"><h2><?php esc_html_e( 'نشانی سالن', 'parisan' ); ?></h2><p><?php echo nl2br( esc_html( $address ) ); ?></p></section><?php endif; ?>
				<?php if ( $hours ) : ?><section class="parisan-contact-card"><h2><?php esc_html_e( 'ساعات پاسخ‌گویی', 'parisan' ); ?></h2><p><?php echo esc_html( $hours ); ?></p></section><?php endif; ?>
			</div>
			<article class="entry-content"><?php the_content(); ?></article>
		</div>
	<?php endwhile; ?>
</main>
<?php get_footer(); ?>
