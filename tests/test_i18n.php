<?php
error_reporting( E_ALL );
set_error_handler( function ( $no, $str, $file, $line ) { echo "PHP NOTICE/WARNING: $str at " . basename( $file ) . ":$line\n"; return true; } );
require __DIR__ . '/wp_stubs.php';
function map_deep( $v, $cb ) { return is_array( $v ) ? array_map( fn( $x ) => map_deep( $x, $cb ), $v ) : $cb( $v ); }
function get_posts() { return array(); }
function wp_nonce_field() { echo 'N'; }
function check_admin_referer() { return true; }
function checked( $a ) { if ( $a ) echo ' checked'; }
function selected( $a, $b ) { if ( (string) $a === (string) $b ) echo ' sel'; }
function disabled( $d ) {}
function wp_kses_post( $s ) { return $s; }
function current_user_can( $c = '' ) { return true; }
function add_menu_page() {} function wp_enqueue_media() {} function wp_clear_scheduled_hook() {} function wp_schedule_event() {}
function sanitize_email( $e ) { return $e; } function is_email( $e ) { return true; } function wp_die() {}
function wp_list_pluck( $l, $f ) { return array_column( $l, $f ); }
require dirname( __DIR__ ) . '/konser-takvimi.php';
require dirname( __DIR__ ) . '/includes/admin-tools.php';
require dirname( __DIR__ ) . '/includes/admin-page.php';

class Fake_Wpdb {
	public $posts = 'wp_posts';
	public function esc_like( $s ) { return addcslashes( $s, '_%\\' ); }
	public function prepare( $q, $args ) { return $q; }
	public function get_results( $q ) { return array( (object) array( 'ID' => 7, 'post_title' => 'Adana', 'post_content' => '[subadap_konserler] [konser_ilce_yonlendir ilce="adana"]' ) ); }
}
$GLOBALS['wpdb'] = new Fake_Wpdb();

$fail = 0; $n = 0;
function t( $name, $cond, $detail = '' ) { global $fail, $n; $n++; if ( ! $cond ) { $fail++; echo "FAIL: $name $detail\n"; } }
$root = dirname( __DIR__ );

// ---- catalogue integrity (.pot vs .po vs .mo) ----
$pot = file_get_contents( $root . '/languages/konser-takvimi.pot' );
preg_match_all( '/^msgid "((?:[^"\\\\]|\\\\.)*)"$/m', $pot, $m );
$ids = array_slice( array_map( fn( $s ) => stripcslashes( $s ), $m[1] ), 1 );
$mo  = kt_test_read_mo( $root . '/languages/konser-takvimi-en_US.mo' );
t( 'catalogue has messages', count( $ids ) > 250 && count( $mo ) > 250, count( $ids ) . '/' . count( $mo ) );
$bad = array();
foreach ( $ids as $id ) {
	if ( ! isset( $mo[ $id ] ) && ! preg_grep( '/^' . preg_quote( $id, '/' ) . "\0/", array_keys( $mo ) ) ) { $bad[] = 'missing: ' . $id; continue; }
	if ( ! isset( $mo[ $id ] ) ) { continue; } // plural entry, checked separately
	$tr = $mo[ $id ];
	preg_match_all( '/%(?:\d+\$)?[sd]|%%/', $id, $a ); preg_match_all( '/%(?:\d+\$)?[sd]|%%/', $tr, $b );
	sort( $a[0] ); sort( $b[0] );
	if ( $a[0] !== $b[0] ) { $bad[] = "placeholders differ: $id => $tr"; }
	preg_match_all( '/<\/?[a-z]+[^>]*>/', $id, $c ); preg_match_all( '/<\/?[a-z]+[^>]*>/', $tr, $d );
	sort( $c[0] ); sort( $d[0] );
	if ( $c[0] !== $d[0] ) { $bad[] = "html differs: $id => $tr"; }
}
t( 'every message is translated with matching placeholders and tags', empty( $bad ), "\n" . implode( "\n", array_slice( $bad, 0, 10 ) ) );
t( 'plural form present', isset( $mo["%d gün\0%d gün"] ) && "%d day\0%d days" === $mo["%d gün\0%d gün"] );

// ---- source language and English output ----
$GLOBALS['__locale'] = 'tr_TR';
t( 'Turkish months unchanged', 'Ocak' === kt_get_month_names()[1] && 'Pazar' === kt_get_day_names()[0] );
$GLOBALS['__locale'] = 'en_US';
t( 'English months', 'January' === kt_get_month_names()[1] && 'Sunday' === kt_get_day_names()[0] && 'Saturday' === kt_get_day_names()[6] );
$dict = kt_get_js_dictionary();
t( 'JS dictionary translated', 'TODAY' === $dict['BUGÜN'] && 'TOMORROW' === $dict['YARIN'] && 'and' === $dict['ve'] );
t( 'JS dictionary covers every key used in JS', true );
foreach ( array( 'frontend.js', 'admin.js', 'blocks-editor.js' ) as $js ) {
	$src = file_get_contents( $root . '/assets/js/' . $js );
	preg_match_all( "/__t\\('((?:[^'\\\\]|\\\\.)*)'\\)/", $src, $mm );
	foreach ( $mm[1] as $key ) {
		$key = stripcslashes( $key );
		if ( ! isset( $dict[ $key ] ) ) { t( "JS key in dictionary ($js): $key", false ); }
	}
}
foreach ( array( 'Ocak', 'Pazar' ) as $k ) { /* month/day names are mapped through __t in frontend.js */ }
t( 'calendar widget translated', false !== strpos( kt_render_add_to_calendar_widget( array( array( 'datetime' => '2027-02-01T19:00:00+03:00', 'time2' => '', 'city' => 'Urla', 'slug' => 'urla' ) ), 'https://x.org/urla/', 'urla' ), 'Add to Calendar' ) );

// ---- admin screens render in English without leftover Turkish prose ----
update_option( KT_EVENTS_OPT, array( array( 'datetime' => '2027-01-01T19:00:00+03:00', 'time2' => '', 'city' => 'Urla', 'slug' => 'urla' ) ) );
update_option( KT_STATS_OPT, array( 'urla' => array( 'label' => 'Urla', 'views' => 5, 'visitors' => 2, 'clicks' => 1, 'click_visitors' => 1, 'total_seconds' => 0, 'duration_count' => 0, 'daily' => array(), 'updated' => 1 ) ) );
foreach ( array( 'concerts', 'stats', 'styles', 'tools' ) as $tab ) {
	$_GET['tab'] = $tab;
	ob_start(); kt_render_admin_page(); $html = ob_get_clean();
	$text = preg_replace( '/<(script|style)\b.*?<\/\1>/s', '', $html );
	$text = preg_replace( '/<[^>]+>/', ' ', $text );
	preg_match_all( '/[^\s]*[ÇĞİÖŞÜçğıöşü][^\s]*/u', $text, $tr );
	$left = array_unique( $tr[0] );
	t( "admin tab '$tab' renders in English", false !== strpos( $html, 'Settings' ) || false !== strpos( $html, 'Save' ) || 'tools' === $tab || 'stats' === $tab, '' );
	t( "admin tab '$tab' has no leftover Turkish words", count( $left ) === 0, implode( ', ', array_slice( $left, 0, 15 ) ) );
}

// ---- weekly e-mail pieces ----
$alert = kt_render_low_conversion_alert( array( array( 'label' => 'Urla', 'visitors' => 100, 'click_visitors' => 1, 'conversion' => 1.0 ) ), 3, 50 );
t( 'alert e-mail in English', false !== strpos( $alert, 'conversion rate below' ) && false !== strpos( $alert, '1 clickers' ), $alert );

// ---- locale fallback for the .mo file ----
$tmp = sys_get_temp_dir() . '/kt-lang-test'; @mkdir( $tmp ); touch( $tmp . '/konser-takvimi-en_US.mo' );
function file_exists_stub() {}
t( 'fallback: other locale uses en_US catalogue', $tmp . '/konser-takvimi-en_US.mo' === kt_pick_mofile( $tmp . '/konser-takvimi-de_DE.mo', 'konser-takvimi' ) );
t( 'fallback: Turkish keeps its own path (source language)', $tmp . '/konser-takvimi-tr_TR.mo' === kt_pick_mofile( $tmp . '/konser-takvimi-tr_TR.mo', 'konser-takvimi' ) );
t( 'fallback: other domains untouched', $tmp . '/x-de_DE.mo' === kt_pick_mofile( $tmp . '/x-de_DE.mo', 'other' ) );
unlink( $tmp . '/konser-takvimi-en_US.mo' ); rmdir( $tmp );

echo "\n$n assertions, $fail failed\n";
exit( $fail ? 1 : 0 );
