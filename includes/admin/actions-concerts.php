<?php
/**
 * Admin action: save the concert list.
 * Included by kt_render_admin_page(); shares its local variables.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Tab 1: save concerts.
if ( isset( $_POST['kt_save_concerts'] ) && check_admin_referer( 'kt_concerts_nonce_action', 'kt_concerts_nonce' ) ) {
	$archived_events = array();

	foreach ( $existing_events as $ev ) {
		$end_ts = kt_get_event_end_timestamp( $ev );
		if ( false !== $end_ts && $end_ts < $now_ts ) {
			$archived_events[] = $ev;
		}
	}

	$dates         = isset( $_POST['concert_date'] ) ? map_deep( (array) wp_unslash( $_POST['concert_date'] ), 'sanitize_text_field' ) : array();
	$times         = isset( $_POST['concert_time'] ) ? map_deep( (array) wp_unslash( $_POST['concert_time'] ), 'sanitize_text_field' ) : array();
	$times2        = isset( $_POST['concert_time2'] ) ? map_deep( (array) wp_unslash( $_POST['concert_time2'] ), 'sanitize_text_field' ) : array();
	$cities        = isset( $_POST['concert_city'] ) ? map_deep( (array) wp_unslash( $_POST['concert_city'] ), 'sanitize_text_field' ) : array();
	$slugs         = isset( $_POST['concert_slug'] ) ? map_deep( (array) wp_unslash( $_POST['concert_slug'] ), 'sanitize_title' ) : array();
	$active_events = array();
	$total         = count( $cities );

	for ( $i = 0; $i < $total; $i++ ) {
		$city  = sanitize_text_field( wp_unslash( $cities[ $i ] ) );
		$date  = isset( $dates[ $i ] ) ? sanitize_text_field( wp_unslash( $dates[ $i ] ) ) : '';
		$time  = isset( $times[ $i ] ) ? sanitize_text_field( wp_unslash( $times[ $i ] ) ) : '';
		$time2 = isset( $times2[ $i ] ) ? sanitize_text_field( wp_unslash( $times2[ $i ] ) ) : '';
		$slug  = isset( $slugs[ $i ] ) ? sanitize_title( wp_unslash( $slugs[ $i ] ) ) : '';

		if ( empty( $city ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			continue;
		}

		if ( ! preg_match( '/^\d{2}:\d{2}$/', $time ) ) {
			$time = '15:00';
		}

		if ( ! preg_match( '/^\d{2}:\d{2}$/', $time2 ) ) {
			$time2 = '';
		}

		if ( empty( $slug ) ) {
			$slug = sanitize_title( $city . '-konseri' );
		}

		$active_events[] = array(
			'datetime' => kt_local_to_iso( $date, $time ),
			'time2'    => $time2,
			'city'     => $city,
			'slug'     => $slug,
		);
	}

	$all_events = array_merge( $archived_events, $active_events );
	usort( $all_events, 'kt_sort_events_asc' );

	update_option( KT_EVENTS_OPT, $all_events, false );
	$existing_events = $all_events;
	$message         = '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Konser listesi veritabanına kaydedildi.', 'konser-takvimi' ) . '</p></div>';
}
