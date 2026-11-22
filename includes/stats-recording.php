<?php
/**
 * Statistics recording: write buffer, locking, event application and the public AJAX endpoint.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalizes a district key (slug).
 */
function kt_normalize_district_key( $raw_key ) {
	$slug = sanitize_title( $raw_key );
	if ( empty( $slug ) ) {
		return '';
	}
	$base = preg_replace( '/-(konseri|konser)$/', '', $slug );
	$key  = ! empty( $base ) ? $base : $slug;
	return substr( $key, 0, 80 );
}

/**
 * Formats a duration in seconds as human-readable text such as "1 dk 24 sn" (1 min 24 s) or "42 sn".
 */
function kt_format_duration_human( $seconds ) {
	$sec = max( 0, intval( round( $seconds ) ) );
	if ( $sec <= 0 ) {
		return '0 sn';
	}
	$mins = floor( $sec / 60 );
	$rem  = $sec % 60;
	if ( $mins > 0 ) {
		return $mins . ' dk ' . $rem . ' sn';
	}
	return $rem . ' sn';
}

/**
 * Conversion rate in percent: unique people who clicked a ticket link / unique visitors (capped at 100).
 */
function kt_conversion_rate( $click_visitors, $visitors ) {
	$visitors = intval( $visitors );
	if ( $visitors <= 0 ) {
		return 0.0;
	}
	return round( min( 100, ( intval( $click_visitors ) / $visitors ) * 100 ), 1 );
}

/**
 * Safely updates the stats counters (views, visitors, clicks, duration), the UTM breakdown and the last 90 days of daily data.
 */
function kt_record_district_stat( $raw_key, $type = 'views', $page_title = '', $extra = array() ) {
	$key = kt_normalize_district_key( $raw_key );
	if ( empty( $key ) || ! in_array( $type, array( 'views', 'clicks', 'duration' ), true ) ) {
		return;
	}

	// Remember the day the event happened, so buffered events are counted on the right day.
	$extra['date'] = kt_get_site_date_str( 0 );

	$styles = kt_get_style_settings();
	$delay  = intval( $styles['stats_buffer_seconds'] );

	if ( $delay > 0 ) {
		kt_buffer_stat_event( $key, $type, $page_title, $extra, $delay );
		return;
	}

	if ( ! kt_acquire_stats_lock() ) {
		return;
	}
	$stats = get_option( KT_STATS_OPT, array() );
	$stats = kt_apply_stat_event( is_array( $stats ) ? $stats : array(), $key, $type, $page_title, $extra );
	update_option( KT_STATS_OPT, $stats, false );
	kt_release_stats_lock();
}

/**
 * Appends an event to a small buffer option instead of rewriting the (large) statistics option on every hit.
 * The buffer is merged into the statistics in one write when it holds 50 events, when its oldest event is older
 * than the configured delay, when a single scheduled cron event fires, or when an admin screen or report is opened.
 */
function kt_buffer_stat_event( $key, $type, $page_title, $extra, $delay ) {
	if ( ! kt_acquire_stats_lock() ) {
		return;
	}

	$buffer = get_option( KT_STATS_BUFFER_OPT, array() );
	if ( ! is_array( $buffer ) ) {
		$buffer = array();
	}

	if ( count( $buffer ) < KT_STATS_BUFFER_MAX ) {
		$buffer[] = array( $key, $type, $page_title, $extra, time() );
		update_option( KT_STATS_BUFFER_OPT, $buffer, false );
	}

	$count  = count( $buffer );
	$oldest = isset( $buffer[0][4] ) ? intval( $buffer[0][4] ) : time();
	kt_release_stats_lock();

	if ( ! wp_next_scheduled( KT_STATS_FLUSH_HOOK ) ) {
		wp_schedule_single_event( time() + $delay + 5, KT_STATS_FLUSH_HOOK );
	}

	if ( $count >= 50 || ( time() - $oldest ) >= $delay ) {
		kt_flush_stats_buffer();
	}
}

/**
 * Merges all buffered events into the statistics option with a single read and a single write.
 */
add_action( KT_STATS_FLUSH_HOOK, 'kt_flush_stats_buffer' );
function kt_flush_stats_buffer() {
	$buffer = get_option( KT_STATS_BUFFER_OPT, array() );
	if ( empty( $buffer ) || ! is_array( $buffer ) ) {
		return;
	}

	if ( ! kt_acquire_stats_lock() ) {
		return;
	}

	// Re-read inside the lock: another request may have flushed or appended in the meantime.
	$buffer = get_option( KT_STATS_BUFFER_OPT, array() );
	if ( ! empty( $buffer ) && is_array( $buffer ) ) {
		$stats = get_option( KT_STATS_OPT, array() );
		if ( ! is_array( $stats ) ) {
			$stats = array();
		}
		foreach ( $buffer as $item ) {
			$stats = kt_apply_stat_event( $stats, $item[0], $item[1], $item[2], $item[3] );
		}
		update_option( KT_STATS_OPT, $stats, false );
		update_option( KT_STATS_BUFFER_OPT, array(), false );
	}

	kt_release_stats_lock();
}

/**
 * Acquires a short-lived write lock for the statistics option. add_option() is atomic (unique option_name),
 * so concurrent requests cannot overwrite each other's read-modify-write cycle. A stale lock expires after 5 seconds.
 */
function kt_acquire_stats_lock() {
	for ( $attempt = 0; $attempt < 20; $attempt++ ) {
		if ( add_option( KT_STATS_LOCK_OPT, time(), '', 'no' ) ) {
			return true;
		}

		$started = intval( get_option( KT_STATS_LOCK_OPT, 0 ) );
		if ( $started > 0 && ( time() - $started ) > 5 ) {
			delete_option( KT_STATS_LOCK_OPT );
			continue;
		}

		usleep( 50000 );
	}
	return false;
}

function kt_release_stats_lock() {
	delete_option( KT_STATS_LOCK_OPT );
}

/**
 * Applies one event to an in-memory statistics array and returns it (unchanged when the event is rejected).
 * $key is an already normalized district key. Callers are responsible for locking and persisting.
 */
function kt_apply_stat_event( $stats, $key, $type, $page_title, $extra ) {
	$styles    = kt_get_style_settings();
	$today_str = ( ! empty( $extra['date'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $extra['date'] ) ) ? $extra['date'] : kt_get_site_date_str( 0 );
	$cutoff    = kt_get_site_date_str( -intval( $styles['stats_retention_days'] ) );

	if ( ( ! isset( $stats[ $key ] ) || ! is_array( $stats[ $key ] ) ) && count( $stats ) >= KT_STATS_MAX_DISTRICTS ) {
		update_option( KT_STATS_OVERFLOW_OPT, kt_get_site_date_str( 0 ), false );
		return $stats;
	}

	if ( ! isset( $stats[ $key ] ) || ! is_array( $stats[ $key ] ) ) {
		$stats[ $key ] = array(
			'label'          => ! empty( $page_title ) ? sanitize_text_field( $page_title ) : ucfirst( str_replace( '-', ' ', $key ) ),
			'views'          => 0,
			'visitors'       => 0,
			'clicks'         => 0,
			'click_visitors' => 0,
			'total_seconds'  => 0,
			'duration_count' => 0,
			'daily'          => array(),
			'utm'            => array(),
			'since'          => $today_str,
			'updated'        => '',
		);
	} else {
		if ( ! empty( $page_title ) ) {
			$stats[ $key ]['label'] = sanitize_text_field( $page_title );
		}
		if ( ! isset( $stats[ $key ]['visitors'] ) ) {
			$stats[ $key ]['visitors'] = 0;
		}
		if ( ! isset( $stats[ $key ]['total_seconds'] ) ) {
			$stats[ $key ]['total_seconds'] = 0;
		}
		if ( ! isset( $stats[ $key ]['duration_count'] ) ) {
			$stats[ $key ]['duration_count'] = 0;
		}
		if ( ! isset( $stats[ $key ]['daily'] ) || ! is_array( $stats[ $key ]['daily'] ) ) {
			$stats[ $key ]['daily'] = array();
		}
		if ( ! isset( $stats[ $key ]['utm'] ) || ! is_array( $stats[ $key ]['utm'] ) ) {
			$stats[ $key ]['utm'] = array();
		}
	}

	if ( ! isset( $stats[ $key ]['daily'][ $today_str ] ) || ! is_array( $stats[ $key ]['daily'][ $today_str ] ) ) {
		$stats[ $key ]['daily'][ $today_str ] = array(
			'views'    => 0,
			'visitors' => 0,
			'clicks'   => 0,
			'click_visitors' => 0,
			'seconds'  => 0,
			'sessions' => 0,
		);
	}

	if ( 'views' === $type ) {
		$is_unique = ! empty( $extra['is_unique'] );
		if ( ! empty( $extra['utm'] ) && kt_get_utm_settings()['capture_enabled'] ) {
			$stats[ $key ]['utm'] = kt_add_utm_hit( $stats[ $key ]['utm'], $extra['utm'], 'views', $is_unique );
		}
		$stats[ $key ]['views']                        = intval( $stats[ $key ]['views'] ) + 1;
		$stats[ $key ]['daily'][ $today_str ]['views'] = intval( $stats[ $key ]['daily'][ $today_str ]['views'] ) + 1;

		if ( $is_unique ) {
			$stats[ $key ]['visitors']                        = intval( $stats[ $key ]['visitors'] ) + 1;
			$stats[ $key ]['daily'][ $today_str ]['visitors'] = intval( $stats[ $key ]['daily'][ $today_str ]['visitors'] ) + 1;
		}
	} elseif ( 'clicks' === $type ) {
		$is_unique_click = ! empty( $extra['is_unique'] );
		if ( ! empty( $extra['utm'] ) && kt_get_utm_settings()['capture_enabled'] ) {
			$stats[ $key ]['utm'] = kt_add_utm_hit( $stats[ $key ]['utm'], $extra['utm'], 'clicks', $is_unique_click );
		}
		if ( $is_unique_click ) {
			// Unique people who clicked a ticket link today (rows created before 4.1.0 may lack these counters).
			$stats[ $key ]['click_visitors']                        = ( isset( $stats[ $key ]['click_visitors'] ) ? intval( $stats[ $key ]['click_visitors'] ) : 0 ) + 1;
			$stats[ $key ]['daily'][ $today_str ]['click_visitors'] = ( isset( $stats[ $key ]['daily'][ $today_str ]['click_visitors'] ) ? intval( $stats[ $key ]['daily'][ $today_str ]['click_visitors'] ) : 0 ) + 1;
		}
		// Optional per-session attribution (ticket button with session="1" / "2").
		$session = isset( $extra['session'] ) ? (string) $extra['session'] : '';
		if ( in_array( $session, array( '1', '2' ), true ) ) {
			if ( ! isset( $stats[ $key ]['session_clicks'] ) || ! is_array( $stats[ $key ]['session_clicks'] ) ) {
				$stats[ $key ]['session_clicks'] = array( '1' => 0, '2' => 0 );
			}
			$stats[ $key ]['session_clicks'][ $session ] = ( isset( $stats[ $key ]['session_clicks'][ $session ] ) ? intval( $stats[ $key ]['session_clicks'][ $session ] ) : 0 ) + 1;
		}
		$stats[ $key ]['clicks']                        = intval( $stats[ $key ]['clicks'] ) + 1;
		$stats[ $key ]['daily'][ $today_str ]['clicks'] = intval( $stats[ $key ]['daily'][ $today_str ]['clicks'] ) + 1;
	} elseif ( 'duration' === $type ) {
		$sec = isset( $extra['seconds'] ) ? intval( $extra['seconds'] ) : 0;
		if ( $sec >= 1 && $sec <= 1800 ) {
			$stats[ $key ]['total_seconds']                     = intval( $stats[ $key ]['total_seconds'] ) + $sec;
			$stats[ $key ]['duration_count']                    = intval( $stats[ $key ]['duration_count'] ) + 1;
			$stats[ $key ]['daily'][ $today_str ]['seconds']  = intval( $stats[ $key ]['daily'][ $today_str ]['seconds'] ) + $sec;
			$stats[ $key ]['daily'][ $today_str ]['sessions'] = intval( $stats[ $key ]['daily'][ $today_str ]['sessions'] ) + 1;
		} else {
			return $stats;
		}
	}

	$stats[ $key ]['updated'] = kt_get_site_datetime_str();

	foreach ( $stats[ $key ]['daily'] as $d_key => $d_val ) {
		if ( $d_key < $cutoff ) {
			unset( $stats[ $key ]['daily'][ $d_key ] );
		}
	}

	return $stats;
}

/**
 * Cache-friendly AJAX endpoint that records page views, unique visitors, time on page and clicks (with optional UTM data).
 */
add_action( 'wp_ajax_kt_track_district_event', 'kt_ajax_track_district_event' );
add_action( 'wp_ajax_nopriv_kt_track_district_event', 'kt_ajax_track_district_event' );
function kt_ajax_track_district_event() {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- Public, cache-friendly tracking endpoint; a nonce is identical for all anonymous visitors and expires in cached pages. Every value below is sanitized, validated and capped.
	if ( current_user_can( 'edit_pages' ) ) {
		wp_send_json_success( array( 'ignored' => 'admin' ) );
	}

	// No nonce on purpose: for anonymous visitors a nonce is identical for everyone (no CSRF protection)
	// and it expires inside cached pages, which silently stopped tracking. Input is validated and capped instead.

	$district = isset( $_POST['district'] ) ? sanitize_text_field( wp_unslash( $_POST['district'] ) ) : '';
	$label    = isset( $_POST['label'] ) ? substr( sanitize_text_field( wp_unslash( $_POST['label'] ) ), 0, 120 ) : '';
	$ev_type  = isset( $_POST['event_type'] ) ? sanitize_key( wp_unslash( $_POST['event_type'] ) ) : 'clicks';

	if ( ! in_array( $ev_type, array( 'views', 'clicks', 'duration' ), true ) ) {
		$ev_type = 'clicks';
	}

	if ( empty( $district ) ) {
		wp_send_json_error( array( 'message' => 'missing_district' ), 400 );
	}

	$utm = array();
	if ( in_array( $ev_type, array( 'views', 'clicks' ), true ) ) {
		foreach ( array( 'source', 'medium', 'campaign' ) as $utm_field ) {
			$post_key            = 'utm_' . $utm_field;
			$utm[ $utm_field ] = isset( $_POST[ $post_key ] ) ? kt_sanitize_utm_value( sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) ) ) : '';
		}
	}

	$extra = array(
		'is_unique' => isset( $_POST['is_unique'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['is_unique'] ) ),
		'seconds'   => isset( $_POST['seconds'] ) ? intval( wp_unslash( $_POST['seconds'] ) ) : 0,
		'utm'       => $utm,
		'session'   => isset( $_POST['session'] ) ? substr( sanitize_text_field( wp_unslash( $_POST['session'] ) ), 0, 1 ) : '',
	);
	// phpcs:enable WordPress.Security.NonceVerification.Missing

	kt_record_district_stat( $district, $ev_type, $label, $extra );
	wp_send_json_success( array( 'recorded' => $ev_type ) );
}
