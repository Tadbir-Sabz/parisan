<?php
/**
 * Portfolio category archive.
 *
 * @package Parisan
 */

defined( 'ABSPATH' ) || exit;
get_header();

$parisan_term = get_queried_object();
$parisan_terms = get_terms( array( 'taxonomy' => 'parisan_portfolio_category', 'hide_empty' => true ) );
?>
<main id="main-content" class="catalog-page parisan-portfolio-archive">
	<header class="catalog-heading container">
		<p class="eyebrow"><?php esc_html_e( 'دسته‌بندی نمونه‌کارهای پاریسان', 'parisan' ); ?></p>
		<h1><?php echo esc_html( $parisan_term instanceof WP_Term ? $parisan_term->name : __( 'نمونه‌کارها', 'parisan' ) ); ?></h1>
		<?php if ( $parisan_term instanceof WP_Term && $parisan_term->description ) : ?>
			<div><?php echo wp_kses_post( wpautop( $parisan_term->description ) ); ?></div>
		<?php else : ?>
			<p><?php esc_html_e( 'نمونه‌هایی از هنر، دقت و سلیقه متخصصان پاریسان در این دسته.', 'parisan' ); ?></p>
		<?php endif; ?>
		<?php if ( ! is_wp_error( $parisan_terms ) && $parisan_terms ) : ?>
			<nav class="portfolio-filter" aria-label="<?php esc_attr_e( 'دسته‌بندی نمونه‌کارها', 'parisan' ); ?>">
				<a href="<?php echo esc_url( get_post_type_archive_link( 'parisan_portfolio' ) ); ?>"><?php esc_html_e( 'همه', 'parisan' ); ?></a>
				<?php foreach ( $parisan_terms as $parisan_portfolio_term ) : ?>
					<a href="<?php echo esc_url( get_term_link( $parisan_portfolio_term ) ); ?>"<?php echo $parisan_term instanceof WP_Term && $parisan_term->term_id === $parisan_portfolio_term->term_id ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $parisan_portfolio_term->name ); ?></a>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>
	</header>
	<div class="container parisan-portfolio-grid">
		<?php if ( have_posts() ) : ?>
			<?php $parisan_index = 0; ?>
			<?php while ( have_posts() ) : the_post(); ?>
				<article <?php post_class( 'portfolio-card' ); ?>>
					<a class="portfolio-card-media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
						<?php echo parisan_card_image_html( get_the_ID(), 'portfolio', $parisan_index ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</a>
					<div class="portfolio-card-content">
						<?php the_terms( get_the_ID(), 'parisan_portfolio_category', '<p class="portfolio-card-category">', '، ', '</p>' ); ?>
						<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
						<div><?php the_excerpt(); ?></div>
					</div>
				</article>
				<?php $parisan_index++; ?>
			<?php endwhile; ?>
		<?php else : ?>
			<div class="catalog-empty parisan-empty-state"><span aria-hidden="true">✳</span><p><?php esc_html_e( 'هنوز نمونه‌کاری در این دسته ثبت نشده است.', 'parisan' ); ?></p></div>
		<?php endif; ?>
	</div>
	<div class="container catalog-pagination"><?php the_posts_pagination(); ?></div>
</main>
<?php get_footer(); ?>
