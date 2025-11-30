<?php
// messaging/ajax_get_chat_list.php
include 'config.php';

if (!isset($_SESSION['user_id'])) { exit; }

$current_user_id = $_SESSION['user_id'];
$query = $_POST['query'] ?? '';
$search_term = "%{$query}%";

// ==========================================================================
// 1. QUERY FOR DIRECT MESSAGES (USERS)
// ==========================================================================
$sql_users = "
    SELECT 
        u.id AS chat_id,
        CONCAT(u.firstname, ' ', u.lastname) AS chat_name,
        u.profile_photo,
        u.last_activity,
        'user' AS chat_type,
        
        -- Check Muted Status
        (SELECT COUNT(*) FROM muted_users WHERE user_id = $current_user_id AND muted_user_id = u.id) as is_muted,
        
        -- Get Last Message
        (SELECT message FROM messages 
         WHERE (sender_id = u.id AND receiver_id = $current_user_id) 
            OR (sender_id = $current_user_id AND receiver_id = u.id) 
         ORDER BY timestamp DESC LIMIT 1) as last_msg,
         
        -- Get Last Message Time
        (SELECT timestamp FROM messages 
         WHERE (sender_id = u.id AND receiver_id = $current_user_id) 
            OR (sender_id = $current_user_id AND receiver_id = u.id) 
         ORDER BY timestamp DESC LIMIT 1) as last_msg_time,
         
        -- Get Unread Count
        (SELECT COUNT(*) FROM messages 
         WHERE sender_id = u.id AND receiver_id = $current_user_id AND is_read = 0) as unread_count

    FROM users u
    
    JOIN (
        SELECT DISTINCT
            CASE WHEN sender_id = $current_user_id THEN receiver_id ELSE sender_id END AS user_id
        FROM messages
        WHERE (sender_id = $current_user_id OR receiver_id = $current_user_id) 
          AND (group_id IS NULL OR group_id = 0) 
    ) AS conversations ON u.id = conversations.user_id
    
    LEFT JOIN chat_settings cs ON (cs.user_id = $current_user_id AND cs.partner_id = u.id)
    LEFT JOIN blocked_users b1 ON (b1.blocker_id = $current_user_id AND b1.blocked_id = u.id)
    LEFT JOIN blocked_users b2 ON (b2.blocker_id = u.id AND b2.blocked_id = $current_user_id)

    WHERE u.id != $current_user_id
    
    AND (cs.is_archived IS NULL OR cs.is_archived = 0)
    AND (cs.is_request IS NULL OR cs.is_request = 0)
    AND b1.id IS NULL 
    AND b2.id IS NULL
";

if (!empty($query)) {
    $sql_users .= " AND (u.firstname LIKE '$search_term' OR u.lastname LIKE '$search_term')";
}

// ==========================================================================
// 2. QUERY FOR GROUP CHATS
// ==========================================================================
$sql_groups = "
    SELECT 
        g.group_id AS chat_id,
        g.group_name AS chat_name,
        'group_default' AS profile_photo, 
        NULL AS last_activity, 
        'group' AS chat_type,
        0 AS is_muted, 
        
        (SELECT message FROM messages WHERE group_id = g.group_id ORDER BY timestamp DESC LIMIT 1) as last_msg,
        (SELECT timestamp FROM messages WHERE group_id = g.group_id ORDER BY timestamp DESC LIMIT 1) as last_msg_time,
        (SELECT COUNT(*) FROM messages m2 
         WHERE m2.group_id = g.group_id 
           AND m2.sender_id != $current_user_id
           AND m2.is_read = 0 
        ) as unread_count

    FROM groups g
    JOIN group_members gm ON g.group_id = gm.group_id
    
    LEFT JOIN chat_settings cs ON (cs.user_id = $current_user_id AND cs.group_id = g.group_id)

    WHERE gm.user_id = $current_user_id
    
    AND (cs.is_archived IS NULL OR cs.is_archived = 0)
";

if (!empty($query)) {
    $sql_groups .= " AND g.group_name LIKE '$search_term'";
}

// ==========================================================================
// 3. COMBINE AND ORDER
// ==========================================================================
$final_sql = "($sql_users) UNION ALL ($sql_groups) ORDER BY last_msg_time DESC";

$result = $conn->query($final_sql);
$output = '';

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $id = $row['chat_id'];
        $name = htmlspecialchars($row['chat_name']);
        $type = $row['chat_type'];
        
        // Avatar Logic
        $avatar_html = '';
        if ($type === 'group') {
            $avatar_html = "<div style='background: linear-gradient(45deg, #6366f1, #8b5cf6); color:white; width:100%; height:100%; display:flex; align-items:center; justify-content:center; border-radius:50%; font-size: 1.2rem; font-weight:bold;'><i class='bx bx-group' style='font-size:1.1rem;'></i></div>";
        } else {
            $profile_photo_db = $row['profile_photo'];
            $img_path_check = '../profile-img/' . $profile_photo_db; 
            $img_src = 'profile-img/' . htmlspecialchars($profile_photo_db);
            if (!empty($profile_photo_db) && file_exists($img_path_check)) {
                $avatar_html = "<img src='$img_src' alt='$name' style='width:100%; height:100%; object-fit:cover; border-radius:50%;'>";
            } else {
                $initial = strtoupper(substr($name, 0, 1));
                $avatar_html = "<div style='background:#334155; color:white; width:100%; height:100%; display:flex; align-items:center; justify-content:center; border-radius:50%; font-weight:bold;'>$initial</div>";
            }
        }

        // Status Logic
        $status_class = 'offline';
        if ($type === 'user' && $row['last_activity']) {
            $time_diff = time() - strtotime($row['last_activity']);
            if ($time_diff < 120) $status_class = 'online';
        } 

        $is_muted = $row['is_muted'];
        $mute_icon = ($is_muted > 0) ? "<span style='font-size:0.8rem; margin-left:5px;'><i class='bx bx-volume-mute'></i></span>" : "";

        $last_msg = "Start a conversation";
        if ($row['last_msg']) {
             $last_msg = htmlspecialchars($row['last_msg']);
             
             // --- FIX: Hide raw call log marker ---
                 if (strpos($last_msg, '[[CALL_LOG:') === 0) {
                 $last_msg = "<i class='bx bx-phone'></i> Completed call";
             }
             // ------------------------------------
             
             if (strpos($last_msg, 'uploads/') === 0) $last_msg = "<i>[Attachment]</i>";
             if (strlen($last_msg) > 30) $last_msg = substr($last_msg, 0, 30) . '...';
        }

        $time = $row['last_msg_time'] ? date('h:i A', strtotime($row['last_msg_time'])) : "";
        $unread_count = $row['unread_count']; 
        $unread_badge = $unread_count > 0 ? "<span class='chat-unread-count'>$unread_count</span>" : "";

        // Output HTML
        $output .= "
        <div class='chat-item' data-chat-id='$id' data-chat-type='$type' data-chat-name='$name' data-muted='$is_muted'>
            <div class='chat-avatar'>
                $avatar_html
                " . ($type === 'user' ? "<span class='chat-list-status $status_class'></span>" : "") . "
            </div>
            <div class='chat-info'>
                <h4 class='chat-name'>$name $mute_icon</h4>
                <p class='last-message'>$last_msg</p>
            </div>
            <div class='chat-meta'>
                <span class='chat-time'>$time</span>
                $unread_badge
            </div>
            <button class='btn-chat-item-options'>⋮</button>
        </div>
        ";
    }
} else {
     $output = "<p style='text-align:center; color: var(--text-gray); padding: 1rem;'>No conversations found.</p>";
}
echo $output;
?>