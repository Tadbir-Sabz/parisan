<?php
/**
 * Read-only first-run setup and booking readiness center.
 *
 * @package ParisanCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Build actionable setup checks from the current WordPress configuration.
 *
 * This function is deliberately read-only: it never changes settings, content,
 * schedules, database state, or the public-booking feature flag.
 *
 * @return array
 */
function parisan_core_get_setup_readiness_checks() {
	$services = get_posts(
		array(
			'post_type'      => 'parisan_service',
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'fields'         => 'ids',
		)
	);
	$staff = get_posts(
		array(
			'post_type'      => 'parisan_staff',
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'fields'         => 'ids',
		)
	);

	$bookable_services = array();
	foreach ( $services as $service_id ) {
		$duration    = absint( get_post_meta( $service_id, '_parisan_duration', true ) );
		$price       = absint( get_post_meta( $service_id, '_parisan_price', true ) );
		$price_type  = get_post_meta( $service_id, '_parisan_price_type', true );
		if ( $duration > 0 && $price > 0 && 'fixed' === $price_type ) {
			$bookable_services[] = $service_id;
		}
	}

	$staff_with_schedule = array();
	$staff_with_service  = array();
	foreach ( $staff as $staff_id ) {
		$schedule = get_post_meta( $staff_id, '_parisan_staff_weekly_schedule', true );
		$has_open_day = false;
		if ( is_array( $schedule ) ) {
			foreach ( $schedule as $day ) {
				if ( ! is_array( $day ) || ! empty( $day['off'] ) ) {
					continue;
				}
				$start = isset( $day['start'] ) ? (string) $day['start'] : '';
				$end   = isset( $day['end'] ) ? (string) $day['end'] : '';
				if ( preg_match( '/^(?:[01]\\d|2[0-3]):[0-5]\\d$/D', $start ) && preg_match( '/^(?:[01]\\d|2[0-3]):[0-5]\\d$/D', $end ) && $start < $end ) {
					$has_open_day = true;
					break;
				}
			}
		}
		if ( $has_open_day ) {
			$staff_with_schedule[] = $staff_id;
		}
		$assigned = get_post_meta( $staff_id, '_parisan_staff_services', true );
		if ( is_array( $assigned ) && array_intersect( array_map( 'absint', $assigned ), $bookable_services ) ) {
			$staff_with_service[] = $staff_id;
		}
	}

	$timezone = wp_timezone_string();
	$front_page_id = absint( get_option( 'page_on_front' ) );
	$front_page_ready = 'page' !== get_option( 'show_on_front' ) || ( $front_page_id && 'publish' === get_post_status( $front_page_id ) );
	$booking_enabled = function_exists( 'parisan_core_public_booking_enabled' ) && parisan_core_public_booking_enabled();

	return array(
		array(
			'id'          => 'theme',
			'title'       => __( 'پوسته پاریسان فعال باشد', 'parisan-core' ),
			'ready'       => 'parisan' === get_stylesheet(),
			'detail'      => 'parisan' === get_stylesheet() ? __( 'پوسته پاریسان فعال است.', 'parisan-core' ) : __( 'پوسته Parisan Beauty را از بخش نمایش ← پوسته‌ها فعال کنید.', 'parisan-core' ),
			'url'         => admin_url( 'themes.php' ),
			'action'      => __( 'بررسی پوسته‌ها', 'parisan-core' ),
			'required'    => true,
		),
		array(
			'id'          => 'front-page',
			'title'       => __( 'صفحه نخست سایت آماده باشد', 'parisan-core' ),
			'ready'       => $front_page_ready,
			'detail'      => $front_page_ready ? __( 'تنظیم صفحه نخست معتبر است.', 'parisan-core' ) : __( 'صفحه‌ای که به‌عنوان صفحه نخست انتخاب شده منتشر نیست؛ یا یک صفحه منتشرشده انتخاب کنید یا نمایش آخرین نوشته‌ها را به‌کار ببرید.', 'parisan-core' ),
			'url'         => admin_url( 'options-reading.php' ),
			'action'      => __( 'تنظیم صفحه نخست', 'parisan-core' ),
			'required'    => true,
		),
		array(
			'id'          => 'timezone',
			'title'       => __( 'منطقه زمانی سالن مشخص باشد', 'parisan-core' ),
			'ready'       => '' !== $timezone,
			'detail'      => '' !== $timezone ? sprintf( __( 'منطقه زمانی فعلی وردپرس: %s', 'parisan-core' ), $timezone ) : __( 'منطقه زمانی را در تنظیمات عمومی وردپرس تعیین کنید.', 'parisan-core' ),
			'url'         => admin_url( 'options-general.php' ),
			'action'      => __( 'تنظیم منطقه زمانی', 'parisan-core' ),
			'required'    => true,
		),
		array(
			'id'          => 'services',
			'title'       => __( 'حداقل یک خدمت قابل رزرو ثبت شده باشد', 'parisan-core' ),
			'ready'       => ! empty( $bookable_services ),
			'detail'      => sprintf( __( '%1$d خدمت منتشرشده؛ %2$d خدمت با مدت و قیمت ثابت معتبر.', 'parisan-core' ), count( $services ), count( $bookable_services ) ),
			'url'         => admin_url( 'edit.php?post_type=parisan_service' ),
			'action'      => __( 'مدیریت خدمات', 'parisan-core' ),
			'required'    => true,
		),
		array(
			'id'          => 'staff',
			'title'       => __( 'متخصص با برنامه کاری و خدمت مرتبط وجود داشته باشد', 'parisan-core' ),
			'ready'       => ! empty( $staff_with_schedule ) && ! empty( $staff_with_service ),
			'detail'      => sprintf( __( '%1$d متخصص منتشرشده؛ %2$d دارای ساعت کاری معتبر؛ %3$d متصل به خدمت قابل رزرو.', 'parisan-core' ), count( $staff ), count( $staff_with_schedule ), count( $staff_with_service ) ),
			'url'         => admin_url( 'edit.php?post_type=parisan_staff' ),
			'action'      => __( 'مدیریت متخصصان و برنامه کاری', 'parisan-core' ),
			'required'    => true,
		),
		array(
			'id'          => 'booking-rules',
			'title'       => __( 'قواعد فاصله و آماده‌سازی رزرو بررسی شده باشد', 'parisan-core' ),
			'ready'       => true,
			'detail'      => sprintf( __( 'گام شروع: %1$d دقیقه؛ فاصله پیش از خدمت: %2$d دقیقه؛ فاصله پس از خدمت: %3$d دقیقه.', 'parisan-core' ), absint( get_option( 'parisan_booking_slot_interval_minutes', 15 ) ), absint( get_option( 'parisan_booking_buffer_before_minutes', 0 ) ), absint( get_option( 'parisan_booking_buffer_after_minutes', 0 ) ) ),
			'url'         => admin_url( 'edit.php?post_type=parisan_staff&page=parisan-booking-settings' ),
			'action'      => __( 'بازبینی قواعد رزرو', 'parisan-core' ),
			'required'    => true,
		),
		array(
			'id'          => 'https',
			'title'       => __( 'HTTPS برای استفاده عملیاتی بررسی شود', 'parisan-core' ),
			'ready'       => is_ssl(),
			'detail'      => is_ssl() ? __( 'درخواست فعلی از طریق HTTPS دریافت شده است؛ اعتبار گواهی و تنظیم reverse proxy همچنان باید در محیط واقعی بررسی شود.', 'parisan-core' ) : __( 'این سایت در درخواست فعلی HTTPS تشخیص نداده است. پیش از هرگونه رزرو عمومی، گواهی و تنظیمات پراکسی را بررسی کنید.', 'parisan-core' ),
			'url'         => admin_url( 'options-general.php' ),
			'action'      => __( 'بررسی نشانی سایت', 'parisan-core' ),
			'required'    => false,
		),
		array(
			'id'          => 'public-booking',
			'title'       => __( 'دروازه رزرو عمومی', 'parisan-core' ),
			'ready'       => ! $booking_enabled,
			'detail'      => $booking_enabled ? __( 'رزرو عمومی با تنظیم صریح مالک فعال شده است؛ این صفحه آن را تغییر نمی‌دهد.', 'parisan-core' ) : __( 'رزرو عمومی خاموش است. این وضعیت محافظتی است؛ فعال‌سازی فقط پس از تکمیل بررسی امنیت، زیرساخت، تجربه مشتری و تأیید صریح مالک مجاز است.', 'parisan-core' ),
			'url'         => admin_url( 'edit.php?post_type=parisan_staff&page=parisan-settings' ),
			'action'      => __( 'مشاهده وضعیت رزرو', 'parisan-core' ),
			'required'    => false,
		),
	);
}

/**
 * Register the read-only setup readiness screen.
 *
 * @return void
 */
function parisan_core_register_setup_readiness_page() {
	add_submenu_page(
		'edit.php?post_type=parisan_staff',
		__( 'راهنمای شروع و آمادگی', 'parisan-core' ),
		__( 'راهنمای شروع', 'parisan-core' ),
		'manage_options',
		'parisan-setup-readiness',
		'parisan_core_render_setup_readiness_page'
	);
}
add_action( 'admin_menu', 'parisan_core_register_setup_readiness_page', 25 );

/**
 * Render the first-run checklist without changing site state.
 *
 * @return void
 */
function parisan_core_render_setup_readiness_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'اجازه مشاهده راهنمای شروع را ندارید.', 'parisan-core' ), 403 );
	}
	$checks = parisan_core_get_setup_readiness_checks();
	$required_total = 0;
	$required_ready = 0;
	foreach ( $checks as $check ) {
		if ( ! empty( $check['required'] ) ) {
			++$required_total;
			if ( $check['ready'] ) {
				++$required_ready;
			}
		}
	}
	echo '<div class="wrap parisan-settings-workspace" dir="rtl">';
	echo '<h1>' . esc_html__( 'شروع کار با پاریسان', 'parisan-core' ) . '</h1>';
	echo '<p class="description">' . esc_html__( 'این راهنما وضعیت واقعی تنظیمات و محتوای سایت را می‌خواند و برای هر مورد، مسیر اقدام بعدی را نشان می‌دهد. هیچ تنظیم یا داده‌ای را تغییر نمی‌دهد.', 'parisan-core' ) . '</p>';
	echo '<div class="notice notice-info"><p><strong>' . esc_html( sprintf( __( '%1$d از %2$d پیش‌نیاز اصلی آماده است.', 'parisan-core' ), $required_ready, $required_total ) ) . '</strong> ' . esc_html__( 'این شمارنده فقط آمادگی پایه را نشان می‌دهد و به معنی مجوز فعال‌سازی رزرو عمومی یا آمادگی انتشار نیست.', 'parisan-core' ) . '</p></div>';
	echo '<div class="parisan-readiness-list">';
	foreach ( $checks as $check ) {
		$status_class = $check['ready'] ? 'is-ready' : 'is-pending';
		$status_label = $check['ready'] ? __( 'آماده', 'parisan-core' ) : __( 'نیازمند اقدام', 'parisan-core' );
		echo '<section class="parisan-readiness-item ' . esc_attr( $status_class ) . '">';
		echo '<div class="parisan-readiness-copy"><h2>' . esc_html( $check['title'] ) . '</h2><p>' . esc_html( $check['detail'] ) . '</p></div>';
		echo '<div class="parisan-readiness-action"><span class="parisan-readiness-status">' . esc_html( $status_label ) . '</span><a class="button button-secondary" href="' . esc_url( $check['url'] ) . '">' . esc_html( $check['action'] ) . '</a></div>';
		echo '</section>';
	}
	echo '</div>';
	echo '<p class="description">' . esc_html__( 'برای شروع امن، ابتدا خدمات و متخصصان و برنامه هفتگی را تکمیل کنید. رزرو عمومی به‌صورت پیش‌فرض خاموش می‌ماند و این چک‌لیست هیچ راهی برای روشن‌کردن آن ارائه نمی‌کند.', 'parisan-core' ) . '</p>';
	echo '</div>';
}
