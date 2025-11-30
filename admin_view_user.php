<?php
session_start();
if (!file_exists(__DIR__ . '/includes/admin_config.php')) {
    echo "Admin config missing. Create includes/admin_config.php with ADMIN_USER_IDS."; exit;
}
include_once __DIR__ . '/includes/admin_config.php';

if (!isset($_SESSION['user_id']) || !in_array((int)$_SESSION['user_id'], ADMIN_USER_IDS)) {
    header('HTTP/1.1 403 Forbidden'); echo "Access denied."; exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { echo "Invalid user id."; exit; }

$conn = new mysqli('localhost','root','','smart_study'); if ($conn->connect_error) { echo "DB connection failed."; exit; }
$stmt = $conn->prepare('SELECT id, firstname, lastname, email, points, profile_photo, bio FROM users WHERE id = ?');
$stmt->bind_param('i', $id); $stmt->execute(); $res = $stmt->get_result(); $user = $res->fetch_assoc(); $stmt->close();
if (!$user) { echo "User not found."; exit; }

?>
<!doctype html>
<html><head><meta charset="utf-8"><title>View User</title><link rel="stylesheet" href="css/index.css"></head>
<body style="background:#071128; color:#e6eef9; font-family:Inter, sans-serif; padding:24px;">
  <a href="admin_dashboard.php" style="color:#9fb2ff; display:inline-block; margin-bottom:12px;">← Back to Admin</a>
  <h1><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></h1>
  <div style="display:flex; gap:12px; align-items:center;">
    <div style="width:120px; height:120px; border-radius:8px; overflow:hidden; background:#0f172a; display:flex; align-items:center; justify-content:center;">
      <?php if (!empty($user['profile_photo']) && file_exists('profile-img/'. $user['profile_photo'])): ?><img src="profile-img/<?php echo htmlspecialchars($user['profile_photo']); ?>" style="width:100%; height:100%; object-fit:cover;" /><?php else: ?> <div style="font-size:40px;"><?php echo strtoupper(substr($user['firstname'],0,1)); ?></div><?php endif; ?>
    </div>
    <div>
      <div style="font-weight:700; font-size:1.15rem;">Email: <?php echo htmlspecialchars($user['email'] ?? '—'); ?></div>
      <div style="color:#9aa7c7; margin-top:6px;">Points: <?php echo number_format($user['points'] ?? 0); ?></div>
      <div style="margin-top:10px; color:#cfe0ff;">Bio: <?php echo htmlspecialchars($user['bio'] ?? ''); ?></div>
    </div>
  </div>
</body></html>
