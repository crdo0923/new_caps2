<?php
// includes/gametag_overlay.php

// 1. Session Check
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { return; }

$overlay_user_id = $_SESSION['user_id'];

// Default to current page if not set
if(!isset($current_page_link)) {
    $current_page_link = basename($_SERVER['PHP_SELF']);
}

// Check if messaging page (for specific positioning)
$is_messaging = ($current_page_link === 'messaging.php');

// 2. Database Connection
if (!isset($conn)) {
    $servername = 'localhost'; $username = 'root'; $password = ''; $dbname = 'smart_study';
    $conn_overlay = new mysqli($servername, $username, $password, $dbname);
} else { $conn_overlay = $conn; }

// 3. Fetch Data
$overlay_sql = "SELECT firstname, lastname, program, student_id, bio, points, profile_photo FROM users WHERE id = ?";
$stmt_overlay = $conn_overlay->prepare($overlay_sql);
$stmt_overlay->bind_param("i", $overlay_user_id);
$stmt_overlay->execute();
$res_overlay = $stmt_overlay->get_result();
$user_overlay = $res_overlay->fetch_assoc();

// Variables
$gt_firstname = htmlspecialchars($user_overlay['firstname']);
$gt_id = htmlspecialchars($user_overlay['student_id']);
$gt_bio = htmlspecialchars($user_overlay['bio'] ?? 'Ready to learn!');
$gt_points = $user_overlay['points'];
$gt_program = htmlspecialchars($user_overlay['program']);
$gt_photo = $user_overlay['profile_photo'];

// Avatar Logic
$gt_has_photo = !empty($gt_photo) && file_exists(__DIR__ . '/../profile-img/' . $gt_photo);
$gt_avatar_src = $gt_has_photo ? 'profile-img/' . htmlspecialchars($gt_photo) : '';
$gt_initial = strtoupper(substr($gt_firstname, 0, 1));

// Rank Logic
$gt_medal = '⭐'; 
if ($gt_points >= 2000) { $gt_medal = '🏆'; } elseif ($gt_points >= 1500) { $gt_medal = '🥇'; } elseif ($gt_points >= 1000) { $gt_medal = '🥈'; }

if (!isset($conn)) { $conn_overlay->close(); }
?>

<style>
    /* OVERLAY CONTAINER - Default Fixed Position */
    .gametag-overlay-container {
        position: fixed; 
        top: 25px; 
        right: 25px; 
        z-index: 9900;
        display: flex; 
        flex-direction: column; 
        align-items: flex-end;
        font-family: 'Inter', sans-serif;
    }

    /* EXEMPTION: MESSAGING PAGE STYLE - Perfect Center Right */
    .gametag-overlay-container.messaging-mode {
        position: absolute !important; 
        top: 50% !important;           /* Gitna vertically */
        right: 20px !important;        /* Distance from right edge */
        transform: translateY(-50%) !important; /* Perfect centering correction */
        z-index: 100;
    }

    /* TRIGGER BUTTON (AVATAR) */
    .gametag-trigger {
        width: 45px; height: 45px;
        border-radius: 50%;
        background: linear-gradient(135deg, #6366f1, #8b5cf6);
        cursor: pointer;
        box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
        transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        display: flex; align-items: center; justify-content: center;
        border: 2px solid rgba(255,255,255,0.1);
        overflow: hidden;
        z-index: 9902;
    }

    .gametag-trigger:hover {
        transform: scale(1.1);
        box-shadow: 0 8px 25px rgba(99, 102, 241, 0.6);
        border-color: #fff;
    }

    .gametag-trigger img { width: 100%; height: 100%; object-fit: cover; }
    .gametag-trigger .default-av { color: white; font-weight: 700; font-size: 1.1rem; }

    /* CARD POPUP */
    .gametag-card-overlay {
        position: absolute; top: 60px; right: 0;
        width: 280px;
        background: rgba(15, 23, 42, 0.95);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: 0 20px 50px rgba(0,0,0,0.5);
        
        /* Hidden State */
        opacity: 0; visibility: hidden; transform: translateY(-10px) scale(0.95);
        transition: all 0.25s cubic-bezier(0.165, 0.84, 0.44, 1);
        pointer-events: none; z-index: 9901;
    }

    /* Active State (Hover or Click) */
    .gametag-overlay-container:hover .gametag-card-overlay,
    .gametag-overlay-container.active .gametag-card-overlay {
        opacity: 1; visibility: visible; transform: translateY(0) scale(1); pointer-events: auto;
    }

    /* CARD CONTENT */
    .gt-header { display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 1rem; }
    
    .gt-avatar-lg { 
        width: 56px; height: 56px; border-radius: 50%; object-fit: cover; 
        border: 2px solid #6366f1; background: #1e293b;
    }
    
    .gt-default-lg {
        width: 56px; height: 56px; border-radius: 50%; 
        background: linear-gradient(135deg, #6366f1, #ec4899);
        display: flex; align-items: center; justify-content: center;
        color: white; font-weight: 700; font-size: 1.5rem;
        border: 2px solid rgba(255,255,255,0.2);
    }

    .gt-info h4 { margin: 0; color: white; font-size: 1.1rem; font-weight: 700; }
    .gt-info p { margin: 2px 0 0; color: #94a3b8; font-size: 0.85rem; font-family: monospace; }

    .gt-stats { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.5rem; margin-bottom: 1rem; text-align: center; }
    .gt-stat-box { background: rgba(255,255,255,0.03); padding: 0.5rem; border-radius: 8px; border: 1px solid rgba(255,255,255,0.05); transition: 0.2s; }
    .gt-stat-box:hover { background: rgba(255,255,255,0.08); border-color: rgba(99, 102, 241, 0.3); }
    
    .gt-stat-label { display: block; font-size: 0.65rem; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; }
    .gt-stat-val { display: block; font-size: 0.9rem; font-weight: 700; color: #f8fafc; margin-top: 2px; }

    .gt-bio { 
        font-size: 0.85rem; color: #cbd5e1; font-style: italic; text-align: center; 
        margin-bottom: 1.2rem; line-height: 1.4; opacity: 0.8;
    }

    .gt-btn { 
        display: flex; align-items: center; justify-content: center; gap: 0.5rem;
        width: 100%; padding: 0.6rem; 
        background: linear-gradient(90deg, #6366f1, #8b5cf6); 
        color: white; text-decoration: none; border-radius: 8px; 
        font-size: 0.9rem; font-weight: 600; box-sizing: border-box;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .gt-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4); }
</style>

<div class="gametag-overlay-container <?php echo $is_messaging ? 'messaging-mode' : ''; ?>" id="gametagOverlay">
    
    <div class="gametag-trigger" onclick="toggleGametag()">
        <?php if ($gt_has_photo): ?>
            <img src="<?= $gt_avatar_src ?>" alt="Me">
        <?php else: ?>
            <div class="default-av"><?= $gt_initial ?></div>
        <?php endif; ?>
    </div>

    <div class="gametag-card-overlay">
        <div class="gt-header">
            <?php if ($gt_has_photo): ?>
                <img src="<?= $gt_avatar_src ?>" class="gt-avatar-lg">
            <?php else: ?>
                <div class="gt-default-lg"><?= $gt_initial ?></div>
            <?php endif; ?>
            
            <div class="gt-info">
                <h4><?= $gt_firstname ?></h4>
                <p>ID: <?= $gt_id ?></p>
            </div>
        </div>

        <div class="gt-stats">
            <div class="gt-stat-box"><span class="gt-stat-label">Rank</span><span class="gt-stat-val"><?= $gt_medal ?></span></div>
            <div class="gt-stat-box"><span class="gt-stat-label">Points</span><span class="gt-stat-val"><?= number_format($gt_points) ?></span></div>
            <div class="gt-stat-box"><span class="gt-stat-label">Course</span><span class="gt-stat-val"><?= $gt_program ?></span></div>
        </div>

        <div class="gt-bio">"<?= $gt_bio ?>"</div>

        <a href="profile.php?from=<?php echo $current_page_link; ?>" class="gt-btn">
            <i class='bx bx-user-circle'></i> View Profile
        </a>
    </div>
</div>

<script>
    function toggleGametag() {
        document.getElementById('gametagOverlay').classList.toggle('active');
    }
    
    // Close when clicking outside
    document.addEventListener('click', function(event) {
        const overlay = document.getElementById('gametagOverlay');
        if (overlay && !overlay.contains(event.target)) {
            overlay.classList.remove('active');
        }
    });
</script>