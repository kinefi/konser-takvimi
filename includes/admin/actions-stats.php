<?php
/**
 * Admin actions: reset/delete statistics and send a test report.
 * Included by kt_render_admin_page(); shares its local variables.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Reset all statistics / delete a single row.
if ( isset( $_POST['kt_reset_stats'] ) && check_admin_referer( 'kt_stats_nonce_action', 'kt_stats_nonce' ) ) {
	update_option( KT_STATS_OPT, array(), false );
	$message = '<div class="notice notice-info is-dismissible"><p>' . esc_html__( 'Tüm ilçe ziyaret ve tıklama istatistikleri sıfırlandı.', 'konser-takvimi' ) . '</p></div>';
} elseif ( isset( $_POST['kt_delete_stat_key'] ) && check_admin_referer( 'kt_stats_nonce_action', 'kt_stats_nonce' ) ) {
	$del_key = sanitize_title( wp_unslash( $_POST['kt_delete_stat_key'] ) );
	$stats   = get_option( KT_STATS_OPT, array() );
	if ( is_array( $stats ) && isset( $stats[ $del_key ] ) ) {
		unset( $stats[ $del_key ] );
		update_option( KT_STATS_OPT, $stats, false );
		$message = '<div class="notice notice-info is-dismissible"><p>' . esc_html__( 'Seçilen ilçe istatistik kaydı silindi.', 'konser-takvimi' ) . '</p></div>';
	}
}

// Send a test weekly email report.
if ( isset( $_POST['kt_send_test_report'] ) && check_admin_referer( 'kt_styles_nonce_action', 'kt_styles_nonce' ) ) {
	$test_to = isset( $_POST['weekly_report_email'] ) ? sanitize_email( wp_unslash( $_POST['weekly_report_email'] ) ) : '';
	if ( empty( $test_to ) ) {
		$test_to = get_option( 'admin_email' );
	}
	$sent = kt_send_weekly_stats_email( $test_to );
	if ( $sent ) {
		$message = '<div class="notice notice-success is-dismissible"><p>' . sprintf( esc_html__( 'Örnek haftalık istatistik raporu %s adresine gönderildi.', 'konser-takvimi' ), '<strong>' . esc_html( $test_to ) . '</strong>' ) . '</p></div>';
	} else {
		$message = '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'E-posta gönderilemedi. Lütfen sunucunuzun WordPress e-posta (SMTP) ayarlarını kontrol edin.', 'konser-takvimi' ) . '</p></div>';
	}
}
