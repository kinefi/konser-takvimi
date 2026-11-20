# Değişiklik Günlüğü (Changelog)

## [3.5.0] - 2026-11-20
### Eklenenler & İyileştirilenler
- **Google Schema (JSON-LD `MusicEvent`) Desteği:** `[konser_takvimi]` veya `[konser_ilce_yonlendir]` kısa kodlarının bulunduğu sayfalarda `<head>` içerisine otomatik olarak Google `MusicEvent` yapılandırılmış verisi (`startDate`, `endDate`, `location`, `performer`, `offers`) eklendi; Ayarlar sekmesine şema açma/kapama ve sanatçı/grup adı alanı eklendi.
- **Dönüşüm Oranı (CTR %) Sıralaması & Renkli Performans Rozeti:** **İstatistikler & Grafik** tablosunda ilçeleri **Dönüşüm Oranı (CTR %)**, **Tekil Ziyaretçi**, **Bilet Tıklaması**, **Sayfa Gösterimi** veya **Ort. Kalma Süresi** ölçütlerine göre tek tıkla sıralama bağlantıları ve CTR performans renk rozetleri eklendi.
- **Önbellek Korumalı `302 Found` İlçe Yönlendirmesi:** Süresi dolan ilçe sayfalarının arama motorlarınca kalıcı silinmiş (`301`) algılanmaması ve LiteSpeed / WP Rocket gibi önbellek eklentilerince yönlendirme kuralının önbelleğe alınmaması için `DONOTCACHEPAGE` sabiti, `nocache_headers()` ve `302` durum kodu güvencesi eklendi.

## [3.4.1] - 2026-11-19
### Eklenenler
- **Dönem ve Biçim Seçenekli Rapor İndirme Düğmesi:** Yönetim panelindeki **İstatistikler & Grafik** sekmesine **Son 7 Gün (Haftalık)**, **Son 14 Gün**, **Son 30 Gün (Aylık)** veya **Tüm Zamanlar** dönemleri için yazdırılabilir/PDF kaydedilebilir **Görsel Rapor (HTML)** ve **Excel Tablosu (.csv)** indirme düğmesi eklendi.

## [3.4.0] - 2026-11-19
### Eklenenler
- **Günlük Tekil Ziyaretçi Takibi:** Çerez (cookie) gerektirmeden `localStorage` üzerinden aynı gün içindeki tekrar sayfa yenilemelerini ayırarak **Bugünkü Tekil Ziyaretçi** ve **Toplam Tekil Ziyaretçi** metrikleri eklendi.
- **Sayfada Kalma Süresi Ölçümü:** Ziyaretçinin sekmede aktif kaldığı süre (`visibilitychange` / `pagehide` / bilet tıklaması anında) `navigator.sendBeacon()` ile kaydedilerek ilçe bazında **Ortalama Kalma Süresi** hesaplandı.
- **Son 14 Günlük İnteraktif Grafik:** Yönetim panelindeki **İstatistikler & Grafik** sekmesine harici kütüphane gerektirmeyen hafif HTML5 Canvas çubuk grafiği ve ilçe filtresi eklendi.
- **Haftalık Yönetici E-Posta Raporu:** `wp_cron` altyapısıyla her hafta seçilen günde son 7 günün performans özetini HTML tablo olarak yönetici e-posta adresine gönderen otomatik rapor sistemi ve **Şimdi Örnek Rapor E-Postası Gönder** butonu eklendi.

## [3.3.0] - 2026-11-16
### Eklenenler & İyileştirilenler
- **Varsayılan Bilet Satış URL'si Ayarı:** Ayarlar sekmesine **Varsayılan Bilet Satış URL'si** alanı eklendi.
- **Önbellek (Cache) Uyumlu Sayfa Görüntülenme Sayacı:** İlçe sayfası görüntülenmeleri (`views`) `tracker.js` üzerinden AJAX ile kaydedilecek şekilde güncellendi.

## [3.2.1] - 2026-11-16
### Eklenenler
- **Kısa Kodsuz Otomatik Bilet Tıklama Takibi:** `[konser_ilce_yonlendir]` bulunan sayfalarda bilet alan adlarına veya varsayılan bilet görseline yapılan tıklamalar otomatik yakalanır.

## [3.2.0] - 2026-11-16
### Eklenenler
- **Bilet Düğmesi Kısa Kodu (`[konser_bilet_butonu]`):** `<figure class="wp-block-image size-large">` yapısında bilet düğmesi kısa kodu ve **İstatistikler** sekmesi eklendi.

## [3.1.0] - 2026-11-16
### Eklenenler & Değiştirilenler
- **Ayarlanabilir Yönlendirme Hedefi:** Varsayılan yönlendirme adresini (varsayılan `/konserler`) belirleme alanı eklendi.

## [3.0.0] - 2026-11-16
### Eklenenler & Değiştirilenler
- **Çift Seans Desteği (`12.00 ve 14.00`)** ve **İlçe Sayfası Yönlendirme Kısa Kodu (`[konser_ilce_yonlendir]`)** eklendi.

## [2.9.0] - 2026-11-16
### Değiştirilenler
- **Genel Dosya ve Klasör Yapısı:** Eklenti klasörü ve dosyaları yeniden kullanılabilir genel isimlere dönüştürüldü.
- **Modüler Panel Stili:** Yönetim paneli satır içi CSS kuralları `assets/css/admin.css` dosyasına ayrıldı.
- **Dinamik Site URL Desteği:** Rozet bağlantılarındaki kök adres WordPress `home_url()` fonksiyonundan dinamik alınacak şekilde güncellendi.

## [2.8.1] - 2026-11-15
### Eklenenler & Değiştirilenler
- **Otomatik `ilce-konseri` Slug Formatı:** Yeni konser eklerken ilçe/şehir girildiğinde link uzantısı otomatik olarak `ilce-konseri` biçiminde oluşturulur.
- **Canlı Sayfa / Yazı Varlık Kontrolü:** Girilen veya otomatik oluşan uzantı sitenizde yayımlanmış bir sayfa/yazı ile eşleşiyorsa kutu yeşil, eşleşmiyorsa kırmızı çerçeve ve bilgi etiketiyle gösterilir.

## [2.7.0] - 2026-11-15
### Eklenenler
- **Mevcut Yazılardan Slug Filtreleme ve Seçme:** Link uzantısı kutusuna sitenizdeki yayımlanmış yazı ve sayfaları başlık/slug üzerinden filtreleyip seçme özelliği (`datalist`) eklendi.

## [2.6.0] - 2026-11-15
### Eklenenler
- **GitHub Dokümantasyonu & Lisans (`README.md`, `LICENSE`):** Kurulum, kullanım, özellikler ve dosya yapısını açıklayan `README.md` ile GNU GPLv3 (`GPL-3.0-or-later`) lisans bilgileri eklendi.
- **Yapılandırılabilir Kaldırma (`uninstall.php`):** Ayarlar sekmesine isteğe bağlı veritabanı temizleme seçeneği eklendi.

## [2.5.0] - 2026-11-15
### Eklenenler & Değiştirilenler
- **Düğme Biçimleri & Renkler Sekmesi:** Düğmelerin boyut, köşe yuvarlaklığı ve 12'li renk paleti döngüsünü canlı önizleme ile ayarlama sekmesi eklendi.

## [2.4.0] - 2026-11-15
### Eklenenler
- **JSON ve CSV Dışa Aktarma (Export):** Aktif, arşivlenmiş veya tüm konser kayıtlarını `.json` ve `.csv` formatında indirme özelliği eklendi.

## [2.3.0] - 2026-11-15
### Eklenenler
- **Açılır-Kapanır Arşiv (Accordion) & Sayfalama:** Arşivlenen konserler bölümü açılır-kapanır hale getirildi ve sayfalandırıldı.

## [2.2.0] - 2026-11-15
### Eklenenler
- **Otomatik Slug Önerisi:** Yeni konser eklerken canlı link uzantısı önerisi eklendi.

## [2.1.0] - 2026-11-15
### Değiştirilenler
- **Veritabanı Depolaması & Güvenlik:** Konser kayıtları WordPress `wp_options` tablosuna taşındı; otomatik arşivleme ve güvenlik korumaları eklendi.

## [1.0.0]
### Başlangıç
- İlk sürüm ve 12 renkli rozet ızgarası oluşturuldu.
