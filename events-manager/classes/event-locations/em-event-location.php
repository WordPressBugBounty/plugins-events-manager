<?php
namespace EM_Event_Locations;
/**
 * Class EM_Event_Location
 * This class is to be extended by any event location type. The only time this class is not used when not extended is if
 * @property-read string $type
 * @property array $data
 * @property string $event
 */
class Event_Location {
	/**
	 * @var \EM_Event
	 */
	protected $event;
	/**
	 * The type name of this location type, used to store in the database. Use alphanmeric characters (a-z, A-Z, 0-9), dashes and underscores only.
	 * @var string
	 */
	public static $type;
	/**
	 * Represents shortcut property names for an event location child object that can be accessed safely which then refers to the EM_Event object.
	 * This is a map of property names as keys to custom field names in the event_attributes array property of EM_Event.
	 * Naming conventions should follow the lines of lowercase event_location_{static::$type}(_$property_name), for example a url would have event_location_url as a url, and event_location_url_text for link text
	 * @var array
	 */
	public $properties = array();
	/**
	 * Contains associative array of data of this event location. Data is accessed via getters and setters and keys must correspond to values in the $properties property.
	 * @var array
	 */
	protected $data = array();
	/**
	 * The admin template path, if there is one.
	 * @var string
	 * @see EM_Event_Location::load_admin_template()
	 */
	public static $admin_template;
	/**
	 * True when everything this type gives a booking online goes through can_access() or get_online_ticket_bookings(), such as attendee registration, join links and recordings, so it can be the online half of a hybrid event. Types written before hybrid events leave it false, as they treat every booking as an online attendee.
	 * @var bool
	 */
	public static $supports_ticket_access = false;
	
	public static function init(){
		Event_Locations::register( static::$type, get_called_class() );
	}
	
	/**
	 * EM_Event_Location constructor.
	 * @param \EM_Event $EM_Event
	 */
	public function __construct( $EM_Event ) {
		if( is_object($EM_Event) && property_exists($EM_Event, 'event_id') ){ // we check for the event_id property in case we're dealing with an extended class
			$this->event = $EM_Event;
		}
	}
	
	/**
	 * @param $name
	 * @return string|null|array
	 */
	public function __get( $name ) {
		if( $name == 'type' ){
			return static::$type;
		}elseif( $name == 'event' ){
			return $this->event;
		}elseif( $name == 'data' ){
			return $this->data;
		}elseif( in_array($name, $this->properties) && array_key_exists($name, $this->data) ){
			return $this->data[$name];
		}
		return null;
	}
	
	public function __set($name, $value) {
		if( $name === 'event' && is_object($value) && property_exists($value, 'event_id') ){ // we check for the event_id property in case we're dealing with an extended class
			$this->event = $value;
		}elseif( $name == 'data' && is_array($value) ){
			$this->data = $value;
		}elseif( in_array($name, $this->properties) ){
			$this->data[$name] = $value;
		}
	}
	
	public function __isset($name) {
		if( in_array($name, $this->properties) ){
			return isset($this->data[$name]);
		}
		return false;
	}
	
	/**
	 * @param array $event_meta     Array of event meta, if not supplied meta is obtained from linked event object.
	 * @param bool $reload          Reloads post meta again into object, by default previously loaded data will be re-used.
	 */
	public function load_postdata( $event_meta = array(), $reload = false ){
		if( empty($event_meta) ) $event_meta = $this->event->get_event_meta();
		$base_key = '_event_location_'.static::$type;
		foreach( $event_meta as $event_meta_key => $event_meta_val ){
			if( $event_meta_key == $base_key ){
				// in case we have something like _event_location_url which is the actual URL of a Event_Location_URL object/type.
				$this->data[static::$type] = ( is_array($event_meta_val) ) ? $event_meta_val[0]:$event_meta_val;
				$this->data[static::$type] = \EM_Object::maybe_unserialize($this->data[static::$type]);
			}elseif( substr($event_meta_key, 0, strlen($base_key) ) == $base_key ){
				// event location data is placed directly into the event_location_data array and referenced via get_event_location()
				$key = str_replace('_event_location_'.static::$type.'_', '', $event_meta_key);
				$this->data[$key] = ( is_array($event_meta_val) ) ? $event_meta_val[0]:$event_meta_val;
				$this->data[$key] = \EM_Object::maybe_unserialize($this->data[$key]);
			}
		}
		do_action('em_event_location_load_postdata', $this);
	}
	
	/**
	 * @param array $post
	 * @return boolean
	 */
	public function get_post(){
		$this->data = array();
		return apply_filters('em_event_location_get_post', true, $this);
	}
	
	/**
	 * @return boolean
	 */
	public function validate(){
		return apply_filters('em_event_location_validate', false, $this);
	}
	
	public function save(){
		if( $this->owns_post() && static::$type !== null ){
			$this->reset_data();
			foreach( $this->properties as $prop ){
				$meta_key = $prop == static::$type ? '_event_location_'.$prop : '_event_location_'.static::$type.'_'.$prop;
				if( $this->$prop !== null ){
					update_post_meta( $this->event->post_id, $meta_key, $this->$prop );
				}else{
					delete_post_meta( $this->event->post_id, $meta_key );
				}
			}
		}
		return apply_filters('em_event_location_save', true, $this);
	}
	
	public function delete(){
		$this->reset_data();
		$this->data = array();
		do_action('em_event_location_deleted', $this);
		return apply_filters('em_event_location_delete', true, $this);
	}
	
	/**
	 * Whether the event's post holds this location's meta. Postless recurrences report their recurring event's post ID, so writing or deleting meta for one would change the whole series.
	 * @return bool
	 */
	protected function owns_post(){
		return $this->event && is_numeric($this->event->post_id) && $this->event->post_id > 0 && !$this->event->is_recurrence();
	}

	/**
	 * Deletes stored information about this location type in the database
	 * @return int|false
	 */
	final function reset_data(){
		if( $this->owns_post() ){
			global $wpdb;
			$base_key = '_event_location_' . $this->type;
			$result = $wpdb->query( $wpdb->prepare('DELETE FROM '.$wpdb->postmeta." WHERE post_id=%d AND ( meta_key = %s OR meta_key LIKE %s ) AND meta_key != '_event_location_type'", $this->event->post_id, $base_key, $wpdb->esc_like( $base_key . '_' ) . '%') );
			wp_cache_delete( $this->event->post_id, 'post_meta' ); //refresh cache to prevent looking at old data
			return $result;
		}
		return false;
	}
	
	public function get_admin_column(){
		return $this->get_label('singular');
	}
	
	/**
	 * Returns whether or not this event location is enabled for use.
	 * @return bool
	 */
	public static function is_enabled(){
		return Event_Locations::is_enabled( static::$type );
	}

	/**
	 * Type keys of virtual types written before the Virtual base class, such as the free Zoom plugin's.
	 */
	const LEGACY_VIRTUAL_TYPES = array('zoom_meeting', 'zoom_webinar', 'zoom_room');

	/**
	 * Whether attendees join this type over the internet, which groups it under Virtual in settings and the editor. Types written before the Virtual base class, such as the free Zoom plugin's, are recognized by their type key.
	 * @return bool
	 */
	public static function is_virtual(){
		$virtual = in_array( static::$type, self::LEGACY_VIRTUAL_TYPES, true );
		return apply_filters('em_event_location_is_virtual', $virtual, get_called_class());
	}
	
	/**
	 * Loads admin template automatically if static $admin_template is set to a valid path in templates folder.
	 * Classes with custom forms outside of template folders can override this function and provide their own HTML that will go in the loop of event location type forms.
	 */
	public static function load_admin_template(){
		if( static::$admin_template ){
			em_locate_template( static::$admin_template, true );
		}
	}
	
	/**
	 * Outputs additional warning info to user if they're switching event location types, this may include additional information about what may happen to event location data.
	 * For example, webinars on third party platforms may get deleted too, which deserves a mention and should be marked up with surrounding p tags if necessary.
	 */
	public function admin_delete_warning(){
		echo '';
	}
	
	public static function get_label( $label_type = 'siingular' ){
		//override and return plural name.
		return static::$type;
	}
	
	public function output( $what = null, $target = null ){
		if( $what !== null && $what !== 'type' ){
			return esc_html($this->$what);
		}else{
			return static::get_label();
		}
	}
	
	public function get_ical_location(){
		return false;
	}

	/**
	 * Ticket bookings in $EM_Booking whose ticket grants online access, for providers that register or invite each attendee.
	 * @param \EM_Booking $EM_Booking
	 * @return \EM_Ticket_Booking[] Keyed by "ticket_id-ticket_booking_id".
	 */
	public function get_online_ticket_bookings( $EM_Booking ){
		$ticket_bookings = array();
		foreach( $EM_Booking->get_tickets_bookings()->tickets_bookings as $EM_Ticket_Bookings ){
			foreach( $EM_Ticket_Bookings as $EM_Ticket_Booking ){
				if( !method_exists($EM_Ticket_Booking, 'grants_access') || $EM_Ticket_Booking->grants_access('online') ){
					$ticket_bookings[ $EM_Ticket_Bookings->ticket_id . '-' . $EM_Ticket_Booking->ticket_booking_id ] = $EM_Ticket_Booking;
				}
			}
		}
		return apply_filters('em_event_location_online_ticket_bookings', $ticket_bookings, $EM_Booking, $this);
	}

	/**
	 * Whether $EM_Booking may join this event location, checked before printing join links, sending reminders or signing embed tokens.
	 * @param \EM_Booking $EM_Booking
	 * @return bool
	 */
	public function can_access( $EM_Booking ){
		$can_access = false;
		if( $EM_Booking instanceof \EM_Booking && $EM_Booking->booking_id ){
			$statuses = apply_filters('em_event_location_access_statuses', array(1), $this, $EM_Booking);
			$can_access = in_array( (int) $EM_Booking->booking_status, array_map('intval', (array) $statuses), true );
			if( $can_access && method_exists($EM_Booking, 'grants_access') ){
				$can_access = (bool) $EM_Booking->grants_access('online');
			}
		}
		return apply_filters('em_event_location_can_access', $can_access, $EM_Booking, $this);
	}

	/**
	 * The first booking a user holds for this event that can_access() lets in, so pages can show that user their own join link or recording.
	 * @param int|null $user_id Defaults to the current user.
	 * @return \EM_Booking|null
	 */
	public function get_accessible_booking( $user_id = null ){
		$user_id = $user_id === null ? get_current_user_id() : absint($user_id);
		$EM_Booking = null;
		if( $user_id && $this->event && !empty($this->event->event_id) ){
			global $wpdb;
			$sql = $wpdb->prepare('SELECT booking_id FROM '.EM_BOOKINGS_TABLE.' WHERE event_id = %d AND person_id = %d AND booking_status NOT IN (2,3)', $this->event->event_id, $user_id);
			if( !empty($this->event->timeslot_id) ){
				$sql .= $wpdb->prepare(' AND timeslot_id = %d', $this->event->timeslot_id);
			}
			foreach( $wpdb->get_col( $sql . ' ORDER BY booking_id' ) as $booking_id ){
				$booking = em_get_booking( $booking_id );
				if( $this->can_access( $booking ) ){
					$EM_Booking = $booking;
					break;
				}
			}
		}
		return apply_filters('em_event_location_accessible_booking', $EM_Booking, $user_id, $this);
	}

	/**
	 * A URL for this event location that is safe to publish in public feeds such as iCal and schema.org, or an empty string.
	 * @return string
	 */
	public function get_public_url(){
		return apply_filters('em_event_location_public_url', '', $this);
	}

	/**
	 * The provider's own hard attendee cap, such as a webinar seat licence, or null when it has none.
	 * @return int|null
	 */
	public function get_capacity_limit(){
		return apply_filters('em_event_location_capacity_limit', null, $this);
	}

	/**
	 * A join link for $EM_Booking that is safe to email, which checks access again when followed.
	 * @param \EM_Booking $EM_Booking
	 * @return string
	 */
	public function get_join_url( $EM_Booking = null ){
		$url = '';
		if( $EM_Booking && static::supports_join() && $this->can_access($EM_Booking) ){
			$url = Join_Gate::get_url( $this->event, $EM_Booking );
		}
		return apply_filters('em_event_location_join_url', $url, $EM_Booking, $this);
	}

	/**
	 * Where the join gate sends $EM_Booking once it has been authorized, such as the meeting URL or an attendee's own registrant link.
	 * @param \EM_Booking $EM_Booking
	 * @return string
	 */
	public function resolve_join_url( $EM_Booking ){
		return apply_filters('em_event_location_resolve_join_url', '', $EM_Booking, $this);
	}

	/**
	 * Outputs an embedded join page for an authorized $EM_Booking instead of redirecting, returning false when this location has no embed.
	 * @param \EM_Booking $EM_Booking
	 * @return bool
	 */
	public function output_join_page( $EM_Booking ){
		return false;
	}

	/**
	 * Whether this type can send a booking on to its online location through the join gate, which needs resolve_join_url() or output_join_page().
	 * @return bool
	 */
	public static function supports_join(){
		$supports = false;
		foreach( array('resolve_join_url', 'output_join_page') as $method ){
			$reflection = new \ReflectionMethod( get_called_class(), $method );
			if( $reflection->getDeclaringClass()->getName() !== __CLASS__ ){
				$supports = true;
			}
		}
		return apply_filters('em_event_location_supports_join', $supports, get_called_class());
	}

	/**
	 * Whether this type can be the virtual half of a hybrid event, see $supports_ticket_access.
	 * @return bool
	 */
	public static function supports_ticket_access(){
		return apply_filters('em_event_location_supports_ticket_access', !empty(static::$supports_ticket_access), get_called_class());
	}

	/**
	 * When the join gate lets bookings through, as UTC timestamps.
	 * @return array Keys 'opens' and 'closes'.
	 */
	public function get_join_window(){
		$window = array(
			'opens' => $this->get_join_opens(),
			'closes' => $this->event->end()->getTimestamp() + 2 * HOUR_IN_SECONDS,
		);
		return apply_filters('em_event_location_join_window', $window, $this);
	}

	/**
	 * @return int UTC timestamp
	 */
	protected function get_join_opens(){
		return $this->event->start()->getTimestamp() - 15 * MINUTE_IN_SECONDS;
	}

	/**
	 * The stored data anyone who can see the event may be given, as opposed to what only those who can edit it should get. Types name their public fields by overriding this; without that, only a URL that is safe to publish is public.
	 * @return array
	 */
	public function get_public_data(){
		$data = array();
		$url = $this->get_public_url();
		if( $url !== '' ) $data['url'] = $url;
		return apply_filters('em_event_location_public_data', $data, $this);
	}

	/**
	 * Whether the current user gets this location's stored data in API output, rather than only its public data.
	 * @return bool
	 */
	public function can_read_private_data(){
		$can_read = false;
		if( $this->event ){
			// can_manage() records an error on the event when it refuses, which a read should not leave behind
			$errors = $this->event->errors;
			$can_read = $this->event->can_manage('edit_events', 'edit_others_events');
			$this->event->errors = $errors;
		}
		return apply_filters('em_event_location_can_read_private_data', $can_read, $this);
	}

	public function to_api(){
		$data = $this->can_read_private_data() ? $this->data : $this->get_public_data();
		return array_merge( $data, array(
			'type' => static::$type,
		));
	}
}

include('em-event-location-join-gate.php');
include('virtual/virtual.php');
include('virtual/custom-link.php');
include('em-event-location-online-venue.php');
include('online-venues/online-session.php');
include('online-venues/online-venues.php');
//include default Event Locations
include('em-event-location-url.php');