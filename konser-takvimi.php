<?php
/**
 * Plugin Name: Konser Takvimi
 * Description: Database-backed concert calendar with double-session support, Google MusicEvent JSON-LD, automatic archiving of past concerts, cache-safe 302 district redirects, Add to Calendar buttons, Gutenberg blocks, UTM tagging and CTR-ranked analytics.
 * Version: 4.4.0
 * Requires PHP: 8.4
 * Author: Şubadap Çocuk
 * Text Domain: konser-takvimi
 * Domain Path: /languages
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'KT_PLUGIN_FILE' ) ) {
	define( 'KT_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'KT_PLUGIN_DIR' ) ) {
	define( 'KT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'KT_PLUGIN_URL' ) ) {
	define( 'KT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'KT_PLUGIN_VER' ) ) {
	define( 'KT_PLUGIN_VER', '4.4.0' );
}
if ( ! defined( 'KT_EVENTS_OPT' ) ) {
	define( 'KT_EVENTS_OPT', 'subadap_konserler_list' );
}
if ( ! defined( 'KT_STYLES_OPT' ) ) {
	define( 'KT_STYLES_OPT', 'subadap_konserler_styles' );
}
if ( ! defined( 'KT_STATS_OPT' ) ) {
	define( 'KT_STATS_OPT', 'subadap_konserler_stats' );
}
if ( ! defined( 'KT_CRON_HOOK' ) ) {
	define( 'KT_CRON_HOOK', 'kt_weekly_stats_report_event' );
}
// Maximum number of districts kept in the statistics option (protects against bloat from forged requests).
if ( ! defined( 'KT_STATS_MAX_DISTRICTS' ) ) {
	define( 'KT_STATS_MAX_DISTRICTS', 300 );
}
// Buffered tracking events waiting to be merged into the statistics option.
if ( ! defined( 'KT_STATS_BUFFER_OPT' ) ) {
	define( 'KT_STATS_BUFFER_OPT', 'kt_stats_buffer' );
}
if ( ! defined( 'KT_STATS_BUFFER_MAX' ) ) {
	define( 'KT_STATS_BUFFER_MAX', 500 );
}
if ( ! defined( 'KT_STATS_FLUSH_HOOK' ) ) {
	define( 'KT_STATS_FLUSH_HOOK', 'kt_flush_stats_buffer_event' );
}
// Date of the last time a new district was rejected because KT_STATS_MAX_DISTRICTS was reached.
if ( ! defined( 'KT_STATS_OVERFLOW_OPT' ) ) {
	define( 'KT_STATS_OVERFLOW_OPT', 'kt_stats_overflow' );
}
// Option name used as a short-lived lock while the statistics option is read-modified-written.
if ( ! defined( 'KT_STATS_LOCK_OPT' ) ) {
	define( 'KT_STATS_LOCK_OPT', 'kt_stats_write_lock' );
}
// Maximum number of distinct source/medium/campaign combinations stored per district.
if ( ! defined( 'KT_UTM_MAX_COMBOS' ) ) {
	define( 'KT_UTM_MAX_COMBOS', 40 );
}

// Modules (one responsibility per file).
require_once KT_PLUGIN_DIR . 'includes/i18n.php';
require_once KT_PLUGIN_DIR . 'includes/events-time.php';
require_once KT_PLUGIN_DIR . 'includes/settings.php';
require_once KT_PLUGIN_DIR . 'includes/utm.php';
require_once KT_PLUGIN_DIR . 'includes/stats-recording.php';
require_once KT_PLUGIN_DIR . 'includes/reports.php';
require_once KT_PLUGIN_DIR . 'includes/cron.php';
require_once KT_PLUGIN_DIR . 'includes/assets.php';
require_once KT_PLUGIN_DIR . 'includes/schema.php';
require_once KT_PLUGIN_DIR . 'includes/redirect.php';
require_once KT_PLUGIN_DIR . 'includes/calendar.php';
require_once KT_PLUGIN_DIR . 'includes/shortcodes.php';
require_once KT_PLUGIN_DIR . 'includes/blocks.php';

if ( is_admin() ) {
	require_once KT_PLUGIN_DIR . 'includes/admin-tools.php';
	require_once KT_PLUGIN_DIR . 'includes/admin-page.php';
}

register_activation_hook( __FILE__, 'kt_init_db_option' );
function kt_init_db_option() {
	$existing = get_option( KT_EVENTS_OPT );
	if ( ! is_array( $existing ) ) {
		update_option( KT_EVENTS_OPT, array(), false );
	}
	$stats = get_option( KT_STATS_OPT );
	if ( ! is_array( $stats ) ) {
		update_option( KT_STATS_OPT, array(), false );
	}
	kt_sync_weekly_cron_schedule( true );
}

register_deactivation_hook( __FILE__, 'kt_deactivate_plugin' );
function kt_deactivate_plugin() {
	wp_clear_scheduled_hook( KT_CRON_HOOK );
}
