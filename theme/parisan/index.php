<?php
/**
 * Main template fallback for posts, archives, and unhandled public queries.
 *
 * @package Parisan
 */

defined( 'ABSPATH' ) || exit;
get_header();
?>
<main id="main-content" class="catalog-page parisan-content-index">
	<header class="catalog-heading container">
		<p class="eyebrow"><?php esc_html_e( 'مجلهٔ پاریسان', 'parisan' ); ?></p>
		<h1><?php echo esc_html( is_home() ? __( 'تازه‌های پاریسان', 'parisan' ) : __( 'مطالب پاریسان', 'parisan' ) ); ?></h1>
		<p><?php esc_html_e( 'تازه‌ترین نوشته‌ها، راهنماها و روایت‌های پاریسان را دنبال کنید.', 'parisan' ); ?></p>
	</header>
	<div class="container post-grid">
		<?php if ( have_posts() ) : ?>
			<?php $parisan_index = 0; ?>
			<?php while ( have_posts() ) : the_post(); ?>
				<?php
				$post_type_object = get_post_type_object( get_post_type() );
				$post_type_label  = $post_type_object && isset( $post_type_object->labels->singular_name ) ? $post_type_object->labels->singular_name : __( 'مطلب', 'parisan' );
				?>
				<article <?php post_class( 'post-card' ); ?>>
					<a class="post-card-image" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
						<?php echo parisan_card_image_html( get_the_ID(), 'journal', $parisan_index ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</a>
					<div class="post-card-content">
						<p class="eyebrow"><?php echo esc_html( $post_type_label ); ?></p>
						<p class="post-meta"><time datetime="<?php echo esc_attr( get_the_date( DATE_W3C ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time></p>
						<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
						<?php the_excerpt(); ?>
						<a class="service-card-link" href="<?php the_permalink(); ?>"><?php esc_html_e( 'ادامه مطلب', 'parisan' ); ?> <span aria-hidden="true">←</span></a>
					</div>
				</article>
				<?php $parisan_index++; ?>
			<?php endwhile; ?>
		<?php else : ?>
			<div class="catalog-empty parisan-empty-state"><span aria-hidden="true">✳</span><p><?php esc_html_e( 'هنوز محتوایی برای نمایش منتشر نشده است.', 'parisan' ); ?></p></div>
		<?php endif; ?>
	</div>
	<div class="container catalog-pagination">
		<?php the_posts_pagination( array( 'mid_size' => 1, 'prev_text' => __( 'مطالب جدیدتر', 'parisan' ), 'next_text' => __( 'مطالب قدیمی‌تر', 'parisan' ), 'aria_label' => __( 'صفحه‌بندی مطالب', 'parisan' ) ) ); ?>
	</div>
</main>
<?php get_footer(); ?>
