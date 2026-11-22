<?php
/**
 * "Statistics archive" tab: archived snapshots with restore and delete actions.
 * Included by kt_render_admin_page(); shares its local variables.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$kt_archive_rows = array_reverse( kt_get_stats_archive() );
$kt_live_stats   = get_option( KT_STATS_OPT, array() );
$kt_live_stats   = is_array( $kt_live_stats ) ? $kt_live_stats : array();
$kt_reason_names = array(
	'manual'    => __( 'Elle', 'konser-takvimi' ),
	'auto'      => __( 'Konser bitti (otomatik)', 'konser-takvimi' ),
	'reset_all' => __( 'Tümünü sıfırla', 'konser-takvimi' ),
);
?>
<div style="max-width: 1060px; margin-top: 20px;">
	<p><?php echo esc_html__( 'Arşivlenen istatistikler burada saklanır. Her kayıt, bir ilçenin önceki arşivinden (veya ilk kaydından) arşivlendiği güne kadar olan toplamlarını ve günlük verisini içerir.', 'konser-takvimi' ); ?></p>
	<p class="description"><?php echo esc_html( sprintf( /* translators: %d: maximum number of archive entries */ __( 'En fazla %d kayıt saklanır; sınır aşılırsa en eski kayıtlar silinir.', 'konser-takvimi' ), KT_STATS_ARCHIVE_MAX ) ); ?></p>

	<?php if ( empty( $kt_archive_rows ) ) : ?>
		<p><em><?php echo esc_html__( 'Henüz arşivlenmiş istatistik yok.', 'konser-takvimi' ); ?></em></p>
	<?php else : ?>
		<form method="post" action="">
			<?php wp_nonce_field( 'kt_archive_nonce_action', 'kt_archive_nonce' ); ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'İlçe', 'konser-takvimi' ); ?></th>
						<th><?php echo esc_html__( 'Dönem', 'konser-takvimi' ); ?></th>
						<th><?php echo esc_html__( 'Tekil Ziyaretçi', 'konser-takvimi' ); ?></th>
						<th><?php echo esc_html__( 'Gösterim', 'konser-takvimi' ); ?></th>
						<th><?php echo esc_html__( 'Bilet Tıklaması', 'konser-takvimi' ); ?></th>
						<th><?php echo esc_html__( 'CTR', 'konser-takvimi' ); ?></th>
						<th><?php echo esc_html__( 'Dönüşüm', 'konser-takvimi' ); ?></th>
						<th><?php echo esc_html__( 'Neden / Tarih', 'konser-takvimi' ); ?></th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $kt_archive_rows as $entry ) : ?>
						<?php
						$e_row  = isset( $entry['row'] ) && is_array( $entry['row'] ) ? $entry['row'] : array();
						$e_vis  = isset( $e_row['visitors'] ) ? intval( $e_row['visitors'] ) : 0;
						$e_view = isset( $e_row['views'] ) ? intval( $e_row['views'] ) : 0;
						$e_clk  = isset( $e_row['clicks'] ) ? intval( $e_row['clicks'] ) : 0;
						$e_cv   = isset( $e_row['click_visitors'] ) ? intval( $e_row['click_visitors'] ) : 0;
						$e_ctr  = $e_view > 0 ? ( $e_clk / $e_view ) * 100 : 0.0;
						$e_conv = kt_conversion_rate( $e_cv, $e_vis );
						$e_key  = isset( $entry['key'] ) ? (string) $entry['key'] : '';
						$e_why  = isset( $entry['reason'], $kt_reason_names[ $entry['reason'] ] ) ? $kt_reason_names[ $entry['reason'] ] : $kt_reason_names['manual'];
						$e_live = isset( $kt_live_stats[ $e_key ] );
						?>
						<tr>
							<td>
								<strong><?php echo esc_html( isset( $entry['label'] ) ? $entry['label'] : $e_key ); ?></strong>
								<div style="font-size: 11px; color: #64748b;"><code><?php echo esc_html( $e_key ); ?></code></div>
							</td>
							<td><?php echo esc_html( ( ! empty( $entry['period_start'] ) ? $entry['period_start'] : '?' ) . ' → ' . ( isset( $entry['period_end'] ) ? $entry['period_end'] : '' ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( $e_vis ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( $e_view ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( $e_clk ) ); ?></td>
							<td>%<?php echo esc_html( number_format( $e_ctr, 1, ',', '.' ) ); ?></td>
							<td>%<?php echo esc_html( number_format( $e_conv, 1, ',', '.' ) ); ?></td>
							<td>
								<?php echo esc_html( $e_why ); ?>
								<div style="font-size: 11px; color: #64748b;"><?php echo esc_html( isset( $entry['archived_at'] ) ? $entry['archived_at'] : '' ); ?></div>
							</td>
							<td style="white-space: nowrap;">
								<button type="submit" name="kt_restore_archive_id" value="<?php echo esc_attr( isset( $entry['id'] ) ? $entry['id'] : '' ); ?>" class="button button-small" <?php disabled( $e_live ); ?> title="<?php echo esc_attr( $e_live ? __( 'Bu ilçenin canlı istatistikleri var. Önce onları arşivleyip sıfırlayın.', 'konser-takvimi' ) : '' ); ?>"><?php echo esc_html__( 'Geri yükle', 'konser-takvimi' ); ?></button>
								<button type="submit" name="kt_delete_archive_id" value="<?php echo esc_attr( isset( $entry['id'] ) ? $entry['id'] : '' ); ?>" class="button button-small" style="color: #b32d2e;" onclick="return confirm(<?php echo esc_attr( wp_json_encode( __( 'Bu arşiv kaydı kalıcı olarak silinecek ve geri getirilemez. Emin misiniz?', 'konser-takvimi' ) ) ); ?>);"><?php echo esc_html__( 'Sil', 'konser-takvimi' ); ?></button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</form>
	<?php endif; ?>
</div>
