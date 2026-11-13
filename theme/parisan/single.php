<?php
/**
 * Editorial article template.
 *
 * @package Parisan
 */
defined( 'ABSPATH' ) || exit;
get_header();
?>
<main id="main-content" class="single-post-layout container">
	<?php while ( have_posts() ) : the_post(); ?>
		<article <?php post_class( 'standard-entry' ); ?>>
			<header class="entry-header">
				<p class="eyebrow"><?php esc_html_e( 'مجلهٔ پاریسان', 'parisan' ); ?></p>
				<h1><?php the_title(); ?></h1>
				<div class="post-meta"><span><?php echo esc_html( get_the_date() ); ?></span><span><?php echo esc_html( get_the_author() ); ?></span></div>
				<div class="post-taxonomies"><?php the_category( ' · ' ); ?></div>
			</header>
			<?php if ( has_post_thumbnail() ) : ?><div class="entry-featured-image"><?php the_post_thumbnail( 'full' ); ?></div><?php endif; ?>
			<div class="entry-content"><?php the_content(); ?><?php wp_link_pages( array( 'before' => '<nav class="page-links">' . esc_html__( 'صفحات:', 'parisan' ), 'after' => '</nav>' ) ); ?></div>
			<footer class="entry-footer"><div class="post-taxonomies"><?php the_tags( '', ' · ', '' ); ?></div><?php the_post_navigation(); ?></footer>
		</article>
		<?php
		$related = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 3, 'post__not_in' => array( get_the_ID() ), 'category__in' => wp_get_post_categories( get_the_ID() ) ) );
		?>
		<?php if ( $related ) : ?>
			<section class="parisan-related-posts">
				<h2><?php esc_html_e( 'مطالب مرتبط', 'parisan' ); ?></h2>
				<div class="post-grid">
					<?php foreach ( $related as $related_index => $related_post ) : ?>
						<article class="post-card">
							<a class="post-card-image" href="<?php echo esc_url( get_permalink( $related_post ) ); ?>" tabindex="-1" aria-hidden="true">
								<?php echo parisan_card_image_html( $related_post, 'journal', $related_index ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</a>
							<div class="post-card-content">
								<p class="post-meta"><?php echo esc_html( get_the_date( '', $related_post ) ); ?></p>
								<h3><a href="<?php echo esc_url( get_permalink( $related_post ) ); ?>"><?php echo esc_html( get_the_title( $related_post ) ); ?></a></h3>
								<p><?php echo esc_html( wp_trim_words( get_the_excerpt( $related_post ), 18 ) ); ?></p>
								<a class="service-card-link" href="<?php echo esc_url( get_permalink( $related_post ) ); ?>"><?php esc_html_e( 'ادامه مطلب', 'parisan' ); ?> <span aria-hidden="true">←</span></a>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			</section>
		<?php endif; ?>
	<?php endwhile; ?>
</main>
<?php get_footer(); ?>
