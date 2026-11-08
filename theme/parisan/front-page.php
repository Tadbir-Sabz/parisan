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

$parisan_hero_image = get_theme_mod( 'parisan_hero_image', '' );

get_header();
?>
<main id="main-content" class="parisan-home">
	<section class="hero-section">
		<div class="container hero-grid">
			<div class="hero-copy">
				<p class="eyebrow"><?php esc_html_e( 'PARISAN BEAUTY STUDIO · زیبایی با آرامش', 'parisan' ); ?></p>
				<h1><?php esc_html_e( 'زیباییِ شما، با امضای ظرافت', 'parisan' ); ?></h1>
				<p class="hero-description"><?php esc_html_e( 'از لحظه‌ای که وارد می‌شوید تا آخرین جزئیات خدمات، همه‌چیز برای یک تجربه دلنشین، دقیق و شایسته شما طراحی شده است.', 'parisan' ); ?></p>
				<div class="hero-actions">
					<a class="button" href="#booking"><?php esc_html_e( 'انتخاب زمان و رزرو', 'parisan' ); ?></a>
					<a class="text-link" href="#services"><?php esc_html_e( 'آشنایی با خدمات', 'parisan' ); ?></a>
				</div>
				<div class="hero-note"><span class="hero-note-mark" aria-hidden="true">✳</span><span><?php esc_html_e( 'لحظه‌ای برای خودتان؛ تجربه‌ای با توجه به جزئیات', 'parisan' ); ?></span></div>
			</div>
			<div class="hero-art" role="img" aria-label="<?php echo esc_attr( $parisan_hero_image ? __( 'تصویر منتخب سالن زیبایی', 'parisan' ) : __( 'ترکیب هنری انتزاعی با الهام از زیبایی و ظرافت', 'parisan' ) ); ?>">
				<?php if ( $parisan_hero_image ) : ?>
					<img class="hero-art-photo" src="<?php echo esc_url( $parisan_hero_image ); ?>" alt="" loading="eager" decoding="async">
				<?php endif; ?>
				<div class="hero-art-orbit hero-art-orbit-one"></div>
				<div class="hero-art-orbit hero-art-orbit-two"></div>
				<div class="hero-art-petal hero-art-petal-one"></div>
				<div class="hero-art-petal hero-art-petal-two"></div>
				<div class="hero-art-label"><span><?php esc_html_e( 'THE ART OF', 'parisan' ); ?></span><strong><?php esc_html_e( 'BEING YOU', 'parisan' ); ?></strong></div>
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
				<p class="eyebrow"><?php esc_html_e( 'A MORE PERSONAL EXPERIENCE', 'parisan' ); ?></p>
				<h2><?php esc_html_e( 'زیبایی، وقتی دلنشین‌تر است که برای شما معنا داشته باشد.', 'parisan' ); ?></h2>
			</div>
			<div class="intro-copy">
				<p><?php esc_html_e( 'پاریسان فضایی برای انتخاب آگاهانه و مراقبت حرفه‌ای است؛ جایی که می‌توانید خدمات را با جزئیات ببینید، متخصص مناسب را بشناسید و با خیال آسوده برای زمان دلخواهتان برنامه‌ریزی کنید.', 'parisan' ); ?></p>
				<a class="text-link" href="#team"><?php esc_html_e( 'با متخصصان ما آشنا شوید', 'parisan' ); ?></a>
			</div>
		</div>
	</section>

	<section class="parisan-services-section" id="services">
		<div class="container">
			<div class="section-heading">
				<div><p class="eyebrow"><?php esc_html_e( 'THE SERVICE EDIT', 'parisan' ); ?></p><h2><?php esc_html_e( 'خدماتی برای حالِ بهتر شما', 'parisan' ); ?></h2></div>
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
									<span class="service-card-placeholder" aria-hidden="true">✳</span>
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
				<div><p class="eyebrow"><?php esc_html_e( 'THE PARISAN PORTFOLIO', 'parisan' ); ?></p><h2><?php esc_html_e( 'ظرافت را در جزئیات ببینید', 'parisan' ); ?></h2></div>
				<?php if ( post_type_exists( 'parisan_portfolio' ) ) : ?><a class="text-link" href="<?php echo esc_url( get_post_type_archive_link( 'parisan_portfolio' ) ); ?>"><?php esc_html_e( 'مشاهده همه نمونه‌کارها', 'parisan' ); ?> <span aria-hidden="true">←</span></a><?php endif; ?>
			</div>
			<?php if ( $parisan_portfolio ) : ?>
				<div class="parisan-portfolio-grid">
					<?php foreach ( $parisan_portfolio as $parisan_work ) : ?>
						<article class="portfolio-card">
							<a class="portfolio-card-media" href="<?php echo esc_url( get_permalink( $parisan_work ) ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'مشاهده نمونه‌کار %s', 'parisan' ), get_the_title( $parisan_work ) ) ); ?>">
								<?php if ( has_post_thumbnail( $parisan_work ) ) : ?>
									<?php echo get_the_post_thumbnail( $parisan_work, 'large', array( 'loading' => 'lazy' ) ); ?>
								<?php else : ?><span aria-hidden="true">✳</span><?php endif; ?>
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

	<section class="parisan-editorial-band">
		<div class="container editorial-band-inner">
			<div class="editorial-mark" aria-hidden="true">P.</div>
			<div><p class="eyebrow"><?php esc_html_e( 'YOUR TIME, YOUR STYLE', 'parisan' ); ?></p><h2><?php esc_html_e( 'قرار نیست فقط زیباتر شوید؛ قرار است از این لحظه لذت ببرید.', 'parisan' ); ?></h2></div>
			<a class="button button-light" href="#booking"><?php esc_html_e( 'شروع تجربه من', 'parisan' ); ?></a>
		</div>
	</section>

	<section class="parisan-team-section" id="team">
		<div class="container">
			<div class="section-heading">
				<div><p class="eyebrow"><?php esc_html_e( 'THE PEOPLE BEHIND THE CARE', 'parisan' ); ?></p><h2><?php esc_html_e( 'هنر، در دستان متخصصان ما', 'parisan' ); ?></h2></div>
				<?php if ( post_type_exists( 'parisan_staff' ) ) : ?><a class="text-link" href="<?php echo esc_url( get_post_type_archive_link( 'parisan_staff' ) ); ?>"><?php esc_html_e( 'آشنایی با تیم', 'parisan' ); ?> <span aria-hidden="true">←</span></a><?php endif; ?>
			</div>
			<?php if ( $parisan_staff ) : ?>
				<div class="specialist-grid parisan-home-team-grid">
					<?php foreach ( $parisan_staff as $parisan_member ) : ?>
						<article class="specialist-card">
							<a class="specialist-card-image" href="<?php echo esc_url( get_permalink( $parisan_member ) ); ?>" tabindex="-1" aria-hidden="true">
								<?php if ( has_post_thumbnail( $parisan_member ) ) : ?>
									<?php echo get_the_post_thumbnail( $parisan_member, 'large', array( 'loading' => 'lazy' ) ); ?>
								<?php else : ?><span aria-hidden="true">✳</span><?php endif; ?>
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
			<p class="eyebrow"><?php esc_html_e( 'MAKE TIME FOR YOURSELF', 'parisan' ); ?></p>
			<h2><?php esc_html_e( 'زمانی برای خودتان کنار بگذارید.', 'parisan' ); ?></h2>
			<p><?php esc_html_e( 'خدمت و متخصص دلخواهتان را انتخاب کنید و زمان‌های در دسترس را ببینید.', 'parisan' ); ?></p>
		</div>
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
