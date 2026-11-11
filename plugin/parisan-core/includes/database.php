<?php
/**
 * Versioned database schema for transactional salon operations.
 *
 * @package ParisanCore
 */

defined( 'ABSPATH' ) || exit;

define( 'PARISAN_CORE_DB_VERSION', 6 );

/**
 * Install or upgrade Parisan's additive database schema.
 *
 * This function never deletes tables or records. Deactivation and theme
 * switching do not invoke data removal.
 *
 * @return void
 */
function parisan_core_install_database() {
	global $wpdb;

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset_collate = $wpdb->get_charset_collate();
	$bookings_table  = $wpdb->prefix . 'parisan_bookings';
	$events_table    = $wpdb->prefix . 'parisan_booking_events';
	$exceptions_table = $wpdb->prefix . 'parisan_schedule_exceptions';
	$customer_tokens_table = $wpdb->prefix . 'parisan_customer_tokens';

	$bookings_sql = "CREATE TABLE {$bookings_table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		public_id char(36) NOT NULL,
		service_id bigint(20) unsigned NOT NULL,
		staff_id bigint(20) unsigned NOT NULL,
		resource_id bigint(20) unsigned NOT NULL DEFAULT 0,
		customer_user_id bigint(20) unsigned NULL DEFAULT NULL,
		customer_name varchar(190) NOT NULL,
		customer_phone varchar(40) NOT NULL,
		customer_email varchar(190) NOT NULL DEFAULT '',
		starts_at_utc datetime NOT NULL,
		ends_at_utc datetime NOT NULL,
		duration_minutes smallint(5) unsigned NOT NULL DEFAULT 0,
		salon_timezone varchar(64) NOT NULL,
		status varchar(24) NOT NULL DEFAULT 'pending',
		price_amount bigint(20) unsigned NOT NULL DEFAULT 0,
		currency_code char(3) NOT NULL DEFAULT 'IRR',
		idempotency_key char(64) NOT NULL,
		created_at_utc datetime NOT NULL,
		updated_at_utc datetime NOT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY public_id (public_id),
		UNIQUE KEY idempotency_key (idempotency_key),
		KEY staff_starts_at (staff_id, starts_at_utc),
		KEY resource_starts_at (resource_id, starts_at_utc),
		KEY customer_user_id (customer_user_id),
		KEY status_starts_at (status, starts_at_utc)
	) ENGINE=InnoDB {$charset_collate};";

	$events_sql = "CREATE TABLE {$events_table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		booking_id bigint(20) unsigned NOT NULL,
		actor_user_id bigint(20) unsigned NULL DEFAULT NULL,
		event_type varchar(40) NOT NULL,
		from_status varchar(24) NOT NULL DEFAULT '',
		to_status varchar(24) NOT NULL DEFAULT '',
		event_note text NULL,
		created_at_utc datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY booking_id (booking_id),
		KEY actor_user_id (actor_user_id),
		KEY event_type (event_type)
	) ENGINE=InnoDB {$charset_collate};";

	$exceptions_sql = "CREATE TABLE {$exceptions_table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		staff_id bigint(20) unsigned NOT NULL,
		starts_at_utc datetime NOT NULL,
		ends_at_utc datetime NOT NULL,
		exception_type varchar(24) NOT NULL DEFAULT 'unavailable',
		note varchar(255) NOT NULL DEFAULT '',
		created_at_utc datetime NOT NULL,
		PRIMARY KEY  (id),
		KEY staff_starts_at (staff_id, starts_at_utc),
		KEY starts_at_utc (starts_at_utc),
		KEY exception_type (exception_type)
	) ENGINE=InnoDB {$charset_collate};";

	dbDelta( $bookings_sql );
	dbDelta( $events_sql );
	dbDelta( $exceptions_sql );

	$customer_tokens_sql = "CREATE TABLE {$customer_tokens_table} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		booking_id bigint(20) unsigned NOT NULL,
		token_hash char(64) NOT NULL,
		expires_at_utc datetime NOT NULL,
		revoked_at_utc datetime NULL DEFAULT NULL,
		created_at_utc datetime NOT NULL,
		last_used_at_utc datetime NULL DEFAULT NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY token_hash (token_hash),
		KEY booking_id (booking_id),
		KEY expires_at_utc (expires_at_utc)
	) ENGINE=InnoDB {$charset_collate};";
	dbDelta( $customer_tokens_sql );


	$booking_resources_table = $wpdb->prefix . 'parisan_booking_resources';
	$booking_resources_sql = "CREATE TABLE {$booking_resources_table} (
		booking_id bigint(20) unsigned NOT NULL,
		resource_id bigint(20) unsigned NOT NULL,
		PRIMARY KEY  (booking_id,resource_id),
		KEY resource_booking (resource_id,booking_id)
	) ENGINE=InnoDB {$charset_collate};";
	dbDelta( $booking_resources_sql );

	$finance_entries_table = $wpdb->prefix . 'parisan_finance_entries';
	$finance_entries_sql = "CREATE TABLE {$finance_entries_table} (\n\t\tid bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n\t\tentry_type varchar(12) NOT NULL,\n\t\tcategory varchar(100) NOT NULL,\n\t\tdescription text NOT NULL,\n\t\tamount_irr bigint(20) unsigned NOT NULL,\n\t\tstaff_id bigint(20) unsigned NOT NULL DEFAULT 0,\n\t\toccurred_at_utc datetime NOT NULL,\n\t\tcreated_by bigint(20) unsigned NOT NULL,\n\t\tcreated_at_utc datetime NOT NULL,\n\t\tPRIMARY KEY  (id),\n\t\tKEY type_occurred (entry_type,occurred_at_utc),\n\t\tKEY staff_occurred (staff_id,occurred_at_utc),\n\t\tKEY created_by (created_by)\n\t) ENGINE=InnoDB {$charset_collate};";
\tdbDelta( $finance_entries_sql );

	$contact_inquiries_table = $wpdb->prefix . 'parisan_contact_inquiries';
	$contact_inquiries_sql = "CREATE TABLE {$contact_inquiries_table} (\n\t\tid bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n\t\tcustomer_name varchar(120) NOT NULL,\n\t\tcustomer_phone varchar(24) NOT NULL,\n\t\tcustomer_email varchar(190) NOT NULL DEFAULT '',\n\t\tsubject_key varchar(24) NOT NULL,\n\t\tmessage_body text NOT NULL,\n\t\tstatus varchar(20) NOT NULL DEFAULT 'new',\n\t\tcreated_at_utc datetime NOT NULL,\n\t\tupdated_at_utc datetime NULL DEFAULT NULL,\n\t\tPRIMARY KEY  (id),\n\t\tKEY status_created (status,created_at_utc),\n\t\tKEY phone_created (customer_phone,created_at_utc)\n\t) ENGINE=InnoDB {$charset_collate};";
\tdbDelta( $contact_inquiries_sql );

	update_option( 'parisan_core_db_version', PARISAN_CORE_DB_VERSION, false );
}

/**
 * Apply additive schema updates when an older plugin version is loaded.
 *
 * @return void
 */
function parisan_core_maybe_upgrade_database() {
	$installed_version = (int) get_option( 'parisan_core_db_version', 0 );

	if ( $installed_version < PARISAN_CORE_DB_VERSION ) {
		parisan_core_install_database();
	}
}
add_action( 'plugins_loaded', 'parisan_core_maybe_upgrade_database', 5 );
