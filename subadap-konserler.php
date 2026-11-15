<?php
/**
 * Plugin Name: Şubadap Konser Takvimi
 * Description: Veritabanı tabanlı, geçmiş konserleri otomatik arşivleyen, otomatik ilce-konseri slug önerisi ve canlı sayfa varlık kontrolü sunan WordPress eklentisi.
 * Version: 2.8.1
 * Author: Şubadap Çocuk
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SUBADAP_KONSER_DIR', plugin_dir_path( __FILE__ ) );
define( 'SUBADAP_KONSER_URL', plugin_dir_url( __FILE__ ) );
define( 'SUBADAP_KONSER_VER', '2.8.1' );
define( 'SUBADAP_KONSER_OPT', 'subadap_konserler_list' );
define( 'SUBADAP_STYLE_OPT', 'subadap_konserler_styles' );

function subadap_get_istanbul_timestamp() {
	$tz  = new DateTimeZone( 'Europe/Istanbul' );
	$now = new DateTime( 'now', $tz );
	return $now->getTimestamp();
}

function subadap_sort_events_asc( $a, $b ) {
	return strcmp( $a['datetime'], $b['datetime'] );
}

function subadap_sort_events_desc( $a, $b ) {
	return strcmp( $b['datetime'], $a['datetime'] );
}

function subadap_get_default_style_settings() {
	return array(
		'border_radius'     => 20,
		'badge_height'      => 90,
		'grid_gap'          => 20,
		'card_min_w'        => 250,
		'max_items'         => 16,
		'date_text'         => '#111827',
		'city_text'         => '#0f172a',
		'delete_on_uninst'  => 0,
		'palettes'          => array(
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

function subadap_get_style_settings() {
	$defaults = subadap_get_default_style_settings();
	$saved    = get_option( SUBADAP_STYLE_OPT, array() );

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

register_activation_hook( __FILE__, 'subadap_init_db_option' );
function subadap_init_db_option() {
	$existing = get_option( SUBADAP_KONSER_OPT );
	if ( ! is_array( $existing ) ) {
		update_option( SUBADAP_KONSER_OPT, array(), false );
	}
}

if ( is_admin() ) {
	require_once SUBADAP_KONSER_DIR . 'includes/admin-page.php';
}

add_action( 'wp_enqueue_scripts', 'subadap_register_frontend_assets' );
function subadap_register_frontend_assets() {
	wp_register_style(
		'subadap-quicksand-font',
		'https://fonts.googleapis.com/css2?family=Quicksand:wght@500;600;700;900&display=swap',
		array(),
		null
	);

	wp_register_style(
		'subadap-konseriler-css',
		SUBADAP_KONSER_URL . 'assets/css/konserler.css',
		array( 'subadap-quicksand-font' ),
		SUBADAP_KONSER_VER
	);

	wp_register_script(
		'subadap-konseriler-js',
		SUBADAP_KONSER_URL . 'assets/js/konserler.js',
		array(),
		SUBADAP_KONSER_VER,
		true
	);
}

add_shortcode( 'subadap_konserler', 'subadap_konser_grid_shortcode' );
function subadap_konser_grid_shortcode() {
	$all_events      = get_option( SUBADAP_KONSER_OPT, array() );
	$upcoming_events = array();
	$styles          = subadap_get_style_settings();

	if ( is_array( $all_events ) ) {
		$now_ts = subadap_get_istanbul_timestamp();
		foreach ( $all_events as $event ) {
			$ev_ts = isset( $event['datetime'] ) ? strtotime( $event['datetime'] ) : false;
			if ( false !== $ev_ts && $ev_ts >= $now_ts ) {
				$upcoming_events[] = array(
					'datetime' => sanitize_text_field( $event['datetime'] ),
					'city'     => sanitize_text_field( $event['city'] ),
					'slug'     => sanitize_title( $event['slug'] ),
				);
			}
		}
	}

	wp_enqueue_style( 'subadap-konseriler-css' );

	$custom_css = sprintf(
		"#subadapConcertGridContainer {
			--subadap-radius: %dpx;
			--subadap-height: %dpx;
			--subadap-gap: %dpx;
			--subadap-min-w: %dpx;
			--subadap-date-text: %s;
			--subadap-city-text: %s;
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
			"#subadapConcertGridContainer .style-%d .date-part { background-color: %s; }
#subadapConcertGridContainer .style-%d .city-part { background-color: %s; }
",
			$num,
			esc_attr( $pair['date'] ),
			$num,
			esc_attr( $pair['city'] )
		);
	}

	wp_add_inline_style( 'subadap-konseriler-css', $custom_css );
	wp_enqueue_script( 'subadap-konseriler-js' );
	wp_localize_script(
		'subadap-konseriler-js',
		'subadapKonserData',
		array(
			'events'   => $upcoming_events,
			'baseUrl'  => 'https://www.subadapcocuk.org/',
			'maxItems' => intval( $styles['max_items'] ),
		)
	);

	return '<div id="subadapConcertGridContainer"><div class="subadap-concert-grid" id="subadapConcertGrid"></div></div>';
}
