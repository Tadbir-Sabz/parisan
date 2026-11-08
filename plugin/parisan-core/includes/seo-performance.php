<?php
/**
 * First-party SEO metadata and privacy-aware cache boundaries.
 *
 * SEO output yields to an installed SEO plugin to prevent duplicate tags.
 * Booking/customer contexts are explicitly marked as non-cacheable.
 *
 * @package ParisanCore
 */

defined( 'ABSPATH' ) || exit;

define( 'PARISAN_CORE_SEO_OPTION', 'parisan_core_seo_settings' );

/**
 * Return sanitized SEO defaults.
 *
 * @return array
 */
function parisan_core_seo_settings() {
	$defaults = array(
		'default_description' => '',
		'organization_name'   => get_bloginfo( 'name' ),
		'organization_url'    => home_url( '/' ),
		'output_json_ld'      => 1,
	);
	$stored = get_option( PARISAN_CORE_SEO_OPTION, array() );
	$stored = is_array( $stored ) ? $stored : array();
	$settings = wp_parse_args( $stored, $defaults );
	$settings['default_description'] = sanitize_textarea_field( $settings['default_description'] );
	$settings['organization_name'] = sanitize_text_field( $settings['organization_name'] );
	$settings['organization_url'] = esc_url_raw( $settings['organization_url'] );
	$settings['output_json_ld'] = empty( $settings['output_json_ld'] ) ? 0 : 1;
	return $settings;
}

/**
 * Detect popular SEO plugins before emitting overlapping metadata.
 *
 * @return bool
 */
function parisan_core_seo_plugin_controls_output() {
	return defined( 'WPSEO_VERSION' )
		|| defined( 'RANK_MATH_VERSION' )
		|| defined( 'SEOPRESS_VERSION' )
		|| defined( 'AIOSEO_VERSION' )
		|| class_exists( '\The_SEO_Framework\Load' );
}

/**
 * Get a safe description for the current public singular/archive view.
 *
 * @return string
 */
function parisan_core_current_meta_description() {
	$description = '';
	if ( is_singular() ) {
		$post = get_queried_object();
		if ( $post instanceof WP_Post ) {
			$custom_description = get_post_meta( $post->ID, '_parisan_seo_description', true );
			$description = $custom_description ? $custom_description : ( has_excerpt( $post ) ? $post->post_excerpt : wp_strip_all_tags( $post->post_content ) );
		}
	}
	if ( '' === trim( $description ) ) {
		$description = parisan_core_seo_settings()['default_description'];
	}
	$description = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $description ) ) );
	if ( function_exists( 'mb_substr' ) ) {
		return mb_substr( $description, 0, 160 );
	}
	return substr( $description, 0, 160 );
}

/**
 * Emit first-party metadata only when no SEO plugin owns the document head.
 *
 * @return void
 */
function parisan_core_output_seo_metadata() {
	if ( is_admin() || is_feed() || is_404() || parisan_core_seo_plugin_controls_output() || ! ( is_singular() || is_front_page() || is_post_type_archive() ) ) {
		return;
	}
	$description = parisan_core_current_meta_description();
	$url = home_url( '/' );
	if ( is_singular() ) {
		$url = get_permalink();
	} elseif ( is_post_type_archive() ) {
		$queried_type = get_query_var( 'post_type' );
		if ( is_string( $queried_type ) && $queried_type ) {
			$url = get_post_type_archive_link( $queried_type );
		}
	}
	$title = wp_get_document_title();
	if ( is_singular() ) {
		$custom_title = get_post_meta( get_queried_object_id(), '_parisan_seo_title', true );
		if ( $custom_title ) {
			$title = $custom_title;
		}
	}
	if ( $description ) {
		printf( "<meta name=\"description\" content=\"%s\">\n", esc_attr( $description ) );
	}
	if ( $url ) {
		printf( "<link rel=\"canonical\" href=\"%s\">\n", esc_url( $url ) );
		printf( "<meta property=\"og:url\" content=\"%s\">\n", esc_url( $url ) );
	}
	printf( "<meta property=\"og:type\" content=\"%s\">\n", is_singular( 'parisan_service' ) ? 'product' : 'website' );
	printf( "<meta property=\"og:title\" content=\"%s\">\n", esc_attr( $title ) );
	if ( $description ) {
		printf( "<meta property=\"og:description\" content=\"%s\">\n", esc_attr( $description ) );
	}
	if ( is_singular() && has_post_thumbnail() ) {
		$image = get_the_post_thumbnail_url( get_queried_object_id(), 'large' );
		if ( $image ) {
			printf( "<meta property=\"og:image\" content=\"%s\">\n", esc_url( $image ) );
		}
	}
	$settings = parisan_core_seo_settings();
	if ( $settings['output_json_ld'] && ! is_front_page() && is_singular( 'parisan_service' ) ) {
		$post_id = get_queried_object_id();
		$schema = array(
			'@context' => 'https://schema.org',
			'@type' => 'Service',
			'name' => get_the_title( $post_id ),
			'url' => get_permalink( $post_id ),
			'provider' => array(
				'@type' => 'BeautySalon',
				'name' => $settings['organization_name'],
				'url' => $settings['organization_url'],
			),
		);
		$excerpt = get_the_excerpt( $post_id );
		if ( $excerpt ) {
			$schema['description'] = wp_strip_all_tags( $excerpt );
		}
		echo "<script type=\"application/ld+json\">" . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
	}
}
add_action( 'wp_head', 'parisan_core_output_seo_metadata', 2 );

/**
 * Add per-content SEO fields to public content editors.
 *
 * @return void
 */
function parisan_core_add_seo_meta_boxes() {
	foreach ( get_post_types( array( 'public' => true ), 'names' ) as $post_type ) {
		add_meta_box( 'parisan-seo-fields', __( 'پیش‌نمایش و تنظیمات سئو', 'parisan-core' ), 'parisan_core_render_seo_meta_box', $post_type, 'normal', 'default' );
	}
}
add_action( 'add_meta_boxes', 'parisan_core_add_seo_meta_boxes' );

/**
 * Render per-content SEO fields.
 *
 * @param WP_Post $post Current post.
 * @return void
 */
function parisan_core_render_seo_meta_box( $post ) {
	wp_nonce_field( 'parisan_save_seo_meta', 'parisan_seo_nonce' );
	$title = get_post_meta( $post->ID, '_parisan_seo_title', true );
	$description = get_post_meta( $post->ID, '_parisan_seo_description', true );
	$noindex = get_post_meta( $post->ID, '_parisan_seo_noindex', true );
	echo '<p><label for="parisan-seo-title"><strong>' . esc_html__( 'عنوان سئو', 'parisan-core' ) . '</strong></label><br>';
	printf( '<input id="parisan-seo-title" class="widefat" maxlength="180" name="parisan_seo_title" value="%s">', esc_attr( $title ) );
	echo '</p><p><label for="parisan-seo-description"><strong>' . esc_html__( 'توضیحات متا', 'parisan-core' ) . '</strong></label><br>';
	printf( '<textarea id="parisan-seo-description" class="widefat" rows="3" maxlength="320" name="parisan_seo_description">%s</textarea>', esc_textarea( $description ) );
	echo '<span class="description">' . esc_html__( 'پیشنهاد: توضیح کوتاه و منحصربه‌فرد؛ خروجی در ۱۶۰ نویسه محدود می‌شود.', 'parisan-core' ) . '</span></p>';
	printf( '<p><label><input type="checkbox" name="parisan_seo_noindex" value="1" %s> %s</label></p>', checked( '1', $noindex, false ), esc_html__( 'این صفحه در موتورهای جست‌وجو ایندکس نشود (noindex)', 'parisan-core' ) );
}

/**
 * Save per-content SEO fields after capability and nonce validation.
 *
 * @param int $post_id Current post ID.
 * @return void
 */
function parisan_core_save_seo_meta( $post_id ) {
	if ( ! isset( $_POST['parisan_seo_nonce'] ) || ! is_string( $_POST['parisan_seo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['parisan_seo_nonce'] ) ), 'parisan_save_seo_meta' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$title = isset( $_POST['parisan_seo_title'] ) && is_scalar( $_POST['parisan_seo_title'] ) ? sanitize_text_field( wp_unslash( $_POST['parisan_seo_title'] ) ) : '';
	$description = isset( $_POST['parisan_seo_description'] ) && is_scalar( $_POST['parisan_seo_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['parisan_seo_description'] ) ) : '';
	$noindex = isset( $_POST['parisan_seo_noindex'] ) && '1' === (string) wp_unslash( $_POST['parisan_seo_noindex'] ) ? '1' : '0';
	update_post_meta( $post_id, '_parisan_seo_title', $title );
	update_post_meta( $post_id, '_parisan_seo_description', $description );
	update_post_meta( $post_id, '_parisan_seo_noindex', $noindex );
}
add_action( 'save_post', 'parisan_core_save_seo_meta' );

/**
 * Respect the editor's noindex choice when Parisan owns SEO metadata.
 *
 * @param array $robots WordPress robots directives.
 * @return array
 */
function parisan_core_filter_robots( $robots ) {
	if ( parisan_core_seo_plugin_controls_output() || ! is_singular() ) {
		return $robots;
	}
	$post_id = get_queried_object_id();
	if ( $post_id && '1' === get_post_meta( $post_id, '_parisan_seo_noindex', true ) ) {
		$robots['noindex'] = true;
		unset( $robots['index'] );
	}
	return $robots;
}
add_filter( 'wp_robots', 'parisan_core_filter_robots', 20 );

/**
 * Exclude Parisan noindex content from the WordPress core XML sitemap.
 *
 * The first-party noindex control is authoritative only when Parisan owns
 * metadata output. Existing sitemap query constraints are preserved.
 *
 * @param array  $args     WordPress sitemap query arguments.
 * @param string $post_type Current post type.
 * @return array
 */
function parisan_core_exclude_noindex_from_sitemap( $args, $post_type ) {
	if ( parisan_core_seo_plugin_controls_output() || ! is_array( $args ) || ! is_string( $post_type ) || ! post_type_exists( $post_type ) ) {
		return $args;
	}

	$noindex_clause = array(
		'relation' => 'OR',
		array(
			'key'     => '_parisan_seo_noindex',
			'compare' => 'NOT EXISTS',
		),
		array(
			'key'     => '_parisan_seo_noindex',
			'value'   => '1',
			'compare' => '!=',
		),
	);

	$existing_meta_query = isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ? $args['meta_query'] : array();
	if ( ! empty( $existing_meta_query ) ) {
		$args['meta_query'] = array(
			'relation' => 'AND',
			$existing_meta_query,
			$noindex_clause,
		);
	} else {
		$args['meta_query'] = array( $noindex_clause );
	}

	return $args;
}
add_filter( 'wp_sitemaps_posts_query_args', 'parisan_core_exclude_noindex_from_sitemap', 10, 2 );



/** Remove WordPress core canonical output; Parisan or an SEO plugin owns it. */
function parisan_core_remove_core_canonical() {
	remove_action( 'wp_head', 'rel_canonical' );
}
add_action( 'init', 'parisan_core_remove_core_canonical', 1 );

/**
 * Register a small Persian-first SEO settings screen.
 *
 * @return void
 */
function parisan_core_register_seo_settings() {
	register_setting(
		'parisan_core_seo',
		PARISAN_CORE_SEO_OPTION,
		array(
			'type' => 'array',
			'sanitize_callback' => 'parisan_core_sanitize_seo_settings',
			'default' => array(),
		)
	);
	add_settings_section( 'parisan_core_seo_defaults', __( 'اطلاعات پایه سئو', 'parisan-core' ), '__return_false', 'parisan-core-seo' );
	add_settings_field( 'default_description', __( 'توضیحات پیش‌فرض سایت', 'parisan-core' ), 'parisan_core_render_seo_description_field', 'parisan-core-seo', 'parisan_core_seo_defaults' );
	add_settings_field( 'organization_name', __( 'نام سالن / کسب‌وکار', 'parisan-core' ), 'parisan_core_render_seo_name_field', 'parisan-core-seo', 'parisan_core_seo_defaults' );
	add_settings_field( 'organization_url', __( 'نشانی رسمی سایت', 'parisan-core' ), 'parisan_core_render_seo_url_field', 'parisan-core-seo', 'parisan_core_seo_defaults' );
	add_settings_field( 'output_json_ld', __( 'داده ساختاریافته خدمات', 'parisan-core' ), 'parisan_core_render_seo_schema_field', 'parisan-core-seo', 'parisan_core_seo_defaults' );

}
add_action( 'admin_init', 'parisan_core_register_seo_settings' );

/** Register the SEO page in the WordPress admin menu. */
function parisan_core_add_seo_admin_menu() {
	add_submenu_page( 'edit.php?post_type=parisan_staff', __( 'سئو و عملکرد', 'parisan-core' ), __( 'سئو و عملکرد', 'parisan-core' ), 'manage_options', 'parisan-core-seo', 'parisan_core_render_seo_settings_page' );
}
add_action( 'admin_menu', 'parisan_core_add_seo_admin_menu' );

/**
 * Sanitize SEO settings.
 *
 * @param mixed $input Submitted settings.
 * @return array
 */
function parisan_core_sanitize_seo_settings( $input ) {
	$input = is_array( $input ) ? $input : array();
	return array(
		'default_description' => isset( $input['default_description'] ) ? sanitize_textarea_field( $input['default_description'] ) : '',
		'organization_name' => isset( $input['organization_name'] ) ? sanitize_text_field( $input['organization_name'] ) : '',
		'organization_url' => isset( $input['organization_url'] ) ? esc_url_raw( $input['organization_url'] ) : '',
		'output_json_ld' => empty( $input['output_json_ld'] ) ? 0 : 1,
	);
}

/** Render default description setting. */
function parisan_core_render_seo_description_field() {
	$s = parisan_core_seo_settings();
	printf( '<textarea class="large-text" rows="3" maxlength="320" name="%1$s[default_description]">%2$s</textarea><p class="description">%3$s</p>', esc_attr( PARISAN_CORE_SEO_OPTION ), esc_textarea( $s['default_description'] ), esc_html__( 'برای صفحاتی که توضیح اختصاصی ندارند؛ توضیح نهایی در خروجی به ۱۶۰ نویسه محدود می‌شود.', 'parisan-core' ) );
}

/** Render organization name setting. */
function parisan_core_render_seo_name_field() {
	$s = parisan_core_seo_settings();
	printf( '<input class="regular-text" name="%1$s[organization_name]" value="%2$s">', esc_attr( PARISAN_CORE_SEO_OPTION ), esc_attr( $s['organization_name'] ) );
}

/** Render organization URL setting. */
function parisan_core_render_seo_url_field() {
	$s = parisan_core_seo_settings();
	printf( '<input type="url" class="regular-text" name="%1$s[organization_url]" value="%2$s">', esc_attr( PARISAN_CORE_SEO_OPTION ), esc_attr( $s['organization_url'] ) );
}

/** Render structured-data toggle. */
function parisan_core_render_seo_schema_field() {
	$s = parisan_core_seo_settings();
	printf( '<label><input type="checkbox" name="%1$s[output_json_ld]" value="1" %2$s> %3$s</label>', esc_attr( PARISAN_CORE_SEO_OPTION ), checked( 1, $s['output_json_ld'], false ), esc_html__( 'برای صفحه اختصاصی هر خدمت، Service JSON-LD تولید شود.', 'parisan-core' ) );
}

/** Render SEO settings page. */
function parisan_core_render_seo_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	echo '<div class="wrap"><h1>' . esc_html__( 'سئو و عملکرد پاریسان', 'parisan-core' ) . '</h1>';
	echo '<p>' . esc_html__( 'این نسخه تنظیمات پایه سئو و مرزبندی ایمن کش را فراهم می‌کند. نقشه سایت اختصاصی، کنترل‌های پیشرفته خزنده و ابزارهای تشخیص کش در مراحل بعدی تکمیل می‌شوند.', 'parisan-core' ) . '</p>';
	echo '<form method="post" action="options.php">';
	settings_fields( 'parisan_core_seo' );
	do_settings_sections( 'parisan-core-seo' );
	submit_button( __( 'ذخیره تنظیمات', 'parisan-core' ) );
	echo '</form></div>';
}

/**
 * Identify requests that must never be served from a shared public cache.
 *
 * @return bool
 */
function parisan_core_request_is_private_or_booking() {
	if ( is_user_logged_in() ) {
		return true;
	}
	$token_keys = array( 'customer_token', 'parisan_token', 'booking_token' );
	foreach ( $token_keys as $key ) {
		if ( isset( $_GET[ $key ] ) && is_scalar( $_GET[ $key ] ) && '' !== (string) wp_unslash( $_GET[ $key ] ) ) {
			return true;
		}
	}
	if ( isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) && false !== strpos( wp_unslash( $_SERVER['REQUEST_URI'] ), '/wp-json/parisan/v1/' ) ) {
		return true;
	}
	if ( isset( $_GET['rest_route'] ) && is_scalar( $_GET['rest_route'] ) && false !== strpos( (string) wp_unslash( $_GET['rest_route'] ), '/parisan/v1/' ) ) {
		return true;
	}
	if ( function_exists( 'is_singular' ) && is_singular() ) {
		$post = get_queried_object();
		if ( $post instanceof WP_Post && has_shortcode( $post->post_content, 'parisan_booking' ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Set the standard cache-plugin bypass flag before template output.
 *
 * @return void
 */
function parisan_core_mark_private_requests_uncacheable() {
	if ( parisan_core_request_is_private_or_booking() ) {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		if ( ! defined( 'DONOTCACHEDB' ) ) {
			define( 'DONOTCACHEDB', true );
		}
		if ( ! defined( 'DONOTCACHEOBJECT' ) ) {
			define( 'DONOTCACHEOBJECT', true );
		}
	}
}
add_action( 'init', 'parisan_core_mark_private_requests_uncacheable', 0 );
add_action( 'template_redirect', 'parisan_core_mark_private_requests_uncacheable', 0 );

/**
 * Send restrictive cache headers for private/booking contexts.
 *
 * @return void
 */
function parisan_core_send_private_cache_headers() {
	if ( parisan_core_request_is_private_or_booking() && ! headers_sent() ) {
		nocache_headers();
		header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', true );
		header( 'Vary: Cookie, Authorization', false );
	}
}
add_action( 'send_headers', 'parisan_core_send_private_cache_headers', 0 );

/**
 * Invalidate public cache integration points after content changes.
 *
 * Core object-cache invalidation runs first; cache plugins can subscribe to this
 * stable action without Parisan depending on a vendor-specific API.
 *
 * @param int $post_id Updated post ID.
 * @return void
 */
function parisan_core_invalidate_public_content_cache( $post_id, $post = null ) {
	$post_id = absint( $post_id );
	if ( ! $post_id ) {
		return;
	}

	$post_type = $post instanceof WP_Post ? $post->post_type : get_post_type( $post_id );
	if ( ! in_array( $post_type, array( 'parisan_service', 'parisan_staff' ), true ) ) {
		return;
	}

	clean_post_cache( $post_id );
	do_action( 'parisan_core_public_content_cache_invalidated', $post_id );
}
add_action( 'save_post_parisan_service', 'parisan_core_invalidate_public_content_cache', 20, 1 );
add_action( 'save_post_parisan_staff', 'parisan_core_invalidate_public_content_cache', 20, 1 );
add_action( 'deleted_post', 'parisan_core_invalidate_public_content_cache', 20, 2 );
