<?php
namespace EM\Migrate\Sources\TEC;

/**
 * Turns a TEC recurring event into EM recurrence sets.
 *
 * TEC stores the pattern as an iCalendar RSET (tec_events.rset) and every generated date in tec_occurrences. The RSET is translated where EM has an
 * equivalent pattern, so the migrated event stays editable as a pattern; the caller checks EM's generated dates against the occurrence list and falls
 * back to explicit date lists (from_occurrences) when they differ, so no date is ever lost or invented.
 */
class Recurrence {

	const DAYS = array( 'SU' => 0, 'MO' => 1, 'TU' => 2, 'WE' => 3, 'TH' => 4, 'FR' => 5, 'SA' => 6 );

	/**
	 * @param string $rset
	 * @param array  $occurrences each array( start => 'Y-m-d H:i:s', end => 'Y-m-d H:i:s' ), local time, sorted
	 * @param string $timezone
	 * @return array|null EM sets for Writer::build_recurrence_sets, or null when the pattern has no EM equivalent.
	 */
	public static function from_rset( $rset, array $occurrences, $timezone ) {
		if ( ! $rset || ! $occurrences ) {
			return null;
		}
		$lines = preg_split( '/\r\n|\r|\n/', trim( $rset ) );
		$dtstart = $dtend = null;
		$rrules = $exrules = $rdates = $exdates = array();
		foreach ( $lines as $line ) {
			if ( ! preg_match( '/^([A-Z]+)((?:;[^:]+)?):(.*)$/', trim( $line ), $m ) ) {
				continue;
			}
			list( , $name, $params, $value ) = $m;
			switch ( $name ) {
				case 'DTSTART':
					$dtstart = static::parse_datetime( $value, $params, $timezone );
					break;
				case 'DTEND':
					$dtend = static::parse_datetime( $value, $params, $timezone );
					break;
				case 'RRULE':
					$rrules[] = static::parse_rule( $value );
					break;
				case 'EXRULE':
					$exrules[] = static::parse_rule( $value );
					break;
				case 'RDATE':
					foreach ( explode( ',', $value ) as $v ) {
						$rdates[] = static::parse_period( $v, $params, $timezone );
					}
					break;
				case 'EXDATE':
					foreach ( explode( ',', $value ) as $v ) {
						$exdates[] = static::parse_period( $v, $params, $timezone );
					}
					break;
			}
		}
		if ( ! $dtstart ) {
			return null;
		}
		if ( ! $dtend ) {
			$first = $occurrences[0];
			$dtend = ( clone $dtstart )->modify( '+' . ( strtotime( $first['end'] ) - strtotime( $first['start'] ) ) . ' seconds' );
		}
		$series_end = substr( end( $occurrences )['start'], 0, 10 );
		$sets = array();
		foreach ( $rrules as $rule ) {
			$set = static::rule_to_set( $rule, $dtstart, $dtend, $series_end );
			if ( ! $set ) {
				return null;
			}
			$sets[] = $set;
		}
		// RDATEs with their own times become date-list sets, one per distinct time range.
		foreach ( static::group_periods( $rdates ) as $group ) {
			$sets[] = $group;
		}
		if ( ! $sets ) {
			return null;
		}
		foreach ( $exrules as $rule ) {
			$set = static::rule_to_set( $rule, $dtstart, $dtend, $series_end );
			if ( ! $set ) {
				return null;
			}
			$set['type'] = 'exclude';
			unset( $set['start_time'], $set['end_time'], $set['duration'] );
			$sets[] = $set;
		}
		if ( $exdates ) {
			$sets[] = array( 'type' => 'exclude', 'freq' => 'on', 'dates' => array_map( function( $p ) { return $p['start']->format( 'Y-m-d' ); }, $exdates ) );
		}
		// EM's primary set (order 1) defines the event's own date range, so lead with the set that contains the first occurrence.
		return static::order_primary_first( $sets, $occurrences[0]['start'] );
	}

	/**
	 * Explicit date lists, one include set per distinct start time, end time and day span.
	 */
	public static function from_occurrences( array $occurrences ) {
		$periods = array();
		foreach ( $occurrences as $o ) {
			$periods[] = array( 'start' => new \DateTime( $o['start'] ), 'end' => new \DateTime( $o['end'] ) );
		}
		$sets = static::group_periods( $periods );
		return static::order_primary_first( $sets, $occurrences[0]['start'] );
	}

	protected static function order_primary_first( array $sets, $first_start ) {
		$first_date = substr( $first_start, 0, 10 );
		$first_time = substr( $first_start, 11, 8 );
		usort( $sets, function( $a, $b ) use ( $first_date, $first_time ) {
			$score = function( $s ) use ( $first_date, $first_time ) {
				if ( ( $s['type'] ?? 'include' ) === 'exclude' ) {
					return 3;
				}
				$time_match = ( $s['start_time'] ?? '' ) === $first_time;
				if ( 'on' === $s['freq'] ) {
					return in_array( $first_date, $s['dates'], true ) && $time_match ? 0 : 2;
				}
				$on_pattern = 'weekly' !== $s['freq'] || in_array( (string) gmdate( 'w', strtotime( $first_date ) ), explode( ',', (string) $s['byday'] ), true );
				return ( $s['start_date'] === $first_date && $time_match && $on_pattern ) ? 0 : 1;
			};
			return $score( $a ) <=> $score( $b );
		} );
		return $sets;
	}

	protected static function group_periods( array $periods ) {
		$groups = array();
		foreach ( $periods as $p ) {
			$start = $p['start'];
			$end = $p['end'] ?? $p['start'];
			$days = (int) floor( ( strtotime( $end->format( 'Y-m-d' ) ) - strtotime( $start->format( 'Y-m-d' ) ) ) / DAY_IN_SECONDS );
			$key = $start->format( 'H:i:s' ) . '|' . $end->format( 'H:i:s' ) . '|' . $days;
			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array( 'type' => 'include', 'freq' => 'on', 'dates' => array(), 'start_time' => $start->format( 'H:i:s' ), 'end_time' => $end->format( 'H:i:s' ), 'duration' => $days );
			}
			$groups[ $key ]['dates'][] = $start->format( 'Y-m-d' );
		}
		foreach ( $groups as &$g ) {
			$g['dates'] = array_values( array_unique( $g['dates'] ) );
			sort( $g['dates'] );
		}
		return array_values( $groups );
	}

	protected static function rule_to_set( array $rule, \DateTime $dtstart, \DateTime $dtend, $series_end ) {
		$freq = strtolower( $rule['FREQ'] ?? '' );
		// EM has no BYMONTHDAY, BYSETPOS, BYYEARDAY, BYWEEKNO or sub-daily frequencies; those fall back to explicit dates.
		foreach ( array( 'BYMONTHDAY', 'BYSETPOS', 'BYYEARDAY', 'BYWEEKNO', 'BYHOUR', 'BYMINUTE' ) as $unsupported ) {
			if ( isset( $rule[ $unsupported ] ) ) {
				return null;
			}
		}
		if ( ! in_array( $freq, array( 'daily', 'weekly', 'monthly', 'yearly' ), true ) ) {
			return null;
		}
		$set = array(
			'type' => 'include',
			'freq' => $freq,
			'interval' => max( 1, (int) ( $rule['INTERVAL'] ?? 1 ) ),
			'start_date' => $dtstart->format( 'Y-m-d' ),
			'end_date' => $series_end,
			'start_time' => $dtstart->format( 'H:i:s' ),
			'end_time' => $dtend->format( 'H:i:s' ),
			'duration' => (int) floor( ( strtotime( $dtend->format( 'Y-m-d' ) ) - strtotime( $dtstart->format( 'Y-m-d' ) ) ) / DAY_IN_SECONDS ),
		);
		if ( ! empty( $rule['UNTIL'] ) ) {
			$until = static::parse_datetime( $rule['UNTIL'], '', $dtstart->getTimezone()->getName() );
			if ( $until ) {
				$until->setTimezone( $dtstart->getTimezone() );
				$set['end_date'] = min( $series_end, $until->format( 'Y-m-d' ) );
			}
		}
		$byday = isset( $rule['BYDAY'] ) ? explode( ',', $rule['BYDAY'] ) : array();
		if ( 'weekly' === $freq ) {
			$days = array();
			foreach ( $byday ?: array( strtoupper( substr( $dtstart->format( 'D' ), 0, 2 ) ) ) as $d ) {
				if ( ! isset( static::DAYS[ $d ] ) ) {
					return null;
				}
				$days[] = static::DAYS[ $d ];
			}
			sort( $days );
			$set['byday'] = implode( ',', array_unique( $days ) );
		} elseif ( 'monthly' === $freq ) {
			if ( ! $byday ) {
				// same day number each month: EM can't express it
				return null;
			}
			if ( count( $byday ) !== 1 || ! preg_match( '/^([+-]?\d)?(SU|MO|TU|WE|TH|FR|SA)$/', $byday[0], $m ) || '' === $m[1] ) {
				return null;
			}
			$set['byday'] = static::DAYS[ $m[2] ];
			$set['byweekno'] = (int) $m[1] < 0 ? -1 : (int) $m[1];
		} elseif ( 'yearly' === $freq ) {
			if ( $byday || ( isset( $rule['BYMONTH'] ) && (int) $rule['BYMONTH'] !== (int) $dtstart->format( 'n' ) ) ) {
				return null;
			}
		} elseif ( 'daily' === $freq && $byday ) {
			return null;
		}
		return $set;
	}

	protected static function parse_rule( $value ) {
		$rule = array();
		foreach ( explode( ';', $value ) as $part ) {
			if ( strpos( $part, '=' ) ) {
				list( $k, $v ) = explode( '=', $part, 2 );
				$rule[ strtoupper( $k ) ] = $v;
			}
		}
		return $rule;
	}

	protected static function parse_datetime( $value, $params, $timezone ) {
		$tz = $timezone;
		if ( preg_match( '/TZID=([^;:]+)/', $params, $m ) ) {
			$tz = $m[1];
		}
		$utc = 'Z' === substr( $value, -1 );
		$value = rtrim( $value, 'Z' );
		$format = strlen( $value ) > 8 ? 'Ymd\THis' : 'Ymd';
		try {
			$zone = $utc ? new \DateTimeZone( 'UTC' ) : new \DateTimeZone( $tz ?: 'UTC' );
		} catch ( \Exception $e ) {
			$zone = new \DateTimeZone( 'UTC' );
		}
		$dt = \DateTime::createFromFormat( $format, $value, $zone );
		if ( ! $dt ) {
			return null;
		}
		if ( 'Ymd' === $format ) {
			$dt->setTime( 0, 0, 0 );
		}
		return $dt;
	}

	protected static function parse_period( $value, $params, $timezone ) {
		if ( strpos( $value, '/' ) ) {
			list( $s, $e ) = explode( '/', $value, 2 );
			$start = static::parse_datetime( $s, $params, $timezone );
			if ( $start && 'P' === $e[0] ) {
				$end = ( clone $start )->add( new \DateInterval( $e ) );
			} else {
				$end = static::parse_datetime( $e, $params, $timezone );
			}
			return array( 'start' => $start, 'end' => $end );
		}
		$start = static::parse_datetime( $value, $params, $timezone );
		return array( 'start' => $start, 'end' => $start );
	}
}
