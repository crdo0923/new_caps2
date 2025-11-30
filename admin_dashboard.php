<?php
session_start();
if (!file_exists(__DIR__ . '/includes/admin_config.php')) {
    echo "Admin config missing. Create includes/admin_config.php with ADMIN_USER_IDS."; exit;
}
include_once __DIR__ . '/includes/admin_config.php';

if (!isset($_SESSION['user_id']) || !in_array((int)$_SESSION['user_id'], ADMIN_USER_IDS)) {
    header('HTTP/1.1 403 Forbidden');
    echo "Access denied."; exit;
}

$conn = new mysqli('localhost','root','','smart_study');
if ($conn->connect_error) { echo "DB connect failed."; exit; }

// Allow master admin to toggle whether admin signup is enabled
$state_file = __DIR__ . '/includes/admin_signup_state.json';
$signup_enabled = true;
if (file_exists($state_file)) {
  $raw = @file_get_contents($state_file);
  $sjson = json_decode($raw, true);
  if (is_array($sjson) && isset($sjson['enabled'])) $signup_enabled = (bool)$sjson['enabled'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_signup_enabled'])) {
  // Only allow the master admin to toggle signup state
  $current_user = (int)($_SESSION['user_id'] ?? 0);
  if (!defined('ADMIN_MASTER_ID') || $current_user !== (int)ADMIN_MASTER_ID) {
    header('HTTP/1.1 403 Forbidden'); echo 'Not authorized'; exit;
  }
  $new = $_POST['set_signup_enabled'] === '1' ? true : false;
  $payload = json_encode(['enabled' => $new], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  file_put_contents($state_file, $payload, LOCK_EX);
  // refresh to reflect new state
  header('Location: admin_dashboard.php'); exit;
}

// Fetch basic per-user aggregates
$has_created_at = false;
// Detect whether the users table has a created_at column (avoid fatal error when missing)
try {
  $colCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'created_at'");
  if ($colCheck && $colCheck->num_rows > 0) $has_created_at = true;
} catch (Throwable $e) { $has_created_at = false; }

$selectCols = $has_created_at ? 'id, firstname, lastname, email, points, last_activity, created_at' : 'id, firstname, lastname, email, points, last_activity';
$res = $conn->query("SELECT {$selectCols} FROM users ORDER BY points DESC LIMIT 200");
$users = [];
while ($r = $res->fetch_assoc()) { $users[] = $r; }

// Total sessions
$tot_sessions = $conn->query("SELECT COUNT(*) as tot FROM study_sessions")->fetch_assoc()['tot'] ?? 0;

// extra site-wide counts for admin tiles
$tot_users = $conn->query("SELECT COUNT(*) as tot FROM users")->fetch_assoc()['tot'] ?? 0;
$tot_tasks = $conn->query("SELECT COUNT(*) as tot FROM tasks")->fetch_assoc()['tot'] ?? 0;
$tot_messages = $conn->query("SELECT COUNT(*) as tot FROM messages")->fetch_assoc()['tot'] ?? 0;

// recent signups
$recentCols = $has_created_at ? 'id, firstname, lastname, email, created_at' : 'id, firstname, lastname, email';
$recent_res = $conn->query("SELECT {$recentCols} FROM users ORDER BY id DESC LIMIT 8");
$recent_signups = [];
while($r2 = $recent_res->fetch_assoc()) $recent_signups[] = $r2;

// active users in last 24h
$active_24h = $conn->query("SELECT COUNT(DISTINCT id) as tot FROM users WHERE last_activity >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetch_assoc()['tot'] ?? 0;

// simple averages
$avg_points = $conn->query("SELECT AVG(points) as avgp FROM users")->fetch_assoc()['avgp'] ?? 0;

?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Admin Dashboard</title>
  <link rel="stylesheet" href="css/index.css">
  <?php include __DIR__ . '/includes/layout_preamble.php'; ?>
  <link rel="stylesheet" href="css/layout.css">
  <style>body { background:#0b1220;color:#e6eef9;padding:24px;font-family:Inter, sans-serif; }</style>
</head>
<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>
<h1>Admin Dashboard</h1>
<p>Welcome, <?php echo ADMIN_DISPLAY_NAME; ?> — overview and quick analytics about your site.</p>
  <div style="display:flex;gap:18px;align-items:center;margin-top:12px;margin-bottom:14px;flex-wrap:wrap;">
  <div style="background:#0f172a;padding:12px;border-radius:8px;border:1px solid rgba(255,255,255,0.03);min-width:160px;">Total users: <?php echo number_format($tot_users); ?></div>
  <div style="background:#0f172a;padding:12px;border-radius:8px;border:1px solid rgba(255,255,255,0.03);min-width:160px;">Total study sessions: <?php echo number_format($tot_sessions); ?></div>
  <div style="background:#0f172a;padding:12px;border-radius:8px;border:1px solid rgba(255,255,255,0.03);min-width:160px;">Total tasks: <?php echo number_format($tot_tasks); ?></div>
  <div style="background:#0f172a;padding:12px;border-radius:8px;border:1px solid rgba(255,255,255,0.03);min-width:160px;">Total messages: <?php echo number_format($tot_messages); ?></div>
  <div style="background:#0f172a;padding:12px;border-radius:8px;border:1px solid rgba(255,255,255,0.03);min-width:160px;">Active (24h): <?php echo number_format($active_24h); ?></div>
  <div style="background:#0f172a;padding:12px;border-radius:8px;border:1px solid rgba(255,255,255,0.03);min-width:160px;">Avg points per user: <?php echo number_format($avg_points,1); ?></div>
  <div style="flex:1"></div>
  <?php if (defined('ADMIN_MASTER_ID') && (int)$_SESSION['user_id'] === (int)ADMIN_MASTER_ID): ?>
    <div style="display:flex; align-items:center; gap:8px;">
      <form method="POST" style="display:inline-block; margin:0;">
        <input type="hidden" name="set_signup_enabled" value="<?php echo $signup_enabled ? '0' : '1'; ?>" />
        <button type="submit" style="padding:8px 12px; border-radius:8px; border:1px solid rgba(255,255,255,0.06); background:<?php echo $signup_enabled ? '#ef4444' : '#10b981'; ?>; color:white; font-weight:700; cursor:pointer;"><?php echo $signup_enabled ? 'Disable admin signup' : 'Enable admin signup'; ?></button>
      </form>
      <div style="font-size:0.85rem; color:#9aa7c7; margin-left:6px;">Signups are: <strong style="color:#dbeafe"><?php echo $signup_enabled ? 'Enabled' : 'Disabled'; ?></strong></div>
    </div>
  <?php endif; ?>
  <!-- Back to user dashboard removed; sidebar navigation is used instead -->
</div>

  <div style="display:flex;gap:18px;margin-top:20px;flex-wrap:wrap;">
    <div style="flex:1 1 320px; background: linear-gradient(90deg,#071025,#0f172a); padding:14px; border-radius:12px; border:1px solid rgba(255,255,255,0.03);">
      <strong style="display:block;color:#9aa7c7;margin-bottom:8px;">Top users (by points)</strong>
      <canvas id="topUsersChart" height="140"></canvas>
    </div>
    <div style="flex:1 1 320px; background: linear-gradient(90deg,#071025,#0f172a); padding:14px; border-radius:12px; border:1px solid rgba(255,255,255,0.03);">
      <strong style="display:block;color:#9aa7c7;margin-bottom:8px;">Recent signups</strong>
      <ul style="list-style:none;padding:0;margin:0;color:#cfe0ff">
        <?php foreach($recent_signups as $rs): ?>
          <li style="padding:8px 6px;border-bottom:1px solid rgba(255,255,255,0.03); display:flex; justify-content:space-between; align-items:center;">
            <div><?php echo htmlspecialchars($rs['firstname'] . ' ' . $rs['lastname']); ?><br><small style="color:#9aa7c7"><?php echo htmlspecialchars($rs['email']); ?></small></div>
            <div style="font-size:0.85rem;color:#9aa7c7"><?php echo htmlspecialchars($rs['created_at'] ?? '—'); ?></div>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>

  <table style="width:100%;border-collapse:collapse;margin-top:18px;">
<thead style="text-align:left;color:#9fb2ff;"><tr><th>#</th><th>User</th><th>Points</th><th>Last Activity</th><th>Actions</th></tr></thead>
<tbody style="color:#cfe0ff">
<?php foreach ($users as $u): ?>
  <tr style="border-top:1px solid rgba(255,255,255,0.03);vertical-align:top;">
    <td style="padding:8px;"><?php echo $u['id']; ?></td>
    <td style="padding:8px;"><?php echo htmlspecialchars($u['firstname'] . ' ' . $u['lastname']); ?><br><small style="color:#9aa7c7"><?php echo htmlspecialchars($u['email']); ?></small></td>
    <td style="padding:8px; color:#10b981; font-weight:700"><?php echo number_format($u['points']); ?></td>
    <td style="padding:8px; color:#9aa7c7"><?php echo $u['last_activity'] ?? '—'; ?></td>
    <td style="padding:8px;"><a href="admin_view_user.php?id=<?php echo $u['id']; ?>">View</a></td>
  </tr>
<?php endforeach; ?>
</tbody></table>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="js/main.js"></script>
<script src="js/sidebar.js"></script>
<script>
  (function(){
    try {
      const ctx = document.getElementById('topUsersChart');
      if (!ctx) return;
      const users = <?php echo json_encode(array_slice(array_map(function($u){ return ['name'=>trim(($u['firstname'] . ' ' . $u['lastname'])), 'points'=>intval($u['points'])]; }, $users), 0, 10)); ?>;
      const labels = users.map(u => u.name || '#'+Math.floor(Math.random()*1000));
      const data = users.map(u => u.points || 0);
      new Chart(ctx, {
        type: 'bar', data: { labels, datasets: [{ label: 'Points', data, backgroundColor: '#6366f1', borderRadius: 6 }] },
        options: { responsive: true, plugins:{ legend:{ display:false } }, scales:{ x:{ ticks:{ color:'#9aa7c7' } }, y:{ ticks:{ color:'#9aa7c7' } } } }
      });
    } catch (e) { console.warn('chart err', e); }
  })();
</script>
</body></html>
