/* ─────────────────────────────────────────────────────
Calculator class — each instance has its own state
───────────────────────────────────────────────────── */
class Calc {
    constructor(id, num) {
        this.id = id;
        this.num = num;
        this.val = '0';
        this.expr = '';
        this.op = null;
        this.prev = null;
        this.justCalc = false;
        this.memory = null;
        this.mode = 'standard';
        this.progBase = 'dec';
        this.el = this._build();
    }

    /* ── DOM ── */
    _build() {
        const d = document.createElement('div');
        d.className = 'calc-win';
        d.id = this.id;
        d.innerHTML = this._tpl();
        return d;
    }

    _tpl() {
        const q = this.id; // shorthand
        const cb = (fn) => `C('${q}').${fn}`;
        const inp = (v) => `${cb(`inp('${v}')`)}`;
        return `
<div class="c-titlebar">
  <div class="c-tb-left">🖩 <span style="font-size:12px">Calculator</span><span class="focus-badge">⌨ active</span></div>
  <div class="c-tb-ctrl">
    <button class="c-tb-btn cx" onclick="CalcManager.remove('${q}')">✕</button>
  </div>
</div>
<div class="c-tabs">
  <button class="c-tab on"  data-m="standard"   onclick="${cb("setMode('standard')")}">Standard</button>
  <button class="c-tab"     data-m="scientific"  onclick="${cb("setMode('scientific')")}">Scientific</button>
  <button class="c-tab"     data-m="programmer"  onclick="${cb("setMode('programmer')")}">Programmer</button>
  <button class="c-tab"     data-m="converter"   onclick="${cb("setMode('converter')")}">Converter</button>
</div>
<div class="c-display" id="${q}-disp">
  <div class="c-mode-lbl" id="${q}-mlbl">Standard</div>
  <div class="c-expr" id="${q}-expr">&nbsp;</div>
  <div class="c-main" id="${q}-main">0</div>
</div>
<div class="c-bases" id="${q}-bases">
  <div class="c-brow"><span class="c-blbl">HEX</span><span class="c-bval" id="${q}-hV" onclick="${cb("switchBase('hex')")}">0</span></div>
  <div class="c-brow"><span class="c-blbl">DEC</span><span class="c-bval active" id="${q}-dV" onclick="${cb("switchBase('dec')")}">0</span></div>
  <div class="c-brow"><span class="c-blbl">OCT</span><span class="c-bval" id="${q}-oV" onclick="${cb("switchBase('oct')")}">0</span></div>
  <div class="c-brow"><span class="c-blbl">BIN</span><span class="c-bval" id="${q}-bV" onclick="${cb("switchBase('bin')")}">0</span></div>
</div>
<div class="c-bits" id="${q}-bits"><div class="c-bit-groups" id="${q}-bitG"></div></div>
<div class="c-membar" id="${q}-mem">
  <button class="c-mbtn" id="${q}-mc" disabled onclick="${cb('memClear()')}">MC</button>
  <button class="c-mbtn" id="${q}-mr" disabled onclick="${cb('memRecall()')}">MR</button>
  <button class="c-mbtn" onclick="${cb('memAdd()')}">M+</button>
  <button class="c-mbtn" onclick="${cb('memSub()')}">M−</button>
  <button class="c-mbtn" onclick="${cb('memStore()')}">MS</button>
</div>

<!-- Standard -->
<div class="c-grid cg-std on" id="${q}-std">
  <button class="c-btn c-fn"  onclick="${cb('pct()')}">%</button>
  <button class="c-btn c-clr" onclick="${cb('ce()')}">CE</button>
  <button class="c-btn c-clr" onclick="${cb('ca()')}">C</button>
  <button class="c-btn c-fn"  onclick="${cb('back()')}">⌫</button>
  <button class="c-btn c-fn"  onclick="${cb('frac()')}">¹/x</button>
  <button class="c-btn c-fn"  onclick="${cb('sq()')}">x²</button>
  <button class="c-btn c-fn"  onclick="${cb('sqr()')}">√x</button>
  <button class="c-btn c-op"  onclick="${cb("setOp('/')")}">÷</button>
  <button class="c-btn c-num" onclick="${inp(7)}">7</button>
  <button class="c-btn c-num" onclick="${inp(8)}">8</button>
  <button class="c-btn c-num" onclick="${inp(9)}">9</button>
  <button class="c-btn c-op"  onclick="${cb("setOp('*')")}">×</button>
  <button class="c-btn c-num" onclick="${inp(4)}">4</button>
  <button class="c-btn c-num" onclick="${inp(5)}">5</button>
  <button class="c-btn c-num" onclick="${inp(6)}">6</button>
  <button class="c-btn c-op"  onclick="${cb("setOp('-')")}">−</button>
  <button class="c-btn c-num" onclick="${inp(1)}">1</button>
  <button class="c-btn c-num" onclick="${inp(2)}">2</button>
  <button class="c-btn c-num" onclick="${inp(3)}">3</button>
  <button class="c-btn c-op"  onclick="${cb("setOp('+')")}">+</button>
  <button class="c-btn c-fn"  onclick="${cb('sign()')}">+/−</button>
  <button class="c-btn c-num" onclick="${inp(0)}">0</button>
  <button class="c-btn c-num" onclick="${cb('dot()')}">.</button>
  <button class="c-btn c-eq"  onclick="${cb('calc()')}"> = </button>
</div>

<!-- Scientific -->
<div class="c-grid cg-sci" id="${q}-sci">
  <button class="c-btn c-fn" style="font-size:11px" onclick="${cb("sci('sin')")}">sin</button>
  <button class="c-btn c-fn" style="font-size:11px" onclick="${cb("sci('cos')")}">cos</button>
  <button class="c-btn c-fn" style="font-size:11px" onclick="${cb("sci('tan')")}">tan</button>
  <button class="c-btn c-fn" style="font-size:11px" onclick="${cb("sci('log')")}">log</button>
  <button class="c-btn c-fn" style="font-size:11px" onclick="${cb("sci('ln')")}">ln</button>
  <button class="c-btn c-fn" style="font-size:11px" onclick="${cb("sci('asin')")}">sin⁻¹</button>
  <button class="c-btn c-fn" style="font-size:11px" onclick="${cb("sci('acos')")}">cos⁻¹</button>
  <button class="c-btn c-fn" style="font-size:11px" onclick="${cb("sci('atan')")}">tan⁻¹</button>
  <button class="c-btn c-fn" style="font-size:11px" onclick="${cb("sci('log2')")}">log₂</button>
  <button class="c-btn c-fn" style="font-size:11px" onclick="${cb("sci('exp')")}">eˣ</button>
  <button class="c-btn c-fn" style="font-size:11px" onclick="${cb("sci('pi')")}">π</button>
  <button class="c-btn c-fn" style="font-size:11px" onclick="${cb("sci('e')")}">e</button>
  <button class="c-btn c-fn" style="font-size:12px" onclick="${cb("sci('pow')")}">xʸ</button>
  <button class="c-btn c-fn" style="font-size:11px" onclick="${cb("sci('cbrt')")}">∛x</button>
  <button class="c-btn c-fn" style="font-size:11px" onclick="${cb("sci('abs')")}">|x|</button>
  <button class="c-btn c-fn"  onclick="${cb('pct()')}">%</button>
  <button class="c-btn c-clr" onclick="${cb('ce()')}">CE</button>
  <button class="c-btn c-clr" onclick="${cb('ca()')}">C</button>
  <button class="c-btn c-fn"  onclick="${cb('back()')}">⌫</button>
  <button class="c-btn c-op"  onclick="${cb("setOp('/')")}">÷</button>
  <button class="c-btn c-fn"  onclick="${cb('frac()')}">¹/x</button>
  <button class="c-btn c-num" onclick="${inp(7)}">7</button>
  <button class="c-btn c-num" onclick="${inp(8)}">8</button>
  <button class="c-btn c-num" onclick="${inp(9)}">9</button>
  <button class="c-btn c-op"  onclick="${cb("setOp('*')")}">×</button>
  <button class="c-btn c-fn"  onclick="${cb('sq()')}">x²</button>
  <button class="c-btn c-num" onclick="${inp(4)}">4</button>
  <button class="c-btn c-num" onclick="${inp(5)}">5</button>
  <button class="c-btn c-num" onclick="${inp(6)}">6</button>
  <button class="c-btn c-op"  onclick="${cb("setOp('-')")}">−</button>
  <button class="c-btn c-fn"  onclick="${cb('sqr()')}">√x</button>
  <button class="c-btn c-num" onclick="${inp(1)}">1</button>
  <button class="c-btn c-num" onclick="${inp(2)}">2</button>
  <button class="c-btn c-num" onclick="${inp(3)}">3</button>
  <button class="c-btn c-op"  onclick="${cb("setOp('+')")}">+</button>
  <button class="c-btn c-fn"  onclick="${cb('sign()')}">+/−</button>
  <button class="c-btn c-fn"  onclick="${cb("sci('fac')")}">n!</button>
  <button class="c-btn c-num" onclick="${inp(0)}">0</button>
  <button class="c-btn c-num" onclick="${cb('dot()')}">.</button>
  <button class="c-btn c-eq"  onclick="${cb('calc()')}"> = </button>
</div>

<!-- Programmer -->
<div class="c-grid cg-prog" id="${q}-prog">
  <button class="c-btn c-bop" onclick="${cb("bitOp('AND')")}">AND</button>
  <button class="c-btn c-bop" onclick="${cb("bitOp('OR')")}">OR</button>
  <button class="c-btn c-bop" onclick="${cb("bitOp('XOR')")}">XOR</button>
  <button class="c-btn c-bop" onclick="${cb("bitOp('NOT')")}">NOT</button>
  <button class="c-btn c-bop" onclick="${cb("bitOp('LSH')")}">LSH</button>
  <button class="c-btn c-bop" onclick="${cb("bitOp('RSH')")}">RSH</button>
  <button class="c-btn c-clr" onclick="${cb('ce()')}">CE</button>
  <button class="c-btn c-clr" onclick="${cb('ca()')}">C</button>
  <button class="c-btn c-fn"  onclick="${cb('back()')}">⌫</button>
  <button class="c-btn c-op"  onclick="${cb("setOp('/')")}">÷</button>
  <button class="c-btn c-hex" id="${q}-xA" onclick="${inp('A')}">A</button>
  <button class="c-btn c-hex" id="${q}-xB" onclick="${inp('B')}">B</button>
  <button class="c-btn c-num" onclick="${inp(7)}">7</button>
  <button class="c-btn c-num" onclick="${inp(8)}">8</button>
  <button class="c-btn c-num" onclick="${inp(9)}">9</button>
  <button class="c-btn c-hex" id="${q}-xC" onclick="${inp('C')}">C</button>
  <button class="c-btn c-hex" id="${q}-xD" onclick="${inp('D')}">D</button>
  <button class="c-btn c-num" onclick="${inp(4)}">4</button>
  <button class="c-btn c-num" onclick="${inp(5)}">5</button>
  <button class="c-btn c-num" onclick="${inp(6)}">6</button>
  <button class="c-btn c-hex" id="${q}-xE" onclick="${inp('E')}">E</button>
  <button class="c-btn c-hex" id="${q}-xF" onclick="${inp('F')}">F</button>
  <button class="c-btn c-num" onclick="${inp(1)}">1</button>
  <button class="c-btn c-num" onclick="${inp(2)}">2</button>
  <button class="c-btn c-num" onclick="${inp(3)}">3</button>
  <button class="c-btn c-op"  onclick="${cb("setOp('*')")}">×</button>
  <button class="c-btn c-op"  onclick="${cb("setOp('-')")}">−</button>
  <button class="c-btn c-fn"  onclick="${cb('sign()')}">+/−</button>
  <button class="c-btn c-num" onclick="${inp(0)}">0</button>
  <button class="c-btn c-eq"  onclick="${cb('calc()')}"> = </button>
  <button class="c-btn c-op"  style="grid-column:1/3" onclick="${cb("setOp('+')")}">+</button>
  <button class="c-btn c-fn"  onclick="${cb('modOp()')}">MOD</button>
  <button class="c-btn c-fn"  onclick="${cb('dot()')}">.</button>
  <button class="c-btn c-op"  onclick="${cb("setOp('/')")}">÷</button>
</div>

<!-- Converter -->
<div class="c-grid cg-conv" id="${q}-conv">
  <div class="conv-panel">
    <h6>Number Base Converter</h6>
    <div class="conv-group">
      <div class="conv-lbl"><span class="conv-badge badge-dec">DEC</span> Decimal <span style="color:#333;font-size:10px">( 0–9 )</span></div>
      <input class="conv-inp" id="${q}-cDec" type="text" placeholder="0"
        onkeypress="C('${q}').convKey(event,/[0-9]/,'cDec')"
        oninput="C('${q}').convSan('cDec',/^[0-9]*$/);C('${q}').cvFrom('dec')">
    </div>
    <div class="conv-group">
      <div class="conv-lbl"><span class="conv-badge badge-bin">BIN</span> Binary <span style="color:#333;font-size:10px">( 0–1 )</span></div>
      <input class="conv-inp" id="${q}-cBin" type="text" placeholder="0"
        onkeypress="C('${q}').convKey(event,/[01]/,'cBin')"
        oninput="C('${q}').convSan('cBin',/^[01]*$/);C('${q}').cvFrom('bin')">
    </div>
    <div class="conv-group">
      <div class="conv-lbl"><span class="conv-badge badge-hex">HEX</span> Hexadecimal <span style="color:#333;font-size:10px">( 0–9, A–F )</span></div>
      <input class="conv-inp" id="${q}-cHex" type="text" placeholder="0"
        onkeypress="C('${q}').convKey(event,/[0-9a-fA-F]/,'cHex')"
        oninput="C('${q}').convSan('cHex',/^[0-9a-fA-F]*$/);C('${q}').cvFrom('hex')">
    </div>
    <div class="conv-group">
      <div class="conv-lbl"><span class="conv-badge badge-oct">OCT</span> Octal <span style="color:#333;font-size:10px">( 0–7 )</span></div>
      <input class="conv-inp" id="${q}-cOct" type="text" placeholder="0"
        onkeypress="C('${q}').convKey(event,/[0-7]/,'cOct')"
        oninput="C('${q}').convSan('cOct',/^[0-7]*$/);C('${q}').cvFrom('oct')">
    </div>
    <div class="conv-divider"></div>
    <div style="text-align:center;font-size:11px;color:#2a2a2a;font-family:'JetBrains Mono',monospace">Type in any field — others update instantly</div>
  </div>
</div>`;
    }

    /* ── Helpers ── */
    _e(s) {
        return document.getElementById(this.id + s);
    }

    /* ── Render ── */
    render() {
        let v = this.val;
        if (this.mode === 'programmer') {
            if (this.progBase === 'hex') v = (parseInt(v) || 0).toString(16).toUpperCase();
            else if (this.progBase === 'bin') v = (parseInt(v) || 0).toString(2);
            else if (this.progBase === 'oct') v = (parseInt(v) || 0).toString(8);
        }
        const m = this._e('-main');
        m.textContent = v || '0';
        m.className = 'c-main' + (v.length > 10 ? ' sm' : '');
        this._e('-expr').textContent = this.expr || '\u00a0';
        if (this.mode === 'programmer') {
            this._renderBases();
            this._renderBits();
        }
    }

    _renderBases() {
        const n = parseInt(this.val) || 0;
        this._e('-hV').textContent = n.toString(16).toUpperCase() || '0';
        this._e('-dV').textContent = n.toString(10) || '0';
        this._e('-oV').textContent = n.toString(8) || '0';
        this._e('-bV').textContent = n.toString(2) || '0';
        ['h', 'd', 'o', 'b'].forEach((x, i) => {
            const base = ['hex', 'dec', 'oct', 'bin'][i];
            this._e('-' + x + 'V').className = 'c-bval' + (base === this.progBase ? ' active' : '');
        });
    }

    _renderBits() {
        const n = (parseInt(this.val) || 0) & 0xFFFFFFFF;
        const bits = n.toString(2).padStart(32, '0');
        const g = this._e('-bitG');
        g.innerHTML = '';
        for (let i = 0; i < 4; i++) {
            const grp = document.createElement('div');
            grp.className = 'c-bit-group';
            for (let j = 0; j < 8; j++) {
                const pos = 31 - (i * 8 + j);
                const c = document.createElement('div');
                c.className = 'c-bit' + (bits[i * 8 + j] === '1' ? ' one' : '');
                c.textContent = bits[i * 8 + j];
                c.title = 'Bit ' + pos;
                c.onclick = () => {
                    this.val = ((parseInt(this.val) || 0) ^ (1 << pos)).toString();
                    this.render();
                };
                grp.appendChild(c);
            }
            g.appendChild(grp);
        }
    }

    /* ── Input ── */
    inp(ch) {
        if (this.justCalc) {
            this.val = '0';
            this.expr = '';
            this.justCalc = false;
        }
        const c = String(ch).toUpperCase();
        if (this.mode === 'programmer') {
            const ok = {bin: /[01]/, oct: /[0-7]/, dec: /[0-9]/, hex: /[0-9A-F]/}[this.progBase];
            if (!ok.test(c)) return;
            let raw = (parseInt(this.val) || 0).toString(
                this.progBase === 'hex' ? 16 : this.progBase === 'bin' ? 2 : this.progBase === 'oct' ? 8 : 10
            ).toUpperCase();
            if (raw === '0') raw = '';
            const parsed = parseInt(raw + c, this.progBase === 'hex' ? 16 : this.progBase === 'bin' ? 2 : this.progBase === 'oct' ? 8 : 10);
            this.val = isNaN(parsed) ? '0' : parsed.toString();
        } else {
            this.val = (this.val === '0' && c !== '.') ? c : this.val + c;
        }
        this.render();
    }

    dot() {
        if (this.mode === 'programmer') return;
        if (!this.val.includes('.')) this.val += '.';
        this.render();
    }

    back() {
        this.val = this.val.length <= 1 ? '0' : this.val.slice(0, -1);
        this.render();
    }

    ce() {
        this.val = '0';
        this.render();
    }

    ca() {
        this.val = '0';
        this.expr = '';
        this.op = null;
        this.prev = null;
        this.justCalc = false;
        this.render();
    }

    sign() {
        this.val = (parseFloat(this.val) * -1).toString();
        this.render();
    }

    /* ── Operators ── */
    setOp(op) {
        if (this.op && this.prev !== null && !this.justCalc) this.calc(true);
        this.prev = parseFloat(this.val);
        this.op = op;
        this.expr = this.prev + ' ' + ({'+': '+', '-': '−', '*': '×', '/': '÷'}[op] || op);
        this.justCalc = false;
        this.val = '0';
        this.render();
    }

    calc(fromOp = false) {
        if (['AND', 'OR', 'XOR', 'LSH', 'RSH', 'MOD'].includes(this.op)) {
            const a = parseInt(this.prev) || 0, b = parseInt(this.val) || 0;
            const r = {
                AND: (a & b) >>> 0,
                OR: (a | b) >>> 0,
                XOR: (a ^ b) >>> 0,
                LSH: (a << b) >>> 0,
                RSH: (a >> b) >>> 0,
                MOD: a % b
            }[this.op];
            this.val = r.toString();
            if (!fromOp) {
                this.op = null;
                this.prev = null;
                this.expr = '';
                this.justCalc = true;
            }
            this.render();
            return;
        }
        if (this.op === '**') {
            this.val = parseFloat(Math.pow(this.prev, parseFloat(this.val)).toFixed(10)).toString();
            this.op = null;
            this.prev = null;
            this.expr = '';
            this.justCalc = true;
            this.render();
            return;
        }
        if (!this.op || this.prev === null) return;
        const a = parseFloat(this.prev), b = parseFloat(this.val);
        let r;
        if (this.op === '+') r = a + b;
        else if (this.op === '-') r = a - b;
        else if (this.op === '*') r = a * b;
        else if (this.op === '/') {
            if (b === 0) {
                this.val = 'Error';
                this.op = null;
                this.prev = null;
                this.render();
                return;
            }
            r = a / b;
        }
        this.val = parseFloat(r.toFixed(10)).toString();
        if (!fromOp) {
            this.expr = '';
            this.op = null;
            this.prev = null;
            this.justCalc = true;
        } else {
            this.prev = parseFloat(this.val);
        }
        this.render();
    }

    /* ── Fn buttons ── */
    pct() {
        this.val = (this.prev !== null ? parseFloat(this.prev) * parseFloat(this.val) / 100 : parseFloat(this.val) / 100).toString();
        this.render();
    }

    frac() {
        this.val = (1 / parseFloat(this.val)).toString();
        this.render();
    }

    sq() {
        const v = parseFloat(this.val);
        this.val = (v * v).toString();
        this.render();
    }

    sqr() {
        this.val = Math.sqrt(parseFloat(this.val)).toString();
        this.render();
    }

    modOp() {
        this.setOp('MOD');
    }

    sci(fn) {
        const v = parseFloat(this.val), r2d = Math.PI / 180;
        if (fn === 'fac') {
            let r = 1;
            for (let i = 2; i <= Math.round(v); i++) r *= i;
            this.val = r.toString();
            this.render();
            return;
        }
        if (fn === 'pow') {
            this.prev = v;
            this.op = '**';
            this.expr = v + ' ^';
            this.val = '0';
            this.render();
            return;
        }
        const tbl = {
            sin: Math.sin(v * r2d),
            cos: Math.cos(v * r2d),
            tan: Math.tan(v * r2d),
            asin: Math.asin(v) * 180 / Math.PI,
            acos: Math.acos(v) * 180 / Math.PI,
            atan: Math.atan(v) * 180 / Math.PI,
            log: Math.log10(v),
            ln: Math.log(v),
            log2: Math.log2(v),
            exp: Math.exp(v),
            pi: Math.PI,
            e: Math.E,
            cbrt: Math.cbrt(v),
            abs: Math.abs(v)
        };
        if (tbl[fn] !== undefined) {
            this.val = parseFloat(tbl[fn].toFixed(10)).toString();
            this.render();
        }
    }

    /* ── Programmer ── */
    bitOp(op) {
        if (op === 'NOT') {
            this.val = ((~parseInt(this.val)) >>> 0).toString();
            this.render();
            return;
        }
        this.prev = parseInt(this.val) || 0;
        this.op = op;
        this.expr = this.prev + ' ' + op;
        this.val = '0';
        this.render();
    }

    switchBase(base) {
        this.progBase = base;
        ['A', 'B', 'C', 'D', 'E', 'F'].forEach(l => {
            const b = this._e('-x' + l);
            if (b) b.disabled = (base !== 'hex');
        });
        this.render();
    }

    /* ── Memory ── */
    memStore() {
        this.memory = parseFloat(this.val);
        this._upMem();
    }

    memRecall() {
        if (this.memory !== null) {
            this.val = this.memory.toString();
            this.render();
        }
    }

    memAdd() {
        this.memory = (this.memory || 0) + parseFloat(this.val);
        this._upMem();
    }

    memSub() {
        this.memory = (this.memory || 0) - parseFloat(this.val);
        this._upMem();
    }

    memClear() {
        this.memory = null;
        this._upMem();
    }

    _upMem() {
        const h = this.memory !== null;
        this._e('-mc').disabled = !h;
        this._e('-mr').disabled = !h;
    }

    /* ── Converter ── */
    convKey(e, pat, sfx) {
        const ch = String.fromCharCode(e.charCode);
        if (e.charCode === 0) return true;
        if (!pat.test(ch)) {
            const el = this._e('-' + sfx);
            el.classList.add('shake');
            setTimeout(() => el.classList.remove('shake'), 300);
            return false;
        }
        return true;
    }

    convSan(sfx, pat) {
        const el = this._e('-' + sfx);
        el.value = el.value.split('').filter(c => pat.test(c)).join('');
        if (sfx === 'cHex') el.value = el.value.toUpperCase();
    }

    cvFrom(base) {
        const f = {dec: this._e('-cDec'), bin: this._e('-cBin'), hex: this._e('-cHex'), oct: this._e('-cOct')};
        const v = f[base].value.trim();
        if (!v) {
            ['dec', 'bin', 'hex', 'oct'].filter(b => b !== base).forEach(b => {
                f[b].value = '';
                f[b].classList.remove('err');
            });
            return;
        }
        let dec;
        if (base === 'dec') dec = parseFloat(v);
        else if (base === 'bin') dec = parseInt(v, 2);
        else if (base === 'hex') dec = parseInt(v, 16);
        else if (base === 'oct') dec = parseInt(v, 8);
        const bad = isNaN(dec);
        ['dec', 'bin', 'hex', 'oct'].forEach(b => {
            if (b !== base) {
                f[b].classList.toggle('err', bad);
                if (!bad) {
                    const n = Math.floor(dec);
                    f[b].value = b === 'dec' ? n.toString() : b === 'bin' ? n.toString(2) : b === 'hex' ? n.toString(16).toUpperCase() : n.toString(8);
                }
            }
            if (bad && b === base) f[b].classList.add('err'); else f[base].classList.remove('err');
        });
        if (!bad) {
            const n = Math.floor(dec);
            this._e('-hV').textContent = n.toString(16).toUpperCase() || '0';
            this._e('-dV').textContent = n.toString(10) || '0';
            this._e('-oV').textContent = n.toString(8) || '0';
            this._e('-bV').textContent = n.toString(2) || '0';
        }
    }

    /* ── Mode ── */
    setMode(mode) {
        this.mode = mode;
        this.ca();
        this.el.querySelectorAll('.c-tab').forEach(t => t.classList.toggle('on', t.dataset.m === mode));
        [['std', 'standard'], ['sci', 'scientific'], ['prog', 'programmer'], ['conv', 'converter']].forEach(([k, m]) => {
            const g = this._e('-' + k);
            if (g) g.classList.toggle('on', m === mode);
        });
        const labels = {
            standard: 'Standard',
            scientific: 'Scientific',
            programmer: 'Programmer',
            converter: 'Converter'
        };
        this._e('-mlbl').textContent = labels[mode];
        const isConv = mode === 'converter', isProg = mode === 'programmer';
        this._e('-disp').classList.toggle('gone', isConv);
        this._e('-mem').classList.toggle('gone', isConv);
        this._e('-bases').classList.toggle('on', isConv || isProg);
        this._e('-bits').classList.toggle('on', isProg);
        if (isProg) {
            this.progBase = 'dec';
            this.switchBase('dec');
        }
        this.render();
    }
}

/* ── Global registry ── */
const _reg = {};

function C(id) {
    return _reg[id];
}

/* ── Manager ── */
const CalcManager = {
    seq: 0, ids: [], activeId: null,

    focus(id) {
        // Remove focused from all
        document.querySelectorAll('.calc-win.focused').forEach(el => el.classList.remove('focused'));
        // Update label colors
        document.querySelectorAll('.calc-num-label').forEach(el => el.style.color = '');
        this.activeId = id;
        const el = document.getElementById(id);
        if (el) {
            el.classList.add('focused');
            const lbl = el.closest('[id^="w"]')?.querySelector('.calc-num-label');
            if (lbl) lbl.style.color = '#0078d4';
        }
    },

    add() {
        this.seq++;
        const id = 'calc' + this.seq;
        const c = new Calc(id, this.seq);
        _reg[id] = c;
        this.ids.push(id);

        const wrap = document.createElement('div');
        wrap.id = 'w' + id;
        wrap.style.cssText = 'position:relative;flex-shrink:0;';
        // Click anywhere on calc to focus it
        wrap.addEventListener('mousedown', () => this.focus(id));
        wrap.appendChild(c.el);

        const lbl = document.createElement('div');
        lbl.className = 'calc-num-label';
        lbl.textContent = '#' + this.seq;
        wrap.appendChild(lbl);

        document.getElementById('desktop').appendChild(wrap);
        // Auto-focus the newly created calculator
        this.focus(id);
        this._ui();
    },

    remove(id) {
        const el = document.getElementById(id);
        if (!el) return;
        el.classList.add('dying');
        setTimeout(() => {
            const w = document.getElementById('w' + id);
            if (w) w.remove();
            delete _reg[id];
            this.ids = this.ids.filter(i => i !== id);
            // If removed was active, focus the last remaining
            if (this.activeId === id) {
                this.activeId = null;
                if (this.ids.length > 0) this.focus(this.ids[this.ids.length - 1]);
            }
            this._ui();
        }, 200);
    },

    closeAll() {
        [...this.ids].forEach(id => this.remove(id));
    },

    _ui() {
        const n = this.ids.length;
        document.getElementById('globalCounter').textContent = n;
        document.getElementById('emptyState').classList.toggle('gone', n > 0);
    }
};

/* ── Global Keyboard Router ── */
document.addEventListener('keydown', e => {
    const id = CalcManager.activeId;
    if (!id) return;
    const calc = _reg[id];
    if (!calc) return;
    // Don't intercept when user is typing in converter inputs
    if (document.activeElement && document.activeElement.classList.contains('conv-inp')) return;
    if (calc.mode === 'converter') return;

    const k = e.key;
    if (k >= '0' && k <= '9') {
        e.preventDefault();
        calc.inp(k);
    } else if (k === '.') {
        e.preventDefault();
        calc.dot();
    } else if (k === '+') {
        e.preventDefault();
        calc.setOp('+');
    } else if (k === '-') {
        e.preventDefault();
        calc.setOp('-');
    } else if (k === '*') {
        e.preventDefault();
        calc.setOp('*');
    } else if (k === '/') {
        e.preventDefault();
        calc.setOp('/');
    } else if (k === 'Enter' || k === '=') {
        e.preventDefault();
        calc.calc();
    } else if (k === 'Backspace') {
        e.preventDefault();
        calc.back();
    } else if (k === 'Escape') {
        e.preventDefault();
        calc.ca();
    } else if (k === 'Delete') {
        e.preventDefault();
        calc.ce();
    } else if (k === '%') {
        e.preventDefault();
        calc.pct();
    } else if (calc.mode === 'programmer' && 'abcdefABCDEF'.includes(k)) {
        e.preventDefault();
        calc.inp(k.toUpperCase());
    }
});
window.addEventListener('load', function() {
    CalcManager.add();
});