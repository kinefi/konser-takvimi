# Konser Takvimi (WordPress Eklentisi)

Konser ve etkinlik takvimini yönetmek, yaklaşan etkinlikleri renkli rozetler halinde sayfada göstermek, çift seanslı konserleri tek düğmede listelemek, ilçe sayfalarını otomatik yönlendirmek, günlük tekil ziyaretçi ve sayfada kalma süresi analitiğini grafiklerle izlemek ve indirilebilir/e-posta raporları üretmek için geliştirilmiş veritabanı tabanlı WordPress eklentisidir.

## Özellikler

- **Takvim Kısa Kodu (`[konser_takvimi]`):** Herhangi bir sayfaya veya yazıya eklendiğinde yalnızca tarihi ve son seans saati henüz geçmemiş konserleri (İstanbul saat dilimine göre) kronolojik olarak listeler.
- **Çift Seans Desteği (`12.00 ve 14.00`):** Aynı gün iki seans varsa 2. seans saati girilebilir; rozet üzerinde `12.00 ve 14.00` biçiminde gösterilir ve 2. seans saati dolana kadar konser aktif kalır.
- **İlçe Sayfası Yönlendirme Kısa Kodu (`[konser_ilce_yonlendir]`):** İlçe konser sayfalarına eklendiğinde o ilçeye ait yaklaşan konser olup olmadığını kontrol eder; aktif konser yoksa ziyaretçiyi otomatik olarak Ayarlar sekmesinde tanımlanan yönlendirme sayfasına (varsayılan `/konserler`) yönlendirir, aktif konser varsa ilçe sayfasının ziyaretçi ve süre istatistiklerini toplar.
  - **Önemli URL Notu:** Kısa kod parametresiz (`[konser_ilce_yonlendir]`) kullanıldığında kontrolü sayfanın URL uzantısına (slug) göre yapar; bu nedenle sayfa URL'sinde ilçe adının mutlaka yer alması gerekir (örneğin `/urla-konseri` veya `/urla-konser`). URL'de ilçe adı geçmiyorsa `[konser_ilce_yonlendir ilce="Urla"]` parametresi kullanılabilir.
- **Bilet Düğmesi Kısa Kodu (`[konser_bilet_butonu]`) ve Otomatik Tıklama Takibi:**
  - Ayarlar sekmesinden **Varsayılan Bilet Satış URL'si** ve **Varsayılan Bilet Düğmesi Görseli** tanımlandığında sayfalarda parametresiz olarak doğrudan `[konser_bilet_butonu]` kullanılabilir.
  - Kısa kod kullanılmayan eski sayfalarda dahi, sayfada `[konser_ilce_yonlendir]` varsa Ayarlar sekmesindeki bilet alan adlarına (`biletinial.com`, `biletix.com`, `passo.com.tr`, `bubilet.com.tr`, `mobilet.com`, `biletino.com`) veya varsayılan bilet görseline yapılan tıklamalar otomatik yakalanır.
- **Günlük Ziyaretçi, Sayfada Kalma Süresi, Grafik & Rapor İndirme:**
  - Çerez kullanmadan (`localStorage`) günlük tekil ziyaretçileri ve toplam gösterimleri ayırır; kullanıcının sekmede aktif kaldığı süreyi ölçerek ilçe bazında ortalama kalma süresini hesaplar.
  - **İstatistikler & Grafik** sekmesinde ilçe bazında filtrelenebilir son 14 günlük çubuk grafik sunar ve **Son 7 Gün / Son 14 Gün / Son 30 Gün / Tüm Zamanlar** dönemleri için yazdırılabilir görsel rapor (HTML/PDF) veya Excel tablosu (CSV) indirme imkânı verir.
- **Haftalık Yönetici E-Posta Raporu (`wp_cron`):** Ayarlar sekmesinde seçilen gün (varsayılan Pazartesi saat 09:00) son 7 günün özet tablosunu yönetici e-posta adresine HTML rapor olarak gönderir.
- **Otomatik `ilce-konseri` Uzantısı, Arşivleme & JSON/CSV Dışa Aktarma:** İlçe yazıldığında URL uzantısını otomatik `ilce-konseri` olarak oluşturur, sayfa varlığını renkli olarak doğrular, geçmiş konserleri otomatik arşivler ve yedekleme imkânı sunar.
- **Otomatik Arşivleme:** Tarihi ve son seans saati geçen konserler otomatik olarak yönetim panelindeki açılır-kapanır **Arşivlenen Konserler** bölümüne taşınır, salt okunur hale getirilir ve siteden gizlenir.
- **JSON ve CSV Dışa Aktarma (Export):** Aktif, arşivlenmiş veya tüm konser kayıtlarını tek tıkla `.json` ya da Excel uyumlu (UTF-8 BOM) `.csv` dosyası olarak yedekleme imkânı sunar.
- **Canlı Düğme Biçimi & Renk Özelleştirme:** Düğmelerin köşe yuvarlaklığını, yüksekliğini, genişliğini, aralık boşluğunu ve 12'li renk paleti döngüsünü canlı önizleme ile düzenleyebilirsiniz.

## Kurulum

1. Eski sürüm yüklüyse WordPress **Eklentiler** sayfasından önce onu etkisizleştirip silin (ayarlarda silme seçeneği işaretli değilse veritabanı kayıtlarınız korunur).
2. `konser-takvimi-3.4.1.zip` arşivini indirin.
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
├── konser-takvimi.php           # Ana eklenti dosyası, kısa kodlar, yönlendirme, analitik ve haftalık rapor cron'u
├── uninstall.php                # İsteğe bağlı veritabanı ve zamanlanmış görev temizleme betiği
├── LICENSE                      # GNU GPLv3 lisans metni
├── README.md                    # Proje dokümantasyonu
├── CHANGELOG.md                 # Sürüm geçmişi
├── includes/
│   └── admin-page.php           # Yönetim paneli, grafikli istatistikler, dışa aktarma ve ayar sekmeleri
└── assets/
    ├── css/
    │   ├── admin.css            # Yönetim paneli stilleri
    │   └── frontend.css         # Ön yüz rozet ve ızgara stilleri
    └── js/
        ├── admin.js             # Otomatik slug, sayfa kontrolü, medya seçici, önizleme ve Canvas grafik çizimi
        ├── frontend.js          # Ön yüz filtreleme, çift seans biçimlendirme ve DOM oluşturma
        └── tracker.js           # Tekil ziyaretçi, aktif kalma süresi ve bilet tıklama izleme betiği
```

## Lisans

Bu proje [GNU General Public License v3.0](LICENSE) altında lisanslanmıştır.
