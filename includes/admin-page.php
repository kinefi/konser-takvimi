<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', 'subadap_register_concert_menu' );
function subadap_register_concert_menu() {
	add_menu_page(
		'Konser Takvimi',
		'Konser Takvimi',
		'manage_options',
		'subadap-konseriler',
		'subadap_konser_admin_page',
		'dashicons-calendar-alt',
		25
	);
}

add_action( 'admin_enqueue_scripts', 'subadap_enqueue_admin_assets' );
function subadap_enqueue_admin_assets( $hook ) {
	if ( 'toplevel_page_subadap-konseriler' !== $hook ) {
		return;
	}
	wp_enqueue_script(
		'subadap-admin-js',
		SUBADAP_KONSER_URL . 'assets/js/admin.js',
		array(),
		SUBADAP_KONSER_VER,
		true
	);
}

// JSON ve CSV Dışa Aktarma (Export) İşleyicisi
add_action( 'admin_init', 'subadap_handle_concert_export' );
function subadap_handle_concert_export() {
	if ( ! isset( $_POST['subadap_export_action'] ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Yetkisiz erişim.' );
	}

	check_admin_referer( 'subadap_export_nonce_action', 'subadap_export_nonce' );

	$format = isset( $_POST['export_format'] ) ? sanitize_key( $_POST['export_format'] ) : 'json';
	$scope  = isset( $_POST['export_scope'] ) ? sanitize_key( $_POST['export_scope'] ) : 'all';

	if ( ! in_array( $format, array( 'json', 'csv' ), true ) ) {
		$format = 'json';
	}
	if ( ! in_array( $scope, array( 'all', 'active', 'archived' ), true ) ) {
		$scope = 'all';
	}

	$now_ts     = subadap_get_istanbul_timestamp();
	$all_events = get_option( SUBADAP_KONSER_OPT, array() );
	if ( ! is_array( $all_events ) ) {
		$all_events = array();
	}

	$filtered = array();
	foreach ( $all_events as $ev ) {
		$ev_ts       = isset( $ev['datetime'] ) ? strtotime( $ev['datetime'] ) : false;
		$is_archived = ( false !== $ev_ts && $ev_ts < $now_ts );

		if ( 'active' === $scope && $is_archived ) {
			continue;
		}
		if ( 'archived' === $scope && ! $is_archived ) {
			continue;
		}

		$ev['status'] = $is_archived ? 'Arşivlendi' : 'Aktif';
		$filtered[]   = $ev;
	}

	$date_stamp = gmdate( 'Y-m-d', $now_ts + 10800 );
	$filename   = "subadap-konseriler-{$scope}-{$date_stamp}.{$format}";

	nocache_headers();

	if ( 'csv' === $format ) {
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$output = fopen( 'php://output', 'w' );
		fwrite( $output, "\xEF\xBB\xBF" );
		fputcsv( $output, array( 'Tarih', 'Saat', 'Şehir / İlçe', 'Slug', 'Durum', 'ISO Tarih' ), ';' );

		foreach ( $filtered as $row ) {
			$dt_parts  = explode( 'T', $row['datetime'] );
			$d_val     = isset( $dt_parts[0] ) ? $dt_parts[0] : '';
			$t_val     = isset( $dt_parts[1] ) ? substr( $dt_parts[1], 0, 5 ) : '';
			$safe_city = preg_replace( '/^[=+\-@]/', "'$0", $row['city'] );
			$safe_slug = preg_replace( '/^[=+\-@]/', "'$0", $row['slug'] );

			fputcsv(
				$output,
				array(
					$d_val,
					$t_val,
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

function subadap_konser_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'concerts';
	if ( ! in_array( $active_tab, array( 'concerts', 'styles' ), true ) ) {
		$active_tab = 'concerts';
	}

	$message         = '';
	$now_ts          = subadap_get_istanbul_timestamp();
	$existing_events = get_option( SUBADAP_KONSER_OPT, array() );

	if ( ! is_array( $existing_events ) ) {
		$existing_events = array();
	}

	// 1. Sekme: Konserleri Kaydetme
	if ( isset( $_POST['subadap_save_concerts'] ) && check_admin_referer( 'subadap_conser_nonce_action', 'subadap_conser_nonce' ) ) {
		$archived_events = array();

		foreach ( $existing_events as $ev ) {
			$ev_ts = isset( $ev['datetime'] ) ? strtotime( $ev['datetime'] ) : false;
			if ( false !== $ev_ts && $ev_ts < $now_ts ) {
				$archived_events[] = $ev;
			}
		}

		$dates         = isset( $_POST['concert_date'] ) ? (array) $_POST['concert_date'] : array();
		$times         = isset( $_POST['concert_time'] ) ? (array) $_POST['concert_time'] : array();
		$cities        = isset( $_POST['concert_city'] ) ? (array) $_POST['concert_city'] : array();
		$slugs         = isset( $_POST['concert_slug'] ) ? (array) $_POST['concert_slug'] : array();
		$active_events = array();
		$total         = count( $cities );

		for ( $i = 0; $i < $total; $i++ ) {
			$city = sanitize_text_field( wp_unslash( $cities[ $i ] ) );
			$date = isset( $dates[ $i ] ) ? sanitize_text_field( wp_unslash( $dates[ $i ] ) ) : '';
			$time = isset( $times[ $i ] ) ? sanitize_text_field( wp_unslash( $times[ $i ] ) ) : '';
			$slug = isset( $slugs[ $i ] ) ? sanitize_title( wp_unslash( $slugs[ $i ] ) ) : '';

			if ( empty( $city ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
				continue;
			}

			if ( ! preg_match( '/^\d{2}:\d{2}$/', $time ) ) {
				$time = '15:00';
			}

			if ( empty( $slug ) ) {
				$slug = sanitize_title( $city . '-konseri' );
			}

			$active_events[] = array(
				'datetime' => $date . 'T' . $time . ':00+03:00',
				'city'     => $city,
				'slug'     => $slug,
			);
		}

		$all_events = array_merge( $archived_events, $active_events );
		usort( $all_events, 'subadap_sort_events_asc' );

		update_option( SUBADAP_KONSER_OPT, $all_events, false );
		$existing_events = $all_events;
		$message         = '<div class="notice notice-success is-dismissible"><p>Konser listesi veritabanına kaydedildi.</p></div>';
	}

	// 2. Sekme: Düğme Biçimleri & Renk Ayarlarını Kaydetme veya Sıfırlama
	if ( isset( $_POST['subadap_reset_styles'] ) && check_admin_referer( 'subadap_styles_nonce_action', 'subadap_styles_nonce' ) ) {
		delete_option( SUBADAP_STYLE_OPT );
		$message = '<div class="notice notice-info is-dismissible"><p>Düğme biçimleri ve renk paletleri varsayılan ayarlara döndürüldü.</p></div>';
	} elseif ( isset( $_POST['subadap_save_styles'] ) && check_admin_referer( 'subadap_styles_nonce_action', 'subadap_styles_nonce' ) ) {
		$defaults = subadap_get_default_style_settings();

		$radius     = isset( $_POST['border_radius'] ) ? max( 0, min( 50, intval( $_POST['border_radius'] ) ) ) : 20;
		$height     = isset( $_POST['badge_height'] ) ? max( 60, min( 160, intval( $_POST['badge_height'] ) ) ) : 90;
		$gap        = isset( $_POST['grid_gap'] ) ? max( 4, min( 60, intval( $_POST['grid_gap'] ) ) ) : 20;
		$min_w      = isset( $_POST['card_min_w'] ) ? max( 180, min( 450, intval( $_POST['card_min_w'] ) ) ) : 250;
		$max_it     = isset( $_POST['max_items'] ) ? max( 1, min( 100, intval( $_POST['max_items'] ) ) ) : 16;
		$del_uninst = isset( $_POST['delete_on_uninst'] ) ? 1 : 0;

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
			'border_radius'    => $radius,
			'badge_height'     => $height,
			'grid_gap'         => $gap,
			'card_min_w'       => $min_w,
			'max_items'        => $max_it,
			'date_text'        => $d_text ? $d_text : $defaults['date_text'],
			'city_text'        => $c_text ? $c_text : $defaults['city_text'],
			'delete_on_uninst' => $del_uninst,
			'palettes'         => $palettes,
		);

		update_option( SUBADAP_STYLE_OPT, $new_styles, false );
		$message = '<div class="notice notice-success is-dismissible"><p>Düğme biçimleri ve ayarlar kaydedildi.</p></div>';
	}

	$upcoming_list = array();
	$archived_list = array();

	foreach ( $existing_events as $event ) {
		$ev_ts = isset( $event['datetime'] ) ? strtotime( $event['datetime'] ) : false;
		if ( false !== $ev_ts && $ev_ts < $now_ts ) {
			$archived_list[] = $event;
		} else {
			$upcoming_list[] = $event;
		}
	}

	usort( $archived_list, 'subadap_sort_events_desc' );
	$archive_count = count( $archived_list );
	$active_count  = count( $upcoming_list );
	$styles        = subadap_get_style_settings();

	// Mevcut yayımlanmış yazı ve sayfaların slug ve başlıklarını çek
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
		<h1>Şubadap Konser Takvimi</h1>
		<p>Sayfada göstermek için <code>[subadap_konserler]</code> kısa kodunu kullanabilirsiniz.</p>
		<?php echo $message; ?>

		<style>
			.subadap-slug-input {
				width: 100%;
				transition: border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
			}
			.subadap-slug-input.is-valid-page {
				border: 1.5px solid #16a34a !important;
				background-color: #f0fdf4 !important;
				box-shadow: 0 0 0 1px rgba(22, 163, 74, 0.15);
			}
			.subadap-slug-input.is-missing-page {
				border: 1.5px solid #dc2626 !important;
				background-color: #fef2f2 !important;
				box-shadow: 0 0 0 1px rgba(220, 38, 38, 0.15);
			}
			.subadap-slug-status {
				display: block;
				font-size: 11px;
				margin-top: 3px;
				line-height: 1.3;
				font-weight: 600;
			}
			.subadap-slug-status.is-valid-page {
				color: #15803d;
			}
			.subadap-slug-status.is-missing-page {
				color: #b91c1c;
			}
		</style>

		<!-- Sekme Menüsü -->
		<nav class="nav-tab-wrapper" style="margin-top: 15px; max-width: 950px;">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=subadap-konseriler&tab=concerts' ) ); ?>" class="nav-tab <?php echo 'concerts' === $active_tab ? 'nav-tab-active' : ''; ?>">
				Konser Listesi & Arşiv
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=subadap-konseriler&tab=styles' ) ); ?>" class="nav-tab <?php echo 'styles' === $active_tab ? 'nav-tab-active' : ''; ?>">
				Düğme Biçimleri, Renkler & Ayarlar
			</a>
		</nav>

		<?php if ( 'concerts' === $active_tab ) : ?>

			<!-- Mevcut WordPress Yazı ve Sayfaları İçin Filtrelenebilir Seçim Listesi -->
			<datalist id="subadap-existing-posts-list">
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
			<p class="description">İlçe/şehir yazıldığında link uzantısı otomatik olarak <code>ilce-konseri</code> biçiminde oluşturulur. İlgili uzantıda yayımlanmış bir sayfa veya yazı varsa kutu <span style="color:#15803d;font-weight:600;">yeşil</span>, yoksa <span style="color:#b91c1c;font-weight:600;">kırmızı</span> görünür.</p>

			<form method="post" action="">
				<?php wp_nonce_field( 'subadap_conser_nonce_action', 'subadap_conser_nonce' ); ?>

				<table class="widefat fixed striped" id="subadap-concerts-table" style="max-width: 950px; margin-top: 10px;">
					<thead>
						<tr>
							<th style="width: 150px;">Tarih</th>
							<th style="width: 100px;">Saat</th>
							<th style="width: 200px;">Şehir / İlçe Adı</th>
							<th>Link Uzantısı (Otomatik: ilce-konseri / Mevcut Yazı-Sayfa)</th>
							<th style="width: 75px;">İşlem</th>
						</tr>
					</thead>
					<tbody>
						<?php
						foreach ( $upcoming_list as $event ) :
							$dt_parts = explode( 'T', $event['datetime'] );
							$d_val    = isset( $dt_parts[0] ) ? $dt_parts[0] : '';
							$t_val    = isset( $dt_parts[1] ) ? substr( $dt_parts[1], 0, 5 ) : '15:00';
							?>
						<tr>
							<td style="vertical-align: top;"><input type="date" name="concert_date[]" value="<?php echo esc_attr( $d_val ); ?>" required style="width: 100%;"></td>
							<td style="vertical-align: top;"><input type="time" name="concert_time[]" value="<?php echo esc_attr( $t_val ); ?>" required style="width: 100%;"></td>
							<td style="vertical-align: top;"><input type="text" name="concert_city[]" value="<?php echo esc_attr( $event['city'] ); ?>" placeholder="Örn: Urla" required style="width: 100%;"></td>
							<td style="vertical-align: top;">
								<input type="text" class="subadap-slug-input" name="concert_slug[]" list="subadap-existing-posts-list" value="<?php echo esc_attr( $event['slug'] ); ?>" data-auto="0" placeholder="ilce-konseri veya ara..." autocomplete="off">
								<span class="subadap-slug-status"></span>
							</td>
							<td style="vertical-align: top;"><button type="button" class="button remove-row" style="color: #b32d2e;">Sil</button></td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<p style="margin-top: 15px;">
					<button type="button" class="button button-secondary" id="add-concert-row">+ Yeni Konser Ekle</button>
					<input type="submit" name="subadap_save_concerts" class="button button-primary" value="Değişiklikleri Kaydet" style="margin-left: 10px;">
				</p>
			</form>

			<hr style="max-width: 950px; margin: 35px 0 25px 0;">

			<!-- Yedekle / Dışa Aktar Kutusu (Arşiv panelinin hemen üstünde) -->
			<div style="max-width: 950px; background: #fff; border: 1px solid #c3c4c7; border-left: 4px solid #2271b1; border-radius: 4px; padding: 12px 16px; margin-bottom: 16px; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; box-sizing: border-box;">
				<div>
					<strong>Yedekle / Dışa Aktar (Export):</strong>
					<span class="description" style="margin-left: 6px;">Aktif (<?php echo intval( $active_count ); ?>), Arşiv (<?php echo intval( $archive_count ); ?>) veya Tüm kayıtları indirin.</span>
				</div>
				<form method="post" action="" style="display: flex; align-items: center; gap: 8px; margin: 0;">
					<?php wp_nonce_field( 'subadap_export_nonce_action', 'subadap_export_nonce' ); ?>
					<select name="export_scope" aria-label="Kapsam">
						<option value="all">Tümü (Aktif + Arşiv)</option>
						<option value="active">Sadece Aktif Konserler</option>
						<option value="archived">Sadece Arşivlenenler</option>
					</select>
					<select name="export_format" aria-label="Format">
						<option value="json">JSON (.json)</option>
						<option value="csv">Excel / CSV (.csv)</option>
					</select>
					<input type="submit" name="subadap_export_action" class="button button-secondary" value="İndir">
				</form>
			</div>

			<!-- Açılır-Kapanır Arşiv Paneli -->
			<details id="subadap-archive-accordion" style="max-width: 950px; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 12px 16px; box-sizing: border-box;">
				<summary style="cursor: pointer; font-size: 15px; font-weight: 600; color: #1d2327; user-select: none;">
					Arşivlenen Konserler (<?php echo intval( $archive_count ); ?> adet - Görmek için tıklayın)
				</summary>

				<div style="margin-top: 15px;">
					<p class="description" style="margin-bottom: 12px;">Tarihi geçen konserler otomatik olarak buraya taşınır, düzenlemeye kapatılır ve sitedeki kısa kod alanında gizlenir.</p>

					<table class="widefat fixed striped" id="subadap-archive-table" style="opacity: 0.85;">
						<thead>
							<tr>
								<th style="width: 150px;">Tarih</th>
								<th style="width: 100px;">Saat</th>
								<th>Şehir / İlçe Adı</th>
								<th>Link Uzantısı (Slug)</th>
								<th style="width: 95px;">Durum</th>
							</tr>
						</thead>
						<tbody>
							<?php if ( empty( $archived_list ) ) : ?>
								<tr>
									<td colspan="5" style="text-align: center; color: #666;">Henüz arşivlenmiş konser bulunmuyor.</td>
								</tr>
							<?php else : ?>
								<?php
								foreach ( $archived_list as $event ) :
									$dt_parts = explode( 'T', $event['datetime'] );
									$d_val    = isset( $dt_parts[0] ) ? $dt_parts[0] : '';
									$t_val    = isset( $dt_parts[1] ) ? substr( $dt_parts[1], 0, 5 ) : '15:00';
									?>
								<tr class="archive-row">
									<td><input type="date" value="<?php echo esc_attr( $d_val ); ?>" disabled style="width: 100%; background: #f0f0f1; color: #50575e;"></td>
									<td><input type="time" value="<?php echo esc_attr( $t_val ); ?>" disabled style="width: 100%; background: #f0f0f1; color: #50575e;"></td>
									<td><input type="text" value="<?php echo esc_attr( $event['city'] ); ?>" disabled style="width: 100%; background: #f0f0f1; color: #50575e;"></td>
									<td><input type="text" value="<?php echo esc_attr( $event['slug'] ); ?>" disabled style="width: 100%; background: #f0f0f1; color: #50575e;"></td>
									<td><span style="display: inline-block; padding: 3px 8px; background: #e2e8f0; color: #475569; border-radius: 4px; font-size: 12px; font-weight: 600;">Arşivlendi</span></td>
								</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>

					<div id="subadap-archive-pagination" style="display: flex; justify-content: space-between; align-items: center; margin-top: 12px;"></div>
				</div>
			</details>

		<?php else : ?>

			<!-- 2. Sekme: Düğme Biçimleri, Renkler & Ayarlar -->
			<form method="post" action="" style="max-width: 950px; margin-top: 20px;">
				<?php wp_nonce_field( 'subadap_styles_nonce_action', 'subadap_styles_nonce' ); ?>

				<div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 16px 20px; margin-bottom: 20px;">
					<h2 style="margin-top: 0;">Canlı Düğme Önizlemesi</h2>
					<div id="subadap-live-preview-box" style="display: flex; flex-wrap: wrap; gap: <?php echo intval( $styles['grid_gap'] ); ?>px; padding: 15px; background: #f8fafc; border-radius: 8px; border: 1px dashed #cbd5e1;">
						<?php
						$sample_cities = array( 'İzmir', 'İstanbul', 'Ankara', 'Bursa' );
						for ( $p = 0; $p < 4; $p++ ) :
							$d_bg = $styles['palettes'][ $p ]['date'];
							$c_bg = $styles['palettes'][ $p ]['city'];
							?>
							<div class="preview-badge" data-index="<?php echo $p; ?>" style="display: flex; width: <?php echo intval( $styles['card_min_w'] ); ?>px; height: <?php echo intval( $styles['badge_height'] ); ?>px; border-radius: <?php echo intval( $styles['border_radius'] ); ?>px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.06); font-family: system-ui, sans-serif;">
								<div class="preview-date" style="width: 44%; background-color: <?php echo esc_attr( $d_bg ); ?>; color: <?php echo esc_attr( $styles['date_text'] ); ?>; display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; line-height: 1.2;">
									<strong style="font-size: 15px;">24 Ekim</strong>
									<span style="font-size: 13px;">Cumartesi</span>
									<strong style="font-size: 13px;">15:00</strong>
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

				<div style="background: #fff; border: 1px solid #c3c4c7; border-left: 4px solid #d63638; border-radius: 4px; padding: 16px 20px; margin-bottom: 20px;">
					<h2 style="margin-top: 0;">Kaldırma (Uninstall) Veritabanı Davranışı</h2>
					<label style="display: flex; align-items: flex-start; gap: 8px; cursor: pointer;">
						<input type="checkbox" name="delete_on_uninst" value="1" <?php checked( ! empty( $styles['delete_on_uninst'] ) ); ?> style="margin-top: 3px;">
						<span>
							<strong>Eklenti WordPress panelinden silindiğinde veritabanındaki tüm konser kayıtlarını ve görünüm ayarlarını da sil.</strong><br>
							<span class="description">Güvenlik amacıyla varsayılan olarak kapalıdır (işaretsizdir). İşaretsiz bırakıldığında eklentiyi silip yeniden yükleseniz bile konser arşiviniz korunur.</span>
						</span>
					</label>
				</div>

				<p>
					<input type="submit" name="subadap_save_styles" class="button button-primary" value="Ayarları Kaydet">
					<input type="submit" name="subadap_reset_styles" class="button button-secondary" value="Varsayılan Renk ve Boyutlara Sıfırla" style="margin-left: 10px;" onclick="return confirm('Tüm düğme biçimi ve renk ayarları varsayılana sıfırlanacak. Emin misiniz?');">
				</p>
			</form>

		<?php endif; ?>
	</div>
	<?php
}
