<?php
/**
 * Portfolio archive.
 *
 * @package Parisan
 */

defined( 'ABSPATH' ) || exit;
get_header();
?>
<main id="main-content" class="catalog-page parisan-portfolio-archive">
	<header class="catalog-heading container">
		<p class="eyebrow"><?php esc_html_e( 'THE PARISAN PORTFOLIO', 'parisan' ); ?></p>
		<h1><?php post_type_archive_title(); ?></h1>
		<p><?php esc_html_e( 'نمونه‌هایی از هنر، دقت و سلیقه متخصصان پاریسان.', 'parisan' ); ?></p>
		<?php $portfolio_terms = get_terms( array( 'taxonomy' => 'parisan_portfolio_category', 'hide_empty' => true ) ); ?>
		<?php if ( ! is_wp_error( $portfolio_terms ) && $portfolio_terms ) : ?>
			<nav class="portfolio-filter" aria-label="<?php esc_attr_e( 'دسته‌بندی نمونه‌کارها', 'parisan' ); ?>">
				<a href="<?php echo esc_url( get_post_type_archive_link( 'parisan_portfolio' ) ); ?>"><?php esc_html_e( 'همه', 'parisan' ); ?></a>
				<?php foreach ( $portfolio_terms as $portfolio_term ) : ?>
					<a href="<?php echo esc_url( get_term_link( $portfolio_term ) ); ?>"><?php echo esc_html( $portfolio_term->name ); ?></a>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>
	</header>
	<div class="container parisan-portfolio-grid">
		<?php if ( have_posts() ) : ?>
			<?php while ( have_posts() ) : the_post(); ?>
				<article <?php post_class( 'portfolio-card' ); ?>>
					<a class="portfolio-card-media" href="<?php the_permalink(); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'مشاهده نمونه‌کار %s', 'parisan' ), get_the_title() ) ); ?>">
						<?php if ( has_post_thumbnail() ) : ?>
							<?php the_post_thumbnail( 'large', array( 'loading' => 'lazy' ) ); ?>
						<?php else : ?><span aria-hidden="true">✳</span><?php endif; ?>
					</a>
					<div class="portfolio-card-content">
						<?php the_terms( get_the_ID(), 'parisan_portfolio_category', '<p class="portfolio-card-category">', '، ', '</p>' ); ?>
						<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
						<div><?php the_excerpt(); ?></div>
					</div>
				</article>
			<?php endwhile; ?>
		<?php else : ?>
			<p class="catalog-empty"><?php esc_html_e( 'هنوز نمونه‌کاری ثبت نشده است.', 'parisan' ); ?></p>
		<?php endif; ?>
	</div>
	<div class="container catalog-pagination"><?php the_posts_pagination(); ?></div>
</main>
<?php get_footer(); ?>
