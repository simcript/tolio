(() => {
'use strict';
document.querySelectorAll('time[data-date]').forEach(time => {
 time.textContent = new Date(Number(time.dataset.date)).toLocaleDateString('fa-IR', {timeZone: 'Asia/Tehran'});
});
document.querySelectorAll('[data-password]').forEach(button => {
 const input = document.getElementById(button.dataset.password);
 if (!input) return;
 button.hidden = false;
 const label = button.getAttribute('aria-label');
 button.addEventListener('click', () => {
  const visible = input.type === 'password';
  input.type = visible ? 'text' : 'password';
  button.setAttribute('aria-pressed', String(visible));
  button.setAttribute('aria-label', visible ? label.replace('نمایش', 'پنهان کردن') : label);
  button.textContent = visible ? 'پنهان' : 'نمایش';
 });
});
document.querySelectorAll('form[data-pending]').forEach(form => {
 form.addEventListener('submit', event => {
  if (form.dataset.submitting === 'true') { event.preventDefault(); return; }
  form.dataset.submitting = 'true';
  form.setAttribute('aria-busy', 'true');
  form.querySelectorAll('[type="submit"]').forEach(button => { button.disabled = true; });
  const status = form.querySelector('.kq-submit-status');
  if (status) status.textContent = form.dataset.pending;
 });
});
window.addEventListener('pageshow', () => {
 document.querySelectorAll('form[data-pending]').forEach(form => {
  delete form.dataset.submitting;
  form.removeAttribute('aria-busy');
  form.querySelectorAll('[type="submit"]').forEach(button => { button.disabled = false; });
  const status = form.querySelector('.kq-submit-status');
  if (status) status.textContent = '';
 });
});
const dialog = document.querySelector('[data-account-dialog]');
if (dialog && typeof dialog.showModal === 'function') {
 dialog.removeAttribute('open');
 dialog.showModal();
 const cancel = dialog.querySelector('[data-dialog-cancel]');
 const dismiss = event => {
  event.preventDefault();
  dialog.close();
  history.replaceState(null, '', cancel.getAttribute('href'));
  (document.querySelector('.kq-logout-link') || document.getElementById('account-title'))?.focus();
 };
 cancel.addEventListener('click', dismiss);
 dialog.addEventListener('cancel', dismiss);
} else {
 document.getElementById('account-error')?.focus();
}
// The former in-game profile kept presence alive while forms were open. Reuse
// the same authenticated heartbeat only for a participating profile, never reset links.
const presence = document.getElementById('kq-account-presence');
if (presence) {
 let timer, active = false, stopped = false, failures = 0;
 const status = presence.querySelector('[role="status"]');
 const heartbeat = async () => {
  if (active || stopped) return;
  active = true;
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 8000);
  try {
   const response = await fetch(presence.dataset.base + '/api/heartbeat', {
    method: 'POST', credentials: 'same-origin', cache: 'no-store',
    headers: {'Content-Type': 'application/json', 'X-KQ-CSRF': presence.dataset.csrf},
    body: '{}', signal: controller.signal
   });
   if (response.status === 401 || response.status === 403) {
    stopped = true;
    status.textContent = 'هویت تغییر کرده است. پیش از ادامه، پروفایل را دوباره باز کن.';
    status.hidden = false;
    return;
   }
   if (!response.ok) throw new Error('unavailable');
   const data = await response.json();
   if (!data.ok) throw new Error('unavailable');
   if (!data.state?.engagement) stopped = true;
   failures = 0;
   status.hidden = true;
  } catch {
   failures++;
   status.textContent = 'ارتباط با بازی برقرار نیست. برای بررسی وضعیت به بازی برگرد؛ پر کردن فرم، پاسخ دورهای بازی را ثبت نمی‌کند.';
   status.hidden = false;
  } finally {
   clearTimeout(timeout);
   active = false;
   if (!stopped) timer = setTimeout(heartbeat, failures ? Math.min(15000, 700 * 2 ** Math.min(failures, 5)) : document.hidden ? 4000 : 1000);
  }
 };
 document.addEventListener('visibilitychange', () => { if (!document.hidden) { clearTimeout(timer); heartbeat(); } });
 window.addEventListener('online', () => { clearTimeout(timer); heartbeat(); });
 window.addEventListener('pagehide', () => { stopped = true; clearTimeout(timer); });
 window.addEventListener('pageshow', event => { if (event.persisted) location.reload(); });
 heartbeat();
}
})();
