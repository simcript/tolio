(() => {
'use strict';
const root = document.getElementById('kalaqpar');
if (!root) return;
const $ = id => document.getElementById(id);
const content = $('kq-content');
const base = root.dataset.base; // Server-generated path, including the configured mount prefix.
const fa = v => v === null || v === undefined ? '—' : new Intl.NumberFormat('fa-IR', {maximumFractionDigits: 1}).format(v);
const node = (tag, cls = '', text = '') => { const e = document.createElement(tag); if (cls) e.className = cls; if (text !== '') e.textContent = text; return e; };
const append = (e, ...children) => { children.flat().filter(Boolean).forEach(c => e.append(c)); return e; };
const p = text => node('p', '', text);
const card = (...children) => append(node('div', 'kq-card'), ...children);
const note = (title, detail = '', color = 'kq-yellow') => append(node('div', 'kq-note ' + color), node('h3', '', title), detail ? p(detail) : null);
let csrf = '', profile = null, state = null, page = 'home', viewEpoch = 0, fingerprint = '', lastHeartbeat = 0, lastProfile = 0, lastLeaders = 0;
let invitation = root.dataset.invitation || '', preview = null, requestedMatch = null, offset = 0, samples = [], pollTimer = null;
let codeDraft = '', connected = true;
let polling = false, activePoll = null, failures = 0, booted = false, pending = null, pendingSending = false, answerError = '', roundSeen = 0;
let leaders = null, leaderPeriod = 'weekly', leaderPage = 1, historyPage = 1, colorDraft = 0, previousHost = null, previousRoom = null, navigating = false;
const message = text => { $('kq-message').textContent = text; $('kq-message').hidden = !text; };
function button(text, action, cls = '', disabled = false) {
 const b = node('button', 'kq-button ' + cls, text); b.type = 'button'; b.disabled = disabled;
 b.addEventListener('click', async () => { if (b.disabled) return; b.disabled = true; try { await action(); } catch (e) { message(e.message || 'ارتباط برقرار نشد؛ دوباره تلاش کن.'); } finally { if (b.isConnected) b.disabled = disabled; } });
 return b;
}
function link(text, action) { return button(text, action, 'kq-link'); }
function avatar(name, color, big = false) { const a = node('span', 'kq-avatar' + (big ? ' kq-big' : ''), [...(name || 'م')][0]); a.dataset.color = color ?? 2; a.setAttribute('aria-hidden','true'); return a; }
function hero(title, subtitle = '', crow = true) { const box = node('div','kq-hero'); if (subtitle) box.append(node('div','kq-eyebrow',subtitle)); if (crow) { const img = node('img'); img.src=root.dataset.hero; img.alt='تصویر کلاغ'; box.append(img); } box.append(node('h2','',title)); return box; }
function stats(entries) { return append(node('div','kq-stats'), entries.map(([value,label]) => append(node('div','kq-stat'),node('strong','',fa(value)),node('small','',label)))); }
function heading(title, subtitle='') { $('kq-title').textContent=title; $('kq-subtitle').textContent=subtitle; $('kq-back').hidden=page==='home'; $('kq-nav').hidden=page==='game' || page==='invite'; root.querySelectorAll('[data-page]').forEach(b=>b.classList.toggle('active',b.dataset.page===page)); }
function rows(players, lobby=false) {
 return players.map(u => {
  const r = node('div','kq-row' + (u.me ? ' kq-mine':''));
  if (!lobby) r.append(node('span','kq-rank',fa(u.rank)));
  r.append(avatar(u.name,u.avatar));
  const body = append(node('div','kq-grow'),node('div','kq-name',u.name+(u.me?' (تو)':'')));
  body.append(node('small','',lobby ? (u.host?'میزبان':!u.ready?'برای بازی دوباره اعلام آمادگی نکرده':u.kind==='waiting'?'در انتظار دست بعد':'آماده بازی') : (u.present===false?'آفلاین':'') ));
  r.append(body,node('span','kq-score',lobby ? (u.present?'آنلاین':'آفلاین') : fa(u.score)+' امتیاز'));
  return r;
 });
}
async function api(path, body, extraHeaders={}) {
 const controller = new AbortController(); const timeout=setTimeout(()=>controller.abort(),8000); const sent=Date.now();
 try {
  const response=await fetch(base+'/api/'+path,{method:body===undefined?'GET':'POST',credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json',...(body!==undefined?{'Content-Type':'application/json','X-KQ-CSRF':csrf}:{}),...extraHeaders},body:body===undefined?undefined:JSON.stringify(body),signal:controller.signal});
  const data=await response.json(); const received=Date.now();
  if (typeof data.server_now==='number') { samples.push({rtt:received-sent,value:data.server_now-(sent+received)/2}); samples=samples.slice(-9); const best=[...samples].sort((a,b)=>a.rtt-b.rtt).slice(0,3); offset=best.reduce((sum,s)=>sum+s.value,0)/best.length; }
  if (!response.ok || !data.ok) { const error=new Error(data.error?.message || 'سرویس بازی در دسترس نیست.'); error.code=data.error?.code || 'network'; error.status=response.status; throw error; }
  return data;
 } catch(e) { if (e.name==='AbortError' || e instanceof TypeError || e instanceof SyntaxError) { connected=false;const error=new Error('ارتباط قطع شد؛ بازی چندنفره به اینترنت نیاز دارد. پاسخ آفلاین ارسال نمی‌شود.'); error.code='network'; throw error; } throw e; }
 finally {clearTimeout(timeout);}
}
function syncPending() {
 // In-flight choices live only in memory. A fresh authoritative state resolves
 // uncertain acknowledgements; never restore or replay answers after reconnect.
 if (state?.answer || !pendingSending) pending=null;
 if (state?.answer) answerError='';
}
function acceptState(s) {
 if (state?.scope===s.scope && s.state_version<state.state_version) return false;
 const oldRound=state?.round_id; const oldMatch=state?.match_id;
 if (previousRoom===s.room_id && previousHost && s.host_id && previousHost!==s.host_id) message('میزبان اتاق تغییر کرد.');
 previousRoom=s.room_id;
 if (s.host_id) previousHost=s.host_id;
 state=s;
 root.dataset.kqBusy=String(!!s.engagement || ['queue','lobby','waiting','countdown','round_active','round_feedback'].includes(s.phase));
 if (oldRound!==s.round_id || oldMatch!==s.match_id) { roundSeen=performance.now()-Math.max(0,Date.now()+offset-(s.starts_at || Date.now()+offset)); answerError=''; pending=null; }
 syncPending();
 $('kq-worker').hidden=s.worker_available;
 if (s.match_id && page==='game') { try { sessionStorage.setItem('kq-last-match',String(s.match_id)); } catch {} }
 return true;
}
function render(force=false) {
 if (!booted) return;
 const printable=JSON.stringify({page,state:{...state,server_now:0},profile:page==='home'?profile:null,pending,answerError});
 if (!force && printable===fingerprint) return; fingerprint=printable;
 if (page==='profile' || page==='leaders' || page==='create' || page==='invite') return;
 const codeInput=$('kq-room-code');const selection=codeInput && document.activeElement===codeInput?{start:codeInput.selectionStart,end:codeInput.selectionEnd}:null;
 content.replaceChildren();
 if (page==='home') renderHome(); else renderGame();
 if(selection && $('kq-room-code')){$('kq-room-code').focus();$('kq-room-code').setSelectionRange(selection.start,selection.end);}
 if ($('kq-players').open) $('kq-player-rows').replaceChildren(...rows(state?.players || state?.members || [],!state?.players));
}
function renderHome() {
 heading('کلاغ‌پر',profile.name+' · ببین، تصمیم بگیر، امتیاز بگیر!');
 content.append(hero('با هم بیشتر خوش می‌گذره!','۲ تا ۳۰ نفر · بدون حذف'),stats([[profile.total_score,'امتیاز کل'],[profile.weekly_score,'امتیاز هفته'],[profile.weekly_rank,'رتبه هفتگی']]));
 if (state.engagement) content.append(button('بازگشت به بازی و اتاق فعلی',async()=>{requestedMatch=null;await navigate('game');}));
 else if (state.last_match_id) content.append(link('دیدن نتیجه آخرین مسابقه',()=>openMatch(state.last_match_id)));
 content.append(button('بازی سریع',()=>mutation('queue',{},true),'',!state.worker_available || !!state.engagement),p('با بازیکن‌های آنلاین هم‌بازی شو'));
 content.append(button('بازی با دوستان',()=>navigate('create'),'kq-yellow',!!state.engagement),p('اتاق بساز و کد یا لینک دعوت رو بفرست'),codeForm());
 content.append(note('اشتباه کردی؟ بازی ادامه داره!','۱۵ دور بازی؛ بیشترین امتیاز برنده می‌شه. پاسخ اشتباه یا بی‌پاسخ: ۱−. پرواز درست: برای همه ۱+. رد کردن درست: فقط اولین نفر ۱+، بقیه صفر.','kq-white'));
 content.append(p('زمان دریافت پاسخ در سرور ملاکه؛ تأخیر شبکه و سرعت اینترنت ممکنه روی نتیجه اثر بذاره.'));
}
function asciiDigits(value) {
 return value.replace(/[۰-۹٠-٩]/g,c=>String('۰۱۲۳۴۵۶۷۸۹'.includes(c)?'۰۱۲۳۴۵۶۷۸۹'.indexOf(c):'٠١٢٣٤٥٦٧٨٩'.indexOf(c)));
}
function normalizeCode(value) {
 const compact=asciiDigits(value).replace(/ /g,'');
 return /^[1-9][0-9]{2}-?[0-9]{3}$/.test(compact)?compact.replace('-',''):null;
}
function codeForm() {
 const form=node('form','kq-card');const label=node('label','kq-field','کد اتاق دوستت');
 const input=node('input','kq-code-entry');input.type='text';input.inputMode='numeric';input.dir='ltr';input.id='kq-room-code';input.name='code';input.required=true;input.maxLength=32;input.autocomplete='off';input.spellcheck=false;
 label.htmlFor=input.id;const help=p('کد شش‌رقمی را وارد کن؛ با خط تیره یا فاصله هم می‌شه.');help.id='kq-code-help';input.setAttribute('aria-describedby',help.id);
 // No per-keystroke formatting: typing, selection, deleting and pasting stay native.
 input.value=codeDraft;input.addEventListener('input',()=>{codeDraft=input.value;input.setCustomValidity('');});
 const submit=button('دیدن دعوت',async()=>{
  const code=normalizeCode(input.value);input.setCustomValidity(code?'':'کد شش‌رقمی اتاق را درست وارد کن.');if(!form.reportValidity())return;
  invitation=code;preview=null;page='invite';viewEpoch++;message('');history.replaceState({},'',base+'/join/'+code);await renderInvitation();
 },'',!!state?.engagement);
 form.addEventListener('submit',e=>{e.preventDefault();submit.click();});
 return append(form,node('h3','','دعوت شدی؟'),label,input,help,submit);
}
function manualCopy(text, container) {
 let box=container.querySelector('.kq-copy-fallback');
 if(!box){box=append(node('div','kq-copy-fallback'),p('کپی خودکار انجام نشد؛ متن را انتخاب کن و از گزینهٔ کپی مرورگر استفاده کن.'));container.append(box);}
 let field=box.querySelector('textarea');if(!field){field=node('textarea','kq-share-text');field.readOnly=true;field.setAttribute('aria-label','متن آماده برای کپی دستی');box.append(field);}
 field.value=text;field.dir='auto';field.focus();field.select();field.setSelectionRange(0,text.length);return box;
}
async function copyText(text, label, container) {
 if(navigator.clipboard?.writeText){try{await navigator.clipboard.writeText(text);message(label+' کپی شد.');return true;}catch{}}
 const focused=document.activeElement;const fallback=manualCopy(text,container);
 let copied=false;try{copied=typeof document.execCommand==='function' && document.execCommand('copy');}catch{}
 if(copied){fallback.remove();focused?.focus();message(label+' کپی شد.');return true;}
 message('کپی خودکار انجام نشد؛ متن را دستی کپی کن.');return false;
}
function inviteCard(details=state) {
 const box=node('div','kq-note kq-lavender kq-invite');box.append(node('h3','','دوست‌هات رو دعوت کن'));
 const code=node('bdi','kq-code',details.invitation_formatted);code.dir='ltr';code.setAttribute('aria-label','کد اتاق: '+details.invitation_formatted);
 const input=node('input');input.type='text';input.value=details.invitation_url;input.readOnly=true;input.dir='ltr';input.setAttribute('aria-label','لینک دعوت اتاق');input.addEventListener('focus',()=>input.select());
 const text='بیا کلاغ‌پر بازی کنیم!\nکد اتاق: '+details.invitation_formatted+'\nلینک ورود: '+details.invitation_url;
 const share=async()=>{
  if(navigator.share){try{await navigator.share({title:'کلاغ‌پر',text});message('دعوت به برنامهٔ اشتراک‌گذاری فرستاده شد.');return;}catch(e){if(e.name==='AbortError'){message('اشتراک‌گذاری لغو شد.');return;}}}
  if(await copyText(text,'متن دعوت',box))message('متن دعوت کپی شد؛ در پیام‌رسان برای دوستت بفرست.');
 };
 const shareButton=button('اشتراک‌گذاری',share,'kq-share');
 box.append(code,p('سه رقم اول، سه رقم بعد؛ کد رو به دوستت بگو!'),input,append(node('div','kq-invite-actions'),button('کپی کد',()=>copyText(details.invitation_formatted,'کد اتاق',box),'kq-white'),button('کپی لینک',()=>copyText(details.invitation_url,'لینک دعوت',box),'kq-white'),shareButton));
 return box;
}
function renderGame() {
 if (!state || state.phase==='idle') { page='home';render(true);return; }
 if(state.departed && ['round_active','round_feedback'].includes(state.phase)) {
  heading('مسابقه ادامه دارد','از صفحه بازی خارج شده‌ای');content.append(note('امتیازدهی همچنان ادامه دارد','تا پایان مسابقه عضو همین بازی هستی؛ هر دور بی‌پاسخ ۱− دارد.'),button('بازگشت به مسابقه',()=>mutation('resume',{},true)));return;
 }
 if(state.phase==='queue') return renderQueue();
 if(state.phase==='lobby') return renderLobby();
 if(state.phase==='waiting') return renderWaiting();
 if(['finished','technical_cancelled'].includes(state.phase)) return renderResults();
 if(state.phase==='countdown') { heading('آماده‌ای؟','شمارش معکوس مشترک برای همه');content.append(hero('همه با هم شروع می‌کنیم!'),append(node('div','kq-card kq-center'),node('div','kq-countdown',''),p('با کمتر از دو بازیکن آنلاین، به انتظار برمی‌گردیم.')));content.querySelector('.kq-countdown').id='kq-clock';content.append(link('خروج پیش از شروع',leave));return; }
 if(['round_active','round_feedback'].includes(state.phase)) return renderRound();
 content.append(note('این اتاق بسته شده است'),button('رفتن به خانه',()=>navigate('home')));
}
function renderQueue() {
 heading('بازی سریع','صف عمومی · از ۲ تا '+fa(state.capacity)+' نفر');
 const status=node('div','kq-center');status.append(node('span','kq-pill kq-green',fa(state.count)+' نفر در صف'));
 const orbit=node('div','kq-orbit');const core=append(node('div','kq-orbit-core'),node('strong','','…'),node('small','','تا شروع بازی'));core.firstChild.id='kq-clock';orbit.append(core,avatar(profile.name,0));status.append(orbit,node('h2','','هم‌بازی‌ها دارن می‌رسن!'),p('با پایان شمارش، بازی برای همه شروع می‌شه.'));
 content.append(status,card(node('h3','',fa(state.count)+' از '+fa(state.capacity)+' بازیکن'),p('جایگاه تو در صف: '+fa(state.position)+' · با پر شدن صف زودتر شروع می‌کنیم.')));
 const waited=p('');waited.id='kq-waited';
 content.append(note('برای شروع، حداقل ۲ نفر لازمه','اگر تعداد کمتر بشه، دوباره منتظر می‌مونیم.'),waited,button('خروج از صف',leave,'kq-white'));
}
function renderLobby() {
 const host=state.members?.find(u=>u.host);heading(state.room_name,'لابی دوستانه · '+(state.role==='host'?'تو میزبان هستی':'میزبان: '+(host?.name || 'در انتظار تعیین میزبان')));
 if(state.invitation)content.append(inviteCard());
 content.append(append(node('div','kq-lobby-title'),node('h2','','بازیکن‌های آماده'),node('span','kq-pill',fa(state.lobby_count)+' از '+fa(state.capacity))));
 content.append(...rows(state.members.filter(u=>u.admitted),true));
 const otherMembers=state.members.filter(u=>!u.admitted);
 if(otherMembers.length)content.append(node('h3','mt-4','در انتظار و اعضای قبلی — '+fa(otherMembers.length)),...rows(otherMembers,true));
 if(state.role==='host') content.append(note('منتظر بقیه دوست‌هاتی؟','بازی فقط وقتی شروع می‌شه که تو دکمه رو بزنی. اولویت دست جدید با بازیکن‌های قبلیه؛ سپس نفرات منتظر به ترتیب ورود.'),button('شروع بازی',()=>mutation('start',{room_id:state.room_id},true),'',!state.can_start));
 else content.append(note('منتظر شروع میزبان هستیم','وقتی میزبان شروع کنه، شمارش معکوس برای همه همزمان نمایش داده می‌شه.','kq-lavender'));
 content.append(link('خروج از اتاق',leave),p('اگر میزبان خارج شود، میزبانی به قدیمی‌ترین عضو آنلاین منتقل می‌شود.'));
}
function renderWaiting() {
 heading(state.room_name,'مسابقه در حال برگزاریه');if(state.invitation_url)content.append(inviteCard());content.append(hero('به دست بعدی می‌رسی!','دور '+fa(state.number)+' از '+fa(state.total)));
 content.append(p('بازی شروع شده؛ وسط مسابقه کسی به شرکت‌کنندگان اضافه نمی‌شه.'),card(node('h3','',fa(state.participant_count)+' نفر مشغول بازی‌اند'),p(fa(state.waiting_count)+' نفر منتظر دست بعد؛ از این گروه '+fa(state.spectator_count)+' نفر تماشاگرند.')),
 note('بعد از پایان، وارد لابی می‌شی','بازیکن‌های قبلی برای برگشت اولویت دارند؛ بعد منتظرها به ترتیب ورود پذیرفته می‌شن.'),button('تماشای مسابقه',()=>mutation('spectate',{room_id:state.room_id},true)),link('خروج از اتاق',leave));
}
function openPlayers() { $('kq-player-rows').replaceChildren(...rows(state.players || state.members || [],!state.players));if(!$('kq-players').open)$('kq-players').showModal(); }
function renderRound() {
 heading('دور '+fa(state.number)+' از '+fa(state.total),state.room_name+' · '+fa(state.participant_count)+' بازیکن'+(state.role==='spectator'?' · تماشاگر':''));
 const me=state.me;const own=append(node('div','kq-game-stats'),append(node('div','kq-lavender'),node('small','','امتیاز تو'),node('span','',fa(me?.score))),append(node('div'),node('small','','رتبه تو'),node('span','',me?fa(me.rank)+' از '+fa(state.participant_count):'تماشاگر')),
 button('همه بازیکن‌ها — '+fa(state.participant_count),openPlayers,'kq-link'));
 content.append(own);
 if(state.mode==='private')content.append(node('div','kq-topline',fa(state.waiting_count || 0)+' نفر منتظر دست بعد · '+fa(state.spectator_count || 0)+' نفر از آن‌ها تماشاگر'));
 const item=node('div','kq-item');if(state.item){const img=node('img');img.src=state.item.image;img.alt=state.item.name;img.addEventListener('error',()=>{img.remove();item.prepend(node('div','kq-fallback','تصویر بارگیری نشد؛ نام: '+state.item.name));},{once:true});item.append(img,node('h2','',state.item.name));}else item.append(node('div','kq-fallback','در انتظار نمایش تصویر…'));
 const timer=node('div','kq-timer','');timer.id='kq-clock';item.append(timer);if(state.phase==='round_active'){const progress=node('progress','kq-progress');progress.max=state.deadline_at-state.starts_at;progress.value=progress.max;progress.id='kq-progress';progress.setAttribute('aria-label','زمان باقی‌مانده دور');item.append(progress);}content.append(item);
 if(state.phase==='round_feedback'){
  const f=state.feedback;
  if(f){let title,detail;
   if(f.reason==='correct_skip_zero'){title='درست رد کردی!';detail='رد کردنت درست بود، اما فقط اولین پاسخ صحیح امتیاز سرعت گرفت. '+(state.speed_winner?.name || 'بازیکن دیگری')+' زودتر رد کرد و ۱ امتیاز گرفت.';}
   else if(f.correct){title='درست زدی!';detail=state.correct_action==='fly'?'این مورد پرواز می‌کنه؛ هر پاسخ درست ۱ امتیاز داره.':'اولین رد کردن درست برای تو بود!';}
   else if(f.reason==='timeout'){title='پاسخی در زمان مجاز ثبت نشد';detail='برای دور بی‌پاسخ یک امتیاز کم شد؛ بازی ادامه داره.';}
   else{title='این بار اشتباه شد؛ هنوز فرصت داری!';detail=state.correct_action==='fly'?'پاسخ درست «پر!» بود.':'پاسخ درست «رد کن» بود.';}
   content.append(append(node('div','kq-feedback '+(f.correct?(f.delta===0?'kq-lavender':'kq-green'):'kq-red')),node('h3','',title),node('strong','',(f.delta>0?'+':'')+fa(f.delta)+' امتیاز'),p(detail)));
  } else content.append(note('پایان دور',state.correct_action==='fly'?'پاسخ درست: پر!':'پاسخ درست: رد کن','kq-lavender'));
  const next=node('h3','',state.number===state.total?'در حال آماده‌سازی نتیجه مسابقه':'دور بعد به‌زودی');if(state.number<state.total)next.id='kq-next';
  content.append(card(next,p('امتیازهای جدید برای همه ثبت شد.')));
 } else if(state.role==='participant') {
  const locked=!!state.answer || !!pending || !state.worker_available;
  const fly=button('پر!',()=>choose('fly'),'kq-answer',locked);fly.dataset.answer='fly';fly.append(node('span','','↑'),node('small','','پرواز می‌کنه · Space'));
  const skip=button('رد کن',()=>choose('skip'),'kq-answer kq-yellow',locked);skip.dataset.answer='skip';skip.append(node('span','','×'),node('small','','پرواز نمی‌کنه · ↓'));
  content.append(fly,skip);
  const acknowledgement=node('div','kq-action-note',state.answer?'پاسخ ثبت شد.':pending?'در حال ثبت پاسخ؛ انتخابت قفل شده…':'در هر دور فقط یک انتخاب؛ اشتباه یعنی ۱− امتیاز.');acknowledgement.id='kq-ack';content.append(acknowledgement);
  if(answerError)content.append(note('ثبت پاسخ تأیید نشد',answerError));
 } else content.append(note('در حال تماشای مسابقه هستی','پاسخ‌دادن فقط برای شرکت‌کنندگان این دست ممکن است.','kq-lavender'));
 content.append(node('div','kq-topline',(state.players || []).slice(0,3).map(u=>u.name+' '+fa(u.score)).join(' · ')));
 content.append(link('خروج از صفحه بازی',leave));
}
function renderResults() {
 const cancelled=state.phase==='technical_cancelled';heading(cancelled?'مسابقه لغو فنی شد':'نتیجه مسابقه',state.room_name+' · '+fa(state.total)+' دور · '+fa(state.participant_count)+' بازیکن');
 const box=node('div','kq-results');box.append(node('div','kq-trophy',cancelled?'!':'♜'));
 box.append(node('h2','',cancelled?'وقفه سرویس بیش از حد مجاز بود':(state.winners || []).map(u=>u.name).join(' و ')+((state.winners?.length || 0)>1?' مشترکاً برنده شدند!':' برنده شد!')));
 if(cancelled)box.append(note('برد و نتیجه رقابتی ثبت نشد','امتیاز دورهای تسویه‌شده محفوظ است. دور باز جریمه نشد و در آمار دقت حساب نمی‌شود.'));
 const me=state.me;if(me)box.append(append(node('div','kq-card kq-lavender kq-mine'),node('h3','','نتیجه تو'),node('strong','',fa(me.score)+' امتیاز'),p('رتبه '+fa(me.rank)+' از '+fa(state.participant_count))));
 box.append(node('h3','','سه نفر برتر'),...rows(state.players.slice(0,3)),link('دیدن نتیجه همه بازیکن‌ها',openPlayers));
 if(state.can_rematch)box.append(button('دوباره با همین جمع',async()=>{requestedMatch=null;await mutation('rematch',{room_id:state.room_id},true);}),p('بازگشت به لابی؛ شروع دست بعد با میزبان.'));
 else if(state.mode==='public')box.append(button('بازی سریع دوباره',async()=>{requestedMatch=null;await mutation('queue',{},true);},'',!state.worker_available));
 if(state.can_rematch)box.append(link('خروج از اتاق',leave));
 box.append(link('رفتن به خانه',()=>navigate('home')));content.append(box);
}
async function navigate(next) {
 if(navigating)return;
 if(page==='game' && ['round_active','round_feedback'].includes(state?.phase) && state.role==='participant' && !state.departed){
  if(!confirm('امتیازدهی تا پایان مسابقه ادامه دارد. هر دور بی‌پاسخ ۱− دارد و نمی‌توانی همزمان وارد مسابقه دیگری شوی. از صفحه بازی خارج می‌شوی؟'))return;
  navigating=true;try{await api('leave',{});}finally{navigating=false;}
 }
 if(page==='invite' && next==='home'){history.replaceState({},'',base+'/');invitation='';}
 page=next;viewEpoch++;fingerprint='';message('');
 if(next!=='game')requestedMatch=null;
 if(next==='create')return renderCreate();
 if(next==='invite')return renderInvitation();
 if(next==='profile')return loadProfile();
 if(next==='leaders')return loadLeaders();
 if(next==='home') { const epoch=viewEpoch;try{const data=await api('profile');if(epoch!==viewEpoch)return;profile=data.profile;lastProfile=Date.now();}catch(e){message(e.message);} }
 render(true);schedule(0);
}
function renderCreate() {
 heading('بازی با دوستان','یک اتاق مخصوص جمع خودتون بساز');content.replaceChildren();
 const group=append(node('div','kq-hero'),append(node('div','kq-group'),avatar('ش',2),avatar('تو',0),avatar('ن',1)),node('h2','','لینکش رو بفرست، دور هم بازی کنین'));
 const form=node('form');const name=node('input');name.id='kq-room-name';name.required=true;name.maxLength=40;name.value='دورهمی ما';const label=node('label','kq-field','نام اتاق');label.htmlFor=name.id;
 let capacity=30;const output=node('output','',fa(capacity)+' نفر');const decrease=button('−',()=>{capacity=Math.max(2,capacity-1);output.textContent=fa(capacity)+' نفر';});decrease.setAttribute('aria-label','کاهش ظرفیت');const increase=button('+',()=>{capacity=Math.min(30,capacity+1);output.textContent=fa(capacity)+' نفر';});increase.setAttribute('aria-label','افزایش ظرفیت');
 const submit=button('ساخت اتاق و دریافت دعوت',async()=>{if(!form.reportValidity())return;await mutation('create',{name:name.value,capacity},true);});
 form.addEventListener('submit',e=>{e.preventDefault();submit.click();});
 form.append(label,name,node('h2','mt-4','ظرفیت اتاق'),p('از ۲ تا ۳۰ نفر؛ لازم نیست اتاق پر بشه.'),append(node('div','kq-stepper'),increase,output,decrease),note('شروع بازی دست توئه','با حضور حداقل دو نفر، هر وقت آماده بودین شروع کن.'),p('۱۵ دور · ورود با کد یا لینک · بدون حذف'),submit);content.append(group,form,codeForm());
}
async function renderInvitation() {
 heading('دعوت به بازی','بدون ثبت‌نام، به جمع دوست‌هات بپیوند');content.replaceChildren(card(p('در حال بررسی دعوت…')));const epoch=viewEpoch;
 try{
  const data=await api('preview?code='+encodeURIComponent(invitation));if(epoch!==viewEpoch || page!=='invite')return;preview=data.room;invitation=preview.invitation_code;
  history.replaceState({},'',base+'/join/'+invitation);content.replaceChildren();
  const box=append(node('div','kq-profile-head'),avatar('د',0,true),node('h2','',preview.name),p('ظرفیت '+fa(preview.capacity)+' نفر · '+(preview.phase==='playing'?'در حال بازی':'لابی')));content.append(box,inviteCard(preview));
  const label=node('label','kq-field','دوست‌هات با چه اسمی بشناسنت؟');label.htmlFor='kq-join-name';const name=node('input');name.type='text';name.id='kq-join-name';name.maxLength=30;name.value=profile.name;
  content.append(label,name,note('همه تا آخر بازی می‌مونن','اشتباه فقط امتیاز کم می‌کنه؛ حذف نداریم.'),p('۱۵ دور · پر! یا رد کن · بیشترین امتیاز برنده'));
  if(state.engagement)content.append(note('هم‌اکنون در صف یا اتاق هستی','برای ادامه به وضعیت فعلی برگرد.'),button('بازگشت به وضعیت فعلی',()=>navigate('game')));
  else content.append(button(preview.phase==='playing'?'پیوستن به انتظار دست بعد':'پیوستن به اتاق',async()=>{
   const renamed=await api('rename',{name:name.value,avatar:profile.avatar});profile=renamed.profile;
   await mutation('join',{invitation},true);history.replaceState({},'',base+'/');invitation='';
  },'',!preview.available));
  if(!preview.available)content.append(note(preview.phase==='playing'?'فهرست انتظار پر است':'اتاق پر است','کمی بعد دوباره بررسی کن.'));
  if(preview.phase==='playing')content.append(note('به دست بعدی می‌رسی','با پیوستن، منتظر دست بعد می‌مونی؛ وسط مسابقه وارد بازی نمی‌شی.'));
  content.append(button('بررسی دوباره دعوت',renderInvitation,'kq-white'),card(node('h3','','دعوت مخصوص همین اتاقه'),p('بازیکن‌های صف عمومی وارد جمع شما نمی‌شن.')));
 }catch(e){if(epoch!==viewEpoch)return;content.replaceChildren(note('دعوت در دسترس نیست',e.message),button('بررسی دوباره',renderInvitation,'kq-white'),codeForm(),button('رفتن به خانه',()=>navigate('home')));}
}
function pager(current,pages,action) {const bar=node('div','kq-pagination');bar.append(button('›',()=>action(current-1),'',current<=1),node('span','',fa(current)+' / '+fa(Math.max(1,pages))),button('‹',()=>action(current+1),'',current>=pages));return bar;}
async function loadProfile() {
 heading('پروفایل من','هویت مهمان، بدون دردسر ثبت‌نام');content.replaceChildren(card(p('در حال بارگیری پروفایل…')));const epoch=viewEpoch;
 try{const data=await api('profile?page='+historyPage);if(epoch!==viewEpoch || page!=='profile')return;profile=data.profile;colorDraft=profile.avatar;drawProfile();}catch(e){if(epoch===viewEpoch)content.replaceChildren(note('پروفایل بارگیری نشد',e.message),button('تلاش دوباره',loadProfile));}
}
function drawProfile() {
 content.replaceChildren(append(node('div','kq-profile-head'),avatar(profile.name,profile.avatar,true),node('h2','',profile.name),p('شناسه بازیکن: '+profile.id)));
 const form=node('form');const label=node('label','kq-field','نام نمایشی');label.htmlFor='kq-profile-name';const input=node('input');input.id='kq-profile-name';input.maxLength=30;input.required=true;input.value=profile.name;const colors=node('div','kq-colors');
 for(let i=0;i<6;i++){const b=avatar(profile.name,i);const choose=node('button','kq-avatar');choose.type='button';choose.dataset.color=i;choose.setAttribute('aria-label','رنگ آواتار '+fa(i+1));choose.setAttribute('aria-pressed',String(i===colorDraft));choose.addEventListener('click',()=>{colorDraft=i;colors.querySelectorAll('button').forEach(c=>c.setAttribute('aria-pressed',String(Number(c.dataset.color)===i)));});colors.append(choose);}
 const save=button('ذخیره نام و آواتار',async()=>{if(!form.reportValidity())return;const epoch=viewEpoch;const data=await api('rename',{name:input.value,avatar:colorDraft});profile=data.profile;if(page==='profile' && epoch===viewEpoch){message('پروفایل ذخیره شد.');drawProfile();}});form.append(label,input,colors,save);form.addEventListener('submit',e=>{e.preventDefault();save.click();});
 content.append(form,stats([[profile.total_score,'امتیاز کل'],[profile.weekly_score,'امتیاز هفته'],[profile.wins,'برد']]),stats([[profile.completed,'مسابقه کامل'],[profile.best,'بهترین امتیاز'],[profile.accuracy,'دقت ٪']]),node('div','kq-date','عضویت: '+new Date(profile.created_at).toLocaleDateString('fa-IR')),
 note('این هویت به مرورگر تو وصله','با پاک‌کردن کوکی‌ها یا تغییر مرورگر، دسترسی به این پروفایل از بین می‌رود.'),node('h2','','تاریخچه مسابقه‌ها'));
 if(!profile.history.length)content.append(node('div','kq-empty','هنوز مسابقه‌ای تمام نکرده‌ای.'));
 for(const m of profile.history){const row=card(node('h3','',m.name),p(m.phase==='technical_cancelled'?'لغو فنی · بدون ثبت برد':'رتبه '+fa(m.rank)+' · '+fa(m.total)+' دور'),node('strong','',fa(m.score)+' امتیاز'),node('small','',new Date(m.ended_at).toLocaleDateString('fa-IR')),link('دیدن نتیجه',()=>openMatch(m.match_id)));content.append(row);}
 if(profile.history_pages>1)content.append(pager(historyPage,profile.history_pages,async n=>{historyPage=n;await loadProfile();}));
}
async function loadLeaders() {
 heading('جدول رتبه‌بندی','هر دور، یه فرصت برای بالاتر رفتن');content.replaceChildren(card(p('در حال بارگیری رتبه‌بندی…')));const epoch=viewEpoch;
 try{const data=await api('leaderboard?period='+leaderPeriod+'&page='+leaderPage);if(epoch!==viewEpoch || page!=='leaders')return;leaders=data.leaderboard;lastLeaders=Date.now();drawLeaders();}catch(e){if(epoch===viewEpoch)content.replaceChildren(note('رتبه‌بندی بارگیری نشد',e.message),button('تلاش دوباره',loadLeaders));}
}
function drawLeaders() {
 content.replaceChildren();const tabs=node('div','kq-tabs');for(const [period,label] of [['weekly','این هفته'],['all','همه زمان‌ها']]){const b=button(label,async()=>{leaderPeriod=period;leaderPage=1;viewEpoch++;await loadLeaders();});b.className=period===leaderPeriod?'active':'';b.setAttribute('aria-pressed',String(period===leaderPeriod));tabs.append(b);}content.append(tabs,node('div','kq-date','شروع هفته: شنبه ساعت ۰۰:۰۰ تهران'));
 let remaining=leaders.rows;
 if(leaderPage===1 && remaining.length){const podium=node('div','kq-podium');for(const u of remaining.slice(0,3)){podium.append(append(node('div'),avatar(u.name,u.avatar,true),append(node('div','kq-podium-box'),node('h3','',u.name),node('strong','',fa(u.score)),node('span','',fa(u.rank)))));}content.append(podium);remaining=remaining.slice(3);}
 if(!leaders.rows.length)content.append(node('div','kq-empty','هنوز امتیازی در این بازه ثبت نشده؛ اولین نفر باش!'));
 content.append(...rows(remaining));if(leaders.me)content.append(note('رتبه تو: '+fa(leaders.me.rank),leaders.me.name+' · '+fa(leaders.me.score)+' امتیاز','kq-lavender'));
 else content.append(p('پس از اولین دور تسویه‌شده، وارد جدول می‌شی.'));
 if(leaders.pages>1)content.append(pager(leaderPage,leaders.pages,async n=>{leaderPage=n;viewEpoch++;await loadLeaders();}));content.append(p('امتیازها پس از پایان هر دور به‌روز می‌شن. امتیاز مساوی، رتبه مساوی داره.'));
}
async function mutation(action,body,showGame=false) {
 const data=await api(action,body);if(data.profile)profile=data.profile;
 if(showGame){page='game';viewEpoch++;requestedMatch=null;fingerprint='';}
 if(data.state)acceptState(data.state);await refresh(true);render(true);schedule(0);
}
async function leave() {
 const active=['round_active','round_feedback'].includes(state?.phase) && state.role==='participant';
 if(active && !confirm('امتیازدهی تا پایان مسابقه ادامه دارد؛ هر دور بی‌پاسخ ۱− دارد و نمی‌توانی وارد مسابقه دیگری شوی. از صفحه بازی خارج می‌شوی؟'))return;
 await api('leave',{});requestedMatch=null;page='home';viewEpoch++;await refresh(true);const data=await api('profile');profile=data.profile;lastProfile=Date.now();render(true);
}
async function openMatch(id){requestedMatch=Number(id);page='game';viewEpoch++;fingerprint='';await refresh(true);render(true);}
function uuid(){if(crypto.randomUUID)return crypto.randomUUID();const bytes=crypto.getRandomValues(new Uint8Array(16));return Array.from(bytes,b=>b.toString(16).padStart(2,'0')).join('');}
async function choose(action){
 if(!connected || failures || !state || state.phase!=='round_active' || state.role!=='participant' || state.answer || pending || !state.worker_available || Date.now()+offset>=state.deadline_at)return;
 syncPending();if(pending || state.answer)return;
 pending={match_id:state.match_id,round_id:state.round_id,action,request_id:uuid(),client_elapsed_ms:Math.max(0,Math.round(performance.now()-roundSeen))};render(true);await sendPending();
}
async function sendPending(){
 if(!connected || failures || !pending || pendingSending || state?.answer || !state?.worker_available || state.phase!=='round_active' || Date.now()+offset>=state.deadline_at)return;
 const operation={...pending};pendingSending=true;
 try{const accepted=await api('answer',operation);if(state?.round_id===operation.round_id && state?.match_id===operation.match_id){state.state_version=Math.max(state.state_version,accepted.state_version);state.answer={action:operation.action,request_id:operation.request_id};pending=null;answerError='';render(true);}await refresh();}
 catch(e){answerError=e.message;if(e.code && !['network','service_unavailable','worker_unavailable'].includes(e.code)){await refresh(true);}message(e.message);render(true);}
 finally{pendingSending=false;}
}
async function refresh(force=false){
 if(activePoll){await activePoll;if(!force)return;}
 if(activePoll)return activePoll;
 activePoll=pollOnce();
 try{await activePoll;}finally{activePoll=null;}
}
async function pollOnce(){
 if(!booted || polling)return;
 polling=true;const epoch=viewEpoch;const requested=requestedMatch;const previous=state;
 try{
  const shared=state?.progression_mode==='shared';
  if(!shared && Date.now()-lastHeartbeat>4500){await api('heartbeat',{});lastHeartbeat=Date.now();}
  let data=shared?await api('heartbeat',requested?{match_id:requested}:{}):await api('state'+(requested?'?match_id='+requested:''));
  if(epoch!==viewEpoch || requested!==requestedMatch)return;
  if(data.state.phase==='idle' && previous?.match_id && data.state.last_match_id===previous.match_id && ['countdown','round_active','round_feedback'].includes(previous.phase) && page==='game'){
   requestedMatch=previous.match_id;data=await api('state?match_id='+previous.match_id);if(epoch!==viewEpoch)return;
  }
  connected=true;acceptState(data.state);failures=0;$('kq-connection').hidden=true;$('kq-reconnect').hidden=true;render();
  if(page==='home' && Date.now()-lastProfile>15000){const fresh=await api('profile');if(epoch===viewEpoch && page==='home'){profile=fresh.profile;lastProfile=Date.now();render();}}
  if(page==='leaders' && Date.now()-lastLeaders>10000){const fresh=await api('leaderboard?period='+leaderPeriod+'&page='+leaderPage);if(epoch===viewEpoch && page==='leaders'){leaders=fresh.leaderboard;lastLeaders=Date.now();drawLeaders();}}
 }catch(e){failures++;$('kq-reconnect').hidden=false;$('kq-connection').hidden=false;$('kq-connection').textContent='اتصال به بازی برقرار نیست؛ مسابقه به اینترنت نیاز دارد. پاسخ آفلاین ارسال نمی‌شود؛ در حال دریافت دوبارهٔ وضعیت از سرور…';if(e.status===401){message('هویت مهمان منقضی شده؛ صفحه را تازه کن.');}else if(e.code!=='network')message(e.message);}
 finally{polling=false;}
}
function schedule(ms){clearTimeout(pollTimer);pollTimer=setTimeout(async()=>{await refresh();const fast=page==='game' && ['round_active','round_feedback','countdown'].includes(state?.phase);const delay=failures?Math.min(15000,700*2**Math.min(failures,5))*(.8+Math.random()*.4):document.hidden?4000:fast?300:state?.engagement?1000:5000;schedule(delay);},ms);}
function animate(){
 const clock=$('kq-clock');
 if(!connected || failures){if(clock)clock.textContent='اتصال قطع است؛ زمان معتبر از سرور دریافت می‌شود.';root.querySelectorAll('[data-answer]').forEach(b=>b.disabled=true);requestAnimationFrame(animate);return;}
 if(clock && state){const now=Date.now()+offset;const left=Math.max(0,(state.phase==='queue'?state.next_at:state.phase==='round_active'?state.deadline_at:state.next_at)-now);
 if(state.phase==='queue'){clock.textContent=state.next_at?fa(Math.ceil(left/1000)):'…';const waited=$('kq-waited');if(waited)waited.textContent='زمان انتظار: '+fa(Math.max(0,Math.floor((now-state.joined_at)/1000)))+' ثانیه';}
 else if(state.phase==='countdown')clock.textContent=left>0?fa(Math.ceil(left/1000)):'شروع!';
 else if(state.phase==='round_active'){clock.textContent=left>0?fa(left/1000)+' ثانیه':'پایان زمان دور';const progress=$('kq-progress');if(progress)progress.value=left;root.querySelectorAll('[data-answer]').forEach(b=>b.disabled=!connected || !!failures || !!state.answer || !!pending || !state.worker_available || left<=0);}
 else{clock.textContent='پایان زمان دور';const next=$('kq-next');if(next)next.textContent=left>0?'دور بعد تا '+fa(Math.ceil(left/1000))+' ثانیه دیگه':'در انتظار شروع دور بعد…';}}
 requestAnimationFrame(animate);
}
$('kq-back').addEventListener('click',()=>navigate('home').catch(e=>message(e.message)));
root.querySelectorAll('[data-page]').forEach(b=>b.addEventListener('click',()=>navigate(b.dataset.page).catch(e=>message(e.message))));
$('kq-close-players').addEventListener('click',()=>$('kq-players').close());
$('kq-players').addEventListener('click',e=>{if(e.target===$('kq-players')){const r=$('kq-players').getBoundingClientRect();if(e.clientX<r.left || e.clientX>r.right || e.clientY<r.top || e.clientY>r.bottom)$('kq-players').close();}});
document.addEventListener('keydown',e=>{if(!root.isConnected || e.repeat || e.ctrlKey || e.altKey || e.metaKey || $('kq-players').open || ['INPUT','TEXTAREA','SELECT'].includes(e.target.tagName) || e.target.isContentEditable || page!=='game')return;if(e.code==='Space' || e.code==='ArrowDown'){e.preventDefault();choose(e.code==='Space'?'fly':'skip');}});
window.addEventListener('beforeunload',e=>{if(page==='game' && state?.role==='participant' && ['round_active','round_feedback'].includes(state.phase)){e.preventDefault();e.returnValue='';}});
window.addEventListener('online',()=>{failures=0;schedule(0);});
$('kq-reconnect').addEventListener('click',()=>schedule(0));
window.addEventListener('offline',()=>{connected=false;$('kq-reconnect').hidden=false;$('kq-connection').hidden=false;$('kq-connection').textContent='مسابقهٔ چندنفره به اینترنت نیاز دارد؛ پاسخ آفلاین ذخیره یا ارسال نمی‌شود.';});
document.addEventListener('visibilitychange',()=>{if(!document.hidden)schedule(0);});
async function bootstrap(){
 const begin=async()=>{let nonce=document.querySelector('meta[name="kq-bootstrap"]').content;
 try{return await api('bootstrap',{}, {'X-KQ-Boot':nonce});}catch(e){if(e.code!=='csrf')throw e;const response=await fetch(base+'/',{credentials:'same-origin',cache:'no-store'});const html=new DOMParser().parseFromString(await response.text(),'text/html');nonce=html.querySelector('meta[name="kq-bootstrap"]').content;return api('bootstrap',{}, {'X-KQ-Boot':nonce});}};
 try{
  const data=navigator.locks?await navigator.locks.request('kq-bootstrap',begin):await begin();csrf=data.csrf;profile=data.profile;connected=true;try{localStorage.removeItem('kq-answer:'+profile.id);}catch{}lastProfile=Date.now();booted=true;acceptState(data.state);
  if(invitation){page='invite';await renderInvitation();}
  else{page=state.engagement?'game':'home';
   if(!state.engagement && state.last_match_id){let previous=null;try{previous=Number(sessionStorage.getItem('kq-last-match'));}catch{}if(previous===state.last_match_id){requestedMatch=previous;page='game';}}
   if(requestedMatch)await refresh(true);render(true);}
  schedule(0);
 }catch(e){heading('کلاغ‌پر','در حال آماده‌سازی سرویس');content.replaceChildren(note('اتصال به بازی برقرار نشد',e.message),button('تلاش دوباره',bootstrap));}
}
if(root.dataset.error){page='invite';heading('دعوت در دسترس نیست','کمی بعد دوباره تلاش کن');$('kq-back').hidden=true;content.replaceChildren(note('دعوت در دسترس نیست',root.dataset.error),button('تلاش دوباره',()=>location.reload()),button('رفتن به خانه',()=>location.assign(base+'/'),'kq-white'));}
else{bootstrap();requestAnimationFrame(animate);}
})();
