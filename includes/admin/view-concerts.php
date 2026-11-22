<?php
/**
 * Admin view: concerts tab.
 * Included by kt_render_admin_page(); shares its local variables.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
			<datalist id="kt-existing-posts-list">
				<?php foreach ( $existing_posts as $wp_post ) : ?>
					<?php
					if ( ! empty( $wp_post->post_name ) ) :
						$type_label = ( 'page' === $wp_post->post_type ) ? __( 'Sayfa', 'konser-takvimi' ) : __( 'Yazı', 'konser-takvimi' );
						?>
						<option value="<?php echo esc_attr( $wp_post->post_name ); ?>">
							<?php echo esc_html( '[' . $type_label . '] ' . $wp_post->post_title ); ?>
						</option>
					<?php endif; ?>
				<?php endforeach; ?>
			</datalist>

			<h2 style="margin-top: 25px;"><?php echo esc_html__( 'Yaklaşan Konserler (Düzenlenebilir)', 'konser-takvimi' ); ?></h2>
			<p class="description"><?php echo wp_kses( __( 'Aynı gün iki seans varsa <strong>2. Seans</strong> alanını doldurun; düğmede <code>12.00 ve 14.00</code> olarak gösterilir. Tek seans için 2. seansı boş bırakabilirsiniz.', 'konser-takvimi' ), kt_i18n_allowed_html() ); ?></p>

			<form method="post" action="">
				<?php wp_nonce_field( 'kt_concerts_nonce_action', 'kt_concerts_nonce' ); ?>

				<table class="widefat fixed striped" id="kt-concerts-table" style="max-width: 1060px; margin-top: 10px;">
					<thead>
						<tr>
							<th style="width: 145px;"><?php echo esc_html__( 'Tarih', 'konser-takvimi' ); ?></th>
							<th style="width: 95px;"><?php echo esc_html__( '1. Seans', 'konser-takvimi' ); ?></th>
							<th style="width: 110px;"><?php echo esc_html__( '2. Seans (Ops.)', 'konser-takvimi' ); ?></th>
							<th style="width: 185px;"><?php echo esc_html__( 'Şehir / İlçe Adı', 'konser-takvimi' ); ?></th>
							<th><?php echo esc_html__( 'Link Uzantısı (Otomatik: ilce-konseri / Mevcut Yazı-Sayfa)', 'konser-takvimi' ); ?></th>
							<th style="width: 70px;"><?php echo esc_html__( 'İşlem', 'konser-takvimi' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $upcoming_list as $event ) :
							$dt_parts = explode( 'T', $event['datetime'] );
							$d_val    = isset( $dt_parts[0] ) ? $dt_parts[0] : '';
							$t1_val   = isset( $dt_parts[1] ) ? substr( $dt_parts[1], 0, 5 ) : '15:00';
							$t2_val   = isset( $event['time2'] ) ? $event['time2'] : '';
							?>
						<tr>
							<td style="vertical-align: top;"><input type="date" name="concert_date[]" value="<?php echo esc_attr( $d_val ); ?>" required style="width: 100%;"></td>
							<td style="vertical-align: top;"><input type="time" name="concert_time[]" value="<?php echo esc_attr( $t1_val ); ?>" required style="width: 100%;"></td>
							<td style="vertical-align: top;"><input type="time" name="concert_time2[]" value="<?php echo esc_attr( $t2_val ); ?>" style="width: 100%;"></td>
							<td style="vertical-align: top;"><input type="text" name="concert_city[]" value="<?php echo esc_attr( $event['city'] ); ?>" placeholder="<?php echo esc_attr__( 'Örn: Urla', 'konser-takvimi' ); ?>" required style="width: 100%;"></td>
							<td style="vertical-align: top;">
								<input type="text" class="kt-slug-input" name="concert_slug[]" list="kt-existing-posts-list" value="<?php echo esc_attr( $event['slug'] ); ?>" data-auto="0" placeholder="ilce-konseri veya ara..." autocomplete="off">
								<span class="kt-slug-status"></span>
							</td>
							<td style="vertical-align: top;"><button type="button" class="button remove-row" style="color: #b32d2e;"><?php echo esc_html__( 'Sil', 'konser-takvimi' ); ?></button></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<p style="margin-top: 15px;">
					<button type="button" class="button button-secondary" id="add-concert-row"><?php echo esc_html__( '+ Yeni Konser Ekle', 'konser-takvimi' ); ?></button>
					<input type="submit" name="kt_save_concerts" class="button button-primary" value="<?php echo esc_attr__( 'Değişiklikleri Kaydet', 'konser-takvimi' ); ?>" style="margin-left: 10px;">
				</p>
			</form>

			<hr style="max-width: 1060px; margin: 35px 0 25px 0;">

			<div style="max-width: 1060px; background: #fff; border: 1px solid #c3c4c7; border-left: 4px solid #2271b1; border-radius: 4px; padding: 12px 16px; margin-bottom: 16px; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; box-sizing: border-box;">
				<div>
					<strong><?php echo esc_html__( 'Yedekle / Dışa Aktar (Export):', 'konser-takvimi' ); ?></strong>
					<span class="description" style="margin-left: 6px;"><?php echo esc_html( sprintf( /* translators: 1: active count, 2: archive count */ __( 'Aktif (%1$d), Arşiv (%2$d) veya Tüm kayıtları indirin.', 'konser-takvimi' ), $active_count, $archive_count ) ); ?></span>
				</div>
				<form method="post" action="" style="display: flex; align-items: center; gap: 8px; margin: 0;">
					<?php wp_nonce_field( 'kt_export_nonce_action', 'kt_export_nonce' ); ?>
					<select name="export_scope" aria-label="<?php echo esc_attr__( 'Kapsam', 'konser-takvimi' ); ?>">
						<option value="all"><?php echo esc_html__( 'Tümü (Aktif + Arşiv)', 'konser-takvimi' ); ?></option>
						<option value="active"><?php echo esc_html__( 'Sadece Aktif Konserler', 'konser-takvimi' ); ?></option>
						<option value="archived"><?php echo esc_html__( 'Sadece Arşivlenenler', 'konser-takvimi' ); ?></option>
					</select>
					<select name="export_format" aria-label="<?php echo esc_attr__( 'Format', 'konser-takvimi' ); ?>">
						<option value="json"><?php echo esc_html__( 'JSON (.json)', 'konser-takvimi' ); ?></option>
						<option value="csv"><?php echo esc_html__( 'Excel / CSV (.csv)', 'konser-takvimi' ); ?></option>
					</select>
					<input type="submit" name="kt_export_action" class="button button-secondary" value="<?php echo esc_attr__( 'İndir', 'konser-takvimi' ); ?>">
				</form>
			</div>

			<details id="kt-archive-accordion" style="max-width: 1060px; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 12px 16px; box-sizing: border-box;">
				<summary style="cursor: pointer; font-size: 15px; font-weight: 600; color: #1d2327; user-select: none;">
					<?php echo esc_html( sprintf( /* translators: %d: number of archived concerts */ __( 'Arşivlenen Konserler (%d adet - Görmek için tıklayın)', 'konser-takvimi' ), $archive_count ) ); ?>
				</summary>

				<div style="margin-top: 15px;">
					<p class="description" style="margin-bottom: 12px;"><?php echo esc_html__( 'Tarihi ve son seans saati geçen konserler otomatik olarak buraya taşınır, düzenlemeye kapatılır ve sitedeki takvim alanında gizlenir.', 'konser-takvimi' ); ?></p>

					<table class="widefat fixed striped" id="kt-archive-table" style="opacity: 0.85;">
						<thead>
							<tr>
								<th style="width: 145px;"><?php echo esc_html__( 'Tarih', 'konser-takvimi' ); ?></th>
								<th style="width: 95px;"><?php echo esc_html__( '1. Seans', 'konser-takvimi' ); ?></th>
								<th style="width: 95px;"><?php echo esc_html__( '2. Seans', 'konser-takvimi' ); ?></th>
								<th><?php echo esc_html__( 'Şehir / İlçe Adı', 'konser-takvimi' ); ?></th>
								<th><?php echo esc_html__( 'Link Uzantısı (Slug)', 'konser-takvimi' ); ?></th>
								<th style="width: 95px;"><?php echo esc_html__( 'Durum', 'konser-takvimi' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $archived_list ) ) : ?>
								<tr>
									<td colspan="6" style="text-align: center; color: #666;"><?php echo esc_html__( 'Henüz arşivlenmiş konser bulunmuyor.', 'konser-takvimi' ); ?></td>
								</tr>
							<?php else : ?>
								<?php
								foreach ( $archived_list as $event ) :
									$dt_parts = explode( 'T', $event['datetime'] );
									$d_val    = isset( $dt_parts[0] ) ? $dt_parts[0] : '';
									$t1_val   = isset( $dt_parts[1] ) ? substr( $dt_parts[1], 0, 5 ) : '15:00';
									$t2_val   = isset( $event['time2'] ) ? $event['time2'] : '';
									?>
								<tr class="archive-row">
									<td><input type="date" value="<?php echo esc_attr( $d_val ); ?>" disabled style="width: 100%; background: #f0f0f1; color: #50575e;"></td>
									<td><input type="time" value="<?php echo esc_attr( $t1_val ); ?>" disabled style="width: 100%; background: #f0f0f1; color: #50575e;"></td>
									<td><input type="text" value="<?php echo esc_attr( $t2_val ? $t2_val : '-' ); ?>" disabled style="width: 100%; background: #f0f0f1; color: #50575e; text-align: center;"></td>
									<td><input type="text" value="<?php echo esc_attr( $event['city'] ); ?>" disabled style="width: 100%; background: #f0f0f1; color: #50575e;"></td>
									<td><input type="text" value="<?php echo esc_attr( $event['slug'] ); ?>" disabled style="width: 100%; background: #f0f0f1; color: #50575e;"></td>
									<td><span style="display: inline-block; padding: 3px 8px; background: #e2e8f0; color: #475569; border-radius: 4px; font-size: 12px; font-weight: 600;"><?php echo esc_html__( 'Arşivlendi', 'konser-takvimi' ); ?></span></td>
								</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>

					<div id="kt-archive-pagination" style="display: flex; justify-content: space-between; align-items: center; margin-top: 12px;"></div>
				</div>
			</details>
