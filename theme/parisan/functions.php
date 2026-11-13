<?php
/**
 * Parisan Beauty theme bootstrap.
 *
 * @package Parisan
 */

defined( 'ABSPATH' ) || exit;

define( 'PARISAN_THEME_VERSION', '0.7.1' );
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
			'description' => esc_html__( 'برای حالت روز و شب تصویر جداگانه از کتابخانه رسانه انتخاب کنید؛ متن جایگزین و نقطه تمرکز هر تصویر مستقل است.', 'parisan' ),
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
				'label'       => esc_html__( 'تصویر اصلی حالت روز از کتابخانه رسانه', 'parisan' ),
				'description' => esc_html__( 'برای بارگذاری تصویر اختصاصی، از کتابخانه رسانه انتخاب کنید.', 'parisan' ),
				'mime_type'   => 'image',
				'section'     => 'parisan_homepage',
			)
		)
	);

	$wp_customize->add_setting(
		'parisan_hero_image_night_id',
		array(
			'default'           => 0,
			'sanitize_callback' => 'absint',
			'transport'         => 'refresh',
		)
	);
	$wp_customize->add_control(
		new WP_Customize_Media_Control(
			$wp_customize,
			'parisan_hero_image_night_id',
			array(
				'label'       => esc_html__( 'تصویر اصلی مخصوص حالت شب', 'parisan' ),
				'description' => esc_html__( 'برای حالت شب تصویر جداگانه انتخاب کنید؛ اگر خالی بماند، تصویر شب پیش‌فرض استفاده می‌شود.', 'parisan' ),
				'mime_type'   => 'image',
				'section'     => 'parisan_homepage',
			)
		)
	);
	$wp_customize->add_setting(
		'parisan_hero_image_night_alt',
		array(
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
			'transport'         => 'refresh',
		)
	);
	$wp_customize->add_control(
		'parisan_hero_image_night_alt',
		array(
			'label'       => esc_html__( 'متن جایگزین تصویر حالت شب', 'parisan' ),
			'description' => esc_html__( 'برای دسترس‌پذیری، تصویر شب را نیز به‌صورت کوتاه توصیف کنید.', 'parisan' ),
			'section'     => 'parisan_homepage',
			'type'        => 'text',
		)
	);

	$wp_customize->add_setting(
		'parisan_hero_image_alt',
		array(
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
			'transport'         => 'refresh',
		)
	);
	$wp_customize->add_control(
		'parisan_hero_image_alt',
		array(
			'label'       => esc_html__( 'متن جایگزین تصویر حالت روز', 'parisan' ),
			'description' => esc_html__( 'توضیح کوتاهی برای کاربران صفحه‌خوان بنویسید؛ اگر خالی باشد، متن جایگزین ثبت‌شده در کتابخانه رسانه استفاده می‌شود.', 'parisan' ),
			'section'     => 'parisan_homepage',
			'type'        => 'text',
		)
	);

	foreach ( array(
		'parisan_hero_position_x' => array( 50, esc_html__( 'جایگاه افقی سوژه در حالت روز', 'parisan' ) ),
		'parisan_hero_position_y' => array( 28, esc_html__( 'جایگاه عمودی سوژه در حالت روز', 'parisan' ) ),
		'parisan_hero_night_position_x' => array( 50, esc_html__( 'جایگاه افقی سوژه در حالت شب', 'parisan' ) ),
		'parisan_hero_night_position_y' => array( 28, esc_html__( 'جایگاه عمودی سوژه در حالت شب', 'parisan' ) ),
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
		'hero_title' => array( 'زیبایی، با امضای ظرافت', 'text', 'عنوان اصلی صفحه نخست' ),
		'hero_description' => array( 'تجربه‌ای متفاوت از زیبایی و آرامش در سالن زیبایی پاریسان؛ با بهترین خدمات و حرفه‌ای‌ترین تیم متخصص.', 'textarea', 'توضیح بخش اصلی' ),
		'hero_primary_label' => array( 'مشاهده خدمات', 'text', 'عنوان دکمه اصلی' ),
		'hero_primary_url' => array( '#booking', 'url', 'پیوند دکمه اصلی' ),
		'hero_secondary_label' => array( 'درباره ما', 'text', 'عنوان پیوند دوم' ),
		'hero_secondary_url' => array( home_url( '/about/' ), 'url', 'پیوند دوم' ),

		'benefit_1_title' => array( 'محیط امن و بهداشتی', 'text', 'عنوان مزیت اول — سمت چپ' ),
		'benefit_1_description' => array( 'با رعایت کامل استانداردها', 'text', 'توضیح مزیت اول' ),
		'benefit_2_title' => array( 'پشتیبانی حرفه‌ای', 'text', 'عنوان مزیت دوم' ),
		'benefit_2_description' => array( 'همیشه در کنار شما', 'text', 'توضیح مزیت دوم' ),
		'benefit_3_title' => array( 'وقت‌دهی آسان', 'text', 'عنوان مزیت سوم' ),
		'benefit_3_description' => array( 'آنلاین و سریع', 'text', 'توضیح مزیت سوم' ),
		'benefit_4_title' => array( 'خدمات لوکس', 'text', 'عنوان مزیت چهارم — سمت راست' ),
		'benefit_4_description' => array( 'با بالاترین کیفیت', 'text', 'توضیح مزیت چهارم' ),

		'intro_eyebrow' => array( 'تجربه‌ای متناسب با شما', 'text', 'متن کوچک معرفی سالن' ),
		'intro_title' => array( 'زیبایی، وقتی دلنشین‌تر است که برای شما معنا داشته باشد.', 'text', 'عنوان معرفی سالن' ),
		'intro_description' => array( 'پاریسان فضایی برای انتخاب آگاهانه و مراقبت حرفه‌ای است؛ جایی که می‌توانید خدمات را با جزئیات ببینید، متخصص مناسب را بشناسید و با خیال آسوده برای زمان دلخواهتان برنامه‌ریزی کنید.', 'textarea', 'توضیح معرفی سالن' ),
		'services_eyebrow' => array( 'خدمات منتخب', 'text', 'متن کوچک بخش خدمات' ),
		'services_title' => array( 'خدماتی برای حالِ بهتر شما', 'text', 'عنوان بخش خدمات' ),
		'portfolio_eyebrow' => array( 'نمونه‌کارهای پاریسان', 'text', 'متن کوچک نمونه‌کارها' ),
		'portfolio_title' => array( 'ظرافت را در جزئیات ببینید', 'text', 'عنوان نمونه‌کارها' ),
		'journal_eyebrow' => array( 'مجلهٔ پاریسان', 'text', 'متن کوچک مجله' ),
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
		'contact_eyebrow' => array( 'ارتباط با پاریسان', 'text', 'متن کوچک بخش تماس' ),
		'contact_title' => array( 'برای هماهنگی و راهنمایی در کنار شما هستیم.', 'text', 'عنوان بخش تماس' ),
		'contact_description' => array( 'برای انتخاب خدمت، مشاوره یا هماهنگی زمان مراجعه، با سالن در تماس باشید. اگر رزرو آنلاین فعال باشد، می‌توانید زمان دلخواهتان را مستقیم انتخاب کنید.', 'textarea', 'توضیح بخش تماس' ),
		'testimonial_1_quote' => array( '', 'textarea', 'نظر مشتری ۱ — متن' ),
		'testimonial_1_name' => array( '', 'text', 'نظر مشتری ۱ — نام' ),
		'testimonial_1_role' => array( '', 'text', 'نظر مشتری ۱ — خدمت یا توضیح کوتاه' ),
		'testimonial_2_quote' => array( '', 'textarea', 'نظر مشتری ۲ — متن' ),
		'testimonial_2_name' => array( '', 'text', 'نظر مشتری ۲ — نام' ),
		'testimonial_2_role' => array( '', 'text', 'نظر مشتری ۲ — خدمت یا توضیح کوتاه' ),
		'testimonial_3_quote' => array( '', 'textarea', 'نظر مشتری ۳ — متن' ),
		'testimonial_3_name' => array( '', 'text', 'نظر مشتری ۳ — نام' ),
		'testimonial_3_role' => array( '', 'text', 'نظر مشتری ۳ — خدمت یا توضیح کوتاه' ),
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
 * Build a safe telephone link from owner-entered, possibly Persian-digit numbers.
 *
 * @param string $phone Display phone number.
 * @return string Empty string when no usable digits exist.
 */
function parisan_phone_href( $phone ) {
	$phone = trim( (string) $phone );
	if ( '' === $phone ) {
		return '';
	}
	$latin = strtr(
		$phone,
		array(
			'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
			'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
			'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
			'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
		)
	);
	$digits = preg_replace( '/\D+/', '', $latin );
	if ( '' === $digits ) {
		return '';
	}
	return 'tel:' . ( 0 === strpos( $latin, '+' ) ? '+' : '' ) . $digits;
}

/**
 * Resolve the most useful public appointment action for the current site state.
 *
 * @return string
 */
function parisan_booking_cta_url() {
	if ( function_exists( 'parisan_core_public_booking_enabled' ) && parisan_core_public_booking_enabled() ) {
		return home_url( '/#booking' );
	}
	return home_url( '/contact/' );
}

/**
 * Resolve the matching appointment action label.
 *
 * @return string
 */
function parisan_booking_cta_label() {
	return function_exists( 'parisan_core_public_booking_enabled' ) && parisan_core_public_booking_enabled()
		? __( 'دریافت نوبت', 'parisan' )
		: __( 'هماهنگی نوبت', 'parisan' );
}

/**
 * Return a specialist's configured weekly hours as localized display rows.
 *
 * @param int $staff_id Staff post ID.
 * @return array<string,string> Day label => hours or off state.
 */
function parisan_staff_schedule_rows( $staff_id ) {
	$schedule = get_post_meta( absint( $staff_id ), '_parisan_staff_weekly_schedule', true );
	if ( ! is_array( $schedule ) || ! $schedule ) {
		return array();
	}
	$days = array(
		6 => __( 'شنبه', 'parisan' ),
		0 => __( 'یکشنبه', 'parisan' ),
		1 => __( 'دوشنبه', 'parisan' ),
		2 => __( 'سه‌شنبه', 'parisan' ),
		3 => __( 'چهارشنبه', 'parisan' ),
		4 => __( 'پنجشنبه', 'parisan' ),
		5 => __( 'جمعه', 'parisan' ),
	);
	$rows = array();
	foreach ( $days as $day => $label ) {
		$row = isset( $schedule[ $day ] ) && is_array( $schedule[ $day ] ) ? $schedule[ $day ] : null;
		if ( null === $row ) {
			continue;
		}
		if ( ! empty( $row['off'] ) ) {
			$rows[ $label ] = __( 'تعطیل', 'parisan' );
			continue;
		}
		$start = isset( $row['start'] ) ? sanitize_text_field( (string) $row['start'] ) : '';
		$end   = isset( $row['end'] ) ? sanitize_text_field( (string) $row['end'] ) : '';
		if ( '' !== $start && '' !== $end ) {
			$rows[ $label ] = sprintf( '%1$s تا %2$s', $start, $end );
		}
	}
	return $rows;
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
		array( home_url( '/' ), __( 'صفحه اصلی', 'parisan' ) ),
		array( home_url( '/#services' ), __( 'خدمات', 'parisan' ) ),
		array( home_url( '/#portfolio' ), __( 'نمونه‌کارها', 'parisan' ) ),
	);
	$blog_url = parisan_blog_index_url();
	if ( $blog_url ) {
		$items[] = array( $blog_url, __( 'وبلاگ', 'parisan' ) );
	}
	$items[] = array( home_url( '/about/' ), __( 'درباره ما', 'parisan' ) );
	$items[] = array( home_url( '/contact/' ), __( 'تماس با ما', 'parisan' ) );
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
	wp_enqueue_style(
		'parisan-final-qa',
		PARISAN_THEME_URI . '/assets/css/final-qa.css',
		array( 'parisan-main' ),
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
 * Determine whether public Parisan templates should render in Persian RTL.
 *
 * WordPress resets WPLANG to en_US when fa_IR translation packs are absent on disk,
 * even though the Parisan theme templates are authored in Persian. Keep RTL active
 * for Persian locales and default WordPress installs unless filtered otherwise.
 *
 * @return bool
 */
function parisan_is_rtl() {
	$locale  = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
	$default = is_rtl() || 0 === strpos( (string) $locale, 'fa' ) || 'en_US' === (string) $locale;
	return (bool) apply_filters( 'parisan_force_rtl', $default, $locale );
}

/**
 * Align front-end wp_locale direction with Parisan's Persian RTL templates.
 *
 * @return void
 */
function parisan_prepare_rtl_locale() {
	if ( is_admin() || ! parisan_is_rtl() ) {
		return;
	}
	if ( isset( $GLOBALS['wp_locale'] ) && is_object( $GLOBALS['wp_locale'] ) ) {
		$GLOBALS['wp_locale']->text_direction = 'rtl';
	}
}
add_action( 'wp', 'parisan_prepare_rtl_locale' );

/**
 * Ensure front-end <html> attributes include dir="rtl" and Persian lang fallback.
 *
 * @param string $output Existing language attributes string.
 * @return string
 */
function parisan_filter_language_attributes( $output ) {
	if ( is_admin() || ! parisan_is_rtl() ) {
		return $output;
	}
	if ( false === strpos( $output, 'dir=' ) ) {
		$output .= ' dir="rtl"';
	} else {
		$output = preg_replace( '/\bdir=(["\'])ltr\1/i', 'dir="rtl"', $output );
	}
	if ( apply_filters( 'parisan_force_persian_lang', true ) && preg_match( '/\blang=(["\'])en-US\1/i', $output ) ) {
		$output = preg_replace( '/\blang=(["\'])en-US\1/i', 'lang="fa-IR"', $output );
	}
	return $output;
}
add_filter( 'language_attributes', 'parisan_filter_language_attributes', 20 );

/**
 * Return the self-contained local default hero portrait URL for day or night mode.
 *
 * @param string $mode Either 'day' or 'night'.
 * @return string
 */
function parisan_hero_fallback_image_url( $mode = 'day' ) {
	$file = 'night' === $mode ? 'hero-night.jpg' : 'hero-day.jpg';
	return PARISAN_THEME_URI . '/assets/images/' . $file;
}

/**
 * Return curated local fallback image metadata for a homepage or archive card slot.
 *
 * Each section cycles across distinct bundled assets so cards never depend on
 * unreliable external URLs, empty boxes, or a repeated single placeholder image.
 *
 * @param string $group Card group: services, portfolio, staff, or journal.
 * @param int    $index Zero-based card position within the section.
 * @return array{url:string,object_position:string}
 */
function parisan_fallback_image_for_card( $group, $index = 0 ) {
	$catalog = array(
		'services'  => array(
			array( 'file' => 'service-skincare.jpg', 'object_position' => '50% 45%' ),
			array( 'file' => 'service-brows.jpg', 'object_position' => '50% 45%' ),
			array( 'file' => 'service-hair.jpg', 'object_position' => '50% 35%' ),
		),
		'portfolio' => array(
			array( 'file' => 'portfolio-glow.jpg', 'object_position' => '50% 24%' ),
			array( 'file' => 'portfolio-nails.jpg', 'object_position' => '50% 50%' ),
			array( 'file' => 'portfolio-updo.jpg', 'object_position' => '50% 26%' ),
			array( 'file' => 'portfolio-evening.jpg', 'object_position' => '50% 24%' ),
			array( 'file' => 'portfolio-care.jpg', 'object_position' => '50% 45%' ),
			array( 'file' => 'portfolio-bridal.jpg', 'object_position' => '50% 24%' ),
		),
		'staff'     => array(
			array( 'file' => 'specialist-1.jpg', 'object_position' => '50% 20%' ),
			array( 'file' => 'specialist-2.jpg', 'object_position' => '50% 20%' ),
			array( 'file' => 'specialist-3.jpg', 'object_position' => '50% 20%' ),
		),
		'journal'   => array(
			array( 'file' => 'journal-skincare.jpg', 'object_position' => '50% 50%' ),
			array( 'file' => 'journal-ritual.jpg', 'object_position' => '50% 45%' ),
			array( 'file' => 'journal-color.jpg', 'object_position' => '50% 45%' ),
		),
	);

	$items = isset( $catalog[ $group ] ) ? $catalog[ $group ] : $catalog['journal'];
	$item  = $items[ absint( $index ) % count( $items ) ];
	return array(
		'url'             => PARISAN_THEME_URI . '/assets/images/' . $item['file'],
		'object_position' => $item['object_position'],
	);
}

/**
 * Render an intentional card image using the post thumbnail or curated local fallback.
 *
 * @param WP_Post|int $post  Post object or ID.
 * @param string      $group Card group: services, portfolio, staff, or journal.
 * @param int         $index Zero-based card index for distinct fallback selection.
 * @param string      $size  WordPress image size when a featured image exists.
 * @param array       $args  Optional attributes (`decorative`, `alt`, `loading`, `class`).
 * @return string
 */
function parisan_card_image_html( $post, $group, $index = 0, $size = 'large', $args = array() ) {
	$post_id    = is_object( $post ) ? absint( $post->ID ) : absint( $post );
	$decorative = ! isset( $args['decorative'] ) || (bool) $args['decorative'];
	$loading    = isset( $args['loading'] ) && in_array( $args['loading'], array( 'lazy', 'eager' ), true ) ? $args['loading'] : 'lazy';
	$class      = isset( $args['class'] ) && is_string( $args['class'] ) ? trim( $args['class'] ) : '';

	if ( $post_id && has_post_thumbnail( $post_id ) ) {
		$alt = '';
		if ( ! $decorative ) {
			$alt = isset( $args['alt'] ) && is_string( $args['alt'] ) && '' !== $args['alt']
				? $args['alt']
				: (string) get_post_meta( get_post_thumbnail_id( $post_id ), '_wp_attachment_image_alt', true );
			if ( '' === $alt ) {
				$alt = get_the_title( $post_id );
			}
		}
		$attr = array(
			'loading'  => $loading,
			'decoding' => 'async',
			'alt'      => $alt,
		);
		if ( '' !== $class ) {
			$attr['class'] = $class;
		}
		return get_the_post_thumbnail( $post_id, $size, $attr );
	}

	$fallback = parisan_fallback_image_for_card( $group, $index );
	$alt      = '';
	if ( ! $decorative ) {
		$alt = isset( $args['alt'] ) && is_string( $args['alt'] ) && '' !== $args['alt']
			? $args['alt']
			: ( $post_id ? get_the_title( $post_id ) : '' );
	}
	return sprintf(
		'<img%1$s src="%2$s" alt="%3$s" loading="%4$s" decoding="async" style="object-position:%5$s;">',
		'' !== $class ? ' class="' . esc_attr( $class ) . '"' : '',
		esc_url( $fallback['url'] ),
		esc_attr( $alt ),
		esc_attr( $loading ),
		esc_attr( $fallback['object_position'] )
	);
}

/**
 * Add a body class for the theme's RTL-aware design system.
 *
 * @param array $classes Existing body classes.
 * @return array
 */
function parisan_body_classes( $classes ) {
	$rtl       = parisan_is_rtl();
	$classes[] = $rtl ? 'parisan-is-rtl' : 'parisan-is-ltr';
	if ( is_front_page() ) {
		$classes[] = 'parisan-front-page';
	}
	if ( $rtl && ! in_array( 'rtl', $classes, true ) ) {
		$classes[] = 'rtl';
	}
	return $classes;
}
add_filter( 'body_class', 'parisan_body_classes' );
