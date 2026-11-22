# Konser Takvimi (WordPress Eklentisi)

[English](README.md) | **Türkçe**

Konser ve etkinlik takvimini yönetmek, yaklaşan etkinlikleri **BUGÜN / YARIN** rozetli renkli kartlar halinde sayfada göstermek, çift seanslı konserleri tek düğmede listelemek, ilçe konser sayfalarına **Takvime Ekle (Google Takvim & Apple/Outlook .ics)** düğmesi sunmak, Google arama zengin sonuçları için otomatik `MusicEvent` JSON-LD şeması üretmek, süresi dolan ilçe sayfalarını önbellek korumalı `302` ile yönlendirmek, giden bilet bağlantılarını ve takvim bağlantılarını **UTM parametreleriyle** etiketlemek ve günlük tekil ziyaretçi, dönüşüm oranı (CTR), sayfada kalma süresi ile gelen UTM kaynaklarını grafiklerle izlemek için geliştirilmiş veritabanı tabanlı bir WordPress eklentisidir.

> **3.x sürümünden mi güncelliyorsunuz?** 4.0.0 sürümünde kısa kod ve blok öznitelikleri İngilizce adlarla değiştirildi. Canlı bir siteyi güncellemeden önce [3.x sürümünden yükseltme](#3x-sürümünden-yükseltme) bölümüne bakın.

## Özellikler

- **Gutenberg Blokları (ve Geriye Dönük Uyumlu Kısa Kodlar):**
  - **Konser Takvimi (`konser-takvimi/takvim`):** Yalnızca tarihi ve son seans saati henüz geçmemiş konserleri (İstanbul saat dilimine göre) kronolojik olarak listeler. *(Eski `[konser_takvimi]` kısa kodu çalışmaya devam eder.)*
  - **Konser İlçe Yönlendirici & Takvime Ekle (`konser-takvimi/ilce-yonlendir`):** İlçede aktif konser varsa sayfa istatistiklerini toplar ve tek tıkla **Google Takvim** veya **Apple / Outlook (.ics)** dosyası indiren **Takvime Ekle** düğmesini gösterir; aktif konser yoksa `DONOTCACHEPAGE` ve `nocache_headers()` korumasıyla `302 Found` yönlendirmesi yapar. *(Eski `[konser_ilce_yonlendir]` kısa kodu çalışmaya devam eder.)*
  - **Konser Bilet Butonu (`konser-takvimi/bilet-butonu`):** CTR analitiği ve UTM etiketlemeyle entegre bilet satın alma görsel butonu ekler. *(Eski `[konser_bilet_butonu]` kısa kodu çalışmaya devam eder.)*
- **Bugün & Yarın Rozetleri:** Önbellek (cache) dostu biçimde İstanbul saat dilimine göre konser gününde kırmızı **BUGÜN**, bir gün öncesinde amber **YARIN** rozetlerini otomatik gösterir.
- **Çift Seans Desteği (`12.00 ve 14.00`):** Aynı gün iki seans varsa 2. seans saati girilebilir; kartta `12.00 ve 14.00` olarak gösterilir ve Takvime Ekle menüsünde her iki seans ayrı seçenek olarak sunulur.
- **Google Schema (JSON-LD `MusicEvent`) Desteği:** `<head>` bölümüne otomatik olarak `MusicEvent` (`description`, `startDate`, `endDate`, `location`, `performer`, `offers.validFrom`) yapılandırılmış verisi ekler.
- **UTM Etiketleme ve Takibi (4.0.0 ile geldi):** Giden bilet bağlantılarına ve takvim etkinliklerindeki sayfa bağlantısına `utm_*` parametreleri ekler, gelen `utm_*` trafiğini ilçe bazında kaydeder. Bkz. [UTM Parametreleri](#utm-parametreleri).
- **Günlük Ziyaretçi, CTR, Dönüşüm Oranı & Grafik:** Çerez kullanmadan (`localStorage`) günlük tekil ziyaretçileri, aktif kalma süresini ve bilet tıklamalarını ölçer; 14 günlük grafik ve HTML/CSV rapor sunar. İki oran yan yana gösterilir:
  - **CTR** = bilet tıklaması / sayfa gösterimi.
  - **Dönüşüm** = bilet bağlantısına tıklayan tekil kişi / tekil ziyaretçi (günlük, ilçe bazında; en çok %100). "Ziyaretçilerin kaçı gerçekten bilete gitti" sorusunun cevabıdır. Sıralama ölçütü, özet kartlar, ilçe tablosu, UTM tablosu, HTML/CSV raporlar ve haftalık e-posta içinde yer alır. 4.1.0 öncesi kayıtlarda tekil tıklayan sayısı tutulmadığı için eski satırlarda dönüşüm düşük görünür.

## UTM Parametreleri

Tüm UTM özellikleri **Konser Takvimi → Düğme Biçimleri, Renkler & Ayarlar → UTM Parametreleri** bölümünden açılıp kapatılabilir.

### Giden bilet bağlantıları

Bilet düğmesi ve izleyici betiğin yüklendiği sayfalardaki takip edilen bilet alan adlarına giden bağlantılar şu parametreleri alır:

| Parametre | Değer |
|---|---|
| `utm_source` | Ayarlardaki değer; boşsa site alan adı (örn. `example.org`) |
| `utm_medium` | Ayarlardaki değer (varsayılan `referral`) |
| `utm_campaign` | Sabit kampanya adınız; boşsa ilçe anahtarı (örn. `urla`) |
| `utm_content` | Blok/kısa kod düğmesi için `ticket-button`, otomatik algılanan bağlantılar için `ticket-link` |

Örnek: `https://biletinial.com/e/1?utm_source=example.org&utm_medium=referral&utm_campaign=urla&utm_content=ticket-button`

### Takvime Ekle bağlantıları

Her takvim kaydının açıklamasındaki sayfa bağlantısı, kanala ve seansa göre etiketlenir:

| Parametre | Değer |
|---|---|
| `utm_source` | `google-calendar` veya `ics-file` |
| `utm_medium` | `calendar` |
| `utm_campaign` | Sabit kampanya adınız; boşsa ilçe anahtarı |
| `utm_content` | `session-1` veya `session-2` |

### Gelen UTM trafiği

Ziyaretçi adreste `utm_source`, `utm_medium` veya `utm_campaign` bulunan bir sayfaya geldiğinde ziyaret (ve o ziyaret sırasındaki bilet tıklaması) kaydedilir ve **İstatistikler & Grafik → Trafik Kaynakları (UTM)** tablosunda kaynak / ortam / kampanya / ilçe bazında ziyaretçi, gösterim, bilet tıklaması ve CTR olarak gösterilir.

### Kurallar ve sınırlar

- Bağlantıda zaten bulunan `utm_*` parametreleri **asla ezilmez**; elle etiketlenmiş bağlantılar kendi değerlerini korur.
- İzleyici betik yalnızca dış `http(s)` bağlantılarını etiketler; iç bağlantılar etiketlenmez, böylece ziyaretçinin asıl trafik kaynağı analiz aracınızda ezilmez.
- UTM değerleri küçük harfli `[a-z0-9._-]` karakterlerine ve en çok 64 karaktere indirgenir (Türkçe harfler karşılıklarına çevrilir).
- Her ilçe için en fazla **40** farklı kaynak/ortam/kampanya kombinasyonu saklanır; fazlası `(other)` altında toplanır, böylece rastgele adres değerleri veritabanını şişiremez.
- `MusicEvent` JSON-LD içindeki adresler bilerek **etiketlenmez**; yapılandırılmış veri kanonik adresleri göstermelidir.
- Gelen UTM verisi tüm zamanları kapsar (rapor dönemine göre bölünmez). HTML rapor ve haftalık e-posta ilk 15 satırı listeler; CSV dışa aktarma hepsini ikinci bir blokta içerir.

### Gelen trafiğin sınıflandırılması (kanal bağlantıları)
Biletinial gibi bilet platformları kendi bağlantılarını zaten etiketler; giden bağlantıya UTM eklemeniz gerekmez. Önemli olan **sizin paylaştığınız** bağlantıların etiketli olmasıdır. **Araçlar & Tanılama → Kampanya bağlantı oluşturucu** ile (ör. Adana konseri): WhatsApp `whatsapp/messaging/adana`, Meta reklam `meta/cpc/adana`, YouTube reklam `youtube/cpc/adana`, e-posta `newsletter/email/adana`, Instagram organik `instagram/social/adana`, etiketsiz bağlantı doğrudan sayılır. Tabloda gruplanması için kaynak/ortam adlarını her kampanyada aynı (küçük harf) tutun. Uygulamalar yönlendiren bilgisini silebildiği için etiketsiz ziyaretler güvenilir biçimde kanala atfedilemez.

## Gizlilik

- İstatistikler çerez kullanmaz. Tarayıcının `localStorage` alanına yalnızca ilçe başına günlük bir "sayıldı" işareti (ziyaret ve bilet tıklaması için) yazılır.
- Quicksand yazı tipi varsayılan olarak Google Fonts'tan yüklenir. Google'a hiç bağlanılmaması için **Ayarlar → Gizlilik & Yazı Tipi** bölümünden kapatın; kartlar sistem yazı tipiyle gösterilir.
- Onay politikanızın (KVKK/GDPR) `localStorage` işaretlerini ve UTM istatistiklerini kapsayıp kapsamadığını ayrıca değerlendirin.

## Veritabanı Boyutu

Tüm veri `wp_options` tablosundaki üç satırda tutulur (autoload kapalı): konserler, istatistikler ve ayarlar. Bir konser kaydı yaklaşık 130 bayttır. İstatistikler her ilçe için 90 günlük günlük değerleri, tüm zaman toplamlarını ve en çok 40 UTM kombinasyonunu saklar. Bir ilçe en çok 15-25 KB yer kaplar ve en fazla 300 ilçe saklanır; bu yüzden istatistik satırı en kötü durumda bile yaklaşık 8 MB'ın altında kalır (bir düzine ilçe için yaklaşık 200 KB). Arşivlenmiş konserler siz silene kadar tutulur.

## Kurulum

**Gereksinimler:** WordPress 6.0+ ve PHP 8.4+ (eklenti PHP 8.4 ve 8.5 üzerinde test ediliyor).

1. Eski sürüm yüklüyse WordPress **Eklentiler** sayfasından önce etkisizleştirip silin (ayarlarda silme seçeneği işaretli değilse veritabanı kayıtlarınız korunur).
2. Eklenti `.zip` arşivini indirin.
3. WordPress yönetim panelinde **Eklentiler > Yeni Eklenti Ekle > Eklenti Yükle** yolunu izleyin.
4. `.zip` dosyasını seçip yükleyin ve eklentiyi etkinleştirin.

## Kullanım

1. Sol menüdeki **Konser Takvimi** sekmesinden yaklaşan konserlerinizi (tek veya çift seans olarak) ekleyin ve kaydedin.
2. Takvimi göstermek istediğiniz sayfaya **Konser Takvimi** bloğunu (veya `[konser_takvimi]` kısa kodunu) yerleştirin.
3. URL'sinde ilçe adı geçen konser sayfalarının başına **Konser İlçe Yönlendirici** bloğunu (veya `[konser_ilce_yonlendir]` kısa kodunu) yerleştirin. Ayarlar sekmesinde varsayılan bilet URL'sini tanımladıysanız bilet düğmesini göstermek istediğiniz yere sadece **Konser Bilet Butonu** bloğunu (veya `[konser_bilet_butonu]`) eklemeniz yeterlidir.
4. İlçe ziyaret, CTR dönüşüm sıralaması, UTM kaynakları ve bilet tıklama verilerini **İstatistikler & Grafik** sekmesinden takip edin (yönetici girişleri sayılmadığından test için gizli sekme kullanın).

### Kısa kod ve blok öznitelikleri

| Blok / kısa kod | Öznitelik | Açıklama |
|---|---|---|
| İlçe Yönlendirici (`[konser_ilce_yonlendir]`) | `district` | İlçe slug'ı. Varsayılan: geçerli sayfanın slug'ı. |
| | `redirect_target` | Yönlendirme yolu veya URL. Varsayılan: ayarlardaki değer. |
| | `show_calendar_button` | `1` (varsayılan) veya Takvime Ekle düğmesini gizlemek için `0`. |
| Bilet Butonu (`[konser_bilet_butonu]`) | `url` | Bilet URL'si. Varsayılan: ayarlardaki değer. |
| | `image` | Düğme görseli URL'si. Varsayılan: ayarlardaki değer. |
| | `district` | Analitik ve varsayılan `utm_campaign` için ilçe anahtarı. Varsayılan: sayfa slug'ı. |
| | `alt` | Görsel alternatif metni. |
| | `link_target` | HTML bağlantı hedefi, örn. `_blank`. |

Örnek: `[konser_ilce_yonlendir district="urla" redirect_target="/konserler"]`

**Yönlendirme hedefleri:** Blok veya kısa kodda verilen `redirect_target` bu siteye ya da ayarlardaki varsayılan hedefin alan adına gidebilir. Başka bir alan adı yok sayılır ve varsayılan hedef kullanılır; böylece içerik yazarları açık yönlendirme (open redirect) oluşturamaz. Başka bir alan adına yönlendirmek için o alan adını ayarlarda varsayılan hedef olarak girin.

## 3.x Sürümünden Yükseltme

4.0.0 sürümü Türkçe öznitelik adlarını İngilizce adlarla değiştirir. **Otomatik bir geri dönüş yoktur**, bu yüzden yükselttikten sonra mevcut içeriği güncelleyin:

| Nerede | Eski (3.x) | Yeni (4.0.0) |
|---|---|---|
| İlçe Yönlendirici bloğu / kısa kodu | `ilce` | `district` |
| İlçe Yönlendirici bloğu / kısa kodu | `hedef` | `redirect_target` |
| İlçe Yönlendirici bloğu / kısa kodu | `takvime_ekle` | `show_calendar_button` |
| Bilet Butonu bloğu / kısa kodu | `ilce` | `district` |
| Bilet Butonu bloğu / kısa kodu | `resim` | `image` |
| Bilet Butonu bloğu / kısa kodu | `hedef` | `link_target` |

- **Kısa kodlar:** Sayfaları düzenleyip öznitelik adlarını değiştirin, örn. `[konser_ilce_yonlendir ilce="urla"]` → `[konser_ilce_yonlendir district="urla"]`.
- **Bloklar:** WordPress bilinmeyen blok özniteliklerini sessizce yok sayar; bu yüzden `ilce` değeri girilmiş bir blok, değeri blok kenar çubuğundan yeniden girene kadar sayfa slug'ına döner.
- Blok adları (`konser-takvimi/...`), kısa kod adları, `subadap_konserler_*` veritabanı seçenekleri ve `{sehir}-konseri` slug biçimi değişmedi; kayıtlı konserleriniz ve istatistikleriniz etkilenmez.
- Giden bilet bağlantısı etiketleme 4.3.0'dan beri **varsayılan olarak kapalıdır** ve zaten `utm_*` parametresi taşıyan bağlantılara (örn. Biletinial) hiç dokunmaz. Gelen UTM kaydı açıktır.

## Geliştirme ve Testler

Eklenti mantığı, ihtiyaç duyduğu WordPress fonksiyonlarını taklit eden (`tests/wp_stubs.php`), bağımlılıksız testlerle kapsanır; WordPress kurulumu gerekmez:

```sh
./tests/run.sh   # PHP lint, JS sözdizimi, PHP testleri ve tracker.js testleri
```

GitHub Actions aynı kontrolleri PHP 8.4 – 8.5 ve Node 26 üzerinde çalıştırır (`.github/workflows/tests.yml`); güvenlik workflow'u (`security.yml`) ayrıca çalışır. Taklit fonksiyonlar WordPress'i yalnızca yaklaşık olarak temsil eder; bir deneme sitesinde elle kontrolün yerini tutmaz.

## Dosya Yapısı

```text
konser-takvimi/
├── konser-takvimi.php           # Ana eklenti dosyası, JSON-LD MusicEvent, 302 yönlendirme, Takvime Ekle, UTM ve analitik
├── uninstall.php                # İsteğe bağlı veritabanı ve zamanlanmış görev temizleme betiği
├── LICENSE                      # GNU GPLv3 lisans metni
├── README.md                    # Proje dokümantasyonu (İngilizce)
├── README.tr.md                 # Proje dokümantasyonu (Türkçe)
├── CHANGELOG.md                 # Sürüm geçmişi
├── .github/workflows/
│   ├── tests.yml                # Lint + birim testleri (PHP 8.4-8.5, Node 26)
│   ├── security.yml             # WPCS güvenlik kuralları, Semgrep ve Gitleaks
│   └── release.yml              # v* etiketlerinde eklenti zip'ini üretir ve GitHub sürümü yayımlar
├── tests/                       # WordPress taklitli birim testleri ve tracker.js testleri (./tests/run.sh)
├── includes/
│   ├── admin-page.php           # Yönetim paneli, CTR sıralamalı istatistikler, UTM kaynakları, grafik, dışa aktarma ve ayar sekmeleri
│   └── blocks.php               # Gutenberg blok kaydı ve sunucu taraflı önizleme/render işleyicileri
└── assets/
    ├── css/
    │   ├── admin.css            # Yönetim paneli stilleri
    │   └── frontend.css         # Ön yüz kartları, Bugün/Yarın rozetleri ve Takvime Ekle stilleri
    └── js/
        ├── admin.js             # Otomatik slug, sayfa kontrolü, medya seçici, önizleme ve Canvas grafik çizimi
        ├── blocks-editor.js     # Gutenberg blok editör arayüzü
        ├── frontend.js          # Ön yüz filtreleme, Bugün/Yarın rozetleri ve .ics takvim dosyası üretimi
        └── tracker.js           # Tekil ziyaretçi, aktif kalma süresi, bilet tıklama izleme ve UTM kaydı/etiketleme betiği
```

## Lisans

Bu proje [GNU General Public License v3.0](LICENSE) altında lisanslanmıştır.

## Çeviriler
Tüm metinler çevrilebilir (text domain `konser-takvimi`, kaynak dil Türkçe). Dil, **Ayarlar → Genel → Site Dili**'nden otomatik alınır (yöneticiler profillerinden değiştirebilir). İngilizce katalog dahildir ve kendi kataloğu olmayan tüm diller için kullanılır; Türkçe sitelerde özgün metinler görünür.

Yeni dil eklemek için `languages/konser-takvimi.pot` dosyasını `languages/konser-takvimi-<yerel>.po` olarak kopyalayın (örn. `de_DE`), çevirin (Poedit kullanılabilir) ve `.mo` dosyasını derleyin: `python3 tools/build-catalog.py --mo-only languages/konser-takvimi-de_DE.po`. Metin ekleyip değiştirdikten sonra şablonu `php tools/extract-strings.php` ile yenileyin.

## Kod yapısı
`konser-takvimi.php` yalnızca eklentiyi başlatır. Özellikler `includes/` altındadır: `events-time.php` (saat dilimi ve konser saatleri), `settings.php`, `utm.php`, `stats-recording.php` (tamponlu, kilitli yazma ve takip uç noktası), `reports.php`, `cron.php`, `assets.php`, `schema.php`, `redirect.php`, `calendar.php`, `shortcodes.php`, `blocks.php`, `i18n.php`; yönetim arayüzü `admin-page.php`, `admin-tools.php` ve `includes/admin/` altındaki sekme dosyalarıdır.
