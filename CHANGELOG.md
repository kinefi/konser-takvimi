# Değişiklik Günlüğü (Changelog)

## [3.2.0] - 2026-11-16
### Eklenenler
- **Bilet Düğmesi Kısa Kodu (`[konser_bilet_butonu]`):** Mevcut `<figure class="wp-block-image size-large">` yapısını koruyan `[konser_bilet_butonu url="..."]` kısa kodu eklendi. Ayarlar sekmesinden varsayılan bilet düğmesi görseli Ortam Kütüphanesinden seçilebilir veya URL olarak tanımlanabilir.
- **İlçe Ziyaret & Bilet Tıklama İstatistikleri:** Yönetim paneline **İstatistikler** sekmesi eklendi. Her ilçe sayfasının toplam görüntülenme sayısı (`[konser_ilce_yonlendir]` aktifken), bilet düğmesi tıklama sayısı (`[konser_bilet_butonu]`), tıklama oranı (CTR %) ve son etkileşim zamanı kaydedilir; CSV dışa aktarma ve sıfırlama desteği sunar.

## [3.1.1] - 2026-11-16
### Değiştirilenler
- **Arayüz ve Dokümantasyon Sadeleştirmesi:** Yönetim paneli ve dokümantasyonda yalnızca `[konser_takvimi]` kısa kodu belirtilecek şekilde sadeleştirildi.

## [3.1.0] - 2026-11-16
### Eklenenler & Değiştirilenler
- **Ayarlanabilir Yönlendirme Hedefi:** **Düğme Biçimleri, Renkler & Ayarlar** sekmesine `[konser_ilce_yonlendir]` kısa kodunun varsayılan yönlendirme adresini (varsayılan `/konserler`) belirleme alanı eklendi.
- **URL ve İlçe Adı Bilgilendirmesi:** Ayarlar sekmesine ve dokümantasyona, otomatik eşleşme için ilçe adının sayfa URL uzantısında yer alması gerektiğine dair açıklayıcı not eklendi.

## [3.0.0] - 2026-11-16
### Eklenenler & Değiştirilenler
- **Çift Seans Desteği (`12.00 ve 14.00`):** Yönetim paneline isteğe bağlı **2. Seans** alanı eklendi. İkinci seans girildiğinde düğmede saat `12.00 ve 14.00` biçiminde tek satırda gösterilir ve 2. seans saati geçene kadar konser aktif kalır.
- **İlçe Sayfası Yönlendirme Kısa Kodu (`[konser_ilce_yonlendir]`):** İlçe konser sayfalarına yerleştirildiğinde ilgili ilçede yaklaşan konser olup olmadığını denetler; aktif konser yoksa ziyaretçiyi otomatik belirlenen sayfaya (`302` geçici yönlendirme ile) yönlendirir.
- **CSV / JSON Dışa Aktarma Güncellemesi:** CSV dışa aktarımına `1. Seans` ve `2. Seans` sütunları eklendi.

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
### Eklenenler & Değiştirilenler
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
