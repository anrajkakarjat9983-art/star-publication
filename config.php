<?php
/**
 * Star Publication — Database configuration & auto-setup.
 * Connects to MySQL and auto-creates the database/tables on first run.
 */

declare(strict_types=1);

mysqli_report(MYSQLI_REPORT_OFF); // handle errors manually

function env_or(string $key, string $default): string {
    $v = getenv($key);
    return ($v === false || $v === '') ? $default : $v;
}

define('DB_HOST', env_or('DB_HOST', 'localhost'));
define('DB_PORT', env_or('DB_PORT', '3306'));
define('DB_USER', env_or('DB_USER', 'root'));
define('DB_PASS', env_or('DB_PASS', ''));
define('DB_NAME', env_or('DB_NAME', 'star_publication'));
define('DB_SSL',  getenv('DB_SSL') === '1');

$conn = @mysqli_init();
if ($conn === false) {
    $conn = null;
} elseif (DB_SSL) {
    mysqli_ssl_set($conn, null, null, null, null, null);
    $ok = @mysqli_real_connect($conn, DB_HOST, DB_USER, DB_PASS, null, (int)DB_PORT, null, MYSQLI_CLIENT_SSL);
    if ($ok === false) $conn = null;
} else {
    @$conn->real_connect(DB_HOST, DB_USER, DB_PASS, null, (int)DB_PORT);
}

if ($conn === null || ($conn instanceof mysqli && $conn->connect_errno)) {
    http_response_code(500);
    die(
        '<div style="font-family:Segoe UI,Arial,sans-serif;max-width:560px;margin:80px auto;'
        . 'padding:32px;border:1px solid #e3e9f4;border-radius:16px;text-align:center;color:#182238">'
        . '<h2 style="color:#0b1f44;margin-top:0">Database connection failed</h2>'
        . '<p>Please start <strong>MySQL</strong> and refresh this page.</p>'
        . '<p style="color:#5a6478;font-size:13px">' . htmlspecialchars(($conn instanceof mysqli && $conn->connect_error) ? $conn->connect_error : 'Unable to initialise connection.') . '</p></div>'
    );
}

const WAIT_SECONDS   = 1800;                       // 30-minute review window
const PROCESSING_FEE = 1500;                       // file processing charge (INR)
const GST_AMOUNT     = 2790.01;                    // GST payment (INR)
const UPLOAD_DIR     = __DIR__ . '/uploads/payments/';

$conn->set_charset('utf8mb4');

// Auto-create database if it does not exist
$conn->query('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$conn->select_db(DB_NAME);

// Migrate older installs: ensure requests.status supports GST stages
$col = $conn->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '" . $conn->real_escape_string(DB_NAME) . "' AND TABLE_NAME = 'requests' AND COLUMN_NAME = 'status'");
if ($col && ($row = $col->fetch_assoc()) && strpos($row['COLUMN_TYPE'], 'gst_pending') === false) {
    $conn->query('ALTER TABLE requests MODIFY status ENUM("pending","confirmed","payment_submitted","gst_pending","gst_submitted","completed","rejected") NOT NULL DEFAULT "pending"');
}
// Migrate older installs: ensure payments.type exists
$col2 = $conn->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '" . $conn->real_escape_string(DB_NAME) . "' AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'type'");
if ($col2 && $col2->num_rows === 0) {
    $conn->query('ALTER TABLE payments ADD COLUMN type ENUM("processing","gst") NOT NULL DEFAULT "processing" AFTER request_id');
}

// Auto-create tables if they do not exist
$conn->query(
    'CREATE TABLE IF NOT EXISTS users (
        id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name          VARCHAR(100) NOT NULL,
        email         VARCHAR(150) NOT NULL UNIQUE,
        phone         VARCHAR(15)  NOT NULL,
        address       VARCHAR(255) NOT NULL,
        pincode       VARCHAR(10)  NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$conn->query(
    'CREATE TABLE IF NOT EXISTS contact_messages (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name       VARCHAR(100) NOT NULL,
        email      VARCHAR(150) NOT NULL,
        phone      VARCHAR(15)  DEFAULT NULL,
        message    TEXT         NOT NULL,
        created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$conn->query(
    'CREATE TABLE IF NOT EXISTS admins (
        id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name          VARCHAR(100) NOT NULL,
        email         VARCHAR(150) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$conn->query(
    'CREATE TABLE IF NOT EXISTS requests (
        id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id      INT UNSIGNED NOT NULL,
        status       ENUM("pending","confirmed","payment_submitted","gst_pending","gst_submitted","completed","rejected") NOT NULL DEFAULT "pending",
        confirmed_at DATETIME NULL,
        paid_at      DATETIME NULL,
        completed_at DATETIME NULL,
        created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$conn->query(
    'CREATE TABLE IF NOT EXISTS payments (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        request_id INT UNSIGNED NOT NULL,
        type       ENUM("processing","gst") NOT NULL DEFAULT "processing",
        amount     DECIMAL(10,2) NOT NULL DEFAULT ' . PROCESSING_FEE . '.00,
        utr        VARCHAR(50)   NOT NULL,
        payer_name VARCHAR(100)  DEFAULT NULL,
        screenshot VARCHAR(255)  DEFAULT NULL,
        created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_req (request_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

// Seed the default admin account once
$chk = $conn->query('SELECT COUNT(*) AS c FROM admins');
if ($chk && (int)$chk->fetch_assoc()['c'] === 0) {
    $hash = password_hash(env_or('ADMIN_PASS', 'admin123'), PASSWORD_DEFAULT);
    $ins = $conn->prepare('INSERT INTO admins (name, email, password_hash) VALUES (?, ?, ?)');
    $adminName = 'Administrator';
    $adminMail = env_or('ADMIN_EMAIL', 'admin@starpublication.in');
    $ins->bind_param('sss', $adminName, $adminMail, $hash);
    $ins->execute();
    $ins->close();
}
