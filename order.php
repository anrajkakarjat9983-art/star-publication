<?php
session_start();
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/config.php';

$uid = (int)$_SESSION['user_id'];
$error = '';

/* ---------- POST handlers ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_request') {
        $chk = $conn->prepare("SELECT id FROM requests WHERE user_id = ? AND status IN ('pending','confirmed','payment_submitted','gst_pending','gst_submitted') LIMIT 1");
        $chk->bind_param('i', $uid);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            header('Location: order.php');
            exit;
        }
        $ins = $conn->prepare('INSERT INTO requests (user_id, status) VALUES (?, "pending")');
        $ins->bind_param('i', $uid);
        $ins->execute();
        header('Location: order.php');
        exit;
    }

    if ($action === 'submit_payment' || $action === 'submit_gst') {
        $cur = latest_request($conn, $uid);
        $map = [
            'confirmed'   => ['processing', (float)PROCESSING_FEE, 'payment_submitted', 'submit_payment'],
            'gst_pending' => ['gst',        GST_AMOUNT,            'gst_submitted',     'submit_gst'],
        ];
        if (!$cur || !isset($map[$cur['status']]) || $map[$cur['status']][3] !== $action) { header('Location: order.php'); exit; }
        [$payType, $payAmt, $nextStatus] = $map[$cur['status']];

        $payer = trim((string)($_POST['payer_name'] ?? ''));
        $utr   = strtoupper(preg_replace('/\s+/', '', (string)($_POST['utr'] ?? '')));

        if (mb_strlen($payer) < 3) $error = 'Please enter the account holder / payer name.';
        elseif (!preg_match('/^[A-Z0-9]{6,50}$/', $utr)) $error = 'UTR / Reference number must be 6-50 letters or digits.';
        else {
            if (!isset($_FILES['screenshot']) || $_FILES['screenshot']['error'] !== UPLOAD_ERR_OK) {
                $error = 'Please upload your payment screenshot or receipt.';
            } else {
                $tmp  = $_FILES['screenshot']['tmp_name'];
                $size = (int)$_FILES['screenshot']['size'];
                $ext  = strtolower(pathinfo($_FILES['screenshot']['name'], PATHINFO_EXTENSION));
                $mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file($tmp);
                $okExt = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

                $valid = in_array($ext, $okExt, true) &&
                         ($mime === 'application/pdf' || str_starts_with($mime, 'image/'));

                if ($size > 5 * 1024 * 1024)      $error = 'File is too large. Maximum size is 5 MB.';
                elseif (!$valid)                  $error = 'Invalid file type. Upload JPG, PNG, WEBP or PDF only.';
                else {
                    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0777, true);
                    $fname = 'sp_' . bin2hex(random_bytes(8)) . '.' . $ext;
                    if (!move_uploaded_file($tmp, UPLOAD_DIR . $fname)) {
                        $error = 'Could not save the uploaded file. Please try again.';
                    } else {
                        $ins = $conn->prepare('INSERT INTO payments (request_id, type, amount, utr, payer_name, screenshot) VALUES (?, ?, ?, ?, ?, ?)');
                        $ins->bind_param('isdsss', $cur['id'], $payType, $payAmt, $utr, $payer, $fname);
                        $ins->execute();
                        $upd = $conn->prepare('UPDATE requests SET status = ?, paid_at = NOW() WHERE id = ?');
                        $upd->bind_param('si', $nextStatus, $cur['id']);
                        $upd->execute();
                        header('Location: order.php?submitted=1');
                        exit;
                    }
                }
            }
        }
    }
}

function latest_request(mysqli $conn, int $uid): ?array {
    $q = $conn->prepare('SELECT * FROM requests WHERE user_id = ? ORDER BY id DESC LIMIT 1');
    $q->bind_param('i', $uid);
    $q->execute();
    $r = $q->get_result()->fetch_assoc();
    return $r ?: null;
}

$req   = latest_request($conn, $uid);
$status = $req['status'] ?? 'none';

$payP = null; $payG = null;
if ($req) {
    $p = $conn->prepare("SELECT * FROM payments WHERE request_id = ? AND type = 'processing' ORDER BY id DESC LIMIT 1");
    $p->bind_param('i', $req['id']);
    $p->execute();
    $payP = $p->get_result()->fetch_assoc() ?: null;

    $g = $conn->prepare("SELECT * FROM payments WHERE request_id = ? AND type = 'gst' ORDER BY id DESC LIMIT 1");
    $g->bind_param('i', $req['id']);
    $g->execute();
    $payG = $g->get_result()->fetch_assoc() ?: null;
}
$pay = $status === 'payment_submitted' ? $payP : ($status === 'gst_submitted' ? $payG : null);

$name     = htmlspecialchars((string)$_SESSION['user_name']);
$fee      = number_format(PROCESSING_FEE);
$gstFee   = number_format(GST_AMOUNT, 2);
$deadline = 0;
if ($status === 'pending')           $deadline = strtotime($req['created_at']) + WAIT_SECONDS;
if ($status === 'payment_submitted') $deadline = strtotime($req['paid_at']) + WAIT_SECONDS;
if ($status === 'gst_submitted')     $deadline = (isset($payG['created_at']) ? strtotime($payG['created_at']) : strtotime($req['paid_at'])) + WAIT_SECONDS;

$qrLocal = __DIR__ . '/assets/img/company-qr.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php if ($deadline > 0): ?><meta http-equiv="refresh" content="30"><?php endif; ?>
  <title>My Request | Star Publication</title>
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
    <a class="brand" href="dashboard.php">
      <span class="brand-mark" aria-hidden="true">
        <svg viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#0b1f44"/><path d="M32 10l6.2 12.9L52 25l-10 9.7 2.4 13.8L32 42l-12.4 6.5L22 34.7 12 25l13.8-2.1z" fill="#c9a227"/></svg>
      </span>
      <span class="brand-text"><strong>Star Publication</strong><small>Request Tracker</small></span>
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
    <h1>Service <em>Request</em></h1>
    <p>Track your enquiry and payment status here — updates appear automatically.</p>
  </div>
</header>

<main class="dash-section">
  <div class="container">

    <?php if ($error): ?>
      <div class="alert alert-error" data-autoclose role="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php /* ---------- STEP 0: no active request ---------- */ ?>
    <?php if ($status === 'none' || $status === 'rejected'): ?>
      <?php if ($status === 'rejected'): ?>
        <div class="flow-card" style="margin-bottom:22px;border-left:5px solid #a03030;">
          <h2>Previous request was not approved</h2>
          <p>Please contact our team at +91 98XXX XXXXX for details, or place a fresh enquiry below.</p>
        </div>
      <?php endif; ?>
      <div class="flow-card center-card reveal in">
        <span class="flow-icon"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/></svg></span>
        <h2>Place an Enquiry</h2>
        <p class="muted">Start your handwriting / documentation service request.<br>Our team reviews every request within <strong>30 minutes</strong>.</p>
        <form method="post" action="order.php">
          <input type="hidden" name="action" value="create_request">
          <button class="btn btn-primary btn-lg" type="submit">Place an Enquiry
            <svg viewBox="0 0 24 24"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
          </button>
        </form>
      </div>

    <?php /* ---------- STEP 1: waiting for admin confirmation ---------- */ ?>
    <?php elseif ($status === 'pending'): ?>
      <div class="flow-card center-card">
        <div class="wait-ring" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></div>
        <h2>Please wait — we are reviewing your request</h2>
        <p class="muted">Your enquiry <strong>#<?= (int)$req['id'] ?></strong> has been sent to our admin team.
        Confirmation usually takes up to <strong>30 minutes</strong>. This page updates automatically.</p>
        <div class="count-box"><strong data-deadline="<?= (int)$deadline ?>">30:00</strong></div>
      </div>

    <?php /* ---------- STEP 2: payment page ---------- */ ?>
    <?php elseif ($status === 'confirmed'): ?>
      <div class="pay-grid">
        <div class="flow-card">
          <h2>File Processing Charge</h2>
          <p class="muted">Your request has been confirmed by our team. Pay the processing fee to move forward.</p>
          <p class="muted"><strong>Note:</strong> Your payment refund will be processed in 7-8 working days (if applicable).</p>

          <div class="fee-banner">
            <span>File Pending Charge</span>
            <strong>&#8377;<?= $fee ?></strong>
          </div>

          <ul class="pay-steps">
            <li><span>1</span> Scan the QR with any UPI app (GPay / PhonePe / Paytm)</li>
            <li><span>2</span> Pay exactly <strong>&#8377;<?= $fee ?></strong></li>
            <li><span>3</span> Fill the UTR / reference number and upload the receipt</li>
            <li><span>4</span> Click Submit — verification completes within 30 minutes</li>
          </ul>
        </div>

        <div class="flow-card">
          <div class="qr-wrap">
            <?php if (is_file($qrLocal)): ?>
              <img src="assets/img/company-qr.png" alt="Company UPI QR code" width="210" height="210">
            <?php else: ?>
              <img src="https://api.qrserver.com/v1/create-qr-code/?size=210x210&amp;data=<?= rawurlencode('upi://pay?pa=starpublication@upi&pn=Star Publication&am=' . PROCESSING_FEE . '&cu=INR') ?>" alt="Company UPI QR code" width="210" height="210" onerror="this.parentNode.innerHTML='<div class=&quot;qr-fallback&quot;>QR unavailable&lt;/div>'">
            <?php endif; ?>
            <span class="qr-caption">Star Publication · Company QR</span>
            <code class="upi-id">starpublication@upi</code>
          </div>

          <form method="post" action="order.php" enctype="multipart/form-data" class="pay-form">
            <input type="hidden" name="action" value="submit_payment">
            <div class="field">
              <label for="pName">Payer Name *</label>
              <input type="text" id="pName" name="payer_name" placeholder="Account holder name" required maxlength="100">
            </div>
            <div class="field">
              <label for="pUtr">UTR / Transaction Ref No. *</label>
              <input type="text" id="pUtr" name="utr" placeholder="e.g. 405512345678" required maxlength="50">
            </div>
            <div class="field">
              <label for="pAmt">Amount Paid</label>
              <input type="text" id="pAmt" value="&#8377;<?= $fee ?>" readonly>
            </div>
            <div class="field">
              <label for="pShot">Payment Screenshot / Receipt *</label>
              <input type="file" id="pShot" name="screenshot" accept=".jpg,.jpeg,.png,.webp,.pdf" required>
              <span class="input-hint">JPG, PNG, WEBP or PDF · max 5 MB</span>
            </div>
            <button class="btn btn-primary btn-block" type="submit">Submit Payment Details</button>
          </form>
        </div>
      </div>

    <?php /* ---------- STEP 3: payment verification wait ---------- */ ?>
    <?php elseif ($status === 'payment_submitted'): ?>
      <div class="flow-card center-card">
        <div class="wait-ring gold-ring" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div>
        <h2>Payment details received!</h2>
        <p class="muted">Thank you — your payment of <strong>&#8377;<?= isset($pay) && $pay ? number_format((float)$pay['amount']) : $fee ?></strong>
        (Ref: <strong><?= isset($pay) && $pay ? htmlspecialchars($pay['utr']) : '—' ?></strong>) is under review by our admin team.
        Approval usually takes up to <strong>30 minutes</strong>. This page updates automatically.</p>
        <div class="count-box"><strong data-deadline="<?= (int)$deadline ?>">30:00</strong></div>
      </div>

    <?php /* ---------- STEP 3b: GST PAYMENT ---------- */ ?>
    <?php elseif ($status === 'gst_pending'): ?>
      <div class="pay-grid">
        <div class="flow-card">
          <h2>GST Payment</h2>
          <p class="muted">Your file processing charge has been approved. Please complete the final <strong>GST payment</strong> to finish the process.</p>
          <p class="muted"><strong>Note:</strong> Your payment refund will be processed in 7-8 working days (if applicable).</p>

          <div class="fee-banner">
            <span>GST Charge</span>
            <strong>&#8377;<?= $gstFee ?></strong>
          </div>

          <ul class="pay-steps">
            <li><span>1</span> Scan the QR with any UPI app (GPay / PhonePe / Paytm)</li>
            <li><span>2</span> Pay exactly <strong>&#8377;<?= $gstFee ?></strong></li>
            <li><span>3</span> Fill the UTR / reference number and upload the receipt</li>
            <li><span>4</span> Click Submit — admin approval completes within 30 minutes</li>
          </ul>
        </div>

        <div class="flow-card">
          <div class="qr-wrap">
            <?php if (is_file($qrLocal)): ?>
              <img src="assets/img/company-qr.png" alt="Company UPI QR code" width="210" height="210">
            <?php else: ?>
              <img src="https://api.qrserver.com/v1/create-qr-code/?size=210x210&amp;data=<?= rawurlencode('upi://pay?pa=starpublication@upi&pn=Star Publication&am=' . GST_AMOUNT . '&cu=INR&tn=GST Payment') ?>" alt="Company UPI QR code" width="210" height="210" onerror="this.parentNode.innerHTML='<div class=&quot;qr-fallback&quot;>QR unavailable&lt;/div>'">
            <?php endif; ?>
            <span class="qr-caption">Star Publication · Company QR</span>
            <code class="upi-id">starpublication@upi</code>
          </div>

          <form method="post" action="order.php" enctype="multipart/form-data" class="pay-form">
            <input type="hidden" name="action" value="submit_gst">
            <div class="field">
              <label for="gName">Payer Name *</label>
              <input type="text" id="gName" name="payer_name" placeholder="Account holder name" required maxlength="100">
            </div>
            <div class="field">
              <label for="gUtr">UTR / Transaction Ref No. *</label>
              <input type="text" id="gUtr" name="utr" placeholder="e.g. 405599999999" required maxlength="50">
            </div>
            <div class="field">
              <label for="gAmt">Amount Paid</label>
              <input type="text" id="gAmt" value="&#8377;<?= $gstFee ?>" readonly>
            </div>
            <div class="field">
              <label for="gShot">Payment Screenshot / Receipt *</label>
              <input type="file" id="gShot" name="screenshot" accept=".jpg,.jpeg,.png,.webp,.pdf" required>
              <span class="input-hint">JPG, PNG, WEBP or PDF · max 5 MB</span>
            </div>
            <button class="btn btn-primary btn-block" type="submit">Submit GST Payment</button>
          </form>
        </div>
      </div>

    <?php /* ---------- STEP 3c: GST verification wait ---------- */ ?>
    <?php elseif ($status === 'gst_submitted'): ?>
      <div class="flow-card center-card">
        <div class="wait-ring gold-ring" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div>
        <h2>GST payment details received!</h2>
        <p class="muted">Thank you — your GST payment of <strong>&#8377;<?= $payG ? number_format((float)$payG['amount'], 2) : $gstFee ?></strong>
        (Ref: <strong><?= $payG ? htmlspecialchars($payG['utr']) : '—' ?></strong>) is under review by our admin team.
        Final approval usually takes up to <strong>30 minutes</strong>. This page updates automatically.</p>
        <div class="count-box"><strong data-deadline="<?= (int)$deadline ?>">30:00</strong></div>
      </div>

    <?php /* ---------- STEP 4: completed ---------- */ ?>
    <?php elseif ($status === 'completed'): ?>
      <div class="flow-card center-card success-card">
        <div class="tick-badge" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg></div>
        <span class="status-pill st-done">Approved</span>
        <h2>All Payments Confirmed — You're all set!</h2>
        <p class="muted">Request <strong>#<?= (int)$req['id'] ?></strong> has been fully approved by our admin team.
        Our writing team will contact you shortly at your registered number/email.</p>
        <table class="mini-table">
          <tr><th>File Processing Charge</th><td>&#8377;<?= $payP ? number_format((float)$payP['amount']) : $fee ?> · <?= $payP ? htmlspecialchars($payP['utr']) : '—' ?></td></tr>
          <tr><th>GST Payment</th><td>&#8377;<?= $payG ? number_format((float)$payG['amount'], 2) : $gstFee ?> · <?= $payG ? htmlspecialchars($payG['utr']) : '—' ?></td></tr>
          <tr><th>Payer Name</th><td><?= $payP ? htmlspecialchars($payP['payer_name']) : '—' ?></td></tr>
          <tr><th>Approved On</th><td><?= !empty($req['completed_at']) ? date('d M Y, h:i A', strtotime($req['completed_at'])) : '—' ?></td></tr>
        </table>
        <a class="btn btn-outline" href="dashboard.php">Back to Dashboard</a>
      </div>
    <?php endif; ?>

  </div>
</main>

<script>
(function () {
  var els = document.querySelectorAll('[data-deadline]');
  var serverNow = <?= (int)time() ?>;
  els.forEach(function (el) {
    var dl = parseInt(el.dataset.deadline, 10);
    var remain = dl - serverNow; // remaining seconds, computed entirely on the server clock
    function fmt(s) {
      var h = Math.floor(s / 3600), m = Math.floor((s % 3600) / 60), sec = s % 60;
      return (h > 0 ? h + ':' + (m < 10 ? '0' : '') : '') + m + ':' + (sec < 10 ? '0' : '') + sec;
    }
    function tick() {
      var s = Math.max(0, remain);
      el.textContent = fmt(s);
      if (s <= 0) el.textContent = 'almost done…';
      remain = remain - 1;
    }
    tick(); setInterval(tick, 1000);
  });
})();
</script>
<script src="assets/js/main.js" defer></script>
</body>
</html>
