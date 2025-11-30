<?php
session_start();
include __DIR__ . '/includes/ai_faq.php';

// Use a simple page (login required)
if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php'); exit;
}

?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>FAQ - SmartStudy</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
  <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
  <link rel="stylesheet" href="css/index.css">
  <?php include __DIR__ . '/includes/layout_preamble.php'; ?>
  <link rel="stylesheet" href="css/layout.css">
  <style>
    body { font-family: Inter, sans-serif; background:#071029; color:#e6eef9 }
    .faq-wrap { max-width:1000px; margin:48px auto; padding:18px; }
    h1 { font-weight:800; margin-bottom:6px }
    .faq-list { display:flex; flex-direction:column; gap:10px; margin-top:18px }
    .faq-item { background: linear-gradient(90deg,#0f172a,#071025); border:1px solid rgba(255,255,255,0.03); padding:12px 14px; border-radius:10px; cursor:pointer; transition: transform .12s, background .12s }
    .faq-item:hover { transform: translateY(-2px); }
    .faq-q { display:flex; justify-content:space-between; align-items:center; gap:12px }
    .faq-q h3 { margin:0; font-size:1rem; color:#e6eef9 }
    .faq-p { color:#9aa7c7; font-size:0.95rem; margin-top:8px; display:none }
    .faq-item.expanded .faq-p { display:block }
    .faq-item .chev { color:#9aa7c7 }
  </style>
</head>
<body>
  <?php include 'includes/mobile_blocker.php'; ?>
  <?php include 'includes/sidebar.php'; ?>
  <main class="main-content faq-wrap">
    <div style="display:flex;align-items:center;justify-content:space-between;gap:18px;">
      <div>
        <h1>Frequently Asked Questions</h1>
        <p style="color:#9aa7c7;margin-top:6px;">Helpful answers for common SmartStudy workflows.</p>
      </div>
    </div>

    <div class="faq-list" id="faqList">
      <?php foreach ($AI_FAQ as $f): ?>
        <div class="faq-item" data-id="<?php echo htmlspecialchars($f['id']); ?>">
          <div class="faq-q">
            <h3><?php echo htmlspecialchars($f['question']); ?></h3>
            <div class="chev">▾</div>
          </div>
          <div class="faq-p"><?php echo nl2br(htmlspecialchars($f['answer'])); ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </main>

  <script src="js/main.js"></script>
  <script src="js/sidebar.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function(){
      document.querySelectorAll('.faq-item').forEach(item => {
        item.addEventListener('click', () => {
          const expanded = item.classList.contains('expanded');
          // collapse others
          document.querySelectorAll('.faq-item.expanded').forEach(e => { if (e !== item) e.classList.remove('expanded'); });
          if (expanded) item.classList.remove('expanded'); else item.classList.add('expanded');
          // send analytics click for admin stats
          const id = item.getAttribute('data-id');
          if (id) fetch('ajax_gemini.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action: 'faq_click', id: id, context: 'faq' }) }).catch(()=>{});
        });
      });
    });
  </script>
  <?php include 'includes/call_overlay.php'; ?>
</body>
</html>
