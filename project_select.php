<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/config.php';

$uid = (int)$_SESSION['user_id'];
$name = htmlspecialchars((string)$_SESSION['user_name']);
$projects = [
    '1' => ['label' => 'Project 1', 'pages' => '50 Pages · Front & Back · 100', 'salary' => '25000', 'days' => '7', 'fee' => '499'],
    '2' => ['label' => 'Project 2', 'pages' => '90 Pages · Front & Back · 180', 'salary' => '30000', 'days' => '10', 'fee' => '599'],
    '3' => ['label' => 'Project 3', 'pages' => '120 Pages · Front & Back · 240', 'salary' => '35000', 'days' => '15', 'fee' => '699'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Select Your Project | Star Publication</title>
  <meta name="robots" content="noindex">
  <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='14' fill='%230b1f44'/%3E%3Cpath d='M32 10l6.2 12.9L52 25l-10 9.7 2.4 13.8L32 42l-12.4 6.5L22 34.7 12 25l13.8-2.1z' fill='%23c9a227'/%3E%3C/svg%3E">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <style>
    .proj-grid { display: grid; gap: 20px; margin-top: 10px; }
    @media (min-width: 900px) { .proj-grid { grid-template-columns: repeat(3, 1fr); } }
    .proj-card { background: var(--card); border: 1.5px solid var(--line); border-radius: var(--radius); padding: 26px 22px; text-align: center; box-shadow: var(--shadow-sm); transition: transform .25s ease, box-shadow .25s ease, border-color .25s ease; }
    .proj-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-md); border-color: var(--gold); }
    .proj-card h3 { font-family: var(--font-display); color: var(--navy-800); margin-bottom: 6px; }
    .proj-pages { color: var(--muted); font-size: .85rem; margin-bottom: 14px; }
    .proj-table { width: 100%; font-size: .87rem; border-top: 1px dashed var(--line); }
    .proj-table tr { display: flex; justify-content: space-between; gap: 10px; padding: 8px 4px; border-bottom: 1px dashed var(--line); }
    .proj-table td:first-child { color: var(--muted); font-weight: 500; }
    .proj-table td:last-child { font-weight: 600; color: var(--navy-800); }
    .proj-fee { margin-top: 14px; font-weight: 700; color: var(--navy-800); }
  </style>
</head>
<body>

<nav class="dash-nav">
  <div class="container dash-nav-inner">
    <a class="brand" href="index.html">
      <span class="brand-mark" aria-hidden="true">
        <svg viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#0b1f44"/><path d="M32 10l6.2 12.9L52 25l-10 9.7 2.4 13.8L32 42l-12.4 6.5L22 34.7 12 25l13.8-2.1z" fill="#c9a227"/></svg>
      </span>
      <span class="brand-text"><strong>Star Publication</strong><small>Project Selection</small></span>
    </a>
    <div class="dash-user">
      <span class="avatar"><?= strtoupper(mb_substr($name, 0, 1)) ?></span>
      <span><strong><?= $name ?></strong></span>
      <a class="btn btn-danger btn-sm" href="logout.php">Logout</a>
    </div>
  </div>
</nav>

<header class="dash-hero">
  <div class="container">
    <h1>Select Your <em>Project</em></h1>
    <p>Choose the project you want to join. Your choice is confirmed only after the registration payment is submitted.</p>
  </div>
</header>

<main class="dash-section">
  <div class="container">
    <div class="proj-grid">
      <?php foreach ($projects as $key => $p): ?>
        <div class="proj-card">
          <h3><?= htmlspecialchars($p['label']) ?></h3>
          <p class="proj-pages"><?= htmlspecialchars($p['pages']) ?></p>
          <table class="proj-table">
            <tr><td>Salary Amount</td><td>&#8377;<?= number_format((float)$p['salary']) ?></td></tr>
            <tr><td>Validity</td><td><?= $p['days'] ?> days</td></tr>
          </table>
          <div class="proj-fee">Registration charge: &#8377;<?= $p['fee'] ?></div>
          <button class="btn btn-gold btn-sm" type="button" style="margin-top:16px;"
                  data-open-pay data-amount="<?= $p['fee'] ?>" data-project="<?= $key ?>">Select <?= htmlspecialchars($p['label']) ?></button>
        </div>
      <?php endforeach; ?>
    </div>

    <div style="text-align:center;margin-top:26px;">
      <a class="btn btn-outline btn-sm" href="logout.php">Log Out</a>
    </div>
  </div>
</main>

<!-- ===================== PROJECT PAYMENT MODAL ===================== -->
<div class="modal" id="projPayModal" aria-hidden="true" role="dialog" aria-modal="true">
  <div class="modal-overlay" data-p-close></div>
  <div class="modal-box">
    <button class="modal-x" type="button" data-p-close aria-label="Close">
      <svg viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></svg>
    </button>
    <h3>Project Payment — ₹<span id="pAmt">499</span></h3>
    <p class="muted">Pay ₹<span id="pAmt2">499</span> via UPI (GPay / PhonePe / Paytm), then fill the details below. Your project is confirmed after payment.</p>

    <div class="modal-qr-wrap">
      <img id="pQr" src="https://api.qrserver.com/v1/create-qr-code/?size=190x190&amp;data=upi%3A%2F%2Fpay%3Fpa%3Dstarpublication%40upi%26pn%3DStar%20Publication%26am%3D499%26cu%3DINR%26tn%3DProject%20Fee" alt="Project QR code" width="190" height="190">
      <code class="upi-id">starpublication@upi</code>
    </div>

    <form class="auth-form" method="post" action="register_pay.php" enctype="multipart/form-data" novalidate>
      <input type="hidden" name="amount" id="pAmtVal" value="499">
      <input type="hidden" name="project" id="pProjVal" value="">
      <div class="field">
        <label for="pName">Full Name *</label>
        <input type="text" id="pName" name="name" placeholder="Your full name" required maxlength="100">
      </div>
      <div class="field">
        <label for="pPhone">Mobile Number *</label>
        <input type="tel" id="pPhone" name="phone" placeholder="10-digit mobile number" required pattern="[6-9][0-9]{9}" maxlength="10" inputmode="numeric">
      </div>
      <div class="field">
        <label for="pUtr">UTR / Transaction Ref No. *</label>
        <input type="text" id="pUtr" name="utr" placeholder="e.g. 405512345678" required maxlength="50">
      </div>
      <div class="field">
        <label for="pShot">Payment Screenshot / Receipt *</label>
        <input type="file" id="pShot" name="screenshot" accept=".jpg,.jpeg,.png,.webp,.pdf" required>
        <span class="input-hint">JPG, PNG, WEBP or PDF · max 5 MB</span>
      </div>
      <button class="btn btn-primary btn-block" type="submit">Pay &amp; Confirm Project</button>
    </form>
  </div>
</div>

<script src="assets/js/main.js" defer></script>
<script>
(function () {
  var modal = document.getElementById('projPayModal');
  if (!modal) return;
  function open() { modal.classList.add('open'); modal.setAttribute('aria-hidden', 'false'); document.body.style.overflow = 'hidden'; }
  function close() { modal.classList.remove('open'); modal.setAttribute('aria-hidden', 'true'); document.body.style.overflow = ''; }
  document.querySelectorAll('[data-open-pay]').forEach(function (b) {
    b.addEventListener('click', function () {
      var amt = b.getAttribute('data-amount') || '499';
      var proj = b.getAttribute('data-project') || '';
      document.getElementById('pAmt').textContent = amt;
      document.getElementById('pAmt2').textContent = amt;
      document.getElementById('pAmtVal').value = amt;
      document.getElementById('pProjVal').value = proj;
      document.getElementById('pQr').src = 'https://api.qrserver.com/v1/create-qr-code/?size=190x190&data=upi%3A%2F%2Fpay%3Fpa%3Dstarpublication%40upi%26pn%3DStar%20Publication%26am%3D' + amt + '%26cu%3DINR%26tn%3DProject%20Fee';
      open();
    });
  });
  modal.querySelectorAll('[data-p-close]').forEach(function (b) { b.addEventListener('click', close); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && modal.classList.contains('open')) close(); });
})();
</script>
</body>
</html>
