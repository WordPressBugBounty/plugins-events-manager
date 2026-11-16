<?php
namespace EM_Event_Locations;
/**
 * Handles registration and retrival of EM_Event_Location child classes for use with events.
 * Class EM_Event_Locations
 */
class Event_Locations {
	
	/**
	 * Associative array with type key => class name.
	 * @var array
	 */
	private static $types = array();
	
	/**
	 * @return array[EM_Event_Location::]
	 */
	public static function get_types(){
		return static::$types;
	}
	
	/**
	 * @param string $type
	 * @param string $classname
	 * @return bool
	 */
	public static function register( $type, $classname ){
		self::$types[$type] = $classname;
		return true;
	}
	
	/**
	 * @param string $type
	 * @return bool
	 */
	public static function unregister( $type ){
		if( !empty(self::$types[$type]) ){
			unset(self::$types[$type]);
			return true;
		}
		return false;
	}
	
	/**
	 * @param string $type
	 * @param \EM_Event $EM_Event
	 * @return bool
	 */
	public static function get( $type = null, $EM_Event = null ){
		if( !empty(self::$types[$type]) && class_exists(self::$types[$type]) ){
			$location_type = self::$types[$type];
			return new $location_type( $EM_Event );
		}
		return false;
	}
	
	/**
	 * Returns whether or not the supplied event location $type is enabled for use.
	 * @param string $type
	 * @return bool
	 */
	public static function is_enabled( $type ){
		if( empty(self::$types[$type]) || !class_exists(self::$types[$type]) ) return false;
		$Event_Location_Class = self::$types[$type];
		$location_types = em_get_option('dbem_location_types', array());
		if( $Event_Location_Class::is_virtual() ){
			// virtual types are switched on and off together, and some are always available while virtual locations are on
			if( !static::is_virtual_enabled() ) return false;
			if( !empty($Event_Location_Class::$always_enabled) ) return true;
		}
		return !empty($location_types[$type]);
	}

	/**
	 * Whether virtual locations are switched on, which hybrid events, ticket access and the virtual search filter all rest on.
	 * @return bool
	 */
	public static function is_virtual_enabled(){
		$location_types = em_get_option('dbem_location_types', array());
		return apply_filters('em_event_locations_virtual_enabled', !empty($location_types['virtual']));
	}

	/**
	 * Registered virtual types, keyed by type, whether or not they are enabled.
	 * @return array
	 */
	public static function get_virtual_types(){
		$types = array();
		foreach( self::$types as $type => $Event_Location_Class ){
			if( class_exists($Event_Location_Class) && $Event_Location_Class::is_virtual() ){
				$types[$type] = $Event_Location_Class;
			}
		}
		return $types;
	}

	/**
	 * Whether a type key belongs to a virtual type, including the keys of older virtual types that are not registered yet.
	 * @param string $type
	 * @return bool
	 */
	public static function is_virtual_type( $type ){
		if( !empty(self::$types[$type]) && class_exists(self::$types[$type]) ){
			$Event_Location_Class = self::$types[$type];
			return $Event_Location_Class::is_virtual();
		}
		return in_array( $type, Event_Location::LEGACY_VIRTUAL_TYPES, true );
	}

	/**
	 * Switches virtual locations on when a virtual type is newly enabled by code unaware of the switch, such as a Zoom plugin enabling its types when activated, so the type is not left hidden behind it.
	 * @param mixed $value
	 * @param mixed $old_value
	 * @return mixed
	 */
	public static function switch_on_virtual_for_new_types( $value, $old_value ){
		// a value that sets the switch itself, or a save from the settings page, is a deliberate choice
		if( !is_array($value) || array_key_exists('virtual', $value) || !empty($_POST['em-submitted']) ) return $value;
		$old_value = is_array($old_value) ? $old_value : array();
		foreach( $value as $type => $enabled ){
			if( empty($enabled) || !empty($old_value[$type]) || !static::is_virtual_type($type) ) continue;
			// an always available type has no checkbox of its own, so its key says nothing about wanting virtual locations
			$Event_Location_Class = !empty(self::$types[$type]) ? self::$types[$type] : null;
			if( $Event_Location_Class && !empty($Event_Location_Class::$always_enabled) ) continue;
			$value['virtual'] = 1;
			break;
		}
		return $value;
	}

}
add_filter('pre_update_option_dbem_location_types', array(Event_Locations::class, 'switch_on_virtual_for_new_types'), 10, 2);
require('em-event-location.php');