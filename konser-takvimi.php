<?php
/**
 * Plugin Name: Konser Takvimi
 * Description: Veritabanı tabanlı, çift seans destekli, geçmiş konserleri otomatik arşivleyen ve ilçe sayfası yönlendirme kısa kodu sunan WordPress eklentisi.
 * Version: 3.0.0
 * Author: Şubadap Çocuk
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'KT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'KT_PLUGIN_VER', '3.0.0' );
define( 'KT_EVENTS_OPT', 'subadap_konserler_list' );
define( 'KT_STYLES_OPT', 'subadap_konserler_styles' );

function kt_get_istanbul_timestamp() {
	$tz  = new DateTimeZone( 'Europe/Istanbul' );
	$now = new DateTime( 'now', $tz );
	return $now->getTimestamp();
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
}

/**
 * Belirli bir ilçe / slug için yaklaşan aktif konser olup olmadığını kontrol eder.
 */
function kt_has_upcoming_concert_for_target( $target ) {
	$target_slug = sanitize_title( $target );
	if ( empty( $target_slug ) ) {
		return false;
	}

	// "urla-konseri" veya "urla-konser" gibi ekleri soyarak temel ilçe slug'ını da çıkar
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
 * [konser_ilce_yonlendir] kısa kodunu sayfa HTML'i gönderilmeden önce yakalar
 * ve yaklaşan konser yoksa /konserler sayfasına yönlendirir.
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

	// Yönetici olarak giriş yapmış kullanıcıları düzenleme/önizleme yapabilsin diye yönlendirme
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
					'hedef' => '/konserler',
				),
				$atts,
				'konser_ilce_yonlendir'
			);

			$lookup_key = ! empty( $atts['ilce'] ) ? $atts['ilce'] : $post->post_name;

			if ( ! kt_has_upcoming_concert_for_target( $lookup_key ) ) {
				$redirect_path = ! empty( $atts['hedef'] ) ? $atts['hedef'] : '/konserler';
				$redirect_url  = ( 0 === strpos( $redirect_path, 'http' ) )
					? esc_url_raw( $redirect_path )
					: home_url( '/' . ltrim( $redirect_path, '/' ) );

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
			'hedef' => '/konserler',
		),
		$atts,
		'konser_ilce_yonlendir'
	);

	global $post;
	$lookup_key = ! empty( $atts['ilce'] ) ? $atts['ilce'] : ( $post ? $post->post_name : '' );
	$has_event  = kt_has_upcoming_concert_for_target( $lookup_key );

	// Yöneticiye sayfa içinde bilgilendirme rozeti göster
	if ( current_user_can( 'edit_pages' ) ) {
		if ( $has_event ) {
			return '<div style="padding:10px 14px;margin:10px 0;background:#f0fdf4;border:1px solid #86efac;border-radius:6px;color:#166534;font-size:13px;"><strong>Konser Takvimi Bilgi:</strong> Bu ilçe için yaklaşan konser aktif olduğundan ziyaretçiler bu sayfada kalacaktır.</div>';
		}
		return '<div style="padding:10px 14px;margin:10px 0;background:#fef2f2;border:1px solid #fca5a5;border-radius:6px;color:#991b1b;font-size:13px;"><strong>Konser Takvimi Bilgi:</strong> Bu ilçe için yaklaşan konser bulunmadığından normal ziyaretçiler otomatik olarak <code>' . esc_html( $atts['hedef'] ) . '</code> adresine yönlendirilecektir (yönetici olduğunuz için yönlendirilmediniz).</div>';
	}

	// Eğer template_redirect aşamasında çalışmadıysa (örneğin sayfa oluşturucu widget içindeyse) JS ile yedek yönlendirme
	if ( ! $has_event ) {
		$redirect_path = ! empty( $atts['hedef'] ) ? $atts['hedef'] : '/konserler';
		$redirect_url  = ( 0 === strpos( $redirect_path, 'http' ) )
			? esc_url( $redirect_path )
			: esc_url( home_url( '/' . ltrim( $redirect_path, '/' ) ) );

		return '<script>window.location.replace(' . wp_json_encode( $redirect_url ) . ');</script>';
	}

	return '';
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
