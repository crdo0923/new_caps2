// Minimal client-side helper for the Gemini assistant UI on dashboard.php
document.addEventListener('DOMContentLoaded', function() {
    const toggle = document.getElementById('geminiToggle');
    const panel = document.getElementById('geminiPanel');
    const close = document.getElementById('geminiClose');
    const messagesEl = document.getElementById('geminiMessages');
    const input = document.getElementById('geminiInput');
    const sendBtn = document.getElementById('geminiSend');
    const badge = document.getElementById('geminiBadge');

    function appendMsg(from, text) {
        const el = document.createElement('div');
        el.className = 'gemini-msg ' + (from === 'user' ? 'user' : 'ai');
        el.textContent = text;
        if (!messagesEl) return; // guard if panel DOM is absent
        messagesEl.appendChild(el);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function setLoading(yes) {
        sendBtn.disabled = !!yes;
        if (yes) sendBtn.textContent = 'Thinking…';
        else sendBtn.textContent = 'Send';
    }

            if (toggle) {
        toggle.addEventListener('click', function() {
            if (!panel.classList.contains('show')) {
                panel.classList.add('show');
                badge && (badge.style.display = 'none');
                // load FAQ into panel (best-effort)
                // Parse server response safely (server may return HTML if there's a PHP warning)
                fetch('ajax_gemini.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ action: 'faq_list', context: 'dashboard' }) })
                    .then(async (r) => {
                        const txt = await r.text();
                        try { return JSON.parse(txt); } catch(e) { console.warn('ajax_gemini non-JSON response', txt); return null; }
                    }).then(j => {
                        if (j && j.success && Array.isArray(j.faq)) {
                            const list = document.createElement('div'); list.className = 'gemini-faq-list';
                            j.faq.forEach(entry => {
                                const it = document.createElement('div'); it.className = 'gemini-faq-item';
                                it.innerHTML = `<div><div class="faq-question">${entry.question}</div></div><div class="faq-hint">Ask</div>`;
                                it.addEventListener('click', function() {
                                    // send the FAQ id to the server (click analytics) then display answer
                                    fetch('ajax_gemini.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ action: 'faq_click', id: entry.id, context: 'dashboard' }) }).catch(()=>{});
                                    messagesEl.innerHTML = '';
                                    appendMsg('ai', entry.answer || '');
                                });
                                list.appendChild(it);
                            });
                            const body = document.getElementById('geminiBody');
                            if (body) {
                                const existing = body.querySelector('.gemini-faq-list');
                                if (existing) existing.remove();
                                body.insertBefore(list, body.querySelector('.gemini-empty'));
                            }
                        }
                    }).catch(()=>{});
            } else {
                panel.classList.remove('show');
            }
        });
    }

    if (close) close.addEventListener('click', () => panel.classList.remove('show'));

    if (sendBtn) sendBtn.addEventListener('click', () => {
        const text = (input && input.value || '').trim();
        if (!text) return;
        appendMsg('user', text);
        input.value = '';
        setLoading(true);

        fetch('ajax_gemini.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ action: 'chat', prompt: text }) })
            .then(async (r) => { const txt = await r.text(); try { return JSON.parse(txt); } catch(e) { console.warn('ajax_gemini non-JSON response', txt); return null; } })
            .then(j => {
                setLoading(false);
                if (j && j.success && j.text) appendMsg('ai', j.text);
                else if (j && j.error) appendMsg('ai', 'Assistant error: ' + j.error);
                else appendMsg('ai', 'No response.');
            }).catch(e => {
                setLoading(false);
                appendMsg('ai', 'Request failed.');
            });
    });
});
// js/smart_study_ai.js

// This script uses a server-side proxy (ajax_gemini.php) so API keys are not exposed in client JS.
// Set GENERATIVE_API_KEY as an environment variable or in includes/ai_config.php on the server.

// ------------------------------
// PART X: INLINE AI CHAT (text-bison-001 default)
// A lightweight in-page assistant so users don't need to switch tabs
// ------------------------------

// Model endpoint label (server will decide mode — set to 'faq' when AI disabled)
const GEMINI_CHAT_MODEL = 'faq';
// PRE-RENDERED mode flag (set at runtime depending on whether the current user is an admin)
let GEMINI_PRE_RENDERED = true; // default; will be overwritten on DOMContentLoaded
let GEMINI_ADMIN = false;
let GEMINI_CONTEXT = '';

// Small helper to append messages to the floating assistant
function appendGeminiMessage(role, text) {
    const container = document.getElementById('geminiMessages');
    const empty = document.getElementById('geminiEmpty');
    if (!container) return;
    if (empty) empty.style.display = 'none';
    const msg = document.createElement('div');
    msg.className = 'gemini-msg ' + (role === 'user' ? 'user' : 'ai');
    msg.innerHTML = '<div>' + text.replace(/\n/g, '<br>') + '</div>';
    container.appendChild(msg);
    // persist tiny conversation log to localStorage (keeps latest ~50 messages)
    try {
        if (!GEMINI_PRE_RENDERED) {
            if (!window.geminiConversation) window.geminiConversation = [];
            window.geminiConversation.push({ role, text, ts: Date.now() });
            // Keep the conversation array bounded to prevent unbounded memory growth
            try { if (window.geminiConversation.length > 200) window.geminiConversation.splice(0, window.geminiConversation.length - 200); } catch (e) {}
            if (window.geminiConversation.length > 50) window.geminiConversation.shift();
            localStorage.setItem('gemini_conv', JSON.stringify(window.geminiConversation));
        }
    } catch (e) { /* ignore storage errors */ }
    // keep scroll bottom
    container.scrollTop = container.scrollHeight;
}

async function callGeminiAPI(promptText) {
    // Forward to our server-side proxy so we don't leak keys
    try {
        const res = await fetch('ajax_gemini.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'chat', prompt: promptText, model: GEMINI_CHAT_MODEL })
        });
        // Read as text first to avoid JSON.parse errors when server returns HTML or warnings
        const raw = await res.text();
        let json;
        try {
            json = JSON.parse(raw);
        } catch (parseErr) {
            const trimmed = (raw || '').trim();
            if (trimmed.startsWith('<') || trimmed.toLowerCase().indexOf('no_api_key') !== -1 || res.status >= 500) {
                // indicate non-json/html/server failures clearly
                throw new Error('non-json-html');
            }
            throw new Error('api_response_parse_error');
        }
        if (!json || !json.success) throw new Error(json?.error || 'api_error');
        return json.text || '';
    } catch (e) {
        console.error('callGeminiAPI failed', e);
        throw e;
    }
}

// UI wiring
document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('geminiToggle');
    const panel = document.getElementById('geminiPanel');
    const closeBtn = document.getElementById('geminiClose');
    const header = panel.querySelector('.gemini-header');
    const sendBtn = document.getElementById('geminiSend');
    const input = document.getElementById('geminiInput');
    const badge = document.getElementById('geminiBadge');

    // Establish runtime flags from page-provided globals (set in server-rendered pages)
    GEMINI_ADMIN = (typeof window.GEMINI_ADMIN !== 'undefined' && window.GEMINI_ADMIN === true);
    GEMINI_CONTEXT = (typeof window.GEMINI_CONTEXT !== 'undefined') ? (window.GEMINI_CONTEXT || '') : '';
    // admin users get interactive chat; regular users see pre-rendered read-only assistant
    GEMINI_PRE_RENDERED = !GEMINI_ADMIN;

    const footer = panel.querySelector('.gemini-footer');
    if (!toggle || !panel || !closeBtn || !sendBtn || !input || !footer) return;

    // If admin, inject a small Stats button into header so admins can view FAQ-click analytics
    if (GEMINI_ADMIN && header) {
        try {
            const statsBtn = document.createElement('button');
            statsBtn.id = 'geminiAdminStatsBtn';
            statsBtn.className = 'gemini-admin-btn';
            statsBtn.style = 'margin-left:8px; background:transparent; color:#9aa7c7; border:1px solid rgba(255,255,255,0.03); padding:6px 8px; border-radius:8px; cursor:pointer; font-weight:600;';
            statsBtn.innerText = 'Stats';
            header.insertBefore(statsBtn, closeBtn);
            statsBtn.addEventListener('click', async () => {
                const m = document.getElementById('geminiMessages');
                appendGeminiMessage('user', 'Show FAQ click stats');
                appendGeminiMessage('ai', 'Loading stats...');
                try {
                    const r = await fetch('ajax_gemini.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'faq_stats' }) });
                    const j = await r.json();
                    if (!j || !j.success) {
                        appendGeminiMessage('ai', 'Unable to fetch stats.');
                        return;
                    }
                    // render a short report
                    const stats = j.stats ?? {};
                    const counts = stats.counts ?? {};
                    let out = 'FAQ click counts:\n';
                    const rows = [];
                    for (const id in counts) rows.push({ id, count: counts[id].count ?? counts[id] });
                    rows.sort((a,b) => (b.count||0) - (a.count||0));
                    if (rows.length === 0) out += 'No clicks recorded yet.';
                    else for (const r2 of rows) out += `${r2.id}: ${r2.count}\n`;
                    appendGeminiMessage('ai', out);
                } catch (e) {
                    appendGeminiMessage('ai', 'Failed to load stats. See console.');
                    console.error('stats fetch failed', e);
                }
            });
        } catch (e) { /* ignore */ }
    }

    let _geminiFaqLoaded = false;

    // pre-hide footer immediately for pre-rendered mode to avoid flashing the input
    try { if (GEMINI_PRE_RENDERED && footer) footer.style.display = 'none'; } catch(e) {}

    async function loadAndRenderPreRenderedFAQ(limit = 12) {
        // Render a clickable list of FAQ questions. Answers are only shown after the user clicks.
        if (_geminiFaqLoaded) return;
        const container = document.getElementById('geminiMessages');
        if (!container) return;
        // clear existing messages and replace with a question list
        container.innerHTML = '';
        try {
            const r = await fetch('ajax_gemini.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'faq_list', context: GEMINI_CONTEXT }) });
            const json = await r.json();
            if (!json || !json.success || !Array.isArray(json.faq)) return;
            const items = json.faq.slice(0, limit);

            const listEl = document.createElement('div'); listEl.className = 'gemini-faq-list';

            for (const it of items) {
                const q = it.question || '(No question)';
                const a = it.answer || '(No answer)';
                const id = it.id || null;

                const item = document.createElement('div');
                item.className = 'gemini-faq-item';
                item.tabIndex = 0; // make focusable
                item.setAttribute('role', 'button');
                item.setAttribute('aria-pressed', 'false');

                const qEl = document.createElement('div'); qEl.className = 'faq-question'; qEl.innerText = q;
                const hint = document.createElement('div'); hint.className = 'faq-hint'; hint.innerText = 'Show answer';

                item.appendChild(qEl); item.appendChild(hint);

                // click/keyboard handler: dropdown-style toggle show/hide of the answer
                const showAnswer = () => {
                    // use a toggled expanded state so clicking again hides the answer (dropdown behavior)
                    const expanded = item.classList.contains('expanded');
                    const storedId = item.dataset.answerId || null;

                    // collapse if already expanded
                    if (expanded && storedId) {
                        const existing = document.getElementById(storedId);
                        if (existing && existing.parentElement) existing.parentElement.removeChild(existing);
                        item.classList.remove('expanded');
                        item.setAttribute('aria-pressed', 'false');
                        hint.innerText = 'Show answer';
                        return;
                    }

                    // expand: ensure only one item is expanded at a time
                    const allExpanded = listEl.querySelectorAll('.gemini-faq-item.expanded');
                    for (const other of allExpanded) {
                        if (other === item) continue;
                        const otherId = other.dataset.answerId;
                        if (otherId) {
                            const existingOther = document.getElementById(otherId);
                            if (existingOther && existingOther.parentElement) existingOther.parentElement.removeChild(existingOther);
                        }
                        other.classList.remove('expanded');
                        other.setAttribute('aria-pressed', 'false');
                        const otherHint = other.querySelector('.faq-hint'); if (otherHint) otherHint.innerText = 'Show answer';
                    }

                    // expand: create a unique id for the answer block and insert below the clicked question
                    const answerId = id ? ('gemini-faq-answer-' + id) : ('gemini-faq-answer-' + (Date.now() + Math.floor(Math.random() * 9999)));
                    // avoid duplicating if we somehow already created one
                    if (document.getElementById(answerId)) return;
                    item.classList.add('answered', 'expanded');
                    item.setAttribute('aria-pressed', 'true');
                    item.dataset.answerId = answerId;

                    const aiMsg = document.createElement('div'); aiMsg.className = 'gemini-msg ai gemini-faq-answer'; aiMsg.id = answerId; aiMsg.innerHTML = '<div>' + a.replace(/\n/g, '<br>') + '</div>';
                    // insert the answer directly after the clicked question item (so answer appears below that question)
                    try {
                        if (item.parentNode) item.parentNode.insertBefore(aiMsg, item.nextSibling);
                        else container.appendChild(aiMsg);
                    } catch (e) { container.appendChild(aiMsg); }

                    // analytics: notify server that user clicked this FAQ (only when expanding)
                    if (id) {
                        try {
                            fetch('ajax_gemini.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'faq_click', id: id, context: (GEMINI_CONTEXT || '') }) });
                        } catch (e) { /* ignore */ }
                    }

                    // update hint to reflect visible state and scroll only to the newly inserted answer
                    hint.innerText = 'Hide answer';
                    try {
                        // smoothly bring the answer into view but don't jump to the very bottom
                        aiMsg.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    } catch (e) {
                        // fallback for older browsers: only adjust container minimally
                        container.scrollTop = Math.min(container.scrollHeight, (aiMsg.offsetTop || 0));
                    }
                };

                item.addEventListener('click', showAnswer);
                item.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); showAnswer(); } });

                listEl.appendChild(item);
            }

            container.appendChild(listEl);
            _geminiFaqLoaded = true;
        } catch (e) {
            console.warn('Failed to load pre-rendered FAQ', e);
            container.innerHTML = '<div class="gemini-msg ai">Unable to load help right now.</div>';
        }
    }

    function openPanel() {
        panel.classList.add('show');
        // Make the panel focusable / interactive for assistive tech
        try { panel.removeAttribute('inert'); panel.setAttribute('aria-hidden', 'false'); } catch(e) {}
        input.focus();
        // Pre-rendered / read-only assistant: hide typing controls and load canned messages
        if (GEMINI_PRE_RENDERED) {
            try {
                // fully hide the footer so no freeform prompt is visible
                footer.style.display = 'none';
            } catch (e) { /* ignore UI errors */ }
            // populate messages once
            loadAndRenderPreRenderedFAQ();
        }
    }
    function closePanel() {
        panel.classList.remove('show');
        // Use inert so we don't hide elements that may be focused elsewhere in the DOM
        try { panel.setAttribute('inert', ''); panel.setAttribute('aria-hidden', 'true'); } catch(e) {}
        // show notifications badge when closed if there are messages
        const messages = document.getElementById('geminiMessages');
        if (messages && messages.children.length > 0) badge.style.display = 'inline-block';
    }

    toggle.addEventListener('click', () => {
        // toggle panel
        if (panel.classList.contains('show')) closePanel(); else openPanel();
    });
    closeBtn.addEventListener('click', closePanel);

    // send handler
    async function doSend() {
        // In pre-rendered mode we disallow user sending/typing — this is a read-only assistant.
        if (GEMINI_PRE_RENDERED) return;
        const text = input.value.trim();
        if (!text) return;
        // append user
        appendGeminiMessage('user', text);
        // disable send while pending to avoid duplicates
        sendBtn.disabled = true;
        input.value = '';
        // show ephemeral loading
        const loading = document.createElement('div'); loading.className = 'gemini-msg ai'; loading.id = 'geminiLoading'; loading.innerHTML = '<div><span class="gemini-loading">Thinking…</span></div>';
        const messages = document.getElementById('geminiMessages');
        messages.appendChild(loading);
        messages.scrollTop = messages.scrollHeight;

        try {
            // craft a safety-aware minimal system prompt to help with study tasks
            const prompt = `You are SmartStudy's helpful assistant focused on study planning, summarization and explanations. Be concise, friendly, and provide actionable study tips when helpful. User: ${text}`;
            const aiText = await callGeminiAPI(prompt);
            // remove loader and append reply
            const loader = document.getElementById('geminiLoading'); if (loader) loader.remove();
            if (!aiText || aiText.trim().length === 0) appendGeminiMessage('ai', 'Sorry — no response from model. Try again.');
            else appendGeminiMessage('ai', aiText);
            // hide badge while panel open
            badge.style.display = 'none';
        } catch (err) {
            const loader = document.getElementById('geminiLoading'); if (loader) loader.remove();
            console.error('Gemini chat error', err);
            const msg = (err && err.message) ? `AI request failed: ${err.message}` : 'AI request failed. Check console or try later.';
            appendGeminiMessage('ai', msg);
            // show small notification to user
            if (typeof window.showToast === 'function') window.showToast('AI Error', err?.message || 'The assistant could not answer — try again later.', '⚠️');
            badge.style.display = 'inline-block';
        } finally {
            // re-enable send regardless
            sendBtn.disabled = false;
        }
    }

    sendBtn.addEventListener('click', doSend);
    input.addEventListener('keydown', (e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); doSend(); } });

    // restore saved conversation (if any) — skipped when pre-rendered mode is enabled
    try {
        if (!GEMINI_PRE_RENDERED) {
            const saved = localStorage.getItem('gemini_conv');
            if (saved) {
                window.geminiConversation = JSON.parse(saved) || [];
                for (const m of window.geminiConversation) appendGeminiMessage(m.role, m.text);
                // hide badge if panel open
                if (panel.classList.contains('show')) badge.style.display = 'none';
            }
        }
    } catch (e) { console.warn('Failed to restore Gemini conversation', e); }

    // (removed temporary AI Debug button wiring)

});

// Safety-binding: ensure FAQ toggle always works even if earlier bindings fail
(function ensureGeminiToggleBinding(){
    try {
        const toggle = document.getElementById('geminiToggle');
        const panel = document.getElementById('geminiPanel');
        const footer = panel ? panel.querySelector('.gemini-footer') : null;
        if (!toggle || !panel) return;
        toggle.addEventListener('click', function tryToggle(){
            // Toggle CSS class and styles conservatively
            if (panel.classList.contains('show')) {
                panel.classList.remove('show'); panel.style.display = 'none';
                try { panel.setAttribute('inert', ''); panel.setAttribute('aria-hidden', 'true'); } catch(e) {}
            } else {
                panel.classList.add('show'); panel.style.display = 'flex';
                try { panel.removeAttribute('inert'); panel.setAttribute('aria-hidden', 'false'); } catch(e) {}
                if (footer && footer.style) footer.style.display = footer.style.display || 'none';
            }
        });
    } catch(e) { console.warn('ensureGeminiToggleBinding failed', e); }
})();

// ==========================================
// PART 1: AI SMART SCHEDULING
// ==========================================

// Lightweight client-side subject detector (keyword mapping)
function detectSubjectFromText(text) {
    if (!text || typeof text !== 'string') return 'General';
    const t = text.toLowerCase();
    const map = [
        { keywords: ['sql', 'database', 'mysql', 'postgres', 'query', 'schema'], subject: 'Databases' },
        { keywords: ['react', 'vue', 'angular', 'frontend', 'html', 'css', 'javascript'], subject: 'Frontend' },
        { keywords: ['node', 'express', 'php', 'backend', 'server', 'api'], subject: 'Backend' },
        { keywords: ['web', 'website', 'web dev', 'html', 'css', 'http'], subject: 'Web Development' },
        { keywords: ['algorithm', 'sorting', 'graph', 'dfs', 'bfs', 'complexity'], subject: 'Algorithms' },
        { keywords: ['data structure', 'stack', 'queue', 'tree', 'linked list'], subject: 'Data Structures' },
        { keywords: ['machine learning', 'ml', 'neural', 'model', 'training', 'ai', 'deep learning'], subject: 'AI/ML' },
        { keywords: ['calculus', 'algebra', 'math', 'statistics', 'probability'], subject: 'Mathematics' },
        { keywords: ['physics', 'quantum', 'mechanics'], subject: 'Physics' },
        { keywords: ['biology', 'anatomy', 'genetics'], subject: 'Biology' },
        { keywords: ['chemistry', 'organic', 'inorganic'], subject: 'Chemistry' },
        { keywords: ['devops', 'docker', 'kubernetes', 'ci/cd', 'server'], subject: 'DevOps' },
        { keywords: ['language', 'english', 'grammar', 'vocab', 'writing'], subject: 'Languages' },
        { keywords: ['history', 'world war', 'revolution', 'timeline'], subject: 'History' }
    ];

    for (const m of map) {
        for (const kw of m.keywords) if (t.includes(kw)) return m.subject;
    }
    // fallback to some simple patterns
    if (t.match(/(review|final|exam|study)/)) return 'General';
    return 'Other';
}

// expose
window.detectSubjectFromText = detectSubjectFromText;

// AI fallback detection using Gemini — returns short subject string
async function aiDetectSubject(text) {
    if (!text || !text.trim()) return 'General';
    const prompt = `You are a helpful assistant. Given this study task title or short description, return a single concise subject label that best fits it (examples: "Databases", "Algorithms", "AI/ML", "Frontend"). Task: "${text}"\nReturn only the subject name.`;
    try {
        // Call server-side detection proxy
        const rep = await fetch('ajax_gemini.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'detect_subject', text: text }) });
        const data = await rep.json();
        const txt = (data?.subject || '').trim();
        // heuristics: remove surrounding quotes, keep first line
        let candidate = txt.split(/\n/)[0].replace(/^"|"$/g, '').trim();
        if (!candidate) return 'Other';
        // sanitize length
        if (candidate.length > 60) candidate = candidate.substring(0, 60);
        return candidate;
    } catch (e) {
        console.error('AI detect error', e);
        return 'Other';
    }
}
window.aiDetectSubject = aiDetectSubject;

function closeAIConfirmModal() {
    const modal = document.getElementById('aiConfirmModal');
    if (!modal) return;
    // remove save handler and hide
    const saveBtn = document.getElementById('aiConfirmSaveBtn');
    if (saveBtn) saveBtn.onclick = null;
        modal.classList.remove('show');
        modal.style.display = 'none';
}

function openSubjectEditModal(taskId, currentSubject) {
    console.log('openSubjectEditModal called', taskId, currentSubject);
    const modal = document.getElementById('subjectEditModal');
    const sel = document.getElementById('subjectEditSelect');
    const saveBtn = document.getElementById('subjectEditSaveBtn');
    if (!modal || !sel || !saveBtn) return;
    // populate options from main select
    const main = document.getElementById('taskSubject');
    sel.innerHTML = '';
    if (main) {
        for (const opt of main.options) {
            if (opt.value === 'Auto Detect') continue;
            const o = document.createElement('option'); o.value = opt.value; o.text = opt.text; sel.appendChild(o);
        }
    }
    let found = Array.from(sel.options).some(o => o.value.toLowerCase() === (currentSubject || '').toLowerCase());
    if (!found && currentSubject) { const o = document.createElement('option'); o.value = currentSubject; o.text = currentSubject; sel.appendChild(o); }
    sel.value = currentSubject || (sel.options[0]?.value ?? 'General');
    modal.classList.add('show');
    modal.style.display = 'flex';

    // set save handler
    saveBtn.onclick = function() {
        const newSub = sel.value;
        console.log('subjectEdit save clicked for', taskId, newSub);
        const fd = new FormData(); fd.append('action', 'change_subject'); fd.append('task_id', taskId); fd.append('subject', newSub);
        fetch('ajax_task_action.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                console.log('subject edit response', res);
                if (res && res.status === 'success') {
                    // update DOM: subject badge
                    const taskEl = document.getElementById('task-' + taskId);
                    if (taskEl) {
                        const badge = taskEl.querySelector('.subject-badge');
                        if (badge) badge.innerText = res.subject;
                        else {
                            const h = taskEl.querySelector('.schedule-header-row h4');
                            if (h) {
                                const sp = document.createElement('span'); sp.className = 'subject-badge'; sp.innerText = res.subject; h.appendChild(sp);
                            }
                        }
                    }
                    if (typeof window.showToast === 'function') window.showToast('Saved', 'Subject updated', '✅');
                    closeSubjectEditModal();
                } else {
                    if (typeof window.showToast === 'function') window.showToast('Error', res?.message || 'Could not save subject', '⚠️');
                }
            }).catch(err => { console.error(err); if (typeof window.showToast === 'function') window.showToast('Error', 'Server error', '⚠️'); });
    };
}

function closeSubjectEditModal() {
    const modal = document.getElementById('subjectEditModal');
    if (!modal) return; modal.classList.remove('show'); modal.style.display = 'none';
    document.getElementById('subjectEditSaveBtn').onclick = null;
}

// Inject edit-subject buttons into task items (for items rendered server-side or via ajax)
function injectSubjectEditButtons() {
    // debug
    // console.log('injectSubjectEditButtons running');
    document.querySelectorAll('.schedule-item').forEach(item => {
        if (!item.id) return;
        const id = item.id.replace('task-', '');
        // if already injected, skip
        if (item.querySelector('.subject-edit-inserted')) return;
        const actions = item.querySelector('.task-actions');
        if (!actions) return;
        const btn = document.createElement('button');
        btn.className = 'subject-edit-inserted';
        btn.title = 'Edit Subject';
        btn.style = "background: rgba(255,255,255,0.03); border: 1px solid rgba(51,65,85,1); color: #a6b1d6; width: 120px; height: 40px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap:6px; margin-left:4px;";
        btn.innerHTML = "<i class='bx bx-pencil' style='font-size: 1.0rem;'></i> <span style='font-size:0.85rem;'>Subject</span>";
        btn.addEventListener('click', () => {
            const cur = item.querySelector('.subject-badge')?.innerText || 'General';
            openSubjectEditModal(id, cur);
        });
        // Place before the delete button if present, else append
        const deleteBtn = actions.querySelector("[onclick*='confirmDeleteTask']");
        if (deleteBtn) actions.insertBefore(btn, deleteBtn);
        else actions.appendChild(btn);
    });
}

// Move a task element to the top of the schedule list so active task is always visible first
function bringTaskToTop(taskId) {
    if (!taskId) return;
    const list = document.getElementById('aiScheduleList');
    if (!list) return;
    const el = document.getElementById('task-' + taskId);
    if (!el) return;
    // If already first child, nothing to do
    if (list.firstElementChild === el) return;
    // Animate and prepend
    try {
        el.style.transition = 'transform 0.18s ease, box-shadow 0.18s ease';
        el.style.transform = 'translateY(-6px)';
        el.style.boxShadow = '0 8px 20px rgba(0,0,0,0.25)';
        // ensure it exists in the DOM and prepend
        list.insertBefore(el, list.firstElementChild);
        // restore after short animation
        setTimeout(() => {
            el.style.transform = '';
            el.style.boxShadow = '';
        }, 250);
    } catch (e) { console.error('bringTaskToTop error', e); }
}

// run injection on load and after content updates
document.addEventListener('DOMContentLoaded', () => {
    injectSubjectEditButtons();
    // Also observe the schedule-list container to re-inject when new items are inserted
    const scheduleListLocal = document.getElementById('aiScheduleList');
    if (scheduleListLocal) {
        const obs = new MutationObserver((m) => injectSubjectEditButtons());
        obs.observe(scheduleListLocal, { childList: true, subtree: true });
    }
});

// Debounced background detection helper (non-blocking suggestion)
function debounce(fn, wait) {
    let t = null;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), wait); };
}

// show subject suggestion (non-blocking)
function showSubjectSuggestion(subject) {
    const root = document.getElementById('subjectSuggestion');
    const txt = document.getElementById('subjectSuggestionText');
    const btn = document.getElementById('applySuggestionBtn');
    if (!root || !txt) return;
    txt.innerText = subject;
    root.style.display = 'block';
    if (btn) btn.onclick = () => {
        // apply suggestion: set taskSubject select (if available)
        const main = document.getElementById('taskSubject');
        if (!main) return;
        // try to match option case-insensitively
        let matched = false;
        for (const opt of main.options) {
            if (opt.value.toLowerCase() === subject.toLowerCase()) { main.value = opt.value; matched = true; break; }
        }
        // if no match, add a new option and select it
        if (!matched) { const o = document.createElement('option'); o.value = subject; o.text = subject; main.appendChild(o); main.value = subject; }
        root.style.display = 'none';
    };
}

// background detector that first uses the heuristic then AI-fallback if ambiguous
const backgroundDetect = debounce(async (text) => {
    if (!text || text.trim().length === 0) { document.getElementById('subjectSuggestion')?.style && (document.getElementById('subjectSuggestion').style.display = 'none'); return; }
    try {
        let s = detectSubjectFromText(text);
        if (s === 'Other' || s === 'General') {
            if (typeof aiDetectSubject === 'function') {
                const ai = await aiDetectSubject(text);
                if (ai && ai.length > 0) s = ai;
            }
        }
        showSubjectSuggestion(s);
    } catch (e) { console.error('backgroundDetect failed', e); }
}, 600);

// wire up suggestion while typing on quick-add input (if exists)
document.addEventListener('DOMContentLoaded', () => {
    const el = document.getElementById('taskInput');
    if (el) {
        el.addEventListener('input', (e) => backgroundDetect(e.target.value));
        el.addEventListener('blur', () => setTimeout(() => { document.getElementById('subjectSuggestion')?.style && (document.getElementById('subjectSuggestion').style.display = 'none'); }, 700));
    }
});

async function generateSchedule() {
    const taskInput = document.getElementById('taskInput');
    const task = taskInput.value.trim();
    let subject = document.getElementById('taskSubject').value;
    // if user chose auto-detect, determine subject from the task text
    if (subject === 'Auto Detect') {
        try {
            subject = detectSubjectFromText(task);
            // if client heuristics are ambiguous, try AI fallback
            if ((subject === 'Other' || subject === 'General') && typeof window.aiDetectSubject === 'function') {
                const aiNote = document.getElementById('aiLoading');
                if (aiNote) aiNote.style.display = 'flex';
                try {
                    const aiSub = await aiDetectSubject(task);
                    if (aiSub && aiSub.length > 0) subject = aiSub;
                } catch (err) { console.error('ai detect fallback failed', err); }
                if (aiNote) aiNote.style.display = 'none';
            }
        } catch(e) { subject = 'General'; }
    }
    const priority = document.getElementById('taskPriority').value;
    const listContainer = document.getElementById('aiScheduleList');
    const loader = document.getElementById('aiLoading');
    const generateBtn = document.querySelector('.btn-add');
    const insightBox = document.getElementById('aiInsightBox');

    if (!task) { alert("Please enter a task first!"); return; }

    if (generateBtn) generateBtn.disabled = true;
    if (loader) loader.style.display = 'flex';

    if (listContainer && listContainer.innerText.includes('No tasks')) listContainer.innerHTML = '';

    const prompt = `Act as a smart study planner. Task: "${task}", Subject: "${subject}", Priority: ${priority}. 
    Break into 1 sub-task. Estimate time (e.g. "25 mins"). Provide motivation tip.
    JSON ONLY: { "subtasks": [ {"time": "Now", "duration": "25 mins", "title": "Title", "desc": "Desc"} ], "tip": "Tip" }`;

    try {
        // Request server-side plan generation
        const rep = await fetch('ajax_gemini.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'plan', task: task, subject: subject, priority: priority }) });
        // Always read as text first so we can gracefully handle HTML responses and other non-JSON errors
        const rawText = await rep.text();
        let data = null;
        try {
            data = JSON.parse(rawText);
        } catch (jsonErr) {
            // Detect an HTML error page (starts with '<') or other unexpected response
            const rawLower = (rawText || '').trim().toLowerCase();
            // treat common server-side/AI failure indicators as token/quota issues
            const serverProblemIndicators = ['no_api_key', 'no_api', 'no_tokens', 'quota', 'limit', 'rate_limited', '429', 'request_failed', 'no_json_in_response', 'no_text_extracted'];
            const looksLikeHtml = rawText && rawText.trim().startsWith('<');
            const mentionsIndicator = serverProblemIndicators.some(s => rawLower.indexOf(s) !== -1);
            if (looksLikeHtml || mentionsIndicator || rep.status >= 500) {
                // Throw an Error that will be caught by the outer try/catch and trigger the fallback
                throw new Error('AI response not usable: ' + (looksLikeHtml ? 'non-json-html' : rawText.substring(0, 200)));
            }
            // If not identifiable as a server/AI token problem, surface parse error so developer can inspect rawText
            throw new Error('AI returned invalid JSON: ' + (rawText ? rawText.substring(0,200) : 'empty'));
        }
        if (!data || data.success !== true) {
            // Show helpful message to user when ai generation fails
            const errMsg = (data && data.error) ? data.error : 'AI generation failed';
            throw new Error(errMsg);
        }

        // `data.json` may already be an object (if server returned parsed JSON) or a string.
        let schedule = null;
        if (!data.json) throw new Error('No JSON plan returned');
        if (typeof data.json === 'string') {
            // remove any code fences and parse
            let aiText = data.json.replace(/```json/g, '').replace(/```/g, '').trim();
            try { schedule = JSON.parse(aiText); } catch (e) {
                // If parsing failed, attempt to parse by finding the first {...}
                const m = aiText.match(/\{[\s\S]*\}/);
                if (m) schedule = JSON.parse(m[0]);
                else throw e;
            }
        } else if (typeof data.json === 'object') {
            schedule = data.json;
        } else {
            throw new Error('Unexpected plan format');
        }

        if (schedule.subtasks.length > 0) {
            const item = schedule.subtasks[0];
            // Show confirmation modal so user can tune title/desc/subject/priority
            const modal = document.getElementById('aiConfirmModal');
            const tTitle = document.getElementById('aiConfirmTitle');
            const tDesc = document.getElementById('aiConfirmDesc');
            const tSub = document.getElementById('aiConfirmSubject');
            const tPrio = document.getElementById('aiConfirmPriority');
            if (modal && tTitle && tDesc && tSub && tPrio) {
                tTitle.value = item.title || '';
                tDesc.value = item.desc || '';
                // fill subject options using main select
                const mainSelect = document.getElementById('taskSubject');
                tSub.innerHTML = '';
                if (mainSelect) {
                    for (const opt of mainSelect.options) {
                        if (opt.value === 'Auto Detect') continue;
                        const o = document.createElement('option'); o.value = opt.value; o.text = opt.text; tSub.appendChild(o);
                    }
                }
                // ensure detected subject is present
                let found = Array.from(tSub.options).some(o => o.value.toLowerCase() === subject.toLowerCase());
                if (!found) { const o = document.createElement('option'); o.value = subject; o.text = subject; tSub.appendChild(o); }
                tSub.value = subject;
                tPrio.value = priority;

                // show modal
                modal.classList.add('show');
                modal.style.display = 'flex';

                const saveBtn = document.getElementById('aiConfirmSaveBtn');
                const closeModal = () => closeAIConfirmModal();
                const doConfirm = () => {
                    const finalTitle = tTitle.value.trim() || item.title;
                    const finalDesc = tDesc.value.trim() || item.desc || '';
                    const finalSub = tSub.value || subject;
                    const finalPrio = tPrio.value || priority;
                    // Save and insert into DOM in realtime when done
                    saveTaskToDB(finalTitle, finalDesc, finalSub, finalPrio, item.time, item.duration)
                        .then(() => { if (typeof window.showToast === 'function') window.showToast('Saved', 'Task added to schedule', '✅'); })
                        .catch(() => {});
                    closeModal();
                };
                // bind handlers
                saveBtn.onclick = doConfirm;
                // close button is already wired in HTML
            } else {
                // Save using AJAX and allow saveTaskToDB to inject new item into the UI in realtime
                saveTaskToDB(item.title, item.desc, subject, priority, item.time, item.duration)
                    .then(() => { if (typeof window.showToast === 'function') window.showToast('Saved', 'Task added to schedule', '✅'); })
                    .catch(() => {});
            }
        }
        if(insightBox && schedule.tip) insightBox.innerHTML = `<p><strong><i class='bx bx-bot'></i> AI Insight:</strong> ${schedule.tip}</p>`;
        // show detected subject when using Auto Detect
        if (document.getElementById('aiInsightBox') && document.getElementById('taskSubject').value === 'Auto Detect') {
            const detEl = document.createElement('div'); detEl.style.fontSize = '0.9rem'; detEl.style.marginTop = '6px'; detEl.style.color = '#a6b1d6'; detEl.innerText = `Detected subject: ${subject}`; insightBox.appendChild(detEl);
        }

    } catch (error) {
        console.error("AI Error:", error);
        // If AI failed due to no API key / tokens / rate-limit etc. fallback to a simple non-AI task create
        const msg = String(error?.message || error || '').toLowerCase();
        const tokenProblems = ['no_api_key', 'no_api', 'no_tokens', 'quota', 'limit', 'rate_limited', '429', 'request_failed', 'no_json_in_response', 'no_text_extracted'];

        // also treat non-JSON/html errors and generic parse errors as conditions to fallback
        const htmlOrParseIndicators = ['non-json-html', 'invalid json', 'unexpected token', '<'];

        const shouldFallback = tokenProblems.some(p => msg.indexOf(p) !== -1) || htmlOrParseIndicators.some(p => msg.indexOf(p) !== -1);
        if (shouldFallback) {
            // Create a simple task (no AI) so the user doesn't lose the input
            if (typeof window.showToast === 'function') window.showToast('AI Unavailable', 'No tokens or quota left — your task will be saved without AI suggestions.', '⚠️');
            // Use simple defaults for time/duration so UI remains consistent
            const fallbackTime = 'ASAP';
            const fallbackDuration = '25 mins';
            try {
                saveTaskToDB(task, '', subject, priority, fallbackTime, fallbackDuration);
            } catch (e) { console.error('Fallback save failed', e); }
            // clear inputs conservatively and don't try to show the AI modal
            if (taskInput) taskInput.value = '';
            if (loader) loader.style.display = 'none';
            if (generateBtn) generateBtn.disabled = false;
            return;
        }

        // show feedback to user for other AI errors
        if (typeof window.showToast === 'function') window.showToast('AI Error', String(error.message || error), '⚠️');
    } finally {
        if (loader) loader.style.display = 'none';
        if (generateBtn) generateBtn.disabled = false;
        taskInput.value = ''; 
    }
}

function saveTaskToDB(title, desc, subject, priority, time, duration) {
    const formData = new FormData();
    formData.append('title', title); formData.append('description', desc);
    formData.append('subject', subject); formData.append('priority', priority);
    formData.append('time', time); formData.append('duration', duration);

    // Return a promise so callers can await result if needed
    return fetch('ajax_save_task.php', { method: 'POST', body: formData })
        .then(async (res) => {
            const txt = await res.text();
            try { return JSON.parse(txt); } catch (e) { console.warn('ajax_save_task returned non-JSON:', txt); return { status: 'error', message: 'Server returned non-JSON response', _raw: txt }; }
        })
        .then(data => {
            if (data.status === 'success') {
                // Insert new task into schedule list in realtime (if present)
                try {
                    const list = document.getElementById('aiScheduleList');
                    if (list) {
                        const tid = data.task_id || ('new-' + Date.now());
                        const container = document.createElement('div');
                        container.className = 'schedule-item ' + (priority === 'High' ? 'priority-urgent' : (priority === 'Medium' ? 'priority-medium' : 'priority-low'));
                        container.id = 'task-' + tid;

                        // Construct inner HTML safely by creating elements instead of dangerous string building
                        const indicator = document.createElement('div'); indicator.className = 'schedule-indicator ' + (priority === 'High' ? 'urgent' : (priority === 'Medium' ? 'high' : 'medium'));
                        const bodyWrapper = document.createElement('div'); bodyWrapper.style.flexGrow = '1'; bodyWrapper.style.paddingRight = '15px';

                        const headerRow = document.createElement('div'); headerRow.className = 'schedule-header-row'; headerRow.style.display = 'flex'; headerRow.style.alignItems = 'center'; headerRow.style.gap = '10px'; headerRow.style.marginBottom = '5px';
                        const h4 = document.createElement('h4'); h4.style.margin = '0'; h4.style.color = 'var(--text-light)'; h4.style.fontSize = '1.1rem';
                        const titleNode = document.createTextNode(title + ' ');
                        const subjectSpan = document.createElement('span'); subjectSpan.className = 'subject-badge'; subjectSpan.innerText = subject;
                        h4.appendChild(titleNode); h4.appendChild(subjectSpan);
                        const prioSpan = document.createElement('span'); prioSpan.className = 'priority-badge ' + (priority === 'High' ? 'urgent' : (priority === 'Medium' ? 'high' : 'medium')); prioSpan.style.fontSize = '0.75rem'; prioSpan.style.padding = '3px 10px'; prioSpan.style.borderRadius = '4px'; prioSpan.style.background = 'rgba(255,255,255,0.08)'; prioSpan.innerText = (priority || 'Medium').toUpperCase();
                        headerRow.appendChild(h4); headerRow.appendChild(prioSpan);

                        const pdesc = document.createElement('p'); pdesc.className = 'schedule-desc'; pdesc.style.margin = '0'; pdesc.style.color = 'var(--text-gray)'; pdesc.style.fontSize = '0.9rem'; pdesc.innerText = desc || '';
                        const timeBlock = document.createElement('div'); timeBlock.className = 'schedule-time-block'; timeBlock.style.marginTop = '8px'; timeBlock.style.fontSize = '0.85rem'; timeBlock.style.color = '#6366f1'; timeBlock.style.display = 'flex'; timeBlock.style.alignItems = 'center'; timeBlock.style.gap = '5px';
                        timeBlock.innerHTML = "<i class='bx bx-time' style='font-size:1.1rem'></i> " + (duration || '') + " (" + (time || '') + ")";

                        bodyWrapper.appendChild(headerRow);
                        bodyWrapper.appendChild(pdesc);
                        bodyWrapper.appendChild(timeBlock);

                        const actions = document.createElement('div'); actions.className = 'task-actions'; actions.style.display = 'flex'; actions.style.alignItems = 'center'; actions.style.gap = '10px';

                        // Start focus button
                        const startBtn = document.createElement('button'); startBtn.title = 'Start Focus'; startBtn.style.cssText = "background:#6366f1;color:white;border:none;padding:10px 20px;border-radius:10px;cursor:pointer;font-weight:600;display:flex;align-items:center;gap:6px;";
                        startBtn.innerHTML = "<i class='bx bx-play' style='font-size:1.4rem;'></i>";
                        startBtn.addEventListener('click', () => startFocusMode(duration || null, tid, title || 'New Task'));

                        // Edit button
                        const editBtn = document.createElement('button'); editBtn.title = 'Edit'; editBtn.style.cssText = "background:rgba(255,255,255,0.05);border:1px solid #334155;color:#94a3b8;width:45px;height:45px;border-radius:10px;cursor:pointer;display:flex;align-items:center;justify-content:center;";
                        editBtn.innerHTML = "<i class='bx bx-edit-alt' style='font-size:1.4rem;'></i>";
                        editBtn.addEventListener('click', () => openEditTaskModal(tid, title.replace(/"/g, '&quot;'), (desc||'').replace(/"/g, '&quot;')));

                        // Delete
                        const delBtn = document.createElement('button'); delBtn.title = 'Delete'; delBtn.style.cssText = "background:rgba(239,68,68,0.1);border:1px solid #ef4444;color:#ef4444;width:45px;height:45px;border-radius:10px;cursor:pointer;display:flex;align-items:center;justify-content:center;";
                        delBtn.innerHTML = "<i class='bx bx-trash' style='font-size:1.4rem;'></i>";
                        delBtn.addEventListener('click', () => confirmDeleteTask(tid));

                        actions.appendChild(startBtn); actions.appendChild(editBtn); actions.appendChild(delBtn);

                        container.appendChild(indicator); container.appendChild(bodyWrapper); container.appendChild(actions);

                        // Prepend to list
                        list.insertAdjacentElement('afterbegin', container);

                        // Ensure subject edit button injection runs
                        injectSubjectEditButtons();
                    }
                } catch (insertErr) { console.error('Failed to insert schedule item into DOM', insertErr); }

                if (typeof window.showToast === 'function') window.showToast('Saved', 'Task saved to your schedule ✅', '✅');
            } else {
                if (typeof window.showToast === 'function') window.showToast('Error', data.message || 'Failed to save task', '⚠️');
                console.error('Save task response error:', data);
            }

            return data;
        })
        .catch(err => {
            console.error('saveTaskToDB error:', err);
            if (typeof window.showToast === 'function') window.showToast('Error', 'Could not reach server', '⚠️');
            throw err;
        });
}

// --- TASK ACTIONS ---
function confirmDeleteTask(taskId) {
    if (typeof window.showCustomConfirm === 'function') {
        window.showCustomConfirm('Delete Task?', 'This action cannot be undone.', () => deleteTask(taskId));
    } else if (typeof window.askConfirm === 'function') {
        // Ask using global async wrapper
        window.askConfirm('Delete Task?', 'This action cannot be undone.').then(yes => { if (yes) deleteTask(taskId); });
    } else {
        // fallback to native confirm to prevent accidental deletes
        if (confirm('Delete this task? This action cannot be undone.')) deleteTask(taskId);
    }
}

function deleteTask(taskId) {
    const formData = new FormData();
    formData.append('action', 'delete'); formData.append('task_id', taskId);
    fetch('ajax_task_action.php', { method: 'POST', body: formData })
    .then(r => r.text())
    .then(res => {
        if(res.trim() === 'success') {
            const el = document.getElementById('task-' + taskId);
            if(el) { el.style.opacity = '0'; setTimeout(() => el.remove(), 300); }
        }
    });
}

// --- EDIT TASK MODAL LOGIC ---
function openEditTaskModal(id, title, desc) {
    const modal = document.getElementById('editTaskModal');
    // Decode HTML entities if needed (simple replace for now)
    const cleanTitle = title.replace(/&quot;/g, '"').replace(/&#039;/g, "'");
    const cleanDesc = desc.replace(/&quot;/g, '"').replace(/&#039;/g, "'");
    
    document.getElementById('editTaskId').value = id;
    document.getElementById('editTaskTitle').value = cleanTitle;
    document.getElementById('editTaskDesc').value = cleanDesc;
    if(modal) modal.style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editTaskModal').style.display = 'none';
}

function saveEditedTask() {
    const id = document.getElementById('editTaskId').value;
    const title = document.getElementById('editTaskTitle').value;
    const desc = document.getElementById('editTaskDesc').value;
    // ask for confirmation before saving edits
    const confirmAndSave = () => {
        const formData = new FormData();
        formData.append('action', 'edit'); formData.append('task_id', id);
        formData.append('title', title); formData.append('description', desc);

        fetch('ajax_task_action.php', { method: 'POST', body: formData })
        .then(r => r.text())
        .then(res => {
            if(res.trim() === 'success') location.reload();
            else alert("Update failed.");
        });
    };

    // Prefer site-wide confirm UI if available
    if (typeof window.showCustomConfirm === 'function') {
        window.showCustomConfirm('Save Changes?', 'Save your edits to this task?', confirmAndSave);
        return;
    }
    if (typeof window.askConfirm === 'function') {
        window.askConfirm('Save Changes?', 'Save your edits to this task?').then(yes => { if (yes) confirmAndSave(); });
        return;
    }
    // Last-resort fallback (native) — proceed to save if user accepts
    if (!confirm('Save your edits?')) return;
    // (actual work moved to guarded confirmAndSave above)
}

// ==========================================
// PART 2: FOCUS MODE (FIXED RESUME & CONFIRM)
// ==========================================
let ssaiTimerInterval = null;
let timeLeft = 1500; 
let isFocusing = false;
let currentActiveTaskId = null;
let sessionStartTime = null; // timestamp when the current focus session started (ms)
let focusedElapsed = 0; // total seconds focused in the current session (persists across pauses)
// Admin experimental controls
let SS_ADMIN_ALLOW_BACKGROUND = false;
let SS_TIMER_SPEED = 1.0;

// 1. LOAD STATE
document.addEventListener('DOMContentLoaded', () => {
    const savedState = localStorage.getItem('smartStudyTimer');
    if (savedState) {
        const state = JSON.parse(savedState);
        timeLeft = parseInt(state.timeLeft);
        currentActiveTaskId = state.activeTaskId;
        if (state.startTimestamp) sessionStartTime = parseInt(state.startTimestamp);
        if (state.focusedElapsed) focusedElapsed = parseInt(state.focusedElapsed);
        
        const taskLabel = document.getElementById('activeTaskLabel');
        if (taskLabel && state.taskTitle) taskLabel.innerText = "Working on: " + state.taskTitle;
        
        // Restore Highlight
        if (currentActiveTaskId) {
            // Use timeout to ensure DOM is ready
                setTimeout(() => {
                    const activeItem = document.getElementById('task-' + currentActiveTaskId);
                    if (activeItem) {
                        activeItem.classList.add('active-focus');
                        bringTaskToTop(currentActiveTaskId);
                    }
                }, 500);
        }
        
        updateTimerDisplay();

        
        
        // If paused, show resume state
        if (timeLeft > 0 && timeLeft < (25*60)) {
             const msgEl = document.getElementById('focusMessage');
             if(msgEl) {
                 msgEl.innerText = "⚠️ Session Paused. Resume when ready.";
                 msgEl.style.color = "#f59e0b";
             }
             const startBtn = document.getElementById('startFocusBtn');
             if(startBtn) startBtn.innerHTML = "<i class='bx bx-play'></i> Resume";
        }
    }
});

// 2. START LOGIC (WITH CONFIRMATION)
function startFocusMode(aiDuration = null, taskId = null, taskTitle = null) {
    // A. CHECK IF RESUMING
    // If no params are passed, it means the big Start/Resume button was clicked directly
    const isResuming = (!aiDuration && !taskId);
    const startBtn = document.getElementById('startFocusBtn');
    
    // If clicking resume button, just continue
    if (isResuming && startBtn.innerText.includes('Resume')) {
        executeStart(null, null, null, true); // true = isResuming
        return;
    }

    // B. CHECK FOR CONFLICT (New Task while Active/Paused)
    // If timer has progress (timeLeft < max) AND we are starting a NEW task (taskId is present)
    // OR if timer is currently running (isFocusing)
    if ((isFocusing || timeLeft < getFullDuration()) && taskId && taskId != currentActiveTaskId) {
        
        if (typeof window.showCustomConfirm === 'function') {
            window.showCustomConfirm(
                "Switch Task?",
                "Starting a new task will reset your current timer progress. Continue?",
                function() {
                    stopFocusMode(false); // Reset current
                    executeStart(aiDuration, taskId, taskTitle, false); // Start new
                }
            );
        } else if (typeof window.askConfirm === 'function') {
            window.askConfirm('Switch Task?', 'Starting a new task will reset your current timer progress. Continue?').then(yes => {
                if (yes) { stopFocusMode(false); executeStart(aiDuration, taskId, taskTitle, false); }
            });
        } else {
            // fallback to askConfirm (promise) or native confirm to avoid unintentional resets
            if (typeof window.askConfirm === 'function') {
                window.askConfirm('Switch Task?', 'Starting a new task will reset your current timer progress. Continue?').then(yes => {
                    if (yes) { stopFocusMode(false); executeStart(aiDuration, taskId, taskTitle, false); }
                });
            } else {
                if (confirm('Starting a new task will reset your current timer progress. Continue?')) {
                    stopFocusMode(false);
                    executeStart(aiDuration, taskId, taskTitle, false);
                }
            }
        }
        return; // Stop execution, wait for user choice
    }

    // C. NORMAL START (Fresh start or Manual Start)
    executeStart(aiDuration, taskId, taskTitle, false);
}

function getFullDuration() {
    // Estimate based on radio. If using AI time, this check might be approximate but safe enough for UI logic.
    const selectedRadio = document.querySelector('input[name="study-time"]:checked');
    return (selectedRadio ? parseInt(selectedRadio.value) : 25) * 60;
}

function executeStart(aiDuration, taskId, taskTitle, isResuming) {
    // hide any outstanding focus warnings when starting/resuming
    try { document.getElementById('focusWarning')?.classList.remove('show'); } catch(e) {}
    if (!isResuming) {
        // SETUP NEW TIME
        let durationMins = 25;

        if (aiDuration) {
            const numbers = aiDuration.match(/\d+/);
            if (numbers) {
                durationMins = parseInt(numbers[0]);
                if (aiDuration.toLowerCase().includes('hour') || aiDuration.toLowerCase().includes('hr')) durationMins *= 60;
            }
            currentActiveTaskId = taskId;
        } else {
            const selectedRadio = document.querySelector('input[name="study-time"]:checked');
            if (selectedRadio) durationMins = parseInt(selectedRadio.value);
        }

        timeLeft = durationMins * 60;
        // record start time for this session and reset elapsed counter
        sessionStartTime = Date.now();
        focusedElapsed = 0;
        
        // Update UI Labels
        const taskLabel = document.getElementById('activeTaskLabel');
        if (taskLabel) {
            if (taskTitle) taskLabel.innerText = "Working on: " + taskTitle;
            else taskLabel.innerText = "Free Study Session";
        }

        // Highlight
        document.querySelectorAll('.schedule-item').forEach(el => el.classList.remove('active-focus'));
        if (taskId) {
            const activeItem = document.getElementById('task-' + taskId);
            if (activeItem) {
                activeItem.classList.add('active-focus');
                bringTaskToTop(taskId);
            }
        }
    }

    // RUN TIMER
    isFocusing = true;
    document.getElementById('startFocusBtn').style.display = 'none';
    document.getElementById('stopFocusBtn').style.display = 'inline-flex';
    document.getElementById('focusStatus').style.display = 'block';
    
    const msgEl = document.getElementById('focusMessage');
    if(msgEl) {
        if (typeof window.GEMINI_ADMIN !== 'undefined' && window.GEMINI_ADMIN === true && SS_ADMIN_ALLOW_BACKGROUND) {
            msgEl.innerText = `⚠️ Admin experimental: timer running in background at ${SS_TIMER_SPEED}x speed.`;
            msgEl.style.color = "#f59e0b";
        } else {
            msgEl.innerText = "⚠️ FOCUS MODE ON! Do not switch tabs.";
            msgEl.style.color = "#ef4444";
        }
    }
    
    const elem = document.documentElement;
    if (elem.requestFullscreen) elem.requestFullscreen().catch(err => console.log(err));

    updateTimerDisplay();
    if(ssaiTimerInterval) clearInterval(ssaiTimerInterval);
    ssaiTimerInterval = setInterval(updateTimer, 1000);
    
    // If resuming, ensure we have a fresh session start timestamp
    if (isResuming) sessionStartTime = Date.now();
    // Save state immediately
    saveTimerState();
}

// SPA teardown for SmartStudy AI: clear interval and conversation history
window.spaTeardown = function() {
    try { if (typeof ssaiTimerInterval !== 'undefined' && ssaiTimerInterval) { clearInterval(ssaiTimerInterval); ssaiTimerInterval = null; } } catch(e) {}
    try { if (window.geminiConversation) { window.geminiConversation = []; } } catch(e) {}
};

function stopFocusMode(completed = false) {
    clearInterval(ssaiTimerInterval);
    isFocusing = false;
    
        // prefer tracked focused elapsed seconds (accurate across pauses/resumes)
        let timeSpentSeconds = Math.round(Math.max(0, focusedElapsed || 0));

    if (completed) {
        localStorage.removeItem('smartStudyTimer');
        if(currentActiveTaskId) {
            const el = document.getElementById('task-' + currentActiveTaskId);
            if(el) el.classList.remove('active-focus');
        }
    } else {
        saveTimerState(); // Save paused state
        // clear current session start; resume will set it again
        sessionStartTime = null;
    }

    const startBtn = document.getElementById('startFocusBtn');
    if(startBtn) {
        startBtn.style.display = 'inline-flex';
        startBtn.innerHTML = "<i class='bx bx-play'></i> Resume"; // Button becomes Resume
    }

    document.getElementById('stopFocusBtn').style.display = 'none';
    document.getElementById('focusStatus').style.display = 'none';
    
    const msgEl = document.getElementById('focusMessage');
    if(msgEl) {
        msgEl.innerText = completed ? "Session Completed! Points Added." : "Session Paused.";
        msgEl.style.color = completed ? "#10b981" : "#f59e0b";
    }
    
    if (completed) {
        // Reset if done
        if(startBtn) startBtn.innerHTML = "<i class='bx bx-play'></i> Start Focus";
        if (document.exitFullscreen && document.fullscreenElement) document.exitFullscreen();
        document.getElementById('activeTaskLabel').innerText = "Select a task to start";
        
        // Send completion with duration so server can calculate points
        if(currentActiveTaskId) {
            const fd = new FormData(); fd.append('action', 'complete'); fd.append('task_id', currentActiveTaskId);
            fd.append('duration_spent', timeSpentSeconds);
            fetch('ajax_task_action.php', {method:'POST', body:fd})
                .then(r => r.text())
                .then(text => {
                    // server returns JSON for complete action — try parse
                    try { return JSON.parse(text); } catch(e) { return null; }
                })
                .then(res => {
                    if (res && res.status === 'success') {
                        document.getElementById('task-'+currentActiveTaskId)?.remove();
                        if (res.awarded_points && typeof window.showToast === 'function') window.showToast('Great job!', `+${res.awarded_points} points awarded`, '🏆');
                        if (res.total_points) {
                            const pd = document.getElementById('userPointsDisplay'); if (pd) pd.innerText = parseInt(res.total_points).toLocaleString();
                        }
                    } else {
                        // fallback: try removing the task in DOM in case server didn't send JSON
                        document.getElementById('task-'+currentActiveTaskId)?.remove();
                    }
                }).catch(err => { console.error('Complete task error', err); document.getElementById('task-'+currentActiveTaskId)?.remove(); });
            currentActiveTaskId = null;
            focusedElapsed = 0;
            sessionStartTime = null;
        }
        playAlarm();
    } else {
        if (document.exitFullscreen && document.fullscreenElement) document.exitFullscreen();
    }
}

function updateTimer() {
    if (timeLeft > 0) {
        // decrement according to time speed (supports fractional steps)
        timeLeft = Math.max(0, timeLeft - SS_TIMER_SPEED);
        // accumulate focused time at same rate
        focusedElapsed += SS_TIMER_SPEED;
        updateTimerDisplay();
        saveTimerState();
    } else {
        stopFocusMode(true); 
    }
}

function saveTimerState() {
    const labelEl = document.getElementById('activeTaskLabel');
    const taskLabel = labelEl ? labelEl.innerText.replace("Working on: ", "") : "";
    const state = {
        timeLeft: Math.ceil(timeLeft),
        activeTaskId: currentActiveTaskId,
        taskTitle: taskLabel,
        startTimestamp: sessionStartTime,
        focusedElapsed: Math.round(focusedElapsed),
        timestamp: Date.now()
    };
    localStorage.setItem('smartStudyTimer', JSON.stringify(state));
}

function updateTimerDisplay() {
    const minutes = Math.floor(timeLeft / 60);
    const seconds = Math.floor(timeLeft % 60);
    document.getElementById('timerMinutes').innerText = minutes < 10 ? '0' + minutes : minutes;
    document.getElementById('timerSeconds').innerText = seconds < 10 ? '0' + seconds : seconds;
    
    const maxTime = 60*60; // Approx visual
    const percentage = timeLeft / maxTime; 
    const dashOffset = 534 - (534 * (1-percentage)); 
    const progressEl = document.getElementById('timerProgress');
    if(progressEl) progressEl.style.strokeDashoffset = dashOffset;
}

function playAlarm() {
    const audio = new Audio('/new_caps/assets/sounds/alarm.wav'); 
    audio.play().catch(() => {});
    if ('vibrate' in navigator) navigator.vibrate([500, 200, 500]);
    alert("⏰ TIME'S UP! Great work.");
}

// GAMIFICATION
function addPoints(action, duration = 0) {
    const formData = new URLSearchParams();
    formData.append('action', action);
    if (duration > 0) formData.append('duration', duration);

    fetch('messaging/ajax_gamification.php', {
        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            const pointsDisplay = document.getElementById('userPointsDisplay');
            if (pointsDisplay) pointsDisplay.innerText = parseInt(data.total_points).toLocaleString();
            if (data.new_achievements && data.new_achievements.length > 0) {
                data.new_achievements.forEach(ach => {
                   if(typeof window.showToast === 'function') window.showToast('🏆 UNLOCKED!', ach.name, '🏆');
                });
            }
        }
    });
}

// FILTER
function filterTasks() {
    const filterVal = document.getElementById('taskFilterSelect').value;
    const listContainer = document.getElementById('aiScheduleList');
    listContainer.style.opacity = '0.5';
    const formData = new FormData(); formData.append('filter', filterVal);

    fetch('ajax_fetch_tasks.php', { method: 'POST', body: formData })
    .then(r => r.text())
    .then(html => {
        listContainer.innerHTML = html;
        listContainer.style.opacity = '1';
           if(currentActiveTaskId) {
               const el = document.getElementById('task-' + currentActiveTaskId);
               if(el) { el.classList.add('active-focus'); bringTaskToTop(currentActiveTaskId); }
        }
    });
}

// Toggle View All
function toggleAllTasks() {
    const hiddenTasks = document.querySelectorAll('.hidden-task');
    const btn = document.getElementById('viewAllTasksBtn');
    const isExpanding = btn.innerHTML.includes('View All');

    hiddenTasks.forEach(task => {
        task.style.display = isExpanding ? 'flex' : 'none';
        if(isExpanding) task.style.animation = 'slideIn 0.3s ease';
    });

    if (isExpanding) {
        btn.innerHTML = "Show Less <i class='bx bx-chevron-up'></i>";
        btn.style.borderColor = "var(--primary-color)";
        btn.style.color = "var(--primary-color)";
    } else {
        btn.innerHTML = "View All <i class='bx bx-chevron-down'></i>";
        btn.style.borderColor = "#334155";
        btn.style.color = "var(--text-gray)";
    }
}

function showFocusWarning(message, autoHide = 6000) {
    const el = document.getElementById('focusWarning');
    if (!el) return;
    try {
        el.querySelector('.focus-warning-text h4').innerText = 'Focus Interrupted';
        el.querySelector('.focus-warning-text p').innerText = message;
    } catch (e) { /* ignore */ }
    el.classList.add('show');
    // allow manual dismiss
    const dismiss = document.getElementById('dismissFocusWarning');
    if (dismiss) dismiss.onclick = () => el.classList.remove('show');
    // auto hide
    clearTimeout(el._hideTimeout);
    if (autoHide) el._hideTimeout = setTimeout(() => el.classList.remove('show'), autoHide);
}

// Pause when user changes tabs
document.addEventListener("visibilitychange", function() {
    if (document.hidden && isFocusing) {
        // Admin experimental: allow continuing in background when enabled
        if (typeof window.GEMINI_ADMIN !== 'undefined' && window.GEMINI_ADMIN === true && SS_ADMIN_ALLOW_BACKGROUND) {
            // Keep running; show a transient info message
            showFocusWarning('Admin experimental: session continues while tab is hidden', 2500);
        } else {
            stopFocusMode(false);
            showFocusWarning('You switched tabs — your focus session was paused. Return and resume when ready.');
        }
    } else {
        document.title = "Dashboard - SmartStudy";
    }
});

// Pause when user exits fullscreen
document.addEventListener('fullscreenchange', () => {
    if (!document.fullscreenElement && isFocusing) {
        if (typeof window.GEMINI_ADMIN !== 'undefined' && window.GEMINI_ADMIN === true && SS_ADMIN_ALLOW_BACKGROUND) {
            showFocusWarning('Admin experimental: session continues even after leaving fullscreen', 2500);
        } else {
            stopFocusMode(false);
            showFocusWarning('You exited full-screen — your focus session was paused to protect your progress.');
        }
    }
});

// Admin controls init (ensure these are wired even when timer state isn't present)
document.addEventListener('DOMContentLoaded', () => {
    try {
        const isAdmin = (typeof window.GEMINI_ADMIN !== 'undefined' && window.GEMINI_ADMIN === true);
        if (!isAdmin) return;
        const allowEl = document.getElementById('adminAllowBackground');
        const speedEl = document.getElementById('adminTimerSpeed');
        if (allowEl) {
            SS_ADMIN_ALLOW_BACKGROUND = allowEl.checked = (localStorage.getItem('ss_admin_allow_bg') === '1');
            allowEl.addEventListener('change', () => {
                SS_ADMIN_ALLOW_BACKGROUND = allowEl.checked;
                localStorage.setItem('ss_admin_allow_bg', SS_ADMIN_ALLOW_BACKGROUND ? '1' : '0');
                const msg = document.getElementById('focusMessage');
                if (msg) msg.innerText = SS_ADMIN_ALLOW_BACKGROUND ? '⚠️ Admin experimental: timer will continue when switching tabs.' : '⚠️ FOCUS MODE ON! Do not switch tabs.';
            });
        }
        if (speedEl) {
            SS_TIMER_SPEED = parseFloat(localStorage.getItem('ss_timer_speed') || '1');
            speedEl.value = SS_TIMER_SPEED;
            speedEl.addEventListener('change', () => {
                SS_TIMER_SPEED = parseFloat(speedEl.value) || 1.0;
                localStorage.setItem('ss_timer_speed', String(SS_TIMER_SPEED));
                const msg = document.getElementById('focusMessage');
                if (msg) msg.innerText = `⚠️ Admin experimental: timer speed ${SS_TIMER_SPEED}x`;
            });
        }
    } catch(e) { /* ignore */ }
});

// INIT CHART
setTimeout(() => {
    const ctx = document.getElementById('weeklyChart');
    if (ctx && typeof Chart !== 'undefined') {
        if (window.myChart instanceof Chart) window.myChart.destroy();
        window.myChart = new Chart(ctx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'Remaining'],
                datasets: [{ data: [0, 20], backgroundColor: ['#6366f1', '#334155'], borderWidth: 0 }]
            },
            options: { cutout: '75%', responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
        });
    }
}, 500);