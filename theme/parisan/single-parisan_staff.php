<?php
/**
 * Specialist profile.
 *
 * @package Parisan
 */

defined( 'ABSPATH' ) || exit;
get_header();
?>
<main id="main-content" class="specialist-detail-page">
	<?php while ( have_posts() ) : the_post(); ?>
		<?php
		$role     = get_post_meta( get_the_ID(), '_parisan_staff_role', true );
		$services = get_post_meta( get_the_ID(), '_parisan_staff_services', true );
		$services = is_array( $services ) ? array_map( 'absint', $services ) : array();
		$hours    = parisan_staff_schedule_rows( get_the_ID() );
		?>
		<article <?php post_class( 'container specialist-detail' ); ?>>
			<?php if ( has_post_thumbnail() ) : ?>
				<div class="specialist-detail-image"><?php the_post_thumbnail( 'large' ); ?></div>
			<?php endif; ?>
			<div class="specialist-detail-copy">
				<p class="eyebrow"><?php esc_html_e( 'متخصصان پاریسان', 'parisan' ); ?></p>
				<h1><?php the_title(); ?></h1>
				<?php if ( $role ) : ?><p class="specialist-role"><?php echo esc_html( $role ); ?></p><?php endif; ?>
				<div class="entry-content"><?php the_content(); ?></div>
				<?php if ( $services ) : ?>
					<section class="specialist-services" aria-labelledby="specialist-services-title">
						<h2 id="specialist-services-title"><?php esc_html_e( 'خدمات قابل ارائه', 'parisan' ); ?></h2>
						<ul>
							<?php foreach ( $services as $service_id ) : ?>
								<?php if ( 'publish' === get_post_status( $service_id ) && 'parisan_service' === get_post_type( $service_id ) ) : ?>
									<li><a href="<?php echo esc_url( get_permalink( $service_id ) ); ?>"><?php echo esc_html( get_the_title( $service_id ) ); ?></a></li>
								<?php endif; ?>
							<?php endforeach; ?>
						</ul>
					</section>
				<?php endif; ?>
				<?php if ( $hours ) : ?>
					<section class="specialist-hours" aria-labelledby="specialist-hours-title">
						<h2 id="specialist-hours-title"><?php esc_html_e( 'ساعات حضور هفتگی', 'parisan' ); ?></h2>
						<dl>
							<?php foreach ( $hours as $day_label => $day_hours ) : ?>
								<dt><?php echo esc_html( $day_label ); ?></dt>
								<dd<?php echo 'تعطیل' === $day_hours ? ' class="is-off"' : ''; ?>><?php echo esc_html( $day_hours ); ?></dd>
							<?php endforeach; ?>
						</dl>
					</section>
				<?php endif; ?>
				<a class="button" href="<?php echo esc_url( parisan_booking_cta_url() ); ?>"><?php echo esc_html( parisan_booking_cta_label() ); ?></a>
			</div>
		</article>
	<?php endwhile; ?>
</main>
<?php get_footer(); ?>
