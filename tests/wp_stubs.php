<?php
// Minimal WordPress stubs so the plugin's pure logic can be exercised outside WordPress.
define( 'ABSPATH', '/tmp/' );
$GLOBALS['__opts'] = array();
$GLOBALS['__localized'] = array();
$GLOBALS['__json'] = null;
function plugin_dir_path( $f ) { return rtrim( dirname( $f ), '/' ) . '/'; }
function plugin_dir_url( $f ) { return 'https://example.org/wp-content/plugins/konser-takvimi/'; }
function add_action() {} function add_filter() {} function add_shortcode() {}
function register_activation_hook() {} function register_deactivation_hook() {}
function is_admin() { return false; }
function add_option( $k, $v = '', $d = '', $a = 'yes' ) { if ( array_key_exists( $k, $GLOBALS['__opts'] ) ) { return false; } $GLOBALS['__opts'][ $k ] = $v; return true; }
function delete_option( $k ) { unset( $GLOBALS['__opts'][ $k ] ); return true; }
// Tests run unbuffered by default so stats are readable right after recording; set $GLOBALS['__buffer_on'] = true to test buffering.
function get_option( $k, $d = false ) {
	$v = array_key_exists( $k, $GLOBALS['__opts'] ) ? $GLOBALS['__opts'][ $k ] : $d;
	if ( 'subadap_konserler_styles' === $k && empty( $GLOBALS['__buffer_on'] ) ) {
		$v = array_merge( is_array( $v ) ? $v : array(), array( 'stats_buffer_seconds' => 0 ) );
	}
	return $v;
}
function wp_next_scheduled( $h ) { return isset( $GLOBALS['__cron'][ $h ] ) ? $GLOBALS['__cron'][ $h ] : false; }
function wp_schedule_single_event( $t, $h ) { $GLOBALS['__cron'][ $h ] = $t; return true; }
function update_option( $k, $v ) { $GLOBALS['__opts'][ $k ] = $v; return true; }
function wp_parse_args( $a, $d ) { return array_merge( $d, (array) $a ); }
function sanitize_hex_color( $c ) { return preg_match( '/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', (string) $c ) ? $c : ''; }
function sanitize_text_field( $s ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $s ) ) ); }
function remove_accents( $s ) { return strtr( $s, array( 'İ'=>'I','ı'=>'i','Ş'=>'S','ş'=>'s','Ğ'=>'G','ğ'=>'g','Ü'=>'U','ü'=>'u','Ö'=>'O','ö'=>'o','Ç'=>'C','ç'=>'c','é'=>'e' ) ); }
function sanitize_title( $s ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( remove_accents( (string) $s ) ) ), '-' ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function home_url( $p = '' ) { return 'https://subadapcocuk.org' . $p; }
function admin_url( $p = '' ) { return 'https://subadapcocuk.org/wp-admin/' . $p; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function wp_parse_str( $s, &$out ) { parse_str( $s, $out ); }
function add_query_arg( $params, $url ) {
	$frag = ''; if ( false !== ( $h = strpos( $url, '#' ) ) ) { $frag = substr( $url, $h ); $url = substr( $url, 0, $h ); }
	$q = ''; if ( false !== ( $p = strpos( $url, '?' ) ) ) { $q = substr( $url, $p + 1 ); $url = substr( $url, 0, $p ); }
	parse_str( $q, $existing ); $merged = array_merge( $existing, $params );
	return $url . '?' . http_build_query( $merged, '', '&', PHP_QUERY_RFC1738 ) . $frag;
}
function esc_url( $u ) { return str_replace( '&', '&#038;', $u ); }
function esc_url_raw( $u ) { return $u; }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f ); }
function wp_enqueue_style() {} function wp_enqueue_script() {} function wp_register_style() {} function wp_register_script() {}
function wp_add_inline_style() {}
function wp_localize_script( $h, $n, $d ) { $GLOBALS['__localized'][ $n ] = $d; }
function wp_create_nonce() { return 'nonce123'; }
function get_bloginfo() { return 'Subadap'; }
function wp_specialchars_decode( $s ) { return $s; }
if ( ! function_exists( 'current_user_can' ) ) { function current_user_can() { return false; } }
function check_ajax_referer() { return true; }
function wp_unslash( $v ) { return is_string( $v ) ? stripslashes( $v ) : $v; }
function wp_send_json_success( $d = null ) { $GLOBALS['__json'] = array( 'ok' => true, 'data' => $d ); }
function wp_send_json_error( $d = null, $c = 0 ) { $GLOBALS['__json'] = array( 'ok' => false, 'data' => $d, 'code' => $c ); }
function shortcode_atts( $pairs, $atts, $s = '' ) { $atts = (array) $atts; $out = array(); foreach ( $pairs as $k => $v ) { $out[ $k ] = array_key_exists( $k, $atts ) ? $atts[ $k ] : $v; } return $out; }
function attachment_url_to_postid() { return 0; }
function nocache_headers() {}
function number_format_i18n( $n ) { return number_format( $n ); }
function trailingslashit( $s ) { return rtrim( $s, '/' ) . '/'; }

if ( ! defined( 'DAY_IN_SECONDS' ) ) { define( 'DAY_IN_SECONDS', 86400 ); }
function wp_date( $f, $ts = null, $tz = null ) { $d = new DateTime( '@' . ( null === $ts ? time() : $ts ) ); if ( $tz ) { $d->setTimezone( $tz ); } return $d->format( $f ); }
