<?php
/**
 * Parisan Beauty theme bootstrap.
 *
 * @package Parisan
 */

defined( 'ABSPATH' ) || exit;

define( 'PARISAN_THEME_VERSION', '0.7.0' );
define( 'PARISAN_THEME_DIR', get_template_directory() );
define( 'PARISAN_THEME_URI', get_template_directory_uri() );

/**
 * Configure theme supports and navigation.
 *
 * @return void
 */
function parisan_theme_setup() {
	load_theme_textdomain( 'parisan', PARISAN_THEME_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support(
		'html5',
		array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' )
	);
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 96,
			'width'       => 280,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);

	register_nav_menus(
		array(
			'primary' => esc_html__( 'منوی اصلی', 'parisan' ),
			'footer'  => esc_html__( 'منوی پابرگ', 'parisan' ),
		)
	);
}
add_action( 'after_setup_theme', 'parisan_theme_setup' );

/**
 * Register homepage art customization.
 *
 * @param WP_Customize_Manager $wp_customize Customizer manager.
 * @return void
 */
function parisan_customize_register( $wp_customize ) {
	$wp_customize->add_section(
		'parisan_homepage',
		array(
			'title'       => esc_html__( 'پاریسان — صفحه اصلی', 'parisan' ),
			'description' => esc_html__( 'تصویر اصلی را از کتابخانه رسانه انتخاب کنید و جایگاه سوژه را برای برش قاب تنظیم کنید. در صورت خالی‌بودن، طرح هنری پیش‌فرض نمایش داده می‌شود.', 'parisan' ),
			'priority'    => 35,
		)
	);

	$wp_customize->add_setting(
		'parisan_hero_image',
		array(
			'default'           => '',
			'sanitize_callback' => 'esc_url_raw',
			'transport'         => 'refresh',
		)
	);


	// Prefer attachment IDs so WordPress can provide responsive srcsets and media-library alt text.
	$wp_customize->add_setting(
		'parisan_hero_image_id',
		array(
			'default'           => 0,
			'sanitize_callback' => 'absint',
			'transport'         => 'refresh',
		)
	);
	$wp_customize->add_control(
		new WP_Customize_Media_Control(
			$wp_customize,
			'parisan_hero_image_id',
			array(
				'label'       => esc_html__( 'تصویر اصلی از کتابخانه رسانه', 'parisan' ),
				'description' => esc_html__( 'برای بارگذاری تصویر اختصاصی، از کتابخانه رسانه انتخاب کنید.', 'parisan' ),
				'mime_type'   => 'image',
				'section'     => 'parisan_homepage',
			)
		)
	);

	foreach ( array(
		'parisan_hero_position_x' => array( 50, esc_html__( 'جایگاه افقی سوژه', 'parisan' ) ),
		'parisan_hero_position_y' => array( 34, esc_html__( 'جایگاه عمودی سوژه', 'parisan' ) ),
	) as $setting_id => $definition ) {
		$wp_customize->add_setting(
			$setting_id,
			array(
				'default'           => $definition[0],
				'sanitize_callback' => 'parisan_sanitize_hero_position',
				'transport'         => 'refresh',
			)
		);
		$wp_customize->add_control(
			$setting_id,
			array(
				'label'       => $definition[1],
				'description' => esc_html__( 'با تغییر این مقدار، نقطه تمرکز تصویر در قاب جابه‌جا می‌شود (۰ تا ۱۰۰).', 'parisan' ),
				'section'     => 'parisan_homepage',
				'type'        => 'range',
				'input_attrs' => array( 'min' => 0, 'max' => 100, 'step' => 1 ),
			)
		);
	}
}
add_action( 'customize_register', 'parisan_customize_register' );

/**
 * Clamp the hero image focal point to a percentage.
 *
 * @param mixed $value Raw Customizer value.
 * @return int
 */
function parisan_sanitize_hero_position( $value ) {
	$value = is_numeric( $value ) ? (int) $value : 0;
	return max( 0, min( 100, $value ) );
}

/**
 * Register brand, contact, editorial, and global layout controls.
 *
 * @param WP_Customize_Manager $wp_customize Customizer manager.
 * @return void
 */
function parisan_customize_brand_register( $wp_customize ) {
	$wp_customize->add_section( 'parisan_brand', array(
		'title'       => esc_html__( 'پاریسان — هویت بصری و برند', 'parisan' ),
		'description' => esc_html__( 'رنگ‌ها، تایپوگرافی و اطلاعات عمومی سالن را بدون ویرایش کد تنظیم کنید.', 'parisan' ),
		'priority'    => 36,
	) );
	$settings = array(
		'parisan_brand_accent' => array( '#6f4657', 'color', __( 'رنگ اصلی برند', 'parisan' ) ),
		'parisan_brand_accent_dark' => array( '#573443', 'color', __( 'رنگ تیره برند', 'parisan' ) ),
		'parisan_brand_page' => array( '#fbf8f3', 'color', __( 'رنگ پس‌زمینه صفحات', 'parisan' ) ),
		'parisan_brand_surface' => array( '#fffdf9', 'color', __( 'رنگ سطح کارت‌ها', 'parisan' ) ),
		'parisan_brand_gold' => array( '#b69a70', 'color', __( 'رنگ تأکیدی و جزئیات', 'parisan' ) ),
		'parisan_brand_dark_accent' => array( '#e0b7ca', 'color', __( 'رنگ اصلی در حالت تاریک', 'parisan' ) ),
		'parisan_brand_dark_accent_dark' => array( '#f0d6e2', 'color', __( 'رنگ تیترها در حالت تاریک', 'parisan' ) ),
		'parisan_brand_font' => array( 'vazirmatn', 'select', __( 'فونت رابط فارسی', 'parisan' ) ),
		'parisan_brand_font_scale' => array( '16px', 'select', __( 'اندازه پایه متن', 'parisan' ) ),
		'parisan_announcement_text' => array( '', 'text', __( 'متن نوار اطلاع‌رسانی', 'parisan' ) ),
		'parisan_announcement_url' => array( '', 'url', __( 'پیوند نوار اطلاع‌رسانی', 'parisan' ) ),
		'parisan_contact_phone' => array( '', 'text', __( 'تلفن سالن', 'parisan' ) ),
		'parisan_contact_email' => array( '', 'email', __( 'ایمیل عمومی', 'parisan' ) ),
		'parisan_contact_address' => array( '', 'textarea', __( 'نشانی سالن', 'parisan' ) ),
		'parisan_contact_hours' => array( '', 'text', __( 'ساعات پاسخ‌گویی', 'parisan' ) ),
		'parisan_social_instagram' => array( '', 'url', __( 'نشانی اینستاگرام', 'parisan' ) ),
		'parisan_social_whatsapp' => array( '', 'url', __( 'پیوند واتساپ', 'parisan' ) ),
		'parisan_footer_description' => array( 'زیبایی، با آرامش و ظرافت.', 'textarea', __( 'توضیح کوتاه پابرگ', 'parisan' ) ),
	);
	foreach ( $settings as $id => $definition ) {
		list( $default, $type, $label ) = $definition;
		$sanitize = 'color' === $type ? 'sanitize_hex_color' : ( 'url' === $type ? 'esc_url_raw' : ( 'email' === $type ? 'sanitize_email' : ( 'textarea' === $type ? 'sanitize_textarea_field' : 'sanitize_text_field' ) ) );
		$wp_customize->add_setting( $id, array( 'default' => $default, 'sanitize_callback' => $sanitize, 'transport' => 'refresh' ) );
		$args = array( 'label' => $label, 'section' => 'parisan_brand' );
		if ( 'parisan_brand_font' === $id ) {
			$args['choices'] = array( 'vazirmatn' => 'Vazirmatn', 'system' => 'System UI' );
		}
		if ( 'parisan_brand_font_scale' === $id ) {
			$args['choices'] = array( '15px' => 'کوچک', '16px' => 'استاندارد', '17px' => 'درشت' );
		}
		if ( 'color' === $type ) {
			$wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, $id, $args ) );
		} else {
			$args['type'] = 'textarea' === $type ? 'textarea' : ( 'select' === $type ? 'select' : $type );
			$wp_customize->add_control( $id, $args );
		}
	}
}
add_action( 'customize_register', 'parisan_customize_brand_register', 20 );

/**
 * Register editable homepage messaging and conversion controls.
 *
 * @param WP_Customize_Manager $wp_customize Customizer manager.
 * @return void
 */
function parisan_customize_homepage_content_register( $wp_customize ) {
	$wp_customize->add_section(
		'parisan_homepage_content',
		array(
			'title'       => esc_html__( 'پاریسان — محتوای صفحه اصلی', 'parisan' ),
			'description' => esc_html__( 'متن‌ها و پیوندهای اصلی صفحه نخست را بدون ویرایش کد تغییر دهید.', 'parisan' ),
			'priority'    => 37,
		)
	);

	$fields = array(
		'hero_eyebrow' => array(  'استودیوی زیبایی پاریسان' , 'text', 'متن کوچک بالای عنوان اصلی' ),
		'hero_title' => array( 'زیباییِ شما، با امضای ظرافت', 'text', 'عنوان اصلی صفحه نخست' ),
		'hero_description' => array( 'از لحظه‌ای که وارد می‌شوید تا آخرین جزئیات خدمات، همه‌چیز برای یک تجربه دلنشین، دقیق و شایسته شما طراحی شده است.', 'textarea', 'توضیح بخش اصلی' ),
		'hero_primary_label' => array( 'انتخاب زمان و رزرو', 'text', 'عنوان دکمه اصلی' ),
		'hero_primary_url' => array( '#booking', 'url', 'پیوند دکمه اصلی' ),
		'hero_secondary_label' => array( 'آشنایی با خدمات', 'text', 'عنوان پیوند دوم' ),
		'hero_secondary_url' => array( '#services', 'url', 'پیوند دوم' ),
		'intro_eyebrow' => array( 'تجربه‌ای متناسب با شما', 'text', 'متن کوچک معرفی سالن' ),
		'intro_title' => array( 'زیبایی، وقتی دلنشین‌تر است که برای شما معنا داشته باشد.', 'text', 'عنوان معرفی سالن' ),
		'intro_description' => array( 'پاریسان فضایی برای انتخاب آگاهانه و مراقبت حرفه‌ای است؛ جایی که می‌توانید خدمات را با جزئیات ببینید، متخصص مناسب را بشناسید و با خیال آسوده برای زمان دلخواهتان برنامه‌ریزی کنید.', 'textarea', 'توضیح معرفی سالن' ),
		'services_eyebrow' => array( 'خدمات تخصصی', 'text', 'متن کوچک بخش خدمات' ),
		'services_title' => array( 'خدماتی برای حالِ بهتر شما', 'text', 'عنوان بخش خدمات' ),
		'portfolio_eyebrow' => array( 'نمونه‌کارهای پاریسان', 'text', 'متن کوچک نمونه‌کارها' ),
		'portfolio_title' => array( 'ظرافت را در جزئیات ببینید', 'text', 'عنوان نمونه‌کارها' ),
		'journal_eyebrow' => array( 'مجلهٔ زیبایی', 'text', 'متن کوچک مجله' ),
		'journal_title' => array( 'تازه‌هایی برای زیبایی و آرامش', 'text', 'عنوان مجله' ),
		'editorial_eyebrow' => array( 'زمانی برای خودتان', 'text', 'متن کوچک نوار تبلیغاتی' ),
		'editorial_title' => array( 'قرار نیست فقط زیباتر شوید؛ قرار است از این لحظه لذت ببرید.', 'textarea', 'متن نوار تبلیغاتی' ),
		'editorial_button_label' => array( 'شروع تجربه من', 'text', 'عنوان دکمه نوار تبلیغاتی' ),
		'editorial_button_url' => array( '#booking', 'url', 'پیوند دکمه نوار تبلیغاتی' ),
		'team_eyebrow' => array( 'تیم متخصص پاریسان', 'text', 'متن کوچک متخصصان' ),
		'team_title' => array( 'هنر، در دستان متخصصان ما', 'text', 'عنوان متخصصان' ),
		'booking_eyebrow' => array( 'رزرو و هماهنگی نوبت', 'text', 'متن کوچک بخش رزرو' ),
		'booking_title' => array( 'زمانی برای خودتان کنار بگذارید.', 'text', 'عنوان بخش رزرو' ),
		'booking_description' => array( 'خدمت و متخصص دلخواهتان را انتخاب کنید و زمان‌های در دسترس را ببینید.', 'textarea', 'توضیح بخش رزرو' ),
	);

	foreach ( $fields as $key => $definition ) {
		list( $default, $type, $label ) = $definition;
		$id = 'parisan_home_' . $key;
		$sanitize = 'url' === $type ? 'esc_url_raw' : ( 'textarea' === $type ? 'sanitize_textarea_field' : 'sanitize_text_field' );
		$wp_customize->add_setting(
			$id,
			array(
				'default'           => $default,
				'sanitize_callback' => $sanitize,
				'transport'         => 'refresh',
			)
		);
		$wp_customize->add_control(
			$id,
			array(
				'label'   => esc_html( $label ),
				'section' => 'parisan_homepage_content',
				'type'    => 'url' === $type ? 'url' : ( 'textarea' === $type ? 'textarea' : 'text' ),
			)
		);
	}
}
add_action( 'customize_register', 'parisan_customize_homepage_content_register', 25 );


/**
 * Output sanitized theme design tokens.
 *
 * @return void
 */
/**
 * Return a validated hex color from theme settings.
 *
 * @param string $key Theme modification key.
 * @param string $default Safe fallback color.
 * @return string
 */
function parisan_safe_theme_color( $key, $default ) {
	$value = get_theme_mod( $key, $default );
	if ( ! is_string( $value ) ) {
		return $default;
	}
	$color = sanitize_hex_color( $value );
	return $color ? $color : $default;
}

/**
 * Output sanitized theme design tokens.
 *
 * @return void
 */
function parisan_output_brand_tokens() {
	$font_sizes = array( '15px', '16px', '17px' );
	$font_size  = get_theme_mod( 'parisan_brand_font_scale', '16px' );
	$font_size  = is_string( $font_size ) && in_array( $font_size, $font_sizes, true ) ? $font_size : '16px';
	$fonts      = array(
		'vazirmatn' => 'Vazirmatn, sans-serif',
		'system'    => 'Vazirmatn, system-ui, sans-serif',
	);
	$font = get_theme_mod( 'parisan_brand_font', 'vazirmatn' );
	$font = is_string( $font ) && isset( $fonts[ $font ] ) ? $font : 'vazirmatn';
	$tokens = array(
		'--parisan-plum'           => parisan_safe_theme_color( 'parisan_brand_accent', '#6f4657' ),
		'--parisan-plum-dark'      => parisan_safe_theme_color( 'parisan_brand_accent_dark', '#573443' ),
		'--parisan-paper'          => parisan_safe_theme_color( 'parisan_brand_page', '#fbf8f3' ),
		'--parisan-surface'        => parisan_safe_theme_color( 'parisan_brand_surface', '#fffdf9' ),
		'--parisan-gold'           => parisan_safe_theme_color( 'parisan_brand_gold', '#b69a70' ),
		'--parisan-dark-plum'      => parisan_safe_theme_color( 'parisan_brand_dark_accent', '#e0b7ca' ),
		'--parisan-dark-plum-dark' => parisan_safe_theme_color( 'parisan_brand_dark_accent_dark', '#f0d6e2' ),
		'--parisan-body-size'      => $font_size,
		'--parisan-font'           => $fonts[ $font ],
	);
	echo '<style id="parisan-brand-tokens">:root{';
	foreach ( $tokens as $name => $value ) {
		printf( '%1$s:%2$s;', esc_html( $name ), esc_html( $value ) );
	}
	echo '}</style>';
}
add_action( 'wp_head', 'parisan_output_brand_tokens', 20 );

/**
 * Read a public string theme setting.
 *
 * @param string $key Theme modification key.
 * @return string
 */
function parisan_public_setting( $key ) {
	$value = get_theme_mod( $key, '' );
	return is_string( $value ) ? trim( $value ) : '';
}

/**
 * Read an editable homepage content setting with a safe default.
 *
 * @param string $key Homepage content key.
 * @param string $default Default text or URL.
 * @return string
 */
function parisan_home_content( $key, $default = '' ) {
	$value = get_theme_mod( 'parisan_home_' . sanitize_key( $key ), $default );
	$value = is_string( $value ) ? trim( $value ) : '';
	return '' !== $value ? $value : $default;
}

/**
 * Resolve the actual WordPress posts index without assuming a /blog/ route.
 *
 * @return string
 */
function parisan_blog_index_url() {
	$posts_page = absint( get_option( 'page_for_posts' ) );
	if ( $posts_page ) {
		$url = get_permalink( $posts_page );
		if ( is_string( $url ) && '' !== $url ) {
			return $url;
		}
	}

	// The site front page is the posts index only in the default "latest posts" mode.
	return 'posts' === get_option( 'show_on_front', 'posts' ) ? home_url( '/' ) : '';
}




/**
 * Accessible primary navigation fallback when no menu is assigned.
 *
 * @return void
 */
function parisan_primary_menu_fallback() {
	echo '<ul class="menu-list">';
	$items = array(
		array( home_url( '/' ), __( 'خانه', 'parisan' ) ),
		array( home_url( '/#services' ), __( 'خدمات', 'parisan' ) ),
		array( home_url( '/#team' ), __( 'متخصصان', 'parisan' ) ),
		array( home_url( '/#portfolio' ), __( 'نمونه‌کارها', 'parisan' ) ),
	);
	$blog_url = parisan_blog_index_url();
	if ( $blog_url ) {
		$items[] = array( $blog_url, __( 'مجله', 'parisan' ) );
	}
	$items = array_merge(
		$items,
		array(
			array( home_url( '/about/' ), __( 'درباره ما', 'parisan' ) ),
			array( home_url( '/contact/' ), __( 'تماس با ما', 'parisan' ) ),
			array( home_url( '/faq/' ), __( 'سؤالات متداول', 'parisan' ) ),
			array( home_url( '/#booking' ), __( 'رزرو نوبت', 'parisan' ) ),
		)
	);
	foreach ( $items as $item ) {
		printf( '<li><a href="%1$s">%2$s</a></li>', esc_url( $item[0] ), esc_html( $item[1] ) );
	}
	echo '</ul>';
}



/**
 * Accessible footer utility navigation fallback.
 *
 * @return void
 */
function parisan_footer_menu_fallback() {
	$items = array(
		array( home_url( '/about/' ), __( 'درباره ما', 'parisan' ) ),
		array( home_url( '/contact/' ), __( 'تماس با ما', 'parisan' ) ),
		array( home_url( '/faq/' ), __( 'سؤالات متداول', 'parisan' ) ),
		array( home_url( '/privacy-policy/' ), __( 'حریم خصوصی', 'parisan' ) ),
		array( home_url( '/terms/' ), __( 'شرایط رزرو', 'parisan' ) ),
	);
	echo '<ul class="footer-menu">';
	foreach ( $items as $item ) {
		printf( '<li><a href="%1$s">%2$s</a></li>', esc_url( $item[0] ), esc_html( $item[1] ) );
	}
	echo '</ul>';
}

/**
 * Load only the theme's small, local stylesheet.
 *
 * @return void
 */
function parisan_enqueue_assets() {
	wp_enqueue_style(
		'parisan-main',
		PARISAN_THEME_URI . '/assets/css/main.css',
		array(),
		PARISAN_THEME_VERSION
	);
	wp_enqueue_script(
		'parisan-theme',
		PARISAN_THEME_URI . '/assets/js/theme.js',
		array(),
		PARISAN_THEME_VERSION,
		array( 'in_footer' => true, 'strategy' => 'defer' )
	);
}
add_action( 'wp_enqueue_scripts', 'parisan_enqueue_assets' );

/**
 * Add a body class for the theme's RTL-aware design system.
 *
 * @param array $classes Existing body classes.
 * @return array
 */
function parisan_body_classes( $classes ) {
	$classes[] = is_rtl() ? 'parisan-is-rtl' : 'parisan-is-ltr';
	return $classes;
}
add_filter( 'body_class', 'parisan_body_classes' );
