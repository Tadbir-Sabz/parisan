<?php
/**
 * Booking terms page template (page slug: terms).
 *
 * @package Parisan
 */
defined( 'ABSPATH' ) || exit;
get_header();
?>
<main id="main-content">
	<?php while ( have_posts() ) : the_post(); ?>
		<section class="parisan-page-hero"><div class="container"><p class="eyebrow"><?php esc_html_e( 'TERMS & CONDITIONS', 'parisan' ); ?></p><h1><?php the_title(); ?></h1><p><?php esc_html_e( 'شرایط استفاده از خدمات و ضوابط رزرو نوبت در سالن.', 'parisan' ); ?></p></div></section>
		<article <?php post_class( 'parisan-page-content container' ); ?>><p class="post-meta"><?php esc_html_e( 'آخرین به‌روزرسانی:', 'parisan' ); ?> <?php echo esc_html( get_the_modified_date() ); ?></p><div class="entry-content"><?php the_content(); ?></div></article>
	<?php endwhile; ?>
</main>
<?php get_footer(); ?>
