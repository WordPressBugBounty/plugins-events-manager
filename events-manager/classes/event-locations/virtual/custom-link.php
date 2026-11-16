<?php
namespace EM\Event\Locations;

/**
 * A link attendees follow to join a virtual event hosted anywhere, such as a livestream or a meeting on a platform without its own integration.
 *
 * @property string url     Where attendees join.
 * @property string text    The link text.
 * @property int private    1 when an event that takes bookings only gives the link to bookings with virtual access, through their join link.
 */
class Custom_Link extends Virtual {

	public static $type = 'custom_link';
	public static $admin_template = '/forms/event/event-locations/custom-link.php';
	public static $always_enabled = true;

	public $properties = array('url', 'text', 'private');

	public function get_post(){
		$private = $this->private ? 1 : null;
		$return = parent::get_post();
		if( !empty($_POST['event_location_custom_link_url']) ){
			$this->data['url'] = esc_url_raw( wp_unslash($_POST['event_location_custom_link_url']) );
		}
		if( !empty($_POST['event_location_custom_link_text']) ){
			$this->data['text'] = sanitize_text_field( wp_unslash($_POST['event_location_custom_link_text']) );
		}
		// an unticked checkbox posts nothing, so the editor marks its form and anything else (API partial updates, older templates) keeps the saved setting
		if( !empty($_POST['event_location_custom_link_private_form']) || isset($_POST['event_location_custom_link_private']) ){
			$private = !empty($_POST['event_location_custom_link_private']) ? 1 : null;
		}
		$this->data['private'] = $private;
		return apply_filters('em_event_location_custom_link_get_post', $return, $this);
	}

	public function validate(){
		$result = parent::validate();
		if( empty($this->data['url']) ){
			$this->event->add_error( __('Please enter a valid URL for this event location.', 'events-manager') );
			$result = false;
		}
		if( empty($this->data['text']) ){
			$this->event->add_error( __('Please provide some link text for this event location URL.', 'events-manager') );
			$result = false;
		}
		return apply_filters('em_event_location_custom_link_validate', $result, $this);
	}

	public static function get_label( $label_type = 'singular' ){
		if( $label_type === 'plural' ){
			return esc_html__('Custom Links', 'events-manager');
		}
		return esc_html__('Custom Link', 'events-manager');
	}

	public function get_link( $new_target = true ){
		return '<a href="'.esc_url($this->url).'">'. esc_html($this->text).'</a>';
	}

	public function get_admin_column() {
		return '<strong>'. static::get_label() . ' - ' . $this->get_link().'</strong>';
	}

	protected function link_shown_setting(){
		return empty($this->data['private']);
	}

	/**
	 * Whether the URL is kept off public output and only reached through each booking's join link.
	 * @return bool
	 */
	public function is_private(){
		return !$this->is_link_public();
	}

	public function output( $what = null, $target = null ){
		if( $this->is_private() && in_array($what, array(null, '_self', '_parent', '_top', 'url'), true) ){
			return $this->output_private( $what, $target );
		}
		if( $this->is_link_hidden() && in_array($what, array(null, '_self', '_parent', '_top', 'url'), true) ){
			return $this->output_hidden( $what, $target );
		}
		if( $what === null ){
			return '<a href="'. esc_url($this->url) .'" target="_blank">'. esc_html($this->text) .'</a>';
		}elseif( $what === '_self' ){
			return '<a href="'. esc_url($this->url) .'">'. esc_html($this->text) .'</a>';
		}elseif( $what === '_parent' || $what === '_top' ){
			return '<a href="'. esc_url($this->url) .'" target="'.$what.'">'. esc_html($this->text) .'</a>';
		}elseif( $what === 'url' ){
			return esc_url($this->url);
		}
		return parent::output($what);
	}

	/**
	 * Output for a private link: the logged-in viewer's own join link when one of their bookings has virtual access, otherwise the link text alone.
	 * @param string|null $what
	 * @param string|null $target
	 * @return string
	 */
	protected function output_private( $what, $target ){
		$join_url = '';
		if( !in_array($target, array('email', 'raw', 'ical', 'rss'), true) ){
			$EM_Booking = $this->get_accessible_booking();
			$join_url = $EM_Booking ? $this->get_join_url( $EM_Booking ) : '';
		}
		if( $join_url && $this->is_link_hidden() ){
			return $this->output_hidden( $what, $target );
		}
		if( $what === 'url' ){
			$output = $join_url ? esc_url($join_url) : '';
		}elseif( $join_url ){
			$link_target = $what === null ? ' target="_blank"' : ( $what === '_self' ? '' : ' target="'. $what .'"' );
			$output = '<a href="'. esc_url($join_url) .'"'. $link_target .'>'. esc_html($this->text) .'</a>';
		}elseif( in_array($target, array('raw', 'ical', 'rss'), true) || ( $target === 'email' && !em_get_option('dbem_smtp_html') ) ){
			$output = esc_html($this->text);
		}else{
			$output = '<span class="em-event-location-private">'. esc_html( $this->get_private_note() ) .'</span>';
		}
		return apply_filters('em_event_location_custom_link_output_private', $output, $what, $target, $join_url, $this);
	}

	/**
	 * Output while the link is held back until shortly before the event: the hidden text in place of the link, and nothing where only a URL fits.
	 * @param string|null $what
	 * @param string|null $target
	 * @return string
	 */
	protected function output_hidden( $what, $target ){
		if( $what === 'url' ){
			$output = '';
		}elseif( in_array($target, array('raw', 'ical', 'rss'), true) || ( $target === 'email' && !em_get_option('dbem_smtp_html') ) ){
			$output = esc_html( $this->get_link_hidden_text() );
		}else{
			$output = '<span class="em-event-location-hidden">'. esc_html( $this->get_link_hidden_text() ) .'</span>';
		}
		return apply_filters('em_event_location_custom_link_output_hidden', $output, $what, $target, $this);
	}

	/**
	 * The link text with a note on why this viewer has no join link: no booking yet, a booking still to be confirmed, or a booking with no virtual access.
	 * @return string
	 */
	protected function get_private_note(){
		$note = __('%s (the link is provided after booking)', 'events-manager');
		$user_id = get_current_user_id();
		if( $user_id && $this->event && !empty($this->event->event_id) ){
			global $wpdb;
			$sql = $wpdb->prepare('SELECT booking_id FROM '.EM_BOOKINGS_TABLE.' WHERE event_id = %d AND person_id = %d AND booking_status NOT IN (2,3)', $this->event->event_id, $user_id);
			if( !empty($this->event->timeslot_id) ){
				$sql .= $wpdb->prepare(' AND timeslot_id = %d', $this->event->timeslot_id);
			}
			foreach( $wpdb->get_col( $sql ) as $booking_id ){
				if( em_get_booking( $booking_id )->grants_access('online') ){
					$note = __('%s (the link is provided once your booking is confirmed)', 'events-manager');
					break;
				}
				$note = __('%s (your booking does not include virtual access)', 'events-manager');
			}
		}
		return sprintf( $note, $this->text );
	}

	public function get_ical_location(){
		return $this->is_private() || $this->is_link_hidden() ? false : $this->url;
	}

	public function get_public_url(){
		return apply_filters('em_event_location_public_url', $this->is_private() || $this->is_link_hidden() ? '' : (string) $this->url, $this);
	}

	public function get_public_data(){
		$data = array_intersect_key( $this->data, array('text' => 1, 'private' => 1) );
		$url = $this->get_public_url();
		if( $url !== '' ) $data['url'] = $url;
		return apply_filters('em_event_location_public_data', $data, $this);
	}

	public function to_api(){
		$api = parent::to_api();
		$api['private'] = $this->is_private();
		return $api;
	}

	public function resolve_join_url( $EM_Booking ){
		return apply_filters('em_event_location_resolve_join_url', (string) $this->url, $EM_Booking, $this);
	}
}
Custom_Link::init();
