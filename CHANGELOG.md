# Changelog

## [4.4.0] - 2026-11-22
### Added
- **Internationalisation:** all admin screens, reports, e-mails, notices, month/day names and JavaScript texts are translatable (text domain `konser-takvimi`). Turkish is the source language; an English catalogue ships in `languages/`. The plugin follows the WordPress locale automatically (site language, or the user's own language in the admin); every locale without its own catalogue falls back to English, Turkish sites keep the original texts.
- `languages/konser-takvimi.pot`, `konser-takvimi-en_US.po` and `.mo`, plus dependency-free tools: `tools/extract-strings.php` (regenerates the template) and `tools/build-catalog.py` (builds `.po`/`.mo`).
- Tests that verify the shipped catalogue (every string translated, placeholders and HTML tags identical to the source), English rendering of all admin tabs without leftover Turkish text, locale fallback, and the JavaScript dictionary.
- CI checks that `languages/konser-takvimi.pot` is up to date.

### Changed
- **Refactoring (no behaviour change):** the 1,988-line `konser-takvimi.php` is now a ~100-line bootstrap plus single-responsibility modules in `includes/` (`events-time`, `settings`, `utm`, `stats-recording`, `reports`, `cron`, `assets`, `schema`, `redirect`, `calendar`, `shortcodes`, `i18n`). The 1,472-line admin page is split into tab action/view files in `includes/admin/`. The Turkish-locale admin output was compared before and after the split: only HTML-escaping of `&` and `'` differs.
- The daily chart caption no longer says "Istanbul time zone"; it follows the selected timezone.
- Archive cleanup and the conversion-alert threshold are now sentences that translate naturally.

### Fixed
- The **Tools & Diagnostics** tab no longer shows the button/colour/settings form below the diagnostics; each tab now renders only its own content (regression test added).

## [4.3.0] - 2026-11-22
### Added
- **Tools & Diagnostics tab:** tracking health panel (districts stored, buffered events, last write, lock), a scanner that lists posts/pages using the deprecated `[subadap_konserler]` shortcode or pre-4.1.0 Turkish attribute names (`ilce`, `hedef`, `resim`, `takvime_ekle`) in shortcodes or block JSON, an archive cleanup ("delete archived events older than 6/12/24/36 months") and a warning when the 300-district cap is reached.
- **Campaign link builder:** one tracking link per channel for any concert page (WhatsApp, Meta ads, YouTube ads, newsletter, Instagram, Facebook, X, plain/direct). Campaign defaults to the page slug. These are the links that incoming-traffic capture classifies.
- **Selectable timezone** (default `Europe/Istanbul`) for event times, "today/tomorrow", daily statistics and calendar links. Stored events keep their own UTC offset.
- **Font choice:** Quicksand from Google Fonts, system font, or the active theme's font.
- **Statistics retention** (30 / 90 / 180 days of daily data) and **write buffering** (off / 30 s / 60 s / 5 min) to reduce database writes. Pending events are flushed when you open the statistics or tools tab, or build a report.
- **Per-session ticket clicks:** `session="1"` / `session="2"` on the ticket button (block setting too); counted separately and tagged `utm_content=ticket-button-s1/s2` when tagging is on.
- **Low-conversion alert** in the weekly e-mail (thresholds configurable).
- **Release workflow:** pushing a `vX.Y.Z` tag builds the plugin zip and publishes a GitHub release using this changelog.

### Changed
- **UTM tagging is all-or-nothing:** a link that already carries any `utm_*` parameter (for example the ticket platform's own) is never touched, in PHP and JavaScript.
- **Outbound ticket-link tagging is off by default** (ticket platforms tag their own links). Incoming UTM capture and calendar-link tagging stay on.
- CI: tests run on PHP 8.3, 8.4 and 8.5; phpcs skips `tests/`; the public tracking endpoint documents why it has no nonce.

## [4.2.0] - 2026-11-21
### Added
- **Incoming UTM data in reports:** The HTML report and the weekly e-mail now include a **UTM traffic sources** table (top 15 rows: source / medium / campaign, district, unique visitors, unique ticket clickers and conversion). The CSV export contains all UTM rows in a second block, with spreadsheet-formula characters neutralised.
- **Database size section** in the README with worst-case figures.

### Changed
- The admin statistics tab and the reports share one UTM row builder (`kt_get_utm_report_rows()`), and every UTM row now includes both CTR and conversion.

## [4.1.0] - 2026-11-21
### Added
- **Conversion rate next to CTR:** The plugin now counts the unique people who clicked a ticket link (once per browser, per district, per day) and shows **conversion = unique ticket clickers / unique visitors** next to the existing CTR. It appears in a new summary card, the district table (with a new sort option), the UTM sources table, the HTML and CSV reports and the weekly e-mail report. Data recorded before this version has no unique-clicker counts, so older rows understate the rate.
- **Privacy setting:** New option to turn off loading the Quicksand font from Google Fonts. The README documents what the tracker stores in `localStorage`.
- **Automated tests:** Dependency-free PHP and JavaScript tests in `tests/` (run with `./tests/run.sh`) and a GitHub Actions workflow, `tests.yml`, that lints and tests on PHP 8.1 - 8.3 and Node 20.

### Fixed
- **External redirect targets:** `wp_safe_redirect()` sent visitors to `wp-admin` when the redirect target was on another host. The host chosen as the default target in the settings is now allowed, and a target set in a block or shortcode is only accepted for this site or that host (otherwise the default target is used), so content authors cannot create open redirects.
- **Statistics growth and lost updates:** The statistics option is now written under a short-lived atomic lock, at most 300 districts are stored, district keys are limited to 80 characters and labels to 120 characters. Forged requests can no longer bloat the option, and concurrent visits no longer overwrite each other's counters.
- **Tracking stopping on cached pages:** The tracking endpoint no longer requires a nonce. For anonymous visitors the nonce was identical for everyone (no CSRF protection) and it expired inside cached pages, which silently stopped tracking.
- **JavaScript fallback redirect:** The URL is no longer HTML-escaped (`&#038;`), so redirect targets with query strings work.
- **Schema `endDate`:** `endDate` is now one hour after the start of the last session instead of equal to `startDate`, matching the calendar entries.
- **Admin media picker preview:** The selected image preview is built with DOM APIs instead of `innerHTML`.
- **CI:** The security workflow now locates `phpcs` through Composer instead of a hard-coded path.

## [4.0.0] - 2026-11-21
### Added
- **UTM tagging for outbound ticket links:** The ticket button and links to tracked ticket domains get `utm_source`, `utm_medium`, `utm_campaign` and `utm_content` (`ticket-button` / `ticket-link`). The source defaults to the site host and the campaign defaults to the district key unless a fixed value is configured. Existing `utm_*` parameters on a link are never overwritten, and internal links are never tagged.
- **UTM tagging for Add to Calendar links:** The page link inside each Google Calendar / `.ics` event description is tagged per channel (`utm_source=google-calendar` or `ics-file`, `utm_medium=calendar`) and per session (`utm_content=session-1` / `session-2`).
- **Incoming UTM capture:** Visits (and the ticket clicks made during those visits) that arrive with `utm_source`, `utm_medium` or `utm_campaign` are recorded per district and shown in a new **UTM traffic sources** table on the statistics tab, with visitors, page views, ticket clicks and CTR. At most 40 distinct combinations are stored per district; the rest are merged into an `(other)` bucket.
- **UTM settings card:** New settings section with switches for outbound ticket links, calendar links and incoming capture, plus fields for source, medium and campaign.
- **English documentation:** `README.md` is now in English and `README.tr.md` provides the Turkish version.

### Changed
- **BREAKING: English shortcode and block attribute names.** There is no automatic fallback; update existing content after upgrading (see the README for details).
  - District redirector (block and `[konser_ilce_yonlendir]`): `ilce` → `district`, `hedef` → `redirect_target`, `takvime_ekle` → `show_calendar_button`.
  - Ticket button (block and `[konser_bilet_butonu]`): `ilce` → `district`, `resim` → `image`, `hedef` → `link_target`.
- Internal rename: the admin page callback is now `kt_render_admin_page`, and the concert form nonce is now `kt_concerts_nonce` / `kt_concerts_nonce_action`.
- The fallback district key for a ticket button rendered outside a post changed from `genel` to `general`.
- All code comments, the plugin header description and internal identifiers are now in English. Block names, shortcode names, the `subadap_konserler_*` options and the `{city}-konseri` slug format are unchanged, so stored concerts and statistics are not affected.
- The tracker script now also decorates ticket links on page load, so links opened in a new tab or copied carry the UTM parameters too.
- The UTM sanitizer transliterates Turkish letters (for example `İstanbul` becomes `istanbul`) instead of dropping them.

## [3.6.0] - 2026-11-21
### Added & Improved
- **Today & Tomorrow badges:** Cache-friendly **TODAY** and **TOMORROW** badges based on the Istanbul time zone (`Europe/Istanbul`) are shown in the top-right corner of concert cards.
- **Add to Calendar button (Google Calendar & Apple/Outlook `.ics`):** District concert pages with an active concert now include an **Add to Calendar** dropdown that opens Google Calendar or instantly downloads a standard `.ics` file in the browser. Double-session concerts offer each session as a separate option.
- **Gutenberg blocks & backward compatibility:** Added the `konser-takvimi/takvim`, `konser-takvimi/ilce-yonlendir` and `konser-takvimi/bilet-butonu` blocks. The existing shortcodes (`[konser_takvimi]`, `[konser_ilce_yonlendir]`, `[konser_bilet_butonu]`) remain supported and are marked `@deprecated`.
- **Google schema (`MusicEvent`) completeness:** Added the `description` and `offers.validFrom` fields to the `MusicEvent` schema for Google Search Console rich result validation.

## [3.5.0] - 2026-11-20
### Added & Improved
- **Google schema (JSON-LD `MusicEvent`) support:** Pages containing the `[konser_takvimi]` or `[konser_ilce_yonlendir]` shortcodes now get Google `MusicEvent` structured data (`startDate`, `endDate`, `location`, `performer`, `offers`) in `<head>`. The settings tab gained a schema on/off switch and a performer/band name field.
- **Click-through rate (CTR %) sorting & coloured performance badge:** The statistics table now offers one-click sorting links by **CTR %**, **unique visitors**, **ticket clicks**, **page views** or **average time on page**, plus coloured CTR performance badges.
- **Cache-safe `302 Found` district redirect:** Added the `DONOTCACHEPAGE` constant, `nocache_headers()` and an explicit `302` status so that search engines do not treat expired district pages as permanently removed (`301`) and caching plugins such as LiteSpeed or WP Rocket do not cache the redirect rule.

## [3.4.1] - 2026-11-19
### Added
- **Report download button with period and format options:** The statistics tab gained a download button for a printable / save-as-PDF **visual report (HTML)** and an **Excel table (.csv)** covering the **last 7 days (weekly)**, **last 14 days**, **last 30 days (monthly)** or **all time**.

## [3.4.0] - 2026-11-19
### Added
- **Daily unique visitor tracking:** Repeated reloads on the same day are filtered out via `localStorage` (no cookies), adding **unique visitors today** and **total unique visitors** metrics.
- **Time on page measurement:** The time a visitor stays active in the tab (on `visibilitychange`, `pagehide` or a ticket click) is recorded with `navigator.sendBeacon()` and used to calculate the **average time on page** per district.
- **Interactive 14-day chart:** The statistics tab gained a lightweight HTML5 Canvas bar chart, with no external library, and a district filter.
- **Weekly administrator e-mail report:** An automated report built on `wp_cron` sends a 7-day performance summary as an HTML table to the administrator's e-mail address on a chosen weekday. A **Send a sample report now** button was added as well.

## [3.3.0] - 2026-11-16
### Added & Improved
- **Default ticket sales URL setting:** Added a **Default Ticket Sales URL** field to the settings tab.
- **Cache-compatible page view counter:** District page views (`views`) are now recorded through AJAX from `tracker.js`.

## [3.2.1] - 2026-11-16
### Added
- **Automatic ticket click tracking without a shortcode:** On pages containing `[konser_ilce_yonlendir]`, clicks on ticket domain links or on the default ticket image are captured automatically.

## [3.2.0] - 2026-11-16
### Added
- **Ticket button shortcode (`[konser_bilet_butonu]`):** Added a ticket button shortcode that renders a `<figure class="wp-block-image size-large">` structure, together with a **Statistics** tab.

## [3.1.0] - 2026-11-16
### Added & Changed
- **Configurable redirect target:** Added a field for the default redirect address (default `/konserler`).

## [3.0.0] - 2026-11-16
### Added & Changed
- Added **double-session support (`12.00 ve 14.00`)** and the **district page redirect shortcode (`[konser_ilce_yonlendir]`)**.

## [2.9.0] - 2026-11-16
### Changed
- **General file and folder structure:** The plugin folder and files were renamed to reusable generic names.
- **Modular admin styling:** Inline admin CSS rules were moved to `assets/css/admin.css`.
- **Dynamic site URL support:** The base address in badge links is now taken dynamically from WordPress `home_url()`.

## [2.8.1] - 2026-11-15
### Added & Changed
- **Automatic `ilce-konseri` slug format:** When a district/city is entered for a new concert, the link slug is generated automatically in the `{city}-konseri` format.
- **Live page / post existence check:** If the entered or generated slug matches a published page or post on your site, the field gets a green border; otherwise it gets a red border and an info label.

## [2.7.0] - 2026-11-15
### Added
- **Filter and pick from existing posts:** The slug field can now filter and select published posts and pages by title or slug (`datalist`).

## [2.6.0] - 2026-11-15
### Added
- **GitHub documentation & licence (`README.md`, `LICENSE`):** Added a `README.md` describing installation, usage, features and file structure, plus GNU GPLv3 (`GPL-3.0-or-later`) licence information.
- **Configurable uninstall (`uninstall.php`):** Added an optional database cleanup setting to the settings tab.

## [2.5.0] - 2026-11-15
### Added & Changed
- **Button styles & colours tab:** Added a tab for adjusting button size, corner radius and the 12-colour palette cycle with a live preview.

## [2.4.0] - 2026-11-15
### Added
- **JSON and CSV export:** Active, archived or all concert records can now be downloaded as `.json` and `.csv`.

## [2.3.0] - 2026-11-15
### Added
- **Collapsible archive (accordion) & pagination:** The archived concerts section is now collapsible and paginated.

## [2.2.0] - 2026-11-15
### Added
- **Automatic slug suggestion:** A live link slug suggestion was added when creating a new concert.

## [2.1.0] - 2026-11-15
### Changed
- **Database storage & security:** Concert records moved to the WordPress `wp_options` table; automatic archiving and security protections were added.

## [1.0.0]
### Initial release
- First release with a 12-colour badge grid.
