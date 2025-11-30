document.addEventListener('DOMContentLoaded', function() {
    
    // 1. AUTO-SAVE LOGIC
    const autoSaveTriggers = document.querySelectorAll('.autosave-trigger');
    const indicator = document.getElementById('autoSaveIndicator');

    autoSaveTriggers.forEach(element => {
        element.addEventListener('change', function() {
            indicator.textContent = "Saving...";
            indicator.style.opacity = '1';
            indicator.style.color = '#fbbf24';

            const form = document.getElementById('preferences-form');
            const formData = new FormData(form);

            fetch('settings.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if(data.status === 'success') {
                    indicator.textContent = "All changes saved";
                    indicator.style.color = '#10b981';
                    setTimeout(() => { indicator.style.opacity = '0'; }, 2000);
                } else {
                    indicator.textContent = "Error saving";
                    indicator.style.color = '#ef4444';
                }
            })
            .catch(error => {
                indicator.textContent = "Network Error";
                indicator.style.color = '#ef4444';
            });
        });
    });

    // 2. SECURITY FORM (CLICK LISTENER)
    const updateBtn = document.getElementById('btnUpdatePass');
    const securityForm = document.getElementById('security-form');
    
    if (updateBtn) {
        updateBtn.addEventListener('click', function(e) {
            e.preventDefault(); // SURE BALL WALANG REFRESH

            const currentPass = document.getElementById('currentPassword').value;
            const newPass = document.getElementById('newPassword').value;
            const confirmPass = document.getElementById('confirmPassword').value;

            // Validation: Check Empty
            if(!currentPass || !newPass || !confirmPass) {
                showDynamicAlert('⚠️ Please fill in all password fields.', 'error');
                return;
            }

            // Validation: Check Match
            if (newPass !== confirmPass) {
                showDynamicAlert('❌ New password and Confirm password do not match.', 'error');
                return;
            }

            // UI: Loading
            const originalText = updateBtn.innerHTML;
            updateBtn.innerHTML = '<span>⏳</span> Updating...';
            updateBtn.style.opacity = '0.7';
            updateBtn.disabled = true;

            const formData = new FormData(securityForm);

            // AJAX Request
            fetch('settings.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'error') {
                    showDynamicAlert(data.message, 'error'); 
                } else {
                    showDynamicAlert(data.message, 'success');
                    securityForm.reset(); 
                }
            })
            .catch(error => {
                console.error(error);
                showDynamicAlert('❌ Server Error. Check console.', 'error');
            })
            .finally(() => {
                updateBtn.innerHTML = originalText;
                updateBtn.style.opacity = '1';
                updateBtn.disabled = false;
            });
        });
    }

    // 3. DOWNLOAD DATA
    const btnDownload = document.getElementById('btnDownload');
    if (btnDownload) {
        btnDownload.addEventListener('click', function() {
            showModal("Download Data?", "This will download a JSON file containing your profile info, study sessions, and achievements.", () => {
                window.location.href = 'settings.php?action=download_data';
            });
        });
    }

    // 4. DELETE BUTTONS
    const btnDeleteData = document.getElementById('btnDeleteData');
    if (btnDeleteData) {
        btnDeleteData.addEventListener('click', function() {
            showModal("Delete All Data?", "WARNING: This will permanently delete your study sessions, achievements, and messages.", () => {
                const formData = new FormData();
                formData.append('action', 'delete_data');
                fetch('settings.php', { method: 'POST', body: formData })
                    .then(r => r.text())
                    .then(res => {
                        if(res.trim() === 'success') showDynamicAlert('All data deleted successfully.', 'success');
                        else showDynamicAlert('Failed to delete data.', 'error');
                    });
            });
        });
    }

    const btnDeleteAccount = document.getElementById('btnDeleteAccount');
    if (btnDeleteAccount) {
        btnDeleteAccount.addEventListener('click', function() {
            showModal("Delete Account?", "FINAL WARNING: This will permanently delete your entire account. You will be logged out immediately.", () => {
                const formData = new FormData();
                formData.append('action', 'delete_account');
                fetch('settings.php', { method: 'POST', body: formData })
                    .then(r => r.text())
                    .then(res => {
                        if(res.trim() === 'success') window.location.href = 'auth.php';
                        else showDynamicAlert('Failed to delete account.', 'error');
                    });
            });
        });
    }

    // 6. SHOW TUTORIAL AGAIN (RE-RUN ONBOARDING)
    const btnShowTutorialAgain = document.getElementById('btnShowTutorialAgain');
    if (btnShowTutorialAgain) {
        btnShowTutorialAgain.addEventListener('click', function() {
            showModal('Restart Tutorial?', 'This will re-enable the onboarding / guided tour and open the dashboard tour.', () => {
                // Call the onboarding endpoint to reset flag, then redirect to dashboard to run tour
                fetch('ajax_onboarding.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'reset' })
                }).then(r => r.json()).then(res => {
                    if (res && res.success) {
                        showDynamicAlert('Tutorial re-enabled. Opening dashboard...', 'success');
                        setTimeout(() => window.location.href = 'dashboard.php?start_tour=1', 700);
                    } else {
                        showDynamicAlert('Could not re-enable tutorial. Try again later.', 'error');
                    }
                }).catch(err => {
                    console.error('Error enabling tutorial', err);
                    showDynamicAlert('Network error. Please try again.', 'error');
                });
            });
        });
    }

    // Restore loader fadeout when present
    const loader = document.querySelector('.page-loader');
    if (loader) {
        setTimeout(() => {
            loader.style.opacity = '0';
            setTimeout(() => loader.style.display = 'none', 500);
        }, 800);
    }
});

// --- HELPER: DYNAMIC ALERT ---
function showDynamicAlert(message, type) {
    const container = document.getElementById('notification-area');
    const div = document.createElement('div');
    div.className = `alert-box ${type}`;
    div.textContent = message;
    div.style.animation = 'fadeIn 0.5s ease';
    container.innerHTML = ''; 
    container.appendChild(div);

    // Auto-hide after 3 seconds
    setTimeout(() => {
        div.style.transition = 'opacity 0.5s ease';
        div.style.opacity = '0';
        setTimeout(() => div.remove(), 500);
    }, 3000);
}

// --- MODAL LOGIC ---
const modal = document.getElementById('customModal');
const modalTitle = document.getElementById('modalTitle');
const modalMessage = document.getElementById('modalMessage');
const modalConfirmBtn = document.getElementById('modalConfirm');
const modalCancelBtn = document.getElementById('modalCancel');
let confirmCallback = null;

function showModal(title, message, onConfirm) {
    modalTitle.textContent = title;
    modalMessage.textContent = message;
    confirmCallback = onConfirm;
    modal.style.display = 'flex';
}

if(modalCancelBtn) modalCancelBtn.addEventListener('click', () => { modal.style.display = 'none'; confirmCallback = null; });
if(modalConfirmBtn) modalConfirmBtn.addEventListener('click', () => { if(confirmCallback) confirmCallback(); modal.style.display = 'none'; });
window.onclick = function(event) { if(event.target == modal) modal.style.display = 'none'; }

// --- PASSWORD TOGGLE ---
function togglePassword(inputId, iconElement) {
    const input = document.getElementById(inputId);
    if (input.type === "password") {
        input.type = "text";
        iconElement.textContent = "🙈"; 
    } else {
        input.type = "password";
        iconElement.textContent = "👁️"; 
    }
}