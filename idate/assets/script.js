/**
 * IMPORTANT: Server-side setup required!
 *
 * Set the value of #server-utc-time input with current UTC time:
 * Format: YYYY-MM-DD HH:MM:SS
 *
 * PHP Example:
 * <input type="hidden" id="server-utc-time" value="<?php echo gmdate('Y-m-d H:i:s'); ?>">
 *
 * Node.js/Express Example:
 * const serverTime = new Date().toISOString().slice(0, 19).replace('T', ' ');
 * <input type="hidden" id="server-utc-time" value="${serverTime}">
 *
 * Python/Django Example:
 * from datetime import datetime
 * <input type="hidden" id="server-utc-time" value="{{ datetime.utcnow().strftime('%Y-%m-%d %H:%M:%S') }}">
 */

    // Month names
const gregorianMonths = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
const persianMonths = ['Farvardin', 'Ordibehesht', 'Khordad', 'Tir', 'Mordad', 'Shahrivar', 'Mehr', 'Aban', 'Azar', 'Dey', 'Bahman', 'Esfand'];
const islamicMonths = ['Muharram', 'Safar', 'Rabi I', 'Rabi II', 'Jumada I', 'Jumada II', 'Rajab', 'Sha\'ban', 'Ramadan', 'Shawwal', 'Dhu al-Qi\'dah', 'Dhu al-Hijjah'];

// Day names
const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
const persianDayNames = ['Yekshanbeh', 'Doshanbeh', 'Seshnbeh', 'Chaharshanbeh', 'Panjshanbeh', 'Jomeh', 'Shanbeh'];
const islamicDayNames = ['Al-Ahad', 'Al-Ithnayn', 'Al-Thulatha', 'Al-Arbi\'a', 'Al-Khamis', 'Al-Jumu\'ah', 'Al-Sabt'];

// Timezone
let selectedTimezone = 'UTC';
let serverTimeAtLoad = null;
let pageLoadTime = null;
let useServerTime = false;

// Initialize server time
function initializeServerTime() {
    const serverTimeInput = document.getElementById('server-utc-time');
    const serverTimeValue = serverTimeInput.value.trim();

    if (serverTimeValue) {
        // Parse server time (format: YYYY-MM-DD HH:MM:SS)
        serverTimeAtLoad = new Date(serverTimeValue + ' UTC');
        pageLoadTime = Date.now();
        useServerTime = true;

        document.getElementById('time-source-badge').textContent = 'Server Time ✓';
        document.getElementById('time-source-badge').style.background = 'var(--success-color)';

        console.log('Using server time:', serverTimeAtLoad.toISOString());
    } else {
        // Fallback to system time
        useServerTime = false;
        document.getElementById('time-source-badge').textContent = 'System Time';
        document.getElementById('time-source-badge').style.background = '#ffa600';

        console.warn('Server time not provided, using system time');
    }
}

// Get current accurate time
function getCurrentTime() {
    if (useServerTime && serverTimeAtLoad) {
        // Calculate current time based on server time at load + elapsed time
        const elapsedTime = Date.now() - pageLoadTime;
        return new Date(serverTimeAtLoad.getTime() + elapsedTime);
    }
    return new Date();
}

// Update current time
function updateCurrentTime() {
    const now = getCurrentTime();
    const options = {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        timeZone: selectedTimezone,
        timeZoneName: 'short'
    };
    const formatted = now.toLocaleString('en-US', options);
    document.getElementById('currentTime').textContent = formatted;
}

setInterval(updateCurrentTime, 1000);

// Tab switching
function switchTab(tabName) {
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
    });

    document.getElementById(`${tabName}-tab`).classList.add('active');
    event.target.classList.add('active');
}

// ========================================
// FIXED: Persian calendar conversion functions
// ========================================

function gregorianToJalali(gy, gm, gd) {
    const g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    let jy = (gy <= 1600) ? 0 : 979;
    gy -= (gy <= 1600) ? 621 : 1600;
    let gy2 = (gm > 2) ? (gy + 1) : gy;
    let days = (365 * gy) + (Math.floor((gy2 + 3) / 4)) - (Math.floor((gy2 + 99) / 100)) + (Math.floor((gy2 + 399) / 400)) - 80 + gd + g_d_m[gm - 1];
    jy += 33 * Math.floor(days / 12053);
    days %= 12053;
    jy += 4 * Math.floor(days / 1461);
    days %= 1461;
    if (days > 365) {
        jy += Math.floor((days - 1) / 365);
        days = (days - 1) % 365;
    }
    let jm, jd;
    if (days < 186) {
        jm = 1 + Math.floor(days / 31);
        jd = 1 + (days % 31);
    } else {
        jm = 7 + Math.floor((days - 186) / 30);
        jd = 1 + ((days - 186) % 30);
    }
    return [jy, jm, jd];
}

// FIXED: jalaliToGregorian function
function jalaliToGregorian(jy, jm, jd) {
    jy += 1595;
    // BUG FIX: Added -355668 constant and fixed days calculation
    let days = -355668 + (365 * jy) + (Math.floor(jy / 33) * 8) + Math.floor(((jy % 33) + 3) / 4) + jd;
    if (jm < 7) {
        days += (jm - 1) * 31;
    } else {
        days += ((jm - 7) * 30) + 186;
    }

    let gy = 400 * Math.floor(days / 146097);
    days %= 146097;

    // BUG FIX: Changed condition from >= 36525 to > 36524
    if (days > 36524) {
        // BUG FIX: days-- must be applied before division
        days--;
        gy += 100 * Math.floor(days / 36524);
        days %= 36524;
        if (days >= 365) days++;
    }

    gy += 4 * Math.floor(days / 1461);
    days %= 1461;

    // BUG FIX: Changed condition from >= 366 to > 365
    if (days > 365) {
        gy += Math.floor((days - 1) / 365);
        days = (days - 1) % 365;
    }

    let gd = days + 1;

    // BUG FIX: Proper leap year calculation for Gregorian calendar
    const isLeap = (gy % 4 === 0 && gy % 100 !== 0) || (gy % 400 === 0);
    const sal_a = [0, 31, isLeap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

    let gm;
    // BUG FIX: Changed condition from >= to >
    for (gm = 0; gm < 13 && gd > sal_a[gm]; gm++) {
        gd -= sal_a[gm];
    }

    return [gy, gm, gd];
}

// Islamic calendar (simplified approximation)
function gregorianToIslamic(year, month, day) {
    const gregorianDate = new Date(year, month - 1, day);
    const islamicEpoch = new Date(622, 6, 16);
    const daysSinceEpoch = Math.floor((gregorianDate - islamicEpoch) / (1000 * 60 * 60 * 24));
    const islamicYear = Math.floor(daysSinceEpoch / 354.36667);
    const remainingDays = daysSinceEpoch - Math.floor(islamicYear * 354.36667);
    const islamicMonth = Math.floor(remainingDays / 29.53059) + 1;
    const islamicDay = Math.floor(remainingDays % 29.53059) + 1;
    return [islamicYear + 1, Math.min(islamicMonth, 12), Math.min(islamicDay, 30)];
}

function islamicToGregorian(year, month, day) {
    const islamicEpoch = new Date(622, 6, 16);
    const daysSinceEpoch = Math.floor((year - 1) * 354.36667 + (month - 1) * 29.53059 + day - 1);
    const gregorianDate = new Date(islamicEpoch.getTime() + daysSinceEpoch * 24 * 60 * 60 * 1000);
    return [gregorianDate.getFullYear(), gregorianDate.getMonth() + 1, gregorianDate.getDate()];
}

// Format date
function formatDate(year, month, day, monthNames) {
    const monthFormat = document.querySelector('input[name="month-format"]:checked').value;
    const dayFormat = document.querySelector('input[name="day-format"]:checked').value;
    const separator = document.getElementById('separator').value;
    const zeroPad = document.getElementById('zero-padding').checked;

    const pad = (num) => zeroPad && num < 10 ? '0' + num : num;

    let monthStr = monthFormat === 'name' ? monthNames[month - 1] : pad(month);
    let dayStr = pad(day);

    return `${year}${separator}${monthStr}${separator}${dayStr}`;
}

// Display results
function displayResults(gregorianDate, persianDate, islamicDate, timestamp) {
    const date = new Date(gregorianDate[0], gregorianDate[1] - 1, gregorianDate[2]);
    const dayName = dayNames[date.getDay()];
    const persianDayName = persianDayNames[date.getDay()];
    const islamicDayName = islamicDayNames[date.getDay()];

    const results = `
                <div class="result-card">
                    <div class="result-header">
                        <span class="result-title">📅 Gregorian Calendar</span>
                        <button class="copy-btn" onclick="copyToClipboard('${formatDate(...gregorianDate, gregorianMonths)}', this)">Copy</button>
                    </div>
                    <div class="result-value">${formatDate(...gregorianDate, gregorianMonths)}</div>
                    <div class="result-formats">
                        <div class="format-option">
                            <span class="format-label">ISO Format:</span>
                            <span class="format-value">${date.toISOString().split('T')[0]}</span>
                        </div>
                        <div class="format-option">
                            <span class="format-label">Full:</span>
                            <span class="format-value">${dayName}, ${gregorianMonths[gregorianDate[1] - 1]} ${gregorianDate[2]}, ${gregorianDate[0]}</span>
                        </div>
                        <div class="format-option">
                            <span class="format-label">Day of Week:</span>
                            <span class="format-value">${dayName}</span>
                        </div>
                        <div class="format-option">
                            <span class="format-label">Timezone:</span>
                            <span class="format-value">${selectedTimezone}</span>
                        </div>
                    </div>
                </div>

                <div class="result-card">
                    <div class="result-header">
                        <span class="result-title">🌙 Persian Calendar (Jalali)</span>
                        <button class="copy-btn" onclick="copyToClipboard('${formatDate(...persianDate, persianMonths)}', this)">Copy</button>
                    </div>
                    <div class="result-value">${formatDate(...persianDate, persianMonths)}</div>
                    <div class="result-formats">
                        <div class="format-option">
                            <span class="format-label">Full:</span>
                            <span class="format-value">${persianDayName}, ${persianDate[2]} ${persianMonths[persianDate[1] - 1]} ${persianDate[0]}</span>
                        </div>
                        <div class="format-option">
                            <span class="format-label">Numeric:</span>
                            <span class="format-value">${persianDate[0]}/${String(persianDate[1]).padStart(2, '0')}/${String(persianDate[2]).padStart(2, '0')}</span>
                        </div>
                        <div class="format-option">
                            <span class="format-label">Day of Week:</span>
                            <span class="format-value">${persianDayName}</span>
                        </div>
                    </div>
                </div>

                <div class="result-card">
                    <div class="result-header">
                        <span class="result-title">☪️ Islamic Calendar (Hijri)</span>
                        <button class="copy-btn" onclick="copyToClipboard('${formatDate(...islamicDate, islamicMonths)}', this)">Copy</button>
                    </div>
                    <div class="result-value">${formatDate(...islamicDate, islamicMonths)}</div>
                    <div class="result-formats">
                        <div class="format-option">
                            <span class="format-label">Full:</span>
                            <span class="format-value">${islamicDayName}, ${islamicDate[2]} ${islamicMonths[islamicDate[1] - 1]} ${islamicDate[0]} AH</span>
                        </div>
                        <div class="format-option">
                            <span class="format-label">Numeric:</span>
                            <span class="format-value">${islamicDate[0]}/${String(islamicDate[1]).padStart(2, '0')}/${String(islamicDate[2]).padStart(2, '0')}</span>
                        </div>
                        <div class="format-option">
                            <span class="format-label">Day of Week:</span>
                            <span class="format-value">${islamicDayName}</span>
                        </div>
                    </div>
                </div>

                <div class="result-card">
                    <div class="result-header">
                        <span class="result-title">⏰ Unix Timestamp</span>
                        <button class="copy-btn" onclick="copyToClipboard('${timestamp}', this)">Copy</button>
                    </div>
                    <div class="result-value">${timestamp}</div>
                    <div class="result-formats">
                        <div class="format-option">
                            <span class="format-label">Milliseconds:</span>
                            <span class="format-value">${timestamp * 1000}</span>
                        </div>
                        <div class="format-option">
                            <span class="format-label">UTC String:</span>
                            <span class="format-value">${date.toUTCString()}</span>
                        </div>
                    </div>
                </div>
            `;

    document.getElementById('results').innerHTML = results;
}

// Conversion functions
function convertFromGregorian() {
    const year = parseInt(document.getElementById('g-year').value);
    const month = parseInt(document.getElementById('g-month').value);
    const day = parseInt(document.getElementById('g-day').value);

    if (!year || !month || !day) {
        alert('Please enter a valid date');
        return;
    }

    const persianDate = gregorianToJalali(year, month, day);
    const islamicDate = gregorianToIslamic(year, month, day);
    const timestamp = Math.floor(new Date(year, month - 1, day).getTime() / 1000);

    displayResults([year, month, day], persianDate, islamicDate, timestamp);
}

function convertFromPersian() {
    const year = parseInt(document.getElementById('p-year').value);
    const month = parseInt(document.getElementById('p-month').value);
    const day = parseInt(document.getElementById('p-day').value);

    if (!year || !month || !day) {
        alert('Please enter a valid date');
        return;
    }

    const gregorianDate = jalaliToGregorian(year, month, day);
    const islamicDate = gregorianToIslamic(...gregorianDate);
    const timestamp = Math.floor(new Date(gregorianDate[0], gregorianDate[1] - 1, gregorianDate[2]).getTime() / 1000);

    displayResults(gregorianDate, [year, month, day], islamicDate, timestamp);
}

function convertFromIslamic() {
    const year = parseInt(document.getElementById('i-year').value);
    const month = parseInt(document.getElementById('i-month').value);
    const day = parseInt(document.getElementById('i-day').value);

    if (!year || !month || !day) {
        alert('Please enter a valid date');
        return;
    }

    const gregorianDate = islamicToGregorian(year, month, day);
    const persianDate = gregorianToJalali(...gregorianDate);
    const timestamp = Math.floor(new Date(gregorianDate[0], gregorianDate[1] - 1, gregorianDate[2]).getTime() / 1000);

    displayResults(gregorianDate, persianDate, [year, month, day], timestamp);
}

function convertFromTimestamp() {
    const timestamp = parseInt(document.getElementById('timestamp').value);

    if (!timestamp) {
        alert('Please enter a valid timestamp');
        return;
    }

    const date = new Date(timestamp * 1000);
    const year = date.getFullYear();
    const month = date.getMonth() + 1;
    const day = date.getDate();

    const persianDate = gregorianToJalali(year, month, day);
    const islamicDate = gregorianToIslamic(year, month, day);

    displayResults([year, month, day], persianDate, islamicDate, timestamp);
}

// Set today
function setToday(calendar) {
    const today = getCurrentTime();
    const year = today.getFullYear();
    const month = today.getMonth() + 1;
    const day = today.getDate();

    if (calendar === 'gregorian') {
        document.getElementById('g-year').value = year;
        document.getElementById('g-month').value = month;
        document.getElementById('g-day').value = day;
        convertFromGregorian();
    } else if (calendar === 'persian') {
        const [pYear, pMonth, pDay] = gregorianToJalali(year, month, day);
        document.getElementById('p-year').value = pYear;
        document.getElementById('p-month').value = pMonth;
        document.getElementById('p-day').value = pDay;
        convertFromPersian();
    } else if (calendar === 'islamic') {
        const [iYear, iMonth, iDay] = gregorianToIslamic(year, month, day);
        document.getElementById('i-year').value = iYear;
        document.getElementById('i-month').value = iMonth;
        document.getElementById('i-day').value = iDay;
        convertFromIslamic();
    }
}

function setNowTimestamp() {
    const timestamp = Math.floor(getCurrentTime().getTime() / 1000);
    document.getElementById('timestamp').value = timestamp;
    convertFromTimestamp();
}

// Copy to clipboard
function copyToClipboard(text, button) {
    navigator.clipboard.writeText(text).then(() => {
        button.textContent = 'Copied!';
        button.classList.add('copied');
        setTimeout(() => {
            button.textContent = 'Copy';
            button.classList.remove('copied');
        }, 2000);
    });
}

// Change timezone
function changeTimezone() {
    selectedTimezone = document.getElementById('timezone').value;
    updateCurrentTime();

    const results = document.getElementById('results');
    if (results.innerHTML.trim() !== '') {
        const activeTab = document.querySelector('.tab.active').textContent.toLowerCase();
        if (activeTab === 'gregorian') convertFromGregorian();
        else if (activeTab === 'persian') convertFromPersian();
        else if (activeTab === 'islamic') convertFromIslamic();
        else if (activeTab === 'timestamp') convertFromTimestamp();
    }
}

// Update results when format options change
document.querySelectorAll('input[name="month-format"], input[name="day-format"], #separator, #zero-padding').forEach(element => {
    element.addEventListener('change', () => {
        const results = document.getElementById('results');
        if (results.innerHTML.trim() !== '') {
            const activeTab = document.querySelector('.tab.active').textContent.toLowerCase();
            if (activeTab === 'gregorian') convertFromGregorian();
            else if (activeTab === 'persian') convertFromPersian();
            else if (activeTab === 'islamic') convertFromIslamic();
            else if (activeTab === 'timestamp') convertFromTimestamp();
        }
    });
});

// Initialize on page load
window.addEventListener('load', () => {
    initializeServerTime();
    updateCurrentTime();
    setToday('gregorian');
});
