# Konser Takvimi (WordPress Plugin)

**English** | [Türkçe](README.tr.md)

A database-backed WordPress plugin for managing a concert and event calendar. It shows upcoming events as colourful cards with **TODAY / TOMORROW** badges (rendered as `BUGÜN` / `YARIN` on the site), lists double-session concerts on a single card, offers an **Add to Calendar** button (Google Calendar and Apple/Outlook `.ics`) on district concert pages, generates `MusicEvent` JSON-LD for Google rich results, redirects expired district pages with a cache-safe `302`, tags outbound ticket links and calendar links with **UTM parameters**, and tracks daily unique visitors, click-through rate (CTR), time on page and incoming UTM sources with charts.

> **Upgrading from 3.x?** Version 4.0.0 renames the shortcode and block attributes to English. See [Upgrading from 3.x](#upgrading-from-3x) before updating a live site.

## Features

- **Gutenberg blocks (with backward-compatible shortcodes):**
  - **Concert Calendar (`konser-takvimi/takvim`):** Lists only concerts whose date and last session time have not passed yet (Istanbul time zone), in chronological order. *(The legacy `[konser_takvimi]` shortcode still works.)*
  - **District Redirector & Add to Calendar (`konser-takvimi/ilce-yonlendir`):** When the district has an active concert, it collects page statistics and shows an **Add to Calendar** button that opens Google Calendar or downloads an Apple/Outlook (`.ics`) file with one click. When there is no active concert, it sends a `302 Found` redirect protected by `DONOTCACHEPAGE` and `nocache_headers()`. *(The legacy `[konser_ilce_yonlendir]` shortcode still works.)*
  - **Concert Ticket Button (`konser-takvimi/bilet-butonu`):** Adds an image button for buying tickets, integrated with CTR analytics and UTM tagging. *(The legacy `[konser_bilet_butonu]` shortcode still works.)*
- **Today & Tomorrow badges:** Cache-friendly red **TODAY** badge on the day of the concert and amber **TOMORROW** badge one day before, calculated in the Istanbul time zone.
- **Double-session support (`12.00 ve 14.00`):** If there are two sessions on the same day, enter the second session time; the card shows both times and the Add to Calendar menu offers each session separately.
- **Google structured data (JSON-LD `MusicEvent`):** Automatically adds `MusicEvent` data (`description`, `startDate`, `endDate`, `location`, `performer`, `offers.validFrom`) to `<head>`.
- **UTM tagging and tracking (new in 4.0.0):** Adds `utm_*` parameters to outbound ticket links and to the page link inside calendar events, and records incoming `utm_*` traffic per district. See [UTM parameters](#utm-parameters).
- **Daily unique visitors, CTR, conversion rate & chart:** Measures daily unique visitors, active time on page and ticket clicks without cookies (`localStorage`), with a 14-day chart and HTML/CSV reports. Two ratios are shown side by side:
  - **CTR** = ticket clicks / page views.
  - **Conversion** = unique people who clicked a ticket link / unique visitors (per day, per district; capped at 100%). This is the "how many visitors actually went to buy" number. It is available as a sort option, in the summary cards, the district table, the UTM table, the HTML/CSV reports and the weekly e-mail. Data recorded before 4.1.0 has no unique-clicker counts, so older rows understate the conversion rate.

## UTM parameters

All UTM features can be switched on or off in **Konser Takvimi → Düğme Biçimleri, Renkler & Ayarlar → UTM Parametreleri**.

### Outbound ticket links

The ticket button, and any link to one of the tracked ticket domains on a page that loads the tracker, gets these parameters:

| Parameter | Value |
|---|---|
| `utm_source` | Your setting, or the site host when empty (e.g. `example.org`) |
| `utm_medium` | Your setting (default `referral`) |
| `utm_campaign` | Your fixed campaign, or the district key when empty (e.g. `urla`) |
| `utm_content` | `ticket-button` for the block/shortcode button, `ticket-link` for auto-detected links |

Example: `https://biletinial.com/e/1?utm_source=example.org&utm_medium=referral&utm_campaign=urla&utm_content=ticket-button`

### Add to Calendar links

The page link inside the event description of each calendar entry is tagged per channel and per session:

| Parameter | Value |
|---|---|
| `utm_source` | `google-calendar` or `ics-file` |
| `utm_medium` | `calendar` |
| `utm_campaign` | Your fixed campaign, or the district key |
| `utm_content` | `session-1` or `session-2` |

### Incoming UTM traffic

When a visitor lands on a page with `utm_source`, `utm_medium` or `utm_campaign` in the URL, the visit (and any ticket click made during that visit) is recorded and shown in **İstatistikler & Grafik → Trafik Kaynakları (UTM)**: visitors, page views, ticket clicks and CTR per source / medium / campaign / district.

### Rules and limits

- Existing `utm_*` parameters on a link are **never overwritten**, so manually tagged links keep their values.
- Only external `http(s)` links are tagged by the tracker script; internal links are never tagged, so a visitor's original traffic source is not overwritten in your analytics tool.
- UTM values are normalised to lowercase `[a-z0-9._-]` and at most 64 characters (Turkish letters are transliterated).
- At most **40** distinct source/medium/campaign combinations are stored per district; anything beyond that is merged into an `(other)` bucket so arbitrary URL values cannot bloat the database.
- The `MusicEvent` JSON-LD URLs are intentionally **not** tagged, because structured data should point at canonical URLs.
- Incoming UTM data is all-time (it is not split by report period). The HTML report and the weekly e-mail list the top 15 rows; the CSV export contains all of them in a second block.

### Classifying incoming traffic (channel links)
Ticket platforms such as Biletinial tag their own links, so you do not need outbound tagging. What matters is tagging the links **you share**. Use **Tools & Diagnostics → Campaign link builder**, e.g. for an Adana concert page:

| Channel | `utm_source` | `utm_medium` | `utm_campaign` |
|---|---|---|---|
| WhatsApp | `whatsapp` | `messaging` | `adana` |
| Meta ads | `meta` | `cpc` | `adana` |
| YouTube ads | `youtube` | `cpc` | `adana` |
| Newsletter | `newsletter` | `email` | `adana` |
| Instagram (organic) | `instagram` | `social` | `adana` |
| Plain link | – | – | – (counted as direct) |

Keep the same lowercase source/medium names on every campaign so the table groups them. Apps may strip referrers, so untagged visits cannot be attributed reliably; only tagged links are classified.

## Statistics archive

Statistics belong to a district (the page slug), not to a single concert. To start each concert from zero without losing history, the plugin keeps an **archive** (tab **İstatistik Arşivi / Statistics Archive**):

- **Archive & reset** on a district row (or **Archive & reset all**) copies the numbers (totals, daily data, UTM rows) to the archive and starts a clean count. Nothing is deleted outright.
- **Automatic:** a daily job archives and resets a district one day after all of its concerts have ended. Districts that still have an upcoming concert are skipped. Switch it off under **Settings → İstatistik arşivi**.
- Each archive record covers the period from the district's previous archive (or first record) to the day it was archived. Daily detail is limited by the retention setting (30/90/180 days); totals cover the whole period.
- **Restore** puts a record back as the district's live counters (only while the district has no live row). **Delete** removes it permanently. At most 400 records are kept; the oldest are dropped beyond that.

## Privacy

- Statistics use no cookies. The browser's `localStorage` only stores a per-day "already counted" marker per district (visits and ticket clicks).
- The Quicksand font is loaded from Google Fonts by default. Turn it off in **Settings → Gizlilik & Yazı Tipi** to avoid any connection to Google; cards then use the system font.
- Decide separately whether your consent policy (GDPR/KVKK) covers `localStorage` markers and UTM statistics.

## Database size

All data lives in three `wp_options` rows (autoload off): concerts, statistics and settings. A concert record is about 130 bytes. Statistics keep 90 days of daily numbers per district plus all-time totals and at most 40 UTM combinations per district. One district takes roughly 15-25 KB at most and at most 300 districts are stored, so the statistics row stays below about 8 MB even in the worst case (around 200 KB for a dozen districts). Archived concerts are kept until you remove them.

## Installation

**Requirements:** WordPress 6.0+ and PHP 8.4+ (the plugin is tested on PHP 8.4 and 8.5).

1. If an older version is installed, deactivate and delete it on the WordPress **Plugins** page first (your database records are kept unless the delete-on-uninstall option is ticked in the settings).
2. Download the plugin `.zip` archive.
3. In the WordPress admin, go to **Plugins > Add New Plugin > Upload Plugin**.
4. Select the `.zip` file, upload it and activate the plugin.

## Usage

1. Add your upcoming concerts (single or double session) in the **Konser Takvimi** menu and save.
2. Place the **Concert Calendar** block (or the `[konser_takvimi]` shortcode) on the page where the calendar should appear.
3. At the top of each district concert page, place the **District Redirector** block (or `[konser_ilce_yonlendir]`). If you set a default ticket URL in the settings, you only need to add the **Concert Ticket Button** block (or `[konser_bilet_butonu]`) wherever the button should appear.
4. Follow district visits, CTR ranking, UTM sources and ticket clicks in the **İstatistikler & Grafik** tab (admin visits are not counted, so use a private window for testing).

### Shortcode and block attributes

| Block / shortcode | Attribute | Description |
|---|---|---|
| District Redirector (`[konser_ilce_yonlendir]`) | `district` | District slug. Defaults to the slug of the current page. |
| | `redirect_target` | Redirect path or URL. Defaults to the value in the settings. |
| | `show_calendar_button` | `1` (default) or `0` to hide the Add to Calendar button. |
| Ticket Button (`[konser_bilet_butonu]`) | `url` | Ticket URL. Defaults to the value in the settings. |
| | `image` | Button image URL. Defaults to the value in the settings. |
| | `district` | District key used for analytics and as the default `utm_campaign`. Defaults to the page slug. |
| | `alt` | Image alt text. |
| | `link_target` | HTML link target, e.g. `_blank`. |

Example: `[konser_ilce_yonlendir district="urla" redirect_target="/konserler"]`

**Redirect targets:** a `redirect_target` set in a block or shortcode may point to this site or to the host chosen as the default target in the settings. Any other host is ignored and the default target is used, so content authors cannot create open redirects. To redirect to another domain, set that domain as the default target in the settings.

## Upgrading from 3.x

Version 4.0.0 replaces the Turkish attribute names with English ones. **There is no automatic fallback**, so update existing content after upgrading:

| Where | Old (3.x) | New (4.0.0) |
|---|---|---|
| District Redirector block / shortcode | `ilce` | `district` |
| District Redirector block / shortcode | `hedef` | `redirect_target` |
| District Redirector block / shortcode | `takvime_ekle` | `show_calendar_button` |
| Ticket Button block / shortcode | `ilce` | `district` |
| Ticket Button block / shortcode | `resim` | `image` |
| Ticket Button block / shortcode | `hedef` | `link_target` |

- **Shortcodes:** edit the pages and rename the attributes, e.g. `[konser_ilce_yonlendir ilce="urla"]` → `[konser_ilce_yonlendir district="urla"]`.
- **Blocks:** WordPress silently ignores unknown block attributes, so a block that had `ilce` set falls back to the page slug until you re-enter the value in the block sidebar.
- Block names (`konser-takvimi/...`), shortcode names, the `subadap_konserler_*` database options and the `{city}-konseri` slug format are unchanged, so stored concerts and statistics are untouched.
- Outbound ticket-link tagging is **off by default** since 4.3.0 and never touches links that already have `utm_*` parameters (e.g. Biletinial). Incoming UTM capture is on.

## Development & Tests

The plugin logic is covered by dependency-free tests that stub the WordPress functions they need (`tests/wp_stubs.php`), so they run without a WordPress install:

```sh
./tests/run.sh   # PHP lint, JS syntax, PHP tests and tracker.js tests
```

GitHub Actions runs the same checks on PHP 8.4 – 8.5 and Node 26 (`.github/workflows/tests.yml`), next to the security workflow (`security.yml`). The stubs only approximate WordPress, so they do not replace a manual check on a staging site.

## File Structure

```text
konser-takvimi/
├── konser-takvimi.php           # Main plugin file: MusicEvent JSON-LD, 302 redirect, Add to Calendar, UTM and analytics
├── uninstall.php                # Optional database and scheduled-task cleanup script
├── LICENSE                      # GNU GPLv3 licence text
├── README.md                    # Project documentation (English)
├── README.tr.md                 # Project documentation (Turkish)
├── CHANGELOG.md                 # Release history
├── .github/workflows/
│   ├── tests.yml                # Lint + unit tests (PHP 8.4-8.5, Node 26)
│   ├── security.yml             # WPCS security sniffs, Semgrep and Gitleaks
│   └── release.yml              # Builds the plugin zip and publishes a GitHub release on v* tags
├── tests/                       # Stubbed-WordPress unit tests and tracker.js tests (run with ./tests/run.sh)
├── includes/
│   ├── admin-page.php           # Admin screens: CTR-ranked statistics, UTM sources, chart, export and settings tabs
│   └── blocks.php               # Gutenberg block registration and server-side render/preview handlers
└── assets/
    ├── css/
    │   ├── admin.css            # Admin styles
    │   └── frontend.css         # Front-end cards, Today/Tomorrow badges and Add to Calendar styles
    └── js/
        ├── admin.js             # Auto slug, page check, media picker, preview and Canvas chart drawing
        ├── blocks-editor.js     # Gutenberg block editor UI
        ├── frontend.js          # Front-end filtering, Today/Tomorrow badges and .ics file generation
        └── tracker.js           # Unique visitor, time on page, ticket click tracking and UTM capture/tagging
```

## License

This project is licensed under the [GNU General Public License v3.0](LICENSE).

## Translations
All texts are translatable (text domain `konser-takvimi`, source language Turkish). WordPress picks the language from **Settings → General → Site Language** (administrators can override it in their profile). An English catalogue is included and used for every locale that has no catalogue of its own; Turkish sites show the original texts.

To add a language: copy `languages/konser-takvimi.pot` to `languages/konser-takvimi-<locale>.po` (e.g. `de_DE`), translate it (Poedit works), then compile it: `python3 tools/build-catalog.py --mo-only languages/konser-takvimi-de_DE.po` (or use any gettext tool). After adding or changing strings run `php tools/extract-strings.php` to refresh the template.

## Code layout
`konser-takvimi.php` only bootstraps the plugin. Features live in `includes/`: `events-time.php` (timezone and event times), `settings.php`, `utm.php`, `stats-recording.php` (buffered, locked writes and the tracking endpoint), `reports.php`, `cron.php`, `assets.php`, `schema.php`, `redirect.php`, `calendar.php`, `shortcodes.php`, `blocks.php`, `i18n.php`; the admin UI is `admin-page.php`, `admin-tools.php` and the tab files in `includes/admin/`.
