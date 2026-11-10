<?php
/**
 * Persian-first Parisan settings workspace and booking rules.
 *
 * @package ParisanCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Load the settings workspace stylesheet only on its own screens.
 *
 * @param string $hook_suffix Current admin screen hook.
 * @return void
 */
function parisan_core_enqueue_settings_admin_assets( $hook_suffix ) {
	if ( false === strpos( (string) $hook_suffix, 'parisan-settings' ) && false === strpos( (string) $hook_suffix, 'parisan-booking-settings' ) ) {
		return;
	}
	wp_enqueue_style(
		'parisan-admin-settings',
		plugins_url( 'assets/css/admin-settings.css', PARISAN_CORE_FILE ),
		array(),
		PARISAN_CORE_VERSION
	);
}
add_action( 'admin_enqueue_scripts', 'parisan_core_enqueue_settings_admin_assets' );

/**
 * Sanitize the slot increment used by the availability engine.
 *
 * @param mixed $value Submitted value.
 * @return int
 */
function parisan_core_sanitize_booking_interval( $value ) {
	$value = absint( $value );
	$allowed = array( 5, 10, 15, 20, 30, 60 );
	return in_array( $value, $allowed, true ) ? $value : 15;
}

/**
 * Sanitize a booking buffer in minutes.
 *
 * @param mixed $value Submitted value.
 * @return int
 */
function parisan_core_sanitize_booking_buffer( $value ) {
	return min( 240, absint( $value ) );
}

/**
 * Register settings that are already consumed by the availability engine.
 *
 * @return void
 */
function parisan_core_register_booking_settings() {
	register_setting(
		'parisan_core_booking',
		'parisan_booking_slot_interval_minutes',
		array(
			'type'              => 'integer',
			'default'           => 15,
			'sanitize_callback' => 'parisan_core_sanitize_booking_interval',
		)
	);
	register_setting(
		'parisan_core_booking',
		'parisan_booking_buffer_before_minutes',
		array(
			'type'              => 'integer',
			'default'           => 0,
			'sanitize_callback' => 'parisan_core_sanitize_booking_buffer',
		)
	);
	register_setting(
		'parisan_core_booking',
		'parisan_booking_buffer_after_minutes',
		array(
			'type'              => 'integer',
			'default'           => 0,
			'sanitize_callback' => 'parisan_core_sanitize_booking_buffer',
		)
	);

	add_settings_section(
		'parisan_core_booking_rules',
		__( 'قواعد محاسبه زمان‌های قابل رزرو', 'parisan-core' ),
		'parisan_core_render_booking_settings_intro',
		'parisan-core-booking-settings'
	);
	add_settings_field(
		'parisan_booking_slot_interval_minutes',
		__( 'فاصله شروع نوبت‌ها', 'parisan-core' ),
		'parisan_core_render_booking_interval_field',
		'parisan-core-booking-settings',
		'parisan_core_booking_rules'
	);
	add_settings_field(
		'parisan_booking_buffer_before_minutes',
		__( 'زمان آماده‌سازی پیش از خدمت', 'parisan-core' ),
		'parisan_core_render_booking_buffer_before_field',
		'parisan-core-booking-settings',
		'parisan_core_booking_rules'
	);
	add_settings_field(
		'parisan_booking_buffer_after_minutes',
		__( 'زمان جمع‌بندی پس از خدمت', 'parisan-core' ),
		'parisan_core_render_booking_buffer_after_field',
		'parisan-core-booking-settings',
		'parisan_core_booking_rules'
	);
}
add_action( 'admin_init', 'parisan_core_register_booking_settings' );

/**
 * Register the unified settings workspace and its booking-rules page.
 *
 * @return void
 */
function parisan_core_register_settings_pages() {
	$parent = 'edit.php?post_type=parisan_staff';
	add_submenu_page(
		$parent,
		__( 'تنظیمات پاریسان', 'parisan-core' ),
		__( 'تنظیمات پاریسان', 'parisan-core' ),
		'manage_options',
		'parisan-settings',
		'parisan_core_render_settings_hub'
	);
	add_submenu_page(
		$parent,
		__( 'قوانین رزرو', 'parisan-core' ),
		__( 'قوانین رزرو', 'parisan-core' ),
		'manage_options',
		'parisan-booking-settings',
		'parisan_core_render_booking_settings_page'
	);
}
add_action( 'admin_menu', 'parisan_core_register_settings_pages', 20 );

/**
 * Render a settings workspace card.
 *
 * @param string $title Card title.
 * @param string $description Card description.
 * @param string $url Destination URL.
 * @param string $status Optional availability note.
 * @return void
 */
function parisan_core_render_settings_card( $title, $description, $url, $status = '' ) {
	printf(
		'<section class="parisan-settings-card"><h2>%1$s</h2><p>%2$s</p><p><a class="button button-secondary" href="%3$s">%4$s</a></p>%5$s</section>',
		esc_html( $title ),
		esc_html( $description ),
		esc_url( $url ),
		esc_html__( 'ورود به تنظیمات', 'parisan-core' ),
		'' !== $status ? '<p class="description">' . esc_html( $status ) . '</p>' : ''
	);
}

/**
 * Render the central settings workspace.
 *
 * @return void
 */
function parisan_core_render_settings_hub() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'اجازه دسترسی به تنظیمات پاریسان را ندارید.', 'parisan-core' ), 403 );
	}

	$booking_enabled = function_exists( 'parisan_core_public_booking_enabled' ) && parisan_core_public_booking_enabled();
	$cards = array(
		array(
			__( 'هویت بصری و رنگ‌بندی', 'parisan-core' ),
			__( 'رنگ‌های حالت روز و شب، فونت، اندازه متن و اطلاعات تماس عمومی سالن را مدیریت کنید.', 'parisan-core' ),
			admin_url( 'customize.php?autofocus[section]=parisan_brand' ),
			__( 'تنظیمات در سفارشی‌ساز پوسته قرار دارد.', 'parisan-core' ),
		),
		array(
			__( 'محتوای صفحه اصلی', 'parisan-core' ),
			__( 'عنوان‌ها، توضیحات، متن دکمه‌ها و پیوندهای بخش‌های صفحه نخست را ویرایش کنید.', 'parisan-core' ),
			admin_url( 'customize.php?autofocus[section]=parisan_homepage_content' ),
			__( 'تغییرات پس از انتشار سفارشی‌ساز روی سایت اعمال می‌شوند.', 'parisan-core' ),
		),
		array(
			__( 'تصویر اصلی و کادر نمایش', 'parisan-core' ),
			__( 'تصویر هیرو، متن جایگزین و نقطه تمرکز عکس را برای نمایش دسکتاپ و موبایل تنظیم کنید.', 'parisan-core' ),
			admin_url( 'customize.php?autofocus[section]=parisan_homepage' ),
			'',
		),
		array(
			__( 'قوانین رزرو و زمان‌بندی', 'parisan-core' ),
			__( 'فاصله شروع نوبت‌ها و زمان آماده‌سازی پیش و پس از هر خدمت را تعیین کنید.', 'parisan-core' ),
			admin_url( 'edit.php?post_type=parisan_staff&page=parisan-booking-settings' ),
			__( 'این مقادیر مستقیماً در محاسبه زمان‌های آزاد استفاده می‌شوند.', 'parisan-core' ),
		),
		array(
			__( 'سئو و عملکرد', 'parisan-core' ),
			__( 'اطلاعات پایه سئو، داده ساختاریافته و وضعیت تشخیص لایه‌های کش را بررسی کنید.', 'parisan-core' ),
			admin_url( 'edit.php?post_type=parisan_staff&page=parisan-core-seo' ),
			'',
		),
		array(
			__( 'منطقه زمانی و زبان سایت', 'parisan-core' ),
			__( 'منطقه زمانی وردپرس مبنای محاسبه ساعت کاری و نوبت‌هاست؛ آن را مطابق محل فعالیت سالن تنظیم کنید.', 'parisan-core' ),
			admin_url( 'options-general.php' ),
			__( 'از تنظیمات عمومی وردپرس، منطقه زمانی و زبان سایت را تعیین کنید.', 'parisan-core' ),
		),
		array(
			__( 'منوهای وب‌سایت', 'parisan-core' ),
			__( 'پیوندهای منوی اصلی و پابرگ را بدون ویرایش کد مدیریت کنید.', 'parisan-core' ),
			admin_url( 'nav-menus.php' ),
			'',
		),
	);

	echo '<div class="wrap parisan-settings-workspace" dir="rtl">';
	echo '<h1>' . esc_html__( 'تنظیمات پاریسان', 'parisan-core' ) . '</h1>';
	echo '<p class="description">' . esc_html__( 'مرکز مدیریت ظاهر، محتوای وب‌سایت و قواعد عملیاتی سالن. هر بخش به تنظیمات واقعی همان قابلیت هدایت می‌شود.', 'parisan-core' ) . '</p>';
	echo '<div class="notice notice-' . ( $booking_enabled ? 'success' : 'info' ) . '"><p><strong>' . esc_html__( 'وضعیت رزرو عمومی:', 'parisan-core' ) . '</strong> ' . esc_html( $booking_enabled ? __( 'فعال است؛ پیش از استفاده عملیاتی، آزمون امنیت و زیرساخت را کامل کنید.', 'parisan-core' ) : __( 'غیرفعال است. این وضعیت پیش‌فرض محافظتی محصول است و از این صفحه قابل فعال‌سازی نیست.', 'parisan-core' ) ) . '</p></div>';
	echo '<div class="parisan-settings-grid">';
	foreach ( $cards as $card ) {
		parisan_core_render_settings_card( $card[0], $card[1], $card[2], $card[3] );
	}
	echo '</div>';
	echo '<h2 class="parisan-settings-roadmap-title">' . esc_html__( 'امکانات در حال توسعه', 'parisan-core' ) . '</h2>';
	echo '<p>' . esc_html__( 'تنظیمات پیامک، پرداخت آنلاین، فرم‌ساز و گزارش‌های پیشرفته تا زمان تکمیل و آزمون، به‌عنوان قابلیت فعال نمایش داده نمی‌شوند.', 'parisan-core' ) . '</p>';
	echo '</div>';
}

/** Render explanatory text for booking settings. */
function parisan_core_render_booking_settings_intro() {
	echo '<p>' . esc_html__( 'این مقادیر روی محاسبه زمان‌های قابل رزرو اثر مستقیم دارند. ساعت کاری هر متخصص را جداگانه در برنامه هفتگی او تنظیم کنید.', 'parisan-core' ) . '</p>';
	echo '<p>' . esc_html__( 'منطقه زمانی از تنظیمات عمومی وردپرس خوانده می‌شود؛ پیش از تعریف برنامه کاری، آن را بررسی کنید.', 'parisan-core' ) . '</p>';
}

/** Render the slot interval selector. */
function parisan_core_render_booking_interval_field() {
	$value = parisan_core_sanitize_booking_interval( get_option( 'parisan_booking_slot_interval_minutes', 15 ) );
	echo '<select name="parisan_booking_slot_interval_minutes" id="parisan_booking_slot_interval_minutes">';
	foreach ( array( 5, 10, 15, 20, 30, 60 ) as $minutes ) {
		printf( '<option value="%1$d" %2$s>%3$s</option>', esc_attr( $minutes ), selected( $value, $minutes, false ), esc_html( sprintf( __( '%d دقیقه', 'parisan-core' ), $minutes ) ) );
	}
	echo '</select><p class="description">' . esc_html__( 'مثلاً فاصله ۱۵ دقیقه یعنی زمان‌های شروع می‌توانند در گام‌های ۱۵ دقیقه‌ای پیشنهاد شوند.', 'parisan-core' ) . '</p>';
}

/** Render the pre-service buffer field. */
function parisan_core_render_booking_buffer_before_field() {
	$value = parisan_core_sanitize_booking_buffer( get_option( 'parisan_booking_buffer_before_minutes', 0 ) );
	printf( '<input type="number" min="0" max="240" step="5" class="small-text" name="parisan_booking_buffer_before_minutes" id="parisan_booking_buffer_before_minutes" value="%1$d"> <span>%2$s</span>', esc_attr( $value ), esc_html__( 'دقیقه', 'parisan-core' ) );
	echo '<p class="description">' . esc_html__( 'برای آماده‌سازی متخصص یا فضای کار پیش از شروع خدمت؛ صفر یعنی بدون فاصله اضافی.', 'parisan-core' ) . '</p>';
}

/** Render the post-service buffer field. */
function parisan_core_render_booking_buffer_after_field() {
	$value = parisan_core_sanitize_booking_buffer( get_option( 'parisan_booking_buffer_after_minutes', 0 ) );
	printf( '<input type="number" min="0" max="240" step="5" class="small-text" name="parisan_booking_buffer_after_minutes" id="parisan_booking_buffer_after_minutes" value="%1$d"> <span>%2$s</span>', esc_attr( $value ), esc_html__( 'دقیقه', 'parisan-core' ) );
	echo '<p class="description">' . esc_html__( 'برای نظافت، ضدعفونی یا جمع‌بندی پس از خدمت؛ صفر یعنی بدون فاصله اضافی.', 'parisan-core' ) . '</p>';
}

/**
 * Render the booking rules page.
 *
 * @return void
 */
function parisan_core_render_booking_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'اجازه ویرایش قوانین رزرو را ندارید.', 'parisan-core' ), 403 );
	}

	echo '<div class="wrap parisan-booking-settings" dir="rtl">';
	echo '<h1>' . esc_html__( 'قوانین رزرو و زمان‌بندی', 'parisan-core' ) . '</h1>';
	echo '<p class="description">' . esc_html__( 'تنظیمات زیر برای تمام خدمات و متخصصان اعمال می‌شود؛ ساعت کاری و روزهای تعطیل هر متخصص از صفحه پروفایل او مدیریت می‌شود.', 'parisan-core' ) . '</p>';
	echo '<form method="post" action="options.php">';
	settings_fields( 'parisan_core_booking' );
	do_settings_sections( 'parisan-core-booking-settings' );
	submit_button( __( 'ذخیره قوانین رزرو', 'parisan-core' ) );
	echo '</form>';
	echo '<p><a href="' . esc_url( admin_url( 'edit.php?post_type=parisan_staff&page=parisan-settings' ) ) . '">' . esc_html__( 'بازگشت به تنظیمات پاریسان', 'parisan-core' ) . '</a></p>';
	echo '</div>';
}
