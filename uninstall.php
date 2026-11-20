<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

wp_clear_scheduled_hook( 'kt_weekly_stats_report_event' );

$styles = get_option( 'subadap_konserler_styles', array() );

if ( is_array( $styles ) && ! empty( $styles['delete_on_uninst'] ) ) {
	delete_option( 'subadap_konserler_list' );
	delete_option( 'subadap_konserler_styles' );
	delete_option( 'subadap_konserler_stats' );
}
