<?php
/**
 * Internationalisation helpers.
 *
 * Source strings are Turkish (the plugin's original language); an English (en_US) catalogue ships in /languages.
 * The text domain follows the WordPress locale, so the admin screens and visitor-facing texts switch language
 * together with the site (or the user's own profile language in the admin).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'plugins_loaded', 'kt_load_textdomain' );
function kt_load_textdomain() {
	load_plugin_textdomain( 'konser-takvimi', false, dirname( plugin_basename( KT_PLUGIN_FILE ) ) . '/languages' );
}

add_filter( 'load_textdomain_mofile', 'kt_pick_mofile', 10, 2 );
/**
 * Falls back to the English catalogue for every locale that has no catalogue of its own (en_GB, de_DE, ...),
 * so only Turkish sites show the Turkish source strings.
 */
function kt_pick_mofile( $mofile, $domain ) {
	if ( 'konser-takvimi' !== $domain || file_exists( $mofile ) ) {
		return $mofile;
	}
	if ( preg_match( '/-tr_TR\.mo$/', $mofile ) ) {
		return $mofile;
	}
	$fallback = dirname( $mofile ) . '/konser-takvimi-en_US.mo';
	return file_exists( $fallback ) ? $fallback : $mofile;
}

/**
 * Inline HTML allowed in translated strings that contain markup.
 */
function kt_i18n_allowed_html() {
	return array(
		'strong' => array(),
		'em'     => array(),
		'b'      => array(),
		'i'      => array(),
		'code'   => array(),
		'br'     => array(),
	);
}

/**
 * Month names (index 1-12; index 0 is empty) in the current locale.
 */
function kt_get_month_names() {
	return array(
		'',
		__( 'Ocak', 'konser-takvimi' ),
		__( 'Şubat', 'konser-takvimi' ),
		__( 'Mart', 'konser-takvimi' ),
		__( 'Nisan', 'konser-takvimi' ),
		__( 'Mayıs', 'konser-takvimi' ),
		__( 'Haziran', 'konser-takvimi' ),
		__( 'Temmuz', 'konser-takvimi' ),
		__( 'Ağustos', 'konser-takvimi' ),
		__( 'Eylül', 'konser-takvimi' ),
		__( 'Ekim', 'konser-takvimi' ),
		__( 'Kasım', 'konser-takvimi' ),
		__( 'Aralık', 'konser-takvimi' ),
	);
}

/**
 * Day names, index 0 = Sunday, in the current locale.
 */
function kt_get_day_names() {
	return array(
		__( 'Pazar', 'konser-takvimi' ),
		__( 'Pazartesi', 'konser-takvimi' ),
		__( 'Salı', 'konser-takvimi' ),
		__( 'Çarşamba', 'konser-takvimi' ),
		__( 'Perşembe', 'konser-takvimi' ),
		__( 'Cuma', 'konser-takvimi' ),
		__( 'Cumartesi', 'konser-takvimi' ),
	);
}

/**
 * Strings used by the JavaScript files. JS looks them up by their source (Turkish) text: ktI18n[ source ] || source.
 */
function kt_get_js_dictionary() {
	return array(
		'Yakın tarihte planlanmış konser bulunmamaktadır.' => __( 'Yakın tarihte planlanmış konser bulunmamaktadır.', 'konser-takvimi' ),
		've' => __( 've', 'konser-takvimi' ),
		'BUGÜN' => __( 'BUGÜN', 'konser-takvimi' ),
		'YARIN' => __( 'YARIN', 'konser-takvimi' ),
		'✓ Mevcut: ' => __( '✓ Mevcut: ', 'konser-takvimi' ),
		'✕ Bu uzantıda sayfa/yazı bulunamadı' => __( '✕ Bu uzantıda sayfa/yazı bulunamadı', 'konser-takvimi' ),
		'Örn: Urla' => __( 'Örn: Urla', 'konser-takvimi' ),
		'ilce-konseri veya ara...' => __( 'ilce-konseri veya ara...', 'konser-takvimi' ),
		'Sil' => __( 'Sil', 'konser-takvimi' ),
		'Sayfa %1$d / %2$d (Toplam %3$d arşiv kaydı)' => __( 'Sayfa %1$d / %2$d (Toplam %3$d arşiv kaydı)', 'konser-takvimi' ),
		'« Önceki' => __( '« Önceki', 'konser-takvimi' ),
		'Sonraki »' => __( 'Sonraki »', 'konser-takvimi' ),
		'Varsayılan Bilet Düğmesi Görselini Seçin' => __( 'Varsayılan Bilet Düğmesi Görselini Seçin', 'konser-takvimi' ),
		'Bu Görseli Kullan' => __( 'Bu Görseli Kullan', 'konser-takvimi' ),
		'Bilet Düğmesi Önizleme' => __( 'Bilet Düğmesi Önizleme', 'konser-takvimi' ),
		'Konser Takvimi' => __( 'Konser Takvimi', 'konser-takvimi' ),
		'Aktif ve yaklaşan tüm konserleri Bugün/Yarın rozetleriyle renkli kart ızgarası halinde listeler.' => __( 'Aktif ve yaklaşan tüm konserleri Bugün/Yarın rozetleriyle renkli kart ızgarası halinde listeler.', 'konser-takvimi' ),
		'Konser İlçe Yönlendirici & Takvime Ekle' => __( 'Konser İlçe Yönlendirici & Takvime Ekle', 'konser-takvimi' ),
		'İlçede aktif konser varsa Takvime Ekle düğmesini ve analitiği çalıştırır; yoksa ziyaretçiyi hedef sayfaya yönlendirir.' => __( 'İlçede aktif konser varsa Takvime Ekle düğmesini ve analitiği çalıştırır; yoksa ziyaretçiyi hedef sayfaya yönlendirir.', 'konser-takvimi' ),
		'İlçe Sayfası & Takvime Ekle Ayarları' => __( 'İlçe Sayfası & Takvime Ekle Ayarları', 'konser-takvimi' ),
		'İlçe Slug (Boş bırakılırsa sayfa slug kullanılır)' => __( 'İlçe Slug (Boş bırakılırsa sayfa slug kullanılır)', 'konser-takvimi' ),
		'orn: kadikoy' => __( 'orn: kadikoy', 'konser-takvimi' ),
		'Yönlendirme Hedefi (Boş bırakılırsa genel ayar kullanılır)' => __( 'Yönlendirme Hedefi (Boş bırakılırsa genel ayar kullanılır)', 'konser-takvimi' ),
		'Takvime Ekle düğmesini göster (Google & .ics)' => __( 'Takvime Ekle düğmesini göster (Google & .ics)', 'konser-takvimi' ),
		'Konser Bilet Butonu' => __( 'Konser Bilet Butonu', 'konser-takvimi' ),
		'CTR tıklama analitiği ile entegre bilet satın alma görsel butonu.' => __( 'CTR tıklama analitiği ile entegre bilet satın alma görsel butonu.', 'konser-takvimi' ),
		'Bilet Butonu Ayarları' => __( 'Bilet Butonu Ayarları', 'konser-takvimi' ),
		'Bilet Satış URL (Boş bırakılırsa genel ayar kullanılır)' => __( 'Bilet Satış URL (Boş bırakılırsa genel ayar kullanılır)', 'konser-takvimi' ),
		'Buton Görsel URL (Boş bırakılırsa varsayılan görsel)' => __( 'Buton Görsel URL (Boş bırakılırsa varsayılan görsel)', 'konser-takvimi' ),
		'İlçe Analitik Anahtarı (Boş bırakılırsa sayfa slug)' => __( 'İlçe Analitik Anahtarı (Boş bırakılırsa sayfa slug)', 'konser-takvimi' ),
		'orn: bornova' => __( 'orn: bornova', 'konser-takvimi' ),
		'Görsel Alt Metni' => __( 'Görsel Alt Metni', 'konser-takvimi' ),
		'Seans (isteğe bağlı: hangi seansın butonu?)' => __( 'Seans (isteğe bağlı: hangi seansın butonu?)', 'konser-takvimi' ),
		'Belirtilmedi' => __( 'Belirtilmedi', 'konser-takvimi' ),
		'1. Seans' => __( '1. Seans', 'konser-takvimi' ),
		'2. Seans' => __( '2. Seans', 'konser-takvimi' ),
		'Bağlantıyı yeni sekmede aç (_blank)' => __( 'Bağlantıyı yeni sekmede aç (_blank)', 'konser-takvimi' ),
	);
}
