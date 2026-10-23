(() => {
'use strict';
const root = document.getElementById('kalaqpar');
if (!root || !window.isSecureContext) return;
const base = root.dataset.base;
const install = document.getElementById('kq-install');
const update = document.getElementById('kq-update');
const status = document.getElementById('kq-pwa-status');
const standaloneQuery = matchMedia('(display-mode: standalone)');
const standalone = () => standaloneQuery.matches || navigator.standalone === true;
// iPadOS may use a desktop user agent. Other browsers still get their own menu guidance.
const ios = /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
let prompt = null;
function explain(text) { status.textContent = text; status.hidden = false; }
function visibility() { install.hidden = standalone() || (!prompt && !ios); if (standalone()) status.hidden = true; }
window.addEventListener('beforeinstallprompt', event => { event.preventDefault(); prompt = event; visibility(); });
window.addEventListener('appinstalled', () => { prompt = null; install.hidden = true; status.hidden = true; });
standaloneQuery.addEventListener('change', visibility);
install.addEventListener('click', async () => {
 if (standalone()) return;
 if (prompt) {
  const current = prompt; prompt = null; visibility();
  try { await current.prompt(); await current.userChoice; } catch { explain('نصب از منوی مرورگر را بررسی کن؛ پشتیبانی مرورگرها یکسان نیست.'); }
 } else if (ios) {
  explain('در iPhone یا iPad، در Safari منوی اشتراک‌گذاری را باز کن و «افزودن به صفحهٔ اصلی» (Add to Home Screen) را انتخاب کن. اگر این گزینه در مرورگرت نیست، همین نشانی را در Safari باز کن. نصب، تو را وارد اتاق نمی‌کند. مسابقه به اینترنت نیاز دارد.');
 }
});
update.addEventListener('click', () => {
 if (root.dataset.kqBusy !== 'false') {
  explain('به‌روزرسانی منتظر می‌ماند. ابتدا مسابقه را تمام کن و از صف یا اتاق خارج شو؛ سپس بازیِ زبانه‌ها و پنجره‌های دیگر را هم بررسی کن.');
  return;
 }
 if (confirm('برای دریافت نسخهٔ تازه، پس از پایان بازی در همهٔ زبانه‌ها و پنجره‌ها، تمام پنجره‌های کلاغ‌پر (از جمله برنامهٔ نصب‌شده) را ببند و دوباره باز کن. آماده‌ای راهنمای به‌روزرسانی را ببینی؟')) {
  explain('همهٔ زبانه‌ها و پنجره‌های کلاغ‌پر را پس از پایان مسابقهٔ هرکدام ببند، سپس برنامه را دوباره باز کن. تا بسته‌شدن آخرین پنجره، نسخهٔ تازه فعال نمی‌شود. این صفحه خودکار تازه نمی‌شود.');
 }
});
visibility();
if (!('serviceWorker' in navigator)) return;
navigator.serviceWorker.register(base + '/sw.js', {scope: base + '/', updateViaCache: 'none'}).then(registration => {
 const waiting = () => { if (registration.waiting) { update.hidden = false; update.setAttribute('aria-label', 'نسخهٔ تازه آماده است؛ راهنمای به‌روزرسانی امن در همهٔ پنجره‌ها'); } };
 waiting();
 registration.addEventListener('updatefound', () => {
  const worker = registration.installing;
  worker?.addEventListener('statechange', waiting);
 });
 // No activation messages, skipWaiting, controllerchange reload, heartbeat or timer.
}).catch(() => { /* The existing online game continues if installation is unavailable. */ });
})();
