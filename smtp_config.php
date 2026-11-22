<?php
$localEnv = file_exists(__DIR__ . '/env.local.php') ? (require __DIR__ . '/env.local.php') : [];
return [
    'host'       => getenv('SMTP_HOST') ?: ($localEnv['SMTP_HOST'] ?? 'smtp.gmail.com'),
    'port'       => (int)(getenv('SMTP_PORT') ?: ($localEnv['SMTP_PORT'] ?? 587)),
    'username'   => getenv('SMTP_USER') ?: ($localEnv['SMTP_USER'] ?? 'ballafresh01@gmail.com'),
    'password'   => getenv('SMTP_PASS') ?: ($localEnv['SMTP_PASS'] ?? ''),
    'from_email' => getenv('SMTP_FROM_EMAIL') ?: ($localEnv['SMTP_FROM_EMAIL'] ?? 'ballafresh01@gmail.com'),
    'from_name'  => getenv('SMTP_FROM_NAME') ?: ($localEnv['SMTP_FROM_NAME'] ?? "Balla' Fresh"),
];
