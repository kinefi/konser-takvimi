<?php
error_reporting( E_ALL );
set_error_handler( function ( $no, $str, $file, $line ) { echo "PHP NOTICE/WARNING: $str at " . basename( $file ) . ":$line\n"; return true; } );
require __DIR__ . '/wp_stubs.php';
require dirname( __DIR__ ) . '/konser-takvimi.php';
function wp_rand() { return mt_rand(); }
function wp_list_pluck( $l, $f ) { return array_column( $l, $f ); }
function wp_clear_scheduled_hook() {} function wp_schedule_event( $t, $r, $h ) { $GLOBALS['__cron'][ $h ] = $t; return true; }
function map_deep( $v, $cb ) { return is_array( $v ) ? array_map( fn( $x ) => map_deep( $x, $cb ), $v ) : $cb( $v ); }
function get_posts() { return array(); }
function wp_nonce_field() { echo '<input type="hidden" name="_nonce">'; }
function check_admin_referer() { return true; }
function checked( $a ) { if ( $a ) echo ' checked="checked"'; }
function selected( $a, $b ) { if ( (string) $a === (string) $b ) echo ' selected="selected"'; }
function disabled( $d ) { if ( $d ) echo ' disabled="disabled"'; }
function wp_kses_post( $s ) { return $s; }
function current_user_can( $c = '' ) { return 'manage_options' === $c; }
function sanitize_email( $e ) { return $e; } function is_email( $e ) { return true; }
class KT_Test_Wpdb { public $posts = 'wp_posts'; public function esc_like( $s ) { return $s; } public function prepare( $q, $a = array() ) { return $q; } public function get_results( $q ) { return array(); } }
$GLOBALS['wpdb'] = new KT_Test_Wpdb();
require dirname( __DIR__ ) . '/includes/admin-tools.php';
require dirname( __DIR__ ) . '/includes/admin-page.php';

$fail = 0; $n = 0;
function t( $name, $cond, $detail = '' ) { global $fail, $n; $n++; if ( ! $cond ) { $fail++; echo "FAIL: $name $detail\n"; } }
function reset_state() { $GLOBALS['__opts'] = array(); $GLOBALS['__cron'] = array(); $_GET = array(); $_POST = array(); }
function stats() { return get_option( KT_STATS_OPT, array() ); }
function row( $views, $vis, $clicks, $cv, $since ) {
	return array( 'label' => 'Istanbul', 'views' => $views, 'visitors' => $vis, 'clicks' => $clicks, 'click_visitors' => $cv, 'total_seconds' => 100, 'duration_count' => 4,
		'daily' => array( $since => array( 'views' => $views, 'visitors' => $vis, 'clicks' => $clicks, 'click_visitors' => $cv, 'seconds' => 100, 'sessions' => 4 ) ),
		'utm' => array( 'ig|social|istanbul' => array( 'views' => 3, 'visitors' => 2, 'clicks' => 1, 'click_visitors' => 1 ) ), 'since' => $since, 'updated' => '2026-10-01 10:00:00' );
}

// ---- new rows carry a "since" date ----
reset_state();
update_option( KT_STYLES_OPT, array( 'stats_buffer_seconds' => 0 ) );
kt_record_district_stat( 'izmir', 'views', 'Izmir', array( 'is_unique' => true ) );
t( 'new row has since = today', kt_get_site_date_str( 0 ) === stats()['izmir']['since'] );

// ---- manual archive + reset of one district ----
reset_state();
update_option( KT_STATS_OPT, array( 'istanbul' => row( 100, 60, 20, 12, '2026-09-01' ), 'adana' => row( 10, 5, 1, 1, '2026-10-01' ) ) );
$entry = kt_archive_district_stats( 'istanbul', 'manual', true );
t( 'archive returns entry', is_array( $entry ) && 'istanbul' === $entry['key'] );
t( 'live row removed only for that district', ! isset( stats()['istanbul'] ) && isset( stats()['adana'] ) );
$arch = kt_get_stats_archive();
t( 'archive has 1 entry with full data', 1 === count( $arch ) && 100 === $arch[0]['row']['views'] && 12 === $arch[0]['row']['click_visitors'] && ! empty( $arch[0]['row']['utm'] ) );
t( 'period start from since', '2026-09-01' === $arch[0]['period_start'] );
t( 'period end defaults to today', kt_get_site_date_str( 0 ) === $arch[0]['period_end'] );
t( 'archiving an unknown district fails', false === kt_archive_district_stats( 'nowhere', 'manual', true ) );
t( 'lock released', ! array_key_exists( KT_STATS_LOCK_OPT, $GLOBALS['__opts'] ) );

// period start falls back to the oldest daily day when "since" is missing (rows created before 4.5.0)
$old = row( 5, 3, 1, 1, '2026-08-15' ); unset( $old['since'] );
t( 'period start fallback', '2026-08-15' === kt_stats_period_start( $old ) );

// ---- restore ----
t( 'restore ok', 'ok' === kt_restore_archived_stats( $entry['id'] ) );
t( 'restored row equals the archived one', 100 === stats()['istanbul']['views'] && '2026-09-01' === stats()['istanbul']['since'] );
t( 'archive entry removed after restore', array() === kt_get_stats_archive() );
t( 'restore of a missing id', 'missing' === kt_restore_archived_stats( 'nope' ) );

// restore refused while a live row exists
$e2 = kt_archive_district_stats( 'istanbul', 'manual', false );
t( 'archive without reset keeps live row', isset( stats()['istanbul'] ) );
t( 'restore refused when live row exists', 'exists' === kt_restore_archived_stats( $e2['id'] ) && 1 === count( kt_get_stats_archive() ) );

// ---- delete an archive entry ----
t( 'delete entry', true === kt_delete_archive_entry( $e2['id'] ) && array() === kt_get_stats_archive() );
t( 'delete unknown entry', false === kt_delete_archive_entry( 'nope' ) );

// ---- archive everything ----
reset_state();
update_option( KT_STATS_OPT, array( 'a' => row( 1, 1, 0, 0, '2026-10-01' ), 'b' => row( 2, 1, 0, 0, '2026-10-02' ) ) );
t( 'archive all returns count', 2 === kt_archive_all_district_stats() );
t( 'archive all clears live stats', array() === stats() );
t( 'archive all keeps both entries', 2 === count( kt_get_stats_archive() ) && 'reset_all' === kt_get_stats_archive()[0]['reason'] );
t( 'archive all on empty stats', 0 === kt_archive_all_district_stats() );

// ---- archive size cap keeps the newest entries ----
reset_state();
$many = array();
for ( $i = 0; $i < KT_STATS_ARCHIVE_MAX + 5; $i++ ) { $many[] = array( 'id' => 'x' . $i, 'key' => 'k', 'row' => array() ); }
kt_append_to_stats_archive( $many );
$a = kt_get_stats_archive();
t( 'archive capped', KT_STATS_ARCHIVE_MAX === count( $a ) && 'x' . ( KT_STATS_ARCHIVE_MAX + 4 ) === $a[ count( $a ) - 1 ]['id'] && 'x5' === $a[0]['id'] );

// ---- automatic archiving of finished concerts ----
reset_state();
update_option( KT_STYLES_OPT, array( 'stats_buffer_seconds' => 0 ) );
$past   = kt_local_to_iso( kt_get_site_date_str( -10 ), '15:00' );
$past2  = kt_local_to_iso( kt_get_site_date_str( -20 ), '15:00' );
$future = kt_local_to_iso( kt_get_site_date_str( 10 ), '15:00' );
update_option( KT_EVENTS_OPT, array(
	array( 'datetime' => $past2, 'time2' => '', 'city' => 'Istanbul', 'slug' => 'istanbul-konseri' ),
	array( 'datetime' => $past, 'time2' => '', 'city' => 'Adana', 'slug' => 'adana-konseri' ),
	array( 'datetime' => $past2, 'time2' => '', 'city' => 'Izmir', 'slug' => 'izmir-konseri' ),
	array( 'datetime' => $future, 'time2' => '', 'city' => 'Izmir', 'slug' => 'izmir-konseri' ),
	array( 'datetime' => $past, 'time2' => '', 'city' => 'Bursa', 'slug' => 'bursa-konseri' ),
) );
update_option( KT_STATS_OPT, array( 'istanbul' => row( 100, 60, 20, 12, '2026-01-01' ), 'adana' => row( 10, 5, 1, 1, '2026-01-02' ), 'izmir' => row( 7, 4, 1, 1, '2026-01-03' ), 'ankara' => row( 9, 9, 0, 0, '2026-01-04' ) ) );
$done = kt_auto_archive_finished_stats();
t( 'auto: two finished districts archived', 2 === $done );
t( 'auto: finished districts reset', ! isset( stats()['istanbul'] ) && ! isset( stats()['adana'] ) );
t( 'auto: district with an upcoming concert kept', isset( stats()['izmir'] ) );
t( 'auto: district without any concert kept', isset( stats()['ankara'] ) );
t( 'auto: district with a concert but no stats is ignored (bursa)', 2 === count( kt_get_stats_archive() ) );
$by = array(); foreach ( kt_get_stats_archive() as $e ) { $by[ $e['key'] ] = $e; }
t( 'auto: reason', 'auto' === $by['istanbul']['reason'] );
t( 'auto: period ends on the day the concert ended', kt_get_site_date_str( -20 ) === $by['istanbul']['period_end'] && kt_get_site_date_str( -10 ) === $by['adana']['period_end'] );
t( 'auto: second run archives nothing', 0 === kt_auto_archive_finished_stats() );

// grace period: a concert that ended a few hours ago is not archived yet
reset_state();
update_option( KT_EVENTS_OPT, array( array( 'datetime' => kt_local_to_iso( kt_get_site_date_str( 0 ), '00:01' ), 'time2' => '', 'city' => 'X', 'slug' => 'x-konseri' ) ) );
update_option( KT_STATS_OPT, array( 'x' => row( 3, 2, 0, 0, '2026-01-01' ) ) );
t( 'auto: nothing archived within one day after the concert', 0 === kt_auto_archive_finished_stats() && isset( stats()['x'] ) );

// switched off in the settings
reset_state();
update_option( KT_STYLES_OPT, array( 'auto_archive_stats' => 0 ) );
update_option( KT_EVENTS_OPT, array( array( 'datetime' => $past, 'time2' => '', 'city' => 'A', 'slug' => 'a-konseri' ) ) );
update_option( KT_STATS_OPT, array( 'a' => row( 3, 2, 0, 0, '2026-01-01' ) ) );
t( 'auto: disabled in settings', 0 === kt_auto_archive_finished_stats() && isset( stats()['a'] ) );
t( 'auto archive on by default', 1 === kt_get_default_style_settings()['auto_archive_stats'] );

// cron registration
reset_state();
kt_ensure_archive_cron_scheduled();
t( 'daily job scheduled once', isset( $GLOBALS['__cron'][ KT_ARCHIVE_CRON_HOOK ] ) );

// ---- admin screens ----
reset_state();
$GLOBALS['__opts'][ KT_STATS_OPT ] = array( 'istanbul' => row( 100, 60, 20, 12, '2026-09-01' ) );
$_GET = array( 'tab' => 'stats' ); $_POST = array( 'kt_archive_stat_key' => 'istanbul' );
ob_start(); kt_render_admin_page(); $html = ob_get_clean();
t( 'stats tab: archive+reset done', ! isset( stats()['istanbul'] ) && 1 === count( kt_get_stats_archive() ) );
t( 'stats tab: message links to the archive', false !== strpos( $html, 'tab=archive' ) );

$_GET = array( 'tab' => 'archive' ); $_POST = array();
ob_start(); kt_render_admin_page(); $html = ob_get_clean();
t( 'archive tab: nav entry', false !== strpos( $html, 'tab=archive' ) );
t( 'archive tab: entry shown with period', false !== strpos( $html, '2026-09-01' ) && false !== strpos( $html, '<code>istanbul</code>' ) );
t( 'archive tab: restore enabled', false === strpos( $html, 'kt_restore_archive_id" value="' . kt_get_stats_archive()[0]['id'] . '" class="button button-small" disabled' ) );
t( 'archive tab: no settings/stats form', false === strpos( $html, 'kt_styles_nonce' ) );

$id = kt_get_stats_archive()[0]['id'];
$_POST = array( 'kt_restore_archive_id' => $id );
ob_start(); kt_render_admin_page(); $html = ob_get_clean();
t( 'archive tab: restore via form', isset( stats()['istanbul'] ) && array() === kt_get_stats_archive() );
t( 'archive tab: empty state', false !== strpos( $html, 'Henüz arşivlenmiş istatistik yok' ) );

// reset all from the stats tab goes through the archive
$GLOBALS['__opts'][ KT_STATS_OPT ] = array( 'a' => row( 1, 1, 0, 0, '2026-10-01' ), 'b' => row( 2, 1, 0, 0, '2026-10-02' ) );
$_GET = array( 'tab' => 'stats' ); $_POST = array( 'kt_reset_stats' => '1' );
ob_start(); kt_render_admin_page(); ob_get_clean();
t( 'stats tab: reset all archives first', array() === stats() && 2 === count( kt_get_stats_archive() ) );

// restore blocked by a live row shows a warning and keeps the entry
$GLOBALS['__opts'][ KT_STATS_OPT ] = array( 'a' => row( 5, 5, 0, 0, '2026-11-01' ) );
$_GET = array( 'tab' => 'archive' );
$aid = null; foreach ( kt_get_stats_archive() as $e ) { if ( 'a' === $e['key'] ) { $aid = $e['id']; } }
$_POST = array( 'kt_restore_archive_id' => $aid );
ob_start(); kt_render_admin_page(); $html = ob_get_clean();
t( 'archive tab: restore refused with warning', 5 === stats()['a']['views'] && false !== strpos( $html, 'notice-warning' ) && 2 === count( kt_get_stats_archive() ) );

echo "$n assertions, $fail failed\n";
exit( $fail ? 1 : 0 );
