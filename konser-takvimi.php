<?php
/**
 * Plugin Name: Konser Takvimi
 * Description: Database-backed concert calendar with double-session support, Google MusicEvent JSON-LD, automatic archiving of past concerts, cache-safe 302 district redirects, Add to Calendar buttons, Gutenberg blocks, UTM tagging and CTR-ranked analytics.
 * Version: 4.3.0
 * Author: Şubadap Çocuk
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'KT_PLUGIN_DIR' ) ) {
	define( 'KT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'KT_PLUGIN_URL' ) ) {
	define( 'KT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'KT_PLUGIN_VER' ) ) {
	define( 'KT_PLUGIN_VER', '4.3.0' );
}
if ( ! defined( 'KT_EVENTS_OPT' ) ) {
	define( 'KT_EVENTS_OPT', 'subadap_konserler_list' );
}
if ( ! defined( 'KT_STYLES_OPT' ) ) {
	define( 'KT_STYLES_OPT', 'subadap_konserler_styles' );
}
if ( ! defined( 'KT_STATS_OPT' ) ) {
	define( 'KT_STATS_OPT', 'subadap_konserler_stats' );
}
if ( ! defined( 'KT_CRON_HOOK' ) ) {
	define( 'KT_CRON_HOOK', 'kt_weekly_stats_report_event' );
}
// Maximum number of districts kept in the statistics option (protects against bloat from forged requests).
if ( ! defined( 'KT_STATS_MAX_DISTRICTS' ) ) {
	define( 'KT_STATS_MAX_DISTRICTS', 300 );
}
// Buffered tracking events waiting to be merged into the statistics option.
if ( ! defined( 'KT_STATS_BUFFER_OPT' ) ) {
	define( 'KT_STATS_BUFFER_OPT', 'kt_stats_buffer' );
}
if ( ! defined( 'KT_STATS_BUFFER_MAX' ) ) {
	define( 'KT_STATS_BUFFER_MAX', 500 );
}
if ( ! defined( 'KT_STATS_FLUSH_HOOK' ) ) {
	define( 'KT_STATS_FLUSH_HOOK', 'kt_flush_stats_buffer_event' );
}
// Date of the last time a new district was rejected because KT_STATS_MAX_DISTRICTS was reached.
if ( ! defined( 'KT_STATS_OVERFLOW_OPT' ) ) {
	define( 'KT_STATS_OVERFLOW_OPT', 'kt_stats_overflow' );
}
// Option name used as a short-lived lock while the statistics option is read-modified-written.
if ( ! defined( 'KT_STATS_LOCK_OPT' ) ) {
	define( 'KT_STATS_LOCK_OPT', 'kt_stats_write_lock' );
}
// Maximum number of distinct source/medium/campaign combinations stored per district.
if ( ! defined( 'KT_UTM_MAX_COMBOS' ) ) {
	define( 'KT_UTM_MAX_COMBOS', 40 );
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

/**
 * Normalizes a UTM value: accents/Turkish letters transliterated, lowercase, spaces to hyphens,
 * only [a-z0-9._-], max 64 characters.
 */
function kt_sanitize_utm_value( $value ) {
	$value = strtolower( remove_accents( sanitize_text_field( (string) $value ) ) );
	$value = preg_replace( '/\s+/', '-', $value );
	$value = preg_replace( '/[^a-z0-9._\-]/', '', $value );
	return substr( $value, 0, 64 );
}

/**
 * Returns the effective UTM configuration (settings merged with defaults).
 * The source falls back to the site host when it is left empty in the settings.
 */
function kt_get_utm_settings() {
	$styles = kt_get_style_settings();
	$source = kt_sanitize_utm_value( $styles['utm_source'] );

	if ( '' === $source ) {
		$source = kt_sanitize_utm_value( wp_parse_url( home_url(), PHP_URL_HOST ) );
	}

	return array(
		'ticket_enabled'   => ! empty( $styles['utm_ticket_enabled'] ),
		'calendar_enabled' => ! empty( $styles['utm_calendar_enabled'] ),
		'capture_enabled'  => ! empty( $styles['utm_capture_enabled'] ),
		'source'           => $source,
		'medium'           => kt_sanitize_utm_value( $styles['utm_medium'] ),
		'campaign'         => kt_sanitize_utm_value( $styles['utm_campaign'] ),
	);
}

/**
 * Resolves the campaign value: the fixed campaign from the settings, or the district key when it is empty.
 */
function kt_resolve_utm_campaign( $district_key = '' ) {
	$utm = kt_get_utm_settings();
	if ( '' !== $utm['campaign'] ) {
		return $utm['campaign'];
	}
	return kt_sanitize_utm_value( kt_normalize_district_key( $district_key ) );
}

/**
 * UTM parameters for outbound ticket links. Returns an empty array when ticket UTM is disabled.
 */
function kt_get_ticket_utm_params( $district_key = '', $content = 'ticket-button' ) {
	$utm = kt_get_utm_settings();
	if ( ! $utm['ticket_enabled'] ) {
		return array();
	}

	return array_filter(
		array(
			'utm_source'   => $utm['source'],
			'utm_medium'   => $utm['medium'],
			'utm_campaign' => kt_resolve_utm_campaign( $district_key ),
			'utm_content'  => kt_sanitize_utm_value( $content ),
		),
		'strlen'
	);
}

/**
 * UTM parameters for the page link placed in "Add to Calendar" event descriptions.
 * $channel is 'google-calendar' or 'ics-file'. Returns an empty array when calendar UTM is disabled.
 */
function kt_get_calendar_utm_params( $channel, $district_key = '', $session_number = 1 ) {
	$utm = kt_get_utm_settings();
	if ( ! $utm['calendar_enabled'] ) {
		return array();
	}

	return array_filter(
		array(
			'utm_source'   => kt_sanitize_utm_value( $channel ),
			'utm_medium'   => 'calendar',
			'utm_campaign' => kt_resolve_utm_campaign( $district_key ),
			'utm_content'  => 'session-' . max( 1, intval( $session_number ) ),
		),
		'strlen'
	);
}

/**
 * Appends utm_* parameters to an http(s) URL. If the URL already carries any utm_* parameter it is returned
 * unchanged (all-or-nothing), so manually tagged links and ticket platforms keep their own tagging. Non-http(s) and relative URLs are returned unchanged.
 */
function kt_append_utm_params( $url, $params ) {
	if ( empty( $url ) || empty( $params ) || ! is_array( $params ) ) {
		return $url;
	}

	$parts = wp_parse_url( $url );
	if ( empty( $parts['scheme'] ) || ! in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
		return $url;
	}

	$existing = array();
	if ( ! empty( $parts['query'] ) ) {
		wp_parse_str( $parts['query'], $existing );
	}

	// All-or-nothing: if the destination already carries any utm_* parameter (for example the ticket platform's
	// own tagging), leave the URL alone so we never create a hybrid of two tagging schemes.
	foreach ( array_keys( $existing ) as $existing_name ) {
		if ( 0 === strpos( strtolower( (string) $existing_name ), 'utm_' ) ) {
			return $url;
		}
	}

	$to_add = array();
	foreach ( $params as $name => $value ) {
		if ( 0 !== strpos( $name, 'utm_' ) || '' === (string) $value ) {
			continue;
		}
		$to_add[ $name ] = $value;
	}

	if ( empty( $to_add ) ) {
		return $url;
	}

	return add_query_arg( $to_add, $url );
}

/**
 * Registers the weekly schedule interval with the WordPress cron system.
 */
add_filter( 'cron_schedules', 'kt_add_weekly_cron_schedule' );
function kt_add_weekly_cron_schedule( $schedules ) {
	if ( ! isset( $schedules['kt_weekly'] ) ) {
		$schedules['kt_weekly'] = array(
			'interval' => 604800,
			'display'  => 'Haftada Bir (Konser Takvimi Raporu)',
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

register_activation_hook( __FILE__, 'kt_init_db_option' );
function kt_init_db_option() {
	$existing = get_option( KT_EVENTS_OPT );
	if ( ! is_array( $existing ) ) {
		update_option( KT_EVENTS_OPT, array(), false );
	}
	$stats = get_option( KT_STATS_OPT );
	if ( ! is_array( $stats ) ) {
		update_option( KT_STATS_OPT, array(), false );
	}
	kt_sync_weekly_cron_schedule( true );
}

register_deactivation_hook( __FILE__, 'kt_deactivate_plugin' );
function kt_deactivate_plugin() {
	wp_clear_scheduled_hook( KT_CRON_HOOK );
}

require_once KT_PLUGIN_DIR . 'includes/blocks.php';

if ( is_admin() ) {
	require_once KT_PLUGIN_DIR . 'includes/admin-tools.php';
	require_once KT_PLUGIN_DIR . 'includes/admin-page.php';
}

add_action( 'wp_enqueue_scripts', 'kt_register_frontend_assets' );
function kt_register_frontend_assets() {
	$style_deps = array();

	// The Quicksand web font is loaded from Google Fonts; sites with strict privacy requirements can turn it off.
	$font_settings = kt_get_style_settings();
	if ( 'google' === $font_settings['font_mode'] ) {
		wp_register_style(
			'kt-quicksand-font',
			'https://fonts.googleapis.com/css2?family=Quicksand:wght@500;600;700;900&display=swap',
			array(),
			null
		);
		$style_deps[] = 'kt-quicksand-font';
	}

	wp_register_style(
		'kt-frontend-css',
		KT_PLUGIN_URL . 'assets/css/frontend.css',
		$style_deps,
		KT_PLUGIN_VER
	);

	if ( 'theme' === $font_settings['font_mode'] ) {
		// Use the active theme's typography instead of Quicksand.
		wp_add_inline_style( 'kt-frontend-css', '#ktConcertGridContainer, .kt-add-calendar-wrap { font-family: inherit; }' );
	}

	wp_register_script(
		'kt-frontend-js',
		KT_PLUGIN_URL . 'assets/js/frontend.js',
		array(),
		KT_PLUGIN_VER,
		true
	);

	wp_register_script(
		'kt-tracker-js',
		KT_PLUGIN_URL . 'assets/js/tracker.js',
		array(),
		KT_PLUGIN_VER,
		true
	);
}

/**
 * Enqueues the tracker script and the district context on the front end (page views, unique visitors, time on page and ticket clicks).
 */
function kt_enqueue_tracker_script( $district_key = '', $page_label = '', $track_view = false ) {
	static $track_page_view = false;

	if ( $track_view ) {
		$track_page_view = true;
	}

	$styles      = kt_get_style_settings();
	$raw_domains = isset( $styles['ticket_domains'] ) ? $styles['ticket_domains'] : '';
	$domains     = array_values(
		array_filter(
			array_map(
				function ( $item ) {
					return strtolower( trim( $item ) );
				},
				explode( ',', $raw_domains )
			)
		)
	);

	$utm = kt_get_utm_settings();

	wp_enqueue_script( 'kt-tracker-js' );
	wp_localize_script(
		'kt-tracker-js',
		'ktTrackerData',
		array(
			'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
			'defaultDistrict' => kt_normalize_district_key( $district_key ),
			'defaultLabel'    => sanitize_text_field( $page_label ),
			'trackView'       => $track_page_view ? 1 : 0,
			'todayStr'        => kt_get_site_date_str( 0 ),
			'ticketDomains'   => $domains,
			'defaultImgUrl'   => esc_url_raw( $styles['ticket_btn_img'] ),
			'captureUtm'      => $utm['capture_enabled'] ? 1 : 0,
			'utm'             => array(
				'enabled'  => $utm['ticket_enabled'] ? 1 : 0,
				'source'   => $utm['source'],
				'medium'   => $utm['medium'],
				'campaign' => $utm['campaign'],
			),
		)
	);
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
 * Adds one hit to the UTM breakdown of a district stats row.
 * The number of distinct source/medium/campaign combinations is capped per district; once the cap is reached,
 * new combinations are merged into a single "(other)" bucket so that arbitrary utm_* values sent by
 * a hostile client cannot bloat the stats option.
 */
function kt_add_utm_hit( $utm_rows, $utm, $type, $is_unique ) {
	if ( ! is_array( $utm_rows ) ) {
		$utm_rows = array();
	}

	$source   = isset( $utm['source'] ) ? $utm['source'] : '';
	$medium   = isset( $utm['medium'] ) ? $utm['medium'] : '';
	$campaign = isset( $utm['campaign'] ) ? $utm['campaign'] : '';

	if ( '' === $source && '' === $medium && '' === $campaign ) {
		return $utm_rows;
	}

	// Sanitized values only contain [a-z0-9._-], so "|" is a safe separator.
	$combo_key = $source . '|' . $medium . '|' . $campaign;

	if ( ! isset( $utm_rows[ $combo_key ] ) && count( $utm_rows ) >= KT_UTM_MAX_COMBOS ) {
		$combo_key = '(other)';
		$source    = '(other)';
		$medium    = '';
		$campaign  = '';
	}

	if ( ! isset( $utm_rows[ $combo_key ] ) ) {
		$utm_rows[ $combo_key ] = array(
			'source'   => $source,
			'medium'   => $medium,
			'campaign' => $campaign,
			'views'    => 0,
			'visitors' => 0,
			'clicks'   => 0,
			'click_visitors' => 0,
		);
	}

	if ( 'views' === $type ) {
		$utm_rows[ $combo_key ]['views'] = intval( $utm_rows[ $combo_key ]['views'] ) + 1;
		if ( $is_unique ) {
			$utm_rows[ $combo_key ]['visitors'] = intval( $utm_rows[ $combo_key ]['visitors'] ) + 1;
		}
	} elseif ( 'clicks' === $type ) {
		$utm_rows[ $combo_key ]['clicks'] = intval( $utm_rows[ $combo_key ]['clicks'] ) + 1;
		if ( $is_unique ) {
			$utm_rows[ $combo_key ]['click_visitors'] = ( isset( $utm_rows[ $combo_key ]['click_visitors'] ) ? intval( $utm_rows[ $combo_key ]['click_visitors'] ) : 0 ) + 1;
		}
	}

	return $utm_rows;
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

/**
 * Flattens the per-district UTM breakdown into rows (all time), sorted by unique visitors then page views.
 * Used by the admin statistics tab and by the HTML/CSV/e-mail reports.
 */
function kt_get_utm_report_rows( $stats ) {
	$utm_rows = array();

	if ( ! is_array( $stats ) ) {
		return $utm_rows;
	}

	foreach ( $stats as $district_key => $stat_row ) {
		if ( empty( $stat_row['utm'] ) || ! is_array( $stat_row['utm'] ) ) {
			continue;
		}
		foreach ( $stat_row['utm'] as $utm_item ) {
			$visitors       = isset( $utm_item['visitors'] ) ? intval( $utm_item['visitors'] ) : 0;
			$views          = isset( $utm_item['views'] ) ? intval( $utm_item['views'] ) : 0;
			$clicks         = isset( $utm_item['clicks'] ) ? intval( $utm_item['clicks'] ) : 0;
			$click_visitors = isset( $utm_item['click_visitors'] ) ? intval( $utm_item['click_visitors'] ) : 0;

			$utm_rows[] = array(
				'district'       => isset( $stat_row['label'] ) ? $stat_row['label'] : $district_key,
				'district_key'   => $district_key,
				'source'         => isset( $utm_item['source'] ) ? $utm_item['source'] : '',
				'medium'         => isset( $utm_item['medium'] ) ? $utm_item['medium'] : '',
				'campaign'       => isset( $utm_item['campaign'] ) ? $utm_item['campaign'] : '',
				'visitors'       => $visitors,
				'views'          => $views,
				'clicks'         => $clicks,
				'click_visitors' => $click_visitors,
				'ctr'            => $views > 0 ? round( ( $clicks / $views ) * 100, 1 ) : 0.0,
				'conversion'     => kt_conversion_rate( $click_visitors, $visitors ),
			);
		}
	}

	usort(
		$utm_rows,
		function ( $a, $b ) {
			if ( $a['visitors'] === $b['visitors'] ) {
				return $b['views'] - $a['views'];
			}
			return $b['visitors'] - $a['visitors'];
		}
	);

	return $utm_rows;
}

/**
 * Builds the summary statistics report data and its HTML output for the given number of days (e.g. 7, 14, 30, or all time).
 */
function kt_build_stats_report_bundle( $days_span = 7, $render_direct = false ) {
	kt_flush_stats_buffer();
	$stats = get_option( KT_STATS_OPT, array() );
	if ( ! is_array( $stats ) ) {
		$stats = array();
	}

	$days_span  = intval( $days_span );
	$is_alltime = ( $days_span <= 0 );
	$day_keys   = array();

	if ( ! $is_alltime ) {
		for ( $d = $days_span - 1; $d >= 0; $d-- ) {
			$day_keys[] = kt_get_site_date_str( -$d );
		}
		$period_label = $day_keys[0] . ' – ' . $day_keys[ count( $day_keys ) - 1 ] . ' (Son ' . $days_span . ' Gün)';
	} else {
		$period_label = 'Tüm Zamanlar (Kümülatif)';
	}

	$rows         = array();
	$sum_views    = 0;
	$sum_visitors = 0;
	$sum_clicks   = 0;
	$sum_click_visitors = 0;
	$sum_seconds  = 0;
	$sum_sessions = 0;

	foreach ( $stats as $key => $item ) {
		$all_v   = isset( $item['views'] ) ? intval( $item['views'] ) : 0;
		$all_vis = isset( $item['visitors'] ) ? intval( $item['visitors'] ) : 0;
		$all_c   = isset( $item['clicks'] ) ? intval( $item['clicks'] ) : 0;
		$all_cv  = isset( $item['click_visitors'] ) ? intval( $item['click_visitors'] ) : 0;
		$all_sec = isset( $item['total_seconds'] ) ? intval( $item['total_seconds'] ) : 0;
		$all_ses = isset( $item['duration_count'] ) ? intval( $item['duration_count'] ) : 0;

		if ( $is_alltime ) {
			$p_v   = $all_v;
			$p_vis = $all_vis;
			$p_c   = $all_c;
			$p_cv  = $all_cv;
			$p_sec = $all_sec;
			$p_ses = $all_ses;
		} else {
			$p_v   = 0;
			$p_vis = 0;
			$p_c   = 0;
			$p_cv  = 0;
			$p_sec = 0;
			$p_ses = 0;

			if ( isset( $item['daily'] ) && is_array( $item['daily'] ) ) {
				foreach ( $day_keys as $day_str ) {
					if ( isset( $item['daily'][ $day_str ] ) ) {
						$d_row  = $item['daily'][ $day_str ];
						$p_v   += isset( $d_row['views'] ) ? intval( $d_row['views'] ) : 0;
						$p_vis += isset( $d_row['visitors'] ) ? intval( $d_row['visitors'] ) : 0;
						$p_c   += isset( $d_row['clicks'] ) ? intval( $d_row['clicks'] ) : 0;
						$p_cv  += isset( $d_row['click_visitors'] ) ? intval( $d_row['click_visitors'] ) : 0;
						$p_sec += isset( $d_row['seconds'] ) ? intval( $d_row['seconds'] ) : 0;
						$p_ses += isset( $d_row['sessions'] ) ? intval( $d_row['sessions'] ) : 0;
					}
				}
			}
		}

		if ( $p_v > 0 || $p_c > 0 || $all_v > 0 ) {
			$rows[] = array(
				'key'          => $key,
				'label'        => isset( $item['label'] ) ? $item['label'] : $key,
				'views'        => $p_v,
				'visitors'     => $p_vis,
				'clicks'       => $p_c,
				'ctr'          => $p_v > 0 ? round( ( $p_c / $p_v ) * 100, 1 ) : 0.0,
				'click_visitors' => $p_cv,
				'conversion'   => kt_conversion_rate( $p_cv, $p_vis ),
				'avg_sec'      => $p_ses > 0 ? round( $p_sec / $p_ses ) : 0,
				'total_views'  => $all_v,
				'total_vis'    => $all_vis,
				'total_clicks' => $all_c,
			);
		}

		$sum_views    += $p_v;
		$sum_visitors += $p_vis;
		$sum_clicks   += $p_c;
		$sum_click_visitors += $p_cv;
		$sum_seconds  += $p_sec;
		$sum_sessions += $p_ses;
	}

	usort(
		$rows,
		function ( $a, $b ) {
			if ( $a['visitors'] === $b['visitors'] ) {
				return $b['views'] - $a['views'];
			}
			return $b['visitors'] - $a['visitors'];
		}
	);

	$utm_report_rows = kt_get_utm_report_rows( $stats );
	$utm_top_rows    = array_slice( $utm_report_rows, 0, 15 );

	$overall_avg_sec = $sum_sessions > 0 ? round( $sum_seconds / $sum_sessions ) : 0;
	$overall_ctr        = $sum_views > 0 ? number_format( ( $sum_clicks / $sum_views ) * 100, 1, ',', '.' ) : '0,0';
	$overall_conversion = number_format( kt_conversion_rate( $sum_click_visitors, $sum_visitors ), 1, ',', '.' );
	$site_name       = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$generated_at    = kt_get_site_datetime_str();

	if ( ! $render_direct ) {
		ob_start();
	}
	?>
	<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #1e293b; max-width: 760px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
		<div style="background: #1e293b; color: #ffffff; padding: 20px 24px;">
			<h2 style="margin: 0; font-size: 18px;"><?php echo esc_html( $site_name ); ?> — Konser Sayfası Performans Raporu</h2>
			<p style="margin: 6px 0 0 0; font-size: 13px; color: #cbd5e1;">Dönem: <?php echo esc_html( $period_label ); ?> &bull; Oluşturulma: <?php echo esc_html( $generated_at ); ?></p>
		</div>

		<div style="padding: 20px 24px;">
			<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 20px; border-collapse: collapse;">
				<tr>
					<td style="width: 25%; padding: 10px; background: #f8fafc; border: 1px solid #e2e8f0; text-align: center;">
						<div style="font-size: 11px; color: #64748b; font-weight: 600;">Tekil Ziyaretçi</div>
						<div style="font-size: 20px; font-weight: 700; color: #0f172a; margin-top: 4px;"><?php echo esc_html( number_format_i18n( $sum_visitors ) ); ?></div>
					</td>
					<td style="width: 25%; padding: 10px; background: #f8fafc; border: 1px solid #e2e8f0; text-align: center;">
						<div style="font-size: 11px; color: #64748b; font-weight: 600;">Sayfa Gösterimi</div>
						<div style="font-size: 20px; font-weight: 700; color: #0f172a; margin-top: 4px;"><?php echo esc_html( number_format_i18n( $sum_views ) ); ?></div>
					</td>
					<td style="width: 25%; padding: 10px; background: #f8fafc; border: 1px solid #e2e8f0; text-align: center;">
						<div style="font-size: 11px; color: #64748b; font-weight: 600;">Bilet Tıklaması (CTR)</div>
						<div style="font-size: 20px; font-weight: 700; color: #16a34a; margin-top: 4px;"><?php echo esc_html( number_format_i18n( $sum_clicks ) ); ?> (%<?php echo esc_html( $overall_ctr ); ?>)</div>
						<div style="font-size: 11px; color: #64748b; margin-top: 6px;">Tıklayan kişi / ziyaretçi: <strong><?php echo esc_html( number_format_i18n( $sum_click_visitors ) ); ?> / <?php echo esc_html( number_format_i18n( $sum_visitors ) ); ?> (%<?php echo esc_html( $overall_conversion ); ?>)</strong></div>
					</td>
					<td style="width: 25%; padding: 10px; background: #f8fafc; border: 1px solid #e2e8f0; text-align: center;">
						<div style="font-size: 11px; color: #64748b; font-weight: 600;">Ort. Kalma Süresi</div>
						<div style="font-size: 20px; font-weight: 700; color: #2563eb; margin-top: 4px;"><?php echo esc_html( kt_format_duration_human( $overall_avg_sec ) ); ?></div>
					</td>
				</tr>
			</table>

			<h3 style="font-size: 15px; margin: 0 0 10px 0; color: #0f172a;">İlçe Bazlı Performans Dağılımı</h3>
			<?php if ( empty( $rows ) ) : ?>
				<p style="color: #64748b; font-size: 13px;">Bu dönemde henüz kayıtlı ziyaret verisi oluşmamıştır.</p>
			<?php else : ?>
				<table width="100%" cellpadding="8" cellspacing="0" style="border-collapse: collapse; font-size: 13px; border: 1px solid #e2e8f0;">
					<thead>
						<tr style="background: #f1f5f9; text-align: left;">
							<th style="border-bottom: 1px solid #cbd5e1;">İlçe / Sayfa</th>
							<th style="border-bottom: 1px solid #cbd5e1; text-align: right;">Ziyaretçi</th>
							<th style="border-bottom: 1px solid #cbd5e1; text-align: right;">Gösterim</th>
							<th style="border-bottom: 1px solid #cbd5e1; text-align: right;">Bilet Tık</th>
							<th style="border-bottom: 1px solid #cbd5e1; text-align: right;">CTR</th>
							<th style="border-bottom: 1px solid #cbd5e1; text-align: right;">Tıklayan Kişi</th>
							<th style="border-bottom: 1px solid #cbd5e1; text-align: right;">Dönüşüm</th>
							<th style="border-bottom: 1px solid #cbd5e1; text-align: right;">Ort. Süre</th>
							<?php if ( ! $is_alltime ) : ?>
								<th style="border-bottom: 1px solid #cbd5e1; text-align: right;">Genel Tık</th>
							<?php endif; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $r ) : ?>
							<tr>
								<td style="border-bottom: 1px solid #e2e8f0;"><strong><?php echo esc_html( $r['label'] ); ?></strong></td>
								<td style="border-bottom: 1px solid #e2e8f0; text-align: right;"><?php echo esc_html( number_format_i18n( $r['visitors'] ) ); ?></td>
								<td style="border-bottom: 1px solid #e2e8f0; text-align: right;"><?php echo esc_html( number_format_i18n( $r['views'] ) ); ?></td>
								<td style="border-bottom: 1px solid #e2e8f0; text-align: right; color: #16a34a; font-weight: 600;"><?php echo esc_html( number_format_i18n( $r['clicks'] ) ); ?></td>
								<td style="border-bottom: 1px solid #e2e8f0; text-align: right;">%<?php echo esc_html( number_format( $r['ctr'], 1, ',', '.' ) ); ?></td>
								<td style="border-bottom: 1px solid #e2e8f0; text-align: right;"><?php echo esc_html( number_format_i18n( $r['click_visitors'] ) ); ?></td>
								<td style="border-bottom: 1px solid #e2e8f0; text-align: right;">%<?php echo esc_html( number_format( $r['conversion'], 1, ',', '.' ) ); ?></td>
								<td style="border-bottom: 1px solid #e2e8f0; text-align: right;"><?php echo esc_html( kt_format_duration_human( $r['avg_sec'] ) ); ?></td>
								<?php if ( ! $is_alltime ) : ?>
									<td style="border-bottom: 1px solid #e2e8f0; text-align: right; color: #64748b;"><?php echo esc_html( number_format_i18n( $r['total_clicks'] ) ); ?></td>
								<?php endif; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<?php if ( ! empty( $utm_top_rows ) ) : ?>
				<h3 style="font-size: 15px; margin: 24px 0 4px 0; color: #0f172a;">Trafik Kaynakları (UTM)</h3>
				<p style="margin: 0 0 10px 0; font-size: 12px; color: #64748b;">Tüm zamanlar; yalnızca UTM parametreli ziyaretler<?php echo count( $utm_report_rows ) > count( $utm_top_rows ) ? ' (ilk ' . intval( count( $utm_top_rows ) ) . ' satır)' : ''; ?>.</p>
				<table width="100%" cellpadding="8" cellspacing="0" style="border-collapse: collapse; font-size: 13px; border: 1px solid #e2e8f0;">
					<thead>
						<tr style="background: #f1f5f9; text-align: left;">
							<th style="border-bottom: 1px solid #cbd5e1;">Kaynak / Ortam / Kampanya</th>
							<th style="border-bottom: 1px solid #cbd5e1;">İlçe</th>
							<th style="border-bottom: 1px solid #cbd5e1; text-align: right;">Ziyaretçi</th>
							<th style="border-bottom: 1px solid #cbd5e1; text-align: right;">Tıklayan</th>
							<th style="border-bottom: 1px solid #cbd5e1; text-align: right;">Dönüşüm</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $utm_top_rows as $u ) : ?>
							<tr>
								<td style="border-bottom: 1px solid #e2e8f0;"><?php echo esc_html( implode( ' / ', array_map( function ( $part ) { return '' !== $part ? $part : '—'; }, array( $u['source'], $u['medium'], $u['campaign'] ) ) ) ); ?></td>
								<td style="border-bottom: 1px solid #e2e8f0;"><?php echo esc_html( $u['district'] ); ?></td>
								<td style="border-bottom: 1px solid #e2e8f0; text-align: right;"><?php echo esc_html( number_format_i18n( $u['visitors'] ) ); ?></td>
								<td style="border-bottom: 1px solid #e2e8f0; text-align: right; color: #16a34a; font-weight: 600;"><?php echo esc_html( number_format_i18n( $u['click_visitors'] ) ); ?></td>
								<td style="border-bottom: 1px solid #e2e8f0; text-align: right;">%<?php echo esc_html( number_format( $u['conversion'], 1, ',', '.' ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<p style="margin-top: 20px; font-size: 12px; color: #64748b;">
				Detaylı günlük grafikler için WordPress yönetim panelindeki <a href="<?php echo esc_url( admin_url( 'admin.php?page=konser-takvimi&tab=stats' ) ); ?>">Konser Takvimi &rarr; İstatistikler &amp; Grafik</a> sayfasını ziyaret edebilirsiniz.
			</p>
		</div>
	</div>
	<?php
	$html_card = ! $render_direct ? ob_get_clean() : '';

	return array(
		'period_label'    => $period_label,
		'generated_at'    => $generated_at,
		'site_name'       => $site_name,
		'sum_visitors'    => $sum_visitors,
		'sum_views'       => $sum_views,
		'sum_clicks'      => $sum_clicks,
		'overall_ctr'     => $overall_ctr,
		'sum_click_visitors' => $sum_click_visitors,
		'overall_conversion' => $overall_conversion,
		'overall_avg_sec' => $overall_avg_sec,
		'rows'            => $rows,
		'utm_rows'        => $utm_report_rows,
		'html_card'       => $html_card,
	);
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
	$subject = sprintf( '[%s] Haftalık Konser Sayfası İstatistik Raporu (%s)', $bundle['site_name'], $bundle['period_label'] );
	$headers = array( 'Content-Type: text/html; charset=UTF-8' );
	$body    = $bundle['html_card'];

	// Optional alert block for pages whose visitors rarely click the ticket button.
	if ( ! empty( $styles['alert_enabled'] ) ) {
		$low = kt_get_low_conversion_rows( $bundle['rows'], $styles['alert_min_conversion'], $styles['alert_min_visitors'] );
		if ( ! empty( $low ) ) {
			$subject .= ' — Düşük dönüşüm uyarısı';
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
	$html .= '<strong>' . esc_html( sprintf( 'Dönüşüm oranı %%%s altında kalan sayfalar (en az %d tekil ziyaretçi):', rtrim( rtrim( number_format( (float) $min_conversion, 1, ',', '' ), '0' ), ',' ), intval( $min_visitors ) ) ) . '</strong><ul style="margin:8px 0 0 18px;padding:0;">';
	foreach ( $low_rows as $row ) {
		$html .= '<li>' . esc_html( sprintf( '%s — %d tekil ziyaretçi, %d tıklayan, dönüşüm %%%s', $row['label'], $row['visitors'], $row['click_visitors'], number_format( $row['conversion'], 1, ',', '.' ) ) ) . '</li>';
	}
	$html .= '</ul></div>';
	return $html;
}

/**
 * Returns all upcoming active concert records for a given district / slug.
 */
function kt_get_upcoming_events_for_target( $target ) {
	$target_slug = sanitize_title( $target );
	if ( empty( $target_slug ) ) {
		return array();
	}

	$base_target = preg_replace( '/-(konseri|konser)$/', '', $target_slug );
	$all_events  = get_option( KT_EVENTS_OPT, array() );
	if ( ! is_array( $all_events ) || empty( $all_events ) ) {
		return array();
	}

	$now_ts  = kt_get_site_timestamp();
	$matched = array();

	foreach ( $all_events as $event ) {
		$end_ts = kt_get_event_end_timestamp( $event );
		if ( false === $end_ts || $end_ts < $now_ts ) {
			continue;
		}

		$ev_slug      = isset( $event['slug'] ) ? sanitize_title( $event['slug'] ) : '';
		$ev_city_slug = isset( $event['city'] ) ? sanitize_title( $event['city'] ) : '';
		$ev_base_slug = preg_replace( '/-(konseri|konser)$/', '', $ev_slug );

		if (
			$ev_slug === $target_slug ||
			$ev_city_slug === $target_slug ||
			( ! empty( $base_target ) && ( $ev_base_slug === $base_target || $ev_city_slug === $base_target ) )
		) {
			$matched[] = $event;
		}
	}

	usort( $matched, 'kt_sort_events_asc' );
	return $matched;
}

/**
 * Converts a single concert record into a Google MusicEvent JSON-LD object.
 */
function kt_build_music_event_schema_item( $event, $performer_name, $ticket_url = '', $image_url = '', $page_url = '' ) {
	if ( empty( $event['datetime'] ) || empty( $event['city'] ) ) {
		return null;
	}

	$city_name = sanitize_text_field( $event['city'] );
	$ev_slug   = isset( $event['slug'] ) ? sanitize_title( $event['slug'] ) : sanitize_title( $city_name . '-konseri' );
	$event_url = ! empty( $page_url ) ? $page_url : home_url( '/' . ltrim( $ev_slug, '/' ) . '/' );

	$start_iso = sanitize_text_field( $event['datetime'] );
	$dt_parts  = explode( 'T', $start_iso );
	$last_start = $start_iso;

	if ( ! empty( $event['time2'] ) && preg_match( '/^\d{2}:\d{2}$/', $event['time2'] ) && ! empty( $dt_parts[0] ) ) {
		$last_start = kt_second_session_iso( $event );
	}

	// Google expects endDate after startDate: assume one hour after the start of the last session (same as the calendar entries).
	$end_iso = $last_start;
	try {
		$end_dt = new DateTime( $last_start );
		$end_dt->modify( '+1 hour' );
		$end_iso = $end_dt->format( 'Y-m-d\TH:i:sP' );
	} catch ( Exception $e ) {
		$end_iso = $last_start;
	}

	$item = array(
		'@context'            => 'https://schema.org',
		'@type'               => 'MusicEvent',
		'name'                => $performer_name . ' — ' . $city_name . ' Konseri',
		'description'         => $performer_name . ' ' . $city_name . ' konseri etkinlik ve bilet bilgileri.',
		'startDate'           => $start_iso,
		'endDate'             => $end_iso,
		'eventStatus'         => 'https://schema.org/EventScheduled',
		'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
		'location'            => array(
			'@type'   => 'Place',
			'name'    => $city_name,
			'address' => array(
				'@type'           => 'PostalAddress',
				'addressLocality' => $city_name,
				'addressCountry'  => 'TR',
			),
		),
		'performer'           => array(
			'@type' => 'MusicGroup',
			'name'  => $performer_name,
		),
		'organizer'           => array(
			'@type' => 'Organization',
			'name'  => $performer_name,
			'url'   => home_url( '/' ),
		),
		'url'                 => esc_url_raw( $event_url ),
	);

	if ( ! empty( $image_url ) ) {
		$item['image'] = array( esc_url_raw( $image_url ) );
	}

	$offer_url      = ! empty( $ticket_url ) ? $ticket_url : $event_url;
	$item['offers'] = array(
		'@type'         => 'Offer',
		'url'           => esc_url_raw( $offer_url ),
		'availability'  => 'https://schema.org/InStock',
		'validFrom'     => kt_local_to_iso( gmdate( 'Y' ) . '-01-01', '00:00' ),
		'priceCurrency' => 'TRY',
	);

	return $item;
}

/**
 * Outputs Google MusicEvent JSON-LD in <head> on pages that contain the Concert Calendar or District Redirector blocks/shortcodes.
 */
add_action( 'wp_head', 'kt_output_music_event_json_ld', 20 );
function kt_output_music_event_json_ld() {
	if ( is_admin() || ! is_singular() ) {
		return;
	}

	$styles = kt_get_style_settings();
	if ( empty( $styles['schema_enabled'] ) ) {
		return;
	}

	global $post;
	if ( ! $post || empty( $post->post_content ) ) {
		return;
	}

	$has_calendar = has_block( 'konser-takvimi/takvim', $post )
		|| has_shortcode( $post->post_content, 'konser_takvimi' )
		|| has_shortcode( $post->post_content, 'subadap_konserler' );
	$has_district = has_block( 'konser-takvimi/ilce-yonlendir', $post )
		|| has_shortcode( $post->post_content, 'konser_ilce_yonlendir' );

	if ( ! $has_calendar && ! $has_district ) {
		return;
	}

	$performer_name = ! empty( $styles['schema_performer'] )
		? sanitize_text_field( $styles['schema_performer'] )
		: wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

	$ticket_url = ! empty( $styles['ticket_btn_url'] ) ? $styles['ticket_btn_url'] : '';
	$image_url  = get_the_post_thumbnail_url( $post->ID, 'full' );
	if ( empty( $image_url ) && ! empty( $styles['ticket_btn_img'] ) ) {
		$image_url = $styles['ticket_btn_img'];
	}

	$schema_items = array();
	$seen_keys    = array();

	if ( $has_district ) {
		$district_keys = array();

		if ( has_block( 'konser-takvimi/ilce-yonlendir', $post ) ) {
			$blocks = parse_blocks( $post->post_content );
			foreach ( $blocks as $blk ) {
				if ( isset( $blk['blockName'] ) && 'konser-takvimi/ilce-yonlendir' === $blk['blockName'] ) {
					$district_keys[] = ! empty( $blk['attrs']['district'] ) ? $blk['attrs']['district'] : $post->post_name;
				}
			}
		}

		$pattern = get_shortcode_regex( array( 'konser_ilce_yonlendir' ) );
		if ( preg_match_all( '/' . $pattern . '/s', $post->post_content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$atts            = shortcode_atts( array( 'district' => '' ), shortcode_parse_atts( $match[3] ), 'konser_ilce_yonlendir' );
				$district_keys[] = ! empty( $atts['district'] ) ? $atts['district'] : $post->post_name;
			}
		}

		foreach ( array_unique( $district_keys ) as $lookup_key ) {
			$events = kt_get_upcoming_events_for_target( $lookup_key );
			foreach ( $events as $ev ) {
				$dedup = $ev['datetime'] . '|' . $ev['slug'];
				if ( isset( $seen_keys[ $dedup ] ) ) {
					continue;
				}
				$seen_keys[ $dedup ] = true;
				$built               = kt_build_music_event_schema_item( $ev, $performer_name, $ticket_url, $image_url, get_permalink( $post->ID ) );
				if ( $built ) {
					$schema_items[] = $built;
				}
			}
		}
	}

	if ( $has_calendar ) {
		$all_events = get_option( KT_EVENTS_OPT, array() );
		if ( is_array( $all_events ) ) {
			$now_ts   = kt_get_site_timestamp();
			$upcoming = array();
			foreach ( $all_events as $ev ) {
				$end_ts = kt_get_event_end_timestamp( $ev );
				if ( false !== $end_ts && $end_ts >= $now_ts ) {
					$upcoming[] = $ev;
				}
			}
			usort( $upcoming, 'kt_sort_events_asc' );
			$max_items = max( 1, intval( $styles['max_items'] ) );
			$upcoming  = array_slice( $upcoming, 0, $max_items );

			foreach ( $upcoming as $ev ) {
				$dedup = $ev['datetime'] . '|' . $ev['slug'];
				if ( isset( $seen_keys[ $dedup ] ) ) {
					continue;
				}
				$seen_keys[ $dedup ] = true;
				$built               = kt_build_music_event_schema_item( $ev, $performer_name, $ticket_url, $image_url, '' );
				if ( $built ) {
					$schema_items[] = $built;
				}
			}
		}
	}

	if ( empty( $schema_items ) ) {
		return;
	}

	$payload = ( 1 === count( $schema_items ) ) ? $schema_items[0] : $schema_items;
	echo "\n<!-- Konser Takvimi Google MusicEvent JSON-LD -->\n";
	echo '<script type="application/ld+json">' . wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
}

/**
 * Checks whether a given district / slug has an upcoming active concert.
 */
function kt_has_upcoming_concert_for_target( $target ) {
	$events = kt_get_upcoming_events_for_target( $target );
	return ! empty( $events );
}

/**
 * Resolves the redirect target URL (attribute -> settings -> /konserler).
 */
function kt_resolve_redirect_url( $custom_target = '' ) {
	$styles         = kt_get_style_settings();
	$default_target = ! empty( $styles['redirect_target'] ) ? $styles['redirect_target'] : '/konserler';
	$default_url    = kt_redirect_path_to_url( $default_target );

	if ( empty( $custom_target ) ) {
		return $default_url;
	}

	// A target set in a block/shortcode may only point to this site or to the host chosen in the plugin settings.
	// Anything else falls back to the default target, so content authors cannot create open redirects.
	$custom_url   = kt_redirect_path_to_url( $custom_target );
	$custom_host  = strtolower( (string) wp_parse_url( $custom_url, PHP_URL_HOST ) );
	$allowed_host = array(
		strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ),
		strtolower( (string) wp_parse_url( $default_url, PHP_URL_HOST ) ),
	);

	return in_array( $custom_host, $allowed_host, true ) ? $custom_url : $default_url;
}

/**
 * Converts a redirect path (or absolute http/https URL) into an absolute URL.
 */
function kt_redirect_path_to_url( $path ) {
	if ( 0 === strpos( $path, 'http://' ) || 0 === strpos( $path, 'https://' ) ) {
		return esc_url_raw( $path );
	}
	return home_url( '/' . ltrim( $path, '/' ) );
}

/**
 * Detects the district redirector block or shortcode before any page HTML is sent:
 * redirects to the configured page with a 302 when there is no upcoming concert.
 */
add_action( 'template_redirect', 'kt_handle_district_redirect_shortcode' );
function kt_handle_district_redirect_shortcode() {
	if ( is_admin() || ! is_singular() ) {
		return;
	}

	global $post;
	if ( ! $post || empty( $post->post_content ) ) {
		return;
	}

	$has_redirect_block = has_block( 'konser-takvimi/ilce-yonlendir', $post );
	$has_redirect_sc    = has_shortcode( $post->post_content, 'konser_ilce_yonlendir' );

	if ( ! $has_redirect_block && ! $has_redirect_sc ) {
		return;
	}

	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}

	if ( current_user_can( 'edit_pages' ) ) {
		return;
	}

	$targets_to_check = array();

	if ( $has_redirect_block ) {
		$blocks = parse_blocks( $post->post_content );
		foreach ( $blocks as $blk ) {
			if ( isset( $blk['blockName'] ) && 'konser-takvimi/ilce-yonlendir' === $blk['blockName'] ) {
				$targets_to_check[] = array(
					'district'        => isset( $blk['attrs']['district'] ) ? $blk['attrs']['district'] : '',
					'redirect_target' => isset( $blk['attrs']['redirect_target'] ) ? $blk['attrs']['redirect_target'] : '',
				);
			}
		}
	}

	if ( $has_redirect_sc ) {
		$pattern = get_shortcode_regex( array( 'konser_ilce_yonlendir' ) );
		if ( preg_match_all( '/' . $pattern . '/s', $post->post_content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$atts               = shortcode_parse_atts( $match[3] );
				$atts               = shortcode_atts(
					array(
						'district'        => '',
						'redirect_target' => '',
					),
					$atts,
					'konser_ilce_yonlendir'
				);
				$targets_to_check[] = $atts;
			}
		}
	}

	foreach ( $targets_to_check as $item_atts ) {
		$lookup_key = ! empty( $item_atts['district'] ) ? $item_atts['district'] : $post->post_name;

		if ( ! kt_has_upcoming_concert_for_target( $lookup_key ) ) {
			nocache_headers();
			$redirect_url = kt_resolve_redirect_url( $item_atts['redirect_target'] );

			// wp_safe_redirect() only accepts the site's own host by default; allow the (already validated) target host.
			$target_host = wp_parse_url( $redirect_url, PHP_URL_HOST );
			add_filter(
				'allowed_redirect_hosts',
				function ( $hosts ) use ( $target_host ) {
					$hosts[] = $target_host;
					return $hosts;
				}
			);

			wp_safe_redirect( $redirect_url, 302 );
			exit;
		}
	}
}

/**
 * Builds the "Add to Calendar" (Google Calendar & Apple/Outlook .ics) button HTML for a district concert page.
 * UTM tags are added to the page link inside the event description, per channel and per session.
 */
function kt_render_add_to_calendar_widget( $events, $page_url = '', $district_key = '' ) {
	if ( empty( $events ) || ! is_array( $events ) ) {
		return '';
	}

	$styles         = kt_get_style_settings();
	$performer_name = ! empty( $styles['schema_performer'] )
		? sanitize_text_field( $styles['schema_performer'] )
		: wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

	$months_tr = array( '', 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık' );
	$tz        = kt_get_site_timezone();
	$utc_tz    = new DateTimeZone( 'UTC' );

	$sessions = array();
	foreach ( $events as $ev ) {
		if ( empty( $ev['datetime'] ) || empty( $ev['city'] ) ) {
			continue;
		}
		$city_name = sanitize_text_field( $ev['city'] );
		$title     = $performer_name . ' — ' . $city_name . ' Konseri';
		$ev_slug   = isset( $ev['slug'] ) ? sanitize_title( $ev['slug'] ) : sanitize_title( $city_name . '-konseri' );
		$loc_url   = ! empty( $page_url ) ? $page_url : home_url( '/' . ltrim( $ev_slug, '/' ) . '/' );
		$utm_key   = ! empty( $district_key ) ? $district_key : $ev_slug;
		$desc_base = $performer_name . ' ' . $city_name . ' konseri. Detaylar ve bilet: ';

		// Each channel (Google Calendar link vs .ics file) and each session gets its own UTM tags on the page link.
		$desc_gcal1 = $desc_base . kt_append_utm_params( $loc_url, kt_get_calendar_utm_params( 'google-calendar', $utm_key, 1 ) );
		$desc_ics1  = $desc_base . kt_append_utm_params( $loc_url, kt_get_calendar_utm_params( 'ics-file', $utm_key, 1 ) );
		$desc_gcal2 = $desc_base . kt_append_utm_params( $loc_url, kt_get_calendar_utm_params( 'google-calendar', $utm_key, 2 ) );
		$desc_ics2  = $desc_base . kt_append_utm_params( $loc_url, kt_get_calendar_utm_params( 'ics-file', $utm_key, 2 ) );

		try {
			$start1 = new DateTime( $ev['datetime'], $tz );
			$end1   = clone $start1;
			$end1->modify( '+1 hour' );

			$s1_utc = clone $start1;
			$s1_utc->setTimezone( $utc_tz );
			$e1_utc = clone $end1;
			$e1_utc->setTimezone( $utc_tz );

			$day_label  = intval( $start1->format( 'j' ) ) . ' ' . $months_tr[ intval( $start1->format( 'n' ) ) ];
			$time1_lbl  = $start1->format( 'H.i' );
			$gcal1_url  = add_query_arg(
				array(
					'action'   => 'TEMPLATE',
					'text'     => $title,
					'dates'    => $s1_utc->format( 'Ymd\THis\Z' ) . '/' . $e1_utc->format( 'Ymd\THis\Z' ),
					'details'  => $desc_gcal1,
					'location' => $city_name,
				),
				'https://calendar.google.com/calendar/render'
			);

			$sessions[] = array(
				'label'     => $day_label . ' — Saat ' . $time1_lbl,
				'title'     => $title,
				'location'  => $city_name,
				'desc'      => $desc_ics1,
				'start_utc' => $s1_utc->format( 'Ymd\THis\Z' ),
				'end_utc'   => $e1_utc->format( 'Ymd\THis\Z' ),
				'gcal_url'  => $gcal1_url,
				'filename'  => sanitize_title( $city_name . '-' . $start1->format( 'Y-m-d-Hi' ) ) . '.ics',
			);

			if ( ! empty( $ev['time2'] ) && preg_match( '/^\d{2}:\d{2}$/', $ev['time2'] ) ) {
				$start2 = new DateTime( $start1->format( 'Y-m-d' ) . ' ' . $ev['time2'] . ':00', $tz );
				$end2   = clone $start2;
				$end2->modify( '+1 hour' );

				$s2_utc = clone $start2;
				$s2_utc->setTimezone( $utc_tz );
				$e2_utc = clone $end2;
				$e2_utc->setTimezone( $utc_tz );

				$time2_lbl = $start2->format( 'H.i' );
				$gcal2_url = add_query_arg(
					array(
						'action'   => 'TEMPLATE',
						'text'     => $title . ' (2. Seans)',
						'dates'    => $s2_utc->format( 'Ymd\THis\Z' ) . '/' . $e2_utc->format( 'Ymd\THis\Z' ),
						'details'  => $desc_gcal2,
						'location' => $city_name,
					),
					'https://calendar.google.com/calendar/render'
				);

				$sessions[] = array(
					'label'     => $day_label . ' — Saat ' . $time2_lbl . ' (2. Seans)',
					'title'     => $title . ' (2. Seans)',
					'location'  => $city_name,
					'desc'      => $desc_ics2,
					'start_utc' => $s2_utc->format( 'Ymd\THis\Z' ),
					'end_utc'   => $e2_utc->format( 'Ymd\THis\Z' ),
					'gcal_url'  => $gcal2_url,
					'filename'  => sanitize_title( $city_name . '-' . $start2->format( 'Y-m-d-Hi' ) ) . '.ics',
				);
			}
		} catch ( Exception $e ) {
			continue;
		}
	}

	if ( empty( $sessions ) ) {
		return '';
	}

	wp_enqueue_style( 'kt-frontend-css' );
	wp_enqueue_script( 'kt-frontend-js' );

	ob_start();
	?>
	<div class="kt-add-calendar-wrap">
		<details class="kt-cal-dropdown">
			<summary class="kt-cal-trigger">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
				<span>Takvime Ekle</span>
				<svg class="kt-cal-chevron" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"></polyline></svg>
			</summary>
			<div class="kt-cal-menu">
				<?php foreach ( $sessions as $ses ) : ?>
					<div class="kt-cal-session-group">
						<?php if ( count( $sessions ) > 1 ) : ?>
							<div class="kt-cal-session-title"><?php echo esc_html( $ses['label'] ); ?></div>
						<?php endif; ?>
						<a href="<?php echo esc_url( $ses['gcal_url'] ); ?>" target="_blank" rel="noopener noreferrer" class="kt-cal-option">
							Google Takvim'e Ekle
						</a>
						<button
							type="button"
							class="kt-cal-option kt-cal-ics-btn"
							data-title="<?php echo esc_attr( $ses['title'] ); ?>"
							data-location="<?php echo esc_attr( $ses['location'] ); ?>"
							data-desc="<?php echo esc_attr( $ses['desc'] ); ?>"
							data-start="<?php echo esc_attr( $ses['start_utc'] ); ?>"
							data-end="<?php echo esc_attr( $ses['end_utc'] ); ?>"
							data-filename="<?php echo esc_attr( $ses['filename'] ); ?>"
						>
							Apple / Outlook Takvimi (.ics)
						</button>
					</div>
				<?php endforeach; ?>
			</div>
		</details>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * @deprecated 3.6.0 Use the 'konser-takvimi/ilce-yonlendir' Gutenberg block instead.
 */
add_shortcode( 'konser_ilce_yonlendir', 'kt_district_redirect_shortcode_output' );
function kt_district_redirect_shortcode_output( $atts ) {
	$atts = shortcode_atts(
		array(
			'district'             => '',
			'redirect_target'      => '',
			'show_calendar_button' => '1',
		),
		$atts,
		'konser_ilce_yonlendir'
	);

	global $post;
	$lookup_key      = ! empty( $atts['district'] ) ? $atts['district'] : ( $post ? $post->post_name : '' );
	$page_label      = $post ? $post->post_title : '';
	$page_url        = $post ? get_permalink( $post->ID ) : '';
	$upcoming_events = kt_get_upcoming_events_for_target( $lookup_key );
	$has_event       = ! empty( $upcoming_events );
	$redirect_url    = kt_resolve_redirect_url( $atts['redirect_target'] );
	$show_cal_btn    = ( '0' !== strval( $atts['show_calendar_button'] ) && false !== $atts['show_calendar_button'] );

	if ( current_user_can( 'edit_pages' ) ) {
		if ( $has_event ) {
			$cal_preview = $show_cal_btn ? kt_render_add_to_calendar_widget( $upcoming_events, $page_url, $lookup_key ) : '';
			return '<div style="padding:10px 14px;margin:10px 0;background:#f0fdf4;border:1px solid #86efac;border-radius:6px;color:#166534;font-size:13px;"><strong>Konser Takvimi Bilgi:</strong> Bu ilçe (<code>' . esc_html( $lookup_key ) . '</code>) için yaklaşan konser aktif olduğundan ziyaretçiler bu sayfada kalacaktır (yönetici girişi yapıldığı için ziyaret ve tıklamalar sayaca eklenmez).</div>' . $cal_preview;
		}
		return '<div style="padding:10px 14px;margin:10px 0;background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;color:#991b1b;font-size:13px;"><strong>Konser Takvimi Bilgi:</strong> Bu ilçe (<code>' . esc_html( $lookup_key ) . '</code>) için yaklaşan konser bulunmadığından normal ziyaretçiler otomatik olarak <code>' . esc_html( $redirect_url ) . '</code> adresine yönlendirilecektir (yönetici olduğunuz için yönlendirilmediniz).</div>';
	}

	if ( ! $has_event ) {
		return '<script>window.location.replace(' . wp_json_encode( esc_url_raw( $redirect_url ) ) . ');</script>';
	}

	kt_enqueue_tracker_script( $lookup_key, $page_label, true );

	return $show_cal_btn ? kt_render_add_to_calendar_widget( $upcoming_events, $page_url, $lookup_key ) : '';
}

/**
 * @deprecated 3.6.0 Use the 'konser-takvimi/bilet-butonu' Gutenberg block instead.
 */
add_shortcode( 'konser_bilet_butonu', 'kt_ticket_button_shortcode' );
function kt_ticket_button_shortcode( $atts ) {
	$styles = kt_get_style_settings();

	$atts = shortcode_atts(
		array(
			'url'         => $styles['ticket_btn_url'],
			'image'       => $styles['ticket_btn_img'],
			'district'    => '',
			'alt'         => 'Bilet Al',
			'link_target' => '',
			'session'     => '',
		),
		$atts,
		'konser_bilet_butonu'
	);

	// Optional per-session attribution: "1" or "2" marks this button as the first/second session's ticket button.
	$session = in_array( (string) $atts['session'], array( '1', '2' ), true ) ? (string) $atts['session'] : '';

	$target_url = ! empty( $atts['url'] ) ? $atts['url'] : $styles['ticket_btn_url'];
	if ( empty( $target_url ) ) {
		return '';
	}

	global $post;
	$district_key = ! empty( $atts['district'] ) ? $atts['district'] : ( $post ? $post->post_name : 'general' );
	$page_label   = $post ? $post->post_title : '';
	$img_url      = ! empty( $atts['image'] ) ? $atts['image'] : $styles['ticket_btn_img'];

	kt_enqueue_tracker_script( $district_key, $page_label, false );

	// Tag the outbound ticket URL with UTM parameters (existing utm_* values on the URL are kept).
	$target_url = kt_append_utm_params( $target_url, kt_get_ticket_utm_params( $district_key, '' !== $session ? 'ticket-button-s' . $session : 'ticket-button' ) );

	$target_attr = '';
	if ( ! empty( $atts['link_target'] ) ) {
		$target_attr = ' target="' . esc_attr( $atts['link_target'] ) . '" rel="noopener noreferrer"';
	}

	$attachment_id = attachment_url_to_postid( $img_url );
	if ( $attachment_id ) {
		$img_html = wp_get_attachment_image(
			$attachment_id,
			'large',
			false,
			array(
				'fetchpriority' => 'high',
				'decoding'      => 'async',
				'alt'           => $atts['alt'],
			)
		);
	} else {
		$img_html = '<img fetchpriority="high" decoding="async" width="1024" height="281" src="' . esc_url( $img_url ) . '" alt="' . esc_attr( $atts['alt'] ) . '">';
	}

	return sprintf(
		'<figure class="wp-block-image size-large"><a href="%s" class="kt-ticket-btn-link" data-kt-district="%s" data-kt-label="%s"%s%s>%s</a></figure>',
		esc_url( $target_url ),
		esc_attr( kt_normalize_district_key( $district_key ) ),
		esc_attr( $page_label ),
		'' !== $session ? ' data-kt-session="' . esc_attr( $session ) . '"' : '',
		$target_attr,
		$img_html
	);
}

/**
 * @deprecated 3.6.0 Use the 'konser-takvimi/takvim' Gutenberg block instead.
 */
add_shortcode( 'konser_takvimi', 'kt_concert_grid_shortcode' );
add_shortcode( 'subadap_konserler', 'kt_concert_grid_shortcode' );
function kt_concert_grid_shortcode() {
	$all_events      = get_option( KT_EVENTS_OPT, array() );
	$upcoming_events = array();
	$styles          = kt_get_style_settings();

	if ( is_array( $all_events ) ) {
		$now_ts = kt_get_site_timestamp();
		foreach ( $all_events as $event ) {
			$end_ts = kt_get_event_end_timestamp( $event );
			if ( false !== $end_ts && $end_ts >= $now_ts ) {
				$end_iso = ! empty( $event['time2'] ) ? kt_second_session_iso( $event ) : $event['datetime'];

				$upcoming_events[] = array(
					'datetime'    => sanitize_text_field( $event['datetime'] ),
					'endDatetime' => sanitize_text_field( $end_iso ),
					'time2'       => isset( $event['time2'] ) ? sanitize_text_field( $event['time2'] ) : '',
					'city'        => sanitize_text_field( $event['city'] ),
					'slug'        => sanitize_title( $event['slug'] ),
				);
			}
		}
	}

	wp_enqueue_style( 'kt-frontend-css' );

	$custom_css = sprintf(
		"#ktConcertGridContainer {
			--kt-radius: %dpx;
			--kt-height: %dpx;
			--kt-gap: %dpx;
			--kt-min-w: %dpx;
			--kt-date-text: %s;
			--kt-city-text: %s;
		}\n",
		intval( $styles['border_radius'] ),
		intval( $styles['badge_height'] ),
		intval( $styles['grid_gap'] ),
		intval( $styles['card_min_w'] ),
		esc_attr( $styles['date_text'] ),
		esc_attr( $styles['city_text'] )
	);

	foreach ( $styles['palettes'] as $idx => $pair ) {
		$num         = $idx + 1;
		$custom_css .= sprintf(
			"#ktConcertGridContainer .style-%d .date-part { background-color: %s; }\n#ktConcertGridContainer .style-%d .city-part { background-color: %s; }\n",
			$num,
			esc_attr( $pair['date'] ),
			$num,
			esc_attr( $pair['city'] )
		);
	}

	wp_add_inline_style( 'kt-frontend-css', $custom_css );
	wp_enqueue_script( 'kt-frontend-js' );
	wp_localize_script(
		'kt-frontend-js',
		'ktConcertData',
		array(
			'events'   => $upcoming_events,
			'baseUrl'  => trailingslashit( home_url() ),
			'maxItems' => intval( $styles['max_items'] ),
			'timezone' => $styles['timezone'],
		)
	);

	return '<div id="ktConcertGridContainer"><div class="kt-concert-grid" id="ktConcertGrid"></div></div>';
}
