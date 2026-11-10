<?php
/**
 * Portfolio detail.
 *
 * @package Parisan
 */

defined( 'ABSPATH' ) || exit;
get_header();
?>
<main id="main-content" class="portfolio-detail-page">
	<?php while ( have_posts() ) : the_post(); ?>
		<article <?php post_class( 'container portfolio-detail' ); ?>>
			<header class="portfolio-detail-heading">
				<p class="eyebrow"><?php esc_html_e( 'نمونه‌کار برگزیده · استودیوی پاریسان', 'parisan' ); ?></p>
				<h1><?php the_title(); ?></h1>
				<?php the_terms( get_the_ID(), 'parisan_portfolio_category', '<div class="portfolio-detail-categories">', '، ', '</div>' ); ?>
			</header>
			<?php if ( has_post_thumbnail() ) : ?>
				<figure class="portfolio-detail-image"><?php the_post_thumbnail( 'full' ); ?></figure>
			<?php endif; ?>
			<div class="entry-content portfolio-detail-content"><?php the_content(); ?></div>
			<div class="portfolio-detail-cta">
				<p><?php esc_html_e( 'برای تجربه‌ای متناسب با سلیقه خودتان، خدمات ما را ببینید و زمان دلخواهتان را انتخاب کنید.', 'parisan' ); ?></p>
				<a class="button" href="<?php echo esc_url( home_url( '/#booking' ) ); ?>"><?php esc_html_e( 'رزرو نوبت', 'parisan' ); ?></a>
			</div>
		</article>
	<?php endwhile; ?>
</main>
<?php get_footer(); ?>
