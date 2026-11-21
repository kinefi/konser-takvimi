# Konser Takvimi (WordPress Eklentisi)

Konser ve etkinlik takvimini yönetmek, yaklaşan etkinlikleri **BUGÜN / YARIN** rozetli renkli kartlar halinde sayfada göstermek, çift seanslı konserleri tek düğmede listelemek, ilçe konser sayfalarına **Takvime Ekle (Google Takvim & Apple/Outlook .ics)** düğmesi sunmak, Google arama zengin sonuçları için otomatik `MusicEvent` JSON-LD şeması üretmek, ilçe sayfalarını önbellek korumalı `302` ile yönlendirmek ve günlük tekil ziyaretçi, dönüşüm oranı (CTR) ile sayfada kalma süresi analitiğini grafiklerle izlemek için geliştirilmiş veritabanı tabanlı WordPress eklentisidir.

## Özellikler

- **Gutenberg Blokları (ve Geriye Dönük Uyumlu Kısa Kodlar):**
  - **Konser Takvimi (`konser-takvimi/takvim`):** Yalnızca tarihi ve son seans saati henüz geçmemiş konserleri (İstanbul saat dilimine göre) kronolojik olarak listeler. *(Eski `[konser_takvimi]` kısa kodu geriye dönük uyumludur.)*
  - **Konser İlçe Yönlendirici & Takvime Ekle (`konser-takvimi/ilce-yonlendir`):** İlçede aktif konser varsa sayfa istatistiklerini toplar ve tek tıkla **Google Takvim** veya **Apple / Outlook (.ics)** dosyası indiren **Takvime Ekle** düğmesini gösterir; aktif konser yoksa `DONOTCACHEPAGE` ve `nocache_headers()` korumasıyla `302 Found` yönlendirmesi yapar. *(Eski `[konser_ilce_yonlendir]` kısa kodu geriye dönük uyumludur.)*
  - **Konser Bilet Butonu (`konser-takvimi/bilet-butonu`):** CTR analitiği ile entegre bilet satın alma görsel butonu ekler. *(Eski `[konser_bilet_butonu]` kısa kodu geriye dönük uyumludur.)*
- **Bugün & Yarın Rozetleri:** Önbellek (cache) dostu biçimde İstanbul saat dilimine göre konser gününde kırmızı **BUGÜN**, bir gün öncesinde amber **YARIN** rozetlerini otomatik gösterir.
- **Çift Seans Desteği (`12.00 ve 14.00`):** Aynı gün iki seans varsa 2. seans saati girilebilir; kartta `12.00 ve 14.00` olarak gösterilir ve Takvime Ekle menüsünde her iki seans ayrı seçenek olarak sunulur.
- **Google Schema (JSON-LD `MusicEvent`) Desteği:** `<head>` bölümüne otomatik olarak `MusicEvent` (`description`, `startDate`, `endDate`, `location`, `performer`, `offers.validFrom`) yapılandırılmış verisi ekler.
- **Günlük Ziyaretçi, Dönüşüm Oranı (CTR) Sıralaması & Grafik:** Çerez kullanmadan (`localStorage`) günlük tekil ziyaretçileri, aktif kalma süresini ve bilet tıklamalarını ölçer; 14 günlük grafik ve HTML/CSV rapor sunar.

## Kurulum

1. Eski sürüm yüklüyse WordPress **Eklentiler** sayfasından önce etkisizleştirip silin (ayarlarda silme seçeneği işaretli değilse veritabanı kayıtlarınız korunur).
2. `konser-takvimi-3.6.0.zip` arşivini indirin.
3. WordPress yönetim panelinde **Eklentiler > Yeni Eklenti Ekle > Eklenti Yükle** yolunu izleyin.
4. `.zip` dosyasını seçip yükleyin ve eklentiyi etkinleştirin.

## Kullanım

1. Sol menüdeki **Konser Takvimi** sekmesinden yaklaşan konserlerinizi (tek veya çift seans olarak) ekleyin ve kaydedin.
2. Takvimi göstermek istediğiniz sayfaya `[konser_takvimi]` kısa kodunu yerleştirin.
3. URL'sinde ilçe adı geçen konser sayfalarının başına `[konser_ilce_yonlendir]` kısa kodunu yerleştirin. Ayarlar sekmesinde varsayılan bilet URL'sini tanımladıysanız bilet düğmesini göstermek istediğiniz yere sadece `[konser_bilet_butonu]` yazmanız yeterlidir.
4. İlçe ziyaret, CTR dönüşüm sıralaması ve bilet tıklama verilerini **İstatistikler & Grafik** sekmesinden takip edin (yönetici girişleri sayılmadığından test için gizli sekme kullanın).

## Dosya Yapısı

```text
konser-takvimi/
├── konser-takvimi.php           # Ana eklenti dosyası, JSON-LD MusicEvent, 302 yönlendirme, Takvime Ekle ve analitik
├── uninstall.php                # İsteğe bağlı veritabanı ve zamanlanmış görev temizleme betiği
├── LICENSE                      # GNU GPLv3 lisans metni
├── README.md                    # Proje dokümantasyonu
├── CHANGELOG.md                 # Sürüm geçmişi
├── includes/
│   ├── admin-page.php           # Yönetim paneli, CTR sıralamalı istatistikler, grafik, dışa aktarma ve ayar sekmeleri
│   └── blocks.php               # Gutenberg blok kaydı ve sunucu taraflı önizleme/render işleyicileri
└── assets/
    ├── css/
    │   ├── admin.css            # Yönetim paneli stilleri
    │   └── frontend.css         # Ön yüz kartları, Bugün/Yarın rozetleri ve Takvime Ekle stilleri
    └── js/
        ├── admin.js             # Otomatik slug, sayfa kontrolü, medya seçici, önizleme ve Canvas grafik çizimi
        ├── blocks-editor.js     # Gutenberg blok editör arayüzü
        ├── frontend.js          # Ön yüz filtreleme, Bugün/Yarın rozetleri ve .ics takvim dosyası üretimi
        └── tracker.js           # Tekil ziyaretçi, aktif kalma süresi ve bilet tıklama izleme betiği
```

## Lisans

Bu proje [GNU General Public License v3.0](LICENSE) altında lisanslanmıştır.
