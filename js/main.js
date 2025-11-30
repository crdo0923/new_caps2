// ========================================
// PAGE LOADING ANIMATION
// ========================================

// Show loading animation on page load
window.addEventListener('load', function() {
    const loader = document.querySelector('.page-loader');
    
    // 🛑 FIX 1: Mag-check muna kung nahanap ang loader
    if (!loader) return; 

    // Hide loader after 2 seconds
    setTimeout(() => {
        loader.classList.add('fade-out');
        
        // Remove loader from DOM after fade animation
        setTimeout(() => {
            loader.style.display = 'none';
        }, 500);
    }, 2000);
});

// Show loading animation when clicking links
document.addEventListener('DOMContentLoaded', function() {
    // Get all navigation links
    const links = document.querySelectorAll('a:not([href^="#"]):not([target="_blank"])');
    
    links.forEach(link => {
        link.addEventListener('click', function(e) {
            // Only show loader for internal links
            const href = this.getAttribute('href');
            if (!href) return;

            // Decide when to show the full-page loader. We intentionally avoid showing it
            // for internal SPA navigation (to prevent flashing the same page twice).
            // Show loader only for:
            //  - external links (different origin)
            //  - logout or download links
            //  - links with explicit data-force-loader attribute
            const isExternal = href.startsWith('http') && !(href.indexOf(location.origin) === 0);
            const isLogout = href.includes('logout');
            const isDownload = this.hasAttribute('download');
            const forceLoader = this.getAttribute('data-force-loader') === '1' || this.classList.contains('force-loader');

            if (!(isExternal || isLogout || isDownload || forceLoader)) {
                // Let SPA or native handlers take care of single-page navigation — don't show page loader.
                return;
            }
                e.preventDefault();
                
                // Show loader
                const loader = document.querySelector('.page-loader');
                
                // 🛑 FIX 2: Tiyakin na may loader bago gamitin ang style
                if (loader) {
                        loader.style.display = 'flex';
                        loader.classList.remove('fade-out');
                }

                // Navigate after 300ms to give the UI a short transition
                setTimeout(() => {
                    window.location.href = href;
                }, 300);
            }
        });
    });
});

// ========================================
// SMOOTH SCROLL FOR ANCHOR LINKS
// ========================================
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});

// ========================================
// NAVBAR SCROLL EFFECT
// ========================================
let lastScroll = 0;
const navbar = document.querySelector('.navbar');

if (navbar) {
    window.addEventListener('scroll', () => {
        const currentScroll = window.pageYOffset;
        
        if (currentScroll > 100) {
            navbar.style.background = 'rgba(15, 23, 42, 0.98)';
            navbar.style.boxShadow = '0 5px 20px rgba(0, 0, 0, 0.3)';
        } else {
            navbar.style.background = 'rgba(15, 23, 42, 0.95)';
            navbar.style.boxShadow = 'none';
        }
        
        lastScroll = currentScroll;
    });
}

// ========================================
// INTERSECTION OBSERVER FOR ANIMATIONS
// ========================================
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('animate-in');
        }
    });
}, observerOptions);

// Observe elements with animation
document.querySelectorAll('.feature-card, .team-card, .step, .mission-card').forEach(el => {
    observer.observe(el);
});

// ========================================
// MOBILE MENU TOGGLE
// ========================================
const menuToggle = document.querySelector('.menu-toggle');
const navMenu = document.querySelector('.nav-menu');

if (menuToggle && navMenu) { 
    menuToggle.addEventListener('click', () => {
        navMenu.classList.toggle('active');
    });
}

// ========================================
// STATS COUNTER ANIMATION
// ========================================
function animateCounter(element, target) {
    let current = 0;
    const increment = target / 100;
    const timer = setInterval(() => {
        current += increment;
        if (current >= target) {
            element.textContent = target;
            clearInterval(timer);
        } else {
            element.textContent = Math.floor(current);
        }
    }, 20);
}

const statsObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            const stat = entry.target;
            const target = parseInt(stat.textContent.replace(/[^0-9]/g, ''));
            if (!isNaN(target)) {
                animateCounter(stat, target);
                statsObserver.unobserve(stat);
            }
        }
    });
}, { threshold: 0.5 });

document.querySelectorAll('.stat h3').forEach(stat => {
    statsObserver.observe(stat);
});

// ========================================
// LOGOUT MODAL FUNCTIONALITY (Fixed Redirect)
// ========================================
document.addEventListener('DOMContentLoaded', function() {
    // Kukunin ang mga elements
    const logoutModal = document.getElementById('logoutModal');
    const openLogoutBtns = document.querySelectorAll('.openLogoutModal');
    const cancelLogoutBtn = document.getElementById('cancelLogout');
    const confirmLogoutBtn = document.getElementById('confirmLogout');

    // 1. Open Modal when sidebar button is clicked
    if (openLogoutBtns && openLogoutBtns.length && logoutModal) {
        openLogoutBtns.forEach(function(btn){
            btn.addEventListener('click', function(e) {
                e.preventDefault(); 
                logoutModal.classList.add('active'); 
            });
        });
    }

    // ========================================
    // GLOBAL CUSTOM CONFIRM HANDLER
    // Makes a consistent, styled confirm available on every page
    // Usage: window.showCustomConfirm(title, message, onConfirm)
    window.showCustomConfirm = function(title, message, onConfirm) {
        const modal = document.getElementById('customConfirmModal');
        if (!modal) {
            // If our styled modal isn't present, fallback to native confirm so the user still gets a choice
            try {
                const ok = window.confirm(message || title || 'Please confirm');
                if (ok && typeof onConfirm === 'function') onConfirm();
            } catch (e) {
                if (onConfirm) onConfirm();
            }
            return;
        }

        const titleEl = document.getElementById('confirmTitle');
        const msgEl = document.getElementById('confirmMessage');
        const cancelBtn = document.getElementById('btnCancelConfirm');
        const yesBtn = document.getElementById('btnYesConfirm');

        if (titleEl) titleEl.textContent = title || 'Confirm';
        if (msgEl) msgEl.textContent = message || '';

        // remove previous handler by cloning node
        const newYes = yesBtn.cloneNode(true);
        yesBtn.parentNode.replaceChild(newYes, yesBtn);

        modal.style.display = 'flex';
        // wire up explicit close X
        const closeX = document.getElementById('btnCloseCustomConfirm');
        if (closeX) closeX.onclick = () => { modal.classList.remove('show'); setTimeout(() => { modal.style.display = 'none'; }, 220); };
        modal.classList.add('show');
        newYes.addEventListener('click', () => {
            // close with animation
            modal.classList.remove('show');
            setTimeout(() => { modal.style.display = 'none'; if (typeof onConfirm === 'function') onConfirm(); }, 220);
        });

        if (cancelBtn) cancelBtn.onclick = () => { modal.classList.remove('show'); setTimeout(() => { modal.style.display = 'none'; }, 220); };
    }

    // 2. Close Modal when Cancel is clicked
    if (cancelLogoutBtn && logoutModal) {
        cancelLogoutBtn.addEventListener('click', function() {
            logoutModal.classList.remove('active');
        });
    }

    // 3. Confirm Logout: Redirect sa tamang logout script
    if (confirmLogoutBtn) {
        confirmLogoutBtn.addEventListener('click', function() {
            // Redirect to logout script
            window.location.href = 'php/logout.php';
        });
    }

    // 4. Optional: Close modal when clicking the dark overlay
    if (logoutModal) {
        logoutModal.addEventListener('click', function(e) {
            // Tiyakin na ang click ay sa mismong overlay
            if (e.target.id === 'logoutModal') {
                logoutModal.classList.remove('active');
            }
        });
    }
});

// Global async confirm wrapper
// Usage: window.askConfirm(title, message) -> Promise<boolean>
// This centralizes confirm logic so other scripts call this instead of native confirm()
window.askConfirm = function(title, message) {
    return new Promise((resolve) => {
        // Prefer the styled modal when available
        try {
            const modal = document.getElementById('customConfirmModal');
                if (modal && typeof window.showCustomConfirm === 'function') {
                // showCustomConfirm calls the onConfirm callback but doesn't provide onCancel,
                // so resolve true on confirm and false on cancel
                const yesBtn = document.getElementById('btnYesConfirm');
                const cancelBtn = document.getElementById('btnCancelConfirm');

                // Reset handlers by cloning nodes
                const newYes = yesBtn.cloneNode(true);
                yesBtn.parentNode.replaceChild(newYes, yesBtn);

                // Set text
                const titleEl = document.getElementById('confirmTitle');
                const msgEl = document.getElementById('confirmMessage');
                if (titleEl) titleEl.textContent = title || 'Confirm';
                if (msgEl) msgEl.textContent = message || '';

                modal.style.display = 'flex';
                modal.classList.add('show');

                newYes.addEventListener('click', () => {
                    modal.classList.remove('show');
                    setTimeout(() => { modal.style.display = 'none'; resolve(true); }, 220);
                });

                if (cancelBtn) cancelBtn.onclick = () => { modal.classList.remove('show'); setTimeout(() => { modal.style.display = 'none'; resolve(false); }, 220); };
                return;
            }
        } catch (e) { /* ignore modal errors */ }

        // Last-resort synchronous fallback (rare) — keep centralized here so other files don't use confirm()
        try { resolve(window.confirm(message)); } catch (e) { resolve(false); }
    });
}