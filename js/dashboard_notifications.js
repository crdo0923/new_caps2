// ========================================================
// DASHBOARD NOTIFICATIONS - Lightweight Badge Counter
// Runs on ALL pages to show sidebar message count
// ========================================================

(function() {
    'use strict';
    
    // Wait for DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    
    function init() {
        // Setup notification container
        let container = document.getElementById('notification-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'notification-container';
            container.style.cssText = "position: fixed; bottom: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px;";
            document.body.appendChild(container);
        }

        const badgeElement = document.getElementById('sidebarMsgBadge');

        // Animated counter
        function animateValue(obj, start, end, duration) {
            if (start === end || !obj) return;
            let startTimestamp = null;
            
            function step(timestamp) {
                if (!startTimestamp) startTimestamp = timestamp;
                const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                const current = Math.floor(progress * (end - start) + start);
                obj.textContent = current > 99 ? "99+" : current;
                if (progress < 1) requestAnimationFrame(step);
                else obj.textContent = end > 99 ? "99+" : end;
            }
            requestAnimationFrame(step);
        }

        // Check for new notifications
        function checkNotifications() {
            fetch('messaging/ajax_get_notifications.php')
                .then(r => r.json())
                .then(data => {
                    if (data.status === 'success' && badgeElement) {
                        const newTotal = parseInt(data.total_unread) || 0;
                        const currentTotal = parseInt(badgeElement.getAttribute('data-count')) || 0;

                        if (newTotal > 0) {
                            badgeElement.style.display = 'inline-block';
                            badgeElement.classList.add('visible');
                            if (newTotal !== currentTotal) {
                                animateValue(badgeElement, currentTotal, newTotal, 800);
                                badgeElement.setAttribute('data-count', newTotal);
                            }
                        } else {
                            badgeElement.classList.remove('visible');
                            badgeElement.style.display = 'none';
                            badgeElement.setAttribute('data-count', 0);
                        }
                    }
                    
                    // Show popups for new messages
                    if (data.notifications && data.notifications.length > 0) {
                        data.notifications.forEach(n => showPopup(n.sender_name, n.sender_id, n.count));
                    }
                })
                .catch(() => {}); // Silent fail
        }

        function showPopup(name, id, count) {
            const popup = document.createElement('div');
            popup.className = 'toast-notification';
            popup.innerHTML = `
                <div class="toast-icon">💬</div>
                <div class="toast-content">
                    <h4 style="margin:0; font-size:0.9rem; font-weight:700; color:#fff;">${name}</h4>
                    <p style="margin:0; font-size:0.8rem; color:#cbd5e1;">${count > 1 ? `${count} new messages` : 'Sent you a message'}</p>
                </div>
                <button class="toast-close" style="background:none; border:none; color:#64748b; cursor:pointer;">×</button>
            `;

            popup.addEventListener('click', e => {
                if (!e.target.classList.contains('toast-close')) {
                    window.location.href = `messaging.php?user_id=${id}`;
                }
            });

            popup.querySelector('.toast-close')?.addEventListener('click', e => {
                e.stopPropagation();
                popup.remove();
            });

            container.appendChild(popup);
            requestAnimationFrame(() => popup.classList.add('show'));
            setTimeout(() => { popup.classList.remove('show'); setTimeout(() => popup.remove(), 500); }, 5000);
        }

        // Start polling (every 30 seconds) - will be cleared on page navigation
        setInterval(checkNotifications, 30000);
        
        // Initial check
        checkNotifications();
    }
})();