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
}

wp_clear_scheduled_hook( 'kt_weekly_stats_report_event' );
