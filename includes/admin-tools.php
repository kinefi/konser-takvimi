<?php
/**
 * Admin "Tools & Diagnostics" tab: tracking health panel, deprecated shortcode/attribute scanner,
 * UTM link builder for social channels and archive cleanup.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode names that are deprecated or renamed.
 */
function kt_get_deprecated_shortcode_names() {
	return array( 'subadap_konserler' );
}

/**
 * Attribute names used before 4.1.0 (Turkish) mapped to their English replacements.
 */
function kt_get_legacy_attribute_map() {
	return array(
		'ilce'          => 'district',
		'hedef'         => 'redirect_target',
		'resim'         => 'image',
		'takvime_ekle'  => 'show_calendar_button',
	);
}

/**
 * Analyzes a post's content and returns a list of human-readable findings (empty when the content is clean).
 * Pure function: no database access.
 */
function kt_analyze_content_for_legacy_usage( $content ) {
	$findings = array();
	$content  = (string) $content;

	foreach ( kt_get_deprecated_shortcode_names() as $name ) {
		if ( false !== strpos( $content, '[' . $name ) ) {
			$findings[] = sprintf( '[%s] kısa kodu kullanımdan kalktı', $name );
		}
	}

	$kt_shortcodes = array_merge( array( 'konser_takvimi', 'konser_ilce_yonlendir', 'konser_bilet_butonu' ), kt_get_deprecated_shortcode_names() );
	foreach ( kt_get_legacy_attribute_map() as $old => $new ) {
		$found = false;
		foreach ( $kt_shortcodes as $name ) {
			$pattern = '/\[' . preg_quote( $name, '/' ) . '\b[^\]]*\b' . preg_quote( $old, '/' ) . '\s*=/i';
			if ( preg_match( $pattern, $content ) ) {
				$found = true;
				break;
			}
		}
		if ( ! $found && preg_match( '/<!--\s*wp:konser-takvimi\/[a-z\-]+\s+\{[^}]*"' . preg_quote( $old, '/' ) . '"\s*:/i', $content ) ) {
			$found = true;
		}
		if ( $found ) {
			$findings[] = sprintf( 'eski öznitelik "%s" → "%s"', $old, $new );
		}
	}

	return $findings;
}

/**
 * Scans published posts/pages for deprecated shortcodes and old attribute names.
 * Returns rows: array( 'id' => int, 'title' => string, 'edit_url' => string, 'findings' => string[] ).
 */
function kt_scan_legacy_usage( $limit = 300 ) {
	global $wpdb;

	$like_terms = array( '[subadap_konserler', '[konser_', 'wp:konser-takvimi/' );
	$where      = array();
	$args       = array();
	foreach ( $like_terms as $term ) {
		$where[] = 'post_content LIKE %s';
		$args[]  = '%' . $wpdb->esc_like( $term ) . '%';
	}
	$args[] = intval( $limit );

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- The WHERE fragment only contains fixed "post_content LIKE %s" placeholders; values are passed to prepare().
	$sql  = "SELECT ID, post_title, post_content FROM {$wpdb->posts} WHERE post_status IN ('publish','draft','private','pending') AND post_type NOT IN ('revision','nav_menu_item') AND (" . implode( ' OR ', $where ) . ') LIMIT %d';
	$rows = $wpdb->get_results( $wpdb->prepare( $sql, $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	$out = array();
	foreach ( (array) $rows as $row ) {
		$findings = kt_analyze_content_for_legacy_usage( $row->post_content );
		if ( ! empty( $findings ) ) {
			$out[] = array(
				'id'       => intval( $row->ID ),
				'title'    => '' !== $row->post_title ? $row->post_title : '(başlıksız)',
				'edit_url' => admin_url( 'post.php?post=' . intval( $row->ID ) . '&action=edit' ),
				'findings' => $findings,
			);
		}
	}

	return $out;
}

/**
 * Default social channel presets for the UTM link builder (editable in the UI by changing the generated links).
 */
function kt_get_utm_link_presets() {
	return array(
		array( 'label' => 'WhatsApp', 'source' => 'whatsapp', 'medium' => 'messaging' ),
		array( 'label' => 'Meta Reklam', 'source' => 'meta', 'medium' => 'cpc' ),
		array( 'label' => 'YouTube Reklam', 'source' => 'youtube', 'medium' => 'cpc' ),
		array( 'label' => 'E-posta bülteni', 'source' => 'newsletter', 'medium' => 'email' ),
		array( 'label' => 'Instagram (organik)', 'source' => 'instagram', 'medium' => 'social' ),
		array( 'label' => 'Facebook (organik)', 'source' => 'facebook', 'medium' => 'social' ),
		array( 'label' => 'X / Twitter (organik)', 'source' => 'x', 'medium' => 'social' ),
		array( 'label' => 'Doğrudan (etiketsiz)', 'source' => '', 'medium' => '' ),
	);
}

/**
 * Builds one tracking link per preset for a landing page. The campaign defaults to the district key.
 * A preset with an empty source returns the plain URL (counted as "direct").
 */
function kt_build_utm_links( $page_url, $campaign, $presets = null ) {
	if ( null === $presets ) {
		$presets = kt_get_utm_link_presets();
	}
	$campaign = kt_sanitize_utm_value( $campaign );
	$links    = array();
	foreach ( $presets as $preset ) {
		$source = kt_sanitize_utm_value( $preset['source'] );
		$medium = kt_sanitize_utm_value( $preset['medium'] );
		$url    = $page_url;
		if ( '' !== $source ) {
			$params = array( 'utm_source' => $source );
			if ( '' !== $medium ) {
				$params['utm_medium'] = $medium;
			}
			if ( '' !== $campaign ) {
				$params['utm_campaign'] = $campaign;
			}
			$url = add_query_arg( $params, $page_url );
		}
		$links[] = array( 'label' => $preset['label'], 'url' => $url );
	}
	return $links;
}

/**
 * Removes archived events whose last session ended more than $months months ago. Returns array( kept, removed ).
 */
function kt_purge_archive_older_than( $events, $months, $now_ts ) {
	$months = max( 1, intval( $months ) );
	$cutoff = $now_ts - ( $months * 30 * DAY_IN_SECONDS );
	$kept   = array();
	$gone   = 0;
	foreach ( (array) $events as $event ) {
		$end_ts = kt_get_event_end_timestamp( $event );
		if ( false !== $end_ts && $end_ts < $cutoff ) {
			$gone++;
		} else {
			$kept[] = $event;
		}
	}
	return array( $kept, $gone );
}

/**
 * Collects the data shown in the tracking diagnostics panel.
 */
function kt_get_tracking_diagnostics() {
	$buffer = get_option( KT_STATS_BUFFER_OPT, array() );
	$stats  = get_option( KT_STATS_OPT, array() );
	$last   = 0;
	if ( is_array( $stats ) ) {
		foreach ( $stats as $row ) {
			if ( isset( $row['updated'] ) && intval( $row['updated'] ) > $last ) {
				$last = intval( $row['updated'] );
			}
		}
	}
	$lock = get_option( KT_STATS_LOCK_OPT, 0 );

	return array(
		'districts'   => is_array( $stats ) ? count( $stats ) : 0,
		'buffered'    => is_array( $buffer ) ? count( $buffer ) : 0,
		'last_update' => $last,
		'lock_active' => ! empty( $lock ),
		'overflow'    => get_option( KT_STATS_OVERFLOW_OPT, '' ),
		'max'         => KT_STATS_MAX_DISTRICTS,
	);
}

/**
 * Handles POST actions of the tools tab. Returns an admin notice HTML string (or an empty string).
 */
function kt_handle_tools_actions() {
	if ( isset( $_POST['kt_purge_archive'] ) && check_admin_referer( 'kt_tools_nonce_action', 'kt_tools_nonce' ) ) {
		$months = isset( $_POST['purge_months'] ) ? intval( $_POST['purge_months'] ) : 12;
		if ( ! in_array( $months, array( 6, 12, 24, 36 ), true ) ) {
			$months = 12;
		}
		$events = get_option( KT_EVENTS_OPT, array() );
		list( $kept, $gone ) = kt_purge_archive_older_than( $events, $months, kt_get_site_timestamp() );
		if ( $gone > 0 ) {
			update_option( KT_EVENTS_OPT, $kept, false );
		}
		return '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf( '%d aydan eski %d arşiv kaydı silindi.', $months, $gone ) ) . '</p></div>';
	}

	if ( isset( $_POST['kt_flush_buffer_now'] ) && check_admin_referer( 'kt_tools_nonce_action', 'kt_tools_nonce' ) ) {
		kt_flush_stats_buffer();
		return '<div class="notice notice-success is-dismissible"><p>Bekleyen istatistik olayları işlendi.</p></div>';
	}

	if ( isset( $_POST['kt_clear_overflow'] ) && check_admin_referer( 'kt_tools_nonce_action', 'kt_tools_nonce' ) ) {
		delete_option( KT_STATS_OVERFLOW_OPT );
		return '<div class="notice notice-info is-dismissible"><p>Uyarı temizlendi.</p></div>';
	}

	return '';
}

/**
 * Renders the tools tab. $concert_events is the full event list (used for the link builder dropdown).
 */
function kt_render_tools_tab( $concert_events ) {
	$diag     = kt_get_tracking_diagnostics();
	$scan     = kt_scan_legacy_usage();
	$selected = isset( $_GET['kt_link_page'] ) ? esc_url_raw( wp_unslash( $_GET['kt_link_page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only link builder input, escaped on output.
	$campaign = isset( $_GET['kt_link_campaign'] ) ? kt_sanitize_utm_value( sanitize_text_field( wp_unslash( $_GET['kt_link_campaign'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$tz       = kt_get_site_timezone();
	$now_str  = kt_get_site_datetime_str();
	?>
	<div style="max-width: 1060px;">
		<h2>Takip Tanılama</h2>
		<table class="widefat striped" style="max-width: 700px;">
			<tbody>
				<tr><td>Saat dilimi / şu an</td><td><?php echo esc_html( $tz->getName() . ' — ' . $now_str ); ?></td></tr>
				<tr><td>Kayıtlı ilçe sayısı</td><td><?php echo esc_html( $diag['districts'] . ' / ' . $diag['max'] ); ?></td></tr>
				<tr><td>İşlenmeyi bekleyen olay</td><td><?php echo esc_html( (string) $diag['buffered'] ); ?></td></tr>
				<tr><td>Son istatistik yazımı</td><td><?php echo $diag['last_update'] ? esc_html( wp_date( 'd.m.Y H:i:s', $diag['last_update'], $tz ) ) : 'Henüz kayıt yok'; ?></td></tr>
				<tr><td>Yazma kilidi</td><td><?php echo $diag['lock_active'] ? 'Şu an aktif (normalde birkaç ms sürer)' : 'Boş'; ?></td></tr>
			</tbody>
		</table>
		<p class="description">Yönetici (sayfa düzenleme yetkili) ziyaretleri sayılmaz. Bunu test etmek için siteyi çıkış yapmış bir tarayıcıda veya gizli sekmede açın.</p>
		<form method="post" action="">
			<?php wp_nonce_field( 'kt_tools_nonce_action', 'kt_tools_nonce' ); ?>
			<input type="submit" name="kt_flush_buffer_now" class="button" value="Bekleyen olayları şimdi işle">
		</form>

		<?php if ( ! empty( $diag['overflow'] ) ) : ?>
			<div class="notice notice-warning inline" style="margin-top: 15px;"><p>
				<strong>Uyarı:</strong> İstatistik tablosu <?php echo esc_html( (string) $diag['max'] ); ?> ilçe sınırına ulaştı; <?php echo esc_html( $diag['overflow'] ); ?> tarihinde yeni bir ilçe kaydedilemedi. Gereksiz kayıtları İstatistikler sekmesinden silin.
			</p>
			<form method="post" action="">
				<?php wp_nonce_field( 'kt_tools_nonce_action', 'kt_tools_nonce' ); ?>
				<input type="submit" name="kt_clear_overflow" class="button button-small" value="Uyarıyı kapat">
			</form></div>
		<?php endif; ?>

		<h2 style="margin-top: 30px;">Eski Kısa Kod / Öznitelik Kullanan Sayfalar</h2>
		<?php if ( empty( $scan ) ) : ?>
			<p>Taranan yazı ve sayfalarda kullanımdan kalkmış kısa kod veya eski öznitelik bulunmadı.</p>
		<?php else : ?>
			<table class="widefat striped">
				<thead><tr><th>Sayfa</th><th>Bulgular</th></tr></thead>
				<tbody>
				<?php foreach ( $scan as $row ) : ?>
					<tr>
						<td><a href="<?php echo esc_url( $row['edit_url'] ); ?>"><?php echo esc_html( $row['title'] ); ?></a></td>
						<td><?php echo esc_html( implode( '; ', $row['findings'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<h2 style="margin-top: 30px;">Sosyal Medya &amp; Kampanya Bağlantı Oluşturucu</h2>
		<p class="description">Sayfa adresine kanal bazlı UTM ekler. Kampanya adı varsayılan olarak ilçe/slug olur. Bağlantıyı ilgili kanalda paylaşın; ziyaretler İstatistikler sekmesindeki "Trafik Kaynakları" tablosunda ayrışır.</p>
		<form method="get" action="">
			<input type="hidden" name="page" value="konser-takvimi">
			<input type="hidden" name="tab" value="tools">
			<select name="kt_link_page">
				<?php foreach ( (array) $concert_events as $ev ) : ?>
					<?php
					if ( empty( $ev['slug'] ) ) {
						continue;
					}
					$ev_url = home_url( '/' . ltrim( $ev['slug'], '/' ) . '/' );
					?>
					<option value="<?php echo esc_attr( $ev_url ); ?>" <?php selected( $selected, $ev_url ); ?>><?php echo esc_html( $ev['city'] . ' — ' . $ev_url ); ?></option>
				<?php endforeach; ?>
			</select>
			<input type="text" name="kt_link_campaign" value="<?php echo esc_attr( $campaign ); ?>" placeholder="kampanya (boşsa slug)">
			<input type="submit" class="button" value="Bağlantıları oluştur">
		</form>
		<?php
		if ( '' !== $selected ) {
			$path_slug = trim( (string) wp_parse_url( $selected, PHP_URL_PATH ), '/' );
			$links     = kt_build_utm_links( $selected, '' !== $campaign ? $campaign : $path_slug );
			echo '<table class="widefat striped" style="margin-top: 10px;"><thead><tr><th style="width: 200px;">Kanal</th><th>Bağlantı</th></tr></thead><tbody>';
			foreach ( $links as $link ) {
				echo '<tr><td>' . esc_html( $link['label'] ) . '</td><td><input type="text" readonly class="large-text code" value="' . esc_attr( $link['url'] ) . '" onclick="this.select();"></td></tr>';
			}
			echo '</tbody></table>';
		}
		?>

		<h2 style="margin-top: 30px;">Arşiv Temizliği</h2>
		<form method="post" action="">
			<?php wp_nonce_field( 'kt_tools_nonce_action', 'kt_tools_nonce' ); ?>
			<select name="purge_months">
				<option value="6">6</option>
				<option value="12" selected>12</option>
				<option value="24">24</option>
				<option value="36">36</option>
			</select>
			aydan eski arşiv kayıtlarını
			<input type="submit" name="kt_purge_archive" class="button" value="Sil" onclick="return confirm('Seçilen süreden eski arşivlenmiş konser kayıtları kalıcı olarak silinecek. Emin misiniz?');">
			<p class="description">Yalnızca son seansı bitmiş ve seçilen süreden eski kayıtlar silinir. İstatistikler etkilenmez.</p>
		</form>
	</div>
	<?php
}
