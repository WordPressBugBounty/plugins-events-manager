<?php
namespace EM_Event_Locations;

use EM_Exception;

/**
 * Base class for event locations hosted on a video or streaming platform. Providers declare what the platform can do in capabilities() and implement the remote_*() calls; this class decides which remote objects an event needs, queues the calls and keeps them in step with the event.
 *
 * @property string $account  ID of the connected provider account that hosts this event.
 * @property array $settings  Provider settings chosen in the event editor, keyed as in settings_fields().
 * @property bool $public_link Whether the organizer allows the join link in public outputs such as iCal.
 */
abstract class Online_Venue extends \EM\Event\Locations\Virtual {

	public $properties = array('account', 'settings', 'public_link');
	/**
	 * EM_OAuth\OAuth_API subclass used to connect provider accounts, if the provider uses EM_OAuth.
	 * @var string
	 */
	public static $api_class;
	public static $admin_template = '/forms/event/event-locations/online-venue.php';
	protected static $initialized = false;

	public static function init(){
		parent::init();
		if( !static::$initialized ){
			static::$initialized = true;
			Online_Venues::init();
		}
	}

	/**
	 * Provider capabilities, merged over the defaults in get_capabilities().
	 * @return array
	 */
	protected static function capabilities(){
		return array();
	}

	/**
	 * @return array {
	 *     @type string     $mode                'meeting' or 'broadcast'.
	 *     @type bool       $registrants         Each attendee gets their own join link.
	 *     @type bool       $registrant_approval Registrants can be held for approval on the provider.
	 *     @type string|false $access_list       Identity the provider admits without a lobby: 'email', 'google' or 'entra'.
	 *     @type string|false $embed             'iframe', 'sdk' or 'acs' when the event can be joined inside the site.
	 *     @type bool       $embed_needs_app     Embedding requires the site's own provider app or billing.
	 *     @type bool       $cohosts             Co-hosts can be assigned.
	 *     @type bool       $native_recurrence   The provider can hold a recurring schedule on one remote object.
	 *     @type bool       $shared_room         One remote object can serve every occurrence of a series.
	 *     @type array      $webhooks            Provider events the site can receive.
	 *     @type int        $webhook_ttl         Seconds a webhook subscription lasts, 0 if it does not expire.
	 *     @type string|false $attendance        'pull' or 'push' when attendance can be read back.
	 *     @type string|null $attendance_identity What attendance reports identify people by: 'email' or 'opaque'.
	 *     @type bool       $recordings          Recordings can be read back.
	 *     @type int        $max_schedule_ahead  Seconds before start a remote object can be created, 0 for no limit.
	 *     @type array      $rate_limit          Remote writes allowed: array( 'requests' => int, 'per' => seconds ).
	 *     @type bool       $public_only         Remote objects are always publicly visible on the provider.
	 * }
	 */
	public static function get_capabilities(){
		$defaults = array(
			'mode' => 'meeting',
			'registrants' => false,
			'registrant_approval' => false,
			'access_list' => false,
			'embed' => false,
			'embed_needs_app' => false,
			'cohosts' => false,
			'native_recurrence' => false,
			'shared_room' => false,
			'webhooks' => array(),
			'webhook_ttl' => 0,
			'attendance' => false,
			'attendance_identity' => null,
			'recordings' => false,
			'max_schedule_ahead' => 0,
			'rate_limit' => array(),
			'public_only' => false,
		);
		return apply_filters('em_online_venue_capabilities', array_merge( $defaults, static::capabilities() ), static::$type);
	}

	public static function supports( $capability ){
		$capabilities = static::get_capabilities();
		return !empty( $capabilities[ $capability ] );
	}

	/**
	 * Creates the remote object for $session, filling in remote_id, join_url, remote_status and any provider data.
	 * @param Online_Session $session
	 * @throws EM_Exception
	 */
	abstract public function remote_create( $session );

	/**
	 * @param Online_Session $session
	 * @throws EM_Exception
	 */
	abstract public function remote_update( $session );

	/**
	 * Deletes or cancels the remote object. Runs from the queue, possibly after the event itself has been deleted, so it may only rely on $session.
	 * @param Online_Session $session
	 * @throws EM_Exception
	 */
	abstract public function remote_delete( $session );

	/**
	 * Reads the remote object back and updates $session, such as its status or join URL.
	 * @param Online_Session $session
	 * @throws EM_Exception
	 */
	public function remote_sync( $session ){
	}

	/**
	 * Called when a booking gains access, for providers with registrants or access lists.
	 * @param \EM_Booking $EM_Booking
	 * @param Online_Session $session
	 * @throws EM_Exception
	 */
	public function register_booking( $EM_Booking, $session ){
	}

	/**
	 * Called when a booking loses access or is deleted.
	 * @param \EM_Booking $EM_Booking
	 * @param Online_Session $session
	 * @throws EM_Exception
	 */
	public function unregister_booking( $EM_Booking, $session ){
	}

	/**
	 * Provider settings shown in the event editor.
	 * @return array Keyed by setting name, each with 'type' (select, checkbox or text), 'label', 'default', and 'options' for selects.
	 */
	public static function settings_fields(){
		$fields = array();
		if( static::supports('shared_room') ){
			$fields['per_occurrence'] = array(
				'type' => 'checkbox',
				'label' => __('Give each date of a recurring event its own meeting link', 'events-manager'),
				'default' => false,
			);
		}
		return $fields;
	}

	/**
	 * Connected provider accounts that can host events.
	 * @return array Account ID => label.
	 */
	public static function get_accounts(){
		$accounts = array();
		if( static::$api_class ){
			\EM_Loader::oauth();
			$api = static::$api_class;
			foreach( $api::get_site_tokens() as $account_id => $token ){
				$label = !empty($token['email']) ? $token['email'] : ( !empty($token['name']) ? $token['name'] : $account_id );
				$accounts[ $account_id ] = $label;
			}
		}
		return apply_filters('em_online_venue_accounts', $accounts, static::$type);
	}

	/**
	 * @param string $account
	 * @return \EM_OAuth\OAuth_API_Client
	 * @throws EM_Exception
	 */
	public static function get_client( $account = '' ){
		\EM_Loader::oauth();
		$api = static::$api_class;
		if( !$api ) throw new EM_Exception( sprintf( 'No API class is defined for %s.', static::$type ) );
		// the token store falls back to the first connected account when the requested one is missing, which would host the event on someone else's account
		if( $account === '' || !array_key_exists( $account, $api::get_site_tokens() ) ){
			throw new EM_Exception( sprintf( __('The %s account that hosts this event is no longer connected. Reconnect it or choose another account for the event.', 'events-manager'), static::get_label() ), 'account_disconnected' );
		}
		return $api::get_client( 0, $account );
	}

	public function get_setting( $name ){
		$settings = is_array( $this->settings ) ? $this->settings : array();
		if( array_key_exists( $name, $settings ) ) return $settings[ $name ];
		$fields = static::settings_fields();
		return isset( $fields[ $name ]['default'] ) ? $fields[ $name ]['default'] : null;
	}

	public function get_post(){
		$previous = $this->data;
		$result = parent::get_post();
		$post = isset( $_POST[ 'event_location_' . static::$type ] ) && is_array( $_POST[ 'event_location_' . static::$type ] ) ? wp_unslash( $_POST[ 'event_location_' . static::$type ] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- EM_Event::get_post() runs behind the event form's nonce
		$accounts = static::get_accounts();
		$form_shown = !empty( $post['fields'] );
		if( !empty( $post['account'] ) && isset( $accounts[ $post['account'] ] ) ){
			$this->data['account'] = (string) $post['account'];
		}elseif( !empty( $previous['account'] ) && isset( $accounts[ $previous['account'] ] ) ){
			$this->data['account'] = (string) $previous['account'];
		}elseif( count( $accounts ) === 1 ){
			$this->data['account'] = (string) key( $accounts );
		}
		$settings = array();
		$posted = isset( $post['settings'] ) && is_array( $post['settings'] ) ? $post['settings'] : array();
		foreach( static::settings_fields() as $name => $field ){
			$value = isset( $posted[ $name ] ) ? $posted[ $name ] : null;
			if( !$form_shown && $value === null ){
				// forms that never rendered these fields, such as custom submission templates, keep what was saved rather than reading every checkbox as unticked
				$settings[ $name ] = isset( $previous['settings'][ $name ] ) ? $previous['settings'][ $name ] : ( isset( $field['default'] ) ? $field['default'] : null );
			}elseif( $field['type'] === 'checkbox' ){
				$settings[ $name ] = !empty( $value );
			}elseif( $field['type'] === 'select' ){
				$settings[ $name ] = $value !== null && array_key_exists( (string) $value, $field['options'] ) ? (string) $value : $field['default'];
			}elseif( $value !== null ){
				$settings[ $name ] = sanitize_text_field( (string) $value );
			}
		}
		$this->data['settings'] = $settings;
		$this->data['public_link'] = static::supports('public_only') || !empty( $post['public_link'] ) || ( !$form_shown && !empty( $previous['public_link'] ) );
		return apply_filters('em_online_venue_get_post', $result, $this);
	}

	public function validate(){
		$result = true;
		$accounts = static::get_accounts();
		if( empty( $accounts ) ){
			$this->event->add_error( sprintf( __('Connect a %s account in the Events Manager settings before using it as an event location.', 'events-manager'), static::get_label() ) );
			$result = false;
		}elseif( empty( $this->data['account'] ) || !isset( $accounts[ $this->data['account'] ] ) ){
			$this->event->add_error( sprintf( __('Choose which %s account hosts this event.', 'events-manager'), static::get_label() ) );
			$result = false;
		}
		return apply_filters('em_online_venue_validate', $result, $this);
	}

	/**
	 * Postless recurrences report their parent's post ID, so writing location meta from one would overwrite the series' settings; their remote objects live in the sessions table instead.
	 */
	public function save(){
		$EM_Event = $this->event;
		if( $EM_Event->is_recurrence() ){
			$result = true;
		}else{
			$result = parent::save();
		}
		if( $result && $EM_Event->event_id ){
			$this->plan( true );
		}
		return apply_filters('em_online_venue_save', $result, $this);
	}

	public function delete(){
		$EM_Event = $this->event;
		if( $EM_Event && $EM_Event->event_id ){
			$sessions = Online_Session::get_for_event( $EM_Event->event_id );
			if( $EM_Event->is_recurring( true ) ){
				$sessions = array_merge( $sessions, Online_Session::get_for_series( $EM_Event->event_id ) );
			}
			foreach( $sessions as $session ){
				if( $session->location_type !== static::$type ) continue;
				$session->queue('delete');
				$session->save();
				if( apply_filters('em_online_venue_process_inline', !$EM_Event->is_recurring( true ) && !$EM_Event->is_recurrence( true ), $session, $this) ){
					Online_Venues::process( $session );
				}
			}
			Online_Venues::schedule_queue();
		}
		if( $EM_Event && $EM_Event->is_recurrence() ){
			$this->data = array();
			do_action('em_event_location_deleted', $this);
			return apply_filters('em_event_location_delete', true, $this);
		}
		return parent::delete();
	}

	/**
	 * How many remote objects this event needs: 'single' for one event or a lone occurrence, 'shared' for one room serving a whole series, 'occurrences' for one per date of a series.
	 * @return string
	 */
	public function get_session_strategy(){
		$shared = static::supports('shared_room') && !$this->get_setting('per_occurrence');
		if( $this->event->is_recurring( true ) ){
			$strategy = $shared ? 'shared' : 'occurrences';
		}elseif( $this->event->is_recurrence( true ) ){
			$strategy = $shared ? 'series' : 'occurrence';
		}else{
			$strategy = 'single';
		}
		return apply_filters('em_online_venue_session_strategy', $strategy, $this);
	}

	/**
	 * Queues whatever remote objects this event needs, running the call straight away for a single event or shared room when $inline.
	 * Provider failures are shown as an alert rather than an event error, because an event error sends a published event back to draft.
	 * @param bool $inline
	 */
	public function plan( $inline = false ){
		$EM_Event = $this->event;
		$strategy = $this->get_session_strategy();
		if( $strategy === 'series' ) return;
		if( $strategy === 'occurrence' ){
			$EM_Recurring = $EM_Event->get_recurring_event();
			$this->plan_session( $EM_Event, $EM_Recurring && $EM_Recurring->event_id != $EM_Event->event_id ? $EM_Recurring->event_id : null );
			return;
		}
		if( $strategy === 'occurrences' ){
			if( !$inline ) Online_Venues::plan_occurrences( $EM_Event, Online_Venues::get_occurrence_ids( $EM_Event ) );
			return;
		}
		$session = $this->plan_session( $EM_Event, $strategy === 'shared' ? $EM_Event->event_id : null );
		if( $strategy === 'shared' ){
			Online_Venues::queue_series_deletes( $EM_Event, static::$type, array( $EM_Event->event_id ) );
		}
		if( $inline && $session && $session->is_due() && apply_filters('em_online_venue_process_inline', true, $session, $this) ){
			$processed = Online_Venues::process( $session, $this );
			if( is_wp_error( $processed ) && $processed->get_error_code() !== 'em_online_venue_busy' ){
				global $EM_Notices;
				if( $EM_Notices instanceof \EM_Notices ){
					$EM_Notices->add_alert( sprintf( __('%1$s could not be updated yet, and Events Manager will keep retrying in the background: %2$s', 'events-manager'), static::get_label(), $processed->get_error_message() ), true );
				}
				do_action('em_online_venue_inline_error', $processed, $session, $this);
			}
		}
	}

	/**
	 * Whether remote objects should exist for $EM_Event yet. Drafts and events awaiting review are not created on the provider.
	 * @param \EM_Event $EM_Event
	 * @return bool
	 */
	public function should_create( $EM_Event ){
		$status = $EM_Event->event_status;
		if( (int) $status !== 1 && $EM_Event->is_recurrence( true ) ){
			$EM_Recurring = $EM_Event->get_recurring_event();
			if( $EM_Recurring ) $status = $EM_Recurring->event_status;
		}
		return apply_filters('em_online_venue_should_create', (int) $status === 1, $EM_Event, $this);
	}

	/**
	 * A fingerprint of everything the provider receives for $EM_Event, so remote updates only happen when something it uses has changed.
	 * @param \EM_Event $EM_Event
	 * @return string
	 */
	public function get_fingerprint( $EM_Event ){
		$parts = array(
			'name' => $EM_Event->event_name,
			'start' => $EM_Event->start( true )->getTimestamp(),
			'end' => $EM_Event->end( true )->getTimestamp(),
			'timezone' => $EM_Event->get_timezone()->getName(),
			'settings' => $this->settings,
			'public_link' => (bool) $this->public_link,
		);
		return md5( wp_json_encode( apply_filters('em_online_venue_fingerprint', $parts, $EM_Event, $this) ) );
	}

	/**
	 * Makes sure $EM_Event has a session queued for whatever the provider needs to match it.
	 * @param \EM_Event $EM_Event
	 * @param int|null $series_id
	 * @return Online_Session|null
	 */
	public function plan_session( $EM_Event, $series_id = null ){
		$session = Online_Session::get( $EM_Event->event_id, static::$type );
		if( $session && $session->remote_id && $session->account !== (string) $this->account ){
			$session->queue('delete');
			$session->save_columns( array( 'queue', 'attempts', 'last_error', 'not_before' ) );
			$session = null;
		}
		if( !$session ){
			if( !$this->should_create( $EM_Event ) ) return null;
			$session = new Online_Session( array(
				'event_id' => $EM_Event->event_id,
				'series_id' => $series_id,
				'location_type' => static::$type,
				'account' => (string) $this->account,
			));
		}
		if( !$session->remote_id ){
			// a create still waiting in the queue follows the event, which may have moved in time or to another account
			$session->account = (string) $this->account;
			$session->queue( 'create', $this->get_create_not_before( $EM_Event ) );
		}elseif( $session->get_data('fingerprint') !== $this->get_fingerprint( $EM_Event ) ){
			$session->queue('update');
		}
		$session->series_id = $series_id;
		if( $session->session_id ){
			// only the queue columns, so a worker that is creating or syncing this session at the same moment keeps the remote ID it writes
			$session->save_columns( array( 'queue', 'attempts', 'last_error', 'not_before', 'account', 'series_id' ) );
		}else{
			$session->save();
		}
		if( $session->queue ) Online_Venues::schedule_queue( $session->not_before ? strtotime( $session->not_before . ' UTC' ) : time() );
		return $session;
	}

	/**
	 * @param \EM_Event $EM_Event
	 * @return int|null UTC timestamp, or null to create straight away.
	 */
	protected function get_create_not_before( $EM_Event ){
		$capabilities = static::get_capabilities();
		if( !empty( $capabilities['max_schedule_ahead'] ) ){
			$earliest = $EM_Event->start( true )->getTimestamp() - $capabilities['max_schedule_ahead'] + HOUR_IN_SECONDS;
			if( $earliest > time() ) return $earliest;
		}
		return null;
	}

	/**
	 * The session serving this event: its own, or the series' shared room for an occurrence.
	 * @return Online_Session|null
	 */
	public function get_session(){
		if( !$this->event || !$this->event->event_id ) return null;
		$session = Online_Session::get( $this->event->event_id, static::$type );
		if( !$session && $this->event->is_recurrence( true ) ){
			$EM_Recurring = $this->event->get_recurring_event();
			if( $EM_Recurring && $EM_Recurring->event_id && $EM_Recurring->event_id != $this->event->event_id ){
				$session = Online_Session::get( $EM_Recurring->event_id, static::$type );
			}
		}
		return apply_filters('em_online_venue_get_session', $session, $this);
	}

	public function resolve_join_url( $EM_Booking ){
		$session = $this->get_session();
		$url = $session && $session->remote_id ? (string) $session->join_url : '';
		return apply_filters('em_event_location_resolve_join_url', $url, $EM_Booking, $this);
	}

	protected function link_shown_setting(){
		return static::supports('public_only') || !empty($this->data['public_link']);
	}

	public function get_public_url(){
		$url = '';
		if( $this->is_link_public() && !$this->is_link_hidden() ){
			$session = $this->get_session();
			if( $session && $session->remote_id ) $url = (string) $session->join_url;
		}
		return apply_filters('em_event_location_public_url', $url, $this);
	}

	/**
	 * URL loaded in an iframe by the join page when the provider's embed is 'iframe'.
	 * @param Online_Session $session
	 * @return string
	 */
	public function get_embed_url( $session ){
		return '';
	}

	public function output_join_page( $EM_Booking ){
		if( static::get_capabilities()['embed'] !== 'iframe' || !$this->get_setting('embed') ) return false;
		$session = $this->get_session();
		$embed_url = $session ? $this->get_embed_url( $session ) : '';
		if( !$embed_url ) return false;
		em_locate_template('templates/event-location-embed.php', true, array( 'EM_Event' => $this->event, 'EM_Booking' => $EM_Booking, 'embed_url' => $embed_url, 'EM_Event_Location' => $this ));
		return true;
	}

	public function get_ical_location(){
		$url = $this->get_public_url();
		return $url ? $url : false;
	}

	public function output( $what = null, $target = null ){
		if( $what === null ){
			$url = $this->get_public_url();
			if( $url ){
				return '<a href="' . esc_url( $url ) . '" target="_blank">' . esc_html( static::get_label() ) . '</a>';
			}
			if( $this->is_link_public() && $this->is_link_hidden() ){
				return '<span class="em-event-location-hidden">' . esc_html( $this->get_link_hidden_text() ) . '</span>';
			}
			return esc_html( static::get_label() );
		}elseif( $what === 'url' ){
			return esc_url( $this->get_public_url() );
		}elseif( $what === 'status' ){
			$session = $this->get_session();
			return $session ? esc_html( $session->remote_status ) : '';
		}
		return parent::output( $what, $target );
	}

	public function get_admin_column(){
		$column = '<strong>' . esc_html( static::get_label() ) . '</strong>';
		$session = $this->get_session();
		if( $session && $session->last_error ){
			$column .= '<br><span class="em-online-venue-error">' . esc_html__('Not in sync with the provider', 'events-manager') . '</span>';
		}
		return $column;
	}

	public function admin_delete_warning(){
		echo '<p>' . esc_html( sprintf( __('Changing the location of this event will delete its %s meeting or broadcast, and anyone holding the old link will no longer be able to join.', 'events-manager'), static::get_label() ) ) . '</p>';
	}

	/**
	 * Only what is safe for any API consumer: account IDs, settings and provider data stay private.
	 */
	public function to_api(){
		$api = array(
			'type' => static::$type,
			'url' => $this->get_public_url(),
		);
		return apply_filters('em_online_venue_to_api', $api, $this);
	}

	public static function load_admin_template(){
		em_locate_template( static::$admin_template, true, array( 'EM_Event_Location_Class' => get_called_class() ) );
	}
}
