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
	add_meta_box( 'parisan-resource-details', __( 'مشخصات منبع', 'parisan-core' ), 'parisan_core_render_resource_meta_box', 'parisan_resource', 'side', 'high' );
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
	echo '<p>' . esc_html__( 'از بین منابع انتخاب‌شده، یک منبع آزاد برای هر نوبت اختصاص داده می‌شود. یک منبع می‌تواند بین چند خدمت مشترک باشد.', 'parisan-core' ) . '</p>';
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
	$submitted = isset( $_POST['parisan_service_resources'] ) && is_array( $_POST['parisan_service_resources'] ) ? array_map( 'absint', wp_unslash( $_POST['parisan_service_resources'] ) ) : array();
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
	$ids = is_array( $ids ) ? array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) ) : array();
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
 * Attach an available resource to each eligible slot, omitting slots where all
 * configured resources are occupied. Legacy unassigned bookings for the same
 * service conservatively block the whole pool until they finish.
 *
 * @param array  $slots Candidate staff slots.
 * @param int    $service_id Service ID.
 * @param string $range_start UTC date range start.
 * @param string $range_end UTC date range end.
 * @param int    $buffer_before Buffer before service.
 * @param int    $buffer_after Buffer after service.
 * @return array
 */
function parisan_core_assign_resources_to_slots( $slots, $service_id, $range_start, $range_end, $buffer_before = 0, $buffer_after = 0, $exclude_booking_id = 0 ) {
	global $wpdb;
	$resource_ids = parisan_core_get_service_resource_ids( $service_id );
	if ( ! $resource_ids ) {
		foreach ( $slots as &$slot ) {
			$slot['resource_id'] = 0;
		}
		unset( $slot );
		return $slots;
	}
	$bookings_table = $wpdb->prefix . 'parisan_bookings';
	$placeholders = implode( ',', array_fill( 0, count( $resource_ids ), '%d' ) );
	$query_start = ( new DateTimeImmutable( $range_start, new DateTimeZone( 'UTC' ) ) )->modify( '-240 minutes' )->format( 'Y-m-d H:i:s' );
	$query_end = ( new DateTimeImmutable( $range_end, new DateTimeZone( 'UTC' ) ) )->modify( '+240 minutes' )->format( 'Y-m-d H:i:s' );
	$sql = "SELECT resource_id, service_id, starts_at_utc, ends_at_utc FROM {$bookings_table}
		WHERE status IN ('pending','confirmed') AND starts_at_utc < %s AND ends_at_utc > %s
		AND (resource_id IN ({$placeholders}) OR (resource_id = 0 AND service_id = %d))";
	$sql .= ' AND id <> %d';
	$args = array_merge( array( $query_end, $query_start ), $resource_ids, array( absint( $service_id ), absint( $exclude_booking_id ) ) );
	$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
	$busy_by_resource = array();
	foreach ( is_array( $rows ) ? $rows : array() as $row ) {
		$resource_id = absint( $row['resource_id'] );
		$busy_start = ( new DateTimeImmutable( $row['starts_at_utc'], new DateTimeZone( 'UTC' ) ) )->modify( '-' . min( 240, max( 0, (int) $buffer_before ) ) . ' minutes' );
		$busy_end = ( new DateTimeImmutable( $row['ends_at_utc'], new DateTimeZone( 'UTC' ) ) )->modify( '+' . min( 240, max( 0, (int) $buffer_after ) ) . ' minutes' );
		$targets = $resource_id ? array( $resource_id ) : $resource_ids;
		foreach ( $targets as $target_id ) {
			$busy_by_resource[ $target_id ][] = array( 'start' => $busy_start, 'end' => $busy_end );
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
		foreach ( $resource_ids as $resource_id ) {
			$conflict = false;
			foreach ( isset( $busy_by_resource[ $resource_id ] ) ? $busy_by_resource[ $resource_id ] : array() as $busy ) {
				if ( $start < $busy['end'] && $end > $busy['start'] ) {
					$conflict = true;
					break;
				}
			}
			if ( ! $conflict ) {
				$slot['resource_id'] = $resource_id;
				$available[] = $slot;
				break;
			}
		}
	}
	return $available;
}
