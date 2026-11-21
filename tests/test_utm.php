<?php
require __DIR__ . '/wp_stubs.php';
require dirname( __DIR__ ) . '/konser-takvimi.php';

$fail = 0; $n = 0;
function t( $name, $cond, $detail = '' ) { global $fail, $n; $n++; if ( ! $cond ) { $fail++; echo "FAIL: $name $detail\n"; } }
function reset_state() { $GLOBALS['__opts'] = array(); $GLOBALS['__localized'] = array(); $GLOBALS['__json'] = null; $_POST = array(); }

// ---- sanitizer ----
t( 'sanitize basic', kt_sanitize_utm_value( 'Instagram Story!' ) === 'instagram-story' );
t( 'sanitize turkish', kt_sanitize_utm_value( 'İstanbul Şehir' ) === 'istanbul-sehir', kt_sanitize_utm_value( 'İstanbul Şehir' ) );
t( 'sanitize strips injection', 1 === preg_match( '/^[a-z0-9._\-]*$/', kt_sanitize_utm_value( '<script>x</script>a&b=c"\'' ) ) );
t( 'sanitize length', strlen( kt_sanitize_utm_value( str_repeat( 'a', 200 ) ) ) === 64 );
t( 'sanitize pipe removed', false === strpos( kt_sanitize_utm_value( 'a|b|c' ), '|' ) );

// ---- append ----
$p = array( 'utm_source' => 'site', 'utm_medium' => 'referral' );
t( 'append plain', kt_append_utm_params( 'https://biletinial.com/x', $p ) === 'https://biletinial.com/x?utm_source=site&utm_medium=referral' );
t( 'append keeps query', kt_append_utm_params( 'https://b.com/x?a=1', $p ) === 'https://b.com/x?a=1&utm_source=site&utm_medium=referral' );
t( 'append keeps existing utm', kt_append_utm_params( 'https://b.com/x?utm_source=mine', $p ) === 'https://b.com/x?utm_source=mine&utm_medium=referral', kt_append_utm_params( 'https://b.com/x?utm_source=mine', $p ) );
t( 'append keeps fragment', kt_append_utm_params( 'https://b.com/x#sec', $p ) === 'https://b.com/x?utm_source=site&utm_medium=referral#sec', kt_append_utm_params( 'https://b.com/x#sec', $p ) );
t( 'append relative untouched', kt_append_utm_params( '/konserler', $p ) === '/konserler' );
t( 'append mailto untouched', kt_append_utm_params( 'mailto:a@b.c', $p ) === 'mailto:a@b.c' );
t( 'append javascript untouched', kt_append_utm_params( 'javascript:alert(1)', $p ) === 'javascript:alert(1)' );
t( 'append ignores non-utm keys', kt_append_utm_params( 'https://b.com/', array( 'foo' => 'bar' ) ) === 'https://b.com/' );
t( 'append empty params', kt_append_utm_params( 'https://b.com/', array() ) === 'https://b.com/' );

// ---- settings / params ----
reset_state();
$u = kt_get_utm_settings();
t( 'default source = host', 'subadapcocuk.org' === $u['source'], $u['source'] );
t( 'default medium', 'referral' === $u['medium'] );
t( 'defaults enabled', $u['ticket_enabled'] && $u['calendar_enabled'] && $u['capture_enabled'] );
$tp = kt_get_ticket_utm_params( 'urla-konseri' );
t( 'ticket params campaign = normalized district', 'urla' === $tp['utm_campaign'], json_encode( $tp ) );
t( 'ticket params content', 'ticket-button' === $tp['utm_content'] );
update_option( KT_STYLES_OPT, array( 'utm_campaign' => 'Sonbahar Turnesi 2026', 'utm_source' => 'Subadap Site' ) );
$tp = kt_get_ticket_utm_params( 'urla' );
t( 'fixed campaign overrides district', 'sonbahar-turnesi-2026' === $tp['utm_campaign'], json_encode( $tp ) );
t( 'custom source', 'subadap-site' === $tp['utm_source'] );
update_option( KT_STYLES_OPT, array( 'utm_ticket_enabled' => 0, 'utm_calendar_enabled' => 0 ) );
t( 'ticket disabled -> no params', array() === kt_get_ticket_utm_params( 'urla' ) );
t( 'calendar disabled -> no params', array() === kt_get_calendar_utm_params( 'ics-file', 'urla', 1 ) );

// ---- kt_add_utm_hit ----
$rows = array();
$rows = kt_add_utm_hit( $rows, array( 'source' => 'ig', 'medium' => 'social', 'campaign' => 'urla' ), 'views', true );
$rows = kt_add_utm_hit( $rows, array( 'source' => 'ig', 'medium' => 'social', 'campaign' => 'urla' ), 'views', false );
$rows = kt_add_utm_hit( $rows, array( 'source' => 'ig', 'medium' => 'social', 'campaign' => 'urla' ), 'clicks', false );
$r = $rows['ig|social|urla'];
t( 'hit counters', 2 === $r['views'] && 1 === $r['visitors'] && 1 === $r['clicks'], json_encode( $r ) );
t( 'empty utm ignored', $rows === kt_add_utm_hit( $rows, array( 'source' => '', 'medium' => '', 'campaign' => '' ), 'views', true ) );
$rows = array();
for ( $i = 0; $i < 100; $i++ ) { $rows = kt_add_utm_hit( $rows, array( 'source' => "s$i", 'medium' => 'm', 'campaign' => 'c' ), 'views', true ); }
t( 'cap enforced', count( $rows ) === KT_UTM_MAX_COMBOS + 1, (string) count( $rows ) );
t( 'overflow bucket counts', isset( $rows['(other)'] ) && 60 === $rows['(other)']['views'], json_encode( $rows['(other)'] ?? null ) );
$rows = kt_add_utm_hit( $rows, array( 'source' => 's0', 'medium' => 'm', 'campaign' => 'c' ), 'views', false );
t( 'existing combo still increments at cap', 2 === $rows['s0|m|c']['views'] );

// ---- kt_record_district_stat (new + legacy rows) ----
reset_state();
kt_record_district_stat( 'urla-konseri', 'views', 'Urla', array( 'is_unique' => true, 'utm' => array( 'source' => 'ig', 'medium' => 'social', 'campaign' => 'urla' ) ) );
kt_record_district_stat( 'urla-konseri', 'clicks', 'Urla', array( 'utm' => array( 'source' => 'ig', 'medium' => 'social', 'campaign' => 'urla' ) ) );
kt_record_district_stat( 'urla-konseri', 'duration', 'Urla', array( 'seconds' => 30, 'utm' => array( 'source' => 'zzz', 'medium' => '', 'campaign' => '' ) ) );
$s = get_option( KT_STATS_OPT );
t( 'record keyed by normalized district', isset( $s['urla'] ) );
t( 'record utm view+click', 1 === $s['urla']['utm']['ig|social|urla']['visitors'] && 1 === $s['urla']['utm']['ig|social|urla']['clicks'] );
t( 'duration ignores utm', ! isset( $s['urla']['utm']['zzz||'] ) );
t( 'totals unaffected', 1 === $s['urla']['views'] && 1 === $s['urla']['clicks'] && 30 === $s['urla']['total_seconds'] );

update_option( KT_STATS_OPT, array( 'old' => array( 'label' => 'Old', 'views' => 5, 'visitors' => 3, 'clicks' => 1, 'daily' => array() ) ) );
kt_record_district_stat( 'old', 'views', 'Old', array( 'is_unique' => true, 'utm' => array( 'source' => 'fb', 'medium' => '', 'campaign' => '' ) ) );
$s = get_option( KT_STATS_OPT );
t( 'legacy row gets utm array', isset( $s['old']['utm']['fb||'] ) && 6 === $s['old']['views'] );

update_option( KT_STYLES_OPT, array( 'utm_capture_enabled' => 0 ) );
reset_state(); update_option( KT_STYLES_OPT, array( 'utm_capture_enabled' => 0 ) );
kt_record_district_stat( 'x', 'views', 'X', array( 'is_unique' => true, 'utm' => array( 'source' => 'ig', 'medium' => '', 'campaign' => '' ) ) );
$s = get_option( KT_STATS_OPT );
t( 'capture disabled -> no utm stored', empty( $s['x']['utm'] ) && 1 === $s['x']['views'] );

// ---- AJAX handler: hostile input ----
reset_state();
$_POST = array( 'district' => 'urla', 'label' => 'Urla', 'event_type' => 'views', 'is_unique' => '1',
	'utm_source' => '<b>IG</b>"; DROP', 'utm_medium' => str_repeat( 'x', 500 ), 'utm_campaign' => "a\nb|c" );
kt_ajax_track_district_event();
$s = get_option( KT_STATS_OPT );
$keys = array_keys( $s['urla']['utm'] );
t( 'ajax stored sanitized utm', 1 === count( $keys ) && preg_match( '/^[a-z0-9._\-]*\|[a-z0-9._\-]*\|[a-z0-9._\-]*$/', $keys[0] ), json_encode( $keys ) );
t( 'ajax medium truncated', strlen( explode( '|', $keys[0] )[1] ) === 64 );
$_POST = array( 'district' => 'urla', 'event_type' => 'duration', 'seconds' => '20', 'utm_source' => 'ig' );
kt_ajax_track_district_event();
t( 'ajax duration ok', true === $GLOBALS['__json']['ok'] );

// ---- ticket button shortcode ----
reset_state();
update_option( KT_STYLES_OPT, array( 'ticket_btn_url' => 'https://biletinial.com/e/1' ) );
$GLOBALS['post'] = (object) array( 'post_name' => 'urla-konseri', 'post_title' => 'Urla Konseri', 'ID' => 1 );
$html = kt_ticket_button_shortcode( array() );
t( 'button href has utm', false !== strpos( $html, 'href="https://biletinial.com/e/1?utm_source=subadapcocuk.org&#038;utm_medium=referral&#038;utm_campaign=urla&#038;utm_content=ticket-button"' ), $html );
t( 'button data district intact', false !== strpos( $html, 'data-kt-district="urla"' ) );
$html = kt_ticket_button_shortcode( array( 'url' => 'https://biletinial.com/e/1?utm_source=newsletter', 'link_target' => '_blank', 'district' => 'bornova' ) );
t( 'button keeps manual utm_source', false !== strpos( $html, 'utm_source=newsletter' ) && false === strpos( $html, 'utm_source=subadapcocuk.org' ), $html );
t( 'button link_target works', false !== strpos( $html, 'target="_blank"' ) );
t( 'button campaign from district attr', false !== strpos( $html, 'utm_campaign=bornova' ) );
$loc = $GLOBALS['__localized']['ktTrackerData'];
t( 'tracker localized utm cfg', 1 === $loc['utm']['enabled'] && 'subadapcocuk.org' === $loc['utm']['source'] && 1 === $loc['captureUtm'], json_encode( $loc['utm'] ) );
$html = kt_render_ticket_button_block( array( 'url' => 'https://biletix.com/x', 'image' => 'https://x/y.png', 'district' => 'urla', 'link_target' => '_blank' ) );
t( 'block render uses english attrs', false !== strpos( $html, 'utm_campaign=urla' ) && false !== strpos( $html, 'target="_blank"' ) && false !== strpos( $html, 'y.png' ), $html );
update_option( KT_STYLES_OPT, array( 'ticket_btn_url' => 'https://biletinial.com/e/1', 'utm_ticket_enabled' => 0 ) );
$html = kt_ticket_button_shortcode( array() );
t( 'ticket utm disabled -> clean url', false !== strpos( $html, 'href="https://biletinial.com/e/1"' ), $html );

// ---- calendar widget ----
reset_state();
$events = array( array( 'datetime' => '2099-05-02T12:00:00+03:00', 'time2' => '14:00', 'city' => 'Urla', 'slug' => 'urla-konseri' ) );
$html = kt_render_add_to_calendar_widget( $events, 'https://subadapcocuk.org/urla-konseri/', 'urla' );
preg_match_all( '/href="([^"]+calendar\.google\.com[^"]+)"/', $html, $g );
$g1 = html_entity_decode( $g[1][0] ); $g2 = html_entity_decode( $g[1][1] );
parse_str( parse_url( $g1, PHP_URL_QUERY ), $q1 ); parse_str( parse_url( $g2, PHP_URL_QUERY ), $q2 );
t( 'gcal session1 details utm', false !== strpos( $q1['details'], 'utm_source=google-calendar' ) && false !== strpos( $q1['details'], 'utm_content=session-1' ) && false !== strpos( $q1['details'], 'utm_campaign=urla' ), $q1['details'] );
t( 'gcal session2 details utm', false !== strpos( $q2['details'], 'utm_content=session-2' ), $q2['details'] );
preg_match_all( '/data-desc="([^"]*)"/', $html, $d );
t( 'ics desc has ics-file source', false !== strpos( html_entity_decode( $d[1][0] ), 'utm_source=ics-file' ) && false !== strpos( html_entity_decode( $d[1][1] ), 'utm_content=session-2' ), $d[1][0] ?? '' );
update_option( KT_STYLES_OPT, array( 'utm_calendar_enabled' => 0 ) );
$html = kt_render_add_to_calendar_widget( $events, 'https://subadapcocuk.org/urla-konseri/', 'urla' );
t( 'calendar utm disabled', false === strpos( $html, 'utm_' ) );

echo "\n$n assertions, $fail failed\n";
exit( $fail ? 1 : 0 );
