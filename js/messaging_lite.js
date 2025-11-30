// ========================================================
// MESSAGING LITE - Lightweight version for non-messaging pages
// Only handles: incoming call notifications (no PeerJS, no polling)
// ========================================================

// This file is loaded on dashboard, settings, profile, etc.
// Full messaging.js with PeerJS is only loaded on messaging.php

document.addEventListener('DOMContentLoaded', function() {
    // No heavy initialization needed
    // Call UI elements exist in call_overlay.php but PeerJS is not loaded
    // Users will be redirected to messaging.php to answer calls
    
    console.log('Messaging Lite loaded (non-messaging page)');
});

// SPA teardown - nothing to clean up in lite version
window.spaTeardown = function() {
    // No intervals or connections to clean up
};
