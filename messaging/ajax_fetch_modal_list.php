<?php
// messaging/ajax_fetch_modal_list.php
include 'config.php';

// Security Check
if (!isset($_SESSION['user_id'])) { exit; }

$current_user_id = $_SESSION['user_id'];
$type = $_POST['type'] ?? ''; // values: 'blocked', 'restricted', 'archived', 'requests'

$output = '';

// --- HELPER FUNCTION FOR AVATAR ---
if (!function_exists('getAvatar')) {
    function getAvatar($row) {
        $img_path = '../profile-img/' . $row['profile_photo'];
        $display_path = 'profile-img/' . $row['profile_photo'];
        
        if (!empty($row['profile_photo']) && file_exists($img_path)) {
            return "<img src='$display_path' style='width:100%; height:100%; border-radius:50%; object-fit:cover;'>";
        }
        $initial = strtoupper(substr($row['firstname'], 0, 1));
        return "<div style='width:100%; height:100%; background:#6366f1; color:white; display:flex; align-items:center; justify-content:center; font-weight:bold;'>$initial</div>";
    }
}

// 1. FETCH BLOCKED
if ($type === 'blocked') {
    $sql = "SELECT u.id, u.firstname, u.lastname, u.profile_photo 
            FROM blocked_users b 
            JOIN users u ON b.blocked_id = u.id 
            WHERE b.blocker_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            $avatar = getAvatar($row);
            $name = htmlspecialchars($row['firstname'] . ' ' . $row['lastname']);
            $output .= "
            <div class='list-item-row' id='blocked-row-{$row['id']}'>
                <div class='list-item-info'>
                    <div class='default-avatar-small' style='width:40px;height:40px;font-size:1rem;'>$avatar</div>
                    <span>$name</span>
                </div>
                <button class='list-btn' style='color:#ef4444;' onclick='unblockUser({$row['id']})'>Unblock</button>
            </div>";
        }
    } else {
        $output = "<div class='empty-state-container'><div style='font-size: 3rem; opacity: 0.5; margin-bottom: 10px;'><i class='bx bx-block' style='font-size:3rem;opacity:0.5;margin-bottom:10px;'></i></div><p class='empty-state-text' style='color: var(--text-gray);'>No blocked accounts.</p></div>";
    }
}

// 2. FETCH RESTRICTED (MUTED)
if ($type === 'restricted') {
    $sql = "SELECT u.id, u.firstname, u.lastname, u.profile_photo 
            FROM muted_users m 
            JOIN users u ON m.muted_user_id = u.id 
            WHERE m.user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            $avatar = getAvatar($row);
            $name = htmlspecialchars($row['firstname'] . ' ' . $row['lastname']);
            $output .= "
            <div class='list-item-row' id='restricted-row-{$row['id']}'>
                <div class='list-item-info'>
                    <div class='default-avatar-small' style='width:40px;height:40px;font-size:1rem;'>$avatar</div>
                    <span>$name</span>
                </div>
                <button class='list-btn' onclick='unrestrictUser({$row['id']})'>Unrestrict</button>
            </div>";
        }
    } else {
        $output = "<div class='empty-state-container'><div style='font-size: 3rem; opacity: 0.5; margin-bottom: 10px;'><i class='bx bx-shield' style='font-size:3rem;opacity:0.5;margin-bottom:10px;'></i></div><p class='empty-state-text' style='color: var(--text-gray);'>No restricted accounts.</p></div>";
    }
}

// 3. FETCH ARCHIVED (Users & Groups)
if ($type === 'archived') {
    $has_items = false;

    // A. Fetch Archived Users
    $sql_users = "SELECT u.id, u.firstname, u.lastname, u.profile_photo 
                  FROM chat_settings cs 
                  JOIN users u ON cs.partner_id = u.id 
                  WHERE cs.user_id = ? AND cs.is_archived = 1";
    $stmt = $conn->prepare($sql_users);
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $has_items = true;
        while ($row = $res->fetch_assoc()) {
            $avatar = getAvatar($row);
            $name = htmlspecialchars($row['firstname'] . ' ' . $row['lastname']);
            $output .= "
            <div class='list-item-row' id='archived-row-user-{$row['id']}'>
                <div class='list-item-info'>
                    <div class='default-avatar-small' style='width:40px;height:40px;'>$avatar</div>
                    <span>$name</span>
                </div>
                <button class='list-btn' onclick='unarchiveChat({$row['id']}, \"user\")'>Unarchive</button>
            </div>";
        }
    }

    // B. Fetch Archived Groups
    $sql_groups = "SELECT g.group_id, g.group_name 
                   FROM chat_settings cs 
                   JOIN groups g ON cs.group_id = g.group_id 
                   WHERE cs.user_id = ? AND cs.is_archived = 1";
    $stmt_g = $conn->prepare($sql_groups);
    $stmt_g->bind_param("i", $current_user_id);
    $stmt_g->execute();
    $res_g = $stmt_g->get_result();

    if ($res_g->num_rows > 0) {
        $has_items = true;
        while ($row = $res_g->fetch_assoc()) {
            $avatar = "<div style='width:100%; height:100%; background:#6366f1; color:white; display:flex; align-items:center; justify-content:center; border-radius:50%; font-size:1.2rem;'><i class='bx bx-group' style='font-size:1.2rem;'></i></div>";
            $name = htmlspecialchars($row['group_name']);
            
            $output .= "
            <div class='list-item-row' id='archived-row-group-{$row['group_id']}'>
                <div class='list-item-info'>
                    <div class='default-avatar-small' style='width:40px;height:40px;'>$avatar</div>
                    <span>$name</span>
                </div>
                <button class='list-btn' onclick='unarchiveChat({$row['group_id']}, \"group\")'>Unarchive</button>
            </div>";
        }
    }

    if (!$has_items) {
        $output = "<div class='empty-state-container'><div style='font-size: 3rem; opacity: 0.5; margin-bottom: 10px;'><i class='bx bx-archive' style='font-size:3rem;opacity:0.5;margin-bottom:10px;'></i></div><p class='empty-state-text' style='color: var(--text-gray);'>No archived chats.</p></div>";
    }
}

// 4. FETCH MESSAGE REQUESTS
if ($type === 'requests') {
    $sql = "SELECT u.id, u.firstname, u.lastname, u.profile_photo 
            FROM chat_settings cs 
            JOIN users u ON cs.partner_id = u.id 
            WHERE cs.user_id = ? AND cs.is_request = 1";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $current_user_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        while ($row = $res->fetch_assoc()) {
            $avatar = getAvatar($row);
            $name = htmlspecialchars($row['firstname'] . ' ' . $row['lastname']);
            $id = $row['id'];
            
            $output .= "
            <div class='list-item-row' id='req-row-$id' style='display:flex; justify-content:space-between; align-items:center; padding:12px; border-bottom:1px solid #334155;'>
                <div class='list-item-info' style='display:flex; align-items:center; gap:10px;'>
                    <div style='width:40px;height:40px;'>$avatar</div>
                    <span style='color:white; font-weight:500;'>$name</span>
                </div>
                <div style='display:flex; gap:5px;'>
                    <button class='list-btn' style='background:var(--primary-color); color:white; border:none; padding:5px 10px; border-radius:5px; cursor:pointer;' onclick='acceptRequest($id)'>Accept</button>
                    <button class='list-btn' style='background:#ef4444; color:white; border:none; padding:5px 10px; border-radius:5px; cursor:pointer;' onclick='declineRequest($id)'>Decline</button>
                </div>
            </div>";
        }
    } else {
        $output = "<div class='empty-state-container' style='padding:20px; text-align:center;'><div style='font-size: 3rem; opacity: 0.5; margin-bottom: 10px;'><i class='bx bx-check' style='font-size:3rem;opacity:0.5;margin-bottom:10px;'></i></div><p class='empty-state-text' style='color: var(--text-gray);'>No new message requests.</p></div>";
    }
}

echo $output;
?>