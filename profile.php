<?php
// --- CONFIG & SESSION ---
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include 'messaging/config.php'; 

if ($conn->connect_error) { die("Connection failed: " . $conn->connect_error); }

// Authorization Check
if (!isset($_SESSION['user_id'])) { header('Location: auth.php'); exit(); }
$current_user_id = $_SESSION['user_id'];
$is_own_profile = true;
$user_id_to_view = isset($_GET['user_id']) && !empty($_GET['user_id']) ? (int)$_GET['user_id'] : $current_user_id;

if ($user_id_to_view != $current_user_id) { $is_own_profile = false; }

// ==========================================
// DYNAMIC BACK BUTTON LOGIC (Fixed)
// ==========================================
$back_link = 'dashboard.php'; // Default fallback
$back_text = '<i class="bx bx-arrow-back"></i> Back to Dashboard';

// Check if 'from' parameter exists
if (isset($_GET['from']) && !empty($_GET['from'])) {
    $from_page = basename($_GET['from']);
    
    // Allowed pages whitelist
    $allowed_pages = ['dashboard.php', 'learning_resources.php', 'messaging.php', 'messages', 'settings.php', 'tasks.php'];

    // Check if the passed page is in our allowed list (or contains 'messaging')
    if (in_array($from_page, $allowed_pages) || strpos($from_page, 'messag') !== false) {
        
        // SPECIAL CASE: Galing sa Messaging
        if ($from_page === 'messaging.php' || $from_page === 'messages') {
            $back_link = 'messaging.php'; // Force link to messaging.php
            $back_text = '<i class="bx bx-arrow-back"></i> Back to Messages'; // Force text change
            
            // Kung tinitingnan mo profile ng iba, bumalik sa chat nila
            if (!$is_own_profile) {
                $back_link .= '?user_id=' . $user_id_to_view;
            }
        } 
        // NORMAL CASE: Iba pang pages
        else {
            $back_link = $from_page;
            // Remove .php extension for prettier text
            $pretty_name = ucwords(str_replace(['.php', '_'], ['', ' '], $from_page));
            $back_text = '<i class="bx bx-arrow-back"></i> Back to ' . $pretty_name;
        }
    }
}
$message = '';

// --- MAIN SAVE ACTION ---
if ($is_own_profile && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_all') {
    
    // 1. Get Text Data
    $new_firstname = htmlspecialchars($_POST['firstname']);
    $new_lastname = htmlspecialchars($_POST['lastname']);
    $new_email = htmlspecialchars($_POST['email']);
    $new_bio = htmlspecialchars($_POST['bio']);
    $new_year_level = htmlspecialchars($_POST['year_level']);
    $new_section = htmlspecialchars($_POST['section']);
    $new_study_time = htmlspecialchars($_POST['study_time']);

    // 2. Get Current Photos (Default)
    $stmt = $conn->prepare("SELECT profile_photo, cover_photo FROM users WHERE id=?");
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $curr = $stmt->get_result()->fetch_assoc();
    $final_profile_photo = $curr['profile_photo'];
    $final_cover_photo = $curr['cover_photo'];

    // 3. Handle Profile Photo Upload
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === 0) {
        $file = $_FILES['profile_photo'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif']) && $file['size'] < 5000000) {
            $target_dir = "profile-img/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
            $newName = "profile_" . $current_user_id . "_" . time() . "." . $ext;
            if (move_uploaded_file($file['tmp_name'], $target_dir . $newName)) {
                $final_profile_photo = $newName;
                $conn->query("INSERT INTO user_photo_history (user_id, filename) VALUES ($current_user_id, '$newName')");
            }
        }
    }

    // 4. Handle Cover Photo Upload
    if (isset($_FILES['cover_photo']) && $_FILES['cover_photo']['error'] === 0) {
        $file = $_FILES['cover_photo'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif']) && $file['size'] < 10000000) {
            $target_dir = "cover-img/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
            $newName = "cover_" . $current_user_id . "_" . time() . "." . $ext;
            if (move_uploaded_file($file['tmp_name'], $target_dir . $newName)) {
                $final_cover_photo = $newName;
            }
        }
    }

    // 5. Update Database
    $update = $conn->prepare("UPDATE users SET firstname=?, lastname=?, email=?, bio=?, year_level=?, section=?, preferred_study_time=?, profile_photo=?, cover_photo=? WHERE id=?");
    $update->bind_param("sssssssssi", $new_firstname, $new_lastname, $new_email, $new_bio, $new_year_level, $new_section, $new_study_time, $final_profile_photo, $final_cover_photo, $current_user_id);
    
    if ($update->execute()) {
        $message = '✅ Profile updated successfully!';
        $_SESSION['firstname'] = $new_firstname;
        $_SESSION['lastname'] = $new_lastname;
    } else {
        $message = '❌ Error saving profile.';
    }
}

// --- FETCH DATA ---
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id_to_view);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
if (!$user) die("User not found.");

$firstname = $user['firstname'];
$lastname = $user['lastname'];
$email = $user['email'];
$student_id = $user['student_id'];
$program = $user['program']; 
$bio = $user['bio'];
$year_level = $user['year_level'] ?? '1st Year';
$section = $user['section'] ?? '';
$preferred_study_time = $user['preferred_study_time'] ?? 'Morning';
$current_points = $user['points'] ?? 0;

$profile_photo = (!empty($user['profile_photo']) && file_exists('profile-img/' . $user['profile_photo'])) ? 'profile-img/' . $user['profile_photo'] : '';
$cover_photo = (!empty($user['cover_photo']) && file_exists('cover-img/' . $user['cover_photo'])) ? 'cover-img/' . $user['cover_photo'] : 'images/default-cover.jpg';

$year_level_options = ['1st Year', '2nd Year', '3rd Year', '4th Year'];
$study_time_options = ['Morning' => 'Morning (6AM - 12PM)', 'Afternoon' => 'Afternoon (12PM - 6PM)', 'Evening' => 'Evening (6PM - 12AM)', 'Night' => 'Night (12AM - 6AM)'];

$stmt = $conn->prepare("SELECT SUM(duration_minutes) as total FROM study_sessions WHERE user_id=? AND is_completed=1");
$stmt->bind_param("i", $user_id_to_view);
$stmt->execute();
$total_study_hours = round(($stmt->get_result()->fetch_assoc()['total'] ?? 0) / 60, 1);

$stmt = $conn->prepare("SELECT COUNT(id) as cnt FROM user_achievements WHERE user_id=?");
$stmt->bind_param("i", $user_id_to_view);
$stmt->execute();
$achievement_count = $stmt->get_result()->fetch_assoc()['cnt'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - SmartStudy</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="css/loading.css">
    <?php include __DIR__ . '/includes/layout_preamble.php'; ?>
    <link rel="stylesheet" href="css/layout.css">
    <!-- dashboard.css removed: profile page has its own styling defined inline / in index.css -->
    
    <style>
        /* DARK THEME STYLES */
        :root { --primary: #6366f1; --primary-hover: #4f46e5; --bg-dark: #0f172a; --bg-card: #1e293b; --text-light: #f8fafc; --text-gray: #94a3b8; --border: #334155; --success: #10b981; --danger: #ef4444; }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-dark); color: var(--text-light); margin: 0; padding: 0; overflow-x: hidden; }
        .profile-container { max-width: 1000px; margin: 2rem auto; padding: 0 1.5rem; }
        
        /* ACTIONS */
        .profile-actions { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .btn-back { color: var(--text-gray); text-decoration: none; display: flex; align-items: center; gap: 0.5rem; font-weight: 500; transition: 0.3s; }
        .btn-back:hover { color: var(--primary); }
        .action-buttons { display: flex; gap: 1rem; }
        .btn-toggle-edit, .btn-save, .btn-logout { padding: 0.6rem 1.2rem; border-radius: 8px; font-weight: 600; border: none; cursor: pointer; display: flex; align-items: center; gap: 0.5rem; font-size: 0.9rem; transition: 0.3s; }
        .btn-toggle-edit { background: var(--bg-card); color: var(--text-light); border: 1px solid var(--border); }
        .btn-toggle-edit:hover { background: #334155; }
        .btn-save { background: var(--primary); color: white; }
        .btn-logout { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

        /* HEADER CARD */
        .profile-header-card { background: var(--bg-card); border-radius: 16px; overflow: hidden; border: 1px solid var(--border); margin-bottom: 2rem; position: relative; }
        .profile-banner { height: 250px; background-size: cover; background-position: center; position: relative; transition: background 0.3s; }
        
        /* Gradient Overlay & Text Shadow for Visibility */
        .profile-banner::after { content: ''; position: absolute; bottom: 0; left: 0; width: 100%; height: 100%; background: linear-gradient(to top, rgba(15, 23, 42, 0.95), rgba(15, 23, 42, 0.3) 60%, transparent); pointer-events: none; }

        /* Edit Controls (Hidden by Default) */
        .edit-controls { display: none; } 
        
        .btn-edit-cover { position: absolute; top: 1rem; right: 1rem; z-index: 10; background: rgba(0,0,0,0.6); color: white; padding: 0.5rem 1rem; border-radius: 8px; font-size: 0.85rem; cursor: pointer; border: 1px solid rgba(255,255,255,0.2); backdrop-filter: blur(4px); transition: 0.3s; }
        .btn-edit-cover:hover { background: rgba(0,0,0,0.8); }

        .profile-header-content { padding: 0 2rem 2rem 2rem; position: relative; display: flex; align-items: flex-end; gap: 1.5rem; margin-top: -5rem; z-index: 5; }
        
        .profile-photo-wrapper { width: 140px; height: 140px; border-radius: 50%; border: 4px solid var(--bg-card); background: var(--bg-dark); position: relative; flex-shrink: 0; box-shadow: 0 4px 15px rgba(0,0,0,0.5); }
        .profile-photo { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; }
        .default-avatar { width: 100%; height: 100%; background: var(--primary); color: white; font-size: 3.5rem; font-weight: 700; display: flex; align-items: center; justify-content: center; border-radius: 50%; }
        
        .photo-upload-btn { position: absolute; bottom: 5px; right: 5px; background: var(--bg-card); border: 1px solid var(--border); width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 1.1rem; box-shadow: 0 2px 5px rgba(0,0,0,0.3); transition: 0.3s; color: var(--text-light); }
        .photo-upload-btn:hover { background: var(--primary); color: white; border-color: var(--primary); }

        .profile-header-info { flex-grow: 1; padding-bottom: 0.5rem; color: white; text-shadow: 0 2px 8px rgba(0,0,0,0.8); }
        .profile-header-info h1 { font-size: 2rem; margin: 0; font-weight: 800; letter-spacing: -0.5px; }
        .profile-program-id { color: rgba(255,255,255,0.9); font-size: 1rem; margin: 0.25rem 0 0.75rem 0; font-weight: 500; }
        
        .profile-badges { display: flex; gap: 0.5rem; }
        .badge { font-size: 0.75rem; padding: 0.25rem 0.75rem; border-radius: 20px; font-weight: 600; text-transform: uppercase; box-shadow: 0 2px 5px rgba(0,0,0,0.5); }
        .rank-badge { background: #f59e0b; color: #fff; }
        .points-badge { background: #6366f1; color: #fff; }

        .profile-stats-mini-header { display: flex; gap: 2.5rem; padding-bottom: 1rem; text-shadow: 0 2px 8px rgba(0,0,0,0.9); }
        .stat-mini { display: flex; flex-direction: column; align-items: center; }
        .stat-value { font-weight: 800; font-size: 1.2rem; color: white; }
        .stat-label { font-size: 0.75rem; color: rgba(255,255,255,0.8); text-transform: uppercase; letter-spacing: 0.5px; }

        .profile-body-grid, .profile-edit-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; }
        .view-card, .edit-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 16px; padding: 1.5rem; margin-bottom: 1.5rem; }
        
        /* CARD HEADER FLEX FIX */
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
        .view-card h3, .edit-card h3 { margin: 0; font-size: 1.1rem; color: var(--text-light); display: flex; align-items: center; gap: 0.5rem; }
        
        /* VIEW ALL BUTTON FIX */
        .view-all { 
            font-size: 0.75rem; font-weight: 600; color: var(--primary); background: rgba(99, 102, 241, 0.1); 
            padding: 0.4rem 0.8rem; border-radius: 20px; text-decoration: none; transition: all 0.3s ease; border: 1px solid rgba(99, 102, 241, 0.2); 
        }
        .view-all:hover { background: var(--primary); color: white; box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3); transform: translateY(-1px); }

        .view-bio { font-style: italic; color: var(--text-gray); margin-bottom: 1.5rem; }
        .view-list li { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.75rem; color: var(--text-light); }
        .view-list .icon { color: var(--primary); font-size: 1.2rem; }
        
        /* ACHIEVEMENTS LAYOUT FIX */
        .achievements-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 1rem; }
        .achievement-item {
            background: rgba(15, 23, 42, 0.5); padding: 1rem; border-radius: 12px; text-align: center; border: 1px solid var(--border); 
            display: flex; flex-direction: column; align-items: center; justify-content: center; transition: transform 0.2s;
        }
        .achievement-item:hover { transform: translateY(-3px); border-color: var(--primary); }
        .achievement-icon { font-size: 2rem; display: block; margin-bottom: 0.5rem; }
        .achievement-info h4 { font-size: 0.8rem; margin: 0; color: var(--text-gray); line-height: 1.2; }
        
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-size: 0.85rem; color: var(--text-gray); margin-bottom: 0.4rem; }
        .form-input, .form-select, .form-textarea { width: 100%; padding: 0.6rem 1rem; background: var(--bg-dark); border: 1px solid var(--border); border-radius: 8px; color: white; box-sizing: border-box; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }

        /* Notification Toast */
        #notification-toast {
            position: fixed; top: 20px; right: 20px; z-index: 10000; padding: 15px 25px; border-radius: 10px; color: white; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.5); animation: slideIn 0.5s ease;
        }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes fadeOut { from { opacity: 1; } to { opacity: 0; } }

        @media (max-width: 900px) {
            .profile-body-grid, .profile-edit-grid { grid-template-columns: 1fr; }
            .profile-header-content { flex-direction: column; align-items: center; text-align: center; margin-top: -80px; }
            .profile-stats-mini-header { justify-content: center; width: 100%; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.1); }
        }
    </style>
</head>
<body class="profile-body">
    
    <?php include 'includes/mobile_blocker.php'; ?>
    <?php include 'includes/sidebar.php'; ?>
    <!-- page loader (restore) -->
    <div class="page-loader">
    <div class="loader-container">
        <div class="loader-icon"><i class='bx bxl-brain'></i></div>
        <div class="loader-text" style="color: #cbd5e1; font-family: 'Inter', sans-serif; margin-bottom: 10px;"></div>
        <div class="loader-spinner"><div class="spinner-ring"></div></div>
    </div>
</div>
    <main class="main-content">
    <?php if (!empty($message)): ?>
        <div id="notification-toast" style="background-color: <?php echo strpos($message, '❌') !== false ? '#ef4444' : '#10b981'; ?>;">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="profile-container">
        <div class="profile-actions">
            <!-- Back link removed per site navigation cleanup. Use sidebar to navigate. -->
            <?php if ($is_own_profile): ?>
                <div class="action-buttons">
                    <button class="btn-toggle-edit" id="toggleEditBtn" data-state="view"><i class='bx bx-edit-alt'></i> <span class="text">Edit Profile</span></button>
                    <button class="btn-save" id="saveProfile" type="submit" form="unified-profile-form" style="display:none;"><i class='bx bx-save'></i> Save Changes</button>
                    <a href="#" class="btn-logout openLogoutModal" data-href="php/logout.php"><i class='bx bx-log-out'></i> Logout</a>
                </div>
            <?php endif; ?>
        </div>
        
        <form id="unified-profile-form" action="profile.php?from=<?php echo $_GET['from'] ?? ''; ?>" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="save_all">
            
            <div class="profile-content">
                <div class="profile-header-card">
                    <div class="profile-banner" id="profileBanner" style="background-image: url('<?php echo $cover_photo; ?>');">
                        <?php if ($is_own_profile): ?>
                            <div class="edit-controls">
                                <label for="coverPhotoInput" class="btn-edit-cover" id="editCoverLabel"><i class='bx bx-camera'></i> Edit Cover</label>
                                <input type="file" id="coverPhotoInput" name="cover_photo" accept="image/*" style="display:none;" onchange="previewCover(this)">
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="profile-header-content">
                        <div class="profile-photo-section">
                            <div class="profile-photo-wrapper">
                                <?php if (empty($profile_photo)): ?>
                                    <div class="default-avatar" id="mainProfilePhoto"><?php echo strtoupper(substr($firstname, 0, 1)); ?></div>
                                <?php else: ?>
                                    <img src="<?php echo $profile_photo; ?>" alt="Profile" class="profile-photo" id="mainProfilePhoto">
                                <?php endif; ?>
                                
                                <?php if ($is_own_profile): ?>
                                    <div class="edit-controls">
                                        <label for="profilePhotoInput" class="photo-upload-btn"><i class='bx bx-camera'></i></label>
                                        <input type="file" id="profilePhotoInput" name="profile_photo" accept="image/*" style="display:none;" onchange="previewProfilePhoto(this)">
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="profile-header-info">
                            <h1><?php echo htmlspecialchars($firstname . ' ' . $lastname); ?></h1>
                            <p class="profile-program-id">
                                <span class="info-program"><?php echo htmlspecialchars($program); ?></span> <span class="info-divider">|</span> <span class="info-id">ID: <?php echo htmlspecialchars($student_id); ?></span>
                            </p>
                            <div class="profile-badges">
                                 <span class="badge rank-badge"><i class='bx bx-medal'></i> Rank #5</span>
                                 <span class="badge points-badge"><i class='bx bx-star'></i> <?php echo number_format($current_points); ?> Pts</span>
                            </div>
                        </div>
                        
                         <div class="profile-stats-mini-header">
                            <div class="stat-mini"><span class="stat-value"><?php echo $total_study_hours; ?>h</span><span class="stat-label">Study</span></div>
                            <div class="stat-mini"><span class="stat-value"><?php echo $achievement_count; ?></span><span class="stat-label">Awards</span></div>
                            <div class="stat-mini"><span class="stat-value">8</span><span class="stat-label">Subs</span></div>
                        </div>
                    </div>
                </div>

                <div class="profile-edit-grid" id="editFormContent" style="display: none;">
                     <div class="edit-card">
                        <div class="card-header"><h3><i class='bx bx-user'></i> Personal Info</h3></div>
                        <div class="edit-form">
                            <div class="form-row"><div class="form-group"><label>First Name</label><input type="text" name="firstname" value="<?php echo htmlspecialchars($firstname); ?>" class="form-input"></div><div class="form-group"><label>Last Name</label><input type="text" name="lastname" value="<?php echo htmlspecialchars($lastname); ?>" class="form-input"></div></div>
                            <div class="form-group"><label>Email</label><input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" class="form-input"></div>
                             <div class="form-row"><div class="form-group"><label>Student ID</label><input type="text" value="<?php echo htmlspecialchars($student_id); ?>" class="form-input" readonly style="opacity:0.6;"></div><div class="form-group"><label>Program</label><input type="text" value="<?php echo htmlspecialchars($program); ?>" class="form-input" readonly style="opacity:0.6;"></div></div>
                        </div>
                    </div>
                    <div class="edit-card">
                        <div class="card-header"><h3><i class='bx bx-note'></i> Bio</h3></div>
                        <div class="form-group"><textarea name="bio" class="form-textarea" rows="4" maxlength="200"><?php echo htmlspecialchars($bio); ?></textarea></div>
                    </div>
                    <div class="edit-card">
                        <div class="card-header"><h3><i class='bx bxs-graduation'></i> Academic Info</h3></div>
                        <div class="edit-form">
                             <div class="form-group"><label>Year Level</label><select class="form-input form-select" name="year_level"><?php foreach ($year_level_options as $opt) echo "<option value='$opt' " . ($year_level == $opt ? 'selected' : '') . ">$opt</option>"; ?></select></div>
                             <div class="form-group"><label>Section</label><input type="text" name="section" value="<?php echo htmlspecialchars($section); ?>" class="form-input"></div>
                             <div class="form-group"><label>Preferred Study Time</label><select class="form-input form-select" name="study_time"><?php foreach ($study_time_options as $val => $lbl) echo "<option value='$val' " . ($preferred_study_time == $val ? 'selected' : '') . ">$lbl</option>"; ?></select></div>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <div class="profile-view-mode" id="viewModeContent">
            <div class="profile-body-grid">
                <div class="profile-view-left">
                    <div class="view-card intro-card">
                        <h3><i class='bx bx-info-circle'></i> Intro</h3>
                        <p class="view-bio">"<?php echo empty($bio) ? 'No bio set yet.' : htmlspecialchars($bio); ?>"</p>
                        <ul class="view-list">
                            <li><i class='bx bxs-graduation icon'></i> <?php echo htmlspecialchars($year_level); ?> - <?php echo htmlspecialchars($section); ?></li>
                            <li><i class='bx bx-book-open icon'></i> Studying <?php echo htmlspecialchars($program); ?></li>
                            <li><i class='bx bx-time-five icon'></i> <?php echo $study_time_options[$preferred_study_time] ?? $preferred_study_time; ?></li>
                        </ul>
                    </div>
                    <div class="view-card achievements-card">
                        <div class="card-header">
                            <h3><i class='bx bx-trophy'></i> Achievements</h3>
                            <a href="#" class="view-all">View All</a>
                        </div>
                        <div class="achievements-grid">
                            <div class="achievement-item"><span class="achievement-icon">🥇</span><div class="achievement-info"><h4>First Blood</h4></div></div>
                            <div class="achievement-item"><span class="achievement-icon">🔥</span><div class="achievement-info"><h4>Week Warrior</h4></div></div>
                        </div>
                    </div>
                </div>
                <div class="profile-view-right">
                     <div class="view-card stats-card">
                        <h3><i class='bx bx-stats'></i> Activity Stats</h3>
                        <div style="margin-top: 1rem;">
                            <div style="display:flex; justify-content:space-between; margin-bottom:1rem; border-bottom:1px solid var(--border); padding-bottom:1rem;">
                                <div><small style="color:var(--text-gray);">Total Hours</small><div style="font-size:1.5rem; font-weight:700;"><?php echo $total_study_hours; ?>h</div></div>
                                <div><small style="color:var(--text-gray);">Sessions</small><div style="font-size:1.5rem; font-weight:700;">12</div></div>
                            </div>
                            <p style="color:var(--text-gray); font-size:0.9rem;">More detailed analytics are available on your Dashboard.</p>
                        </div>
                     </div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/main.js"></script>
    <script src="js/sidebar.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Loader fadeout
            // page loader removed — no loader to fade out

            // Auto Hide Notification
            const notification = document.getElementById('notification-toast');
            if(notification) {
                setTimeout(() => {
                    notification.style.animation = 'fadeOut 0.5s ease forwards';
                    setTimeout(() => { notification.remove(); }, 500);
                }, 3000); // 3 seconds visible
            }

            // TOGGLE LOGIC
            const toggleBtn = document.getElementById('toggleEditBtn');
            const saveBtn = document.getElementById('saveProfile');
            const viewContent = document.getElementById('viewModeContent');
            const editContent = document.getElementById('editFormContent');
            const editControls = document.querySelectorAll('.edit-controls');

            if(toggleBtn) {
                toggleBtn.addEventListener('click', function() {
                    const isViewing = this.getAttribute('data-state') === 'view';
                    if (isViewing) {
                        // Switch to EDIT
                        this.setAttribute('data-state', 'edit');
                        this.querySelector('.text').textContent = 'Cancel Edit';
                        this.querySelector('i').classList.replace('bx-edit-alt', 'bx-x');
                        saveBtn.style.display = 'flex';
                        
                        viewContent.style.display = 'none';
                        editContent.style.display = 'grid'; 
                        
                        // Show Camera Buttons
                        editControls.forEach(el => el.style.display = 'block');
                    } else {
                        // Switch to VIEW
                        this.setAttribute('data-state', 'view');
                        this.querySelector('.text').textContent = 'Edit Profile';
                        this.querySelector('i').classList.replace('bx-x', 'bx-edit-alt');
                        saveBtn.style.display = 'none';
                        
                        viewContent.style.display = 'block';
                        editContent.style.display = 'none';
                        
                        // Hide Camera Buttons
                        editControls.forEach(el => el.style.display = 'none');
                    }
                });
            }
        });

        // PREVIEW FUNCTIONS
        function previewCover(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profileBanner').style.backgroundImage = "url('" + e.target.result + "')";
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function previewProfilePhoto(input) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.getElementById('mainProfilePhoto');
                    if(img.tagName === 'IMG') {
                        img.src = e.target.result;
                    } else {
                        const wrapper = document.querySelector('.profile-photo-wrapper');
                        const newImg = document.createElement('img');
                        newImg.src = e.target.result;
                        newImg.className = 'profile-photo';
                        newImg.id = 'mainProfilePhoto';
                        img.replaceWith(newImg);
                    }
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>

    <!-- PROFILE LOGOUT: Using centralized global logout modal in includes/call_overlay.php -->
    
    <?php include 'includes/call_overlay.php'; ?>
    </main>
</body>
</html>