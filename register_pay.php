<?php
session_start();
require_once __DIR__ . '/config.php';

$error = '';
$name   = trim((string)($_POST['name'] ?? ''));
$phone  = preg_replace('/\D/', '', (string)($_POST['phone'] ?? ''));
$utr    = strtoupper(preg_replace('/\s+/', '', (string)($_POST['utr'] ?? '')));
$amount = (float)($_POST['amount'] ?? 0);
$proj   = (string)($_POST['project'] ?? '');

if (!in_array($amount, [499.00, 599.00, 699.00], true)) $amount = 499.00;

$projMap = ['1' => 'Project 1', '2' => 'Project 2', '3' => 'Project 3'];
if (isset($projMap[$proj])) {
    $projLabel = $projMap[$proj];
    $projKey = $proj;
} else {
    $projLabel = null;
    $projKey = null;
}

if (mb_strlen($name) < 3) {
    $msg = 'Please enter your full name.';
} elseif (!preg_match('/^[6-9]\d{9}$/', $phone)) {
    $msg = 'Please enter a valid 10-digit mobile number.';
} elseif (!preg_match('/^[A-Z0-9]{6,50}$/', $utr)) {
    $msg = 'UTR / Reference number must be 6-50 letters or digits.';
} elseif (!isset($_FILES['screenshot']) || $_FILES['screenshot']['error'] !== UPLOAD_ERR_OK) {
    $msg = 'Please upload your payment screenshot or receipt.';
} else {
    $tmp  = $_FILES['screenshot']['tmp_name'];
    $size = (int)$_FILES['screenshot']['size'];
    $ext  = strtolower(pathinfo($_FILES['screenshot']['name'], PATHINFO_EXTENSION));
    $mime = (string)(new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $okExt = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

    $valid = in_array($ext, $okExt, true) &&
             ($mime === 'application/pdf' || str_starts_with($mime, 'image/'));

    if ($size > 5 * 1024 * 1024) {
        $msg = 'File is too large. Maximum size is 5 MB.';
    } elseif (!$valid) {
        $msg = 'Invalid file type. Upload JPG, PNG, WEBP or PDF only.';
    } else {
        $conn->query(
            'CREATE TABLE IF NOT EXISTS registrations (
                id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id    INT UNSIGNED DEFAULT NULL,
                project    VARCHAR(20)  DEFAULT NULL,
                name       VARCHAR(100) NOT NULL,
                phone      VARCHAR(15)  NOT NULL,
                utr        VARCHAR(50)  NOT NULL,
                amount     DECIMAL(10,2) NOT NULL DEFAULT ' . REGISTRATION_FEE . '.00,
                screenshot VARCHAR(255) DEFAULT NULL,
                status     ENUM("pending","approved","rejected") NOT NULL DEFAULT "pending",
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
        if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0777, true);
        $fname = 'rg_' . bin2hex(random_bytes(8)) . '.' . $ext;
        if (!move_uploaded_file($tmp, UPLOAD_DIR . $fname)) {
            $msg = 'Could not save the uploaded file. Please try again.';
        } else {
            $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
            $ins = $conn->prepare('INSERT INTO registrations (user_id, project, name, phone, utr, amount, screenshot) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $ins->bind_param('isssdss', $uid, $projLabel, $name, $phone, $utr, $amount, $fname);
            if ($ins->execute()) {
                if ($uid && $projLabel) {
                    $u = $conn->prepare('UPDATE users SET project = ? WHERE id = ?');
                    $u->bind_param('si', $projLabel, $uid);
                    $u->execute();
                    header('Location: dashboard.php?welcome=1&paid=1');
                    exit;
                }
                header('Location: login.php?tab=register&regpay=1');
                exit;
            }
            $msg = 'Could not save your payment. Please try again.';
        }
    }
}

header('Location: index.html?regpay=0#contact');
exit;
