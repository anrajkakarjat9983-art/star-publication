<?php
session_start();
require_once __DIR__ . '/config.php';

$errors = [];
$success = '';
$tab = ($_GET['tab'] ?? 'login') === 'register' ? 'register' : 'login';
if (isset($_GET['tab']) && $_GET['tab'] === 'register') $tab = 'register';

$old = ['name' => '', 'email' => '', 'phone' => '', 'address' => '', 'pincode' => ''];

/* Already logged in? go to dashboard */
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* ---------------- REGISTER ---------------- */
    if ($action === 'register') {
        $tab = 'register';
        foreach ($old as $k => $v) {
            $old[$k] = trim((string)($_POST[$k] ?? ''));
        }
        $name     = $old['name'];
        $email    = strtolower($old['email']);
        $phone    = preg_replace('/\D/', '', $old['phone']);
        $address  = $old['address'];
        $pincode  = preg_replace('/\D/', '', $old['pincode']);
        $password = (string)($_POST['password'] ?? '');
        $confirm  = (string)($_POST['confirm'] ?? '');

        if (mb_strlen($name) < 3)                                   $errors[] = 'Please enter your full name (min. 3 characters).';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))             $errors[] = 'Please enter a valid email address.';
        if (!preg_match('/^[6-9]\d{9}$/', $phone))                  $errors[] = 'Please enter a valid 10-digit mobile number.';
        if (mb_strlen($address) < 6)                                $errors[] = 'Please enter your complete address.';
        if (!preg_match('/^\d{6}$/', $pincode))                     $errors[] = 'PIN code must be exactly 6 digits.';
        if (strlen($password) < 6)                                  $errors[] = 'Password must be at least 6 characters.';
        if ($password !== $confirm)                                 $errors[] = 'Passwords do not match.';

        if (!$errors) {
            $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $errors[] = 'This email is already registered. Please login instead.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $ins = $conn->prepare('INSERT INTO users (name, email, phone, address, pincode, password_hash) VALUES (?, ?, ?, ?, ?, ?)');
                $ins->bind_param('ssssss', $name, $email, $phone, $address, $pincode, $hash);
                if ($ins->execute()) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $conn->insert_id;
                    $_SESSION['user_name'] = $name;
                    header('Location: dashboard.php?welcome=1');
                    exit;
                }
                $errors[] = 'Could not save your account. Please try again.';
            }
            $stmt->close();
        }
    }

    /* ---------------- LOGIN ---------------- */
    else {
        $tab = 'login';
        $email    = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        $old['email'] = $email;

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
        if ($password === '')                           $errors[] = 'Please enter your password.';

        if (!$errors) {
            $stmt = $conn->prepare('SELECT id, name, password_hash FROM users WHERE email = ? LIMIT 1');
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($user && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['user_name'] = $user['name'];
                header('Location: dashboard.php');
                exit;
            }
            $errors[] = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $tab === 'register' ? 'Create Account' : 'Client Login' ?> | Star Publication</title>
  <meta name="description" content="Login or create your Star Publication client account to request handwriting and documentation services.">
  <meta name="robots" content="noindex">
  <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='14' fill='%230b1f44'/%3E%3Cpath d='M32 10l6.2 12.9L52 25l-10 9.7 2.4 13.8L32 42l-12.4 6.5L22 34.7 12 25l13.8-2.1z' fill='%23c9a227'/%3E%3C/svg%3E">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="auth-page">

  <!-- Left brand panel -->
  <aside class="auth-side">
    <a class="brand brand-light" href="index.html">
      <span class="brand-mark" aria-hidden="true">
        <svg viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#12295c"/><path d="M32 10l6.2 12.9L52 25l-10 9.7 2.4 13.8L32 42l-12.4 6.5L22 34.7 12 25l13.8-2.1z" fill="#c9a227"/></svg>
      </span>
      <span class="brand-text"><strong>Star Publication</strong><small>Handwriting &amp; Documentation Services</small></span>
    </a>

    <div class="auth-tagline">
      <h2>Your documents, written with <em>precision</em>.</h2>
      <p>Create your client account to place orders, track requirements and manage your writing projects with Star Publication.</p>
      <ul class="auth-perks">
        <li><svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg> Legitimate &amp; legally compliant processes</li>
        <li><svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg> Confidential handling of your details</li>
        <li><svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg> Transparent pricing, on-time delivery</li>
      </ul>
    </div>

    <p class="auth-foot">© <?= date('Y') ?> Star Publication · We claim no government approval or affiliation.</p>
  </aside>

  <!-- Right form panel -->
  <main class="auth-main">
    <div class="auth-box">
      <div class="auth-head">
        <span class="brand-mark" aria-hidden="true">
          <svg viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#0b1f44"/><path d="M32 10l6.2 12.9L52 25l-10 9.7 2.4 13.8L32 42l-12.4 6.5L22 34.7 12 25l13.8-2.1z" fill="#c9a227"/></svg>
        </span>
        <h1><?= $tab === 'register' ? 'Create Your Account' : 'Welcome Back' ?></h1>
        <p><?= $tab === 'register'
              ? 'Register below — your details are stored securely in our database.'
              : 'Login to access your Star Publication client dashboard.' ?></p>
      </div>

      <div class="auth-tabs" role="tablist" aria-label="Authentication options">
        <a href="login.php?tab=login" class="<?= $tab === 'login' ? 'active' : '' ?>" role="tab" aria-selected="<?= $tab === 'login' ? 'true' : 'false' ?>">Login</a>
        <a href="login.php?tab=register" class="<?= $tab === 'register' ? 'active' : '' ?>" role="tab" aria-selected="<?= $tab === 'register' ? 'true' : 'false' ?>">Register</a>
      </div>

      <?php if ($errors): ?>
        <div class="alert alert-error" data-autoclose role="alert">
          <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="alert alert-success" data-autoclose role="status"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <?php if ($tab === 'register'): ?>
      <!-- ================= REGISTER ================= -->
      <form class="auth-form" method="post" action="login.php?tab=register" novalidate autocomplete="on">
        <input type="hidden" name="action" value="register">

        <div class="field">
          <label for="rName">Full Name *</label>
          <input type="text" id="rName" name="name" placeholder="e.g. Aarav Sharma" required minlength="3" maxlength="100"
                 value="<?= htmlspecialchars($old['name']) ?>">
        </div>

        <div class="field">
          <label for="rEmail">Email ID *</label>
          <input type="email" id="rEmail" name="email" placeholder="you@example.com" required maxlength="150"
                 value="<?= htmlspecialchars($old['email']) ?>">
        </div>

        <div class="field">
          <label for="rPhone">Mobile Number *</label>
          <input type="tel" id="rPhone" name="phone" placeholder="10-digit mobile number" required
                 pattern="[6-9][0-9]{9}" maxlength="10" inputmode="numeric"
                 value="<?= htmlspecialchars($old['phone']) ?>">
        </div>

        <div class="field">
          <label for="rAddress">Address *</label>
          <textarea id="rAddress" name="address" rows="2" placeholder="House / Street, Area, City, State" required maxlength="255"><?= htmlspecialchars($old['address']) ?></textarea>
        </div>

        <div class="field">
          <label for="rPin">Pincode *</label>
          <input type="text" id="rPin" name="pincode" placeholder="6-digit PIN code" required
                 pattern="[0-9]{6}" maxlength="6" inputmode="numeric"
                 value="<?= htmlspecialchars($old['pincode']) ?>">
        </div>

        <div class="field">
          <label for="rPass">Password *</label>
          <input type="password" id="rPass" name="password" placeholder="Minimum 6 characters" required minlength="6">
          <span class="input-hint">Use at least 6 characters.</span>
        </div>

        <div class="field">
          <label for="rConfirm">Confirm Password *</label>
          <input type="password" id="rConfirm" name="confirm" placeholder="Re-enter password" required minlength="6">
        </div>

        <button class="btn btn-primary btn-block" type="submit">Create Account</button>
        <p class="auth-alt">Already registered? <a href="login.php?tab=login">Login here</a></p>
      </form>
      <?php else: ?>
      <!-- ================= LOGIN ================= -->
      <form class="auth-form" method="post" action="login.php" novalidate>
        <input type="hidden" name="action" value="login">

        <div class="field">
          <label for="lEmail">Email ID *</label>
          <input type="email" id="lEmail" name="email" placeholder="you@example.com" required maxlength="150"
                 value="<?= htmlspecialchars($old['email']) ?>">
        </div>

        <div class="field">
          <label for="lPass">Password *</label>
          <input type="password" id="lPass" name="password" placeholder="Your password" required>
        </div>

        <button class="btn btn-primary btn-block" type="submit">Login</button>
        <p class="auth-alt">New to Star Publication? <a href="login.php?tab=register">Create an account</a></p>
      </form>
      <?php endif; ?>

      <a class="back-home" href="index.html">
        <svg viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg> Back to Website
      </a>
    </div>
  </main>
</div>
<script src="assets/js/main.js" defer></script>
</body>
</html>
