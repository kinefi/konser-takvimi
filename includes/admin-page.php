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
		'kt_konser_admin_page',
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
}

// İstatistik CSV Dışa Aktarma İşleyicisi
add_action( 'admin_init', 'kt_handle_stats_export' );
function kt_handle_stats_export() {
	if ( ! isset( $_POST['kt_export_stats_action'] ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Yetkisiz erişim.' );
	}

	check_admin_referer( 'kt_stats_nonce_action', 'kt_stats_nonce' );

	$stats = get_option( KT_STATS_OPT, array() );
	if ( ! is_array( $stats ) ) {
		$stats = array();
	}

	$today_str  = kt_get_istanbul_date_str( 0 );
	$date_stamp = kt_get_istanbul_date_str( 0 );
	$filename   = "konser-ilce-istatistikleri-{$date_stamp}.csv";

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

	$output = fopen( 'php://output', 'w' );
	fwrite( $output, "\xEF\xBB\xBF" );
	fputcsv(
		$output,
		array(
			'İlçe / Anahtar',
			'Sayfa Başlığı',
			'Bugünkü Ziyaretçi',
			'Toplam Tekil Ziyaretçi',
			'Toplam Gösterim',
			'Bilet Tıklaması',
			'Tıklama Oranı (%)',
			'Ort. Kalma Süresi (sn)',
			'Son Etkileşim',
		),
		';'
	);

	foreach ( $stats as $key => $row ) {
		$views     = isset( $row['views'] ) ? intval( $row['views'] ) : 0;
		$visitors  = isset( $row['visitors'] ) ? intval( $row['visitors'] ) : 0;
		$clicks    = isset( $row['clicks'] ) ? intval( $row['clicks'] ) : 0;
		$tot_sec   = isset( $row['total_seconds'] ) ? intval( $row['total_seconds'] ) : 0;
		$dur_cnt   = isset( $row['duration_count'] ) ? intval( $row['duration_count'] ) : 0;
		$avg_sec   = $dur_cnt > 0 ? round( $tot_sec / $dur_cnt ) : 0;
		$today_vis = ( isset( $row['daily'][ $today_str ]['visitors'] ) ) ? intval( $row['daily'][ $today_str ]['visitors'] ) : 0;
		$ctr       = $views > 0 ? number_format( ( $clicks / $views ) * 100, 1, ',', '' ) : '0,0';
		$label     = isset( $row['label'] ) ? preg_replace( '/^[=+\-@]/', "'$0", $row['label'] ) : $key;
		$upd       = isset( $row['updated'] ) ? $row['updated'] : '';

		fputcsv( $output, array( $key, $label, $today_vis, $visitors, $views, $clicks, $ctr, $avg_sec, $upd ), ';' );
	}

	fclose( $output );
	exit;
}

// JSON ve CSV Konser Dışa Aktarma (Export) İşleyicisi
add_action( 'admin_init', 'kt_handle_concert_export' );
function kt_handle_concert_export() {
	if ( ! isset( $_POST['kt_export_action'] ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Yetkisiz erişim.' );
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

	$now_ts     = kt_get_istanbul_timestamp();
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

		$ev['status'] = $is_archived ? 'Arşivlendi' : 'Aktif';
		$filtered[]   = $ev;
	}

	$date_stamp = kt_get_istanbul_date_str( 0 );
	$filename   = "konser-takvimi-{$scope}-{$date_stamp}.{$format}";

	nocache_headers();

	if ( 'csv' === $format ) {
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$output = fopen( 'php://output', 'w' );
		fwrite( $output, "\xEF\xBB\xBF" );
		fputcsv( $output, array( 'Tarih', '1. Seans', '2. Seans', 'Şehir / İlçe', 'Slug', 'Durum', 'ISO Tarih' ), ';' );

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

function kt_konser_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'concerts';
	if ( ! in_array( $active_tab, array( 'concerts', 'stats', 'styles' ), true ) ) {
		$active_tab = 'concerts';
	}

	$message         = '';
	$now_ts          = kt_get_istanbul_timestamp();
	$existing_events = get_option( KT_EVENTS_OPT, array() );

	if ( ! is_array( $existing_events ) ) {
		$existing_events = array();
	}

	// 1. Sekme: Konserleri Kaydetme
	if ( isset( $_POST['kt_save_concerts'] ) && check_admin_referer( 'kt_conser_nonce_action', 'kt_conser_nonce' ) ) {
		$archived_events = array();

		foreach ( $existing_events as $ev ) {
			$end_ts = kt_get_event_end_timestamp( $ev );
			if ( false !== $end_ts && $end_ts < $now_ts ) {
				$archived_events[] = $ev;
			}
		}

		$dates         = isset( $_POST['concert_date'] ) ? (array) $_POST['concert_date'] : array();
		$times         = isset( $_POST['concert_time'] ) ? (array) $_POST['concert_time'] : array();
		$times2        = isset( $_POST['concert_time2'] ) ? (array) $_POST['concert_time2'] : array();
		$cities        = isset( $_POST['concert_city'] ) ? (array) $_POST['concert_city'] : array();
		$slugs         = isset( $_POST['concert_slug'] ) ? (array) $_POST['concert_slug'] : array();
		$active_events = array();
		$total         = count( $cities );

		for ( $i = 0; $i < $total; $i++ ) {
			$city  = sanitize_text_field( wp_unslash( $cities[ $i ] ) );
			$date  = isset( $dates[ $i ] ) ? sanitize_text_field( wp_unslash( $dates[ $i ] ) ) : '';
			$time  = isset( $times[ $i ] ) ? sanitize_text_field( wp_unslash( $times[ $i ] ) ) : '';
			$time2 = isset( $times2[ $i ] ) ? sanitize_text_field( wp_unslash( $times2[ $i ] ) ) : '';
			$slug  = isset( $slugs[ $i ] ) ? sanitize_title( wp_unslash( $slugs[ $i ] ) ) : '';

			if ( empty( $city ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
				continue;
			}

			if ( ! preg_match( '/^\d{2}:\d{2}$/', $time ) ) {
				$time = '15:00';
			}

			if ( ! preg_match( '/^\d{2}:\d{2}$/', $time2 ) ) {
				$time2 = '';
			}

			if ( empty( $slug ) ) {
				$slug = sanitize_title( $city . '-konseri' );
			}

			$active_events[] = array(
				'datetime' => $date . 'T' . $time . ':00+03:00',
				'time2'    => $time2,
				'city'     => $city,
				'slug'     => $slug,
			);
		}

		$all_events = array_merge( $archived_events, $active_events );
		usort( $all_events, 'kt_sort_events_asc' );

		update_option( KT_EVENTS_OPT, $all_events, false );
		$existing_events = $all_events;
		$message         = '<div class="notice notice-success is-dismissible"><p>Konser listesi veritabanına kaydedildi.</p></div>';
	}

	// İstatistik Sıfırlama / Tek Satır Silme
	if ( isset( $_POST['kt_reset_stats'] ) && check_admin_referer( 'kt_stats_nonce_action', 'kt_stats_nonce' ) ) {
		update_option( KT_STATS_OPT, array(), false );
		$message = '<div class="notice notice-info is-dismissible"><p>Tüm ilçe ziyaret ve tıklama istatistikleri sıfırlandı.</p></div>';
	} elseif ( isset( $_POST['kt_delete_stat_key'] ) && check_admin_referer( 'kt_stats_nonce_action', 'kt_stats_nonce' ) ) {
		$del_key = sanitize_title( wp_unslash( $_POST['kt_delete_stat_key'] ) );
		$stats   = get_option( KT_STATS_OPT, array() );
		if ( is_array( $stats ) && isset( $stats[ $del_key ] ) ) {
			unset( $stats[ $del_key ] );
			update_option( KT_STATS_OPT, $stats, false );
			$message = '<div class="notice notice-info is-dismissible"><p>Seçilen ilçe istatistik kaydı silindi.</p></div>';
		}
	}

	// Test Haftalık E-Posta Raporu Gönderme
	if ( isset( $_POST['kt_send_test_report'] ) && check_admin_referer( 'kt_styles_nonce_action', 'kt_styles_nonce' ) ) {
		$test_to = isset( $_POST['weekly_report_email'] ) ? sanitize_email( wp_unslash( $_POST['weekly_report_email'] ) ) : '';
		if ( empty( $test_to ) ) {
			$test_to = get_option( 'admin_email' );
		}
		$sent = kt_send_weekly_stats_email( $test_to );
		if ( $sent ) {
			$message = '<div class="notice notice-success is-dismissible"><p>Örnek haftalık istatistik raporu <strong>' . esc_html( $test_to ) . '</strong> adresine gönderildi.</p></div>';
		} else {
			$message = '<div class="notice notice-error is-dismissible"><p>E-posta gönderilemedi. Lütfen sunucunuzun WordPress e-posta (SMTP) ayarlarını kontrol edin.</p></div>';
		}
	}

	// Ayarlar Sekmesi Kaydetme veya Sıfırlama
	if ( isset( $_POST['kt_reset_styles'] ) && check_admin_referer( 'kt_styles_nonce_action', 'kt_styles_nonce' ) ) {
		delete_option( KT_STYLES_OPT );
		kt_sync_weekly_cron_schedule( true );
		$message = '<div class="notice notice-info is-dismissible"><p>Düğme biçimleri, yönlendirme hedefi, bilet düğmesi ve renk paletleri varsayılan ayarlara döndürüldü.</p></div>';
	} elseif ( isset( $_POST['kt_save_styles'] ) && check_admin_referer( 'kt_styles_nonce_action', 'kt_styles_nonce' ) ) {
		$defaults = kt_get_default_style_settings();

		$radius     = isset( $_POST['border_radius'] ) ? max( 0, min( 50, intval( $_POST['border_radius'] ) ) ) : 20;
		$height     = isset( $_POST['badge_height'] ) ? max( 60, min( 160, intval( $_POST['badge_height'] ) ) ) : 90;
		$gap        = isset( $_POST['grid_gap'] ) ? max( 4, min( 60, intval( $_POST['grid_gap'] ) ) ) : 20;
		$min_w      = isset( $_POST['card_min_w'] ) ? max( 180, min( 450, intval( $_POST['card_min_w'] ) ) ) : 250;
		$max_it     = isset( $_POST['max_items'] ) ? max( 1, min( 100, intval( $_POST['max_items'] ) ) ) : 16;
		$del_uninst = isset( $_POST['delete_on_uninst'] ) ? 1 : 0;

		$raw_redirect = isset( $_POST['redirect_target'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['redirect_target'] ) ) ) : '/konserler';
		if ( empty( $raw_redirect ) ) {
			$raw_redirect = '/konserler';
		} elseif ( 0 !== strpos( $raw_redirect, 'http://' ) && 0 !== strpos( $raw_redirect, 'https://' ) && 0 !== strpos( $raw_redirect, '/' ) ) {
			$raw_redirect = '/' . $raw_redirect;
		}

		$raw_ticket_url = isset( $_POST['ticket_btn_url'] ) ? esc_url_raw( trim( wp_unslash( $_POST['ticket_btn_url'] ) ) ) : '';

		$raw_ticket_img = isset( $_POST['ticket_btn_img'] ) ? esc_url_raw( trim( wp_unslash( $_POST['ticket_btn_img'] ) ) ) : '';
		if ( empty( $raw_ticket_img ) ) {
			$raw_ticket_img = $defaults['ticket_btn_img'];
		}

		$raw_domains = isset( $_POST['ticket_domains'] ) ? sanitize_text_field( wp_unslash( $_POST['ticket_domains'] ) ) : $defaults['ticket_domains'];

		$weekly_on    = isset( $_POST['weekly_report_on'] ) ? 1 : 0;
		$weekly_email = isset( $_POST['weekly_report_email'] ) ? sanitize_email( wp_unslash( $_POST['weekly_report_email'] ) ) : '';
		$weekly_day   = isset( $_POST['weekly_report_day'] ) ? sanitize_key( wp_unslash( $_POST['weekly_report_day'] ) ) : 'monday';
		$valid_days   = array( 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' );
		if ( ! in_array( $weekly_day, $valid_days, true ) ) {
			$weekly_day = 'monday';
		}

		$d_text = isset( $_POST['date_text'] ) ? sanitize_hex_color( wp_unslash( $_POST['date_text'] ) ) : '';
		$c_text = isset( $_POST['city_text'] ) ? sanitize_hex_color( wp_unslash( $_POST['city_text'] ) ) : '';

		$post_dates  = isset( $_POST['palette_date'] ) ? (array) $_POST['palette_date'] : array();
		$post_cities = isset( $_POST['palette_city'] ) ? (array) $_POST['palette_city'] : array();
		$palettes    = array();

		for ( $i = 0; $i < 12; $i++ ) {
			$pd = isset( $post_dates[ $i ] ) ? sanitize_hex_color( wp_unslash( $post_dates[ $i ] ) ) : '';
			$pc = isset( $post_cities[ $i ] ) ? sanitize_hex_color( wp_unslash( $post_cities[ $i ] ) ) : '';

			$palettes[ $i ] = array(
				'date' => $pd ? $pd : $defaults['palettes'][ $i ]['date'],
				'city' => $pc ? $pc : $defaults['palettes'][ $i ]['city'],
			);
		}

		$new_styles = array(
			'border_radius'       => $radius,
			'badge_height'        => $height,
			'grid_gap'            => $gap,
			'card_min_w'          => $min_w,
			'max_items'           => $max_it,
			'date_text'           => $d_text ? $d_text : $defaults['date_text'],
			'city_text'           => $c_text ? $c_text : $defaults['city_text'],
			'redirect_target'     => $raw_redirect,
			'ticket_btn_url'      => $raw_ticket_url,
			'ticket_btn_img'      => $raw_ticket_img,
			'ticket_domains'      => $raw_domains,
			'weekly_report_on'    => $weekly_on,
			'weekly_report_email' => $weekly_email,
			'weekly_report_day'   => $weekly_day,
			'delete_on_uninst'    => $del_uninst,
			'palettes'            => $palettes,
		);

		update_option( KT_STYLES_OPT, $new_styles, false );
		kt_sync_weekly_cron_schedule( true );
		$message = '<div class="notice notice-success is-dismissible"><p>Düğme biçimleri ve ayarlar kaydedildi.</p></div>';
	}

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
		<h1>Konser Takvimi</h1>
		<p>
			Takvimi göstermek için <code>[konser_takvimi]</code>, ilçe sayfasında konser yoksa yönlendirmek ve sayfa istatistiklerini toplamak için <code>[konser_ilce_yonlendir]</code>, bilet düğmesi için <code>[konser_bilet_butonu]</code> kısa kodlarını kullanabilirsiniz.
		</p>
		<?php echo $message; ?>

		<nav class="nav-tab-wrapper" style="margin-top: 15px; max-width: 1060px;">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=konser-takvimi&tab=concerts' ) ); ?>" class="nav-tab <?php echo 'concerts' === $active_tab ? 'nav-tab-active' : ''; ?>">
				Konser Listesi & Arşiv
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=konser-takvimi&tab=stats' ) ); ?>" class="nav-tab <?php echo 'stats' === $active_tab ? 'nav-tab-active' : ''; ?>">
				İstatistikler & Grafik
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=konser-takvimi&tab=styles' ) ); ?>" class="nav-tab <?php echo 'styles' === $active_tab ? 'nav-tab-active' : ''; ?>">
				Düğme Biçimleri, Renkler & Ayarlar
			</a>
		</nav>

		<?php if ( 'concerts' === $active_tab ) : ?>

			<datalist id="kt-existing-posts-list">
				<?php foreach ( $existing_posts as $wp_post ) : ?>
					<?php
					if ( ! empty( $wp_post->post_name ) ) :
						$type_label = ( 'page' === $wp_post->post_type ) ? 'Sayfa' : 'Yazı';
						?>
						<option value="<?php echo esc_attr( $wp_post->post_name ); ?>">
							<?php echo esc_html( '[' . $type_label . '] ' . $wp_post->post_title ); ?>
						</option>
					<?php endif; ?>
				<?php endforeach; ?>
			</datalist>

			<h2 style="margin-top: 25px;">Yaklaşan Konserler (Düzenlenebilir)</h2>
			<p class="description">Aynı gün iki seans varsa <strong>2. Seans</strong> alanını doldurun; düğmede <code>12.00 ve 14.00</code> olarak gösterilir. Tek seans için 2. seansı boş bırakabilirsiniz.</p>

			<form method="post" action="">
				<?php wp_nonce_field( 'kt_conser_nonce_action', 'kt_conser_nonce' ); ?>

				<table class="widefat fixed striped" id="kt-concerts-table" style="max-width: 1060px; margin-top: 10px;">
					<thead>
						<tr>
							<th style="width: 145px;">Tarih</th>
							<th style="width: 95px;">1. Seans</th>
							<th style="width: 110px;">2. Seans (Ops.)</th>
							<th style="width: 185px;">Şehir / İlçe Adı</th>
							<th>Link Uzantısı (Otomatik: ilce-konseri / Mevcut Yazı-Sayfa)</th>
							<th style="width: 70px;">İşlem</th>
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
							<td style="vertical-align: top;"><input type="text" name="concert_city[]" value="<?php echo esc_attr( $event['city'] ); ?>" placeholder="Örn: Urla" required style="width: 100%;"></td>
							<td style="vertical-align: top;">
								<input type="text" class="kt-slug-input" name="concert_slug[]" list="kt-existing-posts-list" value="<?php echo esc_attr( $event['slug'] ); ?>" data-auto="0" placeholder="ilce-konseri veya ara..." autocomplete="off">
								<span class="kt-slug-status"></span>
							</td>
							<td style="vertical-align: top;"><button type="button" class="button remove-row" style="color: #b32d2e;">Sil</button></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<p style="margin-top: 15px;">
					<button type="button" class="button button-secondary" id="add-concert-row">+ Yeni Konser Ekle</button>
					<input type="submit" name="kt_save_concerts" class="button button-primary" value="Değişiklikleri Kaydet" style="margin-left: 10px;">
				</p>
			</form>

			<hr style="max-width: 1060px; margin: 35px 0 25px 0;">

			<div style="max-width: 1060px; background: #fff; border: 1px solid #c3c4c7; border-left: 4px solid #2271b1; border-radius: 4px; padding: 12px 16px; margin-bottom: 16px; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; box-sizing: border-box;">
				<div>
					<strong>Yedekle / Dışa Aktar (Export):</strong>
					<span class="description" style="margin-left: 6px;">Aktif (<?php echo intval( $active_count ); ?>), Arşiv (<?php echo intval( $archive_count ); ?>) veya Tüm kayıtları indirin.</span>
				</div>
				<form method="post" action="" style="display: flex; align-items: center; gap: 8px; margin: 0;">
					<?php wp_nonce_field( 'kt_export_nonce_action', 'kt_export_nonce' ); ?>
					<select name="export_scope" aria-label="Kapsam">
						<option value="all">Tümü (Aktif + Arşiv)</option>
						<option value="active">Sadece Aktif Konserler</option>
						<option value="archived">Sadece Arşivlenenler</option>
					</select>
					<select name="export_format" aria-label="Format">
						<option value="json">JSON (.json)</option>
						<option value="csv">Excel / CSV (.csv)</option>
					</select>
					<input type="submit" name="kt_export_action" class="button button-secondary" value="İndir">
				</form>
			</div>

			<details id="kt-archive-accordion" style="max-width: 1060px; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 12px 16px; box-sizing: border-box;">
				<summary style="cursor: pointer; font-size: 15px; font-weight: 600; color: #1d2327; user-select: none;">
					Arşivlenen Konserler (<?php echo intval( $archive_count ); ?> adet - Görmek için tıklayın)
				</summary>

				<div style="margin-top: 15px;">
					<p class="description" style="margin-bottom: 12px;">Tarihi ve son seans saati geçen konserler otomatik olarak buraya taşınır, düzenlemeye kapatılır ve sitedeki kısa kod alanında gizlenir.</p>

					<table class="widefat fixed striped" id="kt-archive-table" style="opacity: 0.85;">
						<thead>
							<tr>
								<th style="width: 145px;">Tarih</th>
								<th style="width: 95px;">1. Seans</th>
								<th style="width: 95px;">2. Seans</th>
								<th>Şehir / İlçe Adı</th>
								<th>Link Uzantısı (Slug)</th>
								<th style="width: 95px;">Durum</th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $archived_list ) ) : ?>
								<tr>
									<td colspan="6" style="text-align: center; color: #666;">Henüz arşivlenmiş konser bulunmuyor.</td>
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
									<td><span style="display: inline-block; padding: 3px 8px; background: #e2e8f0; color: #475569; border-radius: 4px; font-size: 12px; font-weight: 600;">Arşivlendi</span></td>
								</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>

					<div id="kt-archive-pagination" style="display: flex; justify-content: space-between; align-items: center; margin-top: 12px;"></div>
				</div>
			</details>

		<?php elseif ( 'stats' === $active_tab ) : ?>

			<?php
			$stats = get_option( KT_STATS_OPT, array() );
			if ( ! is_array( $stats ) ) {
				$stats = array();
			}

			$today_str = kt_get_istanbul_date_str( 0 );

			uasort(
				$stats,
				function ( $a, $b ) {
					$va = isset( $a['visitors'] ) ? intval( $a['visitors'] ) : ( isset( $a['views'] ) ? intval( $a['views'] ) : 0 );
					$vb = isset( $b['visitors'] ) ? intval( $b['visitors'] ) : ( isset( $b['views'] ) ? intval( $b['views'] ) : 0 );
					if ( $va === $vb ) {
						$ca = isset( $a['clicks'] ) ? intval( $a['clicks'] ) : 0;
						$cb = isset( $b['clicks'] ) ? intval( $b['clicks'] ) : 0;
						return $cb - $ca;
					}
					return $vb - $va;
				}
			);

			$today_visitors = 0;
			$total_visitors = 0;
			$total_views    = 0;
			$total_clicks   = 0;
			$total_seconds  = 0;
			$duration_count = 0;

			foreach ( $stats as $st ) {
				$total_views    += isset( $st['views'] ) ? intval( $st['views'] ) : 0;
				$total_visitors += isset( $st['visitors'] ) ? intval( $st['visitors'] ) : 0;
				$total_clicks   += isset( $st['clicks'] ) ? intval( $st['clicks'] ) : 0;
				$total_seconds  += isset( $st['total_seconds'] ) ? intval( $st['total_seconds'] ) : 0;
				$duration_count += isset( $st['duration_count'] ) ? intval( $st['duration_count'] ) : 0;
				if ( isset( $st['daily'][ $today_str ]['visitors'] ) ) {
					$today_visitors += intval( $st['daily'][ $today_str ]['visitors'] );
				}
			}

			$overall_ctr     = $total_views > 0 ? number_format( ( $total_clicks / $total_views ) * 100, 1, ',', '.' ) : '0,0';
			$overall_avg_sec = $duration_count > 0 ? round( $total_seconds / $duration_count ) : 0;

			// Son 14 gün için grafik veri setini hazırla
			$chart_days = array();
			for ( $d = 13; $d >= 0; $d-- ) {
				$d_str        = kt_get_istanbul_date_str( -$d );
				$chart_days[] = array(
					'date'  => $d_str,
					'label' => substr( $d_str, 8, 2 ) . '.' . substr( $d_str, 5, 2 ),
				);
			}

			$chart_series = array(
				'all' => array(
					'label'    => 'Tüm İlçeler (Genel Toplam)',
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

			<div style="max-width: 1060px; margin-top: 20px;">
				<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(195px, 1fr)); gap: 14px; margin-bottom: 20px;">
					<div style="background: #fff; border: 1px solid #c3c4c7; border-left: 4px solid #0284c7; border-radius: 4px; padding: 14px 16px;">
						<div style="font-size: 12px; color: #50575e; font-weight: 600;">Bugünkü Tekil Ziyaretçi</div>
						<div style="font-size: 24px; font-weight: 700; color: #1d2327; margin-top: 4px;"><?php echo number_format_i18n( $today_visitors ); ?></div>
					</div>
					<div style="background: #fff; border: 1px solid #c3c4c7; border-left: 4px solid #2271b1; border-radius: 4px; padding: 14px 16px;">
						<div style="font-size: 12px; color: #50575e; font-weight: 600;">Toplam Ziyaretçi / Gösterim</div>
						<div style="font-size: 24px; font-weight: 700; color: #1d2327; margin-top: 4px;">
							<?php echo number_format_i18n( $total_visitors ); ?> <span style="font-size: 14px; font-weight: 500; color: #64748b;">/ <?php echo number_format_i18n( $total_views ); ?></span>
						</div>
					</div>
					<div style="background: #fff; border: 1px solid #c3c4c7; border-left: 4px solid #16a34a; border-radius: 4px; padding: 14px 16px;">
						<div style="font-size: 12px; color: #50575e; font-weight: 600;">Bilet Tıklaması (CTR)</div>
						<div style="font-size: 24px; font-weight: 700; color: #1d2327; margin-top: 4px;">
							<?php echo number_format_i18n( $total_clicks ); ?> <span style="font-size: 14px; font-weight: 600; color: #16a34a;">(%<?php echo esc_html( $overall_ctr ); ?>)</span>
						</div>
					</div>
					<div style="background: #fff; border: 1px solid #c3c4c7; border-left: 4px solid #9333ea; border-radius: 4px; padding: 14px 16px;">
						<div style="font-size: 12px; color: #50575e; font-weight: 600;">Ort. Sayfada Kalma Süresi</div>
						<div style="font-size: 24px; font-weight: 700; color: #1d2327; margin-top: 4px;"><?php echo esc_html( kt_format_duration_human( $overall_avg_sec ) ); ?></div>
					</div>
				</div>

				<!-- Son 14 Günlük Ziyaretçi & Bilet Tıklama Grafiği -->
				<div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 16px 20px; margin-bottom: 20px;">
					<div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 12px;">
						<div>
							<h2 style="margin: 0; font-size: 16px;">Son 14 Günlük Ziyaretçi ve Bilet Tıklama Grafiği</h2>
							<span class="description">Günlük tekil ziyaretçi ve bilet düğmesi tıklama eğilimi (İstanbul saat dilimi).</span>
						</div>
						<div style="display: flex; align-items: center; gap: 14px;">
							<div style="display: flex; align-items: center; gap: 12px; font-size: 12px;">
								<span style="display: inline-flex; align-items: center; gap: 5px;"><span style="width: 12px; height: 12px; background: #2271b1; border-radius: 2px; display: inline-block;"></span> Günlük Ziyaretçi</span>
								<span style="display: inline-flex; align-items: center; gap: 5px;"><span style="width: 12px; height: 12px; background: #16a34a; border-radius: 2px; display: inline-block;"></span> Bilet Tıklaması</span>
							</div>
							<select id="kt-chart-district-select" aria-label="İlçe Filtresi">
								<option value="all">Tüm İlçeler (Toplam)</option>
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

				<div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 12px 16px; margin-bottom: 16px; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px;">
					<span class="description">
						Yönetici/editör girişleri sayımlara dahil edilmez. Günlük kırılımlar son 90 gün boyunca saklanır.
					</span>
					<form method="post" action="" style="display: flex; gap: 8px; margin: 0;">
						<?php wp_nonce_field( 'kt_stats_nonce_action', 'kt_stats_nonce' ); ?>
						<input type="submit" name="kt_export_stats_action" class="button button-secondary" value="CSV Olarak İndir" <?php disabled( empty( $stats ) ); ?>>
						<input type="submit" name="kt_reset_stats" class="button" style="color: #b32d2e;" value="Tüm İstatistikleri Sıfırla" <?php disabled( empty( $stats ) ); ?> onclick="return confirm('Tüm ilçe ziyaret ve tıklama verileri kalıcı olarak sıfırlanacak. Emin misiniz?');">
					</form>
				</div>

				<form method="post" action="">
					<?php wp_nonce_field( 'kt_stats_nonce_action', 'kt_stats_nonce' ); ?>
					<table class="widefat fixed striped">
						<thead>
							<tr>
								<th style="width: 140px;">İlçe Anahtarı</th>
								<th>Sayfa Başlığı</th>
								<th style="width: 105px;">Bugün (Tekil)</th>
								<th style="width: 115px;">Toplam Ziyaretçi</th>
								<th style="width: 105px;">Gösterim</th>
								<th style="width: 110px;">Bilet Tıklaması</th>
								<th style="width: 90px;">CTR</th>
								<th style="width: 115px;">Ort. Süre</th>
								<th style="width: 65px;">İşlem</th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $stats ) ) : ?>
								<tr>
									<td colspan="9" style="text-align: center; color: #666; padding: 20px;">Henüz kaydedilmiş ziyaret veya bilet tıklama verisi bulunmuyor.</td>
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
									$ctr     = $v > 0 ? number_format( ( $c / $v ) * 100, 1, ',', '.' ) : '0,0';
									?>
									<tr>
										<td><code><?php echo esc_html( $key ); ?></code></td>
										<td>
											<strong><?php echo esc_html( isset( $row['label'] ) ? $row['label'] : $key ); ?></strong>
											<div style="font-size: 11px; color: #64748b;">Son: <?php echo esc_html( isset( $row['updated'] ) ? $row['updated'] : '-' ); ?></div>
										</td>
										<td><strong><?php echo number_format_i18n( $t_vis ); ?></strong></td>
										<td><?php echo number_format_i18n( $vis ); ?></td>
										<td><?php echo number_format_i18n( $v ); ?></td>
										<td style="color: #15803d; font-weight: 600;"><?php echo number_format_i18n( $c ); ?></td>
										<td><span style="display: inline-block; padding: 2px 7px; background: #f1f5f9; border-radius: 4px; font-weight: 600;">%<?php echo esc_html( $ctr ); ?></span></td>
										<td><?php echo esc_html( kt_format_duration_human( $avg_sec ) ); ?></td>
										<td>
											<button type="submit" name="kt_delete_stat_key" value="<?php echo esc_attr( $key ); ?>" class="button button-small" style="color: #b32d2e;" onclick="return confirm('Bu ilçeye ait istatistik kaydını silmek istediğinize emin misiniz?');">Sil</button>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</form>
			</div>

		<?php else : ?>

			<form method="post" action="" style="max-width: 1060px; margin-top: 20px;">
				<?php wp_nonce_field( 'kt_styles_nonce_action', 'kt_styles_nonce' ); ?>

				<div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 16px 20px; margin-bottom: 20px;">
					<h2 style="margin-top: 0;">Canlı Düğme Önizlemesi</h2>
					<div id="kt-live-preview-box" style="display: flex; flex-wrap: wrap; gap: <?php echo intval( $styles['grid_gap'] ); ?>px; padding: 15px; background: #f8fafc; border-radius: 8px; border: 1px dashed #cbd5e1;">
						<?php
						$sample_cities = array( 'İzmir', 'İstanbul', 'Ankara', 'Bursa' );
						$sample_times  = array( '12.00 ve 14.00', '15.00', '13.00 ve 15.00', '14.00' );
						for ( $p = 0; $p < 4; $p++ ) :
							$d_bg = $styles['palettes'][ $p ]['date'];
							$c_bg = $styles['palettes'][ $p ]['city'];
							?>
							<div class="preview-badge" data-index="<?php echo $p; ?>" style="display: flex; width: <?php echo intval( $styles['card_min_w'] ); ?>px; height: <?php echo intval( $styles['badge_height'] ); ?>px; border-radius: <?php echo intval( $styles['border_radius'] ); ?>px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.06); font-family: system-ui, sans-serif;">
								<div class="preview-date" style="width: 44%; background-color: <?php echo esc_attr( $d_bg ); ?>; color: <?php echo esc_attr( $styles['date_text'] ); ?>; display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; line-height: 1.2;">
									<strong style="font-size: 15px;">24 Ekim</strong>
									<span style="font-size: 13px;">Cumartesi</span>
									<strong style="font-size: 12px;"><?php echo esc_html( $sample_times[ $p ] ); ?></strong>
								</div>
								<div class="preview-city" style="width: 56%; background-color: <?php echo esc_attr( $c_bg ); ?>; color: <?php echo esc_attr( $styles['city_text'] ); ?>; display: flex; justify-content: center; align-items: center; font-size: 20px; font-weight: 700;">
									<?php echo esc_html( $sample_cities[ $p ] ); ?>
								</div>
							</div>
						<?php endfor; ?>
					</div>
				</div>

				<div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 16px 20px; margin-bottom: 20px;">
					<h2 style="margin-top: 0;">Boyut, Köşe Yuvarlaklığı ve Izgara Ayarları</h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="border_radius">Köşe Yuvarlaklığı (Border Radius)</label></th>
							<td>
								<input type="range" id="border_radius" name="border_radius" min="0" max="45" value="<?php echo intval( $styles['border_radius'] ); ?>">
								<span id="border_radius_val" style="font-weight: 600; margin-left: 8px;"><?php echo intval( $styles['border_radius'] ); ?> px</span>
								<p class="description">0 px keskin dikdörtgen, 20 px yumuşak köşe, 45 px hap (pill) görünümü verir.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="badge_height">Düğme Yüksekliği</label></th>
							<td>
								<input type="range" id="badge_height" name="badge_height" min="65" max="140" value="<?php echo intval( $styles['badge_height'] ); ?>">
								<span id="badge_height_val" style="font-weight: 600; margin-left: 8px;"><?php echo intval( $styles['badge_height'] ); ?> px</span>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="card_min_w">Minimum Düğme Genişliği</label></th>
							<td>
								<input type="range" id="card_min_w" name="card_min_w" min="200" max="400" step="5" value="<?php echo intval( $styles['card_min_w'] ); ?>">
								<span id="card_min_w_val" style="font-weight: 600; margin-left: 8px;"><?php echo intval( $styles['card_min_w'] ); ?> px</span>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="grid_gap">Düğmeler Arası Boşluk (Gap)</label></th>
							<td>
								<input type="range" id="grid_gap" name="grid_gap" min="6" max="45" value="<?php echo intval( $styles['grid_gap'] ); ?>">
								<span id="grid_gap_val" style="font-weight: 600; margin-left: 8px;"><?php echo intval( $styles['grid_gap'] ); ?> px</span>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="max_items">Sayfada Gösterilecek Maksimum Konser</label></th>
							<td>
								<input type="number" id="max_items" name="max_items" min="1" max="100" value="<?php echo intval( $styles['max_items'] ); ?>" style="width: 80px;">
								<span class="description" style="margin-left: 8px;">Varsayılan: 16 konser</span>
							</td>
						</tr>
						<tr>
							<th scope="row">Yazı Renkleri</th>
							<td>
								<label style="margin-right: 20px;">
									Tarih Bölümü Yazı Rengi:
									<input type="color" id="date_text" name="date_text" value="<?php echo esc_attr( $styles['date_text'] ); ?>">
								</label>
								<label>
									Şehir Bölümü Yazı Rengi:
									<input type="color" id="city_text" name="city_text" value="<?php echo esc_attr( $styles['city_text'] ); ?>">
								</label>
							</td>
						</tr>
					</table>
				</div>

				<div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 16px 20px; margin-bottom: 20px;">
					<h2 style="margin-top: 0;">12'li Düğme Renk Paleti Döngüsü</h2>
					<p class="description" style="margin-bottom: 15px;">Konser rozetleri sırasıyla bu 12 renk çiftini kullanır. Sol taraftaki koyu rengi seçtiğinizde sağ taraf için otomatik uyumlu pastel ton önerilir; dilerseniz sağ rengi de bağımsız seçebilirsiniz.</p>

					<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 12px;">
						<?php for ( $i = 0; $i < 12; $i++ ) : ?>
							<div class="palette-item-card" data-palette-idx="<?php echo $i; ?>" style="border: 1px solid #dcdcde; border-radius: 6px; padding: 10px 12px; display: flex; align-items: center; justify-content: space-between; background: #fafafa;">
								<strong style="min-width: 65px;">Renk #<?php echo $i + 1; ?></strong>
								<label style="display: flex; align-items: center; gap: 4px; font-size: 12px;">
									Tarih:
									<input type="color" class="palette-date-input" name="palette_date[<?php echo $i; ?>]" value="<?php echo esc_attr( $styles['palettes'][ $i ]['date'] ); ?>">
								</label>
								<label style="display: flex; align-items: center; gap: 4px; font-size: 12px;">
									Şehir:
									<input type="color" class="palette-city-input" name="palette_city[<?php echo $i; ?>]" value="<?php echo esc_attr( $styles['palettes'][ $i ]['city'] ); ?>">
								</label>
							</div>
						<?php endfor; ?>
					</div>
				</div>

				<div style="background: #fff; border: 1px solid #c3c4c7; border-left: 4px solid #2271b1; border-radius: 4px; padding: 16px 20px; margin-bottom: 20px;">
					<h2 style="margin-top: 0;">İlçe Sayfası Yönlendirme, Bilet Düğmesi & Otomatik Takip Ayarları</h2>
					<table class="form-table" role="presentation" style="margin-top: 0;">
						<tr>
							<th scope="row"><label for="redirect_target">Varsayılan Yönlendirme Sayfası</label></th>
							<td>
								<input type="text" id="redirect_target" name="redirect_target" value="<?php echo esc_attr( $styles['redirect_target'] ); ?>" class="regular-text" placeholder="/konserler">
								<p class="description">
									İlçe sayfasında aktif konser bulunmadığında ziyaretçilerin yönlendirileceği sayfa yolu (örn. <code>/konserler</code>) veya tam URL.
								</p>
								<p class="description" style="margin-top: 6px; color: #1d2327;">
									<strong>Önemli Not:</strong> <code>[konser_ilce_yonlendir]</code> kısa kodu parametresiz kullanıldığında kontrolü <strong>sayfanın URL uzantısına (slug)</strong> göre yapar. Otomatik algılama için sayfa URL'sinde ilçe adının mutlaka yer alması gerekir (örneğin <code>/urla-konseri</code> veya <code>/urla-konser</code>). URL'de ilçe adı geçmiyorsa kısa kodu <code>[konser_ilce_yonlendir ilce="Urla"]</code> biçiminde kullanabilirsiniz.
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="ticket_btn_url">Varsayılan Bilet Satış URL'si</label></th>
							<td>
								<input type="url" id="ticket_btn_url" name="ticket_btn_url" value="<?php echo esc_url( $styles['ticket_btn_url'] ); ?>" class="large-text" placeholder="https://biletinial.com/tr-tr/tiyatro/subadap-cocuk-konseri">
								<p class="description">
									Buraya bir varsayılan bilet bağlantısı girerseniz sayfalarda parametre vermeden doğrudan <code>[konser_bilet_butonu]</code> yazabilirsiniz. Belirli bir ilçe için farklı link kullanmak isterseniz <code>[konser_bilet_butonu url="https://..."]</code> ile ezebilirsiniz.
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="ticket_btn_img">Varsayılan Bilet Düğmesi Görseli</label></th>
							<td>
								<div style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center;">
									<input type="url" id="ticket_btn_img" name="ticket_btn_img" value="<?php echo esc_url( $styles['ticket_btn_img'] ); ?>" class="regular-text" style="min-width: 360px;">
									<button type="button" class="button button-secondary" id="kt-select-ticket-img">Ortam Kütüphanesinden Seç</button>
								</div>
								<div id="kt-ticket-img-preview" style="margin-top: 10px;">
									<?php if ( ! empty( $styles['ticket_btn_img'] ) ) : ?>
										<img src="<?php echo esc_url( $styles['ticket_btn_img'] ); ?>" alt="Bilet Düğmesi Önizleme" style="max-width: 360px; height: auto; border: 1px solid #dcdcde; border-radius: 4px; padding: 4px; background: #f8fafc;">
									<?php endif; ?>
								</div>
								<p class="description" style="margin-top: 6px;">
									Gerekirse tekil sayfalarda <code>resim="..."</code> veya <code>ilce="..."</code> parametresi eklenebilir.
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="ticket_domains">Otomatik Takip Edilecek Bilet Alan Adları</label></th>
							<td>
								<input type="text" id="ticket_domains" name="ticket_domains" value="<?php echo esc_attr( $styles['ticket_domains'] ); ?>" class="large-text" placeholder="biletinial.com, biletix.com, passo.com.tr, bubilet.com.tr">
								<p class="description">
									Sayfada <code>[konser_bilet_butonu]</code> kısa kodu <strong>olmasa bile</strong>, <code>[konser_ilce_yonlendir]</code> bulunan sayfalarda bu alan adlarına giden bağlantılara veya bilet görseli içeren linklere yapılan tıklamalar otomatik olarak ilgili ilçeye kaydedilir. Virgülle ayırarak alan adı ekleyebilirsiniz.
								</p>
							</td>
						</tr>
					</table>
				</div>

				<div style="background: #fff; border: 1px solid #c3c4c7; border-left: 4px solid #16a34a; border-radius: 4px; padding: 16px 20px; margin-bottom: 20px;">
					<h2 style="margin-top: 0;">Haftalık İstatistik E-Posta Raporu</h2>
					<table class="form-table" role="presentation" style="margin-top: 0;">
						<tr>
							<th scope="row">Otomatik Haftalık Rapor</th>
							<td>
								<label>
									<input type="checkbox" name="weekly_report_on" value="1" <?php checked( ! empty( $styles['weekly_report_on'] ) ); ?>>
									Son 7 günün ilçe ziyaretçi, bilet tıklaması ve kalma süresi özetini her hafta e-posta ile gönder.
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="weekly_report_email">Alıcı E-Posta Adresi</label></th>
							<td>
								<input type="email" id="weekly_report_email" name="weekly_report_email" value="<?php echo esc_attr( $styles['weekly_report_email'] ); ?>" class="regular-text" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
								<p class="description">Boş bırakılırsa WordPress genel yönetici e-posta adresine (<code><?php echo esc_html( get_option( 'admin_email' ) ); ?></code>) gönderilir.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="weekly_report_day">Gönderim Günü (Saat 09:00)</label></th>
							<td>
								<?php
								$days_tr = array(
									'monday'    => 'Pazartesi',
									'tuesday'   => 'Salı',
									'wednesday' => 'Çarşamba',
									'thursday'  => 'Perşembe',
									'friday'    => 'Cuma',
									'saturday'  => 'Cumartesi',
									'sunday'    => 'Pazar',
								);
								?>
								<select id="weekly_report_day" name="weekly_report_day">
									<?php foreach ( $days_tr as $d_val => $d_label ) : ?>
										<option value="<?php echo esc_attr( $d_val ); ?>" <?php selected( $styles['weekly_report_day'], $d_val ); ?>><?php echo esc_html( $d_label ); ?></option>
									<?php endforeach; ?>
								</select>
								<input type="submit" name="kt_send_test_report" class="button button-secondary" value="Şimdi Örnek Rapor E-Postası Gönder" style="margin-left: 10px;">
							</td>
						</tr>
					</table>
				</div>

				<div style="background: #fff; border: 1px solid #c3c4c7; border-left: 4px solid #d63638; border-radius: 4px; padding: 16px 20px; margin-bottom: 20px;">
					<h2 style="margin-top: 0;">Kaldırma (Uninstall) Veritabanı Davranışı</h2>
					<label style="display: flex; align-items: flex-start; gap: 8px; cursor: pointer;">
						<input type="checkbox" name="delete_on_uninst" value="1" <?php checked( ! empty( $styles['delete_on_uninst'] ) ); ?> style="margin-top: 3px;">
						<span>
							<strong>Eklenti WordPress panelinden silindiğinde veritabanındaki tüm konser kayıtlarını, istatistikleri ve görünüm ayarlarını da sil.</strong><br>
							<span class="description">Güvenlik amacıyla varsayılan olarak kapalıdır (işaretsizdir). İşaretsiz bırakıldığında eklentiyi silip yeniden yükleseniz bile konser arşiviniz ve istatistikleriniz korunur.</span>
						</span>
					</label>
				</div>

				<p>
					<input type="submit" name="kt_save_styles" class="button button-primary" value="Ayarları Kaydet">
					<input type="submit" name="kt_reset_styles" class="button button-secondary" value="Varsayılan Renk ve Boyutlara Sıfırla" style="margin-left: 10px;" onclick="return confirm('Tüm düğme biçimi ve renk ayarları varsayılana sıfırlanacak. Emin misiniz?');">
				</p>
			</form>

		<?php endif; ?>
	</div>
	<?php
}
