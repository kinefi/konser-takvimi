# Konser Takvimi (WordPress Eklentisi)

Konser ve etkinlik takvimini yönetmek, yaklaşan etkinlikleri renkli rozetler halinde sayfada göstermek, çift seanslı konserleri tek düğmede listelemek, ilçe sayfalarını otomatik yönlendirmek ve önbellek uyumlu sayfa ziyareti ile bilet tıklama istatistiklerini toplamak için geliştirilmiş veritabanı tabanlı WordPress eklentisidir.

## Özellikler

- **Takvim Kısa Kodu (`[konser_takvimi]`):** Herhangi bir sayfaya veya yazıya eklendiğinde yalnızca tarihi ve son seans saati henüz geçmemiş konserleri (İstanbul saat dilimine göre) kronolojik olarak listeler.
- **Çift Seans Desteği (`12.00 ve 14.00`):** Aynı gün iki seans varsa 2. seans saati girilebilir; rozet üzerinde `12.00 ve 14.00` biçiminde gösterilir ve 2. seans saati dolana kadar konser aktif kalır.
- **İlçe Sayfası Yönlendirme Kısa Kodu (`[konser_ilce_yonlendir]`):** İlçe konser sayfalarına eklendiğinde o ilçeye ait yaklaşan konser olup olmadığını kontrol eder; aktif konser yoksa ziyaretçiyi otomatik olarak Ayarlar sekmesinde tanımlanan yönlendirme sayfasına (varsayılan `/konserler`) yönlendirir, aktif konser varsa ilçe sayfasının ziyaret sayacını artırır.
  - **Önemli URL Notu:** Kısa kod parametresiz (`[konser_ilce_yonlendir]`) kullanıldığında kontrolü sayfanın URL uzantısına (slug) göre yapar; bu nedenle sayfa URL'sinde ilçe adının mutlaka yer alması gerekir (örneğin `/urla-konseri` veya `/urla-konser`). URL'de ilçe adı geçmiyorsa `[konser_ilce_yonlendir ilce="Urla"]` parametresi kullanılabilir.
- **Bilet Düğmesi Kısa Kodu (`[konser_bilet_butonu]`) ve Otomatik Tıklama Takibi:**
  - Ayarlar sekmesinden **Varsayılan Bilet Satış URL'si** ve **Varsayılan Bilet Düğmesi Görseli** tanımlandığında sayfalarda parametresiz olarak doğrudan `[konser_bilet_butonu]` kullanılabilir. Gerekirse tekil sayfalarda `url="..."`, `resim="..."`, `ilce="..."`, `alt="..."`, `hedef="_blank"` parametreleriyle özelleştirilebilir.
  - Kısa kod kullanılmayan eski sayfalarda dahi, sayfada `[konser_ilce_yonlendir]` varsa Ayarlar sekmesindeki bilet alan adlarına (`biletinial.com`, `biletix.com`, `passo.com.tr`, `bubilet.com.tr`, `mobilet.com`, `biletino.com`) veya varsayılan bilet görseline yapılan tıklamalar otomatik yakalanır.
- **Önbellek (Cache) Uyumlu İlçe Ziyaret & Bilet Tıklama İstatistikleri:** Sayfa görüntülenmeleri ve bilet tıklamaları `tracker.js` üzerinden AJAX ile kaydedildiği için LiteSpeed Cache, WP Rocket veya Cloudflare gibi önbellekleme eklentileri aktifken dahi doğru sayım yapar. Yönetici/editör oturumları istatistiklere dahil edilmez.
- **Otomatik `ilce-konseri` Uzantısı ve Canlı Sayfa Kontrolü:** İlçe/şehir yazıldığında URL uzantısını otomatik `ilce-konseri` olarak oluşturur; ilgili uzantıda yayımlanmış sayfa veya yazı varsa yeşil, yoksa kırmızı uyarıyla anlık bildirir.
- **Otomatik Arşivleme:** Tarihi ve son seans saati geçen konserler otomatik olarak yönetim panelindeki açılır-kapanır **Arşivlenen Konserler** bölümüne taşınır, salt okunur hale getirilir ve siteden gizlenir.
- **JSON ve CSV Dışa Aktarma (Export):** Aktif, arşivlenmiş veya tüm konser kayıtlarını tek tıkla `.json` ya da Excel uyumlu (UTF-8 BOM) `.csv` dosyası olarak yedekleme imkânı sunar.
- **Canlı Düğme Biçimi & Renk Özelleştirme:** Düğmelerin köşe yuvarlaklığını, yüksekliğini, genişliğini, aralık boşluğunu ve 12'li renk paleti döngüsünü canlı önizleme ile düzenleyebilirsiniz.

## Kurulum

1. Eski sürüm yüklüyse WordPress **Eklentiler** sayfasından önce onu etkisizleştirip silin (ayarlarda silme seçeneği işaretli değilse veritabanı kayıtlarınız korunur).
2. `konser-takvimi-3.3.0.zip` arşivini indirin.
3. WordPress yönetim panelinde **Eklentiler > Yeni Eklenti Ekle > Eklenti Yükle** yolunu izleyin.
4. `.zip` dosyasını seçip yükleyin ve eklentiyi etkinleştirin.

## Kullanım

1. Sol menüdeki **Konser Takvimi** sekmesinden yaklaşan konserlerinizi (tek veya çift seans olarak) ekleyin ve kaydedin.
2. Takvimi göstermek istediğiniz sayfaya `[konser_takvimi]` kısa kodunu yerleştirin.
3. URL'sinde ilçe adı geçen konser sayfalarının başına `[konser_ilce_yonlendir]` kısa kodunu yerleştirin. Ayarlar sekmesinde varsayılan bilet URL'sini tanımladıysanız bilet düğmesini göstermek istediğiniz yere sadece `[konser_bilet_butonu]` yazmanız yeterlidir.
4. İlçe ziyaret ve bilet tıklama verilerini **İstatistikler** sekmesinden takip edin (yönetici girişleri sayılmadığından test için gizli sekme kullanın).

## Dosya Yapısı

```text
konser-takvimi/
├── konser-takvimi.php           # Ana eklenti dosyası, kısa kodlar, yönlendirme ve istatistik mantığı
├── uninstall.php                # İsteğe bağlı veritabanı temizleme betiği
├── LICENSE                      # GNU GPLv3 lisans metni
├── README.md                    # Proje dokümantasyonu
├── CHANGELOG.md                 # Sürüm geçmişi
├── includes/
│   └── admin-page.php           # Yönetim paneli, istatistikler, dışa aktarma ve ayar sekmeleri
└── assets/
    ├── css/
    │   ├── admin.css            # Yönetim paneli stilleri
    │   └── frontend.css         # Ön yüz rozet ve ızgara stilleri
    └── js/
        ├── admin.js             # Otomatik slug, sayfa varlık kontrolü, medya seçici ve canlı önizleme
        ├── frontend.js          # Ön yüz filtreleme, çift seans biçimlendirme ve DOM oluşturma
        └── tracker.js           # Önbellek uyumlu görüntülenme ve bilet tıklama izleme betiği
```

## Lisans

Bu proje [GNU General Public License v3.0](LICENSE) altında lisanslanmıştır.
