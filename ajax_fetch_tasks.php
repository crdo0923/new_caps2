<?php
// ajax_fetch_tasks.php
session_start();

if (file_exists('messaging/config.php')) include 'messaging/config.php';
elseif (file_exists('../messaging/config.php')) include '../messaging/config.php';
else $conn = new mysqli('localhost', 'root', '', 'smart_study');

if (!isset($_SESSION['user_id'])) { echo '<p style="text-align:center; color:red;">Login required</p>'; exit; }

$user_id = $_SESSION['user_id'];
$filter = $_POST['filter'] ?? 'latest';

// QUERY
$sql = "SELECT * FROM tasks WHERE user_id = ? AND status = 'pending'";
switch ($filter) {
    case 'oldest': $sql .= " ORDER BY created_at ASC"; break;
    case 'priority': $sql .= " ORDER BY FIELD(priority, 'High', 'Medium', 'Low')"; break;
    case 'subject': $sql .= " ORDER BY subject ASC"; break;
    case 'latest': default: $sql .= " ORDER BY id DESC"; break;
}
$sql .= " LIMIT 20";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        // Styles
        $prio = $row['priority'];
        $p_class = ($prio == 'High') ? 'priority-urgent' : (($prio == 'Medium') ? 'priority-medium' : 'priority-low');
        $b_class = ($prio == 'High') ? 'urgent' : (($prio == 'Medium') ? 'high' : 'medium');
        
        // Display Data
        $tid = $row['id'];
        $subject_display = htmlspecialchars($row['subject'] ?? 'General', ENT_QUOTES);
        $title = htmlspecialchars($row['title']);
        $desc = htmlspecialchars($row['description']);
        $time = htmlspecialchars($row['time_sched']);
        $dur = htmlspecialchars($row['duration']);
        $prio_label = strtoupper($prio);

        // --- THE FIX IS HERE ALSO ---
        $js_title = htmlspecialchars($row['title'], ENT_QUOTES);
        $js_desc = htmlspecialchars($row['description'], ENT_QUOTES);
        $js_desc = str_replace(array("\r", "\n"), ' ', $js_desc); // Remove newlines
        $js_dur = htmlspecialchars($row['duration'], ENT_QUOTES);

        echo "
        <div class='schedule-item $p_class' id='task-$tid' style='animation: slideIn 0.3s ease; position: relative; display: flex; align-items: center; justify-content: space-between; padding: 15px; background: var(--dark-card); margin-bottom: 10px; border-radius: 12px; border: 1px solid var(--border-color); width: 100%; box-sizing: border-box;'>
            
            <div style='flex-grow: 1; padding-right: 15px; overflow: hidden;'>
                <div class='schedule-header-row' style='display: flex; align-items: center; gap: 10px; margin-bottom: 5px;'>
                    <h4 style='margin: 0; color: var(--text-light); font-size: 1.1rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;'>$title <span class='subject-badge'>$subject_display</span></h4>
                    <span class='priority-badge $b_class' style='font-size: 0.75rem; padding: 3px 10px; border-radius: 4px; background: rgba(255,255,255,0.1);'>$prio_label</span>
                </div>
                <p class='schedule-desc' style='margin: 0; color: var(--text-gray); font-size: 0.9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;'>$desc</p>
                <div class='schedule-time-block' style='margin-top: 8px; font-size: 0.85rem; color: #6366f1; display: flex; align-items: center; gap: 5px;'>
                    <i class='bx bx-time' style='font-size: 1.1rem;'></i> $dur ($time)
                </div>
            </div>

            <div class='task-actions' style='display: flex; align-items: center; gap: 10px; flex-shrink: 0;'>
                <button type='button' onclick=\"startFocusMode('$js_dur', $tid, '$js_title')\" title='Start Focus' style='background: #6366f1; color: white; border: none; padding: 10px 20px; border-radius: 10px; cursor: pointer; font-weight: 600; font-size: 0.95rem; display: flex; align-items: center; gap: 6px;'>
                    <i class='bx bx-play' style='font-size: 1.4rem;'></i> Start
                </button>
                
                <button type='button' onclick=\"openEditTaskModal($tid, '$js_title', '$js_desc')\" title='Edit' style='background: rgba(255,255,255,0.05); border: 1px solid #334155; color: #94a3b8; width: 45px; height: 45px; border-radius: 10px; cursor: pointer; display: flex; align-items: center; justify-content: center;'>
                    <i class='bx bx-edit-alt' style='font-size: 1.4rem;'></i>
                </button>

                <button type='button' onclick=\"confirmDeleteTask($tid)\" title='Delete' style='background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; color: #ef4444; width: 45px; height: 45px; border-radius: 10px; cursor: pointer; display: flex; align-items: center; justify-content: center;'>
                    <i class='bx bx-trash' style='font-size: 1.4rem;'></i>
                </button>
            </div>
        </div>";
    }
} else {
    echo '<div style="text-align: center; padding: 20px; color: #94a3b8;">
            <i class="bx bx-filter-alt" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.5;"></i>
            <p style="font-size: 1.1rem;">No tasks found.</p>
          </div>';
}
?>