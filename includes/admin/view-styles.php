<?php
/**
 * Admin view: settings tab.
 * Included by kt_render_admin_page(); shares its local variables.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
			<form method="post" action="" style="max-width: 1060px; margin-top: 20px;">
				<?php wp_nonce_field( 'kt_styles_nonce_action', 'kt_styles_nonce' ); ?>

				<div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 16px 20px; margin-bottom: 20px;">
					<h2 style="margin-top: 0;"><?php echo esc_html__( 'Canlı Düğme Önizlemesi', 'konser-takvimi' ); ?></h2>
					<div id="kt-live-preview-box" style="display: flex; flex-wrap: wrap; gap: <?php echo intval( $styles['grid_gap'] ); ?>px; padding: 15px; background: #f8fafc; border-radius: 8px; border: 1px dashed #cbd5e1;">
						<?php
						$sample_cities = array( __( 'İzmir', 'konser-takvimi' ), __( 'İstanbul', 'konser-takvimi' ), __( 'Ankara', 'konser-takvimi' ), __( 'Bursa', 'konser-takvimi' ) );
						$sample_times  = array( sprintf( __( '%1$s ve %2$s', 'konser-takvimi' ), '12.00', '14.00' ), '15.00', sprintf( __( '%1$s ve %2$s', 'konser-takvimi' ), '13.00', '15.00' ), '14.00' );
						for ( $p = 0; $p < 4; $p++ ) :
							$d_bg = $styles['palettes'][ $p ]['date'];
							$c_bg = $styles['palettes'][ $p ]['city'];
							?>
							<div class="preview-badge" data-index="<?php echo intval( $p ); ?>" style="display: flex; width: <?php echo intval( $styles['card_min_w'] ); ?>px; height: <?php echo intval( $styles['badge_height'] ); ?>px; border-radius: <?php echo intval( $styles['border_radius'] ); ?>px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.06); font-family: system-ui, sans-serif;">
								<div class="preview-date" style="width: 44%; background-color: <?php echo esc_attr( $d_bg ); ?>; color: <?php echo esc_attr( $styles['date_text'] ); ?>; display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; line-height: 1.2;">
									<strong style="font-size: 15px;"><?php echo esc_html__( '24 Ekim', 'konser-takvimi' ); ?></strong>
									<span style="font-size: 13px;"><?php echo esc_html__( __( 'Cumartesi', 'konser-takvimi' ), 'konser-takvimi' ); ?></span>
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
					<h2 style="margin-top: 0;"><?php echo esc_html__( 'Boyut, Köşe Yuvarlaklığı ve Izgara Ayarları', 'konser-takvimi' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="border_radius"><?php echo esc_html__( 'Köşe Yuvarlaklığı (Border Radius)', 'konser-takvimi' ); ?></label></th>
							<td>
								<input type="range" id="border_radius" name="border_radius" min="0" max="45" value="<?php echo intval( $styles['border_radius'] ); ?>">
								<span id="border_radius_val" style="font-weight: 600; margin-left: 8px;"><?php echo intval( $styles['border_radius'] ); ?> px</span>
								<p class="description"><?php echo esc_html__( '0 px keskin dikdörtgen, 20 px yumuşak köşe, 45 px hap (pill) görünümü verir.', 'konser-takvimi' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="badge_height"><?php echo esc_html__( 'Düğme Yüksekliği', 'konser-takvimi' ); ?></label></th>
							<td>
								<input type="range" id="badge_height" name="badge_height" min="65" max="140" value="<?php echo intval( $styles['badge_height'] ); ?>">
								<span id="badge_height_val" style="font-weight: 600; margin-left: 8px;"><?php echo intval( $styles['badge_height'] ); ?> px</span>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="card_min_w"><?php echo esc_html__( 'Minimum Düğme Genişliği', 'konser-takvimi' ); ?></label></th>
							<td>
								<input type="range" id="card_min_w" name="card_min_w" min="200" max="400" step="5" value="<?php echo intval( $styles['card_min_w'] ); ?>">
								<span id="card_min_w_val" style="font-weight: 600; margin-left: 8px;"><?php echo intval( $styles['card_min_w'] ); ?> px</span>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="grid_gap"><?php echo esc_html__( 'Düğmeler Arası Boşluk (Gap)', 'konser-takvimi' ); ?></label></th>
							<td>
								<input type="range" id="grid_gap" name="grid_gap" min="6" max="45" value="<?php echo intval( $styles['grid_gap'] ); ?>">
								<span id="grid_gap_val" style="font-weight: 600; margin-left: 8px;"><?php echo intval( $styles['grid_gap'] ); ?> px</span>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="max_items"><?php echo esc_html__( 'Sayfada Gösterilecek Maksimum Konser', 'konser-takvimi' ); ?></label></th>
							<td>
								<input type="number" id="max_items" name="max_items" min="1" max="100" value="<?php echo intval( $styles['max_items'] ); ?>" style="width: 80px;">
								<span class="description" style="margin-left: 8px;"><?php echo esc_html__( 'Varsayılan: 16 konser', 'konser-takvimi' ); ?></span>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Yazı Renkleri', 'konser-takvimi' ); ?></th>
							<td>
								<label style="margin-right: 20px;">
									<?php echo esc_html__( 'Tarih Bölümü Yazı Rengi:', 'konser-takvimi' ); ?>
									<input type="color" id="date_text" name="date_text" value="<?php echo esc_attr( $styles['date_text'] ); ?>">
								</label>
								<label>
									<?php echo esc_html__( 'Şehir Bölümü Yazı Rengi:', 'konser-takvimi' ); ?>
									<input type="color" id="city_text" name="city_text" value="<?php echo esc_attr( $styles['city_text'] ); ?>">
								</label>
							</td>
						</tr>
					</table>
				</div>

				<div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 16px 20px; margin-bottom: 20px;">
					<h2 style="margin-top: 0;"><?php echo esc_html__( '12\'li Düğme Renk Paleti Döngüsü', 'konser-takvimi' ); ?></h2>
					<p class="description" style="margin-bottom: 15px;"><?php echo esc_html__( 'Konser rozetleri sırasıyla bu 12 renk çiftini kullanır. Sol taraftaki koyu rengi seçtiğinizde sağ taraf için otomatik uyumlu pastel ton önerilir; dilerseniz sağ rengi de bağımsız seçebilirsiniz.', 'konser-takvimi' ); ?></p>

					<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 12px;">
						<?php for ( $i = 0; $i < 12; $i++ ) : ?>
							<div class="palette-item-card" data-palette-idx="<?php echo intval( $i ); ?>" style="border: 1px solid #dcdcde; border-radius: 6px; padding: 10px 12px; display: flex; align-items: center; justify-content: space-between; background: #fafafa;">
								<strong style="min-width: 65px;">Renk #<?php echo intval( $i + 1 ); ?></strong>
								<label style="display: flex; align-items: center; gap: 4px; font-size: 12px;">
									<?php echo esc_html__( 'Tarih:', 'konser-takvimi' ); ?>
									<input type="color" class="palette-date-input" name="palette_date[<?php echo intval( $i ); ?>]" value="<?php echo esc_attr( $styles['palettes'][ $i ]['date'] ); ?>">
								</label>
								<label style="display: flex; align-items: center; gap: 4px; font-size: 12px;">
									<?php echo esc_html__( 'Şehir:', 'konser-takvimi' ); ?>
									<input type="color" class="palette-city-input" name="palette_city[<?php echo intval( $i ); ?>]" value="<?php echo esc_attr( $styles['palettes'][ $i ]['city'] ); ?>">
								</label>
							</div>
						<?php endfor; ?>
					</div>
				</div>

				<div style="background: #fff; border: 1px solid #c3c4c7; border-left: 4px solid #2271b1; border-radius: 4px; padding: 16px 20px; margin-bottom: 20px;">
					<h2 style="margin-top: 0;"><?php echo esc_html__( 'İlçe Sayfası Yönlendirme, Bilet Düğmesi & Otomatik Takip Ayarları', 'konser-takvimi' ); ?></h2>
					<table class="form-table" role="presentation" style="margin-top: 0;">
						<tr>
							<th scope="row"><label for="redirect_target"><?php echo esc_html__( 'Varsayılan Yönlendirme Sayfası', 'konser-takvimi' ); ?></label></th>
							<td>
								<input type="text" id="redirect_target" name="redirect_target" value="<?php echo esc_attr( $styles['redirect_target'] ); ?>" class="regular-text" placeholder="/konserler">
								<p class="description">
									<?php echo wp_kses( __( 'İlçe sayfasında aktif konser bulunmadığında ziyaretçilerin yönlendirileceği sayfa yolu (örn. <code>/konserler</code>) veya tam URL.', 'konser-takvimi' ), kt_i18n_allowed_html() ); ?>
								</p>
								<p class="description" style="margin-top: 6px; color: #1d2327;">
									<?php echo wp_kses( __( '<strong>Önemli Not:</strong> <strong>Konser İlçe Yönlendirici</strong> bloğu (veya eski <code>[konser_ilce_yonlendir]</code> kısa kodu) parametresiz kullanıldığında kontrolü <strong>sayfanın URL uzantısına (slug)</strong> göre yapar ve aktif konser varsa otomatik olarak <strong>Takvime Ekle</strong> düğmesini gösterir. URL\'de ilçe adı geçmiyorsa blok ayarlarından <strong>İlçe Slug</strong> alanını doldurabilirsiniz.', 'konser-takvimi' ), kt_i18n_allowed_html() ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="ticket_btn_url"><?php echo esc_html__( 'Varsayılan Bilet Satış URL\'si', 'konser-takvimi' ); ?></label></th>
							<td>
								<input type="url" id="ticket_btn_url" name="ticket_btn_url" value="<?php echo esc_url( $styles['ticket_btn_url'] ); ?>" class="large-text" placeholder="https://biletinial.com/tr-tr/tiyatro/subadap-cocuk-konseri">
								<p class="description">
									<?php echo wp_kses( __( 'Buraya bir varsayılan bilet bağlantısı girerseniz sayfalarda ekstra URL girmeden doğrudan <strong>Konser Bilet Butonu</strong> bloğunu ekleyebilirsiniz.', 'konser-takvimi' ), kt_i18n_allowed_html() ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="ticket_btn_img"><?php echo esc_html__( 'Varsayılan Bilet Düğmesi Görseli', 'konser-takvimi' ); ?></label></th>
							<td>
								<div style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center;">
									<input type="url" id="ticket_btn_img" name="ticket_btn_img" value="<?php echo esc_url( $styles['ticket_btn_img'] ); ?>" class="regular-text" style="min-width: 360px;">
									<button type="button" class="button button-secondary" id="kt-select-ticket-img"><?php echo esc_html__( 'Ortam Kütüphanesinden Seç', 'konser-takvimi' ); ?></button>
								</div>
								<div id="kt-ticket-img-preview" style="margin-top: 10px;">
									<?php if ( ! empty( $styles['ticket_btn_img'] ) ) : ?>
										<img src="<?php echo esc_url( $styles['ticket_btn_img'] ); ?>" alt="<?php echo esc_attr__( 'Bilet Düğmesi Önizleme', 'konser-takvimi' ); ?>" style="max-width: 360px; height: auto; border: 1px solid #dcdcde; border-radius: 4px; padding: 4px; background: #f8fafc;">
									<?php endif; ?>
								</div>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="ticket_domains"><?php echo esc_html__( 'Otomatik Takip Edilecek Bilet Alan Adları', 'konser-takvimi' ); ?></label></th>
							<td>
								<input type="text" id="ticket_domains" name="ticket_domains" value="<?php echo esc_attr( $styles['ticket_domains'] ); ?>" class="large-text" placeholder="biletinial.com, biletix.com, passo.com.tr, bubilet.com.tr">
								<p class="description">
									<?php echo esc_html__( 'İlçe sayfalarında bu alan adlarına giden bağlantılara veya bilet görseli içeren linklere yapılan tıklamalar otomatik olarak ilgili ilçeye kaydedilir. Virgülle ayırarak alan adı ekleyebilirsiniz.', 'konser-takvimi' ); ?>
								</p>
							</td>
						</tr>
					</table>
				</div>

				<div style="background: #fff; border: 1px solid #c3c4c7; border-left: 4px solid #9333ea; border-radius: 4px; padding: 16px 20px; margin-bottom: 20px;">
					<h2 style="margin-top: 0;"><?php echo esc_html__( 'Google Arama Zengin Sonuçları — Schema (JSON-LD MusicEvent)', 'konser-takvimi' ); ?></h2>
					<table class="form-table" role="presentation" style="margin-top: 0;">
						<tr>
							<th scope="row"><?php echo esc_html__( 'Otomatik MusicEvent Şeması', 'konser-takvimi' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="schema_enabled" value="1" <?php checked( ! empty( $styles['schema_enabled'] ) ); ?>>
									<?php echo esc_html__( 'Konser Takvimi veya İlçe Yönlendirici blok/kısa kodlarının bulunduğu sayfalara otomatik Google', 'konser-takvimi' ); ?> <code><?php echo esc_html__( 'MusicEvent', 'konser-takvimi' ); ?></code> <?php echo esc_html__( 'JSON-LD yapılandırılmış verisi ekle.', 'konser-takvimi' ); ?>
								</label>
								<p class="description"><?php echo esc_html__( 'Etkinlik tarihi, saati, ilçe konumu, açıklama ve bilet bağlantısı Google arama sonuçlarında zengin etkinlik kartı olarak dizine eklenir.', 'konser-takvimi' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="schema_performer"><?php echo esc_html__( 'Sanatçı / Grup Adı (Performer)', 'konser-takvimi' ); ?></label></th>
							<td>
								<input type="text" id="schema_performer" name="schema_performer" value="<?php echo esc_attr( $styles['schema_performer'] ); ?>" class="regular-text" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
								<p class="description"><?php echo wp_kses( sprintf( /* translators: %s: site title in a <code> element */ __( 'Boş bırakılırsa site başlığı (%s) kullanılır.', 'konser-takvimi' ), '<code>' . esc_html( get_bloginfo( 'name' ) ) . '</code>' ), kt_i18n_allowed_html() ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<div style="background: #fff; border: 1px solid #c3c4c7; border-left: 4px solid #0891b2; border-radius: 4px; padding: 16px 20px; margin-bottom: 20px;">
					<h2 style="margin-top: 0;"><?php echo esc_html__( 'UTM Parametreleri', 'konser-takvimi' ); ?></h2>
					<table class="form-table" role="presentation" style="margin-top: 0;">
						<tr>
							<th scope="row"><?php echo esc_html__( 'Giden Bağlantılar', 'konser-takvimi' ); ?></th>
							<td>
								<label style="display: block; margin-bottom: 6px;">
									<input type="checkbox" name="utm_ticket_enabled" value="1" <?php checked( ! empty( $styles['utm_ticket_enabled'] ) ); ?>>
									<?php echo esc_html__( 'Bilet düğmesine ve otomatik takip edilen bilet alan adlarına giden bağlantılara UTM parametreleri ekle.', 'konser-takvimi' ); ?>
								</label>
								<label style="display: block;">
									<input type="checkbox" name="utm_calendar_enabled" value="1" <?php checked( ! empty( $styles['utm_calendar_enabled'] ) ); ?>>
									<?php echo esc_html__( 'Takvime Ekle (Google Takvim / .ics) etkinlik açıklamasındaki sayfa bağlantısına UTM parametreleri ekle.', 'konser-takvimi' ); ?>
								</label>
								<p class="description"><?php echo wp_kses( __( 'Bağlantıda zaten bir <code>utm_*</code> parametresi varsa değiştirilmez. Bilet bağlantıları şu biçimi alır: <code>?utm_source=<em>kaynak</em>&amp;utm_medium=<em>ortam</em>&amp;utm_campaign=<em>kampanya</em>&amp;utm_content=ticket-button</code>. Takvim bağlantıları için kaynak <code>google-calendar</code> veya <code>ics-file</code>, ortam <code>calendar</code> olur.', 'konser-takvimi' ), kt_i18n_allowed_html() ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="utm_source"><?php echo esc_html__( 'Kaynak (utm_source)', 'konser-takvimi' ); ?></label></th>
							<td>
								<input type="text" id="utm_source" name="utm_source" value="<?php echo esc_attr( $styles['utm_source'] ); ?>" class="regular-text" placeholder="<?php echo esc_attr( kt_get_utm_settings()['source'] ); ?>">
								<p class="description"><?php echo wp_kses( __( 'Boş bırakılırsa site alan adı kullanılır. Yalnızca küçük harf, rakam, <code>.</code>, <code>_</code> ve <code>-</code> kabul edilir.', 'konser-takvimi' ), kt_i18n_allowed_html() ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="utm_medium"><?php echo esc_html__( 'Ortam (utm_medium)', 'konser-takvimi' ); ?></label></th>
							<td>
								<input type="text" id="utm_medium" name="utm_medium" value="<?php echo esc_attr( $styles['utm_medium'] ); ?>" class="regular-text" placeholder="referral">
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="utm_campaign"><?php echo esc_html__( 'Kampanya (utm_campaign)', 'konser-takvimi' ); ?></label></th>
							<td>
								<input type="text" id="utm_campaign" name="utm_campaign" value="<?php echo esc_attr( $styles['utm_campaign'] ); ?>" class="regular-text" placeholder="<?php echo esc_attr__( '(ilçe anahtarı)', 'konser-takvimi' ); ?>">
								<p class="description"><?php echo wp_kses( __( 'Boş bırakılırsa her bağlantı için ilgili ilçe anahtarı (örn. <code>urla</code>) kullanılır. Tüm turneyi tek kampanya altında toplamak için sabit bir değer girin (örn. <code>sonbahar-turnesi-2026</code>).', 'konser-takvimi' ), kt_i18n_allowed_html() ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Gelen Bağlantılar', 'konser-takvimi' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="utm_capture_enabled" value="1" <?php checked( ! empty( $styles['utm_capture_enabled'] ) ); ?>>
									<?php echo wp_kses( __( 'Sayfaya <code>utm_source</code>, <code>utm_medium</code> ve <code>utm_campaign</code> ile gelen ziyaretleri ve bilet tıklamalarını <strong>İstatistikler & Grafik</strong> sekmesinde kaynak bazında göster.', 'konser-takvimi' ), kt_i18n_allowed_html() ); ?>
								</label>
								<p class="description"><?php echo esc_html( sprintf( /* translators: %d: maximum UTM combinations per district */ __( 'Her ilçe için en fazla %d farklı kaynak/ortam/kampanya kombinasyonu saklanır; fazlası "(other)" altında toplanır.', 'konser-takvimi' ), KT_UTM_MAX_COMBOS ) ); ?></p>
							</td>
						</tr>
					</table>
				</div>

				<div style="background: #fff; border: 1px solid #c3c4c7; border-left: 4px solid #16a34a; border-radius: 4px; padding: 16px 20px; margin-bottom: 20px;">
					<h2 style="margin-top: 0;"><?php echo esc_html__( 'Haftalık İstatistik E-Posta Raporu', 'konser-takvimi' ); ?></h2>
					<table class="form-table" role="presentation" style="margin-top: 0;">
						<tr>
							<th scope="row"><?php echo esc_html__( 'Otomatik Haftalık Rapor', 'konser-takvimi' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="weekly_report_on" value="1" <?php checked( ! empty( $styles['weekly_report_on'] ) ); ?>>
									<?php echo esc_html__( 'Son 7 günün ilçe ziyaretçi, bilet tıklaması ve kalma süresi özetini her hafta e-posta ile gönder.', 'konser-takvimi' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="weekly_report_email"><?php echo esc_html__( 'Alıcı E-Posta Adresi', 'konser-takvimi' ); ?></label></th>
							<td>
								<input type="email" id="weekly_report_email" name="weekly_report_email" value="<?php echo esc_attr( $styles['weekly_report_email'] ); ?>" class="regular-text" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>">
								<p class="description"><?php echo wp_kses( sprintf( /* translators: %s: admin e-mail address in a <code> element */ __( 'Boş bırakılırsa WordPress genel yönetici e-posta adresine (%s) gönderilir.', 'konser-takvimi' ), '<code>' . esc_html( get_option( 'admin_email' ) ) . '</code>' ), kt_i18n_allowed_html() ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="weekly_report_day"><?php echo esc_html__( 'Gönderim Günü (Saat 09:00)', 'konser-takvimi' ); ?></label></th>
							<td>
								<?php
								$days_tr = array(
									'monday'    => __( 'Pazartesi', 'konser-takvimi' ),
									'tuesday'   => __( 'Salı', 'konser-takvimi' ),
									'wednesday' => __( 'Çarşamba', 'konser-takvimi' ),
									'thursday'  => __( 'Perşembe', 'konser-takvimi' ),
									'friday'    => __( 'Cuma', 'konser-takvimi' ),
									'saturday'  => __( 'Cumartesi', 'konser-takvimi' ),
									'sunday'    => __( 'Pazar', 'konser-takvimi' ),
								);
								?>
								<select id="weekly_report_day" name="weekly_report_day">
									<?php foreach ( $days_tr as $d_val => $d_label ) : ?>
										<option value="<?php echo esc_attr( $d_val ); ?>" <?php selected( $styles['weekly_report_day'], $d_val ); ?>><?php echo esc_html( $d_label ); ?></option>
									<?php endforeach; ?>
								</select>
								<input type="submit" name="kt_send_test_report" class="button button-secondary" value="<?php echo esc_attr__( 'Şimdi Örnek Rapor E-Postası Gönder', 'konser-takvimi' ); ?>" style="margin-left: 10px;">
							</td>
						</tr>
					</table>
				</div>

				<div style="background: #fff; border: 1px solid #c3c4c7; border-left: 4px solid #64748b; border-radius: 4px; padding: 16px 20px; margin-bottom: 20px;">
					<h2 style="margin-top: 0;"><?php echo esc_html__( 'Gizlilik, Yazı Tipi, Saat Dilimi & Veri', 'konser-takvimi' ); ?></h2>
					<table class="form-table" role="presentation" style="margin-top: 0;">
						<tr>
							<th scope="row"><?php echo esc_html__( 'Yazı tipi', 'konser-takvimi' ); ?></th>
							<td>
								<?php
								$font_options = array(
									'google' => __( 'Quicksand (Google Fonts üzerinden yüklenir)', 'konser-takvimi' ),
									'system' => __( 'Sistem yazı tipi (Google\'a bağlanılmaz)', 'konser-takvimi' ),
									'theme'  => __( 'Temanın varsayılan yazı tipi', 'konser-takvimi' ),
								);
								foreach ( $font_options as $font_value => $font_label ) :
									?>
									<label style="display: block; margin-bottom: 4px;">
										<input type="radio" name="font_mode" value="<?php echo esc_attr( $font_value ); ?>" <?php checked( $styles['font_mode'], $font_value ); ?>>
										<?php echo esc_html( $font_label ); ?>
									</label>
								<?php endforeach; ?>
								<p class="description"><?php echo wp_kses( __( 'Google Fonts kapalıyken ziyaretçilerin tarayıcısı Google\'a bağlanmaz. İstatistikler çerez kullanmaz; yalnızca tarayıcının <code>localStorage</code> alanına günlük bir "ziyaret edildi" işareti yazar. Sitenizin onay (KVKK/GDPR) politikasına uygunluğunu ayrıca değerlendirin.', 'konser-takvimi' ), kt_i18n_allowed_html() ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="timezone"><?php echo esc_html__( 'Saat dilimi', 'konser-takvimi' ); ?></label></th>
							<td>
								<select id="timezone" name="timezone">
									<?php foreach ( timezone_identifiers_list() as $tz_name ) : ?>
										<option value="<?php echo esc_attr( $tz_name ); ?>" <?php selected( $styles['timezone'], $tz_name ); ?>><?php echo esc_html( $tz_name ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php echo esc_html__( 'Konser saatleri, "bugün/yarın" etiketleri, günlük istatistikler ve Takvime Ekle bağlantıları bu saat dilimine göre hesaplanır. Daha önce kaydedilmiş konserler kendi saat farkını korur.', 'konser-takvimi' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="stats_retention_days"><?php echo esc_html__( 'Günlük istatistik saklama', 'konser-takvimi' ); ?></label></th>
							<td>
								<select id="stats_retention_days" name="stats_retention_days">
									<?php foreach ( array( 30, 90, 180 ) as $days_opt ) : ?>
										<option value="<?php echo esc_attr( (string) $days_opt ); ?>" <?php selected( intval( $styles['stats_retention_days'] ), $days_opt ); ?>><?php echo esc_html( sprintf( /* translators: %d: number of days */ _n( '%d gün', '%d gün', $days_opt, 'konser-takvimi' ), $days_opt ) ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php echo esc_html__( 'Günlük kırılımlar bu süreden sonra silinir; toplam sayılar korunur. Kısa süre veritabanını küçük tutar.', 'konser-takvimi' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="stats_buffer_seconds"><?php echo esc_html__( 'Yazma tamponu', 'konser-takvimi' ); ?></label></th>
							<td>
								<select id="stats_buffer_seconds" name="stats_buffer_seconds">
									<?php
									$buffer_labels = array(
										0   => __( 'Kapalı (anında yaz)', 'konser-takvimi' ),
										30  => __( '30 saniye', 'konser-takvimi' ),
										60  => __( '60 saniye (önerilen)', 'konser-takvimi' ),
										300 => __( '5 dakika', 'konser-takvimi' ),
									);
									foreach ( $buffer_labels as $buf_value => $buf_label ) :
										?>
										<option value="<?php echo esc_attr( (string) $buf_value ); ?>" <?php selected( intval( $styles['stats_buffer_seconds'] ), $buf_value ); ?>><?php echo esc_html( $buf_label ); ?></option>
									<?php endforeach; ?>
								</select>
								<p class="description"><?php echo esc_html__( 'Yoğun trafikte veritabanı yazma sayısını azaltır. İstatistikler bu süre kadar gecikmeli görünebilir; İstatistikler sekmesini açtığınızda bekleyenler hemen işlenir.', 'konser-takvimi' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php echo esc_html__( 'Düşük dönüşüm uyarısı', 'konser-takvimi' ); ?></th>
							<td>
								<label style="display: block; margin-bottom: 6px;">
									<input type="checkbox" name="alert_enabled" value="1" <?php checked( ! empty( $styles['alert_enabled'] ) ); ?>>
									<?php echo esc_html__( 'Haftalık rapor e-postasına, dönüşüm oranı düşük sayfalar için uyarı ekle.', 'konser-takvimi' ); ?>
								</label>
								<?php
								$alert_conv_input = '<input type="number" name="alert_min_conversion" value="' . esc_attr( (string) $styles['alert_min_conversion'] ) . '" min="0" max="100" step="0.1" style="width: 80px;">';
								$alert_vis_input  = '<input type="number" name="alert_min_visitors" value="' . esc_attr( (string) $styles['alert_min_visitors'] ) . '" min="1" step="1" style="width: 90px;">';
								// The inputs are built and escaped above, so the sentence keeps a natural word order in every language.
								echo wp_kses(
									sprintf(
										/* translators: 1: conversion threshold input, 2: minimum unique visitors input */
										__( 'Dönüşüm oranı %1$s %% altında ve en az %2$s tekil ziyaretçisi olan sayfalar için uyar.', 'konser-takvimi' ),
										$alert_conv_input,
										$alert_vis_input
									),
									array( 'input' => array( 'type' => true, 'name' => true, 'value' => true, 'min' => true, 'max' => true, 'step' => true, 'style' => true ) )
								);
								?>
							</td>
						</tr>
					</table>
				</div>

				<div style="background: #fff; border: 1px solid #c3c4c7; border-left: 4px solid #d63638; border-radius: 4px; padding: 16px 20px; margin-bottom: 20px;">
					<h2 style="margin-top: 0;"><?php echo esc_html__( 'Kaldırma (Uninstall) Veritabanı Davranışı', 'konser-takvimi' ); ?></h2>
					<label style="display: flex; align-items: flex-start; gap: 8px; cursor: pointer;">
						<input type="checkbox" name="delete_on_uninst" value="1" <?php checked( ! empty( $styles['delete_on_uninst'] ) ); ?> style="margin-top: 3px;">
						<span>
							<strong><?php echo esc_html__( 'Eklenti WordPress panelinden silindiğinde veritabanındaki tüm konser kayıtlarını, istatistikleri ve görünüm ayarlarını da sil.', 'konser-takvimi' ); ?></strong><br>
							<span class="description"><?php echo esc_html__( 'Güvenlik amacıyla varsayılan olarak kapalıdır (işaretsizdir). İşaretsiz bırakıldığında eklentiyi silip yeniden yükleseniz bile konser arşiviniz ve istatistikleriniz korunur.', 'konser-takvimi' ); ?></span>
						</span>
					</label>
				</div>

				<p>
					<input type="submit" name="kt_save_styles" class="button button-primary" value="<?php echo esc_attr__( 'Ayarları Kaydet', 'konser-takvimi' ); ?>">
					<input type="submit" name="kt_reset_styles" class="button button-secondary" value="<?php echo esc_attr__( 'Varsayılan Renk ve Boyutlara Sıfırla', 'konser-takvimi' ); ?>" style="margin-left: 10px;" onclick="return confirm(<?php echo esc_attr( wp_json_encode( __( 'Tüm düğme biçimi ve renk ayarları varsayılana sıfırlanacak. Emin misiniz?', 'konser-takvimi' ) ) ); ?>);">
				</p>
			</form>
