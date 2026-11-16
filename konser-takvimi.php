<?php
/**
 * Plugin Name: Konser Takvimi
 * Description: Veritabanı tabanlı, çift seans destekli, geçmiş konserleri otomatik arşivleyen, ilçe sayfası yönlendirme ve bilet tıklama istatistikleri sunan WordPress eklentisi.
 * Version: 3.3.0
 * Author: Şubadap Çocuk
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'KT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'KT_PLUGIN_VER', '3.3.0' );
define( 'KT_EVENTS_OPT', 'subadap_konserler_list' );
define( 'KT_STYLES_OPT', 'subadap_konserler_styles' );
define( 'KT_STATS_OPT', 'subadap_konserler_stats' );

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
		'border_radius'    => 20,
		'badge_height'     => 90,
		'grid_gap'         => 20,
		'card_min_w'       => 250,
		'max_items'        => 16,
		'date_text'        => '#111827',
		'city_text'        => '#0f172a',
		'redirect_target'  => '/konserler',
		'ticket_btn_url'   => '',
		'ticket_btn_img'   => 'https://subadapcocuk.org/wp-content/uploads/2026/09/4-edited-1024x281.png',
		'ticket_domains'   => 'biletinial.com, biletix.com, passo.com.tr, bubilet.com.tr, mobilet.com, biletino.com',
		'delete_on_uninst' => 0,
		'palettes'         => array(
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
 * Tracker scriptini ve ilçe bağlamını ön yüze yükler (önbellek uyumlu görüntülenme + tıklama izleme).
 */
function kt_enqueue_tracker_script( $district_key = '', $page_label = '', $track_view = false ) {
	static $already_enqueued = false;
	static $track_page_view  = false;

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
			'ticketDomains'   => $domains,
			'defaultImgUrl'   => esc_url_raw( $styles['ticket_btn_img'] ),
		)
	);

	$already_enqueued = true;
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
 * İstatistik sayacını (views veya clicks) güvenli biçimde artırır.
 */
function kt_record_district_stat( $raw_key, $type = 'views', $page_title = '' ) {
	$key = kt_normalize_district_key( $raw_key );
	if ( empty( $key ) || ! in_array( $type, array( 'views', 'clicks' ), true ) ) {
		return;
	}

	$stats = get_option( KT_STATS_OPT, array() );
	if ( ! is_array( $stats ) ) {
		$stats = array();
	}

	if ( ! isset( $stats[ $key ] ) || ! is_array( $stats[ $key ] ) ) {
		$stats[ $key ] = array(
			'label'   => ! empty( $page_title ) ? sanitize_text_field( $page_title ) : ucfirst( str_replace( '-', ' ', $key ) ),
			'views'   => 0,
			'clicks'  => 0,
			'updated' => '',
		);
	} elseif ( ! empty( $page_title ) ) {
		$stats[ $key ]['label'] = sanitize_text_field( $page_title );
	}

	$stats[ $key ][ $type ] = intval( $stats[ $key ][ $type ] ) + 1;
	$stats[ $key ]['updated'] = kt_get_istanbul_datetime_str();

	update_option( KT_STATS_OPT, $stats, false );
}

/**
 * AJAX üzerinden önbellek (cache) uyumlu ilçe sayfası görüntülenme ve bilet tıklama kaydı.
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

	if ( ! in_array( $ev_type, array( 'views', 'clicks' ), true ) ) {
		$ev_type = 'clicks';
	}

	if ( empty( $district ) ) {
		wp_send_json_error( array( 'message' => 'missing_district' ), 400 );
	}

	kt_record_district_stat( $district, $ev_type, $label );
	wp_send_json_success( array( 'recorded' => $ev_type ) );
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

	// Sayfa önbelleğe (cache) alınsa dahi ziyaret ve tıklama istatistiklerini AJAX ile kaydet
	kt_enqueue_tracker_script( $lookup_key, $page_label, true );

	return '';
}

/**
 * [konser_bilet_butonu url="..." resim="..." ilce="..." alt="..." hedef="_blank"]
 * url belirtilmezse Ayarlar sekmesindeki "Varsayılan Bilet Satış URL'si" kullanılır.
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
