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
        serverTimeAtLoad = new Date(serverTimeValue + ' UTC');
        pageLoadTime = Date.now();
        useServerTime = true;

        document.getElementById('time-source-badge').textContent = 'Server Time ✓';
        document.getElementById('time-source-badge').classList.remove('system');
    } else {
        useServerTime = false;
        document.getElementById('time-source-badge').textContent = 'System Time';
        document.getElementById('time-source-badge').classList.add('system');
    }
}

// Get current accurate time
function getCurrentTime() {
    if (useServerTime && serverTimeAtLoad) {
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

// Persian calendar conversion functions
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

function jalaliToGregorian(jy, jm, jd) {
    let sal_a, gy, gm, gd, days;

    jy += 1595;
    days = 365 * jy + Math.floor(jy / 33) * 8 + Math.floor(((jy % 33) + 3) / 4) + 78 + jd;

    if (jm < 7) {
        days += (jm - 1) * 31;
    } else {
        days += (jm - 7) * 30 + 186;
    }

    gy = 400 * Math.floor(days / 146097);
    days %= 146097;

    let leap = true;
    if (days >= 36525) {
        days--;
        gy += 100 * Math.floor(days / 36524);
        days %= 36524;
        if (days >= 365) {
            days++;
            leap = false;
        }
    }

    gy += 4 * Math.floor(days / 1461);
    days %= 1461;

    if (days >= 366) {
        leap = false;
        days--;
        gy += Math.floor(days / 365);
        days = days % 365;
    }

    sal_a = [0, 31, (leap ? 29 : 28), 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

    gm = 0;
    for (gm = 0; gm < 13; gm++) {
        let v = sal_a[gm];
        if (days < v) break;
        days -= v;
    }

    gd = days + 1;

    return [gy, gm, gd];
}

// Islamic calendar (Improved algorithm)
function gregorianToIslamic(year, month, day) {
    // More accurate conversion
    const gregorianDate = new Date(year, month - 1, day);

    // Islamic epoch: July 16, 622 CE (Gregorian)
    const islamicEpoch = new Date(622, 6, 16);
    const daysSinceEpoch = Math.floor((gregorianDate - islamicEpoch) / (1000 * 60 * 60 * 24));

    // Average Islamic year length
    const islamicYearLength = 354.36667;

    // Calculate Islamic year
    let islamicYear = Math.floor(daysSinceEpoch / islamicYearLength) + 1;

    // Calculate remaining days
    let remainingDays = daysSinceEpoch - Math.floor((islamicYear - 1) * islamicYearLength);

    // Month lengths in Islamic calendar (alternating 30 and 29 days)
    const monthLengths = [30, 29, 30, 29, 30, 29, 30, 29, 30, 29, 30, 29];

    // Find the month
    let islamicMonth = 1;
    for (let i = 0; i < 12; i++) {
        if (remainingDays < monthLengths[i]) {
            islamicMonth = i + 1;
            break;
        }
        remainingDays -= monthLengths[i];
    }

    let islamicDay = Math.floor(remainingDays) + 1;

    // Boundary checks
    if (islamicDay < 1) islamicDay = 1;
    if (islamicDay > 30) islamicDay = 30;
    if (islamicMonth < 1) islamicMonth = 1;
    if (islamicMonth > 12) islamicMonth = 12;

    return [islamicYear, islamicMonth, islamicDay];
}

function islamicToGregorian(year, month, day) {
    // Islamic epoch
    const islamicEpoch = new Date(622, 6, 16);

    // Calculate days since epoch
    const islamicYearLength = 354.36667;
    const daysSinceEpoch = Math.floor((year - 1) * islamicYearLength);

    // Add days for months
    const monthLengths = [30, 29, 30, 29, 30, 29, 30, 29, 30, 29, 30, 29];
    let daysInMonths = 0;
    for (let i = 0; i < month - 1; i++) {
        daysInMonths += monthLengths[i];
    }

    // Add day of month
    const totalDays = daysSinceEpoch + daysInMonths + day - 1;

    // Convert to Gregorian
    const gregorianDate = new Date(islamicEpoch.getTime() + totalDays * 24 * 60 * 60 * 1000);

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
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="result-title">📅 Gregorian Calendar</span>
                            <button class="copy-btn btn btn-sm" onclick="copyToClipboard('${formatDate(...gregorianDate, gregorianMonths)}', this)">
                                <i class="bi bi-clipboard"></i> Copy
                            </button>
                        </div>
                        <div class="result-value mb-3">${formatDate(...gregorianDate, gregorianMonths)}</div>
                        <div class="d-flex flex-column gap-2">
                            <div class="format-item d-flex justify-content-between">
                                <span class="format-label">ISO Format:</span>
                                <span class="format-value">${date.toISOString().split('T')[0]}</span>
                            </div>
                            <div class="format-item d-flex justify-content-between">
                                <span class="format-label">Full:</span>
                                <span class="format-value">${dayName}, ${gregorianMonths[gregorianDate[1] - 1]} ${gregorianDate[2]}, ${gregorianDate[0]}</span>
                            </div>
                            <div class="format-item d-flex justify-content-between">
                                <span class="format-label">Day of Week:</span>
                                <span class="format-value">${dayName}</span>
                            </div>
                            <div class="format-item d-flex justify-content-between">
                                <span class="format-label">Timezone:</span>
                                <span class="format-value">${selectedTimezone}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="result-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="result-title">🌙 Persian Calendar (Jalali)</span>
                            <button class="copy-btn btn btn-sm" onclick="copyToClipboard('${formatDate(...persianDate, persianMonths)}', this)">
                                <i class="bi bi-clipboard"></i> Copy
                            </button>
                        </div>
                        <div class="result-value mb-3">${formatDate(...persianDate, persianMonths)}</div>
                        <div class="d-flex flex-column gap-2">
                            <div class="format-item d-flex justify-content-between">
                                <span class="format-label">Full:</span>
                                <span class="format-value">${persianDayName}, ${persianDate[2]} ${persianMonths[persianDate[1] - 1]} ${persianDate[0]}</span>
                            </div>
                            <div class="format-item d-flex justify-content-between">
                                <span class="format-label">Numeric:</span>
                                <span class="format-value">${persianDate[0]}/${String(persianDate[1]).padStart(2, '0')}/${String(persianDate[2]).padStart(2, '0')}</span>
                            </div>
                            <div class="format-item d-flex justify-content-between">
                                <span class="format-label">Day of Week:</span>
                                <span class="format-value">${persianDayName}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="result-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="result-title">☪️ Islamic Calendar (Hijri)</span>
                            <button class="copy-btn btn btn-sm" onclick="copyToClipboard('${formatDate(...islamicDate, islamicMonths)}', this)">
                                <i class="bi bi-clipboard"></i> Copy
                            </button>
                        </div>
                        <div class="result-value mb-3">${formatDate(...islamicDate, islamicMonths)}</div>
                        <div class="d-flex flex-column gap-2">
                            <div class="format-item d-flex justify-content-between">
                                <span class="format-label">Full:</span>
                                <span class="format-value">${islamicDayName}, ${islamicDate[2]} ${islamicMonths[islamicDate[1] - 1]} ${islamicDate[0]} AH</span>
                            </div>
                            <div class="format-item d-flex justify-content-between">
                                <span class="format-label">Numeric:</span>
                                <span class="format-value">${islamicDate[0]}/${String(islamicDate[1]).padStart(2, '0')}/${String(islamicDate[2]).padStart(2, '0')}</span>
                            </div>
                            <div class="format-item d-flex justify-content-between">
                                <span class="format-label">Day of Week:</span>
                                <span class="format-value">${islamicDayName}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="result-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="result-title">⏰ Unix Timestamp</span>
                            <button class="copy-btn btn btn-sm" onclick="copyToClipboard('${timestamp}', this)">
                                <i class="bi bi-clipboard"></i> Copy
                            </button>
                        </div>
                        <div class="result-value mb-3">${timestamp}</div>
                        <div class="d-flex flex-column gap-2">
                            <div class="format-item d-flex justify-content-between">
                                <span class="format-label">Milliseconds:</span>
                                <span class="format-value">${timestamp * 1000}</span>
                            </div>
                            <div class="format-item d-flex justify-content-between">
                                <span class="format-label">UTC String:</span>
                                <span class="format-value">${date.toUTCString()}</span>
                            </div>
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
        button.innerHTML = '<i class="bi bi-check"></i> Copied!';
        button.classList.add('copied');
        setTimeout(() => {
            button.innerHTML = '<i class="bi bi-clipboard"></i> Copy';
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
        const activeTab = document.querySelector('.nav-link.active').textContent.toLowerCase();
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
            const activeTab = document.querySelector('.nav-link.active').textContent.toLowerCase();
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
