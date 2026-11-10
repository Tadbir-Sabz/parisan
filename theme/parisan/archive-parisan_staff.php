<?php
/**
 * Specialist archive.
 *
 * @package Parisan
 */

defined( 'ABSPATH' ) || exit;
get_header();
?>
<main id="main-content" class="catalog-page">
	<header class="catalog-heading container">
		<p class="eyebrow"><?php esc_html_e( 'تیم حرفه‌ای ما', 'parisan' ); ?></p>
		<h1><?php post_type_archive_title(); ?></h1>
		<p><?php esc_html_e( 'با متخصصان سالن آشنا شوید و تجربه‌ای متناسب با سلیقه خود انتخاب کنید.', 'parisan' ); ?></p>
	</header>
	<div class="container specialist-grid">
		<?php if ( have_posts() ) : ?>
			<?php $parisan_index = 0; ?>
			<?php while ( have_posts() ) : the_post(); ?>
				<?php $role = get_post_meta( get_the_ID(), '_parisan_staff_role', true ); ?>
				<article <?php post_class( 'specialist-card' ); ?>>
					<a class="specialist-card-image" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
						<?php echo parisan_card_image_html( get_the_ID(), 'staff', $parisan_index ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</a>
					<div class="specialist-card-content">
						<?php if ( $role ) : ?><p class="specialist-role"><?php echo esc_html( $role ); ?></p><?php endif; ?>
						<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
						<?php the_excerpt(); ?>
						<a class="service-card-link" href="<?php the_permalink(); ?>"><?php esc_html_e( 'مشاهده پروفایل', 'parisan' ); ?> <span aria-hidden="true">←</span></a>
					</div>
				</article>
				<?php $parisan_index++; ?>
			<?php endwhile; ?>
		<?php else : ?>
			<div class="catalog-empty parisan-empty-state"><span aria-hidden="true">✳</span><p><?php esc_html_e( 'هنوز متخصصی ثبت نشده است.', 'parisan' ); ?></p></div>
		<?php endif; ?>
	</div>
	<div class="container catalog-pagination"><?php the_posts_pagination(); ?></div>
</main>
<?php get_footer(); ?>
