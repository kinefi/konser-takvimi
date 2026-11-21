const vm = require('vm');
const fs = require('fs');
const src = fs.readFileSync(require('path').join(__dirname, '..', 'assets', 'js', 'tracker.js'), 'utf8');
let failed = 0, count = 0;
function t(name, cond, detail) { count++; if (!cond) { failed++; console.log('FAIL:', name, detail || ''); } }

function makeLink(href, classes, district) {
  const attrs = district ? { 'data-kt-district': district } : {};
  return {
    href, classList: { contains: c => (classes || []).includes(c) },
    getAttribute: n => (n in attrs ? attrs[n] : null),
    querySelector: () => null,
  };
}

function run(cfg, search, links) {
  const sent = [];
  const handlers = {};
  const sandbox = {
    URL, URLSearchParams, Date, Math, Array, Object, parseInt, String, JSON,
    FormData: class { constructor() { this.m = {}; } append(k, v) { this.m[k] = String(v); } },
    navigator: { sendBeacon: (url, fd) => { sent.push(fd.m); return true; } },
    fetch: () => {},
    ktTrackerData: cfg,
    window: { location: { search, href: 'https://subadapcocuk.org/urla-konseri/' + search, hostname: 'subadapcocuk.org' },
              localStorage: { _d: {}, getItem(k) { return this._d[k] || null; }, setItem(k, v) { this._d[k] = v; } },
              addEventListener() {} },
    document: { hidden: false, readyState: 'complete',
      addEventListener: (ev, fn) => { (handlers[ev] = handlers[ev] || []).push(fn); },
      querySelectorAll: () => links },
  };
  sandbox.window.window = sandbox.window;
  vm.runInNewContext(src, sandbox);
  return { sent, handlers };
}

const baseCfg = () => ({ ajaxUrl: '/ajax', nonce: 'n', defaultDistrict: 'urla', defaultLabel: 'Urla', trackView: 1, todayStr: '2026-11-21',
  ticketDomains: ['biletinial.com', 'biletix.com'], defaultImgUrl: '', captureUtm: 1,
  utm: { enabled: 1, source: 'subadapcocuk.org', medium: 'referral', campaign: '' } });

// 1) view event + link decoration
{
  const ticket = makeLink('https://biletinial.com/e/1', [], null);
  const manual = makeLink('https://biletix.com/e/2?utm_source=newsletter', [], 'bornova');
  const internal = makeLink('https://subadapcocuk.org/baska-sayfa/', [], null);
  const other = makeLink('https://example.com/', [], null);
  const button = makeLink('https://biletinial.com/e/3?utm_source=subadapcocuk.org&utm_content=ticket-button', ['kt-ticket-btn-link'], 'urla');
  const { sent, handlers } = run(baseCfg(), '?utm_source=Instagram&utm_medium=social&utm_campaign=urla&foo=1', [ticket, manual, internal, other, button]);
  const view = sent.find(m => m.event_type === 'views');
  t('view sent', !!view);
  t('view carries utm', view && view.utm_source === 'Instagram' && view.utm_medium === 'social' && view.utm_campaign === 'urla', JSON.stringify(view));
  t('view has no stray params', view && !('foo' in view));
  t('view is_unique', view && view.is_unique === '1');
  t('ticket link decorated', ticket.href === 'https://biletinial.com/e/1?utm_source=subadapcocuk.org&utm_medium=referral&utm_campaign=urla&utm_content=ticket-link', ticket.href);
  t('manual utm_source kept, rest added', manual.href.includes('utm_source=newsletter') && manual.href.includes('utm_campaign=bornova') && !manual.href.includes('utm_source=subadapcocuk.org'), manual.href);
  t('internal link untouched', internal.href === 'https://subadapcocuk.org/baska-sayfa/', internal.href);
  t('non-ticket external untouched', other.href === 'https://example.com/');
  t('server-tagged button gets only missing params', button.href.includes('utm_medium=referral') && button.href.includes('utm_campaign=urla') && (button.href.match(/utm_source=/g) || []).length === 1 && (button.href.match(/utm_content=/g) || []).length === 1, button.href);

  // 3) click event
  const fresh = makeLink('https://biletinial.com/e/9', [], null);
  const click = handlers.click[0];
  click({ target: { closest: () => fresh } });
  const clk = sent.find(m => m.event_type === 'clicks');
  t('click sent', !!clk);
  t('click carries incoming utm', clk && clk.utm_source === 'Instagram' && clk.utm_campaign === 'urla', JSON.stringify(clk));
  t('click marks first click of the day as unique', clk && clk.is_unique === '1', JSON.stringify(clk));
  click({ target: { closest: () => fresh } });
  const clicks = sent.filter(m => m.event_type === 'clicks');
  t('second click same day not unique', clicks.length === 2 && clicks[1].is_unique === '0', JSON.stringify(clicks));
  t('click link decorated before navigation', fresh.href.includes('utm_content=ticket-link'), fresh.href);
}

// 4) capture disabled
{
  const cfg = baseCfg(); cfg.captureUtm = 0;
  const { sent } = run(cfg, '?utm_source=ig', []);
  const view = sent.find(m => m.event_type === 'views');
  t('capture off -> no utm in view', view && !('utm_source' in view), JSON.stringify(view));
}

// 5) outbound tagging disabled / fixed campaign
{
  const cfg = baseCfg(); cfg.utm.enabled = 0;
  const l = makeLink('https://biletinial.com/e/1', [], null);
  run(cfg, '', [l]);
  t('tagging off -> unchanged', l.href === 'https://biletinial.com/e/1', l.href);
  const cfg2 = baseCfg(); cfg2.utm.campaign = 'sonbahar-2026';
  const l2 = makeLink('https://biletinial.com/e/1', [], 'urla');
  run(cfg2, '', [l2]);
  t('fixed campaign wins', l2.href.includes('utm_campaign=sonbahar-2026'), l2.href);
}

// 6) no ktTrackerData -> no crash
{
  const sandbox = { window: { location: { search: '' }, addEventListener() {} }, document: { hidden: false, readyState: 'complete', addEventListener() {}, querySelectorAll: () => [] }, navigator: {}, URL, URLSearchParams, Date, Math, Array, Object, parseInt, String, FormData: class {} };
  let ok = true; try { vm.runInNewContext(src, sandbox); } catch (e) { ok = false; console.log(e.message); }
  t('no config -> no crash', ok);
}

console.log(`\n${count} assertions, ${failed} failed`);
process.exit(failed ? 1 : 0);
