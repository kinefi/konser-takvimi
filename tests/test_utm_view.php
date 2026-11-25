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
if ( ! function_exists( 'wp_delete_file' ) ) { function wp_delete_file( $f ) { unlink( $f ); } }
if ( ! function_exists( 'add_query_arg' ) ) { function add_query_arg( $a, $u ) { return $u . '?' . http_build_query( $a ); } }
require dirname( __DIR__ ) . '/includes/admin-page.php';

$fail = 0; $n = 0;
function t( $name, $cond, $detail = '' ) { global $fail, $n; $n++; if ( ! $cond ) { $fail++; echo "FAIL: $name $detail\n"; } }
function u( $s, $m, $c, $v, $vw, $cl, $cv ) { return array( 'source' => $s, 'medium' => $m, 'campaign' => $c, 'visitors' => $v, 'views' => $vw, 'clicks' => $cl, 'click_visitors' => $cv ); }
$stats = array(
	'istanbul' => array( 'label' => 'İstanbul', 'utm' => array( 'a' => u( 'whatsapp', 'messaging', 'istanbul', 100, 120, 30, 25 ), 'b' => u( 'meta', 'cpc', 'istanbul', 400, 450, 20, 18 ) ) ),
	'adana'    => array( 'label' => 'Adana', 'utm' => array( 'c' => u( 'meta', 'cpc', 'adana', 200, 210, 40, 35 ), 'd' => u( 'youtube', 'cpc', 'adana', 50, 55, 0, 0 ), 'e' => u( '', 'email', 'adana', 10, 10, 2, 2 ) ) ),
);
$rows = kt_get_utm_report_rows( $stats );
t( '5 rows', 5 === count( $rows ) );

// filter
t( 'filter by district', 2 === count( kt_filter_utm_rows( $rows, 'istanbul', '' ) ) );
t( 'filter by source', 2 === count( kt_filter_utm_rows( $rows, '', 'meta' ) ) );
t( 'filter by both', 1 === count( kt_filter_utm_rows( $rows, 'adana', 'meta' ) ) );
t( 'no filter keeps all', 5 === count( kt_filter_utm_rows( $rows ) ) );

// sort
$by = function ( $r, $k ) { return array_column( $r, $k ); };
t( 'sort visitors desc by default', array( 400, 200, 100, 50, 10 ) === $by( kt_sort_utm_rows( $rows ), 'visitors' ) );
t( 'sort visitors asc', array( 10, 50, 100, 200, 400 ) === $by( kt_sort_utm_rows( $rows, 'visitors', 'asc' ), 'visitors' ) );
t( 'sort district A-Z by default (text)', array( 'Adana', 'Adana', 'Adana', 'İstanbul', 'İstanbul' ) === $by( kt_sort_utm_rows( $rows, 'district' ), 'district' ) );
t( 'sort source A-Z, ties by visitors', array( 'meta', 'meta' ) === array_slice( $by( kt_sort_utm_rows( $rows, 'source', 'asc' ), 'source' ), 1, 2 ) && '' === kt_sort_utm_rows( $rows, 'source', 'asc' )[0]['source'] );
t( 'sort by conversion desc', 'whatsapp' === kt_sort_utm_rows( $rows, 'conversion' )[0]['source'] && array( 25.0, 20.0, 17.5, 4.5, 0.0 ) === array_map( 'floatval', array_column( kt_sort_utm_rows( $rows, 'conversion' ), 'conversion' ) ) );
t( 'sort by ctr desc', array( 25.0, 20.0, 19.0, 4.4, 0.0 ) === array_map( 'floatval', array_column( kt_sort_utm_rows( $rows, 'ctr' ), 'ctr' ) ) );
t( 'unknown sort column falls back to visitors', 400 === kt_sort_utm_rows( $rows, 'evil' )[0]['visitors'] );

// group
$g = kt_group_utm_rows( $rows, 'district' );
$gm = array(); foreach ( $g as $x ) { $gm[ $x['group_key'] ] = $x; }
t( 'group by district sums', 500 === $gm['istanbul']['visitors'] && 260 === $gm['adana']['visitors'] && 2 === $gm['istanbul']['rows'] && 3 === $gm['adana']['rows'] );
t( 'group label is the district name', 'İstanbul' === $gm['istanbul']['group'] );
t( 'group ctr recomputed from sums', round( 50 / 570 * 100, 1 ) === $gm['istanbul']['ctr'] );
t( 'group conversion recomputed', abs( $gm['istanbul']['conversion'] - kt_conversion_rate( 43, 500 ) ) < 0.001 );
$gs = array(); foreach ( kt_group_utm_rows( $rows, 'source' ) as $x ) { $gs[ $x['group_key'] ] = $x; }
t( 'group by source merges cities', 600 === $gs['meta']['visitors'] && 2 === $gs['meta']['rows'] && isset( $gs[''] ) && 10 === $gs['']['visitors'] );
t( 'unknown group returns rows unchanged', $rows === kt_group_utm_rows( $rows, 'evil' ) );

// view normalisation
$v = kt_normalize_utm_view( array( 'group' => 'DROP TABLE', 'sort' => '<script>', 'dir' => 'sideways', 'district' => 'İstanbul-konseri', 'source' => " meta<b>\n" ) );
t( 'bad view values are neutralised', '' === $v['group'] && '' === $v['sort'] && '' === $v['dir'] && 'meta' === $v['source'] );
t( 'district filter keeps the key format', 'istanbul' === kt_normalize_utm_view( array( 'district' => 'istanbul-konseri' ) )['district'] );
t( 'group sort only valid when grouped', '' === kt_normalize_utm_view( array( 'sort' => 'group' ) )['sort'] && 'group' === kt_normalize_utm_view( array( 'group' => 'source', 'sort' => 'group' ) )['sort'] );

// view rows
$vr = kt_get_utm_view_rows( $stats, kt_normalize_utm_view( array( 'group' => 'source', 'sort' => 'group', 'dir' => 'asc' ) ) );
t( 'grouped view sorted by group name', array( '', 'meta', 'whatsapp', 'youtube' ) === array_column( $vr, 'group' ) );
$vr = kt_get_utm_view_rows( $stats, kt_normalize_utm_view( array( 'group' => 'district' ) ) );
t( 'grouped view default: most visitors first', 'istanbul' === $vr[0]['group_key'] );
$vr = kt_get_utm_view_rows( $stats, kt_normalize_utm_view( array( 'district' => 'adana', 'sort' => 'clicks' ) ) );
t( 'filtered + sorted', 3 === count( $vr ) && 40 === $vr[0]['clicks'] );

// sheets
$sheets = kt_build_utm_export_sheets( $stats, kt_normalize_utm_view( array() ) );
t( '5 sheets', 5 === count( $sheets ) );
t( 'detail sheet header + 5 rows', 6 === count( $sheets[0]['rows'] ) && 10 === count( $sheets[0]['rows'][0] ) );
t( 'empty source shown as dash', '—' === $sheets[0]['rows'][5][0] || in_array( '—', array_column( $sheets[0]['rows'], 0 ), true ) );
t( 'numbers stay numbers', is_int( $sheets[0]['rows'][1][4] ) && is_float( $sheets[0]['rows'][1][7] ) );
t( 'summary sheets have a group column + 6 numbers', 7 === count( $sheets[1]['rows'][0] ) && 3 === count( $sheets[1]['rows'] ) );
$filtered = kt_build_utm_export_sheets( $stats, kt_normalize_utm_view( array( 'district' => 'adana' ) ) );
t( 'export honours the filter', 4 === count( $filtered[0]['rows'] ) && 2 === count( $filtered[1]['rows'] ) );

// xlsx writer
t( 'column letters', 'A' === kt_xlsx_col( 0 ) && 'Z' === kt_xlsx_col( 25 ) && 'AA' === kt_xlsx_col( 26 ) && 'AB' === kt_xlsx_col( 27 ) );
t( 'sheet names are cleaned and unique', 'a b' === kt_xlsx_sheet_name( 'a/b', array() ) && 31 === strlen( kt_xlsx_sheet_name( str_repeat( 'x', 40 ), array() ) ) && 'x 2' === kt_xlsx_sheet_name( 'x', array( 'x' ) ) );
t( 'xml escaping and control characters', '&lt;a&gt; &amp; &quot;q&quot;' === kt_xlsx_escape( "<a> & \"q\"\x01" ) );
if ( kt_xlsx_available() ) {
	$bytes = kt_build_xlsx( $sheets );
	t( 'xlsx built', is_string( $bytes ) && 'PK' === substr( $bytes, 0, 2 ) );
	file_put_contents( '/tmp/kt_test_export.xlsx', $bytes );
	$tricky = kt_build_xlsx( array( array( 'name' => 'T/est', 'rows' => array( array( 'h' ), array( '=HYPERLINK("x")' ), array( "a\x01<b>" ), array( 3.5 ) ) ) ) );
	file_put_contents( '/tmp/kt_test_tricky.xlsx', $tricky );
	t( 'empty workbook is refused', false === kt_build_xlsx( array() ) );
} else {
	echo "(ZipArchive missing: xlsx build skipped)\n";
}

// stats tab renders the new controls
$GLOBALS['__opts'] = array( KT_STATS_OPT => $stats );
$_GET = array( 'tab' => 'stats', 'utm_group' => 'source', 'utm_sort' => 'visitors', 'utm_dir' => 'asc' ); $_POST = array();
ob_start(); kt_render_admin_page(); $html = ob_get_clean();
t( 'stats tab: export button', false !== strpos( $html, 'kt_export_utm_action' ) && false !== strpos( $html, '.xlsx' ) );
t( 'stats tab: grouped header and rows', false !== strpos( $html, '<strong>meta</strong>' ) );
t( 'stats tab: sort links keep the group and flip the direction', false !== strpos( $html, 'utm_group=source' ) && false !== strpos( $html, 'utm_dir=desc' ) && false !== strpos( $html, '#kt-utm' ) );
t( 'stats tab: filter selects list districts and sources', false !== strpos( $html, 'name="utm_district"' ) && false !== strpos( $html, '<option value="adana"' ) && false !== strpos( $html, '<option value="youtube"' ) );
t( 'stats tab: hidden export fields carry the view', false !== strpos( $html, 'name="utm_group" value="source"' ) );
$_GET = array( 'tab' => 'stats', 'utm_district' => 'adana' );
ob_start(); kt_render_admin_page(); $html = ob_get_clean();
t( 'stats tab: detailed rows filtered by district', false === strpos( $html, '<code>whatsapp</code>' ) && false !== strpos( $html, '<code>youtube</code>' ) );

echo "$n assertions, $fail failed\n";
exit( $fail ? 1 : 0 );
