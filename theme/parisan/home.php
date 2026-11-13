<?php
/**
 * Editorial blog index.
 *
 * @package Parisan
 */
defined( 'ABSPATH' ) || exit;
get_header();
$current_page = max( 1, absint( get_query_var( 'paged' ) ) );
$featured     = 1 === $current_page ? get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 1, 'ignore_sticky_posts' => false ) ) : array();
$featured_id  = ! empty( $featured ) ? absint( $featured[0]->ID ) : 0;
$categories   = get_categories( array( 'hide_empty' => true ) );
$blog_url     = parisan_blog_index_url();
?>
<main id="main-content" class="catalog-page parisan-blog">
	<section class="parisan-blog-hero">
		<div class="container">
			<p class="eyebrow"><?php esc_html_e( 'مجلهٔ پاریسان', 'parisan' ); ?></p>
			<h1><?php esc_html_e( 'مجله پاریسان', 'parisan' ); ?></h1>
			<p><?php esc_html_e( 'دانستنی‌ها، مراقبت‌های زیبایی و روایت‌هایی برای لحظه‌های بهتر شما.', 'parisan' ); ?></p>
			<?php if ( $categories ) : ?>
				<nav class="parisan-blog-categories" aria-label="<?php esc_attr_e( 'دسته‌بندی مطالب', 'parisan' ); ?>">
					<?php if ( $blog_url ) : ?><a href="<?php echo esc_url( $blog_url ); ?>"><?php esc_html_e( 'همه مطالب', 'parisan' ); ?></a><?php endif; ?>
					<?php foreach ( $categories as $category ) : ?>
						<a href="<?php echo esc_url( get_category_link( $category ) ); ?>"><?php echo esc_html( $category->name ); ?></a>
					<?php endforeach; ?>
				</nav>
			<?php endif; ?>
		</div>
	</section>
	<div class="container">
		<?php if ( ! empty( $featured ) ) : $featured_post = $featured[0]; ?>
			<article class="parisan-blog-featured">
				<a href="<?php echo esc_url( get_permalink( $featured_post ) ); ?>" tabindex="-1" aria-hidden="true">
					<?php echo parisan_card_image_html( $featured_post, 'journal', 0, 'large', array( 'loading' => 'eager' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</a>
				<div>
					<p class="eyebrow"><?php esc_html_e( 'پیشنهاد سردبیر', 'parisan' ); ?></p>
					<h2><a href="<?php echo esc_url( get_permalink( $featured_post ) ); ?>"><?php echo esc_html( get_the_title( $featured_post ) ); ?></a></h2>
					<p class="post-meta"><?php echo esc_html( get_the_date( '', $featured_post ) ); ?> · <?php echo esc_html( get_the_author_meta( 'display_name', $featured_post->post_author ) ); ?></p>
					<p><?php echo esc_html( wp_trim_words( get_the_excerpt( $featured_post ), 34 ) ); ?></p>
					<a class="button" href="<?php echo esc_url( get_permalink( $featured_post ) ); ?>"><?php esc_html_e( 'خواندن مقاله', 'parisan' ); ?></a>
				</div>
			</article>
		<?php endif; ?>
		<div class="post-grid">
			<?php if ( have_posts() ) : ?>
				<?php $parisan_index = 1; ?>
				<?php while ( have_posts() ) : the_post(); ?>
					<?php if ( get_the_ID() !== $featured_id ) : ?>
						<article <?php post_class( 'post-card' ); ?>>
							<a class="post-card-image" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
								<?php echo parisan_card_image_html( get_the_ID(), 'journal', $parisan_index ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</a>
							<div class="post-card-content">
								<p class="post-meta"><?php echo esc_html( get_the_date() ); ?> · <?php echo esc_html( get_the_author() ); ?></p>
								<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
								<?php the_excerpt(); ?>
								<a class="service-card-link" href="<?php the_permalink(); ?>"><?php esc_html_e( 'ادامه مطلب', 'parisan' ); ?> <span aria-hidden="true">←</span></a>
							</div>
						</article>
						<?php $parisan_index++; ?>
					<?php endif; ?>
				<?php endwhile; ?>
			<?php else : ?>
				<div class="catalog-empty parisan-empty-state"><span aria-hidden="true">✳</span><p><?php esc_html_e( 'هنوز مطلبی منتشر نشده است.', 'parisan' ); ?></p></div>
			<?php endif; ?>
		</div>
		<div class="catalog-pagination"><?php the_posts_pagination( array( 'mid_size' => 1, 'prev_text' => __( 'مطالب جدیدتر', 'parisan' ), 'next_text' => __( 'مطالب قدیمی‌تر', 'parisan' ) ) ); ?></div>
	</div>
</main>
<?php get_footer(); ?>
