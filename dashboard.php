<?php
session_start();

// ===================================================
// 1. REALTIME AJAX HANDLER (Dito kumukuha ng update)
// ===================================================
if (isset($_GET['ajax_action']) && $_GET['ajax_action'] === 'get_unread_count') {
    header('Content-Type: application/json');
    
    // Database connection for AJAX
    $conn = new mysqli('localhost', 'root', '', 'smart_study');
    if ($conn->connect_error) { echo json_encode(['count' => 0]); exit; }
    
    $user_id = $_SESSION['user_id'] ?? 0;
    $count = 0;
    
    if ($user_id > 0) {
        $stmt = $conn->prepare("SELECT COUNT(*) as total FROM messages WHERE receiver_id = ? AND is_read = 0");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $count = $row['total'];
        }
        $stmt->close();
    }
    $conn->close();
    
    echo json_encode(['count' => $count]);
    exit; // Stop execution here for AJAX requests
}

// ===================================================
// STANDARD PAGE LOAD LOGIC
// ===================================================

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit();
}

// Config & DB
$servername = 'localhost'; $db_username = 'root'; $db_password = ''; $database = 'smart_study';
$user_id = (int)($_SESSION['user_id'] ?? 0); 
// Admin config is optional; include it so the UI can show admin links when present
if (file_exists(__DIR__ . '/includes/admin_config.php')) include_once __DIR__ . '/includes/admin_config.php';
$conn = mysqli_connect($servername, $db_username, $db_password, $database); 
$db_fetch_success = $conn ? true : false;

// Defaults
$firstname = htmlspecialchars($_SESSION['firstname'] ?? 'Student');
$lastname = htmlspecialchars($_SESSION['lastname'] ?? 'User');
$program = htmlspecialchars($_SESSION['program'] ?? 'BSIT');
$student_id = htmlspecialchars($_SESSION['student_id'] ?? '2021-12345');
$bio = htmlspecialchars($_SESSION['bio'] ?? 'Ready to learn!');
$current_user_points = 0;
$profile_photo_db = ''; 
$unread_count_display = 0; 
$total_hours_studied = 0;
$max_streak = 0;

// Welcome Message
$welcome_message = null; 
if (isset($_SESSION['just_logged_in']) && $_SESSION['just_logged_in'] === true) {
    $welcome_message = "Welcome back, " . $firstname . "! Let's get productive. 💪";
    unset($_SESSION['just_logged_in']);
}

// Fetch Data
if ($db_fetch_success && $user_id > 0) {
    // User Info
    $stmt = $conn->prepare("SELECT firstname, lastname, program, student_id, bio, points, profile_photo FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id); $stmt->execute(); $user = $stmt->get_result()->fetch_assoc();
    if ($user) {
        $firstname = htmlspecialchars($user['firstname']);
        $lastname = htmlspecialchars($user['lastname']);
        $program = htmlspecialchars($user['program']);
        $student_id = htmlspecialchars($user['student_id']);
        $bio = htmlspecialchars($user['bio']);
        $current_user_points = $user['points'];
        $profile_photo_db = $user['profile_photo'];
        $_SESSION['firstname'] = $user['firstname']; 
        $_SESSION['lastname'] = $user['lastname'];
    }
    $stmt->close();

    // Unread Count (Initial Load)
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM messages WHERE receiver_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id); $stmt->execute();
    $unread_count_display = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();

    // Stats
    $stmt = $conn->prepare("SELECT SUM(duration_minutes) AS total_minutes FROM study_sessions WHERE user_id = ?");
    $stmt->bind_param("i", $user_id); $stmt->execute();
    $total_minutes = $stmt->get_result()->fetch_assoc()['total_minutes'] ?? 0;
    $total_hours_studied = round($total_minutes / 60, 2);
    $stmt->close();
    
    // Streak
    $streak_query = "SELECT DISTINCT DATE(session_datetime) AS study_day FROM study_sessions WHERE user_id = ? ORDER BY study_day DESC"; 
    if ($stmt = $conn->prepare($streak_query)) {
        $stmt->bind_param("i", $user_id); $stmt->execute(); $res_streak = $stmt->get_result();
        $dates = []; while ($row = $res_streak->fetch_assoc()) { $dates[] = strtotime($row['study_day']); }
        $stmt->close();
        if (!empty($dates)) {
            $current_streak = 0; $max_streak = 0; rsort($dates);
            $today = strtotime(date('Y-m-d')); $yesterday = strtotime(date('Y-m-d', strtotime('-1 day')));
            if (in_array($today, $dates)) { $current_streak = 1; $current_date = $today; } 
            elseif (in_array($yesterday, $dates)) { $current_streak = 1; $current_date = $yesterday; } 
            else { $current_date = 0; }
            foreach ($dates as $date) {
                if ($date < $current_date) {
                    $expected_prev_day = $current_date - 86400;
                    if (floor($date / 86400) == floor($expected_prev_day / 86400)) { $current_streak++; $current_date = $date; } else { break; }
                }
            }
            $max_streak = max($max_streak, $current_streak);
        }
    }
    
    // Leaderboard
    $top_students = [];
    $result = $conn->query("SELECT id, firstname, lastname, profile_photo, points FROM users ORDER BY points DESC LIMIT 3");
    while($row = $result->fetch_assoc()) { $top_students[] = $row; }
    
    // Weekly Goal
    $weekly_goal_minutes = 1200; 
    $study_minutes_this_week = 0;
    $weekly_goal_query = "SELECT SUM(duration_minutes) AS total_minutes_this_week FROM study_sessions WHERE user_id = ? AND session_datetime >= DATE_SUB(CURDATE(), INTERVAL (WEEKDAY(CURDATE())) DAY)"; 
    if ($stmt = $conn->prepare($weekly_goal_query)) {
        $stmt->bind_param("i", $user_id); $stmt->execute(); $res_goal = $stmt->get_result();
        if ($row_goal = $res_goal->fetch_assoc()) { $study_minutes_this_week = (int)($row_goal['total_minutes_this_week'] ?? 0); }
        $stmt->close();
    }
    $study_hours_this_week = round($study_minutes_this_week / 60, 1);
    $goal_progress_percent = $weekly_goal_minutes > 0 ? min(100, round(($study_minutes_this_week / $weekly_goal_minutes) * 100)) : 0;

    // Additional progress insights
    $minutes_left_this_week = max(0, $weekly_goal_minutes - $study_minutes_this_week);
    $hours_left_this_week = round($minutes_left_this_week / 60, 2);

    // days into week (1=Mon .. 7=Sun), remaining days to reach weekly goal
    $weekday_num = (int)date('N');
    $days_elapsed = max(1, $weekday_num); // avoid division by zero
    $days_remaining = max(0, 7 - $weekday_num);

    $avg_minutes_per_day_so_far = $study_minutes_this_week / $days_elapsed;
    $avg_hours_per_day_so_far = round($avg_minutes_per_day_so_far / 60, 2);

    // required daily minutes for remaining days (if days_remaining=0, show all remaining minutes for today)
    if ($days_remaining > 0) {
        $required_minutes_per_remaining_day = ceil($minutes_left_this_week / $days_remaining);
    } else {
        $required_minutes_per_remaining_day = $minutes_left_this_week; // final push
    }

    // Count sessions this week
    $sessions_this_week = 0;
    $sessions_q = "SELECT COUNT(*) AS total_sessions FROM study_sessions WHERE user_id = ? AND session_datetime >= DATE_SUB(CURDATE(), INTERVAL (WEEKDAY(CURDATE())) DAY)";
    if ($stmt = $conn->prepare($sessions_q)) {
        $stmt->bind_param('i', $user_id); $stmt->execute(); $sessions_this_week = (int)$stmt->get_result()->fetch_assoc()['total_sessions'] ?? 0; $stmt->close();
    }

    // --- ONBOARDING / TUTORIAL DETECTION ---------------------------------
    // We'll detect if the user appears to be "new" (no points, no study minutes, no tasks)
    $onboarding_column_exists = false;
    $onboarding_seen = 0;
    $is_new_user = false;
    $show_onboarding = false; // default

    // Check if column exists (safe check)
    $col_check = $conn->query("SHOW COLUMNS FROM users LIKE 'onboarding_seen'");
    if ($col_check && $col_check->num_rows > 0) {
        $onboarding_column_exists = true;

        $stmt = $conn->prepare("SELECT onboarding_seen FROM users WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $user_id); $stmt->execute(); $res_col = $stmt->get_result();
            if ($row_col = $res_col->fetch_assoc()) { $onboarding_seen = (int)($row_col['onboarding_seen'] ?? 0); }
            $stmt->close();
        }
    }

    // Check quick indicators of activity: points (already fetched), total minutes and tasks count
    $task_count = 0;
    if ($stmt = $conn->prepare("SELECT COUNT(*) AS total_tasks FROM tasks WHERE user_id = ?")) {
        $stmt->bind_param("i", $user_id); $stmt->execute(); $task_count = (int)$stmt->get_result()->fetch_assoc()['total_tasks'] ?? 0; $stmt->close();
    }

    // $total_minutes is computed earlier (sum of durations) — consider user "new" if all indicators are zero
    $is_new_user = (empty($current_user_points) && empty($total_minutes) && $task_count === 0);

    if ($is_new_user) {
        if ($onboarding_column_exists) {
            $show_onboarding = ($onboarding_seen === 0);
        } else {
            // no column available — show onboarding for now (we will create column when user opts out)
            $show_onboarding = true;
        }
    }
}

// Avatar
$has_photo = !empty($profile_photo_db) && file_exists('profile-img/' . $profile_photo_db);
$avatar_src = $has_photo ? 'profile-img/' . htmlspecialchars($profile_photo_db) : '';
$initial = strtoupper(substr($firstname, 0, 1));
$medal_icon = ($current_user_points >= 2000) ? '🏆' : (($current_user_points >= 1000) ? '🥇' : (($current_user_points >= 500) ? '🥈' : '⭐'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SmartStudy</title>
    
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="css/loading.css">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <?php include __DIR__ . '/includes/layout_preamble.php'; ?>
    <link rel="stylesheet" href="css/layout.css">
    <!-- page loader (restored) -->
    <!-- Inline styles moved to css/dashboard.css for better performance -->
</head>
<body>
    <?php include 'includes/mobile_blocker.php'; ?>

    <div class="page-loader">
    <div class="loader-container">
        <div class="loader-icon"><i class='bx bxl-brain'></i></div>
        <div class="loader-text" style="color: #cbd5e1; font-family: 'Inter', sans-serif; margin-bottom: 10px;"></div>
        <div class="loader-spinner"><div class="spinner-ring"></div></div>
    </div>
</div>

    <!-- page loader removed (no animation) -->

    <div id="toast-container"></div>
    <!-- Pretty focus warning banner (shown when user exits fullscreen / switches tabs) -->
    <div id="focusWarning" class="focus-warning" style="display:none;">
        <div class="focus-warning-inner">
            <div class="focus-warning-icon"><i class='bx bx-shield-x' style="font-size:1.6rem"></i></div>
            <div class="focus-warning-text">
                <h4>Focus Interrupted</h4>
                <p>Your session was paused because you left full-screen or switched tabs. Click resume to continue.</p>
            </div>
            <div class="focus-warning-actions">
                <button id="dismissFocusWarning" class="btn-small">Dismiss</button>
            </div>
        </div>
    </div>

    <?php include 'includes/sidebar.php'; ?>

    <main class="main-content">
        <section id="dashboard-section" class="content-section active">
            <div class="section-header" style="display:flex; align-items:center; gap:12px; justify-content:space-between;">
                <div>
                    <h1>Study Dashboard</h1>
                    <p>Your all-in-one study hub</p>
                </div>
                <?php if ((defined('ADMIN_USER_IDS') && in_array($user_id, ADMIN_USER_IDS)) || (defined('ADMIN_MASTER_ID') && $user_id === (int)ADMIN_MASTER_ID)):
                    // Only show admin dashboard link if the file exists to avoid broken links
                    if (file_exists(__DIR__ . '/admin_dashboard.php')):
                ?>
                    <div style="display:flex; gap:10px; align-items:center;">
                        <a href="admin_dashboard.php" id="adminDashboardBtn" class="btn-add" style="padding:8px 12px; border-radius:8px; font-weight:700; background:#334155;">Admin Dashboard</a>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card"><div class="stat-icon" style="background: rgba(99, 102, 241, 0.1); color: #6366f1;"><i class='bx bx-time'></i></div><div class="stat-info"><h3 id="studyTimeDisplay"><?= number_format($total_hours_studied, 1); ?>h</h3><p>Total Hours Studied</p></div></div>
                <div class="stat-card"><div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: #10b981;"><i class='bx bx-check-circle'></i></div><div class="stat-info"><h3 id="tasksCompletedDisplay">8/12</h3><p>Tasks Completed</p></div></div>
                <div class="stat-card"><div class="stat-icon" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;"><i class='bx bxs-flame'></i></div><div class="stat-info"><h3><?= number_format($max_streak); ?> Days</h3><p>Max Study Streak</p></div></div>
                <div class="stat-card"><div class="stat-icon" style="background: rgba(236, 72, 153, 0.1); color: #ec4899;"><i class='bx bxs-trophy'></i></div><div class="stat-info"><h3 id="userPointsDisplay"><?= number_format($current_user_points); ?></h3><p>Study Points</p></div></div>
            </div>

            <div class="dashboard-grid">
                
                <div class="dashboard-card scheduler-card">
                    <div class="card-header">
                        <h3><i class='bx bx-calendar-star'></i> AI Smart Scheduler</h3>
                        <div id="aiLoading" style="display:none; font-size:0.8rem; color:#6366f1; align-items:center; gap:5px;">
                            <i class='bx bx-loader-alt bx-spin'></i> Generating...
                        </div>
                    </div>
                    <div class="ai-recommendation-concise" id="aiInsightBox">
                        <p><strong><i class='bx bx-bulb'></i> AI Insight:</strong> Add a task below to get a personalized plan.</p>
                    </div>
                    <div class="schedule-section">
                        <div class="schedule-header">
                            <h4>Today's Plan</h4>
                            <span class="schedule-date"><?= date('F d, Y'); ?></span>
                        </div>
                        
                        <div class="schedule-list" id="aiScheduleList">
                        <?php
                        // Reuse existing $conn instead of creating new connection
                        if (!$db_fetch_success) {
                            echo "<p style='color:red; text-align:center; padding:20px;'>DB Connection Failed.</p>";
                        } else {
                            $uid_for_task = $_SESSION['user_id'];
                            // Kumuha ng mas marami (Limit 20) pero 3 lang ipapakita muna
                            $f_sql = "SELECT * FROM tasks WHERE user_id = $uid_for_task AND status = 'pending' ORDER BY id DESC LIMIT 20";
                            $f_result = $conn->query($f_sql);

                            if ($f_result && $f_result->num_rows > 0) {
                                $count = 0;
                                $visible_limit = 3; // Ilan ang ipapakita sa simula

                                while ($row = $f_result->fetch_assoc()) {
                                    // Styles
                                    $prio = $row['priority'];
                                    $p_class = ($prio == 'High') ? 'priority-urgent' : (($prio == 'Medium') ? 'priority-medium' : 'priority-low');
                                    $b_class = ($prio == 'High') ? 'urgent' : (($prio == 'Medium') ? 'high' : 'medium');
                                    
                                    // Data
                                    $tid = $row['id'];
                                    $subject_display = htmlspecialchars($row['subject'] ?? 'General', ENT_QUOTES);
                                    $title = htmlspecialchars($row['title'], ENT_QUOTES);
                                    $desc = htmlspecialchars($row['description'], ENT_QUOTES);
                                    $time = htmlspecialchars($row['time_sched']);
                                    $dur = htmlspecialchars($row['duration']);
                                    $prio_label = strtoupper($prio);
                                    
                                    // View All Logic
                                    $is_hidden = ($count >= $visible_limit) ? 'style="display:none;"' : '';
                                    $extra_class = ($count >= $visible_limit) ? 'hidden-task' : '';

                                    echo "
                                    <div class='schedule-item $p_class $extra_class' id='task-$tid' $is_hidden>
                                        <div class='schedule-indicator $b_class'></div>
                                        
                                        <div style='flex-grow: 1; padding-right: 15px;'>
                                            <div class='schedule-header-row' style='display: flex; align-items: center; gap: 10px; margin-bottom: 5px;'>
                                                <h4 style='margin: 0; color: var(--text-light); font-size: 1.1rem;'>$title <span class='subject-badge'>$subject_display</span></h4>
                                                <span class='priority-badge $b_class' style='font-size: 0.75rem; padding: 3px 10px; border-radius: 4px; background: rgba(255,255,255,0.1);'>$prio_label</span>
                                            </div>
                                            <p class='schedule-desc' style='margin: 0; color: var(--text-gray); font-size: 0.9rem;'>$desc</p>
                                            <div class='schedule-time-block' style='margin-top: 8px; font-size: 0.85rem; color: #6366f1; display: flex; align-items: center; gap: 5px;'>
                                                <i class='bx bx-time' style='font-size: 1.1rem;'></i> $dur ($time)
                                            </div>
                                        </div>

                                        <div class='task-actions' style='display: flex; align-items: center; gap: 10px;'>
                                            <button onclick=\"startFocusMode('$dur', $tid)\" title='Start Focus' style='background: #6366f1; color: white; border: none; padding: 10px 20px; border-radius: 10px; cursor: pointer; font-weight: 600; font-size: 0.95rem; display: flex; align-items: center; gap: 6px; transition: transform 0.2s;box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);'>
                                                <i class='bx bx-play' style='font-size: 1.4rem;'></i>
                                            </button>
                                            <button onclick=\"openEditTaskModal($tid, '$title', '$desc')\" title='Edit' style='background: rgba(255,255,255,0.05); border: 1px solid #334155; color: #94a3b8; width: 45px; height: 45px; border-radius: 10px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s;'>
                                                <i class='bx bx-edit-alt' style='font-size: 1.4rem;'></i>
                                            </button>
                                            <button onclick=\"confirmDeleteTask($tid)\" title='Delete' style='background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; color: #ef4444; width: 45px; height: 45px; border-radius: 10px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s;'>
                                                <i class='bx bx-trash' style='font-size: 1.4rem;'></i>
                                            </button>
                                        </div>
                                    </div>";
                                    
                                    $count++;
                                }

                                // SHOW VIEW ALL BUTTON if more than limit
                                if ($count > $visible_limit) {
                                    echo "
                                    <button id='viewAllTasksBtn' onclick='toggleAllTasks()' style='width: 100%; padding: 10px; background: transparent; border: 1px dashed #334155; color: var(--text-gray); border-radius: 10px; cursor: pointer; margin-top: 10px; transition: all 0.2s; font-size: 0.9rem;'>
                                        View All ($count Tasks) <i class='bx bx-chevron-down'></i>
                                    </button>";
                                }

                            } else {
                                echo '<div style="text-align: center; padding: 20px; color: #94a3b8;">
                                        <i class="bx bx-list-plus" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.5;"></i>
                                        <p style="font-size: 1.1rem;">No tasks yet. Generate one above!</p>
                                      </div>';
                            }
                            // No need to close connection here - will be closed at end of page or when PHP exits
                        }
                        ?>
                    </div>
                    </div>
                </div>

                <div class="dashboard-card quick-add-task-card" id="quickAddForm"> 
                    <h3><i class='bx bx-plus-circle'></i> Add Task for AI</h3>
                    <input type="text" id="taskInput" placeholder="E.g., Review for Finals" class="task-input"> 
                    <div id="subjectSuggestion" style="font-size:0.85rem; color:#9fb2ff; margin-top:6px; display:none;">Detected: <strong id="subjectSuggestionText"></strong> <button id="applySuggestionBtn" class="btn-small" style="margin-left:12px; padding:4px 8px; font-size:0.8rem;">Use</button></div>
                    <div class="task-selects">
                        <select class="task-subject" id="taskSubject">
                            <option value="Auto Detect">🔍 Auto Detect</option>
                            <option value="General">General</option>
                            <option value="Programming">Programming</option>
                            <option value="Algorithms">Algorithms</option>
                            <option value="Data Structures">Data Structures</option>
                            <option value="Databases">Databases</option>
                            <option value="Web Dev">Web Development</option>
                            <option value="Frontend">Frontend</option>
                            <option value="Backend">Backend</option>
                            <option value="DevOps">DevOps / SysAdmin</option>
                            <option value="AI/ML">AI / Machine Learning</option>
                            <option value="Math">Mathematics</option>
                            <option value="Statistics">Statistics</option>
                            <option value="Physics">Physics</option>
                            <option value="Biology">Biology</option>
                            <option value="Chemistry">Chemistry</option>
                            <option value="Languages">Languages</option>
                            <option value="History">History</option>
                            <option value="Other">Other</option>
                        </select>
                        <div style="font-size:0.82rem; color: #9aa7c7; margin-top:6px;">Tip: Choose "Auto Detect" and SmartStudy will guess the best subject from your task text.</div>
                        <select class="priority-select" id="taskPriority">
                            <option value="High">High Priority</option>
                            <option value="Medium">Medium Priority</option>
                            <option value="Low">Low Priority</option>
                        </select>
                    </div>
                    <!-- Detected subject confirmation (appears when Auto Detect used) -->
                    <div id="detectedSubjectConfirm" style="display:none; margin-top:10px; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.03); padding:10px; border-radius:8px;">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div style="font-weight:600; color:#cfe0ff;">Detected subject</div>
                            <select id="detectedSubjectSelectConfirm" style="background:transparent; color: #dbeafe; border:1px solid rgba(255,255,255,0.04); padding:6px 8px; border-radius:6px;"></select>
                            <button id="confirmDetectedSaveBtn" class="btn-add" style="padding:6px 12px; font-size:0.9rem;">Confirm & Save</button>
                            <button id="cancelDetectedBtn" class="btn-small" style="margin-left:auto;">Cancel</button>
                        </div>
                        <div style="font-size:0.8rem; color:#98a7d5; margin-top:6px;">Confirm or change the detected subject before the task is created.</div>
                    </div>
                    <button type="button" class="btn-add" onclick="generateSchedule()">
                        <i class='bx bxs-magic-wand'></i> Generate with AI
                    </button>
                </div>

                <div class="dashboard-card focus-card">
                    <div class="card-header">
                        <h3 id="focusModeTitle"><i class='bx bx-target-lock'></i> Focus Mode</h3>
                        <div id="focusStatus" style="font-size:0.8rem; color:#10b981; display:none;">● Focus Active</div>
                    </div>
                    
                    <div id="activeTaskContainer" style="text-align:center; margin-bottom:15px; min-height: 30px;">
                        <h4 id="activeTaskLabel" style="color:var(--primary-color); font-weight:600; margin:0;">Select a task to start</h4>
                    </div>

                    <div class="timer-display">
                        <svg class="timer-ring" width="200" height="200">
                            <circle cx="100" cy="100" r="85" fill="none" stroke="#1e293b" stroke-width="10"/>
                            <circle id="timerProgress" cx="100" cy="100" r="85" fill="none" stroke="url(#timer-gradient)" stroke-width="10" transform="rotate(-90 100 100)" stroke-dasharray="534" stroke-dashoffset="0"/>
                            <defs><linearGradient id="timer-gradient" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#6366f1"/><stop offset="100%" stop-color="#ec4899"/></linearGradient></defs>
                        </svg>
                        <div class="timer-text">
                            <span id="timerMinutes">25</span>:<span id="timerSeconds">00</span>
                        </div>
                    </div>
                    
                    <p class="focus-mode-description text-center" id="focusMessage" style="color: #94a3b8;">
                        Ready to lock in? Distractions will be monitored.
                    </p> 
                    
                    <div class="timer-settings">
                        <div class="time-options" id="studyTimeOptions">
                            <label class="time-radio"><input type="radio" name="study-time" value="25" checked> <span>25m</span></label>
                            <label class="time-radio"><input type="radio" name="study-time" value="45"> <span>45m</span></label>
                            <label class="time-radio"><input type="radio" name="study-time" value="60"> <span>60m</span></label>
                        </div>
                    </div>
                    <?php if (defined('ADMIN_USER_IDS') && in_array($user_id, ADMIN_USER_IDS)): ?>
                    <!-- Admin experimental controls -->
                    <div class="admin-experimental" style="margin-top:12px; padding:10px; border-radius:8px; border:1px solid rgba(255,255,255,0.03); background: rgba(255,255,255,0.01);">
                        <div style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
                            <div style="flex:1">
                                <div style="font-weight:700; color:#dbeafe;">Experimental</div>
                                <div style="color:#9aa7c7; font-size:0.85rem;">Admin-only controls: change timer speed & allow background running while switching tabs.</div>
                            </div>
                            <div style="display:flex; gap:8px; align-items:center;">
                                <label style="font-size:0.85rem; color:#cfe0ff;">Continue on tab switch</label>
                                <input id="adminAllowBackground" type="checkbox" />
                                <label style="font-size:0.85rem; color:#cfe0ff; margin-left:12px;">Speed</label>
                                <select id="adminTimerSpeed" style="background:transparent; color:#e6eef9; border:1px solid rgba(255,255,255,0.04); padding:6px; border-radius:6px;">
                                    <option value="0.5">0.5x</option>
                                    <option value="0.75">0.75x</option>
                                    <option value="1" selected>1x</option>
                                    <option value="1.25">1.25x</option>
                                    <option value="1.5">1.5x</option>
                                    <option value="2">2x</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="focus-controls">
                        <button class="btn-focus" id="startFocusBtn" onclick="startFocusMode(null, null, 'Free Study')"><i class='bx bx-play'></i> Start Focus</button>
                        <button class="btn-focus" id="stopFocusBtn" onclick="stopFocusMode()" style="background:#ef4444; display:none;"><i class='bx bx-stop'></i> Stop</button>
                    </div>
                </div>
                
                <div class="dashboard-card analytics-card">
                    <div class="card-header"><h3><i class='bx bx-bar-chart-alt-2'></i> Progress</h3></div>
                    <div class="chart-container" style="position: relative; height: 180px; width:100%;">
                        <canvas id="weeklyChart"></canvas>
                    </div>
                        <div class="goals-list">
                            <div class="goal-item">
                                <div class="goal-info">
                                    <span class="goal-icon"><i class='bx bx-trophy'></i></span>
                                    <div>
                                        <h4>Weekly Goal</h4>
                                        <p id="goalText"><?= number_format($study_hours_this_week,1) ?> / <?= round($weekly_goal_minutes/60,1) ?> hours &mdash; <strong style="color:#9fb2ff"><?= $hours_left_this_week ?>h left</strong></p>
                                    </div>
                                </div>
                                <div class="goal-progress">
                                    <div class="progress-bar-mini" aria-hidden="true"><div class="progress-fill" id="goalProgressBar" style="width: <?= $goal_progress_percent; ?>%; transition: width 700ms ease;"></div></div>
                                    <span id="goalPercent"><?= $goal_progress_percent; ?>%</span>
                                </div>
                            </div>

                            <div class="goal-insights" style="display:flex; gap:12px; margin-top:12px; color:#cfe0ff; font-size:0.92rem;">
                                <div style="flex:1; background: rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.03); padding:10px; border-radius:8px;">
                                    <div style="font-weight:700; font-size:0.95rem;">This week</div>
                                    <div style="margin-top:6px; color:#9aa7c7;"><?= number_format($study_hours_this_week,1) ?>h across <?= $sessions_this_week ?> session<?= $sessions_this_week != 1 ? 's' : '' ?></div>
                                </div>
                                <div style="flex:1; background: rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.03); padding:10px; border-radius:8px;">
                                    <div style="font-weight:700; font-size:0.95rem;">Avg per day</div>
                                    <div style="margin-top:6px; color:#9aa7c7;"><?= $avg_hours_per_day_so_far ?>h/day</div>
                                </div>
                                <div style="flex:1; background: rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.03); padding:10px; border-radius:8px;">
                                    <div style="font-weight:700; font-size:0.95rem;">To hit target</div>
                                    <div style="margin-top:6px; color:#9aa7c7;"><?= round($required_minutes_per_remaining_day/60,2) ?>h/day (next <?= $days_remaining ?> day<?= $days_remaining != 1 ? 's' : '' ?>)</div>
                                </div>
                            </div>
                    </div>
                </div>

                <div class="dashboard-card rewards-card">
                    <h3><i class='bx bx-crown'></i> Leaderboard</h3>
                    <div class="leaderboard-mini">
                        <?php 
                        if (!empty($top_students)) {
                            $rank_count = 1;
                            foreach ($top_students as $s): 
                                $is_me = ($s['id'] == $user_id);
                                $s_photo = !empty($s['profile_photo']) ? 'profile-img/'.$s['profile_photo'] : '';
                                $s_initial = $s['initial'] ?? substr($s['firstname'], 0, 1);
                        ?>
                            <div class="leaderboard-item <?= $is_me ? 'highlight' : '' ?>">
                                <span class="rank">#<?= $rank_count++ ?></span>
                                <div class="user-mini">
                                    <div class="avatar-mini">
                                        <?php if ($s_photo): ?><img src="<?= $s_photo ?>"><?php else: ?><?= $s_initial ?><?php endif; ?>
                                    </div>
                                    <span><?= htmlspecialchars($s['firstname']) ?></span>
                                </div>
                                <span class="points" id="<?= $is_me ? 'userPointsDisplay' : '' ?>"><?= number_format($s['points']) ?></span>
                            </div>
                        <?php endforeach; 
                        } else { echo "<p style='color:var(--text-gray); text-align:center; padding:1rem;'>No data yet.</p>"; }
                        ?>
                    </div>
                </div>
            </div>
            </section>
    </main>
    
    <!-- Logout modal is handled globally in includes/call_overlay.php -->
    <!-- ONBOARDING / TUTORIAL MODAL (Skippable) -->
    <div id="onboardingModal" class="modal-overlay" style="z-index:16000; display: none;">
        <div class="modal-content" style="max-width:760px; padding:20px;">
            <div class="upload-header">
                <h3>Welcome to SmartStudy 🎉</h3>
                <button class="close-upload-btn" id="onboardingCloseBtn"><i class='bx bx-x'></i></button>
            </div>
            <div style="padding: 14px 6px;">
                <div id="onboardingSlides" style="min-height:120px; max-height:260px; overflow-y:auto; padding-right:8px;">
                    <div class="onboarding-step" data-step="1" style="word-wrap:break-word;">
                        <h4 style="margin-top:0;">Build a daily habit</h4>
                        <p style="color: #cbd5e1;">Use the Focus Mode to create short, focused study sessions — earn points and track progress.</p>
                    </div>
                    <div class="onboarding-step" data-step="2" style="display:none; word-wrap:break-word;">
                        <h4>Use AI scheduling</h4>
                        <p style="color: #cbd5e1;">Add a task and let SmartStudy generate a personalized plan for the day.</p>
                    </div>
                    <div class="onboarding-step" data-step="3" style="display:none; word-wrap:break-word;">
                        <h4>Connect with peers</h4>
                        <p style="color: #cbd5e1;">Join groups, message classmates and track your leaderboard progress.</p>
                    </div>
                </div>

                <div style="display:flex; align-items:center; gap:10px; margin-top: 16px;">
                    <label style="display:flex; align-items:center; gap:8px; color:#cbd5e1; font-size:0.9rem;"><input type="checkbox" id="onboardingDontShow"> Don't show again</label>
                    <div style="margin-left:auto; display:flex; gap:8px;">
                        <button class="btn-small" id="onboardingPrev" style="display:none;">Back</button>
                        <button class="btn-small" id="onboardingNext">Next</button>
                        <button class="btn-add" id="onboardingFinish" style="display:none;">Finish</button>
                    </div>
                </div>
                <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:12px;">
                    <button class="btn-small" id="onboardingSkip">Skip tutorial</button>
                    <button class="btn-add" id="onboardingStart">Start using app</button>
                </div>
            </div>
        </div>
    </div>
    <!-- GUIDED TOUR OVERLAY -->
    <div id="tourOverlay" style="display:none; position:fixed; inset:0; z-index:17000; pointer-events:none;">
        <div id="tourBackdrop" style="position:absolute; inset:0; background:rgba(0,0,0,0.72);"></div>
        <div id="tourHighlight" style="position:absolute; border-radius:8px; box-shadow:0 0 0 9999px rgba(0,0,0,0.6); pointer-events:none; transition: all 220ms ease; border: 2px solid #fff; background: rgba(255,255,255,0.03);"></div>
        <div id="tourTooltip" style="position:absolute; max-width:420px; background: #0b1220; color: #e6eef9; border-radius:10px; padding:14px; border:1px solid rgba(255,255,255,0.06); box-shadow:0 10px 30px rgba(0,0,0,0.6); pointer-events:auto;">
            <div id="tourTitle" style="font-weight:700; margin-bottom:6px;">Step title</div>
            <div id="tourDesc" style="color:#9fb2ff; font-size:0.92rem;">Description...</div>
            <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:10px;">
                <button id="tourPrev" class="btn-small">Back</button>
                <button id="tourNext" class="btn-small">Next</button>
                <button id="tourEnd" class="btn-add">End Tour</button>
            </div>
        </div>
    </div>
     <div id="editTaskModal" class="modal-overlay" style="z-index: 10002; display: none;">
        <div class="modal-content" style="width: 400px;">
            <div class="upload-header">
                <h3>Edit Task</h3>
                <button class="close-upload-btn" onclick="closeEditModal()"><i class='bx bx-x'></i></button>
            </div>
            <div class="upload-options" style="display: block; padding: 20px;">
                <input type="hidden" id="editTaskId">
                <div style="margin-bottom: 15px;">
                    <label style="color: var(--text-gray); font-size: 0.9rem;">Task Title</label>
                    <input type="text" id="editTaskTitle" class="task-input" style="width: 100%; margin-top: 5px;">
                </div>
                <div style="margin-bottom: 20px;">
                    <label style="color: var(--text-gray); font-size: 0.9rem;">Description</label>
                    <textarea id="editTaskDesc" class="task-input" style="width: 100%; height: 80px; margin-top: 5px; resize: none;"></textarea>
                </div>
                <button class="btn-add" onclick="saveEditedTask()" style="width: 100%; justify-content: center;">Save Changes</button>
            </div>
        </div>
    </div>
    <!-- AI Confirm Modal -->
    <div id="aiConfirmModal" class="modal-overlay" style="z-index: 10004; display: none;">
        <div class="modal-content" style="max-width:500px; padding:20px;">
            <div class="upload-header">
                <h3>Confirm AI Suggestion</h3>
                <button class="close-upload-btn" onclick="closeAIConfirmModal()"><i class='bx bx-x'></i></button>
            </div>
            <div style="padding:12px 6px 0 6px;">
                <label style="color:var(--text-gray); font-size:0.9rem;">Task Title</label>
                <input id="aiConfirmTitle" type="text" class="task-input" style="width:100%; margin-top:6px;"/>

                <label style="color:var(--text-gray); font-size:0.9rem; margin-top:10px; display:block;">Description</label>
                <textarea id="aiConfirmDesc" class="task-input" style="width:100%; height:80px; margin-top:6px; resize:none;"></textarea>

                <div style="display:flex; gap:10px; margin-top:10px; align-items:center;">
                    <div style="flex:1">
                        <label style="color:var(--text-gray); font-size:0.9rem;">Subject</label>
                        <select id="aiConfirmSubject" class="task-input" style="width:100%; margin-top:6px; padding:8px;">
                        </select>
                    </div>
                    <div style="width:150px;">
                        <label style="color:var(--text-gray); font-size:0.9rem;">Priority</label>
                        <select id="aiConfirmPriority" style="width:100%; padding:8px; margin-top:6px;">
                            <option value="High">High</option>
                            <option value="Medium">Medium</option>
                            <option value="Low">Low</option>
                        </select>
                    </div>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:14px;">
                    <button class="btn-small" onclick="closeAIConfirmModal()">Cancel</button>
                    <button class="btn-add" id="aiConfirmSaveBtn">Confirm & Save</button>
                </div>
            </div>
        </div>
    </div>
    <!-- Subject edit modal -->
    <div id="subjectEditModal" class="modal-overlay" style="z-index: 10005; display: none;">
        <div class="modal-content" style="max-width:420px; padding:18px;">
            <div class="upload-header">
                <h3>Edit Task Subject</h3>
                <button class="close-upload-btn" onclick="closeSubjectEditModal()"><i class='bx bx-x'></i></button>
            </div>
            <div style="padding:12px 6px 0 6px;">
                <label style="color:var(--text-gray); font-size:0.9rem;">Select Subject</label>
                <select id="subjectEditSelect" style="width:100%; margin-top:8px; padding:8px; border-radius:8px; background:transparent; border:1px solid rgba(255,255,255,0.04); color:#e6eef9;">
                </select>
                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:14px;">
                    <button class="btn-small" onclick="closeSubjectEditModal()">Cancel</button>
                    <button class="btn-add" id="subjectEditSaveBtn">Save</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- customConfirmModal moved to includes/call_overlay.php to make it site-wide and avoid per-page duplicates -->
    <button id="persistentCallBtn" style="display: none;">📞 Back to Call</button>

    <!-- Floating Gemini Chat Assistant -->
    <!-- Floating Gemini Chat Assistant -->
    <div id="geminiAssistant" class="gemini-assistant">
        <!-- Gemini toggle removed (hidden by design across pages) -->
        <div class="gemini-panel" id="geminiPanel" role="dialog" aria-label="FAQ" inert>
            <div class="gemini-header">
                <div class="gemini-title"><i class='bx bx-rocket'></i> FAQ</div>
                <button id="geminiClose" class="gemini-close" aria-label="Close">×</button>
            </div>
            <div class="gemini-body" id="geminiBody">
                <div class="gemini-messages" id="geminiMessages" aria-live="polite"></div>
                <!-- Removed freeform prompt — FAQ panel will show selectable questions and answers only -->
                <div class="gemini-empty" id="geminiEmpty" style="display:none"></div>
            </div>
            <div class="gemini-footer">
                <input id="geminiInput" autocomplete="off" />
                <button id="geminiSend" class="btn-add">Send</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script> 
    <!-- PeerJS loaded in call_overlay.php to avoid duplication -->
    <script src="js/main.js" defer></script>
    <script src="js/dashboard.js" defer></script>
    <script src="js/smart_study_ai.js" defer></script>
    <script src="js/sidebar.js" defer></script>
    <!-- messaging.js included via call_overlay.php so remove duplicate here -->
    <script src="js/dashboard_notifications.js" defer></script>
    
    <?php if (file_exists(__DIR__ . '/includes/admin_config.php')) include_once __DIR__ . '/includes/admin_config.php'; ?>
    <script>
        // Expose admin flag and page/context for the assistant client
        window.GEMINI_ADMIN = <?= (defined('ADMIN_USER_IDS') && in_array($user_id, ADMIN_USER_IDS)) ? 'true' : 'false'; ?>;
        window.GEMINI_CONTEXT = 'dashboard';
    </script>
    
    <!-- page loader removed -->
    <script>
        window.showToast = function(title, message, icon = '👋') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = 'toast-notification show';
            toast.innerHTML = `<div class="toast-icon">${icon}</div><div><h4 class="toast-title">${title}</h4><p class="toast-message">${message}</p></div>`;
            container.appendChild(toast);
            setTimeout(() => toast.remove(), 5000);
        }

        document.addEventListener('DOMContentLoaded', function() {
            const welcomeMsg = "<?php echo $welcome_message ? addslashes($welcome_message) : ''; ?>";
            if (welcomeMsg) showToast("Welcome Back!", welcomeMsg, '👋');
            
            // Sidebar toggle handled centrally by js/sidebar.js

            // LOGOUT MODAL is handled globally; no per-page handlers to avoid duplicates

            // NOTE: Badge updater removed - now handled by js/dashboard_notifications.js
            // to avoid duplicate intervals and memory leaks

            // ONBOARDING / TUTORIAL UI HANDLER
            try {
                const showOnboarding = document.getElementById('showOnboarding')?.value === '1';
                const onboardingModal = document.getElementById('onboardingModal');
                if (showOnboarding && onboardingModal) {
                    onboardingModal.style.display = 'block';
                }

                // slide logic
                let currentStep = 1;
                const totalSteps = 3;
                const prevBtn = document.getElementById('onboardingPrev');
                const nextBtn = document.getElementById('onboardingNext');
                const finishBtn = document.getElementById('onboardingFinish');
                const closeBtn = document.getElementById('onboardingCloseBtn');
                const skipBtn = document.getElementById('onboardingSkip');
                const startBtn = document.getElementById('onboardingStart');

                function updateSteps() {
                    for (let i = 1; i <= totalSteps; i++) {
                        const el = document.querySelector('.onboarding-step[data-step="' + i + '"]');
                        if (!el) continue;
                        el.style.display = (i === currentStep) ? 'block' : 'none';
                    }
                    if (prevBtn) prevBtn.style.display = currentStep > 1 ? 'inline-block' : 'none';
                    if (nextBtn) nextBtn.style.display = currentStep < totalSteps ? 'inline-block' : 'none';
                    if (finishBtn) finishBtn.style.display = currentStep === totalSteps ? 'inline-block' : 'none';
                }

                if (prevBtn) prevBtn.addEventListener('click', () => { currentStep = Math.max(1, currentStep - 1); updateSteps(); });
                if (nextBtn) nextBtn.addEventListener('click', () => { currentStep = Math.min(totalSteps, currentStep + 1); updateSteps(); });

                // SPA teardown: clear any intervals and references to avoid memory leaks when navigating away
                window.spaTeardown = function() {
                    try { if (window.dashboardBadgeInterval) { clearInterval(window.dashboardBadgeInterval); window.dashboardBadgeInterval = null; } } catch(e) {}
                    try { if (typeof window.onboardingTeardown === 'function') window.onboardingTeardown(); } catch(e) {}
                    try { if (typeof window.timerInterval !== 'undefined' && window.timerInterval) { clearInterval(window.timerInterval); window.timerInterval = null; } } catch(e) {}
                };
                if (finishBtn) finishBtn.addEventListener('click', () => { markOnboardingSeen(true); });
                if (skipBtn) skipBtn.addEventListener('click', () => { markOnboardingSeen(true); });
                if (closeBtn) closeBtn.addEventListener('click', () => { onboardingModal.style.display = 'none'; });
                if (startBtn) startBtn.addEventListener('click', () => { onboardingModal.style.display = 'none'; startTour(); });

                function markOnboardingSeen(forceDontShow) {
                    const dontShowCheckbox = document.getElementById('onboardingDontShow');
                    const dontShow = forceDontShow || (dontShowCheckbox && dontShowCheckbox.checked);
                    const payload = { action: 'mark_seen', dont_show: dontShow ? 1 : 0 };

                    fetch('ajax_onboarding.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    }).then(r => r.json()).then(data => {
                        if (data && data.success) {
                            onboardingModal.style.display = 'none';
                            document.getElementById('showOnboarding').value = '0';
                            try {
                                const u2 = new URL(window.location.href);
                                u2.searchParams.delete('start_tour');
                                history.replaceState({}, '', u2.pathname + (u2.search ? ('?' + u2.searchParams.toString()) : '') + u2.hash);
                            } catch(e) { /* ignore */ }
                            if (dontShow) localStorage.setItem('onboarding_dont_show', '1');
                        } else {
                            onboardingModal.style.display = 'none';
                        }
                    }).catch(err => {
                        console.warn('Onboarding save failed', err);
                        onboardingModal.style.display = 'none';
                    });
                }

                if (localStorage.getItem('onboarding_dont_show') === '1') {
                    const el = document.getElementById('showOnboarding'); if (el) el.value = '0'; if (onboardingModal) onboardingModal.style.display = 'none';
                }
                updateSteps();

                // ---- GUIDED TOUR FUNCTIONS -------------------------------------
                let tourState = { active: false, step: 0, steps: [] };

                function getTourSteps() {
                    return [
                        { sel: '#quickAddForm', title: 'Add a Task', desc: 'Quickly add a task and ask AI to generate a smart plan.' , padding: 12 },
                        { sel: '.focus-card', title: 'Focus Mode', desc: 'Use Focus Mode to start a focused session and earn points.' , padding: 12 },
                        { sel: '.scheduler-card', title: 'AI Smart Scheduler', desc: 'Let the AI Assistant recommend a schedule for your day.' , padding: 12 },
                        { sel: 'a[href="messaging.php"]', title: 'Messaging', desc: 'Message classmates, create groups and stay connected.' , padding: 12 },
                        { sel: '#gametagOverlay .gametag-trigger', title: 'Profile & Avatar', desc: 'Open your gametag overlay here to view profile, points and quick actions.' , padding: 12 }
                    ];
                }

                // Debounce helper
                function debounce(func, wait) {
                    let t = null;
                    return function(...args) {
                        if (t) clearTimeout(t);
                        t = setTimeout(() => { func.apply(this, args); t = null; }, wait);
                    }
                }

                function startTour() {
                    try {
                        tourState.steps = getTourSteps();
                        tourState.active = true; tourState.step = 0;
                        document.getElementById('tourOverlay').style.display = 'block';
                        document.getElementById('tourOverlay').style.pointerEvents = 'auto';
                        showTourStep(tourState.step);
                        // attach resize/scroll listeners to reposition highlight in real-time
                        window.addEventListener('resize', realignHandler);
                        window.addEventListener('scroll', realignHandler);
                    } catch(e) { console.warn('startTour failed', e); }
                }

                function stopTour(andMarkSeen = true) {
                    tourState.active = false; tourState.step = 0;
                    const overlay = document.getElementById('tourOverlay'); if (overlay) overlay.style.display = 'none';
                    // detach listeners
                    window.removeEventListener('resize', realignHandler);
                    window.removeEventListener('scroll', realignHandler);
                    if (andMarkSeen) {
                        // Mark as seen on server so it doesn't auto-run again
                        fetch('ajax_onboarding.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'mark_seen', dont_show: 0 }) })
                        .catch(() => {});
                    }
                    try { const g = document.getElementById('gametagOverlay'); if (g) g.classList.remove('active'); } catch(e) {}
                }

                // reposition wrapper used by event listeners
                const realignHandler = debounce(function() {
                    if (!tourState.active) return;
                    // re-run the current step placement
                    try { showTourStep(tourState.step); } catch(e) { /* ignore */ }
                }, 100);

                function showTourStep(idx) {
                    const steps = tourState.steps || [];
                    if (!steps[idx]) { stopTour(true); return; }
                    const step = steps[idx];
                    const el = document.querySelector(step.sel);
                    const highlight = document.getElementById('tourHighlight');
                    const tooltip = document.getElementById('tourTooltip');
                    const title = document.getElementById('tourTitle');
                    const desc = document.getElementById('tourDesc');

                    title.innerText = step.title;
                    desc.innerText = step.desc;

                    if (!el) {
                        // If element is missing, center the tooltip
                        highlight.style.display = 'none';
                        tooltip.style.left = '50%'; tooltip.style.top = '30%'; tooltip.style.transform = 'translateX(-50%)';
                        return;
                    }

                    // clear any previous tour-driven gametag overlay state
                    try { const g = document.getElementById('gametagOverlay'); if (g) g.classList.remove('active'); } catch(e) {}

                    // Scroll element into view then calculate viewport coordinates (overlay is fixed => use viewport coordinates)
                    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    const rect = el.getBoundingClientRect();
                    const pad = step.padding || 10;
                    // position highlight using viewport coordinates (no scroll offset)
                    highlight.style.display = 'block';
                    highlight.style.left = Math.max(0, rect.left - pad) + 'px';
                    highlight.style.top = Math.max(0, rect.top - pad) + 'px';
                    highlight.style.width = Math.max(0, rect.width + pad * 2) + 'px';
                    highlight.style.height = Math.max(0, rect.height + pad * 2) + 'px';

                    // Place tooltip near element — prefer right / bottom then fallback
                    let ttLeft = rect.right + 16;
                    let ttTop = rect.top;
                    const tooltipEl = tooltip;

                    // If put right doesn't fit, try below the element
                    const vw = window.innerWidth;
                    const vh = window.innerHeight;
                    // ensure tooltip dimensions available
                    const ttW = tooltipEl.offsetWidth || Math.min(380, vw - 40);
                    const ttH = tooltipEl.offsetHeight || 120;
                    if (ttLeft + ttW > vw - 10) {
                        ttLeft = Math.max(10, rect.left);
                        ttTop = rect.bottom + 14;
                    }

                    // If still off bottom or top, clamp inside viewport
                    if (ttTop + ttH > vh - 10) ttTop = Math.max(10, vh - ttH - 10);
                    if (ttTop < 10) ttTop = 10;

                    tooltipEl.style.left = Math.max(8, Math.min(ttLeft, vw - ttW - 8)) + 'px';
                    tooltipEl.style.top = ttTop + 'px';
                    tooltipEl.style.transform = 'none';

                    // If current step is gametag trigger, activate the overlay so the card (View Profile) is visible
                    try {
                        if (step.sel && step.sel.indexOf('gametag') !== -1) {
                            const g = document.getElementById('gametagOverlay'); if (g) g.classList.add('active');
                        }
                    } catch(e) { /* ignore */ }

                    // Update Prev/Next button states
                    document.getElementById('tourPrev').style.display = idx > 0 ? 'inline-block' : 'none';
                    document.getElementById('tourNext').style.display = idx < (tourState.steps.length - 1) ? 'inline-block' : 'none';
                }

                // Attach tour controls
                document.getElementById('tourPrev').addEventListener('click', function() { if (tourState.step > 0) { tourState.step--; showTourStep(tourState.step); } });
                document.getElementById('tourNext').addEventListener('click', function() { if (tourState.step < tourState.steps.length - 1) { tourState.step++; showTourStep(tourState.step); } else stopTour(true); });
                document.getElementById('tourEnd').addEventListener('click', function() { stopTour(true); });

                // Start tour if URL contains start_tour=1 or onboarding triggered it
                if (window.location.search.indexOf('start_tour=1') !== -1) {
                    // small delay to ensure DOM is ready and layout settled
                    setTimeout(() => {
                        startTour();
                        try {
                            // remove start_tour from URL so refresh or navigation won't restart the tour
                            const u = new URL(window.location.href);
                            u.searchParams.delete('start_tour');
                            history.replaceState({}, '', u.pathname + (u.search ? ('?' + u.searchParams.toString()) : '') + u.hash);
                        } catch(e) { /* ignore */ }
                    }, 800);
                }
            } catch(e) { console.warn('Onboarding init failed', e); }
        });
    </script>
    
    <?php include 'includes/call_overlay.php'; ?> 
    <input type="hidden" id="currentUserId" value="<?php echo $_SESSION['user_id']; ?>">
    <input type="hidden" id="showOnboarding" value="<?php echo isset($show_onboarding) && $show_onboarding ? '1' : '0'; ?>">
</body>
</html>