<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>iDate | Universal Date Converter</title>

    <link rel="icon"
          href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📅</text></svg>">

    <link href="assets/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">

</head>
<body>
<!-- Hidden input for server time - Should be populated by server-side code -->
<!-- Format: YYYY-MM-DD HH:MM:SS (UTC) -->
<!-- Example: 2026-03-04 15:30:50 -->
<input type="hidden" id="server-utc-time" value="<?= (new DateTime('UTC', new DateTimeZone('UTC')))->format('Y-m-d H:i:s'); ?>">

<div class="container py-4">
    <!-- Current Time Card -->
    <div class="current-time-card">

        <h1 class="page-title">iDate</h1>
        <p class="page-subtitle">Convert between Gregorian, Jalali, Hijri & Unix Timestamp</p>
        <h2>
            <span class="time-badge d-none" id="time-source-badge">Server Time ✓</span>
        </h2>
        <div class="time-display" id="currentTime"></div>
    </div>

    <!-- Main Content -->
    <div class="row g-4 mb-4">
        <!-- Input Card -->
        <div class="col-lg-6">
            <div class="card custom-card">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3 pb-3 border-bottom border-secondary">
                        <div class="card-icon me-2">📅</div>
                        <h5 class="mb-0">Input Date</h5>
                    </div>

                    <!-- Tabs -->
                    <ul class="nav nav-tabs mb-3" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#gregorian-tab">
                                Gregorian
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#persian-tab">Persian</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#islamic-tab">Islamic</button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#timestamp-tab">Timestamp
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <!-- Gregorian Tab -->
                        <div class="tab-pane fade show active" id="gregorian-tab">
                            <div class="row g-2 mb-3">
                                <div class="col-4">
                                    <label class="form-label">Year</label>
                                    <input type="number" class="form-control" id="g-year" placeholder="2024" min="1"
                                           max="9999">
                                </div>
                                <div class="col-4">
                                    <label class="form-label">Month</label>
                                    <input type="number" class="form-control" id="g-month" placeholder="1-12" min="1"
                                           max="12">
                                </div>
                                <div class="col-4">
                                    <label class="form-label">Day</label>
                                    <input type="number" class="form-control" id="g-day" placeholder="1-31" min="1"
                                           max="31">
                                </div>
                            </div>
                            <div class="d-grid gap-2">
                                <button class="btn btn-primary" onclick="convertFromGregorian()">Convert</button>
                                <button class="btn btn-secondary" onclick="setToday('gregorian')">Today</button>
                            </div>
                        </div>

                        <!-- Persian Tab -->
                        <div class="tab-pane fade" id="persian-tab">
                            <div class="row g-2 mb-3">
                                <div class="col-4">
                                    <label class="form-label">Year</label>
                                    <input type="number" class="form-control" id="p-year" placeholder="1403" min="1"
                                           max="9999">
                                </div>
                                <div class="col-4">
                                    <label class="form-label">Month</label>
                                    <input type="number" class="form-control" id="p-month" placeholder="1-12" min="1"
                                           max="12">
                                </div>
                                <div class="col-4">
                                    <label class="form-label">Day</label>
                                    <input type="number" class="form-control" id="p-day" placeholder="1-31" min="1"
                                           max="31">
                                </div>
                            </div>
                            <div class="d-grid gap-2">
                                <button class="btn btn-primary" onclick="convertFromPersian()">Convert</button>
                                <button class="btn btn-secondary" onclick="setToday('persian')">Today</button>
                            </div>
                        </div>

                        <!-- Islamic Tab -->
                        <div class="tab-pane fade" id="islamic-tab">
                            <div class="row g-2 mb-3">
                                <div class="col-4">
                                    <label class="form-label">Year</label>
                                    <input type="number" class="form-control" id="i-year" placeholder="1446" min="1"
                                           max="9999">
                                </div>
                                <div class="col-4">
                                    <label class="form-label">Month</label>
                                    <input type="number" class="form-control" id="i-month" placeholder="1-12" min="1"
                                           max="12">
                                </div>
                                <div class="col-4">
                                    <label class="form-label">Day</label>
                                    <input type="number" class="form-control" id="i-day" placeholder="1-30" min="1"
                                           max="30">
                                </div>
                            </div>
                            <div class="d-grid gap-2">
                                <button class="btn btn-primary" onclick="convertFromIslamic()">Convert</button>
                                <button class="btn btn-secondary" onclick="setToday('islamic')">Today</button>
                            </div>
                        </div>

                        <!-- Timestamp Tab -->
                        <div class="tab-pane fade" id="timestamp-tab">
                            <div class="mb-3">
                                <label class="form-label">Unix Timestamp (seconds)</label>
                                <input type="number" class="form-control" id="timestamp" placeholder="1704067200">
                            </div>
                            <div class="d-grid gap-2">
                                <button class="btn btn-primary" onclick="convertFromTimestamp()">Convert</button>
                                <button class="btn btn-secondary" onclick="setNowTimestamp()">Now</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Format Options Card -->
        <div class="col-lg-6">
            <div class="card custom-card">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3 pb-3 border-bottom border-secondary">
                        <div class="card-icon me-2">⚙️</div>
                        <h5 class="mb-0">Format Options</h5>
                    </div>

                    <div class="row g-3">
                        <!-- Timezone -->
                        <div class="col-md-6">
                            <label class="form-label">Timezone</label>
                            <select class="form-select" id="timezone" onchange="changeTimezone()">
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

                        <!-- Separator -->
                        <div class="col-md-6">
                            <label class="form-label">Separator</label>
                            <select class="form-select" id="separator">
                                <option value="/">/</option>
                                <option value="-" selected>-</option>
                                <option value=".">.</option>
                                <option value=" ">Space</option>
                            </select>
                        </div>

                        <!-- Month Format -->
                        <div class="col-md-6">
                            <label class="form-label">Month Format</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="month-format" id="month-number"
                                       value="number" checked>
                                <label class="form-check-label" for="month-number">Number (01, 02, ...)</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="month-format" id="month-name"
                                       value="name">
                                <label class="form-check-label" for="month-name">Name (January, Feb, ...)</label>
                            </div>
                        </div>

                        <!-- Day Format -->
                        <div class="col-md-6">
                            <label class="form-label">Day Format</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="day-format" id="day-number"
                                       value="number" checked>
                                <label class="form-check-label" for="day-number">Number only</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="day-format" id="day-name"
                                       value="name">
                                <label class="form-check-label" for="day-name">With day name</label>
                            </div>
                        </div>

                        <!-- Zero Padding -->
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="zero-padding" checked>
                                <label class="form-check-label" for="zero-padding">Add leading zeros (01, 02)</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Results -->
    <div id="results"></div>
</div>

<!-- Bootstrap JS -->

<script src="assets/bootstrap.bundle.min.js"></script>
<script src="assets/script.js"></script>
</body>
</html>
