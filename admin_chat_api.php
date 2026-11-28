<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Makassar');

require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

// Verifikasi sesi login admin
if (empty($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Sesi login admin tidak valid atau telah berakhir. Silakan muat ulang halaman.'
    ]);
    exit;
}

$adminId = (int) $_SESSION['admin_id'];
$adminName = (string) ($_SESSION['admin_name'] ?? 'Admin Agency');
$adminRole = (string) ($_SESSION['admin_role'] ?? 'admin');
$adminIsOwner = (int) ($_SESSION['admin_is_owner'] ?? 0);

// Helper: Format bytes
if (!function_exists('formatChatBytes')) {
    function formatChatBytes(int $bytes): string {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 0) . ' KB';
        }
        return $bytes . ' B';
    }
}

// Helper: Parse lampiran chat
if (!function_exists('parseChatAttachments')) {
    function parseChatAttachments(string $rawMessage): array {
        $lines = preg_split('/\r\n|\r|\n/', $rawMessage) ?: [];
        $plainLines = [];
        $attachments = [];
        $pendingName = '';

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (preg_match('/^\[Lampiran\]\s*(.+)$/i', $trimmed, $mName)) {
                $pendingName = trim($mName[1]);
                continue;
            }
            if (preg_match('/^URL:\s*(https?:\/\/[^\s]+)$/i', $trimmed, $mUrl)) {
                $url = trim($mUrl[1]);
                $path = (string) parse_url($url, PHP_URL_PATH);
                if (strpos($path, 'uploads/chat_attachments/') !== false || strpos($url, 'uploads/chat_attachments/') !== false) {
                    $ext = strtolower(pathinfo($path ?: $url, PATHINFO_EXTENSION));
                    $displayName = $pendingName ?: basename($path ?: $url);

                    $fileSizeFormatted = '';
                    $cleanPath = ltrim((string)strstr($path ?: $url, 'uploads/chat_attachments/'), '/');
                    $localPath = __DIR__ . '/' . $cleanPath;
                    if (is_file($localPath)) {
                        $bytes = filesize($localPath);
                        $fileSizeFormatted = formatChatBytes((int)$bytes);
                    }

                    $attachments[] = [
                        'url' => $url,
                        'name' => $displayName,
                        'ext' => $ext,
                        'size' => $fileSizeFormatted
                    ];
                }
                $pendingName = '';
                continue;
            }
            if ($pendingName !== '') {
                $plainLines[] = '[Lampiran] ' . $pendingName;
                $pendingName = '';
            }
            $plainLines[] = $line;
        }

        if ($pendingName !== '') {
            $plainLines[] = '[Lampiran] ' . $pendingName;
        }

        $plainText = trim(implode("\n", $plainLines));
        return [
            'text' => $plainText,
            'attachments' => $attachments
        ];
    }
}

// Helper: Format status online / offline admin
if (!function_exists('formatAdminOnlineStatus')) {
    function formatAdminOnlineStatus(?string $lastActiveAt): array {
        if (empty($lastActiveAt)) {
            return [
                'is_online' => false,
                'status_type' => 'never',
                'badge_class' => 'offline',
                'label' => 'Belum Pernah Aktif',
                'time_text' => 'Belum pernah login',
                'dot_color' => '#94a3b8'
            ];
        }

        $tz = new DateTimeZone('Asia/Makassar');
        $now = new DateTime('now', $tz);
        try {
            $act = new DateTime($lastActiveAt, $tz);
        } catch (Throwable $e) {
            return [
                'is_online' => false,
                'status_type' => 'unknown',
                'badge_class' => 'offline',
                'label' => 'Waktu Tidak Valid',
                'time_text' => (string)$lastActiveAt,
                'dot_color' => '#94a3b8'
            ];
        }

        $diffSec = $now->getTimestamp() - $act->getTimestamp();
        if ($diffSec < 0) {
            $diffSec = 0;
        }

        // Aktif dalam 5 menit terakhir (300 detik) dianggap SEDANG ONLINE
        if ($diffSec <= 300) {
            return [
                'is_online' => true,
                'status_type' => 'online',
                'badge_class' => 'online',
                'label' => 'Aktif Sekarang',
                'time_text' => 'Online di dashboard',
                'dot_color' => '#10b981'
            ];
        }

        if ($diffSec < 3600) {
            $mins = max(1, (int)round($diffSec / 60));
            return [
                'is_online' => false,
                'status_type' => 'recent',
                'badge_class' => 'recent',
                'label' => "Offline {$mins} mnt lalu",
                'time_text' => "Terakhir aktif {$mins} menit yang lalu",
                'dot_color' => '#e48e1e'
            ];
        }

        if ($diffSec < 86400) {
            $hours = (int)round($diffSec / 3600);
            return [
                'is_online' => false,
                'status_type' => 'offline',
                'badge_class' => 'offline',
                'label' => "Offline {$hours} jam lalu",
                'time_text' => "Terakhir aktif {$hours} jam yang lalu",
                'dot_color' => '#64748b'
            ];
        }

        $days = (int)round($diffSec / 86400);
        return [
            'is_online' => false,
            'status_type' => 'offline',
            'badge_class' => 'offline',
            'label' => "Offline {$days} hr lalu",
            'time_text' => "Terakhir aktif {$days} hari yang lalu",
            'dot_color' => '#94a3b8'
        ];
    }
}

// Perbarui heartbeat admin yang sedang mengakses API
$nowMakassar = (new DateTime('now', new DateTimeZone('Asia/Makassar')))->format('Y-m-d H:i:s');
try {
    $upHeartbeat = $conn->prepare("UPDATE admins SET last_active_at = ? WHERE id = ?");
    if ($upHeartbeat) {
        $upHeartbeat->bind_param("si", $nowMakassar, $adminId);
        $upHeartbeat->execute();
        $upHeartbeat->close();
    }
} catch (Throwable $e) {}

// Fungsi bantu: Ambil daftar seluruh admin agency dan status onlinenya
function fetchAllAdmins(mysqli|SupabaseMysqliAdapter|SupabaseRestAdapter|null $conn, int $currentAdminId): array {
    $admins = [];
    $totalOnline = 0;

    $query = "SELECT id, name, email, is_approved, is_owner, created_at, last_active_at,
                     COALESCE(role, IF(is_owner = 1, 'owner', 'admin')) AS role, phone
              FROM admins
              WHERE is_approved = 1
              ORDER BY is_owner DESC, id ASC";

    $res = $conn ? $conn->query($query) : null;
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $st = formatAdminOnlineStatus($row['last_active_at'] ?? null);
            $row['online_status'] = $st;
            if ($st['is_online']) {
                $totalOnline++;
            }
            $row['is_current'] = ((int)$row['id'] === $currentAdminId);
            unset($row['password']);

            // Inisial avatar
            $parts = preg_split('/\s+/', trim((string)$row['name'])) ?: [];
            $init = 'AD';
            if (count($parts) >= 2) {
                $init = strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
            } elseif (!empty($parts[0])) {
                $init = strtoupper(substr($parts[0], 0, 2));
            }
            $row['initials'] = $init;

            $admins[] = $row;
        }
        if (method_exists($res, 'free')) {
            $res->free();
        }
    }

    return [
        'admins' => $admins,
        'total_online' => $totalOnline,
        'total_admins' => count($admins)
    ];
}

// Fungsi bantu: Validasi dan mapping Room ID
function resolveRoomTarget(string $roomId, int $currentAdminId): array {
    $roomId = trim($roomId);
    if ($roomId === '' || $roomId === 'general' || $roomId === 'team') {
        return [
            'room_id' => 'general',
            'room_type' => 'team',
            'title' => 'Ruang Tim Agency (Semua Admin)',
            'subtitle' => 'Saluran utama koordinasi tim & update operasional',
            'partner' => null,
            'consultation_id' => 9000
        ];
    }

    // Direct message: dm_{id1}_{id2}
    if (preg_match('/^dm_(\d+)_(\d+)$/', $roomId, $matches)) {
        $idA = (int) $matches[1];
        $idB = (int) $matches[2];
        $minId = min($idA, $idB);
        $maxId = max($idA, $idB);
        $canonicalRoomId = "dm_{$minId}_{$maxId}";
        $partnerId = ($currentAdminId === $minId) ? $maxId : $minId;

        // Mapped consultation_id untuk isolasi pesan DM di tabel consultation_messages jika belum ada tabel khusus
        $consultationId = 9100 + ($minId * 50) + $maxId;

        return [
            'room_id' => $canonicalRoomId,
            'room_type' => 'direct',
            'partner_id' => $partnerId,
            'consultation_id' => $consultationId
        ];
    }

    // Default ke general jika format tidak dikenali
    return [
        'room_id' => 'general',
        'room_type' => 'team',
        'title' => 'Ruang Tim Agency (Semua Admin)',
        'subtitle' => 'Saluran utama koordinasi tim & update operasional',
        'partner' => null,
        'consultation_id' => 9000
    ];
}

// Helper: Deteksi apakah tabel agency_chat_messages ada di database
function hasAgencyChatTable(mysqli|SupabaseMysqliAdapter|SupabaseRestAdapter|null $conn): bool {
    static $hasTable = null;
    if ($hasTable !== null) {
        return $hasTable;
    }

    if ($conn instanceof SupabaseRestAdapter) {
        // Cek via REST apakah endpoint ada
        if (function_exists('supabaseRestRequest')) {
            $check = supabaseRestRequest('agency_chat_messages?select=id&limit=1', 'GET');
            $hasTable = ($check['status'] >= 200 && $check['status'] < 300);
            return $hasTable;
        }
    }

    // MySQL / PostgreSQL check
    try {
        $chk = $conn->query("SELECT 1 FROM agency_chat_messages LIMIT 1");
        $hasTable = ($chk !== false);
        return $hasTable;
    } catch (Throwable $e) {
        $hasTable = false;
        return false;
    }
}

// Fungsi bantu: Ambil riwayat pesan berdasarkan room
function fetchMessagesByRoom(
    mysqli|SupabaseMysqliAdapter|SupabaseRestAdapter|null $conn,
    array $roomTarget,
    int $currentAdminId,
    int $afterId = 0,
    int $limit = 60
): array {
    $roomId = $roomTarget['room_id'];
    $consultationId = (int)$roomTarget['consultation_id'];
    $useAgencyTable = hasAgencyChatTable($conn);
    $messages = [];

    if ($useAgencyTable) {
        // Query dari agency_chat_messages
        if ($conn instanceof SupabaseRestAdapter && function_exists('supabaseRestRequest')) {
            $endpoint = 'agency_chat_messages?select=*&room_id=eq.' . rawurlencode($roomId);
            if ($afterId > 0) {
                $endpoint .= '&id=gt.' . $afterId;
            }
            $endpoint .= '&order=id.asc&limit=' . $limit;

            $res = supabaseRestRequest($endpoint, 'GET');
            if ($res['success'] && is_array($res['data'])) {
                foreach ($res['data'] as $row) {
                    $messages[] = formatMessageItem($row, $currentAdminId);
                }
            }
        } else {
            $sql = "SELECT id, room_type, room_id, sender_id, sender_name, message, created_at
                    FROM agency_chat_messages
                    WHERE room_id = ? " . ($afterId > 0 ? "AND id > ? " : "") . "
                    ORDER BY id ASC
                    LIMIT ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                if ($afterId > 0) {
                    $stmt->bind_param("sii", $roomId, $afterId, $limit);
                } else {
                    $stmt->bind_param("si", $roomId, $limit);
                }
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res) {
                    while ($row = $res->fetch_assoc()) {
                        $messages[] = formatMessageItem($row, $currentAdminId);
                    }
                }
                $stmt->close();
            }
        }
    } else {
        // Fallback: Query dari consultation_messages dengan mapping consultation_id
        if ($conn instanceof SupabaseRestAdapter && function_exists('supabaseRestRequest')) {
            $endpoint = 'consultation_messages?select=*&consultation_id=eq.' . $consultationId;
            if ($afterId > 0) {
                $endpoint .= '&id=gt.' . $afterId;
            }
            $endpoint .= '&order=id.asc&limit=' . $limit;

            $res = supabaseRestRequest($endpoint, 'GET');
            if ($res['success'] && is_array($res['data'])) {
                foreach ($res['data'] as $row) {
                    $messages[] = formatMessageItem($row, $currentAdminId);
                }
            }
        } else {
            $sql = "SELECT id, consultation_id, sender_type, sender_id, sender_name, message, created_at
                    FROM consultation_messages
                    WHERE consultation_id = ? " . ($afterId > 0 ? "AND id > ? " : "") . "
                    ORDER BY id ASC
                    LIMIT ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                if ($afterId > 0) {
                    $stmt->bind_param("iii", $consultationId, $afterId, $limit);
                } else {
                    $stmt->bind_param("ii", $consultationId, $limit);
                }
                $stmt->execute();
                $res = $stmt->get_result();
                if ($res) {
                    while ($row = $res->fetch_assoc()) {
                        $messages[] = formatMessageItem($row, $currentAdminId);
                    }
                }
                $stmt->close();
            }
        }
    }

    return $messages;
}

// Helper: Format satu pesan untuk output JSON
function formatMessageItem(array $raw, int $currentAdminId): array {
    $senderId = (int)($raw['sender_id'] ?? 0);
    $isSelf = ($senderId === $currentAdminId);
    $rawMessage = (string)($raw['message'] ?? '');
    $parsed = parseChatAttachments($rawMessage);

    // Initial sender
    $parts = preg_split('/\s+/', trim((string)($raw['sender_name'] ?? 'Admin'))) ?: [];
    $init = 'AD';
    if (count($parts) >= 2) {
        $init = strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    } elseif (!empty($parts[0])) {
        $init = strtoupper(substr($parts[0], 0, 2));
    }

    $createdTime = (string)($raw['created_at'] ?? '');
    $timeFormatted = '';
    $dateFormatted = '';
    if (!empty($createdTime)) {
        try {
            $dt = new DateTime($createdTime, new DateTimeZone('Asia/Makassar'));
            $timeFormatted = $dt->format('H:i');
            $dateFormatted = $dt->format('d M Y');
        } catch (Throwable $e) {
            $timeFormatted = substr($createdTime, 11, 5);
            $dateFormatted = substr($createdTime, 0, 10);
        }
    }

    return [
        'id' => (int)($raw['id'] ?? 0),
        'sender_id' => $senderId,
        'sender_name' => (string)($raw['sender_name'] ?? 'Admin'),
        'sender_initials' => $init,
        'is_self' => $isSelf,
        'text' => $parsed['text'],
        'attachments' => $parsed['attachments'],
        'time' => $timeFormatted,
        'date' => $dateFormatted,
        'created_at' => $createdTime
    ];
}

// -------------------------------------------------------------
// ROUTER AKSI
// -------------------------------------------------------------
$action = (string)($_POST['action'] ?? $_GET['action'] ?? 'fetch_init');

switch ($action) {
    case 'fetch_init':
        $adminsData = fetchAllAdmins($conn, $adminId);
        $roomId = (string)($_GET['room_id'] ?? 'general');
        $roomTarget = resolveRoomTarget($roomId, $adminId);

        // Jika DM, lengkapi data partner
        if ($roomTarget['room_type'] === 'direct' && !empty($roomTarget['partner_id'])) {
            foreach ($adminsData['admins'] as $adm) {
                if ((int)$adm['id'] === (int)$roomTarget['partner_id']) {
                    $roomTarget['partner'] = $adm;
                    $roomTarget['title'] = $adm['name'];
                    $roomTarget['subtitle'] = ($adm['role'] === 'owner' ? 'Owner & Agency Lead' : 'Admin Agency') . ' · ' . $adm['online_status']['label'];
                    break;
                }
            }
        }

        $messages = fetchMessagesByRoom($conn, $roomTarget, $adminId, 0, 80);

        echo json_encode([
            'success' => true,
            'current_admin' => [
                'id' => $adminId,
                'name' => $adminName,
                'role' => $adminRole,
                'is_owner' => $adminIsOwner
            ],
            'admins' => $adminsData['admins'],
            'total_online' => $adminsData['total_online'],
            'room' => $roomTarget,
            'messages' => $messages
        ], JSON_UNESCAPED_UNICODE);
        exit;

    case 'fetch_messages':
        $roomId = (string)($_GET['room_id'] ?? 'general');
        $afterId = (int)($_GET['after_id'] ?? 0);
        $roomTarget = resolveRoomTarget($roomId, $adminId);

        $adminsData = fetchAllAdmins($conn, $adminId);
        $messages = fetchMessagesByRoom($conn, $roomTarget, $adminId, $afterId, 60);

        echo json_encode([
            'success' => true,
            'room_id' => $roomTarget['room_id'],
            'total_online' => $adminsData['total_online'],
            'admins' => $adminsData['admins'],
            'messages' => $messages
        ], JSON_UNESCAPED_UNICODE);
        exit;

    case 'send_message':
        $roomId = (string)($_POST['room_id'] ?? 'general');
        $roomTarget = resolveRoomTarget($roomId, $adminId);
        $messageText = trim((string)($_POST['message'] ?? ''));

        // Konfigurasi upload file konsisten dengan consultation desk
        $allowedChatFiles = [
            'jpg'  => ['image/jpeg'], 'jpeg' => ['image/jpeg'],
            'png'  => ['image/png'],  'webp' => ['image/webp'], 'gif' => ['image/gif'],
            'mp4'  => ['video/mp4'],  'webm' => ['video/webm'], 'mov' => ['video/quicktime'],
            'pdf'  => ['application/pdf'],
            'doc'  => ['application/msword', 'application/x-ole-storage'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
            'xls'  => ['application/vnd.ms-excel', 'application/x-ole-storage'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
            'ppt'  => ['application/vnd.ms-powerpoint', 'application/x-ole-storage'],
            'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
            'txt'  => ['text/plain'],
            'csv'  => ['text/plain', 'text/csv', 'application/vnd.ms-excel']
        ];

        $maxChatFileSize = 20 * 1024 * 1024; // 20 MB per file
        $maxChatFiles = 5;
        $uploadedChatFiles = [];
        $uploadError = '';

        if (!empty($_FILES['chat_files']) && is_array($_FILES['chat_files']['name'])) {
            $fileCount = count($_FILES['chat_files']['name']);
            if ($fileCount > $maxChatFiles) {
                $uploadError = 'Maksimal 5 file lampiran dalam satu pesan.';
            } else {
                $uploadDir = __DIR__ . '/uploads/chat_attachments';
                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                    $uploadError = 'Folder penyimpanan lampiran chat gagal dibuat di server.';
                } else {
                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    for ($i = 0; $i < $fileCount; $i++) {
                        $errorCode = (int)($_FILES['chat_files']['error'][$i] ?? UPLOAD_ERR_NO_FILE);
                        if ($errorCode === UPLOAD_ERR_NO_FILE) {
                            continue;
                        }
                        if ($errorCode !== UPLOAD_ERR_OK) {
                            $uploadError = 'Salah satu file gagal diunggah ke server.';
                            break;
                        }

                        $tmpName = (string)($_FILES['chat_files']['tmp_name'][$i] ?? '');
                        $originalName = basename((string)($_FILES['chat_files']['name'][$i] ?? 'file'));
                        $fileSize = (int)($_FILES['chat_files']['size'][$i] ?? 0);
                        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                        $detectedMime = ($tmpName !== '' && is_uploaded_file($tmpName)) ? $finfo->file($tmpName) : '';

                        if (!isset($allowedChatFiles[$extension]) ||
                            !in_array($detectedMime, $allowedChatFiles[$extension], true) ||
                            $fileSize <= 0 || $fileSize > $maxChatFileSize) {
                            $uploadError = 'File "' . htmlspecialchars($originalName, ENT_QUOTES, 'UTF-8') . '" tidak didukung atau melebihi batas 20 MB.';
                            break;
                        }

                        $storedName = 'agency-chat-' . $adminId . '-' . bin2hex(random_bytes(12)) . '.' . $extension;
                        if (!move_uploaded_file($tmpName, $uploadDir . '/' . $storedName)) {
                            $uploadError = 'File "' . htmlspecialchars($originalName, ENT_QUOTES, 'UTF-8') . '" gagal disimpan ke disk.';
                            break;
                        }

                        $relativePath = 'uploads/chat_attachments/' . $storedName;
                        $uploadedChatFiles[] = [
                            'original_name' => $originalName,
                            'path' => $relativePath,
                            'mime' => $detectedMime
                        ];
                    }
                }
            }
        }

        if ($uploadError !== '') {
            foreach ($uploadedChatFiles as $uFile) {
                $storedPath = __DIR__ . '/' . $uFile['path'];
                if (is_file($storedPath)) @unlink($storedPath);
            }
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => $uploadError]);
            exit;
        }

        if ($messageText === '' && empty($uploadedChatFiles)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ketik pesan atau pilih minimal satu file lampiran untuk dikirim.']);
            exit;
        }

        // Susun URL publik untuk lampiran file
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $scheme = $isHttps ? 'https' : 'http';
        $host = preg_replace('/[^a-zA-Z0-9.:-]/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $basePath = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/admin_dashboard.php')));
        $basePath = ($basePath === '/' || $basePath === '.') ? '' : rtrim($basePath, '/');
        $messageToSave = $messageText;

        foreach ($uploadedChatFiles as $uFile) {
            $publicUrl = $scheme . '://' . $host . $basePath . '/' . $uFile['path'];
            $messageToSave .= ($messageToSave !== '' ? "\n\n" : '') .
                '[Lampiran] ' . $uFile['original_name'] .
                "\nURL: " . $publicUrl;
        }

        $useAgencyTable = hasAgencyChatTable($conn);
        $insertedRow = null;

        if ($conn instanceof SupabaseRestAdapter && function_exists('supabaseRestRequest')) {
            // Direct REST Post ke Supabase
            if ($useAgencyTable) {
                $payload = [
                    'room_type' => $roomTarget['room_type'],
                    'room_id' => $roomTarget['room_id'],
                    'sender_id' => $adminId,
                    'sender_name' => $adminName,
                    'recipient_id' => $roomTarget['partner_id'] ?? null,
                    'message' => $messageToSave
                ];
                $res = supabaseRestRequest('agency_chat_messages', 'POST', $payload);
                if ($res['success'] && is_array($res['data']) && !empty($res['data'][0])) {
                    $insertedRow = $res['data'][0];
                }
            }

            if (!$insertedRow) {
                // Fallback ke consultation_messages di Supabase jika tabel khusus belum merespons
                $cId = (int)$roomTarget['consultation_id'];
                $senderType = ($roomTarget['room_type'] === 'team') ? 'agency_team' : 'agency_direct';
                $payload = [
                    'consultation_id' => $cId,
                    'sender_type' => $senderType,
                    'sender_id' => $adminId,
                    'sender_name' => $adminName,
                    'message' => $messageToSave
                ];
                $res = supabaseRestRequest('consultation_messages', 'POST', $payload);
                if ($res['success'] && is_array($res['data']) && !empty($res['data'][0])) {
                    $insertedRow = $res['data'][0];
                }
            }
        } else {
            // MySQL / PostgreSQL PDO
            if ($useAgencyTable) {
                $stmt = $conn->prepare("INSERT INTO agency_chat_messages (room_type, room_id, sender_id, sender_name, recipient_id, message) VALUES (?, ?, ?, ?, ?, ?)");
                if ($stmt) {
                    $rType = $roomTarget['room_type'];
                    $rId = $roomTarget['room_id'];
                    $partnerId = $roomTarget['partner_id'] ?? null;
                    $stmt->bind_param("ssisis", $rType, $rId, $adminId, $adminName, $partnerId, $messageToSave);
                    if ($stmt->execute()) {
                        $newId = $stmt->insert_id ?: 0;
                        $insertedRow = [
                            'id' => $newId,
                            'room_id' => $rId,
                            'sender_id' => $adminId,
                            'sender_name' => $adminName,
                            'message' => $messageToSave,
                            'created_at' => (new DateTime('now', new DateTimeZone('Asia/Makassar')))->format('Y-m-d H:i:s')
                        ];
                    }
                    $stmt->close();
                }
            }

            if (!$insertedRow) {
                $cId = (int)$roomTarget['consultation_id'];
                $senderType = ($roomTarget['room_type'] === 'team') ? 'agency_team' : 'agency_direct';
                $stmt = $conn->prepare("INSERT INTO consultation_messages (consultation_id, sender_type, sender_id, sender_name, message) VALUES (?, ?, ?, ?, ?)");
                if ($stmt) {
                    $stmt->bind_param("isiss", $cId, $senderType, $adminId, $adminName, $messageToSave);
                    if ($stmt->execute()) {
                        $newId = $stmt->insert_id ?: 0;
                        $insertedRow = [
                            'id' => $newId,
                            'consultation_id' => $cId,
                            'sender_id' => $adminId,
                            'sender_name' => $adminName,
                            'message' => $messageToSave,
                            'created_at' => (new DateTime('now', new DateTimeZone('Asia/Makassar')))->format('Y-m-d H:i:s')
                        ];
                    }
                    $stmt->close();
                }
            }
        }

        if (!$insertedRow) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Gagal menyimpan pesan ke database.']);
            exit;
        }

        $formattedItem = formatMessageItem($insertedRow, $adminId);

        echo json_encode([
            'success' => true,
            'message' => 'Pesan terkirim!',
            'data' => $formattedItem
        ], JSON_UNESCAPED_UNICODE);
        exit;

    case 'heartbeat':
        $adminsData = fetchAllAdmins($conn, $adminId);
        echo json_encode([
            'success' => true,
            'total_online' => $adminsData['total_online'],
            'admins' => $adminsData['admins']
        ], JSON_UNESCAPED_UNICODE);
        exit;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Aksi tidak dikenal.']);
        exit;
}
