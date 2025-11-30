<?php
// Safe migration helper for adding onboarding_seen column to users table
// Usage:
//  - Dry-run: open in browser: http://localhost/new_caps/php/migrate_add_onboarding.php
//  - Apply changes: visit http://localhost/new_caps/php/migrate_add_onboarding.php?run=1
// This script will check for column existence and attempt to add it. It will not remove column.
// Always back up your DB before running migrations. This is a convenience helper for dev environments.

session_start();
$servername = 'localhost'; $username = 'root'; $password = ''; $database = 'smart_study';
$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    echo '<h2>DB Connection Failed</h2><p>' . htmlspecialchars($conn->connect_error) . '</p>';
    exit;
}

$col_check = $conn->query("SHOW COLUMNS FROM users LIKE 'onboarding_seen'");
$exists = ($col_check && $col_check->num_rows > 0);
$action = isset($_GET['run']) && $_GET['run'] === '1';

?><!doctype html>
<html><head><meta charset="utf-8"><title>Migration: add onboarding_seen</title>
<style>body{font-family:Inter,system-ui,Arial;color:#0b1220;background:#f8fafc;padding:24px} .card{max-width:720px;background:white;border-radius:12px;padding:18px;border:1px solid #e6eef9;box-shadow:0 10px 30px rgba(2,6,23,0.06)}</style></head><body>
<div class="card">
  <h2>Migration helper: add onboarding_seen to users table</h2>
  <p>This checks whether the column <code>onboarding_seen</code> exists and will add it if you run the migration.</p>
  <hr />
  <p><strong>Database:</strong> <?= htmlspecialchars($database) ?></p>
  <p><strong>Table:</strong> users</p>
  <p><strong>Column exists:</strong> <?= $exists ? '<span style="color:green">Yes</span>' : '<span style="color:orange">No</span>' ?></p>
<?php if(!$exists): ?>
  <p style="color:#444">If you want to add the column now, click the Run Migration button below. The migration will run an ALTER TABLE to add the column as <code>TINYINT(1) NOT NULL DEFAULT 0</code>.</p>
  <?php if(!$action): ?>
    <p><a href="?run=1" style="display:inline-block;padding:10px 14px;background:#6366f1;color:#fff;border-radius:8px;text-decoration:none">Run Migration</a></p>
  <?php else: ?>
    <p>Attempting to add column...</p>
    <?php
      $sql = "ALTER TABLE users ADD COLUMN onboarding_seen TINYINT(1) NOT NULL DEFAULT 0";
      if ($conn->query($sql)) {
          echo '<p style="color:green">Column added successfully.</p>';
      } else {
          echo '<p style="color:red">Error adding column: ' . htmlspecialchars($conn->error) . '</p>';
      }
    ?>
  <?php endif; ?>
<?php else: ?>
  <p style="color:green">The column <code>onboarding_seen</code> is already present. No action required.</p>
<?php endif; ?>
  <hr />
  <h4>Rollback note</h4>
  <p>If you later want to remove the column, for example in dev or test environments, run:</p>
  <pre style="background:#f3f4f6;padding:8px;border-radius:6px;">ALTER TABLE users DROP COLUMN onboarding_seen;</pre>
  <p><strong>Warning:</strong> Do NOT drop the column during normal operation if users rely on it. Always backup data before removing columns.</p>
</div>
</body></html>
<?php $conn->close();
?>