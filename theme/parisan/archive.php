<?php
/**
 * Blog archive template.
 *
 * @package Parisan
 */

defined( 'ABSPATH' ) || exit;
get_header();
?>
<main id="main-content" class="catalog-page">
	<header class="catalog-heading container">
		<p class="eyebrow"><?php esc_html_e( 'مجله پاریسان', 'parisan' ); ?></p>
		<h1><?php the_archive_title(); ?></h1>
		<div><?php the_archive_description(); ?></div>
	</header>
	<div class="container post-grid">
		<?php if ( have_posts() ) : ?>
			<?php while ( have_posts() ) : the_post(); ?>
				<article <?php post_class( 'post-card' ); ?>>
					<?php if ( has_post_thumbnail() ) : ?><a class="post-card-image" href="<?php the_permalink(); ?>"><?php the_post_thumbnail( 'large', array( 'loading' => 'lazy' ) ); ?></a><?php endif; ?>
					<div class="post-card-content"><p class="eyebrow"><?php echo esc_html( get_the_date() ); ?></p><h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2><?php the_excerpt(); ?><a class="service-card-link" href="<?php the_permalink(); ?>"><?php esc_html_e( 'ادامه مطلب', 'parisan' ); ?> <span aria-hidden="true">←</span></a></div>
				</article>
			<?php endwhile; ?>
		<?php else : ?><p class="catalog-empty"><?php esc_html_e( 'مطلبی برای نمایش وجود ندارد.', 'parisan' ); ?></p><?php endif; ?>
	</div>
	<div class="container catalog-pagination"><?php the_posts_pagination(); ?></div>
</main>
<?php get_footer(); ?>
