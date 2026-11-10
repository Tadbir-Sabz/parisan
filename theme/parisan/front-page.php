<?php
/**
 * Editorial homepage for Parisan Beauty.
 *
 * @package Parisan
 */

defined( 'ABSPATH' ) || exit;

$parisan_services = post_type_exists( 'parisan_service' ) ? get_posts(
	array(
		'post_type'      => 'parisan_service',
		'post_status'    => 'publish',
		'posts_per_page' => 3,
		'orderby'        => 'menu_order title',
		'order'          => 'ASC',
	)
) : array();

$parisan_staff = post_type_exists( 'parisan_staff' ) ? get_posts(
	array(
		'post_type'      => 'parisan_staff',
		'post_status'    => 'publish',
		'posts_per_page' => 3,
		'orderby'        => 'menu_order title',
		'order'          => 'ASC',
	)
) : array();

$parisan_portfolio = post_type_exists( 'parisan_portfolio' ) ? get_posts( array( 'post_type' => 'parisan_portfolio', 'post_status' => 'publish', 'posts_per_page' => 6, 'orderby' => 'date', 'order' => 'DESC' ) ) : array();
$parisan_articles = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 3, 'orderby' => 'date', 'order' => 'DESC' ) );

$parisan_hero_image_id = absint( get_theme_mod( 'parisan_hero_image_id', 0 ) );
$parisan_hero_image = get_theme_mod( 'parisan_hero_image', '' );
$parisan_hero_position_x = parisan_sanitize_hero_position( get_theme_mod( 'parisan_hero_position_x', 50 ) );
$parisan_hero_position_y = parisan_sanitize_hero_position( get_theme_mod( 'parisan_hero_position_y', 34 ) );
$parisan_hero_image_style = sprintf( 'object-position:%1$d%% %2$d%%;', $parisan_hero_position_x, $parisan_hero_position_y );
$parisan_has_hero_attachment = $parisan_hero_image_id && wp_attachment_is_image( $parisan_hero_image_id );
$parisan_has_hero_image = $parisan_has_hero_attachment || ( is_string( $parisan_hero_image ) && '' !== $parisan_hero_image );
$parisan_hero_image_alt = sanitize_text_field( get_theme_mod( 'parisan_hero_image_alt', '' ) );
if ( '' === $parisan_hero_image_alt && $parisan_has_hero_attachment ) {
	$parisan_hero_image_alt = sanitize_text_field( get_post_meta( $parisan_hero_image_id, '_wp_attachment_image_alt', true ) );
}

$parisan_booking_enabled = function_exists( 'parisan_core_public_booking_enabled' ) && parisan_core_public_booking_enabled();

get_header();
?>
<main id="main-content" class="parisan-home">
	<section class="hero-section">
		<div class="container hero-grid">
			<div class="hero-copy">
				<p class="eyebrow"><?php echo esc_html( parisan_home_content( 'hero_eyebrow', 'PARISAN BEAUTY STUDIO · زیبایی با آرامش' ) ); ?></p>
				<h1><?php echo esc_html( parisan_home_content( 'hero_title', 'زیباییِ شما، با امضای ظرافت' ) ); ?></h1>
				<p class="hero-description"><?php echo esc_html( parisan_home_content( 'hero_description', 'از لحظه‌ای که وارد می‌شوید تا آخرین جزئیات خدمات، همه‌چیز برای یک تجربه دلنشین، دقیق و شایسته شما طراحی شده است.' ) ); ?></p>
				<div class="hero-actions">
					<a class="button" href="<?php echo esc_url( $parisan_booking_enabled ? parisan_home_content( 'hero_primary_url', '#booking' ) : home_url( '/contact/' ) ); ?>"><?php echo esc_html( $parisan_booking_enabled ? parisan_home_content( 'hero_primary_label', 'انتخاب زمان و رزرو' ) : 'هماهنگی نوبت' ); ?></a>
					<a class="text-link" href="<?php echo esc_url( parisan_home_content( 'hero_secondary_url', '#services' ) ); ?>"><?php echo esc_html( parisan_home_content( 'hero_secondary_label', 'آشنایی با خدمات' ) ); ?></a>
				</div>
				<div class="hero-note"><span class="hero-note-mark" aria-hidden="true">✳</span><span><?php esc_html_e( 'لحظه‌ای برای خودتان؛ تجربه‌ای با توجه به جزئیات', 'parisan' ); ?></span></div>
			</div>
			<div class="hero-art">
				<?php if ( $parisan_hero_image_id && wp_attachment_is_image( $parisan_hero_image_id ) ) : ?>
					<?php echo wp_get_attachment_image( $parisan_hero_image_id, 'full', false, array( 'class' => 'hero-art-photo', 'alt' => $parisan_hero_image_alt, 'loading' => 'eager', 'decoding' => 'async', 'style' => $parisan_hero_image_style ) ); ?>
				<?php elseif ( is_string( $parisan_hero_image ) && '' !== $parisan_hero_image ) : ?>
					<img class="hero-art-photo" src="<?php echo esc_url( $parisan_hero_image ); ?>" alt="<?php echo esc_attr( $parisan_hero_image_alt ); ?>" loading="eager" decoding="async" style="<?php echo esc_attr( $parisan_hero_image_style ); ?>">
				<?php else : ?>
					<img class="hero-art-photo hero-art-demo-photo" src="https://images.unsplash.com/photo-1771254240529-dbe324f0a401?auto=format&amp;fit=crop&amp;fm=jpg&amp;q=82&amp;w=1400" alt="<?php esc_attr_e( 'تصویر نمونه از تجربه آرایش و زیبایی در فضای استودیو', 'parisan' ); ?>" loading="eager" decoding="async" fetchpriority="high" style="<?php echo esc_attr( $parisan_hero_image_style ); ?>">
				<?php endif; ?>
				<div class="hero-art-orbit hero-art-orbit-one"></div>
				<div class="hero-art-orbit hero-art-orbit-two"></div>
				<div class="hero-art-petal hero-art-petal-one"></div>
				<div class="hero-art-petal hero-art-petal-two"></div>
				<div class="hero-art-label"><span><?php esc_html_e( 'هنرِ', 'parisan' ); ?></span><strong><?php esc_html_e( 'خودِ واقعی شما', 'parisan' ); ?></strong></div>
				<div class="hero-art-index">P / 01</div>
				<div class="hero-art-seal" aria-hidden="true">P</div>
			</div>
		</div>
		<div class="container hero-bottomline" aria-label="<?php esc_attr_e( 'ویژگی‌های تجربه پاریسان', 'parisan' ); ?>">
			<span><?php esc_html_e( 'توجه به جزئیات', 'parisan' ); ?></span>
			<span><?php esc_html_e( 'انتخاب آگاهانه خدمات', 'parisan' ); ?></span>
			<span><?php esc_html_e( 'آرامش در تمام مراحل', 'parisan' ); ?></span>
		</div>
	</section>

	<section class="intro-section parisan-home-intro">
		<div class="container intro-grid">
			<div>
				<p class="eyebrow"><?php echo esc_html( parisan_home_content( 'intro_eyebrow', 'تجربه‌ای متناسب با شما' ) ); ?></p>
				<h2><?php echo esc_html( parisan_home_content( 'intro_title', 'زیبایی، وقتی دلنشین‌تر است که برای شما معنا داشته باشد.' ) ); ?></h2>
			</div>
			<div class="intro-copy">
				<p><?php echo esc_html( parisan_home_content( 'intro_description', 'پاریسان فضایی برای انتخاب آگاهانه و مراقبت حرفه‌ای است؛ جایی که می‌توانید خدمات را با جزئیات ببینید، متخصص مناسب را بشناسید و با خیال آسوده برای زمان دلخواهتان برنامه‌ریزی کنید.' ) ); ?></p>
				<a class="text-link" href="#team"><?php esc_html_e( 'با متخصصان ما آشنا شوید', 'parisan' ); ?></a>
			</div>
		</div>
	</section>

	<section class="parisan-services-section" id="services">
		<div class="container">
			<div class="section-heading">
				<div><p class="eyebrow"><?php echo esc_html( parisan_home_content( 'services_eyebrow', 'خدمات منتخب' ) ); ?></p><h2><?php echo esc_html( parisan_home_content( 'services_title', 'خدماتی برای حالِ بهتر شما' ) ); ?></h2></div>
				<a class="text-link" href="<?php echo esc_url( post_type_exists( 'parisan_service' ) ? get_post_type_archive_link( 'parisan_service' ) : home_url( '/#booking' ) ); ?>"><?php esc_html_e( 'مشاهده همه خدمات', 'parisan' ); ?> <span aria-hidden="true">←</span></a>
			</div>
			<?php if ( $parisan_services ) : ?>
				<div class="service-grid parisan-home-service-grid">
					<?php foreach ( $parisan_services as $parisan_service ) : ?>
						<?php
						$parisan_duration = absint( get_post_meta( $parisan_service->ID, '_parisan_duration', true ) );
						$parisan_price = absint( get_post_meta( $parisan_service->ID, '_parisan_price', true ) );
						$parisan_price_type = get_post_meta( $parisan_service->ID, '_parisan_price_type', true );
						?>
						<article class="service-card">
							<a class="service-card-media" href="<?php echo esc_url( get_permalink( $parisan_service ) ); ?>" tabindex="-1" aria-hidden="true">
								<?php if ( has_post_thumbnail( $parisan_service ) ) : ?>
									<?php echo get_the_post_thumbnail( $parisan_service, 'large', array( 'loading' => 'lazy' ) ); ?>
								<?php else : ?>
									<img src="https://images.unsplash.com/photo-1675034743339-0b0747047727?auto=format&amp;fit=crop&amp;fm=jpg&amp;q=78&amp;w=900" alt="" loading="lazy" decoding="async">
								<?php endif; ?>
							</a>
							<div class="service-card-content">
								<div class="service-card-meta">
									<?php if ( $parisan_duration ) : ?><span><?php echo esc_html( sprintf( __( '%s دقیقه', 'parisan' ), number_format_i18n( $parisan_duration ) ) ); ?></span><?php endif; ?>
									<?php if ( $parisan_price ) : ?><span><?php echo esc_html( ( 'from' === $parisan_price_type ? __( 'از ', 'parisan' ) : '' ) . number_format_i18n( $parisan_price ) . ' ' . __( 'تومان', 'parisan' ) ); ?></span><?php endif; ?>
								</div>
								<h3><a href="<?php echo esc_url( get_permalink( $parisan_service ) ); ?>"><?php echo esc_html( get_the_title( $parisan_service ) ); ?></a></h3>
								<p><?php echo esc_html( wp_trim_words( get_the_excerpt( $parisan_service ), 20 ) ); ?></p>
								<a class="service-card-link" href="<?php echo esc_url( get_permalink( $parisan_service ) ); ?>"><?php esc_html_e( 'جزئیات و رزرو', 'parisan' ); ?> <span aria-hidden="true">←</span></a>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<div class="catalog-empty parisan-empty-state"><span aria-hidden="true">✳</span><p><?php esc_html_e( 'خدمات سالن به‌زودی در این بخش معرفی می‌شوند.', 'parisan' ); ?></p></div>
			<?php endif; ?>
		</div>
	</section>

	<section class="parisan-portfolio-section" id="portfolio">
		<div class="container">
			<div class="section-heading">
				<div><p class="eyebrow"><?php echo esc_html( parisan_home_content( 'portfolio_eyebrow', 'نمونه‌کارهای پاریسان' ) ); ?></p><h2><?php echo esc_html( parisan_home_content( 'portfolio_title', 'ظرافت را در جزئیات ببینید' ) ); ?></h2></div>
				<?php if ( post_type_exists( 'parisan_portfolio' ) ) : ?><a class="text-link" href="<?php echo esc_url( get_post_type_archive_link( 'parisan_portfolio' ) ); ?>"><?php esc_html_e( 'مشاهده همه نمونه‌کارها', 'parisan' ); ?> <span aria-hidden="true">←</span></a><?php endif; ?>
			</div>
			<?php if ( $parisan_portfolio ) : ?>
				<div class="parisan-portfolio-grid">
					<?php foreach ( $parisan_portfolio as $parisan_work ) : ?>
						<article class="portfolio-card">
							<a class="portfolio-card-media" href="<?php echo esc_url( get_permalink( $parisan_work ) ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'مشاهده نمونه‌کار %s', 'parisan' ), get_the_title( $parisan_work ) ) ); ?>">
								<?php if ( has_post_thumbnail( $parisan_work ) ) : ?>
									<?php echo get_the_post_thumbnail( $parisan_work, 'large', array( 'loading' => 'lazy' ) ); ?>
								<?php else : ?><img src="https://images.unsplash.com/photo-1699207012725-72af224cca65?auto=format&amp;fit=crop&amp;fm=jpg&amp;q=78&amp;w=900" alt="" loading="lazy" decoding="async"><?php endif; ?>
							</a>
							<div class="portfolio-card-content">
								<h3><a href="<?php echo esc_url( get_permalink( $parisan_work ) ); ?>"><?php echo esc_html( get_the_title( $parisan_work ) ); ?></a></h3>
								<p><?php echo esc_html( wp_trim_words( get_the_excerpt( $parisan_work ), 16 ) ); ?></p>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<div class="catalog-empty parisan-empty-state"><span aria-hidden="true">✳</span><p><?php esc_html_e( 'نمونه‌کارهای منتخب سالن به‌زودی در این بخش قرار می‌گیرند.', 'parisan' ); ?></p></div>
			<?php endif; ?>
		</div>
	</section>

	<section class="parisan-journal-section">
		<div class="container">
			<div class="section-heading">
				<div><p class="eyebrow"><?php echo esc_html( parisan_home_content( 'journal_eyebrow', 'مجلهٔ پاریسان' ) ); ?></p><h2><?php echo esc_html( parisan_home_content( 'journal_title', 'تازه‌هایی برای زیبایی و آرامش' ) ); ?></h2></div>
				<?php $parisan_blog_url = parisan_blog_index_url(); ?>
				<?php if ( $parisan_blog_url ) : ?><a class="text-link" href="<?php echo esc_url( $parisan_blog_url ); ?>"><?php esc_html_e( 'همه مطالب مجله', 'parisan' ); ?> <span aria-hidden="true">←</span></a><?php endif; ?>
			</div>
			<?php if ( $parisan_articles ) : ?><div class="post-grid">
				<?php foreach ( $parisan_articles as $parisan_article ) : ?>
					<article class="post-card">
						<a class="post-card-image" href="<?php echo esc_url( get_permalink( $parisan_article ) ); ?>"><?php if ( has_post_thumbnail( $parisan_article ) ) : ?><?php echo get_the_post_thumbnail( $parisan_article, 'large', array( 'loading' => 'lazy' ) ); ?><?php else : ?><img src="https://images.unsplash.com/photo-1675034743339-0b0747047727?auto=format&amp;fit=crop&amp;fm=jpg&amp;q=78&amp;w=900" alt="" loading="lazy" decoding="async"><?php endif; ?></a>
						<div class="post-card-content"><p class="post-meta"><?php echo esc_html( get_the_date( '', $parisan_article ) ); ?></p><h3><a href="<?php echo esc_url( get_permalink( $parisan_article ) ); ?>"><?php echo esc_html( get_the_title( $parisan_article ) ); ?></a></h3><p><?php echo esc_html( wp_trim_words( get_the_excerpt( $parisan_article ), 18 ) ); ?></p></div>
					</article>
				<?php endforeach; ?>
			</div><?php else : ?><p class="catalog-empty"><?php esc_html_e( 'مطالب مجله به‌زودی منتشر می‌شوند.', 'parisan' ); ?></p><?php endif; ?>
		</div>
	</section>

	<section class="parisan-editorial-band">
		<div class="container editorial-band-inner">
			<div class="editorial-mark" aria-hidden="true">P.</div>
			<div><p class="eyebrow"><?php echo esc_html( parisan_home_content( 'editorial_eyebrow', 'زمانی برای خودتان' ) ); ?></p><h2><?php echo esc_html( parisan_home_content( 'editorial_title', 'قرار نیست فقط زیباتر شوید؛ قرار است از این لحظه لذت ببرید.' ) ); ?></h2></div>
			<a class="button button-light" href="<?php echo esc_url( $parisan_booking_enabled ? parisan_home_content( 'editorial_button_url', '#booking' ) : home_url( '/contact/' ) ); ?>"><?php echo esc_html( $parisan_booking_enabled ? parisan_home_content( 'editorial_button_label', 'شروع تجربه من' ) : 'تماس با سالن' ); ?></a>
		</div>
	</section>

	<section class="parisan-team-section" id="team">
		<div class="container">
			<div class="section-heading">
				<div><p class="eyebrow"><?php echo esc_html( parisan_home_content( 'team_eyebrow', 'تیم متخصص پاریسان' ) ); ?></p><h2><?php echo esc_html( parisan_home_content( 'team_title', 'هنر، در دستان متخصصان ما' ) ); ?></h2></div>
				<?php if ( post_type_exists( 'parisan_staff' ) ) : ?><a class="text-link" href="<?php echo esc_url( get_post_type_archive_link( 'parisan_staff' ) ); ?>"><?php esc_html_e( 'آشنایی با تیم', 'parisan' ); ?> <span aria-hidden="true">←</span></a><?php endif; ?>
			</div>
			<?php if ( $parisan_staff ) : ?>
				<div class="specialist-grid parisan-home-team-grid">
					<?php foreach ( $parisan_staff as $parisan_member ) : ?>
						<article class="specialist-card">
							<a class="specialist-card-image" href="<?php echo esc_url( get_permalink( $parisan_member ) ); ?>" tabindex="-1" aria-hidden="true">
								<?php if ( has_post_thumbnail( $parisan_member ) ) : ?>
									<?php echo get_the_post_thumbnail( $parisan_member, 'large', array( 'loading' => 'lazy' ) ); ?>
								<?php else : ?><img src="https://images.unsplash.com/photo-1771254240529-dbe324f0a401?auto=format&amp;fit=crop&amp;fm=jpg&amp;q=78&amp;w=900" alt="" loading="lazy" decoding="async"><?php endif; ?>
							</a>
							<div class="specialist-card-content">
								<?php $parisan_role = get_post_meta( $parisan_member->ID, '_parisan_staff_role', true ); ?>
								<?php if ( $parisan_role ) : ?><p class="specialist-role"><?php echo esc_html( $parisan_role ); ?></p><?php endif; ?>
								<h3><a href="<?php echo esc_url( get_permalink( $parisan_member ) ); ?>"><?php echo esc_html( get_the_title( $parisan_member ) ); ?></a></h3>
								<p><?php echo esc_html( wp_trim_words( get_the_excerpt( $parisan_member ), 16 ) ); ?></p>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<div class="catalog-empty parisan-empty-state"><span aria-hidden="true">✳</span><p><?php esc_html_e( 'معرفی متخصصان سالن به‌زودی در این بخش قرار می‌گیرد.', 'parisan' ); ?></p></div>
			<?php endif; ?>
		</div>
	</section>

	<section class="parisan-booking-section" id="booking">
		<div class="container booking-section-heading">
			<p class="eyebrow"><?php echo esc_html( parisan_home_content( 'booking_eyebrow', 'MAKE TIME FOR YOURSELF' ) ); ?></p>
			<h2><?php echo esc_html( $parisan_booking_enabled ? parisan_home_content( 'booking_title', 'زمانی برای خودتان کنار بگذارید.' ) : 'برای هماهنگی نوبت با سالن در ارتباط باشید.' ); ?></h2>
			<p><?php echo esc_html( $parisan_booking_enabled ? parisan_home_content( 'booking_description', 'خدمت و متخصص دلخواهتان را انتخاب کنید و زمان‌های در دسترس را ببینید.' ) : 'رزرو آنلاین در حال حاضر فعال نیست. برای دریافت راهنمایی با سالن تماس بگیرید.' ); ?></p>
		</div>
		<?php if ( ! $parisan_booking_enabled ) : ?>
			<?php $parisan_contact_phone = parisan_public_setting( 'parisan_contact_phone' ); ?>
			<a class="button" href="<?php echo esc_url( $parisan_contact_phone ? 'tel:' . preg_replace( '/[^0-9+]/', '', $parisan_contact_phone ) : home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'هماهنگی با سالن', 'parisan' ); ?></a>
		<?php endif; ?>
		<div class="container parisan-booking-embed">
			<?php if ( shortcode_exists( 'parisan_booking' ) ) : ?>
				<?php echo do_shortcode( '[parisan_booking]' ); ?>
			<?php else : ?>
				<div class="parisan-booking-unavailable"><p><?php esc_html_e( 'برای نمایش نوبت‌دهی آنلاین، افزونه Parisan Core را نصب و فعال کنید.', 'parisan' ); ?></p></div>
			<?php endif; ?>
		</div>
	</section>

	<?php if ( have_posts() ) : ?>
		<section class="container content-loop parisan-home-content">
			<?php while ( have_posts() ) : the_post(); ?>
				<article <?php post_class( 'content-card' ); ?>>
					<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
					<?php the_excerpt(); ?>
				</article>
			<?php endwhile; ?>
		</section>
	<?php endif; ?>
</main>
<?php get_footer(); ?>
