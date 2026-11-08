<?php
/**
 * About page template (page slug: about).
 *
 * @package Parisan
 */
defined( 'ABSPATH' ) || exit;
get_header();
?>
<main id="main-content">
	<?php while ( have_posts() ) : the_post(); ?>
		<section class="parisan-page-hero"><div class="container"><p class="eyebrow"><?php esc_html_e( 'OUR STORY · PARISAN BEAUTY', 'parisan' ); ?></p><h1><?php the_title(); ?></h1><p><?php esc_html_e( 'زیبایی، تجربه‌ای شخصی است؛ ما اینجا هستیم تا آن را با دقت، آرامش و احترام همراه کنیم.', 'parisan' ); ?></p></div></section>
		<article <?php post_class( 'parisan-page-content container' ); ?>>
			<?php if ( has_post_thumbnail() ) : ?><div class="entry-featured-image"><?php the_post_thumbnail( 'large' ); ?></div><?php endif; ?>
			<div class="entry-content"><?php the_content(); ?></div>
		</article>
	<?php endwhile; ?>
</main>
<?php get_footer(); ?>
