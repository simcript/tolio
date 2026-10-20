// DOM Elements
const regexInput = document.getElementById('regexInput');
const testText = document.getElementById('testText');
const resultsContainer = document.getElementById('results');
const errorContainer = document.getElementById('errorContainer');
const statsContainer = document.getElementById('stats');
const matchCountEl = document.getElementById('matchCount');
const patternLengthEl = document.getElementById('patternLength');
const textLengthEl = document.getElementById('textLength');
const matchBadge = document.getElementById('matchBadge');
const matchBadgeText = document.getElementById('matchBadgeText');

// Flags
const flagGlobal = document.getElementById('flagGlobal');
const flagCaseInsensitive = document.getElementById('flagCaseInsensitive');
const flagMultiline = document.getElementById('flagMultiline');
const flagDotAll = document.getElementById('flagDotAll');
const flagUnicode = document.getElementById('flagUnicode');

// Quick pattern buttons
const patternButtons = document.querySelectorAll('.btn-pattern');

// Test regex function
function testRegex() {
    const pattern = regexInput.value.trim();
    const text = testText.value;

    // Clear previous errors
    errorContainer.innerHTML = '';
    regexInput.classList.remove('is-invalid');

    // Validate inputs
    if (!pattern) {
        resultsContainer.innerHTML = `
                    <div class="no-match">
                        <i class="bi bi-exclamation-circle fs-3 mb-2 d-block"></i>
                        Please enter a regex pattern
                    </div>
                `;
        statsContainer.style.display = 'none';
        matchBadge.style.display = 'none';
        return;
    }

    if (!text) {
        resultsContainer.innerHTML = `
                    <div class="no-match">
                        <i class="bi bi-exclamation-circle fs-3 mb-2 d-block"></i>
                        Please enter test text
                    </div>
                `;
        statsContainer.style.display = 'none';
        matchBadge.style.display = 'none';
        return;
    }

    try {
        // Build flags
        let flags = '';
        if (flagGlobal.checked) flags += 'g';
        if (flagCaseInsensitive.checked) flags += 'i';
        if (flagMultiline.checked) flags += 'm';
        if (flagDotAll.checked) flags += 's';
        if (flagUnicode.checked) flags += 'u';

        // Create regex
        const regex = new RegExp(pattern, flags);

        // Find all matches
        const matches = [...text.matchAll(regex)];

        // Update stats
        patternLengthEl.textContent = pattern.length;
        textLengthEl.textContent = text.length;
        matchCountEl.textContent = matches.length;

        // Update badge
        matchBadge.style.display = 'inline-block';
        matchBadgeText.textContent = `${matches.length} ${matches.length === 1 ? 'match' : 'matches'}`;

        // Display results
        if (matches.length === 0) {
            resultsContainer.innerHTML = `
                        <div class="no-match">
                            <i class="bi bi-x-circle fs-3 mb-2 d-block text-danger"></i>
                            No matches found
                        </div>
                    `;
            statsContainer.style.display = 'flex';

            // Show error alert
            errorContainer.innerHTML = `
                        <div class="alert alert-custom d-flex align-items-center" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <div>
                                No matches found for pattern "<strong>${escapeHtml(pattern)}</strong>"
                            </div>
                        </div>
                    `;
        } else {
            // Highlight matches in text
            let highlightedText = '';
            let lastIndex = 0;

            matches.forEach((match) => {
                const matchStart = match.index;
                const matchEnd = matchStart + match[0].length;

                // Add text before match
                highlightedText += escapeHtml(text.substring(lastIndex, matchStart));

                // Add matched text with highlight
                highlightedText += `<span class="match">${escapeHtml(match[0])}</span>`;

                lastIndex = matchEnd;
            });

            // Add remaining text
            highlightedText += escapeHtml(text.substring(lastIndex));

            resultsContainer.innerHTML = highlightedText;
            statsContainer.style.display = 'flex';
        }

    } catch (error) {
        // Handle regex errors
        regexInput.classList.add('is-invalid');
        resultsContainer.innerHTML = `
                    <div class="no-match">
                        <i class="bi bi-bug fs-3 mb-2 d-block text-danger"></i>
                        Invalid regex pattern
                    </div>
                `;
        statsContainer.style.display = 'none';
        matchBadge.style.display = 'none';

        errorContainer.innerHTML = `
                    <div class="alert alert-custom d-flex align-items-center" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <div>
                            <strong>Regex Error:</strong> ${escapeHtml(error.message)}
                        </div>
                    </div>
                `;
    }
}

// Escape HTML to prevent XSS
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Event listeners
regexInput.addEventListener('input', testRegex);
testText.addEventListener('input', testRegex);

flagGlobal.addEventListener('change', testRegex);
flagCaseInsensitive.addEventListener('change', testRegex);
flagMultiline.addEventListener('change', testRegex);
flagDotAll.addEventListener('change', testRegex);
flagUnicode.addEventListener('change', testRegex);

// Quick pattern buttons
patternButtons.forEach(btn => {
    btn.addEventListener('click', () => {
        regexInput.value = btn.dataset.pattern;
        testRegex();
    });
});

// Sample data for demo
const sampleData = {
    pattern: '\\d{3}-\\d{3}-\\d{4}',
    text: 'Contact us at 123-456-7890 or 987-654-3210 for more information.\nYou can also reach us at 555-123-4567.'
};

// Load sample data after a short delay
setTimeout(() => {
    regexInput.value = sampleData.pattern;
    testText.value = sampleData.text;
    testRegex();
}, 500);
