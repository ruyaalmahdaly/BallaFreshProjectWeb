<?php

$sessionPath = 'C:\\xampp\\session_data';

if (!is_dir($sessionPath)) {
    @mkdir($sessionPath, 0700, true);
}

if (!is_dir($sessionPath) || !is_writable($sessionPath)) {
    http_response_code(500);
    exit('Folder session tidak tersedia.');
}

if (session_status() === PHP_SESSION_NONE) {

    session_save_path($sessionPath);

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');

    session_start();
}
