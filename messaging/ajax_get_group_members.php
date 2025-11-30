<?php
include 'config.php';

// Check session to prevent error
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_GET['group_id'])) exit;

$group_id = intval($_GET['group_id']);
$viewer_id = $_SESSION['user_id'];

// 1. Identify who is the Creator (Admin)
$creator_id = 0;
$g_sql = "SELECT created_by FROM groups WHERE group_id = $group_id";
$g_res = $conn->query($g_sql);
if($g_res && $row = $g_res->fetch_assoc()) {
    $creator_id = $row['created_by'];
}

$i_am_admin = ($viewer_id == $creator_id);

// 2. Get Members
$sql = "SELECT u.id, u.firstname, u.lastname, u.profile_photo 
        FROM group_members gm 
        JOIN users u ON gm.user_id = u.id 
        WHERE gm.group_id = $group_id
        ORDER BY (u.id = $creator_id) DESC, u.firstname ASC"; // Admin first

$result = $conn->query($sql);

echo "<div style='display:flex; flex-direction:column; gap:10px; padding: 10px;'>";

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $member_id = $row['id'];
        $is_admin = ($member_id == $creator_id);
        $is_me = ($member_id == $viewer_id);
        
        // Badge Logic
        $badge = "";
        if ($is_admin) {
            $badge = "<span style='background:linear-gradient(45deg, #10b981, #059669); color:white; padding:2px 8px; border-radius:12px; font-size:0.7rem; font-weight:bold; box-shadow:0 2px 5px rgba(16,185,129,0.4);'>ADMIN</span>";
        } else if ($is_me) {
             $badge = "<span style='background:#334155; color:#cbd5e1; padding:2px 8px; border-radius:12px; font-size:0.7rem;'>YOU</span>";
        }

        // Kick Button Logic (Only Admin can kick others)
        $kick_btn = "";
        if ($i_am_admin && !$is_admin) {
            $kick_btn = "
            <button onclick='kickMember($group_id, $member_id)' title='Kick User' 
                    style='background:rgba(239,68,68,0.1); color:#ef4444; border:1px solid rgba(239,68,68,0.3); 
                           width:30px; height:30px; border-radius:50%; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all 0.2s;'>
                <i class='bx bx-user-x'></i>
            </button>";
        }

        // Avatar Logic
        $photo = $row['profile_photo'];
        if (!empty($photo) && file_exists("../profile-img/$photo")) {
            $avatar = "<img src='profile-img/$photo' style='width:100%; height:100%; border-radius:50%; object-fit:cover; border:2px solid #1e293b;'>";
        } else {
            $initial = strtoupper(substr($row['firstname'], 0, 1));
            $bg_color = $is_admin ? '#10b981' : '#6366f1';
            $avatar = "<div style='width:100%; height:100%; background:$bg_color; color:white; display:flex; align-items:center; justify-content:center; font-weight:bold; border-radius:50%; border:2px solid #1e293b;'>$initial</div>";
        }

        // Modern Card Design
        echo "
        <div style='display:flex; align-items:center; justify-content:space-between; background:#1e293b; padding:10px 15px; border-radius:12px; border:1px solid #334155; transition: transform 0.2s;'>
            <div style='display:flex; align-items:center; gap:12px;'>
                <div style='width:45px; height:45px;'>$avatar</div>
                <div style='display:flex; flex-direction:column;'>
                    <span style='color:white; font-weight:500; font-size:0.95rem;'>" . htmlspecialchars($row['firstname'] . ' ' . $row['lastname']) . "</span>
                    <div style='margin-top:2px;'>$badge</div>
                </div>
            </div>
            <div>
                $kick_btn
            </div>
        </div>";
    }
} else {
    echo "<p style='padding:20px; text-align:center; color:#888;'>No members found.</p>";
}
echo "</div>";
?>