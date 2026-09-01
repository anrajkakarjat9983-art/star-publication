<?php
session_start();
if (empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/../config.php';

$msg = '';
$msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirmText = trim((string)($_POST['confirm'] ?? ''));
    if (mb_strtolower($confirmText) !== 'update') {
        $msg     = 'Type "update" in the confirm box to save changes.';
        $msgType = 'error';
    } else {
        $upiId   = trim((string)($_POST['upi_id'] ?? ''));
        $upiName = trim((string)($_POST['upi_name'] ?? ''));

        $errors = [];
        if ($upiId === '' || !str_contains($upiId, '@')) {
            $errors[] = 'Enter a valid UPI ID (must contain @).';
        }
        if ($upiName === '') {
            $errors[] = 'Enter the UPI display name.';
        }

        // Optional QR image upload
        $qrPath = null;
        if (!empty($_FILES['qr_image']['name']) && $_FILES['qr_image']['error'] === UPLOAD_ERR_OK) {
            $tmp  = $_FILES['qr_image']['tmp_name'];
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
            if (str_starts_with($mime, 'image/')) {
                $dir = __DIR__ . '/../uploads/qr/';
                if (!is_dir($dir)) mkdir($dir, 0777, true);
                $ext  = strtolower(pathinfo($_FILES['qr_image']['name'], PATHINFO_EXTENSION)) ?: 'png';
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) $ext = 'png';
                $fname = 'qr_' . time() . '.' . $ext;
                if (move_uploaded_file($tmp, $dir . $fname)) {
                    $qrPath = 'uploads/qr/' . $fname;
                } else {
                    $errors[] = 'Could not save the QR image.';
                }
            } else {
                $errors[] = 'QR file must be an image (JPG, PNG, WEBP, GIF).';
            }
        }

        if ($errors) {
            $msg     = implode(' ', $errors);
            $msgType = 'error';
        } else {
            $upsert = $conn->prepare('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)');
            $sets = ['upi_id' => $upiId, 'upi_name' => $upiName];
            if ($qrPath !== null) $sets['qr_path'] = $qrPath;
            $conn->query('START TRANSACTION');
            foreach ($sets as $k => $v) {
                $upsert->bind_param('ss', $k, $v);
                $upsert->execute();
            }
            $conn->query('COMMIT');
            $upsert->close();

            // Reload
            $GLOBALS['SETTINGS'] = settings_defaults();
            if ($res = $conn->query('SELECT k, v FROM settings')) {
                while ($row = $res->fetch_assoc()) $GLOBALS['SETTINGS'][$row['k']] = $row['v'];
            }
            $SETTINGS = $GLOBALS['SETTINGS'];

            $msg     = 'Settings saved successfully.';
            $msgType = 'ok';
        }
    }
}

$upiId   = (string)($GLOBALS['SETTINGS']['upi_id'] ?? 'starpublication@upi');
$upiName = (string)($GLOBALS['SETTINGS']['upi_name'] ?? 'Star Publication');
$qrPath  = (string)($GLOBALS['SETTINGS']['qr_path'] ?? '');

$adminName = htmlspecialchars((string)$_SESSION['admin_name']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QR / UPI Settings | Star Publication Admin</title>
  <meta name="robots" content="noindex">
  <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='14' fill='%230b1f44'/%3E%3Cpath d='M32 10l6.2 12.9L52 25l-10 9.7 2.4 13.8L32 42l-12.4 6.5L22 34.7 12 25l13.8-2.1z' fill='%23c9a227'/%3E%3C/svg%3E">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    .set-wrap{max-width:760px;margin:0 auto}
    .set-card{background:#fff;border:1px solid #e3e9f4;border-radius:16px;padding:26px;margin-top:24px}
    .set-card h2{margin-top:0}
    .set-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px}
    @media(max-width:640px){.set-grid{grid-template-columns:1fr}}
    .set-field{display:flex;flex-direction:column;gap:6px}
    .set-field label{font-weight:600;font-size:13px;color:#182238}
    .set-field input[type=text]{padding:10px 12px;border:1px solid #d7dfec;border-radius:10px;font-size:15px}
    .qr-preview{display:flex;align-items:center;gap:16px;margin-top:14px}
    .qr-preview img{border:1px solid #e3e9f4;border-radius:12px;background:#fff;padding:6px}
    .msg-ok{background:#e7f7ee;color:#0a6b36;padding:12px 16px;border-radius:10px;margin-bottom:6px}
    .msg-error{background:#fdeaea;color:#a11;padding:12px 16px;border-radius:10px;margin-bottom:6px}
    .set-actions{margin-top:22px;display:flex;gap:10px;align-items:center}
    .back-link{display:inline-block;margin-top:18px;color:#0b1f44;font-weight:600;text-decoration:none}
  </style>
</head>
<body class="admin-body">

<nav class="dash-nav">
  <div class="container dash-nav-inner">
    <a class="brand" href="index.php">
      <span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#0b1f44"/><path d="M32 10l6.2 12.9L52 25l-10 9.7 2.4 13.8L32 42l-12.4 6.5L22 34.7 12 25l13.8-2.1z" fill="#c9a227"/></svg></span>
      <span class="brand-text"><strong>Star Publication</strong><small>Admin Panel</small></span>
    </a>
    <div class="dash-user">
      <span class="avatar admin-avatar"><?= strtoupper(mb_substr($adminName, 0, 1)) ?></span>
      <span><strong><?= $adminName ?></strong><small>Administrator</small></span>
      <a class="btn btn-ok btn-sm" href="index.php" style="margin-left:10px">← Dashboard</a>
      <a class="btn btn-danger btn-sm" href="logout.php">Logout</a>
    </div>
  </div>
</nav>

<header class="dash-hero">
  <div class="container">
    <h1>QR &amp; <em>UPI Settings</em></h1>
    <p>Update the UPI ID, display name, and QR code shown on payment pages.</p>
  </div>
</header>

<main class="dash-section">
  <div class="container set-wrap">
    <div class="set-card">
      <?php if ($msg !== ''): ?>
        <div class="<?= $msgType === 'ok' ? 'msg-ok' : 'msg-error' ?>"><?= htmlspecialchars($msg) ?></div>
      <?php endif; ?>

      <h2>Payment details shown to clients</h2>
      <p class="muted">These appear on the registration / project payment modals and the processing &amp; GST payment pages.</p>

      <form method="post" enctype="multipart/form-data" style="margin-top:18px">
        <div class="set-grid">
          <div class="set-field">
            <label for="upi_id">UPI ID</label>
            <input type="text" id="upi_id" name="upi_id" value="<?= htmlspecialchars($upiId) ?>" placeholder="yourname@upi" required>
          </div>
          <div class="set-field">
            <label for="upi_name">UPI Display Name</label>
            <input type="text" id="upi_name" name="upi_name" value="<?= htmlspecialchars($upiName) ?>" placeholder="Star Publication" required>
          </div>
        </div>

        <div class="set-field" style="margin-top:18px">
          <label for="qr_image">Upload Custom QR Code (optional)</label>
          <input type="file" id="qr_image" name="qr_image" accept="image/png,image/jpeg,image/webp,image/gif">
          <small class="muted">Leave empty to keep the current QR. If none uploaded, a QR is auto-generated from the UPI ID.</small>
        </div>

        <div class="qr-preview">
          <?php if ($qrPath !== '' && file_exists(__DIR__ . '/../' . $qrPath)): ?>
            <img src="../<?= htmlspecialchars($qrPath) ?>" alt="Current QR" width="130" height="130">
          <?php else: ?>
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=130x130&amp;data=<?= rawurlencode('upi://pay?pa=' . $upiId . '&pn=Star%20Publication') ?>" alt="Auto QR" width="130" height="130">
          <?php endif; ?>
          <div>
            <strong>Current UPI ID:</strong> <code class="upi-id"><?= htmlspecialchars($upiId) ?></code><br>
            <strong>Name:</strong> <?= htmlspecialchars($upiName) ?>
          </div>
        </div>

        <div class="set-actions">
          <button class="btn btn-ok" type="submit">Save Settings</button>
          <small class="muted">Confirm below by typing <strong>update</strong></small>
        </div>
        <div class="set-field" style="margin-top:14px;max-width:260px">
          <label for="confirm">Type <strong>update</strong> to confirm</label>
          <input type="text" id="confirm" name="confirm" placeholder="update" autocomplete="off">
        </div>
      </form>
    </div>

    <a class="back-link" href="index.php">← Back to Dashboard</a>
  </div>
</main>

</body>
</html>
