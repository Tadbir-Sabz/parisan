<?php
/**
 * Seed clearly synthetic demo content for local visual review.
 *
 * Run only against a disposable WordPress installation:
 * wp eval-file tests/seed-demo-content.php --path=/path/to/wordpress
 *
 * All names, contact details, prices, and copy are fictional demo content.
 * Images are bundled AI-generated demo assets and are not real customers.
 *
 * @package Parisan
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	fwrite( STDERR, "This seed script must run through WP-CLI in a disposable environment.\n" );
	exit( 1 );
}

require_once ABSPATH . 'wp-admin/includes/image.php';

/**
 * Import a bundled demo image once and return its attachment ID.
 *
 * @param string $file File name under theme assets/images.
 * @param string $title Attachment title.
 * @param string $alt Alternative text.
 * @return int
 */
function parisan_demo_image( $file, $title, $alt ) {
	$marker = 'parisan_demo_image_' . md5( $file );
	$found  = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_parisan_demo_asset',
			'meta_value'     => $marker,
		)
	);
	if ( $found ) {
		return (int) $found[0];
	}

	$source = get_template_directory() . '/assets/images/' . $file;
	if ( ! is_readable( $source ) ) {
		WP_CLI::warning( 'Missing bundled demo image: ' . $file );
		return 0;
	}

	$upload = wp_upload_bits( $file, null, file_get_contents( $source ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	if ( ! empty( $upload['error'] ) ) {
		WP_CLI::warning( 'Could not import demo image: ' . $file );
		return 0;
	}

	$attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/jpeg',
			'post_title'     => $title,
			'post_content'   => '',
			'post_status'    => 'inherit',
		),
		$upload['file']
	);
	if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
		return 0;
	}

	wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );
	update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
	update_post_meta( $attachment_id, '_parisan_demo_asset', $marker );
	return (int) $attachment_id;
}

/**
 * Create or update a demo page by slug.
 *
 * @param string $slug Slug.
 * @param string $title Title.
 * @param string $content Content.
 * @return int
 */
function parisan_demo_page( $slug, $title, $content = '' ) {
	$existing = get_page_by_path( $slug, OBJECT, 'page' );
	$data     = array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => $title,
		'post_name'    => $slug,
		'post_content' => $content,
		'meta_input'   => array( '_parisan_demo_content' => '1' ),
	);
	if ( $existing ) {
		$data['ID'] = $existing->ID;
	}
	$id = wp_insert_post( $data, true );
	if ( is_wp_error( $id ) ) {
		WP_CLI::warning( 'Page failed: ' . $slug . ' — ' . $id->get_error_message() );
		return 0;
	}
	return (int) $id;
}

/**
 * Create or update a demo post.
 *
 * @param string $type Post type.
 * @param string $slug Slug.
 * @param array  $args Post fields.
 * @return int
 */
function parisan_demo_post( $type, $slug, $args ) {
	$existing = get_page_by_path( $slug, OBJECT, $type );
	$data     = array_merge(
		array(
			'post_type'    => $type,
			'post_status'  => 'publish',
			'post_name'    => $slug,
			'meta_input'   => array( '_parisan_demo_content' => '1' ),
		),
		$args
	);
	if ( $existing ) {
		$data['ID'] = $existing->ID;
	}
	$id = wp_insert_post( $data, true );
	if ( is_wp_error( $id ) ) {
		WP_CLI::warning( 'Post failed: ' . $slug . ' — ' . $id->get_error_message() );
		return 0;
	}
	return (int) $id;
}

$hero_day   = parisan_demo_image( 'hero-day.jpg', 'تصویر نمایشی پرتره روز', 'پرتره نمایشی با آرایش طبیعی و گل‌های صورتی' );
$hero_night = parisan_demo_image( 'hero-night.jpg', 'تصویر نمایشی پرتره شب', 'پرتره نمایشی با آرایش هنری در نورپردازی شب' );

$pages = array(
	'home'           => array( 'خانه', '' ),
	'journal'        => array( 'مجله پاریسان', '' ),
	'about'          => array( 'درباره پاریسان', '<p>پاریسان یک استودیوی زیبایی نمایشی برای ارائه این محصول است. تمام نام‌ها، تصاویر و اطلاعات این نسخه، نمونه و غیرواقعی هستند.</p><h2>نگاه ما به زیبایی</h2><p>زیبایی شخصی است. تجربه پاریسان بر گفت‌وگوی دقیق، انتخاب آگاهانه و مراقبت آرام بنا شده است؛ بدون عجله و بدون قالب‌های تکراری.</p><h2>فضایی برای آرامش</h2><p>از اولین تماس تا پایان خدمت، هر جزئیات برای این طراحی شده که احساس راحتی و اعتماد داشته باشید.</p>' ),
	'contact'        => array( 'تماس با ما', '<p>برای پرسش درباره خدمات، هماهنگی مراجعه یا راهنمایی پیش از رزرو، از راه‌های ارتباطی این صفحه استفاده کنید. اطلاعات تماس این نسخه نمایشی است.</p>' ),
	'faq'            => array( 'پرسش‌های متداول', '<p>پاسخ پرسش‌های رایج درباره خدمات، رزرو و مراجعه به استودیوی پاریسان.</p>' ),
	'privacy-policy' => array( 'حریم خصوصی', '<p>این صفحه نمونه برای نمایش ساختار سیاست حریم خصوصی است. پیش از انتشار واقعی، متن حقوقی و سیاست نگهداری اطلاعات باید با مشاوره حقوقی نهایی شود.</p><h2>اطلاعاتی که دریافت می‌کنیم</h2><p>در فرم تماس، نام، شماره تماس، ایمیل اختیاری و متن پیام دریافت می‌شود. اطلاعات کارت بانکی در این وب‌سایت ذخیره نمی‌شود.</p><h2>نگهداری و حذف</h2><p>درخواست‌های حذف اطلاعات از طریق صفحه تماس پذیرفته می‌شود. سیاست نگهداری نهایی باید توسط صاحب سالن تعیین شود.</p>' ),
	'terms'          => array( 'شرایط رزرو', '<p>این متن نمونه شرایط رزرو است و باید پیش از استفاده واقعی توسط صاحب سالن و مشاور حقوقی بازبینی شود.</p><h2>زمان مراجعه</h2><p>لطفاً چند دقیقه پیش از زمان نوبت حاضر شوید. تأخیر ممکن است به کوتاه شدن زمان خدمت منجر شود.</p><h2>لغو و جابه‌جایی</h2><p>شرایط دقیق لغو و جابه‌جایی باید در تنظیمات عملیاتی سالن تعیین و در این صفحه منتشر شود.</p>' ),
);
$page_ids = array();
foreach ( $pages as $slug => $definition ) {
	$page_ids[ $slug ] = parisan_demo_page( $slug, $definition[0], $definition[1] );
}

update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $page_ids['home'] );
update_option( 'page_for_posts', $page_ids['journal'] );
update_option( 'blogname', 'استودیوی زیبایی پاریسان' );
update_option( 'blogdescription', 'زیبایی با امضای ظرافت' );
update_option( 'timezone_string', 'Asia/Tehran' );
update_option( 'start_of_week', 6 );
update_option( 'permalink_structure', '/%postname%/' );
update_option( 'default_comment_status', 'closed' );

$hero_mods = array(
	'parisan_hero_image_id'          => $hero_day,
	'parisan_hero_image_night_id'    => $hero_night,
	'parisan_hero_image_alt'         => 'پرتره نمایشی با آرایش طبیعی و گل‌های صورتی',
	'parisan_hero_image_night_alt'   => 'پرتره نمایشی با آرایش هنری در نورپردازی شب',
	'parisan_hero_position_x'        => 50,
	'parisan_hero_position_y'        => 24,
	'parisan_hero_night_position_x'  => 50,
	'parisan_hero_night_position_y'  => 24,
	'parisan_contact_phone'          => '۰۲۱-۱۲۳۴۵۶۷۸',
	'parisan_contact_email'          => 'hello@example.test',
	'parisan_contact_address'        => 'تهران، خیابان نمونه، پلاک ۱۲ — نشانی نمایشی',
	'parisan_contact_hours'          => 'شنبه تا پنجشنبه، ۱۰ تا ۲۰',
	'parisan_social_instagram'       => 'https://example.com/parisan-demo-instagram',
	'parisan_social_whatsapp'        => 'https://example.com/parisan-demo-whatsapp',
	'parisan_footer_description'     => 'استودیوی زیبایی پاریسان؛ تجربه‌ای آرام و دقیق برای زیبایی شخصی شما.',
	'parisan_announcement_text'      => 'رزرو آنلاین این نسخه نمایشی غیرفعال است؛ برای هماهنگی با سالن تماس بگیرید.',
);
foreach ( $hero_mods as $key => $value ) {
	set_theme_mod( $key, $value );
}

$service_terms = array(
	'skin'   => 'مراقبت پوست',
	'hair'   => 'مو',
	'brows'  => 'ابرو',
	'nails'  => 'ناخن',
	'bridal' => 'عروس',
);
$service_term_ids = array();
foreach ( $service_terms as $slug => $name ) {
	$term = term_exists( $slug, 'parisan_service_category' );
	if ( ! $term ) {
		$term = wp_insert_term( $name, 'parisan_service_category', array( 'slug' => $slug ) );
	}
	if ( ! is_wp_error( $term ) && isset( $term['term_id'] ) ) {
		$service_term_ids[ $slug ] = (int) $term['term_id'];
	}
}

$services = array(
	array(
		'slug'     => 'skin-hydration',
		'title'    => 'مراقبت و آبرسانی پوست',
		'excerpt'  => 'پاک‌سازی ملایم، آبرسانی عمیق و پیشنهاد روتین خانگی متناسب با پوست شما.',
		'content'  => '<p>جلسه با بررسی وضعیت پوست و گفت‌وگو درباره روتین فعلی شما آغاز می‌شود. سپس پاک‌سازی ملایم، آبرسانی و ماسک تخصصی انجام می‌شود.</p><h2>پیش از مراجعه</h2><p>در صورت استفاده از درمان فعال پوستی، پیش از رزرو با سالن هماهنگ کنید.</p><h2>بعد از خدمت</h2><p>استفاده از ضدآفتاب و پرهیز از محصولات لایه‌بردار قوی برای ۲۴ ساعت توصیه می‌شود.</p>',
		'duration' => 60,
		'price'    => 850000,
		'type'     => 'fixed',
		'image'    => 'service-skincare.jpg',
		'term'     => 'skin',
	),
	array(
		'slug'     => 'brow-design',
		'title'    => 'اصلاح و طراحی ابرو',
		'excerpt'  => 'طراحی متناسب با فرم چهره، اصلاح دقیق و رنگ‌بندی ملایم.',
		'content'  => '<p>فرم چهره، تراکم ابرو و سلیقه شما بررسی می‌شود و طراحی نهایی پیش از اجرا هماهنگ می‌گردد.</p><h2>نکته</h2><p>برای بهترین نتیجه، پیش از مراجعه از لایه‌برداری پوست اطراف ابرو خودداری کنید.</p>',
		'duration' => 45,
		'price'    => 480000,
		'type'     => 'fixed',
		'image'    => 'service-brows.jpg',
		'term'     => 'brows',
	),
	array(
		'slug'     => 'hair-color',
		'title'    => 'رنگ و کراتین مو',
		'excerpt'  => 'مشاوره رنگ، اجرای تخصصی و مراقبت پس از رنگ برای درخشش ماندگار.',
		'content'  => '<p>رنگ مو با مشاوره تخصصی و بررسی سابقه رنگ شما آغاز می‌شود. زمان جلسه به طول و تراکم مو بستگی دارد.</p><h2>پیش از مراجعه</h2><p>لطفاً سابقه رنگ، دکلره یا کراتین شش ماه گذشته را با متخصص در میان بگذارید.</p>',
		'duration' => 120,
		'price'    => 2450000,
		'type'     => 'from',
		'image'    => 'service-hair.jpg',
		'term'     => 'hair',
	),
	array(
		'slug'     => 'bridal-makeup',
		'title'    => 'میکاپ و شینیون عروس',
		'excerpt'  => 'مشاوره پیش از مراسم، میکاپ ماندگار و شینیون متناسب با سبک شما.',
		'content'  => '<p>این تجربه شامل جلسه مشاوره، آماده‌سازی پوست، میکاپ و شینیون است. جزئیات نهایی در گفت‌وگوی پیش از مراسم هماهنگ می‌شود.</p><h2>رزرو</h2><p>برای مراسم عروسی، هماهنگی زودتر توصیه می‌شود. این خدمت در نسخه نمایشی قیمت ثابت نمونه دارد.</p>',
		'duration' => 180,
		'price'    => 6500000,
		'type'     => 'fixed',
		'image'    => 'portfolio-bridal.jpg',
		'term'     => 'bridal',
	),
	array(
		'slug'     => 'manicure-pedicure',
		'title'    => 'مانیکور و پدیکور',
		'excerpt'  => 'مراقبت دقیق دست و پا با محصولات ملایم و رنگ‌بندی دلخواه.',
		'content'  => '<p>خدمت با آماده‌سازی و مراقبت پوست آغاز می‌شود و با انتخاب رنگ و نهایی‌سازی دلخواه شما پایان می‌یابد.</p><h2>ماندگاری</h2><p>برای حفظ نتیجه، استفاده از دستکش هنگام شست‌وشو و مرطوب‌کننده روزانه پیشنهاد می‌شود.</p>',
		'duration' => 75,
		'price'    => 980000,
		'type'     => 'fixed',
		'image'    => 'portfolio-nails.jpg',
		'term'     => 'nails',
	),
	array(
		'slug'     => 'facial-cleanse',
		'title'    => 'پاکسازی و فیشیال تخصصی',
		'excerpt'  => 'پاکسازی منافذ، لایه‌برداری ملایم و آرام‌سازی پوست.',
		'content'  => '<p>پیش از شروع، حساسیت‌ها و وضعیت پوست بررسی می‌شود. شدت لایه‌برداری بر اساس تحمل پوست تنظیم می‌گردد.</p><h2>پس از خدمت</h2><p>تا ۲۴ ساعت از محصولات فعال و نور مستقیم خورشید دوری کنید.</p>',
		'duration' => 90,
		'price'    => 1250000,
		'type'     => 'fixed',
		'image'    => 'portfolio-care.jpg',
		'term'     => 'skin',
	),
);
$service_ids = array();
foreach ( $services as $index => $service ) {
	$id = parisan_demo_post(
		'parisan_service',
		$service['slug'],
		array(
			'post_title'   => $service['title'],
			'post_excerpt' => $service['excerpt'],
			'post_content' => $service['content'],
			'menu_order'   => $index,
		)
	);
	if ( ! $id ) {
		continue;
	}
	$service_ids[ $service['slug'] ] = $id;
	update_post_meta( $id, '_parisan_duration', $service['duration'] );
	update_post_meta( $id, '_parisan_price', $service['price'] );
	update_post_meta( $id, '_parisan_price_type', $service['type'] );
	$image_id = parisan_demo_image( $service['image'], $service['title'] . ' — تصویر نمایشی', $service['title'] );
	if ( $image_id ) {
		set_post_thumbnail( $id, $image_id );
	}
	if ( isset( $service_term_ids[ $service['term'] ] ) ) {
		wp_set_object_terms( $id, array( $service_term_ids[ $service['term'] ] ), 'parisan_service_category' );
	}
}

$schedule = array();
foreach ( array( 6, 0, 1, 2, 3, 4 ) as $day ) {
	$schedule[ $day ] = array(
		'start'       => '10:00',
		'end'         => '20:00',
		'break_start' => '14:00',
		'break_end'   => '15:00',
	);
}
$schedule[5] = array( 'off' => true, 'start' => '', 'end' => '', 'break_start' => '', 'break_end' => '' );

$staff = array(
	array(
		'slug'     => 'nazanin',
		'title'    => 'نازنین احمدی',
		'role'     => 'متخصص پوست و فیشیال',
		'excerpt'  => 'مراقبت پوست با رویکرد آرام، دقیق و متناسب با شرایط هر فرد.',
		'content'  => '<p>نازنین در این نسخه نمایشی، متخصص مراقبت پوست است. تمرکز او بر گفت‌وگوی دقیق پیش از خدمت و انتخاب روتین ساده و قابل ادامه است.</p><p>متن این معرفی نمونه است و باید با سابقه واقعی متخصص جایگزین شود.</p>',
		'image'    => 'specialist-1.jpg',
		'services' => array( 'skin-hydration', 'facial-cleanse' ),
	),
	array(
		'slug'     => 'sara',
		'title'    => 'سارا محمدی',
		'role'     => 'متخصص رنگ و کراتین مو',
		'excerpt'  => 'طراحی رنگ، اصلاح تناژ و مراقبت از مو با نگاه به سلیقه شما.',
		'content'  => '<p>سارا در این نسخه نمایشی، متخصص رنگ و مراقبت مو است. مشاوره رنگ و بررسی سابقه درمان مو بخشی از هر جلسه است.</p><p>متن این معرفی نمونه است و باید با سابقه واقعی متخصص جایگزین شود.</p>',
		'image'    => 'specialist-2.jpg',
		'services' => array( 'hair-color' ),
	),
	array(
		'slug'     => 'mahsa',
		'title'    => 'مهسا کریمی',
		'role'     => 'متخصص میکاپ و طراحی چهره',
		'excerpt'  => 'میکاپ ماندگار و متناسب با فرم چهره برای لحظه‌های خاص.',
		'content'  => '<p>مهسا در این نسخه نمایشی، متخصص میکاپ و طراحی چهره است. هماهنگی سبک و انتخاب محصولات بر اساس حساسیت پوست انجام می‌شود.</p><p>متن این معرفی نمونه است و باید با سابقه واقعی متخصص جایگزین شود.</p>',
		'image'    => 'specialist-3.jpg',
		'services' => array( 'bridal-makeup', 'brow-design' ),
	),
);
$staff_ids = array();
foreach ( $staff as $index => $member ) {
	$id = parisan_demo_post(
		'parisan_staff',
		$member['slug'],
		array(
			'post_title'   => $member['title'],
			'post_excerpt' => $member['excerpt'],
			'post_content' => $member['content'],
			'menu_order'   => $index,
		)
	);
	if ( ! $id ) {
		continue;
	}
	$staff_ids[ $member['slug'] ] = $id;
	update_post_meta( $id, '_parisan_staff_role', $member['role'] );
	$eligible = array();
	foreach ( $member['services'] as $slug ) {
		if ( isset( $service_ids[ $slug ] ) ) {
			$eligible[] = $service_ids[ $slug ];
		}
	}
	update_post_meta( $id, '_parisan_staff_services', $eligible );
	update_post_meta( $id, '_parisan_staff_weekly_schedule', parisan_core_normalize_weekly_schedule( $schedule ) );
	$image_id = parisan_demo_image( $member['image'], $member['title'] . ' — پرتره نمایشی', 'پرتره نمایشی ' . $member['title'] );
	if ( $image_id ) {
		set_post_thumbnail( $id, $image_id );
	}
}

$portfolio_terms = array(
	'hair-style' => 'مو',
	'makeup'     => 'میکاپ',
	'nails'      => 'ناخن',
	'skin'       => 'پوست',
);
$portfolio_term_ids = array();
foreach ( $portfolio_terms as $slug => $name ) {
	$term = term_exists( $slug, 'parisan_portfolio_category' );
	if ( ! $term ) {
		$term = wp_insert_term( $name, 'parisan_portfolio_category', array( 'slug' => $slug ) );
	}
	if ( ! is_wp_error( $term ) && isset( $term['term_id'] ) ) {
		$portfolio_term_ids[ $slug ] = (int) $term['term_id'];
	}
}

$portfolio = array(
	array( 'glow', 'درخشش طبیعی', 'پوست', 'آرایش نمایشی با تمرکز بر درخشش طبیعی و رنگ‌های گرم.', 'portfolio-glow.jpg', 'skin' ),
	array( 'nails', 'ظرافت ناخن', 'ناخن', 'طراحی ناخن نمایشی با جزئیات ظریف و رنگ‌بندی هماهنگ.', 'portfolio-nails.jpg', 'nails' ),
	array( 'updo', 'شینیون کلاسیک', 'مو', 'شینیون نمایشی با خطوط نرم و جزئیات دست‌ساز.', 'portfolio-updo.jpg', 'hair-style' ),
	array( 'evening', 'میکاپ مجلسی', 'میکاپ', 'میکاپ نمایشی برای مهمانی شب با تمرکز بر چشم‌ها.', 'portfolio-evening.jpg', 'makeup' ),
	array( 'care', 'مراقبت پوست', 'پوست', 'روتین نمایشی مراقبت و آبرسانی پوست.', 'portfolio-care.jpg', 'skin' ),
	array( 'bridal', 'میکاپ عروس', 'میکاپ', 'میکاپ نمایشی عروس با پرداخت ملایم و ماندگار.', 'portfolio-bridal.jpg', 'makeup' ),
);
foreach ( $portfolio as $index => $work ) {
	$id = parisan_demo_post(
		'parisan_portfolio',
		'portfolio-' . $work[0],
		array(
			'post_title'   => $work[1],
			'post_excerpt' => $work[3],
			'post_content' => '<p>' . $work[3] . '</p><p>این نمونه‌کار نمایشی است و تصویر آن با هوش مصنوعی برای ارائه محصول ساخته شده است.</p>',
			'menu_order'   => $index,
		)
	);
	if ( ! $id ) {
		continue;
	}
	$image_id = parisan_demo_image( $work[4], $work[1] . ' — نمونه‌کار نمایشی', $work[1] );
	if ( $image_id ) {
		set_post_thumbnail( $id, $image_id );
	}
	if ( isset( $portfolio_term_ids[ $work[5] ] ) ) {
		wp_set_object_terms( $id, array( $portfolio_term_ids[ $work[5] ] ), 'parisan_portfolio_category' );
	}
}

$post_categories = array(
	'skin-care'  => 'مراقبت پوست',
	'hair-tips'  => 'مراقبت مو',
	'self-care'  => 'آرامش و مراقبت',
);
$post_category_ids = array();
foreach ( $post_categories as $slug => $name ) {
	$term = term_exists( $slug, 'category' );
	if ( ! $term ) {
		$term = wp_insert_term( $name, 'category', array( 'slug' => $slug ) );
	}
	if ( ! is_wp_error( $term ) && isset( $term['term_id'] ) ) {
		$post_category_ids[ $slug ] = (int) $term['term_id'];
	}
}

$posts = array(
	array( 'winter-skincare', 'روتین ساده برای پوست در روزهای سرد', 'چند عادت کوچک می‌تواند از خشکی و حساسیت پوست در فصل سرد جلوگیری کند.', 'روتین پوستی خوب، روتین پیچیده نیست. در این مقاله نمایشی، سه اصل ساده را مرور می‌کنیم: پاک‌سازی ملایم، آبرسانی منظم و محافظت روزانه.', 'journal-skincare.jpg', 'skin-care', 0 ),
	array( 'hair-ritual', 'چطور از موهای رنگ‌شده مراقبت کنیم؟', 'راهنمای نمایشی برای حفظ درخشش و کاهش خشکی مو پس از رنگ.', 'پس از رنگ، مو به مراقبت متفاوتی نیاز دارد. شست‌وشوی ملایم، کاهش حرارت و استفاده از ماسک مناسب می‌تواند به حفظ درخشش کمک کند.', 'journal-ritual.jpg', 'hair-tips', 1 ),
	array( 'quiet-beauty', 'زیبایی آرام؛ چند دقیقه برای خودتان', 'چرا مراقبت از خود باید بخشی از روز باشد، نه یک استثنا.', 'مراقبت از خود لازم نیست زمان زیادی بگیرد. یک روتین کوتاه و قابل تکرار می‌تواند حس آرامش و نظم ایجاد کند.', 'journal-color.jpg', 'self-care', 2 ),
	array( 'bridal-timeline', 'برنامه‌ریزی آرام برای روز عروسی', 'چک‌لیست نمایشی برای هماهنگی خدمات زیبایی پیش از روز مراسم.', 'از مشاوره پوست تا انتخاب سبک میکاپ، زمان‌بندی مناسب به شما کمک می‌کند روز مراسم را با آرامش بیشتری بگذرانید.', 'portfolio-bridal.jpg', 'self-care', 3 ),
);
foreach ( $posts as $post ) {
	$id = parisan_demo_post(
		'post',
		$post[0],
		array(
			'post_title'    => $post[1],
			'post_excerpt'  => $post[2],
			'post_content'  => '<p>' . $post[3] . '</p><h2>یک نکته عملی</h2><p>این مطلب نمونه برای نمایش ساختار مجله پاریسان است. برای انتشار واقعی، آن را با محتوای تخصصی و منابع معتبر جایگزین کنید.</p>',
			'post_author'   => 1,
			'post_category' => isset( $post_category_ids[ $post[5] ] ) ? array( $post_category_ids[ $post[5] ] ) : array(),
		)
	);
	if ( ! $id ) {
		continue;
	}
	$image_id = parisan_demo_image( $post[4], $post[1] . ' — تصویر نمایشی', $post[1] );
	if ( $image_id ) {
		set_post_thumbnail( $id, $image_id );
	}
}

$faqs = array(
	array( 'رزرو آنلاین در این نسخه فعال است؟', 'این نسخه نمایشی به‌صورت پیش‌فرض رزرو آنلاین را غیرفعال نگه می‌دارد. برای هماهنگی از صفحه تماس استفاده کنید.' ),
	array( 'چگونه زمان مناسب را انتخاب کنم؟', 'خدمت مورد نظر را از صفحه خدمات انتخاب کنید و برای مشاوره زمان‌بندی با سالن تماس بگیرید. زمان‌های نمایشی این نسخه واقعی نیستند.' ),
	array( 'اگر به لاتکس یا محصول خاصی حساسیت دارم چه کنم؟', 'پیش از مراجعه، حساسیت‌ها و محصولات مورد استفاده خود را به متخصص اطلاع دهید تا گزینه‌های مناسب بررسی شود.' ),
	array( 'چند دقیقه پیش از نوبت مراجعه کنم؟', 'برای گفت‌وگوی اولیه و آماده‌سازی، چند دقیقه زودتر مراجعه کنید. جزئیات دقیق را هنگام هماهنگی نوبت بپرسید.' ),
	array( 'شرایط لغو نوبت چیست؟', 'شرایط لغو و جابه‌جایی باید توسط صاحب سالن تعیین شود. متن نمونه شرایط در صفحه شرایط رزرو قرار دارد و باید پیش از استفاده واقعی بازبینی شود.' ),
);
foreach ( $faqs as $index => $faq ) {
	$id = parisan_demo_post(
		'parisan_faq',
		'faq-' . ( $index + 1 ),
		array(
			'post_title'   => $faq[0],
			'post_content' => '<p>' . $faq[1] . '</p>',
			'menu_order'   => $index,
		)
	);
}

$primary_menu = wp_get_nav_menu_object( 'منوی اصلی پاریسان' );
if ( ! $primary_menu ) {
	$primary_menu_id = wp_create_nav_menu( 'منوی اصلی پاریسان' );
} else {
	$primary_menu_id = (int) $primary_menu->term_id;
	$existing_items  = wp_get_nav_menu_items( $primary_menu_id );
	foreach ( $existing_items as $existing_item ) {
		wp_delete_post( $existing_item->ID, true );
	}
}
$primary_items = array(
	array( 'page', $page_ids['home'], 'خانه' ),
	array( 'page', $page_ids['about'], 'درباره ما' ),
	array( 'archive', 0, 'خدمات', get_post_type_archive_link( 'parisan_service' ) ),
	array( 'archive', 0, 'نمونه‌کارها', get_post_type_archive_link( 'parisan_portfolio' ) ),
	array( 'page', $page_ids['journal'], 'مجله' ),
	array( 'page', $page_ids['contact'], 'تماس' ),
);
foreach ( $primary_items as $position => $item ) {
	$args = array(
		'menu-item-title'    => $item[2],
		'menu-item-position' => $position + 1,
		'menu-item-status'   => 'publish',
	);
	if ( 'page' === $item[0] ) {
		$args['menu-item-object-id'] = $item[1];
		$args['menu-item-object']    = 'page';
		$args['menu-item-type']      = 'post_type';
	} else {
		$args['menu-item-url']  = $item[3];
		$args['menu-item-type'] = 'custom';
	}
	wp_update_nav_menu_item( $primary_menu_id, 0, $args );
}
set_theme_mod( 'nav_menu_locations', array( 'primary' => $primary_menu_id ) );

$footer_menu = wp_get_nav_menu_object( 'منوی پابرگ پاریسان' );
if ( ! $footer_menu ) {
	$footer_menu_id = wp_create_nav_menu( 'منوی پابرگ پاریسان' );
} else {
	$footer_menu_id = (int) $footer_menu->term_id;
	$existing_items = wp_get_nav_menu_items( $footer_menu_id );
	foreach ( $existing_items as $existing_item ) {
		wp_delete_post( $existing_item->ID, true );
	}
}
$footer_items = array(
	array( $page_ids['about'], 'درباره ما' ),
	array( $page_ids['contact'], 'تماس با ما' ),
	array( $page_ids['faq'], 'پرسش‌های متداول' ),
	array( $page_ids['privacy-policy'], 'حریم خصوصی' ),
	array( $page_ids['terms'], 'شرایط رزرو' ),
);
foreach ( $footer_items as $position => $item ) {
	wp_update_nav_menu_item(
		$footer_menu_id,
		0,
		array(
			'menu-item-object-id' => $item[0],
			'menu-item-object'    => 'page',
			'menu-item-type'      => 'post_type',
			'menu-item-title'     => $item[1],
			'menu-item-position'  => $position + 1,
			'menu-item-status'    => 'publish',
		)
	);
}
$locations               = get_theme_mod( 'nav_menu_locations', array() );
$locations['footer']     = $footer_menu_id;
set_theme_mod( 'nav_menu_locations', $locations );

// Remove WordPress starter content that would otherwise leak into nav counts,
// the journal grid, or the "Uncategorized" archive on a fresh install.
foreach ( array( 'hello-world' ) as $starter_post_slug ) {
	$starter_post = get_page_by_path( $starter_post_slug, OBJECT, 'post' );
	if ( $starter_post && ! get_post_meta( $starter_post->ID, '_parisan_demo_content', true ) ) {
		wp_delete_post( $starter_post->ID, true );
	}
}
$starter_sample_page = get_page_by_path( 'sample-page', OBJECT, 'page' );
if ( $starter_sample_page && ! get_post_meta( $starter_sample_page->ID, '_parisan_demo_content', true ) ) {
	wp_delete_post( $starter_sample_page->ID, true );
}
$starter_comment = get_comment( 1 );
if ( $starter_comment && '1' === (string) $starter_comment->comment_post_ID ) {
	wp_delete_comment( $starter_comment->comment_ID, true );
}

flush_rewrite_rules( false );
WP_CLI::success( 'Synthetic Parisan demo content seeded. No public booking, SMS, or payment feature was enabled.' );
