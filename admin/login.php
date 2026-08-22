<?php
session_start();
if (!empty($_SESSION['admin_id'])) {
    header('Location: index.php');
    exit;
}
require_once __DIR__ . '/../config.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $stmt = $conn->prepare('SELECT id, name, password_hash FROM admins WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id']   = (int)$admin['id'];
            $_SESSION['admin_name'] = $admin['name'];
            header('Location: index.php');
            exit;
        }
        $error = 'Invalid admin credentials.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login | Star Publication</title>
  <meta name="robots" content="noindex">
  <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='14' fill='%230b1f44'/%3E%3Cpath d='M32 10l6.2 12.9L52 25l-10 9.7 2.4 13.8L32 42l-12.4 6.5L22 34.7 12 25l13.8-2.1z' fill='%23c9a227'/%3E%3C/svg%3E">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="auth-page">

  <aside class="auth-side">
    <a class="brand brand-light" href="../index.html">
      <span class="brand-mark" aria-hidden="true">
        <svg viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#12295c"/><path d="M32 10l6.2 12.9L52 25l-10 9.7 2.4 13.8L32 42l-12.4 6.5L22 34.7 12 25l13.8-2.1z" fill="#c9a227"/></svg>
      </span>
      <span class="brand-text"><strong>Star Publication</strong><small>Admin Control Panel</small></span>
    </a>
    <div class="auth-tagline">
      <h2>Manage requests &amp; <em>approvals</em>.</h2>
      <p>Confirm client enquiries, verify UPI payments and track every order from one place.</p>
      <ul class="auth-perks">
        <li><svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg> Live request queue with 30-min SLA</li>
        <li><svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg> Payment verification with receipt proof</li>
        <li><svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg> One-click confirm &amp; approve actions</li>
      </ul>
    </div>
    <p class="auth-foot">© <?= date('Y') ?> Star Publication · Restricted area</p>
  </aside>

  <main class="auth-main">
    <div class="auth-box">
      <div class="auth-head">
        <span class="brand-mark" aria-hidden="true">
          <svg viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#0b1f44"/><path d="M32 10l6.2 12.9L52 25l-10 9.7 2.4 13.8L32 42l-12.4 6.5L22 34.7 12 25l13.8-2.1z" fill="#c9a227"/></svg>
        </span>
        <h1>Admin Login</h1>
        <p>Authorised staff only — all actions are logged.</p>
      </div>

      <?php if ($error): ?>
        <div class="alert alert-error" data-autoclose role="alert"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="post" action="login.php" novalidate>
        <div class="field">
          <label for="aEmail">Admin Email *</label>
          <input type="email" id="aEmail" name="email" placeholder="admin@starpublication.in" required maxlength="150">
        </div>
        <div class="field">
          <label for="aPass">Password *</label>
          <input type="password" id="aPass" name="password" placeholder="Your password" required>
        </div>
        <button class="btn btn-primary btn-block" type="submit">Login to Panel</button>
      </form>

      <a class="back-home" href="../index.html">
        <svg viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg> Back to Website
      </a>
    </div>
  </main>
</div>
<script src="../assets/js/main.js" defer></script>
</body>
</html>
