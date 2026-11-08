<?php
/**
 * Service catalogue archive.
 *
 * @package Parisan
 */

defined( 'ABSPATH' ) || exit;
get_header();
?>
<main id="main-content" class="catalog-page">
	<header class="catalog-heading container">
		<p class="eyebrow"><?php esc_html_e( 'خدمات تخصصی', 'parisan' ); ?></p>
		<h1><?php post_type_archive_title(); ?></h1>
		<p><?php esc_html_e( 'خدمات سالن را بررسی کنید و برای تجربه دلخواهتان نوبت بگیرید.', 'parisan' ); ?></p>
	</header>

	<div class="container service-grid">
		<?php if ( have_posts() ) : ?>
			<?php while ( have_posts() ) : the_post(); ?>
				<?php
				$duration   = absint( get_post_meta( get_the_ID(), '_parisan_duration', true ) );
				$price      = absint( get_post_meta( get_the_ID(), '_parisan_price', true ) );
				$price_type = get_post_meta( get_the_ID(), '_parisan_price_type', true );
				?>
				<article <?php post_class( 'service-card' ); ?>>
					<a class="service-card-media" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
						<?php if ( has_post_thumbnail() ) : ?>
							<?php the_post_thumbnail( 'large', array( 'loading' => 'lazy' ) ); ?>
						<?php else : ?>
							<span class="service-card-placeholder" aria-hidden="true">✳</span>
						<?php endif; ?>
					</a>
					<div class="service-card-content">
						<div class="service-card-meta">
							<?php if ( $duration ) : ?>
								<span><?php echo esc_html( sprintf( __( '%s دقیقه', 'parisan' ), number_format_i18n( $duration ) ) ); ?></span>
							<?php endif; ?>
							<?php if ( $price ) : ?>
								<span>
									<?php
									echo esc_html(
										( 'from' === $price_type ? __( 'از ', 'parisan' ) : '' ) .
										number_format_i18n( $price ) . ' ' . __( 'تومان', 'parisan' )
									);
									?>
								</span>
							<?php endif; ?>
						</div>
						<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
						<div class="service-card-excerpt"><?php the_excerpt(); ?></div>
						<a class="service-card-link" href="<?php the_permalink(); ?>"><?php esc_html_e( 'جزئیات خدمت', 'parisan' ); ?> <span aria-hidden="true">←</span></a>
					</div>
				</article>
			<?php endwhile; ?>
		<?php else : ?>
			<p class="catalog-empty"><?php esc_html_e( 'هنوز خدمتی ثبت نشده است.', 'parisan' ); ?></p>
		<?php endif; ?>
	</div>

	<div class="container catalog-pagination"><?php the_posts_pagination(); ?></div>
</main>
<?php get_footer(); ?>
