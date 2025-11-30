<?php
include 'messaging/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit();
}

$current_user_id = $_SESSION['user_id'];
$open_chat_id = $_GET['user_id'] ?? $_GET['group_id'] ?? 'null';
$open_chat_type = isset($_GET['group_id']) ? 'group' : (isset($_GET['user_id']) ? 'user' : 'null');

// 1. FETCH USER DATA & PREFS
$stmt = $conn->prepare("SELECT firstname, lastname, profile_photo, active_status, notification_sound, do_not_disturb, app_lock_enabled, dark_mode_pref FROM users WHERE id = ?");
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$current_user_name = htmlspecialchars($user['firstname'] ?? 'User');
$profile_photo_db = $user['profile_photo'] ?? '';
$p_active = $user['active_status'] ?? 1;
$p_sound = $user['notification_sound'] ?? 1;
$p_dnd = $user['do_not_disturb'] ?? 0;

// Avatar Logic
$avatar_html = '';
if (!empty($profile_photo_db) && file_exists('profile-img/' . $profile_photo_db)) {
    $avatar_src = 'profile-img/' . htmlspecialchars($profile_photo_db);
    $avatar_html = "<img src='$avatar_src' alt='User' style='width: 100%; height: 100%; object-fit: cover; border-radius: 50%;'>";
} else {
    $initial = strtoupper(substr($current_user_name, 0, 1));
    $avatar_html = $initial;
}

// 2. FETCH LISTS (Archived, Requests, Restricted, Blocked)
function fetchList($conn, $sql, $id) {
    $list = [];
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) { $list[] = $row; }
        $stmt->close();
    }
    return $list;
}

$archived_chats = fetchList($conn, "SELECT u.id, u.firstname, u.lastname, u.profile_photo FROM chat_settings cs JOIN users u ON cs.partner_id = u.id WHERE cs.user_id = ? AND cs.is_archived = 1", $current_user_id);
$msg_requests = fetchList($conn, "SELECT u.id, u.firstname, u.lastname, u.profile_photo FROM chat_settings cs JOIN users u ON cs.partner_id = u.id WHERE cs.user_id = ? AND cs.is_request = 1", $current_user_id);
$restricted_users = fetchList($conn, "SELECT u.id, u.firstname, u.lastname, u.profile_photo FROM muted_users m JOIN users u ON m.muted_user_id = u.id WHERE m.user_id = ?", $current_user_id);
$blocked_users_list = fetchList($conn, "SELECT u.id, u.firstname, u.lastname, u.profile_photo FROM blocked_users b JOIN users u ON b.blocked_id = u.id WHERE b.blocker_id = ?", $current_user_id);

// 3. FETCH TARGET CHAT DETAILS (Server-Side Preload)
$initial_chat_name = "";
$initial_chat_avatar = "";

if ($open_chat_type === 'user' && $open_chat_id !== 'null') {
    $stmt_t = $conn->prepare("SELECT firstname, lastname, profile_photo FROM users WHERE id = ?");
    $stmt_t->bind_param("i", $open_chat_id);
    $stmt_t->execute();
    $res_t = $stmt_t->get_result();
    if ($row_t = $res_t->fetch_assoc()) {
        $initial_chat_name = htmlspecialchars($row_t['firstname'] . ' ' . $row_t['lastname']);
        $p_photo = $row_t['profile_photo'];
        if (!empty($p_photo) && file_exists('profile-img/' . $p_photo)) {
            $initial_chat_avatar = "<img src='profile-img/" . htmlspecialchars($p_photo) . "' style='width:100%;height:100%;object-fit:cover;border-radius:50%;'>";
        } else {
            $initial_chat_avatar = "<div style='width:100%;height:100%;background:#475569;color:white;display:flex;align-items:center;justify-content:center;border-radius:50%;font-weight:bold;'>" . strtoupper(substr($initial_chat_name, 0, 1)) . "</div>";
        }
    }
} elseif ($open_chat_type === 'group' && $open_chat_id !== 'null') {
    $stmt_g = $conn->prepare("SELECT group_name FROM groups WHERE group_id = ?");
    $stmt_g->bind_param("i", $open_chat_id);
    $stmt_g->execute();
    $res_g = $stmt_g->get_result();
    if ($row_g = $res_g->fetch_assoc()) {
        $initial_chat_name = htmlspecialchars($row_g['group_name']);
        $initial_chat_avatar = "<div style='width:100%;height:100%;background:#6366f1;color:white;display:flex;align-items:center;justify-content:center;border-radius:50%;font-weight:bold;'><i class='bx bx-group' style='font-size:1.2rem;'></i></div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messaging - SmartStudy</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/messaging.css">
    <?php include __DIR__ . '/includes/layout_preamble.php'; ?>
    <link rel="stylesheet" href="css/layout.css">
    <link rel="stylesheet" href="css/loading.css">
    <!-- dashboard.css removed: messaging has its own styles in css/messaging.css -->
    
    <style>
        /* Inline animations/styles specific to messaging behavior */
        @keyframes pulse { 0% { opacity: 1; transform: scale(1); } 50% { opacity: 0.7; transform: scale(1.05); } 100% { opacity: 1; transform: scale(1); } }
        @keyframes slideDown { from { top: 65px; opacity: 0; } to { top: 85px; opacity: 1; } }
        
        #btnGoToUnread {
            position: absolute; top: 85px; left: 50%; transform: translateX(-50%);
            background: #ef4444; color: white; padding: 8px 16px; border-radius: 20px;
            font-size: 0.85rem; font-weight: 600; cursor: pointer; z-index: 80;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3); display: none; border: none; animation: slideDown 0.3s ease;
        }

        /* Modal Utilities */
        .sub-modal-content { width: 500px; max-width: 95%; background: var(--dark-card); border: 1px solid var(--border-color); border-radius: 12px; height: 600px; display: flex; flex-direction: column; overflow: hidden; text-align: left; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5); }
        .sub-modal-header { padding: 15px 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
        .sub-modal-header h2 { margin: 0; font-size: 1.2rem; color: var(--text-light); }
        .sub-modal-body { padding: 20px; overflow-y: auto; flex-grow: 1; }
        
        .empty-state-container { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%; color: var(--text-gray); text-align: center; }
        .list-item-row { display: flex; align-items: center; justify-content: space-between; padding: 12px; border-bottom: 1px solid var(--border-color); transition: background 0.2s; }
        .list-item-row:hover { background: var(--bg-hover); }
        .list-item-info { display: flex; align-items: center; gap: 10px; color: var(--text-light); }
        .list-btn { background: var(--bg-input); border: none; padding: 6px 12px; border-radius: 6px; color: var(--text-light); cursor: pointer; font-size: 0.85rem; }
        .list-btn:hover { background: var(--color-unread); color: white; }

        /* Dropdowns & Context Menus */
        .sidebar-dropdown { position: absolute; top: 70px; left: 20px; width: 280px; background-color: #1e293b; border: 1px solid #334155; border-radius: 12px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5); display: none; z-index: 100; flex-direction: column; padding: 8px; }
        .sidebar-dropdown.active { display: flex; }
        .dropdown-item { padding: 12px 15px; color: #f8fafc; cursor: pointer; border-radius: 8px; display: flex; align-items: center; gap: 12px; font-size: 0.95rem; font-weight: 500; transition: background 0.2s; }
        .dropdown-item:hover { background-color: #334155; }
        .dropdown-divider { border-top: 1px solid #334155; margin: 8px 0; }

        .chat-list-context-menu { position: fixed; background: var(--dark-card); border: 1px solid var(--border-color); border-radius: 8px; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.5); z-index: 9999; display: none; flex-direction: column; min-width: 200px; overflow: hidden; }
        .chat-list-context-menu.active { display: flex; }
        .chat-list-option { padding: 12px 15px; text-align: left; background: none; border: none; color: var(--text-light); cursor: pointer; font-size: 0.9rem; display: flex; align-items: center; gap: 10px; transition: background 0.2s; }
        .chat-list-option:hover { background: var(--bg-hover); }
        .chat-list-option.danger { color: #ef4444; }
        .chat-list-option.danger:hover { background: rgba(239, 68, 68, 0.1); }

        
        /* Override Gamer Tag Position (Force Static inside Flex) */
        .gametag-overlay-container.messaging-mode {
            position: static !important;
            transform: none !important;
            margin: 0 !important;
        }

        /* Messaging header should match other pages: simple centered header */
        .section-header { text-align: center; margin-bottom: 0.5rem; }
        .section-header h1 { margin: 0; font-size: 1.4rem; }
    </style>
</head>

<body
      data-open-chat-id="<?php echo $open_chat_id; ?>"
      data-open-chat-type="<?php echo $open_chat_type; ?>" 
      data-init-name="<?php echo $initial_chat_name; ?>"
      data-init-avatar="<?php echo htmlspecialchars($initial_chat_avatar); ?>">
    <?php include 'includes/mobile_blocker.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    <!-- page loader / preserve original messaging layout -->
    <div class="page-loader">
    <div class="loader-container">
<div class="loader-icon"><i class='bx bx-brain' style="font-size: 4rem; color: #6366f1;"></i></div>        <div class="loader-text" style="color: #cbd5e1; font-family: 'Inter', sans-serif; margin-bottom: 10px;"></div>
        <div class="loader-spinner"><div class="spinner-ring"></div></div>
    </div>
</div>
    <input type="hidden" id="currentUserId" value="<?php echo $current_user_id; ?>">
    <div id="notification-container"></div>

    <main class="main-content full-width-layout">
        <section id="messaging-section" class="content-section active">
            
            <div class="section-header">
                <h1><i class='bx bxs-message-rounded-dots'></i> Messages</h1>
                <p style="color:var(--text-gray); margin-top:6px;">Search, open a chat, and collaborate with classmates</p>
            </div>

            <div class="messaging-container" style="position: relative;">
                <div id="sidebarDropdown" class="sidebar-dropdown">
                    <div class="dropdown-item" id="menuPreferences"><span class="icon"><i class="bx bx-cog"></i></span> Preferences</div>
                    <div class="dropdown-divider"></div>
                    <div class="dropdown-item" id="menuMsgRequests"><span class="icon"><i class="bx bx-message"></i></span> Message requests</div>
                    <div class="dropdown-item" id="menuArchived"><span class="icon"><i class="bx bx-archive"></i></span> Archived chats</div>
                    <div class="dropdown-item" id="menuRestricted"><span class="icon"><i class="bx bx-volume-mute"></i></span> Restricted accounts</div>
                        <div class="dropdown-item" id="menuBlocked"><span class="icon"><i class="bx bx-block"></i></span> Blocked accounts</div>
                    <div class="dropdown-divider"></div>
                        <div class="dropdown-item" id="menuPrivacy"><span class="icon"><i class="bx bx-shield"></i></span> Privacy & safety</div>
                    <div class="dropdown-item" id="menuHelp"><span class="icon"><i class="bx bx-help-circle"></i></span> Help</div>
                </div>

                <div class="chat-list-panel">
                    <div class="chat-list-header">
                        <h2>Chats</h2>
                        <div class="header-actions">
                            <button class="btn-icon" id="chatOptionsButton">...</button>
                            <button class="btn-icon" id="newMessageButton"><i class="bx bx-edit-alt"></i></button>
                        </div>
                    </div>
                    <div class="search-bar"><input type="text" placeholder="Search Messenger..." id="searchInput"></div>
                    <div class="chat-filter-buttons">
                        <button class="filter-btn active" data-filter="all">All</button>
                        <button class="filter-btn" data-filter="unread">Unread</button>
                        <button class="filter-btn" data-filter="groups">Groups</button>
                    </div>
                    <div class="chat-list" id="chatList">
                        <p style='text-align:center; color: var(--text-gray); padding: 20px;'>Loading chats...</p>
                    </div>
                </div>

                <div class="chat-window-panel" id="chatWindow">
                    <div class="chat-placeholder"><span class="placeholder-icon"><i class="bx bx-message"></i></span>
                        <h3>Select a chat</h3>
                        <p>Search for users to start a conversation.</p>
                    </div>
                </div>

                <div class="chat-details-panel" id="chatDetails">
                    <div class="details-placeholder"><span class="placeholder-icon"><i class="bx bx-info-circle"></i></span>
                        <h3>Chat Details</h3>
                        <p>Click the 'Info' icon on a chat to see details.</p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <button id="btnGoToUnread"><i class="bx bx-up-arrow-alt"></i> Go to Unread</button>

    <div id="msgRequestsModal" class="modal-overlay" style="z-index: 2001;">
        <div class="sub-modal-content">
            <div class="sub-modal-header"><h2>Message requests</h2><button class="modal-close-btn-icon close-sub-modal">&times;</button></div>
            <div class="sub-modal-body" style="padding: 0; display: flex; flex-direction: column;">
                <div id="msgRequestContainer" style="width: 100%; flex-grow: 1;">
                    <p style="text-align:center; padding:20px; color:#888;">Loading requests...</p>
                </div>
            </div>
        </div>
    </div>

    <div id="preferencesModal" class="modal-overlay" style="z-index: 2000;">
        <div class="modal-content preferences-content">
            <div class="pref-header"><h2>Preferences</h2><button class="modal-close-btn-icon" id="closePreferencesModal">&times;</button></div>
            <div class="pref-scroll-area">
                <div class="pref-section">
                    <h3>Account</h3>
                    <div class="pref-user-row">
                        <div class="pref-avatar"><?php echo $avatar_html; ?></div>
                        <div class="pref-info"><h4><?php echo $current_user_name; ?></h4><a href="profile.php">See your profile</a></div>
                    </div>
                    <div class="pref-item">
                        <div class="pref-label"><span>Active Status</span></div>
                        <label class="switch"><input type="checkbox" id="toggleActiveStatus" autocomplete="off" <?php echo ($p_active == 1) ? 'checked' : ''; ?>><span class="slider"></span></label>
                    </div>
                </div>
                <div class="pref-section">
                    <h3>Notifications</h3>
                    <div class="pref-item">
                        <div class="pref-label"><span>Notification sounds</span></div>
                        <label class="switch"><input type="checkbox" id="toggleNotifSounds" autocomplete="off" <?php echo ($p_sound == 1) ? 'checked' : ''; ?>><span class="slider"></span></label>
                    </div>
                    <div class="pref-item">
                        <div class="pref-label"><span>Do Not Disturb</span></div>
                        <label class="switch"><input type="checkbox" id="toggleDND" autocomplete="off" <?php echo ($p_dnd == 1) ? 'checked' : ''; ?>><span class="slider"></span></label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="archivedModal" class="modal-overlay" style="z-index: 2001;">
        <div class="sub-modal-content">
            <div class="sub-modal-header"><h2>Archived chats</h2><button class="modal-close-btn-icon close-sub-modal">&times;</button></div>
            <div class="sub-modal-body">
                <div id="archivedListContainer">
                    <?php if (empty($archived_chats)): ?>
                        <div class="empty-state-container"><div style="font-size: 3rem; opacity: 0.5; margin-bottom: 10px;"><i class="bx bx-archive" style="font-size:3rem;opacity:0.5;margin-bottom:10px;"></i></div><p class="empty-state-text">No archived chats.</p></div>
                    <?php else: ?>
                        <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div id="blockedModal" class="modal-overlay" style="z-index: 2001;">
        <div class="sub-modal-content">
            <div class="sub-modal-header"><h2>Blocked accounts</h2><button class="modal-close-btn-icon close-sub-modal">&times;</button></div>
            <div class="sub-modal-body">
                <p style="color:var(--text-gray); font-size:0.85rem; margin-bottom:20px; padding: 0 10px;">Blocked people can't message you.</p>
                <div id="blockedListContainer"></div>
            </div>
        </div>
    </div>

    <div id="restrictedModal" class="modal-overlay" style="z-index: 2001;">
        <div class="sub-modal-content">
            <div class="sub-modal-header"><h2>Restricted accounts</h2><button class="modal-close-btn-icon close-sub-modal">&times;</button></div>
            <div class="sub-modal-body"><div id="restrictedListContainer"></div></div>
        </div>
    </div>

    <div id="privacyModal" class="modal-overlay" style="z-index: 2001;">
        <div class="sub-modal-content">
            <div class="sub-modal-header"><h2>Privacy & safety</h2><button class="modal-close-btn-icon close-sub-modal">&times;</button></div>
            <div class="sub-modal-body"><div class="dropdown-item" id="btnBlockedAccounts"><span class="icon"><i class="bx bx-block"></i></span> Blocked Accounts</div></div>
                <div class="sub-modal-body"><div class="dropdown-item" id="btnBlockedAccounts"><span class="icon"><i class="bx bx-block"></i></span> Blocked Accounts</div></div>
        </div>
    </div>
    <div id="helpModal" class="modal-overlay" style="z-index: 2001;">
        <div class="sub-modal-content">
            <div class="sub-modal-header"><h2>Help</h2><button class="modal-close-btn-icon close-sub-modal">&times;</button></div>
            <div class="sub-modal-body"><div class="dropdown-item">Report a problem</div></div>
        </div>
    </div>

    <div id="uploadModal" class="modal-overlay">
    <div class="modal-content">
        <div class="upload-header">
            <h3>Upload Content</h3>
            <button class="close-upload-btn" id="modalCloseButton">&times;</button>
        </div>
        
        <div class="upload-options">
            <label for="photoUpload" class="upload-option-card">
                <span class="upload-icon"><i class="bx bx-image"></i></span>
                <span class="upload-label">Gallery</span>
                <span class="upload-desc">Photos & Videos</span>
                <input type="file" id="photoUpload" accept="image/*,video/*" hidden>
            </label>

            <label for="fileUpload" class="upload-option-card">
                <span class="upload-icon"><i class="bx bx-file"></i></span>
                <span class="upload-label">File</span>
                <span class="upload-desc">Docs, PDF, Zip</span>
                <input type="file" id="fileUpload" accept=".pdf,.doc,.docx,.zip,.rar,.txt" hidden>
            </label>
        </div>
    </div>
</div>

    <!-- global customConfirmModal moved to includes/call_overlay.php -->

    <div id="voteModal" class="modal-overlay">
        <div class="modal-content">
            <h3>Delete?</h3>
            <select id="deleteTimeSelect" style="width:100%;margin-bottom:10px;padding:10px;"><option value="Immediately">Immediately</option></select>
            <button id="btnStartVote" class="modal-option" style="width:100%;background:red;color:white;">Start Vote</button>
            <button id="btnCloseVoteModal" class="modal-close-btn">Cancel</button>
        </div>
    </div>

    <div id="imagePreviewModal" class="image-preview-modal">
        <div class="preview-top-bar">
            <button class="modal-close-btn" id="imageModalCloseButton">&times;</button>
            <div class="preview-actions" style="position:relative;">
                <button id="previewMoreBtn" class="btn-icon" style="color:white; font-size:1.5rem; background:transparent; border:none; cursor:pointer;">⋮</button>
                <div id="previewMenu" class="preview-menu">
                    <div class="menu-info"><strong id="previewFilename">image.png</strong></div>
                    <button id="previewDeleteForMe" class="menu-item"><i class="bx bx-trash"></i> Delete for me</button>
                    <button id="previewDeleteEveryone" class="menu-item danger"><i class="bx bx-error" style="margin-right:6px;"></i> Delete for everyone</button>
                </div>
            </div>
        </div>
        <div class="preview-container">
                <button class="nav-btn prev-btn" id="prevImageBtn"><i class="bx bx-chevron-left"></i></button>
            <img src="" alt="Preview" id="fullScreenImage">
            <button class="nav-btn next-btn" id="nextImageBtn"><i class="bx bx-chevron-right"></i></button>
        </div>
        <a id="downloadImageBtn" href="#" class="btn-download-image" download><i class="bx bx-download"></i> Download</a>
    </div>

    <div id="reactionPicker" class="reaction-picker">
        <button class="reaction-emoji" data-emoji="👍">👍</button><button class="reaction-emoji" data-emoji="❤️">❤️</button><button class="reaction-emoji" data-emoji="😂">😂</button><button class="reaction-emoji" data-emoji="😮">😮</button><button class="reaction-emoji" data-emoji="😢">😢</button><button class="reaction-emoji" data-emoji="😡">😡</button>
    </div>

    <div id="newChatModal" class="modal-overlay">
        <div class="modal-content new-chat-content">
            <div class="modal-header"><h3>New Chat</h3><button class="modal-close-btn" id="closeNewChatModal">&times;</button></div>
            <div class="modal-body">
                <div class="selected-users-area" id="selectedUsersArea"><input type="text" id="userSearchInput" placeholder="To:"></div>
                <div class="search-results-list" id="searchResultsList"></div>
                <div id="groupNameSection" style="display:none;"><input type="text" id="groupNameInput" placeholder="Group Name"></div>
            </div>
            <div class="modal-footer"><button id="btnCreateChat" class="btn-primary" disabled>Chat</button></div>
        </div>
    </div>

    <div id="membersModal" class="modal-overlay">
        <div class="modal-content">
            <div style="display:flex;justify-content:space-between;align-items:center;padding:15px 20px;border-bottom:1px solid var(--border-color);">
                <h3 style="margin:0;">Members</h3>
                <button id="closeMembersModal" style="background:none;border:none;color:var(--text-gray);font-size:1.5rem;cursor:pointer;"><i class='bx bx-x'></i></button>
            </div>
            <div id="membersListContainer"><p style="text-align:center; padding:20px; color:#888;">Loading...</p></div>
        </div>
    </div>

    <div id="chatListContextMenu" class="chat-list-context-menu"></div>

    <div id="callModal" class="modal-overlay" style="background:black; z-index:9999; display:none;">
        <div class="modal-content" style="background:transparent; border:none; width:100%; height:100%; display:flex; flex-direction:column; align-items:center; justify-content:center;">
             <h2 style="color:white;">Call in Progress...</h2>
             <button id="endCallBtn" style="background:#ef4444; border:none; padding:15px; border-radius:50%; color:white; font-size:2rem; cursor:pointer; margin-top:20px;"><i class='bx bxs-phone-off'></i></button>
        </div>
    </div>
    
    <div id="callStatusModal" class="modal-overlay" style="z-index: 5000; display: none;">
        <div class="modal-content" style="max-width: 350px; text-align: center;">
            <div style="font-size: 3rem;" id="statusIcon"><i class='bx bxs-phone-off'></i></div>
            <h3 id="statusTitle">Call Ended</h3>
            <p id="statusMessage">The call was terminated.</p>
            <button id="closeStatusModal" class="modal-option" style="background: var(--primary-color); color:white; margin-top: 15px;">Close</button>
        </div>
    </div>
    
    <div id="incomingCallUI" class="modal-overlay" style="z-index: 3100; display: none; background: rgba(0,0,0,0.7);">
        <div class="incoming-card" style="background: #242526; width: 350px; padding: 30px; border-radius: 15px; text-align: center; box-shadow: 0 10px 40px rgba(0,0,0,0.5); position: relative;">
            <button id="btnCloseIncoming" style="position: absolute; top: 15px; right: 15px; background: rgba(255,255,255,0.1); border: none; color: #ccc; border-radius: 50%; width: 30px; height: 30px; cursor: pointer;">&times;</button>
            <div id="incomingAvatar" style="width: 80px; height: 80px; border-radius: 50%; overflow: hidden; margin: 0 auto 15px; border: 2px solid #3b82f6;"></div>
            <h3 id="incomingName" style="color: white; font-size: 1.3rem; margin-bottom: 5px;">User</h3>
            <p style="color: #e4e6eb; font-weight: 500; font-size: 1.1rem;">is calling you</p>
            <p style="color: #b0b3b8; font-size: 0.8rem; margin-top: 5px;"><i class="bx bx-lock"></i> End-to-end encrypted</p>
            <div style="display: flex; justify-content: center; gap: 40px; margin-top: 30px;">
                <div style="text-align: center;">
                    <button id="btnDeclineCall" style="background: #ef4444; width: 60px; height: 60px; border-radius: 50%; border: none; color: white; font-size: 1.5rem; cursor: pointer; display: flex; align-items: center; justify-content: center; margin-bottom: 8px;"><i class="bx bx-x"></i></button>
                    <span style="color: #b0b3b8; font-size: 0.8rem;">Decline</span>
                </div>
                <div style="text-align: center;">
                    <button id="btnAcceptCall" style="background: #22c55e; width: 60px; height: 60px; border-radius: 50%; border: none; color: white; font-size: 1.5rem; cursor: pointer; display: flex; align-items: center; justify-content: center; margin-bottom: 8px;"><i class="bx bx-phone"></i></button>
                    <span style="color: #b0b3b8; font-size: 0.8rem;">Accept</span>
                </div>
            </div>
        </div>
    </div>
    
    <div id="outgoingCallUI" class="modal-overlay" style="z-index: 3000; background: black; display: none;">
        <div class="call-ui-content" style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; width:100%;">
            <div id="outgoingAvatar" style="width: 120px; height: 120px; border-radius: 50%; overflow: hidden; border: 2px solid #333; margin-bottom: 20px;"></div>
            <h2 id="outgoingName" style="color: white; font-size: 1.5rem; font-weight: 600;">User</h2>
            <p style="color: #cbd5e1; margin-top: 5px; animation: pulse 1.5s infinite;">Calling...</p>
            <div style="margin-top: 50px;">
                <button id="btnCancelOutgoing" style="background: #ef4444; width: 60px; height: 60px; border-radius: 50%; border: none; color: white; font-size: 1.5rem; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4);"><i class='bx bxs-phone-off'></i></button> 
            </div>
        </div>
    </div>
    
    <button id="persistentCallBtn" style="position: fixed; top: 80px; right: 20px; z-index: 10000; background: #10b981; color: white; padding: 10px 20px; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.5); display: none; animation: pulseGreen 1.5s infinite;"><i class='bx bx-phone'></i> Back to Call</button>

    <script src="https://unpkg.com/peerjs@1.5.2/dist/peerjs.min.js"></script>
    <script src="js/main.js"></script>
    <script src="js/sidebar.js"></script>
    <script src="js/messaging.js?v=<?php echo time(); ?>"></script>
    <script>
    // Universal Loader Fade-out
    document.addEventListener('DOMContentLoaded', function() {
        const pageLoader = document.querySelector('.page-loader');
        if (pageLoader) {
            // Mabilis na fade-out (300ms)
            setTimeout(() => {
                pageLoader.classList.add('fade-out');
                // Tanggalin sa DOM after animation
                setTimeout(() => { pageLoader.style.display = 'none'; }, 1500);
            }, 1500); // Delay ng konti para di glitchy tingnan
        }
    });
</script>
    <!-- page loader removed -->
    <script>
        // Toast Logic
        window.showToast = function (title, message, icon = '<i class="bx bx-smile"></i>', linkUrl = null, variant = '') {
            const chatHeaderName = document.querySelector('.chat-header h3')?.textContent.trim();
            if (chatHeaderName && title.includes(chatHeaderName)) return;

            const container = document.getElementById('notification-container');
            if (!container) return;
            const toast = document.createElement('div');
            toast.style.cssText = "background:#1e293b; border-left:4px solid #6366f1; color:white; padding:15px; margin-bottom:10px; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.5); display:flex; align-items:center; gap:10px; min-width:300px; animation:slideIn 0.3s ease;";
            toast.innerHTML = `<div style='font-size:1.5rem;'>${icon}</div><div><h4 style='margin:0;font-size:1rem;'>${title}</h4><p style='margin:5px 0 0;color:#cbd5e1;font-size:0.9rem;'>${message}</p></div>`;
            container.appendChild(toast);
            setTimeout(() => { toast.style.opacity='0'; setTimeout(()=>toast.remove(),300); }, 5000);
        }
    </script>
    <?php include 'includes/call_overlay.php'; ?> 

</body>
</html>