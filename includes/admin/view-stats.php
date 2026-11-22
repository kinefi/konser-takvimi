<?php
/**
 * Admin view: statistics tab.
 * Included by kt_render_admin_page(); shares its local variables.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
			<?php
			$stats = get_option( KT_STATS_OPT, array() );
			if ( ! is_array( $stats ) ) {
				$stats = array();
			}

			$today_str = kt_get_site_date_str( 0 );

			$sort_by = isset( $_GET['sort_by'] ) ? sanitize_key( $_GET['sort_by'] ) : 'visitors'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only sort column; sanitized and whitelisted below.
			if ( ! in_array( $sort_by, array( 'visitors', 'views', 'clicks', 'ctr', 'conversion', 'duration' ), true ) ) {
				$sort_by = 'visitors';
			}

			uasort(
				$stats,
				function ( $a, $b ) use ( $sort_by ) {
					$va   = isset( $a['views'] ) ? intval( $a['views'] ) : 0;
					$vb   = isset( $b['views'] ) ? intval( $b['views'] ) : 0;
					$visa = isset( $a['visitors'] ) ? intval( $a['visitors'] ) : $va;
					$visb = isset( $b['visitors'] ) ? intval( $b['visitors'] ) : $vb;
					$ca   = isset( $a['clicks'] ) ? intval( $a['clicks'] ) : 0;
					$cb   = isset( $b['clicks'] ) ? intval( $b['clicks'] ) : 0;

					if ( 'ctr' === $sort_by ) {
						$ctra = $va > 0 ? ( $ca / $va ) : 0;
						$ctrb = $vb > 0 ? ( $cb / $vb ) : 0;
						if ( abs( $ctra - $ctrb ) > 0.00001 ) {
							return ( $ctrb > $ctra ) ? 1 : -1;
						}
						return $cb - $ca;
					}
					if ( 'conversion' === $sort_by ) {
						$cva   = isset( $a['click_visitors'] ) ? intval( $a['click_visitors'] ) : 0;
						$cvb   = isset( $b['click_visitors'] ) ? intval( $b['click_visitors'] ) : 0;
						$conva = $visa > 0 ? ( $cva / $visa ) : 0;
						$convb = $visb > 0 ? ( $cvb / $visb ) : 0;
						if ( abs( $conva - $convb ) > 0.00001 ) {
							return ( $convb > $conva ) ? 1 : -1;
						}
						return $cvb - $cva;
					}
					if ( 'clicks' === $sort_by ) {
						return ( $cb !== $ca ) ? ( $cb - $ca ) : ( $visb - $visa );
					}
					if ( 'views' === $sort_by ) {
						return ( $vb !== $va ) ? ( $vb - $va ) : ( $cb - $ca );
					}
					if ( 'duration' === $sort_by ) {
						$dura = ( ! empty( $a['duration_count'] ) ) ? ( intval( $a['total_seconds'] ) / intval( $a['duration_count'] ) ) : 0;
						$durb = ( ! empty( $b['duration_count'] ) ) ? ( intval( $b['total_seconds'] ) / intval( $b['duration_count'] ) ) : 0;
						if ( abs( $dura - $durb ) > 0.01 ) {
							return ( $durb > $dura ) ? 1 : -1;
						}
						return $visb - $visa;
					}

					if ( $visa === $visb ) {
						return $cb - $ca;
					}
					return $visb - $visa;
				}
			);

			$today_visitors = 0;
			$total_visitors = 0;
			$total_views    = 0;
			$total_clicks   = 0;
			$total_click_visitors = 0;
			$total_seconds  = 0;
			$duration_count = 0;

			foreach ( $stats as $st ) {
				$total_views    += isset( $st['views'] ) ? intval( $st['views'] ) : 0;
				$total_visitors += isset( $st['visitors'] ) ? intval( $st['visitors'] ) : 0;
				$total_clicks   += isset( $st['clicks'] ) ? intval( $st['clicks'] ) : 0;
				$total_click_visitors += isset( $st['click_visitors'] ) ? intval( $st['click_visitors'] ) : 0;
				$total_seconds  += isset( $st['total_seconds'] ) ? intval( $st['total_seconds'] ) : 0;
				$duration_count += isset( $st['duration_count'] ) ? intval( $st['duration_count'] ) : 0;
				if ( isset( $st['daily'][ $today_str ]['visitors'] ) ) {
					$today_visitors += intval( $st['daily'][ $today_str ]['visitors'] );
				}
			}

			$overall_ctr     = $total_views > 0 ? number_format( ( $total_clicks / $total_views ) * 100, 1, ',', '.' ) : '0,0';
			$overall_conversion = number_format( kt_conversion_rate( $total_click_visitors, $total_visitors ), 1, ',', '.' );
			$overall_avg_sec = $duration_count > 0 ? round( $total_seconds / $duration_count ) : 0;

			$chart_days = array();
			for ( $d = 13; $d >= 0; $d-- ) {
				$d_str        = kt_get_site_date_str( -$d );
				$chart_days[] = array(
					'date'  => $d_str,
					'label' => substr( $d_str, 8, 2 ) . '.' . substr( $d_str, 5, 2 ),
				);
			}

			$chart_series = array(
				'all' => array(
					'label'    => __( 'Tüm İlçeler (Genel Toplam)', 'konser-takvimi' ),
					'visitors' => array_fill( 0, 14, 0 ),
					'views'    => array_fill( 0, 14, 0 ),
					'clicks'   => array_fill( 0, 14, 0 ),
				),
			);

			foreach ( $stats as $d_key => $st_item ) {
				$series_vis = array();
				$series_v   = array();
				$series_c   = array();

				foreach ( $chart_days as $idx => $c_day ) {
					$dt_str = $c_day['date'];
					$vis_v  = isset( $st_item['daily'][ $dt_str ]['visitors'] ) ? intval( $st_item['daily'][ $dt_str ]['visitors'] ) : 0;
					$vw_v   = isset( $st_item['daily'][ $dt_str ]['views'] ) ? intval( $st_item['daily'][ $dt_str ]['views'] ) : 0;
					$cl_v   = isset( $st_item['daily'][ $dt_str ]['clicks'] ) ? intval( $st_item['daily'][ $dt_str ]['clicks'] ) : 0;

					$series_vis[] = $vis_v;
					$series_v[]   = $vw_v;
					$series_c[]   = $cl_v;

					$chart_series['all']['visitors'][ $idx ] += $vis_v;
					$chart_series['all']['views'][ $idx ]    += $vw_v;
					$chart_series['all']['clicks'][ $idx ]   += $cl_v;
				}

				$chart_series[ $d_key ] = array(
					'label'    => isset( $st_item['label'] ) ? $st_item['label'] : $d_key,
					'visitors' => $series_vis,
					'views'    => $series_v,
					'clicks'   => $series_c,
				);
			}

			$chart_payload = array(
				'labels' => wp_list_pluck( $chart_days, 'label' ),
				'dates'  => wp_list_pluck( $chart_days, 'date' ),
				'series' => $chart_series,
			);
			?>

			<div style="max-width: 1280px; margin-top: 20px;">
				<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(195px, 1fr)); gap: 14px; margin-bottom: 20px;">
					<div style="background: #fff; border: 1px solid #c3c4c7; border-left: 4px solid #0284c7; border-radius: 4px; padding: 14px 16px;">
						<div style="font-size: 12px; color: #50575e; font-weight: 600;"><?php echo esc_html__( 'Bugünkü Tekil Ziyaretçi', 'konser-takvimi' ); ?></div>
						<div style="font-size: 24px; font-weight: 700; color: #1d2327; margin-top: 4px;"><?php echo esc_html( number_format_i18n( $today_visitors ) ); ?></div>
					</div>
					<div style="background: #fff; border: 1px solid #c3c4c7; border-left: 4px solid #2271b1; border-radius: 4px; padding: 14px 16px;">
						<div style="font-size: 12px; color: #50575e; font-weight: 600;"><?php echo esc_html__( 'Toplam Ziyaretçi / Gösterim', 'konser-takvimi' ); ?></div>
						<div style="font-size: 24px; font-weight: 700; color: #1d2327; margin-top: 4px;">
							<?php echo esc_html( number_format_i18n( $total_visitors ) ); ?> <span style="font-size: 14px; font-weight: 500; color: #64748b;">/ <?php echo esc_html( number_format_i18n( $total_views ) ); ?></span>
						</div>
					</div>
					<div style="background: #fff; border: 1px solid #c3c4c7; border-left: 4px solid #16a34a; border-radius: 4px; padding: 14px 16px;">
						<div style="font-size: 12px; color: #50575e; font-weight: 600;"><?php echo esc_html__( 'Bilet Tıklaması (CTR)', 'konser-takvimi' ); ?></div>
						<div style="font-size: 24px; font-weight: 700; color: #1d2327; margin-top: 4px;">
							<?php echo esc_html( number_format_i18n( $total_clicks ) ); ?> <span style="font-size: 14px; font-weight: 600; color: #16a34a;">(%<?php echo esc_html( $overall_ctr ); ?>)</span>
						</div>
					</div>
					<div style="background: #fff; border: 1px solid #c3c4c7; border-left: 4px solid #0d9488; border-radius: 4px; padding: 14px 16px;">
						<div style="font-size: 12px; color: #50575e; font-weight: 600;"><?php echo esc_html__( 'Bilet Tıklayan Kişi / Tekil Ziyaretçi', 'konser-takvimi' ); ?></div>
						<div style="font-size: 24px; font-weight: 700; color: #1d2327; margin-top: 4px;">
							<?php echo esc_html( number_format_i18n( $total_click_visitors ) ); ?> <span style="font-size: 14px; font-weight: 500; color: #64748b;">/ <?php echo esc_html( number_format_i18n( $total_visitors ) ); ?></span> <span style="font-size: 14px; font-weight: 600; color: #0d9488;">(%<?php echo esc_html( $overall_conversion ); ?>)</span>
						</div>
					</div>
					<div style="background: #fff; border: 1px solid #c3c4c7; border-left: 4px solid #9333ea; border-radius: 4px; padding: 14px 16px;">
						<div style="font-size: 12px; color: #50575e; font-weight: 600;"><?php echo esc_html__( 'Ort. Sayfada Kalma Süresi', 'konser-takvimi' ); ?></div>
						<div style="font-size: 24px; font-weight: 700; color: #1d2327; margin-top: 4px;"><?php echo esc_html( kt_format_duration_human( $overall_avg_sec ) ); ?></div>
					</div>
				</div>

				<div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 16px 20px; margin-bottom: 20px;">
					<div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 12px;">
						<div>
							<h2 style="margin: 0; font-size: 16px;"><?php echo esc_html__( 'Son 14 Günlük Ziyaretçi ve Bilet Tıklama Grafiği', 'konser-takvimi' ); ?></h2>
							<span class="description"><?php echo esc_html__( 'Günlük tekil ziyaretçi ve bilet düğmesi tıklama eğilimi (seçili saat dilimi).', 'konser-takvimi' ); ?></span>
						</div>
						<div style="display: flex; align-items: center; gap: 14px;">
							<div style="display: flex; align-items: center; gap: 12px; font-size: 12px;">
								<span style="display: inline-flex; align-items: center; gap: 5px;"><span style="width: 12px; height: 12px; background: #2271b1; border-radius: 2px; display: inline-block;"></span> <?php echo esc_html__( 'Günlük Ziyaretçi', 'konser-takvimi' ); ?></span>
								<span style="display: inline-flex; align-items: center; gap: 5px;"><span style="width: 12px; height: 12px; background: #16a34a; border-radius: 2px; display: inline-block;"></span> <?php echo esc_html__( __( 'Bilet Tıklaması', 'konser-takvimi' ), 'konser-takvimi' ); ?></span>
							</div>
							<select id="kt-chart-district-select" aria-label="<?php echo esc_attr__( 'İlçe Filtresi', 'konser-takvimi' ); ?>">
								<option value="all"><?php echo esc_html__( 'Tüm İlçeler (Toplam)', 'konser-takvimi' ); ?></option>
								<?php foreach ( $stats as $d_key => $st_item ) : ?>
									<option value="<?php echo esc_attr( $d_key ); ?>">
										<?php echo esc_html( isset( $st_item['label'] ) ? $st_item['label'] : $d_key ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
					<div id="kt-stats-chart-wrap" style="width: 100%; overflow-x: auto;">
						<canvas id="kt-stats-canvas" width="980" height="250" style="width: 100%; max-width: 100%; height: 250px; display: block;"></canvas>
					</div>
					<script type="application/json" id="kt-chart-json-data"><?php echo wp_json_encode( $chart_payload ); ?></script>
				</div>

				<div style="background: #fff; border: 1px solid #c3c4c7; border-left: 4px solid #16a34a; border-radius: 4px; padding: 12px 16px; margin-bottom: 16px; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px;">
					<div>
						<strong><?php echo esc_html__( 'Rapor İndir:', 'konser-takvimi' ); ?></strong>
						<span class="description" style="margin-left: 4px;"><?php echo esc_html__( 'Dönem ve biçim seçerek yazdırılabilir görsel rapor (HTML/PDF) veya Excel tablosu (CSV) indirin.', 'konser-takvimi' ); ?></span>
					</div>
					<form method="post" action="" style="display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin: 0;">
						<?php wp_nonce_field( 'kt_stats_nonce_action', 'kt_stats_nonce' ); ?>
						<select name="report_period" aria-label="<?php echo esc_attr__( 'Rapor Dönemi', 'konser-takvimi' ); ?>">
							<option value="7"><?php echo esc_html__( 'Son 7 Gün (Haftalık)', 'konser-takvimi' ); ?></option>
							<option value="14"><?php echo esc_html__( 'Son 14 Gün', 'konser-takvimi' ); ?></option>
							<option value="30"><?php echo esc_html__( 'Son 30 Gün (Aylık)', 'konser-takvimi' ); ?></option>
							<option value="0"><?php echo esc_html__( 'Tüm Zamanlar', 'konser-takvimi' ); ?></option>
						</select>
						<select name="report_format" aria-label="<?php echo esc_attr__( 'Rapor Biçimi', 'konser-takvimi' ); ?>">
							<option value="html"><?php echo esc_html__( 'Görsel Rapor (HTML / Yazdır & PDF)', 'konser-takvimi' ); ?></option>
							<option value="csv"><?php echo esc_html__( 'Excel Tablosu (.csv)', 'konser-takvimi' ); ?></option>
						</select>
						<input type="submit" name="kt_download_report_action" class="button button-primary" value="<?php echo esc_attr__( 'Raporu İndir', 'konser-takvimi' ); ?>" <?php disabled( empty( $stats ) ); ?>>
						<input type="submit" name="kt_reset_stats" class="button" style="color: #b32d2e; margin-left: 6px;" value="<?php echo esc_attr__( 'Tümünü Arşivle & Sıfırla', 'konser-takvimi' ); ?>" <?php disabled( empty( $stats ) ); ?> onclick="return confirm(<?php echo esc_attr( wp_json_encode( __( 'Tüm ilçelerin istatistikleri arşive taşınacak ve sayaçlar sıfırlanacak. Arşiv sekmesinden geri yükleyebilirsiniz. Devam edilsin mi?', 'konser-takvimi' ) ) ); ?>);">
					</form>
				</div>

				<div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 10px;">
					<div style="font-size: 13px; color: #50575e;">
						<strong><?php echo esc_html__( 'Sıralama Ölçütü:', 'konser-takvimi' ); ?></strong>
						<?php
						$sort_options = array(
							'visitors' => __( 'Tekil Ziyaretçi', 'konser-takvimi' ),
							'ctr'      => __( 'CTR % (Tıklama / Gösterim)', 'konser-takvimi' ),
							'conversion' => __( 'Dönüşüm % (Tıklayan Kişi / Ziyaretçi)', 'konser-takvimi' ),
							'clicks'   => __( 'Bilet Tıklaması', 'konser-takvimi' ),
							'views'    => __( 'Sayfa Gösterimi', 'konser-takvimi' ),
							'duration' => __( 'Ort. Kalma Süresi', 'konser-takvimi' ),
						);
						$sort_links   = array();
						foreach ( $sort_options as $s_key => $s_label ) {
							$s_url = add_query_arg(
								array(
									'page'    => 'konser-takvimi',
									'tab'     => 'stats',
									'sort_by' => $s_key,
								),
								admin_url( 'admin.php' )
							);
							if ( $sort_by === $s_key ) {
								$sort_links[] = '<strong style="color: #1d2327; background: #e2e8f0; padding: 3px 8px; border-radius: 4px;">' . esc_html( $s_label ) . ' &darr;</strong>';
							} else {
								$sort_links[] = '<a href="' . esc_url( $s_url ) . '" style="text-decoration: none; padding: 3px 6px;">' . esc_html( $s_label ) . '</a>';
							}
						}
						echo wp_kses_post( implode( ' &bull; ', $sort_links ) );
						?>
					</div>
				</div>

				<form method="post" action="">
					<?php wp_nonce_field( 'kt_stats_nonce_action', 'kt_stats_nonce' ); ?>
					<div class="kt-stats-table-wrap">
						<table class="widefat striped kt-stats-table">
							<thead>
								<tr>
									<th class="kt-col-key"><?php echo esc_html__( 'İlçe Anahtarı', 'konser-takvimi' ); ?></th>
									<th class="kt-col-title"><?php echo esc_html__( 'Sayfa Başlığı', 'konser-takvimi' ); ?></th>
									<th class="kt-col-num"><?php echo esc_html__( 'Bugün (Tekil)', 'konser-takvimi' ); ?></th>
									<th class="kt-col-num"><a href="<?php echo esc_url( admin_url( 'admin.php?page=konser-takvimi&tab=stats&sort_by=visitors' ) ); ?>"><?php echo esc_html__( 'Toplam Ziyaretçi', 'konser-takvimi' ); ?><?php echo 'visitors' === $sort_by ? ' &darr;' : ''; ?></a></th>
									<th class="kt-col-num"><a href="<?php echo esc_url( admin_url( 'admin.php?page=konser-takvimi&tab=stats&sort_by=views' ) ); ?>"><?php echo esc_html__( 'Gösterim', 'konser-takvimi' ); ?><?php echo 'views' === $sort_by ? ' &darr;' : ''; ?></a></th>
									<th class="kt-col-num"><a href="<?php echo esc_url( admin_url( 'admin.php?page=konser-takvimi&tab=stats&sort_by=clicks' ) ); ?>"><?php echo esc_html__( 'Bilet Tıklaması', 'konser-takvimi' ); ?><?php echo 'clicks' === $sort_by ? ' &darr;' : ''; ?></a></th>
									<th class="kt-col-num"><a href="<?php echo esc_url( admin_url( 'admin.php?page=konser-takvimi&tab=stats&sort_by=ctr' ) ); ?>">CTR<?php echo 'ctr' === $sort_by ? ' &darr;' : ''; ?></a></th>
									<th class="kt-col-num"><?php echo esc_html__( 'Tıklayan Kişi', 'konser-takvimi' ); ?></th>
									<th class="kt-col-num"><a href="<?php echo esc_url( admin_url( 'admin.php?page=konser-takvimi&tab=stats&sort_by=conversion' ) ); ?>"><?php echo esc_html__( 'Dönüşüm', 'konser-takvimi' ); ?><?php echo 'conversion' === $sort_by ? ' &darr;' : ''; ?></a></th>
									<th class="kt-col-num"><a href="<?php echo esc_url( admin_url( 'admin.php?page=konser-takvimi&tab=stats&sort_by=duration' ) ); ?>"><?php echo esc_html__( 'Ort. Süre', 'konser-takvimi' ); ?><?php echo 'duration' === $sort_by ? ' &darr;' : ''; ?></a></th>
									<th class="kt-col-action"><?php echo esc_html__( 'İşlem', 'konser-takvimi' ); ?></th>
								</tr>
							</thead>
						<tbody>
							<?php if ( empty( $stats ) ) : ?>
								<tr>
									<td colspan="11" style="text-align: center; color: #666; padding: 20px;"><?php echo esc_html__( 'Henüz kaydedilmiş ziyaret veya bilet tıklama verisi bulunmuyor.', 'konser-takvimi' ); ?></td>
								</tr>
							<?php else : ?>
								<?php
								foreach ( $stats as $key => $row ) :
									$v       = isset( $row['views'] ) ? intval( $row['views'] ) : 0;
									$vis     = isset( $row['visitors'] ) ? intval( $row['visitors'] ) : 0;
									$c       = isset( $row['clicks'] ) ? intval( $row['clicks'] ) : 0;
									$tot_sec = isset( $row['total_seconds'] ) ? intval( $row['total_seconds'] ) : 0;
									$dur_cnt = isset( $row['duration_count'] ) ? intval( $row['duration_count'] ) : 0;
									$avg_sec = $dur_cnt > 0 ? round( $tot_sec / $dur_cnt ) : 0;
									$t_vis   = isset( $row['daily'][ $today_str ]['visitors'] ) ? intval( $row['daily'][ $today_str ]['visitors'] ) : 0;
									$ctr_num = $v > 0 ? ( ( $c / $v ) * 100 ) : 0.0;
									$ctr     = number_format( $ctr_num, 1, ',', '.' );
									$ctr_bg  = $ctr_num >= 15 ? '#dcfce7' : ( $ctr_num >= 5 ? '#fef9c3' : '#f1f5f9' );
									$ctr_fg  = $ctr_num >= 15 ? '#166534' : ( $ctr_num >= 5 ? '#854d0e' : '#334155' );
									$cv_count = isset( $row['click_visitors'] ) ? intval( $row['click_visitors'] ) : 0;
									$conv_num = kt_conversion_rate( $cv_count, $vis );
									$conv     = number_format( $conv_num, 1, ',', '.' );
									$conv_bg  = $conv_num >= 15 ? '#dcfce7' : ( $conv_num >= 5 ? '#fef9c3' : '#f1f5f9' );
									$conv_fg  = $conv_num >= 15 ? '#166534' : ( $conv_num >= 5 ? '#854d0e' : '#334155' );
									?>
									<tr>
										<td><code><?php echo esc_html( $key ); ?></code></td>
										<td>
											<strong><?php echo esc_html( isset( $row['label'] ) ? $row['label'] : $key ); ?></strong>
											<div style="font-size: 11px; color: #64748b;">Son: <?php echo esc_html( isset( $row['updated'] ) ? $row['updated'] : '-' ); ?></div>
										</td>
										<td><strong><?php echo esc_html( number_format_i18n( $t_vis ) ); ?></strong></td>
										<td><?php echo esc_html( number_format_i18n( $vis ) ); ?></td>
										<td><?php echo esc_html( number_format_i18n( $v ) ); ?></td>
										<td style="color: #15803d; font-weight: 600;">
											<?php echo esc_html( number_format_i18n( $c ) ); ?>
											<?php if ( ! empty( $row['session_clicks'] ) && is_array( $row['session_clicks'] ) ) : ?>
												<div style="font-size: 11px; font-weight: 400; color: #64748b;"><?php echo esc_html( sprintf( '1. seans: %d · 2. seans: %d', isset( $row['session_clicks']['1'] ) ? intval( $row['session_clicks']['1'] ) : 0, isset( $row['session_clicks']['2'] ) ? intval( $row['session_clicks']['2'] ) : 0 ) ); ?></div>
											<?php endif; ?>
										</td>
										<td><span style="display: inline-block; padding: 3px 8px; background: <?php echo esc_attr( $ctr_bg ); ?>; color: <?php echo esc_attr( $ctr_fg ); ?>; border-radius: 4px; font-weight: 700;">%<?php echo esc_html( $ctr ); ?></span></td>
										<td style="color: #0f766e; font-weight: 600;"><?php echo esc_html( number_format_i18n( $cv_count ) ); ?></td>
										<td><span style="display: inline-block; padding: 3px 8px; background: <?php echo esc_attr( $conv_bg ); ?>; color: <?php echo esc_attr( $conv_fg ); ?>; border-radius: 4px; font-weight: 700;">%<?php echo esc_html( $conv ); ?></span></td>
										<td><?php echo esc_html( kt_format_duration_human( $avg_sec ) ); ?></td>
										<td>
											<button type="submit" name="kt_archive_stat_key" value="<?php echo esc_attr( $key ); ?>" class="button button-small" onclick="return confirm(<?php echo esc_attr( wp_json_encode( __( 'Bu ilçenin istatistikleri arşive taşınacak ve sayaçlar sıfırlanacak. Arşiv sekmesinden geri yükleyebilirsiniz. Devam edilsin mi?', 'konser-takvimi' ) ) ); ?>);"><?php echo esc_html__( 'Arşivle & sıfırla', 'konser-takvimi' ); ?></button>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
						</table>
					</div>
				</form>

				<?php
				$utm_rows = kt_get_utm_report_rows( $stats );
				$utm_row_total = count( $utm_rows );
				$utm_rows      = array_slice( $utm_rows, 0, 200 );
				?>
				<div style="background: #fff; border: 1px solid #c3c4c7; border-left: 4px solid #0891b2; border-radius: 4px; padding: 16px 20px; margin-top: 20px;">
					<h2 style="margin: 0 0 4px 0; font-size: 16px;"><?php echo esc_html__( 'Trafik Kaynakları (UTM)', 'konser-takvimi' ); ?></h2>
					<p class="description" style="margin-top: 0;">
						<?php echo wp_kses( __( 'Yalnızca adreste <code>utm_source</code>, <code>utm_medium</code> veya <code>utm_campaign</code> bulunan ziyaretler listelenir (tüm zamanlar). Bilet tıklaması, aynı sayfa görüntülemesi sırasında yapılan tıklamadır.', 'konser-takvimi' ), kt_i18n_allowed_html() ); ?>
						<?php if ( empty( $styles['utm_capture_enabled'] ) ) : ?>
							<strong style="color: #b32d2e;"><?php echo esc_html__( 'Gelen UTM kaydı Ayarlar sekmesinde kapalı; yeni veri toplanmıyor.', 'konser-takvimi' ); ?></strong>
						<?php endif; ?>
					</p>
					<div class="kt-stats-table-wrap">
						<table class="widefat striped kt-stats-table">
							<thead>
								<tr>
									<th><?php echo esc_html__( 'Kaynak', 'konser-takvimi' ); ?></th>
									<th><?php echo esc_html__( 'Ortam', 'konser-takvimi' ); ?></th>
									<th><?php echo esc_html__( 'Kampanya', 'konser-takvimi' ); ?></th>
									<th><?php echo esc_html__( 'İlçe / Sayfa', 'konser-takvimi' ); ?></th>
									<th class="kt-col-num"><?php echo esc_html__( 'Ziyaretçi', 'konser-takvimi' ); ?></th>
									<th class="kt-col-num"><?php echo esc_html__( 'Gösterim', 'konser-takvimi' ); ?></th>
									<th class="kt-col-num"><?php echo esc_html__( __( 'Bilet Tıklaması', 'konser-takvimi' ), 'konser-takvimi' ); ?></th>
									<th class="kt-col-num"><?php echo esc_html__( 'CTR', 'konser-takvimi' ); ?></th>
									<th class="kt-col-num"><?php echo esc_html__( 'Tıklayan Kişi', 'konser-takvimi' ); ?></th>
									<th class="kt-col-num"><?php echo esc_html__( 'Dönüşüm', 'konser-takvimi' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php if ( empty( $utm_rows ) ) : ?>
									<tr>
										<td colspan="10" style="text-align: center; color: #666; padding: 20px;"><?php echo esc_html__( 'Henüz UTM parametreli ziyaret kaydedilmedi. Örnek:', 'konser-takvimi' ); ?> <code><?php echo esc_html( home_url( '/urla-konseri/?utm_source=instagram&utm_medium=social&utm_campaign=urla' ) ); ?></code></td>
									</tr>
								<?php else : ?>
									<?php foreach ( $utm_rows as $utm_row ) : ?>
										<?php $utm_ctr = $utm_row['views'] > 0 ? number_format( ( $utm_row['clicks'] / $utm_row['views'] ) * 100, 1, ',', '.' ) : '0,0'; ?>
										<tr>
											<td><code><?php echo esc_html( '' !== $utm_row['source'] ? $utm_row['source'] : '—' ); ?></code></td>
											<td><code><?php echo esc_html( '' !== $utm_row['medium'] ? $utm_row['medium'] : '—' ); ?></code></td>
											<td><code><?php echo esc_html( '' !== $utm_row['campaign'] ? $utm_row['campaign'] : '—' ); ?></code></td>
											<td><?php echo esc_html( $utm_row['district'] ); ?></td>
											<td><?php echo esc_html( number_format_i18n( $utm_row['visitors'] ) ); ?></td>
											<td><?php echo esc_html( number_format_i18n( $utm_row['views'] ) ); ?></td>
											<td style="color: #15803d; font-weight: 600;"><?php echo esc_html( number_format_i18n( $utm_row['clicks'] ) ); ?></td>
											<td>%<?php echo esc_html( $utm_ctr ); ?></td>
							<td><?php echo esc_html( number_format_i18n( $utm_row['click_visitors'] ) ); ?></td>
							<td>%<?php echo esc_html( number_format( kt_conversion_rate( $utm_row['click_visitors'], $utm_row['visitors'] ), 1, ',', '.' ) ); ?></td>
										</tr>
									<?php endforeach; ?>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
					<?php if ( $utm_row_total > count( $utm_rows ) ) : ?>
						<p class="description"><?php echo esc_html( sprintf( /* translators: 1: shown rows, 2: total rows */ __( 'İlk %1$d satır gösteriliyor (toplam %2$d).', 'konser-takvimi' ), count( $utm_rows ), $utm_row_total ) ); ?></p>
					<?php endif; ?>
				</div>
			</div>
