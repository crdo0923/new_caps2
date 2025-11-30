// ========================================================
// PAGE NAVIGATION - Full page reload with smooth transition
// ========================================================
// This approach uses traditional navigation (full page reload)
// which automatically clears all memory, intervals, and state.
// Much simpler and more reliable than SPA!

(function() {
    'use strict';
    
    // Add page transition styles
    const style = document.createElement('style');
    style.textContent = `
        .page-transition-out {
            opacity: 0;
            transform: translateY(-10px);
            transition: opacity 0.15s ease, transform 0.15s ease;
        }
        body {
            opacity: 1;
            transition: opacity 0.15s ease;
        }
    `;
    document.head.appendChild(style);
    
    // Fade in on page load
    document.addEventListener('DOMContentLoaded', () => {
        document.body.style.opacity = '1';
    });
    
    // Handle navigation clicks with smooth transition
    document.body.addEventListener('click', (e) => {
        const link = e.target.closest('a');
        
        // Only handle internal navigation links
        if (link && 
            link.href && 
            link.origin === window.location.origin && 
            !link.hasAttribute('download') && 
            link.target !== '_blank' &&
            !link.getAttribute('href').startsWith('#') &&
            !link.getAttribute('href').startsWith('javascript:')
        ) {
            e.preventDefault();
            
            // Add transition effect
            document.body.style.opacity = '0.5';
            
            // Navigate after short delay for visual feedback
            setTimeout(() => {
                window.location.href = link.href;
            }, 100);
        }
    });
})();