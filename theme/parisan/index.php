<?php
/**
 * Main template fallback.
 *
 * @package Parisan
 */

defined( 'ABSPATH' ) || exit;
get_header();
?>
<main id="main-content">
	<section class="hero-section">
		<div class="container hero-grid">
			<div class="hero-copy">
				<p class="eyebrow"><?php esc_html_e( 'تجربه‌ای برای درخشش شما', 'parisan' ); ?></p>
				<h1><?php esc_html_e( 'زیبایی، فراتر از یک انتخاب', 'parisan' ); ?></h1>
				<p class="hero-description"><?php esc_html_e( 'در فضایی آرام و حرفه‌ای، لحظه‌ای را به خودتان اختصاص دهید. خدمات تخصصی، همراه با توجهی که شایسته شماست.', 'parisan' ); ?></p>
				<div class="hero-actions">
					<a class="button" href="#booking"><?php esc_html_e( 'رزرو نوبت', 'parisan' ); ?></a>
					<a class="text-link" href="#services"><?php esc_html_e( 'مشاهده خدمات', 'parisan' ); ?></a>
				</div>
				<div class="hero-note"><span class="hero-note-mark" aria-hidden="true">✳</span><?php esc_html_e( 'زیبایی با امضای سلیقه شما', 'parisan' ); ?></div>
			</div>
			<div class="hero-art" aria-label="<?php esc_attr_e( 'فضای تصویری معرفی سالن زیبایی', 'parisan' ); ?>">
				<div class="hero-art-frame">
					<span class="art-caption"><?php esc_html_e( 'YOUR MOMENT', 'parisan' ); ?></span>
					<span class="art-number">01</span>
				</div>
				<div class="hero-stamp"><?php esc_html_e( 'CARE · BEAUTY · YOU', 'parisan' ); ?></div>
			</div>
		</div>
	</section>

	<section class="intro-section" id="services">
		<div class="container intro-grid">
			<div>
				<p class="eyebrow"><?php esc_html_e( 'خدمات ما', 'parisan' ); ?></p>
				<h2><?php esc_html_e( 'برای لحظه‌هایی که فقط متعلق به شماست', 'parisan' ); ?></h2>
			</div>
			<p><?php esc_html_e( 'از مشاوره تا اجرای خدمات، جزئیات اهمیت دارند. خدمات و تعرفه‌های سالن را از پنل وردپرس مدیریت کنید و این بخش را با محتوای واقعی برند خود کامل کنید.', 'parisan' ); ?></p>
		</div>
		<div class="container service-placeholder-grid">
			<article class="service-placeholder"><span>01</span><h3><?php esc_html_e( 'مراقبت و زیبایی مو', 'parisan' ); ?></h3><p><?php esc_html_e( 'خدمات تخصصی متناسب با نیاز شما', 'parisan' ); ?></p></article>
			<article class="service-placeholder"><span>02</span><h3><?php esc_html_e( 'زیبایی و میکاپ', 'parisan' ); ?></h3><p><?php esc_html_e( 'برای روزهای خاص و هر روز شما', 'parisan' ); ?></p></article>
			<article class="service-placeholder"><span>03</span><h3><?php esc_html_e( 'مراقبت پوست', 'parisan' ); ?></h3><p><?php esc_html_e( 'توجه حرفه‌ای به سلامت و شادابی', 'parisan' ); ?></p></article>
		</div>
	</section>

	<section class="booking-cta" id="booking">
		<div class="container booking-cta-inner">
			<div><p class="eyebrow"><?php esc_html_e( 'وقت شما ارزشمند است', 'parisan' ); ?></p><h2><?php esc_html_e( 'برای تجربه بعدی خود آماده‌اید؟', 'parisan' ); ?></h2></div>
			<p><?php esc_html_e( 'سامانه نوبت‌دهی آنلاین با فعال‌سازی افزونه Parisan Core در دسترس قرار می‌گیرد.', 'parisan' ); ?></p>
		</div>
	</section>

	<div class="container content-loop">
		<?php if ( have_posts() ) : ?>
			<?php while ( have_posts() ) : the_post(); ?>
				<article <?php post_class( 'content-card' ); ?>>
					<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
					<?php the_excerpt(); ?>
				</article>
			<?php endwhile; ?>
			<?php the_posts_pagination(); ?>
		<?php else : ?>
			<p><?php esc_html_e( 'هنوز محتوایی منتشر نشده است.', 'parisan' ); ?></p>
		<?php endif; ?>
	</div>
</main>
<?php get_footer(); ?>
