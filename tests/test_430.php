<?php
error_reporting( E_ALL );
set_error_handler( function ( $no, $str, $file, $line ) { echo "PHP NOTICE/WARNING: $str at " . basename( $file ) . ":$line\n"; return true; } );
require __DIR__ . '/wp_stubs.php';
require dirname( __DIR__ ) . '/konser-takvimi.php';
function map_deep( $v, $cb ) { return is_array( $v ) ? array_map( fn( $x ) => map_deep( $x, $cb ), $v ) : $cb( $v ); }
function get_posts() { return array(); }
function wp_nonce_field() { echo '<input type="hidden" name="_nonce">'; }
function check_admin_referer() { return true; }
function checked( $a ) { if ( $a ) echo ' checked="checked"'; }
function selected( $a, $b ) { if ( (string) $a === (string) $b ) echo ' selected="selected"'; }
function disabled( $d ) { if ( $d ) echo ' disabled="disabled"'; }
function wp_kses_post( $s ) { return $s; }
function current_user_can( $c = '' ) { return 'manage_options' === $c; }
function sanitize_email( $e ) { return $e; } function is_email( $e ) { return (bool) strpos( $e, '@' ); }
function wp_clear_scheduled_hook() {} function wp_schedule_event() {}
require dirname( __DIR__ ) . '/includes/admin-tools.php';

$fail = 0; $n = 0;
function t( $name, $cond, $detail = '' ) { global $fail, $n; $n++; if ( ! $cond ) { $fail++; echo "FAIL: $name $detail\n"; } }
function reset_state() { $GLOBALS['__opts'] = array(); $GLOBALS['__cron'] = array(); }
function stats() { return get_option( KT_STATS_OPT, array() ); }

// ---- write buffering ----
reset_state(); $GLOBALS['__buffer_on'] = true;
update_option( KT_STYLES_OPT, array( 'stats_buffer_seconds' => 60 ) );
kt_record_district_stat( 'urla', 'views', 'Urla', array( 'is_unique' => true ) );
kt_record_district_stat( 'urla', 'views', 'Urla', array( 'is_unique' => false ) );
t( 'buffered: stats not written yet', array() === stats() );
t( 'buffered: 2 events waiting', 2 === count( get_option( KT_STATS_BUFFER_OPT ) ) );
t( 'buffered: single flush event scheduled', isset( $GLOBALS['__cron'][ KT_STATS_FLUSH_HOOK ] ) );
kt_flush_stats_buffer();
t( 'flush merges views', 2 === stats()['urla']['views'] && 1 === stats()['urla']['visitors'] );
t( 'flush empties buffer', array() === get_option( KT_STATS_BUFFER_OPT ) );
t( 'lock released after flush', ! array_key_exists( KT_STATS_LOCK_OPT, $GLOBALS['__opts'] ) );
// 50 events trigger an automatic flush
reset_state();
update_option( KT_STYLES_OPT, array( 'stats_buffer_seconds' => 300 ) );
for ( $i = 0; $i < 50; $i++ ) { kt_record_district_stat( 'bornova', 'views', 'Bornova', array() ); }
t( 'auto flush at 50 events', 50 === ( isset( stats()['bornova']['views'] ) ? stats()['bornova']['views'] : 0 ) );
// buffer 0 writes immediately
reset_state(); $GLOBALS['__buffer_on'] = false;
kt_record_district_stat( 'karsiyaka', 'views', 'K', array() );
t( 'unbuffered writes immediately', 1 === stats()['karsiyaka']['views'] );

// ---- retention ----
reset_state();
update_option( KT_STYLES_OPT, array( 'stats_retention_days' => 30 ) );
$old = kt_get_site_date_str( -45 ); $recent = kt_get_site_date_str( -10 );
update_option( KT_STATS_OPT, array( 'a' => array( 'label' => 'A', 'views' => 5, 'visitors' => 2, 'clicks' => 0, 'total_seconds' => 0, 'duration_count' => 0, 'daily' => array( $old => array( 'views' => 3 ), $recent => array( 'views' => 2 ) ), 'updated' => 'x' ) ) );
kt_record_district_stat( 'a', 'views', 'A', array() );
$d = stats()['a']['daily'];
t( 'retention 30d drops 45-day-old day', ! isset( $d[ $old ] ) && isset( $d[ $recent ] ) );
t( 'retention keeps totals', 6 === stats()['a']['views'] );
update_option( KT_STYLES_OPT, array( 'stats_retention_days' => 7 ) );
t( 'invalid retention falls back to 90', 90 === kt_get_style_settings()['stats_retention_days'] );

// ---- district cap + overflow marker ----
reset_state();
$many = array(); for ( $i = 0; $i < KT_STATS_MAX_DISTRICTS; $i++ ) { $many[ 'd' . $i ] = array( 'label' => 'D', 'views' => 1, 'visitors' => 1, 'clicks' => 0, 'total_seconds' => 0, 'duration_count' => 0, 'daily' => array(), 'updated' => 'x' ); }
update_option( KT_STATS_OPT, $many );
kt_record_district_stat( 'extra', 'views', 'E', array() );
t( 'cap: new district rejected', ! isset( stats()['extra'] ) );
t( 'cap: overflow date recorded', '' !== get_option( KT_STATS_OVERFLOW_OPT, '' ) );
t( 'diagnostics report overflow', '' !== kt_get_tracking_diagnostics()['overflow'] );

// ---- per-session clicks ----
reset_state();
kt_record_district_stat( 'urla', 'clicks', 'Urla', array( 'is_unique' => true, 'session' => '1' ) );
kt_record_district_stat( 'urla', 'clicks', 'Urla', array( 'is_unique' => false, 'session' => '2' ) );
kt_record_district_stat( 'urla', 'clicks', 'Urla', array( 'is_unique' => false, 'session' => '9' ) );
$sc = stats()['urla']['session_clicks'];
t( 'session clicks counted', 1 === $sc['1'] && 1 === $sc['2'] && 3 === stats()['urla']['clicks'] );
$html = kt_ticket_button_shortcode( array( 'url' => 'https://biletinial.com/e/1', 'district' => 'urla', 'session' => '2' ) );
t( 'button has data-kt-session', false !== strpos( $html, 'data-kt-session="2"' ) );
$html = kt_ticket_button_shortcode( array( 'url' => 'https://biletinial.com/e/1', 'district' => 'urla', 'session' => 'x' ) );
t( 'invalid session ignored', false === strpos( $html, 'data-kt-session' ) );
update_option( KT_STYLES_OPT, array( 'utm_ticket_enabled' => 1 ) );
$html = kt_ticket_button_shortcode( array( 'url' => 'https://biletinial.com/e/1', 'district' => 'urla', 'session' => '1' ) );
t( 'utm_content carries session', false !== strpos( $html, 'utm_content=ticket-button-s1' ), $html );

// ---- low conversion alert ----
$rows = array(
	array( 'label' => 'Low', 'visitors' => 100, 'click_visitors' => 1, 'conversion' => 1.0 ),
	array( 'label' => 'Small sample', 'visitors' => 10, 'click_visitors' => 0, 'conversion' => 0.0 ),
	array( 'label' => 'Healthy', 'visitors' => 200, 'click_visitors' => 30, 'conversion' => 15.0 ),
);
$low = kt_get_low_conversion_rows( $rows, 3, 50 );
t( 'alert: only enough-traffic low-conversion rows', 1 === count( $low ) && 'Low' === $low[0]['label'] );
$alert = kt_render_low_conversion_alert( $low, 3, 50 );
t( 'alert html contains label and escapes', false !== strpos( $alert, 'Low' ) && false === strpos( $alert, '<script' ) );

// ---- timezone ----
reset_state();
t( 'default tz Istanbul', 'Europe/Istanbul' === kt_get_site_timezone()->getName() );
t( 'local iso istanbul', '2026-12-01T19:00:00+03:00' === kt_local_to_iso( '2026-12-01', '19:00' ) );
update_option( KT_STYLES_OPT, array( 'timezone' => 'Asia/Tokyo' ) );
t( 'tz setting applied', 'Asia/Tokyo' === kt_get_site_timezone()->getName() );
t( 'local iso tokyo', '2026-12-01T19:00:00+09:00' === kt_local_to_iso( '2026-12-01', '19:00' ) );
update_option( KT_STYLES_OPT, array( 'timezone' => 'Not/AZone' ) );
t( 'invalid tz falls back', 'Europe/Istanbul' === kt_get_style_settings()['timezone'] );
// second session keeps the stored offset even after the setting changed
update_option( KT_STYLES_OPT, array( 'timezone' => 'Asia/Tokyo' ) );
$ev = array( 'datetime' => '2026-12-01T12:00:00+03:00', 'time2' => '14:00', 'city' => 'Urla', 'slug' => 'urla' );
t( 'second session reuses stored offset', '2026-12-01T14:00:00+03:00' === kt_second_session_iso( $ev ) );
t( 'event end uses second session', strtotime( '2026-12-01T14:00:00+03:00' ) === kt_get_event_end_timestamp( $ev ) );
$ev2 = array( 'datetime' => '2026-12-01T12:00:00', 'time2' => '14:00' );
t( 'second session falls back to site tz', '2026-12-01T14:00:00+09:00' === kt_second_session_iso( $ev2 ) );

// ---- font mode ----
reset_state();
t( 'font default google', 'google' === kt_get_style_settings()['font_mode'] && 1 === kt_get_style_settings()['load_google_font'] );
update_option( KT_STYLES_OPT, array( 'load_google_font' => 0 ) );
t( 'legacy load_google_font=0 -> system', 'system' === kt_get_style_settings()['font_mode'] );
update_option( KT_STYLES_OPT, array( 'font_mode' => 'theme' ) );
t( 'theme font mode', 'theme' === kt_get_style_settings()['font_mode'] && 0 === kt_get_style_settings()['load_google_font'] );
update_option( KT_STYLES_OPT, array( 'font_mode' => 'evil' ) );
t( 'invalid font mode falls back', 'google' === kt_get_style_settings()['font_mode'] );

// ---- legacy usage analysis ----
t( 'clean content', array() === kt_analyze_content_for_legacy_usage( '[konser_takvimi] [konser_ilce_yonlendir district="urla"]' ) );
$f = kt_analyze_content_for_legacy_usage( 'x [subadap_konserler] y' );
t( 'deprecated shortcode flagged', 1 === count( $f ) );
$f = kt_analyze_content_for_legacy_usage( '[konser_ilce_yonlendir ilce="urla" hedef="/x" takvime_ekle="1"] [konser_bilet_butonu resim="a.png"]' );
t( 'old attrs flagged', 4 === count( $f ), implode( '|', $f ) );
$f = kt_analyze_content_for_legacy_usage( '<!-- wp:konser-takvimi/ilce-yonlendir {"ilce":"urla"} /-->' );
t( 'old block attr flagged', 1 === count( $f ) );
$f = kt_analyze_content_for_legacy_usage( '<!-- wp:konser-takvimi/ilce-yonlendir {"district":"urla"} /-->' );
t( 'new block attr clean', 0 === count( $f ) );
t( 'unrelated shortcode with ilce ignored', 0 === count( kt_analyze_content_for_legacy_usage( '[other ilce="1"]' ) ) );

// ---- link builder ----
$links = kt_build_utm_links( 'https://subadapcocuk.org/adana-konseri/', 'adana' );
$map = array(); foreach ( $links as $l ) { $map[ $l['label'] ] = $l['url']; }
t( 'builder whatsapp', 'https://subadapcocuk.org/adana-konseri/?utm_source=whatsapp&utm_medium=messaging&utm_campaign=adana' === $map['WhatsApp'], $map['WhatsApp'] );
t( 'builder meta cpc', 'https://subadapcocuk.org/adana-konseri/?utm_source=meta&utm_medium=cpc&utm_campaign=adana' === $map['Meta Reklam'] );
t( 'builder youtube cpc', 'https://subadapcocuk.org/adana-konseri/?utm_source=youtube&utm_medium=cpc&utm_campaign=adana' === $map['YouTube Reklam'] );
t( 'builder newsletter', 'https://subadapcocuk.org/adana-konseri/?utm_source=newsletter&utm_medium=email&utm_campaign=adana' === $map['E-posta bülteni'] );
t( 'builder instagram', 'https://subadapcocuk.org/adana-konseri/?utm_source=instagram&utm_medium=social&utm_campaign=adana' === $map['Instagram (organik)'] );
t( 'builder direct has no utm', 'https://subadapcocuk.org/adana-konseri/' === $map['Doğrudan (etiketsiz)'] );
$links = kt_build_utm_links( 'https://x.org/a/', 'Çocuk Konseri!' );
t( 'builder normalizes campaign', false !== strpos( $links[0]['url'], 'utm_campaign=cocuk' ), $links[0]['url'] );

// ---- archive purge ----
$now = strtotime( '2026-11-21T12:00:00+03:00' );
$events = array(
	array( 'datetime' => '2024-01-01T12:00:00+03:00', 'time2' => '', 'city' => 'Old', 'slug' => 'old' ),
	array( 'datetime' => '2026-08-01T12:00:00+03:00', 'time2' => '', 'city' => 'Recent', 'slug' => 'recent' ),
	array( 'datetime' => '2027-01-01T12:00:00+03:00', 'time2' => '', 'city' => 'Future', 'slug' => 'future' ),
);
list( $kept, $gone ) = kt_purge_archive_older_than( $events, 12, $now );
t( 'purge removes only old archive', 1 === $gone && 2 === count( $kept ) && 'Recent' === $kept[0]['city'] );
list( $kept, $gone ) = kt_purge_archive_older_than( $events, 3, $now );
t( 'purge 3 months removes recent too', 2 === $gone && 'Future' === $kept[0]['city'] );

// ---- tools tab handlers / render ----
reset_state();
update_option( KT_EVENTS_OPT, $events );
$_POST = array( 'kt_purge_archive' => '1', 'purge_months' => '12' );
$msg = kt_handle_tools_actions();
t( 'purge handler message', false !== strpos( $msg, 'notice-success' ) );
t( 'purge handler saved', 2 === count( get_option( KT_EVENTS_OPT ) ) || 3 === count( get_option( KT_EVENTS_OPT ) ) );
$_POST = array();

echo "\n$n assertions, $fail failed\n";
exit( $fail ? 1 : 0 );
