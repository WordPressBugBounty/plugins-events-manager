<?php
namespace EM\Migrate;

/**
 * Source-to-EM id map. One row per migrated source object, which is what makes a migration resumable, re-runnable (delta sync) and reversible (rollback removes only mapped rows).
 */
class Map {

	const DB_VERSION = 1;
	const DB_VERSION_OPTION = 'dbem_migrate_map_db_version';

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'em_migrate_map';
	}

	public static function install() {
		if ( (int) get_option( static::DB_VERSION_OPTION ) >= static::DB_VERSION ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table = static::table();
		$charset = $wpdb->get_charset_collate();
		dbDelta( "CREATE TABLE {$table} (
			map_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			source varchar(40) NOT NULL,
			object_type varchar(40) NOT NULL,
			source_id varchar(100) NOT NULL,
			em_type varchar(40) NOT NULL,
			em_id bigint(20) unsigned NOT NULL DEFAULT 0,
			source_modified datetime NULL DEFAULT NULL,
			data longtext NULL,
			created datetime NOT NULL,
			PRIMARY KEY  (map_id),
			UNIQUE KEY source_object (source,object_type,source_id),
			KEY em_object (em_type,em_id)
		) {$charset};" );
		update_option( static::DB_VERSION_OPTION, static::DB_VERSION, false );
	}

	public static function get( $source, $object_type, $source_id ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . static::table() . ' WHERE source=%s AND object_type=%s AND source_id=%s', $source, $object_type, (string) $source_id ), ARRAY_A );
		if ( $row ) {
			$row['data'] = $row['data'] ? json_decode( $row['data'], true ) : array();
		}
		return $row;
	}

	/**
	 * Like get(), but drops and ignores a row whose EM object was deleted since, so a re-run recreates it instead of skipping it forever.
	 */
	public static function get_live( $source, $object_type, $source_id ) {
		$row = static::get( $source, $object_type, $source_id );
		if ( $row && ! static::em_object_exists( $row ) ) {
			static::delete( $row['map_id'] );
			return null;
		}
		return $row;
	}

	public static function em_object_exists( array $row ) {
		global $wpdb;
		$id = (int) $row['em_id'];
		switch ( $row['em_type'] ) {
			case 'event':
				return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT event_id FROM ' . EM_EVENTS_TABLE . ' WHERE event_id=%d', $id ) );
			case 'location':
				return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT location_id FROM ' . EM_LOCATIONS_TABLE . ' WHERE location_id=%d', $id ) );
			case 'ticket':
				return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT ticket_id FROM ' . EM_TICKETS_TABLE . ' WHERE ticket_id=%d', $id ) );
			case 'booking':
				return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT booking_id FROM ' . EM_BOOKINGS_TABLE . ' WHERE booking_id=%d', $id ) );
			case 'attendee':
				$booking = (int) ( $row['data']['booking_id'] ?? 0 );
				return $booking && $wpdb->get_var( $wpdb->prepare( 'SELECT booking_id FROM ' . EM_BOOKINGS_TABLE . ' WHERE booking_id=%d', $booking ) );
			case 'term':
				return (bool) term_exists( $id, $row['data']['taxonomy'] ?? '' );
		}
		return true;
	}

	public static function get_em_id( $source, $object_type, $source_id ) {
		$row = static::get( $source, $object_type, $source_id );
		return $row ? (int) $row['em_id'] : 0;
	}

	public static function set( $source, $object_type, $source_id, $em_type, $em_id, $data = array(), $source_modified = null ) {
		global $wpdb;
		$existing = static::get( $source, $object_type, $source_id );
		$row = array(
			'em_type' => $em_type,
			'em_id' => (int) $em_id,
			'source_modified' => $source_modified,
			'data' => wp_json_encode( $existing ? array_merge( $existing['data'], $data ) : $data ),
		);
		if ( $existing ) {
			$wpdb->update( static::table(), $row, array( 'map_id' => $existing['map_id'] ) );
			return (int) $existing['map_id'];
		}
		$row += array( 'source' => $source, 'object_type' => $object_type, 'source_id' => (string) $source_id, 'created' => current_time( 'mysql', true ) );
		$wpdb->insert( static::table(), $row );
		return (int) $wpdb->insert_id;
	}

	public static function delete( $map_id ) {
		global $wpdb;
		$wpdb->delete( static::table(), array( 'map_id' => (int) $map_id ) );
	}

	/**
	 * Rows of a source, newest first so rollback removes dependants (bookings, tickets) before what they hang off (events, locations, terms).
	 *
	 * @param string|null $scope Only rows whose source id starts with this, see Source::get_rollback_scope().
	 */
	public static function get_rows( $source, $object_type = null, $limit = 200, $scope = null ) {
		global $wpdb;
		$sql = $wpdb->prepare( 'SELECT * FROM ' . static::table() . ' WHERE source=%s', $source );
		if ( $object_type ) {
			$sql .= $wpdb->prepare( ' AND object_type=%s', $object_type );
		}
		$sql .= static::scope_sql( $scope );
		$sql .= ' ORDER BY map_id DESC LIMIT ' . absint( $limit );
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		foreach ( $rows as &$row ) {
			$row['data'] = $row['data'] ? json_decode( $row['data'], true ) : array();
		}
		return $rows;
	}

	public static function count( $source, $object_type = null, $scope = null ) {
		global $wpdb;
		if ( ! static::exists() ) {
			return 0;
		}
		$sql = $wpdb->prepare( 'SELECT COUNT(*) FROM ' . static::table() . ' WHERE source=%s', $source );
		if ( $object_type ) {
			$sql .= $wpdb->prepare( ' AND object_type=%s', $object_type );
		}
		return (int) $wpdb->get_var( $sql . static::scope_sql( $scope ) );
	}

	public static function counts_by_type( $source, $scope = null ) {
		global $wpdb;
		if ( ! static::exists() ) {
			return array();
		}
		return wp_list_pluck( $wpdb->get_results( $wpdb->prepare( 'SELECT object_type, COUNT(*) AS c FROM ' . static::table() . ' WHERE source=%s', $source ) . static::scope_sql( $scope ) . ' GROUP BY object_type' ), 'c', 'object_type' );
	}

	protected static function scope_sql( $scope ) {
		global $wpdb;
		return null === $scope || '' === $scope ? '' : $wpdb->prepare( ' AND source_id LIKE %s', $wpdb->esc_like( $scope ) . '%' );
	}

	public static function exists() {
		global $wpdb;
		return (int) get_option( static::DB_VERSION_OPTION ) > 0 && $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', static::table() ) ) === static::table();
	}

	/**
	 * Reverse lookup used by redirects: which source object became this EM object.
	 */
	public static function find_by_data( $source, $object_type, $key, $value ) {
		global $wpdb;
		$like = '%' . $wpdb->esc_like( '"' . $key . '":' . wp_json_encode( $value ) ) . '%';
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . static::table() . ' WHERE source=%s AND object_type=%s AND data LIKE %s LIMIT 1', $source, $object_type, $like ), ARRAY_A );
		if ( $row ) {
			$row['data'] = json_decode( $row['data'], true );
		}
		return $row;
	}
}
