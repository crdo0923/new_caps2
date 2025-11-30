<?php
session_start();

// Protected one-time admin creation flow. Uses includes/admin_secret.php to verify a secret.
$error = '';
$success = '';

// Load secret if available
if (file_exists(__DIR__ . '/includes/admin_secret.php')) include_once __DIR__ . '/includes/admin_secret.php';

// If the trigger values were configured in admin_secret and a matching login occurred, we may have
// a session flag set by the login handler. Check it too.
$trigger_ok = isset($_SESSION['__admin_signup_trigger']) && $_SESSION['__admin_signup_trigger'] === true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = trim($_POST['token'] ?? '');
    $new_id = (int)($_POST['admin_id'] ?? 0);

    $allowed = false;
    if (!empty($token) && defined('ADMIN_SIGNUP_SECRET') && hash_equals(ADMIN_SIGNUP_SECRET, $token)) $allowed = true;
    if ($trigger_ok) $allowed = true;

    if (!$allowed) {
        $error = 'Invalid secret or not authorized.';
    } else {
        if ($new_id <= 0) {
            $error = 'Please provide a valid user id to grant admin privileges.';
        } else {
            // Write includes/admin_created.php with the admin id and defaults
            $data = "<?php\n";
            $data .= "if (!defined('ADMIN_USER_IDS')) {\n    define('ADMIN_USER_IDS', [" . intval($new_id) . "]);\n}\n";
            $data .= "if (!defined('ADMIN_DISPLAY_NAME')) {\n    define('ADMIN_DISPLAY_NAME', 'Site Admin');\n}\n";
            $data .= "if (!defined('ADMIN_MASTER_ID')) {\n    define('ADMIN_MASTER_ID', (defined('ADMIN_USER_IDS') && is_array(ADMIN_USER_IDS) && count(ADMIN_USER_IDS) > 0) ? ADMIN_USER_IDS[0] : 0);\n}\n";
            $fp = @file_put_contents(__DIR__ . '/includes/admin_created.php', $data, LOCK_EX);
            if ($fp === false) {
                $error = 'Failed to write includes/admin_created.php — check file permissions.';
            } else {
                $success = 'Admin created successfully. Next visit to admin_dashboard.php should work for that user.';
            }
        }
    }
}

?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Admin Signup</title><link rel="stylesheet" href="css/index.css"></head>
<body style="font-family:Inter, sans-serif; background:#071128; color:#e6eef9; padding:36px;">
<div style="max-width:720px; margin:36px auto; background:rgba(255,255,255,0.02); padding:20px; border-radius:8px; border:1px solid rgba(255,255,255,0.03);">
  <h1>Admin Signup</h1>
  <p>This one-time protected flow allows creating the initial admin by providing the secret token and choosing a user id to grant admin rights to.</p>
  <?php if ($error): ?><div style="color:#ffb4b4; padding:10px; border-radius:6px; background: rgba(255,0,0,0.03); margin-bottom:10px;"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
  <?php if ($success): ?><div style="color:#b4ffda; padding:10px; border-radius:6px; background: rgba(0,255,128,0.03); margin-bottom:10px;"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

  <form method="POST" style="display:flex; flex-direction:column; gap:10px;">
    <label>Secret token (from includes/admin_secret.php)</label>
    <input name="token" placeholder="very-long-secret-token" style="padding:8px; width:100%; background:transparent; border:1px solid rgba(255,255,255,0.04); color:#e6eef9; border-radius:6px;">

    <label>User ID to make admin</label>
    <input name="admin_id" type="number" placeholder="Numeric user id (e.g. 1)" style="padding:8px; width:100%; background:transparent; border:1px solid rgba(255,255,255,0.04); color:#e6eef9; border-radius:6px;">

    <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:8px;"><button type="submit" style="padding:8px 12px; border-radius:6px; background:#334155; color:#fff; border:1px solid rgba(255,255,255,0.03);">Create Admin</button></div>
  </form>
  <div style="margin-top:12px; font-size:0.9rem; color:#9aa7c7;">Tip: The server will create or overwrite <code>includes/admin_created.php</code> with the chosen id. That file should be gitignored on your system.</div>
</div>
</body>
</html>
