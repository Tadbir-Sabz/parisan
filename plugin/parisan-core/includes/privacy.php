<?php
/**
 * Privacy export/erasure, retention controls, and reusable request throttling.
 *
 * Public booking routes are intentionally not registered by this module. Any
 * future public endpoint must call the rate limiter before doing expensive work
 * and must still enforce object-level authorization independently.
 *
 * @package ParisanCore
 */

defined( 'ABSPATH' ) || exit;

define( 'PARISAN_CORE_RETENTION_HOOK', 'parisan_core_booking_retention_cleanup' );
define( 'PARISAN_CORE_RETENTION_OPTION', 'parisan_core_terminal_retention_days' );

/**
 * Return the statuses whose customer contact data may be anonymized.
 *
 * Active bookings are retained because they are still needed to deliver the
 * requested service. Terminal booking history is retained without contact PII.
 *
 * @return string[]
 */
function parisan_core_erasable_booking_statuses() {
	return array( 'cancelled', 'expired', 'completed', 'no_show' );
}

/**
 * Build a safe, parameterized booking identity predicate.
 *
 * @param string $email_address WordPress privacy request email.
 * @param int    $user_id       WordPress user ID.
 * @return array{sql:string,args:array}
 */
function parisan_core_privacy_identity_predicate( $email_address, $user_id ) {
	$clauses = array();
	$args    = array();

	$email_address = is_string( $email_address ) ? sanitize_email( $email_address ) : '';
	$user_id       = absint( $user_id );

	if ( '' !== $email_address && is_email( $email_address ) ) {
		$clauses[] = 'customer_email = %s';
		$args[]    = $email_address;
	}
	if ( $user_id ) {
		$clauses[] = 'customer_user_id = %d';
		$args[]    = $user_id;
	}

	return array(
		'sql'  => $clauses ? '(' . implode( ' OR ', $clauses ) . ')' : '1 = 0',
		'args' => $args,
	);
}

/**
 * Anonymize contact fields while preserving booking and event audit structure.
 *
 * @param int[] $booking_ids Booking row IDs.
 * @return int|WP_Error Number of booking rows anonymized.
 */
function parisan_core_anonymize_booking_ids( $booking_ids ) {
	global $wpdb;

	$booking_ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $booking_ids ) ) ) );
	if ( ! $booking_ids ) {
		return 0;
	}

	$bookings_table = $wpdb->prefix . 'parisan_bookings';
	$events_table   = $wpdb->prefix . 'parisan_booking_events';
	$tokens_table   = $wpdb->prefix . 'parisan_customer_tokens';
	$finance_events_table = $wpdb->prefix . 'parisan_booking_finance_events';
	$reminders_table = $wpdb->prefix . 'parisan_booking_reminders';
	foreach ( array( $bookings_table, $events_table, $tokens_table, $finance_events_table, $reminders_table ) as $table ) {
		$status = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS LIKE %s', $wpdb->esc_like( $table ) ), ARRAY_A );
		if ( ! is_array( $status ) || empty( $status['Engine'] ) || 'innodb' !== strtolower( $status['Engine'] ) ) {
			return new WP_Error( 'parisan_privacy_storage_unavailable', __( 'ذخیره‌ساز امن برای اجرای درخواست حریم خصوصی آماده نیست.', 'parisan-core' ) );
		}
	}

	$placeholders = implode( ',', array_fill( 0, count( $booking_ids ), '%d' ) );
	if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
		return new WP_Error( 'parisan_privacy_transaction_failed', __( 'شروع تراکنش درخواست حریم خصوصی ممکن نشد.', 'parisan-core' ) );
	}
	$updated = $wpdb->query(
		$wpdb->prepare(
			"UPDATE {$bookings_table}
			SET customer_name = %s, customer_phone = %s, customer_email = %s, customer_email_consent = 0, customer_user_id = NULL
			WHERE id IN ({$placeholders})",
			array_merge( array( __( 'اطلاعات حذف‌شده', 'parisan-core' ), '', '' ), $booking_ids )
		)
	);
	if ( false === $updated ) {
		$wpdb->query( 'ROLLBACK' );
		return new WP_Error( 'parisan_privacy_booking_update_failed', __( 'ناشناس‌سازی اطلاعات رزرو انجام نشد.', 'parisan-core' ) );
	}

	$tokens_updated = $wpdb->query(
		$wpdb->prepare(
			"UPDATE {$tokens_table} SET revoked_at_utc = %s WHERE booking_id IN ({$placeholders}) AND revoked_at_utc IS NULL",
			array_merge( array( current_time( 'mysql', true ) ), $booking_ids )
		)
	);
	if ( false === $tokens_updated ) {
		$wpdb->query( 'ROLLBACK' );
		return new WP_Error( 'parisan_privacy_token_revoke_failed', __( 'لغو دسترسی مشتری به رزرو انجام نشد.', 'parisan-core' ) );
	}

	$reminders_updated = $wpdb->query(
		$wpdb->prepare( "UPDATE {$reminders_table} SET status = 'skipped', last_error = %s, updated_at_utc = %s WHERE booking_id IN ({$placeholders}) AND status IN ('pending','failed')", array_merge( array( 'privacy_erasure', current_time( 'mysql', true ) ), $booking_ids ) )
	);
	if ( false === $reminders_updated ) {
		$wpdb->query( 'ROLLBACK' );
		return new WP_Error( 'parisan_privacy_reminder_update_failed', __( 'غیرفعال‌سازی یادآورهای رزرو انجام نشد.', 'parisan-core' ) );
	}

	$events_updated = $wpdb->query(
		$wpdb->prepare(
			"UPDATE {$events_table} SET event_note = NULL WHERE booking_id IN ({$placeholders})",
			$booking_ids
		)
	);
	if ( false === $events_updated ) {
		$wpdb->query( 'ROLLBACK' );
		return new WP_Error( 'parisan_privacy_event_update_failed', __( 'پاک‌سازی توضیحات سوابق رزرو انجام نشد.', 'parisan-core' ) );
	}


	$finance_notes_updated = $wpdb->query(
		$wpdb->prepare(
			"UPDATE {$finance_events_table} SET note = %s WHERE booking_id IN ({$placeholders})",
			array_merge( array( __( 'توضیحات حذف‌شده طبق درخواست حریم خصوصی', 'parisan-core' ) ), $booking_ids )
		)
	);
	if ( false === $finance_notes_updated ) {
		$wpdb->query( 'ROLLBACK' );
		return new WP_Error( 'parisan_privacy_finance_event_update_failed', __( 'پاک‌سازی توضیحات رویدادهای مالی رزرو انجام نشد.', 'parisan-core' ) );
	}

	if ( false === $wpdb->query( 'COMMIT' ) ) {
		$wpdb->query( 'ROLLBACK' );
		return new WP_Error( 'parisan_privacy_commit_failed', __( 'ثبت نهایی درخواست حریم خصوصی انجام نشد.', 'parisan-core' ) );
	}

	return (int) $updated;
}

/**
 * Export a page of booking data for a WordPress personal-data request.
 *
 * @param string $email_address Request email.
 * @param int    $user_id       Request user ID.
 * @param int    $page          One-based page number.
 * @return array
 */
function parisan_core_export_booking_personal_data( $email_address, $user_id, $page = 1 ) {
	global $wpdb;

	$identity = parisan_core_privacy_identity_predicate( $email_address, $user_id );
	if ( '1 = 0' === $identity['sql'] ) {
		return array();
	}

	$table = $wpdb->prefix . 'parisan_bookings';
	$limit = 50;
	$offset = ( max( 1, absint( $page ) ) - 1 ) * $limit;
	$sql = "SELECT id, public_id, service_id, staff_id, customer_name, customer_phone, customer_email,
		starts_at_utc, ends_at_utc, duration_minutes, salon_timezone, status, price_amount,
		currency_code, customer_email_consent, created_at_utc, updated_at_utc
		FROM {$table} WHERE {$identity['sql']} ORDER BY id ASC LIMIT %d OFFSET %d";
	$args = array_merge( $identity['args'], array( $limit, $offset ) );
	$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
	$export = array();

	foreach ( (array) $rows as $row ) {
		$booking_id = absint( $row['id'] );
		$service = get_the_title( absint( $row['service_id'] ) );
		$staff = get_the_title( absint( $row['staff_id'] ) );
		$label = sprintf( __( 'رزرو %s', 'parisan-core' ), $row['public_id'] );
		$data = array(
			array( 'name' => __( 'شناسه رزرو', 'parisan-core' ), 'value' => $row['public_id'] ),
			array( 'name' => __( 'نام مشتری', 'parisan-core' ), 'value' => $row['customer_name'] ),
			array( 'name' => __( 'شماره تماس', 'parisan-core' ), 'value' => $row['customer_phone'] ),
			array( 'name' => __( 'ایمیل', 'parisan-core' ), 'value' => $row['customer_email'] ),
			array( 'name' => __( 'رضایت ایمیل یادآوری', 'parisan-core' ), 'value' => ! empty( $row['customer_email_consent'] ) ? 'yes' : 'no' ),
			array( 'name' => __( 'خدمت', 'parisan-core' ), 'value' => $service ? $service : (string) $row['service_id'] ),
			array( 'name' => __( 'متخصص', 'parisan-core' ), 'value' => $staff ? $staff : (string) $row['staff_id'] ),
			array( 'name' => __( 'شروع رزرو (UTC)', 'parisan-core' ), 'value' => $row['starts_at_utc'] ),
			array( 'name' => __( 'پایان رزرو (UTC)', 'parisan-core' ), 'value' => $row['ends_at_utc'] ),
			array( 'name' => __( 'مدت (دقیقه)', 'parisan-core' ), 'value' => (string) $row['duration_minutes'] ),
			array( 'name' => __( 'منطقه زمانی سالن', 'parisan-core' ), 'value' => $row['salon_timezone'] ),
			array( 'name' => __( 'وضعیت', 'parisan-core' ), 'value' => $row['status'] ),
			array( 'name' => __( 'مبلغ ثبت‌شده', 'parisan-core' ), 'value' => $row['price_amount'] . ' ' . $row['currency_code'] ),
			array( 'name' => __( 'زمان ایجاد (UTC)', 'parisan-core' ), 'value' => $row['created_at_utc'] ),
			array( 'name' => __( 'آخرین به‌روزرسانی (UTC)', 'parisan-core' ), 'value' => $row['updated_at_utc'] ),
		);
		$events_table = $wpdb->prefix . 'parisan_booking_events';
		$events = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT event_type, from_status, to_status, event_note, created_at_utc
				FROM {$events_table} WHERE booking_id = %d ORDER BY created_at_utc ASC, id ASC",
				$booking_id
			),
			ARRAY_A
		);
		foreach ( (array) $events as $index => $event ) {
			$event_value = implode(
				' | ',
				array_filter(
					array(
						$event['event_type'],
						$event['from_status'],
						$event['to_status'],
						$event['created_at_utc'],
						$event['event_note'],
					),
					static function ( $value ) {
						return '' !== (string) $value;
					}
				)
			);
			$data[] = array(
				'name'  => sprintf( __( 'رویداد %d', 'parisan-core' ), $index + 1 ),
				'value' => $event_value,
			);
		}

		$finance_events_table = $wpdb->prefix . 'parisan_booking_finance_events';
		$finance_events = $wpdb->get_results(
			$wpdb->prepare( "SELECT event_kind, amount_irr, note, created_at_utc FROM {$finance_events_table} WHERE booking_id = %d ORDER BY id ASC", $booking_id ),
			ARRAY_A
		);
		foreach ( (array) $finance_events as $index => $finance_event ) {
			$data[] = array(
				'name' => sprintf( __( 'رویداد مالی %d', 'parisan-core' ), $index + 1 ),
				'value' => implode( ' | ', array( $finance_event['event_kind'], (string) $finance_event['amount_irr'], $finance_event['created_at_utc'], $finance_event['note'] ) ),
			);
		}

		$export[] = array(
			'group_id'    => 'parisan-bookings',
			'group_label' => __( 'رزروهای سالن', 'parisan-core' ),
			'item_id'     => 'parisan-booking-' . $booking_id,
			'data'        => $data,
		);
	}

	return $export;
}

/**
 * Register Parisan booking personal-data exporter.
 *
 * @param array $exporters Existing exporters.
 * @return array
 */
function parisan_core_register_privacy_exporter( $exporters ) {
	$exporters['parisan-bookings'] = array(
		'exporter_friendly_name' => __( 'رزروهای سالن پاریسان', 'parisan-core' ),
		'callback'               => 'parisan_core_privacy_exporter_callback',
	);
	return $exporters;
}
add_filter( 'wp_privacy_personal_data_exporters', 'parisan_core_register_privacy_exporter' );

function parisan_core_register_contact_inquiry_exporter( $exporters ) {
	$exporters['parisan-contact-inquiries'] = array( 'exporter_friendly_name' => __( 'پیام‌های تماس پاریسان', 'parisan-core' ), 'callback' => 'parisan_core_contact_inquiry_exporter_callback' );
	return $exporters;
}
add_filter( 'wp_privacy_personal_data_exporters', 'parisan_core_register_contact_inquiry_exporter' );

function parisan_core_contact_inquiry_exporter_callback( $email_address, $page = 1 ) {
	global $wpdb;
	$items = array();
	if ( is_email( $email_address ) ) {
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id,customer_name,customer_phone,customer_email,subject_key,message_body,created_at_utc,status FROM {$wpdb->prefix}parisan_contact_inquiries WHERE customer_email=%s ORDER BY id ASC LIMIT 50 OFFSET %d", $email_address, max( 0, ( absint( $page ) - 1 ) * 50 ) ), ARRAY_A );
		foreach ( (array) $rows as $row ) {
			$data = array();
			foreach ( $row as $key => $value ) { $data[] = array( 'name' => sanitize_text_field( $key ), 'value' => (string) $value ); }
			$items[] = array( 'group_id' => 'parisan-contact-inquiries', 'group_label' => __( 'پیام‌های تماس پاریسان', 'parisan-core' ), 'item_id' => 'contact-inquiry-' . (int) $row['id'], 'data' => $data );
		}
	}
	return array( 'data' => $items, 'done' => count( $items ) < 50 );
}


/**
 * WordPress privacy exporter callback.
 *
 * @param string $email_address Request email.
 * @param int    $page          Page number.
 * @return array
 */
function parisan_core_privacy_exporter_callback( $email_address, $page = 1 ) {
	$user = get_user_by( 'email', $email_address );
	$items = parisan_core_export_booking_personal_data( $email_address, $user ? $user->ID : 0, $page );
	return array(
		'data' => $items,
		'done' => count( $items ) < 50,
	);
}

/**
 * Register the booking personal-data eraser.
 *
 * @param array $erasers Existing erasers.
 * @return array
 */
function parisan_core_register_privacy_eraser( $erasers ) {
	$erasers['parisan-bookings'] = array(
		'eraser_friendly_name' => __( 'اطلاعات رزروهای سالن پاریسان', 'parisan-core' ),
		'callback'              => 'parisan_core_privacy_eraser_callback',
	);
	return $erasers;
}
add_filter( 'wp_privacy_personal_data_erasers', 'parisan_core_register_privacy_eraser' );

function parisan_core_register_contact_inquiry_eraser( $erasers ) {
	$erasers['parisan-contact-inquiries'] = array( 'eraser_friendly_name' => __( 'پیام‌های تماس پاریسان', 'parisan-core' ), 'callback' => 'parisan_core_contact_inquiry_eraser_callback' );
	return $erasers;
}
add_filter( 'wp_privacy_personal_data_erasers', 'parisan_core_register_contact_inquiry_eraser' );

function parisan_core_contact_inquiry_eraser_callback( $email_address, $page = 1 ) {
	global $wpdb;
	if ( ! is_email( $email_address ) ) { return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true ); }
	$table = $wpdb->prefix . 'parisan_contact_inquiries';
	$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE customer_email=%s ORDER BY id ASC LIMIT 50", $email_address ) );
	if ( $ids ) {
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ({$placeholders})", array_map( 'absint', $ids ) ) );
		if ( false === $deleted ) { return array( 'items_removed' => false, 'items_retained' => true, 'messages' => array( __( 'حذف پیام‌های تماس کامل نشد.', 'parisan-core' ) ), 'done' => false ); }
	}
	$remaining = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE customer_email=%s", $email_address ) );
	return array( 'items_removed' => (bool) $ids, 'items_retained' => false, 'messages' => array(), 'done' => 0 === $remaining );
}


/**
 * Anonymize terminal booking records; retain active appointments for fulfilment.
 *
 * @param string $email_address Request email.
 * @param int    $page          WordPress eraser page.
 * @return array
 */
function parisan_core_privacy_eraser_callback( $email_address, $page = 1 ) {
	global $wpdb;

	$user = get_user_by( 'email', $email_address );
	$identity = parisan_core_privacy_identity_predicate( $email_address, $user ? $user->ID : 0 );
	$table = $wpdb->prefix . 'parisan_bookings';
	$terminal = parisan_core_erasable_booking_statuses();
	$placeholders = implode( ',', array_fill( 0, count( $terminal ), '%s' ) );
	$args = array_merge( $identity['args'], $terminal );
	$rows = array();
	if ( '1 = 0' !== $identity['sql'] ) {
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE {$identity['sql']} AND status IN ({$placeholders}) ORDER BY id ASC",
				$args
			),
			ARRAY_A
		);
	}
	$ids = array_map( 'absint', wp_list_pluck( (array) $rows, 'id' ) );
	$anonymized = parisan_core_anonymize_booking_ids( $ids );
	if ( is_wp_error( $anonymized ) ) {
		return array(
			'items_removed'  => false,
			'items_retained' => false,
			'messages'       => array( __( 'درخواست حذف اطلاعات رزرو کامل نشد؛ مدیر سایت باید خطای ذخیره‌سازی را بررسی کند.', 'parisan-core' ) ),
			'done'           => false,
		);
	}

	$active = 0;
	if ( '1 = 0' !== $identity['sql'] ) {
		$active_args = array_merge( $identity['args'], array( 'pending', 'confirmed' ) );
		$active = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE {$identity['sql']} AND status IN (%s, %s)",
				$active_args
			)
		);
	}
	$messages = array();
	if ( $active ) {
		$messages[] = sprintf(
			__( '%d رزرو فعال برای انجام خدمت یا مدیریت نوبت حفظ شد؛ پس از پایان رزرو، اطلاعات تماس طبق سیاست نگهداری قابل ناشناس‌سازی است.', 'parisan-core' ),
			$active
		);
	}
	return array(
		'items_removed'  => (int) $anonymized,
		'items_retained' => $active,
		'messages'       => $messages,
		'done'           => true,
	);
}

/**
 * Anonymize a bounded batch of old terminal bookings.
 *
 * @return int|WP_Error
 */
function parisan_core_run_booking_retention_cleanup() {
	global $wpdb;

	$days = absint( get_option( PARISAN_CORE_RETENTION_OPTION, 0 ) );
	if ( $days < 30 || $days > 3650 ) {
		return 0;
	}
	$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );
	$table = $wpdb->prefix . 'parisan_bookings';
	$statuses = parisan_core_erasable_booking_statuses();
	$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT id FROM {$table} WHERE updated_at_utc < %s AND status IN ({$placeholders})
			AND (customer_name <> %s OR customer_phone <> %s OR customer_email <> %s OR customer_user_id IS NOT NULL)
			ORDER BY updated_at_utc ASC LIMIT %d",
			array_merge( array( $cutoff ), $statuses, array( __( 'اطلاعات حذف‌شده', 'parisan-core' ), '', '', 250 ) )
		),
		ARRAY_A
	);
	$ids = array_map( 'absint', wp_list_pluck( (array) $rows, 'id' ) );
	$result = parisan_core_anonymize_booking_ids( $ids );
	if ( ! is_wp_error( $result ) ) {
		update_option( 'parisan_core_retention_last_run', current_time( 'mysql', true ), false );
		update_option( 'parisan_core_retention_last_count', (int) $result, false );
	}
	return $result;
}
add_action( PARISAN_CORE_RETENTION_HOOK, 'parisan_core_run_booking_retention_cleanup' );

/**
 * Synchronize the opt-in daily retention event.
 *
 * @param int $days Retention period; zero disables automatic anonymization.
 * @return void
 */
function parisan_core_update_retention_schedule( $days ) {
	$days = absint( $days );
	wp_clear_scheduled_hook( PARISAN_CORE_RETENTION_HOOK );
	if ( $days >= 30 && $days <= 3650 ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', PARISAN_CORE_RETENTION_HOOK );
	}
}

/**
 * Register the privacy and retention settings screen.
 *
 * @return void
 */
function parisan_core_register_privacy_admin_page() {
	add_submenu_page(
		'edit.php?post_type=parisan_staff',
		__( 'حریم خصوصی و نگهداری داده', 'parisan-core' ),
		__( 'حریم خصوصی و نگهداری داده', 'parisan-core' ),
		'manage_options',
		'parisan-privacy',
		'parisan_core_render_privacy_admin_page'
	);
}
add_action( 'admin_menu', 'parisan_core_register_privacy_admin_page' );

/**
 * Persist the administrator's explicit retention choice.
 *
 * @return void
 */
function parisan_core_handle_retention_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'اجازه تغییر تنظیمات حریم خصوصی را ندارید.', 'parisan-core' ), 403 );
	}
	check_admin_referer( 'parisan_save_retention_settings', 'parisan_retention_nonce' );
	$days = isset( $_POST['retention_days'] ) && is_scalar( $_POST['retention_days'] ) ? absint( wp_unslash( $_POST['retention_days'] ) ) : 0;
	$allowed = array( 0, 90, 180, 365, 730, 1095, 1825 );
	if ( ! in_array( $days, $allowed, true ) ) {
		wp_die( esc_html__( 'بازه نگهداری انتخاب‌شده معتبر نیست.', 'parisan-core' ), 400 );
	}
	update_option( PARISAN_CORE_RETENTION_OPTION, $days, false );
	parisan_core_update_retention_schedule( $days );
	wp_safe_redirect( add_query_arg( array( 'page' => 'parisan-privacy', 'settings-updated' => '1' ), admin_url( 'edit.php?post_type=parisan_staff' ) ) );
	exit;
}
add_action( 'admin_post_parisan_save_retention_settings', 'parisan_core_handle_retention_settings' );

/**
 * Render the privacy and retention settings screen.
 *
 * @return void
 */
function parisan_core_render_privacy_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'اجازه مشاهده این صفحه را ندارید.', 'parisan-core' ), 403 );
	}
	$days = absint( get_option( PARISAN_CORE_RETENTION_OPTION, 0 ) );
	$last_run = get_option( 'parisan_core_retention_last_run', '' );
	$last_count = absint( get_option( 'parisan_core_retention_last_count', 0 ) );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'حریم خصوصی و نگهداری داده', 'parisan-core' ); ?></h1>
		<?php if ( isset( $_GET['settings-updated'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'تنظیمات نگهداری اطلاعات ذخیره شد.', 'parisan-core' ); ?></p></div>
		<?php endif; ?>
		<p><?php esc_html_e( 'اطلاعات تماس مشتری فقط برای کاربران دارای مجوز در صفحه داخلی نوبت نمایش داده می‌شود. ابزارهای حریم خصوصی وردپرس امکان خروجی‌گرفتن و ناشناس‌سازی رزروهای پایان‌یافته را فراهم می‌کنند.', 'parisan-core' ); ?></p>
		<p><?php esc_html_e( 'رزروهای در انتظار و تأییدشده تا زمان پایان یا لغو برای ارائه خدمت حفظ می‌شوند. ناشناس‌سازی، سابقه عملیاتی رزرو را نگه می‌دارد اما نام، تلفن، ایمیل، شناسه کاربر و توضیحات آزاد رویدادها را پاک می‌کند.', 'parisan-core' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="parisan_save_retention_settings">
			<?php wp_nonce_field( 'parisan_save_retention_settings', 'parisan_retention_nonce' ); ?>
			<table class="form-table"><tbody>
				<tr>
					<th scope="row"><label for="parisan-retention-days"><?php esc_html_e( 'مدت نگهداری اطلاعات تماس پس از پایان رزرو', 'parisan-core' ); ?></label></th>
					<td>
						<select id="parisan-retention-days" name="retention_days">
							<option value="0" <?php selected( $days, 0 ); ?>><?php esc_html_e( 'غیرفعال؛ بدون ناشناس‌سازی خودکار', 'parisan-core' ); ?></option>
							<?php foreach ( array( 90, 180, 365, 730, 1095, 1825 ) as $choice ) : ?>
								<option value="<?php echo esc_attr( $choice ); ?>" <?php selected( $days, $choice ); ?>><?php echo esc_html( $choice . ' ' . __( 'روز', 'parisan-core' ) ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'این گزینه به‌صورت پیش‌فرض خاموش است. پس از فعال‌سازی، روزانه حداکثر ۲۵۰ رزرو پایان‌یافته قدیمی ناشناس می‌شود؛ خود رزرو و نوع/زمان رویدادهای حسابرسی حذف نمی‌شوند.', 'parisan-core' ); ?></p>
					</td>
				</tr>
			</tbody></table>
			<?php submit_button( __( 'ذخیره تنظیمات', 'parisan-core' ) ); ?>
		</form>
		<hr>
		<p><strong><?php esc_html_e( 'آخرین اجرای پاک‌سازی:', 'parisan-core' ); ?></strong> <?php echo esc_html( $last_run ? $last_run . ' UTC' : '—' ); ?></p>
		<p><strong><?php esc_html_e( 'تعداد رکوردهای ناشناس‌شده در اجرای اخیر:', 'parisan-core' ); ?></strong> <?php echo esc_html( number_format_i18n( $last_count ) ); ?></p>
	</div>
	<?php
}

/**
 * Append Parisan's data practices to the WordPress privacy policy guide.
 *
 * @return void
 */
function parisan_core_add_privacy_policy_content() {
	if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
		return;
	}
	$content = '<p>' . esc_html__( 'Parisan Core stores appointment details including customer name, phone, optional email, appointment time, selected service/specialist, price snapshot, and operational event history. This data is used to manage requested salon services and is visible only to authorized staff.', 'parisan-core' ) . '</p>';
	$content .= '<p>' . esc_html__( 'WordPress personal-data export and erasure tools include Parisan booking records. Active appointments may be retained while needed to provide or administer the requested service. After a booking becomes terminal, its contact fields and free-form event notes can be anonymized. Automatic anonymization is disabled unless an administrator explicitly selects a retention period.', 'parisan-core' ) . '</p>';
	$content .= '<p>' . esc_html__( 'Parisan contact inquiries store the sender name, phone, optional email, subject, message, and timestamps in a restricted administrator inbox. The site owner must define and apply an appropriate inquiry retention/deletion policy. Manual financial ledger records contain amounts, categories, descriptions, optional specialist association, and the creating administrator; retain them according to applicable accounting obligations.', 'parisan-core' ) . '</p>';
	$content .= '<p>' . esc_html__( 'The site owner must set a retention period appropriate to applicable legal, accounting, and business obligations, and must separately document any processors, backups, or external services. Parisan Core does not send SMS or process payments in this release.', 'parisan-core' ) . '</p>';
	wp_add_privacy_policy_content( __( 'Parisan Core', 'parisan-core' ), wp_kses_post( $content ) );
}
add_action( 'admin_init', 'parisan_core_add_privacy_policy_content' );

/**
 * Apply a MySQL-lock-serialized fixed-window limit to a future public request.
 *
 * This uses only REMOTE_ADDR and deliberately ignores forwarded-IP headers.
 * Callers must still perform authorization and input validation separately.
 *
 * @param string $scope Stable endpoint/action identifier.
 * @param int    $limit Maximum requests per window.
 * @param int    $window Window size in seconds.
 * @return true|WP_Error
 */
function parisan_core_check_rate_limit( $scope, $limit = 5, $window = 300 ) {
	global $wpdb;

	$scope = sanitize_key( $scope );
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) && is_string( $_SERVER['REMOTE_ADDR'] ) ? trim( $_SERVER['REMOTE_ADDR'] ) : '';
	$limit = absint( $limit );
	$window = absint( $window );
	if ( ! preg_match( '/^[a-z0-9_-]{2,48}$/D', $scope ) || ! filter_var( $ip, FILTER_VALIDATE_IP ) || $limit < 1 || $limit > 1000 || $window < 1 || $window > DAY_IN_SECONDS ) {
		return new WP_Error( 'parisan_rate_limit_configuration_invalid', __( 'تنظیمات محدودسازی درخواست معتبر نیست.', 'parisan-core' ) );
	}

	$identity = hash_hmac( 'sha256', $scope . '|' . $ip, wp_salt( 'auth' ) );
	$transient = 'parisan_rl_' . substr( $identity, 0, 40 );
	$lock = 'parisan:rl:' . substr( hash( 'sha256', $wpdb->dbname . '|' . $wpdb->prefix . '|' . $identity ), 0, 40 );
	$acquired = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 1)', $lock ) );
	if ( '1' !== (string) $acquired ) {
		return new WP_Error( 'parisan_rate_limit_unavailable', __( 'سامانه کنترل درخواست موقتاً در دسترس نیست؛ لطفاً کمی بعد تلاش کنید.', 'parisan-core' ) );
	}

	try {
		$state = get_transient( $transient );
		$now = time();
		if ( ! is_array( $state ) || empty( $state['reset_at'] ) || (int) $state['reset_at'] <= $now ) {
			$state = array( 'count' => 0, 'reset_at' => $now + $window );
		}
		if ( (int) $state['count'] >= $limit ) {
			return new WP_Error( 'parisan_rate_limit_exceeded', __( 'تعداد درخواست‌ها بیش از حد مجاز است؛ کمی بعد دوباره تلاش کنید.', 'parisan-core' ), array( 'status' => 429, 'retry_after' => max( 1, (int) $state['reset_at'] - $now ) ) );
		}
		$state['count']++;
		set_transient( $transient, $state, max( 1, (int) $state['reset_at'] - $now ) );
		return true;
	} finally {
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
	}
}


function parisan_core_waitlist_privacy_eraser( $erasers ) {
	$erasers['parisan-waitlist'] = array( 'eraser_friendly_name' => __( 'درخواست‌های فهرست انتظار پاریسان', 'parisan-core' ), 'callback' => 'parisan_core_waitlist_privacy_eraser_callback' );
	return $erasers;
}
add_filter( 'wp_privacy_personal_data_erasers', 'parisan_core_waitlist_privacy_eraser' );

function parisan_core_waitlist_privacy_eraser_callback( $email, $page = 1 ) {
	global $wpdb;
	if ( ! is_email( $email ) ) { return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true ); }
	$table = $wpdb->prefix . 'parisan_booking_waitlist';
	$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE customer_email=%s ORDER BY id ASC LIMIT 50", $email ) );
	if ( $ids ) {
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$deleted = $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ({$placeholders})", array_map( 'absint', $ids ) ) );
		if ( false === $deleted ) { return array( 'items_removed' => false, 'items_retained' => true, 'messages' => array( __( 'حذف اطلاعات فهرست انتظار کامل نشد.', 'parisan-core' ) ), 'done' => false ); }
	}
	$remaining = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE customer_email=%s", $email ) );
	return array( 'items_removed' => (bool) $ids, 'items_retained' => false, 'messages' => array(), 'done' => 0 === $remaining );
}
