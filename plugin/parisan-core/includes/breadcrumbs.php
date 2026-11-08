<?php
/**
 * Accessible breadcrumbs and BreadcrumbList structured data.
 *
 * @package ParisanCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Append a taxonomy term and its hierarchical ancestors to breadcrumb items.
 *
 * @param array    $items Breadcrumb items.
 * @param WP_Term  $term  Current term.
 * @return array
 */
function parisan_core_append_term_breadcrumbs( $items, $term ) {
	if ( ! $term instanceof WP_Term ) {
		return $items;
	}

	$ancestors = get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' );
	$ancestors = array_reverse( $ancestors );
	foreach ( $ancestors as $ancestor_id ) {
		$ancestor = get_term( $ancestor_id, $term->taxonomy );
		if ( $ancestor instanceof WP_Term && ! is_wp_error( get_term_link( $ancestor ) ) ) {
			$items[] = array(
				'label' => $ancestor->name,
				'url'   => get_term_link( $ancestor ),
			);
		}
	}

	$items[] = array(
		'label' => $term->name,
		'url'   => '',
	);
	return $items;
}

/**
 * Build a context-aware breadcrumb trail for the current WordPress query.
 *
 * @return array
 */
function parisan_core_get_breadcrumb_items() {
	if ( is_front_page() ) {
		return array();
	}

	$items = array(
		array(
			'label' => __( 'خانه', 'parisan-core' ),
			'url'   => home_url( '/' ),
		),
	);

	if ( is_singular() ) {
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return array();
		}

		$post_type = get_post_type( $post );
		if ( in_array( $post_type, array( 'parisan_service', 'parisan_staff' ), true ) ) {
			$type_object = get_post_type_object( $post_type );
			$archive_url = get_post_type_archive_link( $post_type );
			if ( $type_object && $archive_url ) {
				$items[] = array(
					'label' => $type_object->labels->name,
					'url'   => $archive_url,
				);
			}
		} elseif ( 'page' === $post_type ) {
			$ancestors = array_reverse( get_post_ancestors( $post->ID ) );
			foreach ( $ancestors as $ancestor_id ) {
				$items[] = array(
					'label' => get_the_title( $ancestor_id ),
					'url'   => get_permalink( $ancestor_id ),
				);
			}
		} elseif ( 'post' === $post_type ) {
			$posts_page_id = absint( get_option( 'page_for_posts' ) );
			if ( $posts_page_id ) {
				$items[] = array(
					'label' => get_the_title( $posts_page_id ),
					'url'   => get_permalink( $posts_page_id ),
				);
			}
			$categories = get_the_category( $post->ID );
			if ( ! empty( $categories ) && $categories[0] instanceof WP_Term ) {
				$items = parisan_core_append_term_breadcrumbs( $items, $categories[0] );
			}
		} else {
			$type_object = get_post_type_object( $post_type );
			$archive_url = get_post_type_archive_link( $post_type );
			if ( $type_object && $archive_url ) {
				$items[] = array(
					'label' => $type_object->labels->name,
					'url'   => $archive_url,
				);
			}
		}

		$items[] = array(
			'label' => get_the_title( $post ),
			'url'   => '',
		);
		return $items;
	}

	if ( is_home() ) {
		$posts_page_id = absint( get_option( 'page_for_posts' ) );
		$items[] = array(
			'label' => $posts_page_id ? get_the_title( $posts_page_id ) : __( 'نوشته‌ها', 'parisan-core' ),
			'url'   => '',
		);
		return $items;
	}

	if ( is_post_type_archive() ) {
		$post_type = get_query_var( 'post_type' );
		$post_type = is_array( $post_type ) ? reset( $post_type ) : $post_type;
		$type_object = $post_type ? get_post_type_object( $post_type ) : null;
		$items[] = array(
			'label' => $type_object ? $type_object->labels->name : get_the_archive_title(),
			'url'   => '',
		);
		return $items;
	}

	if ( is_category() || is_tag() || is_tax() ) {
		$term = get_queried_object();
		return parisan_core_append_term_breadcrumbs( $items, $term );
	}

	if ( is_search() ) {
		$items[] = array(
			'label' => sprintf( __( 'نتایج جستجو برای «%s»', 'parisan-core' ), get_search_query() ),
			'url'   => '',
		);
		return $items;
	}

	if ( is_404() ) {
		$items[] = array(
			'label' => __( 'صفحه پیدا نشد', 'parisan-core' ),
			'url'   => '',
		);
		return $items;
	}

	if ( is_author() ) {
		$items[] = array(
			'label' => get_the_author_meta( 'display_name', get_queried_object_id() ),
			'url'   => '',
		);
		return $items;
	}

	if ( is_date() ) {
		$items[] = array(
			'label' => get_the_archive_title(),
			'url'   => '',
		);
		return $items;
	}

	return array();
}

/**
 * Render the semantic, RTL-friendly breadcrumb navigation.
 *
 * @return void
 */
function parisan_core_render_breadcrumbs() {
	$items = parisan_core_get_breadcrumb_items();
	if ( count( $items ) < 2 ) {
		return;
	}

	echo '<nav class="parisan-breadcrumbs container" aria-label="' . esc_attr__( 'مسیر صفحه', 'parisan-core' ) . '"><ol>';
	$last_index = count( $items ) - 1;
	foreach ( $items as $index => $item ) {
		echo '<li>';
		if ( $index !== $last_index && ! empty( $item['url'] ) ) {
			printf( '<a href="%1$s">%2$s</a>', esc_url( $item['url'] ), esc_html( $item['label'] ) );
		} else {
			printf( '<span aria-current="page">%s</span>', esc_html( $item['label'] ) );
		}
		echo '</li>';
	}
	echo '</ol></nav>';
}

/**
 * Emit BreadcrumbList JSON-LD when Parisan owns structured-data output.
 *
 * @return void
 */
function parisan_core_output_breadcrumb_schema() {
	if ( function_exists( 'parisan_core_seo_plugin_controls_output' ) && parisan_core_seo_plugin_controls_output() ) {
		return;
	}

	$items = parisan_core_get_breadcrumb_items();
	if ( count( $items ) < 2 ) {
		return;
	}

	$elements = array();
	foreach ( $items as $index => $item ) {
		$element = array(
			'@type'    => 'ListItem',
			'position' => $index + 1,
			'name'     => wp_strip_all_tags( $item['label'] ),
		);
		if ( ! empty( $item['url'] ) ) {
			$element['item'] = esc_url_raw( $item['url'] );
		}
		$elements[] = $element;
	}

	$schema = array(
		'@context'        => 'https://schema.org',
		'@type'           => 'BreadcrumbList',
		'itemListElement' => $elements,
	);
	echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . PHP_EOL;
}
add_action( 'wp_head', 'parisan_core_output_breadcrumb_schema', 3 );
