(function () {
    'use strict';

    var LEVELS = ['EMERGENCY', 'ALERT', 'CRITICAL', 'ERROR', 'WARNING', 'NOTICE', 'INFO', 'DEBUG'];
    var LC = {
        EMERGENCY: 'var(--c-emergency)', ALERT: 'var(--c-alert)', CRITICAL: 'var(--c-critical)',
        ERROR: 'var(--c-error)', WARNING: 'var(--c-warning)', NOTICE: 'var(--c-notice)',
        INFO: 'var(--c-info)', DEBUG: 'var(--c-debug)'
    };
    var LS_KEY = 'llv_sessions';

    var allEntries = [];
    var activeFilters = new Set();
    var currentSessionId = null;

// Bootstrap instances
    var bsModal = null;
    var bsToast = null;

// DOM
    var logInput = document.getElementById('logInput');
    var logList = document.getElementById('logList');
    var logEmpty = document.getElementById('logEmpty');
    var logWrap = document.getElementById('logWrap');
    var inpZone = document.getElementById('inpZone');
    var filterBtns = document.getElementById('filterBtns');
    var sbLevels = document.getElementById('sbLevels');
    var sbSessions = document.getElementById('sbSessions');
    var sbNoSessions = document.getElementById('sbNoSessions');
    var sbSessionCount = document.getElementById('sbSessionCount');
    var searchInput = document.getElementById('searchInput');
    var sumVis = document.getElementById('sumVis');
    var sumTot = document.getElementById('sumTot');
    var sumRange = document.getElementById('sumRange');
    var modalNameInp = document.getElementById('modalName');
    var modalInput = document.getElementById('modalInput');

// ── Init Bootstrap components ──────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        bsModal = new bootstrap.Modal(document.getElementById('newLogModal'));
        bsToast = new bootstrap.Toast(document.getElementById('appToast'));

        // Wire buttons
        document.getElementById('btnParse').addEventListener('click', parseLog);
        document.getElementById('btnSample').addEventListener('click', loadSample);
        document.getElementById('btnClearAll').addEventListener('click', clearAll);
        document.getElementById('btnClearFilters').addEventListener('click', clearFilters);
        document.getElementById('btnCollapseAll').addEventListener('click', collapseAll);
        document.getElementById('btnCopy').addEventListener('click', copyVisible);
        document.getElementById('btnNewLog').addEventListener('click', openModal);
        document.getElementById('modalParse').addEventListener('click', parseModalLog);
        searchInput.addEventListener('input', applyFilters);

        // Focus textarea when modal opens
        document.getElementById('newLogModal').addEventListener('shown.bs.modal', function () {
            modalInput.focus();
        });

        // Entry expand/collapse delegation
        logList.addEventListener('click', function (e) {
            var entry = e.target.closest('.entry');
            if (entry) entry.classList.toggle('open');
        });

        // Keyboard
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') clearFilters();
            if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
                e.preventDefault();
                searchInput.focus();
            }
        });

        // Init
        buildSessionsList();
        var sessions = loadSessions();
        if (sessions.length > 0) switchToSession(sessions[0].id);
    });

// ── Parser ────────────────────────────────────────────
    function parseLog() {
        var raw = logInput.value.trim();
        if (!raw) return;
        allEntries = parseLaravelLog(raw);
        var sid = saveSession(null, raw, allEntries);
        currentSessionId = sid;
        renderAll();
        buildSessionsList();
        if (allEntries.length > 0) inpZone.style.display = 'none';
    }

    function parseLaravelLog(text) {
        var entries = [];
        var re = /^\[(\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}[^\]]*)\]\s+(\w+)\.(\w+):\s+([\s\S]*)/;
        var normalised = text
            .replace(/\r\n/g, '\n')
            .replace(/\r/g, '\n')
            .replace(/(?<!\\)\\n/g, '\n');
        var ENTRY_START = /(?=\[\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2})/g;
        var chunks = normalised.split(ENTRY_START).filter(function (c) {
            return c.trim();
        });
        for (var i = 0; i < chunks.length; i++) {
            var chunk = chunks[i].trim();
            var nl = chunk.indexOf('\n');
            var header = nl >= 0 ? chunk.slice(0, nl) : chunk;
            var body = nl >= 0 ? chunk.slice(nl + 1) : '';
            var m = header.match(re);
            if (!m) continue;
            entries.push(finalise({
                datetime: m[1].replace('T', ' '),
                channel: m[2].toLowerCase(),
                level: m[3].toUpperCase(),
                message: m[4].trim(),
                rawBody: body
            }));
        }
        return entries;
    }

    function finalise(e) {
        var body = (e.rawBody || '').trim();
        var msgCtxMatch = e.message.match(/^([\s\S]*?)\s*(\{[\s\S]*\})\s*$/);
        if (msgCtxMatch) {
            try {
                e.context = JSON.stringify(JSON.parse(msgCtxMatch[2]), null, 2);
                e.message = msgCtxMatch[1].trim();
            } catch (_) {
            }
        }
        body = body.replace(/\s*"\}\s*$/, '');
        var sections = body.split(/\[previous exception\]/gi);
        var mainSection = sections[0].replace(/^\[stacktrace\]\s*/i, '').trim();
        e.stack = mainSection;
        e.frames = parseStack(mainSection);
        e.prevExceptions = [];
        for (var i = 1; i < sections.length; i++) {
            var sec = sections[i].trim();
            var stParts = sec.split(/\[stacktrace\]/i);
            e.prevExceptions.push({
                header: stParts[0].replace(/^\[object\]\s*/i, '').trim(),
                frames: parseStack(stParts[1] ? stParts[1].trim() : '')
            });
        }
        if (!e.context && body) {
            var jm = body.match(/(\{[\s\S]*?\})\s*$/);
            if (jm) {
                try {
                    e.context = JSON.stringify(JSON.parse(jm[1]), null, 2);
                } catch (_) {
                }
            }
        }
        e.id = Math.random().toString(36).slice(2);
        return e;
    }

    function parseStack(stack) {
        if (!stack) return [];
        var frames = [], lines = stack.split('\n'), exMsg = '', frameLines = [];
        for (var i = 0; i < lines.length; i++) {
            var l = lines[i].trim();
            if (/^#\d+/.test(l) || /^\s*at /.test(lines[i])) frameLines.push(lines[i]);
            else if (l && !exMsg) exMsg = l;
        }
        if (exMsg) frames.push({type: 'ex', text: exMsg});
        for (var j = 0; j < frameLines.length; j++) {
            var fl = frameLines[j].trim();
            var pm = fl.match(/^#(\d+)\s+(.*?)\((\d+)\):\s*(.*)/);
            if (pm) {
                frames.push({type: 'frame', num: pm[1], file: pm[2], line: pm[3], fn: pm[4]});
                continue;
            }
            var jm2 = fl.match(/^at\s+(.*?)\s+\((.*?):(\d+):\d+\)/);
            if (jm2) {
                frames.push({type: 'frame', num: j, file: jm2[2], line: jm2[3], fn: jm2[1]});
                continue;
            }
            if (fl) frames.push({type: 'raw', text: fl});
        }
        return frames;
    }

// ── Render ────────────────────────────────────────────
    function renderAll() {
        buildSidebar();
        buildFilterBtns();
        updateStats();
        applyFilters();
    }

    function countByLevel() {
        var c = {};
        allEntries.forEach(function (e) {
            c[e.level] = (c[e.level] || 0) + 1;
        });
        return c;
    }

    function buildSidebar() {
        var counts = countByLevel();
        sbLevels.innerHTML = '';
        LEVELS.forEach(function (lv) {
            var n = counts[lv] || 0;
            if (!n) return;
            var d = document.createElement('div');
            d.className = 'lv-item' + (activeFilters.has(lv) ? ' active' : '');
            if (activeFilters.has(lv)) d.style.color = LC[lv];
            var dot = document.createElement('span');
            dot.className = 'lv-dot';
            dot.style.background = LC[lv];
            var nm = document.createElement('span');
            nm.textContent = lv;
            var cnt = document.createElement('span');
            cnt.className = 'lv-count';
            cnt.textContent = n;
            d.appendChild(dot);
            d.appendChild(nm);
            d.appendChild(cnt);
            d.addEventListener('click', function () {
                toggleFilter(lv);
            });
            sbLevels.appendChild(d);
        });
    }

    function buildFilterBtns() {
        var counts = countByLevel();
        filterBtns.innerHTML = '';
        LEVELS.forEach(function (lv) {
            var n = counts[lv] || 0;
            if (!n) return;
            var b = document.createElement('button');
            b.className = 'lv-filter-btn' + (activeFilters.has(lv) ? ' active' : '');
            b.setAttribute('data-lv', lv);
            var dot = document.createElement('span');
            dot.style.cssText = 'width:6px;height:6px;border-radius:50%;background:' + LC[lv] + ';display:inline-block;';
            b.appendChild(dot);
            b.appendChild(document.createTextNode(' ' + lv + ' '));
            var bold = document.createElement('b');
            bold.textContent = n;
            b.appendChild(bold);
            b.addEventListener('click', function () {
                toggleFilter(lv);
            });
            filterBtns.appendChild(b);
        });
    }

    function toggleFilter(lv) {
        if (activeFilters.has(lv)) activeFilters.delete(lv); else activeFilters.add(lv);
        buildSidebar();
        buildFilterBtns();
        applyFilters();
    }

    function applyFilters() {
        var q = searchInput.value.toLowerCase().trim();
        var visible = allEntries.filter(function (e) {
            if (activeFilters.size > 0 && !activeFilters.has(e.level)) return false;
            if (q) {
                var hay = (e.message + (e.stack || '') + e.channel + e.datetime).toLowerCase();
                if (hay.indexOf(q) === -1) return false;
            }
            return true;
        });
        renderList(visible);
    }

    function renderList(entries) {
        if (allEntries.length === 0) {
            logList.innerHTML = '';
            logEmpty.style.display = 'flex';
            updateSummary(0, 0);
            return;
        }
        logEmpty.style.display = 'none';
        if (entries.length === 0) {
            logList.innerHTML = '<div class="no-res">No entries match your filters</div>';
            updateSummary(0, allEntries.length);
            return;
        }
        var frag = document.createDocumentFragment();
        entries.forEach(function (e) {
            frag.appendChild(buildEntry(e));
        });
        logList.innerHTML = '';
        logList.appendChild(frag);
        var dates = allEntries.map(function (e) {
            return e.datetime;
        }).sort();
        if (dates.length > 1) sumRange.textContent = dates[0] + '  →  ' + dates[dates.length - 1];
        else if (dates.length) sumRange.textContent = dates[0];
        updateSummary(entries.length, allEntries.length);
    }

    function buildEntry(e) {
        var hasStack = e.frames.length > 0 || e.context || (e.prevExceptions && e.prevExceptions.length > 0);
        var wrap = document.createElement('div');
        wrap.className = 'entry';
        wrap.id = 'entry-' + e.id;

        var head = document.createElement('div');
        head.className = 'entry-head';
        var bar = document.createElement('div');
        bar.className = 'lvbar bar-' + e.level;
        var meta = document.createElement('div');
        meta.className = 'emeta';
        var top = document.createElement('div');
        top.className = 'emeta-top';

        var badge = document.createElement('span');
        badge.className = 'lvbadge lc-' + e.level;
        badge.textContent = e.level;
        var ch = document.createElement('span');
        ch.className = 'ech';
        ch.textContent = e.channel;
        var time = document.createElement('span');
        time.className = 'etime';
        time.textContent = e.datetime;

        top.appendChild(badge);
        top.appendChild(ch);
        if (hasStack) {
            var stklbl = document.createElement('span');
            stklbl.className = 'badge bg-secondary bg-opacity-25 text-secondary';
            stklbl.style.cssText = 'font-size:9px;font-family:var(--mono);';
            stklbl.textContent = '▸ stack';
            top.appendChild(stklbl);
        }
        top.appendChild(time);

        var msg = document.createElement('div');
        msg.className = 'emsg';
        msg.textContent = e.message;
        meta.appendChild(top);
        meta.appendChild(msg);
        head.appendChild(bar);
        head.appendChild(meta);
        wrap.appendChild(head);

        if (hasStack) {
            var stk = document.createElement('div');
            stk.className = 'estk';
            buildStack(e, stk);
            wrap.appendChild(stk);
        }
        return wrap;
    }

    function buildStack(e, container) {
        if (e.frames.length) {
            var hdr = document.createElement('div');
            hdr.className = 'stk-hdr';
            hdr.textContent = 'Stack Trace';
            container.appendChild(hdr);
            container.appendChild(buildFrameBlock(e.frames));
        }
        if (e.prevExceptions && e.prevExceptions.length) {
            e.prevExceptions.forEach(function (px, idx) {
                var ph = document.createElement('div');
                ph.className = 'stk-hdr';
                ph.style.cssText = 'margin-top:10px;color:var(--c-alert);';
                ph.textContent = 'Previous Exception ' + (idx + 1);
                container.appendChild(ph);
                if (px.header) {
                    var pmsg = document.createElement('div');
                    pmsg.style.cssText = 'font-family:var(--mono);font-size:11px;color:#6b7a91;margin-bottom:5px;word-break:break-all;line-height:1.6;';
                    pmsg.textContent = px.header;
                    container.appendChild(pmsg);
                }
                if (px.frames.length) container.appendChild(buildFrameBlock(px.frames));
            });
        }
        if (e.context) {
            var ch = document.createElement('div');
            ch.className = 'stk-hdr';
            ch.style.marginTop = '10px';
            ch.textContent = 'Context';
            var cb = document.createElement('div');
            cb.className = 'ctx-box';
            cb.textContent = e.context;
            container.appendChild(ch);
            container.appendChild(cb);
        }
    }

    function buildFrameBlock(frames) {
        var block = document.createElement('div');
        block.className = 'stk-block';
        frames.forEach(function (f) {
            var row = document.createElement('div');
            if (f.type === 'ex') {
                row.className = 'stk-frame';
                var n = document.createElement('span');
                n.className = 'stk-num';
                n.textContent = '⚠';
                var fi = document.createElement('span');
                fi.className = 'stk-file';
                fi.style.color = 'var(--c-error)';
                fi.textContent = f.text;
                row.appendChild(n);
                row.appendChild(fi);
            } else if (f.type === 'frame') {
                var isV = f.file && (f.file.indexOf('/vendor/') >= 0 || f.file.indexOf('node_modules') >= 0);
                row.className = 'stk-frame' + (isV ? ' stk-vendor' : '');
                var n2 = document.createElement('span');
                n2.className = 'stk-num';
                n2.textContent = '#' + f.num;
                var fi2 = document.createElement('span');
                fi2.className = 'stk-file';
                fi2.textContent = f.file || '';
                var ln = document.createElement('span');
                ln.className = 'stk-line';
                ln.textContent = ':' + f.line;
                row.appendChild(n2);
                row.appendChild(fi2);
                row.appendChild(ln);
                if (f.fn) {
                    var fn = document.createElement('span');
                    fn.className = 'stk-fn';
                    fn.textContent = f.fn;
                    row.appendChild(fn);
                }
            } else {
                row.className = 'stk-frame';
                var n3 = document.createElement('span');
                n3.className = 'stk-num';
                n3.textContent = '·';
                var fi3 = document.createElement('span');
                fi3.className = 'stk-file';
                fi3.style.color = '#6b7a91';
                fi3.textContent = f.text;
                row.appendChild(n3);
                row.appendChild(fi3);
            }
            block.appendChild(row);
        });
        return block;
    }

// ── Stats ─────────────────────────────────────────────
    function updateStats() {
        var c = countByLevel();
        document.getElementById('sErr').textContent = (c.EMERGENCY || 0) + (c.ALERT || 0) + (c.CRITICAL || 0) + (c.ERROR || 0);
        document.getElementById('sWarn').textContent = c.WARNING || 0;
        document.getElementById('sInfo').textContent = (c.INFO || 0) + (c.NOTICE || 0);
        document.getElementById('sTotal').textContent = allEntries.length;
    }

    function updateSummary(v, t) {
        sumVis.textContent = v;
        sumTot.textContent = t;
    }

// ── localStorage sessions ──────────────────────────────
    function loadSessions() {
        try {
            return JSON.parse(localStorage.getItem(LS_KEY) || '[]');
        } catch (_) {
            return [];
        }
    }

    function saveSessions(list) {
        try {
            localStorage.setItem(LS_KEY, JSON.stringify(list));
        } catch (_) {
        }
    }

    function saveSession(existingId, rawText, entries) {
        var sessions = loadSessions();
        var counts = {};
        entries.forEach(function (e) {
            counts[e.level] = (counts[e.level] || 0) + 1;
        });
        var errs = (counts.EMERGENCY || 0) + (counts.ALERT || 0) + (counts.CRITICAL || 0) + (counts.ERROR || 0);
        var first = entries.length > 0 ? entries[0].datetime : '';
        var autoName = first ? first.slice(0, 16) + ' (' + entries.length + ' entries)' : 'Log ' + new Date().toLocaleString();
        var sid = existingId || ('s' + Date.now());
        var session = {
            id: sid,
            name: autoName,
            savedAt: new Date().toLocaleString(),
            total: entries.length,
            errors: errs,
            rawText: rawText
        };
        var idx = sessions.findIndex(function (s) {
            return s.id === sid;
        });
        if (idx >= 0) sessions[idx] = session; else sessions.unshift(session);
        saveSessions(sessions);
        return sid;
    }

    function deleteSession(sid) {
        var sessions = loadSessions().filter(function (s) {
            return s.id !== sid;
        });
        saveSessions(sessions);
        if (currentSessionId === sid) {
            if (sessions.length > 0) switchToSession(sessions[0].id); else clearAll();
        }
        buildSessionsList();
    }

    function renameSession(sid, newName) {
        var sessions = loadSessions();
        var s = sessions.find(function (s) {
            return s.id === sid;
        });
        if (s) {
            s.name = newName;
            saveSessions(sessions);
            buildSessionsList();
        }
    }

    function switchToSession(sid) {
        var s = loadSessions().find(function (x) {
            return x.id === sid;
        });
        if (!s) return;
        currentSessionId = sid;
        allEntries = parseLaravelLog(s.rawText);
        activeFilters.clear();
        searchInput.value = '';
        sumRange.textContent = '';
        inpZone.style.display = 'none';
        renderAll();
        buildSessionsList();
        logWrap.scrollTop = 0;
    }

    function buildSessionsList() {
        var sessions = loadSessions();
        sbNoSessions.style.display = sessions.length ? 'none' : 'block';
        sbSessionCount.textContent = sessions.length || '';
        sbSessions.querySelectorAll('.session-item').forEach(function (el) {
            el.remove();
        });
        sessions.forEach(function (s) {
            var item = document.createElement('div');
            item.className = 'session-item' + (s.id === currentSessionId ? ' active' : '');

            var nameEl = document.createElement('div');
            nameEl.className = 'si-name';
            nameEl.title = s.name;
            nameEl.textContent = s.name;
            var metaEl = document.createElement('div');
            metaEl.className = 'si-meta';
            if (s.errors > 0) {
                var errSp = document.createElement('span');
                errSp.className = 'si-err';
                errSp.textContent = s.errors + ' err';
                var dot = document.createElement('span');
                dot.textContent = '·';
                metaEl.appendChild(errSp);
                metaEl.appendChild(dot);
            }
            var totSp = document.createElement('span');
            totSp.textContent = s.total + ' entries';
            metaEl.appendChild(totSp);

            var delBtn = document.createElement('button');
            delBtn.className = 'si-del';
            delBtn.title = 'Delete session';
            delBtn.innerHTML = '<i class="bi bi-x"></i>';
            delBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                if (confirm('Delete "' + s.name + '"?')) deleteSession(s.id);
            });

            item.appendChild(nameEl);
            item.appendChild(metaEl);
            item.appendChild(delBtn);
            item.addEventListener('click', function () {
                switchToSession(s.id);
            });
            item.addEventListener('dblclick', function (e) {
                e.stopPropagation();
                var nn = prompt('Rename:', s.name);
                if (nn && nn.trim()) renameSession(s.id, nn.trim());
            });
            sbSessions.appendChild(item);
        });
    }

// ── Modal ─────────────────────────────────────────────
    function openModal() {
        modalNameInp.value = '';
        modalInput.value = '';
        bsModal.show();
    }

    function parseModalLog() {
        var raw = modalInput.value.trim();
        if (!raw) {
            showToast('Please paste log content first');
            return;
        }
        var entries = parseLaravelLog(raw);
        var sid = saveSession(null, raw, entries);
        var cn = modalNameInp.value.trim();
        if (cn) renameSession(sid, cn);
        currentSessionId = sid;
        allEntries = entries;
        activeFilters.clear();
        searchInput.value = '';
        sumRange.textContent = '';
        inpZone.style.display = 'none';
        bsModal.hide();
        renderAll();
        buildSessionsList();
        logWrap.scrollTop = 0;
        showToast('Session saved — ' + entries.length + ' entries');
    }

// ── Actions ───────────────────────────────────────────
    function clearFilters() {
        activeFilters.clear();
        searchInput.value = '';
        buildSidebar();
        buildFilterBtns();
        applyFilters();
    }

    function collapseAll() {
        document.querySelectorAll('.entry.open').forEach(function (el) {
            el.classList.remove('open');
        });
    }

    function clearAll() {
        allEntries = [];
        currentSessionId = null;
        activeFilters.clear();
        logInput.value = '';
        logList.innerHTML = '';
        logEmpty.style.display = 'flex';
        inpZone.style.display = '';
        filterBtns.innerHTML = '';
        sbLevels.innerHTML = '';
        searchInput.value = '';
        sumRange.textContent = '';
        updateStats();
        updateSummary(0, 0);
        buildSessionsList();
    }

    function copyVisible() {
        var lines = [];
        document.querySelectorAll('.entry').forEach(function (el) {
            var t = el.querySelector('.etime'), lv = el.querySelector('.lvbadge'), mg = el.querySelector('.emsg');
            if (t && lv && mg) lines.push('[' + t.textContent + '] ' + lv.textContent + ': ' + mg.textContent);
        });
        navigator.clipboard.writeText(lines.join('\n')).then(function () {
            showToast('Copied ' + lines.length + ' entries ✓');
        });
    }

    function showToast(msg) {
        document.getElementById('toastMsg').textContent = msg;
        bsToast.show();
    }

// ── Sample ────────────────────────────────────────────
    function loadSample() {
        logInput.value = [
            '[2024-03-15 08:12:34] production.ERROR: SQLSTATE[HY000] [2002] Connection refused (SQL: select * from users) {"userId":42,"ip":"192.168.1.5"}',
            '#0 /var/www/vendor/laravel/framework/src/Illuminate/Database/Connection.php(760): PDO->__construct()',
            '#1 /var/www/app/Http/Controllers/AuthController.php(45): Connector->createConnection()',
            '',
            '[2024-03-15 08:12:40] production.WARNING: Deprecated function called: Use sendEmail() instead {"method":"sendMail","line":128}',
            '',
            '[2024-03-15 08:13:01] production.INFO: User logged in {"user_id":7,"email":"admin@example.com"}',
            '',
            '[2024-03-15 08:15:22] production.ERROR: Undefined variable: userData {"file":"/var/www/app/Services/UserService.php","line":78}',
            '#0 /var/www/app/Services/UserService.php(78): UserService->getProfile()',
            '#1 /var/www/app/Http/Controllers/UserController.php(32): UserService->findUser()',
            '',
            '[2024-03-15 08:16:45] production.DEBUG: Cache miss for key: user_profile_7 {"ttl":3600,"store":"redis"}',
            '',
            '[2024-03-15 08:18:00] production.CRITICAL: Out of memory: Kill process 4892 {"memory_limit":"256M","usage":"258M"}',
            '#0 /var/www/app/Jobs/ProcessReportJob.php(112): Worker->process()',
            '',
            '[2024-03-15 08:19:11] production.WARNING: Queue job failed after 3 attempts {"job":"SendNewsletterJob","tries":3}',
            '',
            '[2024-03-15 08:20:33] production.INFO: Scheduled task completed {"task":"CleanTempFiles","deleted":142}',
            '',
            '[2024-03-15 08:23:58] production.NOTICE: Rate limit reached for IP 203.0.113.42 {"limit":100,"endpoint":"/api/search"}',
            '',
            '[2024-03-15 08:27:12] production.EMERGENCY: Disk space critically low {"disk":"/dev/sda1","used":"99%","free":"512M"}',
            '',
            '[2024-03-15 08:28:01] production.ALERT: Failed login attempts exceeded threshold {"ip":"45.33.32.156","attempts":15}'
        ].join('\n');
        parseLog();
    }

})();
