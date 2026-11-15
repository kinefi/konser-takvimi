# Şubadap Konser Takvimi (WordPress Eklentisi)

**Şubadap Çocuk** konser takvimini yönetmek, yaklaşan etkinlikleri renkli rozetler halinde sayfada göstermek ve geçmiş konserleri otomatik olarak arşivlemek için geliştirilmiş hafif ve veritabanı tabanlı WordPress eklentisidir.

## Özellikler

- **Kısa Kod Desteği (`[subadap_konserler]`):** Herhangi bir sayfaya veya yazıya eklendiğinde yalnızca tarihi ve saati henüz geçmemiş konserleri (İstanbul saat dilimine göre) kronolojik olarak listeler.
- **Otomatik `ilce-konseri` Uzantısı ve Canlı Sayfa Kontrolü:** İlçe/şehir yazıldığında URL uzantısını otomatik `ilce-konseri` olarak oluşturur; ilgili uzantıda yayımlanmış sayfa veya yazı varsa yeşil, yoksa kırmızı uyarıyla anlık bildirir.
- **Mevcut Yazı ve Sayfalardan Seçim:** Link uzantısı alanında sitenizdeki yayımlanmış yazı ve sayfalar arasında başlık veya URL uzantısına göre anlık arama/filtreleme yapabilirsiniz.
- **Otomatik Arşivleme:** Tarihi geçen konserler otomatik olarak yönetim panelindeki açılır-kapanır **Arşivlenen Konserler** bölümüne taşınır, salt okunur hale getirilir ve siteden gizlenir.
- **JSON ve CSV Dışa Aktarma (Export):** Aktif, arşivlenmiş veya tüm konser kayıtlarını tek tıkla `.json` ya da Excel uyumlu (UTF-8 BOM) `.csv` dosyası olarak yedekleme imkânı sunar.
- **Canlı Düğme Biçimi & Renk Özelleştirme:** Düğmelerin köşe yuvarlaklığını, yüksekliğini, genişliğini, aralık boşluğunu ve 12'li renk paleti döngüsünü canlı önizleme ile düzenleyebilirsiniz.
- **Güvenli Kaldırma (Uninstall):** Varsayılan olarak eklenti silinse bile veritabanındaki konser kayıtları korunur; isteğe bağlı olarak ayarlardan tam temizlik aktif edilebilir.

## Kurulum

1. Bu depoyu `.zip` olarak indirin (veya `subadap-konseriler` klasörünü sıkıştırın).
2. WordPress yönetim panelinde **Eklentiler > Yeni Eklenti Ekle > Eklenti Yükle** yolunu izleyin.
3. `.zip` dosyasını seçip yükleyin ve eklentiyi etkinleştirin.

## Kullanım

1. Sol menüdeki **Konser Takvimi** sekmesinden yaklaşan konserlerinizi ekleyin ve kaydedin.
2. Takvimi göstermek istediğiniz sayfaya `[subadap_konserler]` kısa kodunu yerleştirin.
3. Rozetlerin renk ve boyutlarını özelleştirmek için **Düğme Biçimleri, Renkler & Ayarlar** sekmesini kullanın.

## Dosya Yapısı

```text
subadap-konseriler/
├── subadap-konseriler.php      # Ana eklenti dosyası, kısa kod ve CSS değişken çıktısı
├── uninstall.php              # İsteğe bağlı veritabanı temizleme betiği
├── LICENSE                    # GNU GPLv3 lisans metni
├── README.md                  # Proje dokümantasyonu
├── CHANGELOG.md               # Sürüm geçmişi
├── includes/
│   └── admin-page.php         # Yönetim paneli, dışa aktarma (export) ve ayar sekmeleri
└── assets/
    ├── css/
    │   └── konserler.css      # Ön yüz rozet ve ızgara stilleri
    └── js/
        ├── admin.js           # Otomatik slug, sayfa varlık kontrolü ve canlı stil önizlemesi
        └── konserler.js       # Ön yüz filtreleme ve DOM oluşturma
```

## Lisans

Bu proje [GNU General Public License v3.0](LICENSE) altında lisanslanmıştır.
