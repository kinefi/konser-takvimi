<?php
/**
 * Plugin Name: Konser Takvimi
 * Description: Veritabanı tabanlı, çift seans destekli, geçmiş konserleri otomatik arşivleyen, ilçe sayfası yönlendirme, günlük ziyaretçi/süre analitiği, grafik ve haftalık e-posta raporu sunan WordPress eklentisi.
 * Version: 3.4.0
 * Author: Şubadap Çocuk
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'KT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'KT_PLUGIN_VER', '3.4.0' );
define( 'KT_EVENTS_OPT', 'subadap_konserler_list' );
define( 'KT_STYLES_OPT', 'subadap_konserler_styles' );
define( 'KT_STATS_OPT', 'subadap_konserler_stats' );
define( 'KT_CRON_HOOK', 'kt_weekly_stats_report_event' );

function kt_get_istanbul_timestamp() {
	$tz  = new DateTimeZone( 'Europe/Istanbul' );
	$now = new DateTime( 'now', $tz );
	return $now->getTimestamp();
}

function kt_get_istanbul_datetime_str() {
	$tz  = new DateTimeZone( 'Europe/Istanbul' );
	$now = new DateTime( 'now', $tz );
	return $now->format( 'Y-m-d H:i' );
}

function kt_get_istanbul_date_str( $offset_days = 0 ) {
	$tz  = new DateTimeZone( 'Europe/Istanbul' );
	$now = new DateTime( 'now', $tz );
	if ( 0 !== intval( $offset_days ) ) {
		$now->modify( intval( $offset_days ) . ' days' );
	}
	return $now->format( 'Y-m-d' );
}

/**
 * Bir konser kaydının son geçerlilik zaman damgasını döndürür.
 * 2. seans varsa onun saatini, yoksa 1. seansın saatini esas alır.
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
		$dt_parts = explode( 'T', $event['datetime'] );
		if ( ! empty( $dt_parts[0] ) ) {
			$second_ts = strtotime( $dt_parts[0] . 'T' . $event['time2'] . ':00+03:00' );
			if ( false !== $second_ts && $second_ts > $primary_ts ) {
				return $second_ts;
			}
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
 * Haftalık zamanlama aralığını WordPress cron sistemine kaydeder.
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
 * Haftalık rapor cron görevini ayarlara göre planlar veya günceller.
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
	$tz       = new DateTimeZone( 'Europe/Istanbul' );
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

if ( is_admin() ) {
	require_once KT_PLUGIN_DIR . 'includes/admin-page.php';
}

add_action( 'wp_enqueue_scripts', 'kt_register_frontend_assets' );
function kt_register_frontend_assets() {
	wp_register_style(
		'kt-quicksand-font',
		'https://fonts.googleapis.com/css2?family=Quicksand:wght@500;600;700;900&display=swap',
		array(),
		null
	);

	wp_register_style(
		'kt-frontend-css',
		KT_PLUGIN_URL . 'assets/css/frontend.css',
		array( 'kt-quicksand-font' ),
		KT_PLUGIN_VER
	);

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
 * Tracker scriptini ve ilçe bağlamını ön yüze yükler (görüntülenme, tekil ziyaretçi, kalma süresi ve bilet tıklama).
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

	wp_enqueue_script( 'kt-tracker-js' );
	wp_localize_script(
		'kt-tracker-js',
		'ktTrackerData',
		array(
			'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
			'defaultDistrict' => kt_normalize_district_key( $district_key ),
			'defaultLabel'    => sanitize_text_field( $page_label ),
			'trackView'       => $track_page_view ? 1 : 0,
			'todayStr'        => kt_get_istanbul_date_str( 0 ),
			'ticketDomains'   => $domains,
			'defaultImgUrl'   => esc_url_raw( $styles['ticket_btn_img'] ),
		)
	);
}

/**
 * İlçe anahtarını (slug) normalize eder.
 */
function kt_normalize_district_key( $raw_key ) {
	$slug = sanitize_title( $raw_key );
	if ( empty( $slug ) ) {
		return '';
	}
	$base = preg_replace( '/-(konseri|konser)$/', '', $slug );
	return ! empty( $base ) ? $base : $slug;
}

/**
 * Saniye cinsinden süreyi okunabilir "1 dk 24 sn" veya "42 sn" biçimine çevirir.
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
 * İstatistik sayacını (views, visitors, clicks, duration) ve son 90 günlük kırılımı güvenli biçimde günceller.
 */
function kt_record_district_stat( $raw_key, $type = 'views', $page_title = '', $extra = array() ) {
	$key = kt_normalize_district_key( $raw_key );
	if ( empty( $key ) || ! in_array( $type, array( 'views', 'clicks', 'duration' ), true ) ) {
		return;
	}

	$stats = get_option( KT_STATS_OPT, array() );
	if ( ! is_array( $stats ) ) {
		$stats = array();
	}

	$today_str = kt_get_istanbul_date_str( 0 );
	$cutoff    = kt_get_istanbul_date_str( -90 );

	if ( ! isset( $stats[ $key ] ) || ! is_array( $stats[ $key ] ) ) {
		$stats[ $key ] = array(
			'label'          => ! empty( $page_title ) ? sanitize_text_field( $page_title ) : ucfirst( str_replace( '-', ' ', $key ) ),
			'views'          => 0,
			'visitors'       => 0,
			'clicks'         => 0,
			'total_seconds'  => 0,
			'duration_count' => 0,
			'daily'          => array(),
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
	}

	if ( ! isset( $stats[ $key ]['daily'][ $today_str ] ) || ! is_array( $stats[ $key ]['daily'][ $today_str ] ) ) {
		$stats[ $key ]['daily'][ $today_str ] = array(
			'views'    => 0,
			'visitors' => 0,
			'clicks'   => 0,
			'seconds'  => 0,
			'sessions' => 0,
		);
	}

	if ( 'views' === $type ) {
		$is_unique = ! empty( $extra['is_unique'] );
		$stats[ $key ]['views']                        = intval( $stats[ $key ]['views'] ) + 1;
		$stats[ $key ]['daily'][ $today_str ]['views'] = intval( $stats[ $key ]['daily'][ $today_str ]['views'] ) + 1;

		if ( $is_unique ) {
			$stats[ $key ]['visitors']                        = intval( $stats[ $key ]['visitors'] ) + 1;
			$stats[ $key ]['daily'][ $today_str ]['visitors'] = intval( $stats[ $key ]['daily'][ $today_str ]['visitors'] ) + 1;
		}
	} elseif ( 'clicks' === $type ) {
		$stats[ $key ]['clicks']                        = intval( $stats[ $key ]['clicks'] ) + 1;
		$stats[ $key ]['daily'][ $today_str ]['clicks'] = intval( $stats[ $key ]['daily'][ $today_str ]['clicks'] ) + 1;
	} elseif ( 'duration' === $type ) {
		// Tek oturumda 1 sn ile 1800 sn (30 dk) arası makul aktif kalma süresini kabul et
		$sec = isset( $extra['seconds'] ) ? intval( $extra['seconds'] ) : 0;
		if ( $sec >= 1 && $sec <= 1800 ) {
			$stats[ $key ]['total_seconds']                     = intval( $stats[ $key ]['total_seconds'] ) + $sec;
			$stats[ $key ]['duration_count']                    = intval( $stats[ $key ]['duration_count'] ) + 1;
			$stats[ $key ]['daily'][ $today_str ]['seconds']  = intval( $stats[ $key ]['daily'][ $today_str ]['seconds'] ) + $sec;
			$stats[ $key ]['daily'][ $today_str ]['sessions'] = intval( $stats[ $key ]['daily'][ $today_str ]['sessions'] ) + 1;
		} else {
			return;
		}
	}

	$stats[ $key ]['updated'] = kt_get_istanbul_datetime_str();

	// 90 günden eski günlük kayıtları temizleyerek veritabanını hafif tut
	foreach ( $stats[ $key ]['daily'] as $d_key => $d_val ) {
		if ( $d_key < $cutoff ) {
			unset( $stats[ $key ]['daily'][ $d_key ] );
		}
	}

	update_option( KT_STATS_OPT, $stats, false );
}

/**
 * AJAX üzerinden önbellek (cache) uyumlu görüntülenme, tekil ziyaretçi, kalma süresi ve tıklama kaydı.
 */
add_action( 'wp_ajax_kt_track_district_event', 'kt_ajax_track_district_event' );
add_action( 'wp_ajax_nopriv_kt_track_district_event', 'kt_ajax_track_district_event' );
function kt_ajax_track_district_event() {
	if ( current_user_can( 'edit_pages' ) ) {
		wp_send_json_success( array( 'ignored' => 'admin' ) );
	}

	$district = isset( $_POST['district'] ) ? sanitize_text_field( wp_unslash( $_POST['district'] ) ) : '';
	$label    = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
	$ev_type  = isset( $_POST['event_type'] ) ? sanitize_key( wp_unslash( $_POST['event_type'] ) ) : 'clicks';

	if ( ! in_array( $ev_type, array( 'views', 'clicks', 'duration' ), true ) ) {
		$ev_type = 'clicks';
	}

	if ( empty( $district ) ) {
		wp_send_json_error( array( 'message' => 'missing_district' ), 400 );
	}

	$extra = array(
		'is_unique' => isset( $_POST['is_unique'] ) && '1' === strval( $_POST['is_unique'] ),
		'seconds'   => isset( $_POST['seconds'] ) ? intval( $_POST['seconds'] ) : 0,
	);

	kt_record_district_stat( $district, $ev_type, $label, $extra );
	wp_send_json_success( array( 'recorded' => $ev_type ) );
}

/**
 * Son 7 günün özet HTML raporunu oluşturur ve yönetici e-postasına gönderir.
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

	$stats = get_option( KT_STATS_OPT, array() );
	if ( ! is_array( $stats ) ) {
		$stats = array();
	}

	$days_7 = array();
	for ( $d = 6; $d >= 0; $d-- ) {
		$days_7[] = kt_get_istanbul_date_str( -$d );
	}

	$period_start = $days_7[0];
	$period_end   = $days_7[6];

	$rows            = array();
	$sum_views_7     = 0;
	$sum_visitors_7  = 0;
	$sum_clicks_7    = 0;
	$sum_seconds_7   = 0;
	$sum_sessions_7  = 0;

	foreach ( $stats as $key => $item ) {
		$v7   = 0;
		$vis7 = 0;
		$c7   = 0;
		$sec7 = 0;
		$ses7 = 0;

		if ( isset( $item['daily'] ) && is_array( $item['daily'] ) ) {
			foreach ( $days_7 as $day_str ) {
				if ( isset( $item['daily'][ $day_str ] ) ) {
					$d_row = $item['daily'][ $day_str ];
					$v7   += isset( $d_row['views'] ) ? intval( $d_row['views'] ) : 0;
					$vis7 += isset( $d_row['visitors'] ) ? intval( $d_row['visitors'] ) : 0;
					$c7   += isset( $d_row['clicks'] ) ? intval( $d_row['clicks'] ) : 0;
					$sec7 += isset( $d_row['seconds'] ) ? intval( $d_row['seconds'] ) : 0;
					$ses7 += isset( $d_row['sessions'] ) ? intval( $d_row['sessions'] ) : 0;
				}
			}
		}

		$all_v   = isset( $item['views'] ) ? intval( $item['views'] ) : 0;
		$all_vis = isset( $item['visitors'] ) ? intval( $item['visitors'] ) : 0;
		$all_c   = isset( $item['clicks'] ) ? intval( $item['clicks'] ) : 0;

		if ( $v7 > 0 || $c7 > 0 || $all_v > 0 ) {
			$rows[] = array(
				'key'         => $key,
				'label'       => isset( $item['label'] ) ? $item['label'] : $key,
				'views_7'     => $v7,
				'visitors_7'  => $vis7,
				'clicks_7'    => $c7,
				'avg_sec_7'   => $ses7 > 0 ? round( $sec7 / $ses7 ) : 0,
				'total_views' => $all_v,
				'total_vis'   => $all_vis,
				'total_click' => $all_c,
			);
		}

		$sum_views_7    += $v7;
		$sum_visitors_7 += $vis7;
		$sum_clicks_7   += $c7;
		$sum_seconds_7  += $sec7;
		$sum_sessions_7 += $ses7;
	}

	usort(
		$rows,
		function ( $a, $b ) {
			if ( $a['visitors_7'] === $b['visitors_7'] ) {
				return $b['views_7'] - $a['views_7'];
			}
			return $b['visitors_7'] - $a['visitors_7'];
		}
	);

	$overall_avg_sec = $sum_sessions_7 > 0 ? round( $sum_seconds_7 / $sum_sessions_7 ) : 0;
	$overall_ctr_7   = $sum_views_7 > 0 ? number_format( ( $sum_clicks_7 / $sum_views_7 ) * 100, 1, ',', '.' ) : '0,0';
	$site_name       = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$subject         = sprintf( '[%s] Haftalık Konser Sayfası İstatistik Raporu (%s - %s)', $site_name, $period_start, $period_end );

	ob_start();
	?>
	<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #1e293b; max-width: 680px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
		<div style="background: #1e293b; color: #ffffff; padding: 20px 24px;">
			<h2 style="margin: 0; font-size: 18px;">Konser Takvimi — Haftalık Performans Raporu</h2>
			<p style="margin: 6px 0 0 0; font-size: 13px; color: #cbd5e1;">Dönem: <?php echo esc_html( $period_start . ' – ' . $period_end ); ?></p>
		</div>

		<div style="padding: 20px 24px;">
			<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 20px; border-collapse: collapse;">
				<tr>
					<td style="width: 25%; padding: 10px; background: #f8fafc; border: 1px solid #e2e8f0; text-align: center;">
						<div style="font-size: 11px; color: #64748b; font-weight: 600;">7 Günlük Ziyaretçi</div>
						<div style="font-size: 20px; font-weight: 700; color: #0f172a; margin-top: 4px;"><?php echo number_format_i18n( $sum_visitors_7 ); ?></div>
					</td>
					<td style="width: 25%; padding: 10px; background: #f8fafc; border: 1px solid #e2e8f0; text-align: center;">
						<div style="font-size: 11px; color: #64748b; font-weight: 600;">7 Günlük Gösterim</div>
						<div style="font-size: 20px; font-weight: 700; color: #0f172a; margin-top: 4px;"><?php echo number_format_i18n( $sum_views_7 ); ?></div>
					</td>
					<td style="width: 25%; padding: 10px; background: #f8fafc; border: 1px solid #e2e8f0; text-align: center;">
						<div style="font-size: 11px; color: #64748b; font-weight: 600;">Bilet Tıklaması</div>
						<div style="font-size: 20px; font-weight: 700; color: #16a34a; margin-top: 4px;"><?php echo number_format_i18n( $sum_clicks_7 ); ?> (%<?php echo esc_html( $overall_ctr_7 ); ?>)</div>
					</td>
					<td style="width: 25%; padding: 10px; background: #f8fafc; border: 1px solid #e2e8f0; text-align: center;">
						<div style="font-size: 11px; color: #64748b; font-weight: 600;">Ort. Kalma Süresi</div>
						<div style="font-size: 20px; font-weight: 700; color: #2563eb; margin-top: 4px;"><?php echo esc_html( kt_format_duration_human( $overall_avg_sec ) ); ?></div>
					</td>
				</tr>
			</table>

			<h3 style="font-size: 15px; margin: 0 0 10px 0; color: #0f172a;">İlçe Bazlı Son 7 Gün ve Genel Toplamlar</h3>
			<?php if ( empty( $rows ) ) : ?>
				<p style="color: #64748b; font-size: 13px;">Bu dönemde henüz kayıtlı ziyaret verisi oluşmamıştır.</p>
			<?php else : ?>
				<table width="100%" cellpadding="8" cellspacing="0" style="border-collapse: collapse; font-size: 13px; border: 1px solid #e2e8f0;">
					<thead>
						<tr style="background: #f1f5f9; text-align: left;">
							<th style="border-bottom: 1px solid #cbd5e1;">İlçe / Sayfa</th>
							<th style="border-bottom: 1px solid #cbd5e1; text-align: right;">Ziyaretçi (7g)</th>
							<th style="border-bottom: 1px solid #cbd5e1; text-align: right;">Gösterim (7g)</th>
							<th style="border-bottom: 1px solid #cbd5e1; text-align: right;">Bilet Tık (7g)</th>
							<th style="border-bottom: 1px solid #cbd5e1; text-align: right;">Ort. Süre</th>
							<th style="border-bottom: 1px solid #cbd5e1; text-align: right;">Genel Tık</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $r ) : ?>
							<tr>
								<td style="border-bottom: 1px solid #e2e8f0;"><strong><?php echo esc_html( $r['label'] ); ?></strong></td>
								<td style="border-bottom: 1px solid #e2e8f0; text-align: right;"><?php echo number_format_i18n( $r['visitors_7'] ); ?></td>
								<td style="border-bottom: 1px solid #e2e8f0; text-align: right;"><?php echo number_format_i18n( $r['views_7'] ); ?></td>
								<td style="border-bottom: 1px solid #e2e8f0; text-align: right; color: #16a34a; font-weight: 600;"><?php echo number_format_i18n( $r['clicks_7'] ); ?></td>
								<td style="border-bottom: 1px solid #e2e8f0; text-align: right;"><?php echo esc_html( kt_format_duration_human( $r['avg_sec_7'] ) ); ?></td>
								<td style="border-bottom: 1px solid #e2e8f0; text-align: right; color: #64748b;"><?php echo number_format_i18n( $r['total_click'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<p style="margin-top: 20px; font-size: 12px; color: #64748b;">
				Detaylı günlük grafikler ve CSV çıktısı için WordPress yönetim panelindeki <a href="<?php echo esc_url( admin_url( 'admin.php?page=konser-takvimi&tab=stats' ) ); ?>">Konser Takvimi &rarr; İstatistikler</a> sayfasını ziyaret edebilirsiniz.
			</p>
		</div>
	</div>
	<?php
	$html_body = ob_get_clean();
	$headers   = array( 'Content-Type: text/html; charset=UTF-8' );

	return wp_mail( $recipient, $subject, $html_body, $headers );
}

/**
 * Belirli bir ilçe / slug için yaklaşan aktif konser olup olmadığını kontrol eder.
 */
function kt_has_upcoming_concert_for_target( $target ) {
	$target_slug = sanitize_title( $target );
	if ( empty( $target_slug ) ) {
		return false;
	}

	$base_target = preg_replace( '/-(konseri|konser)$/', '', $target_slug );

	$all_events = get_option( KT_EVENTS_OPT, array() );
	if ( ! is_array( $all_events ) || empty( $all_events ) ) {
		return false;
	}

	$now_ts = kt_get_istanbul_timestamp();

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
			return true;
		}
	}

	return false;
}

/**
 * Yönlendirme hedef URL'sini çözümler (kısa kod parametresi -> ayarlar -> /konserler).
 */
function kt_resolve_redirect_url( $custom_target = '' ) {
	$styles         = kt_get_style_settings();
	$default_target = ! empty( $styles['redirect_target'] ) ? $styles['redirect_target'] : '/konserler';
	$redirect_path  = ! empty( $custom_target ) ? $custom_target : $default_target;

	if ( 0 === strpos( $redirect_path, 'http://' ) || 0 === strpos( $redirect_path, 'https://' ) ) {
		return esc_url_raw( $redirect_path );
	}

	return home_url( '/' . ltrim( $redirect_path, '/' ) );
}

/**
 * [konser_ilce_yonlendir] kısa kodunu sayfa HTML'i gönderilmeden önce yakalar:
 * Yaklaşan konser yoksa belirlenen sayfaya yönlendirir.
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

	if ( ! has_shortcode( $post->post_content, 'konser_ilce_yonlendir' ) ) {
		return;
	}

	if ( current_user_can( 'edit_pages' ) ) {
		return;
	}

	$pattern = get_shortcode_regex( array( 'konser_ilce_yonlendir' ) );
	if ( preg_match_all( '/' . $pattern . '/s', $post->post_content, $matches, PREG_SET_ORDER ) ) {
		foreach ( $matches as $match ) {
			$atts = shortcode_parse_atts( $match[3] );
			$atts = shortcode_atts(
				array(
					'ilce'  => '',
					'hedef' => '',
				),
				$atts,
				'konser_ilce_yonlendir'
			);

			$lookup_key = ! empty( $atts['ilce'] ) ? $atts['ilce'] : $post->post_name;

			if ( ! kt_has_upcoming_concert_for_target( $lookup_key ) ) {
				$redirect_url = kt_resolve_redirect_url( $atts['hedef'] );
				wp_safe_redirect( $redirect_url, 302 );
				exit;
			}
		}
	}
}

add_shortcode( 'konser_ilce_yonlendir', 'kt_district_redirect_shortcode_output' );
function kt_district_redirect_shortcode_output( $atts ) {
	$atts = shortcode_atts(
		array(
			'ilce'  => '',
			'hedef' => '',
		),
		$atts,
		'konser_ilce_yonlendir'
	);

	global $post;
	$lookup_key   = ! empty( $atts['ilce'] ) ? $atts['ilce'] : ( $post ? $post->post_name : '' );
	$page_label   = $post ? $post->post_title : '';
	$has_event    = kt_has_upcoming_concert_for_target( $lookup_key );
	$redirect_url = kt_resolve_redirect_url( $atts['hedef'] );

	if ( current_user_can( 'edit_pages' ) ) {
		if ( $has_event ) {
			return '<div style="padding:10px 14px;margin:10px 0;background:#f0fdf4;border:1px solid #86efac;border-radius:6px;color:#166534;font-size:13px;"><strong>Konser Takvimi Bilgi:</strong> Bu ilçe (<code>' . esc_html( $lookup_key ) . '</code>) için yaklaşan konser aktif olduğundan ziyaretçiler bu sayfada kalacaktır (yönetici girişi yapıldığı için ziyaret ve tıklamalar sayaca eklenmez).</div>';
		}
		return '<div style="padding:10px 14px;margin:10px 0;background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;color:#991b1b;font-size:13px;"><strong>Konser Takvimi Bilgi:</strong> Bu ilçe (<code>' . esc_html( $lookup_key ) . '</code>) için yaklaşan konser bulunmadığından normal ziyaretçiler otomatik olarak <code>' . esc_html( $redirect_url ) . '</code> adresine yönlendirilecektir (yönetici olduğunuz için yönlendirilmediniz).</div>';
	}

	if ( ! $has_event ) {
		return '<script>window.location.replace(' . wp_json_encode( esc_url( $redirect_url ) ) . ');</script>';
	}

	kt_enqueue_tracker_script( $lookup_key, $page_label, true );

	return '';
}

/**
 * [konser_bilet_butonu url="..." resim="..." ilce="..." alt="..." hedef="_blank"]
 */
add_shortcode( 'konser_bilet_butonu', 'kt_ticket_button_shortcode' );
function kt_ticket_button_shortcode( $atts ) {
	$styles = kt_get_style_settings();

	$atts = shortcode_atts(
		array(
			'url'   => $styles['ticket_btn_url'],
			'resim' => $styles['ticket_btn_img'],
			'ilce'  => '',
			'alt'   => 'Bilet Al',
			'hedef' => '',
		),
		$atts,
		'konser_bilet_butonu'
	);

	$target_url = ! empty( $atts['url'] ) ? $atts['url'] : $styles['ticket_btn_url'];
	if ( empty( $target_url ) ) {
		return '';
	}

	global $post;
	$district_key = ! empty( $atts['ilce'] ) ? $atts['ilce'] : ( $post ? $post->post_name : 'genel' );
	$page_label   = $post ? $post->post_title : '';
	$img_url      = ! empty( $atts['resim'] ) ? $atts['resim'] : $styles['ticket_btn_img'];

	kt_enqueue_tracker_script( $district_key, $page_label, false );

	$target_attr = '';
	if ( ! empty( $atts['hedef'] ) ) {
		$target_attr = ' target="' . esc_attr( $atts['hedef'] ) . '" rel="noopener noreferrer"';
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
		'<figure class="wp-block-image size-large"><a href="%s" class="kt-ticket-btn-link" data-kt-district="%s" data-kt-label="%s"%s>%s</a></figure>',
		esc_url( $target_url ),
		esc_attr( kt_normalize_district_key( $district_key ) ),
		esc_attr( $page_label ),
		$target_attr,
		$img_html
	);
}

add_shortcode( 'konser_takvimi', 'kt_concert_grid_shortcode' );
add_shortcode( 'subadap_konserler', 'kt_concert_grid_shortcode' );
function kt_concert_grid_shortcode() {
	$all_events      = get_option( KT_EVENTS_OPT, array() );
	$upcoming_events = array();
	$styles          = kt_get_style_settings();

	if ( is_array( $all_events ) ) {
		$now_ts = kt_get_istanbul_timestamp();
		foreach ( $all_events as $event ) {
			$end_ts = kt_get_event_end_timestamp( $event );
			if ( false !== $end_ts && $end_ts >= $now_ts ) {
				$dt_parts = explode( 'T', $event['datetime'] );
				$end_iso  = $event['datetime'];
				if ( ! empty( $event['time2'] ) && ! empty( $dt_parts[0] ) ) {
					$end_iso = $dt_parts[0] . 'T' . sanitize_text_field( $event['time2'] ) . ':00+03:00';
				}

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
		}
",
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
			"#ktConcertGridContainer .style-%d .date-part { background-color: %s; }
#ktConcertGridContainer .style-%d .city-part { background-color: %s; }
",
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
		)
	);

	return '<div id="ktConcertGridContainer"><div class="kt-concert-grid" id="ktConcertGrid"></div></div>';
}
