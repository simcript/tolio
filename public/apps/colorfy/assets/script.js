// ===== State =====
let currentColor = {h: 239, s: 59, v: 95, a: 1};
let currentMode = 'hsv';
let savedColors = JSON.parse(localStorage.getItem('savedColors') || '[]');
let harmonyColors = [];
let viewingHarmonyColor = null; // Track if we're viewing a harmony color

let gradientStops = [
    {color: '#6366f1', position: 0},
    {color: '#a855f7', position: 100}
];
let activeStopIndex = 0;
let activeDrag = null;

// ===== Color Conversions =====
function hsvToRgb(h, s, v) {
    s /= 100;
    v /= 100;
    const c = v * s;
    const x = c * (1 - Math.abs((h / 60) % 2 - 1));
    const m = v - c;
    let r, g, b;
    if (h < 60) {
        r = c;
        g = x;
        b = 0;
    } else if (h < 120) {
        r = x;
        g = c;
        b = 0;
    } else if (h < 180) {
        r = 0;
        g = c;
        b = x;
    } else if (h < 240) {
        r = 0;
        g = x;
        b = c;
    } else if (h < 300) {
        r = x;
        g = 0;
        b = c;
    } else {
        r = c;
        g = 0;
        b = x;
    }
    return {r: Math.round((r + m) * 255), g: Math.round((g + m) * 255), b: Math.round((b + m) * 255)};
}

function rgbToHsv(r, g, b) {
    r /= 255;
    g /= 255;
    b /= 255;
    const max = Math.max(r, g, b), min = Math.min(r, g, b);
    const d = max - min;
    let h, s, v = max;
    s = max === 0 ? 0 : d / max;
    if (max === min) {
        h = 0;
    } else {
        switch (max) {
            case r:
                h = (g - b) / d + (g < b ? 6 : 0);
                break;
            case g:
                h = (b - r) / d + 2;
                break;
            case b:
                h = (r - g) / d + 4;
                break;
        }
        h *= 60;
    }
    return {h: Math.round(h), s: Math.round(s * 100), v: Math.round(v * 100)};
}

function hslToRgb(h, s, l) {
    s /= 100;
    l /= 100;
    const c = (1 - Math.abs(2 * l - 1)) * s;
    const x = c * (1 - Math.abs((h / 60) % 2 - 1));
    const m = l - c / 2;
    let r, g, b;
    if (h < 60) {
        r = c;
        g = x;
        b = 0;
    } else if (h < 120) {
        r = x;
        g = c;
        b = 0;
    } else if (h < 180) {
        r = 0;
        g = c;
        b = x;
    } else if (h < 240) {
        r = 0;
        g = x;
        b = c;
    } else if (h < 300) {
        r = x;
        g = 0;
        b = c;
    } else {
        r = c;
        g = 0;
        b = x;
    }
    return {r: Math.round((r + m) * 255), g: Math.round((g + m) * 255), b: Math.round((b + m) * 255)};
}

function rgbToHsl(r, g, b) {
    r /= 255;
    g /= 255;
    b /= 255;
    const max = Math.max(r, g, b), min = Math.min(r, g, b);
    let h, s, l = (max + min) / 2;
    if (max === min) {
        h = s = 0;
    } else {
        const d = max - min;
        s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
        switch (max) {
            case r:
                h = (g - b) / d + (g < b ? 6 : 0);
                break;
            case g:
                h = (b - r) / d + 2;
                break;
            case b:
                h = (r - g) / d + 4;
                break;
        }
        h *= 60;
    }
    return {h: Math.round(h), s: Math.round(s * 100), l: Math.round(l * 100)};
}

function rgbToHex(r, g, b) {
    return '#' + [r, g, b].map(x => x.toString(16).padStart(2, '0')).join('').toUpperCase();
}

function hexToRgb(hex) {
    hex = hex.replace('#', '');
    if (hex.length === 3) hex = hex.split('').map(c => c + c).join('');
    return {
        r: parseInt(hex.substr(0, 2), 16) || 0,
        g: parseInt(hex.substr(2, 2), 16) || 0,
        b: parseInt(hex.substr(4, 2), 16) || 0
    };
}

function rgbToCmyk(r, g, b) {
    if (r === 0 && g === 0 && b === 0) return {c: 0, m: 0, y: 0, k: 100};
    r /= 255;
    g /= 255;
    b /= 255;
    const k = 1 - Math.max(r, g, b);
    return {
        c: Math.round((1 - r - k) / (1 - k) * 100),
        m: Math.round((1 - g - k) / (1 - k) * 100),
        y: Math.round((1 - b - k) / (1 - k) * 100),
        k: Math.round(k * 100)
    };
}

function rgbToLab(r, g, b) {
    r /= 255;
    g /= 255;
    b /= 255;
    r = r > 0.04045 ? Math.pow((r + 0.055) / 1.055, 2.4) : r / 12.92;
    g = g > 0.04045 ? Math.pow((g + 0.055) / 1.055, 2.4) : g / 12.92;
    b = b > 0.04045 ? Math.pow((b + 0.055) / 1.055, 2.4) : b / 12.92;
    let x = (r * 0.4124 + g * 0.3576 + b * 0.1805) / 0.95047;
    let y = (r * 0.2126 + g * 0.7152 + b * 0.0722) / 1.00000;
    let z = (r * 0.0193 + g * 0.1192 + b * 0.9505) / 1.08883;
    x = x > 0.008856 ? Math.pow(x, 1 / 3) : (7.787 * x) + 16 / 116;
    y = y > 0.008856 ? Math.pow(y, 1 / 3) : (7.787 * y) + 16 / 116;
    z = z > 0.008856 ? Math.pow(z, 1 / 3) : (7.787 * z) + 16 / 116;
    return {l: Math.round((116 * y) - 16), a: Math.round(500 * (x - y)), b: Math.round(200 * (y - z))};
}

function labToLch(l, a, b) {
    const c = Math.sqrt(a * a + b * b);
    let h = Math.atan2(b, a) * 180 / Math.PI;
    if (h < 0) h += 360;
    return {l, c: Math.round(c), h: Math.round(h)};
}

function rgbToHwb(r, g, b) {
    const hsl = rgbToHsl(r, g, b);
    return {h: hsl.h, w: Math.round(Math.min(r, g, b) / 255 * 100), b: Math.round((1 - Math.max(r, g, b) / 255) * 100)};
}

// ===== Update UI =====
function updateColorFromHSV() {
    const rgb = hsvToRgb(currentColor.h, currentColor.s, currentColor.v);
    updateAllDisplays(rgb);
}

function updateAllDisplays(rgb) {
    const hex = rgbToHex(rgb.r, rgb.g, rgb.b);
    const hsl = rgbToHsl(rgb.r, rgb.g, rgb.b);
    const hsv = rgbToHsv(rgb.r, rgb.g, rgb.b);
    const cmyk = rgbToCmyk(rgb.r, rgb.g, rgb.b);
    const lab = rgbToLab(rgb.r, rgb.g, rgb.b);
    const lch = labToLch(lab.l, lab.a, lab.b);
    const hwb = rgbToHwb(rgb.r, rgb.g, rgb.b);
    const alpha = currentColor.a;
    const alphaHex = Math.round(alpha * 255).toString(16).padStart(2, '0').toUpperCase();

    document.getElementById('color-preview').style.background = `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, ${alpha})`;
    document.documentElement.style.setProperty('--current-color', hex);

    // Clear viewing state when main color changes via picker
    if (viewingHarmonyColor) {
        viewingHarmonyColor = null;
        document.getElementById('viewing-indicator').style.display = 'none';
        document.getElementById('viewing-color-bar').style.display = 'none';
        document.querySelectorAll('.harmony-color').forEach(el => el.classList.remove('selected'));
    }

    // Update color codes
    document.getElementById('input-r').value = rgb.r;
    document.getElementById('input-g').value = rgb.g;
    document.getElementById('input-b').value = rgb.b;
    document.getElementById('input-a').value = Math.round(alpha * 100);
    document.getElementById('input-h').value = hsl.h;
    document.getElementById('input-s').value = hsl.s;
    document.getElementById('input-l').value = hsl.l;
    document.getElementById('input-hex').value = hex;

    document.getElementById('code-hex').textContent = hex;
    document.getElementById('code-hex8').textContent = hex + alphaHex;
    document.getElementById('code-rgb').textContent = `rgb(${rgb.r}, ${rgb.g}, ${rgb.b})`;
    document.getElementById('code-rgba').textContent = `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, ${alpha})`;
    document.getElementById('code-hsl').textContent = `hsl(${hsl.h}, ${hsl.s}%, ${hsl.l}%)`;
    document.getElementById('code-hsla').textContent = `hsla(${hsl.h}, ${hsl.s}%, ${hsl.l}%, ${alpha})`;
    document.getElementById('code-hsv').textContent = `hsv(${hsv.h}, ${hsv.s}%, ${hsv.v}%)`;
    document.getElementById('code-hsb').textContent = `hsb(${hsv.h}, ${hsv.s}%, ${hsv.v}%)`;
    document.getElementById('code-cmyk').textContent = `cmyk(${cmyk.c}%, ${cmyk.m}%, ${cmyk.y}%, ${cmyk.k}%)`;
    document.getElementById('code-lab').textContent = `lab(${lab.l}, ${lab.a}, ${lab.b})`;
    document.getElementById('code-lch').textContent = `lch(${lch.l}, ${lch.c}, ${lch.h})`;
    document.getElementById('code-hwb').textContent = `hwb(${hwb.h}, ${hwb.w}%, ${hwb.b}%)`;

    document.getElementById('hue-value').textContent = currentColor.h + '°';
    document.getElementById('hsl-hue-value').textContent = currentColor.h + '°';
    document.getElementById('alpha-value').textContent = Math.round(alpha * 100) + '%';

    updateColorName(hex);
    updateHarmonyColors();
    updatePickerAreas();
}

function updatePickerAreas() {
    const rgb = hsvToRgb(currentColor.h, currentColor.s, currentColor.v);
    const hsl = rgbToHsl(rgb.r, rgb.g, rgb.b);

    document.getElementById('hsv-area').style.background = `linear-gradient(to top, #000, transparent), linear-gradient(to right, #fff, hsl(${currentColor.h}, 100%, 50%))`;
    document.getElementById('hsv-pointer').style.cssText = `left: ${currentColor.s}%; top: ${100 - currentColor.v}%`;
    document.getElementById('hue-thumb').style.left = (currentColor.h / 360 * 100) + '%';

    document.getElementById('hsl-area').style.background = `linear-gradient(to top, #000 0%, transparent 50%, #fff 100%), linear-gradient(to right, #808080, hsl(${currentColor.h}, 100%, 50%))`;
    document.getElementById('hsl-pointer').style.cssText = `left: ${hsl.s}%; top: ${100 - hsl.l}%`;
    document.getElementById('hsl-hue-thumb').style.left = (currentColor.h / 360 * 100) + '%';

    document.getElementById('alpha-thumb').style.left = (currentColor.a * 100) + '%';

    document.getElementById('r-thumb').style.left = (rgb.r / 255 * 100) + '%';
    document.getElementById('g-thumb').style.left = (rgb.g / 255 * 100) + '%';
    document.getElementById('b-thumb').style.left = (rgb.b / 255 * 100) + '%';
    document.getElementById('r-value').textContent = rgb.r;
    document.getElementById('g-value').textContent = rgb.g;
    document.getElementById('b-value').textContent = rgb.b;
    document.getElementById('r-slider').style.background = `linear-gradient(to right, rgb(0,${rgb.g},${rgb.b}), rgb(255,${rgb.g},${rgb.b}))`;
    document.getElementById('g-slider').style.background = `linear-gradient(to right, rgb(${rgb.r},0,${rgb.b}), rgb(${rgb.r},255,${rgb.b}))`;
    document.getElementById('b-slider').style.background = `linear-gradient(to right, rgb(${rgb.r},${rgb.g},0), rgb(${rgb.r},${rgb.g},255))`;

    const lightnessSlider = document.getElementById('lightness-slider');
    lightnessSlider.style.background = `linear-gradient(to right, hsl(${currentColor.h}, 100%, 0%), hsl(${currentColor.h}, 100%, 50%), hsl(${currentColor.h}, 100%, 100%))`;
    document.getElementById('lightness-thumb').style.left = hsl.l + '%';
    document.getElementById('wheel-lightness-value').textContent = hsl.l + '%';
}

function updateColorName(hex) {
    const rgb = hexToRgb(hex);
    const hsl = rgbToHsl(rgb.r, rgb.g, rgb.b);
    const hue = hsl.h, sat = hsl.s, light = hsl.l;

    let hueName;
    if (hue < 15 || hue >= 345) hueName = 'Red';
    else if (hue < 45) hueName = 'Orange';
    else if (hue < 75) hueName = 'Yellow';
    else if (hue < 150) hueName = 'Green';
    else if (hue < 210) hueName = 'Cyan';
    else if (hue < 270) hueName = 'Blue';
    else if (hue < 315) hueName = 'Purple';
    else hueName = 'Pink';

    let prefix = '';
    if (light < 20) prefix = 'Dark ';
    else if (light > 80) prefix = 'Light ';
    else if (sat < 20) prefix = 'Grayish ';
    else if (sat > 80) prefix = 'Vivid ';

    document.getElementById('color-name').textContent = prefix + hueName;
    document.getElementById('color-description').textContent = `H: ${hsl.h}° S: ${hsl.s}% L: ${hsl.l}%`;
}

// ===== Harmony Colors =====
function updateHarmonyColors() {
    const type = document.getElementById('harmony-type').value;
    const h = currentColor.h;
    harmonyColors = [];

    switch (type) {
        case 'complementary':
            harmonyColors = [
                {h: h, s: currentColor.s, v: currentColor.v},
                {h: (h + 180) % 360, s: currentColor.s, v: currentColor.v}
            ];
            break;
        case 'analogous':
            harmonyColors = [
                {h: (h - 30 + 360) % 360, s: currentColor.s, v: currentColor.v},
                {h: h, s: currentColor.s, v: currentColor.v},
                {h: (h + 30) % 360, s: currentColor.s, v: currentColor.v}
            ];
            break;
        case 'triadic':
            harmonyColors = [
                {h: h, s: currentColor.s, v: currentColor.v},
                {h: (h + 120) % 360, s: currentColor.s, v: currentColor.v},
                {h: (h + 240) % 360, s: currentColor.s, v: currentColor.v}
            ];
            break;
        case 'split':
            harmonyColors = [
                {h: h, s: currentColor.s, v: currentColor.v},
                {h: (h + 150) % 360, s: currentColor.s, v: currentColor.v},
                {h: (h + 210) % 360, s: currentColor.s, v: currentColor.v}
            ];
            break;
        case 'tetradic':
            harmonyColors = [
                {h: h, s: currentColor.s, v: currentColor.v},
                {h: (h + 90) % 360, s: currentColor.s, v: currentColor.v},
                {h: (h + 180) % 360, s: currentColor.s, v: currentColor.v},
                {h: (h + 270) % 360, s: currentColor.s, v: currentColor.v}
            ];
            break;
        case 'monochromatic':
            harmonyColors = [
                {h: h, s: Math.max(0, currentColor.s - 30), v: Math.min(100, currentColor.v + 20)},
                {h: h, s: currentColor.s, v: currentColor.v},
                {h: h, s: Math.min(100, currentColor.s + 20), v: Math.max(0, currentColor.v - 25)}
            ];
            break;
    }
    renderHarmonyColors();
}

function renderHarmonyColors() {
    const container = document.getElementById('harmony-colors');
    container.innerHTML = '';

    harmonyColors.forEach((colorData, index) => {
        const rgb = hsvToRgb(colorData.h, colorData.s, colorData.v);
        const hex = rgbToHex(rgb.r, rgb.g, rgb.b);

        const div = document.createElement('div');
        div.className = 'harmony-color';
        div.style.background = hex;
        div.dataset.hex = hex;

        const info = document.createElement('div');
        info.className = 'harmony-color-info';
        info.textContent = hex;
        div.appendChild(info);

        const copyIndicator = document.createElement('div');
        copyIndicator.className = 'copy-indicator';
        copyIndicator.textContent = '✓ Copied!';
        div.appendChild(copyIndicator);

        div.onclick = () => copyHarmonyColor(div, hex, colorData);
        container.appendChild(div);
    });
}

function copyHarmonyColor(element, hex, colorData) {
    // Show this color's codes in Color Codes section
    viewingHarmonyColor = {hex, colorData};
    displayColorCodes(colorData.h, colorData.s, colorData.v);

    // Show viewing indicator
    document.getElementById('viewing-indicator').style.display = 'inline';
    document.getElementById('viewing-color-bar').style.display = 'block';
    document.getElementById('viewing-color-preview').style.background = hex;
    document.getElementById('viewing-color-hex').textContent = hex;

    // Highlight selected harmony color
    document.querySelectorAll('.harmony-color').forEach(el => el.classList.remove('selected'));
    element.classList.add('selected');

    // Copy to clipboard
    navigator.clipboard.writeText(hex);
    element.classList.add('copied');
    showToast(hex, `${hex} copied! Codes shown below.`);
    setTimeout(() => element.classList.remove('copied'), 1500);
}

function displayColorCodes(h, s, v, alpha = 1) {
    const rgb = hsvToRgb(h, s, v);
    const hex = rgbToHex(rgb.r, rgb.g, rgb.b);
    const hsl = rgbToHsl(rgb.r, rgb.g, rgb.b);
    const hsv = rgbToHsv(rgb.r, rgb.g, rgb.b);
    const cmyk = rgbToCmyk(rgb.r, rgb.g, rgb.b);
    const lab = rgbToLab(rgb.r, rgb.g, rgb.b);
    const lch = labToLch(lab.l, lab.a, lab.b);
    const hwb = rgbToHwb(rgb.r, rgb.g, rgb.b);
    const alphaHex = Math.round(alpha * 255).toString(16).padStart(2, '0').toUpperCase();

    document.getElementById('input-r').value = rgb.r;
    document.getElementById('input-g').value = rgb.g;
    document.getElementById('input-b').value = rgb.b;
    document.getElementById('input-a').value = Math.round(alpha * 100);
    document.getElementById('input-h').value = hsl.h;
    document.getElementById('input-s').value = hsl.s;
    document.getElementById('input-l').value = hsl.l;
    document.getElementById('input-hex').value = hex;

    document.getElementById('code-hex').textContent = hex;
    document.getElementById('code-hex8').textContent = hex + alphaHex;
    document.getElementById('code-rgb').textContent = `rgb(${rgb.r}, ${rgb.g}, ${rgb.b})`;
    document.getElementById('code-rgba').textContent = `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, ${alpha})`;
    document.getElementById('code-hsl').textContent = `hsl(${hsl.h}, ${hsl.s}%, ${hsl.l}%)`;
    document.getElementById('code-hsla').textContent = `hsla(${hsl.h}, ${hsl.s}%, ${hsl.l}%, ${alpha})`;
    document.getElementById('code-hsv').textContent = `hsv(${hsv.h}, ${hsv.s}%, ${hsv.v}%)`;
    document.getElementById('code-hsb').textContent = `hsb(${hsv.h}, ${hsv.s}%, ${hsv.v}%)`;
    document.getElementById('code-cmyk').textContent = `cmyk(${cmyk.c}%, ${cmyk.m}%, ${cmyk.y}%, ${cmyk.k}%)`;
    document.getElementById('code-lab').textContent = `lab(${lab.l}, ${lab.a}, ${lab.b})`;
    document.getElementById('code-lch').textContent = `lch(${lch.l}, ${lch.c}, ${lch.h})`;
    document.getElementById('code-hwb').textContent = `hwb(${hwb.h}, ${hwb.w}%, ${hwb.b}%)`;
}

function clearViewingColor() {
    viewingHarmonyColor = null;
    document.getElementById('viewing-indicator').style.display = 'none';
    document.getElementById('viewing-color-bar').style.display = 'none';
    document.querySelectorAll('.harmony-color').forEach(el => el.classList.remove('selected'));
    // Restore main color codes
    displayColorCodes(currentColor.h, currentColor.s, currentColor.v, currentColor.a);
}

function setHarmonyAsMain() {
    if (viewingHarmonyColor) {
        const {colorData} = viewingHarmonyColor;
        currentColor.h = colorData.h;
        currentColor.s = colorData.s;
        currentColor.v = colorData.v;
        viewingHarmonyColor = null;
        document.getElementById('viewing-indicator').style.display = 'none';
        document.getElementById('viewing-color-bar').style.display = 'none';
        document.querySelectorAll('.harmony-color').forEach(el => el.classList.remove('selected'));
        updateColorFromHSV();
        showToast(rgbToHex(...Object.values(hsvToRgb(colorData.h, colorData.s, colorData.v))), 'Set as main color!');
    }
}

function copyAllHarmonyColors() {
    const hexCodes = harmonyColors.map(c => rgbToHex(...Object.values(hsvToRgb(c.h, c.s, c.v))));
    navigator.clipboard.writeText(hexCodes.join(', '));
    showToast(hexCodes[0], `${hexCodes.length} colors copied!`);
}

function applyHarmonyToGradient() {
    gradientStops = harmonyColors.map((c, i) => ({
        color: rgbToHex(...Object.values(hsvToRgb(c.h, c.s, c.v))),
        position: Math.round(i / (harmonyColors.length - 1) * 100)
    }));
    activeStopIndex = 0;
    updateGradient();
    showToast(gradientStops[0].color, 'Applied to gradient!');
}

function showToast(color, text) {
    const toast = document.getElementById('toast');
    document.getElementById('toast-color').style.background = color;
    document.getElementById('toast-text').textContent = text;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 2000);
}

// ===== Event Handlers =====
function getEventPosition(e) {
    if (e.touches && e.touches[0]) return {x: e.touches[0].clientX, y: e.touches[0].clientY};
    return {x: e.clientX, y: e.clientY};
}

function setupColorArea(areaId, pointerId, mode) {
    const area = document.getElementById(areaId);

    function updateFromPosition(e) {
        const pos = getEventPosition(e);
        const rect = area.getBoundingClientRect();
        let x = Math.max(0, Math.min(1, (pos.x - rect.left) / rect.width));
        let y = Math.max(0, Math.min(1, (pos.y - rect.top) / rect.height));

        if (mode === 'hsv') {
            currentColor.s = Math.round(x * 100);
            currentColor.v = Math.round((1 - y) * 100);
        } else if (mode === 'hsl') {
            const saturation = Math.round(x * 100);
            const lightness = Math.round((1 - y) * 100);
            const rgb = hslToRgb(currentColor.h, saturation, lightness);
            const hsv = rgbToHsv(rgb.r, rgb.g, rgb.b);
            currentColor.s = hsv.s;
            currentColor.v = hsv.v;
        }
        updateColorFromHSV();
    }

    area.addEventListener('mousedown', (e) => {
        e.preventDefault();
        activeDrag = {update: updateFromPosition};
        updateFromPosition(e);
    });

    area.addEventListener('touchstart', (e) => {
        e.preventDefault();
        activeDrag = {update: updateFromPosition};
        updateFromPosition(e);
    }, {passive: false});
}

function setupSlider(trackId, callback) {
    const track = document.getElementById(trackId);

    function updateFromPosition(e) {
        const pos = getEventPosition(e);
        const rect = track.getBoundingClientRect();
        let x = Math.max(0, Math.min(1, (pos.x - rect.left) / rect.width));
        callback(x);
    }

    track.addEventListener('mousedown', (e) => {
        e.preventDefault();
        activeDrag = {update: updateFromPosition};
        updateFromPosition(e);
    });

    track.addEventListener('touchstart', (e) => {
        e.preventDefault();
        activeDrag = {update: updateFromPosition};
        updateFromPosition(e);
    }, {passive: false});
}

document.addEventListener('mousemove', (e) => {
    if (activeDrag) activeDrag.update(e);
});
document.addEventListener('mouseup', () => {
    activeDrag = null;
});
document.addEventListener('touchmove', (e) => {
    if (activeDrag) {
        e.preventDefault();
        activeDrag.update(e);
    }
}, {passive: false});
document.addEventListener('touchend', () => {
    activeDrag = null;
});

// ===== Color Wheel =====
function drawColorWheel() {
    const canvas = document.getElementById('color-wheel');
    const ctx = canvas.getContext('2d');
    const cx = canvas.width / 2, cy = canvas.height / 2;
    const radius = Math.min(cx, cy) - 5;
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    for (let angle = 0; angle < 360; angle++) {
        ctx.beginPath();
        ctx.moveTo(cx, cy);
        ctx.arc(cx, cy, radius, (angle - 1) * Math.PI / 180, (angle + 1) * Math.PI / 180);
        ctx.closePath();
        const gradient = ctx.createRadialGradient(cx, cy, 0, cx, cy, radius);
        gradient.addColorStop(0, `hsl(${angle}, 0%, 50%)`);
        gradient.addColorStop(1, `hsl(${angle}, 100%, 50%)`);
        ctx.fillStyle = gradient;
        ctx.fill();
    }
}

function setupColorWheel() {
    const canvas = document.getElementById('color-wheel');
    const pointer = document.getElementById('wheel-pointer');

    function updateFromPosition(e) {
        const pos = getEventPosition(e);
        const rect = canvas.getBoundingClientRect();
        const cx = rect.width / 2, cy = rect.height / 2;
        const x = pos.x - rect.left - cx, y = pos.y - rect.top - cy;
        const angle = Math.atan2(y, x) * 180 / Math.PI;
        const distance = Math.min(Math.sqrt(x * x + y * y), cx - 5);
        currentColor.h = (angle + 360) % 360;
        currentColor.s = Math.round((distance / (cx - 5)) * 100);
        pointer.style.left = (cx + Math.cos(angle * Math.PI / 180) * distance) + 'px';
        pointer.style.top = (cy + Math.sin(angle * Math.PI / 180) * distance) + 'px';
        updateColorFromHSV();
    }

    canvas.addEventListener('mousedown', (e) => {
        e.preventDefault();
        activeDrag = {update: updateFromPosition};
        updateFromPosition(e);
    });
    canvas.addEventListener('touchstart', (e) => {
        e.preventDefault();
        activeDrag = {update: updateFromPosition};
        updateFromPosition(e);
    }, {passive: false});
}

// ===== Tabs =====
document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        tab.classList.add('active');
        document.getElementById(tab.dataset.mode + '-picker').classList.add('active');
        if (tab.dataset.mode === 'wheel') drawColorWheel();
    });
});

// ===== Inputs =====
['input-r', 'input-g', 'input-b'].forEach(id => {
    document.getElementById(id).addEventListener('change', () => {
        const r = Math.max(0, Math.min(255, parseInt(document.getElementById('input-r').value) || 0));
        const g = Math.max(0, Math.min(255, parseInt(document.getElementById('input-g').value) || 0));
        const b = Math.max(0, Math.min(255, parseInt(document.getElementById('input-b').value) || 0));
        Object.assign(currentColor, rgbToHsv(r, g, b));
        updateColorFromHSV();
    });
});

document.getElementById('input-a').addEventListener('change', () => {
    currentColor.a = Math.max(0, Math.min(100, parseInt(document.getElementById('input-a').value) || 0)) / 100;
    updateColorFromHSV();
});

['input-h', 'input-s', 'input-l'].forEach(id => {
    document.getElementById(id).addEventListener('change', () => {
        const h = parseInt(document.getElementById('input-h').value) || 0;
        const s = parseInt(document.getElementById('input-s').value) || 0;
        const l = parseInt(document.getElementById('input-l').value) || 0;
        Object.assign(currentColor, rgbToHsv(...Object.values(hslToRgb(h, s, l))));
        updateColorFromHSV();
    });
});

document.getElementById('input-hex').addEventListener('change', () => {
    let hex = document.getElementById('input-hex').value;
    if (/^#?[0-9A-Fa-f]{6}$/.test(hex)) {
        if (!hex.startsWith('#')) hex = '#' + hex;
        Object.assign(currentColor, rgbToHsv(...Object.values(hexToRgb(hex))));
        updateColorFromHSV();
    }
});

document.getElementById('harmony-type').addEventListener('change', updateHarmonyColors);

// ===== Copy =====
function copyCode(type, btn) {
    navigator.clipboard.writeText(document.getElementById('code-' + type).textContent);
    btn.textContent = '✓';
    btn.classList.add('copied');
    setTimeout(() => {
        btn.textContent = 'Copy';
        btn.classList.remove('copied');
    }, 1500);
}

// ===== Save/Load =====
function saveColor() {
    const hex = rgbToHex(...Object.values(hsvToRgb(currentColor.h, currentColor.s, currentColor.v)));
    if (!savedColors.includes(hex)) {
        savedColors.push(hex);
        localStorage.setItem('savedColors', JSON.stringify(savedColors));
        renderSavedColors();
        showToast(hex, 'Color saved!');
    }
}

function renderSavedColors() {
    const container = document.getElementById('saved-colors');
    container.innerHTML = '';
    savedColors.forEach((hex, i) => {
        const div = document.createElement('div');
        div.className = 'saved-color';
        div.style.background = hex;
        div.onclick = () => {
            Object.assign(currentColor, rgbToHsv(...Object.values(hexToRgb(hex))));
            updateColorFromHSV();
        };
        const del = document.createElement('span');
        del.className = 'delete-color';
        del.textContent = '×';
        del.onclick = (e) => {
            e.stopPropagation();
            savedColors.splice(i, 1);
            localStorage.setItem('savedColors', JSON.stringify(savedColors));
            renderSavedColors();
        };
        div.appendChild(del);
        container.appendChild(div);
    });
}

function randomColor() {
    currentColor.h = Math.floor(Math.random() * 360);
    currentColor.s = Math.floor(Math.random() * 60) + 40;
    currentColor.v = Math.floor(Math.random() * 40) + 60;
    updateColorFromHSV();
}

// ===== Gradient =====
function updateGradient() {
    const type = document.getElementById('gradient-type').value;
    const dir = document.getElementById('gradient-direction').value;
    const shape = document.getElementById('radial-shape').value;
    const pos = document.getElementById('radial-position').value;
    const stops = gradientStops.sort((a, b) => a.position - b.position).map(s => `${s.color} ${s.position}%`).join(', ');

    let css;
    if (type === 'linear') css = `linear-gradient(${dir}, ${stops})`;
    else if (type === 'radial') css = `radial-gradient(${shape} at ${pos}, ${stops})`;
    else css = `conic-gradient(from 0deg at ${pos}, ${stops})`;

    document.getElementById('gradient-preview').style.background = css;
    document.getElementById('gradient-css').textContent = css;
    document.getElementById('gradient-tailwind').textContent = `bg-gradient-to-r from-[${gradientStops[0].color}] to-[${gradientStops[gradientStops.length - 1].color}]`;
    document.getElementById('gradient-svg').textContent = `<linearGradient>${gradientStops.map(s => `<stop offset="${s.position}%" stop-color="${s.color}" />`).join('')}</linearGradient>`;
    renderGradientStops();
}

function renderGradientStops() {
    const container = document.getElementById('gradient-stops');
    container.innerHTML = '';
    gradientStops.forEach((stop, i) => {
        const div = document.createElement('div');
        div.className = 'gradient-stop' + (i === activeStopIndex ? ' active' : '');
        div.style.left = stop.position + '%';
        div.style.background = stop.color;
        div.onclick = (e) => {
            e.stopPropagation();
            activeStopIndex = i;
            renderGradientStops();
        };
        div.ondblclick = (e) => {
            e.stopPropagation();
            if (gradientStops.length > 2) {
                gradientStops.splice(i, 1);
                if (activeStopIndex >= gradientStops.length) activeStopIndex--;
                updateGradient();
            }
        };
        div.onmousedown = (e) => {
            e.preventDefault();
            e.stopPropagation();
            activeStopIndex = i;
            activeDrag = {
                update: (ev) => {
                    const rect = document.getElementById('gradient-preview').getBoundingClientRect();
                    const pos = getEventPosition(ev);
                    gradientStops[i].position = Math.round(Math.max(0, Math.min(100, ((pos.x - rect.left) / rect.width) * 100)));
                    updateGradient();
                }
            };
            renderGradientStops();
        };
        container.appendChild(div);
    });
}

function addGradientStop() {
    const hex = rgbToHex(...Object.values(hsvToRgb(currentColor.h, currentColor.s, currentColor.v)));
    gradientStops.push({color: hex, position: 50});
    gradientStops.sort((a, b) => a.position - b.position);
    activeStopIndex = gradientStops.findIndex(s => s.color === hex && s.position === 50);
    updateGradient();
}

function useCurrentColor() {
    gradientStops[activeStopIndex].color = rgbToHex(...Object.values(hsvToRgb(currentColor.h, currentColor.s, currentColor.v)));
    updateGradient();
}

function reverseGradient() {
    gradientStops.forEach(s => s.position = 100 - s.position);
    gradientStops.reverse();
    updateGradient();
}

function randomGradient() {
    gradientStops = [];
    const n = Math.floor(Math.random() * 3) + 2;
    for (let i = 0; i < n; i++) {
        const rgb = hsvToRgb(Math.floor(Math.random() * 360), Math.floor(Math.random() * 40) + 60, Math.floor(Math.random() * 30) + 70);
        gradientStops.push({color: rgbToHex(rgb.r, rgb.g, rgb.b), position: Math.round(i / (n - 1) * 100)});
    }
    activeStopIndex = 0;
    updateGradient();
}

function copyGradient(type, btn) {
    const code = document.getElementById('gradient-' + type).textContent;
    navigator.clipboard.writeText(code);
    btn.textContent = '✓';
    btn.classList.add('copied');
    setTimeout(() => {
        btn.textContent = 'Copy';
        btn.classList.remove('copied');
    }, 1500);
}

['gradient-type', 'gradient-direction', 'radial-shape', 'radial-position'].forEach(id => {
    document.getElementById(id).addEventListener('change', updateGradient);
});

// ===== Init =====
function init() {
    setupColorArea('hsv-area', 'hsv-pointer', 'hsv');
    setupColorArea('hsl-area', 'hsl-pointer', 'hsl');
    setupColorWheel();

    setupSlider('hue-slider', x => {
        currentColor.h = Math.round(x * 360);
        updateColorFromHSV();
    });
    setupSlider('hsl-hue-slider', x => {
        currentColor.h = Math.round(x * 360);
        updateColorFromHSV();
    });
    setupSlider('alpha-slider', x => {
        currentColor.a = x;
        updateColorFromHSV();
    });

    setupSlider('r-slider', x => {
        const rgb = hsvToRgb(currentColor.h, currentColor.s, currentColor.v);
        rgb.r = Math.round(x * 255);
        Object.assign(currentColor, rgbToHsv(rgb.r, rgb.g, rgb.b));
        updateColorFromHSV();
    });
    setupSlider('g-slider', x => {
        const rgb = hsvToRgb(currentColor.h, currentColor.s, currentColor.v);
        rgb.g = Math.round(x * 255);
        Object.assign(currentColor, rgbToHsv(rgb.r, rgb.g, rgb.b));
        updateColorFromHSV();
    });
    setupSlider('b-slider', x => {
        const rgb = hsvToRgb(currentColor.h, currentColor.s, currentColor.v);
        rgb.b = Math.round(x * 255);
        Object.assign(currentColor, rgbToHsv(rgb.r, rgb.g, rgb.b));
        updateColorFromHSV();
    });
    setupSlider('lightness-slider', x => {
        const rgb = hslToRgb(currentColor.h, currentColor.s, x * 100);
        Object.assign(currentColor, rgbToHsv(rgb.r, rgb.g, rgb.b));
        updateColorFromHSV();
    });

    updateColorFromHSV();
    renderSavedColors();
    updateGradient();
}

init();
