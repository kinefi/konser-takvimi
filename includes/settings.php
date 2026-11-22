<?php
/**
 * Default and normalized plugin settings.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function kt_get_default_style_settings() {
	return array(
		'border_radius'       => 20,
		'badge_height'        => 90,
		'grid_gap'            => 20,
		'card_min_w'          => 250,
		'max_items'           => 16,
		'date_text'           => '#111827',
		'city_text'           => '#0f172a',
		'redirect_target'     => '/konserler',
		'ticket_btn_url'      => '',
		'ticket_btn_img'      => 'https://subadapcocuk.org/wp-content/uploads/2026/09/4-edited-1024x281.png',
		'ticket_domains'      => 'biletinial.com, biletix.com, passo.com.tr, bubilet.com.tr, mobilet.com, biletino.com',
		'schema_enabled'      => 1,
		'schema_performer'    => '',
		'stats_buffer_seconds' => 60,
		'stats_retention_days' => 90,
		'alert_enabled'        => 0,
		'alert_min_conversion' => 3,
		'alert_min_visitors'   => 50,
		'load_google_font'     => 1,
		'font_mode'            => 'google',
		'timezone'             => 'Europe/Istanbul',
		'utm_ticket_enabled'   => 0,
		'utm_calendar_enabled' => 1,
		'utm_capture_enabled'  => 1,
		'utm_source'           => '',
		'utm_medium'           => 'referral',
		'utm_campaign'         => '',
		'weekly_report_on'    => 1,
		'weekly_report_email' => '',
		'weekly_report_day'   => 'monday',
		'delete_on_uninst'    => 0,
		'palettes'            => array(
			array( 'date' => '#e94c86', 'city' => '#fce1ec' ),
			array( 'date' => '#ffbc00', 'city' => '#fff4d1' ),
			array( 'date' => '#00bcd4', 'city' => '#d1f2f7' ),
			array( 'date' => '#b3c24b', 'city' => '#f4f8db' ),
			array( 'date' => '#995c44', 'city' => '#f2e4df' ),
			array( 'date' => '#eade52', 'city' => '#fefcdb' ),
			array( 'date' => '#9a71c6', 'city' => '#ede5f3' ),
			array( 'date' => '#70a488', 'city' => '#d9efe3' ),
			array( 'date' => '#f15623', 'city' => '#fde8e3' ),
			array( 'date' => '#2f83c8', 'city' => '#d2e7e7' ),
			array( 'date' => '#7bb67c', 'city' => '#d8efd9' ),
			array( 'date' => '#efabd4', 'city' => '#ffeef8' ),
		),
	);
}

function kt_get_style_settings() {
	$defaults = kt_get_default_style_settings();
	$saved    = get_option( KT_STYLES_OPT, array() );

	if ( ! is_array( $saved ) ) {
		return $defaults;
	}

	$merged             = wp_parse_args( $saved, $defaults );
	$merged['palettes'] = array();

	if ( empty( $merged['redirect_target'] ) ) {
		$merged['redirect_target'] = '/konserler';
	}

	if ( ! isset( $merged['ticket_btn_url'] ) ) {
		$merged['ticket_btn_url'] = '';
	}

	if ( empty( $merged['ticket_btn_img'] ) ) {
		$merged['ticket_btn_img'] = $defaults['ticket_btn_img'];
	}

	if ( ! isset( $merged['ticket_domains'] ) ) {
		$merged['ticket_domains'] = $defaults['ticket_domains'];
	}

	if ( ! isset( $merged['schema_performer'] ) ) {
		$merged['schema_performer'] = '';
	}

	// Font mode: 'google' (Quicksand from Google Fonts), 'system' (no external request) or 'theme' (inherit the theme font).
	if ( ! isset( $saved['font_mode'] ) || ! in_array( $saved['font_mode'], array( 'google', 'system', 'theme' ), true ) ) {
		$merged['font_mode'] = empty( $merged['load_google_font'] ) ? 'system' : 'google';
	}
	$merged['load_google_font'] = ( 'google' === $merged['font_mode'] ) ? 1 : 0;

	// Timezone used for event times, statistics days and calendar links.
	if ( empty( $merged['timezone'] ) || ! in_array( $merged['timezone'], timezone_identifiers_list(), true ) ) {
		$merged['timezone'] = 'Europe/Istanbul';
	}

	if ( ! in_array( intval( $merged['stats_retention_days'] ), array( 30, 90, 180 ), true ) ) {
		$merged['stats_retention_days'] = 90;
	}
	if ( ! in_array( intval( $merged['stats_buffer_seconds'] ), array( 0, 30, 60, 300 ), true ) ) {
		$merged['stats_buffer_seconds'] = 60;
	}
	$merged['alert_min_conversion'] = max( 0, min( 100, floatval( $merged['alert_min_conversion'] ) ) );
	$merged['alert_min_visitors']   = max( 1, intval( $merged['alert_min_visitors'] ) );

	if ( empty( $merged['utm_medium'] ) ) {
		$merged['utm_medium'] = $defaults['utm_medium'];
	}

	$valid_days = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );
	if ( empty( $merged['weekly_report_day'] ) || ! in_array( $merged['weekly_report_day'], $valid_days, true ) ) {
		$merged['weekly_report_day'] = 'monday';
	}

	for ( $i = 0; $i < 12; $i++ ) {
		$d_col = isset( $saved['palettes'][ $i ]['date'] ) ? sanitize_hex_color( $saved['palettes'][ $i ]['date'] ) : '';
		$c_col = isset( $saved['palettes'][ $i ]['city'] ) ? sanitize_hex_color( $saved['palettes'][ $i ]['city'] ) : '';

		$merged['palettes'][ $i ] = array(
			'date' => $d_col ? $d_col : $defaults['palettes'][ $i ]['date'],
			'city' => $c_col ? $c_col : $defaults['palettes'][ $i ]['city'],
		);
	}

	return $merged;
}
