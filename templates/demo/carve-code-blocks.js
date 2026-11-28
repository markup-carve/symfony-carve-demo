// Code block enhancements for Carve output; carve-css styles the markup.
//
// enhanceCodeBlocks(root, highlight) runs once per page:
// - Highlighting: `highlight(code)` is called for each `pre > code.language-*`.
//   Code callout markers (`b.callout`, carve.codeCallouts) would be lost to a
//   highlighter that re-reads the text, so they are taken out first and put
//   back at the end of their lines.
// - Code line options (carve#6791): each line of a `pre.line-numbers` or
//   `pre[data-highlight]` becomes `span.line[data-line]`, selected lines get
//   `.highlighted`, and token spans that cross a line end are reopened. Blocks
//   a server highlighter already numbered (`.hl-gutter`) are left alone.
// - A copy button in the top-right corner of the code, shown on hover and
//   focus; the copy leaves out every gutter number. A block with a fence title
//   gets a header bar with the title and the language; a block without one
//   shows the language in that corner, where it gives way to the button.

const RANGES = /^[1-9]\d*(?:-[1-9]\d*)?(?:,[1-9]\d*(?:-[1-9]\d*)?)*$/;
const COPY_ICON = '<svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M5 1.5A1.5 1.5 0 0 1 6.5 0h6A1.5 1.5 0 0 1 14 1.5v9a1.5 1.5 0 0 1-1.5 1.5h-6A1.5 1.5 0 0 1 5 10.5zm1.5 0v9h6v-9zM2 5.5A1.5 1.5 0 0 1 3.5 4H4v1.5h-.5v9h6V14H11v.5A1.5 1.5 0 0 1 9.5 16h-6A1.5 1.5 0 0 1 2 14.5z"/></svg>';
const DONE_ICON = '<svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M13.8 3.2a1 1 0 0 1 0 1.4l-7 7a1 1 0 0 1-1.4 0l-3.2-3.2a1 1 0 1 1 1.4-1.4L6.1 9.5l6.3-6.3a1 1 0 0 1 1.4 0z"/></svg>';

function selectedLines(value, count) {
    const selected = new Set();
    if (!value || !RANGES.test(value)) {
        return selected;
    }
    for (const item of value.split(',')) {
        const [first, last = first] = item.split('-').map(Number);
        if (first > last) {
            return new Set();
        }
        for (let line = first; line <= Math.min(last, count); line++) {
            selected.add(line);
        }
    }
    return selected;
}

function splitLines(html) {
    const lines = [];
    const open = [];
    let current = '';
    for (const token of html.split(/(<span\b[^>]*>|<\/span>|\n)/)) {
        if (token === '') {
            continue;
        }
        if (token === '\n') {
            lines.push(current + '</span>'.repeat(open.length));
            current = open.join('');
        } else if (token.startsWith('<span')) {
            open.push(token);
            current += token;
        } else if (token === '</span>') {
            open.pop();
            current += token;
        } else {
            current += token;
        }
    }
    // A trailing line break ends the last line; it opens no new one.
    if (!html.endsWith('\n')) {
        lines.push(current);
    }
    return lines;
}

const CALLOUTS = /(?:\s*<b class="callout"[^>]*>[^<]*<\/b>)+$/;

function highlightKeepingCallouts(code, highlight) {
    if (!code.querySelector('b.callout')) {
        highlight(code);
        return;
    }
    const markers = [];
    const html = code.innerHTML;
    const lines = splitLines(html).map((line, index) => {
        const match = line.match(CALLOUTS);
        markers[index] = match ? match[0] : '';
        return match ? line.slice(0, -match[0].length) : line;
    });
    code.innerHTML = lines.join('\n') + (html.endsWith('\n') ? '\n' : '');
    highlight(code);
    const highlighted = code.innerHTML;
    code.innerHTML = splitLines(highlighted).map((line, index) => line + (markers[index] ?? '')).join('\n')
        + (highlighted.endsWith('\n') ? '\n' : '');
}

function applyCodeLines(root) {
    root.querySelectorAll('pre.line-numbers, pre[data-highlight]').forEach((pre) => {
        const code = pre.querySelector(':scope > code') ?? pre;
        if (code.dataset.carveLines === 'done' || code.querySelector('.hl-gutter, .hl-line-highlighted')) {
            return;
        }
        const startValue = pre.dataset.lineStart ?? '';
        const start = /^[1-9]\d*$/.test(startValue) ? Number(startValue) : 1;
        const lines = splitLines(code.innerHTML);
        const selected = selectedLines(pre.dataset.highlight, lines.length);
        code.innerHTML = lines.map((html, index) => {
            const marked = selected.has(index + 1) ? ' highlighted' : '';
            return `<span class="line${marked}" data-line="${start + index}">${html}</span>`;
        }).join('\n');
        code.dataset.carveLines = 'done';
        pre.style.setProperty('--carve-code-gutter-width', `${String(start + lines.length - 1).length}ch`);
    });
}

function codeText(code) {
    const copy = code.cloneNode(true);
    copy.querySelectorAll('.hl-gutter').forEach((gutter) => gutter.remove());
    return copy.textContent.replace(/\n$/, '');
}

async function writeClipboard(text) {
    if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(text);
        return;
    }
    const area = document.createElement('textarea');
    area.value = text;
    area.setAttribute('readonly', '');
    area.style.position = 'fixed';
    area.style.opacity = '0';
    document.body.append(area);
    area.select();
    document.execCommand('copy');
    area.remove();
}

function addCodeHeaders(root) {
    root.querySelectorAll('pre > code[class*="language-"]').forEach((code) => {
        const pre = code.parentElement;
        if (pre.parentElement?.classList.contains('code-block')) {
            return;
        }
        const language = [...code.classList].find((name) => name.startsWith('language-'))?.slice(9) ?? '';
        const title = pre.getAttribute('title') ?? '';

        const block = document.createElement('div');
        block.className = 'code-block';
        const body = document.createElement('div');
        body.className = 'code-body';
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'code-copy';
        const icon = document.createElement('span');
        icon.className = 'code-copy-icon';
        icon.innerHTML = COPY_ICON;
        const label = document.createElement('span');
        label.className = 'code-copy-text';
        label.setAttribute('aria-live', 'polite');
        label.textContent = 'Copy code';
        button.append(icon, label);
        let reset;
        button.addEventListener('click', async () => {
            clearTimeout(reset);
            try {
                await writeClipboard(codeText(code));
                button.classList.add('is-copied');
                icon.innerHTML = DONE_ICON;
                label.textContent = 'Copied';
            } catch {
                label.textContent = 'Copy failed';
            }
            reset = setTimeout(() => {
                button.classList.remove('is-copied');
                icon.innerHTML = COPY_ICON;
                label.textContent = 'Copy code';
            }, 2000);
        });
        const lang = document.createElement('span');
        lang.className = 'code-lang';
        lang.textContent = language;
        pre.before(block);
        if (title) {
            const header = document.createElement('div');
            header.className = 'code-header';
            const name = document.createElement('span');
            name.className = 'code-title';
            name.textContent = title;
            header.append(name);
            if (language) {
                header.append(lang);
            }
            block.append(header);
        } else if (language && !pre.closest('.code-group-panel')) {
            // A code group's tab already names the panel.
            body.append(lang);
        }
        body.append(button, pre);
        block.append(body);
    });
}

export function enhanceCodeBlocks(root = document, highlight = null) {
    if (root === null) {
        return;
    }
    if (highlight !== null) {
        root.querySelectorAll('pre > code[class*="language-"]').forEach((code) => highlightKeepingCallouts(code, highlight));
    }
    applyCodeLines(root);
    addCodeHeaders(root);
}
