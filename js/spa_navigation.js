document.addEventListener('DOMContentLoaded', () => {
    // Enable SPA navigation site-wide. The loader will carefully preload head assets
    // (styles and inline style tags) and swap body classes before injecting main content
    // to avoid partial / unfinished renders (especially for dashboard).
    
    // 1. INTERCEPT LINKS
    document.body.addEventListener('click', (e) => {
        // Targetin lang ang mga links sa sidebar o navigation na internal
        const link = e.target.closest('a');
        
        // Conditions para i-intercept:
        // - May href
        // - Hindi external link
        // - Hindi download link
        // - Hindi javascript:void(0)
        if (link && 
            link.href && 
            link.origin === window.location.origin && 
            !link.hasAttribute('download') && 
            link.target !== '_blank' &&
            !link.getAttribute('href').startsWith('#') &&
            !link.getAttribute('href').includes('logout') // Hayaan ang logout na mag-reload
        ) {
            const dest = link.getAttribute('href');
            // Intercept and load via SPA loader (loader will decide how to integrate head assets)
            e.preventDefault(); // STOP PAGE RELOAD
            loadPage(link.href);
        }
    });

    // 2. HANDLE BROWSER BACK/FORWARD BUTTONS
    window.addEventListener('popstate', () => {
        // On popstate we treat the current URL as destination — let loader handle safe injection
        loadPage(window.location.href, false);
    });
});

// 3. MAIN PAGE LOADER FUNCTION
async function loadPage(url, pushState = true) {
    try {
        // Show simple loading indicator on main content only
        const mainContent = document.querySelector('main.main-content');
        if(mainContent) mainContent.style.opacity = '0.5';

        const response = await fetch(url);
        const html = await response.text();

        // Convert string to DOM
        const parser = new DOMParser();
        const newDoc = parser.parseFromString(html, 'text/html');

        // --- HEAD ASSET SYNC ---
        // 1) Copy body classes and attributes to avoid sudden layout changes
        try {
            document.body.className = newDoc.body.className || document.body.className;
            // copy basic data-* attributes from body (if any)
            Array.from(newDoc.body.attributes || []).forEach(attr => {
                if (attr.name && attr.name.startsWith('data-')) document.body.setAttribute(attr.name, attr.value);
            });
        } catch (e) { /* ignore */ }

        // 2) Ensure styles in HEAD are present before swapping main content
        const existingLinks = new Set(Array.from(document.head.querySelectorAll('link[rel="stylesheet"]')).map(l => l.href));
        const newHeadLinks = Array.from(newDoc.head.querySelectorAll('link[rel="stylesheet"]'));

        // Append missing stylesheet links and wait for them to load (best-effort)
        const loadPromises = [];
        newHeadLinks.forEach(link => {
            try {
                const href = link.href;
                if (!href || existingLinks.has(href)) return;
                const el = document.createElement('link');
                el.rel = 'stylesheet'; el.href = href; el.media = link.media || 'all';
                document.head.appendChild(el);
                loadPromises.push(new Promise(res => { el.onload = () => res(true); el.onerror = () => res(false); }));
            } catch (e) { /* ignore */ }
        });

        // 3) Copy inline <style> tags if not present
        try {
            const presentStyles = Array.from(document.head.querySelectorAll('style')).map(s => s.innerHTML.trim());
            newDoc.head.querySelectorAll('style').forEach(s => {
                try {
                    const txt = s.innerHTML.trim();
                    if (!txt) return;
                    if (!presentStyles.includes(txt)) {
                        const n = document.createElement('style'); n.innerHTML = txt; document.head.appendChild(n);
                    }
                } catch (e) {}
            });
        } catch (e) { /* ignore */ }

        // Wait for styles to load but don't stall forever — cap at 700ms
        await Promise.race([Promise.all(loadPromises), new Promise(res => setTimeout(res, 700))]);

        // A. SWAP MAIN CONTENT
        const newMain = newDoc.querySelector('main.main-content');
        const currentMain = document.querySelector('main.main-content');

        if (newMain && currentMain) {
            // Before swapping content, attempt to teardown existing page resources (intervals, media, peer, event listeners)
            try { if (typeof window.spaTeardown === 'function') window.spaTeardown(); } catch (err) { console.warn('Error during SPA teardown', err); }
            currentMain.innerHTML = newMain.innerHTML;
            currentMain.className = newMain.className; // Update classes layout
            currentMain.style.opacity = '1';
        }

        // B. UPDATE URL
        if (pushState) {
            window.history.pushState({}, '', url);
        }

        // C. UPDATE TITLE
        document.title = newDoc.title;

        // D. RE-ACTIVATE SCRIPTS (Crucial para sa Charts at Messaging)
        // Ang JS sa bagong page ay hindi gagana kusa, kailangan nating "sindihan" ulit.
        reinitializeScripts(newDoc);

        // E. RE-ACTIVATE SIDEBAR ACTIVE STATE
        updateSidebarActiveState(url);

    } catch (err) {
        console.error("Navigation Error:", err);
        // Fallback: Kung nag-error, mag-reload na lang nang tuluyan
        window.location.href = url;
    }
}

    // 4. SCRIPT RE-INITIALIZER (Advanced)
function reinitializeScripts(newDoc) {
    // Hanapin ang mga scripts sa bagong page na wala sa current page
    const newScripts = newDoc.querySelectorAll('script');
        const existingScriptSources = new Set(Array.from(document.querySelectorAll('script[src]')).map(s => s.src));
        const existingInlineScripts = new Set(Array.from(document.querySelectorAll('script:not([src])')).map(s => s.innerHTML.trim()));
    
    newScripts.forEach(oldScript => {
        // Skip libraries na naka-load na (tulad ng PeerJS o ChartJS)
        if (oldScript.src && (oldScript.src.includes('peerjs') || oldScript.src.includes('chart.js'))) return;
        // Skip script if an identical one (src or inline text) already exists in the document
        try {
            if (oldScript.src && existingScriptSources.has(oldScript.src)) return;
            if (!oldScript.src && existingInlineScripts.has(oldScript.innerHTML.trim())) return;
        } catch(e) {}

        const newScript = document.createElement('script');
        
        // Copy attributes
        Array.from(oldScript.attributes).forEach(attr => {
            newScript.setAttribute(attr.name, attr.value);
        });

        // Copy content (inline scripts)
        if (oldScript.innerHTML) {
            newScript.innerHTML = oldScript.innerHTML;
        }

        // Append to body to trigger execution
        document.body.appendChild(newScript);
        
        // Remove after execution to keep DOM clean and prevent DOM growth
        try { document.body.removeChild(newScript); } catch(e) {}
    });

    // Special Trigger para sa Dashboard Init
    if (typeof initScheduleGenerator === 'function') initScheduleGenerator();
    
    // Special Trigger para sa Persistent Button Visibility
    if (typeof togglePersistentCallButton === 'function') togglePersistentCallButton();
}

// 5. UPDATE SIDEBAR UI
function updateSidebarActiveState(url) {
    document.querySelectorAll('.nav-item').forEach(nav => {
        nav.classList.remove('active');
        if (nav.href === url) {
            nav.classList.add('active');
        }
    });
}