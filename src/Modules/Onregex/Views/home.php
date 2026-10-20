<?php $this->start('content') ?>
    <div class="container py-4">

        <!-- Regex Input Card -->
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="main-title">OnRegex</h1>
                <h1 class="subtitle">Online RegEx Tester</h1>

                <div class="card custom-card">
                    <div class="card-body">
                        <h5 class="card-title-custom">
                            <i class="bi bi-code-slash"></i>
                            Regular Expression
                        </h5>

                        <div class="mb-3">
                            <label for="regexInput" class="form-label">Enter your regex pattern</label>
                            <input
                                type="text"
                                class="form-control"
                                id="regexInput"
                                placeholder="e.g., \d{3}-\d{3}-\d{4}"
                                autocomplete="off"
                                spellcheck="false"
                            >
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Flags</label>
                            <div class="row g-3">
                                <div class="col-auto">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="flagGlobal" checked>
                                        <label class="form-check-label" for="flagGlobal">
                                            g (global)
                                        </label>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="flagCaseInsensitive">
                                        <label class="form-check-label" for="flagCaseInsensitive">
                                            i (case insensitive)
                                        </label>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="flagMultiline">
                                        <label class="form-check-label" for="flagMultiline">
                                            m (multiline)
                                        </label>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="flagDotAll">
                                        <label class="form-check-label" for="flagDotAll">
                                            s (dotAll)
                                        </label>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="flagUnicode">
                                        <label class="form-check-label" for="flagUnicode">
                                            u (unicode)
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="form-label">Quick Patterns</label>
                            <div class="d-flex flex-wrap gap-2">
                                <button class="btn btn-sm btn-pattern" data-pattern="\d+">
                                    <i class="bi bi-123"></i> Numbers
                                </button>
                                <button class="btn btn-sm btn-pattern" data-pattern="[a-zA-Z]+">
                                    <i class="bi bi-fonts"></i> Letters
                                </button>
                                <button class="btn btn-sm btn-pattern" data-pattern="\w+@\w+\.\w+">
                                    <i class="bi bi-envelope"></i> Email
                                </button>
                                <button class="btn btn-sm btn-pattern" data-pattern="https?://[^\s]+">
                                    <i class="bi bi-link-45deg"></i> URL
                                </button>
                                <button class="btn btn-sm btn-pattern" data-pattern="\d{3}-\d{3}-\d{4}">
                                    <i class="bi bi-telephone"></i> Phone (US)
                                </button>
                                <button class="btn btn-sm btn-pattern" data-pattern="#[0-9a-fA-F]{6}">
                                    <i class="bi bi-palette"></i> Hex Color
                                </button>
                            </div>
                        </div>
                        <h5 class="card-title-custom mt-4">
                            <i class="bi bi-file-text"></i>
                            Test Text
                        </h5>
                        <div class="mb-0">
                            <label for="testText" class="form-label">Enter text to test against the pattern</label>
                            <textarea
                                    class="form-control"
                                    id="testText"
                                    placeholder="Enter your test text here..."
                                    spellcheck="false"
                            ></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Results Card -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card custom-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title-custom mb-0">
                                <i class="bi bi-check-circle"></i>
                                Results
                            </h5>
                            <span class="badge badge-custom" id="matchBadge" style="display: none;">
                                <i class="bi bi-bullseye"></i>
                                <span id="matchBadgeText">0 matches</span>
                            </span>
                        </div>

                        <div class="results-box" id="results">
                            <div class="no-match">
                                <i class="bi bi-info-circle fs-3 mb-2 d-block"></i>
                                Enter a regex pattern and test text to see matches
                            </div>
                        </div>

                        <div class="row g-3 mt-3" id="stats" style="display: none;">
                            <div class="col-md-4">
                                <div class="card stat-card">
                                    <div class="card-body">
                                        <div class="stat-label">Matches Found</div>
                                        <div class="stat-value" id="matchCount">0</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card stat-card">
                                    <div class="card-body">
                                        <div class="stat-label">Pattern Length</div>
                                        <div class="stat-value" id="patternLength">0</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card stat-card">
                                    <div class="card-body">
                                        <div class="stat-label">Text Length</div>
                                        <div class="stat-value" id="textLength">0</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="errorContainer" class="mt-3"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php $this->end() ?>
