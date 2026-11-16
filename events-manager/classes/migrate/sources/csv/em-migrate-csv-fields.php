<?php
namespace EM\Migrate\Sources\CSV;

/**
 * The fields each import type accepts and the column headers that select them automatically, which include the labels and keys of The Events Calendar's own CSV importer so a file prepared for it needs no remapping.
 */
class Fields {

	public static function types() {
		return array(
			'events' => __( 'Events', 'events-manager' ),
			'locations' => __( 'Locations', 'events-manager' ),
			'organizers' => __( 'Organizers', 'events-manager' ),
		);
	}

	/**
	 * @return array field key => array( label, aliases (headers lowercased with everything but letters and digits removed), required )
	 */
	public static function get( $type ) {
		switch ( $type ) {
			case 'locations':
				$fields = array(
					'name' => array( 'label' => __( 'Location name', 'events-manager' ), 'aliases' => array( 'venuename', 'locationname', 'name', 'venue', 'location', 'title' ), 'required' => true ),
					'content' => array( 'label' => __( 'Description', 'events-manager' ), 'aliases' => array( 'venuedescription', 'locationdescription', 'description', 'content' ) ),
					'address' => array( 'label' => __( 'Address', 'events-manager' ), 'aliases' => array( 'venueaddress', 'locationaddress', 'address', 'address1', 'street', 'streetaddress' ) ),
					'address2' => array( 'label' => __( 'Address line 2', 'events-manager' ), 'aliases' => array( 'venueaddress2', 'locationaddress2', 'address2', 'addressline2' ) ),
					'town' => array( 'label' => __( 'City', 'events-manager' ), 'aliases' => array( 'venuecity', 'locationtown', 'locationcity', 'city', 'town' ) ),
					'state' => array( 'label' => __( 'State or province', 'events-manager' ), 'aliases' => array( 'venuestateprovince', 'venuestate', 'venueprovince', 'locationstate', 'stateprovince', 'state', 'province', 'county' ) ),
					'postcode' => array( 'label' => __( 'Zip or postal code', 'events-manager' ), 'aliases' => array( 'venuezip', 'locationpostcode', 'zip', 'zipcode', 'postcode', 'postalcode' ) ),
					'region' => array( 'label' => __( 'Region', 'events-manager' ), 'aliases' => array( 'locationregion', 'region' ) ),
					'country' => array( 'label' => __( 'Country', 'events-manager' ), 'aliases' => array( 'venuecountry', 'locationcountry', 'country' ) ),
					'latitude' => array( 'label' => __( 'Latitude', 'events-manager' ), 'aliases' => array( 'venuelatitude', 'locationlatitude', 'latitude', 'lat' ) ),
					'longitude' => array( 'label' => __( 'Longitude', 'events-manager' ), 'aliases' => array( 'venuelongitude', 'locationlongitude', 'longitude', 'lng', 'lon', 'long' ) ),
					'phone' => array( 'label' => __( 'Phone', 'events-manager' ), 'aliases' => array( 'venuephone', 'locationphone', 'phone', 'telephone' ) ),
					'website' => array( 'label' => __( 'Website', 'events-manager' ), 'aliases' => array( 'venuewebsite', 'venueurl', 'locationwebsite', 'locationurl', 'website', 'url' ) ),
					'image' => array( 'label' => __( 'Featured image (web address or media ID)', 'events-manager' ), 'aliases' => array( 'venuefeaturedimage', 'locationimage', 'featuredimage', 'image' ) ),
				);
				break;
			case 'organizers':
				$fields = array(
					'name' => array( 'label' => __( 'Organizer name', 'events-manager' ), 'aliases' => array( 'organizername', 'name', 'organizer', 'title' ), 'required' => true ),
					'content' => array( 'label' => __( 'Description', 'events-manager' ), 'aliases' => array( 'organizerdescription', 'description', 'content' ) ),
					'email' => array( 'label' => __( 'Email', 'events-manager' ), 'aliases' => array( 'organizeremail', 'email', 'emailaddress' ) ),
					'website' => array( 'label' => __( 'Website', 'events-manager' ), 'aliases' => array( 'organizerwebsite', 'organizerurl', 'website', 'url' ) ),
					'phone' => array( 'label' => __( 'Phone', 'events-manager' ), 'aliases' => array( 'organizerphone', 'phone', 'telephone' ) ),
					'image' => array( 'label' => __( 'Featured image (web address or media ID)', 'events-manager' ), 'aliases' => array( 'organizerfeaturedimage', 'organizerimage', 'featuredimage', 'image' ) ),
				);
				break;
			default:
				$fields = array(
					'name' => array( 'label' => __( 'Event name', 'events-manager' ), 'aliases' => array( 'eventname', 'eventtitle', 'name', 'title', 'event' ), 'required' => true ),
					'content' => array( 'label' => __( 'Description', 'events-manager' ), 'aliases' => array( 'eventdescription', 'description', 'content', 'postcontent' ) ),
					'excerpt' => array( 'label' => __( 'Excerpt', 'events-manager' ), 'aliases' => array( 'eventexcerpt', 'excerpt', 'summary', 'postexcerpt' ) ),
					'start_date' => array( 'label' => __( 'Start date', 'events-manager' ), 'aliases' => array( 'eventstartdate', 'startdate', 'start', 'date', 'eventdate' ), 'required' => true ),
					'start_time' => array( 'label' => __( 'Start time', 'events-manager' ), 'aliases' => array( 'eventstarttime', 'starttime', 'time' ) ),
					'end_date' => array( 'label' => __( 'End date', 'events-manager' ), 'aliases' => array( 'eventenddate', 'enddate', 'end' ) ),
					'end_time' => array( 'label' => __( 'End time', 'events-manager' ), 'aliases' => array( 'eventendtime', 'endtime' ) ),
					'all_day' => array( 'label' => __( 'All day (yes or no)', 'events-manager' ), 'aliases' => array( 'alldayevent', 'eventallday', 'allday' ) ),
					'timezone' => array( 'label' => __( 'Time zone', 'events-manager' ), 'aliases' => array( 'eventtimezone', 'timezone', 'tz' ) ),
					'location' => array( 'label' => __( 'Location name', 'events-manager' ), 'aliases' => array( 'eventvenuename', 'eventvenue', 'venuename', 'venue', 'locationname', 'location', 'eventlocation' ) ),
					'organizers' => array( 'label' => __( 'Organizer names', 'events-manager' ), 'aliases' => array( 'eventorganizernamesorids', 'eventorganizername', 'eventorganizernames', 'eventorganizer', 'eventorganizers', 'organizername', 'organizernames', 'organizer', 'organizers' ) ),
					'categories' => array( 'label' => __( 'Categories', 'events-manager' ), 'aliases' => array( 'eventcategory', 'eventcategories', 'category', 'categories' ) ),
					'tags' => array( 'label' => __( 'Tags', 'events-manager' ), 'aliases' => array( 'eventtags', 'eventtag', 'tags', 'tag' ) ),
					'image' => array( 'label' => __( 'Featured image (web address or media ID)', 'events-manager' ), 'aliases' => array( 'eventfeaturedimage', 'featuredimage', 'eventimage', 'image', 'thumbnail' ) ),
					'cost' => array( 'label' => __( 'Cost', 'events-manager' ), 'aliases' => array( 'eventcost', 'cost', 'price' ) ),
					'currency_symbol' => array( 'label' => __( 'Currency symbol', 'events-manager' ), 'aliases' => array( 'eventcurrencysymbol', 'currencysymbol' ) ),
					'currency_code' => array( 'label' => __( 'Currency code', 'events-manager' ), 'aliases' => array( 'eventisocurrencycode', 'eventcurrencycode', 'currencycode', 'currency' ) ),
					'currency_position' => array( 'label' => __( 'Currency position (prefix or suffix)', 'events-manager' ), 'aliases' => array( 'eventcurrencyposition', 'currencyposition' ) ),
					'website' => array( 'label' => __( 'Website', 'events-manager' ), 'aliases' => array( 'eventwebsite', 'eventurl', 'website', 'url' ) ),
					'featured' => array( 'label' => __( 'Featured (yes or no)', 'events-manager' ), 'aliases' => array( 'featureevent', 'featuredevent', 'featured' ) ),
					'hide' => array( 'label' => __( 'Hidden from listings (yes or no)', 'events-manager' ), 'aliases' => array( 'hideeventfromeventlistings', 'eventhide', 'hidefromlistings' ) ),
					'recurrence_freq' => array( 'label' => __( 'Repeats (daily, weekly, monthly or yearly)', 'events-manager' ), 'aliases' => array( 'repeats', 'repeat', 'recurrence', 'recurrencefrequency', 'recurrencefreq', 'frequency', 'recurs' ) ),
					'recurrence_interval' => array( 'label' => __( 'Repeat interval (every 1, 2, 3 ...)', 'events-manager' ), 'aliases' => array( 'repeatinterval', 'repeatevery', 'recurrenceinterval', 'interval' ) ),
					'recurrence_days' => array( 'label' => __( 'Repeat on weekdays', 'events-manager' ), 'aliases' => array( 'repeaton', 'repeatonweekdays', 'repeatdays', 'recurrencedays', 'recurrenceweekdays', 'recurrencebyday', 'weekdays', 'daysofweek', 'byday' ) ),
					'recurrence_week' => array( 'label' => __( 'Repeat on week of the month (first to fourth, or last)', 'events-manager' ), 'aliases' => array( 'repeatonweekofthemonth', 'repeatweek', 'repeatonweek', 'recurrenceweek', 'recurrencebyweekno', 'weekofmonth', 'weekofthemonth' ) ),
					'recurrence_end' => array( 'label' => __( 'Repeat until (date)', 'events-manager' ), 'aliases' => array( 'repeatuntil', 'repeatend', 'repeatenddate', 'recurrenceend', 'recurrenceenddate', 'recurrenceuntil', 'until' ) ),
					'recurrence_dates' => array( 'label' => __( 'Repeat on these dates', 'events-manager' ), 'aliases' => array( 'repeatonthesedates', 'repeatdates', 'repeatondates', 'recurrencedates', 'dates', 'additionaldates' ) ),
					'recurrence_month_day' => array( 'label' => __( 'Repeat on day of the month (1 to 31, or last)', 'events-manager' ), 'aliases' => array( 'repeatondayofthemonth', 'repeatdayofmonth', 'repeatonday', 'recurrencedayofmonth', 'recurrencemonthday', 'recurrencebymonthday', 'dayofmonth', 'dayofthemonth', 'monthday' ) ),
					'recurrence_count' => array( 'label' => __( 'Repeat a number of times', 'events-manager' ), 'aliases' => array( 'repeatanumberoftimes', 'repeatcount', 'repeattimes', 'recurrencecount', 'occurrences', 'count', 'endsafter' ) ),
				);
		}
		return $fields;
	}

	public static function normalize( $header ) {
		return preg_replace( '/[^a-z0-9]/', '', strtolower( remove_accents( (string) $header ) ) );
	}

	/**
	 * @return array column index => field key, or an empty string for a column nothing matches
	 */
	public static function auto_map( $type, array $headers ) {
		$fields = static::get( $type );
		$map = array_fill_keys( array_keys( $headers ), '' );
		$normalized = array_map( array( static::class, 'normalize' ), $headers );
		foreach ( $fields as $key => $field ) {
			// aliases are in order of preference, so "Event Name" wins over a looser "Name" column in the same file
			foreach ( $field['aliases'] as $alias ) {
				$index = array_search( $alias, $normalized, true );
				if ( false !== $index && '' === $map[ $index ] ) {
					$map[ $index ] = $key;
					continue 2;
				}
			}
			// the field's own key, or its label as shown on the mapping screen, in whatever language that is
			foreach ( array( $key, preg_replace( '/\s*\(.*$/', '', $field['label'] ) ) as $name ) {
				$index = array_search( static::normalize( $name ), $normalized, true );
				if ( false !== $index && '' === $map[ $index ] ) {
					$map[ $index ] = $key;
					continue 2;
				}
			}
		}
		return $map;
	}

	/**
	 * Keeps only known fields and gives each to one column, the first that asks for it.
	 */
	public static function sanitize_map( $type, array $map, $columns ) {
		$fields = static::get( $type );
		$clean = array();
		$used = array();
		for ( $i = 0; $i < $columns; $i++ ) {
			$key = isset( $map[ $i ] ) ? (string) $map[ $i ] : '';
			if ( '' === $key || ! isset( $fields[ $key ] ) || isset( $used[ $key ] ) ) {
				$clean[ $i ] = '';
				continue;
			}
			$clean[ $i ] = $key;
			$used[ $key ] = true;
		}
		return $clean;
	}

	/**
	 * @return string[] labels of required fields no column is mapped to
	 */
	public static function missing_required( $type, array $map ) {
		$missing = array();
		foreach ( static::get( $type ) as $key => $field ) {
			if ( ! empty( $field['required'] ) && ! in_array( $key, $map, true ) ) {
				$missing[] = $field['label'];
			}
		}
		return $missing;
	}
}

/**
 * Reads the loose values people type into spreadsheets: dates in either day and month order, 12 and 24 hour times, yes and no, lists.
 */
class Values {

	public static function is_true( $value ) {
		return in_array( strtolower( trim( (string) $value ) ), array( '1', 'yes', 'y', 'true', 'on', 'x' ), true );
	}

	/**
	 * @param string $order mdy or dmy for dates written as three numbers with the year last. Empty reads slashes as month first and dashes or dots as day first, the way PHP and The Events Calendar's importer do.
	 * @return array{date: string, time: string|null}|null
	 */
	public static function parse_date( $value, $order = '' ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return null;
		}
		if ( preg_match( '/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})(?:[T\s]+(.+))?$/', $value, $m ) ) {
			$year = (int) $m[1];
			$month = (int) $m[2];
			$day = (int) $m[3];
			$rest = $m[4] ?? '';
		} elseif ( preg_match( '/^(\d{1,2})([-\/.])(\d{1,2})\2(\d{4}|\d{2})(?:[,\s]+(.+))?$/', $value, $m ) ) {
			$year = (int) $m[4];
			if ( 2 === strlen( $m[4] ) ) {
				$year += $year < 70 ? 2000 : 1900;
			}
			$day_first = $order ? 'dmy' === $order : '/' !== $m[2];
			$day = (int) ( $day_first ? $m[1] : $m[3] );
			$month = (int) ( $day_first ? $m[3] : $m[1] );
			$rest = $m[5] ?? '';
		} else {
			// written out, such as "March 4, 2027" or "4 Mar 2027 6:00 pm"; a month name and a full year keep times and relative words like "tomorrow" out
			if ( ! preg_match( '/[a-z]{3}/i', $value ) || ! preg_match( '/\d{4}/', $value ) ) {
				return null;
			}
			$date = date_create( $value, new \DateTimeZone( 'UTC' ) );
			if ( ! $date ) {
				return null;
			}
			$has_time = (bool) preg_match( '/\d{1,2}:\d{2}|\d\s*[ap]\.?m/i', $value );
			return array( 'date' => $date->format( 'Y-m-d' ), 'time' => $has_time ? $date->format( 'H:i:s' ) : null );
		}
		if ( ! checkdate( $month, $day, $year ) ) {
			return null;
		}
		$time = null;
		if ( '' !== trim( $rest ) ) {
			$time = static::parse_time( $rest );
			if ( null === $time ) {
				return null;
			}
		}
		return array( 'date' => sprintf( '%04d-%02d-%02d', $year, $month, $day ), 'time' => $time );
	}

	/**
	 * @return string|null H:i:s
	 */
	public static function parse_time( $value ) {
		$value = strtolower( trim( (string) $value ) );
		if ( ! preg_match( '/^(\d{1,2})(?:[:.h](\d{2}))?(?::(\d{2}))?(?:\s*([ap])\.?m\.?)?$/', $value, $m ) ) {
			return null;
		}
		$hour = (int) $m[1];
		$minute = (int) ( $m[2] ?? 0 );
		$second = (int) ( $m[3] ?? 0 );
		$meridian = $m[4] ?? '';
		if ( $meridian ) {
			if ( $hour < 1 || $hour > 12 ) {
				return null;
			}
			$hour = $hour % 12 + ( 'p' === $meridian ? 12 : 0 );
		}
		if ( $hour > 23 || $minute > 59 || $second > 59 ) {
			return null;
		}
		return sprintf( '%02d:%02d:%02d', $hour, $minute, $second );
	}

	/**
	 * Works out whether a column writes the day or the month first from dates that only read one way, such as 25/12/2027.
	 *
	 * @return string dmy, mdy, or empty when nothing settles it or the column contradicts itself
	 */
	public static function detect_date_order( array $values ) {
		$day_first = 0;
		$month_first = 0;
		foreach ( $values as $value ) {
			if ( ! preg_match( '/^\s*(\d{1,2})([-\/.])(\d{1,2})\2(\d{4}|\d{2})\b/', (string) $value, $m ) ) {
				continue;
			}
			if ( (int) $m[1] > 12 && (int) $m[3] <= 12 ) {
				$day_first++;
			} elseif ( (int) $m[3] > 12 && (int) $m[1] <= 12 ) {
				$month_first++;
			}
		}
		if ( $day_first && ! $month_first ) {
			return 'dmy';
		}
		return $month_first && ! $day_first ? 'mdy' : '';
	}

	/**
	 * Splits a cell holding several values. A pipe or semicolon wins over commas, so names containing commas can be listed.
	 *
	 * @return string[]
	 */
	public static function split_list( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return array();
		}
		$separator = false !== strpos( $value, '|' ) ? '|' : ( false !== strpos( $value, ';' ) ? ';' : ',' );
		return array_values( array_unique( array_filter( array_map( 'trim', explode( $separator, $value ) ), 'strlen' ) ) );
	}

	/**
	 * @return string|null a time zone name or UTC offset Events Manager accepts, an empty string for an empty value, null when it is not recognized
	 */
	public static function timezone( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		if ( preg_match( '/^(?:UTC|GMT)?\s*([+-])\s*(\d{1,2})(?::?(\d{2})|\.(\d{1,2}))?$/i', $value, $m ) ) {
			$hours = (int) $m[2];
			$minutes = ! empty( $m[4] ) ? (int) round( (float) ( '0.' . $m[4] ) * 60 ) : (int) ( $m[3] ?? 0 );
			if ( $hours > 14 || $minutes > 59 ) {
				return null;
			}
			if ( ! $hours && ! $minutes ) {
				return 'UTC';
			}
			return 'UTC' . $m[1] . $hours . ( $minutes ? rtrim( ltrim( number_format( $minutes / 60, 2, '.', '' ), '0' ), '0' ) : '' );
		}
		if ( in_array( strtoupper( $value ), array( 'UTC', 'GMT', 'Z' ), true ) ) {
			return 'UTC';
		}
		foreach ( timezone_identifiers_list() as $name ) {
			if ( 0 === strcasecmp( $name, $value ) ) {
				return $name;
			}
		}
		return null;
	}
}
