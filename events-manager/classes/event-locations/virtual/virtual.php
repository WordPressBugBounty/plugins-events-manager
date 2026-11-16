<?php
namespace EM\Event\Locations;

use EM_Event_Locations\Event_Location;

/**
 * Base for event locations attendees join over the internet, such as a custom link, Zoom or a streaming platform.
 *
 * Virtual types are switched on and off together by the Virtual setting under Location Types, can be the virtual half of a hybrid event, and send bookings through the join gate so only bookings with virtual access get in.
 */
abstract class Virtual extends Event_Location {

	public static $supports_ticket_access = true;

	/**
	 * Types that are always available while virtual locations are enabled, so they get no checkbox of their own in settings.
	 * @var bool
	 */
	public static $always_enabled = false;

	public static function is_virtual(){
		return apply_filters('em_event_location_is_virtual', true, get_called_class());
	}

	/**
	 * Whether the organizer chose to show the link publicly on an event that takes bookings.
	 * @return bool
	 */
	protected function link_shown_setting(){
		return false;
	}

	/**
	 * Whether the link can appear on public pages and feeds. An event without bookings has nobody to hand a private link to, so its link is always public.
	 * @return bool
	 */
	public function is_link_public(){
		$public = !$this->takes_bookings() || $this->link_shown_setting();
		return apply_filters('em_event_location_virtual_link_public', $public, $this);
	}

	/**
	 * @return bool
	 */
	public function takes_bookings(){
		return $this->event && em_get_option('dbem_rsvp_enabled') && !empty($this->event->event_rsvp);
	}

	/**
	 * The Hidden Links setting: 0 when links are never held back, 1 when every event hides its link until the default time unless it says otherwise, 2 when an event only hides its link once it is given a time.
	 * @return int
	 */
	public static function get_link_hiding_mode(){
		$mode = absint( em_get_option('dbem_virtual_links_hidden', 2) );
		return (int) apply_filters('em_event_location_virtual_link_hiding_mode', $mode > 2 ? 0 : $mode);
	}

	/**
	 * Whether an event can set its own time and text, rather than every event using the defaults in settings.
	 * @return bool
	 */
	public static function can_events_hide_links(){
		return static::get_link_hiding_mode() > 0 && (bool) em_get_option('dbem_virtual_links_hidden_event', 1);
	}

	/**
	 * How many minutes before the event starts its link is shown, or 0 when the link is not held back.
	 * @return int
	 */
	public function get_link_hide_minutes(){
		$minutes = 0;
		$mode = static::get_link_hiding_mode();
		if( $mode && $this->event ){
			$own = $this->event->event_virtual_hide_minutes;
			if( static::can_events_hide_links() && $own !== null && $own !== '' ){
				$minutes = absint($own);
			}elseif( $mode === 1 ){
				$minutes = absint( em_get_option('dbem_virtual_links_hidden_minutes', 15) );
			}
		}
		return (int) apply_filters('em_event_location_virtual_link_hide_minutes', $minutes, $this);
	}

	/**
	 * When the link is shown, as a UTC timestamp, or 0 when it is not held back.
	 * @return int
	 */
	public function get_link_visible_from(){
		$minutes = $this->get_link_hide_minutes();
		return $minutes > 0 ? $this->event->start()->getTimestamp() - $minutes * MINUTE_IN_SECONDS : 0;
	}

	/**
	 * Whether the link is still being held back, from public output and from the bookings that will get it.
	 * @return bool
	 */
	public function is_link_hidden(){
		$visible_from = $this->get_link_visible_from();
		return (bool) apply_filters('em_event_location_virtual_link_hidden', $visible_from && time() < $visible_from, $this);
	}

	/**
	 * What is shown in place of the link while it is held back: the event's own text, else the default in settings, with %s replaced by the number of minutes.
	 * @return string
	 */
	public function get_link_hidden_text(){
		$text = '';
		if( $this->event && static::can_events_hide_links() ){
			$text = trim( (string) $this->event->event_virtual_hide_text );
		}
		if( $text === '' ){
			$text = trim( (string) em_get_option('dbem_virtual_links_hidden_text') );
		}
		if( $text === '' ){
			$text = static::get_default_link_hidden_text();
		}
		$text = str_replace( '%s', (string) $this->get_link_hide_minutes(), $text );
		return apply_filters('em_event_location_virtual_link_hidden_text', $text, $this);
	}

	/**
	 * @return string
	 */
	public static function get_default_link_hidden_text(){
		return __('The link will be shown %s minutes before the event starts.', 'events-manager');
	}

	protected function get_join_opens(){
		return $this->get_link_visible_from() ?: parent::get_join_opens();
	}

	public function to_api(){
		$api = parent::to_api();
		$visible_from = $this->get_link_visible_from();
		$api['hide_minutes'] = $this->get_link_hide_minutes();
		$api['visible_from'] = $visible_from ? gmdate( 'Y-m-d\\TH:i:s\\Z', $visible_from ) : null;
		return $api;
	}
}
