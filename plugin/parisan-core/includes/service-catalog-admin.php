<?php
/**
 * Actionable service catalogue columns and filters.
 *
 * @package ParisanCore
 */
defined( 'ABSPATH' ) || exit;
function parisan_core_service_catalog_columns( $columns ) {
	$updated = array();
	foreach ( $columns as $key => $label ) {
		$updated[ $key ] = $label;
		if ( 'title' === $key ) {
			$updated['parisan_duration'] = __( 'مدت', 'parisan-core' );
			$updated['parisan_price'] = __( 'قیمت', 'parisan-core' );
			$updated['parisan_staff'] = __( 'متخصصان', 'parisan-core' );
			$updated['parisan_resources'] = __( 'منابع موردنیاز', 'parisan-core' );
		}
	}
	return $updated;
}
add_filter( 'manage_parisan_service_posts_columns', 'parisan_core_service_catalog_columns' );
function parisan_core_service_catalog_column_content( $column, $post_id ) {
	if ( 'parisan_duration' === $column ) {
		$value = absint( get_post_meta( $post_id, '_parisan_duration', true ) );
		echo $value ? esc_html( number_format_i18n( $value ) . ' دقیقه' ) : '<span aria-label="' . esc_attr__( 'مدت تنظیم نشده', 'parisan-core' ) . '">—</span>';
		return;
	}
	if ( 'parisan_price' === $column ) {
		$value = absint( get_post_meta( $post_id, '_parisan_price', true ) );
		$type = get_post_meta( $post_id, '_parisan_price_type', true );
		if ( ! $value ) { echo '<span>' . esc_html__( 'نیازمند تعیین قیمت', 'parisan-core' ) . '</span>'; return; }
		echo esc_html( ( 'from' === $type ? __( 'از ', 'parisan-core' ) : '' ) . number_format_i18n( $value ) . ' تومان' );
		return;
	}
	if ( 'parisan_staff' === $column ) {
		$staff_posts = get_posts( array( 'post_type' => 'parisan_staff', 'post_status' => 'publish', 'posts_per_page' => 300, 'fields' => 'ids', 'no_found_rows' => true ) );
		$count = 0;
		foreach ( $staff_posts as $staff_id ) {
			$services = get_post_meta( $staff_id, '_parisan_staff_services', true );
			if ( is_array( $services ) && in_array( (int) $post_id, array_map( 'absint', $services ), true ) ) { $count++; }
		}
		echo esc_html( number_format_i18n( $count ) . ' ' . __( 'متخصص', 'parisan-core' ) );
		return;
	}
	if ( 'parisan_resources' === $column ) {
		$resource_ids = get_post_meta( $post_id, '_parisan_service_resources', true );
		$resource_ids = is_array( $resource_ids ) ? array_values( array_filter( array_map( 'absint', $resource_ids ) ) ) : array();
		if ( ! $resource_ids ) { echo '<span>' . esc_html__( 'بدون منبع اجباری', 'parisan-core' ) . '</span>'; return; }
		$types = array();
		foreach ( $resource_ids as $resource_id ) {
			$type = get_post_meta( $resource_id, '_parisan_resource_type', true );
			$types[ $type ? $type : 'other' ] = true;
		}
		$labels = array( 'room' => 'اتاق', 'bed' => 'تخت', 'chair' => 'صندلی', 'device' => 'دستگاه', 'other' => 'سایر' );
		$names = array();
		foreach ( array_keys( $types ) as $type ) { $names[] = isset( $labels[ $type ] ) ? $labels[ $type ] : $labels['other']; }
		echo esc_html( implode( ' + ', $names ) );
		return;
	}
}
add_action( 'manage_parisan_service_posts_custom_column', 'parisan_core_service_catalog_column_content', 10, 2 );
function parisan_core_service_catalog_sortable_columns( $columns ) {
	$columns['parisan_duration'] = 'parisan_duration';
	$columns['parisan_price'] = 'parisan_price';
	return $columns;
}
add_filter( 'manage_edit-parisan_service_sortable_columns', 'parisan_core_service_catalog_sortable_columns' );
function parisan_core_service_catalog_orderby( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() || 'parisan_service' !== $query->get( 'post_type' ) ) { return; }
	$orderby = $query->get( 'orderby' );
	if ( in_array( $orderby, array( 'parisan_duration', 'parisan_price' ), true ) ) {
		$query->set( 'meta_key', '_parisan_' . ( 'parisan_duration' === $orderby ? 'duration' : 'price' ) );
		$query->set( 'orderby', 'meta_value_num' );
	}
}
add_action( 'pre_get_posts', 'parisan_core_service_catalog_orderby' );
function parisan_core_service_catalog_assignment_filter( $post_type, $which ) {
	if ( 'parisan_service' !== $post_type ) { return; }
	$selected = isset( $_GET['parisan_assignment'] ) && is_string( $_GET['parisan_assignment'] ) ? sanitize_key( wp_unslash( $_GET['parisan_assignment'] ) ) : '';
	echo '<select name="parisan_assignment"><option value="">' . esc_html__( 'همه خدمات', 'parisan-core' ) . '</option><option value="assigned" ' . selected( $selected, 'assigned', false ) . '>' . esc_html__( 'دارای متخصص', 'parisan-core' ) . '</option><option value="unassigned" ' . selected( $selected, 'unassigned', false ) . '>' . esc_html__( 'بدون متخصص', 'parisan-core' ) . '</option></select>';
}
add_action( 'restrict_manage_posts', 'parisan_core_service_catalog_assignment_filter', 10, 2 );
function parisan_core_service_catalog_apply_assignment_filter( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() || 'parisan_service' !== $query->get( 'post_type' ) ) { return; }
	$filter = isset( $_GET['parisan_assignment'] ) && is_string( $_GET['parisan_assignment'] ) ? sanitize_key( wp_unslash( $_GET['parisan_assignment'] ) ) : '';
	if ( ! in_array( $filter, array( 'assigned', 'unassigned' ), true ) ) { return; }
	$staff = get_posts( array( 'post_type' => 'parisan_staff', 'post_status' => 'publish', 'posts_per_page' => 300, 'fields' => 'ids', 'no_found_rows' => true ) );
	$assigned = array();
	foreach ( $staff as $staff_id ) {
		$services = get_post_meta( $staff_id, '_parisan_staff_services', true );
		if ( is_array( $services ) ) { $assigned = array_merge( $assigned, array_map( 'absint', $services ) ); }
	}
	$assigned = array_values( array_unique( array_filter( $assigned ) ) );
	if ( 'assigned' === $filter ) {
		$query->set( 'post__in', $assigned ? $assigned : array( 0 ) );
	} else {
		$query->set( 'post__not_in', $assigned ? $assigned : array( 0 ) );
	}
}
add_action( 'pre_get_posts', 'parisan_core_service_catalog_apply_assignment_filter', 20 );
