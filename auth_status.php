<?php
require_once __DIR__ . '/session_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'loggedIn' => isset($_SESSION['user_id']),
    'name' => $_SESSION['user_name'] ?? null
]);
