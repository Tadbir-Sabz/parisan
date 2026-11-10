<?php
/**
 * FAQ page template (page slug: faq).
 *
 * @package Parisan
 */
defined( 'ABSPATH' ) || exit;
get_header();
$faqs = post_type_exists( 'parisan_faq' ) ? get_posts( array( 'post_type' => 'parisan_faq', 'post_status' => 'publish', 'posts_per_page' => 100, 'orderby' => array( 'menu_order' => 'ASC', 'date' => 'DESC' ) ) ) : array();
?>
<main id="main-content">
	<?php while ( have_posts() ) : the_post(); ?>
		<section class="parisan-page-hero"><div class="container"><p class="eyebrow"><?php esc_html_e( 'راهنما و پاسخ‌ها', 'parisan' ); ?></p><h1><?php the_title(); ?></h1><p><?php esc_html_e( 'پاسخ پرسش‌های رایج درباره خدمات، رزرو و مراجعه به سالن.', 'parisan' ); ?></p></div></section>
		<div class="container parisan-faq-list">
			<?php if ( $faqs ) : foreach ( $faqs as $faq_item ) : ?><details class="parisan-faq-item"><summary><?php echo esc_html( get_the_title( $faq_item ) ); ?></summary><div class="parisan-faq-answer"><?php echo apply_filters( 'the_content', $faq_item->post_content ); ?></div></details><?php endforeach; else : ?><div class="catalog-empty"><?php esc_html_e( 'پرسش‌های متداول هنوز ثبت نشده‌اند. مدیر سایت می‌تواند آن‌ها را از بخش پرسش‌های متداول در پیشخوان اضافه کند.', 'parisan' ); ?></div><?php endif; ?>
			<div class="entry-content"><?php the_content(); ?></div>
		</div>
	<?php endwhile; ?>
</main>
<?php get_footer(); ?>
