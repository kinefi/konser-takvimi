<?php
/**
 * Statistics report bundle used by the HTML report, CSV export and weekly e-mail.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the summary statistics report data and its HTML output for the given number of days (e.g. 7, 14, 30, or all time).
 */
function kt_build_stats_report_bundle( $days_span = 7, $render_direct = false ) {
	kt_flush_stats_buffer();
	$stats = get_option( KT_STATS_OPT, array() );
	if ( ! is_array( $stats ) ) {
		$stats = array();
	}

	$days_span  = intval( $days_span );
	$is_alltime = ( $days_span <= 0 );
	$day_keys   = array();

	if ( ! $is_alltime ) {
		for ( $d = $days_span - 1; $d >= 0; $d-- ) {
			$day_keys[] = kt_get_site_date_str( -$d );
		}
		$period_label = $day_keys[0] . ' – ' . $day_keys[ count( $day_keys ) - 1 ] . sprintf( /* translators: %d: number of days */ __( ' (Son %d Gün)', 'konser-takvimi' ), $days_span );
	} else {
		$period_label = __( 'Tüm Zamanlar (Kümülatif)', 'konser-takvimi' );
	}

	$rows         = array();
	$sum_views    = 0;
	$sum_visitors = 0;
	$sum_clicks   = 0;
	$sum_click_visitors = 0;
	$sum_seconds  = 0;
	$sum_sessions = 0;

	foreach ( $stats as $key => $item ) {
		$all_v   = isset( $item['views'] ) ? intval( $item['views'] ) : 0;
		$all_vis = isset( $item['visitors'] ) ? intval( $item['visitors'] ) : 0;
		$all_c   = isset( $item['clicks'] ) ? intval( $item['clicks'] ) : 0;
		$all_cv  = isset( $item['click_visitors'] ) ? intval( $item['click_visitors'] ) : 0;
		$all_sec = isset( $item['total_seconds'] ) ? intval( $item['total_seconds'] ) : 0;
		$all_ses = isset( $item['duration_count'] ) ? intval( $item['duration_count'] ) : 0;

		if ( $is_alltime ) {
			$p_v   = $all_v;
			$p_vis = $all_vis;
			$p_c   = $all_c;
			$p_cv  = $all_cv;
			$p_sec = $all_sec;
			$p_ses = $all_ses;
		} else {
			$p_v   = 0;
			$p_vis = 0;
			$p_c   = 0;
			$p_cv  = 0;
			$p_sec = 0;
			$p_ses = 0;

			if ( isset( $item['daily'] ) && is_array( $item['daily'] ) ) {
				foreach ( $day_keys as $day_str ) {
					if ( isset( $item['daily'][ $day_str ] ) ) {
						$d_row  = $item['daily'][ $day_str ];
						$p_v   += isset( $d_row['views'] ) ? intval( $d_row['views'] ) : 0;
						$p_vis += isset( $d_row['visitors'] ) ? intval( $d_row['visitors'] ) : 0;
						$p_c   += isset( $d_row['clicks'] ) ? intval( $d_row['clicks'] ) : 0;
						$p_cv  += isset( $d_row['click_visitors'] ) ? intval( $d_row['click_visitors'] ) : 0;
						$p_sec += isset( $d_row['seconds'] ) ? intval( $d_row['seconds'] ) : 0;
						$p_ses += isset( $d_row['sessions'] ) ? intval( $d_row['sessions'] ) : 0;
					}
				}
			}
		}

		if ( $p_v > 0 || $p_c > 0 || $all_v > 0 ) {
			$rows[] = array(
				'key'          => $key,
				'label'        => isset( $item['label'] ) ? $item['label'] : $key,
				'views'        => $p_v,
				'visitors'     => $p_vis,
				'clicks'       => $p_c,
				'ctr'          => $p_v > 0 ? round( ( $p_c / $p_v ) * 100, 1 ) : 0.0,
				'click_visitors' => $p_cv,
				'conversion'   => kt_conversion_rate( $p_cv, $p_vis ),
				'avg_sec'      => $p_ses > 0 ? round( $p_sec / $p_ses ) : 0,
				'total_views'  => $all_v,
				'total_vis'    => $all_vis,
				'total_clicks' => $all_c,
			);
		}

		$sum_views    += $p_v;
		$sum_visitors += $p_vis;
		$sum_clicks   += $p_c;
		$sum_click_visitors += $p_cv;
		$sum_seconds  += $p_sec;
		$sum_sessions += $p_ses;
	}

	usort(
		$rows,
		function ( $a, $b ) {
			if ( $a['visitors'] === $b['visitors'] ) {
				return $b['views'] - $a['views'];
			}
			return $b['visitors'] - $a['visitors'];
		}
	);

	$utm_report_rows = kt_get_utm_report_rows( $stats );
	$utm_top_rows    = array_slice( $utm_report_rows, 0, 15 );

	$overall_avg_sec = $sum_sessions > 0 ? round( $sum_seconds / $sum_sessions ) : 0;
	$overall_ctr        = $sum_views > 0 ? number_format( ( $sum_clicks / $sum_views ) * 100, 1, ',', '.' ) : '0,0';
	$overall_conversion = number_format( kt_conversion_rate( $sum_click_visitors, $sum_visitors ), 1, ',', '.' );
	$site_name       = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
	$generated_at    = kt_get_site_datetime_str();

	if ( ! $render_direct ) {
		ob_start();
	}
	?>
	<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #1e293b; max-width: 760px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden;">
		<div style="background: #1e293b; color: #ffffff; padding: 20px 24px;">
			<h2 style="margin: 0; font-size: 18px;"><?php echo esc_html( sprintf( /* translators: %s: site name */ __( '%s — Konser Sayfası Performans Raporu', 'konser-takvimi' ), $site_name ) ); ?></h2>
			<p style="margin: 6px 0 0 0; font-size: 13px; color: #cbd5e1;"><?php echo esc_html( sprintf( /* translators: 1: period, 2: generation time */ __( 'Dönem: %1$s • Oluşturulma: %2$s', 'konser-takvimi' ), $period_label, $generated_at ) ); ?></p>
		</div>

		<div style="padding: 20px 24px;">
			<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 20px; border-collapse: collapse;">
				<tr>
					<td style="width: 25%; padding: 10px; background: #f8fafc; border: 1px solid #e2e8f0; text-align: center;">
						<div style="font-size: 11px; color: #64748b; font-weight: 600;"><?php echo esc_html__( 'Tekil Ziyaretçi', 'konser-takvimi' ); ?></div>
						<div style="font-size: 20px; font-weight: 700; color: #0f172a; margin-top: 4px;"><?php echo esc_html( number_format_i18n( $sum_visitors ) ); ?></div>
					</td>
					<td style="width: 25%; padding: 10px; background: #f8fafc; border: 1px solid #e2e8f0; text-align: center;">
						<div style="font-size: 11px; color: #64748b; font-weight: 600;"><?php echo esc_html__( 'Sayfa Gösterimi', 'konser-takvimi' ); ?></div>
						<div style="font-size: 20px; font-weight: 700; color: #0f172a; margin-top: 4px;"><?php echo esc_html( number_format_i18n( $sum_views ) ); ?></div>
					</td>
					<td style="width: 25%; padding: 10px; background: #f8fafc; border: 1px solid #e2e8f0; text-align: center;">
						<div style="font-size: 11px; color: #64748b; font-weight: 600;"><?php echo esc_html__( 'Bilet Tıklaması (CTR)', 'konser-takvimi' ); ?></div>
						<div style="font-size: 20px; font-weight: 700; color: #16a34a; margin-top: 4px;"><?php echo esc_html( number_format_i18n( $sum_clicks ) ); ?> (%<?php echo esc_html( $overall_ctr ); ?>)</div>
						<div style="font-size: 11px; color: #64748b; margin-top: 6px;"><?php echo esc_html__( 'Tıklayan kişi / ziyaretçi:', 'konser-takvimi' ); ?> <strong><?php echo esc_html( number_format_i18n( $sum_click_visitors ) ); ?> / <?php echo esc_html( number_format_i18n( $sum_visitors ) ); ?> (%<?php echo esc_html( $overall_conversion ); ?>)</strong></div>
					</td>
					<td style="width: 25%; padding: 10px; background: #f8fafc; border: 1px solid #e2e8f0; text-align: center;">
						<div style="font-size: 11px; color: #64748b; font-weight: 600;"><?php echo esc_html__( 'Ort. Kalma Süresi', 'konser-takvimi' ); ?></div>
						<div style="font-size: 20px; font-weight: 700; color: #2563eb; margin-top: 4px;"><?php echo esc_html( kt_format_duration_human( $overall_avg_sec ) ); ?></div>
					</td>
				</tr>
			</table>

			<h3 style="font-size: 15px; margin: 0 0 10px 0; color: #0f172a;"><?php echo esc_html__( 'İlçe Bazlı Performans Dağılımı', 'konser-takvimi' ); ?></h3>
			<?php if ( empty( $rows ) ) : ?>
				<p style="color: #64748b; font-size: 13px;"><?php echo esc_html__( 'Bu dönemde henüz kayıtlı ziyaret verisi oluşmamıştır.', 'konser-takvimi' ); ?></p>
			<?php else : ?>
				<table width="100%" cellpadding="8" cellspacing="0" style="border-collapse: collapse; font-size: 13px; border: 1px solid #e2e8f0;">
					<thead>
						<tr style="background: #f1f5f9; text-align: left;">
							<th style="border-bottom: 1px solid #cbd5e1;"><?php echo esc_html__( 'İlçe / Sayfa', 'konser-takvimi' ); ?></th>
							<th style="border-bottom: 1px solid #cbd5e1; text-align: right;"><?php echo esc_html__( 'Ziyaretçi', 'konser-takvimi' ); ?></th>
							<th style="border-bottom: 1px solid #cbd5e1; text-align: right;"><?php echo esc_html__( 'Gösterim', 'konser-takvimi' ); ?></th>
							<th style="border-bottom: 1px solid #cbd5e1; text-align: right;"><?php echo esc_html__( 'Bilet Tık', 'konser-takvimi' ); ?></th>
							<th style="border-bottom: 1px solid #cbd5e1; text-align: right;"><?php echo esc_html__( 'CTR', 'konser-takvimi' ); ?></th>
							<th style="border-bottom: 1px solid #cbd5e1; text-align: right;"><?php echo esc_html__( 'Tıklayan Kişi', 'konser-takvimi' ); ?></th>
							<th style="border-bottom: 1px solid #cbd5e1; text-align: right;"><?php echo esc_html__( 'Dönüşüm', 'konser-takvimi' ); ?></th>
							<th style="border-bottom: 1px solid #cbd5e1; text-align: right;"><?php echo esc_html__( 'Ort. Süre', 'konser-takvimi' ); ?></th>
							<?php if ( ! $is_alltime ) : ?>
								<th style="border-bottom: 1px solid #cbd5e1; text-align: right;"><?php echo esc_html__( 'Genel Tık', 'konser-takvimi' ); ?></th>
							<?php endif; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $r ) : ?>
							<tr>
								<td style="border-bottom: 1px solid #e2e8f0;"><strong><?php echo esc_html( $r['label'] ); ?></strong></td>
								<td style="border-bottom: 1px solid #e2e8f0; text-align: right;"><?php echo esc_html( number_format_i18n( $r['visitors'] ) ); ?></td>
								<td style="border-bottom: 1px solid #e2e8f0; text-align: right;"><?php echo esc_html( number_format_i18n( $r['views'] ) ); ?></td>
								<td style="border-bottom: 1px solid #e2e8f0; text-align: right; color: #16a34a; font-weight: 600;"><?php echo esc_html( number_format_i18n( $r['clicks'] ) ); ?></td>
								<td style="border-bottom: 1px solid #e2e8f0; text-align: right;">%<?php echo esc_html( number_format( $r['ctr'], 1, ',', '.' ) ); ?></td>
								<td style="border-bottom: 1px solid #e2e8f0; text-align: right;"><?php echo esc_html( number_format_i18n( $r['click_visitors'] ) ); ?></td>
								<td style="border-bottom: 1px solid #e2e8f0; text-align: right;">%<?php echo esc_html( number_format( $r['conversion'], 1, ',', '.' ) ); ?></td>
								<td style="border-bottom: 1px solid #e2e8f0; text-align: right;"><?php echo esc_html( kt_format_duration_human( $r['avg_sec'] ) ); ?></td>
								<?php if ( ! $is_alltime ) : ?>
									<td style="border-bottom: 1px solid #e2e8f0; text-align: right; color: #64748b;"><?php echo esc_html( number_format_i18n( $r['total_clicks'] ) ); ?></td>
								<?php endif; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<?php if ( ! empty( $utm_top_rows ) ) : ?>
				<h3 style="font-size: 15px; margin: 24px 0 4px 0; color: #0f172a;"><?php echo esc_html__( 'Trafik Kaynakları (UTM)', 'konser-takvimi' ); ?></h3>
				<p style="margin: 0 0 10px 0; font-size: 12px; color: #64748b;"><?php echo esc_html__( 'Tüm zamanlar; yalnızca UTM parametreli ziyaretler', 'konser-takvimi' ); ?><?php echo count( $utm_report_rows ) > count( $utm_top_rows ) ? esc_html( sprintf( /* translators: %d: number of rows */ __( ' (ilk %d satır)', 'konser-takvimi' ), count( $utm_top_rows ) ) ) : ''; ?>.</p>
				<table width="100%" cellpadding="8" cellspacing="0" style="border-collapse: collapse; font-size: 13px; border: 1px solid #e2e8f0;">
					<thead>
						<tr style="background: #f1f5f9; text-align: left;">
							<th style="border-bottom: 1px solid #cbd5e1;"><?php echo esc_html__( 'Kaynak / Ortam / Kampanya', 'konser-takvimi' ); ?></th>
							<th style="border-bottom: 1px solid #cbd5e1;"><?php echo esc_html__( 'İlçe', 'konser-takvimi' ); ?></th>
							<th style="border-bottom: 1px solid #cbd5e1; text-align: right;"><?php echo esc_html__( 'Ziyaretçi', 'konser-takvimi' ); ?></th>
							<th style="border-bottom: 1px solid #cbd5e1; text-align: right;"><?php echo esc_html__( 'Tıklayan', 'konser-takvimi' ); ?></th>
							<th style="border-bottom: 1px solid #cbd5e1; text-align: right;"><?php echo esc_html__( 'Dönüşüm', 'konser-takvimi' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $utm_top_rows as $u ) : ?>
							<tr>
								<td style="border-bottom: 1px solid #e2e8f0;"><?php echo esc_html( implode( ' / ', array_map( function ( $part ) { return '' !== $part ? $part : '—'; }, array( $u['source'], $u['medium'], $u['campaign'] ) ) ) ); ?></td>
								<td style="border-bottom: 1px solid #e2e8f0;"><?php echo esc_html( $u['district'] ); ?></td>
								<td style="border-bottom: 1px solid #e2e8f0; text-align: right;"><?php echo esc_html( number_format_i18n( $u['visitors'] ) ); ?></td>
								<td style="border-bottom: 1px solid #e2e8f0; text-align: right; color: #16a34a; font-weight: 600;"><?php echo esc_html( number_format_i18n( $u['click_visitors'] ) ); ?></td>
								<td style="border-bottom: 1px solid #e2e8f0; text-align: right;">%<?php echo esc_html( number_format( $u['conversion'], 1, ',', '.' ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<p style="margin-top: 20px; font-size: 12px; color: #64748b;">
				<?php echo esc_html__( 'Detaylı günlük grafikler için WordPress yönetim panelindeki', 'konser-takvimi' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=konser-takvimi&tab=stats' ) ); ?>"><?php echo wp_kses( __( 'Konser Takvimi &rarr; İstatistikler &amp; Grafik', 'konser-takvimi' ), kt_i18n_allowed_html() ); ?></a> <?php echo esc_html__( 'sayfasını ziyaret edebilirsiniz.', 'konser-takvimi' ); ?>
			</p>
		</div>
	</div>
	<?php
	$html_card = ! $render_direct ? ob_get_clean() : '';

	return array(
		'period_label'    => $period_label,
		'generated_at'    => $generated_at,
		'site_name'       => $site_name,
		'sum_visitors'    => $sum_visitors,
		'sum_views'       => $sum_views,
		'sum_clicks'      => $sum_clicks,
		'overall_ctr'     => $overall_ctr,
		'sum_click_visitors' => $sum_click_visitors,
		'overall_conversion' => $overall_conversion,
		'overall_avg_sec' => $overall_avg_sec,
		'rows'            => $rows,
		'utm_rows'        => $utm_report_rows,
		'html_card'       => $html_card,
	);
}
