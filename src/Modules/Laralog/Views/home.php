<?php $this->start('content') ?>

<!-- ── Navbar ── -->
<nav class="navbar navbar-dark">
    <div class="navbar-brand">
        <div class="live-dot"></div>
        Laralog
        <sub><small>laravel log viewer</small></sub>
    </div>
    <div class="d-flex align-items-center gap-2 ms-3 d-none">
        <div class="stat-badge">
            <div class="stat-dot" style="background:var(--c-error)"></div>
            Errors: <b id="sErr">0</b></div>
        <div class="stat-badge">
            <div class="stat-dot" style="background:var(--c-warning)"></div>
            Warnings: <b id="sWarn">0</b></div>
        <div class="stat-badge">
            <div class="stat-dot" style="background:var(--c-info)"></div>
            Info: <b id="sInfo">0</b></div>
        <div class="stat-badge">Total: <b id="sTotal">0</b></div>
    </div>
    <div class="ms-auto d-flex gap-2">
        <button class="btn btn-primary btn-sm" id="btnNewLog">
            <i class="bi bi-plus-lg me-1"></i> New Log
        </button>
    </div>
</nav>

<!-- ── App body ── -->
<div class="app-body">

    <!-- ── Sidebar ── -->
    <div class="sidebar">
        <div class="sb-section-title">Levels</div>
        <div id="sbLevels"></div>

        <div class="sb-divider"></div>

        <div class="sb-section-title">
            Storage
            <span id="sbSessionCount" style="color:#3d4a5c;font-size:10px;"></span>
        </div>
        <div class="sb-sessions" id="sbSessions">
            <div class="sb-empty" id="sbNoSessions">No saved sessions yet.<br>Parse a log to create one.</div>
        </div>
    </div>

    <!-- ── Main Panel ── -->
    <div class="main-panel">

        <!-- Toolbar -->
        <div class="toolbar">
            <div class="search-box">
                <i class="bi bi-search"></i>
                <input type="text" class="form-control" id="searchInput" placeholder="Search logs…">
            </div>
            <div class="d-flex gap-1 flex-wrap d-none" id="filterBtns"></div>
            <div class="d-flex gap-1 ms-auto">
                <button class="btn btn-outline-secondary btn-sm" id="btnClearFilters">
                    <i class="bi bi-x-circle me-1"></i>Clear Filters
                </button>
                <button class="btn btn-outline-secondary btn-sm" id="btnCollapseAll">
                    <i class="bi bi-arrows-collapse me-1"></i>Collapse All
                </button>
                <button class="btn btn-outline-secondary btn-sm" id="btnCopy">
                    <i class="bi bi-clipboard me-1"></i>Copy
                </button>
                <button class="btn btn-outline-secondary btn-sm" id="btnClearAll">
                    <i class="bi bi-trash me-1"></i>Clear
                </button>
            </div>
        </div>

        <!-- Input Zone -->
        <div class="inp-zone d-none" id="inpZone">
            <label>⬇ Paste Laravel Log &nbsp;<span
                    style="color:#4a5a72">laravel.log / stderr / artisan output</span></label>
            <textarea class="form-control mb-2" id="logInput" rows="5"
                      placeholder="[2024-01-15 10:23:45] production.ERROR: Connection refused ...&#10;&#10;Paste your laravel.log content here and press Parse"></textarea>
            <div class="d-flex gap-2 align-items-center">
                <button class="btn btn-primary btn-sm" id="btnParse">
                    <i class="bi bi-lightning-charge-fill me-1"></i>Parse Log
                </button>
                <button class="btn btn-outline-secondary btn-sm" id="btnSample">Load Sample</button>
                <span class="ms-auto text-secondary" style="font-size:11px">Supports full stack traces &amp; context JSON</span>
            </div>
        </div>

        <!-- Log List -->
        <div class="log-wrap" id="logWrap">
            <div class="log-empty" id="logEmpty">
                <div class="log-empty-ico">📃</div>
                <p>Click on <b style="color:#8a93a6"> + New Log </b> <br/>and paste your Laravel log<br>and click <b style="color:#8a93a6">Parse & Save</b></p>
            </div>
            <div id="logList"></div>
        </div>

        <!-- Status bar -->
        <div class="status-bar">
            Showing <b id="sumVis">0</b>&nbsp;of&nbsp;<b id="sumTot">0</b> entries
            <span class="ms-auto" id="sumRange"></span>
        </div>
    </div>
</div>

<!-- ── New Log Modal ── -->
<div class="modal fade" id="newLogModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2 text-primary"></i>New Log Session</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Session Name <span
                            style="color:#4a5a72;font-size:9px">(optional)</span></label>
                    <input type="text" class="form-control" id="modalName"
                           placeholder="e.g. production 2026-03-03 morning">
                </div>
                <div class="mb-1">
                    <label class="form-label">⬇ Paste Laravel Log <span style="color:#4a5a72">laravel.log / stderr / artisan output</span></label>
                    <textarea class="form-control" id="modalInput" rows="9"
                              placeholder="[2024-01-15 10:23:45] production.ERROR: ..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="modalParse">
                    <i class="bi bi-lightning-charge-fill me-1"></i>Parse &amp; Save
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ── Toast ── -->
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="appToast" class="toast align-items-center text-bg-primary border-0" role="alert" data-bs-delay="2200">
        <div class="d-flex">
            <div class="toast-body" id="toastMsg"></div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>

<?php $this->end() ?>
