<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'kt_register_concert_menu' );
function kt_register_concert_menu() {
	add_menu_page(
		'Konser Takvimi',
		'Konser Takvimi',
		'manage_options',
		'konser-takvimi',
		'kt_render_admin_page',
		'dashicons-calendar-alt',
		25
	);
}

add_action( 'admin_enqueue_scripts', 'kt_enqueue_admin_assets' );
function kt_enqueue_admin_assets( $hook ) {
	if ( 'toplevel_page_konser-takvimi' !== $hook ) {
		return;
	}
	wp_enqueue_media();
	wp_enqueue_style(
		'kt-admin-css',
		KT_PLUGIN_URL . 'assets/css/admin.css',
		array(),
		KT_PLUGIN_VER
	);
	wp_enqueue_script(
		'kt-admin-js',
		KT_PLUGIN_URL . 'assets/js/admin.js',
		array(),
		KT_PLUGIN_VER,
		true
	);
	wp_localize_script( 'kt-admin-js', 'ktI18n', kt_get_js_dictionary() );
}

// Statistics report download handler (printable HTML report or CSV).
add_action( 'admin_init', 'kt_handle_stats_export' );
function kt_handle_stats_export() {
	if ( ! isset( $_POST['kt_download_report_action'] ) && ! isset( $_POST['kt_export_stats_action'] ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Yetkisiz erişim.', 'konser-takvimi' ) );
	}

	kt_flush_stats_buffer();

	check_admin_referer( 'kt_stats_nonce_action', 'kt_stats_nonce' );

	$date_stamp    = kt_get_site_date_str( 0 );
	$report_format = isset( $_POST['report_format'] ) ? sanitize_key( $_POST['report_format'] ) : 'html';
	$report_period = isset( $_POST['report_period'] ) ? intval( $_POST['report_period'] ) : 7;

	if ( isset( $_POST['kt_export_stats_action'] ) ) {
		$report_format = 'csv';
	}

	if ( ! in_array( $report_period, array( 7, 14, 30, 0 ), true ) ) {
		$report_period = 7;
	}

	$bundle = kt_build_stats_report_bundle( $report_period );
	$scope  = ( $report_period > 0 ) ? "son-{$report_period}-gun" : 'tum-zamanlar';

	if ( 'csv' === $report_format ) {
		$filename = "konser-istatistik-raporu-{$scope}-{$date_stamp}.csv";

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$output = fopen( 'php://output', 'w' );
		fwrite( $output, "\xEF\xBB\xBF" );
		fputcsv(
			$output,
			array(
				sprintf( __( 'Dönem: %s', 'konser-takvimi' ), $bundle['period_label'] ),
				sprintf( __( 'Oluşturulma: %s', 'konser-takvimi' ), $bundle['generated_at'] ),
			),
			';'
		);
		fputcsv( $output, array(), ';' );
		fputcsv(
			$output,
			array(
				__( 'İlçe / Anahtar', 'konser-takvimi' ),
				__( 'Sayfa Başlığı', 'konser-takvimi' ),
				__( 'Tekil Ziyaretçi', 'konser-takvimi' ),
				__( 'Sayfa Gösterimi', 'konser-takvimi' ),
				__( 'Bilet Tıklaması', 'konser-takvimi' ),
				__( 'Tıklama Oranı - CTR (%)', 'konser-takvimi' ),
				__( 'Tıklayan Tekil Kişi', 'konser-takvimi' ),
				__( 'Dönüşüm: Tıklayan Kişi / Tekil Ziyaretçi (%)', 'konser-takvimi' ),
				__( 'Ort. Kalma Süresi (sn)', 'konser-takvimi' ),
				__( 'Genel Toplam Ziyaretçi', 'konser-takvimi' ),
				__( 'Genel Toplam Tıklama', 'konser-takvimi' ),
			),
			';'
		);

		foreach ( $bundle['rows'] as $row ) {
			$label = preg_replace( '/^[=+\-@]/', "'$0", $row['label'] );
			$ctr   = number_format( $row['ctr'], 1, ',', '' );
			$conv  = number_format( $row['conversion'], 1, ',', '' );
			fputcsv(
				$output,
				array(
					$row['key'],
					$label,
					$row['visitors'],
					$row['views'],
					$row['clicks'],
					$ctr,
					$row['click_visitors'],
					$conv,
					$row['avg_sec'],
					$row['total_vis'],
					$row['total_clicks'],
				),
				';'
			);
		}

		// Second block: UTM traffic sources (all time, not limited to the selected period).
		if ( ! empty( $bundle['utm_rows'] ) ) {
			fputcsv( $output, array(), ';' );
			fputcsv( $output, array( __( 'UTM Trafik Kaynakları (tüm zamanlar)', 'konser-takvimi' ) ), ';' );
			fputcsv(
				$output,
				array( __( 'Kaynak', 'konser-takvimi' ), __( 'Ortam', 'konser-takvimi' ), __( 'Kampanya', 'konser-takvimi' ), __( 'İlçe / Anahtar', 'konser-takvimi' ), __( 'Tekil Ziyaretçi', 'konser-takvimi' ), __( 'Sayfa Gösterimi', 'konser-takvimi' ), __( 'Bilet Tıklaması', 'konser-takvimi' ), __( 'Tıklama Oranı - CTR (%)', 'konser-takvimi' ), __( 'Tıklayan Tekil Kişi', 'konser-takvimi' ), __( 'Dönüşüm (%)', 'konser-takvimi' ) ),
				';'
			);
			foreach ( $bundle['utm_rows'] as $u ) {
				fputcsv(
					$output,
					array(
						preg_replace( '/^[=+\-@]/', "'$0", $u['source'] ),
						preg_replace( '/^[=+\-@]/', "'$0", $u['medium'] ),
						preg_replace( '/^[=+\-@]/', "'$0", $u['campaign'] ),
						$u['district_key'],
						$u['visitors'],
						$u['views'],
						$u['clicks'],
						number_format( $u['ctr'], 1, ',', '' ),
						$u['click_visitors'],
						number_format( $u['conversion'], 1, ',', '' ),
					),
					';'
				);
			}
		}

		fclose( $output );
		exit;
	}

	// Printable / save-as-PDF visual HTML report file.
	$filename = "konser-performans-raporu-{$scope}-{$date_stamp}.html";
	nocache_headers();
	header( 'Content-Type: text/html; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	?>
<!DOCTYPE html>
<html lang="tr">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php echo esc_html( $bundle['site_name'] . ' — Konser Performans Raporu (' . $bundle['period_label'] . ')' ); ?></title>
	<style>
		body { background: #f1f5f9; margin: 0; padding: 28px 16px; }
		.print-bar { max-width: 760px; margin: 0 auto 14px auto; display: flex; justify-content: flex-end; }
		.print-btn { background: #1e293b; color: #fff; border: none; padding: 9px 16px; border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer; }
		.print-btn:hover { background: #0f172a; }
		@media print {
			body { background: #ffffff; padding: 0; }
			.print-bar { display: none; }
		}
	</style>
</head>
<body>
	<div class="print-bar">
		<button type="button" class="print-btn" onclick="window.print()"><?php echo esc_html__( 'Yazdır / PDF Olarak Kaydet', 'konser-takvimi' ); ?></button>
	</div>
	<?php kt_build_stats_report_bundle( $report_period, true ); ?>
</body>
</html>
	<?php
	exit;
}

// JSON and CSV concert export handler.
add_action( 'admin_init', 'kt_handle_concert_export' );
function kt_handle_concert_export() {
	if ( ! isset( $_POST['kt_export_action'] ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Yetkisiz erişim.', 'konser-takvimi' ) );
	}

	check_admin_referer( 'kt_export_nonce_action', 'kt_export_nonce' );

	$format = isset( $_POST['export_format'] ) ? sanitize_key( $_POST['export_format'] ) : 'json';
	$scope  = isset( $_POST['export_scope'] ) ? sanitize_key( $_POST['export_scope'] ) : 'all';

	if ( ! in_array( $format, array( 'json', 'csv' ), true ) ) {
		$format = 'json';
	}
	if ( ! in_array( $scope, array( 'all', 'active', 'archived' ), true ) ) {
		$scope = 'all';
	}

	$now_ts     = kt_get_site_timestamp();
	$all_events = get_option( KT_EVENTS_OPT, array() );
	if ( ! is_array( $all_events ) ) {
		$all_events = array();
	}

	$filtered = array();
	foreach ( $all_events as $ev ) {
		$end_ts      = kt_get_event_end_timestamp( $ev );
		$is_archived = ( false !== $end_ts && $end_ts < $now_ts );

		if ( 'active' === $scope && $is_archived ) {
			continue;
		}
		if ( 'archived' === $scope && ! $is_archived ) {
			continue;
		}

		$ev['status'] = $is_archived ? __( 'Arşivlendi', 'konser-takvimi' ) : __( 'Aktif', 'konser-takvimi' );
		$filtered[]   = $ev;
	}

	$date_stamp = kt_get_site_date_str( 0 );
	$filename   = "konser-takvimi-{$scope}-{$date_stamp}.{$format}";

	nocache_headers();

	if ( 'csv' === $format ) {
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$output = fopen( 'php://output', 'w' );
		fwrite( $output, "\xEF\xBB\xBF" );
		fputcsv( $output, array( __( 'Tarih', 'konser-takvimi' ), __( '1. Seans', 'konser-takvimi' ), __( '2. Seans', 'konser-takvimi' ), __( 'Şehir / İlçe', 'konser-takvimi' ), __( 'Slug', 'konser-takvimi' ), __( 'Durum', 'konser-takvimi' ), __( 'ISO Tarih', 'konser-takvimi' ) ), ';' );

		foreach ( $filtered as $row ) {
			$dt_parts  = explode( 'T', $row['datetime'] );
			$d_val     = isset( $dt_parts[0] ) ? $dt_parts[0] : '';
			$t1_val    = isset( $dt_parts[1] ) ? substr( $dt_parts[1], 0, 5 ) : '';
			$t2_val    = isset( $row['time2'] ) ? $row['time2'] : '';
			$safe_city = preg_replace( '/^[=+\-@]/', "'$0", $row['city'] );
			$safe_slug = preg_replace( '/^[=+\-@]/', "'$0", $row['slug'] );

			fputcsv(
				$output,
				array(
					$d_val,
					$t1_val,
					$t2_val,
					$safe_city,
					$safe_slug,
					$row['status'],
					$row['datetime'],
				),
				';'
			);
		}
		fclose( $output );
		exit;
	}

	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	echo wp_json_encode( $filtered, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
	exit;
}

function kt_render_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'concerts'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab selection; sanitized and whitelisted below.
	if ( ! in_array( $active_tab, array( 'concerts', 'stats', 'styles', 'tools' ), true ) ) {
		$active_tab = 'concerts';
	}

	$message         = '';
	$now_ts          = kt_get_site_timestamp();
	$existing_events = get_option( KT_EVENTS_OPT, array() );

	if ( ! is_array( $existing_events ) ) {
		$existing_events = array();
	}

	// Make sure buffered tracking events are visible before any statistics are shown or changed.
	if ( 'stats' === $active_tab || 'tools' === $active_tab ) {
		kt_flush_stats_buffer();
	}

	// Tools tab actions (archive cleanup, buffer flush).
	$tools_message = kt_handle_tools_actions();
	if ( '' !== $tools_message ) {
		$message = $tools_message;
	}

	require KT_PLUGIN_DIR . 'includes/admin/actions-concerts.php';
	require KT_PLUGIN_DIR . 'includes/admin/actions-stats.php';
	require KT_PLUGIN_DIR . 'includes/admin/actions-styles.php';

	$upcoming_list = array();
	$archived_list = array();

	foreach ( $existing_events as $event ) {
		$end_ts = kt_get_event_end_timestamp( $event );
		if ( false !== $end_ts && $end_ts < $now_ts ) {
			$archived_list[] = $event;
		} else {
			$upcoming_list[] = $event;
		}
	}

	usort( $archived_list, 'kt_sort_events_desc' );
	$archive_count = count( $archived_list );
	$active_count  = count( $upcoming_list );
	$styles        = kt_get_style_settings();

	$existing_posts = get_posts(
		array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'publish',
			'posts_per_page' => 500,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);
	?>
	<div class="wrap">
		<h1><?php echo esc_html__( 'Konser Takvimi', 'konser-takvimi' ); ?></h1>
		<p>
			<?php echo wp_kses( __( 'Gutenberg editöründe <strong>Konser Takvimi</strong>, <strong>Konser İlçe Yönlendirici &amp; Takvime Ekle</strong> ve <strong>Konser Bilet Butonu</strong> bloklarını kullanabilirsiniz. <em>(Eski <code>[konser_takvimi]</code>, <code>[konser_ilce_yonlendir]</code> ve <code>[konser_bilet_butonu]</code> kısa kodları geriye dönük uyumluluk için çalışmaya devam eder.)</em>', 'konser-takvimi' ), kt_i18n_allowed_html() ); ?>
		</p>
		<?php echo wp_kses_post( $message ); ?>
		<?php if ( 'tools' !== $active_tab && get_option( KT_STATS_OVERFLOW_OPT, '' ) ) : ?>
			<div class="notice notice-warning"><p>
				<strong>Konser Takvimi:</strong> <?php echo esc_html( sprintf( /* translators: %d: maximum number of districts */ __( 'İstatistik tablosu %d ilçe sınırına ulaştı ve yeni ilçeler kaydedilemiyor.', 'konser-takvimi' ), KT_STATS_MAX_DISTRICTS ) ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=konser-takvimi&tab=tools' ) ); ?>"><?php echo esc_html__( 'Ayrıntılar', 'konser-takvimi' ); ?></a>
			</p></div>
		<?php endif; ?>

		<nav class="nav-tab-wrapper" style="margin-top: 15px; max-width: 1060px;">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=konser-takvimi&tab=concerts' ) ); ?>" class="nav-tab <?php echo 'concerts' === $active_tab ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html__( 'Konser Listesi & Arşiv', 'konser-takvimi' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=konser-takvimi&tab=stats' ) ); ?>" class="nav-tab <?php echo 'stats' === $active_tab ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html__( 'İstatistikler & Grafik', 'konser-takvimi' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=konser-takvimi&tab=styles' ) ); ?>" class="nav-tab <?php echo 'styles' === $active_tab ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html__( 'Düğme Biçimleri, Renkler & Ayarlar', 'konser-takvimi' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=konser-takvimi&tab=tools' ) ); ?>" class="nav-tab <?php echo 'tools' === $active_tab ? 'nav-tab-active' : ''; ?>">
				<?php echo esc_html__( 'Araçlar & Tanılama', 'konser-takvimi' ); ?>
			</a>
		</nav>

		<?php if ( 'tools' === $active_tab ) : ?>
			<?php kt_render_tools_tab( $existing_events ); ?>
		<?php endif; ?>

		<?php if ( 'concerts' === $active_tab ) : ?>
			<?php require KT_PLUGIN_DIR . 'includes/admin/view-concerts.php'; ?>

		<?php elseif ( 'stats' === $active_tab ) : ?>
			<?php require KT_PLUGIN_DIR . 'includes/admin/view-stats.php'; ?>

		<?php else : ?>
			<?php require KT_PLUGIN_DIR . 'includes/admin/view-styles.php'; ?>

		<?php endif; ?>
	</div>
	<?php
}
