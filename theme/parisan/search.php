<?php
/**
 * Search results template.
 *
 * @package Parisan
 */

defined( 'ABSPATH' ) || exit;
get_header();
?>
<main id="main-content" class="catalog-page">
	<header class="catalog-heading container">
		<p class="eyebrow"><?php esc_html_e( 'جستجو در پاریسان', 'parisan' ); ?></p>
		<h1><?php echo esc_html( sprintf( __( 'نتایج جستجو برای: %s', 'parisan' ), get_search_query() ) ); ?></h1>
		<?php get_search_form(); ?>
	</header>
	<div class="container post-grid">
		<?php if ( have_posts() ) : ?>
			<?php while ( have_posts() ) : the_post(); ?>
				<article <?php post_class( 'post-card' ); ?>>
					<div class="post-card-content"><p class="eyebrow"><?php echo esc_html( get_post_type_object( get_post_type() )->labels->singular_name ); ?></p><h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2><?php the_excerpt(); ?><a class="service-card-link" href="<?php the_permalink(); ?>"><?php esc_html_e( 'مشاهده', 'parisan' ); ?> <span aria-hidden="true">←</span></a></div>
				</article>
			<?php endwhile; ?>
		<?php else : ?><p class="catalog-empty"><?php esc_html_e( 'نتیجه‌ای پیدا نشد. عبارت دیگری را امتحان کنید.', 'parisan' ); ?></p><?php endif; ?>
	</div>
	<div class="container catalog-pagination"><?php the_posts_pagination(); ?></div>
</main>
<?php get_footer(); ?>
