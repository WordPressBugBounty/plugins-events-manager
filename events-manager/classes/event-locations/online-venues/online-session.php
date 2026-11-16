<?php
namespace EM_Event_Locations;

/**
 * One remote object on a provider (a meeting, space or broadcast) serving one event, or every occurrence of a series when the provider can share a room.
 * Stored per event_id rather than in postmeta because postless recurrences read their parent's postmeta.
 *
 * @property-read int $session_id
 */
class Online_Session {

	public $session_id = 0;
	public $event_id = 0;
	public $series_id = null;
	public $location_type = '';
	public $account = '';
	public $remote_id = null;
	public $join_url = '';
	public $remote_status = '';
	/**
	 * Pending remote action: 'create', 'update' or 'delete', or null when the remote object matches the event.
	 * @var string|null
	 */
	public $queue = null;
	public $attempts = 0;
	/**
	 * Earliest UTC datetime the queued action may run, used for provider scheduling limits and retry backoff.
	 * @var string|null
	 */
	public $not_before = null;
	public $last_error = '';
	public $synced_at = null;
	public $created_at = null;
	public $updated_at = null;
	protected $data = array();

	protected static $columns = array(
		'event_id' => '%d',
		'series_id' => '%d',
		'location_type' => '%s',
		'account' => '%s',
		'remote_id' => '%s',
		'join_url' => '%s',
		'remote_status' => '%s',
		'queue' => '%s',
		'attempts' => '%d',
		'not_before' => '%s',
		'data' => '%s',
		'last_error' => '%s',
		'synced_at' => '%s',
		'created_at' => '%s',
		'updated_at' => '%s',
	);

	public function __construct( $row = array() ){
		foreach( (array) $row as $key => $value ){
			if( $key === 'data' ){
				$this->data = is_array($value) ? $value : ( json_decode( (string) $value, true ) ?: array() );
			}elseif( $key === 'session_id' || $key === 'event_id' || $key === 'attempts' ){
				$this->$key = (int) $value;
			}elseif( $key === 'series_id' ){
				$this->series_id = $value ? (int) $value : null;
			}elseif( property_exists( $this, $key ) ){
				$this->$key = $value;
			}
		}
	}

	public function get_data( $key = null, $default = null ){
		if( $key === null ) return $this->data;
		return array_key_exists( $key, $this->data ) ? $this->data[ $key ] : $default;
	}

	public function set_data( $key, $value ){
		if( $value === null ){
			unset( $this->data[ $key ] );
		}else{
			$this->data[ $key ] = $value;
		}
	}

	public function queue( $action, $not_before = null ){
		if( $action === 'update' && $this->queue === 'create' ) return;
		$this->queue = $action;
		$this->attempts = 0;
		$this->last_error = '';
		$this->not_before = $not_before ? gmdate( 'Y-m-d H:i:s', $not_before ) : null;
	}

	public function is_due(){
		return $this->queue && ( empty( $this->not_before ) || strtotime( $this->not_before . ' UTC' ) <= time() );
	}

	public function save(){
		global $wpdb;
		$now = gmdate('Y-m-d H:i:s');
		$this->updated_at = $now;
		if( !$this->created_at ) $this->created_at = $now;
		$row = $formats = array();
		foreach( static::$columns as $column => $format ){
			$value = $column === 'data' ? wp_json_encode( $this->data ) : $this->$column;
			if( $value === null || ( $value === '' && in_array( $column, array( 'remote_id', 'queue', 'not_before', 'synced_at', 'series_id' ), true ) ) ){
				$row[ $column ] = null;
				$formats[] = '%s';
			}else{
				$row[ $column ] = $value;
				$formats[] = $format;
			}
		}
		if( $this->session_id ){
			$result = $wpdb->update( EM_ONLINE_SESSIONS_TABLE, $row, array( 'session_id' => $this->session_id ), $formats, array( '%d' ) );
		}else{
			$result = $wpdb->insert( EM_ONLINE_SESSIONS_TABLE, $row, $formats );
			if( $result ) $this->session_id = (int) $wpdb->insert_id;
		}
		return $result !== false;
	}

	/**
	 * Writes only $columns, for callers that must not overwrite what a concurrent worker writes to the rest of the row.
	 * @param string[] $columns
	 * @return bool
	 */
	public function save_columns( $columns ){
		global $wpdb;
		if( !$this->session_id ) return $this->save();
		$this->updated_at = gmdate('Y-m-d H:i:s');
		$columns[] = 'updated_at';
		$row = $formats = array();
		foreach( array_unique( $columns ) as $column ){
			if( empty( static::$columns[ $column ] ) ) continue;
			$value = $column === 'data' ? wp_json_encode( $this->data ) : $this->$column;
			$nullable = in_array( $column, array( 'remote_id', 'queue', 'not_before', 'synced_at', 'series_id' ), true );
			$row[ $column ] = ( $value === null || ( $value === '' && $nullable ) ) ? null : $value;
			$formats[] = $row[ $column ] === null ? '%s' : static::$columns[ $column ];
		}
		return $wpdb->update( EM_ONLINE_SESSIONS_TABLE, $row, array( 'session_id' => $this->session_id ), $formats, array( '%d' ) ) !== false;
	}

	public function delete(){
		global $wpdb;
		if( !$this->session_id ) return false;
		return $wpdb->delete( EM_ONLINE_SESSIONS_TABLE, array( 'session_id' => $this->session_id ), array( '%d' ) ) !== false;
	}

	/**
	 * The session a given event uses for $location_type, ignoring sessions being torn down.
	 * @param int $event_id
	 * @param string $location_type
	 * @return Online_Session|null
	 */
	public static function get( $event_id, $location_type ){
		global $wpdb;
		$sql = $wpdb->prepare( 'SELECT * FROM ' . EM_ONLINE_SESSIONS_TABLE . " WHERE event_id=%d AND location_type=%s AND ( queue IS NULL OR queue != 'delete' ) ORDER BY session_id DESC LIMIT 1", $event_id, $location_type );
		$row = $wpdb->get_row( $sql, ARRAY_A );
		return $row ? new static( $row ) : null;
	}

	/**
	 * @return Online_Session[]
	 */
	public static function get_for_event( $event_id ){
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . EM_ONLINE_SESSIONS_TABLE . ' WHERE event_id=%d ORDER BY session_id', $event_id ), ARRAY_A );
		return array_map( function( $row ){ return new static( $row ); }, $rows );
	}

	/**
	 * @return Online_Session[]
	 */
	public static function get_for_series( $series_id, $location_type = null ){
		global $wpdb;
		$sql = 'SELECT * FROM ' . EM_ONLINE_SESSIONS_TABLE . ' WHERE series_id=%d';
		$args = array( $series_id );
		if( $location_type ){
			$sql .= ' AND location_type=%s';
			$args[] = $location_type;
		}
		$rows = $wpdb->get_results( $wpdb->prepare( $sql . ' ORDER BY session_id', $args ), ARRAY_A );
		return array_map( function( $row ){ return new static( $row ); }, $rows );
	}

	/**
	 * Finds the session for a provider's own ID, as needed when a webhook arrives.
	 * @return Online_Session|null
	 */
	public static function find_by_remote( $location_type, $remote_id ){
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . EM_ONLINE_SESSIONS_TABLE . ' WHERE location_type=%s AND remote_id=%s', $location_type, $remote_id ), ARRAY_A );
		return $row ? new static( $row ) : null;
	}

	/**
	 * @return Online_Session[]
	 */
	public static function get_due( $limit = 50, $max_attempts = 8 ){
		global $wpdb;
		$sql = $wpdb->prepare( 'SELECT * FROM ' . EM_ONLINE_SESSIONS_TABLE . ' WHERE queue IS NOT NULL AND attempts < %d AND ( not_before IS NULL OR not_before <= %s ) ORDER BY not_before IS NULL DESC, not_before, session_id LIMIT %d', $max_attempts, gmdate('Y-m-d H:i:s'), $limit );
		return array_map( function( $row ){ return new static( $row ); }, $wpdb->get_results( $sql, ARRAY_A ) );
	}

	/**
	 * Sessions with a remote object whose event runs between $from and $to, for polling providers.
	 * @return Online_Session[]
	 */
	public static function get_syncable( $from, $to, $limit = 100 ){
		global $wpdb;
		$sql = $wpdb->prepare( 'SELECT s.* FROM ' . EM_ONLINE_SESSIONS_TABLE . ' s INNER JOIN ' . EM_EVENTS_TABLE . ' e ON e.event_id = s.event_id WHERE s.remote_id IS NOT NULL AND s.queue IS NULL AND e.event_end >= %s AND e.event_start <= %s ORDER BY s.synced_at IS NULL DESC, s.synced_at LIMIT %d', gmdate( 'Y-m-d H:i:s', $from ), gmdate( 'Y-m-d H:i:s', $to ), $limit );
		return array_map( function( $row ){ return new static( $row ); }, $wpdb->get_results( $sql, ARRAY_A ) );
	}
}
