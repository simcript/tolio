<?php $this->start('content') ?>
<!-- Hidden input for server time - Should be populated by server-side code -->
<!-- Format: YYYY-MM-DD HH:MM:SS (UTC) -->
<!-- Example: 2026-03-04 15:30:50 -->
<input type="hidden" id="server-utc-time" value="<?= $this->e($serverUtcTime) ?>">

<div class="container py-4">
    <!-- Current Time Card -->
    <div class="current-time">

        <h1 class="page-title">iDate</h1>
        <p class="page-subtitle">Convert between Gregorian, Jalali, Hijri & Unix Timestamp</p>
        <h2>
            <span class="time-badge d-none" id="time-source-badge">Server Time ✓</span>
        </h2>
        <div class="time-display" id="currentTime"></div>
    </div>


    <div class="main-grid">
        <!-- Input Card -->
        <div class="card">
            <div class="card-header">
                <div class="card-icon">📅</div>
                <h2 class="card-title">Input Date</h2>
            </div>

            <div class="tabs">
                <button class="tab active" onclick="switchTab('gregorian')">Gregorian</button>
                <button class="tab" onclick="switchTab('persian')">Persian</button>
                <button class="tab" onclick="switchTab('islamic')">Islamic</button>
                <button class="tab" onclick="switchTab('timestamp')">Timestamp</button>
            </div>

            <!-- Gregorian Tab -->
            <div id="gregorian-tab" class="tab-content active">
                <div class="date-inputs">
                    <div class="form-group">
                        <label>Year</label>
                        <input type="number" id="g-year" placeholder="2024" min="1" max="9999">
                    </div>
                    <div class="form-group">
                        <label>Month</label>
                        <input type="number" id="g-month" placeholder="1-12" min="1" max="12">
                    </div>
                    <div class="form-group">
                        <label>Day</label>
                        <input type="number" id="g-day" placeholder="1-31" min="1" max="31">
                    </div>
                </div>
                <div class="btn-group">
                    <button class="btn btn-primary" onclick="convertFromGregorian()">Convert</button>
                    <button class="btn btn-secondary" onclick="setToday('gregorian')">Today</button>
                </div>
            </div>

            <!-- Persian Tab -->
            <div id="persian-tab" class="tab-content">
                <div class="date-inputs">
                    <div class="form-group">
                        <label>Year</label>
                        <input type="number" id="p-year" placeholder="1403" min="1" max="9999">
                    </div>
                    <div class="form-group">
                        <label>Month</label>
                        <input type="number" id="p-month" placeholder="1-12" min="1" max="12">
                    </div>
                    <div class="form-group">
                        <label>Day</label>
                        <input type="number" id="p-day" placeholder="1-31" min="1" max="31">
                    </div>
                </div>
                <div class="btn-group">
                    <button class="btn btn-primary" onclick="convertFromPersian()">Convert</button>
                    <button class="btn btn-secondary" onclick="setToday('persian')">Today</button>
                </div>
            </div>

            <!-- Islamic Tab -->
            <div id="islamic-tab" class="tab-content">
                <div class="date-inputs">
                    <div class="form-group">
                        <label>Year</label>
                        <input type="number" id="i-year" placeholder="1446" min="1" max="9999">
                    </div>
                    <div class="form-group">
                        <label>Month</label>
                        <input type="number" id="i-month" placeholder="1-12" min="1" max="12">
                    </div>
                    <div class="form-group">
                        <label>Day</label>
                        <input type="number" id="i-day" placeholder="1-30" min="1" max="30">
                    </div>
                </div>
                <div class="btn-group">
                    <button class="btn btn-primary" onclick="convertFromIslamic()">Convert</button>
                    <button class="btn btn-secondary" onclick="setToday('islamic')">Today</button>
                </div>
            </div>

            <!-- Timestamp Tab -->
            <div id="timestamp-tab" class="tab-content">
                <div class="form-group">
                    <label>Unix Timestamp (seconds)</label>
                    <input type="number" id="timestamp" placeholder="1704067200">
                </div>
                <div class="btn-group">
                    <button class="btn btn-primary" onclick="convertFromTimestamp()">Convert</button>
                    <button class="btn btn-secondary" onclick="setNowTimestamp()">Now</button>
                </div>
            </div>
        </div>

        <!-- Format Options Card -->
        <div class="card">
            <div class="card-header">
                <div class="card-icon">⚙️</div>
                <h2 class="card-title">Format Options</h2>
            </div>

            <div class="format-options">
                <div class="format-group">
                    <label>Timezone</label>
                    <select id="timezone" onchange="changeTimezone()">
                        <option value="UTC" selected>GMT (UTC+0)</option>
                        <option value="Europe/London">London (GMT)</option>
                        <option value="Europe/Paris">Paris (GMT+1)</option>
                        <option value="Europe/Istanbul">Istanbul (GMT+3)</option>
                        <option value="Asia/Dubai">Dubai (GMT+4)</option>
                        <option value="Asia/Tehran">Tehran (GMT+3:30)</option>
                        <option value="Asia/Karachi">Karachi (GMT+5)</option>
                        <option value="Asia/Kolkata">Mumbai (GMT+5:30)</option>
                        <option value="Asia/Dhaka">Dhaka (GMT+6)</option>
                        <option value="Asia/Bangkok">Bangkok (GMT+7)</option>
                        <option value="Asia/Shanghai">Shanghai (GMT+8)</option>
                        <option value="Asia/Tokyo">Tokyo (GMT+9)</option>
                        <option value="Australia/Sydney">Sydney (GMT+11)</option>
                        <option value="Pacific/Auckland">Auckland (GMT+13)</option>
                        <option value="America/New_York">New York (GMT-5)</option>
                        <option value="America/Chicago">Chicago (GMT-6)</option>
                        <option value="America/Denver">Denver (GMT-7)</option>
                        <option value="America/Los_Angeles">Los Angeles (GMT-8)</option>
                    </select>
                </div>

                <div class="format-group">
                    <label>Month Format</label>
                    <div class="checkbox-group">
                        <input type="radio" name="month-format" id="month-number" value="number" checked>
                        <label for="month-number">Number (01, 02, ...)</label>
                    </div>
                    <div class="checkbox-group">
                        <input type="radio" name="month-format" id="month-name" value="name">
                        <label for="month-name">Name (January, Feb, ...)</label>
                    </div>
                </div>

                <div class="format-group">
                    <label>Day Format</label>
                    <div class="checkbox-group">
                        <input type="radio" name="day-format" id="day-number" value="number" checked>
                        <label for="day-number">Number only</label>
                    </div>
                    <div class="checkbox-group">
                        <input type="radio" name="day-format" id="day-name" value="name">
                        <label for="day-name">With day name</label>
                    </div>
                </div>

                <div class="format-group">
                    <label>Separator</label>
                    <select id="separator">
                        <option value="/">/</option>
                        <option value="-" selected>-</option>
                        <option value=".">.</option>
                        <option value=" ">Space</option>
                    </select>
                </div>

                <div class="format-group">
                    <label>Zero Padding</label>
                    <div class="checkbox-group">
                        <input type="checkbox" id="zero-padding" checked>
                        <label for="zero-padding">Add leading zeros (01, 02)</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Results -->
    <div class="results-container" id="results">
        <!-- Results will be displayed here -->
    </div>
</div>

<!-- Bootstrap JS -->

<?php $this->end() ?>
