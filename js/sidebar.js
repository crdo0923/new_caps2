document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const mainContent = document.querySelector('.main-content');

    if (sidebar && toggleBtn) {
        const DESKTOP_BREAK = 768; // breakpoint to treat as desktop
        const EXPANDED_MARGIN = '280px';
        const COLLAPSED_MARGIN = '80px';

        // Ensure state is predictable on load
        function applySidebarState() {
            const isMobile = window.innerWidth < DESKTOP_BREAK;
            const stored = localStorage.getItem('sidebarCollapsed');
            const collapsed = (stored === 'true');

            // On mobile, keep sidebar hidden/unstyled (no collapsed state)
            if (isMobile) {
                sidebar.classList.remove('collapsed');
                if (mainContent) mainContent.style.marginLeft = '0';
                // ensure mobile icon state consistent (closed by default)
                const iconEl = toggleBtn.querySelector('i');
                if (iconEl) { iconEl.classList.remove('bx-x'); iconEl.classList.add('bx-menu'); }
                return;
            }

            if (collapsed) sidebar.classList.add('collapsed'); else sidebar.classList.remove('collapsed');
            // Keep main content margin consistent with CSS using the body/html class; optional inline margin left removed to prefer CSS
            // mirror class on both html and body to reduce flicker and provide early paint state
            try { document.documentElement.classList.toggle('sidebar-collapsed', sidebar.classList.contains('collapsed')); } catch (e) {}
            try { document.body.classList.toggle('sidebar-collapsed', sidebar.classList.contains('collapsed')); } catch (e) {}
            if (toggleBtn) toggleBtn.setAttribute('aria-expanded', sidebar.classList.contains('collapsed') ? 'false' : 'true');
            if (sidebar) sidebar.setAttribute('aria-hidden', sidebar.classList.contains('collapsed') ? 'true' : 'false');
            // Update icon state for desktop after applying collapsed/expanded
            const iconEl = toggleBtn.querySelector('i');
            if (iconEl) { iconEl.classList.remove('bx-x'); iconEl.classList.add('bx-menu'); }
            // After applying state, ensure the toggle is positioned correctly under the logo
            alignSidebarToggle();
        }

        // initial apply
        applySidebarState();

        // Toggle Event
        toggleBtn.addEventListener('click', () => {
            const isMobile = window.innerWidth < DESKTOP_BREAK;
            if (isMobile) {
                // Mobile behavior: toggle overlay (open class). Don't persist mobile state.
                const isOpenNow = sidebar.classList.toggle('open');
                // Ensure collapsed class is not used here for mobile
                sidebar.classList.remove('collapsed');
                // main content margin set to 0 on mobile via CSS
                // Set aria-expanded accordingly
                if (toggleBtn) toggleBtn.setAttribute('aria-expanded', isOpenNow ? 'true' : 'false');
                // update the icon for mobile open/close
                const iconEl = toggleBtn.querySelector('i');
                if (iconEl) {
                    iconEl.classList.toggle('bx-x', isOpenNow);
                    iconEl.classList.toggle('bx-menu', !isOpenNow);
                }
                // remove collapsed body/html class on mobile opens
                try { document.documentElement.classList.toggle('sidebar-collapsed', false); } catch (e) {}
                try { document.body.classList.toggle('sidebar-collapsed', false); } catch (e) {}
                // set aria-hidden on sidebar
                if (sidebar) sidebar.setAttribute('aria-hidden', isOpenNow ? 'false' : 'true');
                return;
            }

            // Desktop behavior: toggle collapse state and persist
            const currentlyCollapsed = sidebar.classList.toggle('collapsed');
            localStorage.setItem('sidebarCollapsed', currentlyCollapsed ? 'true' : 'false');
            // margin-left managed by CSS using .sidebar-collapsed
            if (toggleBtn) toggleBtn.setAttribute('aria-expanded', currentlyCollapsed ? 'false' : 'true');
            // update icon for desktop collapsed/expanded -- show menu icon when expanded, menu (or alternative) when collapsed
            const iconEl = toggleBtn.querySelector('i');
            if (iconEl) {
                iconEl.classList.toggle('bx-menu', !currentlyCollapsed);
                // keep the icon as menu for collapse state to avoid confusion; no icon flip required for desktop
            }
            // mirror class on both html and body for preamble and CSS rules
            try { document.documentElement.classList.toggle('sidebar-collapsed', currentlyCollapsed); } catch (e) {}
            try { document.body.classList.toggle('sidebar-collapsed', currentlyCollapsed); } catch (e) {}
            // ensure mobile overlay is removed when toggling desktop collapsed
            sidebar.classList.remove('open');
            if (sidebar) sidebar.setAttribute('aria-hidden', currentlyCollapsed ? 'true' : 'false');
            // Align the toggle under the logo after the collapse/expand action - final placement after CSS transition
            // Wait for the sidebar transition to complete first to compute with final width/height values
            setTimeout(() => alignSidebarToggle(), 40);
        });

        // Keep responsive behavior consistent on resize
        let resizeTimer = null;
        window.addEventListener('resize', () => {
            if (resizeTimer) clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                applySidebarState();
                alignSidebarToggle();
            }, 120);
        });
        // Ensure alignment is updated after the sidebar finishes width transition
        sidebar.addEventListener('transitionend', function (e) {
            try {
                if (e && e.propertyName && (e.propertyName === 'width' || e.propertyName === 'transform')) {
                    alignSidebarToggle();
                }
            } catch (err) { /* ignore */ }
        });
        
        // Position the toggle under the logo precisely when the sidebar is collapsed so it doesn't drift.
        function alignSidebarToggle() {
            if (!sidebar || !toggleBtn) return;
            const isMobile = window.innerWidth < DESKTOP_BREAK;
            // Reset to CSS default when mobile or not collapsed
                if (isMobile || !sidebar.classList.contains('collapsed')) {
                    // Reset to default expanded inline placement and let CSS handle the gap
                    toggleBtn.style.position = 'relative';
                    toggleBtn.style.left = '';
                    toggleBtn.style.right = '';
                    toggleBtn.style.top = '';
                    toggleBtn.style.transform = '';
                    toggleBtn.style.marginLeft = '';
                    toggleBtn.style.zIndex = '';
                // clear any mask var we may have set
                const logoContainer = sidebar.querySelector('.logo-container');
                if (logoContainer) {
                    logoContainer.style.removeProperty('--logo-divider-left-collapsed');
                    logoContainer.style.removeProperty('--logo-divider-right-collapsed');
                }
                return;
            }
            const logoEl = sidebar.querySelector('.logo');
            const logoContainer = sidebar.querySelector('.logo-container');
            if (!logoEl || !logoContainer) return;
            const sidebarRect = sidebar.getBoundingClientRect();
            const logoRect = logoEl.getBoundingClientRect();
            const logoContainerRect = logoContainer.getBoundingClientRect();
            const toggleRect = toggleBtn.getBoundingClientRect();
            // compute left relative to sidebar left (toggle left coordinate)
            // compute left relative to the logo container (we want the toggle to sit *to the right* of the logo glyph in collapsed mode)
            const leftRaw = (logoRect.right - logoContainerRect.left) + 8; // 8px gap to the right of the logo
            const maxLeft = Math.max(6, Math.floor(logoContainerRect.width - toggleRect.width - 6));
            const left = Math.min(maxLeft, Math.max(6, Math.round(leftRaw)));
            const top = Math.round((logoContainerRect.height - toggleRect.height)/2); // vertically center within the logo container
            // Apply inline style to align the toggle precisely under the logo
            toggleBtn.style.left = Math.max(6, Math.round(left)) + 'px';
            toggleBtn.style.top = Math.round(top) + 'px';
            toggleBtn.style.transform = 'none';
            toggleBtn.style.right = 'auto';
            // ensure we remain absolute and on top
            toggleBtn.style.position = 'absolute';
            toggleBtn.style.zIndex = 2400;
            // Also set pseudo-element mask to center underneath the toggle
            const maskWidth = 48; // same width as CSS
            const maskLeftRaw = Math.round(left - ((maskWidth - toggleRect.width) / 2));
            const maskLeft = Math.max(6, Math.min(Math.floor(logoContainerRect.width - maskWidth - 6), maskLeftRaw));
            logoContainer.style.setProperty('--logo-divider-left-collapsed', maskLeft + 'px');
        }
    }
});