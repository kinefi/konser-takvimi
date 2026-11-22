<?php
/**
 * Weekly report scheduling and e-mail delivery (including the low-conversion alert).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the weekly schedule interval with the WordPress cron system.
 */
add_filter( 'cron_schedules', 'kt_add_weekly_cron_schedule' );
function kt_add_weekly_cron_schedule( $schedules ) {
	if ( ! isset( $schedules['kt_weekly'] ) ) {
		$schedules['kt_weekly'] = array(
			'interval' => 604800,
			'display'  => __( 'Haftada Bir (Konser Takvimi Raporu)', 'konser-takvimi' ),
		);
	}
	return $schedules;
}

/**
 * Schedules or updates the weekly report cron job according to the settings.
 */
function kt_sync_weekly_cron_schedule( $force_reschedule = false ) {
	$styles = kt_get_style_settings();

	if ( empty( $styles['weekly_report_on'] ) ) {
		wp_clear_scheduled_hook( KT_CRON_HOOK );
		return;
	}

	$next_ts = wp_next_scheduled( KT_CRON_HOOK );
	if ( $next_ts && ! $force_reschedule ) {
		return;
	}

	wp_clear_scheduled_hook( KT_CRON_HOOK );

	$day_name = ! empty( $styles['weekly_report_day'] ) ? $styles['weekly_report_day'] : 'monday';
	$tz       = kt_get_site_timezone();
	$target   = new DateTime( 'next ' . $day_name . ' 09:00:00', $tz );

	wp_schedule_event( $target->getTimestamp(), 'kt_weekly', KT_CRON_HOOK );
}

add_action( 'init', 'kt_ensure_cron_scheduled' );
function kt_ensure_cron_scheduled() {
	kt_sync_weekly_cron_schedule( false );
}

/**
 * Builds the HTML summary report for the last 7 days and emails it to the administrator.
 */
add_action( KT_CRON_HOOK, 'kt_send_weekly_stats_email' );
function kt_send_weekly_stats_email( $override_email = '' ) {
	$styles = kt_get_style_settings();

	if ( empty( $override_email ) && empty( $styles['weekly_report_on'] ) ) {
		return false;
	}

	$recipient = ! empty( $override_email ) ? sanitize_email( $override_email ) : ( ! empty( $styles['weekly_report_email'] ) ? sanitize_email( $styles['weekly_report_email'] ) : get_option( 'admin_email' ) );
	if ( ! is_email( $recipient ) ) {
		return false;
	}

	$bundle  = kt_build_stats_report_bundle( 7 );
	$subject = sprintf( /* translators: 1: site name, 2: period */ __( '[%1$s] Haftalık Konser Sayfası İstatistik Raporu (%2$s)', 'konser-takvimi' ), $bundle['site_name'], $bundle['period_label'] );
	$headers = array( 'Content-Type: text/html; charset=UTF-8' );
	$body    = $bundle['html_card'];

	// Optional alert block for pages whose visitors rarely click the ticket button.
	if ( ! empty( $styles['alert_enabled'] ) ) {
		$low = kt_get_low_conversion_rows( $bundle['rows'], $styles['alert_min_conversion'], $styles['alert_min_visitors'] );
		if ( ! empty( $low ) ) {
			$subject .= __( ' — Düşük dönüşüm uyarısı', 'konser-takvimi' );
			$body     = kt_render_low_conversion_alert( $low, $styles['alert_min_conversion'], $styles['alert_min_visitors'] ) . $body;
		}
	}

	return wp_mail( $recipient, $subject, $body, $headers );
}

/**
 * Returns report rows whose conversion rate is below the threshold and that have enough unique visitors to be meaningful.
 */
function kt_get_low_conversion_rows( $rows, $min_conversion, $min_visitors ) {
	$low = array();
	foreach ( (array) $rows as $row ) {
		if ( isset( $row['visitors'], $row['conversion'] ) && $row['visitors'] >= $min_visitors && $row['conversion'] < $min_conversion ) {
			$low[] = $row;
		}
	}
	return $low;
}

/**
 * Renders the e-mail alert block (inline styles; e-mail clients ignore stylesheets).
 */
function kt_render_low_conversion_alert( $low_rows, $min_conversion, $min_visitors ) {
	$html  = '<div style="background:#fef2f2;border:1px solid #fecaca;border-left:4px solid #dc2626;border-radius:6px;padding:12px 16px;margin:0 0 16px 0;font-family:Arial,sans-serif;font-size:14px;color:#7f1d1d;">';
	$html .= '<strong>' . esc_html( sprintf( /* translators: 1: conversion threshold in percent, 2: minimum unique visitors */ __( 'Dönüşüm oranı %%%1$s altında kalan sayfalar (en az %2$d tekil ziyaretçi):', 'konser-takvimi' ), rtrim( rtrim( number_format( (float) $min_conversion, 1, ',', '' ), '0' ), ',' ), intval( $min_visitors ) ) ) . '</strong><ul style="margin:8px 0 0 18px;padding:0;">';
	foreach ( $low_rows as $row ) {
		$html .= '<li>' . esc_html( sprintf( /* translators: 1: page, 2: unique visitors, 3: unique clickers, 4: conversion percent */ __( '%1$s — %2$d tekil ziyaretçi, %3$d tıklayan, dönüşüm %%%4$s', 'konser-takvimi' ), $row['label'], $row['visitors'], $row['click_visitors'], number_format( $row['conversion'], 1, ',', '.' ) ) ) . '</li>';
	}
	$html .= '</ul></div>';
	return $html;
}
