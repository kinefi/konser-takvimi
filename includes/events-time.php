<?php
/**
 * Event date/time helpers: selected timezone, ISO conversion, event end times and sorting.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the timezone selected in the plugin settings (default Europe/Istanbul).
 * Event times, "today" for statistics and calendar links are all evaluated in this timezone.
 */
function kt_get_site_timezone() {
	$styles = get_option( KT_STYLES_OPT, array() );
	$name   = ( is_array( $styles ) && ! empty( $styles['timezone'] ) ) ? $styles['timezone'] : 'Europe/Istanbul';
	try {
		return new DateTimeZone( $name );
	} catch ( Exception $e ) {
		return new DateTimeZone( 'Europe/Istanbul' );
	}
}

/**
 * Builds an ISO 8601 date-time with the correct UTC offset for a local date (Y-m-d) and time (H:i) in the selected timezone.
 */
function kt_local_to_iso( $date, $time ) {
	try {
		$dt = new DateTime( $date . ' ' . $time . ':00', kt_get_site_timezone() );
		return $dt->format( 'Y-m-d\TH:i:sP' );
	} catch ( Exception $e ) {
		return $date . 'T' . $time . ':00+00:00';
	}
}

/**
 * Returns the ISO date-time of an event's second session. The UTC offset stored with the first session is reused,
 * so records saved under a different timezone setting keep their original meaning.
 */
function kt_second_session_iso( $event ) {
	$dt_parts = explode( 'T', (string) $event['datetime'] );
	$time2    = isset( $event['time2'] ) ? sanitize_text_field( $event['time2'] ) : '';
	if ( empty( $dt_parts[0] ) || '' === $time2 ) {
		return (string) $event['datetime'];
	}
	if ( isset( $dt_parts[1] ) && preg_match( '/(Z|[+-]\d{2}:\d{2})$/', $dt_parts[1], $m ) ) {
		return $dt_parts[0] . 'T' . $time2 . ':00' . ( 'Z' === $m[1] ? '+00:00' : $m[1] );
	}
	return kt_local_to_iso( $dt_parts[0], $time2 );
}

function kt_get_site_timestamp() {
	$tz  = kt_get_site_timezone();
	$now = new DateTime( 'now', $tz );
	return $now->getTimestamp();
}

function kt_get_site_datetime_str() {
	$tz  = kt_get_site_timezone();
	$now = new DateTime( 'now', $tz );
	return $now->format( 'Y-m-d H:i' );
}

function kt_get_site_date_str( $offset_days = 0 ) {
	$tz  = kt_get_site_timezone();
	$now = new DateTime( 'now', $tz );
	if ( 0 !== intval( $offset_days ) ) {
		$now->modify( intval( $offset_days ) . ' days' );
	}
	return $now->format( 'Y-m-d' );
}

/**
 * Returns the final validity timestamp of a concert record.
 * Uses the second session's time when present, otherwise the first session's time.
 */
function kt_get_event_end_timestamp( $event ) {
	if ( empty( $event['datetime'] ) ) {
		return false;
	}

	$primary_ts = strtotime( $event['datetime'] );
	if ( false === $primary_ts ) {
		return false;
	}

	if ( ! empty( $event['time2'] ) && preg_match( '/^\d{2}:\d{2}$/', $event['time2'] ) ) {
		$second_ts = strtotime( kt_second_session_iso( $event ) );
		if ( false !== $second_ts && $second_ts > $primary_ts ) {
			return $second_ts;
		}
	}

	return $primary_ts;
}

function kt_sort_events_asc( $a, $b ) {
	return strcmp( $a['datetime'], $b['datetime'] );
}

function kt_sort_events_desc( $a, $b ) {
	return strcmp( $b['datetime'], $a['datetime'] );
}
