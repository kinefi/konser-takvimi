<?php
/**
 * Statistics archive: snapshots of a district's counters that are kept when the live counters are reset,
 * the daily job that archives finished concerts, and restore/delete helpers.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the stored archive entries (oldest first).
 */
function kt_get_stats_archive() {
	$archive = get_option( KT_STATS_ARCHIVE_OPT, array() );
	return is_array( $archive ) ? array_values( $archive ) : array();
}

/**
 * First day a live statistics row covers: its "since" date, or the oldest day of daily data, or an empty string.
 */
function kt_stats_period_start( $row ) {
	if ( ! empty( $row['since'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $row['since'] ) ) {
		return $row['since'];
	}
	if ( ! empty( $row['daily'] ) && is_array( $row['daily'] ) ) {
		$days = array_keys( $row['daily'] );
		sort( $days );
		return (string) $days[0];
	}
	return '';
}

/**
 * Builds one archive entry from a live statistics row.
 */
function kt_build_archive_entry( $key, $row, $reason, $period_end ) {
	$now_str = kt_get_site_datetime_str();
	if ( '' === $period_end ) {
		$period_end = kt_get_site_date_str( 0 );
	}
	return array(
		'id'           => 'a' . substr( md5( $key . '|' . $now_str . '|' . wp_rand() ), 0, 12 ),
		'key'          => $key,
		'label'        => isset( $row['label'] ) ? (string) $row['label'] : $key,
		'reason'       => in_array( $reason, array( 'manual', 'auto', 'reset_all' ), true ) ? $reason : 'manual',
		'period_start' => kt_stats_period_start( $row ),
		'period_end'   => $period_end,
		'archived_at'  => $now_str,
		'row'          => $row,
	);
}

/**
 * Appends entries to the archive, keeping at most KT_STATS_ARCHIVE_MAX of the newest ones.
 */
function kt_append_to_stats_archive( $entries ) {
	$archive = array_merge( kt_get_stats_archive(), $entries );
	if ( count( $archive ) > KT_STATS_ARCHIVE_MAX ) {
		$archive = array_slice( $archive, -KT_STATS_ARCHIVE_MAX );
	}
	update_option( KT_STATS_ARCHIVE_OPT, $archive, false );
}

/**
 * Copies one district's statistics into the archive and (by default) removes the live row, so the next concert
 * starts from zero. The archive is written first, so a failure never loses data. Returns the entry or false.
 */
function kt_archive_district_stats( $key, $reason = 'manual', $reset = true, $period_end = '' ) {
	kt_flush_stats_buffer();
	if ( ! kt_acquire_stats_lock() ) {
		return false;
	}

	$stats = get_option( KT_STATS_OPT, array() );
	if ( ! is_array( $stats ) || ! isset( $stats[ $key ] ) || ! is_array( $stats[ $key ] ) ) {
		kt_release_stats_lock();
		return false;
	}

	$entry = kt_build_archive_entry( $key, $stats[ $key ], $reason, $period_end );
	kt_append_to_stats_archive( array( $entry ) );

	if ( $reset ) {
		unset( $stats[ $key ] );
		update_option( KT_STATS_OPT, $stats, false );
	}

	kt_release_stats_lock();
	return $entry;
}

/**
 * Archives every district and clears the live statistics. Returns the number of archived districts.
 */
function kt_archive_all_district_stats() {
	kt_flush_stats_buffer();
	if ( ! kt_acquire_stats_lock() ) {
		return 0;
	}

	$stats = get_option( KT_STATS_OPT, array() );
	if ( ! is_array( $stats ) || empty( $stats ) ) {
		kt_release_stats_lock();
		return 0;
	}

	$entries = array();
	foreach ( $stats as $key => $row ) {
		if ( is_array( $row ) ) {
			$entries[] = kt_build_archive_entry( (string) $key, $row, 'reset_all', '' );
		}
	}
	kt_append_to_stats_archive( $entries );
	update_option( KT_STATS_OPT, array(), false );

	kt_release_stats_lock();
	return count( $entries );
}

/**
 * Puts an archived entry back as the live row of its district. Only possible while that district has no live
 * row (otherwise two periods would be mixed). Returns 'ok', 'missing', 'exists', 'full' or 'busy'.
 */
function kt_restore_archived_stats( $id ) {
	kt_flush_stats_buffer();
	if ( ! kt_acquire_stats_lock() ) {
		return 'busy';
	}

	$archive = kt_get_stats_archive();
	$index   = -1;
	foreach ( $archive as $i => $entry ) {
		if ( isset( $entry['id'] ) && $entry['id'] === $id ) {
			$index = $i;
			break;
		}
	}
	if ( $index < 0 || empty( $archive[ $index ]['row'] ) || ! is_array( $archive[ $index ]['row'] ) ) {
		kt_release_stats_lock();
		return 'missing';
	}

	$key   = (string) $archive[ $index ]['key'];
	$stats = get_option( KT_STATS_OPT, array() );
	$stats = is_array( $stats ) ? $stats : array();
	if ( isset( $stats[ $key ] ) ) {
		kt_release_stats_lock();
		return 'exists';
	}
	if ( count( $stats ) >= KT_STATS_MAX_DISTRICTS ) {
		kt_release_stats_lock();
		return 'full';
	}

	$stats[ $key ] = $archive[ $index ]['row'];
	update_option( KT_STATS_OPT, $stats, false );
	unset( $archive[ $index ] );
	update_option( KT_STATS_ARCHIVE_OPT, array_values( $archive ), false );

	kt_release_stats_lock();
	return 'ok';
}

/**
 * Permanently removes one entry from the archive.
 */
function kt_delete_archive_entry( $id ) {
	$archive = kt_get_stats_archive();
	$kept    = array();
	foreach ( $archive as $entry ) {
		if ( ! isset( $entry['id'] ) || $entry['id'] !== $id ) {
			$kept[] = $entry;
		}
	}
	if ( count( $kept ) === count( $archive ) ) {
		return false;
	}
	update_option( KT_STATS_ARCHIVE_OPT, $kept, false );
	return true;
}

/**
 * Finds districts whose concerts have all ended (more than $grace_seconds ago) and that still have live statistics.
 * Returns key => period end date (the day the last concert ended).
 */
function kt_find_finished_districts( $events, $stats, $now_ts, $grace_seconds = 86400 ) {
	$last_end = array();
	$active   = array();

	foreach ( $events as $event ) {
		if ( empty( $event['slug'] ) ) {
			continue;
		}
		$key    = kt_normalize_district_key( $event['slug'] );
		$end_ts = kt_get_event_end_timestamp( $event );
		if ( '' === $key || false === $end_ts ) {
			continue;
		}
		if ( $end_ts + $grace_seconds >= $now_ts ) {
			$active[ $key ] = true;
		}
		if ( ! isset( $last_end[ $key ] ) || $end_ts > $last_end[ $key ] ) {
			$last_end[ $key ] = $end_ts;
		}
	}

	$result = array();
	foreach ( $last_end as $key => $end_ts ) {
		if ( isset( $active[ $key ] ) || empty( $stats[ $key ] ) || ! is_array( $stats[ $key ] ) ) {
			continue;
		}
		$day = new DateTime( '@' . intval( $end_ts ) );
		$day->setTimezone( kt_get_site_timezone() );
		$result[ $key ] = $day->format( 'Y-m-d' );
	}
	return $result;
}

/**
 * Daily job: archives (and resets) the statistics of districts whose concerts are all over.
 * Returns the number of archived districts.
 */
add_action( KT_ARCHIVE_CRON_HOOK, 'kt_auto_archive_finished_stats' );
function kt_auto_archive_finished_stats() {
	$styles = kt_get_style_settings();
	if ( empty( $styles['auto_archive_stats'] ) ) {
		return 0;
	}

	kt_flush_stats_buffer();
	$events = get_option( KT_EVENTS_OPT, array() );
	$stats  = get_option( KT_STATS_OPT, array() );
	if ( ! is_array( $events ) || ! is_array( $stats ) ) {
		return 0;
	}

	$done = 0;
	foreach ( kt_find_finished_districts( $events, $stats, kt_get_site_timestamp() ) as $key => $period_end ) {
		if ( empty( $stats[ $key ]['views'] ) ) {
			continue;
		}
		if ( kt_archive_district_stats( $key, 'auto', true, $period_end ) ) {
			$done++;
		}
	}
	return $done;
}

add_action( 'init', 'kt_ensure_archive_cron_scheduled' );
function kt_ensure_archive_cron_scheduled() {
	if ( ! wp_next_scheduled( KT_ARCHIVE_CRON_HOOK ) ) {
		wp_schedule_event( time() + 3600, 'daily', KT_ARCHIVE_CRON_HOOK );
	}
}
