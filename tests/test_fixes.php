<?php
// Tests for the 4.1.0 fixes and the unique-clicker conversion rate.
require __DIR__ . '/wp_stubs.php';
require dirname( __DIR__ ) . '/konser-takvimi.php';

$fail = 0; $n = 0;
function t( $name, $cond, $detail = '' ) { global $fail, $n; $n++; if ( ! $cond ) { $fail++; echo "FAIL: $name $detail\n"; } }
function reset_state() { $GLOBALS['__opts'] = array(); $GLOBALS['__localized'] = array(); $GLOBALS['__json'] = null; $_POST = array(); }

// ---- conversion rate ----
t( 'conversion basic', 25.0 === kt_conversion_rate( 1, 4 ) );
t( 'conversion zero visitors', 0.0 === kt_conversion_rate( 3, 0 ) );
t( 'conversion capped at 100', 100.0 === kt_conversion_rate( 9, 4 ) );

reset_state();
$utm = array( 'source' => 'ig', 'medium' => 'social', 'campaign' => 'urla' );
kt_record_district_stat( 'urla', 'views', 'Urla', array( 'is_unique' => true ) );
kt_record_district_stat( 'urla', 'views', 'Urla', array( 'is_unique' => true ) );
kt_record_district_stat( 'urla', 'clicks', 'Urla', array( 'is_unique' => true, 'utm' => $utm ) );
kt_record_district_stat( 'urla', 'clicks', 'Urla', array( 'is_unique' => false, 'utm' => $utm ) );
$s = get_option( KT_STATS_OPT );
t( 'total clicks vs unique clickers', 2 === $s['urla']['clicks'] && 1 === $s['urla']['click_visitors'], json_encode( $s['urla'] ) );
t( 'daily unique clickers', 1 === array_values( $s['urla']['daily'] )[0]['click_visitors'] );
t( 'utm unique clickers', 2 === $s['urla']['utm']['ig|social|urla']['clicks'] && 1 === $s['urla']['utm']['ig|social|urla']['click_visitors'] );
$b = kt_build_stats_report_bundle( 7 );
t( 'report has ctr and conversion', 1 === $b['sum_click_visitors'] && '50,0' === $b['overall_conversion'] && isset( $b['overall_ctr'] ), json_encode( array( $b['overall_conversion'], $b['overall_ctr'] ) ) );
t( 'report row ctr + conversion', 50.0 === $b['rows'][0]['conversion'] && isset( $b['rows'][0]['ctr'] ) );
t( 'report html shows both', false !== strpos( $b['html_card'], 'Tıklayan kişi / ziyaretçi' ) && false !== strpos( $b['html_card'], 'CTR' ) );
// legacy row without click_visitors
update_option( KT_STATS_OPT, array( 'old' => array( 'label' => 'Old', 'views' => 5, 'visitors' => 3, 'clicks' => 1, 'daily' => array() ) ) );
kt_record_district_stat( 'old', 'clicks', 'Old', array( 'is_unique' => true ) );
t( 'legacy row click_visitors', 1 === get_option( KT_STATS_OPT )['old']['click_visitors'] );

// ---- lock ----
reset_state();
t( 'lock acquired', kt_acquire_stats_lock() );
$GLOBALS['__t0'] = microtime( true );
t( 'lock released allows next', ( kt_release_stats_lock() || true ) && kt_acquire_stats_lock() );
kt_release_stats_lock();
update_option( KT_STATS_LOCK_OPT, time() - 60 );
t( 'stale lock is taken over', kt_acquire_stats_lock() );
kt_release_stats_lock();
t( 'lock option removed after record', ( kt_record_district_stat( 'a', 'views', 'A', array() ) || true ) && ! array_key_exists( KT_STATS_LOCK_OPT, $GLOBALS['__opts'] ) );

// ---- district cap ----
reset_state();
$many = array(); for ( $i = 0; $i < KT_STATS_MAX_DISTRICTS; $i++ ) { $many[ "d$i" ] = array( 'label' => "d$i", 'views' => 1, 'visitors' => 1, 'clicks' => 0, 'daily' => array() ); }
update_option( KT_STATS_OPT, $many );
kt_record_district_stat( 'brand-new', 'views', 'New', array( 'is_unique' => true ) );
t( 'new district rejected at cap', ! isset( get_option( KT_STATS_OPT )['brand-new'] ) );
kt_record_district_stat( 'd5', 'views', 'D5', array( 'is_unique' => true ) );
t( 'existing district still counted at cap', 2 === get_option( KT_STATS_OPT )['d5']['views'] );
t( 'district key length capped', 80 === strlen( kt_normalize_district_key( str_repeat( 'a', 300 ) ) ) );

// ---- AJAX without nonce ----
reset_state();
$_POST = array( 'district' => 'urla', 'event_type' => 'clicks', 'is_unique' => '1', 'label' => str_repeat( 'x', 500 ) );
kt_ajax_track_district_event();
$s = get_option( KT_STATS_OPT );
t( 'ajax works without nonce', true === $GLOBALS['__json']['ok'] && 1 === $s['urla']['click_visitors'] );
t( 'label truncated', strlen( $s['urla']['label'] ) <= 120 );
t( 'no nonce in tracker data', ! array_key_exists( 'nonce', ( function () { kt_enqueue_tracker_script( 'urla', 'Urla', true ); return $GLOBALS['__localized']['ktTrackerData']; } )() ) );

// ---- redirect resolution ----
reset_state();
update_option( KT_STYLES_OPT, array( 'redirect_target' => '/konserler' ) );
t( 'default relative target', 'https://subadapcocuk.org/konserler' === kt_resolve_redirect_url( '' ) );
t( 'same-host absolute allowed', 'https://subadapcocuk.org/x' === kt_resolve_redirect_url( 'https://subadapcocuk.org/x' ) );
t( 'author external host rejected -> default', 'https://subadapcocuk.org/konserler' === kt_resolve_redirect_url( 'https://evil.example/phish' ), kt_resolve_redirect_url( 'https://evil.example/phish' ) );
update_option( KT_STYLES_OPT, array( 'redirect_target' => 'https://tickets.example.com/all' ) );
t( 'settings external host works', 'https://tickets.example.com/all' === kt_resolve_redirect_url( '' ) );
t( 'same external host as settings allowed for attributes', 'https://tickets.example.com/urla' === kt_resolve_redirect_url( 'https://tickets.example.com/urla' ) );
t( 'javascript scheme stays a site-local path', 0 === strpos( kt_resolve_redirect_url( 'javascript:alert(1)' ), 'https://tickets.example.com/' ) || 0 === strpos( kt_resolve_redirect_url( 'javascript:alert(1)' ), 'https://subadapcocuk.org/' ) );

// ---- schema endDate ----
reset_state();
$item = kt_build_music_event_schema_item( array( 'datetime' => '2099-05-02T12:00:00+03:00', 'time2' => '', 'city' => 'Urla', 'slug' => 'urla-konseri' ), 'Band' );
t( 'single session endDate after start', '2099-05-02T13:00:00+03:00' === $item['endDate'], $item['endDate'] );
$item = kt_build_music_event_schema_item( array( 'datetime' => '2099-05-02T12:00:00+03:00', 'time2' => '14:00', 'city' => 'Urla', 'slug' => 'urla-konseri' ), 'Band' );
t( 'double session endDate after last start', '2099-05-02T15:00:00+03:00' === $item['endDate'], $item['endDate'] );

// ---- Google font setting ----
$GLOBALS['__styles_registered'] = array();
echo '';
t( 'font default on', 1 === kt_get_style_settings()['load_google_font'] );
update_option( KT_STYLES_OPT, array( 'load_google_font' => 0 ) );
t( 'font can be disabled', 0 === kt_get_style_settings()['load_google_font'] );

echo "\n$n assertions, $fail failed\n";
exit( $fail ? 1 : 0 );
