document.addEventListener('DOMContentLoaded', function() {
    
    // 1. SETUP CONTAINER
    let container = document.getElementById('notification-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'notification-container';
        // Ensure container style is set if dashboard.css fails to load
        container.style.cssText = "position: fixed; bottom: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px;";
        document.body.appendChild(container);
    }

    const badgeElement = document.getElementById('sidebarMsgBadge');

    // --- 🌟 NEW: COUNT UP ANIMATION FUNCTION (Vanilla JS Version of your React Code) ---
    function animateValue(obj, start, end, duration) {
        if (start === end) return;

        let startTimestamp = null;
        
        // Easing function (OutExpo) para magmukhang "Spring" ang feel
        const step = (timestamp) => {
            if (!startTimestamp) startTimestamp = timestamp;
            const progress = Math.min((timestamp - startTimestamp) / duration, 1);
            
            // Easing logic: 1 - pow(2, -10 * progress) (Smooth stop)
            const ease = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress);
            
            const current = Math.floor(progress * (end - start) + start);
            
            // Handle 99+ logic dynamically
            if (current > 99) {
                obj.textContent = "99+";
            } else {
                obj.textContent = current;
            }

            if (progress < 1) {
                window.requestAnimationFrame(step);
            } else {
                // Ensure final value is set
                obj.textContent = end > 99 ? "99+" : end;
            }
        };
        
        window.requestAnimationFrame(step);
    }
    // -----------------------------------------------------------------------

    function checkNotifications() {
        fetch('messaging/ajax_get_notifications.php')
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    
                    // --- A. UPDATE SIDEBAR BADGE WITH ANIMATION ---
                    const newTotal = parseInt(data.total_unread);
                    
                    if (badgeElement) {
                        // Kunin ang dating value (default to 0)
                        const currentTotal = parseInt(badgeElement.getAttribute('data-count')) || 0;

                        if (newTotal > 0) {
                            badgeElement.style.display = 'inline-block';
                            badgeElement.classList.add('visible');
                            
                            // Trigger animation lang kung nagbago ang number
                            if (newTotal !== currentTotal) {
                                animateValue(badgeElement, currentTotal, newTotal, 1000); // 1s duration
                                badgeElement.setAttribute('data-count', newTotal); // Save new state
                            }
                        } else {
                            badgeElement.classList.remove('visible');
                            badgeElement.style.display = 'none';
                            badgeElement.setAttribute('data-count', 0);
                        }
                    }

                    // --- B. SHOW POPUPS (New Messages) ---
                    if (data.notifications && data.notifications.length > 0) {
                        data.notifications.forEach(notif => {
                            showPopup(notif.sender_name, notif.sender_id, notif.count);
                        });
                    }
                }
            })
            .catch(error => {
                // Silent fail
            });
    }

    // 4. Popup Builder Function (Modern Style)
    function showPopup(senderName, senderId, count) {
        const popup = document.createElement('div');
        popup.className = 'notification-popup'; 
        // Fallback styles if CSS hasn't loaded
        if(!popup.classList.contains('toast-notification')) {
             popup.className = 'toast-notification'; // Use dashboard.php class
        }
        
        const message = count > 1 
            ? `You have ${count} new messages.`
            : `Sent you a message just now.`;

        popup.innerHTML = `
            <div class="toast-icon">💬</div>
            <div class="toast-content">
                <h4 class="toast-title" style="margin:0; font-size:0.9rem; font-weight:700; color:#fff;">${senderName}</h4>
                <p class="toast-message" style="margin:0; font-size:0.8rem; color:#cbd5e1;">${message}</p>
            </div>
            <button class="toast-close" style="background:none; border:none; color:#64748b; cursor:pointer;">&times;</button>
        `;

        popup.addEventListener('click', function(e) {
            if (!e.target.classList.contains('toast-close') && !e.target.closest('.toast-close')) {
                window.location.href = `messaging.php?user_id=${senderId}`;
            }
        });

        const closeBtn = popup.querySelector('.toast-close');
        if(closeBtn) {
            closeBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                removePopup(popup);
            });
        }

        container.appendChild(popup);

        requestAnimationFrame(() => {
            popup.classList.add('show');
        });

        setTimeout(() => {
            removePopup(popup);
        }, 5000);
    }

    function removePopup(popup) {
        popup.classList.remove('show');
        setTimeout(() => {
            if (popup.parentElement) popup.remove();
        }, 500);
    }

    // 5. Start Loop (Every 2 Seconds for Realtime feel)
    window.dashboardNotificationsInterval = setInterval(checkNotifications, 2000);
    // SPA teardown support
    window.spaTeardown = function() {
        try { if (window.dashboardNotificationsInterval) { clearInterval(window.dashboardNotificationsInterval); window.dashboardNotificationsInterval = null; } } catch(e) {}
    };
    checkNotifications(); 
});