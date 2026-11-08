<?php
/**
 * Service detail.
 *
 * @package Parisan
 */

defined( 'ABSPATH' ) || exit;
get_header();
?>
<main id="main-content" class="service-detail-page">
	<?php while ( have_posts() ) : the_post(); ?>
		<?php
		$duration   = absint( get_post_meta( get_the_ID(), '_parisan_duration', true ) );
		$price      = absint( get_post_meta( get_the_ID(), '_parisan_price', true ) );
		$price_type = get_post_meta( get_the_ID(), '_parisan_price_type', true );
		?>
		<article <?php post_class( 'container service-detail' ); ?>>
			<div class="service-detail-copy">
				<p class="eyebrow"><?php esc_html_e( 'خدمات تخصصی پاریسان', 'parisan' ); ?></p>
				<h1><?php the_title(); ?></h1>
				<div class="service-detail-summary">
					<?php if ( $duration ) : ?>
						<span><?php echo esc_html( sprintf( __( '%s دقیقه', 'parisan' ), number_format_i18n( $duration ) ) ); ?></span>
					<?php endif; ?>
					<?php if ( $price ) : ?>
						<span><?php echo esc_html( ( 'from' === $price_type ? __( 'شروع از ', 'parisan' ) : '' ) . number_format_i18n( $price ) . ' ' . __( 'تومان', 'parisan' ) ); ?></span>
					<?php endif; ?>
				</div>
				<div class="entry-content"><?php the_content(); ?></div>
				<a class="button" href="<?php echo esc_url( apply_filters( 'parisan_service_booking_url', home_url( '/#booking' ), get_the_ID() ) ); ?>"><?php esc_html_e( 'درخواست نوبت برای این خدمت', 'parisan' ); ?></a>
			</div>
			<?php if ( has_post_thumbnail() ) : ?>
				<div class="service-detail-image"><?php the_post_thumbnail( 'large' ); ?></div>
			<?php endif; ?>
		</article>
	<?php endwhile; ?>
</main>
<?php get_footer(); ?>
