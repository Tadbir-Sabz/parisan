<?php
/**
 * Search results template.
 *
 * @package Parisan
 */

defined( 'ABSPATH' ) || exit;
get_header();
$parisan_search_count = isset( $GLOBALS['wp_query']->found_posts ) ? absint( $GLOBALS['wp_query']->found_posts ) : 0;
?>
<main id="main-content" class="catalog-page parisan-search-page">
	<header class="catalog-heading container">
		<p class="eyebrow"><?php esc_html_e( 'جستجو در پاریسان', 'parisan' ); ?></p>
		<h1 id="search-results-title"><?php echo esc_html( sprintf( __( 'نتایج جستجو برای: %s', 'parisan' ), get_search_query() ) ); ?></h1>
		<p class="search-results-count" role="status" aria-live="polite"><?php echo esc_html( sprintf( _n( '%s نتیجه پیدا شد', '%s نتیجه پیدا شد', $parisan_search_count, 'parisan' ), number_format_i18n( $parisan_search_count ) ) ); ?></p>
		<?php get_search_form(); ?>
	</header>
	<div class="container post-grid" aria-labelledby="search-results-title">
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
						<a class="service-card-link" href="<?php the_permalink(); ?>"><?php esc_html_e( 'مشاهده مطلب', 'parisan' ); ?> <span aria-hidden="true">←</span></a>
					</div>
				</article>
				<?php $parisan_index++; ?>
			<?php endwhile; ?>
		<?php else : ?>
			<div class="catalog-empty parisan-empty-state">
				<span aria-hidden="true">✳</span>
				<p><?php esc_html_e( 'نتیجه‌ای پیدا نشد. عبارت دیگری را امتحان کنید.', 'parisan' ); ?></p>
				<?php get_search_form(); ?>
			</div>
		<?php endif; ?>
	</div>
	<div class="container catalog-pagination">
		<?php the_posts_pagination( array( 'mid_size' => 1, 'prev_text' => __( 'نتایج قبلی', 'parisan' ), 'next_text' => __( 'نتایج بعدی', 'parisan' ), 'aria_label' => __( 'صفحه‌بندی نتایج جستجو', 'parisan' ) ) ); ?>
	</div>
</main>
<?php get_footer(); ?>
