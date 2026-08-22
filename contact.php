<?php
require_once __DIR__ . '/config.php';

$name    = trim((string)($_POST['name'] ?? ''));
$email   = strtolower(trim((string)($_POST['email'] ?? '')));
$phone   = preg_replace('/\D/', '', (string)($_POST['phone'] ?? ''));
$message = trim((string)($_POST['message'] ?? ''));

$ok =
    mb_strlen($name) >= 3 &&
    filter_var($email, FILTER_VALIDATE_EMAIL) &&
    ($phone === '' || preg_match('/^[6-9]\d{9}$/', $phone)) &&
    mb_strlen($message) >= 5;

if (!$ok) {
    header('Location: index.html?sent=0#contact');
    exit;
}

$stmt = $conn->prepare('INSERT INTO contact_messages (name, email, phone, message) VALUES (?, ?, ?, ?)');
$stmt->bind_param('ssss', $name, $email, $phone, $message);
$saved = $stmt->execute();
$stmt->close();

header('Location: index.html?sent=' . ($saved ? '1' : '0') . '#contact');
exit;
