<?php
/**
 * Admin actions: restore or permanently delete an archived statistics snapshot.
 * Included by kt_render_admin_page(); shares its local variables.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( isset( $_POST['kt_restore_archive_id'] ) && check_admin_referer( 'kt_archive_nonce_action', 'kt_archive_nonce' ) ) {
	$restore_result = kt_restore_archived_stats( sanitize_key( wp_unslash( $_POST['kt_restore_archive_id'] ) ) );
	if ( 'ok' === $restore_result ) {
		$message = '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Arşivlenen istatistikler geri yüklendi; ilçenin canlı sayaçları olarak yeniden görünür.', 'konser-takvimi' ) . '</p></div>';
	} elseif ( 'exists' === $restore_result ) {
		$message = '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Bu ilçenin canlı istatistikleri var; iki dönem karışmasın diye geri yükleme yapılmadı. Önce canlı kaydı arşivleyip sıfırlayın.', 'konser-takvimi' ) . '</p></div>';
	} elseif ( 'full' === $restore_result ) {
		$message = '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'İlçe sınırına ulaşıldığı için geri yüklenemedi.', 'konser-takvimi' ) . '</p></div>';
	} else {
		$message = '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Arşiv kaydı bulunamadı veya veritabanı meşgul. Lütfen tekrar deneyin.', 'konser-takvimi' ) . '</p></div>';
	}
} elseif ( isset( $_POST['kt_delete_archive_id'] ) && check_admin_referer( 'kt_archive_nonce_action', 'kt_archive_nonce' ) ) {
	if ( kt_delete_archive_entry( sanitize_key( wp_unslash( $_POST['kt_delete_archive_id'] ) ) ) ) {
		$message = '<div class="notice notice-info is-dismissible"><p>' . esc_html__( 'Arşiv kaydı kalıcı olarak silindi.', 'konser-takvimi' ) . '</p></div>';
	}
}
