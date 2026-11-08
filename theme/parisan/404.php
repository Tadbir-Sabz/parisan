<?php
/**
 * Not-found template.
 *
 * @package Parisan
 */

defined( 'ABSPATH' ) || exit;
get_header();
?>
<main id="main-content" class="not-found-page container">
	<p class="eyebrow"><?php esc_html_e( 'خطای ۴۰۴', 'parisan' ); ?></p>
	<h1><?php esc_html_e( 'این صفحه پیدا نشد', 'parisan' ); ?></h1>
	<p><?php esc_html_e( 'ممکن است نشانی تغییر کرده باشد. از جستجو استفاده کنید یا به صفحه اصلی برگردید.', 'parisan' ); ?></p>
	<?php get_search_form(); ?>
	<a class="button" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'بازگشت به صفحه اصلی', 'parisan' ); ?></a>
</main>
<?php get_footer(); ?>
