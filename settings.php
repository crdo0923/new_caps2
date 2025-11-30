<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0); 

// Database Connection
$servername = 'localhost';
$username = 'root';
$password = '';
$dbname = 'smart_study';

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Database Connection Failed']);
    exit();
}

// Check Authentication
if (!isset($_SESSION['user_id'])) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode(['status' => 'error', 'message' => 'Session expired']);
        exit();
    }
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// ===================================================
// BACKEND LOGIC HANDLERS (AJAX RESPONSES)
// ===================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    header('Content-Type: application/json'); 

    // 1. AUTO-SAVE PREFERENCES
    if ($_POST['action'] === 'update_preferences') {
        $def_subject = $_POST['defaultSubject'] ?? 'General';
        $pomodoro = isset($_POST['pomodoro_sound']) && $_POST['pomodoro_sound'] === 'on' ? 1 : 0;
        $ai_prio = isset($_POST['ai_priority']) && $_POST['ai_priority'] === 'on' ? 1 : 0;

        $stmt = $conn->prepare("UPDATE users SET preferred_study_time=?, notification_sound=? WHERE id=?");
        $stmt->bind_param("ssi", $def_subject, $pomodoro, $user_id);
        
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Preferences saved']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database error']);
        }
        $stmt->close();
        exit();
    }

    // 2. UPDATE PASSWORD (AJAX - STRICT FIELD TARGETING)
    if ($_POST['action'] === 'update_security') {
        $current_pass = $_POST['currentPassword'];
        $new_pass = $_POST['newPassword'];
        $confirm_pass = $_POST['confirmPassword'];

        // Validation with specific TARGET FIELDS
        if (empty($current_pass)) {
            echo json_encode(['status' => 'error', 'field' => 'currentPassword', 'message' => 'Current password is required.']);
            exit();
        }
        if (empty($new_pass)) {
            echo json_encode(['status' => 'error', 'field' => 'newPassword', 'message' => 'New password is required.']);
            exit();
        }
        if (empty($confirm_pass)) {
            echo json_encode(['status' => 'error', 'field' => 'confirmPassword', 'message' => 'Please confirm your password.']);
            exit();
        }

        if ($new_pass !== $confirm_pass) {
            echo json_encode(['status' => 'error', 'field' => 'confirmPassword', 'message' => 'Passwords do not match!']);
            exit();
        }

        // Verify Current Password
        $stmt = $conn->prepare("SELECT password FROM users WHERE id=?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user && password_verify($current_pass, $user['password'])) {
            // Update to New Password
            $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $up_stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
            $up_stmt->bind_param("si", $new_hash, $user_id);
            
            if ($up_stmt->execute()) {
                echo json_encode(['status' => 'success', 'message' => 'Password updated successfully!']);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to update password. Please try again.']); // General error
            }
            $up_stmt->close();
        } else {
            // Incorrect Current Password
            echo json_encode(['status' => 'error', 'field' => 'currentPassword', 'message' => 'Incorrect current password.']);
        }
        $stmt->close();
        exit();
    }

    // 3. DELETE DATA
    if ($_POST['action'] === 'delete_data') {
        $tables = ['study_sessions', 'user_achievements', 'messages', 'group_members'];
        $success = true;
        foreach ($tables as $table) {
            $col = ($table == 'messages') ? 'sender_id' : 'user_id'; 
            $sql = "DELETE FROM $table WHERE $col = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            if (!$stmt->execute()) $success = false;
            $stmt->close();
        }
        echo $success ? "success" : "error";
        exit();
    }

    // 4. DELETE ACCOUNT
    if ($_POST['action'] === 'delete_account') {
        $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            session_destroy();
            echo "success";
        } else {
            echo "error";
        }
        exit();
    }
}

// 5. DOWNLOAD DATA
if (isset($_GET['action']) && $_GET['action'] === 'download_data') {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="my_study_data.json"');
    $data = [];
    $u = $conn->query("SELECT firstname, lastname, email, program FROM users WHERE id=$user_id")->fetch_assoc();
    $data['profile'] = $u;
    $s = $conn->query("SELECT subject, duration_minutes, session_datetime FROM study_sessions WHERE user_id=$user_id");
    $data['sessions'] = $s->fetch_all(MYSQLI_ASSOC);
    $a = $conn->query("SELECT a.name, a.description FROM user_achievements ua JOIN achievements a ON ua.achievement_id = a.id WHERE ua.user_id=$user_id");
    $data['achievements'] = $a->fetch_all(MYSQLI_ASSOC);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit();
}

// --- Fetch Current Preferences ---
$curr_user = $conn->query("SELECT * FROM users WHERE id=$user_id")->fetch_assoc();
$pref_subject = $curr_user['preferred_study_time'] ?? 'Web Dev'; 
$pref_sound = $curr_user['notification_sound'] ?? 1;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - SmartStudy</title> 
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    
    <link rel="stylesheet" href="css/loading.css">
    <?php include __DIR__ . '/includes/layout_preamble.php'; ?>
    <link rel="stylesheet" href="css/layout.css">
    
    <style>
        /* =========================================
           DARK THEME SETTINGS STYLES
           ========================================= */
        :root {
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --bg-dark: #0f172a;
            --bg-card: #1e293b;
            --text-light: #f8fafc;
            --text-gray: #94a3b8;
            --border: #334155;
            --danger: #ef4444;
            --success: #10b981;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-dark);
            color: var(--text-light);
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            line-height: 1.6;
        }

        .profile-container {
            max-width: 1100px; /* Increased width for side-by-side layout */
            margin: 2rem auto;
            padding: 0 1.5rem;
        }

        /* ACTIONS & HEADER */
        .profile-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .btn-back {
            color: var(--text-gray);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
            transition: color 0.3s;
        }
        .btn-back:hover { color: var(--primary); }

        .save-indicator {
            font-size: 0.85rem;
            color: var(--success);
            display: none;
            align-items: center;
            gap: 5px;
            background: rgba(16, 185, 129, 0.1);
            padding: 4px 10px;
            border-radius: 20px;
        }

        .section-header h1 { font-size: 2rem; font-weight: 800; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 10px; }
        .section-header p { color: var(--text-gray); margin-bottom: 2rem; }

        /* SETTINGS GRID LAYOUT (THE FIX) */
        .settings-container { 
            display: grid;
            grid-template-columns: 1fr 1fr; /* Split into two columns */
            gap: 2rem;
        }

        .settings-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 2rem;
        }

        /* Full width for Danger Zone */
        .danger-card {
            grid-column: 1 / -1; /* Span across both columns */
            border-color: rgba(239, 68, 68, 0.3); background: rgba(239, 68, 68, 0.05);
        }

        .settings-card h3 { 
            font-size: 1.25rem; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border); padding-bottom: 1rem; 
            display: flex; align-items: center; gap: 0.5rem;
        }

        /* FORM ELEMENTS */
        .setting-group { margin-bottom: 1.5rem; }
        .setting-group label { display: block; font-size: 0.9rem; color: var(--text-gray); margin-bottom: 0.5rem; }
        
        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            background: var(--bg-dark);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: white;
            font-family: inherit;
            box-sizing: border-box;
            transition: 0.3s;
        }
        .form-input:focus { border-color: var(--primary); outline: none; }
        
        /* ERROR STATES */
        .form-input.input-error { border-color: var(--danger); background: rgba(239, 68, 68, 0.05); }
        
        /* INLINE ERROR MESSAGE */
        .error-text {
            color: var(--danger);
            font-size: 0.85rem;
            margin-top: 0.5rem;
            display: none; /* Hidden by default */
            align-items: center;
            gap: 5px;
            font-weight: 500;
        }

        /* TOGGLE SWITCH */
        .setting-toggle-wrapper { margin-bottom: 1.5rem; }
        .setting-toggle {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(15, 23, 42, 0.5);
            padding: 1rem;
            border-radius: 12px;
            border: 1px solid var(--border);
        }
        
        .switch { position: relative; display: inline-block; width: 50px; height: 26px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0;
            background-color: #334155; transition: .4s; border-radius: 34px;
        }
        .slider:before {
            position: absolute; content: ""; height: 18px; width: 18px; left: 4px; bottom: 4px;
            background-color: white; transition: .4s; border-radius: 50%;
        }
        input:checked + .slider { background-color: var(--primary); }
        input:checked + .slider:before { transform: translateX(24px); }

        /* PASSWORD WRAPPER */
        .password-wrapper { position: relative; }
        .password-wrapper .input-icon {
            position: absolute; left: 1rem; top: 50%; transform: translateY(-50%);
            color: var(--text-gray); font-size: 1.1rem; pointer-events: none;
        }
        .password-wrapper .form-input { padding-left: 2.5rem; } 
        
        .toggle-password {
            position: absolute; right: 1rem; top: 50%; transform: translateY(-50%);
            color: var(--text-gray); cursor: pointer; font-size: 1.2rem;
        }
        .toggle-password:hover { color: var(--text-light); }

        /* BUTTONS */
        .btn-save-settings {
            width: 100%;
            padding: 0.8rem;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        #btnUpdatePass { background: var(--primary); color: white; }
        #btnUpdatePass:hover { background: var(--primary-hover); }

        /* DANGER ZONE COLORS */
        .danger-card h3 { color: var(--danger); border-color: rgba(239, 68, 68, 0.2); }
        .danger-card p { color: #fca5a5; font-size: 0.9rem; margin-bottom: 1.5rem; }
        .danger-actions-wrapper { display: flex; flex-direction: column; gap: 1rem; }
        
        .btn-download { background: #334155; color: white; }
        .btn-download:hover { background: #475569; }
        .btn-delete-data { background: transparent; border: 1px solid var(--danger); color: var(--danger); }
        .btn-delete-data:hover { background: rgba(239, 68, 68, 0.1); }
        .btn-delete-account { background: var(--danger); color: white; }
        .btn-delete-account:hover { background: #dc2626; }

        /* MODAL */
        .modal-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0, 0, 0, 0.8); z-index: 10000;
            display: none; align-items: center; justify-content: center;
            backdrop-filter: blur(5px);
        }
        .modal-box {
            background: var(--bg-card); padding: 2rem; border-radius: 16px;
            width: 90%; max-width: 400px; text-align: center;
            border: 1px solid var(--border); box-shadow: 0 20px 50px rgba(0,0,0,0.5);
        }
        .modal-icon { font-size: 3rem; margin-bottom: 1rem; }
        .modal-actions { display: flex; gap: 1rem; margin-top: 2rem; }
        .btn-modal-cancel { background: transparent; border: 1px solid var(--border); color: var(--text-light); flex: 1; padding: 0.8rem; border-radius: 8px; cursor: pointer; }
        .btn-modal-confirm { background: var(--danger); color: white; border: none; flex: 1; padding: 0.8rem; border-radius: 8px; cursor: pointer; font-weight: 600; }

        /* SUCCESS TOAST (TOP) */
        #notification-area { margin-bottom: 1rem; }
        .alert-box {
            padding: 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: 0.9rem; 
            display: flex; align-items: center; gap: 0.5rem; transition: opacity 0.3s;
            justify-content: center; text-align: center;
        }
        .alert-box.success { background: rgba(16, 185, 129, 0.1); color: var(--success); border: 1px solid rgba(16, 185, 129, 0.2); }
        .alert-box.error { background: rgba(239, 68, 68, 0.1); color: var(--danger); border: 1px solid rgba(239, 68, 68, 0.2); }

        /* RESPONSIVE */
        @media (max-width: 900px) {
            .settings-container { grid-template-columns: 1fr; } /* Stack on mobile */
            .profile-container { padding: 0 1rem; }
            .section-header h1 { font-size: 1.8rem; }
        }
    </style>
    <!-- dashboard.css removed here; settings page relies on index.css and settings.css -->
</head>
<body class="profile-body"> 
    
    <?php include 'includes/mobile_blocker.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <div id="customModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-icon">⚠️</div>
            <h3 id="modalTitle">Are you sure?</h3>
            <p id="modalMessage">This action cannot be undone.</p>
            <div class="modal-actions">
                <button id="modalCancel" class="btn-modal-cancel">Cancel</button>
                <button id="modalConfirm" class="btn-modal-confirm">Yes, Proceed</button>
            </div>
        </div>
    </div>

    <div class="page-loader">
    <div class="loader-container">
<div class="loader-icon"><i class='bx bx-brain' style="font-size: 4rem; color: #6366f1;"></i></div>        <div class="loader-text"></div>
        <div class="loader-spinner"><div class="spinner-ring"></div></div>
    </div>
</div>

    <main class="main-content">
    <div class="profile-container">
            <div class="profile-actions">
                <!-- Back-to-dashboard intentionally removed — use the sidebar to navigate -->
            <div class="action-buttons">
                <span id="autoSaveIndicator" class="save-indicator"><i class='bx bx-check'></i> Preferences saved</span>
            </div>
        </div>

        <div class="profile-content">
            <div class="section-header">
                <h1><i class='bx bx-cog'></i> Settings</h1>
                <p>Manage your study preferences, security features, and data options.</p>
            </div>

            <div id="notification-area"></div>

            <div class="settings-container">
                
                <div class="settings-card">
                    <h3><i class='bx bx-book-open'></i> Study Preferences</h3>
                    <form id="preferences-form">
                        <input type="hidden" name="action" value="update_preferences">
                        <div class="setting-group">
                            <label for="defaultSubject">Default Subject Context</label>
                            <div class="password-wrapper">
                                <i class='bx bx-book-content input-icon'></i>
                                <select id="defaultSubject" name="defaultSubject" class="form-input autosave-trigger" style="padding-left: 2.5rem;">
                                    <option value="Database" <?= $pref_subject == 'Database' ? 'selected' : '' ?>>Database Management Systems</option>
                                    <option value="Web Dev" <?= $pref_subject == 'Web Dev' ? 'selected' : '' ?>>Web Development</option>
                                    <option value="Data Structures" <?= $pref_subject == 'Data Structures' ? 'selected' : '' ?>>Data Structures & Algo</option>
                                    <option value="Networks" <?= $pref_subject == 'Networks' ? 'selected' : '' ?>>Computer Networks</option>
                                </select>
                            </div>
                        </div>
                        <div class="setting-toggle-wrapper">
                            <div class="setting-toggle">
                                <span>Enable Pomodoro Sound Notifications</span>
                                <label class="switch">
                                    <input type="checkbox" name="pomodoro_sound" class="autosave-trigger" <?= $pref_sound ? 'checked' : '' ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>
                        </div>
                        <div class="setting-toggle-wrapper">
                            <div class="setting-toggle">
                                <span>AI Scheduler Auto-Prioritization</span>
                                <label class="switch">
                                    <input type="checkbox" name="ai_priority" class="autosave-trigger" checked> 
                                    <span class="slider"></span>
                                </label>
                            </div>
                        </div>
                    </form>
                    <div style="margin-top:14px; display:flex; gap:8px; align-items:center;">
                        <button id="btnShowTutorialAgain" class="btn-save-settings" style="max-width:320px;">📘 Show tutorial again</button>
                        <div style="color:#9aa7c7; font-size:0.9rem;">Restart the guided tour and tips on the dashboard.</div>
                    </div>
                </div>

                <div class="settings-card">
                    <h3><i class='bx bx-lock-alt'></i> Security & Password</h3>
                    <form id="security-form">
                        <input type="hidden" name="action" value="update_security">
                        
                        <div class="setting-group">
                            <label for="currentPassword">Current Password</label>
                            <div class="password-wrapper">
                                <i class='bx bx-key input-icon'></i>
                                <input type="password" id="currentPassword" name="currentPassword" placeholder="Required to change password" class="form-input" required>
                                <i class='bx bx-show toggle-password' onclick="togglePassword('currentPassword', this)"></i>
                            </div>
                            <div class="error-text" id="msg-currentPassword"></div>
                        </div>
                        
                        <div class="setting-group">
                            <label for="newPassword">New Password</label>
                            <div class="password-wrapper">
                                <i class='bx bx-lock-alt input-icon'></i>
                                <input type="password" id="newPassword" name="newPassword" placeholder="Enter new password" class="form-input" required>
                                <i class='bx bx-show toggle-password' onclick="togglePassword('newPassword', this)"></i>
                            </div>
                            <div class="error-text" id="msg-newPassword"></div>
                        </div>

                        <div class="setting-group">
                            <label for="confirmPassword">Confirm Password</label>
                            <div class="password-wrapper">
                                <i class='bx bx-lock-alt input-icon'></i>
                                <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirm new password" class="form-input" required>
                                <i class='bx bx-show toggle-password' onclick="togglePassword('confirmPassword', this)"></i>
                            </div>
                            <div class="error-text" id="msg-confirmPassword"></div>
                        </div>

                        <button type="button" class="btn-save-settings" id="btnUpdatePass">Update Password</button>
                    </form>
                </div>
                
                <div class="settings-card danger-card">
                    <h3><i class='bx bx-error'></i> Danger Zone</h3>
                    <p>These actions are permanent and cannot be undone. Proceed with caution.</p>
                    
                    <div class="danger-actions-wrapper"> 
                        <button type="button" id="btnDownload" class="btn-save-settings btn-download"><i class='bx bx-download'></i> Download My Study Data</button>
                        <form id="formDeleteData"><input type="hidden" name="action" value="delete_data"><button type="button" class="btn-save-settings btn-delete-data" id="btnDeleteData"><i class='bx bx-trash'></i> Delete All Study Data</button></form>
                        <form id="formDeleteAccount"><input type="hidden" name="action" value="delete_account"><button type="button" class="btn-save-settings btn-delete-account" id="btnDeleteAccount"><i class='bx bx-user-x'></i> Delete Account Permanently</button></form>
                    </div>
                </div>

            </div>
        </div>
    </div>
    </main>

    <script src="js/main.js"></script>
    <script src="js/sidebar.js"></script>
    <script src="js/settings.js?v=<?php echo time(); ?>"></script> 
    
    <script>
        // 1. Toggle Password Visibility
        function togglePassword(id, icon) {
            const input = document.getElementById(id);
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('bx-show', 'bx-hide');
            } else {
                input.type = 'password';
                icon.classList.replace('bx-hide', 'bx-show');
            }
        }
        
        // Loader Fade
        document.addEventListener('DOMContentLoaded', function() {
            // page loader removed (no fade/loader present)

            // --- PASSWORD UPDATE LOGIC (INLINE ERRORS) ---
            const btnUpdatePass = document.getElementById('btnUpdatePass');
            if(btnUpdatePass) {
                btnUpdatePass.addEventListener('click', function() {
                    const currentPass = document.getElementById('currentPassword').value;
                    const newPass = document.getElementById('newPassword').value;
                    const confirmPass = document.getElementById('confirmPassword').value;
                    
                    // Clear previous errors
                    clearErrors();
                    
                    let hasError = false;

                    // Client-side Validation
                    if(!currentPass) {
                        showInlineError('currentPassword', 'Current password is required.');
                        hasError = true;
                    }
                    if(!newPass) {
                        showInlineError('newPassword', 'New password is required.');
                        hasError = true;
                    }
                    if(!confirmPass) {
                        showInlineError('confirmPassword', 'Please confirm your password.');
                        hasError = true;
                    }
                    
                    if(newPass && confirmPass && newPass !== confirmPass) {
                         showInlineError('confirmPassword', 'Passwords do not match!');
                         hasError = true;
                    }

                    if(hasError) return;

                    // AJAX Request
                    const formData = new FormData();
                    formData.append('action', 'update_security');
                    formData.append('currentPassword', currentPass);
                    formData.append('newPassword', newPass);
                    formData.append('confirmPassword', confirmPass);

                    const btn = this;
                    btn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Updating...';
                    btn.disabled = true;

                    fetch('settings.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        btn.innerHTML = 'Update Password';
                        btn.disabled = false;
                        
                        if (data.status === 'success') {
                            showSuccessNotification(data.message);
                            document.getElementById('security-form').reset();
                        } else {
                            // Handle Server-side Errors Inline
                            if(data.field) {
                                showInlineError(data.field, data.message);
                            } else if (data.message.includes('Incorrect')) {
                                showInlineError('currentPassword', data.message);
                            } else if (data.message.includes('match')) {
                                showInlineError('confirmPassword', data.message);
                            } else {
                                // Fallback to general alert if field is unknown
                                showSuccessNotification(data.message, 'error');
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        btn.innerHTML = 'Update Password';
                        btn.disabled = false;
                    });
                });
            }
        });

        // Helper: Show Inline Error
        function showInlineError(fieldId, message) {
            const input = document.getElementById(fieldId);
            const msgBox = document.getElementById('msg-' + fieldId);
            
            if(input) {
                input.classList.add('input-error');
                // Add shake animation
                input.style.animation = 'shake 0.3s';
                setTimeout(() => input.style.animation = '', 300);
            }
            
            if(msgBox) {
                msgBox.innerHTML = `<i class='bx bx-error-circle'></i> ${message}`;
                msgBox.style.display = 'flex';
            }
        }

        // Helper: Clear Errors
        function clearErrors() {
            document.querySelectorAll('.form-input').forEach(el => el.classList.remove('input-error'));
            document.querySelectorAll('.error-text').forEach(el => el.style.display = 'none');
            const area = document.getElementById('notification-area');
            if(area) area.innerHTML = '';
        }
        
        // Helper: Success Notification (Top)
        function showSuccessNotification(message, type = 'success') {
            const area = document.getElementById('notification-area');
            const icon = type === 'success' ? "<i class='bx bx-check-circle'></i>" : "<i class='bx bx-error'></i>";
            area.innerHTML = `<div class="alert-box ${type}">${icon} ${message}</div>`;
            
            // Auto-hide after 3 seconds
            setTimeout(() => {
                const alert = area.querySelector('.alert-box');
                if(alert) {
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                }
            }, 3000);
        }
        
        // Add shake animation style dynamically
        const style = document.createElement('style');
        style.innerHTML = `
            @keyframes shake {
                0% { transform: translateX(0); }
                25% { transform: translateX(-5px); }
                50% { transform: translateX(5px); }
                75% { transform: translateX(-5px); }
                100% { transform: translateX(0); }
            }
        `;
        document.head.appendChild(style);

        // Auto-Save Logic
        const autoSaveTriggers = document.querySelectorAll('.autosave-trigger');
        const saveIndicator = document.getElementById('autoSaveIndicator');
        autoSaveTriggers.forEach(trigger => {
            trigger.addEventListener('change', function() {
                const formData = new FormData(document.getElementById('preferences-form'));
                fetch('settings.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    if(data.status === 'success') {
                        saveIndicator.style.display = 'flex';
                        saveIndicator.style.opacity = '1';
                        setTimeout(() => { saveIndicator.style.opacity = '0'; }, 2000);
                    }
                });
            });
        });
        
        // Danger Zone Modal Logic
        const modal = document.getElementById('customModal');
        const btnCancel = document.getElementById('modalCancel');
        const btnConfirm = document.getElementById('modalConfirm');
        let actionToConfirm = null;

        document.getElementById('btnDeleteData').addEventListener('click', () => {
            actionToConfirm = 'delete_data';
            document.getElementById('modalTitle').innerText = 'Delete All Study Data?';
            modal.style.display = 'flex';
        });

        document.getElementById('btnDeleteAccount').addEventListener('click', () => {
            actionToConfirm = 'delete_account';
            document.getElementById('modalTitle').innerText = 'Delete Account Permanently?';
            modal.style.display = 'flex';
        });

        btnCancel.addEventListener('click', () => { modal.style.display = 'none'; actionToConfirm = null; });
        
        btnConfirm.addEventListener('click', () => {
            if(actionToConfirm) {
                const formId = actionToConfirm === 'delete_data' ? 'formDeleteData' : 'formDeleteAccount';
                const formData = new FormData(document.getElementById(formId));
                
                fetch('settings.php', { method: 'POST', body: formData })
                .then(res => res.text())
                .then(res => {
                    if(res.includes('success')) {
                        if(actionToConfirm === 'delete_account') window.location.href = 'index.php';
                        else {
                            showSuccessNotification('Data deleted successfully.', 'success');
                            modal.style.display = 'none';
                        }
                    } else {
                        showSuccessNotification('Action failed.', 'error');
                        modal.style.display = 'none';
                    }
                });
            }
        });
    </script>

    <?php include 'includes/call_overlay.php'; ?> 
</body>
</html>