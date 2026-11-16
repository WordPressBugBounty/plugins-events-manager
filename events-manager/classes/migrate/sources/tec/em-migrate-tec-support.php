<?php
namespace EM\Migrate\Sources\TEC;

use EM\Migrate\Map;

/**
 * TEC's venue editor saves the full English country name ("United States"), imports often save an ISO code, and EM wants the ISO code.
 */
class Countries {

	const ALIASES = array(
		'USA' => 'US', 'U.S.A.' => 'US', 'U.S.' => 'US', 'UNITED STATES OF AMERICA' => 'US', 'AMERICA' => 'US',
		'UK' => 'GB', 'U.K.' => 'GB', 'GREAT BRITAIN' => 'GB', 'ENGLAND' => 'GB', 'SCOTLAND' => 'GB', 'WALES' => 'GB', 'NORTHERN IRELAND' => 'GB',
		'SOUTH KOREA' => 'KR', 'KOREA' => 'KR', 'KOREA, REPUBLIC OF' => 'KR', 'RUSSIA' => 'RU', 'RUSSIAN FEDERATION' => 'RU',
		'VIETNAM' => 'VN', 'VIET NAM' => 'VN', 'IRAN' => 'IR', 'SYRIA' => 'SY', 'LAOS' => 'LA', 'BOLIVIA' => 'BO', 'VENEZUELA' => 'VE',
		'TANZANIA' => 'TZ', 'MOLDOVA' => 'MD', 'MACEDONIA' => 'MK', 'NORTH MACEDONIA' => 'MK', 'CZECH REPUBLIC' => 'CZ', 'CZECHIA' => 'CZ',
		'IVORY COAST' => 'CI', "COTE D'IVOIRE" => 'CI', 'HOLLAND' => 'NL', 'THE NETHERLANDS' => 'NL', 'UAE' => 'AE', 'TURKEY' => 'TR', 'TÜRKIYE' => 'TR',
		'VATICAN' => 'VA', 'HONG KONG SAR' => 'HK', 'TAIWAN, PROVINCE OF CHINA' => 'TW', 'PALESTINE' => 'PS', 'BRUNEI' => 'BN', 'CAPE VERDE' => 'CV',
	);

	public static function to_code( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		$countries = em_get_countries();
		$upper = strtoupper( $value );
		if ( 2 === strlen( $value ) && isset( $countries[ $upper ] ) ) {
			return $upper;
		}
		if ( isset( static::ALIASES[ $upper ] ) ) {
			return static::ALIASES[ $upper ];
		}
		foreach ( array( $countries, static::english() ) as $list ) {
			foreach ( $list as $code => $name ) {
				if ( strtoupper( $name ) === $upper || strtoupper( remove_accents( $name ) ) === strtoupper( remove_accents( $value ) ) ) {
					return $code;
				}
			}
		}
		return '';
	}

	protected static function english() {
		static $list = null;
		if ( null === $list ) {
			$file = EM_DIR . '/includes/i18n/countries-en.php';
			$list = file_exists( $file ) ? (array) include $file : array();
		}
		return $list;
	}
}

/**
 * Rewrites TEC shortcodes and blocks in ordinary posts and pages to Events Manager ones.
 */
class Content {

	/**
	 * TEC's single event blocks. Events Manager's event page and the migrated details block already show all of this, and TEC renders them against an EM post with the wrong date or empty, or leaves empty wrappers once deactivated.
	 */
	const EVENT_BLOCKS = array( 'event-datetime', 'event-price', 'event-organizer', 'event-venue', 'event-website', 'event-links', 'event-category', 'event-tags', 'featured-image', 'classic-event-details', 'related-events', 'virtual-event', 'tickets', 'tickets-item', 'rsvp', 'attendees' );

	/**
	 * Removes TEC's single event blocks (and Pro's additional field blocks) from an event description, leaving every other block as it is.
	 */
	public static function strip_event_blocks( $content ) {
		$content = (string) $content;
		if ( false === strpos( $content, '<!-- wp:tribe/' ) ) {
			return $content;
		}
		$names = implode( '|', array_map( 'preg_quote', static::EVENT_BLOCKS ) ) . '|field-[a-z0-9_-]+';
		$stripped = preg_replace( '#\s*<!-- wp:tribe/(' . $names . ')(?:\s+\{.*?\})?\s*(?:/-->|-->.*?<!-- /wp:tribe/\1 -->)#s', '', $content );
		return null === $stripped || $stripped === $content ? $content : trim( $stripped );
	}

	public static function convert( $content, TEC $tec ) {
		// blocks that are only a TEC calendar or list become an EM shortcode block; block wrappers around TEC shortcodes stay
		$content = preg_replace_callback( '#<!-- wp:(tribe|tec)/([a-z0-9-]+)(\s+(\{.*?\}))?\s*/?-->(.*?<!-- /wp:\1/\2 -->)?#s', function( $m ) {
			$attrs = ! empty( $m[4] ) ? json_decode( $m[4], true ) : array();
			$shortcode = static::block_to_shortcode( $m[1] . '/' . $m[2], (array) $attrs );
			if ( null === $shortcode ) {
				return $m[0];
			}
			return "<!-- wp:shortcode -->\n" . $shortcode . "\n<!-- /wp:shortcode -->";
		}, $content );
		$content = preg_replace_callback( '/\[(tribe_events|tribe_events_list|tribe_mini_calendar|tribe_this_week|tribe_featured_venue|tribe_event_countdown|tribe_event_inline)(\s[^\]]*)?\](?:(.*?)\[\/\1\])?/s', function( $m ) use ( $tec ) {
			$atts = shortcode_parse_atts( isset( $m[2] ) ? trim( $m[2] ) : '' );
			$atts = is_array( $atts ) ? $atts : array();
			return static::shortcode( $m[1], $atts, $m[3] ?? '', $tec );
		}, $content );
		return $content;
	}

	protected static function block_to_shortcode( $block, array $attrs ) {
		switch ( $block ) {
			case 'tribe/events-list':
			case 'tribe/widget-events-list':
				return '[events_list limit="' . (int) ( $attrs['limit'] ?? 5 ) . '"]';
			case 'tribe/events-calendar':
			case 'tribe/widget-events-month':
			case 'tec/archive-events':
				return '[events_calendar]';
			case 'tribe/widget-events-week':
				return '[events_list scope="week"]';
		}
		return null;
	}

	protected static function shortcode( $tag, array $atts, $inner, TEC $tec ) {
		$args = array();
		foreach ( array( 'category' => 'category', 'tag' => 'tag' ) as $from => $to ) {
			if ( ! empty( $atts[ $from ] ) ) {
				$args[ $to ] = static::map_terms( $atts[ $from ], 'tribe_events_cat' === $from || 'category' === $from ? 'category' : 'tag', $to );
			}
		}
		if ( ! empty( $atts['venue'] ) ) {
			$location = Map::get_em_id( TEC::KEY, 'venue', (int) $atts['venue'] );
			if ( $location ) {
				$args['location'] = $location;
			}
		}
		if ( isset( $atts['past'] ) && in_array( strtolower( $atts['past'] ), array( 'yes', 'true', '1' ), true ) ) {
			$args['scope'] = 'past';
		}
		if ( ! empty( $atts['featured'] ) ) {
			// TEC featured has no EM equivalent beyond the migrated Featured attribute
			unset( $atts['featured'] );
		}
		$limit = $atts['limit'] ?? ( $atts['events_per_page'] ?? ( $atts['count'] ?? null ) );
		switch ( $tag ) {
			case 'tribe_mini_calendar':
				return static::build( 'events_calendar', $args + array( 'full' => 0 ) );
			case 'tribe_events_list':
			case 'tribe_this_week':
				if ( $limit ) {
					$args['limit'] = (int) $limit;
				}
				if ( 'tribe_this_week' === $tag ) {
					$args['scope'] = 'week';
				}
				return static::build( 'events_list', $args );
			case 'tribe_event_inline':
				$event = ! empty( $atts['id'] ) ? Map::get_em_id( TEC::KEY, 'event', (int) $atts['id'] ) : 0;
				return $event ? static::build( 'event', array( 'event' => $event ) ) . $inner . '[/event]' : '';
			case 'tribe_featured_venue':
				$location = ! empty( $atts['id'] ) ? Map::get_em_id( TEC::KEY, 'venue', (int) $atts['id'] ) : 0;
				return $location ? static::build( 'location', array( 'location' => $location ) ) : '';
			case 'tribe_event_countdown':
				return '';
			case 'tribe_events':
			default:
				$view = strtolower( $atts['view'] ?? 'month' );
				if ( in_array( $view, array( 'list', 'day', 'photo', 'summary' ), true ) ) {
					if ( $limit ) {
						$args['limit'] = (int) $limit;
					}
					if ( 'day' === $view ) {
						$args['scope'] = 'today';
					}
					return static::build( 'events_list', $args );
				}
				if ( 'map' === $view ) {
					return static::build( 'events_map', $args );
				}
				if ( 'week' === $view ) {
					return static::build( 'events_list_grouped', $args + array( 'scope' => 'week', 'mode' => 'daily' ) );
				}
				return static::build( 'events_calendar', $args + array( 'full' => 1 ) );
		}
	}

	protected static function map_terms( $value, $object_type, $em_arg ) {
		$out = array();
		foreach ( preg_split( '/\s*,\s*/', $value ) as $slug ) {
			$out[] = $slug;
		}
		return implode( ',', $out );
	}

	protected static function build( $tag, array $args ) {
		$s = '[' . $tag;
		foreach ( $args as $k => $v ) {
			$s .= ' ' . $k . '="' . esc_attr( $v ) . '"';
		}
		return $s . ']';
	}
}

/**
 * Old TEC URLs to their migrated EM pages. Only consulted on a 404, once TEC is deactivated.
 */
class Redirects {

	public static function resolve( TEC $tec, $path, array $query ) {
		$events_slug = trim( (string) $tec->get_tec_option( 'eventsSlug', 'events' ), '/' ) ?: 'events';
		$single_slug = trim( (string) $tec->get_tec_option( 'singleEventSlug', 'event' ), '/' ) ?: 'event';

		// ?post_type=tribe_events&p=123, ?tribe_events=slug, provisional occurrence ids
		if ( ! empty( $query['p'] ) || ! empty( $query['page_id'] ) ) {
			$url = static::event_url_by_id( $tec, (int) ( $query['p'] ?? $query['page_id'] ) );
			if ( $url ) {
				return $url;
			}
		}
		foreach ( array( 'tribe_events' => 'event', 'tribe_venue' => 'venue', 'tribe_organizer' => 'organizer' ) as $var => $type ) {
			if ( ! empty( $query[ $var ] ) ) {
				$url = static::url_by_slug( $type, sanitize_title( $query[ $var ] ) );
				if ( $url ) {
					return $url;
				}
			}
		}
		if ( ! empty( $query['tribe_events_cat'] ) ) {
			return static::term_url( 'category', sanitize_title( $query['tribe_events_cat'] ) );
		}
		if ( isset( $query['ical'] ) && preg_match( '#^' . preg_quote( $events_slug, '#' ) . '#', $path ) ) {
			return home_url( '/events.ics' );
		}
		$parts = explode( '/', $path );
		$first = $parts[0] ?? '';
		if ( $first === $single_slug && ! empty( $parts[1] ) ) {
			$slug = sanitize_title( $parts[1] );
			$date = $parts[2] ?? '';
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
				$url = static::occurrence_url( $slug, $date );
				if ( $url ) {
					return $url;
				}
			}
			return static::url_by_slug( 'event', $slug );
		}
		foreach ( array( 'venue', 'organizer' ) as $type ) {
			$base = static::linked_slug( $tec, $type );
			if ( $first === $base && ! empty( $parts[1] ) ) {
				return static::url_by_slug( $type, sanitize_title( $parts[1] ) );
			}
		}
		if ( 'series' === $first && ! empty( $parts[1] ) ) {
			return static::series_url( sanitize_title( $parts[1] ) );
		}
		if ( $first === $events_slug ) {
			$second = $parts[1] ?? '';
			if ( 'category' === $second && ! empty( $parts[2] ) ) {
				return static::term_url( 'category', sanitize_title( end( $parts ) === 'list' || end( $parts ) === 'month' ? $parts[ count( $parts ) - 2 ] : end( $parts ) ) );
			}
			if ( 'tag' === $second && ! empty( $parts[2] ) ) {
				return static::term_url( 'tag', sanitize_title( $parts[2] ) );
			}
			if ( preg_match( '/^(\d{4}-\d{2})(-\d{2})?$/', $second, $m ) ) {
				return add_query_arg( array( 'mo' => (int) substr( $m[1], 5, 2 ), 'yr' => (int) substr( $m[1], 0, 4 ) ), static::events_page_url() );
			}
			if ( in_array( $second, array( 'list', 'month', 'today', 'day', 'week', 'photo', 'map', 'summary', 'featured', 'upcoming', 'past', 'page' ), true ) ) {
				return static::events_page_url();
			}
		}
		return null;
	}

	protected static function linked_slug( TEC $tec, $type ) {
		return 'venue' === $type ? 'venue' : 'organizer';
	}

	public static function events_page_url() {
		$page = get_option( 'dbem_events_page' );
		return $page ? get_permalink( $page ) : get_post_type_archive_link( EM_POST_TYPE_EVENT );
	}

	protected static function url_by_slug( $type, $slug ) {
		$row = Map::find_by_data( TEC::KEY, $type, 'slug', $slug );
		if ( ! $row ) {
			return null;
		}
		switch ( $type ) {
			case 'event':
				$EM_Event = em_get_event( (int) $row['em_id'] );
				return $EM_Event && $EM_Event->event_id ? $EM_Event->get_permalink() : null;
			case 'venue':
				$EM_Location = em_get_location( (int) $row['em_id'] );
				return $EM_Location && $EM_Location->location_id ? $EM_Location->get_permalink() : null;
			case 'organizer':
				$link = get_term_link( (int) $row['em_id'], \EM\Organizers::TAXONOMY );
				return is_wp_error( $link ) ? null : $link;
		}
		return null;
	}

	protected static function term_url( $type, $slug ) {
		$row = Map::find_by_data( TEC::KEY, $type, 'slug', $slug );
		if ( ! $row ) {
			return null;
		}
		$link = get_term_link( (int) $row['em_id'], $row['data']['taxonomy'] ?? ( 'tag' === $type ? EM_TAXONOMY_TAG : EM_TAXONOMY_CATEGORY ) );
		return is_wp_error( $link ) ? null : $link;
	}

	protected static function occurrence_url( $slug, $date ) {
		$row = Map::find_by_data( TEC::KEY, 'event', 'slug', $slug );
		if ( ! $row ) {
			return null;
		}
		global $wpdb;
		$event_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT event_id FROM ' . EM_EVENTS_TABLE . ' WHERE recurrence_set_id IN (SELECT recurrence_set_id FROM ' . EM_EVENT_RECURRENCES_TABLE . ' WHERE event_id=%d) AND event_start_date=%s LIMIT 1', $row['em_id'], $date ) );
		$EM_Event = em_get_event( $event_id ?: (int) $row['em_id'] );
		return $EM_Event && $EM_Event->event_id ? $EM_Event->get_permalink() : null;
	}

	protected static function event_url_by_id( TEC $tec, $id ) {
		global $wpdb;
		$post_id = $id;
		$base = (int) get_option( 'tec_custom_tables_v1_provisional_post_base_provisional_id', 10000000 );
		if ( $id > $base && $tec->has_custom_tables() ) {
			$post_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->prefix}tec_occurrences WHERE occurrence_id=%d", $id - $base ) );
		}
		foreach ( array( 'event', 'venue' ) as $type ) {
			$row = Map::get( TEC::KEY, $type, $post_id );
			if ( $row ) {
				if ( 'event' === $type ) {
					$occurrence = $id > $base ? ( $row['data']['occurrences'][ $id - $base ] ?? 0 ) : 0;
					$EM_Event = em_get_event( $occurrence ?: (int) $row['em_id'] );
					return $EM_Event && $EM_Event->event_id ? $EM_Event->get_permalink() : null;
				}
				$EM_Location = em_get_location( (int) $row['em_id'] );
				return $EM_Location->get_permalink();
			}
		}
		return null;
	}

	protected static function series_url( $slug ) {
		global $wpdb;
		$series_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type='tribe_event_series' AND post_name=%s", $slug ) );
		if ( ! $series_id ) {
			return null;
		}
		$row = Map::find_by_data( TEC::KEY, 'event', 'series', $series_id );
		if ( ! $row ) {
			return null;
		}
		$EM_Event = em_get_event( (int) $row['em_id'] );
		return $EM_Event && $EM_Event->event_id ? $EM_Event->get_permalink() : null;
	}
}
