<?php
namespace EM\Migrate\Sources\CSV;

/**
 * Turns the repeat columns of one row into Events Manager recurrence sets.
 *
 * Events Manager repeats daily, weekly on chosen weekdays, monthly on the nth weekday and yearly, always up to an end date. A row asking for anything else (monthly on a day number, ending after a number of times) is either written out as a list of dates or refused, and says so, so nothing is imported on dates the file did not ask for.
 */
class Recurrence {

	const MAX_DATES = 1000;

	const FREQUENCIES = array(
		'daily' => 'daily', 'day' => 'daily', 'days' => 'daily', 'everyday' => 'daily',
		'weekly' => 'weekly', 'week' => 'weekly', 'weeks' => 'weekly', 'everyweek' => 'weekly',
		'monthly' => 'monthly', 'month' => 'monthly', 'months' => 'monthly', 'everymonth' => 'monthly',
		'yearly' => 'yearly', 'year' => 'yearly', 'years' => 'yearly', 'annual' => 'yearly', 'annually' => 'yearly', 'everyyear' => 'yearly',
	);
	const NONE = array( '', '0', 'no', 'n', 'none', 'never', 'false', 'once', 'single' );
	const WEEKDAYS = array(
		'su' => 0, 'sun' => 0, 'sunday' => 0, 'mo' => 1, 'mon' => 1, 'monday' => 1, 'tu' => 2, 'tue' => 2, 'tues' => 2, 'tuesday' => 2,
		'we' => 3, 'wed' => 3, 'wednesday' => 3, 'th' => 4, 'thu' => 4, 'thur' => 4, 'thurs' => 4, 'thursday' => 4,
		'fr' => 5, 'fri' => 5, 'friday' => 5, 'sa' => 6, 'sat' => 6, 'saturday' => 6,
	);
	const WEEKS = array( '1' => 1, '1st' => 1, 'first' => 1, '2' => 2, '2nd' => 2, 'second' => 2, '3' => 3, '3rd' => 3, 'third' => 3, '4' => 4, '4th' => 4, 'fourth' => 4, '5' => 5, '5th' => 5, 'fifth' => 5, '-1' => -1, 'last' => -1 );

	/**
	 * @param array  $row        raw cells: freq, interval, days, week, end, dates, month_day, count
	 * @param string $start_date Y-m-d of the row
	 * @param string $date_order see Values::parse_date()
	 * @param string $unsupported dates to write unsupported rules out as a list of dates, skip to refuse the row
	 * @return array{sets: array|null, start_date: string, end_date: string, note: string|null, error: string|null} sets is null for a row that does not repeat
	 */
	public static function from_row( array $row, $start_date, $date_order = '', $unsupported = 'dates' ) {
		$result = array( 'sets' => null, 'start_date' => $start_date, 'end_date' => $start_date, 'note' => null, 'error' => null );
		$fail = function( $message ) use ( $result ) {
			$result['error'] = $message;
			return $result;
		};
		$get = function( $key ) use ( $row ) {
			return isset( $row[ $key ] ) ? trim( (string) $row[ $key ] ) : '';
		};

		$freq_raw = preg_replace( '/[^a-z0-9]/', '', strtolower( $get( 'freq' ) ) );
		$interval_raw = $get( 'interval' );
		if ( in_array( $freq_raw, array( 'biweekly', 'fortnightly' ), true ) ) {
			$freq_raw = 'weekly';
			$interval_raw = '' === $interval_raw ? '2' : $interval_raw;
		}
		$freq = null;
		if ( ! in_array( $freq_raw, static::NONE, true ) ) {
			if ( ! isset( static::FREQUENCIES[ $freq_raw ] ) ) {
				return $fail( sprintf( __( 'Repeat value "%s" is not understood. Use daily, weekly, monthly or yearly.', 'events-manager' ), $get( 'freq' ) ) );
			}
			$freq = static::FREQUENCIES[ $freq_raw ];
		}

		$extra = static::parse_dates( $get( 'dates' ), $date_order );
		if ( is_string( $extra ) ) {
			return $fail( sprintf( __( 'The date "%s" in the list of dates is not understood.', 'events-manager' ), $extra ) );
		}
		if ( ! $freq && ! $extra ) {
			return $result;
		}

		if ( ! $freq ) {
			return static::as_dates( $result, array_merge( array( $start_date ), $extra ), null );
		}

		$interval = '' === $interval_raw ? 1 : (int) $interval_raw;
		if ( $interval < 1 || ( '' !== $interval_raw && ! ctype_digit( $interval_raw ) ) ) {
			return $fail( sprintf( __( 'Repeat interval "%s" is not a whole number of 1 or more.', 'events-manager' ), $interval_raw ) );
		}
		$until = null;
		if ( '' !== $get( 'end' ) ) {
			$parsed = Values::parse_date( $get( 'end' ), $date_order );
			if ( ! $parsed ) {
				return $fail( sprintf( __( 'The repeat-until date "%s" is not understood.', 'events-manager' ), $get( 'end' ) ) );
			}
			$until = $parsed['date'];
			if ( $until < $start_date ) {
				return $fail( __( 'The repeat-until date is before the start date.', 'events-manager' ) );
			}
		}
		$count = null;
		if ( '' !== $get( 'count' ) ) {
			if ( ! ctype_digit( $get( 'count' ) ) || (int) $get( 'count' ) < 1 ) {
				return $fail( sprintf( __( 'The number of times to repeat, "%s", is not a whole number of 1 or more.', 'events-manager' ), $get( 'count' ) ) );
			}
			$count = (int) $get( 'count' );
		}
		if ( null === $until && null === $count ) {
			return $fail( __( 'A repeating event needs a repeat-until date or a number of times.', 'events-manager' ) );
		}
		$days = static::parse_weekdays( $get( 'days' ) );
		if ( is_string( $days ) ) {
			return $fail( sprintf( __( 'The weekday "%s" is not understood.', 'events-manager' ), $days ) );
		}
		$week = null;
		if ( '' !== $get( 'week' ) ) {
			$key = strtolower( $get( 'week' ) );
			if ( ! isset( static::WEEKS[ $key ] ) ) {
				return $fail( sprintf( __( 'The week of the month "%s" is not understood. Use first, second, third, fourth or last.', 'events-manager' ), $get( 'week' ) ) );
			}
			$week = static::WEEKS[ $key ];
		}
		$month_day = null;
		if ( '' !== $get( 'month_day' ) ) {
			$value = strtolower( $get( 'month_day' ) );
			if ( 'last' === $value || '-1' === $value ) {
				$month_day = -1;
			} elseif ( ctype_digit( $value ) && (int) $value >= 1 && (int) $value <= 31 ) {
				$month_day = (int) $value;
			} else {
				return $fail( sprintf( __( 'The day of the month "%s" is not understood. Use 1 to 31, or last.', 'events-manager' ), $get( 'month_day' ) ) );
			}
		}

		$start_weekday = (int) gmdate( 'w', strtotime( $start_date . ' 00:00:00 UTC' ) );
		$rule = array( 'freq' => $freq, 'interval' => $interval, 'days' => array(), 'week' => null, 'month_day' => null );
		// why Events Manager cannot hold this rule as a rule, if it cannot
		$reason = null;
		switch ( $freq ) {
			case 'daily':
				if ( $days && 1 === $interval ) {
					// every day, but only on some weekdays, is exactly a weekly repeat on those weekdays
					$rule['freq'] = 'weekly';
					$rule['days'] = $days;
				} elseif ( $days ) {
					$rule['days'] = $days;
					$reason = __( 'repeating every few days on chosen weekdays only', 'events-manager' );
				}
				break;
			case 'weekly':
				$rule['days'] = $days ?: array( $start_weekday );
				break;
			case 'monthly':
				if ( null !== $month_day || null === $week ) {
					$rule['month_day'] = null !== $month_day ? $month_day : (int) substr( $start_date, 8, 2 );
					$reason = __( 'repeating monthly on a day of the month', 'events-manager' );
				} else {
					$rule['week'] = $week;
					$rule['days'] = $days ?: array( $start_weekday );
					if ( count( $rule['days'] ) > 1 ) {
						$reason = __( 'repeating monthly on more than one weekday', 'events-manager' );
					}
				}
				break;
		}
		if ( null !== $count ) {
			$reason = $reason ?: __( 'ending a repeat after a number of times', 'events-manager' );
		}

		if ( ! $reason && ! $extra ) {
			$set = array( 'type' => 'include', 'freq' => $rule['freq'], 'interval' => $interval, 'start_date' => $start_date, 'end_date' => $until );
			if ( 'weekly' === $rule['freq'] ) {
				$set['byday'] = implode( ',', $rule['days'] );
			} elseif ( 'monthly' === $rule['freq'] ) {
				$set['byday'] = (string) $rule['days'][0];
				$set['byweekno'] = $rule['week'];
			}
			$result['sets'] = array( $set );
			$result['end_date'] = $until;
			return $result;
		}
		if ( $reason && 'skip' === $unsupported ) {
			return $fail( sprintf( __( 'Events Manager has no rule for %s.', 'events-manager' ), $reason ) );
		}
		$dates = static::generate( $rule, $start_date, $until, $count );
		if ( count( $dates ) > static::MAX_DATES ) {
			return $fail( sprintf( __( 'The repeat rule gives more than %d dates.', 'events-manager' ), static::MAX_DATES ) );
		}
		if ( ! $dates ) {
			return $fail( __( 'The repeat rule gives no dates between the start date and the repeat-until date.', 'events-manager' ) );
		}
		$result = static::as_dates( $result, array_merge( $dates, $extra ), $reason );
		if ( ! $reason && ! $result['error'] && $result['sets'] ) {
			$result['note'] = sprintf( __( 'The repeat rule and the extra dates were combined into a list of %d dates.', 'events-manager' ), count( $result['sets'][0]['dates'] ) );
		}
		return $result;
	}

	protected static function as_dates( array $result, array $dates, $reason ) {
		$dates = array_values( array_unique( $dates ) );
		sort( $dates );
		if ( count( $dates ) > static::MAX_DATES ) {
			$result['error'] = sprintf( __( 'The repeat rule gives more than %d dates.', 'events-manager' ), static::MAX_DATES );
			return $result;
		}
		$result['start_date'] = $dates[0];
		$result['end_date'] = end( $dates );
		if ( count( $dates ) > 1 ) {
			$result['sets'] = array( array( 'type' => 'include', 'freq' => 'on', 'dates' => $dates ) );
		}
		if ( $reason ) {
			$result['note'] = sprintf( _n( 'Events Manager has no rule for %1$s, so this was imported as %2$d date.', 'Events Manager has no rule for %1$s, so this was imported as a list of %2$d dates.', count( $dates ), 'events-manager' ), $reason, count( $dates ) );
		}
		return $result;
	}

	/**
	 * @return string[]|string Y-m-d dates, or the first value that is not a date
	 */
	public static function parse_dates( $value, $date_order = '' ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return array();
		}
		$pieces = array_values( array_filter( array_map( 'trim', preg_split( '/[|;\r\n]+/', $value ) ), 'strlen' ) );
		// commas separate dates only when every piece is one, since "March 4, 2027" has a comma of its own
		if ( 1 === count( $pieces ) && false !== strpos( $value, ',' ) ) {
			$by_comma = array_values( array_filter( array_map( 'trim', explode( ',', $value ) ), 'strlen' ) );
			$all = true;
			foreach ( $by_comma as $piece ) {
				$all = $all && null !== Values::parse_date( $piece, $date_order );
			}
			if ( $all ) {
				$pieces = $by_comma;
			}
		}
		$dates = array();
		foreach ( $pieces as $piece ) {
			$parsed = Values::parse_date( $piece, $date_order );
			if ( ! $parsed ) {
				return $piece;
			}
			$dates[] = $parsed['date'];
		}
		return $dates;
	}

	/**
	 * @return int[]|string weekday numbers with Sunday as 0, or the first value that is not a weekday
	 */
	public static function parse_weekdays( $value ) {
		$value = strtolower( trim( (string) $value ) );
		if ( '' === $value ) {
			return array();
		}
		if ( 'weekdays' === $value ) {
			return array( 1, 2, 3, 4, 5 );
		}
		if ( 'weekends' === $value ) {
			return array( 0, 6 );
		}
		$days = array();
		foreach ( array_filter( preg_split( '/[\s,;|\/&]+|\band\b/', $value ), 'strlen' ) as $token ) {
			$token = rtrim( $token, '.' );
			if ( isset( static::WEEKDAYS[ $token ] ) ) {
				$days[] = static::WEEKDAYS[ $token ];
			} elseif ( ctype_digit( $token ) && (int) $token <= 7 ) {
				$days[] = (int) $token % 7;
			} else {
				return $token;
			}
		}
		$days = array_values( array_unique( $days ) );
		sort( $days );
		return $days;
	}

	/**
	 * Every date a rule lands on from the start date, up to and including the until date or until the count is reached. Weekly rules follow Events Manager's own reading: the seven days from the start date are the first week.
	 *
	 * @param array $rule freq, interval, days (weekday numbers), week (1 to 5 or -1), month_day (1 to 31 or -1)
	 * @return string[] Y-m-d, one more than MAX_DATES at most
	 */
	public static function generate( array $rule, $start_date, $until = null, $count = null ) {
		$utc = new \DateTimeZone( 'UTC' );
		$start = new \DateTime( $start_date . ' 00:00:00', $utc );
		$limit = null !== $count ? min( $count, static::MAX_DATES + 1 ) : static::MAX_DATES + 1;
		// a rule with a count and no end still has to stop somewhere
		$horizon = $until ?: ( clone $start )->modify( '+100 years' )->format( 'Y-m-d' );
		$interval = max( 1, (int) $rule['interval'] );
		$dates = array();
		switch ( $rule['freq'] ) {
			case 'daily':
				for ( $day = clone $start; $day->format( 'Y-m-d' ) <= $horizon && count( $dates ) < $limit; $day->modify( '+' . $interval . ' days' ) ) {
					if ( ! $rule['days'] || in_array( (int) $day->format( 'w' ), $rule['days'], true ) ) {
						$dates[] = $day->format( 'Y-m-d' );
					}
				}
				break;
			case 'weekly':
				$first_week = array();
				for ( $i = 0; $i < 7; $i++ ) {
					$day = ( clone $start )->modify( '+' . $i . ' days' );
					if ( in_array( (int) $day->format( 'w' ), $rule['days'], true ) ) {
						$first_week[] = $day;
					}
				}
				for ( $week = 0; $first_week && count( $dates ) < $limit; $week++ ) {
					$added = false;
					foreach ( $first_week as $day ) {
						$date = ( clone $day )->modify( '+' . ( $week * $interval * 7 ) . ' days' )->format( 'Y-m-d' );
						if ( $date <= $horizon && count( $dates ) < $limit ) {
							$dates[] = $date;
							$added = true;
						}
					}
					if ( ! $added ) {
						break;
					}
				}
				break;
			case 'monthly':
				$month = new \DateTime( $start->format( 'Y-m-01' ), $utc );
				for ( ; $month->format( 'Y-m-d' ) <= $horizon && count( $dates ) < $limit; $month->modify( '+' . $interval . ' months' ) ) {
					foreach ( static::month_dates( $month, $rule ) as $date ) {
						if ( $date >= $start_date && $date <= $horizon && count( $dates ) < $limit ) {
							$dates[] = $date;
						}
					}
				}
				break;
			case 'yearly':
				$year = (int) $start->format( 'Y' );
				$month = (int) $start->format( 'n' );
				$day = (int) $start->format( 'j' );
				for ( ; $year <= (int) substr( $horizon, 0, 4 ) && count( $dates ) < $limit; $year += $interval ) {
					$date = sprintf( '%04d-%02d-%02d', $year, $month, $day );
					// February 29 only exists in leap years
					if ( checkdate( $month, $day, $year ) && $date <= $horizon ) {
						$dates[] = $date;
					}
				}
				break;
		}
		return $dates;
	}

	/**
	 * @return string[] the dates a monthly rule lands on within one month, in order
	 */
	protected static function month_dates( \DateTime $month, array $rule ) {
		$year = (int) $month->format( 'Y' );
		$number = (int) $month->format( 'n' );
		$length = (int) $month->format( 't' );
		if ( null !== $rule['month_day'] ) {
			$day = -1 === $rule['month_day'] ? $length : $rule['month_day'];
			return $day <= $length ? array( sprintf( '%04d-%02d-%02d', $year, $number, $day ) ) : array();
		}
		$dates = array();
		$first_weekday = (int) $month->format( 'w' );
		foreach ( $rule['days'] as $weekday ) {
			$matches = array();
			for ( $day = 1 + ( $weekday - $first_weekday + 7 ) % 7; $day <= $length; $day += 7 ) {
				$matches[] = $day;
			}
			$day = -1 === $rule['week'] ? end( $matches ) : ( $matches[ $rule['week'] - 1 ] ?? null );
			if ( $day ) {
				$dates[] = sprintf( '%04d-%02d-%02d', $year, $number, $day );
			}
		}
		sort( $dates );
		return $dates;
	}
}
