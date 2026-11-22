<?php
error_reporting( E_ALL );
set_error_handler( function ( $no, $str, $file, $line ) { echo "PHP NOTICE/WARNING: $str at " . basename( $file ) . ":$line\n"; return true; } );
require __DIR__ . '/wp_stubs.php';
require dirname( __DIR__ ) . '/konser-takvimi.php';
// extra stubs for the admin screen
function map_deep( $v, $cb ) { return is_array( $v ) ? array_map( fn( $x ) => map_deep( $x, $cb ), $v ) : $cb( $v ); }
function get_posts() { return array(); }
function wp_nonce_field() { echo '<input type="hidden" name="_nonce">'; }
function check_admin_referer() { return true; }
function checked( $a ) { if ( $a ) echo ' checked="checked"'; }
function selected( $a, $b ) { if ( $a === $b ) echo ' selected="selected"'; }
function disabled( $d ) { if ( $d ) echo ' disabled="disabled"'; }
function wp_list_pluck( $l, $f ) { return array_column( $l, $f ); }
function wp_kses_post( $s ) { return $s; }
function add_menu_page() {} function wp_enqueue_media() {}
function wp_clear_scheduled_hook() {} function wp_schedule_event() {}
function sanitize_email( $e ) { return $e; } function is_email( $e ) { return (bool) strpos( $e, '@' ); }
function wp_die( $m ) { echo "WP_DIE: $m\n"; }
function current_user_can( $c = '' ) { return 'manage_options' === $c; }
require dirname( __DIR__ ) . '/includes/admin-tools.php';
require dirname( __DIR__ ) . '/includes/admin-page.php';

$fail = 0; $n = 0;
function t( $name, $cond, $detail = '' ) { global $fail, $n; $n++; if ( ! $cond ) { $fail++; echo "FAIL: $name $detail\n"; } }

// ---- seed stats with utm rows ----
update_option( KT_STATS_OPT, array(
	'urla' => array( 'label' => 'Urla', 'views' => 10, 'visitors' => 6, 'clicks' => 2, 'total_seconds' => 0, 'duration_count' => 0, 'daily' => array(), 'updated' => 'x',
		'utm' => array( 'ig|social|urla' => array( 'source' => 'ig', 'medium' => 'social', 'campaign' => 'urla', 'views' => 4, 'visitors' => 3, 'clicks' => 1 ),
		                'x<script>||' => array( 'source' => '<script>alert(1)</script>', 'medium' => '', 'campaign' => '', 'views' => 1, 'visitors' => 1, 'clicks' => 0 ) ) ),
	'legacy' => array( 'label' => 'Legacy', 'views' => 3, 'visitors' => 2, 'clicks' => 0, 'daily' => array() ),
) );

// stats tab
$_GET = array( 'tab' => 'stats' );
ob_start(); kt_render_admin_page(); $html = ob_get_clean();
t( 'stats: utm card present', false !== strpos( $html, 'Trafik Kaynakları (UTM)' ) );
t( 'stats: utm row rendered', false !== strpos( $html, '<code>ig</code>' ) && false !== strpos( $html, '<code>social</code>' ) );
t( 'stats: ctr computed (1/4 = 25,0)', false !== strpos( $html, '%25,0' ), 'ctr' );
t( 'stats: xss escaped', false === strpos( $html, '<script>alert(1)' ) && false !== strpos( $html, '&lt;script&gt;alert(1)' ) );
t( 'stats: conversion card + column', false !== strpos( $html, 'Bilet Tıklayan Kişi / Tekil Ziyaretçi' ) && false !== strpos( $html, 'sort_by=conversion' ) );
t( 'stats: legacy row w/o utm ok', false !== strpos( $html, 'Legacy' ) );

// empty utm state
update_option( KT_STATS_OPT, array() );
ob_start(); kt_render_admin_page(); $html = ob_get_clean();
t( 'stats: empty state example', false !== strpos( $html, 'Henüz UTM parametreli ziyaret kaydedilmedi' ) );

// capture disabled message
update_option( KT_STYLES_OPT, array( 'utm_capture_enabled' => 0 ) );
ob_start(); kt_render_admin_page(); $html = ob_get_clean();
t( 'stats: capture-off notice', false !== strpos( $html, 'Gelen UTM kaydı Ayarlar sekmesinde kapalı' ) );

// settings tab renders the new card with defaults
$GLOBALS['__opts'] = array();
$_GET = array( 'tab' => 'styles' );
ob_start(); kt_render_admin_page(); $html = ob_get_clean();
t( 'settings: utm card', false !== strpos( $html, 'UTM Parametreleri' ) );
t( 'settings: source placeholder = host', false !== strpos( $html, 'placeholder="subadapcocuk.org"' ) );
t( 'settings: medium default', false !== strpos( $html, 'name="utm_medium" value="referral"' ) );
t( 'settings: capture+calendar checked, ticket unchecked by default', 2 === preg_match_all( '/name="utm_(calendar|capture)_enabled" value="1"\s+checked="checked"/', $html ) && 0 === preg_match_all( '/name="utm_ticket_enabled" value="1"\s+checked="checked"/', $html ) );

// save handler
$GLOBALS['__opts'] = array();
$_GET = array( 'tab' => 'styles' );
$_POST = array( 'kt_save_styles' => '1', 'utm_ticket_enabled' => '1', 'utm_source' => ' Şubadap Site ', 'utm_medium' => '', 'utm_campaign' => 'Sonbahar Turnesi!',
	'border_radius' => '20', 'badge_height' => '90', 'grid_gap' => '20', 'card_min_w' => '250', 'max_items' => '16' );
ob_start(); kt_render_admin_page(); ob_get_clean();
$saved = get_option( KT_STYLES_OPT );
t( 'save: source sanitized', 'subadap-site' === $saved['utm_source'], $saved['utm_source'] ?? 'unset' );
t( 'save: empty medium falls back', 'referral' === $saved['utm_medium'] );
t( 'save: campaign sanitized', 'sonbahar-turnesi' === $saved['utm_campaign'], $saved['utm_campaign'] ?? '' );
t( 'save: unchecked boxes -> 0', 0 === $saved['utm_calendar_enabled'] && 0 === $saved['utm_capture_enabled'] && 1 === $saved['utm_ticket_enabled'] );

// concerts tab still renders (nonce rename)
$_POST = array(); $_GET = array( 'tab' => 'concerts' );
ob_start(); kt_render_admin_page(); $html = ob_get_clean();
t( 'concerts tab renders', false !== strpos( $html, 'Yaklaşan Konserler' ) );
$_POST = array( 'kt_save_concerts' => '1', 'concert_date' => array( '2099-01-02' ), 'concert_time' => array( '15:00' ), 'concert_time2' => array( '' ), 'concert_city' => array( 'Urla' ), 'concert_slug' => array( '' ) );
ob_start(); kt_render_admin_page(); ob_get_clean();
$ev = get_option( KT_EVENTS_OPT );
t( 'concerts save works with renamed nonce', is_array( $ev ) && 1 === count( $ev ) && 'urla-konseri' === $ev[0]['slug'], json_encode( $ev ) );

echo "\n$n assertions, $fail failed\n";
exit( $fail ? 1 : 0 );
