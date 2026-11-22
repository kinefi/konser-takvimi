<?php
/**
 * Admin actions: archive/reset statistics and send a test report.
 * Included by kt_render_admin_page(); shares its local variables.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Archive and reset all statistics / a single district. Nothing is deleted outright: the numbers move to the archive first.
$kt_archive_url = admin_url( 'admin.php?page=konser-takvimi&tab=archive' );
if ( isset( $_POST['kt_reset_stats'] ) && check_admin_referer( 'kt_stats_nonce_action', 'kt_stats_nonce' ) ) {
	$archived_count = kt_archive_all_district_stats();
	$message        = '<div class="notice notice-info is-dismissible"><p>' . wp_kses(
		sprintf(
			/* translators: 1: number of districts, 2: opening link tag, 3: closing link tag */
			__( 'Tüm ilçe istatistikleri (%1$d ilçe) arşive taşındı ve sayaçlar sıfırlandı. %2$sArşivden geri yükleyebilirsiniz%3$s.', 'konser-takvimi' ),
			$archived_count,
			'<a href="' . esc_url( $kt_archive_url ) . '">',
			'</a>'
		),
		array( 'a' => array( 'href' => array() ) )
	) . '</p></div>';
} elseif ( isset( $_POST['kt_archive_stat_key'] ) && check_admin_referer( 'kt_stats_nonce_action', 'kt_stats_nonce' ) ) {
	$arch_key = kt_normalize_district_key( sanitize_text_field( wp_unslash( $_POST['kt_archive_stat_key'] ) ) );
	if ( '' !== $arch_key && kt_archive_district_stats( $arch_key, 'manual', true ) ) {
		$message = '<div class="notice notice-info is-dismissible"><p>' . wp_kses(
			sprintf(
				/* translators: 1: district key, 2: opening link tag, 3: closing link tag */
				__( '%1$s istatistikleri arşive taşındı ve sayaçlar sıfırlandı. %2$sArşivden geri yükleyebilirsiniz%3$s.', 'konser-takvimi' ),
				'<code>' . esc_html( $arch_key ) . '</code>',
				'<a href="' . esc_url( $kt_archive_url ) . '">',
				'</a>'
			),
			array(
				'a'    => array( 'href' => array() ),
				'code' => array(),
			)
		) . '</p></div>';
	} else {
		$message = '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'İstatistik arşivlenemedi (kayıt bulunamadı veya veritabanı meşgul). Lütfen tekrar deneyin.', 'konser-takvimi' ) . '</p></div>';
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
