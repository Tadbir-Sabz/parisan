<?php
/**
 * Shared salon resources and capacity-aware booking helpers.
 *
 * @package ParisanCore
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register bookable rooms, beds, chairs, and devices.
 *
 * @return void
 */
function parisan_core_register_resource_post_type() {
	register_post_type(
		'parisan_resource',
		array(
			'labels' => array(
				'name'               => __( 'منابع سالن', 'parisan-core' ),
				'singular_name'      => __( 'منبع سالن', 'parisan-core' ),
				'add_new_item'       => __( 'افزودن منبع', 'parisan-core' ),
				'edit_item'          => __( 'ویرایش منبع', 'parisan-core' ),
				'new_item'           => __( 'منبع جدید', 'parisan-core' ),
				'view_item'          => __( 'مشاهده منبع', 'parisan-core' ),
				'search_items'       => __( 'جستجوی منابع', 'parisan-core' ),
				'not_found'          => __( 'منبعی پیدا نشد.', 'parisan-core' ),
				'menu_name'          => __( 'منابع سالن', 'parisan-core' ),
			),
			'public'              => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => 'edit.php?post_type=parisan_staff',
			'show_in_rest'        => false,
			'supports'            => array( 'title', 'revisions' ),
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
		)
	);
}
add_action( 'init', 'parisan_core_register_resource_post_type', 6 );

/**
 * Add the resource classification editor.
 *
 * @return void
 */
function parisan_core_add_resource_meta_box() {
	add_meta_box( 'parisan-resource-details', __( 'مشخصات منبع', 'parisan-core' ), 'parisan_core_render_resource_meta_box', 'parisan_resource', 'normal', 'high' );
}
add_action( 'add_meta_boxes_parisan_resource', 'parisan_core_add_resource_meta_box' );

/**
 * Render resource classification.
 *
 * @param WP_Post $post Resource post.
 * @return void
 */
function parisan_core_render_resource_meta_box( $post ) {
	wp_nonce_field( 'parisan_save_resource_details', 'parisan_resource_nonce' );
	$type = get_post_meta( $post->ID, '_parisan_resource_type', true );
	$types = array(
		'room' => __( 'اتاق', 'parisan-core' ),
		'bed' => __( 'تخت', 'parisan-core' ),
		'chair' => __( 'صندلی / جایگاه', 'parisan-core' ),
		'device' => __( 'دستگاه', 'parisan-core' ),
		'other' => __( 'سایر', 'parisan-core' ),
	);
	if ( ! isset( $types[ $type ] ) ) {
		$type = 'other';
	}
	echo '<p><label for="parisan-resource-type"><strong>' . esc_html__( 'نوع منبع', 'parisan-core' ) . '</strong></label></p>';
	echo '<select class="widefat" id="parisan-resource-type" name="parisan_resource_type">';
	foreach ( $types as $value => $label ) {
		printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $value ), selected( $type, $value, false ), esc_html( $label ) );
	}
	echo '</select><p class="description">' . esc_html__( 'برای قابل استفاده بودن در رزرو، وضعیت منبع باید «انتشار‌یافته» باشد.', 'parisan-core' ) . '</p>';
	$schedule = get_post_meta( $post->ID, '_parisan_resource_schedule', true );
	$schedule = is_array( $schedule ) ? $schedule : array();
	$enabled = ! empty( $schedule['enabled'] );
	$days = array( 0 => __( 'یکشنبه', 'parisan-core' ), 1 => __( 'دوشنبه', 'parisan-core' ), 2 => __( 'سه‌شنبه', 'parisan-core' ), 3 => __( 'چهارشنبه', 'parisan-core' ), 4 => __( 'پنجشنبه', 'parisan-core' ), 5 => __( 'جمعه', 'parisan-core' ), 6 => __( 'شنبه', 'parisan-core' ) );
	echo '<hr><p><strong>' . esc_html__( 'ساعات کاری مستقل منبع', 'parisan-core' ) . '</strong></p>';
	echo '<label><input type="checkbox" name="parisan_resource_schedule_enabled" value="1" ' . checked( $enabled, true, false ) . '> ' . esc_html__( 'اعمال برنامه هفتگی اختصاصی', 'parisan-core' ) . '</label>';
	echo '<p class="description">' . esc_html__( 'اگر فعال نباشد، منبع از نظر ساعت محدودیت جداگانه‌ای ندارد و برنامه متخصص/سالن ملاک است. روزهای فعالِ بدون تیک، تعطیل محسوب می‌شوند.', 'parisan-core' ) . '</p>';
	foreach ( $days as $day => $label ) {
		$day_data = isset( $schedule['days'][ $day ] ) && is_array( $schedule['days'][ $day ] ) ? $schedule['days'][ $day ] : array();
		$checked = ! empty( $day_data['enabled'] );
		$start = isset( $day_data['start'] ) && preg_match( '/^(?:[01]\\d|2[0-3]):[0-5]\\d$/D', $day_data['start'] ) ? $day_data['start'] : '09:00';
		$end = isset( $day_data['end'] ) && preg_match( '/^(?:[01]\\d|2[0-3]):[0-5]\\d$/D', $day_data['end'] ) ? $day_data['end'] : '18:00';
		printf( '<p style="margin:8px 0"><label><input type="checkbox" name="parisan_resource_schedule_days[%1$d][enabled]" value="1" %2$s> %3$s</label><br><input type="time" name="parisan_resource_schedule_days[%1$d][start]" value="%4$s" aria-label="%3$s - %5$s"> <span aria-hidden="true">—</span> <input type="time" name="parisan_resource_schedule_days[%1$d][end]" value="%6$s" aria-label="%3$s - %7$s"></p>', (int) $day, checked( $checked, true, false ), esc_attr( $label ), esc_attr( $start ), esc_attr__( 'شروع', 'parisan-core' ), esc_attr( $end ), esc_attr__( 'پایان', 'parisan-core' ) );
	}
}

/**
 * Persist resource classification.
 *
 * @param int $post_id Resource ID.
 * @return void
 */
function parisan_core_save_resource_meta( $post_id ) {
	if ( ! isset( $_POST['parisan_resource_nonce'] ) || ! is_string( $_POST['parisan_resource_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['parisan_resource_nonce'] ) ), 'parisan_save_resource_details' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$type = isset( $_POST['parisan_resource_type'] ) && is_string( $_POST['parisan_resource_type'] ) ? sanitize_key( wp_unslash( $_POST['parisan_resource_type'] ) ) : 'other';
	$type = in_array( $type, array( 'room', 'bed', 'chair', 'device', 'other' ), true ) ? $type : 'other';
	update_post_meta( $post_id, '_parisan_resource_type', $type );
	$enabled = isset( $_POST['parisan_resource_schedule_enabled'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['parisan_resource_schedule_enabled'] ) );
	if ( ! $enabled ) {
		delete_post_meta( $post_id, '_parisan_resource_schedule' );
		return;
	}
	$raw_days = isset( $_POST['parisan_resource_schedule_days'] ) && is_array( $_POST['parisan_resource_schedule_days'] ) ? wp_unslash( $_POST['parisan_resource_schedule_days'] ) : array();
	$clean_days = array();
	foreach ( range( 0, 6 ) as $day ) {
		$raw = isset( $raw_days[ $day ] ) && is_array( $raw_days[ $day ] ) ? $raw_days[ $day ] : array();
		if ( empty( $raw['enabled'] ) ) {
			continue;
		}
		$start = isset( $raw['start'] ) && is_string( $raw['start'] ) ? sanitize_text_field( $raw['start'] ) : '';
		$end = isset( $raw['end'] ) && is_string( $raw['end'] ) ? sanitize_text_field( $raw['end'] ) : '';
		if ( ! preg_match( '/^(?:[01]\\d|2[0-3]):[0-5]\\d$/D', $start ) || ! preg_match( '/^(?:[01]\\d|2[0-3]):[0-5]\\d$/D', $end ) || $start >= $end ) {
			continue;
		}
		$clean_days[ $day ] = array( 'enabled' => true, 'start' => $start, 'end' => $end );
	}
	update_post_meta( $post_id, '_parisan_resource_schedule', array( 'enabled' => true, 'days' => $clean_days ) );
}
add_action( 'save_post_parisan_resource', 'parisan_core_save_resource_meta' );

/**
 * Add service-to-resource pool assignment.
 *
 * @return void
 */
function parisan_core_add_service_resources_meta_box() {
	add_meta_box( 'parisan-service-resources', __( 'منابع موردنیاز خدمت', 'parisan-core' ), 'parisan_core_render_service_resources_meta_box', 'parisan_service', 'side', 'default' );
}
add_action( 'add_meta_boxes_parisan_service', 'parisan_core_add_service_resources_meta_box', 20 );

/**
 * Render eligible shared resources for a service.
 *
 * @param WP_Post $post Service post.
 * @return void
 */
function parisan_core_render_service_resources_meta_box( $post ) {
	wp_nonce_field( 'parisan_save_service_resources', 'parisan_service_resources_nonce' );
	$selected = get_post_meta( $post->ID, '_parisan_service_resources', true );
	$selected = is_array( $selected ) ? array_map( 'absint', $selected ) : array();
	$resources = get_posts( array( 'post_type' => 'parisan_resource', 'post_status' => 'publish', 'posts_per_page' => 300, 'orderby' => 'title', 'order' => 'ASC' ) );
	echo '<p>' . esc_html__( 'از هر نوع منبع انتخاب‌شده، یک مورد آزاد برای هر نوبت اختصاص داده می‌شود؛ مثلاً انتخاب اتاق و دستگاه یعنی هر دو هم‌زمان لازم‌اند. چند منبع از یک نوع، گزینه‌های جایگزین هستند و یکی از آن‌ها انتخاب می‌شود.', 'parisan-core' ) . '</p>';
	if ( ! $resources ) {
		echo '<p><em>' . esc_html__( 'هنوز منبع منتشرشده‌ای تعریف نشده است. از بخش متخصصان ← منابع سالن، اتاق، تخت، جایگاه یا دستگاه اضافه کنید.', 'parisan-core' ) . '</em></p>';
		return;
	}
	foreach ( $resources as $resource ) {
		$type = get_post_meta( $resource->ID, '_parisan_resource_type', true );
		$labels = array( 'room' => __( 'اتاق', 'parisan-core' ), 'bed' => __( 'تخت', 'parisan-core' ), 'chair' => __( 'جایگاه', 'parisan-core' ), 'device' => __( 'دستگاه', 'parisan-core' ), 'other' => __( 'سایر', 'parisan-core' ) );
		$type_label = isset( $labels[ $type ] ) ? $labels[ $type ] : $labels['other'];
		printf( '<label style="display:block;margin:0 0 8px"><input type="checkbox" name="parisan_service_resources[]" value="%1$d" %2$s> %3$s <span class="description">(%4$s)</span></label>', (int) $resource->ID, checked( in_array( (int) $resource->ID, $selected, true ), true, false ), esc_html( get_the_title( $resource ) ), esc_html( $type_label ) );
	}
}

/**
 * Persist service resource pool assignment.
 *
 * @param int $post_id Service ID.
 * @return void
 */
function parisan_core_save_service_resources( $post_id ) {
	if ( ! isset( $_POST['parisan_service_resources_nonce'] ) || ! is_string( $_POST['parisan_service_resources_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['parisan_service_resources_nonce'] ) ), 'parisan_save_service_resources' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$raw_submitted = isset( $_POST['parisan_service_resources'] ) && is_array( $_POST['parisan_service_resources'] ) ? wp_unslash( $_POST['parisan_service_resources'] ) : array();
	$submitted = array();
	foreach ( $raw_submitted as $raw_id ) {
		if ( is_scalar( $raw_id ) ) {
			$submitted[] = absint( $raw_id );
		}
	}
	$valid = array();
	foreach ( array_unique( $submitted ) as $resource_id ) {
		if ( $resource_id && 'publish' === get_post_status( $resource_id ) && 'parisan_resource' === get_post_type( $resource_id ) ) {
			$valid[] = $resource_id;
		}
	}
	sort( $valid, SORT_NUMERIC );
	update_post_meta( $post_id, '_parisan_service_resources', $valid );
}
add_action( 'save_post_parisan_service', 'parisan_core_save_service_resources', 20 );

/**
 * Return the published resource pool configured for a service.
 *
 * @param int $service_id Service ID.
 * @return int[]
 */
function parisan_core_get_service_resource_ids( $service_id ) {
	$ids = get_post_meta( absint( $service_id ), '_parisan_service_resources', true );
	$ids = is_array( $ids ) ? array_values( array_unique( array_filter( array_map( static function ( $id ) { return is_scalar( $id ) ? absint( $id ) : 0; }, $ids ) ) ) ) : array();
	$valid = array();
	foreach ( $ids as $id ) {
		if ( 'publish' === get_post_status( $id ) && 'parisan_resource' === get_post_type( $id ) ) {
			$valid[] = $id;
		}
	}
	sort( $valid, SORT_NUMERIC );
	return $valid;
}

/**
 * Determine whether a service has a resource requirement configured, even if
 * every configured resource is currently unpublished or unavailable.
 *
 * @param int $service_id Service ID.
 * @return bool
 */
function parisan_core_service_requires_resources( $service_id ) {
	$configured = get_post_meta( absint( $service_id ), '_parisan_service_resources', true );
	return ! empty( $configured );
}

/**
 * Acquire deterministic advisory locks for a resource pool.
 *
 * @param int[] $resource_ids Resource IDs.
 * @return string[]|WP_Error
 */
function parisan_core_acquire_resource_schedule_locks( $resource_ids ) {
	global $wpdb;
	$ids = is_array( $resource_ids ) ? array_values( array_unique( array_filter( array_map( 'absint', $resource_ids ) ) ) ) : array();
	sort( $ids, SORT_NUMERIC );
	$locks = array();
	foreach ( $ids as $id ) {
		$name = 'parisan:' . substr( hash( 'sha256', $wpdb->dbname . '|' . $wpdb->prefix . '|resource|' . $id ), 0, 48 );
		$acquired = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $name ) );
		if ( '1' !== (string) $acquired ) {
			parisan_core_release_resource_schedule_locks( $locks );
			return new WP_Error( 'parisan_resource_lock_unavailable', __( 'ظرفیت یکی از منابع در حال پردازش است؛ لطفاً دوباره تلاش کنید.', 'parisan-core' ) );
		}
		$locks[] = $name;
	}
	return $locks;
}

/**
 * Release resource locks acquired by the current request.
 *
 * @param string[] $lock_names Lock names.
 * @return void
 */
function parisan_core_release_resource_schedule_locks( $lock_names ) {
	global $wpdb;
	foreach ( is_array( $lock_names ) ? array_reverse( $lock_names ) : array() as $name ) {
		if ( is_string( $name ) && '' !== $name ) {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) );
		}
	}
}

/**
 * Check whether a resource's optional weekly schedule contains the complete
 * buffered booking interval. An absent/disabled schedule means unrestricted.
 *
 * @param int                $resource_id Resource post ID.
 * @param DateTimeImmutable  $start_utc Buffered start in UTC.
 * @param DateTimeImmutable  $end_utc Buffered end in UTC.
 * @return bool
 */
function parisan_core_resource_schedule_allows_interval( $resource_id, $start_utc, $end_utc ) {
	$schedule = get_post_meta( absint( $resource_id ), '_parisan_resource_schedule', true );
	if ( ! is_array( $schedule ) || empty( $schedule['enabled'] ) ) {
		return true;
	}
	if ( ! $start_utc instanceof DateTimeImmutable || ! $end_utc instanceof DateTimeImmutable || $end_utc <= $start_utc ) {
		return false;
	}
	$timezone = wp_timezone();
	$local_start = $start_utc->setTimezone( $timezone );
	$local_end = $end_utc->setTimezone( $timezone );
	// A resource interval must fit inside one local calendar day and one enabled window.
	if ( $local_start->format( 'Y-m-d' ) !== $local_end->format( 'Y-m-d' ) ) {
		return false;
	}
	$day = (int) $local_start->format( 'w' );
	$window = isset( $schedule['days'][ $day ] ) && is_array( $schedule['days'][ $day ] ) ? $schedule['days'][ $day ] : array();
	if ( empty( $window['enabled'] ) || empty( $window['start'] ) || empty( $window['end'] ) ) {
		return false;
	}
	$window_start = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $local_start->format( 'Y-m-d' ) . ' ' . $window['start'], $timezone );
	$window_end = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $local_start->format( 'Y-m-d' ) . ' ' . $window['end'], $timezone );
	if ( false === $window_start || false === $window_end ) {
		return false;
	}
	return $local_start >= $window_start && $local_end <= $window_end;
}

/**
 * Attach an available resource to each eligible slot, omitting slots where all
 * configured resources are occupied. Legacy unassigned bookings for every service sharing the pool conservatively
 * block the affected resources until they finish.
 *
 * @param array  $slots Candidate staff slots.
 * @param int    $service_id Service ID.
 * @param string $range_start UTC date range start.
 * @param string $range_end UTC date range end.
 * @param int    $buffer_before Buffer before service.
 * @param int    $buffer_after Buffer after service.
 * @return array
 */
/**
 * Allocate one resource from every required resource type for each slot.
 * Selecting both a room and a device on a service therefore requires both;
 * selecting several beds of the same type means any one bed can satisfy it.
 *
 * @param array  $slots Candidate staff slots.
 * @param int    $service_id Service ID.
 * @param string $range_start UTC range start.
 * @param string $range_end UTC range end.
 * @param int    $buffer_before Buffer before.
 * @param int    $buffer_after Buffer after.
 * @param int    $exclude_booking_id Booking excluded during reschedule.
 * @return array
 */
function parisan_core_assign_resources_to_slots( $slots, $service_id, $range_start, $range_end, $buffer_before = 0, $buffer_after = 0, $exclude_booking_id = 0 ) {
	global $wpdb;
	$resource_ids = parisan_core_get_service_resource_ids( $service_id );
	if ( ! $resource_ids && parisan_core_service_requires_resources( $service_id ) ) {
		return array();
	}
	if ( ! $resource_ids ) {
		foreach ( $slots as &$slot ) {
			$slot['resource_id'] = 0;
			$slot['resource_ids'] = array();
		}
		unset( $slot );
		return $slots;
	}

	$groups = array();
	foreach ( $resource_ids as $resource_id ) {
		$type = get_post_meta( $resource_id, '_parisan_resource_type', true );
		$type = in_array( $type, array( 'room', 'bed', 'chair', 'device', 'other' ), true ) ? $type : 'other';
		$groups[ $type ][] = $resource_id;
	}
	$bookings_table = $wpdb->prefix . 'parisan_bookings';
	$booking_resources_table = $wpdb->prefix . 'parisan_booking_resources';
	$placeholders = implode( ',', array_fill( 0, count( $resource_ids ), '%d' ) );
	$query_start = ( new DateTimeImmutable( $range_start, new DateTimeZone( 'UTC' ) ) )->modify( '-240 minutes' )->format( 'Y-m-d H:i:s' );
	$query_end = ( new DateTimeImmutable( $range_end, new DateTimeZone( 'UTC' ) ) )->modify( '+240 minutes' )->format( 'Y-m-d H:i:s' );
	$shared_service_ids = array( absint( $service_id ) );
	foreach ( $resource_ids as $resource_id ) {
		$service_meta_rows = $wpdb->get_col( $wpdb->prepare(
			"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value LIKE %s",
			'_parisan_service_resources',
			'%' . $wpdb->esc_like( 'i:' . $resource_id . ';' ) . '%'
		) );
		foreach ( is_array( $service_meta_rows ) ? $service_meta_rows : array() as $shared_service_id ) {
			$shared_service_ids[] = absint( $shared_service_id );
		}
	}
	$shared_service_ids = array_values( array_unique( array_filter( $shared_service_ids ) ) );
	$service_placeholders = implode( ',', array_fill( 0, count( $shared_service_ids ), '%d' ) );
	$sql = "SELECT b.id, b.resource_id, b.service_id, b.starts_at_utc, b.ends_at_utc, br.resource_id AS assigned_resource_id
		FROM {$bookings_table} b
		LEFT JOIN {$booking_resources_table} br ON br.booking_id = b.id
		WHERE b.status IN ('pending','confirmed') AND b.starts_at_utc < %s AND b.ends_at_utc > %s
		AND (b.resource_id IN ({$placeholders}) OR br.resource_id IN ({$placeholders}) OR (b.resource_id = 0 AND b.service_id IN ({$service_placeholders})))
		AND b.id <> %d";
	$args = array_merge( array( $query_end, $query_start ), $resource_ids, $resource_ids, $shared_service_ids, array( absint( $exclude_booking_id ) ) );
	$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
	$busy_by_resource = array();
	foreach ( is_array( $rows ) ? $rows : array() as $row ) {
		$assigned_id = absint( $row['assigned_resource_id'] );
		$legacy_id = absint( $row['resource_id'] );
		$targets = $assigned_id ? array( $assigned_id ) : ( $legacy_id ? array( $legacy_id ) : $resource_ids );
		$busy_start = ( new DateTimeImmutable( $row['starts_at_utc'], new DateTimeZone( 'UTC' ) ) )->modify( '-' . min( 240, max( 0, (int) $buffer_before ) ) . ' minutes' );
		$busy_end = ( new DateTimeImmutable( $row['ends_at_utc'], new DateTimeZone( 'UTC' ) ) )->modify( '+' . min( 240, max( 0, (int) $buffer_after ) ) . ' minutes' );
		foreach ( $targets as $target_id ) {
			$busy_by_resource[ $target_id ][] = array( 'start' => $busy_start, 'end' => $busy_end );
		}
	}

	$maintenance_table = $wpdb->prefix . 'parisan_resource_maintenance';
	$maintenance_rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT resource_id, starts_at_utc, ends_at_utc FROM {$maintenance_table}
			WHERE status = 'active' AND resource_id IN ({$placeholders})
			AND starts_at_utc < %s AND ends_at_utc > %s",
			array_merge( $resource_ids, array( $query_end, $query_start ) )
		),
		ARRAY_A
	);
	foreach ( is_array( $maintenance_rows ) ? $maintenance_rows : array() as $maintenance ) {
		$maintenance_resource_id = absint( $maintenance['resource_id'] );
		try {
			$maintenance_start = new DateTimeImmutable( $maintenance['starts_at_utc'], new DateTimeZone( 'UTC' ) );
			$maintenance_end = new DateTimeImmutable( $maintenance['ends_at_utc'], new DateTimeZone( 'UTC' ) );
			$busy_by_resource[ $maintenance_resource_id ][] = array( 'start' => $maintenance_start, 'end' => $maintenance_end );
		} catch ( Exception $exception ) {
			// Invalid historical maintenance rows are ignored rather than blocking all capacity.
		}
	}

	$available = array();
	foreach ( is_array( $slots ) ? $slots : array() as $slot ) {
		try {
			$start = ( new DateTimeImmutable( $slot['starts_at_utc'], new DateTimeZone( 'UTC' ) ) )->modify( '-' . min( 240, max( 0, (int) $buffer_before ) ) . ' minutes' );
			$end = ( new DateTimeImmutable( $slot['ends_at_utc'], new DateTimeZone( 'UTC' ) ) )->modify( '+' . min( 240, max( 0, (int) $buffer_after ) ) . ' minutes' );
		} catch ( Exception $exception ) {
			continue;
		}
		$chosen = array();
		foreach ( $groups as $group_resources ) {
			$group_choice = 0;
			foreach ( $group_resources as $resource_id ) {
				if ( ! parisan_core_resource_schedule_allows_interval( $resource_id, $start, $end ) ) {
					continue;
				}
				$conflict = false;
				foreach ( isset( $busy_by_resource[ $resource_id ] ) ? $busy_by_resource[ $resource_id ] : array() as $busy ) {
					if ( $start < $busy['end'] && $end > $busy['start'] ) {
						$conflict = true;
						break;
					}
				}
				if ( ! $conflict ) {
					$group_choice = $resource_id;
					break;
				}
			}
			if ( ! $group_choice ) {
				$chosen = array();
				break;
			}
			$chosen[] = $group_choice;
		}
		if ( count( $chosen ) === count( $groups ) ) {
			sort( $chosen, SORT_NUMERIC );
			$slot['resource_ids'] = $chosen;
			$slot['resource_id'] = reset( $chosen );
			$available[] = $slot;
		}
	}
	return $available;
}

/**
 * Persist the complete resource assignment for a booking.
 *
 * @param int   $booking_id Booking row ID.
 * @param int[] $resource_ids Assigned resource IDs.
 * @return bool
 */
function parisan_core_store_booking_resources( $booking_id, $resource_ids ) {
	global $wpdb;
	$table = $wpdb->prefix . 'parisan_booking_resources';
	$booking_id = absint( $booking_id );
	$ids = is_array( $resource_ids ) ? array_values( array_unique( array_filter( array_map( 'absint', $resource_ids ) ) ) ) : array();
	if ( ! $booking_id ) {
		return false;
	}
	if ( false === $wpdb->delete( $table, array( 'booking_id' => $booking_id ), array( '%d' ) ) ) {
		return false;
	}
	foreach ( $ids as $resource_id ) {
		if ( false === $wpdb->insert( $table, array( 'booking_id' => $booking_id, 'resource_id' => $resource_id ), array( '%d', '%d' ) ) ) {
			return false;
		}
	}
	return true;
}

/**
 * Get all resources assigned to a booking, including legacy single-resource rows.
 *
 * @param int $booking_id Booking row ID.
 * @return int[]
 */
function parisan_core_get_booking_resource_ids( $booking_id ) {
	global $wpdb;
	$table = $wpdb->prefix . 'parisan_booking_resources';
	$ids = $wpdb->get_col( $wpdb->prepare( "SELECT resource_id FROM {$table} WHERE booking_id = %d ORDER BY resource_id ASC", absint( $booking_id ) ) );
	if ( ! is_array( $ids ) || ! $ids ) {
		$legacy = (int) $wpdb->get_var( $wpdb->prepare( "SELECT resource_id FROM {$wpdb->prefix}parisan_bookings WHERE id = %d", absint( $booking_id ) ) );
		$ids = $legacy ? array( $legacy ) : array();
	}
	return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
}
