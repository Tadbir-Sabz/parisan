<?php
/**
 * Standard page template.
 *
 * @package Parisan
 */

defined( 'ABSPATH' ) || exit;
get_header();
?>
<main id="main-content" class="standard-content container">
	<?php while ( have_posts() ) : the_post(); ?>
		<article <?php post_class( 'standard-entry' ); ?>>
			<header class="entry-header"><p class="eyebrow"><?php esc_html_e( 'پاریسان', 'parisan' ); ?></p><h1><?php the_title(); ?></h1></header>
			<?php if ( has_post_thumbnail() ) : ?><div class="entry-featured-image"><?php the_post_thumbnail( 'large' ); ?></div><?php endif; ?>
			<div class="entry-content"><?php the_content(); ?></div>
			<?php wp_link_pages( array( 'before' => '<nav class="page-links">' . esc_html__( 'صفحات:', 'parisan' ), 'after' => '</nav>' ) ); ?>
		</article>
	<?php endwhile; ?>
</main>
<?php get_footer(); ?>
