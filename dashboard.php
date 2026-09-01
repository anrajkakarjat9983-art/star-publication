<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config.php';

$stmt = $conn->prepare('SELECT name, email, phone, address, pincode, project, created_at FROM users WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) { header('Location: logout.php'); exit; }

$initials = strtoupper(mb_substr($user['name'], 0, 1));
if (preg_match('/\s(\S)/u', $user['name'], $m)) $initials .= strtoupper($m[1]);
$welcome = isset($_GET['welcome']);

$statusMap = [
    'pending'           => ['Pending Review',              'st-pending'],
    'confirmed'         => ['Confirmed — Pay ₹1500',       'st-info'],
    'payment_submitted' => ['Fee Verification',            'st-warn'],
    'gst_pending'       => ['Approved — Pay GST',          'st-info'],
    'gst_submitted'     => ['GST Verification',            'st-warn'],
    'completed'         => ['Approved / Completed',        'st-done'],
    'rejected'          => ['Rejected',                    'st-danger'],
];
$lastReq = null;
$qr = $conn->prepare('SELECT id, status FROM requests WHERE user_id = ? ORDER BY id DESC LIMIT 1');
$qr->bind_param('i', $_SESSION['user_id']);
$qr->execute();
$lastReq = $qr->get_result()->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Dashboard | Star Publication</title>
  <meta name="robots" content="noindex">
  <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='14' fill='%230b1f44'/%3E%3Cpath d='M32 10l6.2 12.9L52 25l-10 9.7 2.4 13.8L32 42l-12.4 6.5L22 34.7 12 25l13.8-2.1z' fill='%23c9a227'/%3E%3C/svg%3E">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<nav class="dash-nav">
  <div class="container dash-nav-inner">
    <a class="brand" href="index.html">
      <span class="brand-mark" aria-hidden="true">
        <svg viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#0b1f44"/><path d="M32 10l6.2 12.9L52 25l-10 9.7 2.4 13.8L32 42l-12.4 6.5L22 34.7 12 25l13.8-2.1z" fill="#c9a227"/></svg>
      </span>
      <span class="brand-text"><strong>Star Publication</strong><small>Client Dashboard</small></span>
    </a>
    <div class="dash-user">
      <span class="avatar"><?= htmlspecialchars($initials) ?></span>
      <span><strong><?= htmlspecialchars($user['name']) ?></strong><small><?= htmlspecialchars($user['email']) ?></small></span>
      <a class="btn btn-danger btn-sm" href="logout.php">Logout</a>
    </div>
  </div>
</nav>

<header class="dash-hero">
  <div class="container">
    <h1><?= $welcome ? 'Welcome to Star Publication, ' : 'Welcome back, ' ?><em><?= htmlspecialchars($user['name']) ?></em></h1>
    <p>Your account is active. Our team will use these details to serve your writing orders.</p>
    <?php if ($lastReq): ?>
      <div class="hero-status">
        <span class="status-pill <?= $statusMap[$lastReq['status']][1] ?>">Request #<?= (int)$lastReq['id'] ?> · <?= $statusMap[$lastReq['status']][0] ?></span>
        <a class="btn btn-gold btn-sm" href="order.php">Open Request Tracker</a>
      </div>
    <?php endif; ?>
  </div>
</header>

<main class="dash-section">
  <div class="container">
    <div class="profile-card">
      <div class="profile-head">
        <span class="avatar"><?= htmlspecialchars($initials) ?></span>
        <div>
          <h2>Account Details</h2>
          <p>Stored securely in the Star Publication database</p>
        </div>
      </div>
      <table class="profile-table">
        <tbody>
          <tr><th scope="row">Full Name</th><td><?= htmlspecialchars($user['name']) ?></td></tr>
          <tr><th scope="row">Email ID</th><td><?= htmlspecialchars($user['email']) ?></td></tr>
          <tr><th scope="row">Mobile Number</th><td><?= htmlspecialchars($user['phone']) ?></td></tr>
          <tr><th scope="row">Address</th><td><?= htmlspecialchars($user['address']) ?></td></tr>
          <tr><th scope="row">Pincode</th><td><?= htmlspecialchars($user['pincode']) ?></td></tr>
          <tr><th scope="row">Selected Project</th><td><?= !empty($user['project']) ? htmlspecialchars($user['project']) : '<a href="project_select.php?step=1">Choose your project</a>' ?></td></tr>
          <tr><th scope="row">Member Since</th><td><?= date('d M Y, h:i A', strtotime($user['created_at'])) ?></td></tr>
        </tbody>
      </table>
      <div class="profile-foot">
        <p>Need a writing order? Call +91 98XXX XXXXX or email info@starpublication.in</p>
        <a class="btn btn-primary btn-sm" href="order.php">Place an Enquiry</a>
      </div>
    </div>
  </div>
</main>

<script src="assets/js/main.js" defer></script>
</body>
</html>
