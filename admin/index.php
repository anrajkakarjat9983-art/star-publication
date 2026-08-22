<?php
session_start();
if (empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/../config.php';

/* ---------- POST actions ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)($_POST['request_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($id > 0) {
        if ($action === 'confirm') {
            $q = $conn->prepare('UPDATE requests SET status = "confirmed", confirmed_at = NOW() WHERE id = ? AND status = "pending"');
            $q->bind_param('i', $id);
            $q->execute();
        } elseif ($action === 'approve') {
            $q = $conn->prepare('UPDATE requests SET status = "gst_pending" WHERE id = ? AND status = "payment_submitted"');
            $q->bind_param('i', $id);
            $q->execute();
        } elseif ($action === 'approve_gst') {
            $q = $conn->prepare('UPDATE requests SET status = "completed", completed_at = NOW() WHERE id = ? AND status = "gst_submitted"');
            $q->bind_param('i', $id);
            $q->execute();
        } elseif ($action === 'reject') {
            $q = $conn->prepare('UPDATE requests SET status = "rejected" WHERE id = ? AND status IN ("pending","confirmed","payment_submitted","gst_pending","gst_submitted")');
            $q->bind_param('i', $id);
            $q->execute();
        }
    }
    header('Location: index.php' . (isset($_POST['view']) && $_POST['view'] === 'payments' ? '?tab=payments' : ''));
    exit;
}

/* ---------- Fetch data ---------- */
$all = $conn->query(
    'SELECT r.*, u.name, u.email, u.phone,
            pp.utr AS p_utr, pp.payer_name AS p_payer, pp.screenshot AS p_shot, pp.amount AS p_amount, pp.created_at AS p_on,
            pg.utr AS g_utr, pg.payer_name AS g_payer, pg.screenshot AS g_shot, pg.amount AS g_amount, pg.created_at AS g_on
     FROM requests r
     JOIN users u ON u.id = r.user_id
     LEFT JOIN payments pp ON pp.request_id = r.id AND pp.type = "processing"
     LEFT JOIN payments pg ON pg.request_id = r.id AND pg.type = "gst"
     ORDER BY r.id DESC'
)->fetch_all(MYSQLI_ASSOC);

$groups = ['pending' => [], 'payment_submitted' => [], 'confirmed' => [], 'gst_pending' => [], 'gst_submitted' => [], 'completed' => [], 'rejected' => []];
foreach ($all as $r) {
    $groups[$r['status']][] = $r;
}
$counts = array_map('count', $groups);

function badge(string $s): string {
    $map = [
        'pending'           => ['st-pending', 'Pending Review'],
        'confirmed'         => ['st-info',    'Confirmed — Awaiting ₹1500'],
        'payment_submitted' => ['st-warn',    'Fee Verification'],
        'gst_pending'       => ['st-info',    'Approved — Awaiting GST'],
        'gst_submitted'     => ['st-warn',    'GST Verification'],
        'completed'         => ['st-done',    'Completed / Approved'],
        'rejected'          => ['st-danger',  'Rejected'],
    ];
    [$cls, $lbl] = $map[$s] ?? ['', $s];
    return '<span class="status-pill ' . $cls . '">' . $lbl . '</span>';
}

$adminName = htmlspecialchars((string)$_SESSION['admin_name']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Panel | Star Publication</title>
  <meta name="robots" content="noindex">
  <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='14' fill='%230b1f44'/%3E%3Cpath d='M32 10l6.2 12.9L52 25l-10 9.7 2.4 13.8L32 42l-12.4 6.5L22 34.7 12 25l13.8-2.1z' fill='%23c9a227'/%3E%3C/svg%3E">
  <meta http-equiv="refresh" content="60">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="admin-body">

<nav class="dash-nav">
  <div class="container dash-nav-inner">
    <a class="brand" href="index.php">
      <span class="brand-mark" aria-hidden="true">
        <svg viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#0b1f44"/><path d="M32 10l6.2 12.9L52 25l-10 9.7 2.4 13.8L32 42l-12.4 6.5L22 34.7 12 25l13.8-2.1z" fill="#c9a227"/></svg>
      </span>
      <span class="brand-text"><strong>Star Publication</strong><small>Admin Panel</small></span>
    </a>
    <div class="dash-user">
      <span class="avatar admin-avatar"><?= strtoupper(mb_substr($adminName, 0, 1)) ?></span>
      <span><strong><?= $adminName ?></strong><small>Administrator</small></span>
      <a class="btn btn-danger btn-sm" href="logout.php">Logout</a>
    </div>
  </div>
</nav>

<header class="dash-hero">
  <div class="container">
    <h1>Requests &amp; <em>Approvals</em></h1>
    <p>New enquiries need confirmation · payment submissions need approval. Auto-refreshes every 60s.</p>
  </div>
</header>

<main class="dash-section">
  <div class="container">

    <div class="admin-stats">
      <div class="astat"><strong><?= (int)$counts['pending'] ?></strong><span>Pending Requests</span></div>
      <div class="astat"><strong><?= (int)$counts['payment_submitted'] ?></strong><span>₹1500 Fee to Verify</span></div>
      <div class="astat"><strong><?= (int)$counts['gst_submitted'] ?></strong><span>GST to Verify</span></div>
      <div class="astat"><strong><?= (int)$counts['completed'] ?></strong><span>Completed</span></div>
    </div>

    <?php function renderTable(array $rows, string $mode): void {
        $pre = $mode === 'gst' ? 'g_' : 'p_';
        $payCols = in_array($mode, ['payments', 'gst'], true);
    ?>
      <table class="admin-table">
        <thead>
          <tr><th>#ID</th><th>Client</th><th>Contact</th><th>Timeline</th><?= $payCols ? '<th>Payment Proof</th>' : '' ?><th>Status</th><th>Action</th></tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
          <tr>
            <td><strong>#<?= (int)$r['id'] ?></strong></td>
            <td><?= htmlspecialchars($r['name']) ?><br><small class="muted"><?= htmlspecialchars($r['email']) ?></small></td>
            <td><?= htmlspecialchars($r['phone']) ?></td>
            <td class="muted small-cell">
              Placed: <?= date('d M, h:i A', strtotime($r['created_at'])) ?>
              <?php if ($payCols && !empty($r[$pre . 'utr'])): ?>
                <br>Paid: <?= !empty($r[$pre . 'on']) ? date('d M, h:i A', strtotime($r[$pre . 'on'])) : '—' ?>
                <br>UTR: <strong><?= htmlspecialchars($r[$pre . 'utr']) ?></strong>
                <?php if (!empty($r[$pre . 'payer'])): ?> · <?= htmlspecialchars($r[$pre . 'payer']) ?><?php endif; ?>
                · ₹<?= number_format((float)$r[$pre . 'amount'], 2) ?>
              <?php elseif (!empty($r['paid_at'])): ?>
                <br>Paid: <?= date('d M, h:i A', strtotime($r['paid_at'])) ?>
              <?php endif; ?>
            </td>
            <?php if ($payCols): ?>
            <td><?php if (!empty($r[$pre . 'shot'])): ?>
              <a class="shot-link" href="../uploads/payments/<?= rawurlencode($r[$pre . 'shot']) ?>" target="_blank">View Receipt ↗</a>
            <?php else: ?>—<?php endif; ?></td>
            <?php endif; ?>
            <td><?= badge($r['status']) ?></td>
            <td class="actions-cell">
              <?php if ($r['status'] === 'pending'): ?>
                <form method="post"><input type="hidden" name="action" value="confirm"><input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>"><button class="btn btn-ok btn-xs" type="submit">Confirm ✓</button></form>
              <?php elseif ($r['status'] === 'payment_submitted'): ?>
                <form method="post"><input type="hidden" name="action" value="approve"><input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>"><button class="btn btn-ok btn-xs" type="submit">Approve → GST Step ✓</button></form>
              <?php elseif ($r['status'] === 'gst_submitted'): ?>
                <form method="post"><input type="hidden" name="action" value="approve_gst"><input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>"><button class="btn btn-ok btn-xs" type="submit">Approve GST ✓</button></form>
              <?php else: ?>—<?php endif; ?>
              <?php if (in_array($r['status'], ['pending','confirmed','payment_submitted','gst_pending','gst_submitted'], true)): ?>
                <form method="post" onsubmit="return confirm('Reject this request?')"><input type="hidden" name="action" value="reject"><input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>"><button class="btn btn-danger btn-xs" type="submit">Reject ✕</button></form>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$rows): ?>
          <tr><td colspan="7" class="empty-row">Nothing here right now.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    <?php } ?>

    <section class="admin-section">
      <h2 class="admin-h2">🟡 New Requests — Confirm to open ₹1500 payment step <span class="count-chip"><?= (int)$counts['pending'] ?></span></h2>
      <?php renderTable($groups['pending'], 'requests'); ?>
    </section>

    <section class="admin-section">
      <h2 class="admin-h2">💰 Fee Verifications (₹1500) — Approve to open GST step <span class="count-chip gold"><?= (int)$counts['payment_submitted'] ?></span></h2>
      <?php renderTable($groups['payment_submitted'], 'payments'); ?>
    </section>

    <section class="admin-section">
      <h2 class="admin-h2">🧾 GST Verifications (₹2790.01) — Final approval <span class="count-chip green"><?= (int)$counts['gst_submitted'] ?></span></h2>
      <?php renderTable($groups['gst_submitted'], 'gst'); ?>
    </section>

    <section class="admin-section">
      <h2 class="admin-h2">🔵 Confirmed — Awaiting ₹1500 payment <span class="count-chip blue"><?= (int)$counts['confirmed'] ?></span></h2>
      <?php renderTable($groups['confirmed'], 'requests'); ?>
    </section>

    <section class="admin-section">
      <h2 class="admin-h2">🟢 Approved — Awaiting GST payment <span class="count-chip blue"><?= (int)$counts['gst_pending'] ?></span></h2>
      <?php renderTable($groups['gst_pending'], 'requests'); ?>
    </section>

    <section class="admin-section collapsed-section">
      <details>
        <summary class="admin-h2">History — Completed / Rejected</summary>
        <?php renderTable($groups['completed'], 'gst'); ?>
        <?php renderTable($groups['rejected'], 'requests'); ?>
      </details>
    </section>

  </div>
</main>

<script src="../assets/js/main.js" defer></script>
</body>
</html>
