<?php
/**
 * Single post template.
 *
 * @package Parisan
 */

defined( 'ABSPATH' ) || exit;
get_header();
?>
<main id="main-content" class="standard-content container">
	<?php while ( have_posts() ) : the_post(); ?>
		<article <?php post_class( 'standard-entry' ); ?>>
			<header class="entry-header">
				<p class="eyebrow"><?php echo esc_html( get_the_date() ); ?></p>
				<h1><?php the_title(); ?></h1>
				<p class="entry-byline"><?php echo esc_html( get_the_author() ); ?></p>
			</header>
			<?php if ( has_post_thumbnail() ) : ?><div class="entry-featured-image"><?php the_post_thumbnail( 'large' ); ?></div><?php endif; ?>
			<div class="entry-content"><?php the_content(); ?></div>
			<?php the_post_navigation(); ?>
		</article>
	<?php endwhile; ?>
</main>
<?php get_footer(); ?>
