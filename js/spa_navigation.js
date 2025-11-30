// ========================================================
// SPA NAVIGATION - Optimized for Memory Efficiency
// ========================================================

// Global registry of all intervals - for centralized cleanup
if (!window._spaIntervals) window._spaIntervals = [];

// Helper to register intervals so they can be cleaned up
window.registerInterval = function(intervalId) {
    if (intervalId) window._spaIntervals.push(intervalId);
    return intervalId;
};

// Master cleanup function
window.spaTeardown = function() {
    // Clear all registered intervals
    window._spaIntervals.forEach(id => {
        try { clearInterval(id); } catch(e) {}
    });
    window._spaIntervals = [];
    
    // Reset initialization flags
    window._dashboardNotificationsInitialized = false;
    window._spaScriptsInitialized = false;
    
    // Clear any dashboard-specific intervals
    if (window.dashboardNotificationsInterval) {
        clearInterval(window.dashboardNotificationsInterval);
        window.dashboardNotificationsInterval = null;
    }
    if (window.dashboardBadgeInterval) {
        clearInterval(window.dashboardBadgeInterval);
        window.dashboardBadgeInterval = null;
    }
    if (window.timerInterval) {
        clearInterval(window.timerInterval);
        window.timerInterval = null;
    }
};

document.addEventListener('DOMContentLoaded', () => {
    // Prevent duplicate initialization
    if (window._spaNavigationInitialized) return;
    window._spaNavigationInitialized = true;
    
    // 1. INTERCEPT LINKS
    document.body.addEventListener('click', (e) => {
        const link = e.target.closest('a');
        
        if (link && 
            link.href && 
            link.origin === window.location.origin && 
            !link.hasAttribute('download') && 
            link.target !== '_blank' &&
            !link.getAttribute('href').startsWith('#') &&
            !link.getAttribute('href').includes('logout')
        ) {
            e.preventDefault();
            loadPage(link.href);
        }
    });

    // 2. HANDLE BROWSER BACK/FORWARD BUTTONS
    window.addEventListener('popstate', () => {
        loadPage(window.location.href, false);
    });
});

// 3. MAIN PAGE LOADER FUNCTION
async function loadPage(url, pushState = true) {
    try {
        const mainContent = document.querySelector('main.main-content');
        if(mainContent) mainContent.style.opacity = '0.5';

        const response = await fetch(url);
        const html = await response.text();

        const parser = new DOMParser();
        const newDoc = parser.parseFromString(html, 'text/html');

        // Copy body classes
        try {
            document.body.className = newDoc.body.className || document.body.className;
            Array.from(newDoc.body.attributes || []).forEach(attr => {
                if (attr.name && attr.name.startsWith('data-')) document.body.setAttribute(attr.name, attr.value);
            });
        } catch (e) {}

        // Sync stylesheets
        const existingLinks = new Set(Array.from(document.head.querySelectorAll('link[rel="stylesheet"]')).map(l => l.href));
        const newHeadLinks = Array.from(newDoc.head.querySelectorAll('link[rel="stylesheet"]'));
        const loadPromises = [];
        
        newHeadLinks.forEach(link => {
            try {
                const href = link.href;
                if (!href || existingLinks.has(href)) return;
                const el = document.createElement('link');
                el.rel = 'stylesheet'; el.href = href; el.media = link.media || 'all';
                document.head.appendChild(el);
                loadPromises.push(new Promise(res => { el.onload = () => res(true); el.onerror = () => res(false); }));
            } catch (e) {}
        });

        // Wait for styles (max 500ms)
        await Promise.race([Promise.all(loadPromises), new Promise(res => setTimeout(res, 500))]);

        // CLEANUP BEFORE SWAP
        try { window.spaTeardown(); } catch (err) { console.warn('SPA teardown error', err); }

        // SWAP CONTENT
        const newMain = newDoc.querySelector('main.main-content');
        const currentMain = document.querySelector('main.main-content');

        if (newMain && currentMain) {
            currentMain.innerHTML = newMain.innerHTML;
            currentMain.className = newMain.className;
            currentMain.style.opacity = '1';
        }

        // UPDATE URL & TITLE
        if (pushState) window.history.pushState({}, '', url);
        document.title = newDoc.title;

        // RE-INITIALIZE (minimal - only essential scripts)
        reinitializeScripts(newDoc);
        updateSidebarActiveState(url);

    } catch (err) {
        console.error("Navigation Error:", err);
        window.location.href = url;
    }
}

// 4. SCRIPT RE-INITIALIZER (Minimal - prevent memory leaks)
function reinitializeScripts(newDoc) {
    // Get new page's inline scripts only (skip external ones - they're already loaded)
    const newScripts = newDoc.querySelectorAll('script:not([src])');
    
    // Track what we've already executed to prevent duplicates
    const executedScripts = new Set();
    
    newScripts.forEach(oldScript => {
        const content = oldScript.innerHTML.trim();
        
        // Skip empty or already executed scripts
        if (!content || executedScripts.has(content)) return;
        
        // Skip scripts that set up intervals (let the main files handle this)
        if (content.includes('setInterval') && !content.includes('clearInterval')) {
            console.log('Skipping interval script to prevent memory leak');
            return;
        }
        
        executedScripts.add(content);
        
        try {
            // Use Function instead of eval for cleaner scope
            const fn = new Function(content);
            fn();
        } catch (e) {
            console.warn('Script execution error:', e);
        }
    });

    // Trigger dashboard-specific initializers if they exist
    if (typeof initScheduleGenerator === 'function') {
        try { initScheduleGenerator(); } catch(e) {}
    }
}

// 5. UPDATE SIDEBAR UI
function updateSidebarActiveState(url) {
    document.querySelectorAll('.nav-item').forEach(nav => {
        nav.classList.remove('active');
        if (nav.href === url) nav.classList.add('active');
    });
}