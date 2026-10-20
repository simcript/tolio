let currentJSON = null;
let formattedText = '';

function getIndent() {
    const val = document.getElementById('indentSize').value;
    return val === 'tab' ? '\t' : ' '.repeat(parseInt(val));
}

function sortObject(obj, order) {
    if (Array.isArray(obj)) {
        return obj.map(item => sortObject(item, order));
    }
    if (obj !== null && typeof obj === 'object') {
        const sorted = {};
        const keys = Object.keys(obj).sort((a, b) => {
            return order === 'asc' ? a.localeCompare(b) : b.localeCompare(a);
        });
        keys.forEach(key => {
            sorted[key] = sortObject(obj[key], order);
        });
        return sorted;
    }
    return obj;
}

function syntaxHighlight(json) {
    json = json.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    return json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?|[{}\[\],])/g, function (match) {
        let cls = 'json-number';
        if (/^"/.test(match)) {
            if (/:$/.test(match)) {
                cls = 'json-key';
            } else {
                cls = 'json-string';
            }
        } else if (/true|false/.test(match)) {
            cls = 'json-boolean';
        } else if (/null/.test(match)) {
            cls = 'json-null';
        } else if (/[{}\[\],]/.test(match)) {
            cls = 'json-bracket';
        }
        return '<span class="' + cls + '">' + match + '</span>';
    });
}

function parseNestedJsonStrings(obj) {
    if (typeof obj === 'string') {
        const trimmed = obj.trim();
        if ((trimmed.startsWith('{') && trimmed.endsWith('}')) ||
            (trimmed.startsWith('[') && trimmed.endsWith(']'))) {
            try {
                const parsed = JSON.parse(trimmed);
                return parseNestedJsonStrings(parsed);
            } catch (e) {
                return obj;
            }
        }
        return obj;
    }

    if (Array.isArray(obj)) {
        return obj.map(item => parseNestedJsonStrings(item));
    }

    if (obj !== null && typeof obj === 'object') {
        const result = {};
        for (const key in obj) {
            result[key] = parseNestedJsonStrings(obj[key]);
        }
        return result;
    }

    return obj;
}

function parseInput() {
    let input = document.getElementById('input').value.trim();

    input = input.replace(/^\uFEFF/, '');
    input = input.replace(/[\u200B-\u200D\uFEFF]/g, '');

    if ((input.startsWith('`') && input.endsWith('`')) ||
        (input.startsWith("'") && input.endsWith("'") && input.includes('{'))) {
        input = input.slice(1, -1);
    }

    let parsed = JSON.parse(input);

    if (document.getElementById('parseNested').checked) {
        parsed = parseNestedJsonStrings(parsed);
    }

    return parsed;
}

function formatJSON() {
    try {
        const input = document.getElementById('input').value.trim();
        if (!input) {
            showStatus('Please enter JSON', 'danger');
            return;
        }

        let parsed;
        try {
            parsed = parseInput();
        } catch (e) {
            const match = e.message.match(/position (\d+)/);
            if (match) {
                const pos = parseInt(match[1]);
                const lines = input.substring(0, pos).split('\n');
                const line = lines.length;
                const col = lines[lines.length - 1].length + 1;
                showStatus(`Error at line ${line}, column ${col}: ${e.message}`, 'danger');
            } else {
                showStatus('Error: ' + e.message, 'danger');
            }
            document.getElementById('stats').classList.add('d-none');
            return;
        }

        const sortOrder = document.getElementById('sortKeys').value;
        if (sortOrder !== 'none') {
            parsed = sortObject(parsed, sortOrder);
        }

        currentJSON = parsed;
        const indent = getIndent();
        formattedText = JSON.stringify(parsed, null, indent);

        document.getElementById('output').innerHTML = '<pre>' + syntaxHighlight(formattedText) + '</pre>';
        showStatus('JSON formatted successfully', 'success');
        updateStats(parsed, formattedText);
    } catch (e) {
        document.getElementById('output').innerHTML = '';
        showStatus('Error: ' + e.message, 'danger');
        document.getElementById('stats').classList.add('d-none');
    }
}

function minifyJSON() {
    try {
        const parsed = parseInput();
        currentJSON = parsed;
        formattedText = JSON.stringify(parsed);

        document.getElementById('output').innerHTML = '<pre style="word-wrap: break-word; white-space: pre-wrap;">' + syntaxHighlight(formattedText) + '</pre>';
        showStatus('JSON minified - ' + formattedText.length + ' chars', 'success');
        updateStats(parsed, formattedText);
    } catch (e) {
        showStatus('Error: ' + e.message, 'danger');
    }
}

function expandJSON() {
    try {
        const parsed = parseInput();
        currentJSON = parsed;
        formattedText = JSON.stringify(parsed, null, 4);

        document.getElementById('output').innerHTML = '<pre>' + syntaxHighlight(formattedText) + '</pre>';

        const minified = JSON.stringify(parsed);
        const ratio = ((formattedText.length / minified.length) * 100).toFixed(0);
        showStatus(`JSON expanded - ${formattedText.length} chars (${ratio}% of minified)`, 'success');
        updateStats(parsed, formattedText);
    } catch (e) {
        showStatus('Error: ' + e.message, 'danger');
    }
}

function validateJSON() {
    try {
        const input = document.getElementById('input').value.trim();
        if (!input) {
            showStatus('Please enter JSON', 'danger');
            return;
        }
        JSON.parse(input);
        showStatus('Valid JSON!', 'success');
    } catch (e) {
        showStatus('Invalid JSON: ' + e.message, 'danger');
    }
}

function decodeUnicode() {
    try {
        const parsed = parseInput();
        currentJSON = parsed;
        const indent = getIndent();

        formattedText = JSON.stringify(parsed, null, indent);
        formattedText = formattedText.replace(/\\u[\dA-Fa-f]{4}/g, match => {
            return String.fromCharCode(parseInt(match.replace('\\u', ''), 16));
        });

        document.getElementById('output').innerHTML = '<pre>' + syntaxHighlight(formattedText) + '</pre>';
        showStatus('Unicode decoded', 'success');
        updateStats(parsed, formattedText);
    } catch (e) {
        showStatus('Error: ' + e.message, 'danger');
    }
}

function fixJSON() {
    let input = document.getElementById('input').value;

    input = input.replace(/,(\s*[}\]])/g, '$1');
    input = input.replace(/([{,]\s*)(\w+)(\s*:)/g, '$1"$2"$3');
    input = input.replace(/:\s*'([^']*)'/g, ': "$1"');
    input = input.replace(/:\s*undefined/g, ': null');

    document.getElementById('input').value = input;
    showStatus('JSON fix attempted - try formatting now', 'info');
}

function clearAll() {
    document.getElementById('input').value = '';
    document.getElementById('output').innerHTML = '';
    document.getElementById('status').classList.add('d-none');
    document.getElementById('stats').classList.add('d-none');
    currentJSON = null;
    formattedText = '';
}

function loadSample() {
    const sample = {
        "name": "JSON Formatter",
        "version": "2.0",
        "features": ["format", "minify", "validate", "search"],
        "settings": {
            "theme": "dark",
            "indent": 4,
            "nested": "{\"inner\": \"value\"}"
        },
        "active": true,
        "count": 42
    };
    document.getElementById('input').value = JSON.stringify(sample);
    formatJSON();
}

function copyOutput() {
    if (formattedText) {
        navigator.clipboard.writeText(formattedText).then(() => {
            showStatus('Copied to clipboard!', 'success');
        });
    }
}

function pasteFromClipboard() {
    navigator.clipboard.readText().then(text => {
        document.getElementById('input').value = text;
        showStatus('Pasted from clipboard', 'info');
    });
}

function showStatus(message, type) {
    const status = document.getElementById('status');
    status.className = `alert alert-${type}`;
    status.textContent = message;
    status.classList.remove('d-none');
}

function updateStats(parsed, text) {
    function countKeys(obj) {
        let count = 0;
        if (obj && typeof obj === 'object') {
            if (Array.isArray(obj)) {
                obj.forEach(item => count += countKeys(item));
            } else {
                count += Object.keys(obj).length;
                Object.values(obj).forEach(val => count += countKeys(val));
            }
        }
        return count;
    }

    function getDepth(obj, current = 0) {
        if (!obj || typeof obj !== 'object') return current;
        if (Array.isArray(obj)) {
            return Math.max(current + 1, ...obj.map(item => getDepth(item, current + 1)));
        }
        return Math.max(current + 1, ...Object.values(obj).map(val => getDepth(val, current + 1)));
    }

    document.getElementById('statKeys').textContent = countKeys(parsed);
    document.getElementById('statDepth').textContent = getDepth(parsed);
    document.getElementById('statSize').textContent = new Blob([text]).size;
    document.getElementById('stats').classList.remove('d-none');
}

// Keyboard shortcuts
document.addEventListener('keydown', function (e) {
    if (e.ctrlKey || e.metaKey) {
        if (e.key === 'Enter') {
            e.preventDefault();
            formatJSON();
        } else if (e.key === 'm') {
            e.preventDefault();
            minifyJSON();
        } else if (e.key === 'f') {
            e.preventDefault();
            document.getElementById('searchText').focus();
        }
    }
});

document.getElementById('indentSize').addEventListener('change', () => {
    if (currentJSON) formatJSON();
});
document.getElementById('sortKeys').addEventListener('change', () => {
    if (document.getElementById('input').value.trim()) formatJSON();
});

// Search & Replace
let searchMatches = [];
let currentMatchIndex = -1;

function getSearchRegex() {
    const searchText = document.getElementById('searchText').value;
    if (!searchText) return null;

    const caseSensitive = document.getElementById('caseSensitive').checked;
    const isRegex = document.getElementById('regexSearch').checked;

    try {
        if (isRegex) {
            return new RegExp(searchText, caseSensitive ? 'g' : 'gi');
        } else {
            const escaped = searchText.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            return new RegExp(escaped, caseSensitive ? 'g' : 'gi');
        }
    } catch (e) {
        showStatus('Regex error: ' + e.message, 'danger');
        return null;
    }
}

function findText() {
    const searchText = document.getElementById('searchText').value;
    if (!searchText) {
        document.getElementById('searchResult').textContent = '';
        clearHighlights();
        return;
    }

    const regex = getSearchRegex();
    if (!regex) return;

    let content = formattedText || document.getElementById('input').value;

    searchMatches = [];
    let match;
    const tempRegex = new RegExp(regex.source, regex.flags);
    while ((match = tempRegex.exec(content)) !== null) {
        searchMatches.push({
            index: match.index,
            length: match[0].length,
            text: match[0]
        });
    }

    if (searchMatches.length > 0) {
        currentMatchIndex = 0;
        highlightAllMatches();
        updateSearchStatus();
        scrollToCurrentMatch();
    } else {
        document.getElementById('searchResult').textContent = 'No matches';
        clearHighlights();
        currentMatchIndex = -1;
    }
}

function highlightAllMatches() {
    let content = formattedText || document.getElementById('input').value;
    const regex = getSearchRegex();
    if (!regex) return;

    let highlighted = syntaxHighlight(content);

    const searchText = document.getElementById('searchText').value;
    const caseSensitive = document.getElementById('caseSensitive').checked;
    const isRegex = document.getElementById('regexSearch').checked;

    let pattern;
    if (isRegex) {
        pattern = searchText;
    } else {
        pattern = searchText.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    const flags = caseSensitive ? 'g' : 'gi';
    const highlightRegex = new RegExp(`(${pattern})`, flags);

    let matchIndex = 0;
    highlighted = highlighted.replace(highlightRegex, (match) => {
        const className = matchIndex === currentMatchIndex ? 'highlight highlight-current' : 'highlight';
        const span = `<span id="match-${matchIndex}" class="${className}">${match}</span>`;
        matchIndex++;
        return span;
    });

    document.getElementById('output').innerHTML = '<pre>' + highlighted + '</pre>';
}

function scrollToCurrentMatch() {
    if (currentMatchIndex < 0 || searchMatches.length === 0) return;

    const matchEl = document.getElementById(`match-${currentMatchIndex}`);
    if (matchEl) {
        const output = document.getElementById('output');
        const scrollTop = matchEl.offsetTop - output.offsetTop - (output.clientHeight / 2) + (matchEl.clientHeight / 2);
        const scrollLeft = matchEl.offsetLeft - output.offsetLeft - (output.clientWidth / 2) + (matchEl.clientWidth / 2);

        output.scrollTo({
            top: Math.max(0, scrollTop),
            left: Math.max(0, scrollLeft),
            behavior: 'smooth'
        });
    }
}

function updateSearchStatus() {
    document.getElementById('searchResult').textContent = `${currentMatchIndex + 1} of ${searchMatches.length}`;
}

function nextMatch() {
    if (searchMatches.length === 0) {
        findText();
        return;
    }
    currentMatchIndex = (currentMatchIndex + 1) % searchMatches.length;
    highlightAllMatches();
    updateSearchStatus();
    scrollToCurrentMatch();
}

function prevMatch() {
    if (searchMatches.length === 0) {
        findText();
        return;
    }
    currentMatchIndex = (currentMatchIndex - 1 + searchMatches.length) % searchMatches.length;
    highlightAllMatches();
    updateSearchStatus();
    scrollToCurrentMatch();
}

function clearHighlights() {
    if (formattedText) {
        document.getElementById('output').innerHTML = '<pre>' + syntaxHighlight(formattedText) + '</pre>';
    }
    searchMatches = [];
    currentMatchIndex = -1;
}

function replaceOne() {
    if (searchMatches.length === 0 || currentMatchIndex < 0) {
        findText();
        if (searchMatches.length === 0) return;
    }

    const replaceTextVal = document.getElementById('replaceText').value;
    const input = document.getElementById('input');
    const match = searchMatches[currentMatchIndex];

    let content = input.value;
    content = content.substring(0, match.index) + replaceTextVal + content.substring(match.index + match.length);

    input.value = content;
    formatJSON();
    findText();
    showStatus('1 replacement made', 'success');
}

function replaceAll() {
    const searchText = document.getElementById('searchText').value;
    const replaceTextVal = document.getElementById('replaceText').value;

    if (!searchText) return;

    const regex = getSearchRegex();
    if (!regex) return;

    const input = document.getElementById('input');
    const originalContent = input.value;
    const newContent = originalContent.replace(regex, replaceTextVal);

    const count = (originalContent.match(regex) || []).length;

    if (count > 0) {
        input.value = newContent;
        formatJSON();
        searchMatches = [];
        currentMatchIndex = -1;
        document.getElementById('searchResult').textContent = '';
        showStatus(`${count} replacements made`, 'success');
    } else {
        showStatus('No matches found', 'warning');
    }
}

document.getElementById('searchText').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
        if (e.shiftKey) {
            prevMatch();
        } else if (searchMatches.length > 0) {
            nextMatch();
        } else {
            findText();
        }
        e.preventDefault();
    }
});

document.getElementById('replaceText').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') {
        replaceOne();
    }
});

// History & LocalStorage
const HISTORY_KEY = 'json_formatter_history';
const MAX_HISTORY = 20;

function getHistory() {
    try {
        const data = localStorage.getItem(HISTORY_KEY);
        return data ? JSON.parse(data) : [];
    } catch (e) {
        return [];
    }
}

function saveHistoryData(history) {
    try {
        localStorage.setItem(HISTORY_KEY, JSON.stringify(history));
    } catch (e) {
        showStatus('Storage error', 'danger');
    }
}

function generateTitle(json) {
    if (typeof json === 'object' && json !== null) {
        if (json.name) return String(json.name).substring(0, 30);
        if (json.title) return String(json.title).substring(0, 30);
        if (json.id) return 'ID: ' + String(json.id).substring(0, 25);

        if (Array.isArray(json)) {
            return `Array (${json.length} items)`;
        }

        const keys = Object.keys(json);
        if (keys.length > 0) {
            return keys.slice(0, 3).join(', ');
        }
    }
    return 'Saved JSON';
}

function saveToHistory() {
    const input = document.getElementById('input').value.trim();
    if (!input) {
        showStatus('Nothing to save', 'warning');
        return;
    }

    try {
        const parsed = JSON.parse(input);
        const history = getHistory();

        const item = {
            id: Date.now(),
            title: generateTitle(parsed),
            preview: JSON.stringify(parsed).substring(0, 100),
            data: input,
            date: new Date().toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            }),
            size: input.length
        };

        history.unshift(item);

        if (history.length > MAX_HISTORY) {
            history.pop();
        }

        saveHistoryData(history);
        renderHistory();
        showStatus('Saved to history', 'success');
    } catch (e) {
        showStatus('Invalid JSON - format first', 'danger');
    }
}

function renderHistory() {
    const history = getHistory();
    const container = document.getElementById('historyList');

    if (history.length === 0) {
        container.innerHTML = '<div class="text-center text-muted small py-4"><i class="bi bi-inbox fs-1 d-block mb-2"></i>No history<br><small>Save JSON using the Save button</small></div>';
        return;
    }

    container.innerHTML = history.map(item => `
                <div class="history-item p-2 mb-2" onclick="loadFromHistory(${item.id})">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-cyan small fw-bold text-truncate" id="title-${item.id}" style="max-width: 150px;" title="${item.title}">${item.title}</span>
                        <small class="text-muted">${item.date}</small>
                    </div>
                    <div class="history-preview">${escapeHtml(item.preview)}${item.preview.length >= 100 ? '...' : ''}</div>
                    <div class="mt-2">
                        <button class="btn btn-outline-primary btn-sm py-0 px-2" onclick="event.stopPropagation(); loadFromHistory(${item.id})"><i class="bi bi-folder2-open"></i></button>
                        <button class="btn btn-outline-secondary btn-sm py-0 px-2" onclick="event.stopPropagation(); editHistoryTitle(${item.id})"><i class="bi bi-pencil"></i></button>
                        <button class="btn btn-outline-danger btn-sm py-0 px-2" onclick="event.stopPropagation(); deleteFromHistory(${item.id})"><i class="bi bi-trash"></i></button>
                    </div>
                </div>
            `).join('');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function loadFromHistory(id) {
    const history = getHistory();
    const item = history.find(h => h.id === id);

    if (item) {
        document.getElementById('input').value = item.data;
        formatJSON();
        showStatus('Loaded from history', 'success');
    }
}

function deleteFromHistory(id) {
    if (!confirm('Delete this item from history?')) return;

    let history = getHistory();
    history = history.filter(h => h.id !== id);
    saveHistoryData(history);
    renderHistory();
    showStatus('Deleted from history', 'success');
}

function editHistoryTitle(id) {
    const history = getHistory();
    const item = history.find(h => h.id === id);
    if (!item) return;

    const titleEl = document.getElementById(`title-${id}`);
    const currentTitle = item.title;

    const input = document.createElement('input');
    input.type = 'text';
    input.value = currentTitle;
    input.className = 'form-control form-control-sm';
    input.style.cssText = 'max-width: 150px;';

    titleEl.replaceWith(input);
    input.focus();
    input.select();

    function saveTitle() {
        const newTitle = input.value.trim() || currentTitle;

        const hist = getHistory();
        const idx = hist.findIndex(h => h.id === id);
        if (idx !== -1) {
            hist[idx].title = newTitle;
            saveHistoryData(hist);
        }

        renderHistory();
        showStatus('Title updated', 'success');
    }

    input.addEventListener('blur', saveTitle);
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            input.blur();
        } else if (e.key === 'Escape') {
            renderHistory();
        }
    });
}

function clearHistory() {
    if (!confirm('Clear all history?')) return;

    localStorage.removeItem(HISTORY_KEY);
    renderHistory();
    showStatus('History cleared', 'success');
}

// Initialize
document.addEventListener('DOMContentLoaded', function () {
    renderHistory();
});
renderHistory();
