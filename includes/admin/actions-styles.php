<?php
/**
 * Admin action: save or reset the settings tab.
 * Included by kt_render_admin_page(); shares its local variables.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Save or reset the settings tab.
if ( isset( $_POST['kt_reset_styles'] ) && check_admin_referer( 'kt_styles_nonce_action', 'kt_styles_nonce' ) ) {
	delete_option( KT_STYLES_OPT );
	kt_sync_weekly_cron_schedule( true );
	$message = '<div class="notice notice-info is-dismissible"><p>' . esc_html__( 'Düğme biçimleri, yönlendirme hedefi, bilet düğmesi ve renk paletleri varsayılan ayarlara döndürüldü.', 'konser-takvimi' ) . '</p></div>';
} elseif ( isset( $_POST['kt_save_styles'] ) && check_admin_referer( 'kt_styles_nonce_action', 'kt_styles_nonce' ) ) {
	$defaults = kt_get_default_style_settings();

	$radius     = isset( $_POST['border_radius'] ) ? max( 0, min( 50, intval( $_POST['border_radius'] ) ) ) : 20;
	$height     = isset( $_POST['badge_height'] ) ? max( 60, min( 160, intval( $_POST['badge_height'] ) ) ) : 90;
	$gap        = isset( $_POST['grid_gap'] ) ? max( 4, min( 60, intval( $_POST['grid_gap'] ) ) ) : 20;
	$min_w      = isset( $_POST['card_min_w'] ) ? max( 180, min( 450, intval( $_POST['card_min_w'] ) ) ) : 250;
	$max_it     = isset( $_POST['max_items'] ) ? max( 1, min( 100, intval( $_POST['max_items'] ) ) ) : 16;
	$del_uninst = isset( $_POST['delete_on_uninst'] ) ? 1 : 0;

	$raw_redirect = isset( $_POST['redirect_target'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['redirect_target'] ) ) ) : '/konserler';
	if ( empty( $raw_redirect ) ) {
		$raw_redirect = '/konserler';
	} elseif ( 0 !== strpos( $raw_redirect, 'http://' ) && 0 !== strpos( $raw_redirect, 'https://' ) && 0 !== strpos( $raw_redirect, '/' ) ) {
		$raw_redirect = '/' . $raw_redirect;
	}

	$raw_ticket_url = isset( $_POST['ticket_btn_url'] ) ? trim( esc_url_raw( wp_unslash( $_POST['ticket_btn_url'] ) ) ) : '';

	$raw_ticket_img = isset( $_POST['ticket_btn_img'] ) ? trim( esc_url_raw( wp_unslash( $_POST['ticket_btn_img'] ) ) ) : '';
	if ( empty( $raw_ticket_img ) ) {
		$raw_ticket_img = $defaults['ticket_btn_img'];
	}

	$raw_domains      = isset( $_POST['ticket_domains'] ) ? sanitize_text_field( wp_unslash( $_POST['ticket_domains'] ) ) : $defaults['ticket_domains'];
	$schema_enabled   = isset( $_POST['schema_enabled'] ) ? 1 : 0;
	$schema_performer = isset( $_POST['schema_performer'] ) ? sanitize_text_field( wp_unslash( $_POST['schema_performer'] ) ) : '';

	$font_mode = isset( $_POST['font_mode'] ) ? sanitize_key( wp_unslash( $_POST['font_mode'] ) ) : 'google';
	if ( ! in_array( $font_mode, array( 'google', 'system', 'theme' ), true ) ) {
		$font_mode = 'google';
	}
	$timezone = isset( $_POST['timezone'] ) ? sanitize_text_field( wp_unslash( $_POST['timezone'] ) ) : 'Europe/Istanbul';
	if ( ! in_array( $timezone, timezone_identifiers_list(), true ) ) {
		$timezone = 'Europe/Istanbul';
	}
	$retention_days  = isset( $_POST['stats_retention_days'] ) ? intval( $_POST['stats_retention_days'] ) : 90;
	$buffer_seconds  = isset( $_POST['stats_buffer_seconds'] ) ? intval( $_POST['stats_buffer_seconds'] ) : 60;
	$alert_enabled   = isset( $_POST['alert_enabled'] ) ? 1 : 0;
	$auto_archive    = isset( $_POST['auto_archive_stats'] ) ? 1 : 0;
	$alert_min_conv  = isset( $_POST['alert_min_conversion'] ) ? floatval( $_POST['alert_min_conversion'] ) : 3;
	$alert_min_vis   = isset( $_POST['alert_min_visitors'] ) ? intval( $_POST['alert_min_visitors'] ) : 50;
	$utm_ticket_on   = isset( $_POST['utm_ticket_enabled'] ) ? 1 : 0;
	$utm_calendar_on = isset( $_POST['utm_calendar_enabled'] ) ? 1 : 0;
	$utm_capture_on  = isset( $_POST['utm_capture_enabled'] ) ? 1 : 0;
	$utm_source      = isset( $_POST['utm_source'] ) ? kt_sanitize_utm_value( sanitize_text_field( wp_unslash( $_POST['utm_source'] ) ) ) : '';
	$utm_medium      = isset( $_POST['utm_medium'] ) ? kt_sanitize_utm_value( sanitize_text_field( wp_unslash( $_POST['utm_medium'] ) ) ) : '';
	$utm_campaign    = isset( $_POST['utm_campaign'] ) ? kt_sanitize_utm_value( sanitize_text_field( wp_unslash( $_POST['utm_campaign'] ) ) ) : '';
	if ( '' === $utm_medium ) {
		$utm_medium = $defaults['utm_medium'];
	}

	$weekly_on    = isset( $_POST['weekly_report_on'] ) ? 1 : 0;
	$weekly_email = isset( $_POST['weekly_report_email'] ) ? sanitize_email( wp_unslash( $_POST['weekly_report_email'] ) ) : '';
	$weekly_day   = isset( $_POST['weekly_report_day'] ) ? sanitize_key( wp_unslash( $_POST['weekly_report_day'] ) ) : 'monday';
	$valid_days   = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );
	if ( ! in_array( $weekly_day, $valid_days, true ) ) {
		$weekly_day = 'monday';
	}

	$d_text = isset( $_POST['date_text'] ) ? sanitize_hex_color( wp_unslash( $_POST['date_text'] ) ) : '';
	$c_text = isset( $_POST['city_text'] ) ? sanitize_hex_color( wp_unslash( $_POST['city_text'] ) ) : '';

	$post_dates  = isset( $_POST['palette_date'] ) ? map_deep( (array) wp_unslash( $_POST['palette_date'] ), 'sanitize_hex_color' ) : array();
	$post_cities = isset( $_POST['palette_city'] ) ? map_deep( (array) wp_unslash( $_POST['palette_city'] ), 'sanitize_hex_color' ) : array();
	$palettes    = array();

	for ( $i = 0; $i < 12; $i++ ) {
		$pd = isset( $post_dates[ $i ] ) ? sanitize_hex_color( wp_unslash( $post_dates[ $i ] ) ) : '';
		$pc = isset( $post_cities[ $i ] ) ? sanitize_hex_color( wp_unslash( $post_cities[ $i ] ) ) : '';

		$palettes[ $i ] = array(
			'date' => $pd ? $pd : $defaults['palettes'][ $i ]['date'],
			'city' => $pc ? $pc : $defaults['palettes'][ $i ]['city'],
		);
	}

	$new_styles = array(
		'border_radius'       => $radius,
		'badge_height'        => $height,
		'grid_gap'            => $gap,
		'card_min_w'          => $min_w,
		'max_items'           => $max_it,
		'date_text'           => $d_text ? $d_text : $defaults['date_text'],
		'city_text'           => $c_text ? $c_text : $defaults['city_text'],
		'redirect_target'     => $raw_redirect,
		'ticket_btn_url'      => $raw_ticket_url,
		'ticket_btn_img'      => $raw_ticket_img,
		'ticket_domains'      => $raw_domains,
		'schema_enabled'      => $schema_enabled,
		'schema_performer'    => $schema_performer,
		'load_google_font'     => ( 'google' === $font_mode ) ? 1 : 0,
		'font_mode'            => $font_mode,
		'timezone'             => $timezone,
		'stats_retention_days' => $retention_days,
		'stats_buffer_seconds' => $buffer_seconds,
		'auto_archive_stats'   => $auto_archive,
		'alert_enabled'        => $alert_enabled,
		'alert_min_conversion' => $alert_min_conv,
		'alert_min_visitors'   => $alert_min_vis,
		'utm_ticket_enabled'   => $utm_ticket_on,
		'utm_calendar_enabled' => $utm_calendar_on,
		'utm_capture_enabled'  => $utm_capture_on,
		'utm_source'           => $utm_source,
		'utm_medium'           => $utm_medium,
		'utm_campaign'         => $utm_campaign,
		'weekly_report_on'    => $weekly_on,
		'weekly_report_email' => $weekly_email,
		'weekly_report_day'   => $weekly_day,
		'delete_on_uninst'    => $del_uninst,
		'palettes'            => $palettes,
	);

	update_option( KT_STYLES_OPT, $new_styles, false );
	kt_sync_weekly_cron_schedule( true );
	$message = '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Düğme biçimleri ve ayarlar kaydedildi.', 'konser-takvimi' ) . '</p></div>';
}
