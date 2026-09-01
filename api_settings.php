<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');
echo json_encode([
    'upi_id'        => (string)($SETTINGS['upi_id'] ?? 'starpublication@upi'),
    'upi_name'      => (string)($SETTINGS['upi_name'] ?? 'Star Publication'),
    'qr_path'       => (string)($SETTINGS['qr_path'] ?? ''),
    'contact_phone' => (string)($SETTINGS['contact_phone'] ?? '+91 98XXX XXXXX'),
    'contact_email' => (string)($SETTINGS['contact_email'] ?? 'info@starpublication.in'),
    'contact_email2'=> (string)($SETTINGS['contact_email2'] ?? 'support@starpublication.in'),
]);