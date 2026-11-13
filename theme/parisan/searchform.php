<?php
/**
 * Accessible Persian search form.
 *
 * @package Parisan
 */

defined( 'ABSPATH' ) || exit;

$parisan_search_id = wp_unique_id( 'parisan-search-' );
?>
<form role="search" method="get" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label for="<?php echo esc_attr( $parisan_search_id ); ?>">
		<span class="screen-reader-text"><?php esc_html_e( 'جستجو در پاریسان', 'parisan' ); ?></span>
		<input type="search" id="<?php echo esc_attr( $parisan_search_id ); ?>" class="search-field" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php esc_attr_e( 'جستجو در خدمات، نمونه‌کارها و مجله…', 'parisan' ); ?>" autocomplete="off">
	</label>
	<button type="submit" class="search-submit"><?php esc_html_e( 'جستجو', 'parisan' ); ?></button>
</form>
