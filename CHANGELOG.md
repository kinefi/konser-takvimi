# Değişiklik Günlüğü (Changelog)

## [3.0.0] - 2026-11-16
### Eklenenler & Değiştirilenler
- **Çift Seans Desteği (`12.00 ve 14.00`):** Yönetim paneline isteğe bağlı **2. Seans** alanı eklendi. İkinci seans girildiğinde düğmede saat `12.00 ve 14.00` biçiminde tek satırda gösterilir ve 2. seans saati geçene kadar konser aktif kalır.
- **İlçe Sayfası Yönlendirme Kısa Kodu (`[konser_ilce_yonlendir]`):** İlçe konser sayfalarına yerleştirildiğinde ilgili ilçede yaklaşan konser olup olmadığını denetler; aktif konser yoksa ziyaretçiyi otomatik `/konserler` sayfasına (`302` geçici yönlendirme ile) yönlendirir. Yöneticiler için sayfa içinde durum bilgi kutusu gösterir.
- **CSV / JSON Dışa Aktarma Güncellemesi:** CSV dışa aktarımına `1. Seans` ve `2. Seans` sütunları eklendi.

## [2.9.0] - 2026-11-16
### Değiştirilenler
- **Genel Dosya ve Klasör Yapısı:** Eklenti klasörü ve dosyaları yeniden kullanılabilir genel isimlere (`konser-takvimi/konser-takvimi.php`, `assets/css/frontend.css`, `assets/css/admin.css`, `assets/js/frontend.js`) dönüştürüldü.
- **Modüler Panel Stili:** Yönetim paneli satır içi CSS kuralları `assets/css/admin.css` dosyasına ayrıldı.
- **Genel Kısa Kod & Geriye Dönük Uyumluluk:** Yeni genel `[konser_takvimi]` kısa kodu eklendi; mevcut veritabanı kayıtları ve `[subadap_konserler]` kısa kodu tam uyumlu şekilde korundu.
- **Dinamik Site URL Desteği:** Rozet bağlantılarındaki kök adres sabit URL yerine WordPress `home_url()` fonksiyonundan dinamik alınacak şekilde güncellendi.

## [2.8.1] - 2026-11-15
### Eklenenler & Değiştirilenler
- **Otomatik `ilce-konseri` Slug Formatı:** Yeni konser eklerken ilçe/şehir girildiğinde link uzantısı otomatik olarak `ilce-konseri` biçiminde oluşturulur.
- **Canlı Sayfa / Yazı Varlık Kontrolü:** Girilen veya otomatik oluşan uzantı sitenizde yayımlanmış bir sayfa/yazı ile eşleşiyorsa kutu yeşil, eşleşmiyorsa kırmızı çerçeve ve bilgi etiketiyle gösterilir.

## [2.7.0] - 2026-11-15
### Eklenenler & Değiştirilenler
- **Mevcut Yazılardan Slug Filtreleme ve Seçme:** Link uzantısı kutusuna sitenizdeki yayımlanmış yazı ve sayfaları başlık/slug üzerinden filtreleyip seçme özelliği (`datalist`) eklendi.
- **Temiz Kurulum:** Eski statik `konserler.json` dosyası kaldırıldı; eklenti tamamen veritabanı tabanlı çalışacak şekilde sadeleştirildi.

## [2.6.0] - 2026-11-15
### Eklenenler
- **GitHub Dokümantasyonu & Lisans (`README.md`, `LICENSE`):** Kurulum, kullanım, özellikler ve dosya yapısını açıklayan `README.md` ile GNU GPLv3 (`GPL-3.0-or-later`) lisans bilgileri eklendi.
- **Yapılandırılabilir Kaldırma (`uninstall.php`):** Ayarlar sekmesine "Eklenti silindiğinde veritabanı kayıtlarını da sil" seçeneği eklendi (varsayılan olarak verileri koruyacak şekilde kapalıdır).

## [2.5.0] - 2026-11-15
### Eklenenler & Değiştirilenler
- **Düğme Biçimleri & Renkler Sekmesi:** Yönetim paneline düğmelerin köşe yuvarlaklığı, yüksekliği, minimum genişliği, ızgara boşluğu, maksimum gösterim adedi, yazı renkleri ve 12'li renk paleti döngüsünü canlı önizleme ile ayarlama sekmesi eklendi.
- **Otomatik Pastel Ton Önerisi:** Renk paletinde tarih kutusu için koyu renk seçildiğinde şehir kutusu için uyumlu açık pastel ton otomatik hesaplanır.
- **Arayüz Yerleşimi:** Yedekle / Dışa Aktar (Export) kutusu arşivlenen konserler panelinin hemen üstüne taşındı.

## [2.4.0] - 2026-11-15
### Eklenenler
- **JSON ve CSV Dışa Aktarma (Export):** Aktif, arşivlenmiş veya tüm konser kayıtlarını tek tıkla `.json` ve `.csv` (Excel uyumlu UTF-8 BOM) formatında indirme özelliği eklendi.
- **CSV Güvenlik Koruması:** Dışa aktarılan CSV dosyalarında formül enjeksiyonunu engelleyen sanitizasyon eklendi.

## [2.3.0] - 2026-11-15
### Eklenenler
- **Açılır-Kapanır Arşiv (Accordion) & Sayfalama:** Arşivlenen konserler bölümü açılır-kapanır hale getirildi ve sayfa başına 10 kayıt gösterecek şekilde sayfalandırıldı.

## [2.2.0] - 2026-11-15
### Eklenenler
- **Otomatik Slug Önerisi:** Yeni konser eklerken canlı link uzantısı önerisi eklendi.

## [2.1.0] - 2026-11-15
### Değiştirilenler
- **Veritabanı Depolaması & Güvenlik:** Konser kayıtları WordPress `wp_options` tablosuna taşındı; otomatik arşivleme, CSRF Nonce, yetki denetimleri ve XSS korumaları eklendi.

## [1.0.0]
### Başlangıç
- `[subadap_konserler]` kısa kodu ve 12 renkli rozet ızgarası oluşturuldu.
