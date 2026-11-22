<?php
/**
 * Konser Takvimi — plugin uninstall script.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$kt_styles = get_option( 'subadap_konserler_styles', array() );

if ( is_array( $kt_styles ) && ! empty( $kt_styles['delete_on_uninst'] ) ) {
	delete_option( 'subadap_konserler_list' );
	delete_option( 'subadap_konserler_styles' );
	delete_option( 'subadap_konserler_stats' );
	delete_option( 'kt_stats_buffer' );
	delete_option( 'kt_stats_write_lock' );
	delete_option( 'kt_stats_overflow' );
}

// Scheduled hooks are always removed, regardless of the data-deletion setting.
wp_clear_scheduled_hook( 'kt_weekly_stats_report_event' );
wp_clear_scheduled_hook( 'kt_flush_stats_buffer_event' );
