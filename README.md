# Konser Takvimi (WordPress Eklentisi)

Konser ve etkinlik takvimini yönetmek, yaklaşan etkinlikleri renkli rozetler halinde sayfada göstermek, çift seanslı konserleri tek düğmede listelemek ve geçmiş konserleri otomatik olarak arşivlemek için geliştirilmiş veritabanı tabanlı WordPress eklentisidir.

## Özellikler

- **Takvim Kısa Kodu (`[konser_takvimi]` ve `[subadap_konserler]`):** Herhangi bir sayfaya veya yazıya eklendiğinde yalnızca tarihi ve son seans saati henüz geçmemiş konserleri (İstanbul saat dilimine göre) kronolojik olarak listeler.
- **Çift Seans Desteği (`12.00 ve 14.00`):** Aynı gün iki seans varsa 2. seans saati girilebilir; rozet üzerinde `12.00 ve 14.00` biçiminde gösterilir ve 2. seans saati dolana kadar konser aktif kalır.
- **İlçe Sayfası Yönlendirme Kısa Kodu (`[konser_ilce_yonlendir]`):** `ilce-konser` veya `ilce-konseri` sayfalarına eklendiğinde o ilçeye ait yaklaşan konser olup olmadığını kontrol eder; aktif konser yoksa ziyaretçiyi otomatik olarak `/konserler` sayfasına yönlendirir. İsteğe bağlı olarak `[konser_ilce_yonlendir ilce="Urla" hedef="/konserler"]` parametreleriyle özelleştirilebilir.
- **Otomatik `ilce-konseri` Uzantısı ve Canlı Sayfa Kontrolü:** İlçe/şehir yazıldığında URL uzantısını otomatik `ilce-konseri` olarak oluşturur; ilgili uzantıda yayımlanmış sayfa veya yazı varsa yeşil, yoksa kırmızı uyarıyla anlık bildirir.
- **Mevcut Yazı ve Sayfalardan Seçim:** Link uzantısı alanında sitenizdeki yayımlanmış yazı ve sayfalar arasında başlık veya URL uzantısına göre anlık arama/filtreleme yapabilirsiniz.
- **Otomatik Arşivleme:** Tarihi ve son seans saati geçen konserler otomatik olarak yönetim panelindeki açılır-kapanır **Arşivlenen Konserler** bölümüne taşınır, salt okunur hale getirilir ve siteden gizlenir.
- **JSON ve CSV Dışa Aktarma (Export):** Aktif, arşivlenmiş veya tüm konser kayıtlarını tek tıkla `.json` ya da Excel uyumlu (UTF-8 BOM) `.csv` dosyası olarak yedekleme imkânı sunar.
- **Canlı Düğme Biçimi & Renk Özelleştirme:** Düğmelerin köşe yuvarlaklığını, yüksekliğini, genişliğini, aralık boşluğunu ve 12'li renk paleti döngüsünü canlı önizleme ile düzenleyebilirsiniz.
- **Güvenli Kaldırma (Uninstall):** Varsayılan olarak eklenti silinse bile veritabanındaki konser kayıtları korunur; isteğe bağlı olarak ayarlardan tam temizlik aktif edilebilir.

## Kurulum

1. Eski sürüm yüklüyse WordPress **Eklentiler** sayfasından önce onu etkisizleştirip silin (ayarlarda silme seçeneği işaretli değilse veritabanı kayıtlarınız korunur).
2. `konser-takvimi-3.0.0.zip` arşivini indirin.
3. WordPress yönetim panelinde **Eklentiler > Yeni Eklenti Ekle > Eklenti Yükle** yolunu izleyin.
4. `.zip` dosyasını seçip yükleyin ve eklentiyi etkinleştirin.

## Kullanım

1. Sol menüdeki **Konser Takvimi** sekmesinden yaklaşan konserlerinizi (tek veya çift seans olarak) ekleyin ve kaydedin.
2. Takvimi göstermek istediğiniz sayfaya `[konser_takvimi]` (veya geriye dönük uyumlu `[subadap_konserler]`) kısa kodunu yerleştirin.
3. İlçe konser sayfalarının başına `[konser_ilce_yonlendir]` kısa kodunu yerleştirin; o ilçede aktif konser kalmadığında sayfa otomatik `/konserler` adresine yönlenecektir.
4. Rozetlerin renk ve boyutlarını özelleştirmek için **Düğme Biçimleri, Renkler & Ayarlar** sekmesini kullanın.

## Dosya Yapısı

```text
konser-takvimi/
├── konser-takvimi.php           # Ana eklenti dosyası, kısa kodlar ve yönlendirme mantığı
├── uninstall.php                # İsteğe bağlı veritabanı temizleme betiği
├── LICENSE                      # GNU GPLv3 lisans metni
├── README.md                    # Proje dokümantasyonu
├── CHANGELOG.md                 # Sürüm geçmişi
├── includes/
│   └── admin-page.php           # Yönetim paneli, dışa aktarma (export) ve ayar sekmeleri
└── assets/
    ├── css/
    │   ├── admin.css            # Yönetim paneli stilleri
    │   └── frontend.css         # Ön yüz rozet ve ızgara stilleri
    └── js/
        ├── admin.js             # Otomatik slug, sayfa varlık kontrolü ve canlı stil önizlemesi
        └── frontend.js          # Ön yüz filtreleme, çift seans biçimlendirme ve DOM oluşturma
```

## Lisans

Bu proje [GNU General Public License v3.0](LICENSE) altında lisanslanmıştır.
