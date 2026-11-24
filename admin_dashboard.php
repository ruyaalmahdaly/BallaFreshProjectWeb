<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Makassar');

require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/midtrans_config.php';
// Token keamanan untuk proses logout admin.
if (empty($_SESSION['admin_logout_csrf'])) {
    $_SESSION['admin_logout_csrf'] = bin2hex(random_bytes(32));
}

/*
 * Balla' Fresh Admin Dashboard
 * Requires: admins, users, user_addresses (address table is optional).
 * Protect every admin page with the admin session.
 */
if (empty($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$adminId = (int) $_SESSION['admin_id'];
$adminName = (string) ($_SESSION['admin_name'] ?? 'Administrator');
$adminIsOwner = (int) ($_SESSION['admin_is_owner'] ?? 0);
$adminManageMessage = '';
$adminManageMessageType = 'success';

$adminEmail = '';

$page = (string) ($_GET['page'] ?? 'dashboard');
$allowedPages = ['dashboard', 'orders', 'customers', 'businesses', 'consultations', 'activity', 'agency', 'articles', 'admins', 'settings'];
if (!in_array($page, $allowedPages, true)) {
    $page = 'dashboard';
}
$pageTitles = [
    'dashboard' => 'Dashboard',
    'orders' => 'Pengelolaan Pesanan',
    'customers' => 'Data Pelanggan',
    'businesses' => 'Data Usaha Mitra',
    'consultations' => 'Konsultasi & Room Chat',
    'activity' => 'Aktivitas Terbaru',
    'agency' => 'Operasional Agency',
    'articles' => 'Kelola Artikel & Edukasi',
    'admins' => 'Kelola Akses Administrator',
    'settings' => 'Pengaturan & Kebijakan'
];
$pageTitle = $pageTitles[$page];

// CSRF token untuk aksi pengelolaan pesanan.
if (empty($_SESSION['order_status_csrf'])) {
    $_SESSION['order_status_csrf'] = bin2hex(random_bytes(32));
}

// CSRF token untuk pengelolaan akun admin.
if (empty($_SESSION['admin_manage_csrf'])) {
    $_SESSION['admin_manage_csrf'] = bin2hex(random_bytes(32));
}

$orderActionMessage = '';
$orderActionType = 'success';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'update_order_status') {
    $postedToken = (string) ($_POST['csrf'] ?? '');
    $orderId = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
    $newStatus = (string) ($_POST['status'] ?? '');
    $allowedStatuses = ['pending', 'processing', 'completed', 'cancelled'];

    if (!hash_equals($_SESSION['order_status_csrf'], $postedToken)) {
        $orderActionMessage = 'Permintaan tidak valid. Muat ulang halaman dan coba lagi.';
        $orderActionType = 'error';
    } elseif (!$orderId || !in_array($newStatus, $allowedStatuses, true)) {
        $orderActionMessage = 'Data perubahan status pesanan tidak valid.';
        $orderActionType = 'error';
    } else {
        $updateOrder = $conn->prepare('UPDATE orders SET status = ? WHERE id = ?');
        if ($updateOrder) {
            $updateOrder->bind_param('si', $newStatus, $orderId);
            if ($updateOrder->execute() && $updateOrder->affected_rows >= 0) {
                $orderActionMessage = 'Status pesanan berhasil diperbarui.';
            } else {
                $orderActionMessage = 'Status pesanan gagal diperbarui.';
                $orderActionType = 'error';
            }
            $updateOrder->close();
        } else {
            $orderActionMessage = 'Fitur perubahan status belum dapat dijalankan. Periksa struktur tabel orders.';
            $orderActionType = 'error';
        }
    }
}


$stmt = $conn->prepare(
    'SELECT name, email, is_approved, is_owner
     FROM admins
     WHERE id = ?
     LIMIT 1'
);
if ($stmt) {
    $stmt->bind_param('i', $adminId);
    $stmt->execute();
    $adminRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$adminRow || (int) $adminRow['is_approved'] !== 1) {
        unset(
            $_SESSION['admin_id'],
            $_SESSION['admin_name'],
            $_SESSION['admin_is_owner'],
            $_SESSION['admin_otp_verified']
        );
        header('Location: admin_login.php');
        exit;
    }
    $adminName = (string) $adminRow['name'];
    $adminEmail = (string) $adminRow['email'];
    $_SESSION['admin_is_owner'] = (int) $adminRow['is_owner'];
    $adminIsOwner = (int) $adminRow['is_owner'];
}

// -------------------------------------------------------------------------
// REAL-TIME ONLINE TRACKING: Perbarui timestamp aktif admin yang sedang login
// -------------------------------------------------------------------------
try {
    $nowMakassar = (new DateTime('now', new DateTimeZone('Asia/Makassar')))->format('Y-m-d H:i:s');
    $upAct = $conn->prepare("UPDATE admins SET last_active_at = ? WHERE id = ?");
    if ($upAct) {
        $upAct->bind_param("si", $nowMakassar, $adminId);
        $upAct->execute();
        $upAct->close();
    }
} catch (Throwable $e) {}

// -------------------------------------------------------------------------
// HELPER: Format status online / offline real-time administrator
// -------------------------------------------------------------------------
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

        // Offline dalam 1 jam terakhir
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

        // Offline dalam 24 jam terakhir
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

        // Offline 1 hari atau lebih
        $days = (int)round($diffSec / 86400);
        return [
            'is_online' => false,
            'status_type' => 'offline',
            'badge_class' => 'offline',
            'label' => "Offline {$days} hari lalu",
            'time_text' => "Terakhir aktif pada " . $act->format('d M Y, H:i') . " WITA",
            'dot_color' => '#94a3b8'
        ];
    }
}

// -------------------------------------------------------------------------
// PROSES AKSI PENGELOLAAN ADMINISTRATOR (TAMBAH, STATUS, HAPUS)
// -------------------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])) {
    $postedAction = (string)$_POST['action'];

    if (in_array($postedAction, ['create_admin', 'toggle_admin_status', 'delete_admin'], true)) {
        $csrfToken = (string)($_POST['csrf'] ?? '');

        if (!hash_equals($_SESSION['admin_manage_csrf'] ?? '', $csrfToken)) {
            $adminManageMessage = 'Sesi formulir berakhir atau token keamanan tidak valid. Silakan coba lagi.';
            $adminManageMessageType = 'error';
        } elseif ($adminIsOwner !== 1) {
            $adminManageMessage = 'Akses ditolak: Hanya Admin Utama (Owner) yang memiliki hak mengelola data administrator.';
            $adminManageMessageType = 'error';
        } else {
            // 1. TAMBAH ADMINISTRATOR BARU
            if ($postedAction === 'create_admin') {
                $newAdminName = trim((string)($_POST['name'] ?? ''));
                $newAdminEmail = strtolower(trim((string)($_POST['email'] ?? '')));
                $newAdminPhone = trim((string)($_POST['phone'] ?? ''));
                $newAdminRole = trim((string)($_POST['role'] ?? 'admin'));
                $newAdminPass = (string)($_POST['password'] ?? '');
                $newAdminConfirm = (string)($_POST['confirm_password'] ?? '');

                $isOwnerVal = ($newAdminRole === 'owner') ? 1 : 0;

                if ($newAdminName === '' || strlen($newAdminName) > 100) {
                    $adminManageMessage = 'Mohon masukkan nama lengkap administrator (maksimal 100 karakter).';
                    $adminManageMessageType = 'error';
                } elseif (!filter_var($newAdminEmail, FILTER_VALIDATE_EMAIL) || strlen($newAdminEmail) > 150) {
                    $adminManageMessage = 'Format alamat email tidak valid.';
                    $adminManageMessageType = 'error';
                } elseif (strlen($newAdminPass) < 8) {
                    $adminManageMessage = 'Password akses minimal 8 karakter demi keamanan sistem.';
                    $adminManageMessageType = 'error';
                } elseif ($newAdminPass !== $newAdminConfirm) {
                    $adminManageMessage = 'Password dan konfirmasi password tidak cocok.';
                    $adminManageMessageType = 'error';
                } else {
                    $chk = $conn->prepare("SELECT id FROM admins WHERE email = ? LIMIT 1");
                    $emailTaken = false;
                    if ($chk) {
                        $chk->bind_param("s", $newAdminEmail);
                        $chk->execute();
                        if ($chk->get_result()->fetch_assoc()) {
                            $emailTaken = true;
                        }
                        $chk->close();
                    }

                    if ($emailTaken) {
                        $adminManageMessage = 'Alamat email sudah terdaftar untuk administrator lain.';
                        $adminManageMessageType = 'error';
                    } else {
                        $hashedPass = password_hash($newAdminPass, PASSWORD_DEFAULT);
                        $nowDate = (new DateTime('now', new DateTimeZone('Asia/Makassar')))->format('Y-m-d H:i:s');

                        $saved = false;
                        try {
                            $inStmt = $conn->prepare("
                                INSERT INTO admins (name, email, password, is_approved, is_owner, role, phone, created_at, last_active_at)
                                VALUES (?, ?, ?, 1, ?, ?, ?, ?, NULL)
                            ");
                            if ($inStmt) {
                                $inStmt->bind_param("sssisss", $newAdminName, $newAdminEmail, $hashedPass, $isOwnerVal, $newAdminRole, $newAdminPhone, $nowDate);
                                $saved = $inStmt->execute();
                                $inStmt->close();
                            }
                        } catch (Throwable $inErr) {}

                        // Fallback jika kolom role / phone belum ditambahkan
                        if (!$saved) {
                            try {
                                $inStmt2 = $conn->prepare("
                                    INSERT INTO admins (name, email, password, is_approved, is_owner, created_at)
                                    VALUES (?, ?, ?, 1, ?, ?)
                                ");
                                if ($inStmt2) {
                                    $inStmt2->bind_param("sssis", $newAdminName, $newAdminEmail, $hashedPass, $isOwnerVal, $nowDate);
                                    $saved = $inStmt2->execute();
                                    $inStmt2->close();
                                }
                            } catch (Throwable $inErr2) {}
                        }

                        if ($saved) {
                            $adminManageMessage = "Administrator baru '{$newAdminName}' berhasil didaftarkan dan dapat langsung login.";
                            $adminManageMessageType = 'success';
                        } else {
                            $adminManageMessage = 'Gagal menyimpan administrator baru: ' . ($conn->error ?? 'Koneksi database bermasalah.');
                            $adminManageMessageType = 'error';
                        }
                    }
                }
            }

            // 2. TOGGLE STATUS AKTIF / SUSPEND ADMINISTRATOR
            elseif ($postedAction === 'toggle_admin_status') {
                $targetId = filter_var($_POST['admin_id'] ?? null, FILTER_VALIDATE_INT);
                $newStatus = filter_var($_POST['new_status'] ?? 0, FILTER_VALIDATE_INT);

                if (!$targetId) {
                    $adminManageMessage = 'ID administrator tidak valid.';
                    $adminManageMessageType = 'error';
                } elseif ($targetId === $adminId) {
                    $adminManageMessage = 'Anda tidak dapat menonaktifkan akun Anda sendiri yang sedang aktif.';
                    $adminManageMessageType = 'error';
                } else {
                    $upSt = $conn->prepare("UPDATE admins SET is_approved = ? WHERE id = ?");
                    if ($upSt) {
                        $upSt->bind_param("ii", $newStatus, $targetId);
                        if ($upSt->execute()) {
                            $statusText = $newStatus === 1 ? 'diaktifkan kembali' : 'ditangguhkan (nonaktif)';
                            $adminManageMessage = "Hak akses administrator ID #{$targetId} berhasil {$statusText}.";
                            $adminManageMessageType = 'success';
                        } else {
                            $adminManageMessage = 'Gagal memperbarui status administrator: ' . $conn->error;
                            $adminManageMessageType = 'error';
                        }
                        $upSt->close();
                    }
                }
            }

            // 3. HAPUS ADMINISTRATOR
            elseif ($postedAction === 'delete_admin') {
                $targetId = filter_var($_POST['admin_id'] ?? null, FILTER_VALIDATE_INT);

                if (!$targetId) {
                    $adminManageMessage = 'ID administrator tidak valid.';
                    $adminManageMessageType = 'error';
                } elseif ($targetId === $adminId) {
                    $adminManageMessage = 'Anda tidak dapat menghapus akun Anda sendiri.';
                    $adminManageMessageType = 'error';
                } else {
                    $delSt = $conn->prepare("DELETE FROM admins WHERE id = ?");
                    if ($delSt) {
                        $delSt->bind_param("i", $targetId);
                        if ($delSt->execute()) {
                            $adminManageMessage = "Akun administrator ID #{$targetId} berhasil dihapus dari sistem.";
                            $adminManageMessageType = 'success';
                        } else {
                            $adminManageMessage = 'Gagal menghapus administrator: ' . $conn->error;
                            $adminManageMessageType = 'error';
                        }
                        $delSt->close();
                    }
                }
            }
        }
    }
}

if (!function_exists('e')) {
    function e(mixed $value): string {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('scalarQuery')) {
    function scalarQuery($conn, string $sql): int {
        $result = $conn->query($sql);
        if (!$result) {
            return 0;
        }
        $row = $result->fetch_row();
        return (int) ($row[0] ?? 0);
    }
}

if (!function_exists('tableColumns')) {
    function tableColumns($conn, string $table): array {
        $allowed = ['users', 'user_addresses', 'agency_site_content', 'agency_services', 'services', 'categories', 'service_reviews'];
        if (!in_array($table, $allowed, true)) return [];
        $result = $conn->query("SHOW COLUMNS FROM `{$table}`");
        if (!$result) return [];
        $columns = [];
        while ($row = $result->fetch_assoc()) $columns[] = (string) $row['Field'];
        $result->free();
        return $columns;
    }
}

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

if (!function_exists('autoLinkUrls')) {
    function autoLinkUrls(string $escapedHtml): string {
        $pattern = '~(https?://[^\s<]+|www\.[^\s<]+)~i';
        return preg_replace_callback($pattern, function($match) {
            $url = $match[1];
            $href = (stripos($url, 'http') === 0) ? $url : 'https://' . $url;
            return '<a href="' . $href . '" target="_blank" rel="noopener noreferrer" style="color:inherit;text-decoration:underline;font-weight:700;word-break:break-all">' . $url . '</a>';
        }, $escapedHtml);
    }
}

if (!function_exists('sanitizeArticleHtml')) {
    function sanitizeArticleHtml(string $html): string {
        if (trim($html) === '') return '';

        // 1. Bersihkan tag skrip dan elemen berbahaya
        $html = preg_replace('#<(script|iframe|object|embed|form|input|button|textarea|select|style|link|applet|meta)[^>]*?>.*?</\1>#si', '', $html);
        $html = preg_replace('#<(script|iframe|object|embed|form|input|button|textarea|select|style|link|applet|meta)[^>]*?>#si', '', $html);

        // 2. Bersihkan atribut event handler inline (onclick, onerror, onload, dll.)
        $html = preg_replace('#\s+on[a-zA-Z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html);

        // 3. Bersihkan protokol palsu (javascript:, vbscript:)
        $html = preg_replace('#\b(href|src)\s*=\s*["\']\s*(javascript|vbscript):[^"\']*["\']#i', '$1="#"', $html);

        // 4. Izinkan hanya tag pemformatan yang aman
        $allowedTags = '<h1><h2><h3><h4><h5><h6><p><br><strong><b><em><i><u><s><strike><del><ul><ol><li><blockquote><a><span><div><img><table><thead><tbody><tr><th><td><hr>';
        $clean = strip_tags($html, $allowedTags);

        // 5. Filter atribut pada tag yang diizinkan (style, href, src, alt, title)
        $clean = preg_replace_callback('#<([a-z1-6]+)([^>]*)>#i', function($matches) {
            $tag = strtolower($matches[1]);
            $attrs = $matches[2];

            if (trim($attrs) === '') {
                return "<{$tag}>";
            }

            $safeAttrs = [];

            // Atribut style: hanya izinkan properti pemformatan teks yang aman
            if (preg_match('#\bstyle\s*=\s*["\']([^"\']*)["\']#i', $attrs, $mStyle)) {
                $styleVal = $mStyle[1];
                $declarations = explode(';', $styleVal);
                $safeDecls = [];
                $allowedProps = ['font-size', 'text-align', 'color', 'background-color', 'font-weight', 'text-decoration', 'line-height', 'margin', 'padding'];
                foreach ($declarations as $decl) {
                    $decl = trim($decl);
                    if ($decl === '') continue;
                    $parts = explode(':', $decl, 2);
                    if (count($parts) === 2) {
                        $prop = strtolower(trim($parts[0]));
                        $val = trim($parts[1]);
                        if (in_array($prop, $allowedProps, true)) {
                            if (!preg_match('#(expression|url|javascript|behavior|import)#i', $val)) {
                                $safeDecls[] = "{$prop}: {$val}";
                            }
                        }
                    }
                }
                if (!empty($safeDecls)) {
                    $safeAttrs[] = 'style="' . htmlspecialchars(implode('; ', $safeDecls), ENT_QUOTES, 'UTF-8') . '"';
                }
            }

            // Atribut tautan pada <a>
            if ($tag === 'a') {
                if (preg_match('#\bhref\s*=\s*["\']([^"\']*)["\']#i', $attrs, $mHref)) {
                    $href = trim($mHref[1]);
                    if (preg_match('#^(https?://|mailto:|tel:|#|/)#i', $href)) {
                        $safeAttrs[] = 'href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '"';
                        $safeAttrs[] = 'target="_blank"';
                        $safeAttrs[] = 'rel="noopener noreferrer"';
                    }
                }
                if (preg_match('#\btitle\s*=\s*["\']([^"\']*)["\']#i', $attrs, $mTitle)) {
                    $safeAttrs[] = 'title="' . htmlspecialchars($mTitle[1], ENT_QUOTES, 'UTF-8') . '"';
                }
            }

            // Atribut gambar pada <img>
            if ($tag === 'img') {
                if (preg_match('#\bsrc\s*=\s*["\']([^"\']*)["\']#i', $attrs, $mSrc)) {
                    $src = trim($mSrc[1]);
                    if (preg_match('#^(https?://|uploads/|/)#i', $src)) {
                        $safeAttrs[] = 'src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '"';
                    }
                }
                if (preg_match('#\balt\s*=\s*["\']([^"\']*)["\']#i', $attrs, $mAlt)) {
                    $safeAttrs[] = 'alt="' . htmlspecialchars($mAlt[1], ENT_QUOTES, 'UTF-8') . '"';
                }
                $safeAttrs[] = 'loading="lazy"';
            }

            $attrString = !empty($safeAttrs) ? ' ' . implode(' ', $safeAttrs) : '';
            return "<{$tag}{$attrString}>";
        }, $clean);

        return $clean;
    }
}

$customerCount = scalarQuery($conn, 'SELECT COUNT(*) FROM users');
$adminCount = scalarQuery($conn, 'SELECT COUNT(*) FROM admins');

$addressTableExists = false;
$tableCheck = $conn->query("SHOW TABLES LIKE 'user_addresses'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    $addressTableExists = true;
}
$addressCount = $addressTableExists
    ? scalarQuery($conn, 'SELECT COUNT(DISTINCT user_id) FROM user_addresses')
    : 0;

$pendingCount = null;
$pendingTableCheck = $conn->query("SHOW TABLES LIKE 'registration_pending'");
if ($pendingTableCheck && $pendingTableCheck->num_rows > 0) {
    $pendingCount = scalarQuery($conn, 'SELECT COUNT(*) FROM registration_pending');
}


$search = trim((string) ($_GET['q'] ?? ''));
$customers = [];
$searchError = '';
$userColumns = tableColumns($conn, 'users');
$addressColumns = $addressTableExists ? tableColumns($conn, 'user_addresses') : [];
$phoneColumn = '';
foreach (['phone', 'phone_number', 'mobile', 'no_hp', 'telephone'] as $candidate) {
    if (in_array($candidate, $userColumns, true)) { $phoneColumn = $candidate; break; }
}
$createdColumn = in_array('created_at', $userColumns, true) ? 'created_at' : '';
$phoneSelect = $phoneColumn !== '' ? "u.`{$phoneColumn}` AS phone" : "NULL AS phone";
$createdSelect = $createdColumn !== '' ? "u.`{$createdColumn}` AS registered_at" : "NULL AS registered_at";

$addressSelects = [];
foreach (['address_details', 'street', 'address', 'subdistrict', 'district', 'city', 'province', 'postal_code'] as $column) {
    if ($addressTableExists && in_array($column, $addressColumns, true)) {
        $addressSelects[] = "MAX(a.`{$column}`) AS `{$column}`";
    } else {
        $addressSelects[] = "NULL AS `{$column}`";
    }
}
$extraUserCols = [
    'profile_image' => 'profile_image',
    'birth_date' => 'birth_date',
    'business_name' => 'business_name',
    'business_type' => 'business_type',
    'business_role' => 'business_role',
    'social_media' => 'social_media',
    'website_url' => 'website_url',
    'business_address' => 'business_address',
    'business_description' => 'business_description',
    'service_preferences' => 'service_preferences'
];
$extraUserSelects = [];
foreach ($extraUserCols as $k => $alias) {
    if (in_array($k, $userColumns, true)) {
        $extraUserSelects[] = "u.`{$k}` AS `{$alias}`";
    } else {
        $extraUserSelects[] = "NULL AS `{$alias}`";
    }
}
$userAddressCol = in_array('address', $userColumns, true) ? "u.`address` AS user_direct_address" : "NULL AS user_direct_address";

$customerSql = "SELECT u.id, u.name, u.email, {$phoneSelect}, {$createdSelect}, {$userAddressCol}, " .
    implode(', ', $extraUserSelects) . ", " .
    implode(', ', $addressSelects) . " FROM users u";
if ($addressTableExists) $customerSql .= " LEFT JOIN user_addresses a ON a.user_id = u.id";
$searchWhere = '';
if ($search !== '') {
    $searchWhere = " WHERE (u.name LIKE ? OR u.email LIKE ?" .
        ($phoneColumn !== '' ? " OR u.`{$phoneColumn}` LIKE ?" : "") . ")";
    $customerSql .= $searchWhere;
}
$customerSql .= " GROUP BY u.id ORDER BY u.id DESC";

$customerStmt = $conn->prepare($customerSql);
if ($customerStmt) {
    if ($search !== '') {
        $like = '%' . $search . '%';
        if ($phoneColumn !== '') $customerStmt->bind_param('sss', $like, $like, $like);
        else $customerStmt->bind_param('ss', $like, $like);
    }
    $customerStmt->execute();
    $customerResult = $customerStmt->get_result();
    while ($row = $customerResult->fetch_assoc()) $customers[] = $row;
    $customerStmt->close();
} else {
    $searchError = 'Data pelanggan belum dapat dimuat. Periksa struktur tabel users dan user_addresses.';
}

// Ekspor seluruh hasil pencarian pelanggan ke CSV yang kompatibel dengan Excel/Google Sheets.
if ($page === 'customers' && ($_GET['export'] ?? '') === 'customers') {
    $filename = 'data-pelanggan-' . date('Ymd-His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['ID', 'Nama Lengkap', 'Email', 'Nomor HP', 'Tanggal Lahir', 'Nama Usaha', 'Bidang Usaha', 'Jabatan/Posisi', 'Detail Alamat', 'RT/RW', 'Kelurahan/Kecamatan', 'Kota', 'Provinsi', 'Kode Pos', 'Tanggal Daftar']);
    foreach ($customers as $customer) {
        $address = trim(implode(', ', array_filter([
            $customer['address_details'] ?? '', $customer['street'] ?? '', $customer['address'] ?? '', $customer['user_direct_address'] ?? ''
        ], static fn($value) => trim((string) $value) !== '')));
        fputcsv($output, [
            $customer['id'], $customer['name'], $customer['email'], $customer['phone'] ?? '',
            $customer['birth_date'] ?? '', $customer['business_name'] ?? '', $customer['business_type'] ?? '', $customer['business_role'] ?? '',
            $address, $customer['rt_rw'] ?? '', $customer['subdistrict'] ?? ($customer['district'] ?? ''),
            $customer['city'] ?? '', $customer['province'] ?? '',
            $customer['postal_code'] ?? '', $customer['registered_at'] ?? ''
        ]);
    }
    fclose($output);
    exit;
}

$latestUsers = [];
$stmt = $conn->prepare('SELECT id, name, email FROM users ORDER BY id DESC LIMIT 5');
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $latestUsers[] = $row;
    }
    $stmt->close();
}


// Parameter filter periode dan status untuk pengelolaan pesanan
$statusFilter = strtolower(trim((string)($_GET['status'] ?? 'all')));
$periodFilter = strtolower(trim((string)($_GET['period'] ?? 'all')));

$allowedStatusFilters = ['all', 'pending', 'processing', 'completed', 'cancelled', 'paid', 'unpaid'];
if (!in_array($statusFilter, $allowedStatusFilters, true)) {
    $statusFilter = 'all';
}

$allowedPeriodFilters = ['all', 'today', 'this_week', 'this_month', 'this_year'];
if (!in_array($periodFilter, $allowedPeriodFilters, true)) {
    $periodFilter = 'all';
}

// Hitung rentang tanggal periode dalam zona waktu Asia/Makassar
$nowMakassar = new DateTime('now', new DateTimeZone('Asia/Makassar'));
$periodStart = null;
$periodEnd = null;

if ($periodFilter === 'today') {
    $periodStart = (clone $nowMakassar)->format('Y-m-d 00:00:00');
    $periodEnd   = (clone $nowMakassar)->format('Y-m-d 23:59:59');
} elseif ($periodFilter === 'this_week') {
    $periodStart = (clone $nowMakassar)->modify('monday this week')->setTime(0, 0, 0)->format('Y-m-d H:i:s');
    $periodEnd   = (clone $nowMakassar)->modify('sunday this week')->setTime(23, 59, 59)->format('Y-m-d H:i:s');
} elseif ($periodFilter === 'this_month') {
    $periodStart = (clone $nowMakassar)->format('Y-m-01 00:00:00');
    $periodEnd   = (clone $nowMakassar)->format('Y-m-t 23:59:59');
} elseif ($periodFilter === 'this_year') {
    $periodStart = (clone $nowMakassar)->format('Y-01-01 00:00:00');
    $periodEnd   = (clone $nowMakassar)->format('Y-12-31 23:59:59');
}

// Ringkasan statistik pesanan (mengikuti periode yang dipilih jika aktif)
$orderCounts = [
    'all' => 0,
    'pending' => 0,
    'processing' => 0,
    'completed' => 0,
    'cancelled' => 0
];

if ($periodStart !== null && $periodEnd !== null) {
    $statStmt = $conn->prepare("SELECT status, COUNT(*) AS total FROM orders WHERE created_at BETWEEN ? AND ? GROUP BY status");
    if ($statStmt) {
        $statStmt->bind_param("ss", $periodStart, $periodEnd);
        $statStmt->execute();
        $statRes = $statStmt->get_result();
        while ($stat = $statRes->fetch_assoc()) {
            $statusKey = (string) $stat['status'];
            if (array_key_exists($statusKey, $orderCounts)) {
                $orderCounts[$statusKey] = (int) $stat['total'];
            }
            $orderCounts['all'] += (int) $stat['total'];
        }
        $statStmt->close();
    }
} else {
    $orderStatsResult = $conn->query("SELECT status, COUNT(*) AS total FROM orders GROUP BY status");
    if ($orderStatsResult) {
        while ($stat = $orderStatsResult->fetch_assoc()) {
            $statusKey = (string) $stat['status'];
            if (array_key_exists($statusKey, $orderCounts)) {
                $orderCounts[$statusKey] = (int) $stat['total'];
            }
            $orderCounts['all'] += (int) $stat['total'];
        }
        $orderStatsResult->free();
    }
}

// Susun Query Daftar Pesanan dengan Filter Status & Periode
$whereClauses = [];
$bindTypes = "";
$bindValues = [];

if ($statusFilter !== 'all') {
    if ($statusFilter === 'paid') {
        $whereClauses[] = "o.payment_status IN ('settlement', 'capture', 'lunas')";
    } elseif ($statusFilter === 'unpaid') {
        $whereClauses[] = "(o.payment_status IN ('menunggu_pembayaran', 'pending') OR o.payment_status IS NULL)";
    } elseif ($statusFilter === 'pending') {
        $whereClauses[] = "(o.status = 'pending' OR o.payment_status IN ('menunggu_pembayaran', 'pending'))";
    } elseif (in_array($statusFilter, ['processing', 'completed', 'cancelled'], true)) {
        $whereClauses[] = "o.status = ?";
        $bindTypes .= "s";
        $bindValues[] = $statusFilter;
    }
}

if ($periodStart !== null && $periodEnd !== null) {
    $whereClauses[] = "o.created_at BETWEEN ? AND ?";
    $bindTypes .= "ss";
    $bindValues[] = $periodStart;
    $bindValues[] = $periodEnd;
}

$whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

$orders = [];
$orderQuery = "
    SELECT
        o.id,
        o.order_number,
        o.customer_id,
        o.total_amount,
        o.status,
        o.notes,
        o.payment_method,
        o.payment_type,
        o.snap_token,
        o.midtrans_transaction_id,
        o.transaction_status,
        o.payment_status,
        o.transaction_time,
        o.settlement_time,
        o.business_name,
        o.social_media,
        o.website_url,
        o.payment_code,
        o.biller_code,
        o.qr_url,
        o.deeplink_url,
        o.expiry_time,
        o.created_at,
        c.name AS customer_name,
        c.email AS customer_email,
        c.phone AS customer_phone,
        c.address AS customer_address,
        COALESCE(SUM(oi.quantity), 0) AS total_quantity,
        GROUP_CONCAT(
            CONCAT(oi.service_name, '::', oi.quantity, '::', oi.unit_price, '::', oi.subtotal)
            ORDER BY oi.id ASC SEPARATOR '||'
        ) AS ordered_services_detail
    FROM orders o
    INNER JOIN customers c ON c.id = o.customer_id
    LEFT JOIN order_items oi ON oi.order_id = o.id
    {$whereSql}
    GROUP BY
        o.id, o.order_number, o.customer_id, o.total_amount, o.status,
        o.notes, o.payment_method, o.payment_type, o.snap_token, o.midtrans_transaction_id,
        o.transaction_status, o.payment_status, o.transaction_time, o.settlement_time,
        o.business_name, o.social_media, o.website_url,
        o.payment_code, o.biller_code, o.qr_url, o.deeplink_url, o.expiry_time, o.created_at,
        c.name, c.email, c.phone, c.address
    ORDER BY o.created_at DESC, o.id DESC
    LIMIT 200
";

$orderLoadError = '';
if (!empty($bindValues)) {
    $stmtOrders = $conn->prepare($orderQuery);
    if ($stmtOrders) {
        $stmtOrders->bind_param($bindTypes, ...$bindValues);
        $stmtOrders->execute();
        $orderResult = $stmtOrders->get_result();
        while ($row = $orderResult->fetch_assoc()) {
            $orders[] = $row;
        }
        $stmtOrders->close();
    } else {
        $orderLoadError = 'Gagal memuat pesanan dengan filter: ' . $conn->error;
    }
} else {
    $orderResult = $conn->query($orderQuery);
    if ($orderResult) {
        while ($row = $orderResult->fetch_assoc()) {
            $orders[] = $row;
        }
        $orderResult->free();
    } else {
        $orderLoadError = 'Data pesanan belum dapat dimuat: ' . $conn->error;
    }
}

if (!function_exists('orderStatusLabel')) {
    function orderStatusLabel(string $status): string {
        return [
            'pending' => 'Menunggu',
            'processing' => 'Diproses',
            'completed' => 'Selesai',
            'cancelled' => 'Dibatalkan'
        ][$status] ?? ucfirst($status);
    }
}

if (!function_exists('orderStatusClass')) {
    function orderStatusClass(string $status): string {
        return [
            'pending' => 'pending',
            'processing' => 'processing',
            'completed' => 'completed',
            'cancelled' => 'cancelled'
        ][$status] ?? 'pending';
    }
}

if (!function_exists('paymentStatusBadge')) {
    function paymentStatusBadge(string $status): array {
        $st = strtolower(trim($status));
        if (in_array($st, ['settlement', 'capture', 'lunas'], true)) {
            return ['class' => 'paid', 'label' => 'Pembayaran Berhasil', 'icon' => 'fa-circle-check'];
        }
        if (in_array($st, ['expire', 'expired'], true)) {
            return ['class' => 'expired', 'label' => 'Kedaluwarsa', 'icon' => 'fa-triangle-exclamation'];
        }
        if (in_array($st, ['deny', 'cancel', 'cancelled', 'gagal'], true)) {
            return ['class' => 'failed', 'label' => 'Pembayaran Gagal', 'icon' => 'fa-circle-xmark'];
        }
        return ['class' => 'pending', 'label' => 'Menunggu Pembayaran', 'icon' => 'fa-clock'];
    }
}

if (!function_exists('getOrderDateGroupInfo')) {
    function getOrderDateGroupInfo(string $datetimeStr): array {
        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
        ];
        $ts = strtotime($datetimeStr);
        $ymd = date('Y-m-d', $ts);
        $todayYmd = date('Y-m-d');
        $yesterdayYmd = date('Y-m-d', strtotime('-1 day'));

        $d = date('j', $ts);
        $m = (int)date('n', $ts);
        $y = date('Y', $ts);
        $formattedDate = "{$d} " . strtoupper($months[$m] ?? '') . " {$y}";

        if ($ymd === $todayYmd) {
            return [
                'key' => $ymd,
                'title' => 'HARI INI · ' . $formattedDate,
                'type' => 'today'
            ];
        } elseif ($ymd === $yesterdayYmd) {
            return [
                'key' => $ymd,
                'title' => 'KEMARIN · ' . $formattedDate,
                'type' => 'yesterday'
            ];
        } else {
            return [
                'key' => $ymd,
                'title' => $formattedDate,
                'type' => 'past'
            ];
        }
    }
}

// Kelompokkan pesanan berdasarkan tanggal (Asia/Makassar)
$groupedOrders = [];
foreach ($orders as $order) {
    $grp = getOrderDateGroupInfo((string)$order['created_at']);
    $gKey = $grp['key'];
    if (!isset($groupedOrders[$gKey])) {
        $groupedOrders[$gKey] = [
            'key' => $gKey,
            'title' => $grp['title'],
            'type' => $grp['type'],
            'orders' => []
        ];
    }
    $groupedOrders[$gKey]['orders'][] = $order;
}


// CMS untuk mengelola konten website per-section dari dashboard admin.
// Pastikan tabel agency_site_content dan kolom-kolomnya lengkap.
$conn->query("CREATE TABLE IF NOT EXISTS agency_site_content (
    id INT NOT NULL PRIMARY KEY,
    hero_title VARCHAR(255) NOT NULL DEFAULT '',
    hero_description TEXT NULL,
    contact_email VARCHAR(190) NULL,
    contact_phone VARCHAR(50) NULL,
    hero_image VARCHAR(255) NULL,
    hero_tag VARCHAR(100) DEFAULT 'Digital Growth Partner',
    about_tag VARCHAR(100) DEFAULT 'Tentang Balla’ Fresh',
    about_title VARCHAR(255) DEFAULT 'Mendorong Kemandirian Petani & UMKM Hidroponik Rumahan',
    about_story TEXT NULL,
    about_problem TEXT NULL,
    about_solution TEXT NULL,
    about_image VARCHAR(255) NULL,
    cta_title VARCHAR(255) DEFAULT 'Buka Seluruh Potensi Usaha Hidroponik Anda Sekarang',
    cta_description TEXT NULL,
    cta_badge VARCHAR(100) DEFAULT 'Akses Eksklusif Mitra Balla’ Fresh',
    contact_address VARCHAR(255) DEFAULT 'Makassar, Sulawesi Selatan, Indonesia',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$siteCols = tableColumns($conn, 'agency_site_content');
$expectedSiteCols = [
    'hero_tag' => "VARCHAR(100) DEFAULT 'Digital Growth Partner'",
    'about_tag' => "VARCHAR(100) DEFAULT 'Tentang Balla’ Fresh'",
    'about_title' => "VARCHAR(255) DEFAULT 'Mendorong Kemandirian Petani & UMKM Hidroponik Rumahan'",
    'about_story' => "TEXT NULL",
    'about_problem' => "TEXT NULL",
    'about_solution' => "TEXT NULL",
    'about_image' => "VARCHAR(255) NULL",
    'cta_title' => "VARCHAR(255) DEFAULT 'Buka Seluruh Potensi Usaha Hidroponik Anda Sekarang'",
    'cta_description' => "TEXT NULL",
    'cta_badge' => "VARCHAR(100) DEFAULT 'Akses Eksklusif Mitra Balla’ Fresh'",
    'contact_address' => "VARCHAR(255) DEFAULT 'Makassar, Sulawesi Selatan, Indonesia'"
];
foreach ($expectedSiteCols as $col => $def) {
    if (!in_array($col, $siteCols, true)) {
        $conn->query("ALTER TABLE agency_site_content ADD COLUMN `{$col}` {$def}");
    }
}
$conn->query("INSERT IGNORE INTO agency_site_content (id, hero_title, hero_description) VALUES (1, 'Lebih banyak peluang, #DariRumahAja.', 'Balla’ Fresh membantu petani dan UMKM hidroponik rumahan di Makassar mengembangkan pemasaran dan penjualan melalui solusi digital yang sesuai dengan kebutuhan usahanya.')");

// Pastikan tabel struktur tim (4 orang: 1 Owner + 3 Member) tersedia
$conn->query("CREATE TABLE IF NOT EXISTS agency_team (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    role_type ENUM('owner', 'member') NOT NULL DEFAULT 'member',
    name VARCHAR(190) NOT NULL,
    position VARCHAR(190) NOT NULL,
    photo VARCHAR(255) NULL,
    display_order INT NOT NULL DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$teamCount = (int) scalarQuery($conn, "SELECT COUNT(*) FROM agency_team");
if ($teamCount === 0) {
    $conn->query("INSERT INTO agency_team (id, role_type, name, position, photo, display_order) VALUES
    (1, 'owner', 'Ru\'ya Shadiqa A', 'Owner & Agency Lead', 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=400&q=80', 1),
    (2, 'member', 'Andi Fadhlur Rahman', 'Lead Brand & Visual Designer', 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=400&q=80', 2),
    (3, 'member', 'Nur Sernovita Bunga', 'Lead Marketing & Social Growth', 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=400&q=80', 3),
    (4, 'member', 'Muh. Ilham Pratama', 'Lead Web & Operations Specialist', 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=400&q=80', 4)");
}

$conn->query("CREATE TABLE IF NOT EXISTS agency_services (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(190) NOT NULL,
    description TEXT NULL,
    price DECIMAL(15,2) NOT NULL DEFAULT 0,
    image VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Helper upload file gambar dari komputer ke direktori proyek
if (!function_exists('uploadAdminFile')) {
    function uploadAdminFile(string $fieldKey, string $targetDir, string $filePrefix, string &$msg, string &$msgType): ?string {
        if (empty($_FILES[$fieldKey]['name']) || !is_uploaded_file($_FILES[$fieldKey]['tmp_name'])) {
            return null;
        }
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mime = @mime_content_type($_FILES[$fieldKey]['tmp_name']);
        if (!isset($allowed[$mime]) || (int)$_FILES[$fieldKey]['size'] > 4 * 1024 * 1024) {
            $msg = 'File foto harus berekstensi JPG, PNG, atau WEBP dan maksimal 4 MB.';
            $msgType = 'error';
            return null;
        }
        $destDir = __DIR__ . '/' . trim($targetDir, '/');
        if (!is_dir($destDir)) @mkdir($destDir, 0755, true);
        $filename = $filePrefix . '-' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
        if (move_uploaded_file($_FILES[$fieldKey]['tmp_name'], $destDir . '/' . $filename)) {
            return trim($targetDir, '/') . '/' . $filename;
        }
        $msg = 'Gagal menyimpan file upload ke server. Periksa hak akses folder.';
        $msgType = 'error';
        return null;
    }
}

$agencyMessage = '';
$agencyMessageType = 'success';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['agency_action'])) {
    $postedCsrf = (string) ($_POST['agency_csrf'] ?? '');
    if (!hash_equals($_SESSION['order_status_csrf'], $postedCsrf)) {
        $agencyMessage = 'Sesi formulir tidak valid. Muat ulang halaman.';
        $agencyMessageType = 'error';
    } else {
        $agencyAction = (string) $_POST['agency_action'];

        // 1. SIMPAN SECTION HERO
        if ($agencyAction === 'save_hero_section') {
            $heroTag = trim((string) ($_POST['hero_tag'] ?? 'Digital Growth Partner'));
            $heroTitle = trim((string) ($_POST['hero_title'] ?? ''));
            $heroDescription = trim((string) ($_POST['hero_description'] ?? ''));
            $uploadedHero = uploadAdminFile('hero_image_file', 'uploads/agency', 'hero', $agencyMessage, $agencyMessageType);

            if ($agencyMessageType === 'success') {
                if ($uploadedHero !== null) {
                    $stmt = $conn->prepare('UPDATE agency_site_content SET hero_tag=?, hero_title=?, hero_description=?, hero_image=? WHERE id=1');
                    $stmt->bind_param('ssss', $heroTag, $heroTitle, $heroDescription, $uploadedHero);
                } else {
                    $stmt = $conn->prepare('UPDATE agency_site_content SET hero_tag=?, hero_title=?, hero_description=? WHERE id=1');
                    $stmt->bind_param('sss', $heroTag, $heroTitle, $heroDescription);
                }
                if ($stmt && $stmt->execute()) {
                    $agencyMessage = 'Hero Banner website berhasil diperbarui!';
                } else {
                    $agencyMessage = 'Gagal memperbarui Hero Banner.';
                    $agencyMessageType = 'error';
                }
                if ($stmt) $stmt->close();
            }

        // 2. SIMPAN SECTION TENTANG KAMI & ISU UMKM
        } elseif ($agencyAction === 'save_about_section') {
            $aboutTag = trim((string) ($_POST['about_tag'] ?? 'Tentang Balla’ Fresh'));
            $aboutTitle = trim((string) ($_POST['about_title'] ?? ''));
            $aboutStory = trim((string) ($_POST['about_story'] ?? ''));
            $aboutProblem = trim((string) ($_POST['about_problem'] ?? ''));
            $aboutSolution = trim((string) ($_POST['about_solution'] ?? ''));
            $uploadedAbout = uploadAdminFile('about_image_file', 'uploads/agency', 'about', $agencyMessage, $agencyMessageType);

            if ($agencyMessageType === 'success') {
                if ($uploadedAbout !== null) {
                    $stmt = $conn->prepare('UPDATE agency_site_content SET about_tag=?, about_title=?, about_story=?, about_problem=?, about_solution=?, about_image=? WHERE id=1');
                    $stmt->bind_param('ssssss', $aboutTag, $aboutTitle, $aboutStory, $aboutProblem, $aboutSolution, $uploadedAbout);
                } else {
                    $stmt = $conn->prepare('UPDATE agency_site_content SET about_tag=?, about_title=?, about_story=?, about_problem=?, about_solution=? WHERE id=1');
                    $stmt->bind_param('sssss', $aboutTag, $aboutTitle, $aboutStory, $aboutProblem, $aboutSolution);
                }
                if ($stmt && $stmt->execute()) {
                    $agencyMessage = 'Section Tentang Kami & Isu UMKM berhasil disimpan!';
                } else {
                    $agencyMessage = 'Gagal memperbarui Section Tentang Kami.';
                    $agencyMessageType = 'error';
                }
                if ($stmt) $stmt->close();
            }

        // 3. SIMPAN STRUKTUR ORGANISASI (4 TIM: 1 OWNER + 3 ANGGOTA)
        } elseif ($agencyAction === 'save_team_section') {
            $teamPost = $_POST['team'] ?? [];
            if (is_array($teamPost)) {
                foreach ($teamPost as $memberId => $mdata) {
                    $mid = (int) $memberId;
                    if ($mid <= 0) continue;
                    $mname = trim((string) ($mdata['name'] ?? ''));
                    $mposition = trim((string) ($mdata['position'] ?? ''));
                    if ($mname === '') continue;

                    $fileKey = 'team_photo_' . $mid;
                    $uploadedPhoto = uploadAdminFile($fileKey, 'uploads/team', 'team-' . $mid, $agencyMessage, $agencyMessageType);

                    if ($uploadedPhoto !== null) {
                        $stmt = $conn->prepare('UPDATE agency_team SET name=?, position=?, photo=? WHERE id=?');
                        $stmt->bind_param('sssi', $mname, $mposition, $uploadedPhoto, $mid);
                    } else {
                        $stmt = $conn->prepare('UPDATE agency_team SET name=?, position=? WHERE id=?');
                        $stmt->bind_param('ssi', $mname, $mposition, $mid);
                    }
                    if ($stmt) {
                        $stmt->execute();
                        $stmt->close();
                    }
                }
                if ($agencyMessageType === 'success') {
                    $agencyMessage = 'Struktur organisasi dan foto tim berhasil disimpan langsung!';
                }
            }

        // 4. SIMPAN SECTION AJAKAN BERGABUNG / CTA
        } elseif ($agencyAction === 'save_cta_section') {
            $ctaBadge = trim((string) ($_POST['cta_badge'] ?? 'Akses Eksklusif Mitra Balla’ Fresh'));
            $ctaTitle = trim((string) ($_POST['cta_title'] ?? ''));
            $ctaDescription = trim((string) ($_POST['cta_description'] ?? ''));
            $stmt = $conn->prepare('UPDATE agency_site_content SET cta_badge=?, cta_title=?, cta_description=? WHERE id=1');
            if ($stmt) {
                $stmt->bind_param('sss', $ctaBadge, $ctaTitle, $ctaDescription);
                $agencyMessage = $stmt->execute() ? 'Section Ajakan Bergabung / CTA berhasil diperbarui!' : 'Gagal memperbarui CTA.';
                if ($stmt->errno) $agencyMessageType = 'error';
                $stmt->close();
            }

        // 5. SIMPAN INFORMASI KONTAK WEBSITE
        } elseif ($agencyAction === 'save_contact_section') {
            $contactEmail = trim((string) ($_POST['contact_email'] ?? ''));
            $contactPhone = trim((string) ($_POST['contact_phone'] ?? ''));
            $contactAddress = trim((string) ($_POST['contact_address'] ?? ''));
            $stmt = $conn->prepare('UPDATE agency_site_content SET contact_email=?, contact_phone=?, contact_address=? WHERE id=1');
            if ($stmt) {
                $stmt->bind_param('sss', $contactEmail, $contactPhone, $contactAddress);
                $agencyMessage = $stmt->execute() ? 'Informasi kontak dan alamat website berhasil diperbarui!' : 'Gagal memperbarui kontak.';
                if ($stmt->errno) $agencyMessageType = 'error';
                $stmt->close();
            }

        // UNIFIED CONTENT FALLBACK
        } elseif ($agencyAction === 'save_content') {
            $heroTitle = trim((string) ($_POST['hero_title'] ?? ''));
            $heroDescription = trim((string) ($_POST['hero_description'] ?? ''));
            $contactEmail = trim((string) ($_POST['contact_email'] ?? ''));
            $contactPhone = trim((string) ($_POST['contact_phone'] ?? ''));
            $imagePath = uploadAdminFile('hero_image', 'uploads/agency', 'hero', $agencyMessage, $agencyMessageType);

            if ($agencyMessageType === 'success') {
                if ($imagePath !== null) {
                    $stmt = $conn->prepare('UPDATE agency_site_content SET hero_title=?, hero_description=?, contact_email=?, contact_phone=?, hero_image=? WHERE id=1');
                    $stmt->bind_param('sssss', $heroTitle, $heroDescription, $contactEmail, $contactPhone, $imagePath);
                } else {
                    $stmt = $conn->prepare('UPDATE agency_site_content SET hero_title=?, hero_description=?, contact_email=?, contact_phone=? WHERE id=1');
                    $stmt->bind_param('ssss', $heroTitle, $heroDescription, $contactEmail, $contactPhone);
                }
                if ($stmt && $stmt->execute()) $agencyMessage = 'Konten berhasil disimpan ke database.';
                else { $agencyMessage = 'Konten gagal disimpan.'; $agencyMessageType = 'error'; }
                if ($stmt) $stmt->close();
            }
        } elseif ($agencyAction === 'save_service') {
            $serviceId = (int) ($_POST['service_id'] ?? 0);
            $categoryId = (int) ($_POST['category_id'] ?? 1);
            $name = trim((string) ($_POST['service_name'] ?? ''));
            $description = trim((string) ($_POST['service_description'] ?? ''));
            $features = trim((string) ($_POST['service_features'] ?? ''));
            $price = (float) ($_POST['service_price'] ?? 0);
            $estimatedDays = max(1, (int) ($_POST['estimated_days'] ?? 3));
            $isAvailable = isset($_POST['is_available']) ? 1 : 0;
            $serviceImage = trim((string) ($_POST['service_image'] ?? ''));

            if (!empty($_FILES['service_image_file']['name']) && is_uploaded_file($_FILES['service_image_file']['tmp_name'])) {
                $serviceMime = mime_content_type($_FILES['service_image_file']['tmp_name']);
                $serviceTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                if (!isset($serviceTypes[$serviceMime]) || (int) $_FILES['service_image_file']['size'] > 4 * 1024 * 1024) {
                    $agencyMessage = 'Gambar layanan harus JPG, PNG, atau WEBP dan maksimal 4 MB.';
                    $agencyMessageType = 'error';
                } else {
                    $serviceUploadDir = __DIR__ . '/uploads/services';
                    if (!is_dir($serviceUploadDir)) mkdir($serviceUploadDir, 0755, true);
                    $serviceFileName = 'service-' . bin2hex(random_bytes(8)) . '.' . $serviceTypes[$serviceMime];
                    if (move_uploaded_file($_FILES['service_image_file']['tmp_name'], $serviceUploadDir . '/' . $serviceFileName)) {
                        $serviceImage = 'uploads/services/' . $serviceFileName;
                    } else {
                        $agencyMessage = 'Gagal mengunggah gambar layanan.';
                        $agencyMessageType = 'error';
                    }
                }
            }
            if ($agencyMessageType === 'error') {
                // Pesan validasi unggahan sudah disiapkan di atas.
            } elseif ($name === '' || $price < 0) {
                $agencyMessage = 'Nama layanan wajib diisi dan harga tidak boleh negatif.';
                $agencyMessageType = 'error';
            } else {
                if ($serviceId > 0) {
                    $stmt = $conn->prepare('UPDATE services SET category_id=?, name=?, description=?, features=?, price=?, image=?, estimated_days=?, is_available=? WHERE id=?');
                    $stmt->bind_param('isssdsiii', $categoryId, $name, $description, $features, $price, $serviceImage, $estimatedDays, $isAvailable, $serviceId);
                } else {
                    $stmt = $conn->prepare('INSERT INTO services (category_id, name, description, features, price, image, estimated_days, is_available) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                    $stmt->bind_param('isssdsii', $categoryId, $name, $description, $features, $price, $serviceImage, $estimatedDays, $isAvailable);
                }
                if ($stmt && $stmt->execute()) $agencyMessage = 'Data layanan berhasil disimpan langsung ke database website.';
                else { $agencyMessage = 'Data layanan gagal disimpan.'; $agencyMessageType = 'error'; }
                if ($stmt) $stmt->close();
            }
        } elseif ($agencyAction === 'delete_service') {
            $serviceId = (int) ($_POST['service_id'] ?? 0);
            $stmt = $conn->prepare('DELETE FROM services WHERE id=?');
            if ($stmt) {
                $stmt->bind_param('i', $serviceId);
                $agencyMessage = $stmt->execute() ? 'Layanan berhasil dihapus dari website.' : 'Layanan gagal dihapus.';
                if ($stmt->errno) $agencyMessageType = 'error';
                $stmt->close();
            }
        } elseif ($agencyAction === 'toggle_review') {
            $reviewId = (int) ($_POST['review_id'] ?? 0);
            $stmt = $conn->prepare('UPDATE service_reviews SET is_approved = IF(is_approved = 1, 0, 1) WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('i', $reviewId);
                $agencyMessage = $stmt->execute() ? 'Status tayang ulasan berhasil diubah.' : 'Gagal mengubah status ulasan.';
                $stmt->close();
            }
        } elseif ($agencyAction === 'delete_review') {
            $reviewId = (int) ($_POST['review_id'] ?? 0);
            $stmt = $conn->prepare('DELETE FROM service_reviews WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('i', $reviewId);
                $agencyMessage = $stmt->execute() ? 'Ulasan berhasil dihapus.' : 'Gagal menghapus ulasan.';
                $stmt->close();
            }
        }
    }
}
$siteContent = [
    'hero_tag' => 'Digital Growth Partner',
    'hero_title' => '',
    'hero_description' => '',
    'contact_email' => '',
    'contact_phone' => '',
    'hero_image' => '',
    'about_tag' => 'Tentang Balla’ Fresh',
    'about_title' => 'Mendorong Kemandirian Petani & UMKM Hidroponik Rumahan',
    'about_story' => '',
    'about_problem' => '',
    'about_solution' => '',
    'about_image' => '',
    'cta_title' => 'Buka Seluruh Potensi Usaha Hidroponik Anda Sekarang',
    'cta_description' => '',
    'cta_badge' => 'Akses Eksklusif Mitra Balla’ Fresh',
    'contact_address' => 'Makassar, Sulawesi Selatan, Indonesia'
];
$contentResult = $conn->query('SELECT * FROM agency_site_content WHERE id=1 LIMIT 1');
if ($contentResult && $contentRow = $contentResult->fetch_assoc()) {
    $siteContent = array_merge($siteContent, array_filter($contentRow, fn($v) => $v !== null));
}

$teamMembersList = [];
$teamRes = $conn->query('SELECT * FROM agency_team ORDER BY display_order ASC, id ASC');
if ($teamRes) {
    while ($tRow = $teamRes->fetch_assoc()) {
        $teamMembersList[] = $tRow;
    }
    $teamRes->free();
}

$categoriesList = [];
$catResult = $conn->query('SELECT id, name FROM categories ORDER BY id ASC');
if ($catResult) {
    while ($catRow = $catResult->fetch_assoc()) $categoriesList[] = $catRow;
    $catResult->free();
}

$allServices = [];
$serviceResult = $conn->query('
    SELECT s.*, c.name AS category_name,
           (SELECT COUNT(*) FROM service_reviews WHERE service_id = s.id) AS review_count
    FROM services s
    LEFT JOIN categories c ON s.category_id = c.id
    ORDER BY s.id ASC
');
if ($serviceResult) {
    while ($serviceRow = $serviceResult->fetch_assoc()) $allServices[] = $serviceRow;
    $serviceResult->free();
}

$allReviews = [];
$revResult = $conn->query('
    SELECT r.*, s.name AS service_name
    FROM service_reviews r
    LEFT JOIN services s ON r.service_id = s.id
    ORDER BY r.created_at DESC
    LIMIT 25
');
if ($revResult) {
    while ($revRow = $revResult->fetch_assoc()) $allReviews[] = $revRow;
    $revResult->free();
}

// ==========================================
// KONSULTASI & ROOM CHAT MITRA HANDLER
// ==========================================
$consultationMessage = '';
$consultationMessageType = 'success';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['consultation_action'])) {
    $postedCsrf = (string) ($_POST['consultation_csrf'] ?? '');
    if (!hash_equals($_SESSION['order_status_csrf'], $postedCsrf)) {
        $consultationMessage = 'Sesi formulir tidak valid. Silakan muat ulang halaman.';
        $consultationMessageType = 'error';
    } else {
        $cAction = (string) $_POST['consultation_action'];
        $cId = (int) ($_POST['consultation_id'] ?? 0);

        if ($cAction === 'approve_consultation' && $cId > 0) {
            $stmt = $conn->prepare("UPDATE consultations SET status = 'approved', approved_at = NOW() WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('i', $cId);
                if ($stmt->execute()) {
                    $welcomeMsg = "Halo Mitra Balla’ Fresh! Permohonan konsultasi Anda telah disetujui (ACC) oleh Admin. Silakan sampaikan kendala, kebutuhan desain, atau strategi pemasaran hidroponik Anda di sini.";
                    $wStmt = $conn->prepare("INSERT INTO consultation_messages (consultation_id, sender_type, sender_id, sender_name, message) VALUES (?, 'admin', ?, ?, ?)");
                    if ($wStmt) {
                        $wStmt->bind_param('iiss', $cId, $adminId, $adminName, $welcomeMsg);
                        $wStmt->execute();
                        $wStmt->close();
                    }
                    $consultationMessage = 'Permohonan konsultasi berhasil di-ACC (disetujui)! Room chat sekarang aktif untuk mitra.';
                } else {
                    $consultationMessage = 'Gagal menyetujui konsultasi.';
                    $consultationMessageType = 'error';
                }
                $stmt->close();
            }
        } elseif ($cAction === 'complete_consultation' && $cId > 0) {
            $stmt = $conn->prepare("UPDATE consultations SET status = 'completed' WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('i', $cId);
                if ($stmt->execute()) {
                    $consultationMessage = 'Sesi konsultasi berhasil ditandai selesai.';
                } else {
                    $consultationMessage = 'Gagal menyelesaikan sesi konsultasi.';
                    $consultationMessageType = 'error';
                }
                $stmt->close();
            }
        } elseif ($cAction === 'send_admin_chat' && $cId > 0) {
            // Verifikasi room konsultasi valid dan berstatus approved
            $checkRoom = $conn->prepare("SELECT id, status FROM consultations WHERE id = ? LIMIT 1");
            $cActiveRow = null;
            if ($checkRoom) {
                $checkRoom->bind_param('i', $cId);
                $checkRoom->execute();
                $cActiveRow = $checkRoom->get_result()->fetch_assoc();
                $checkRoom->close();
            }

            if (!$cActiveRow || $cActiveRow['status'] !== 'approved') {
                $consultationMessage = 'Sesi konsultasi belum disetujui (ACC) atau telah selesai.';
                $consultationMessageType = 'error';
            } else {
                $replyText = trim((string) ($_POST['chat_message'] ?? ''));

                // Jenis file yang diizinkan (konsisten dengan sistem pengguna pada profil.php)
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
                            $uploadError = 'Folder penyimpanan lampiran tidak dapat dibuat di server.';
                        } else {
                            $finfo = new finfo(FILEINFO_MIME_TYPE);
                            for ($i = 0; $i < $fileCount; $i++) {
                                $errorCode = (int) ($_FILES['chat_files']['error'][$i] ?? UPLOAD_ERR_NO_FILE);
                                if ($errorCode === UPLOAD_ERR_NO_FILE) {
                                    continue;
                                }
                                if ($errorCode !== UPLOAD_ERR_OK) {
                                    $uploadError = 'Salah satu file gagal diunggah. Silakan coba kembali.';
                                    break;
                                }

                                $tmpName = (string) ($_FILES['chat_files']['tmp_name'][$i] ?? '');
                                $originalName = basename((string) ($_FILES['chat_files']['name'][$i] ?? 'file'));
                                $fileSize = (int) ($_FILES['chat_files']['size'][$i] ?? 0);
                                $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                                $detectedMime = ($tmpName !== '' && is_uploaded_file($tmpName)) ? $finfo->file($tmpName) : '';

                                if (!isset($allowedChatFiles[$extension]) ||
                                    !in_array($detectedMime, $allowedChatFiles[$extension], true) ||
                                    $fileSize <= 0 || $fileSize > $maxChatFileSize) {
                                    $uploadError = 'File "' . htmlspecialchars($originalName, ENT_QUOTES, 'UTF-8') . '" tidak didukung atau melebihi batas 20 MB.';
                                    break;
                                }

                                $storedName = 'chat-' . $cId . '-' . bin2hex(random_bytes(12)) . '.' . $extension;
                                if (!move_uploaded_file($tmpName, $uploadDir . '/' . $storedName)) {
                                    $uploadError = 'File "' . htmlspecialchars($originalName, ENT_QUOTES, 'UTF-8') . '" gagal disimpan ke server.';
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
                    // Bersihkan file yang sudah terlanjur dipindahkan jika batch ada yang gagal
                    foreach ($uploadedChatFiles as $uFile) {
                        $storedPath = __DIR__ . '/' . $uFile['path'];
                        if (is_file($storedPath)) @unlink($storedPath);
                    }
                    $consultationMessage = $uploadError;
                    $consultationMessageType = 'error';
                } elseif ($replyText === '' && empty($uploadedChatFiles)) {
                    $consultationMessage = 'Tulis pesan atau pilih minimal satu file lampiran untuk dikirim.';
                    $consultationMessageType = 'error';
                } else {
                    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
                    $scheme = $isHttps ? 'https' : 'http';
                    $host = preg_replace('/[^a-zA-Z0-9.:-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
                    $basePath = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/admin_dashboard.php')));
                    $basePath = ($basePath === '/' || $basePath === '.') ? '' : rtrim($basePath, '/');
                    $messageToSave = $replyText;

                    foreach ($uploadedChatFiles as $uFile) {
                        $publicUrl = $scheme . '://' . $host . $basePath . '/' . $uFile['path'];
                        $messageToSave .= ($messageToSave !== '' ? "\n\n" : '') .
                            '[Lampiran] ' . $uFile['original_name'] .
                            "\nURL: " . $publicUrl;
                    }

                    $stmt = $conn->prepare("INSERT INTO consultation_messages (consultation_id, sender_type, sender_id, sender_name, message) VALUES (?, 'admin', ?, ?, ?)");
                    if ($stmt) {
                        $stmt->bind_param('iiss', $cId, $adminId, $adminName, $messageToSave);
                        if ($stmt->execute()) {
                            $consultationMessage = 'Pesan balasan dan lampiran berhasil terkirim langsung ke room chat mitra!';
                        } else {
                            $consultationMessage = 'Gagal mengirim pesan balasan: ' . $stmt->error;
                            $consultationMessageType = 'error';
                        }
                        $stmt->close();
                    }
                }
            }
        }
    }
}

// ==========================================
// PENGATURAN & KEBIJAKAN PRIVASI HANDLER (EDIT-ONLY)
// ==========================================
$settingsMessage = '';
$settingsMessageType = 'success';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['settings_action'])) {
    $postedCsrf = (string) ($_POST['settings_csrf'] ?? '');
    if (!hash_equals($_SESSION['order_status_csrf'], $postedCsrf)) {
        $settingsMessage = 'Sesi formulir tidak valid. Silakan muat ulang halaman.';
        $settingsMessageType = 'error';
    } else {
        $sAction = (string) $_POST['settings_action'];
        if ($sAction === 'save_policies') {
            $privacyPolicy = trim((string) ($_POST['privacy_policy'] ?? ''));
            $termsConditions = trim((string) ($_POST['terms_conditions'] ?? ''));

            if ($privacyPolicy === '' || $termsConditions === '') {
                $settingsMessage = 'Kebijakan Privasi dan Syarat & Ketentuan tidak boleh dikosongkan.';
                $settingsMessageType = 'error';
            } else {
                $stmt = $conn->prepare("UPDATE agency_site_content SET privacy_policy = ?, terms_conditions = ?, policy_updated_at = NOW() WHERE id = 1");
                if ($stmt) {
                    $stmt->bind_param('ss', $privacyPolicy, $termsConditions);
                    if ($stmt->execute()) {
                        $settingsMessage = 'Kebijakan Privasi dan Syarat Layanan berhasil diperbarui langsung ke website!';
                    } else {
                        $settingsMessage = 'Gagal memperbarui kebijakan.';
                        $settingsMessageType = 'error';
                    }
                    $stmt->close();
                }
            }
        }
    }
}

// ==========================================
// KELOLA ARTIKEL & EDUKASI HANDLER
// ==========================================
$articleMessage = '';
$articleMessageType = 'success';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['article_action'])) {
    $postedCsrf = (string) ($_POST['article_csrf'] ?? '');
    if (!hash_equals($_SESSION['order_status_csrf'], $postedCsrf)) {
        $articleMessage = 'Sesi formulir tidak valid. Silakan muat ulang halaman.';
        $articleMessageType = 'error';
    } else {
        $aAction = (string) $_POST['article_action'];

        if ($aAction === 'save_article') {
            $artId = (int) ($_POST['article_id'] ?? 0);
            $artTitle = trim((string) ($_POST['title'] ?? ''));
            $artSlug = trim((string) ($_POST['slug'] ?? ''));
            $artCategory = trim((string) ($_POST['category'] ?? 'Branding & Kemasan'));
            $artExcerpt = trim((string) ($_POST['excerpt'] ?? ''));
            $rawContent = trim((string) ($_POST['content'] ?? ''));
            $artContent = sanitizeArticleHtml($rawContent);
            $artAuthorName = trim((string) ($_POST['author_name'] ?? 'Tim Balla’ Fresh'));
            $artAuthorRole = trim((string) ($_POST['author_role'] ?? 'Spesialis Branding & Konsultan'));
            $artReadTime = trim((string) ($_POST['read_time'] ?? '5 menit baca'));
            $isFeatured = isset($_POST['is_featured']) ? 1 : 0;
            $isPublished = isset($_POST['is_published']) ? 1 : 0;

            if ($artTitle === '' || $artContent === '') {
                $articleMessage = 'Judul dan Isi Konten artikel wajib diisi.';
                $articleMessageType = 'error';
                $editArticleData = [
                    'id' => $artId,
                    'title' => $artTitle,
                    'slug' => $artSlug,
                    'category' => $artCategory,
                    'excerpt' => $artExcerpt,
                    'content' => $artContent,
                    'image' => trim((string) ($_POST['existing_image'] ?? '')),
                    'author_name' => $artAuthorName,
                    'author_role' => $artAuthorRole,
                    'author_avatar' => trim((string) ($_POST['author_avatar'] ?? '')),
                    'read_time' => $artReadTime,
                    'is_featured' => $isFeatured,
                    'is_published' => $isPublished
                ];
            } else {
                // Generate slug jika kosong
                if ($artSlug === '') {
                    $slugBase = strtolower(trim((string)preg_replace('/[^A-Za-z0-9-]+/', '-', $artTitle), '-'));
                    $artSlug = $slugBase ?: 'artikel-' . time();
                }

                // Cek gambar
                $existingImage = trim((string) ($_POST['existing_image'] ?? ''));
                $artImage = $existingImage;
                
                // Upload file gambar artikel baru jika ada
                $uploadedImg = uploadAdminFile('article_image_file', 'uploads/articles', 'article', $articleMessage, $articleMessageType);
                if ($uploadedImg) {
                    $artImage = $uploadedImg;
                } elseif (!empty($_POST['article_image_url'])) {
                    $artImage = trim((string) $_POST['article_image_url']);
                }
                if ($artImage === '') {
                    $artImage = 'https://images.unsplash.com/photo-1540420773420-3366772f4999?auto=format&fit=crop&w=1200&q=80';
                }

                // Cek avatar penulis
                $artAvatar = trim((string) ($_POST['author_avatar'] ?? ''));
                if ($artAvatar === '') {
                    $artAvatar = 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80';
                }

                if ($artId > 0) {
                    // Update
                    $stmt = $conn->prepare("
                        UPDATE articles 
                        SET title = ?, slug = ?, category = ?, excerpt = ?, content = ?, 
                            image = ?, author_name = ?, author_role = ?, author_avatar = ?, 
                            read_time = ?, is_featured = ?, is_published = ?, updated_at = NOW() 
                        WHERE id = ?
                    ");
                    if ($stmt) {
                        $stmt->bind_param(
                            'ssssssssssiii',
                            $artTitle, $artSlug, $artCategory, $artExcerpt, $artContent,
                            $artImage, $artAuthorName, $artAuthorRole, $artAvatar,
                            $artReadTime, $isFeatured, $isPublished, $artId
                        );
                        if ($stmt->execute()) {
                            $articleMessage = 'Artikel "' . htmlspecialchars($artTitle, ENT_QUOTES, 'UTF-8') . '" berhasil diperbarui!';
                        } else {
                            $articleMessage = 'Gagal memperbarui artikel: ' . $stmt->error;
                            $articleMessageType = 'error';
                        }
                        $stmt->close();
                    }
                } else {
                    // Insert
                    $stmt = $conn->prepare("
                        INSERT INTO articles 
                        (title, slug, category, excerpt, content, image, author_name, author_role, author_avatar, read_time, is_featured, is_published, views_count, created_at, updated_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW(), NOW())
                    ");
                    if ($stmt) {
                        $stmt->bind_param(
                            'ssssssssssii',
                            $artTitle, $artSlug, $artCategory, $artExcerpt, $artContent,
                            $artImage, $artAuthorName, $artAuthorRole, $artAvatar,
                            $artReadTime, $isFeatured, $isPublished
                        );
                        if ($stmt->execute()) {
                            $articleMessage = 'Artikel baru "' . htmlspecialchars($artTitle, ENT_QUOTES, 'UTF-8') . '" berhasil dibuat dan dipublikasikan!';
                        } else {
                            $articleMessage = 'Gagal membuat artikel baru: ' . $stmt->error;
                            $articleMessageType = 'error';
                        }
                        $stmt->close();
                    }
                }
            }
        } elseif ($aAction === 'delete_article') {
            $artId = (int) ($_POST['article_id'] ?? 0);
            if ($artId > 0) {
                $stmt = $conn->prepare("DELETE FROM articles WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param('i', $artId);
                    $articleMessage = $stmt->execute() ? 'Artikel berhasil dihapus.' : 'Gagal menghapus artikel.';
                    $stmt->close();
                }
            }
        } elseif ($aAction === 'toggle_publish') {
            $artId = (int) ($_POST['article_id'] ?? 0);
            if ($artId > 0) {
                $stmt = $conn->prepare("UPDATE articles SET is_published = IF(is_published = 1, 0, 1) WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param('i', $artId);
                    $articleMessage = $stmt->execute() ? 'Status publikasi artikel berhasil diubah.' : 'Gagal mengubah status publikasi.';
                    $stmt->close();
                }
            }
        } elseif ($aAction === 'toggle_featured') {
            $artId = (int) ($_POST['article_id'] ?? 0);
            if ($artId > 0) {
                $stmt = $conn->prepare("UPDATE articles SET is_featured = IF(is_featured = 1, 0, 1) WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param('i', $artId);
                    $articleMessage = $stmt->execute() ? 'Status unggulan (hero banner) artikel berhasil diubah.' : 'Gagal mengubah status unggulan.';
                    $stmt->close();
                }
            }
        }
    }
}

// Query artikel untuk admin dashboard
$allArticlesList = [];
$artStats = ['total' => 0, 'published' => 0, 'featured' => 0, 'views' => 0];
$allArtQuery = $conn->query("SELECT * FROM articles ORDER BY created_at DESC");
if ($allArtQuery) {
    while ($aRow = $allArtQuery->fetch_assoc()) {
        $allArticlesList[] = $aRow;
        $artStats['total']++;
        if (!empty($aRow['is_published'])) $artStats['published']++;
        if (!empty($aRow['is_featured'])) $artStats['featured']++;
        $artStats['views'] += (int)($aRow['views_count'] ?? 0);
    }
    $allArtQuery->free();
}

// Data artikel yang sedang diedit jika ada edit_article
$editArticleData = null;
$editArtId = (int) ($_GET['edit_article'] ?? 0);
if ($editArtId > 0) {
    foreach ($allArticlesList as $arItem) {
        if ((int)$arItem['id'] === $editArtId) {
            $editArticleData = $arItem;
            break;
        }
    }
}

// Status apakah editor artikel sedang dibuka (mode Tambah Baru atau Edit)
$isArticleEditorOpen = ($editArticleData !== null) || (isset($_GET['action']) && in_array($_GET['action'], ['create', 'add', 'new'], true));

// Query Data Usaha Mitra
$mitraBusinesses = [];
$bizRes = $conn->query("
    SELECT id, name, email, phone, profile_image, birth_date,
           business_name, business_type, business_role, social_media,
           website_url, business_address, business_description,
           service_preferences, address, created_at
    FROM users
    ORDER BY (business_name IS NOT NULL AND business_name != '') DESC, id DESC
");
if ($bizRes) {
    while ($bRow = $bizRes->fetch_assoc()) {
        $mitraBusinesses[] = $bRow;
    }
    $bizRes->free();
}

// Query Konsultasi & Chat
$allConsultations = [];
$consultRes = $conn->query("
    SELECT c.*, u.name AS user_name, u.email AS user_email, u.profile_image,
           (SELECT COUNT(*) FROM consultation_messages WHERE consultation_id = c.id) AS msg_count,
           (SELECT message FROM consultation_messages WHERE consultation_id = c.id ORDER BY id DESC LIMIT 1) AS last_message,
           (SELECT created_at FROM consultation_messages WHERE consultation_id = c.id ORDER BY id DESC LIMIT 1) AS last_message_at
    FROM consultations c
    LEFT JOIN users u ON c.user_id = u.id
    ORDER BY CASE WHEN c.status = 'pending' THEN 1 WHEN c.status = 'approved' THEN 2 ELSE 3 END, c.created_at DESC
");
if ($consultRes) {
    while ($cRow = $consultRes->fetch_assoc()) {
        $allConsultations[] = $cRow;
    }
    $consultRes->free();
}

$pendingConsultationCount = 0;
foreach ($allConsultations as $c) {
    if (($c['status'] ?? '') === 'pending') {
        $pendingConsultationCount++;
    }
}

// Active room chat
$activeChatId = (int) ($_GET['chat_id'] ?? 0);
if ($activeChatId === 0 && !empty($allConsultations)) {
    $activeChatId = (int) $allConsultations[0]['id'];
}
$activeConsultation = null;
$activeChatMessages = [];
if ($activeChatId > 0) {
    foreach ($allConsultations as $c) {
        if ((int)$c['id'] === $activeChatId) {
            $activeConsultation = $c;
            break;
        }
    }
    $msgStmt = $conn->prepare("SELECT * FROM consultation_messages WHERE consultation_id = ? ORDER BY id ASC");
    if ($msgStmt) {
        $msgStmt->bind_param('i', $activeChatId);
        $msgStmt->execute();
        $mRes = $msgStmt->get_result();
        while ($mRow = $mRes->fetch_assoc()) {
            $activeChatMessages[] = $mRow;
        }
        $msgStmt->close();
    }
}

// Policy content data
$policyContent = [
    'privacy_policy' => '',
    'terms_conditions' => '',
    'policy_updated_at' => null
];
$pRes = $conn->query("SELECT privacy_policy, terms_conditions, policy_updated_at FROM agency_site_content WHERE id = 1 LIMIT 1");
if ($pRes && $pRow = $pRes->fetch_assoc()) {
    $policyContent = array_merge($policyContent, array_filter($pRow, fn($v) => $v !== null));
}

// Query Data Administrator & Status Aktivitas Real-Time
$adminList = [];
$onlineAdminCount = 0;
$totalAdminsCount = 0;
$ownerAdminCount = 0;

$aRes = $conn->query("
    SELECT id, name, email, is_approved, is_owner, created_at, last_active_at,
           COALESCE(role, IF(is_owner = 1, 'owner', 'admin')) AS role,
           phone
    FROM admins
    ORDER BY is_owner DESC, id ASC
");
if (!$aRes) {
    $aRes = $conn->query("SELECT id, name, email, is_approved, is_owner, created_at, last_active_at FROM admins ORDER BY is_owner DESC, id ASC");
}
if (!$aRes) {
    $aRes = $conn->query("SELECT id, name, email, is_approved, is_owner, created_at FROM admins ORDER BY is_owner DESC, id ASC");
}
if ($aRes) {
    while ($ar = $aRes->fetch_assoc()) {
        $st = formatAdminOnlineStatus($ar['last_active_at'] ?? null);
        $ar['online_status'] = $st;
        if ($st['is_online']) {
            $onlineAdminCount++;
        }
        if ((int)($ar['is_owner'] ?? 0) === 1) {
            $ownerAdminCount++;
        }
        $adminList[] = $ar;
    }
    if (method_exists($aRes, 'free')) {
        $aRes->free();
    }
}
$totalAdminsCount = count($adminList);

$initials = 'BF';
$nameParts = preg_split('/\s+/', trim($adminName)) ?: [];
if (count($nameParts) >= 2) {
    $initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
} elseif (!empty($nameParts[0])) {
    $initials = strtoupper(substr($nameParts[0], 0, 2));
}

// =========================================================================
// BUSINESS INTELLIGENCE & ANALYTICS ENGINE (DASHBOARD ONLY)
// =========================================================================
$biData = null;
if ($page === 'dashboard') {
    $biPeriod = (string)($_GET['bi_period'] ?? 'all');
    if (!in_array($biPeriod, ['all', 'today', '7days', 'this_month', 'this_year'], true)) {
        $biPeriod = 'all';
    }

    $tz = new DateTimeZone('Asia/Makassar');
    $now = new DateTime('now', $tz);

    $curStart = null;
    $curEnd = null;
    $prevStart = null;
    $prevEnd = null;
    $periodLabel = 'Semua Waktu';
    $comparisonLabel = 'vs Periode Sebelumnya (Tahun Lalu)';

    if ($biPeriod === 'today') {
        $curStart = (clone $now)->format('Y-m-d 00:00:00');
        $curEnd = (clone $now)->format('Y-m-d 23:59:59');
        $prevDay = (clone $now)->modify('-1 day');
        $prevStart = $prevDay->format('Y-m-d 00:00:00');
        $prevEnd = $prevDay->format('Y-m-d 23:59:59');
        $periodLabel = 'Hari Ini (' . (clone $now)->format('d M Y') . ')';
        $comparisonLabel = 'vs Kemarin (' . $prevDay->format('d M') . ')';
    } elseif ($biPeriod === '7days') {
        $start7 = (clone $now)->modify('-6 days');
        $curStart = $start7->format('Y-m-d 00:00:00');
        $curEnd = (clone $now)->format('Y-m-d 23:59:59');
        $prevStartObj = (clone $now)->modify('-13 days');
        $prevEndObj = (clone $now)->modify('-7 days');
        $prevStart = $prevStartObj->format('Y-m-d 00:00:00');
        $prevEnd = $prevEndObj->format('Y-m-d 23:59:59');
        $periodLabel = '7 Hari Terakhir (' . $start7->format('d M') . ' - ' . (clone $now)->format('d M') . ')';
        $comparisonLabel = 'vs 7 Hari Sebelumnya';
    } elseif ($biPeriod === 'this_month') {
        $curStart = (clone $now)->format('Y-m-01 00:00:00');
        $curEnd = (clone $now)->format('Y-m-t 23:59:59');
        $prevMonth = (clone $now)->modify('first day of previous month');
        $prevStart = $prevMonth->format('Y-m-01 00:00:00');
        $prevEnd = $prevMonth->format('Y-m-t 23:59:59');
        $periodLabel = 'Bulan Ini (' . (clone $now)->format('F Y') . ')';
        $comparisonLabel = 'vs Bulan Lalu (' . $prevMonth->format('F') . ')';
    } elseif ($biPeriod === 'this_year') {
        $curStart = (clone $now)->format('Y-01-01 00:00:00');
        $curEnd = (clone $now)->format('Y-12-31 23:59:59');
        $prevYear = (int)$now->format('Y') - 1;
        $prevStart = "{$prevYear}-01-01 00:00:00";
        $prevEnd = "{$prevYear}-12-31 23:59:59";
        $periodLabel = 'Tahun Ini (' . (clone $now)->format('Y') . ')';
        $comparisonLabel = "vs Tahun Lalu ({$prevYear})";
    } else {
        // 'all'
        $periodLabel = 'Semua Waktu (All Time)';
        $prevYear = (int)$now->format('Y') - 1;
        $prevStart = "{$prevYear}-01-01 00:00:00";
        $prevEnd = "{$prevYear}-12-31 23:59:59";
        $comparisonLabel = "vs Tahun Sebelumnya ({$prevYear})";
    }

    // 1. Ambil seluruh data pesanan riil untuk analitik
    $biOrders = [];
    $biOrderQ = $conn->query("
        SELECT o.id, o.order_number, o.customer_id, o.total_amount, o.status,
               o.payment_method, o.payment_type, o.transaction_status, o.payment_status,
               o.created_at, o.business_name, c.name AS customer_name, c.email AS customer_email
        FROM orders o
        LEFT JOIN customers c ON c.id = o.customer_id
        ORDER BY o.created_at ASC
    ");
    if ($biOrderQ) {
        while ($r = $biOrderQ->fetch_assoc()) {
            $biOrders[] = $r;
        }
        if (method_exists($biOrderQ, 'free')) {
            $biOrderQ->free();
        }
    }
    // Jika query di atas kosong, gunakan $orders yang sudah diload sebelumnya
    if (empty($biOrders) && !empty($orders)) {
        $biOrders = $orders;
    }

    // Filter transaksi sukses (pendapatan hanya dari transaksi yang berhasil)
    $isBiOrderSuccess = function(array $o): bool {
        $paySt = strtolower(trim((string)($o['payment_status'] ?? '')));
        $st = strtolower(trim((string)($o['status'] ?? '')));
        if (in_array($paySt, ['settlement', 'capture', 'lunas', 'paid', 'success'], true)) {
            return true;
        }
        if ($st === 'completed' && !in_array($paySt, ['cancel', 'cancelled', 'deny', 'expire', 'expired', 'failed', 'gagal'], true)) {
            return true;
        }
        return false;
    };

    // Filter rentang waktu
    $inPeriod = function(string $dateStr, ?string $start, ?string $end): bool {
        if ($start === null || $end === null) return true;
        $t = strtotime($dateStr);
        return ($t >= strtotime($start) && $t <= strtotime($end));
    };

    // 2. Hitung Metrik Periode Berjalan vs Periode Sebelumnya
    $curOrders = [];
    $prevOrders = [];
    $allSuccessfulOrders = [];

    foreach ($biOrders as $o) {
        $cDate = (string)($o['created_at'] ?? '');
        $isSucc = $isBiOrderSuccess($o);
        if ($isSucc) {
            $allSuccessfulOrders[] = $o;
        }

        if ($inPeriod($cDate, $curStart, $curEnd)) {
            $curOrders[] = $o;
        }
        if ($prevStart !== null && $prevEnd !== null && $inPeriod($cDate, $prevStart, $prevEnd)) {
            $prevOrders[] = $o;
        }
    }

    $curSuccessOrders = array_filter($curOrders, $isBiOrderSuccess);
    $prevSuccessOrders = array_filter($prevOrders, $isBiOrderSuccess);

    $curRevenue = (float)array_sum(array_map(fn($o) => (float)$o['total_amount'], $curSuccessOrders));
    $prevRevenue = (float)array_sum(array_map(fn($o) => (float)$o['total_amount'], $prevSuccessOrders));

    $curSuccessCount = count($curSuccessOrders);
    $prevSuccessCount = count($prevSuccessOrders);

    $curAov = $curSuccessCount > 0 ? ($curRevenue / $curSuccessCount) : 0.0;
    $prevAov = $prevSuccessCount > 0 ? ($prevRevenue / $prevSuccessCount) : 0.0;

    $curTotalOrderCount = count($curOrders);
    $prevTotalOrderCount = count($prevOrders);

    // Hitung pertumbuhan %
    $calcBiGrowth = function(float $current, float $previous): array {
        if ($previous > 0) {
            $diff = $current - $previous;
            $pct = round(($diff / $previous) * 100, 1);
            return [
                'pct' => $pct,
                'is_positive' => $pct >= 0,
                'diff' => $diff,
                'text' => ($pct >= 0 ? '+' : '') . $pct . '%'
            ];
        }
        if ($current > 0) {
            return ['pct' => 100.0, 'is_positive' => true, 'diff' => $current, 'text' => '+100%'];
        }
        return ['pct' => 0.0, 'is_positive' => true, 'diff' => 0.0, 'text' => '0%'];
    };

    $revenueGrowth = $calcBiGrowth($curRevenue, $prevRevenue);
    $ordersGrowth = $calcBiGrowth((float)$curSuccessCount, (float)$prevSuccessCount);
    $aovGrowth = $calcBiGrowth($curAov, $prevAov);
    $totalOrdersGrowth = $calcBiGrowth((float)$curTotalOrderCount, (float)$prevTotalOrderCount);

    // 3. Time Series Datasets untuk Grafik Penjualan (Harian, Mingguan, Bulanan, Tahunan)
    // Harian (30 hari terakhir)
    $tsDaily = [];
    for ($i = 29; $i >= 0; $i--) {
        $dObj = (clone $now)->modify("-{$i} days");
        $key = $dObj->format('Y-m-d');
        $label = $dObj->format('d M');
        $tsDaily[$key] = ['label' => $label, 'revenue' => 0.0, 'orders' => 0];
    }
    foreach ($allSuccessfulOrders as $o) {
        $d = date('Y-m-d', strtotime((string)$o['created_at']));
        if (isset($tsDaily[$d])) {
            $tsDaily[$d]['revenue'] += (float)$o['total_amount'];
            $tsDaily[$d]['orders']++;
        }
    }

    // Mingguan (8 minggu terakhir)
    $tsWeekly = [];
    for ($w = 7; $w >= 0; $w--) {
        $wStart = (clone $now)->modify("-{$w} weeks")->modify('monday this week');
        $wEnd = (clone $wStart)->modify('+6 days');
        $wKey = $wStart->format('Y-m-d');
        $tsWeekly[$wKey] = [
            'label' => $wStart->format('d M') . ' - ' . $wEnd->format('d M'),
            'start' => $wStart->format('Y-m-d 00:00:00'),
            'end' => $wEnd->format('Y-m-d 23:59:59'),
            'revenue' => 0.0,
            'orders' => 0
        ];
    }
    foreach ($allSuccessfulOrders as $o) {
        $ts = strtotime((string)$o['created_at']);
        foreach ($tsWeekly as $k => &$wData) {
            if ($ts >= strtotime($wData['start']) && $ts <= strtotime($wData['end'])) {
                $wData['revenue'] += (float)$o['total_amount'];
                $wData['orders']++;
                break;
            }
        }
        unset($wData);
    }

    // Bulanan (12 bulan terakhir)
    $tsMonthly = [];
    for ($m = 11; $m >= 0; $m--) {
        $mObj = (clone $now)->modify("-{$m} months");
        $mKey = $mObj->format('Y-m');
        $tsMonthly[$mKey] = [
            'label' => $mObj->format('M Y'),
            'revenue' => 0.0,
            'orders' => 0
        ];
    }
    foreach ($allSuccessfulOrders as $o) {
        $mKey = date('Y-m', strtotime((string)$o['created_at']));
        if (isset($tsMonthly[$mKey])) {
            $tsMonthly[$mKey]['revenue'] += (float)$o['total_amount'];
            $tsMonthly[$mKey]['orders']++;
        }
    }

    // Tahunan (3 tahun terakhir)
    $tsYearly = [];
    $thisY = (int)$now->format('Y');
    for ($y = $thisY - 2; $y <= $thisY; $y++) {
        $tsYearly[(string)$y] = [
            'label' => 'Tahun ' . $y,
            'revenue' => 0.0,
            'orders' => 0
        ];
    }
    foreach ($allSuccessfulOrders as $o) {
        $yKey = date('Y', strtotime((string)$o['created_at']));
        if (isset($tsYearly[$yKey])) {
            $tsYearly[$yKey]['revenue'] += (float)$o['total_amount'];
            $tsYearly[$yKey]['orders']++;
        }
    }

    // 4. Analisis Status Transaksi & Distribusi Metode Pembayaran
    $statusDist = [
        'completed' => ['label' => 'Berhasil / Selesai', 'count' => 0, 'color' => '#5b8121'],
        'processing' => ['label' => 'Diproses', 'count' => 0, 'color' => '#3b82f6'],
        'pending' => ['label' => 'Menunggu Pembayaran', 'count' => 0, 'color' => '#e48e1e'],
        'cancelled' => ['label' => 'Dibatalkan / Kedaluwarsa', 'count' => 0, 'color' => '#ef4444']
    ];
    $paymentDist = [
        'qris' => ['label' => 'QRIS (Gopay/Shopee/BCA)', 'count' => 0, 'revenue' => 0.0, 'color' => '#10b981'],
        'bank_transfer' => ['label' => 'Transfer Bank (BCA/Mandiri/BRI)', 'count' => 0, 'revenue' => 0.0, 'color' => '#3b82f6'],
        'gopay' => ['label' => 'GoPay', 'count' => 0, 'revenue' => 0.0, 'color' => '#06b6d4'],
        'shopeepay' => ['label' => 'ShopeePay', 'count' => 0, 'revenue' => 0.0, 'color' => '#f97316'],
        'manual_transfer' => ['label' => 'Transfer Manual', 'count' => 0, 'revenue' => 0.0, 'color' => '#8b5cf6'],
        'other' => ['label' => 'Lainnya', 'count' => 0, 'revenue' => 0.0, 'color' => '#64748b']
    ];

    foreach ($curOrders as $o) {
        $st = strtolower(trim((string)($o['status'] ?? 'pending')));
        $paySt = strtolower(trim((string)($o['payment_status'] ?? '')));
        if ($isBiOrderSuccess($o)) {
            $statusDist['completed']['count']++;
        } elseif ($st === 'processing') {
            $statusDist['processing']['count']++;
        } elseif (in_array($paySt, ['cancel', 'cancelled', 'deny', 'expire', 'expired', 'failed'], true) || $st === 'cancelled') {
            $statusDist['cancelled']['count']++;
        } else {
            $statusDist['pending']['count']++;
        }

        // Metode pembayaran
        $method = strtolower(trim((string)($o['payment_method'] ?? '')));
        $subMethod = strtolower(trim((string)($o['payment_type'] ?? '')));
        $amt = (float)($o['total_amount'] ?? 0);
        $succ = $isBiOrderSuccess($o);

        if ($subMethod === 'qris' || $method === 'qris' || strpos($method, 'qris') !== false) {
            $paymentDist['qris']['count']++;
            if ($succ) $paymentDist['qris']['revenue'] += $amt;
        } elseif (in_array($method, ['bca', 'mandiri', 'bri', 'bni'], true) || in_array($subMethod, ['bca_va', 'mandiri_va', 'bri_va', 'bni_va'], true) || strpos($method, 'bank') !== false) {
            $paymentDist['bank_transfer']['count']++;
            if ($succ) $paymentDist['bank_transfer']['revenue'] += $amt;
        } elseif ($subMethod === 'gopay') {
            $paymentDist['gopay']['count']++;
            if ($succ) $paymentDist['gopay']['revenue'] += $amt;
        } elseif ($subMethod === 'shopeepay') {
            $paymentDist['shopeepay']['count']++;
            if ($succ) $paymentDist['shopeepay']['revenue'] += $amt;
        } elseif ($method === 'manual') {
            $paymentDist['manual_transfer']['count']++;
            if ($succ) $paymentDist['manual_transfer']['revenue'] += $amt;
        } else {
            $paymentDist['other']['count']++;
            if ($succ) $paymentDist['other']['revenue'] += $amt;
        }
    }

    // 5. Layanan Terlaris (Ranking Berdasarkan Jumlah Pesanan & Pendapatan)
    $biOrderItems = [];
    $itemQ = $conn->query("
        SELECT oi.id, oi.order_id, oi.service_id, oi.service_name, oi.quantity, oi.unit_price, oi.subtotal,
               o.payment_status, o.status, o.created_at
        FROM order_items oi
        JOIN orders o ON o.id = oi.order_id
        ORDER BY oi.id ASC
    ");
    if ($itemQ) {
        while ($it = $itemQ->fetch_assoc()) {
            $biOrderItems[] = $it;
        }
        if (method_exists($itemQ, 'free')) {
            $itemQ->free();
        }
    }

    $serviceRanking = [];
    // Daftar layanan resmi Balla' Fresh untuk baseline
    $baselineServices = [
        'Paket Starter Branding' => ['category' => 'Starter Branding', 'price' => 150000.00],
        'Paket E-Commerce Grow' => ['category' => 'E-Commerce Grow', 'price' => 300000.00],
        'Paket Full Marketing Scale' => ['category' => 'Full Marketing Scale', 'price' => 500000.00],
        'Desain Logo & Identitas Brand' => ['category' => 'Starter Branding', 'price' => 750000.00],
        'Website E-Commerce & Pemesanan' => ['category' => 'E-Commerce Grow', 'price' => 1500000.00],
        'Social Media Management Terpadu' => ['category' => 'Full Marketing Scale', 'price' => 1200000.00]
    ];
    foreach ($baselineServices as $sName => $bInfo) {
        $serviceRanking[$sName] = [
            'name' => $sName,
            'category' => $bInfo['category'],
            'order_count' => 0,
            'total_quantity' => 0,
            'revenue' => 0.0
        ];
    }

    foreach ($biOrderItems as $it) {
        $cDate = (string)($it['created_at'] ?? '');
        if (!$inPeriod($cDate, $curStart, $curEnd)) {
            continue;
        }
        $sName = trim((string)$it['service_name']);
        if ($sName === '') continue;
        if (!isset($serviceRanking[$sName])) {
            $serviceRanking[$sName] = [
                'name' => $sName,
                'category' => 'Layanan Digital',
                'order_count' => 0,
                'total_quantity' => 0,
                'revenue' => 0.0
            ];
        }
        $serviceRanking[$sName]['order_count']++;
        $serviceRanking[$sName]['total_quantity'] += (int)($it['quantity'] ?? 1);
        if ($isBiOrderSuccess($it)) {
            $serviceRanking[$sName]['revenue'] += (float)($it['subtotal'] ?? 0);
        }
    }

    // Urutkan ranking: pendapatan tertinggi, lalu jumlah pesanan terbanyak
    usort($serviceRanking, function($a, $b) {
        if ($b['revenue'] !== $a['revenue']) {
            return ($b['revenue'] > $a['revenue']) ? 1 : -1;
        }
        return $b['order_count'] <=> $a['order_count'];
    });

    $maxServiceRev = !empty($serviceRanking) ? max(array_column($serviceRanking, 'revenue')) : 0.0;
    if ($maxServiceRev <= 0) $maxServiceRev = 1.0;

    // 6. Performa Pelanggan (Registrasi vs Bertransaksi, Repeat Order Rate)
    $totalRegisteredCustomers = (int)($customerCount ?? 0);
    if ($totalRegisteredCustomers <= 0 && !empty($mitraBusinesses)) {
        $totalRegisteredCustomers = count($mitraBusinesses);
    }
    // Pelanggan unik yang berhasil bertransaksi
    $successfulCustomerIds = [];
    $customerOrderCounts = [];
    foreach ($allSuccessfulOrders as $o) {
        $cId = (string)($o['customer_id'] ?? $o['customer_email'] ?? '');
        if ($cId !== '') {
            $successfulCustomerIds[$cId] = true;
            $customerOrderCounts[$cId] = ($customerOrderCounts[$cId] ?? 0) + 1;
        }
    }
    $activeBuyingCustomersCount = count($successfulCustomerIds);
    $repeatCustomersCount = 0;
    foreach ($customerOrderCounts as $cid => $cnt) {
        if ($cnt >= 2) {
            $repeatCustomersCount++;
        }
    }
    $repeatOrderRate = $activeBuyingCustomersCount > 0 ? round(($repeatCustomersCount / $activeBuyingCustomersCount) * 100, 1) : 0.0;
    $customerConversionRate = $totalRegisteredCustomers > 0 ? round(($activeBuyingCustomersCount / $totalRegisteredCustomers) * 100, 1) : 0.0;

    // 7. Performa Website & Interaksi Nyata dari Database
    // Total Pembaca Artikel
    $totalArticleViews = 0;
    $artViewCol = 'views_count';
    $chkCol = $conn->query("SHOW COLUMNS FROM articles LIKE 'views_count'");
    if (!$chkCol || $chkCol->num_rows === 0) {
        $artViewCol = 'views';
    }
    $artVRes = $conn->query("SELECT COALESCE(SUM({$artViewCol}), 0) AS total_views, COUNT(*) AS count_published FROM articles WHERE is_published = 1 OR is_published IS NULL");
    if ($artVRes && $avRow = $artVRes->fetch_assoc()) {
        $totalArticleViews = (int)$avRow['total_views'];
    }

    // Konsultasi & Pesan Chat
    $totalConsultationsCount = count($allConsultations);
    $totalChatMessagesCount = 0;
    $msgQ = $conn->query("SELECT COUNT(*) AS total_msg FROM consultation_messages");
    if ($msgQ && $mRow = $msgQ->fetch_assoc()) {
        $totalChatMessagesCount = (int)$mRow['total_msg'];
    }

    // Kelengkapan Profil Usaha Mitra
    $completedProfiles = 0;
    foreach ($mitraBusinesses as $mb) {
        if (!empty($mb['business_name'])) {
            $completedProfiles++;
        }
    }
    $profileCompletionRate = count($mitraBusinesses) > 0 ? round(($completedProfiles / count($mitraBusinesses)) * 100, 1) : 0.0;

    // 8. Susun Ringkasan Komparasi Periode
    $comparisonRows = [
        [
            'metric' => 'Total Pendapatan Bersih',
            'icon' => 'fa-wallet',
            'cur' => 'Rp ' . number_format($curRevenue, 0, ',', '.'),
            'prev' => 'Rp ' . number_format($prevRevenue, 0, ',', '.'),
            'growth' => $revenueGrowth,
            'note' => 'Hanya dari transaksi terkonfirmasi berhasil'
        ],
        [
            'metric' => 'Jumlah Transaksi Berhasil',
            'icon' => 'fa-circle-check',
            'cur' => number_format($curSuccessCount, 0, ',', '.') . ' transaksi',
            'prev' => number_format($prevSuccessCount, 0, ',', '.') . ' transaksi',
            'growth' => $ordersGrowth,
            'note' => 'Pesanan lunas atau selesai dikerjakan'
        ],
        [
            'metric' => 'Rata-rata Nilai Transaksi (AOV)',
            'icon' => 'fa-receipt',
            'cur' => 'Rp ' . number_format($curAov, 0, ',', '.'),
            'prev' => 'Rp ' . number_format($prevAov, 0, ',', '.'),
            'growth' => $aovGrowth,
            'note' => 'Pendapatan dibagi jumlah transaksi berhasil'
        ],
        [
            'metric' => 'Total Pesanan Masuk (Semua Status)',
            'icon' => 'fa-cart-shopping',
            'cur' => number_format($curTotalOrderCount, 0, ',', '.') . ' pesanan',
            'prev' => number_format($prevTotalOrderCount, 0, ',', '.') . ' pesanan',
            'growth' => $totalOrdersGrowth,
            'note' => 'Termasuk pesanan menunggu & sedang diproses'
        ]
    ];

    $biData = [
        'period' => $biPeriod,
        'periodLabel' => $periodLabel,
        'comparisonLabel' => $comparisonLabel,
        'curRevenue' => $curRevenue,
        'prevRevenue' => $prevRevenue,
        'revenueGrowth' => $revenueGrowth,
        'curSuccessCount' => $curSuccessCount,
        'prevSuccessCount' => $prevSuccessCount,
        'ordersGrowth' => $ordersGrowth,
        'curAov' => $curAov,
        'prevAov' => $prevAov,
        'aovGrowth' => $aovGrowth,
        'curTotalOrderCount' => $curTotalOrderCount,
        'totalOrdersGrowth' => $totalOrdersGrowth,
        'totalRegisteredCustomers' => $totalRegisteredCustomers,
        'activeBuyingCustomersCount' => $activeBuyingCustomersCount,
        'repeatCustomersCount' => $repeatCustomersCount,
        'repeatOrderRate' => $repeatOrderRate,
        'customerConversionRate' => $customerConversionRate,
        'tsDaily' => $tsDaily,
        'tsWeekly' => $tsWeekly,
        'tsMonthly' => $tsMonthly,
        'tsYearly' => $tsYearly,
        'statusDist' => $statusDist,
        'paymentDist' => $paymentDist,
        'serviceRanking' => $serviceRanking,
        'maxServiceRev' => $maxServiceRev,
        'totalArticleViews' => $totalArticleViews,
        'totalConsultationsCount' => $totalConsultationsCount,
        'totalChatMessagesCount' => $totalChatMessagesCount,
        'profileCompletionRate' => $profileCompletionRate,
        'completedProfiles' => $completedProfiles,
        'comparisonRows' => $comparisonRows
    ];
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | Admin Balla’ Fresh</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --green-950: #5b8121;
            --green-900: #e48e1e;
            --green-800: #3c5d28;
            --green-700: #527832;
            --green-600: #668c3d;
            --green-100: #edf4e5;
            --green-50: #f6f9f1;
            --cream: #fbfcf8;
            --ink: #263222;
            --muted: #78816f;
            --line: #e7ecdf;
            --white: #fff;
            --amber: #c28b2c;
            --shadow: 0 10px 30px rgba(36, 58, 25, .055);
            --radius: 18px;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: var(--ink);
            background: #f7f9f3;
            font-family: "DM Sans", sans-serif;
            font-size: 13px;
        }
        a { color: inherit; text-decoration: none; }
        button, input { font: inherit; }
        .app { min-height: 100vh; display: grid; grid-template-columns: 235px minmax(0, 1fr); }
        .sidebar {
            position: sticky; top: 0; height: 100vh; max-height: 100vh; padding: 14px 12px;
            display: flex; flex-direction: column; background: var(--green-950); color: #eaf2e2;
            overflow-y: auto; overflow-x: hidden;
        }
        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.22); border-radius: 4px; }
        .brand { display: flex; align-items: center; gap: 10px; padding: 2px 6px 14px; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 6px; flex-shrink: 0; }
        .brand-mark {
            width: 36px; height: 36px; border-radius: 11px; background: #fff;
            display: grid; place-items: center; overflow: hidden; flex: 0 0 auto;
        }
        .brand-mark img { width: 100%; height: 100%; object-fit: contain; }
        .brand-fallback { color: var(--green-800); font-family: "Manrope", sans-serif; font-weight: 800; font-size: 11px; display: none; }
        .brand-name { font: 800 16px/1.1 "Manrope", sans-serif; letter-spacing: -.5px; color: #ffff; }
        .brand-name span { color: #ffff; }
        .brand-caption { color: #b3cba8; font-size: 9px; margin-top: 3px; letter-spacing: 1.2px; text-transform: uppercase; }
        .nav-label { color: #94aa87; font-size: 9px; letter-spacing: 1.3px; text-transform: uppercase; padding: 0 8px; margin: 8px 0 5px; font-weight: 700; flex-shrink: 0; }
        .side-nav { display: grid; gap: 3px; flex-shrink: 0; }
        .side-link {
            display: flex; align-items: center; gap: 9px; padding: 8px 10px;
            border-radius: 9px; color: #d2dfca; font-weight: 600; font-size: 12px; transition: .18s;
        }
        .side-link i { width: 16px; text-align: center; font-size: 13px; color: #a8c18f; flex-shrink: 0; }
        .side-link:hover, .side-link.active { color: #fff; background: rgba(255,255,255,.12); }
        .side-link.active { box-shadow: inset 3px 0 #b7d88b; }
        .side-spacer { flex: 1; min-height: 8px; }
        .side-help { padding: 9px 11px; margin: 8px 2px 6px; border: 1px solid rgba(255,255,255,.12); border-radius: 10px; background: rgba(255,255,255,.055); flex-shrink: 0; }
        .side-help i { color: #b8d891; font-size: 13px; }
        .side-help strong { display: block; margin: 5px 0 2px; font-size: 11px; }
        .side-help p { color: #b7c7ae; font-size: 9.5px; line-height: 1.45; margin: 0; }
        @media (max-height: 760px) {
            .side-help { display: none; }
        }
        .side-bottom { border-top: 1px solid rgba(255,255,255,.12); padding: 9px 4px 2px; display: flex; align-items: center; gap: 8px; flex-shrink: 0; margin-top: auto; }
        .avatar { display: grid; place-items: center; width: 32px; height: 32px; border-radius: 9px; background: #dcebc9; color: var(--green-900); font-weight: 800; font-size: 11px; flex: 0 0 auto; }
        .side-user { min-width: 0; flex: 1; }
        .side-user strong { display: block; font-size: 11.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .side-user small { display: block; color: #adc1a2; font-size: 9.5px; margin-top: 2px; }
        .side-logout { color: #c4d3bc; padding: 6px; border-radius: 8px; }
        .side-logout:hover { background: rgba(255,255,255,.1); color: #fff; }
        .main { min-width: 0; }
        .topbar {
            height: 58px; padding: 0 24px; display: flex; align-items: center; justify-content: space-between;
            gap: 16px; background: rgba(255,255,255,.94); border-bottom: 1px solid var(--line);
        }
        .crumb { color: var(--muted); font-size: 11.5px; }
        .crumb strong { color: var(--green-800); font-weight: 700; }
        .top-actions { display: flex; align-items: center; gap: 9px; }
        .date-chip { display: inline-flex; align-items: center; gap: 6px; color: #5e6b54; background: var(--green-50); border: 1px solid var(--line); padding: 6px 10px; border-radius: 8px; font-size: 11px; }
        .top-avatar { display: flex; align-items: center; gap: 8px; padding-left: 10px; border-left: 1px solid var(--line); }
        .top-avatar .avatar { width: 30px; height: 30px; border-radius: 50%; font-size: 10.5px; }
        .top-avatar strong { display: block; font-size: 11px; }
        .top-avatar small { display: block; margin-top: 1px; color: var(--muted); font-size: 9.5px; }
        .content { max-width: 1400px; margin: 0 auto; padding: 20px 24px 34px; }
        .welcome-row { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; margin-bottom: 16px; }
        .eyebrow { color: var(--green-700); font-size: 9px; letter-spacing: 1.4px; font-weight: 800; text-transform: uppercase; }
        h1 { font: 800 clamp(19px, 1.8vw, 24px)/1.2 "Manrope", sans-serif; letter-spacing: -.5px; margin: 5px 0 4px; color: #e48e1e; }
        .welcome-row p { margin: 0; color: var(--muted); font-size: 12px; line-height: 1.5; }
        .welcome-tag { display: inline-flex; align-items: center; gap: 6px; border-radius: 100px; padding: 6px 11px; background: var(--green-100); color: var(--green-800); font-size: 10.5px; font-weight: 700; white-space: nowrap; }
        .welcome-tag span { width: 6px; height: 6px; background: #7aa84c; border-radius: 50%; }
        .stats-grid { display: grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap: 12px; margin-bottom: 16px; }
        .stat-card { background: var(--white); border: 1px solid var(--line); border-radius: 13px; padding: 13px 15px; box-shadow: 0 2px 8px rgba(36,58,25,.025); min-width: 0; }
        .stat-top { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
        .stat-icon { display: grid; place-items: center; width: 34px; height: 34px; border-radius: 9px; background: var(--green-100); color: var(--green-700); font-size: 14px; }
        .stat-icon.amber { background: #fbf1df; color: #ad7b25; }
        .stat-icon.sage { background: #e9f1ed; color: #4f7968; }
        .stat-icon.lilac { background: #f0edf8; color: #7865a4; }
        .stat-kicker { color: var(--muted); font-size: 10.5px; font-weight: 600; }
        .stat-value { margin-top: 8px; font: 800 22px/1 "Manrope", sans-serif; color: #e48e1e; letter-spacing: -.5px; }
        .stat-foot { margin-top: 5px; font-size: 10px; color: var(--muted); line-height: 1.4; }
        .stat-foot i { color: var(--green-600); margin-right: 4px; }
        .panel-grid { display: grid; grid-template-columns: minmax(0, 1.55fr) minmax(280px, .85fr); gap: 14px; align-items: start; }
        .panel { background: #fff; border: 1px solid var(--line); border-radius: 13px; box-shadow: 0 2px 8px rgba(36,58,25,.025); min-width: 0; }
        .panel-head { padding: 13px 16px 11px; display: flex; align-items: center; justify-content: space-between; gap: 10px; border-bottom: 1px solid #eff2e9; }
        .panel-head h2 { font: 800 13.5px/1.35 "Manrope", sans-serif; margin: 0; color: var(--green-950); }
        .panel-head p { margin: 2px 0 0; color: var(--muted); font-size: 10.5px; }
        .panel-body { padding: 14px 16px 16px; }
        .search-form { display: flex; gap: 7px; width: min(100%, 360px); }
        .search-box { display: flex; align-items: center; gap: 7px; flex: 1; min-width: 0; border: 1px solid var(--line); border-radius: 8px; padding: 0 10px; background: #fcfdf9; }
        .search-box i { color: #8b9780; font-size: 11px; }
        .search-box input { width: 100%; min-width: 0; padding: 8px 0; border: 0; outline: 0; background: transparent; color: var(--ink); font-size: 11.5px; }
        .btn { border: 0; display: inline-flex; align-items: center; justify-content: center; gap: 6px; border-radius: 8px; padding: 8px 12px; background: var(--green-700); color: #fff; font-size: 11.5px; font-weight: 700; cursor: pointer; transition: .18s; }
        .btn:hover { background: var(--green-900); }
        .btn-light { background: var(--green-50); color: var(--green-800); border: 1px solid var(--line); }
        .btn-light:hover { background: var(--green-100); }
        .table-wrap { overflow-x: auto; }
        table { border-collapse: collapse; width: 100%; min-width: 570px; }
        th { padding: 9px 12px; text-align: left; color: #849078; background: #fafbf7; font-size: 9.5px; text-transform: uppercase; letter-spacing: .7px; font-weight: 800; white-space: nowrap; }
        td { padding: 10px 12px; border-top: 1px solid #f0f2eb; font-size: 11.5px; vertical-align: top; }
        td:first-child, th:first-child { padding-left: 16px; }
        td:last-child, th:last-child { padding-right: 16px; }
        .customer-cell { display: flex; align-items: center; gap: 9px; min-width: 170px; }
        .customer-avatar { display: grid; place-items: center; width: 32px; height: 32px; border-radius: 9px; background: var(--green-100); color: var(--green-800); font-size: 11px; font-weight: 800; flex: 0 0 auto; }
        .customer-cell strong { display: block; font-size: 11.5px; color: #34412d; }
        .customer-cell small { display: block; color: #87907e; font-size: 9.5px; margin-top: 2px; }
        .muted-cell { color: #77816d; max-width: 180px; line-height: 1.45; }
        .status-pill { display: inline-flex; align-items: center; gap: 4px; border-radius: 100px; padding: 5px 8px; background: #edf5e7; color: #557d2d; font-size: 9.5px; font-weight: 700; white-space: nowrap; }
        .status-pill i { font-size: 6px; }
        .empty { padding: 28px 16px; text-align: center; color: var(--muted); line-height: 1.6; }
        .empty i { display: block; margin-bottom: 8px; color: #a9bb96; font-size: 22px; }
        .notice { margin: 0 16px 14px; border: 1px solid #f0dfb8; background: #fff9eb; color: #805e20; padding: 9px 12px; border-radius: 8px; font-size: 11.5px; line-height: 1.45; }
        .activity-list { display: grid; }
        .activity-item { display: flex; align-items: flex-start; gap: 11px; padding: 13px 0; border-bottom: 1px solid #f0f2eb; }
        .activity-item:first-child { padding-top: 0; }
        .activity-item:last-child { border-bottom: 0; padding-bottom: 0; }
        .activity-icon { display: grid; place-items: center; width: 34px; height: 34px; flex: 0 0 auto; border-radius: 11px; background: var(--green-100); color: var(--green-700); }
        .activity-item strong { display: block; font-size: 12px; line-height: 1.5; }
        .activity-item p { margin: 4px 0 0; color: var(--muted); font-size: 11px; line-height: 1.55; overflow-wrap: anywhere; }
        .activity-id { margin-left: auto; color: #8a9480; font-size: 10px; white-space: nowrap; }
        .ops-grid { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 12px; }
        .ops-card { border: 1px solid var(--line); background: #fcfdf9; border-radius: 14px; padding: 15px; }
        .ops-card i { color: var(--green-700); font-size: 16px; }
        .ops-card strong { display: block; margin: 10px 0 5px; font-size: 12px; }
        .ops-card p { color: var(--muted); font-size: 11px; line-height: 1.6; margin: 0 0 10px; }
        .coming { display: inline-block; padding: 5px 7px; background: #f3f0e6; color: #92753d; border-radius: 6px; font-size: 9px; font-weight: 800; letter-spacing: .3px; text-transform: uppercase; }
        .footnote { margin-top: 20px; padding: 14px 17px; display: flex; gap: 10px; align-items: flex-start; border: 1px solid #e5eddb; border-radius: 13px; background: #f3f8ec; color: #617452; font-size: 11px; line-height: 1.7; }
        .footnote i { margin-top: 2px; color: var(--green-700); }
        .mobile-menu { display: none; border: 1px solid var(--line); background: #fff; color: var(--green-800); width: 40px; height: 40px; border-radius: 11px; }
        .mobile-overlay { display: none; }
        @media (max-width: 1180px) {
            .stats-grid { grid-template-columns: repeat(2,minmax(0,1fr)); }
            .panel-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 760px) {
            .app { display: block; }
            .sidebar { position: fixed; z-index: 100; left: 0; top: 0; width: 260px; transform: translateX(-105%); transition: transform .22s ease; box-shadow: 20px 0 60px rgba(0,0,0,.15); }
            .sidebar.open { transform: translateX(0); }
            .mobile-overlay.show { display: block; position: fixed; inset: 0; z-index: 90; background: rgba(20,35,16,.4); }
            .topbar { height: 68px; padding: 0 17px; }
            .mobile-menu { display: inline-grid; place-items: center; }
            .crumb { margin-right: auto; }
            .date-chip { display: none; }
            .top-avatar { padding-left: 0; border-left: 0; }
            .top-avatar .avatar { width: 32px; height: 32px; }
            .top-avatar .top-user-copy { display: none; }
            .content { padding: 24px 16px 32px; }
            .welcome-row { align-items: flex-start; flex-direction: column; }
            .stats-grid { gap: 10px; }
            .stat-card { padding: 15px; border-radius: 15px; }
            .stat-icon { width: 37px; height: 37px; border-radius: 12px; }
            .stat-value { font-size: 25px; }
            .panel-head { padding: 17px 16px; align-items: flex-start; flex-direction: column; }
            .panel-body { padding: 16px; }
            .notice { margin: 0 16px 14px; }
            .search-form { width: 100%; }
            .ops-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 390px) {
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .stat-card { padding: 12px; }
            .stat-value { font-size: 22px; }
            .ops-grid { grid-template-columns: 1fr; }
            .welcome-tag { white-space: normal; }
        }

        /* ===== POPUP KONFIRMASI LOGOUT ADMIN ===== */

.side-logout {
    border: none;
    background: transparent;
    cursor: pointer;
    font-size: 15px;
}

.logout-modal {
    position: fixed;
    inset: 0;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(25, 38, 20, 0.55);
    backdrop-filter: blur(5px);

    opacity: 0;
    visibility: hidden;
    transition: .25s ease;
}

.logout-modal.show {
    opacity: 1;
    visibility: visible;
}

.logout-dialog {
    width: 100%;
    max-width: 400px;
    padding: 32px 27px;
    border-radius: 22px;
    background: #fff;
    text-align: center;
    box-shadow: 0 20px 60px rgba(0,0,0,.16);

    transform: translateY(15px) scale(.97);
    transition: .25s ease;
}

.logout-modal.show .logout-dialog {
    transform: translateY(0) scale(1);
}

.logout-icon {
    width: 65px;
    height: 65px;
    margin: 0 auto 19px;
    display: grid;
    place-items: center;
    border-radius: 20px;
    background: #edf4e5;
    color: #5b8121;
    font-size: 25px;
}

.logout-dialog h2 {
    margin: 0 0 10px;
    color: #34452b;
    font: 800 20px "Manrope", sans-serif;
}

.logout-dialog p {
    margin: 0;
    color: #78816f;
    font-size: 13px;
    line-height: 1.7;
}

.logout-actions {
    display: flex;
    gap: 10px;
    margin-top: 25px;
}

.logout-actions button {
    flex: 1;
    padding: 12px 10px;
    border-radius: 11px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    transition: .2s;
}

.logout-cancel {
    border: 1px solid #e5ebdf;
    background: #f7f9f3;
    color: #526447;
}

.logout-cancel:hover {
    background: #edf4e5;
}

.logout-confirm {
    border: 1px solid #5b8121;
    background: #5b8121;
    color: white;
}

.logout-confirm:hover {
    background: #45671a;
}

@media(max-width: 420px) {
    .logout-dialog {
        padding: 27px 20px;
    }

    .logout-actions {
        flex-direction: column-reverse;
    }
}
.logout-actions form {
    flex: 1;
    display: flex;
}

.logout-actions form button {
    width: 100%;
}

        /* ===== PENGELOLAAN PESANAN STYLING OPTIMAL ===== */
        .order-summary-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;padding:18px 22px 0}
        .order-mini-stat{border:1px solid var(--line);background:#fcfdf9;border-radius:13px;padding:13px}
        .order-mini-stat strong{display:block;font:800 23px "Manrope",sans-serif;color:var(--ink);margin-top:7px}
        .order-mini-stat span{font-size:11px;color:var(--muted)}

        .order-filter-toolbar{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:16px;background:#f8fafc;padding:12px 16px;border-radius:12px;border:1px solid #e2e8f0}
        .filter-controls{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
        .filter-group{display:flex;flex-direction:column;gap:3px}
        .filter-group label{font-size:10.5px;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:.04em}
        .filter-group select{border:1px solid #cbd5e1;border-radius:8px;padding:7px 10px;font-size:12px;font-weight:700;background:#fff;color:#0f172a;cursor:pointer}
        .filter-group select:focus{outline:none;border-color:var(--green-700)}
        .btn-reset-filter{display:inline-flex;align-items:center;gap:5px;background:#fff;border:1px solid #cbd5e1;border-radius:8px;padding:7px 12px;font-size:11.5px;font-weight:700;color:#64748b;text-decoration:none;transition:all .2s;align-self:flex-end}
        .btn-reset-filter:hover{background:#f1f5f9;color:#0f172a}

        .order-table{min-width:1250px;width:100%;border-collapse:separate;border-spacing:0}
        .order-table th{font-size:10.5px;text-transform:uppercase;letter-spacing:.05em;background:#f8fafc;color:#475569;padding:11px 12px;border-bottom:2px solid #e2e8f0;white-space:nowrap;font-weight:800}
        .order-table td{padding:12px;border-bottom:1px solid #eef2f6;vertical-align:middle;font-size:12px}

        /* Kelompok Tanggal Separator */
        .date-group-row td{padding:12px 0 6px 0;background:transparent;border:none}
        .date-group-bar{display:flex;align-items:center;justify-content:space-between;padding:8px 14px;border-radius:9px;font-weight:800;font-size:11.5px;letter-spacing:.03em}
        .date-group-left{display:flex;align-items:center;gap:8px}
        .date-group-badge{font-size:10.5px;font-weight:800;padding:2px 9px;border-radius:999px}

        .date-group-today{background:#edf7e9;border:1px solid #c9e4bc;color:#1e3a17}
        .date-group-today .date-group-badge{background:#2e5926;color:#fff}
        .date-group-yesterday{background:#f0fdfa;border:1px solid #ccfbf1;color:#0f766e}
        .date-group-yesterday .date-group-badge{background:#0d9488;color:#fff}
        .date-group-past{background:#f8fafc;border:1px solid #e2e8f0;color:#475569}
        .date-group-past .date-group-badge{background:#64748b;color:#fff}

        /* Kolom 1: Nomor Pesanan */
        .order-number-pill{font-family:monospace;font-size:11.5px;font-weight:800;color:#1e3a17;background:#f4f8f2;border:1px solid #d4eac9;border-radius:6px;padding:3px 7px;display:inline-block;word-break:break-all}
        .order-id-sub{display:block;font-size:10.5px;color:var(--muted);margin-top:3px;font-weight:600}

        /* Kolom 2: Tanggal Masuk */
        .order-time-text{display:block;font-size:12.5px;font-weight:800;color:#0f172a;white-space:nowrap}
        .order-date-text{display:block;font-size:11px;color:var(--muted);margin-top:2px;white-space:nowrap}

        /* Kolom 3: Pelanggan & Usaha */
        .cust-name{font-size:13px;font-weight:800;color:#0f172a;display:block}
        .cust-biz-tag{display:inline-flex;align-items:center;gap:4px;font-size:10.5px;font-weight:700;color:#166534;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:5px;padding:2px 7px;margin:3px 0;max-width:210px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .cust-wa-link{display:inline-flex;align-items:center;gap:4px;font-size:11px;color:#16a34a;text-decoration:none;font-weight:700;margin-top:2px}
        .cust-wa-link:hover{text-decoration:underline}
        .cust-email-sub{display:block;font-size:10.5px;color:#64748b;margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:200px}

        /* Kolom 4: Layanan */
        .service-chip-list{display:flex;flex-direction:column;gap:4px;max-width:230px}
        .service-chip{display:flex;align-items:center;justify-content:space-between;gap:6px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:3px 7px;font-size:11px}
        .service-chip-name{font-weight:700;color:#334155;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:170px}
        .service-chip-qty{font-weight:800;color:#2e5926;background:#edf7e9;padding:1px 5px;border-radius:4px;font-size:10px;white-space:nowrap}
        .btn-more-services{background:none;border:1px dashed #cbd5e1;border-radius:6px;color:#0284c7;font-size:10.5px;font-weight:700;padding:3px 6px;cursor:pointer;text-align:left;transition:all .2s;width:100%}
        .btn-more-services:hover{background:#f0f9ff;border-color:#0284c7}

        /* Kolom 5: Catatan Pelanggan */
        .note-card-box{background:#fffbeb;border:1px solid #fef3c7;border-left:3px solid #f59e0b;border-radius:6px;padding:5px 8px;font-size:11px;color:#78350f;line-height:1.4;max-width:200px;overflow-wrap:break-word}
        .btn-note-detail{background:none;border:none;color:#b45309;font-weight:800;font-size:10px;cursor:pointer;text-decoration:underline;padding:0;display:inline;margin-left:4px}
        .note-empty-pill{font-size:11px;color:#94a3b8;font-style:italic}

        /* Kolom 6: Total Tagihan */
        .order-total-price{display:block;font-size:13.5px;font-weight:800;color:#0f172a;white-space:nowrap}
        .order-total-qty{display:block;font-size:10.5px;color:#64748b;margin-top:2px;white-space:nowrap}

        /* Kolom 7: Pembayaran */
        .pay-badge{display:inline-flex;align-items:center;gap:4px;border-radius:6px;padding:3px 8px;font-size:10.5px;font-weight:800;white-space:nowrap}
        .pay-badge.paid{background:#dcfce7;color:#166534;border:1px solid #bbf7d0}
        .pay-badge.pending{background:#fef3c7;color:#92400e;border:1px solid #fde68a}
        .pay-badge.failed{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}
        .pay-badge.expired{background:#f1f5f9;color:#64748b;border:1px solid #cbd5e1}
        .pay-channel-pill{display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:800;color:#002b49;background:#e0f2fe;padding:2px 6px;border-radius:4px;margin-top:3px;letter-spacing:.02em}
        .pay-midtrans-id{font-family:monospace;font-size:10px;color:#64748b;margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:140px}
        .pay-settle-date{font-size:10px;color:#166534;font-weight:700;margin-top:2px;white-space:nowrap}

        /* Kolom 8: Progres */
        .status-pill{display:inline-flex;align-items:center;gap:4px;border-radius:100px;padding:3px 7px;font-size:9.5px;font-weight:800;white-space:nowrap}
        .status-pill.pending{background:#fff4df;color:#966719}
        .status-pill.processing{background:#eaf2fb;color:#3d6e9e}
        .status-pill.completed{background:#e8f5e9;color:#347448}
        .status-pill.cancelled{background:#fcebea;color:#a34b45}
        .status-compact-form{display:flex;gap:4px;align-items:center;margin-top:5px}
        .status-compact-form select{border:1px solid var(--line);border-radius:6px;padding:4px 6px;font-size:10.5px;background:#fff;color:var(--ink)}
        .status-compact-form button{border:none;border-radius:6px;background:var(--green-700);color:#fff;font-size:10.5px;font-weight:700;padding:4px 7px;cursor:pointer;transition:all .2s}
        .status-compact-form button:hover{background:var(--green-800)}

        /* Kolom 9: Aksi */
        .order-action-stack{display:flex;flex-direction:column;gap:4px;min-width:95px}
        .btn-act-detail{display:inline-flex;align-items:center;justify-content:center;gap:4px;background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;border-radius:6px;padding:4px 7px;font-size:10.5px;font-weight:700;cursor:pointer;transition:all .2s;text-decoration:none}
        .btn-act-detail:hover{background:#dbeafe}
        .btn-act-receipt{display:inline-flex;align-items:center;justify-content:center;gap:4px;background:#f8fafc;color:#334155;border:1px solid #cbd5e1;border-radius:6px;padding:4px 7px;font-size:10.5px;font-weight:700;cursor:pointer;transition:all .2s;text-decoration:none}
        .btn-act-receipt:hover{background:#e2e8f0;color:#0f172a}
        .btn-act-invoice{display:inline-flex;align-items:center;justify-content:center;gap:4px;background:#3a6932;color:#fff;border-radius:6px;padding:4px 7px;font-size:10.5px;font-weight:700;text-decoration:none;transition:all .2s}
        .btn-act-invoice:hover{background:#24461f}

        .order-notice{margin:15px 22px 0;padding:11px 13px;border-radius:10px;font-size:12px;line-height:1.5}
        .order-notice.success{background:#edf7e9;border:1px solid #d8e9ca;color:#42652f}
        .order-notice.error{background:#fff0ee;border:1px solid #f0d3cf;color:#99443b}

        /* Modal Detail Pesanan Styling */
        .modal-overlay{display:none;position:fixed;inset:0;background:rgba(15,23,42,0.65);z-index:99999;backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:20px}
        .modal-dialog-box{background:#fff;border-radius:20px;max-width:740px;width:100%;max-height:90vh;overflow-y:auto;box-shadow:0 25px 50px -12px rgba(0,0,0,0.25);border:1px solid #e2e8f0}
        .modal-dialog-header{background:#f8fafc;padding:18px 24px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;z-index:10}
        .modal-dialog-body{padding:24px}
        .modal-section-card{background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:16px;margin-bottom:16px}
        .modal-section-title{font-size:11px;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:12px;display:flex;align-items:center;gap:6px}
        .modal-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:12.5px}
        .modal-info-row{display:flex;flex-direction:column;gap:2px}
        .modal-info-row label{font-size:10.5px;color:#64748b;font-weight:600}
        .modal-info-row strong{color:#0f172a;font-weight:700}
        .modal-services-table{width:100%;border-collapse:collapse;margin-top:8px;font-size:12px}
        .modal-services-table th{background:#eef2f6;padding:8px 10px;text-align:left;font-weight:800;color:#334155;border-bottom:1px solid #cbd5e1}
        .modal-services-table td{padding:8px 10px;border-bottom:1px solid #e2e8f0;color:#1e293b}
        .modal-total-highlight{font-size:18px;font-weight:800;color:#166534}

        @media(max-width:900px){.order-summary-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.modal-info-grid{grid-template-columns:1fr}}
        @media(max-width:760px){.order-summary-grid{padding:15px 16px 0}.order-notice{margin:14px 16px 0}.order-filter-toolbar{flex-direction:column;align-items:stretch}}

        /* CMS Per-Section Styling */
        .cms-nav-pills {
            display: flex;
            gap: 7px;
            flex-wrap: wrap;
            margin-bottom: 18px;
            background: #ffffff;
            padding: 8px 12px;
            border-radius: 12px;
            border: 1px solid var(--line);
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
        }
        .cms-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11.5px;
            font-weight: 700;
            color: #475569;
            padding: 6px 12px;
            border-radius: 8px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .cms-pill:hover, .cms-pill.active {
            background: var(--green);
            color: #fff;
            border-color: var(--green);
        }
        .admin-team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 14px;
        }
        .admin-team-card {
            background: #fbfdf9;
            border: 1px solid #dce9bd;
            border-radius: 14px;
            padding: 14px;
            display: grid;
            gap: 10px;
            position: relative;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        }
        .admin-team-card.owner-card {
            background: #fffdf5;
            border-color: #fef08a;
            box-shadow: 0 3px 10px rgba(234, 179, 8, 0.06);
        }
        .admin-team-photo-preview {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 2.5px solid var(--green);
            background: #ffffff;
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
        }
        .admin-badge-owner {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            background: #fef08a;
            color: #854d0e;
            padding: 3px 9px;
            border-radius: 999px;
            width: fit-content;
        }
        .admin-badge-member {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            background: var(--green-soft);
            color: var(--green);
            padding: 3px 9px;
            border-radius: 999px;
            width: fit-content;
        }
        .cms-section-card {
            margin-bottom: 18px;
            border-radius: 14px;
            border: 1px solid var(--line);
            background: #ffffff;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        }
        .cms-section-card .panel-head {
            background: #fafbf8;
            border-bottom: 1px solid var(--line);
            padding: 12px 18px;
        }
        .cms-section-card .panel-body {
            padding: 16px 18px 20px;
        }

        /* ROOM CHAT & KONSULTASI ADMIN */
        .admin-chat-grid {
            display: grid;
            grid-template-columns: 330px minmax(0, 1fr);
            gap: 16px;
            align-items: stretch;
        }
        @media (max-width: 992px) {
            .admin-chat-grid {
                grid-template-columns: 1fr;
            }
        }
        .consult-list-box {
            background: #ffffff;
            border: 1px solid var(--line);
            border-radius: 14px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 560px;
            max-height: 560px;
        }
        .consult-list-head {
            padding: 12px 16px;
            background: #f8faf5;
            border-bottom: 1px solid var(--line);
        }
        .consult-list-body {
            overflow-y: auto;
            padding: 10px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .consult-card-item {
            padding: 11px 13px;
            border-radius: 10px;
            border: 1px solid var(--line);
            background: #ffffff;
            display: block;
            text-decoration: none;
            color: inherit;
            transition: all 0.2s ease;
        }
        .consult-card-item:hover {
            border-color: #5b8121;
            background: #fcfdf9;
        }
        .consult-card-item.active {
            border-color: #5b8121;
            background: #f7faf2;
            box-shadow: 0 3px 10px rgba(91, 129, 33, 0.08);
        }
        .consult-card-item.status-pending-acc {
            border-left: 3.5px solid #e48e1e;
        }
        .chat-room-desk {
            background: #ffffff;
            border: 1px solid var(--line);
            border-radius: 14px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: 560px;
        }
        .chat-room-head {
            padding: 12px 18px;
            background: #f8faf5;
            border-bottom: 1px solid var(--line);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .chat-room-scroll {
            flex: 1;
            padding: 16px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 10px;
            background: #fafbf8;
        }
        .chat-room-msg {
            display: flex;
            flex-direction: column;
            max-width: 82%;
        }
        .chat-room-msg.mitra {
            align-self: flex-start;
        }
        .chat-room-msg.admin {
            align-self: flex-end;
            align-items: flex-end;
        }
        .chat-bubble {
            padding: 10px 14px;
            font-size: 12.5px;
            line-height: 1.5;
            word-break: break-word;
        }
        .chat-bubble.mitra-bubble {
            background: #ffffff;
            color: #1e293b;
            border: 1px solid #e2e8f0;
            border-radius: 14px 14px 14px 4px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.02);
        }
        .chat-bubble.admin-bubble {
            background: #5b8121;
            color: #ffffff;
            border-radius: 14px 14px 4px 14px;
            box-shadow: 0 2px 8px rgba(91, 129, 33, 0.16);
        }

        /* Lampiran di dalam Bubble Chat */
        .chat-attachments-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 8px;
        }
        .chat-att-card {
            border-radius: 10px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .chat-att-card.chat-att-image {
            max-width: 270px;
        }
        .chat-thumb-img {
            max-width: 260px;
            max-height: 220px;
            width: auto;
            height: auto;
            object-fit: contain;
            border-radius: 8px;
            background: #ffffff;
            display: block;
            border: 1px solid rgba(0,0,0,0.08);
            cursor: pointer;
            transition: transform 0.18s ease;
        }
        .chat-thumb-img:hover {
            transform: scale(1.02);
        }
        .chat-player-video {
            max-width: 280px;
            max-height: 220px;
            width: 100%;
            border-radius: 8px;
            background: #000000;
            display: block;
        }
        .chat-att-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            font-size: 11px;
            padding: 4px 6px;
        }
        .chat-att-name {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .chat-att-size {
            font-size: 10px;
            opacity: 0.85;
            white-space: nowrap;
        }
        .chat-att-dl {
            color: inherit;
            text-decoration: none;
            padding: 2px 6px;
            border-radius: 4px;
            opacity: 0.85;
            transition: opacity 0.2s;
        }
        .chat-att-dl:hover {
            opacity: 1;
        }
        .chat-att-doc {
            display: flex;
            flex-direction: row;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 10px;
            min-width: 230px;
            max-width: 340px;
        }
        .chat-doc-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: grid;
            place-items: center;
            font-size: 18px;
            flex-shrink: 0;
        }
        .chat-bubble.mitra-bubble .chat-att-doc {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #1e293b;
        }
        .chat-bubble.mitra-bubble .chat-doc-icon.pdf { background: #fee2e2; color: #dc2626; }
        .chat-bubble.mitra-bubble .chat-doc-icon.word { background: #dbeafe; color: #2563eb; }
        .chat-bubble.mitra-bubble .chat-doc-icon.excel { background: #dcfce7; color: #16a34a; }
        .chat-bubble.mitra-bubble .chat-doc-icon.powerpoint { background: #ffedd5; color: #ea580c; }
        .chat-bubble.mitra-bubble .chat-doc-icon.text { background: #f1f5f9; color: #475569; }
        .chat-bubble.mitra-bubble .chat-doc-icon.default { background: #f1f5f9; color: #64748b; }
        .chat-bubble.mitra-bubble .btn-chat-doc {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            color: #334155;
            text-decoration: none;
            font-size: 11px;
            font-weight: 700;
            padding: 5px 9px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.2s;
        }
        .chat-bubble.mitra-bubble .btn-chat-doc:hover {
            background: #f1f5f9;
            color: #0f172a;
        }

        .chat-bubble.admin-bubble .chat-att-doc {
            background: rgba(255, 255, 255, 0.16);
            border: 1px solid rgba(255, 255, 255, 0.25);
            color: #ffffff;
        }
        .chat-bubble.admin-bubble .chat-doc-icon {
            background: rgba(255, 255, 255, 0.22);
            color: #ffffff;
        }
        .chat-bubble.admin-bubble .btn-chat-doc {
            background: rgba(255, 255, 255, 0.22);
            border: 1px solid rgba(255, 255, 255, 0.38);
            color: #ffffff;
            text-decoration: none;
            font-size: 11px;
            font-weight: 700;
            padding: 5px 9px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: background 0.2s;
        }
        .chat-bubble.admin-bubble .btn-chat-doc:hover {
            background: rgba(255, 255, 255, 0.35);
        }

        .chat-doc-info {
            flex: 1;
            min-width: 0;
        }
        .chat-doc-title {
            display: block;
            font-size: 12px;
            font-weight: 700;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .chat-doc-sub {
            display: block;
            font-size: 10px;
            opacity: 0.82;
            margin-top: 2px;
        }
        .chat-doc-actions {
            display: flex;
            align-items: center;
            gap: 5px;
            flex-shrink: 0;
        }

        /* Preview Area File Input Admin */
        .admin-file-preview-wrap {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding: 8px 10px;
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            margin-bottom: 8px;
        }
        .admin-file-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 8px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 11px;
            color: #334155;
            max-width: 220px;
        }
        .admin-file-chip span {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .admin-file-chip button {
            background: none;
            border: none;
            color: #dc2626;
            cursor: pointer;
            padding: 0 2px;
            font-size: 14px;
            line-height: 1;
        }

        /* Lightbox Image Modal */
        .chat-lightbox {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.82);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 99999;
            padding: 20px;
        }
        .chat-lightbox.show {
            display: flex;
        }
        .chat-lightbox-inner {
            max-width: 90vw;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }
        .chat-lightbox-img {
            max-width: 85vw;
            max-height: 80vh;
            object-fit: contain;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            background: #111;
        }
        .chat-lightbox-bar {
            display: flex;
            align-items: center;
            gap: 12px;
            background: rgba(255,255,255,0.18);
            backdrop-filter: blur(8px);
            padding: 7px 16px;
            border-radius: 99px;
            color: #fff;
            font-size: 12px;
        }
        .chat-lightbox-bar a, .chat-lightbox-bar button {
            color: #fff;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 13px;
            text-decoration: none;
        }
        .chat-time-meta {
            font-size: 10px;
            margin-top: 3px;
            color: #94a3b8;
        }
        .chat-room-footer {
            padding: 12px 16px;
            background: #ffffff;
            border-top: 1px solid var(--line);
        }
        .badge-acc-pending {
            background: #fef3c7;
            color: #b45309;
            font-weight: 800;
            font-size: 10px;
            padding: 3px 9px;
            border-radius: 99px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .badge-acc-approved {
            background: #dcfce7;
            color: #15803d;
            font-weight: 800;
            font-size: 10px;
            padding: 3px 9px;
            border-radius: 99px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .badge-acc-completed {
            background: #f1f5f9;
            color: #64748b;
            font-weight: 800;
            font-size: 10px;
            padding: 3px 9px;
            border-radius: 99px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .policy-edit-box {
            background: #ffffff;
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 18px 20px;
        }
        .policy-alert {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 10px;
            padding: 11px 15px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 12px;
            color: #166534;
            margin-bottom: 16px;
        }
        .art-admin-thumb {
            width: 58px;
            height: 42px;
            border-radius: 8px;
            object-fit: cover;
            background: #f1f5f9;
            border: 1px solid var(--line);
        }
        .badge-featured {
            background: #fef3c7;
            color: #d97706;
            font-weight: 800;
            font-size: 10px;
            padding: 3px 8px;
            border-radius: 99px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .badge-published {
            background: #dcfce7;
            color: #15803d;
            font-weight: 800;
            font-size: 10px;
            padding: 3px 8px;
            border-radius: 99px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .badge-draft {
            background: #f1f5f9;
            color: #64748b;
            font-weight: 800;
            font-size: 10px;
            padding: 3px 8px;
            border-radius: 99px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .badge-cat-pill {
            background: rgba(91, 129, 33, 0.1);
            color: var(--green);
            font-weight: 700;
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 6px;
            display: inline-block;
        }

        /* ===== RICH TEXT EDITOR & FORM ARTIKEL MODERN ===== */
        .article-form-grid-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 350px;
            gap: 20px;
            align-items: start;
        }
        @media (max-width: 1024px) {
            .article-form-grid-layout {
                grid-template-columns: 1fr;
            }
        }
        .article-card-section {
            background: #ffffff;
            border: 1px solid var(--line);
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.02);
        }
        .article-section-header {
            background: #fafbf8;
            border-bottom: 1px solid var(--line);
            padding: 13px 18px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .article-section-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: var(--green-soft);
            color: var(--green);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            flex-shrink: 0;
        }
        .article-section-title {
            margin: 0;
            font-size: 13.5px;
            font-weight: 800;
            color: #0f172a;
        }
        .article-section-subtitle {
            margin: 2px 0 0;
            font-size: 11.5px;
            color: var(--muted);
        }
        .article-section-body {
            padding: 16px 18px;
        }
        .form-field-label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 6px;
        }
        .required-star {
            color: #ef4444;
        }
        .article-input-title {
            font-size: 15px !important;
            font-weight: 700;
            padding: 11px 14px !important;
        }

        /* RICH TEXT EDITOR (WYSIWYG) */
        .rte-editor-container {
            background: #ffffff;
            border-radius: 0 0 14px 14px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .rte-toolbar {
            background: #f8fafc;
            border-bottom: 1px solid var(--line);
            padding: 8px 12px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .rte-tool-group {
            display: flex;
            align-items: center;
            gap: 3px;
        }
        .rte-divider {
            width: 1px;
            height: 22px;
            background: #cbd5e1;
            margin: 0 4px;
        }
        .rte-btn {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            border: 1px solid transparent;
            background: transparent;
            color: #475569;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .rte-btn:hover {
            background: #e2e8f0;
            color: #0f172a;
        }
        .rte-btn.active {
            background: var(--green-soft);
            color: var(--green);
            border-color: rgba(91, 129, 33, 0.3);
            font-weight: 700;
        }
        .rte-select {
            height: 32px;
            padding: 0 8px;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #334155;
            font-size: 12px;
            font-family: inherit;
            font-weight: 600;
            cursor: pointer;
            outline: none;
            transition: border-color 0.15s ease;
        }
        .rte-select:focus {
            border-color: var(--green);
        }
        .rte-canvas {
            min-height: 340px;
            max-height: 600px;
            overflow-y: auto;
            padding: 20px 22px;
            outline: none;
            font-family: 'DM Sans', sans-serif;
            font-size: 15px;
            line-height: 1.8;
            color: #1e293b;
            background: #ffffff;
        }
        .rte-canvas:empty:before {
            content: attr(data-placeholder);
            color: #94a3b8;
            font-style: italic;
            pointer-events: none;
        }
        .rte-canvas h1 {
            font-size: 25px;
            font-weight: 800;
            margin: 20px 0 12px;
            color: #0f172a;
            line-height: 1.3;
        }
        .rte-canvas h2 {
            font-size: 20px;
            font-weight: 800;
            margin: 18px 0 10px;
            color: #0f172a;
            line-height: 1.35;
        }
        .rte-canvas h3 {
            font-size: 17px;
            font-weight: 700;
            margin: 14px 0 8px;
            color: #1e293b;
            line-height: 1.4;
        }
        .rte-canvas p {
            margin: 0 0 14px;
        }
        .rte-canvas ul, .rte-canvas ol {
            margin: 0 0 16px;
            padding-left: 24px;
        }
        .rte-canvas li {
            margin-bottom: 6px;
        }
        .rte-canvas a {
            color: var(--green);
            text-decoration: underline;
            font-weight: 600;
        }
        .rte-canvas blockquote {
            margin: 16px 0;
            padding: 12px 18px;
            background: var(--green-soft);
            border-left: 4px solid var(--green);
            border-radius: 0 8px 8px 0;
            font-style: italic;
            color: var(--green-dark);
        }
        .rte-footer {
            padding: 8px 18px;
            background: #f8fafc;
            border-top: 1px solid var(--line);
            font-size: 11.5px;
            color: var(--muted);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* TOGGLE CARDS STATUS */
        .pub-toggle-card {
            background: #f8fafc;
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 12px 14px;
            transition: background 0.15s ease;
        }
        .pub-toggle-card:hover {
            background: #f1f5f9;
        }
        .pub-toggle-label {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            cursor: pointer;
        }
        .pub-checkbox {
            width: 17px;
            height: 17px;
            margin-top: 2px;
            cursor: pointer;
        }
        .pub-checkbox.green {
            accent-color: var(--green);
        }
        .pub-checkbox.orange {
            accent-color: #d97706;
        }

        /* MODAL INSERT LINK */
        .rte-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.55);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 99999;
            padding: 16px;
        }
        .rte-modal-overlay.show {
            display: flex;
        }
        .rte-modal-card {
            background: #ffffff;
            border-radius: 14px;
            padding: 22px 24px;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
        }

        /* =========================================================================
           BUSINESS INTELLIGENCE & ANALYTICS SUITE (BALLA' FRESH ADMIN)
           ========================================================================= */
        .bi-analytics-section {
            margin-bottom: 28px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .bi-header-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 14px;
            background: #ffffff;
            padding: 18px 22px;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
        }
        .bi-title-group h2 {
            margin: 0 0 4px;
            font-size: 18px;
            font-weight: 800;
            font-family: "Manrope", sans-serif;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .bi-title-group p {
            margin: 0;
            font-size: 12px;
            color: #64748b;
        }
        .bi-filter-pills {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }
        .bi-filter-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 11.5px;
            font-weight: 700;
            background: #f8fafc;
            color: #475569;
            border: 1px solid #cbd5e1;
            text-decoration: none;
            transition: all 0.2s ease;
            user-select: none;
        }
        .bi-filter-pill:hover {
            background: #f1f5f9;
            color: #1e293b;
            border-color: #94a3b8;
        }
        .bi-filter-pill.active {
            background: #5b8121;
            color: #ffffff;
            border-color: #5b8121;
            box-shadow: 0 3px 10px rgba(91, 129, 33, 0.25);
        }

        /* 5 KPI Cards Grid */
        .bi-kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 14px;
        }
        .bi-kpi-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 18px 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.025);
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .bi-kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
        }
        .bi-kpi-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3.5px;
            background: var(--kpi-accent, #5b8121);
        }
        .bi-kpi-card.green { --kpi-accent: #5b8121; }
        .bi-kpi-card.emerald { --kpi-accent: #059669; }
        .bi-kpi-card.orange { --kpi-accent: #e48e1e; }
        .bi-kpi-card.blue { --kpi-accent: #2563eb; }
        .bi-kpi-card.purple { --kpi-accent: #7c3aed; }

        .bi-kpi-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .bi-kpi-label {
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #64748b;
        }
        .bi-kpi-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: grid;
            place-items: center;
            font-size: 15px;
        }
        .bi-kpi-card.green .bi-kpi-icon { background: #eff5e5; color: #5b8121; }
        .bi-kpi-card.emerald .bi-kpi-icon { background: #ecfdf5; color: #059669; }
        .bi-kpi-card.orange .bi-kpi-icon { background: #fff7ed; color: #e48e1e; }
        .bi-kpi-card.blue .bi-kpi-icon { background: #eff6ff; color: #2563eb; }
        .bi-kpi-card.purple .bi-kpi-icon { background: #f5f3ff; color: #7c3aed; }

        .bi-kpi-value {
            font-size: 23px;
            font-weight: 800;
            font-family: "Manrope", sans-serif;
            color: #0f172a;
            letter-spacing: -0.5px;
            line-height: 1.2;
            margin-bottom: 8px;
        }
        .bi-kpi-foot {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 11.5px;
            color: #64748b;
            flex-wrap: wrap;
        }
        .bi-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 7px;
            border-radius: 20px;
            font-weight: 800;
            font-size: 10.5px;
        }
        .bi-badge.up {
            background: #ecfdf5;
            color: #059669;
        }
        .bi-badge.down {
            background: #fef2f2;
            color: #dc2626;
        }
        .bi-badge.neutral {
            background: #f1f5f9;
            color: #64748b;
        }

        /* 2-Column Responsive Grids */
        .bi-grid-2col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }
        .bi-chart-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 20px 22px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.025);
            display: flex;
            flex-direction: column;
        }
        .bi-card-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .bi-card-title {
            font-size: 14.5px;
            font-weight: 800;
            font-family: "Manrope", sans-serif;
            color: #1e293b;
            margin: 0 0 2px;
            display: flex;
            align-items: center;
            gap: 7px;
        }
        .bi-card-sub {
            font-size: 11.5px;
            color: #64748b;
            margin: 0;
        }

        /* Time Series Switcher Tabs */
        .bi-tab-switcher {
            display: inline-flex;
            background: #f1f5f9;
            border-radius: 8px;
            padding: 3px;
            gap: 2px;
        }
        .bi-tab-btn {
            border: none;
            background: transparent;
            font-size: 11px;
            font-weight: 700;
            padding: 5px 11px;
            border-radius: 6px;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .bi-tab-btn:hover {
            color: #1e293b;
        }
        .bi-tab-btn.active {
            background: #ffffff;
            color: #5b8121;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
        }

        .bi-chart-wrapper {
            position: relative;
            width: 100%;
            height: 290px;
        }

        /* Table Ranking Layanan Terlaris */
        .bi-table-wrap {
            overflow-x: auto;
            border-radius: 12px;
            border: 1px solid #f1f5f9;
        }
        .bi-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            text-align: left;
        }
        .bi-table th {
            background: #f8fafc;
            color: #475569;
            font-weight: 800;
            font-size: 10.5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 11px 14px;
            border-bottom: 1px solid #e2e8f0;
        }
        .bi-table td {
            padding: 12px 14px;
            border-bottom: 1px solid #f1f5f9;
            color: #1e293b;
            vertical-align: middle;
        }
        .bi-table tr:hover td {
            background: #fafdf7;
        }
        .bi-rank-badge {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: inline-grid;
            place-items: center;
            font-size: 11px;
            font-weight: 800;
            background: #f1f5f9;
            color: #475569;
        }
        .bi-rank-1 { background: #fef3c7; color: #b45309; }
        .bi-rank-2 { background: #e2e8f0; color: #334155; }
        .bi-rank-3 { background: #ffedd5; color: #c2410c; }

        .bi-progress-bar {
            width: 100%;
            height: 7px;
            background: #f1f5f9;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 5px;
        }
        .bi-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #5b8121, #84cc16);
            border-radius: 10px;
            transition: width 0.6s ease;
        }

        /* Engagement Cards Grid */
        .bi-engagement-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }
        .bi-engage-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 14px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .bi-engage-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: grid;
            place-items: center;
            font-size: 16px;
            flex-shrink: 0;
        }
        .bi-engage-val {
            font-size: 17px;
            font-weight: 800;
            font-family: "Manrope", sans-serif;
            color: #0f172a;
            line-height: 1.1;
        }
        .bi-engage-label {
            font-size: 11px;
            color: #64748b;
            margin-top: 2px;
        }

        /* Notice Transparansi Tracking */
        .bi-tracking-notice {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 12px;
            color: #166534;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            line-height: 1.5;
        }

        @media (max-width: 992px) {
            .bi-grid-2col {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 640px) {
            .bi-header-row {
                flex-direction: column;
                align-items: flex-start;
            }
            .bi-kpi-grid {
                grid-template-columns: 1fr;
            }
            .bi-chart-wrapper {
                height: 240px;
            }
        }

        /* =========================================================================
           KELOLA ADMINISTRATOR & REAL-TIME ACTIVITY STYLES
           ========================================================================= */
        @keyframes pulseDot {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }
        .status-dot-online {
            width: 8.5px;
            height: 8.5px;
            border-radius: 50%;
            background: #10b981;
            display: inline-block;
            animation: pulseDot 2s infinite ease-in-out;
            flex-shrink: 0;
        }
        .status-dot-recent {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #e48e1e;
            display: inline-block;
            flex-shrink: 0;
        }
        .status-dot-offline {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #94a3b8;
            display: inline-block;
            flex-shrink: 0;
        }

        .admin-online-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 3px 9px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            line-height: 1;
        }
        .admin-online-badge.online {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }
        .admin-online-badge.recent {
            background: #fffbeb;
            color: #b45309;
            border: 1px solid #fef3c7;
        }
        .admin-online-badge.offline {
            background: #f8fafc;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }

        .admin-role-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 9px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }
        .admin-role-badge.owner {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }
        .admin-role-badge.staff {
            background: #eff5e5;
            color: #5b8121;
            border: 1px solid rgba(91, 129, 33, 0.25);
        }

        .admin-form-panel {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.03);
            margin-bottom: 24px;
        }
        .admin-form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }
        .admin-form-field label {
            display: block;
            font-size: 11.5px;
            font-weight: 700;
            color: #475569;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .admin-form-field input, .admin-form-field select {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 13px;
            color: #1e293b;
            background: #fff;
            outline: none;
            transition: all 0.2s ease;
        }
        .admin-form-field input:focus, .admin-form-field select:focus {
            border-color: #5b8121;
            box-shadow: 0 0 0 3px rgba(91, 129, 33, 0.15);
        }
    </style>
</head>
<body class="page-<?= e($page) ?>">
<div class="app">
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="admin_dashboard.php">
            <span class="brand-mark">
                <img src="logo-balla-fresh.png" alt="Logo Balla’ Fresh"
                     onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
                <span class="brand-fallback">BF</span>
            </span>
            <span>
                <span class="brand-name">Balla’ <span>Fresh</span></span>
                <span class="brand-caption">Agency workspace</span>
            </span>
        </a>

        <div class="nav-label">Workspace</div>
        <nav class="side-nav" aria-label="Navigasi admin">
            <a href="admin_dashboard.php?page=dashboard" class="side-link <?= $page === 'dashboard' ? 'active' : '' ?>"><i class="fa-solid fa-chart-pie"></i> Dashboard</a>
            <a href="admin_dashboard.php?page=orders" class="side-link <?= $page === 'orders' ? 'active' : '' ?>"><i class="fa-solid fa-cart-shopping"></i> Pengelolaan Pesanan</a>
            <a href="admin_dashboard.php?page=customers" class="side-link <?= $page === 'customers' ? 'active' : '' ?>"><i class="fa-solid fa-users"></i> Data Pelanggan</a>
            <a href="admin_dashboard.php?page=businesses" class="side-link <?= $page === 'businesses' ? 'active' : '' ?>"><i class="fa-solid fa-briefcase"></i> Data Usaha Mitra</a>
            <a href="admin_dashboard.php?page=consultations" class="side-link <?= $page === 'consultations' ? 'active' : '' ?>">
                <i class="fa-solid fa-comments"></i> Konsultasi &amp; Chat
                <?php if ($pendingConsultationCount > 0): ?>
                    <span style="margin-left:auto;background:#e48e1e;color:#fff;font-size:10px;font-weight:800;padding:2px 7px;border-radius:99px"><?= $pendingConsultationCount ?> ACC</span>
                <?php endif; ?>
            </a>
            <a href="admin_dashboard.php?page=activity" class="side-link <?= $page === 'activity' ? 'active' : '' ?>"><i class="fa-solid fa-clock-rotate-left"></i> Aktivitas Terbaru</a>
            <a href="admin_dashboard.php?page=agency" class="side-link <?= $page === 'agency' ? 'active' : '' ?>"><i class="fa-solid fa-seedling"></i> Operasional Agency</a>
            <a href="admin_dashboard.php?page=articles" class="side-link <?= $page === 'articles' ? 'active' : '' ?>"><i class="fa-solid fa-newspaper"></i> Kelola Artikel</a>
            <a href="admin_dashboard.php?page=admins" class="side-link <?= $page === 'admins' ? 'active' : '' ?>">
                <i class="fa-solid fa-user-shield"></i> Kelola Admin
                <?php if ($onlineAdminCount > 0): ?>
                    <span style="margin-left:auto;background:rgba(16,185,129,0.22);color:#d1fae5;font-size:10px;font-weight:800;padding:2px 7px;border-radius:99px"><i class="fa-solid fa-circle" style="font-size:7px;color:#10b981;margin-right:2px"></i> <?= $onlineAdminCount ?> On</span>
                <?php endif; ?>
            </a>
            <a href="admin_dashboard.php?page=settings" class="side-link <?= $page === 'settings' ? 'active' : '' ?>"><i class="fa-solid fa-shield-halved"></i> Pengaturan &amp; Kebijakan</a>
        </nav>

        <div class="side-spacer"></div>
        <div class="side-help">
            <i class="fa-regular fa-circle-check"></i>
            <strong>Workspace Balla’ Fresh</strong>
            <p>Pantau data pelanggan dan status sistem dari satu tempat.</p>
        </div>
        <div class="side-bottom">
            <span class="avatar"><?= e($initials) ?></span>
            <span class="side-user">
                <strong><?= e($adminName) ?></strong>
                <small>Administrator</small>
            </span>
            <button
            type="button"
            class="side-logout"
            id="logoutButton"
            title="Logout"
            aria-label="Logout"
>
    <i class="fa-solid fa-arrow-right-from-bracket"></i>
</button>
        </div>
    </aside>

    <div class="mobile-overlay" id="mobileOverlay"></div>

    <main class="main">
        <header class="topbar">
            <button class="mobile-menu" id="mobileMenu" aria-label="Buka menu"><i class="fa-solid fa-bars"></i></button>
            <div class="crumb">Workspace <span aria-hidden="true">/</span> <strong><?= e($pageTitle) ?></strong></div>
            <div class="top-actions">
                <div class="date-chip"><i class="fa-regular fa-calendar"></i> <span id="todayLabel"></span></div>
                <div class="top-avatar">
                    <span class="avatar"><?= e($initials) ?></span>
                    <span class="top-user-copy">
                        <strong><?= e($adminName) ?></strong>
                        <small>Admin Agency</small>
                    </span>
                </div>
            </div>
        </header>


        <div class="content" id="overview">
        <?php if ($page === 'dashboard'): ?>
            <!-- ========================================================== -->
            <!-- BUSINESS INTELLIGENCE & ANALYTICS SUITE (BALLA' FRESH)     -->
            <!-- ========================================================== -->
            <section class="bi-analytics-section" aria-label="Business Intelligence & Analytics">
                
                <!-- 1. Header BI & Interactive Period Filter -->
                <div class="bi-header-row">
                    <div class="bi-title-group">
                        <span class="eyebrow" style="color:var(--green-950);"><i class="fa-solid fa-chart-line"></i> BUSINESS INTELLIGENCE &amp; ANALYTICS</span>
                        <h2>Dashboard Performa Bisnis</h2>
                        <p>Pemantauan terpadu performa penjualan, transaksi berhasil, loyalitas pelanggan, dan performa website.</p>
                    </div>

                    <!-- Interactive Period Filter Pills -->
                    <div class="bi-filter-pills" role="navigation" aria-label="Filter Periode Laporan">
                        <a href="admin_dashboard.php?page=dashboard&bi_period=all" class="bi-filter-pill <?= ($biData['period'] ?? '') === 'all' ? 'active' : '' ?>">
                            <i class="fa-solid fa-earth-asia"></i> Semua Waktu
                        </a>
                        <a href="admin_dashboard.php?page=dashboard&bi_period=today" class="bi-filter-pill <?= ($biData['period'] ?? '') === 'today' ? 'active' : '' ?>">
                            <i class="fa-regular fa-calendar"></i> Hari Ini
                        </a>
                        <a href="admin_dashboard.php?page=dashboard&bi_period=7days" class="bi-filter-pill <?= ($biData['period'] ?? '') === '7days' ? 'active' : '' ?>">
                            <i class="fa-regular fa-calendar-days"></i> 7 Hari Terakhir
                        </a>
                        <a href="admin_dashboard.php?page=dashboard&bi_period=this_month" class="bi-filter-pill <?= ($biData['period'] ?? '') === 'this_month' ? 'active' : '' ?>">
                            <i class="fa-regular fa-calendar-check"></i> Bulan Ini
                        </a>
                        <a href="admin_dashboard.php?page=dashboard&bi_period=this_year" class="bi-filter-pill <?= ($biData['period'] ?? '') === 'this_year' ? 'active' : '' ?>">
                            <i class="fa-solid fa-calendar-week"></i> Tahun Ini
                        </a>
                    </div>
                </div>

                <!-- 2. 5 Executive KPI Cards -->
                <div class="bi-kpi-grid">
                    <!-- KPI 1: Total Pendapatan Bersih (Hanya dari Transaksi Berhasil) -->
                    <article class="bi-kpi-card green">
                        <div class="bi-kpi-head">
                            <span class="bi-kpi-label">Total Pendapatan</span>
                            <div class="bi-kpi-icon"><i class="fa-solid fa-wallet"></i></div>
                        </div>
                        <div class="bi-kpi-value">Rp <?= number_format((float)($biData['curRevenue'] ?? 0), 0, ',', '.') ?></div>
                        <div class="bi-kpi-foot">
                            <span class="bi-badge <?= ($biData['revenueGrowth']['is_positive'] ?? true) ? 'up' : 'down' ?>">
                                <i class="fa-solid <?= ($biData['revenueGrowth']['is_positive'] ?? true) ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' ?>"></i>
                                <?= e($biData['revenueGrowth']['text'] ?? '0%') ?>
                            </span>
                            <span><?= e($biData['comparisonLabel'] ?? '') ?></span>
                        </div>
                    </article>

                    <!-- KPI 2: Total Transaksi Berhasil -->
                    <article class="bi-kpi-card emerald">
                        <div class="bi-kpi-head">
                            <span class="bi-kpi-label">Transaksi Berhasil</span>
                            <div class="bi-kpi-icon"><i class="fa-solid fa-circle-check"></i></div>
                        </div>
                        <div class="bi-kpi-value"><?= number_format((int)($biData['curSuccessCount'] ?? 0), 0, ',', '.') ?></div>
                        <div class="bi-kpi-foot">
                            <span class="bi-badge <?= ($biData['ordersGrowth']['is_positive'] ?? true) ? 'up' : 'down' ?>">
                                <i class="fa-solid <?= ($biData['ordersGrowth']['is_positive'] ?? true) ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' ?>"></i>
                                <?= e($biData['ordersGrowth']['text'] ?? '0%') ?>
                            </span>
                            <span>dari <?= number_format((int)($biData['curTotalOrderCount'] ?? 0), 0, ',', '.') ?> pesanan masuk</span>
                        </div>
                    </article>

                    <!-- KPI 3: Rata-rata Nilai Transaksi (AOV) -->
                    <article class="bi-kpi-card orange">
                        <div class="bi-kpi-head">
                            <span class="bi-kpi-label">Rata-rata Nilai (AOV)</span>
                            <div class="bi-kpi-icon"><i class="fa-solid fa-receipt"></i></div>
                        </div>
                        <div class="bi-kpi-value">Rp <?= number_format((float)($biData['curAov'] ?? 0), 0, ',', '.') ?></div>
                        <div class="bi-kpi-foot">
                            <span class="bi-badge <?= ($biData['aovGrowth']['is_positive'] ?? true) ? 'up' : 'down' ?>">
                                <i class="fa-solid <?= ($biData['aovGrowth']['is_positive'] ?? true) ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' ?>"></i>
                                <?= e($biData['aovGrowth']['text'] ?? '0%') ?>
                            </span>
                            <span>per transaksi berhasil</span>
                        </div>
                    </article>

                    <!-- KPI 4: Total Pelanggan & Pembeli Aktif -->
                    <article class="bi-kpi-card blue">
                        <div class="bi-kpi-head">
                            <span class="bi-kpi-label">Total Pelanggan</span>
                            <div class="bi-kpi-icon"><i class="fa-solid fa-users"></i></div>
                        </div>
                        <div class="bi-kpi-value"><?= number_format((int)($biData['totalRegisteredCustomers'] ?? 0), 0, ',', '.') ?></div>
                        <div class="bi-kpi-foot">
                            <span class="bi-badge up"><i class="fa-solid fa-user-tag"></i> <?= e($biData['customerConversionRate'] ?? 0) ?>%</span>
                            <span><?= number_format((int)($biData['activeBuyingCustomersCount'] ?? 0), 0, ',', '.') ?> mitra bertransaksi</span>
                        </div>
                    </article>

                    <!-- KPI 5: Pertumbuhan Penjualan & Tingkat Repeat Order -->
                    <article class="bi-kpi-card purple">
                        <div class="bi-kpi-head">
                            <span class="bi-kpi-label">Pertumbuhan Penjualan</span>
                            <div class="bi-kpi-icon"><i class="fa-solid fa-arrow-up-right-dots"></i></div>
                        </div>
                        <div class="bi-kpi-value"><?= e($biData['revenueGrowth']['text'] ?? '0%') ?></div>
                        <div class="bi-kpi-foot">
                            <span class="bi-badge neutral"><i class="fa-solid fa-repeat"></i> <?= e($biData['repeatOrderRate'] ?? 0) ?>%</span>
                            <span>repeat order rate mitra</span>
                        </div>
                    </article>
                </div>

                <!-- 3. Grafik Tren Penjualan & Transaksi dengan Switcher Interval -->
                <article class="bi-chart-card">
                    <div class="bi-card-head">
                        <div>
                            <h3 class="bi-card-title"><i class="fa-solid fa-chart-area" style="color:#5b8121;"></i> Tren Pendapatan &amp; Frekuensi Transaksi</h3>
                            <p class="bi-card-sub">Grafik historis perkembangan omset dan jumlah transaksi berhasil secara periodik.</p>
                        </div>
                        <!-- Interval Switcher Tabs -->
                        <div class="bi-tab-switcher" role="tablist">
                            <button type="button" class="bi-tab-btn active" data-interval="daily" onclick="setSalesInterval('daily')">Harian (30 Hari)</button>
                            <button type="button" class="bi-tab-btn" data-interval="weekly" onclick="setSalesInterval('weekly')">Mingguan (8 Mgg)</button>
                            <button type="button" class="bi-tab-btn" data-interval="monthly" onclick="setSalesInterval('monthly')">Bulanan (12 Bln)</button>
                            <button type="button" class="bi-tab-btn" data-interval="yearly" onclick="setSalesInterval('yearly')">Tahunan</button>
                        </div>
                    </div>
                    <div class="bi-chart-wrapper">
                        <canvas id="biSalesTrendChart"></canvas>
                    </div>
                </article>

                <!-- 4. Grid Analisis Transaksi (Status Donut & Metode Pembayaran Bar) -->
                <div class="bi-grid-2col">
                    <!-- Diagram Status Transaksi -->
                    <article class="bi-chart-card">
                        <div class="bi-card-head">
                            <div>
                                <h3 class="bi-card-title"><i class="fa-solid fa-chart-pie" style="color:#e48e1e;"></i> Analisis Status Pesanan</h3>
                                <p class="bi-card-sub">Distribusi pesanan berhasil, diproses, menunggu, dan dibatalkan.</p>
                            </div>
                            <span class="welcome-tag" style="margin:0;font-size:11px;">Periode: <?= e($biData['periodLabel'] ?? '') ?></span>
                        </div>
                        <div class="bi-chart-wrapper" style="height:270px;">
                            <canvas id="biStatusDonutChart"></canvas>
                        </div>
                    </article>

                    <!-- Distribusi Metode Pembayaran -->
                    <article class="bi-chart-card">
                        <div class="bi-card-head">
                            <div>
                                <h3 class="bi-card-title"><i class="fa-solid fa-credit-card" style="color:#2563eb;"></i> Saluran &amp; Metode Pembayaran</h3>
                                <p class="bi-card-sub">Perbandingan penggunaan QRIS, Transfer Bank, dan Saluran Lainnya.</p>
                            </div>
                        </div>
                        <div class="bi-chart-wrapper" style="height:270px;">
                            <canvas id="biPaymentBarChart"></canvas>
                        </div>
                    </article>
                </div>

                <!-- 5. Layanan Terlaris (Ranking Table) -->
                <article class="bi-chart-card">
                    <div class="bi-card-head">
                        <div>
                            <h3 class="bi-card-title"><i class="fa-solid fa-ranking-star" style="color:#e48e1e;"></i> Ranking Layanan Terlaris (Top Services)</h3>
                            <p class="bi-card-sub">Peringkat paket layanan digital berdasarkan volume pesanan dan kontribusi omset.</p>
                        </div>
                        <span class="bi-badge up"><i class="fa-solid fa-shield-halved"></i> Data Riil Database</span>
                    </div>
                    <div class="bi-table-wrap">
                        <table class="bi-table">
                            <thead>
                                <tr>
                                    <th style="width:50px;text-align:center;">#</th>
                                    <th>Nama Layanan Digital</th>
                                    <th>Kategori</th>
                                    <th style="text-align:center;">Pesanan</th>
                                    <th style="text-align:right;">Total Pendapatan</th>
                                    <th style="width:200px;">Kontribusi Omset</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $rankIdx = 1;
                                $maxRev = max(1.0, (float)($biData['maxServiceRev'] ?? 1.0));
                                foreach (($biData['serviceRanking'] ?? []) as $svc): 
                                    $pct = round(((float)$svc['revenue'] / $maxRev) * 100);
                                    $badgeClass = $rankIdx === 1 ? 'bi-rank-1' : ($rankIdx === 2 ? 'bi-rank-2' : ($rankIdx === 3 ? 'bi-rank-3' : ''));
                                ?>
                                <tr>
                                    <td style="text-align:center;">
                                        <span class="bi-rank-badge <?= $badgeClass ?>"><?= $rankIdx ?></span>
                                    </td>
                                    <td>
                                        <strong><?= e($svc['name']) ?></strong>
                                    </td>
                                    <td>
                                        <span class="welcome-tag" style="margin:0;padding:2px 8px;font-size:10.5px;"><?= e($svc['category']) ?></span>
                                    </td>
                                    <td style="text-align:center;">
                                        <strong><?= number_format((int)$svc['order_count'], 0, ',', '.') ?></strong> pesanan
                                    </td>
                                    <td style="text-align:right;font-weight:700;color:#0f172a;">
                                        Rp <?= number_format((float)$svc['revenue'], 0, ',', '.') ?>
                                    </td>
                                    <td>
                                        <div style="display:flex;align-items:center;justify-content:space-between;font-size:11px;color:#64748b;margin-bottom:3px;">
                                            <span><?= $pct ?>%</span>
                                        </div>
                                        <div class="bi-progress-bar">
                                            <div class="bi-progress-fill" style="width: <?= min(100, max(4, $pct)) ?>%;"></div>
                                        </div>
                                    </td>
                                </tr>
                                <?php $rankIdx++; endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </article>

                <!-- 6. Grid Performa Pelanggan & Performa Website -->
                <div class="bi-grid-2col">
                    <!-- Performa Pelanggan -->
                    <article class="bi-chart-card">
                        <div class="bi-card-head">
                            <div>
                                <h3 class="bi-card-title"><i class="fa-solid fa-user-group" style="color:#5b8121;"></i> Performa &amp; Loyalitas Pelanggan</h3>
                                <p class="bi-card-sub">Pertumbuhan pendaftar baru vs pelanggan yang aktif bertransaksi.</p>
                            </div>
                        </div>
                        <div class="bi-engagement-grid" style="grid-template-columns: 1fr 1fr;">
                            <div class="bi-engage-box">
                                <div class="bi-engage-icon" style="background:#eff5e5;color:#5b8121;"><i class="fa-solid fa-user-plus"></i></div>
                                <div>
                                    <div class="bi-engage-val"><?= number_format((int)($biData['totalRegisteredCustomers'] ?? 0), 0, ',', '.') ?></div>
                                    <div class="bi-engage-label">Mitra Terdaftar</div>
                                </div>
                            </div>
                            <div class="bi-engage-box">
                                <div class="bi-engage-icon" style="background:#ecfdf5;color:#059669;"><i class="fa-solid fa-basket-shopping"></i></div>
                                <div>
                                    <div class="bi-engage-val"><?= number_format((int)($biData['activeBuyingCustomersCount'] ?? 0), 0, ',', '.') ?></div>
                                    <div class="bi-engage-label">Mitra Bertransaksi</div>
                                </div>
                            </div>
                            <div class="bi-engage-box">
                                <div class="bi-engage-icon" style="background:#fff7ed;color:#e48e1e;"><i class="fa-solid fa-arrows-rotate"></i></div>
                                <div>
                                    <div class="bi-engage-val"><?= number_format((int)($biData['repeatCustomersCount'] ?? 0), 0, ',', '.') ?></div>
                                    <div class="bi-engage-label">Mitra Repeat Order</div>
                                </div>
                            </div>
                            <div class="bi-engage-box">
                                <div class="bi-engage-icon" style="background:#f5f3ff;color:#7c3aed;"><i class="fa-solid fa-percent"></i></div>
                                <div>
                                    <div class="bi-engage-val"><?= e($biData['repeatOrderRate'] ?? 0) ?>%</div>
                                    <div class="bi-engage-label">Rasio Pembelian Ulang</div>
                                </div>
                            </div>
                        </div>
                        <div style="font-size:11.5px;color:#64748b;line-height:1.5;">
                            <i class="fa-solid fa-circle-info" style="color:#5b8121;"></i> Sebanyak <strong><?= e($biData['customerConversionRate'] ?? 0) ?>%</strong> dari total mitra hidroponik terdaftar telah melakukan pembelian paket layanan digital.
                        </div>
                    </article>

                    <!-- Performa Website (Berdasarkan Data Aktual Database) -->
                    <article class="bi-chart-card">
                        <div class="bi-card-head">
                            <div>
                                <h3 class="bi-card-title"><i class="fa-solid fa-globe" style="color:#2563eb;"></i> Performa Website &amp; Interaksi</h3>
                                <p class="bi-card-sub">Statistik kunjungan artikel, konsultasi, dan profil usaha dari database.</p>
                            </div>
                        </div>
                        <div class="bi-engagement-grid" style="grid-template-columns: 1fr 1fr;">
                            <div class="bi-engage-box">
                                <div class="bi-engage-icon" style="background:#eff6ff;color:#2563eb;"><i class="fa-solid fa-eye"></i></div>
                                <div>
                                    <div class="bi-engage-val"><?= number_format((int)($biData['totalArticleViews'] ?? 0), 0, ',', '.') ?></div>
                                    <div class="bi-engage-label">Pembaca Artikel</div>
                                </div>
                            </div>
                            <div class="bi-engage-box">
                                <div class="bi-engage-icon" style="background:#ecfdf5;color:#059669;"><i class="fa-solid fa-comments"></i></div>
                                <div>
                                    <div class="bi-engage-val"><?= number_format((int)($biData['totalConsultationsCount'] ?? 0), 0, ',', '.') ?></div>
                                    <div class="bi-engage-label">Sesi Konsultasi</div>
                                </div>
                            </div>
                            <div class="bi-engage-box">
                                <div class="bi-engage-icon" style="background:#fdf4ff;color:#c026d3;"><i class="fa-solid fa-message"></i></div>
                                <div>
                                    <div class="bi-engage-val"><?= number_format((int)($biData['totalChatMessagesCount'] ?? 0), 0, ',', '.') ?></div>
                                    <div class="bi-engage-label">Pesan Diskusi</div>
                                </div>
                            </div>
                            <div class="bi-engage-box">
                                <div class="bi-engage-icon" style="background:#eff5e5;color:#5b8121;"><i class="fa-solid fa-id-card"></i></div>
                                <div>
                                    <div class="bi-engage-val"><?= e($biData['profileCompletionRate'] ?? 0) ?>%</div>
                                    <div class="bi-engage-label">Profil Usaha Lengkap</div>
                                </div>
                            </div>
                        </div>

                        <!-- Transparansi Tracking Notice -->
                        <div class="bi-tracking-notice">
                            <i class="fa-solid fa-shield-halved" style="font-size:16px;margin-top:2px;"></i>
                            <div>
                                <strong>Transparansi Data Web Analytics:</strong> Metrik di atas dihitung secara akurat dari data transaksi, pembaca artikel, dan percakapan di database Supabase/MySQL. Untuk pelacakan kunjungan pengunjung anonim eksternal (pageviews/bounce rate), Anda dapat memasang Google Analytics 4 (GA4) atau Vercel Web Analytics.
                            </div>
                        </div>
                    </article>
                </div>

                <!-- 7. Ringkasan Laporan & Komparasi Periode (Summary Report) -->
                <article class="bi-chart-card">
                    <div class="bi-card-head">
                        <div>
                            <h3 class="bi-card-title"><i class="fa-solid fa-table-list" style="color:#5b8121;"></i> Ringkasan Laporan Komparasi Periode</h3>
                            <p class="bi-card-sub">Perbandingan performa indikator bisnis periode saat ini terhadap periode pembanding.</p>
                        </div>
                        <span class="welcome-tag" style="margin:0;"><i class="fa-solid fa-code-compare"></i> <?= e($biData['periodLabel'] ?? '') ?> <?= e($biData['comparisonLabel'] ?? '') ?></span>
                    </div>
                    <div class="bi-table-wrap">
                        <table class="bi-table">
                            <thead>
                                <tr>
                                    <th>Indikator Kinerja (KPI)</th>
                                    <th>Keterangan / Ruang Lingkup</th>
                                    <th>Periode Saat Ini</th>
                                    <th>Periode Pembanding</th>
                                    <th style="text-align:center;">Perubahan (%)</th>
                                    <th style="text-align:center;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (($biData['comparisonRows'] ?? []) as $cRow): ?>
                                <tr>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:9px;font-weight:700;">
                                            <span class="bi-engage-icon" style="width:30px;height:30px;font-size:12px;background:#f8fafc;color:#5b8121;">
                                                <i class="fa-solid <?= e($cRow['icon']) ?>"></i>
                                            </span>
                                            <span><?= e($cRow['metric']) ?></span>
                                        </div>
                                    </td>
                                    <td style="color:#64748b;font-size:11.5px;"><?= e($cRow['note']) ?></td>
                                    <td><strong style="color:#0f172a;"><?= e($cRow['cur']) ?></strong></td>
                                    <td style="color:#64748b;"><?= e($cRow['prev']) ?></td>
                                    <td style="text-align:center;">
                                        <span class="bi-badge <?= ($cRow['growth']['is_positive'] ?? true) ? 'up' : 'down' ?>">
                                            <i class="fa-solid <?= ($cRow['growth']['is_positive'] ?? true) ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down' ?>"></i>
                                            <?= e($cRow['growth']['text'] ?? '0%') ?>
                                        </span>
                                    </td>
                                    <td style="text-align:center;">
                                        <?php if (($cRow['growth']['is_positive'] ?? true)): ?>
                                            <span style="color:#059669;font-weight:700;font-size:11px;"><i class="fa-solid fa-circle-check"></i> Meningkat</span>
                                        <?php else: ?>
                                            <span style="color:#dc2626;font-weight:700;font-size:11px;"><i class="fa-solid fa-circle-exclamation"></i> Menurun</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </article>

            </section>

            <!-- ========================================================== -->
            <!-- RINGKASAN DATA & TABEL BAWAAN DASHBOARD (DIPERTAHANKAN)    -->
            <!-- ========================================================== -->
            <section class="welcome-row">
                <div>
                    <span class="eyebrow">RINGKASAN OPERASIONAL</span>
                    <h2>Aktivitas &amp; Pengelolaan Berjalan</h2>
                    <p>Daftar pesanan baru masuk dan riwayat interaksi pengguna terbaru.</p>
                </div>
                <span class="welcome-tag"><span></span> Sistem terhubung ke database</span>
            </section>

            <section class="stats-grid" aria-label="Ringkasan data" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                <article class="stat-card"><div class="stat-top"><span class="stat-kicker">Total Pelanggan</span><span class="stat-icon"><i class="fa-solid fa-users"></i></span></div><div class="stat-value"><?= number_format($customerCount, 0, ',', '.') ?></div><div class="stat-foot"><i class="fa-solid fa-database"></i> Akun tersimpan</div></article>
                <article class="stat-card"><div class="stat-top"><span class="stat-kicker">Total Pesanan</span><span class="stat-icon amber"><i class="fa-solid fa-cart-shopping"></i></span></div><div class="stat-value"><?= number_format($orderCounts['all'], 0, ',', '.') ?></div><div class="stat-foot">Seluruh pesanan masuk</div></article>
                <article class="stat-card"><div class="stat-top"><span class="stat-kicker">Pesanan Menunggu</span><span class="stat-icon sage"><i class="fa-regular fa-clock"></i></span></div><div class="stat-value"><?= number_format($orderCounts['pending'], 0, ',', '.') ?></div><div class="stat-foot">Perlu ditindaklanjuti</div></article>
                <article class="stat-card"><div class="stat-top"><span class="stat-kicker">Artikel Edukasi</span><span class="stat-icon" style="background:#eff6ff;color:#2563eb"><i class="fa-solid fa-newspaper"></i></span></div><div class="stat-value"><?= (int)$artStats['published'] ?> <small style="font-size:13px;color:var(--muted);font-weight:600">/ <?= (int)$artStats['total'] ?></small></div><div class="stat-foot"><a href="admin_dashboard.php?page=articles" style="color:#2563eb;text-decoration:none">Kelola Artikel <i class="fa-solid fa-arrow-right"></i></a></div></article>
                <article class="stat-card"><div class="stat-top"><span class="stat-kicker">Administrator</span><span class="stat-icon lilac"><i class="fa-solid fa-user-shield"></i></span></div><div class="stat-value"><?= number_format($adminCount, 0, ',', '.') ?></div><div class="stat-foot">Akun pengelola sistem</div></article>
            </section>
            <section class="panel-grid">
                <article class="panel">
                    <div class="panel-head"><div><h2>Pesanan Terbaru</h2><p>Ringkasan pesanan yang baru masuk.</p></div><a class="btn btn-light" href="admin_dashboard.php?page=orders">Lihat semua</a></div>
                    <div class="table-wrap"><table><thead><tr><th>Pesanan</th><th>Pelanggan</th><th>Total</th><th>Status</th></tr></thead><tbody>
                    <?php if (!$orders): ?><tr><td colspan="4"><div class="empty">Belum ada pesanan.</div></td></tr>
                    <?php else: foreach (array_slice($orders, 0, 5) as $order): ?><tr><td><strong><?= e($order['order_number']) ?></strong></td><td><?= e($order['customer_name']) ?></td><td>Rp<?= number_format((float)$order['total_amount'],0,',','.') ?></td><td><span class="status-pill <?= e(orderStatusClass((string)$order['status'])) ?>"><?= e(orderStatusLabel((string)$order['status'])) ?></span></td></tr><?php endforeach; endif; ?>
                    </tbody></table></div>
                </article>
                <article class="panel"><div class="panel-head"><div><h2>Aktivitas Terbaru</h2><p>Akun pelanggan terbaru.</p></div><a class="btn btn-light" href="admin_dashboard.php?page=activity">Lihat aktivitas</a></div><div class="panel-body"><div class="activity-list">
                    <?php if (!$latestUsers): ?><div class="empty">Belum ada aktivitas pelanggan.</div><?php else: foreach (array_slice($latestUsers,0,4) as $latest): ?><div class="activity-item"><span class="activity-icon"><i class="fa-solid fa-user-plus"></i></span><div><strong>Akun pelanggan terdaftar</strong><p><?= e($latest['name']) ?> · <?= e($latest['email']) ?></p></div></div><?php endforeach; endif; ?>
                </div></div></article>
            </section>

            <!-- ========================================================== -->
            <!-- JAVASCRIPT INISIALISASI CHART.JS DENGAN DATA DATABASE      -->
            <!-- ========================================================== -->
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Datasets dari PHP
                const biDatasets = {
                    daily: <?= json_encode($biData['tsDaily'] ?? [], JSON_UNESCAPED_UNICODE) ?>,
                    weekly: <?= json_encode($biData['tsWeekly'] ?? [], JSON_UNESCAPED_UNICODE) ?>,
                    monthly: <?= json_encode($biData['tsMonthly'] ?? [], JSON_UNESCAPED_UNICODE) ?>,
                    yearly: <?= json_encode($biData['tsYearly'] ?? [], JSON_UNESCAPED_UNICODE) ?>
                };

                const statusData = <?= json_encode($biData['statusDist'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
                const paymentData = <?= json_encode($biData['paymentDist'] ?? [], JSON_UNESCAPED_UNICODE) ?>;

                // 1. Inisialisasi Grafik Tren Penjualan & Transaksi
                const ctxSales = document.getElementById('biSalesTrendChart');
                let salesChart = null;

                if (ctxSales && typeof Chart !== 'undefined') {
                    const initialDataset = biDatasets.daily;
                    const labels = Object.values(initialDataset).map(d => d.label);
                    const revenues = Object.values(initialDataset).map(d => d.revenue);
                    const orders = Object.values(initialDataset).map(d => d.orders);

                    salesChart = new Chart(ctxSales, {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [
                                {
                                    label: 'Pendapatan (Rp)',
                                    data: revenues,
                                    borderColor: '#5b8121',
                                    backgroundColor: 'rgba(91, 129, 33, 0.12)',
                                    borderWidth: 2.5,
                                    fill: true,
                                    tension: 0.35,
                                    yAxisID: 'yRev',
                                    pointBackgroundColor: '#5b8121',
                                    pointRadius: 3,
                                    pointHoverRadius: 6
                                },
                                {
                                    label: 'Transaksi Berhasil',
                                    data: orders,
                                    borderColor: '#e48e1e',
                                    backgroundColor: 'rgba(228, 142, 30, 0.08)',
                                    borderWidth: 2,
                                    borderDash: [5, 4],
                                    fill: false,
                                    tension: 0.35,
                                    yAxisID: 'yOrders',
                                    pointBackgroundColor: '#e48e1e',
                                    pointRadius: 3,
                                    pointHoverRadius: 5
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: {
                                mode: 'index',
                                intersect: false
                            },
                            plugins: {
                                legend: {
                                    position: 'top',
                                    labels: {
                                        boxWidth: 12,
                                        font: { family: 'DM Sans', size: 12, weight: '600' }
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            if (context.datasetIndex === 0) {
                                                return ' Pendapatan: Rp ' + new Intl.NumberFormat('id-ID').format(context.parsed.y);
                                            }
                                            return ' Transaksi: ' + context.parsed.y + ' pesanan';
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    grid: { display: false },
                                    ticks: { font: { family: 'DM Sans', size: 10.5 }, maxRotation: 45 }
                                },
                                yRev: {
                                    type: 'linear',
                                    display: true,
                                    position: 'left',
                                    grid: { color: 'rgba(226, 232, 240, 0.7)' },
                                    ticks: {
                                        font: { family: 'DM Sans', size: 11 },
                                        callback: function(value) {
                                            if (value >= 1000000) return 'Rp ' + (value / 1000000).toFixed(1) + 'M';
                                            if (value >= 1000) return 'Rp ' + (value / 1000).toFixed(0) + 'k';
                                            return 'Rp ' + value;
                                        }
                                    }
                                },
                                yOrders: {
                                    type: 'linear',
                                    display: true,
                                    position: 'right',
                                    grid: { drawOnChartArea: false },
                                    ticks: {
                                        font: { family: 'DM Sans', size: 11 },
                                        stepSize: 1,
                                        callback: function(value) { return value + ' tx'; }
                                    }
                                }
                            }
                        }
                    });
                }

                // Global Switcher Interval
                window.setSalesInterval = function(interval) {
                    if (!salesChart || !biDatasets[interval]) return;
                    const ds = biDatasets[interval];
                    const labels = Object.values(ds).map(d => d.label);
                    const revenues = Object.values(ds).map(d => d.revenue);
                    const orders = Object.values(ds).map(d => d.orders);

                    salesChart.data.labels = labels;
                    salesChart.data.datasets[0].data = revenues;
                    salesChart.data.datasets[1].data = orders;
                    salesChart.update();

                    document.querySelectorAll('.bi-tab-btn').forEach(btn => {
                        btn.classList.toggle('active', btn.getAttribute('data-interval') === interval);
                    });
                };

                // 2. Inisialisasi Donut Chart Status Transaksi
                const ctxStatus = document.getElementById('biStatusDonutChart');
                if (ctxStatus && typeof Chart !== 'undefined') {
                    const stLabels = Object.values(statusData).map(d => d.label);
                    const stCounts = Object.values(statusData).map(d => d.count);
                    const stColors = Object.values(statusData).map(d => d.color);

                    new Chart(ctxStatus, {
                        type: 'doughnut',
                        data: {
                            labels: stLabels,
                            datasets: [{
                                data: stCounts,
                                backgroundColor: stColors,
                                borderWidth: 2,
                                borderColor: '#ffffff',
                                hoverOffset: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        boxWidth: 12,
                                        font: { family: 'DM Sans', size: 11.5 }
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                            const val = context.parsed;
                                            const pct = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
                                            return ` ${context.label}: ${val} (${pct}%)`;
                                        }
                                    }
                                }
                            },
                            cutout: '66%'
                        }
                    });
                }

                // 3. Inisialisasi Bar Chart Metode Pembayaran
                const ctxPayment = document.getElementById('biPaymentBarChart');
                if (ctxPayment && typeof Chart !== 'undefined') {
                    const payLabels = Object.values(paymentData).map(d => d.label);
                    const payCounts = Object.values(paymentData).map(d => d.count);
                    const payColors = Object.values(paymentData).map(d => d.color);

                    new Chart(ctxPayment, {
                        type: 'bar',
                        data: {
                            labels: payLabels,
                            datasets: [{
                                label: 'Jumlah Transaksi',
                                data: payCounts,
                                backgroundColor: payColors,
                                borderRadius: 8
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            return ' ' + context.parsed.y + ' pesanan';
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    grid: { display: false },
                                    ticks: { font: { family: 'DM Sans', size: 10.5 }, maxRotation: 25 }
                                },
                                y: {
                                    ticks: { stepSize: 1, font: { family: 'DM Sans', size: 11 } },
                                    grid: { color: 'rgba(226, 232, 240, 0.7)' }
                                }
                            }
                        }
                    });
                }
            });
            </script>
        <?php elseif ($page === 'orders'): ?>
            <section class="welcome-row">
                <div>
                    <span class="eyebrow">BALLA’ FRESH · TRANSAKSI AGENCY</span>
                    <h1>Pengelolaan Pesanan</h1>
                    <p>Pantau transaksi masuk, rincian paket layanan digital, catatan khusus mitra, dan progres pengerjaan.</p>
                </div>
                <span class="welcome-tag"><span></span> <?= count($orders) ?> Pesanan Terpilih</span>
            </section>

            <section class="panel" id="orders" style="margin-bottom:20px">
                <div class="panel-head">
                    <div>
                        <h2>Daftar Pesanan Layanan</h2>
                        <p>Kelola pesanan digital secara rapi berdasarkan kelompok tanggal dan filter status.</p>
                    </div>
                    <span class="welcome-tag"><span></span> Zona Waktu: Asia/Makassar (WITA)</span>
                </div>

                <div class="order-summary-grid">
                    <div class="order-mini-stat">
                        <span>Total Pesanan <?= $periodFilter !== 'all' ? '(' . ucfirst(str_replace('_', ' ', $periodFilter)) . ')' : '' ?></span>
                        <strong><?= number_format($orderCounts['all'], 0, ',', '.') ?></strong>
                    </div>
                    <div class="order-mini-stat">
                        <span>Menunggu</span>
                        <strong><?= number_format($orderCounts['pending'], 0, ',', '.') ?></strong>
                    </div>
                    <div class="order-mini-stat">
                        <span>Diproses</span>
                        <strong><?= number_format($orderCounts['processing'], 0, ',', '.') ?></strong>
                    </div>
                    <div class="order-mini-stat">
                        <span>Selesai</span>
                        <strong><?= number_format($orderCounts['completed'], 0, ',', '.') ?></strong>
                    </div>
                </div>

                <?php if ($orderActionMessage !== ''): ?>
                    <div class="order-notice <?= e($orderActionType) ?>"><?= e($orderActionMessage) ?></div>
                <?php endif; ?>
                <?php if ($orderLoadError !== ''): ?>
                    <div class="notice"><?= e($orderLoadError) ?></div>
                <?php endif; ?>

                <div class="panel-body">
                    <!-- FILTER TOOLBAR: STATUS & PERIODE BEKERJA BERSAMAAN -->
                    <form method="get" action="admin_dashboard.php" class="order-filter-toolbar" id="orderFilterForm">
                        <input type="hidden" name="page" value="orders">
                        <div class="filter-controls">
                            <div class="filter-group">
                                <label for="orderStatusFilter"><i class="fa-solid fa-filter"></i> Status Pesanan</label>
                                <select name="status" class="order-filter" id="orderStatusFilter" onchange="document.getElementById('orderFilterForm').submit()">
                                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Semua Status</option>
                                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Menunggu (Belum Selesai)</option>
                                    <option value="processing" <?= $statusFilter === 'processing' ? 'selected' : '' ?>>Diproses (Sedang Dikerjakan)</option>
                                    <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Selesai</option>
                                    <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Dibatalkan</option>
                                    <option value="paid" <?= $statusFilter === 'paid' ? 'selected' : '' ?>>Pembayaran Berhasil (Lunas)</option>
                                    <option value="unpaid" <?= $statusFilter === 'unpaid' ? 'selected' : '' ?>>Menunggu Pembayaran</option>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label for="orderPeriodFilter"><i class="fa-regular fa-calendar"></i> Periode Waktu (WITA)</label>
                                <select name="period" class="order-filter" id="orderPeriodFilter" onchange="document.getElementById('orderFilterForm').submit()">
                                    <option value="all" <?= $periodFilter === 'all' ? 'selected' : '' ?>>Semua Periode</option>
                                    <option value="today" <?= $periodFilter === 'today' ? 'selected' : '' ?>>Hari Ini</option>
                                    <option value="this_week" <?= $periodFilter === 'this_week' ? 'selected' : '' ?>>Minggu Ini</option>
                                    <option value="this_month" <?= $periodFilter === 'this_month' ? 'selected' : '' ?>>Bulan Ini</option>
                                    <option value="this_year" <?= $periodFilter === 'this_year' ? 'selected' : '' ?>>Tahun Ini</option>
                                </select>
                            </div>

                            <?php if ($statusFilter !== 'all' || $periodFilter !== 'all'): ?>
                                <a href="admin_dashboard.php?page=orders" class="btn-reset-filter" title="Kembalikan semua filter ke awal">
                                    <i class="fa-solid fa-rotate-left"></i> Reset Filter
                                </a>
                            <?php endif; ?>
                        </div>

                        <!-- LIVE SEARCH BOX -->
                        <div style="min-width:240px">
                            <label style="font-size:10.5px;font-weight:800;color:#64748b;text-transform:uppercase;display:block;margin-bottom:3px">
                                <i class="fa-solid fa-magnifying-glass"></i> Pencarian Cepat
                            </label>
                            <input type="search" id="quickSearchOrders" placeholder="Ketik nomor pesanan, nama, usaha..."
                                   style="border:1px solid #cbd5e1;border-radius:8px;padding:7px 10px;font-size:12px;width:100%;background:#fff">
                        </div>
                    </form>

                    <!-- TABEL 9 KOLOM DENGAN GROUPING TANGGAL -->
                    <div class="table-wrap">
                        <table class="order-table" id="ordersMainTable">
                            <thead>
                                <tr>
                                    <th>Nomor Pesanan</th>
                                    <th>Tanggal Masuk</th>
                                    <th>Informasi Pelanggan &amp; Usaha</th>
                                    <th>Layanan yang Dipesan</th>
                                    <th>Catatan Pelanggan</th>
                                    <th>Total Tagihan</th>
                                    <th>Metode &amp; Status Bayar</th>
                                    <th>Progres Pengerjaan</th>
                                    <th>Aksi &amp; Dokumen</th>
                                </tr>
                            </thead>
                            <tbody id="ordersTableBody">
                            <?php if (empty($groupedOrders)): ?>
                                <tr>
                                    <td colspan="9" style="text-align:center;padding:48px 16px">
                                        <div class="empty">
                                            <i class="fa-solid fa-bag-shopping" style="font-size:36px;color:#cbd5e1;margin-bottom:12px;display:block"></i>
                                            <strong>Tidak ada pesanan yang ditemukan.</strong><br>
                                            <span style="font-size:12px;color:var(--muted)">
                                                <?= ($statusFilter !== 'all' || $periodFilter !== 'all') ? 'Coba ubah atau reset filter status dan periode di atas.' : 'Belum ada pesanan yang tercatat di sistem.' ?>
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($groupedOrders as $dateGroup): ?>
                                    <!-- HEADER KELOMPOK TANGGAL -->
                                    <tr class="date-group-row" data-group-row="true">
                                        <td colspan="9">
                                            <div class="date-group-bar date-group-<?= e($dateGroup['type']) ?>">
                                                <div class="date-group-left">
                                                    <i class="fa-solid fa-calendar-day"></i>
                                                    <span><?= e($dateGroup['title']) ?></span>
                                                </div>
                                                <span class="date-group-badge"><?= count($dateGroup['orders']) ?> Pesanan</span>
                                            </div>
                                        </td>
                                    </tr>

                                    <?php foreach ($dateGroup['orders'] as $order): ?>
                                        <?php
                                            $orderTs = strtotime((string)$order['created_at']);
                                            $status = (string)$order['status'];

                                            // Parse Layanan
                                            $serviceItems = [];
                                            if (!empty($order['ordered_services_detail'])) {
                                                $rawItems = explode('||', (string)$order['ordered_services_detail']);
                                                foreach ($rawItems as $rawIt) {
                                                    $parts = explode('::', $rawIt);
                                                    if (count($parts) >= 2) {
                                                        $serviceItems[] = [
                                                            'name' => $parts[0],
                                                            'qty' => (int)($parts[1] ?? 1),
                                                            'price' => (float)($parts[2] ?? 0),
                                                            'subtotal' => (float)($parts[3] ?? 0)
                                                        ];
                                                    }
                                                }
                                            }
                                            $totalServiceCount = count($serviceItems);
                                            $visibleServiceItems = array_slice($serviceItems, 0, 2);
                                            $hiddenCount = $totalServiceCount - count($visibleServiceItems);

                                            // Parse Catatan Pelanggan Khusus
                                            $rawNotes = trim((string)$order['notes']);
                                            $cleanCustomerNote = '';
                                            if (!empty($rawNotes)) {
                                                if (preg_match('/Catatan Tambahan:\s*(.*)/is', $rawNotes, $mN)) {
                                                    $c = trim($mN[1]);
                                                    if ($c !== '' && $c !== '-') {
                                                        $cleanCustomerNote = $c;
                                                    }
                                                }
                                                if ($cleanCustomerNote === '') {
                                                    $lines = explode("\n", $rawNotes);
                                                    $customLines = [];
                                                    foreach ($lines as $line) {
                                                        $l = trim($line);
                                                        if ($l === '') continue;
                                                        if (preg_match('/^(Nama Usaha|Sosial Media|Website|Metode Pembayaran|Catatan Tambahan):/i', $l)) {
                                                            continue;
                                                        }
                                                        $customLines[] = $l;
                                                    }
                                                    if (!empty($customLines)) {
                                                        $cleanCustomerNote = implode(' ', $customLines);
                                                    }
                                                }
                                            }
                                            $hasNotes = ($cleanCustomerNote !== '');
                                            $isNotesLong = mb_strlen($cleanCustomerNote) > 65;

                                            // Payment Info
                                            $payInfo = paymentStatusBadge((string)($order['payment_status'] ?? ''));
                                            $payMethod = (string)($order['payment_method'] ?? 'midtrans');
                                            $payType = (string)($order['payment_type'] ?? '');
                                            $subMeta = function_exists('getPaymentSubMethodMeta') ? getPaymentSubMethodMeta($payType ?: $payMethod) : [];
                                            $subLabel = $subMeta['label'] ?? strtoupper($payType ?: $payMethod);
                                            $subIcon = $subMeta['icon'] ?? 'fa-credit-card';
                                            $transId = (string)($order['midtrans_transaction_id'] ?? '');
                                            $settleTime = !empty($order['settlement_time'])
                                                ? date('d/m/Y H:i', strtotime((string)$order['settlement_time']))
                                                : null;

                                            // Payload JSON Lengkap untuk Modal
                                            $orderPayload = [
                                                'id' => (int)$order['id'],
                                                'order_number' => $order['order_number'],
                                                'customer_name' => $order['customer_name'],
                                                'customer_email' => $order['customer_email'],
                                                'customer_phone' => $order['customer_phone'],
                                                'customer_address' => $order['customer_address'],
                                                'business_name' => $order['business_name'] ?? '',
                                                'social_media' => $order['social_media'] ?? '',
                                                'website_url' => $order['website_url'] ?? '',
                                                'total_amount' => (float)$order['total_amount'],
                                                'total_amount_formatted' => 'Rp' . number_format((float)$order['total_amount'], 0, ',', '.'),
                                                'status' => $status,
                                                'status_label' => orderStatusLabel($status),
                                                'status_class' => orderStatusClass($status),
                                                'payment_status' => (string)($order['payment_status'] ?? ''),
                                                'payment_label' => $payInfo['label'],
                                                'payment_class' => $payInfo['class'],
                                                'payment_method' => strtoupper($payMethod),
                                                'payment_type' => strtoupper($payType ?: $payMethod),
                                                'payment_sub_label' => $subLabel,
                                                'payment_code' => (string)($order['payment_code'] ?? ''),
                                                'biller_code' => (string)($order['biller_code'] ?? ''),
                                                'qr_url' => (string)($order['qr_url'] ?? ''),
                                                'midtrans_id' => $transId ?: '-',
                                                'settlement_time' => $settleTime ?: '-',
                                                'created_at' => date('d M Y, H:i', $orderTs) . ' WITA',
                                                'notes' => $rawNotes,
                                                'customer_note_clean' => $cleanCustomerNote,
                                                'services' => $serviceItems,
                                                'invoice_url' => 'invoice.php?order=' . urlencode($order['order_number']),
                                                'csrf' => $_SESSION['order_status_csrf']
                                            ];
                                            $orderJson = htmlspecialchars(json_encode($orderPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
                                        ?>
                                        <tr data-order-row="true" data-search="<?= strtolower(e($order['order_number'] . ' ' . $order['customer_name'] . ' ' . ($order['business_name'] ?? '') . ' ' . $order['customer_phone'] . ' ' . $order['customer_email'])) ?>">
                                            <!-- 1. Nomor Pesanan -->
                                            <td>
                                                <span class="order-number-pill"><?= e($order['order_number']) ?></span>
                                                <span class="order-id-sub">#<?= (int)$order['id'] ?></span>
                                            </td>

                                            <!-- 2. Tanggal Masuk (Asia/Makassar) -->
                                            <td>
                                                <strong class="order-time-text"><?= date('H:i', $orderTs) ?> WITA</strong>
                                                <span class="order-date-text"><?= date('d M Y', $orderTs) ?></span>
                                            </td>

                                            <!-- 3. Informasi Pelanggan & Usaha -->
                                            <td>
                                                <strong class="cust-name"><?= e($order['customer_name']) ?></strong>
                                                <?php if (!empty($order['business_name'])): ?>
                                                    <div class="cust-biz-tag" title="Nama Usaha Hidroponik">
                                                        <i class="fa-solid fa-leaf"></i> <?= e($order['business_name']) ?>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', (string)$order['customer_phone']) ?>" target="_blank" class="cust-wa-link" title="Hubungi WhatsApp">
                                                        <i class="fa-brands fa-whatsapp"></i> <?= e($order['customer_phone']) ?>
                                                    </a>
                                                </div>
                                                <span class="cust-email-sub" title="<?= e($order['customer_email']) ?>">
                                                    <i class="fa-regular fa-envelope"></i> <?= e($order['customer_email']) ?>
                                                </span>
                                            </td>

                                            <!-- 4. Layanan yang Dipesan -->
                                            <td>
                                                <div class="service-chip-list">
                                                    <?php if (!empty($serviceItems)): ?>
                                                        <?php foreach ($visibleServiceItems as $svc): ?>
                                                            <div class="service-chip" title="<?= e($svc['name']) ?>">
                                                                <span class="service-chip-name"><?= e($svc['name']) ?></span>
                                                                <span class="service-chip-qty"><?= (int)$svc['qty'] ?>x</span>
                                                            </div>
                                                        <?php endforeach; ?>
                                                        <?php if ($hiddenCount > 0): ?>
                                                            <button type="button" class="btn-more-services" onclick="openOrderDetail(<?= $orderJson ?>)">
                                                                +<?= $hiddenCount ?> paket lainnya
                                                            </button>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="note-empty-pill">Layanan Balla’ Fresh</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>

                                            <!-- 5. Catatan Pelanggan (Kolom Terpisah) -->
                                            <td>
                                                <?php if ($hasNotes): ?>
                                                    <div class="note-card-box">
                                                        <i class="fa-solid fa-quote-left" style="color:#d97706;font-size:9px;margin-right:2px"></i>
                                                        <span><?= e($isNotesLong ? mb_substr($cleanCustomerNote, 0, 65) . '...' : $cleanCustomerNote) ?></span>
                                                        <?php if ($isNotesLong): ?>
                                                            <button type="button" class="btn-note-detail" onclick="openOrderDetail(<?= $orderJson ?>)">
                                                                Lihat Detail
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="note-empty-pill"><i class="fa-regular fa-circle"></i> Tanpa catatan</span>
                                                <?php endif; ?>
                                            </td>

                                            <!-- 6. Total Tagihan -->
                                            <td>
                                                <strong class="order-total-price">Rp<?= number_format((float)$order['total_amount'], 0, ',', '.') ?></strong>
                                                <span class="order-total-qty"><?= (int)$order['total_quantity'] ?> Item Layanan</span>
                                            </td>

                                            <!-- 7. Metode & Status Pembayaran -->
                                            <td>
                                                <div style="margin-bottom:4px">
                                                    <span class="pay-badge <?= e($payInfo['class']) ?>">
                                                        <i class="fa-solid <?= e($payInfo['icon']) ?>"></i> <?= e($payInfo['label']) ?>
                                                    </span>
                                                </div>
                                                <div class="pay-channel-pill" title="Metode Pembayaran">
                                                    <i class="fa-solid <?= e($subIcon) ?>"></i> <?= e($subLabel) ?>
                                                </div>
                                                <?php if (!empty($order['payment_code'])): ?>
                                                    <div style="font-family:'JetBrains Mono',monospace;font-size:11px;font-weight:700;color:#0f172a;margin-top:3px;background:#f1f5f9;padding:2px 6px;border-radius:6px;display:inline-block">
                                                        <?= (!empty($order['biller_code']) ? e($order['biller_code']) . ' / ' : '') . e($order['payment_code']) ?>
                                                    </div>
                                                <?php elseif (!empty($order['qr_url'])): ?>
                                                    <div style="font-size:11px;font-weight:700;color:#16a34a;margin-top:3px">
                                                        <i class="fa-solid fa-qrcode"></i> QRIS Ready
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($transId !== ''): ?>
                                                    <div class="pay-midtrans-id" title="ID Transaksi Midtrans">
                                                        ID: <?= e($transId) ?>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($settleTime): ?>
                                                    <div class="pay-settle-date" title="Waktu Lunas">
                                                        <i class="fa-solid fa-clock-rotate-left"></i> <?= e($settleTime) ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>

                                            <!-- 8. Progres Pengerjaan -->
                                            <td>
                                                <div style="margin-bottom:6px">
                                                    <span class="status-pill <?= e(orderStatusClass($status)) ?>">
                                                        <i class="fa-solid fa-circle"></i> <?= e(orderStatusLabel($status)) ?>
                                                    </span>
                                                </div>
                                                <form method="post" action="admin_dashboard.php?page=orders<?= $periodFilter !== 'all' ? '&period=' . urlencode($periodFilter) : '' ?><?= $statusFilter !== 'all' ? '&status=' . urlencode($statusFilter) : '' ?>#orders" class="status-compact-form">
                                                    <input type="hidden" name="action" value="update_order_status">
                                                    <input type="hidden" name="csrf" value="<?= e($_SESSION['order_status_csrf']) ?>">
                                                    <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                                    <select name="status" aria-label="Ubah progres">
                                                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Menunggu</option>
                                                        <option value="processing" <?= $status === 'processing' ? 'selected' : '' ?>>Diproses</option>
                                                        <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Selesai</option>
                                                        <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Dibatalkan</option>
                                                    </select>
                                                    <button type="submit" title="Simpan perubahan progres">Ubah</button>
                                                </form>
                                            </td>

                                            <!-- 9. Aksi & Dokumen -->
                                            <td>
                                                <div class="order-action-stack">
                                                    <button type="button" class="btn-act-detail" onclick="openOrderDetail(<?= $orderJson ?>)">
                                                        <i class="fa-solid fa-eye"></i> Detail
                                                    </button>
                                                    <button type="button" class="btn-act-receipt" onclick="viewReceipt(<?= $orderJson ?>)">
                                                        <i class="fa-solid fa-receipt"></i> Bukti Bayar
                                                    </button>
                                                    <a href="invoice.php?order=<?= urlencode($order['order_number']) ?>" target="_blank" class="btn-act-invoice" title="Cetak / Download PDF Invoice">
                                                        <i class="fa-solid fa-file-invoice"></i> Invoice
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- 1. MODAL DETAIL LENGKAP PESANAN -->
            <div id="orderDetailModal" class="modal-overlay">
                <div class="modal-dialog-box">
                    <div class="modal-dialog-header">
                        <div style="display:flex;align-items:center;gap:12px">
                            <div style="width:40px;height:40px;border-radius:12px;background:#edf7e9;color:#3a6932;display:grid;place-items:center;font-size:18px">
                                <i class="fa-solid fa-box-archive"></i>
                            </div>
                            <div>
                                <h3 style="font-size:16px;font-weight:800;color:#0f172a;margin:0">Rincian Lengkap Pesanan</h3>
                                <span id="dtOrderNumber" style="font-family:monospace;font-size:13px;color:#64748b;font-weight:700"></span>
                            </div>
                        </div>
                        <button type="button" onclick="closeOrderDetailModal()" style="background:transparent;border:none;font-size:20px;color:#94a3b8;cursor:pointer;padding:4px 8px">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>

                    <div class="modal-dialog-body">
                        <!-- GRID PROFIL & STATUS -->
                        <div class="modal-info-grid">
                            <!-- KARTU 1: PELANGGAN & USAHA -->
                            <div class="modal-section-card">
                                <div class="modal-section-title"><i class="fa-solid fa-user-check"></i> Pelanggan &amp; Usaha Mitra</div>
                                <div style="display:grid;gap:8px">
                                    <div class="modal-info-row">
                                        <label>Nama Pelanggan:</label>
                                        <strong id="dtCustomerName">-</strong>
                                    </div>
                                    <div class="modal-info-row">
                                        <label>Nama Usaha Hidroponik:</label>
                                        <span id="dtBusinessName" style="color:#166534;font-weight:800">-</span>
                                    </div>
                                    <div class="modal-info-row">
                                        <label>WhatsApp / Telepon:</label>
                                        <div style="display:flex;align-items:center;gap:8px">
                                            <strong id="dtPhone">-</strong>
                                            <a id="dtWaLink" href="#" target="_blank" style="background:#22c55e;color:#fff;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:700;text-decoration:none">
                                                <i class="fa-brands fa-whatsapp"></i> Chat
                                            </a>
                                        </div>
                                    </div>
                                    <div class="modal-info-row">
                                        <label>Alamat Email:</label>
                                        <span id="dtEmail">-</span>
                                    </div>
                                    <div class="modal-info-row">
                                        <label>Alamat Domisili / Kebun:</label>
                                        <span id="dtAddress" style="color:#334155;font-size:11.5px;line-height:1.4">-</span>
                                    </div>
                                </div>
                            </div>

                            <!-- KARTU 2: TRANSAKSI & PEMBAYARAN -->
                            <div class="modal-section-card">
                                <div class="modal-section-title"><i class="fa-solid fa-credit-card"></i> Status Transaksi &amp; Gateway</div>
                                <div style="display:grid;gap:8px">
                                    <div class="modal-info-row">
                                        <label>Status Pembayaran:</label>
                                        <div><span id="dtPaymentBadge" class="pay-badge paid"></span></div>
                                    </div>
                                    <div class="modal-info-row">
                                        <label>Metode &amp; Saluran:</label>
                                        <strong id="dtPaymentMethod">-</strong>
                                    </div>
                                    <div class="modal-info-row" id="dtPayCodeRow" style="display:none">
                                        <label>Kode / No. VA / QR:</label>
                                        <div id="dtPayCodeVal" style="font-family:'JetBrains Mono',monospace;font-size:12px;font-weight:700;color:#0f172a">-</div>
                                    </div>
                                    <div class="modal-info-row">
                                        <label>ID Transaksi Midtrans:</label>
                                        <span id="dtMidtransId" style="font-family:monospace;font-size:12px">-</span>
                                    </div>
                                    <div class="modal-info-row">
                                        <label>Waktu Lunas (Settlement):</label>
                                        <span id="dtSettlementTime">-</span>
                                    </div>
                                    <div class="modal-info-row">
                                        <label>Waktu Pesanan Masuk:</label>
                                        <span id="dtCreatedAt">-</span>
                                    </div>
                                    <div class="modal-info-row" style="margin-top:4px;padding-top:6px;border-top:1px dashed #e2e8f0">
                                        <label>Total Tagihan:</label>
                                        <strong id="dtTotalAmount" class="modal-total-highlight">Rp0</strong>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- KARTU 3: RINCIAN PAKET LAYANAN -->
                        <div class="modal-section-card">
                            <div class="modal-section-title"><i class="fa-solid fa-cubes"></i> Paket Layanan yang Dipesan</div>
                            <table class="modal-services-table">
                                <thead>
                                    <tr>
                                        <th style="width:30px">No</th>
                                        <th>Nama Layanan Digital</th>
                                        <th style="width:60px;text-align:center">Qty</th>
                                        <th style="width:110px;text-align:right">Harga Satuan</th>
                                        <th style="width:120px;text-align:right">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody id="dtServicesTableBody">
                                </tbody>
                            </table>
                        </div>

                        <!-- KARTU 4: CATATAN & ARAHAN KHUSUS MITRA -->
                        <div class="modal-section-card">
                            <div class="modal-section-title"><i class="fa-solid fa-comment-dots"></i> Catatan &amp; Preferensi Pelanggan</div>
                            <div id="dtNotesContent" style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:12px;font-size:12px;color:#334155;line-height:1.6;white-space:pre-line">
                            </div>
                        </div>

                        <!-- KARTU 5: UPDATE PROGRES PENGERJAAN PROYEK -->
                        <div class="modal-section-card" style="margin-bottom:0">
                            <div class="modal-section-title"><i class="fa-solid fa-bars-progress"></i> Progres Pengerjaan Proyek</div>
                            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
                                <div>
                                    <span style="font-size:11px;color:#64748b;display:block">Status Saat Ini:</span>
                                    <span id="dtStatusPill" class="status-pill pending"></span>
                                </div>

                                <form method="post" action="admin_dashboard.php?page=orders<?= $periodFilter !== 'all' ? '&period=' . urlencode($periodFilter) : '' ?><?= $statusFilter !== 'all' ? '&status=' . urlencode($statusFilter) : '' ?>#orders" style="display:flex;gap:6px;align-items:center">
                                    <input type="hidden" name="action" value="update_order_status">
                                    <input type="hidden" name="csrf" id="dtCsrfInput" value="">
                                    <input type="hidden" name="order_id" id="dtOrderIdInput" value="">
                                    <select name="status" id="dtSelectStatus" style="border:1px solid #cbd5e1;border-radius:8px;padding:7px 10px;font-size:12px;background:#fff;font-weight:700">
                                        <option value="pending">Menunggu</option>
                                        <option value="processing">Diproses</option>
                                        <option value="completed">Selesai</option>
                                        <option value="cancelled">Dibatalkan</option>
                                    </select>
                                    <button type="submit" style="background:var(--green-700);color:#fff;border:none;border-radius:8px;padding:7px 12px;font-size:12px;font-weight:800;cursor:pointer">
                                        Perbarui Status
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- TOMBOL AKSI MODAL -->
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-top:20px;flex-wrap:wrap">
                            <button type="button" onclick="closeOrderDetailModal()" style="background:#f1f5f9;border:1px solid #cbd5e1;color:#475569;padding:9px 18px;border-radius:10px;font-weight:700;font-size:13px;cursor:pointer">
                                Tutup
                            </button>
                            <div style="display:flex;gap:10px">
                                <button type="button" id="dtBtnReceipt" onclick="" style="background:#f8fafc;border:1px solid #cbd5e1;color:#334155;padding:9px 16px;border-radius:10px;font-weight:700;font-size:13px;cursor:pointer;display:inline-flex;align-items:center;gap:6px">
                                    <i class="fa-solid fa-receipt"></i> Bukti Bayar
                                </button>
                                <a id="dtBtnInvoice" href="#" target="_blank" style="background:#3a6932;color:#fff;padding:9px 18px;border-radius:10px;font-weight:800;font-size:13px;text-decoration:none;display:inline-flex;align-items:center;gap:6px">
                                    <i class="fa-solid fa-print"></i> Cetak Invoice
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. MODAL DETAIL BUKTI PEMBAYARAN DIGITAL MIDTRANS -->
            <div id="receiptModal" class="modal-overlay">
                <div class="modal-dialog-box" style="max-width:540px">
                    <div class="modal-dialog-header">
                        <div style="display:flex;align-items:center;gap:10px">
                            <div style="width:36px;height:36px;border-radius:10px;background:#edf7e9;color:#3a6932;display:grid;place-items:center;font-size:16px">
                                <i class="fa-solid fa-file-invoice-dollar"></i>
                            </div>
                            <div>
                                <h3 style="font-size:15px;font-weight:800;color:#0f172a;margin:0">Bukti Pembayaran Digital</h3>
                                <span id="rcpOrderNumber" style="font-family:monospace;font-size:12px;color:#64748b">-</span>
                            </div>
                        </div>
                        <button type="button" onclick="closeReceiptModal()" style="background:transparent;border:none;font-size:18px;color:#94a3b8;cursor:pointer;padding:4px 8px">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                    <div class="modal-dialog-body">
                        <div style="display:flex;justify-content:space-between;align-items:center;padding-bottom:14px;border-bottom:1px dashed #e2e8f0;margin-bottom:14px">
                            <span style="font-size:13px;color:#64748b">Status Pembayaran</span>
                            <span id="rcpStatusBadge" class="pay-badge paid"><i class="fa-solid fa-circle-check"></i> Lunas</span>
                        </div>
                        <div style="display:grid;gap:10px;font-size:13px">
                            <div style="display:flex;justify-content:space-between">
                                <span style="color:#64748b">Pelanggan:</span>
                                <strong id="rcpCustomer" style="color:#0f172a;text-align:right">-</strong>
                            </div>
                            <div style="display:flex;justify-content:space-between">
                                <span style="color:#64748b">Usaha / Brand:</span>
                                <strong id="rcpBusiness" style="color:#0f172a;text-align:right">-</strong>
                            </div>
                            <div style="display:flex;justify-content:space-between">
                                <span style="color:#64748b">Total Pembayaran:</span>
                                <strong id="rcpTotal" style="color:#166534;font-size:15px;font-weight:800;text-align:right">-</strong>
                            </div>
                            <div style="display:flex;justify-content:space-between">
                                <span style="color:#64748b">Metode &amp; Saluran:</span>
                                <span id="rcpMethod" style="font-weight:700;color:#0f172a;text-align:right">-</span>
                            </div>
                            <div style="display:flex;justify-content:space-between" id="rcpPayCodeRow">
                                <span style="color:#64748b">Kode Bayar / VA:</span>
                                <span id="rcpPayCode" style="font-family:monospace;font-weight:700;color:#0f172a;text-align:right">-</span>
                            </div>
                            <div style="display:flex;justify-content:space-between">
                                <span style="color:#64748b">ID Transaksi Midtrans:</span>
                                <span id="rcpMidtransId" style="font-family:monospace;font-size:12px;color:#0f172a;text-align:right">-</span>
                            </div>
                            <div style="display:flex;justify-content:space-between">
                                <span style="color:#64748b">Waktu Pelunasan:</span>
                                <span id="rcpSettleTime" style="color:#0f172a;text-align:right">-</span>
                            </div>
                            <div style="display:flex;justify-content:space-between">
                                <span style="color:#64748b">Waktu Masuk:</span>
                                <span id="rcpCreatedAt" style="color:#0f172a;text-align:right">-</span>
                            </div>
                        </div>
                        <div style="margin-top:20px;display:flex;gap:10px;justify-content:flex-end">
                            <button type="button" onclick="closeReceiptModal()" style="background:#f1f5f9;border:1px solid #cbd5e1;color:#475569;padding:9px 16px;border-radius:10px;font-weight:700;font-size:12.5px;cursor:pointer">
                                Tutup
                            </button>
                            <a id="rcpInvoiceLink" href="#" target="_blank" style="background:#3a6932;color:#fff;padding:9px 16px;border-radius:10px;font-weight:700;font-size:12.5px;text-decoration:none;display:inline-flex;align-items:center;gap:6px">
                                <i class="fa-solid fa-print"></i> Cetak Invoice
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <script>
            // Buka Modal Detail Pesanan
            function openOrderDetail(order) {
                document.getElementById('dtOrderNumber').textContent = order.order_number;
                document.getElementById('dtCustomerName').textContent = order.customer_name;
                document.getElementById('dtBusinessName').textContent = order.business_name || 'Tidak ada profil usaha';
                document.getElementById('dtPhone').textContent = order.customer_phone;
                const cleanPhone = (order.customer_phone || '').replace(/[^0-9]/g, '');
                document.getElementById('dtWaLink').href = 'https://wa.me/' + cleanPhone;
                document.getElementById('dtEmail').textContent = order.customer_email;
                document.getElementById('dtAddress').textContent = order.customer_address || 'Alamat belum diatur';

                // Status Pembayaran
                const payBadge = document.getElementById('dtPaymentBadge');
                payBadge.className = 'pay-badge ' + order.payment_class;
                payBadge.innerHTML = '<i class="fa-solid ' + (order.payment_class === 'paid' ? 'fa-circle-check' : 'fa-clock') + '"></i> ' + order.payment_label;

                document.getElementById('dtPaymentMethod').textContent = order.payment_sub_label || (order.payment_type || order.payment_method);
                const payCodeRow = document.getElementById('dtPayCodeRow');
                const payCodeVal = document.getElementById('dtPayCodeVal');
                if (payCodeRow && payCodeVal) {
                    if (order.payment_code) {
                        payCodeRow.style.display = 'flex';
                        payCodeVal.textContent = (order.biller_code ? ('Biller: ' + order.biller_code + ' | Bill Key: ') : 'No. VA: ') + order.payment_code;
                    } else if (order.qr_url) {
                        payCodeRow.style.display = 'flex';
                        payCodeVal.innerHTML = '<a href="' + order.qr_url + '" target="_blank" style="color:#16a34a;font-weight:700;text-decoration:underline"><i class="fa-solid fa-qrcode"></i> Lihat Gambar QRIS</a>';
                    } else {
                        payCodeRow.style.display = 'none';
                    }
                }
                document.getElementById('dtMidtransId').textContent = order.midtrans_id;
                document.getElementById('dtSettlementTime').textContent = order.settlement_time;
                document.getElementById('dtCreatedAt').textContent = order.created_at;
                document.getElementById('dtTotalAmount').textContent = order.total_amount_formatted;

                // Status Pengerjaan
                const statusPill = document.getElementById('dtStatusPill');
                statusPill.className = 'status-pill ' + order.status_class;
                statusPill.innerHTML = '<i class="fa-solid fa-circle"></i> ' + order.status_label;

                document.getElementById('dtOrderIdInput').value = order.id;
                document.getElementById('dtCsrfInput').value = order.csrf;
                document.getElementById('dtSelectStatus').value = order.status;

                // Layanan Table
                const tbody = document.getElementById('dtServicesTableBody');
                tbody.innerHTML = '';
                if (order.services && order.services.length > 0) {
                    order.services.forEach(function(s, idx) {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td>${idx + 1}</td>
                            <td><strong>${s.name}</strong></td>
                            <td style="text-align:center">${s.qty}x</td>
                            <td style="text-align:right">Rp${Number(s.price).toLocaleString('id-ID')}</td>
                            <td style="text-align:right"><strong>Rp${Number(s.subtotal).toLocaleString('id-ID')}</strong></td>
                        `;
                        tbody.appendChild(tr);
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:12px;color:#94a3b8">Rincian layanan tidak tersedia</td></tr>';
                }

                // Catatan
                const notesBox = document.getElementById('dtNotesContent');
                notesBox.textContent = order.notes || 'Pelanggan tidak menyertakan catatan khusus.';

                // Action buttons
                document.getElementById('dtBtnInvoice').href = order.invoice_url;
                document.getElementById('dtBtnReceipt').onclick = function() {
                    viewReceipt(order);
                };

                document.getElementById('orderDetailModal').style.display = 'flex';
            }

            function closeOrderDetailModal() {
                const modal = document.getElementById('orderDetailModal');
                if (modal) modal.style.display = 'none';
            }

            // Buka Modal Bukti Bayar
            function viewReceipt(data) {
                document.getElementById('rcpOrderNumber').textContent = data.order_number;
                document.getElementById('rcpCustomer').textContent = data.customer_name;
                document.getElementById('rcpBusiness').textContent = data.business_name || '-';
                document.getElementById('rcpTotal').textContent = data.total_amount_formatted || ('Rp' + Number(data.total_amount).toLocaleString('id-ID'));
                document.getElementById('rcpMethod').textContent = data.payment_sub_label || (data.payment_type || data.payment_method);
                const rcpPayCodeRow = document.getElementById('rcpPayCodeRow');
                const rcpPayCode = document.getElementById('rcpPayCode');
                if (rcpPayCodeRow && rcpPayCode) {
                    if (data.payment_code) {
                        rcpPayCodeRow.style.display = 'flex';
                        rcpPayCode.textContent = (data.biller_code ? (data.biller_code + ' / ') : '') + data.payment_code;
                    } else if (data.qr_url) {
                        rcpPayCodeRow.style.display = 'flex';
                        rcpPayCode.innerHTML = '<a href="' + data.qr_url + '" target="_blank" style="color:#16a34a;text-decoration:underline">QRIS Ready</a>';
                    } else {
                        rcpPayCodeRow.style.display = 'none';
                    }
                }
                document.getElementById('rcpMidtransId').textContent = data.midtrans_id;
                document.getElementById('rcpSettleTime').textContent = data.settlement_time;
                document.getElementById('rcpCreatedAt').textContent = data.created_at;
                document.getElementById('rcpInvoiceLink').href = data.invoice_url;

                const badge = document.getElementById('rcpStatusBadge');
                badge.className = 'pay-badge ' + data.payment_class;
                badge.innerHTML = '<i class="fa-solid ' + (data.payment_class === 'paid' ? 'fa-circle-check' : 'fa-clock') + '"></i> ' + data.payment_label;

                document.getElementById('receiptModal').style.display = 'flex';
            }

            function closeReceiptModal() {
                const modal = document.getElementById('receiptModal');
                if (modal) modal.style.display = 'none';
            }

            // Tutup modal jika klik di luar card
            window.addEventListener('click', function(e) {
                const modalDetail = document.getElementById('orderDetailModal');
                if (modalDetail && e.target === modalDetail) {
                    modalDetail.style.display = 'none';
                }
                const modalReceipt = document.getElementById('receiptModal');
                if (modalReceipt && e.target === modalReceipt) {
                    modalReceipt.style.display = 'none';
                }
            });

            // Live quick search tabel pesanan
            const searchInput = document.getElementById('quickSearchOrders');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const q = this.value.toLowerCase().trim();
                    const rows = document.querySelectorAll('#ordersMainTable tbody tr[data-order-row="true"]');
                    const groupRows = document.querySelectorAll('#ordersMainTable tbody tr[data-group-row="true"]');

                    if (q === '') {
                        rows.forEach(r => r.style.display = '');
                        groupRows.forEach(g => g.style.display = '');
                        return;
                    }

                    rows.forEach(r => {
                        const searchData = r.dataset.search || '';
                        if (searchData.includes(q)) {
                            r.style.display = '';
                        } else {
                            r.style.display = 'none';
                        }
                    });

                    // Hide group header if all rows in that date group are hidden
                    groupRows.forEach(g => {
                        let next = g.nextElementSibling;
                        let hasVisible = false;
                        while (next && next.dataset.orderRow === 'true') {
                            if (next.style.display !== 'none') {
                                hasVisible = true;
                                break;
                            }
                            next = next.nextElementSibling;
                        }
                        g.style.display = hasVisible ? '' : 'none';
                    });
                });
            }
            </script>

        <?php elseif ($page === 'customers'): ?>
            <section class="welcome-row"><div><span class="eyebrow">BALLA’ FRESH · CUSTOMER DATABASE</span><h1>Data Pelanggan &amp; Profil Mitra</h1><p>Informasi akun pelanggan yang terdaftar di website, termasuk foto profil, tanggal lahir, identitas usaha, kontak, dan alamat terintegrasi langsung dengan database.</p></div><a class="btn" href="admin_dashboard.php?page=customers&amp;export=customers&amp;q=<?= rawurlencode($search) ?>"><i class="fa-solid fa-file-arrow-down"></i> Unduh CSV / Excel</a></section>
            <section class="stats-grid" style="grid-template-columns:repeat(3,minmax(0,1fr))">
                <article class="stat-card"><div class="stat-kicker">Total Akun Pelanggan</div><div class="stat-value"><?= number_format($customerCount,0,',','.') ?></div><div class="stat-foot">Tersimpan di tabel users</div></article>
                <article class="stat-card"><div class="stat-kicker">Mitra Terdaftar Usaha</div><div class="stat-value"><?= number_format(count(array_filter($customers, fn($c) => !empty($c['business_name']))),0,',','.') ?></div><div class="stat-foot">Memiliki data profil usaha</div></article>
                <article class="stat-card"><div class="stat-kicker">Pelanggan dengan Alamat</div><div class="stat-value"><?= number_format($addressCount,0,',','.') ?></div><div class="stat-foot">Alamat tersimpan</div></article>
            </section>
            <article class="panel">
                <div class="panel-head"><div><h2>Database Akun &amp; Mitra Terdaftar</h2><p>Data profil pengguna yang tersambung langsung dari form profil website.</p></div>
                    <form class="search-form" method="get" action="admin_dashboard.php"><input type="hidden" name="page" value="customers"><label class="search-box"><i class="fa-solid fa-magnifying-glass"></i><input type="search" name="q" value="<?= e($search) ?>" placeholder="Cari nama, email, usaha, atau HP..."></label><button class="btn" type="submit">Cari</button></form>
                </div>
                <?php if ($searchError !== ''): ?><div class="notice"><?= e($searchError) ?></div><?php endif; ?>
                <div class="table-wrap"><table style="min-width:1100px">
                    <thead>
                        <tr>
                            <th>Profil &amp; Nama Lengkap</th>
                            <th>Tanggal Lahir</th>
                            <th>Nama Usaha &amp; Bidang</th>
                            <th>Posisi / Jabatan</th>
                            <th>Kontak (HP &amp; Email)</th>
                            <th>Alamat Domisili</th>
                            <th>Tanggal Daftar</th>
                            <th>ID</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!$customers): ?><tr><td colspan="8"><div class="empty"><i class="fa-solid fa-user-group"></i><?= $search !== '' ? 'Tidak ada pelanggan yang cocok.' : 'Belum ada data pelanggan.' ?></div></td></tr>
                    <?php else: foreach ($customers as $customer):
                        $customerAddress = trim(implode(', ', array_filter([$customer['address_details'] ?? '', $customer['street'] ?? '', $customer['address'] ?? '', $customer['user_direct_address'] ?? '', $customer['rt_rw'] ?? '', $customer['subdistrict'] ?? ($customer['district'] ?? ''), $customer['city'] ?? '', $customer['province'] ?? '', $customer['postal_code'] ?? ''])));
                        $hasPhoto = !empty($customer['profile_image']) && (str_starts_with($customer['profile_image'], 'http') || file_exists(__DIR__ . '/' . $customer['profile_image']));
                    ?><tr>
                        <td>
                            <div class="customer-cell" style="display:flex;align-items:center;gap:12px">
                                <?php if ($hasPhoto): ?>
                                    <img src="<?= e($customer['profile_image']) ?>" alt="Avatar" style="width:42px;height:42px;border-radius:50%;object-fit:cover;border:2px solid #5b8121;flex-shrink:0">
                                <?php else: ?>
                                    <span class="customer-avatar" style="width:42px;height:42px;border-radius:50%;background:#eaf2e2;color:#3b5816;display:inline-grid;place-items:center;font-weight:800;flex-shrink:0"><?= e(strtoupper(substr((string)$customer['name'],0,1))) ?></span>
                                <?php endif; ?>
                                <div>
                                    <strong><?= e($customer['name']) ?></strong>
                                    <small style="display:block;color:var(--muted)"><?= e($customer['email']) ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if (!empty($customer['birth_date'])): ?>
                                <span style="font-weight:600;color:#1e293b"><i class="fa-regular fa-calendar" style="color:var(--muted);margin-right:4px"></i> <?= date('d M Y', strtotime((string)$customer['birth_date'])) ?></span>
                            <?php else: ?>
                                <span style="color:var(--muted);font-size:12px">Belum diisi</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($customer['business_name'])): ?>
                                <strong style="color:#2f4f1e"><?= e($customer['business_name']) ?></strong>
                                <small style="display:block;color:var(--muted)"><?= e($customer['business_type'] ?: 'Agribisnis / Hidroponik') ?></small>
                            <?php else: ?>
                                <span style="color:var(--muted);font-size:12px">Belum diisi</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($customer['business_role'])): ?>
                                <span style="background:#edf4e5;color:#3b5816;padding:4px 10px;border-radius:99px;font-size:11px;font-weight:700"><?= e($customer['business_role']) ?></span>
                            <?php else: ?>
                                <span style="color:var(--muted);font-size:12px">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="font-size:12px">
                                <strong><i class="fa-solid fa-phone" style="color:#5b8121;font-size:11px;margin-right:4px"></i> <?= e($customer['phone'] ?? '') ?: 'HP belum ada' ?></strong>
                                <div style="color:var(--muted);font-size:11px;margin-top:2px"><?= e($customer['email']) ?></div>
                            </div>
                        </td>
                        <td class="muted-cell" style="max-width:220px;font-size:12px"><?= e($customerAddress) ?: 'Alamat belum diisi' ?></td>
                        <td style="font-size:12px;white-space:nowrap"><?= !empty($customer['registered_at']) ? e(date('d M Y',strtotime((string)$customer['registered_at']))) : '—' ?></td>
                        <td><span style="font-size:11px;font-weight:700;color:var(--muted)">#<?= (int)$customer['id'] ?></span></td>
                    </tr><?php endforeach; endif; ?>
                    </tbody></table></div>
                <div class="panel-body"><p style="margin:0;color:var(--muted);font-size:11px">Seluruh data pengguna, foto profil avatar, tanggal lahir, dan identitas usaha diperbarui secara realtime dari formulir halaman profil mitra.</p></div>
            </article>

        <?php elseif ($page === 'activity'): ?>
            <section class="welcome-row"><div><span class="eyebrow">BALLA’ FRESH · MONITORING</span><h1>Aktivitas Terbaru</h1><p>Pantau aktivitas akun dan pesanan terbaru yang tercatat di database.</p></div></section>
            <section class="panel-grid">
                <article class="panel"><div class="panel-head"><div><h2>Registrasi Pelanggan Terbaru</h2><p>5 akun terakhir yang tersimpan.</p></div></div><div class="panel-body"><div class="activity-list"><?php if (!$latestUsers): ?><div class="empty">Belum ada registrasi.</div><?php else: foreach ($latestUsers as $latest): ?><div class="activity-item"><span class="activity-icon"><i class="fa-solid fa-user-plus"></i></span><div><strong>Pelanggan baru terdaftar</strong><p><?= e($latest['name']) ?> · <?= e($latest['email']) ?></p></div><span class="activity-id">#<?= (int)$latest['id'] ?></span></div><?php endforeach; endif; ?></div></div></article>
                <article class="panel"><div class="panel-head"><div><h2>Pesanan Terbaru</h2><p>Aktivitas transaksi terbaru.</p></div></div><div class="panel-body"><div class="activity-list"><?php if (!$orders): ?><div class="empty">Belum ada pesanan.</div><?php else: foreach (array_slice($orders,0,8) as $order): ?><div class="activity-item"><span class="activity-icon"><i class="fa-solid fa-cart-shopping"></i></span><div><strong>Pesanan <?= e($order['order_number']) ?></strong><p><?= e($order['customer_name']) ?> · Rp<?= number_format((float)$order['total_amount'],0,',','.') ?> · <?= e(orderStatusLabel((string)$order['status'])) ?></p></div></div><?php endforeach; endif; ?></div></div></article>
            </section>

        <?php elseif ($page === 'agency'): ?>
            <section class="welcome-row">
                <div>
                    <span class="eyebrow">BALLA’ FRESH · WEBSITE CMS</span>
                    <h1>Katalog Layanan & Konten Website</h1>
                    <p>Kelola katalog layanan, harga, ulasan mitra, dan konten website langsung dari dashboard. Semua data terhubung langsung ke database utama dan halaman publik.</p>
                </div>
                <span class="welcome-tag"><span></span> Terhubung ke Database Publik</span>
            </section>

            <?php if ($agencyMessage !== ''): ?>
                <div class="order-notice <?= e($agencyMessageType) ?>"><?= e($agencyMessage) ?></div>
            <?php endif; ?>

            <!-- NAVIGASI CEPAT SECTION CMS -->
            <div class="cms-nav-pills">
                <a href="#cms-hero" class="cms-pill"><i class="fa-solid fa-image"></i> 1. Hero Banner</a>
                <a href="#cms-about" class="cms-pill"><i class="fa-solid fa-leaf"></i> 2. Tentang Kami &amp; Isu UMKM</a>
                <a href="#cms-articles" class="cms-pill"><i class="fa-solid fa-newspaper"></i> 3. Konten Artikel &amp; Edukasi</a>
                <a href="#cms-services" class="cms-pill"><i class="fa-solid fa-boxes-stacked"></i> 4. Katalog Layanan</a>
                <a href="#cms-reviews" class="cms-pill"><i class="fa-solid fa-star"></i> 5. Moderasi Ulasan</a>
                <a href="#cms-contact" class="cms-pill"><i class="fa-solid fa-address-book"></i> 6. Kontak &amp; Alamat</a>
                <a href="admin_dashboard.php?page=settings" class="cms-pill"><i class="fa-solid fa-shield-halved"></i> 7. Kebijakan Privasi</a>
            </div>

            <!-- 1. SECTION HERO BANNER -->
            <article class="panel cms-section-card" id="cms-hero">
                <div class="panel-head">
                    <div>
                        <h2>Section 1: Hero Banner Website</h2>
                        <p>Atur teks pembuka, headline utama, tagline, dan gambar hero di landing page.</p>
                    </div>
                    <span class="admin-badge-member"><i class="fa-solid fa-eye"></i> Tampil di Landing Page</span>
                </div>
                <div class="panel-body">
                    <form method="post" action="admin_dashboard.php?page=agency#cms-hero" enctype="multipart/form-data" style="display:grid;gap:16px">
                        <input type="hidden" name="agency_action" value="save_hero_section">
                        <input type="hidden" name="agency_csrf" value="<?= e($_SESSION['order_status_csrf']) ?>">

                        <div class="ops-grid">
                            <label style="display:grid;gap:6px;font-size:12px;font-weight:700">Tagline / Eyebrow Hero
                                <input class="order-filter" type="text" name="hero_tag" value="<?= e($siteContent['hero_tag']) ?>" placeholder="Contoh: Digital Growth Partner" required>
                            </label>

                            <label style="display:grid;gap:6px;font-size:12px;font-weight:700">Judul Utama Hero
                                <input class="order-filter" type="text" name="hero_title" value="<?= e($siteContent['hero_title']) ?>" placeholder="Contoh: Lebih banyak peluang, #DariRumahAja." required>
                            </label>
                        </div>

                        <label style="display:grid;gap:6px;font-size:12px;font-weight:700">Deskripsi Hero
                            <textarea class="order-filter" name="hero_description" rows="3" placeholder="Jelaskan proposisi nilai agensi untuk petani hidroponik..." required><?= e($siteContent['hero_description']) ?></textarea>
                        </label>

                        <div style="display:grid;gap:8px">
                            <label style="font-size:12px;font-weight:700">Unggah Gambar Hero dari Komputer (JPG, PNG, WEBP - Maks. 4 MB)
                                <input class="order-filter" type="file" name="hero_image_file" accept="image/jpeg,image/png,image/webp" style="margin-top:6px">
                            </label>
                            <?php if (!empty($siteContent['hero_image'])): ?>
                                <div style="display:flex;align-items:center;gap:14px;background:#f8faf6;padding:12px;border-radius:12px;border:1px solid #dce9bd">
                                    <img src="<?= e($siteContent['hero_image']) ?>" alt="Preview Hero" style="max-width:140px;max-height:80px;object-fit:cover;border-radius:8px">
                                    <div style="font-size:12px;color:var(--muted)">
                                        <strong style="color:#1d2a12">Gambar Aktif Saat Ini:</strong><br>
                                        <span><?= e($siteContent['hero_image']) ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div>
                            <button class="btn" type="submit"><i class="fa-solid fa-floppy-disk"></i> Simpan Section Hero</button>
                        </div>
                    </form>
                </div>
            </article>

            <!-- 2. SECTION TENTANG KAMI & ISU UMKM -->
            <article class="panel cms-section-card" id="cms-about">
                <div class="panel-head">
                    <div>
                        <h2>Section 2: Tentang Kami &amp; Isu Terdekat UMKM</h2>
                        <p>Kelola narasi berkembangnya agensi, permasalahan nyata petani hidroponik rumahan, dan solusi Balla’ Fresh.</p>
                    </div>
                    <span class="admin-badge-member"><i class="fa-solid fa-leaf"></i> Tentang Agensi</span>
                </div>
                <div class="panel-body">
                    <form method="post" action="admin_dashboard.php?page=agency#cms-about" enctype="multipart/form-data" style="display:grid;gap:16px">
                        <input type="hidden" name="agency_action" value="save_about_section">
                        <input type="hidden" name="agency_csrf" value="<?= e($_SESSION['order_status_csrf']) ?>">

                        <div class="ops-grid">
                            <label style="display:grid;gap:6px;font-size:12px;font-weight:700">Tagline / Eyebrow Tentang Kami
                                <input class="order-filter" type="text" name="about_tag" value="<?= e($siteContent['about_tag']) ?>" placeholder="Contoh: Tentang Balla’ Fresh" required>
                            </label>

                            <label style="display:grid;gap:6px;font-size:12px;font-weight:700">Judul Utama Tentang Kami
                                <input class="order-filter" type="text" name="about_title" value="<?= e($siteContent['about_title']) ?>" placeholder="Contoh: Mendorong Kemandirian Petani &amp; UMKM Hidroponik Rumahan" required>
                            </label>
                        </div>

                        <label style="display:grid;gap:6px;font-size:12px;font-weight:700">Cerita Berkembangnya Agensi
                            <textarea class="order-filter" name="about_story" rows="4" placeholder="Ceritakan latar belakang kepedulian agensi terhadap UMKM hidroponik..." required><?= e($siteContent['about_story']) ?></textarea>
                        </label>

                        <label style="display:grid;gap:6px;font-size:12px;font-weight:700">Permasalahan Terdekat UMKM Hidroponik (Kendala Nyata)
                            <textarea class="order-filter" name="about_problem" rows="4" placeholder="Uraikan kendala terdekat yang dihadapi petani hidroponik..." required><?= e($siteContent['about_problem']) ?></textarea>
                        </label>

                        <label style="display:grid;gap:6px;font-size:12px;font-weight:700">Solusi Terarah Balla’ Fresh
                            <textarea class="order-filter" name="about_solution" rows="3" placeholder="Jelaskan pendekatan solusi modular Balla’ Fresh..." required><?= e($siteContent['about_solution']) ?></textarea>
                        </label>

                        <div style="display:grid;gap:8px">
                            <label style="font-size:12px;font-weight:700">Unggah Foto Dokumentasi/Tentang Kami dari Komputer (JPG, PNG, WEBP - Maks. 4 MB)
                                <input class="order-filter" type="file" name="about_image_file" accept="image/jpeg,image/png,image/webp" style="margin-top:6px">
                            </label>
                            <?php if (!empty($siteContent['about_image'])): ?>
                                <div style="display:flex;align-items:center;gap:14px;background:#f8faf6;padding:12px;border-radius:12px;border:1px solid #dce9bd">
                                    <img src="<?= e($siteContent['about_image']) ?>" alt="Preview Tentang Kami" style="max-width:140px;max-height:80px;object-fit:cover;border-radius:8px">
                                    <div style="font-size:12px;color:var(--muted)">
                                        <strong style="color:#1d2a12">Gambar Aktif Saat Ini:</strong><br>
                                        <span><?= e($siteContent['about_image']) ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div>
                            <button class="btn" type="submit"><i class="fa-solid fa-floppy-disk"></i> Simpan Section Tentang Kami</button>
                        </div>
                    </form>
                </div>
            </article>

            <!-- 3. SECTION KONTEN ARTIKEL & EDUKASI -->
            <article class="panel cms-section-card" id="cms-articles">
                <div class="panel-head">
                    <div>
                        <h2>Section 3: Konten Artikel &amp; Edukasi Hidroponik</h2>
                        <p>Kelola artikel wawasan bisnis, panduan budidaya, branding, dan edukasi hidroponik yang tayang pada katalog artikel publik.</p>
                    </div>
                    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                        <a href="artikel.php" target="_blank" class="btn btn-light" style="font-size:12px;padding:7px 12px">
                            <i class="fa-solid fa-arrow-up-right-from-square"></i> Buka Katalog Publik
                        </a>
                        <a href="admin_dashboard.php?page=articles#articleForm" class="btn" style="font-size:12px;padding:7px 14px">
                            <i class="fa-solid fa-plus"></i> Tulis Artikel Baru
                        </a>
                    </div>
                </div>
                <div class="panel-body">
                    <!-- Ringkasan Statistik Konten -->
                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:12px;margin-bottom:18px">
                        <div style="background:#f8faf6;border:1px solid #dce9bd;border-radius:12px;padding:12px 14px">
                            <span style="font-size:11px;color:var(--muted);font-weight:700">TOTAL ARTIKEL</span>
                            <strong style="display:block;font-size:22px;color:var(--ink);margin-top:4px"><?= (int)$artStats['total'] ?> Konten</strong>
                            <span style="font-size:11px;color:var(--muted)">Seluruh arsip artikel</span>
                        </div>
                        <div style="background:#f8faf6;border:1px solid #dce9bd;border-radius:12px;padding:12px 14px">
                            <span style="font-size:11px;color:var(--muted);font-weight:700">TAYANG PUBLIK</span>
                            <strong style="display:block;font-size:22px;color:var(--green);margin-top:4px"><?= (int)$artStats['published'] ?> Tayang</strong>
                            <span style="font-size:11px;color:var(--muted)">Aktif di web mitra</span>
                        </div>
                        <div style="background:#f8faf6;border:1px solid #dce9bd;border-radius:12px;padding:12px 14px">
                            <span style="font-size:11px;color:var(--muted);font-weight:700">HERO / UNGGULAN</span>
                            <strong style="display:block;font-size:22px;color:#d97706;margin-top:4px"><?= (int)$artStats['featured'] ?> Hero</strong>
                            <span style="font-size:11px;color:var(--muted)">Banner utama artikel</span>
                        </div>
                        <div style="background:#f8faf6;border:1px solid #dce9bd;border-radius:12px;padding:12px 14px">
                            <span style="font-size:11px;color:var(--muted);font-weight:700">TOTAL DIBACA MITRA</span>
                            <strong style="display:block;font-size:22px;color:#2563eb;margin-top:4px"><?= number_format((int)$artStats['views']) ?>x Dibaca</strong>
                            <span style="font-size:11px;color:var(--muted)">Total views artikel</span>
                        </div>
                    </div>

                    <!-- Cuplikan Artikel Terbaru & Aksi Cepat -->
                    <div style="border:1px solid var(--line);border-radius:12px;overflow:hidden">
                        <div style="padding:10px 14px;background:#fcfdfa;border-bottom:1px solid var(--line);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
                            <strong style="font-size:13px;color:#1e293b"><i class="fa-solid fa-list-check" style="color:var(--green);margin-right:6px"></i> 5 Artikel Terbaru di Sistem</strong>
                            <a href="admin_dashboard.php?page=articles" style="font-size:12px;font-weight:700;color:var(--green);text-decoration:none">
                                Kelola &amp; Tulis Artikel Lengkap (<?= count($allArticlesList) ?>) <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        </div>
                        <div class="table-wrap">
                            <table style="min-width:680px;margin:0">
                                <thead>
                                    <tr>
                                        <th style="width:60px">Sampul</th>
                                        <th>Judul Artikel &amp; Kategori</th>
                                        <th>Penulis</th>
                                        <th style="text-align:center">Dibaca</th>
                                        <th style="text-align:center">Status</th>
                                        <th style="text-align:center">Aksi Cepat</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($allArticlesList)): ?>
                                        <tr><td colspan="6"><div class="empty">Belum ada artikel. Klik tombol "+ Tulis Artikel Baru" di atas.</div></td></tr>
                                    <?php else: foreach (array_slice($allArticlesList, 0, 5) as $artQuick): 
                                        $qImg = !empty($artQuick['image']) ? $artQuick['image'] : 'https://images.unsplash.com/photo-1540420773420-3366772f4999?auto=format&fit=crop&w=200&q=80';
                                    ?>
                                        <tr>
                                            <td>
                                                <img src="<?= e($qImg) ?>" alt="<?= e($artQuick['title']) ?>" style="width:48px;height:48px;border-radius:8px;object-fit:cover;border:1px solid var(--line)">
                                            </td>
                                            <td>
                                                <strong style="font-size:12.5px;color:#0f172a;line-height:1.3;display:block"><?= e($artQuick['title']) ?></strong>
                                                <div style="margin-top:3px;display:flex;align-items:center;gap:6px">
                                                    <span class="badge-cat-pill"><?= e($artQuick['category']) ?></span>
                                                    <?php if (!empty($artQuick['is_featured'])): ?>
                                                        <span style="font-size:10px;font-weight:800;background:#fef3c7;color:#b45309;padding:2px 6px;border-radius:4px"><i class="fa-solid fa-star"></i> Hero</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td style="font-size:12px;color:var(--muted)">
                                                <?= e($artQuick['author_name']) ?>
                                            </td>
                                            <td style="text-align:center;font-weight:700;font-size:12px;color:#2563eb">
                                                <?= number_format((int)$artQuick['views_count']) ?>
                                            </td>
                                            <td style="text-align:center">
                                                <?php if (!empty($artQuick['is_published'])): ?>
                                                    <span class="status-pill status-completed"><i class="fa-solid fa-circle"></i> Publik</span>
                                                <?php else: ?>
                                                    <span class="status-pill status-cancelled"><i class="fa-solid fa-circle"></i> Draf</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align:center">
                                                <div style="display:inline-flex;gap:6px">
                                                    <a href="baca_artikel.php?slug=<?= urlencode($artQuick['slug']) ?>" target="_blank" class="btn btn-light" style="padding:4px 8px;font-size:11px" title="Lihat di Web">
                                                        <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                                    </a>
                                                    <a href="admin_dashboard.php?page=articles&amp;edit_article=<?= (int)$artQuick['id'] ?>#articleForm" class="btn btn-light" style="padding:4px 8px;font-size:11px" title="Edit di Editor">
                                                        <i class="fa-solid fa-pen-to-square"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div style="margin-top:14px;display:flex;justify-content:flex-end">
                        <a href="admin_dashboard.php?page=articles" class="btn" style="padding:9px 18px">
                            <i class="fa-solid fa-gear"></i> Buka Editor &amp; Manajemen Artikel Lengkap
                        </a>
                    </div>

                    <!-- Catatan penyesuaian section yang dihapus dari beranda -->
                    <div style="margin-top:14px;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:10px;padding:10px 14px;font-size:11.5px;color:#64748b;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
                        <span><i class="fa-solid fa-circle-info" style="color:var(--green)"></i> <strong>Penyesuaian Beranda:</strong> Section Struktur Organisasi dan Banner CTA telah dinonaktifkan dari tampilan beranda agar struktur website lebih clean dan modern.</span>
                        <details style="display:inline-block">
                            <summary style="cursor:pointer;font-weight:700;color:var(--green)">Lihat Arsip Tim</summary>
                            <div style="margin-top:8px;padding-top:8px;border-top:1px solid #e2e8f0;font-size:11px">
                                Terdaftar <?= count($teamMembersList) ?> profil tim agensi tersimpan di sistem internal.
                            </div>
                        </details>
                    </div>
                </div>
            </article>

            <!-- 4. SECTION DAFTAR LAYANAN PUBLIK -->
            <article class="panel cms-section-card" id="cms-services" style="margin-bottom:24px">
                <div class="panel-head">
                    <div>
                        <h2>Section 4: Katalog Layanan Aktif di Website</h2>
                        <p>Layanan yang tampil pada katalog dan halaman detail layanan publik.</p>
                    </div>
                </div>
                <div class="table-wrap">
                    <table style="min-width:820px">
                        <thead>
                            <tr>
                                <th>Gambar</th>
                                <th>Nama Layanan &amp; Kategori</th>
                                <th>Harga</th>
                                <th>Estimasi</th>
                                <th>Ulasan</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$allServices): ?>
                                <tr><td colspan="7"><div class="empty">Belum ada layanan di database. Tambahkan melalui formulir di bawah.</div></td></tr>
                            <?php else: foreach ($allServices as $srv): ?>
                                <tr>
                                    <td style="width:70px">
                                        <div style="width:56px;height:56px;border-radius:12px;overflow:hidden;background:#f1f5f9;border:1px solid var(--line)">
                                            <img src="<?= e($srv['image'] ?: 'https://images.unsplash.com/photo-1634942536790-2534a3b6a3a5?auto=format&fit=crop&w=200&q=80') ?>"
                                                 alt="<?= e($srv['name']) ?>" style="width:100%;height:100%;object-fit:cover"
                                                 onerror="this.src='logo-balla-fresh.png'">
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?= e($srv['name']) ?></strong>
                                        <div style="font-size:11px;color:var(--green);font-weight:700"><?= e($srv['category_name'] ?: 'Layanan') ?></div>
                                        <small style="color:var(--muted);display:block;max-width:280px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                                            <?= e($srv['description'] ?: 'Tidak ada deskripsi') ?>
                                        </small>
                                    </td>
                                    <td style="font-weight:800;color:#e48e1e;white-space:nowrap">
                                        Rp<?= number_format((float)$srv['price'], 0, ',', '.') ?>
                                    </td>
                                    <td><?= (int)$srv['estimated_days'] ?> hari</td>
                                    <td>
                                        <span style="font-size:12px;color:var(--muted)">
                                            <i class="fa-solid fa-star" style="color:#f59e0b"></i> <?= (int)$srv['review_count'] ?> ulasan
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ((int)$srv['is_available'] === 1): ?>
                                            <span class="status-pill status-completed"><i class="fa-solid fa-circle"></i> Tayang</span>
                                        <?php else: ?>
                                            <span class="status-pill status-cancelled"><i class="fa-solid fa-circle"></i> Nonaktif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display:flex;gap:6px;align-items:center">
                                            <a class="btn btn-light" href="detail_layanan.php?id=<?= (int)$srv['id'] ?>" target="_blank" title="Lihat Halaman Publik">
                                                <i class="fa-solid fa-arrow-up-right-from-square"></i> Web
                                            </a>
                                            <form method="post" action="admin_dashboard.php?page=agency#cms-services" onsubmit="return confirm('Apakah Anda yakin ingin menghapus layanan ini dari database?')">
                                                <input type="hidden" name="agency_action" value="delete_service">
                                                <input type="hidden" name="agency_csrf" value="<?= e($_SESSION['order_status_csrf']) ?>">
                                                <input type="hidden" name="service_id" value="<?= (int)$srv['id'] ?>">
                                                <button class="btn btn-light" type="submit" style="color:#dc2626"><i class="fa-regular fa-trash-can"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <!-- FORM EDIT CEPAT LAYANAN -->
                                <tr>
                                    <td colspan="7" style="background:#fafbf8;padding:12px 16px;border-bottom:2px solid var(--line)">
                                        <details style="font-size:12px">
                                            <summary style="cursor:pointer;font-weight:700;color:var(--green)">
                                                <i class="fa-solid fa-pen-to-square"></i> Edit Data Layanan #<?= (int)$srv['id'] ?> (<?= e($srv['name']) ?>)
                                            </summary>
                                            <form method="post" action="admin_dashboard.php?page=agency#cms-services" enctype="multipart/form-data" style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:10px">
                                                <input type="hidden" name="agency_action" value="save_service">
                                                <input type="hidden" name="agency_csrf" value="<?= e($_SESSION['order_status_csrf']) ?>">
                                                <input type="hidden" name="service_id" value="<?= (int)$srv['id'] ?>">
                                                
                                                <label style="display:grid;gap:4px">Kategori
                                                    <select class="order-filter" name="category_id">
                                                        <?php foreach ($categoriesList as $cat): ?>
                                                            <option value="<?= (int)$cat['id'] ?>" <?= (int)$cat['id'] === (int)$srv['category_id'] ? 'selected' : '' ?>>
                                                                <?= e($cat['name']) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </label>

                                                <label style="display:grid;gap:4px">Nama Layanan
                                                    <input class="order-filter" name="service_name" value="<?= e($srv['name']) ?>" required>
                                                </label>

                                                <label style="display:grid;gap:4px">Harga (Rp)
                                                    <input class="order-filter" type="number" min="0" step="1000" name="service_price" value="<?= e($srv['price']) ?>" required>
                                                </label>

                                                <label style="display:grid;gap:4px">Estimasi Hari
                                                    <input class="order-filter" type="number" min="1" max="60" name="estimated_days" value="<?= (int)$srv['estimated_days'] ?>" required>
                                                </label>

                                                <label style="display:grid;gap:4px">URL Gambar
                                                    <input class="order-filter" name="service_image" value="<?= e($srv['image']) ?>" placeholder="https://... atau path gambar">
                                                </label>

                                                <label style="display:grid;gap:4px">Atau Unggah Gambar Baru
                                                    <input class="order-filter" type="file" name="service_image_file" accept="image/jpeg,image/png,image/webp">
                                                </label>

                                                <label style="grid-column:1 / -1;display:grid;gap:4px">Fitur Deliverables (Pisahkan dengan tanda pipa |)
                                                    <input class="order-filter" name="service_features" value="<?= e($srv['features']) ?>" placeholder="Contoh: Konsep visual | Logo utama & variasi | File siap pakai">
                                                </label>

                                                <label style="grid-column:1 / -1;display:grid;gap:4px">Deskripsi Lengkap
                                                    <textarea class="order-filter" name="service_description" rows="2"><?= e($srv['description']) ?></textarea>
                                                </label>

                                                <div style="grid-column:1 / -1;display:flex;align-items:center;justify-content:space-between">
                                                    <label style="font-size:12px;font-weight:700">
                                                        <input type="checkbox" name="is_available" <?= (int)$srv['is_available'] === 1 ? 'checked' : '' ?>> Tampilkan di Katalog Website
                                                    </label>
                                                    <button class="btn" type="submit"><i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan</button>
                                                </div>
                                            </form>
                                        </details>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </article>

            <!-- FORM TAMBAH LAYANAN BARU -->
            <article class="panel" style="margin-bottom:24px">
                <div class="panel-head">
                    <div>
                        <h2>Tambah Layanan Baru</h2>
                        <p>Tambahkan paket atau layanan baru ke katalog publik Balla’ Fresh.</p>
                    </div>
                </div>
                <div class="panel-body">
                    <form method="post" action="admin_dashboard.php?page=agency#cms-services" enctype="multipart/form-data" style="display:grid;gap:14px">
                        <input type="hidden" name="agency_action" value="save_service">
                        <input type="hidden" name="agency_csrf" value="<?= e($_SESSION['order_status_csrf']) ?>">
                        <input type="hidden" name="service_id" value="0">

                        <div class="ops-grid">
                            <label style="display:grid;gap:6px;font-size:12px;font-weight:700">Kategori Layanan
                                <select class="order-filter" name="category_id">
                                    <?php foreach ($categoriesList as $cat): ?>
                                        <option value="<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label style="display:grid;gap:6px;font-size:12px;font-weight:700">Nama Layanan
                                <input class="order-filter" name="service_name" placeholder="Contoh: Paket Desain Kemasan Botol" required>
                            </label>
                        </div>

                        <div class="ops-grid">
                            <label style="display:grid;gap:6px;font-size:12px;font-weight:700">Harga (Rp)
                                <input class="order-filter" type="number" min="0" step="1000" name="service_price" placeholder="450000" required>
                            </label>

                            <label style="display:grid;gap:6px;font-size:12px;font-weight:700">Estimasi Waktu Pengerjaan (Hari)
                                <input class="order-filter" type="number" min="1" max="60" name="estimated_days" value="3" required>
                            </label>
                        </div>

                        <div class="ops-grid">
                            <label style="display:grid;gap:6px;font-size:12px;font-weight:700">URL Gambar (Opsional)
                                <input class="order-filter" name="service_image" placeholder="https://... atau path gambar lokal">
                            </label>

                            <label style="display:grid;gap:6px;font-size:12px;font-weight:700">Atau Unggah File Gambar (JPG/PNG/WEBP, maks 4 MB)
                                <input class="order-filter" type="file" name="service_image_file" accept="image/jpeg,image/png,image/webp">
                            </label>
                        </div>

                        <label style="display:grid;gap:6px;font-size:12px;font-weight:700">Fitur / Deliverables (Gunakan tanda | sebagai pemisah poin)
                            <input class="order-filter" name="service_features" placeholder="Contoh: 3 Konsep Desain | File Master AI/PSD | Mockup 3D | Revisi Bebas">
                        </label>

                        <label style="display:grid;gap:6px;font-size:12px;font-weight:700">Deskripsi Lengkap Layanan
                            <textarea class="order-filter" name="service_description" rows="3" placeholder="Jelaskan manfaat dan detail pengerjaan layanan ini untuk mitra hidroponik..."></textarea>
                        </label>

                        <div style="display:flex;align-items:center;justify-content:space-between;margin-top:6px">
                            <label style="font-size:13px;font-weight:700">
                                <input type="checkbox" name="is_available" checked> Tampilkan langsung di katalog publik website
                            </label>
                            <button class="btn" type="submit"><i class="fa-solid fa-plus"></i> Tambah Layanan ke Website</button>
                        </div>
                    </form>
                </div>
            </article>

            <!-- 5. SECTION KELOLA ULASAN & RATING MITRA -->
            <article class="panel cms-section-card" id="cms-reviews" style="margin-bottom:24px">
                <div class="panel-head">
                    <div>
                        <h2>Section 5: Moderasi Ulasan &amp; Rating Mitra Pelanggan</h2>
                        <p>Moderasi ulasan yang diberikan oleh mitra bisnis pada halaman detail layanan.</p>
                    </div>
                </div>
                <div class="table-wrap">
                    <table style="min-width:780px">
                        <thead>
                            <tr>
                                <th>Mitra &amp; Usaha</th>
                                <th>Layanan</th>
                                <th>Rating</th>
                                <th>Isi Ulasan</th>
                                <th>Tanggal</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$allReviews): ?>
                                <tr><td colspan="7"><div class="empty">Belum ada ulasan yang masuk dari mitra.</div></td></tr>
                            <?php else: foreach ($allReviews as $rev): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($rev['user_name']) ?></strong>
                                        <small style="display:block;color:var(--muted)"><?= e($rev['business_name'] ?: 'Mitra Hidroponik') ?></small>
                                    </td>
                                    <td>
                                        <span style="font-size:12px;font-weight:700;color:var(--green)">
                                            <?= e($rev['service_name'] ?: 'Layanan #' . $rev['service_id']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="color:#f59e0b;font-weight:800;white-space:nowrap">
                                            <?= str_repeat('★', (int)$rev['rating']) ?> (<?= (int)$rev['rating'] ?>)
                                        </span>
                                    </td>
                                    <td>
                                        <p style="font-size:12px;margin:0;max-width:280px;line-height:1.5">
                                            "<?= e($rev['review_text']) ?>"
                                        </p>
                                    </td>
                                    <td style="font-size:11px;color:var(--muted);white-space:nowrap">
                                        <?= date('d M Y', strtotime((string)$rev['created_at'])) ?>
                                    </td>
                                    <td>
                                        <?php if ((int)$rev['is_approved'] === 1): ?>
                                            <span class="status-pill status-completed"><i class="fa-solid fa-circle"></i> Tampil</span>
                                        <?php else: ?>
                                            <span class="status-pill status-cancelled"><i class="fa-solid fa-circle"></i> Disembunyikan</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display:flex;gap:6px;align-items:center">
                                            <form method="post" action="admin_dashboard.php?page=agency#cms-reviews">
                                                <input type="hidden" name="agency_action" value="toggle_review">
                                                <input type="hidden" name="agency_csrf" value="<?= e($_SESSION['order_status_csrf']) ?>">
                                                <input type="hidden" name="review_id" value="<?= (int)$rev['id'] ?>">
                                                <button class="btn btn-light" type="submit" title="Ubah status tampil">
                                                    <?= (int)$rev['is_approved'] === 1 ? 'Sembunyikan' : 'Tampilkan' ?>
                                                </button>
                                            </form>
                                            <form method="post" action="admin_dashboard.php?page=agency#cms-reviews" onsubmit="return confirm('Hapus ulasan ini?')">
                                                <input type="hidden" name="agency_action" value="delete_review">
                                                <input type="hidden" name="agency_csrf" value="<?= e($_SESSION['order_status_csrf']) ?>">
                                                <input type="hidden" name="review_id" value="<?= (int)$rev['id'] ?>">
                                                <button class="btn btn-light" type="submit" style="color:#dc2626"><i class="fa-regular fa-trash-can"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </article>

            <!-- 6. SECTION KONTAK & ALAMAT WEBSITE -->
            <article class="panel cms-section-card" id="cms-contact" style="margin-bottom:24px">
                <div class="panel-head">
                    <div>
                        <h2>Section 6: Informasi Kontak &amp; Alamat Website</h2>
                        <p>Kelola email narahubung, nomor WhatsApp resmi, dan alamat operasional agensi yang tampil di footer website publik.</p>
                    </div>
                    <span class="admin-badge-member"><i class="fa-solid fa-address-book"></i> Kontak Agensi</span>
                </div>
                <div class="panel-body">
                    <form method="post" action="admin_dashboard.php?page=agency#cms-contact" style="display:grid;gap:16px">
                        <input type="hidden" name="agency_action" value="save_contact_section">
                        <input type="hidden" name="agency_csrf" value="<?= e($_SESSION['order_status_csrf']) ?>">

                        <div class="ops-grid">
                            <label style="display:grid;gap:6px;font-size:12px;font-weight:700">Email Narahubung
                                <input class="order-filter" type="email" name="contact_email" value="<?= e($siteContent['contact_email']) ?>" placeholder="Contoh: ballafresh01@gmail.com" required>
                            </label>

                            <label style="display:grid;gap:6px;font-size:12px;font-weight:700">Nomor WhatsApp Resmi
                                <input class="order-filter" type="text" name="contact_phone" value="<?= e($siteContent['contact_phone']) ?>" placeholder="Contoh: 081213725107" required>
                            </label>
                        </div>

                        <label style="display:grid;gap:6px;font-size:12px;font-weight:700">Alamat Fisik / Lokasi Usaha
                            <input class="order-filter" type="text" name="contact_address" value="<?= e($siteContent['contact_address']) ?>" placeholder="Contoh: Makassar, Sulawesi Selatan, Indonesia" required>
                        </label>

                        <div>
                            <button class="btn" type="submit"><i class="fa-solid fa-floppy-disk"></i> Simpan Kontak &amp; Alamat</button>
                        </div>
                    </form>
                </div>
            </article>

        <?php elseif ($page === 'businesses'): ?>
            <section class="welcome-row">
                <div>
                    <span class="eyebrow">BALLA’ FRESH · MITRA BUSINESS DIRECTORY</span>
                    <h1>Data Usaha &amp; Profil Bisnis Mitra</h1>
                    <p>Informasi identitas usaha UMKM hidroponik, bidang usaha, kepemilikan, media sosial, dan tautan website yang terhubung otomatis dari profil mitra.</p>
                </div>
                <span class="welcome-tag"><span></span> Terintegrasi Form Profil Mitra</span>
            </section>

            <section class="stats-grid" style="grid-template-columns:repeat(3,minmax(0,1fr))">
                <article class="stat-card">
                    <div class="stat-kicker">Total Mitra Terdaftar</div>
                    <div class="stat-value"><?= number_format(count($mitraBusinesses), 0, ',', '.') ?></div>
                    <div class="stat-foot">Akun mitra di platform</div>
                </article>
                <article class="stat-card">
                    <div class="stat-kicker">Profil Usaha Terisi</div>
                    <div class="stat-value"><?= number_format(count(array_filter($mitraBusinesses, fn($b) => !empty($b['business_name']))), 0, ',', '.') ?></div>
                    <div class="stat-foot">Memiliki data bisnis spesifik</div>
                </article>
                <article class="stat-card">
                    <div class="stat-kicker">Memiliki Tautan Web / Medsos</div>
                    <div class="stat-value"><?= number_format(count(array_filter($mitraBusinesses, fn($b) => !empty($b['social_media']) || !empty($b['website_url']))), 0, ',', '.') ?></div>
                    <div class="stat-foot">Mitra dengan presensi digital</div>
                </article>
            </section>

            <article class="panel">
                <div class="panel-head">
                    <div>
                        <h2>Direktori Usaha Mitra Balla’ Fresh</h2>
                        <p>Data operasional bisnis yang diinput langsung oleh pengguna pada tab Informasi Usaha di halaman profil.</p>
                    </div>
                </div>
                <div class="table-wrap">
                    <table style="min-width:1150px">
                        <thead>
                            <tr>
                                <th>Nama Usaha &amp; Bidang</th>
                                <th>Pemilik / Akun Mitra</th>
                                <th>Posisi / Peran</th>
                                <th>Kontak &amp; Presensi Digital</th>
                                <th>Alamat Operasional</th>
                                <th>Deskripsi Usaha</th>
                                <th>Kebutuhan Layanan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$mitraBusinesses): ?>
                                <tr><td colspan="7"><div class="empty">Belum ada data usaha mitra yang terdaftar.</div></td></tr>
                            <?php else: foreach ($mitraBusinesses as $biz):
                                $hasAvatar = !empty($biz['profile_image']) && (str_starts_with($biz['profile_image'], 'http') || file_exists(__DIR__ . '/' . $biz['profile_image']));
                            ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($biz['business_name'])): ?>
                                            <strong style="font-size:14px;color:#2f4f1e;display:block"><?= e($biz['business_name']) ?></strong>
                                            <span class="biz-badge"><?= e($biz['business_type'] ?: 'Hidroponik / Agribisnis') ?></span>
                                        <?php else: ?>
                                            <span style="color:var(--muted);font-style:italic">Belum mengisi nama usaha</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:10px">
                                            <?php if ($hasAvatar): ?>
                                                <img src="<?= e($biz['profile_image']) ?>" alt="Avatar" style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:2px solid #5b8121">
                                            <?php else: ?>
                                                <span class="customer-avatar" style="width:36px;height:36px;border-radius:50%;background:#eaf2e2;color:#3b5816;display:inline-grid;place-items:center;font-weight:800"><?= e(strtoupper(substr((string)$biz['name'],0,1))) ?></span>
                                            <?php endif; ?>
                                            <div>
                                                <strong><?= e($biz['name']) ?></strong>
                                                <small style="display:block;color:var(--muted);font-size:11px"><?= e($biz['email']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span style="font-weight:700;color:#1e293b;font-size:12px"><?= e($biz['business_role'] ?: 'Pemilik Usaha') ?></span>
                                    </td>
                                    <td>
                                        <div style="font-size:12px;display:grid;gap:4px">
                                            <div><i class="fa-solid fa-phone" style="color:#5b8121;margin-right:4px"></i> <?= e($biz['phone'] ?: 'Tidak ada') ?></div>
                                            <?php if (!empty($biz['social_media'])): ?>
                                                <div><i class="fa-brands fa-instagram" style="color:#e1306c;margin-right:4px"></i> <a href="<?= e(str_starts_with($biz['social_media'], 'http') ? $biz['social_media'] : 'https://instagram.com/' . ltrim($biz['social_media'], '@')) ?>" target="_blank" rel="noopener" style="color:#5b8121;text-decoration:underline"><?= e($biz['social_media']) ?></a></div>
                                            <?php endif; ?>
                                            <?php if (!empty($biz['website_url'])): ?>
                                                <div><i class="fa-solid fa-globe" style="color:#0284c7;margin-right:4px"></i> <a href="<?= e(str_starts_with($biz['website_url'], 'http') ? $biz['website_url'] : 'https://' . $biz['website_url']) ?>" target="_blank" rel="noopener" style="color:#5b8121;text-decoration:underline"><?= e($biz['website_url']) ?></a></div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td style="max-width:200px;font-size:12px;color:#475569">
                                        <?= e($biz['business_address'] ?: ($biz['address'] ?: 'Alamat belum diatur')) ?>
                                    </td>
                                    <td style="max-width:220px;font-size:12px;color:#334155;line-height:1.45">
                                        <?= e($biz['business_description'] ?: 'Belum ada penjelasan usaha.') ?>
                                    </td>
                                    <td style="max-width:180px">
                                        <?php if (!empty($biz['service_preferences'])): ?>
                                            <span style="font-size:11px;background:#fef3c7;color:#92400e;padding:4px 8px;border-radius:6px;font-weight:600;display:inline-block"><?= e($biz['service_preferences']) ?></span>
                                        <?php else: ?>
                                            <span style="color:var(--muted);font-size:11px">Semua Solusi</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </article>

        <?php elseif ($page === 'consultations'): ?>
            <section class="welcome-row">
                <div>
                    <span class="eyebrow">BALLA’ FRESH · CONSULTATION DESK &amp; ROOM CHAT</span>
                    <h1>Konsultasi &amp; Room Chat Mitra</h1>
                    <p>Kelola permohonan konsultasi dari pengguna, berikan persetujuan (ACC) untuk membuka sesi obrolan, dan balas chat langsung dengan mitra melalui ruang interaktif.</p>
                </div>
                <span class="welcome-tag"><span></span> Chat Realtime Terhubung</span>
            </section>

            <?php if ($consultationMessage !== ''): ?>
                <div class="order-notice <?= e($consultationMessageType) ?>" style="margin-bottom:20px"><?= e($consultationMessage) ?></div>
            <?php endif; ?>

            <div class="admin-chat-grid">
                <!-- SISI KIRI: DAFTAR TIKET KONSULTASI -->
                <div class="consult-list-box">
                    <div class="consult-list-head" style="display:flex;align-items:center;justify-content:space-between">
                        <div>
                            <strong style="font-size:14px;color:#1e293b">Daftar Tiket Konsultasi</strong>
                            <div style="font-size:11px;color:var(--muted);margin-top:2px"><?= count($allConsultations) ?> total permohonan</div>
                        </div>
                        <?php if ($pendingConsultationCount > 0): ?>
                            <span class="badge-acc-pending"><i class="fa-solid fa-bell"></i> <?= $pendingConsultationCount ?> Butuh ACC</span>
                        <?php endif; ?>
                    </div>
                    <div class="consult-list-body">
                        <?php if (!$allConsultations): ?>
                            <div class="empty" style="padding:40px 10px"><i class="fa-solid fa-comments"></i>Belum ada permohonan konsultasi.</div>
                        <?php else: foreach ($allConsultations as $cItem):
                            $isSelected = ((int)$cItem['id'] === $activeChatId);
                            $cStatus = (string) $cItem['status'];
                        ?>
                            <div class="consult-card-item <?= $isSelected ? 'active' : '' ?> <?= $cStatus === 'pending' ? 'status-pending-acc' : '' ?>">
                                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px">
                                    <span style="font-size:11px;font-weight:700;color:var(--muted)">#TICKET-<?= (int)$cItem['id'] ?></span>
                                    <?php if ($cStatus === 'pending'): ?>
                                        <span class="badge-acc-pending"><i class="fa-solid fa-clock"></i> Menunggu ACC</span>
                                    <?php elseif ($cStatus === 'approved'): ?>
                                        <span class="badge-acc-approved"><i class="fa-solid fa-circle-check"></i> Aktif (ACC)</span>
                                    <?php else: ?>
                                        <span class="badge-acc-completed"><i class="fa-solid fa-check"></i> Selesai</span>
                                    <?php endif; ?>
                                </div>

                                <a href="admin_dashboard.php?page=consultations&amp;chat_id=<?= (int)$cItem['id'] ?>#chatdesk" style="text-decoration:none;color:inherit;display:block">
                                    <strong style="font-size:13.5px;color:#1e293b;display:block"><?= e($cItem['client_name']) ?></strong>
                                    <div style="font-size:11.5px;color:#5b8121;font-weight:600;margin-top:2px"><?= e($cItem['business_name'] ?: 'Mitra Hidroponik') ?></div>
                                    <div style="font-size:11px;color:var(--muted);margin-top:4px;line-height:1.4">
                                        <?= e(mb_strimwidth((string)$cItem['notes'], 0, 85, '...')) ?>
                                    </div>
                                    <div style="font-size:10.5px;color:#94a3b8;margin-top:8px;display:flex;justify-content:space-between">
                                        <span><i class="fa-regular fa-calendar"></i> <?= date('d M Y, H:i', strtotime((string)$cItem['created_at'])) ?></span>
                                        <span><i class="fa-regular fa-message"></i> <?= (int)$cItem['msg_count'] ?> pesan</span>
                                    </div>
                                </a>

                                <div style="margin-top:12px;padding-top:10px;border-top:1px dashed #e2e8f0;display:flex;align-items:center;justify-content:space-between;gap:8px">
                                    <?php if ($cStatus === 'pending'): ?>
                                        <form method="post" style="display:inline">
                                            <input type="hidden" name="consultation_action" value="approve_consultation">
                                            <input type="hidden" name="consultation_csrf" value="<?= e($_SESSION['order_status_csrf']) ?>">
                                            <input type="hidden" name="consultation_id" value="<?= (int)$cItem['id'] ?>">
                                            <button type="submit" class="btn" style="background:#e48e1e;padding:6px 14px;font-size:11.5px;border-radius:8px">
                                                <i class="fa-solid fa-check"></i> ACC / Setujui
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                    <a href="admin_dashboard.php?page=consultations&amp;chat_id=<?= (int)$cItem['id'] ?>#chatdesk" class="btn btn-light" style="padding:6px 12px;font-size:11.5px;margin-left:auto">
                                        <?= $isSelected ? 'Sedang Dibuka' : 'Buka Chat' ?> <i class="fa-solid fa-arrow-right" style="font-size:10px"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>

                <!-- SISI KANAN: LIVE ROOM CHAT -->
                <div class="chat-room-desk" id="chatdesk">
                    <?php if (!$activeConsultation): ?>
                        <div class="empty" style="margin:auto;text-align:center">
                            <i class="fa-solid fa-comments" style="font-size:40px;color:#cbd5e1;margin-bottom:12px"></i>
                            <h3>Pilih Sesi Konsultasi</h3>
                            <p style="color:var(--muted);font-size:13px;max-width:320px">Klik salah satu tiket konsultasi di kolom sebelah kiri untuk meninjau detail dan memulai percakapan.</p>
                        </div>
                    <?php else:
                        $cStatus = (string) $activeConsultation['status'];
                    ?>
                        <!-- HEADER ROOM CHAT -->
                        <div class="chat-room-head">
                            <div style="display:flex;align-items:center;gap:12px">
                                <span class="customer-avatar" style="width:42px;height:42px;border-radius:50%;background:#eaf2e2;color:#3b5816;display:inline-grid;place-items:center;font-weight:800;font-size:15px">
                                    <?= e(strtoupper(substr((string)$activeConsultation['client_name'], 0, 1))) ?>
                                </span>
                                <div>
                                    <div style="display:flex;align-items:center;gap:8px">
                                        <strong style="font-size:15px;color:#1e293b"><?= e($activeConsultation['client_name']) ?></strong>
                                        <?php if ($cStatus === 'pending'): ?>
                                            <span class="badge-acc-pending"><i class="fa-solid fa-clock"></i> Menunggu ACC</span>
                                        <?php elseif ($cStatus === 'approved'): ?>
                                            <span class="badge-acc-approved"><i class="fa-solid fa-circle-check"></i> Aktif</span>
                                        <?php else: ?>
                                            <span class="badge-acc-completed"><i class="fa-solid fa-check"></i> Selesai</span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size:12px;color:var(--muted);margin-top:2px">
                                        <?= e($activeConsultation['business_name'] ?: 'Usaha Hidroponik') ?> · <i class="fa-solid fa-phone" style="font-size:10px"></i> <?= e($activeConsultation['phone']) ?> · <?= e($activeConsultation['category']) ?>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <?php if ($cStatus === 'approved'): ?>
                                    <form method="post" style="display:inline" onsubmit="return confirm('Tandai sesi konsultasi ini sudah selesai?')">
                                        <input type="hidden" name="consultation_action" value="complete_consultation">
                                        <input type="hidden" name="consultation_csrf" value="<?= e($_SESSION['order_status_csrf']) ?>">
                                        <input type="hidden" name="consultation_id" value="<?= (int)$activeConsultation['id'] ?>">
                                        <button type="submit" class="btn btn-light" style="font-size:12px"><i class="fa-solid fa-check-double"></i> Selesaikan Sesi</button>
                                    </form>
                                <?php elseif ($cStatus === 'pending'): ?>
                                    <form method="post" style="display:inline">
                                        <input type="hidden" name="consultation_action" value="approve_consultation">
                                        <input type="hidden" name="consultation_csrf" value="<?= e($_SESSION['order_status_csrf']) ?>">
                                        <input type="hidden" name="consultation_id" value="<?= (int)$activeConsultation['id'] ?>">
                                        <button type="submit" class="btn" style="background:#e48e1e;font-size:12px"><i class="fa-solid fa-check"></i> ACC / Setujui Konsultasi</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- SCROLL AREA PESAN -->
                        <div class="chat-room-scroll" id="adminChatScroll">
                            <!-- Catatan Awal Permohonan Mitra -->
                            <div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;padding:14px 18px;margin-bottom:10px">
                                <div style="font-size:11px;font-weight:700;color:#5b8121;text-transform:uppercase;letter-spacing:0.5px">Topik &amp; Kebutuhan Konsultasi Awal</div>
                                <p style="margin:6px 0 0;font-size:13px;line-height:1.5;color:#334155"><?= nl2br(e($activeConsultation['notes'])) ?></p>
                                <div style="font-size:10.5px;color:#94a3b8;margin-top:8px">Diajukan pada: <?= date('d M Y, H:i', strtotime((string)$activeConsultation['created_at'])) ?> WITA</div>
                            </div>

                            <?php if ($cStatus === 'pending'): ?>
                                <div style="background:#fffbeb;border:1.5px dashed #fcd34d;border-radius:14px;padding:20px;text-align:center;margin:auto 0">
                                    <i class="fa-solid fa-lock" style="font-size:32px;color:#d97706;margin-bottom:8px"></i>
                                    <h3 style="margin:0 0 6px;color:#92400e;font-size:16px">Permohonan Menunggu ACC Admin</h3>
                                    <p style="margin:0 0 16px;font-size:12.5px;color:#b45309">Mitra sedang menunggu persetujuan Anda sebelum ruang obrolan dapat dimulai. Klik tombol di bawah untuk menyetujui (ACC) konsultasi.</p>
                                    <form method="post">
                                        <input type="hidden" name="consultation_action" value="approve_consultation">
                                        <input type="hidden" name="consultation_csrf" value="<?= e($_SESSION['order_status_csrf']) ?>">
                                        <input type="hidden" name="consultation_id" value="<?= (int)$activeConsultation['id'] ?>">
                                        <button type="submit" class="btn" style="background:#e48e1e;padding:10px 24px"><i class="fa-solid fa-check"></i> ACC / Setujui Konsultasi Sekarang</button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <?php if (!$activeChatMessages): ?>
                                    <div class="empty" style="margin:auto;text-align:center">
                                        <i class="fa-regular fa-comment-dots" style="font-size:36px;color:#cbd5e1;margin-bottom:8px"></i>
                                        <p style="color:var(--muted);font-size:13px">Belum ada obrolan tambahan. Kirimkan balasan pertama Anda untuk mitra.</p>
                                    </div>
                                <?php else: foreach ($activeChatMessages as $msg):
                                    $isAdmin = ($msg['sender_type'] === 'admin');
                                    $parsedChat = parseChatAttachments((string)$msg['message']);
                                    $plainText = $parsedChat['text'];
                                    $chatAtts = $parsedChat['attachments'];
                                ?>
                                    <div class="chat-room-msg <?= $isAdmin ? 'admin' : 'mitra' ?>">
                                        <div style="font-size:11px;font-weight:700;color:<?= $isAdmin ? '#5b8121' : '#64748b' ?>;margin-bottom:4px">
                                            <?= $isAdmin ? 'Admin Balla’ Fresh (' . e($msg['sender_name']) . ')' : e($msg['sender_name'] ?: $activeConsultation['client_name']) ?>
                                        </div>
                                        <div class="chat-bubble <?= $isAdmin ? 'admin-bubble' : 'mitra-bubble' ?>">
                                            <?php if ($plainText !== ''): ?>
                                                <div style="word-break:break-word"><?= autoLinkUrls(nl2br(e($plainText))) ?></div>
                                            <?php endif; ?>

                                            <?php if (!empty($chatAtts)): ?>
                                                <div class="chat-attachments-list">
                                                    <?php foreach ($chatAtts as $att):
                                                        $ext = strtolower((string)$att['ext']);
                                                        $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
                                                        $isVideo = in_array($ext, ['mp4', 'webm', 'mov'], true);
                                                    ?>
                                                        <?php if ($isImage): ?>
                                                            <div class="chat-att-card chat-att-image">
                                                                <img src="<?= e($att['url']) ?>" alt="<?= e($att['name']) ?>" class="chat-thumb-img" onclick="openChatLightbox('<?= e($att['url']) ?>', '<?= e($att['name']) ?>')" title="Klik untuk memperbesar gambar">
                                                                <div class="chat-att-meta">
                                                                    <span class="chat-att-name" title="<?= e($att['name']) ?>">
                                                                        <i class="fa-solid fa-image"></i> <?= e($att['name']) ?>
                                                                    </span>
                                                                    <?php if (!empty($att['size'])): ?>
                                                                        <span class="chat-att-size"><?= e($att['size']) ?></span>
                                                                    <?php endif; ?>
                                                                    <a href="<?= e($att['url']) ?>" download="<?= e($att['name']) ?>" class="chat-att-dl" title="Unduh gambar"><i class="fa-solid fa-download"></i></a>
                                                                </div>
                                                            </div>
                                                        <?php elseif ($isVideo): ?>
                                                            <div class="chat-att-card">
                                                                <video src="<?= e($att['url']) ?>" class="chat-player-video" controls preload="metadata"></video>
                                                                <div class="chat-att-meta">
                                                                    <span class="chat-att-name" title="<?= e($att['name']) ?>">
                                                                        <i class="fa-solid fa-video"></i> <?= e($att['name']) ?>
                                                                    </span>
                                                                    <?php if (!empty($att['size'])): ?>
                                                                        <span class="chat-att-size"><?= e($att['size']) ?></span>
                                                                    <?php endif; ?>
                                                                    <a href="<?= e($att['url']) ?>" download="<?= e($att['name']) ?>" class="chat-att-dl" title="Unduh video"><i class="fa-solid fa-download"></i></a>
                                                                </div>
                                                            </div>
                                                        <?php else:
                                                            $docClass = 'default';
                                                            $docIcon = 'fa-file-lines';
                                                            if ($ext === 'pdf') { $docClass = 'pdf'; $docIcon = 'fa-file-pdf'; }
                                                            elseif (in_array($ext, ['doc', 'docx'], true)) { $docClass = 'word'; $docIcon = 'fa-file-word'; }
                                                            elseif (in_array($ext, ['xls', 'xlsx', 'csv'], true)) { $docClass = 'excel'; $docIcon = 'fa-file-excel'; }
                                                            elseif (in_array($ext, ['ppt', 'pptx'], true)) { $docClass = 'powerpoint'; $docIcon = 'fa-file-powerpoint'; }
                                                            elseif ($ext === 'txt') { $docClass = 'text'; $docIcon = 'fa-file-lines'; }
                                                        ?>
                                                            <div class="chat-att-doc">
                                                                <div class="chat-doc-icon <?= $docClass ?>">
                                                                    <i class="fa-solid <?= $docIcon ?>"></i>
                                                                </div>
                                                                <div class="chat-doc-info">
                                                                    <span class="chat-doc-title" title="<?= e($att['name']) ?>"><?= e($att['name']) ?></span>
                                                                    <span class="chat-doc-sub"><?= strtoupper($ext) ?><?= !empty($att['size']) ? ' · ' . e($att['size']) : '' ?></span>
                                                                </div>
                                                                <div class="chat-doc-actions">
                                                                    <a href="<?= e($att['url']) ?>" target="_blank" rel="noopener noreferrer" class="btn-chat-doc" title="Buka berkas di tab baru">
                                                                        <i class="fa-solid fa-arrow-up-right-from-square"></i> Buka
                                                                    </a>
                                                                    <a href="<?= e($att['url']) ?>" download="<?= e($att['name']) ?>" class="btn-chat-doc" title="Unduh berkas">
                                                                        <i class="fa-solid fa-download"></i>
                                                                    </a>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <span class="chat-time-meta">
                                            <?= date('d M, H:i', strtotime((string)$msg['created_at'])) ?>
                                        </span>
                                    </div>
                                <?php endforeach; endif; ?>
                            <?php endif; ?>
                        </div>

                        <!-- INPUT BALASAN ADMIN -->
                        <div class="chat-room-footer">
                            <?php if ($cStatus === 'pending'): ?>
                                <div style="font-size:12px;color:var(--muted);text-align:center;padding:8px 0">
                                    <i class="fa-solid fa-lock" style="margin-right:6px"></i> Silakan setujui (ACC) konsultasi terlebih dahulu untuk mulai membalas pesan.
                                </div>
                            <?php elseif ($cStatus === 'completed'): ?>
                                <div style="font-size:12px;color:var(--muted);text-align:center;padding:8px 0">
                                    <i class="fa-solid fa-circle-check" style="color:#15803d;margin-right:6px"></i> Sesi konsultasi ini telah selesai. Anda dapat meninjau riwayat pesan di atas.
                                </div>
                            <?php else: ?>
                                <form id="adminChatForm" method="post" action="admin_dashboard.php?page=consultations&amp;chat_id=<?= (int)$activeConsultation['id'] ?>#chatdesk" enctype="multipart/form-data" style="display:grid;gap:8px">
                                    <input type="hidden" name="consultation_action" value="send_admin_chat">
                                    <input type="hidden" name="consultation_csrf" value="<?= e($_SESSION['order_status_csrf']) ?>">
                                    <input type="hidden" name="consultation_id" value="<?= (int)$activeConsultation['id'] ?>">

                                    <!-- Chip preview file terpilih -->
                                    <div id="adminFilePreviewWrap" class="admin-file-preview-wrap" style="display:none"></div>

                                    <div style="display:flex;gap:10px;align-items:flex-end">
                                        <label for="adminChatFilesInput" class="btn" style="height:52px;width:52px;padding:0;display:inline-flex;align-items:center;justify-content:center;border-radius:10px;background:#f8fafc;color:#475569;border:1px solid #cbd5e1;cursor:pointer;flex-shrink:0" title="Lampirkan File (Gambar, Video, PDF, Dokumen - Maks. 5 file, 20MB/file)">
                                            <i class="fa-solid fa-paperclip" style="font-size:18px"></i>
                                        </label>
                                        <input type="file" name="chat_files[]" id="adminChatFilesInput" multiple accept=".jpg,.jpeg,.png,.webp,.gif,.mp4,.webm,.mov,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv" style="display:none">
                                        <textarea class="order-filter" name="chat_message" id="adminChatMessage" rows="2" placeholder="Ketik balasan untuk mitra <?= e($activeConsultation['client_name']) ?> di sini..." style="resize:none;flex:1"></textarea>
                                        <button type="submit" class="btn" style="height:52px;padding:0 22px;display:inline-flex;align-items:center;gap:8px">
                                            <i class="fa-solid fa-paper-plane"></i> Kirim
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($page === 'articles'): ?>
            <section class="welcome-row">
                <div>
                    <span class="eyebrow">BALLA’ FRESH · CONTENT MANAGEMENT SYSTEM</span>
                    <h1><?= $isArticleEditorOpen ? ($editArticleData ? 'Edit Konten Artikel' : 'Tulis Artikel Edukasi Baru') : 'Kelola Artikel &amp; Edukasi' ?></h1>
                    <p><?= $isArticleEditorOpen ? 'Gunakan rich text editor profesional untuk memformat teks, ringkasan, dan wawasan edukasi hidroponik untuk mitra Balla’ Fresh.' : 'Kelola artikel panduan budidaya, branding, dan pemasaran hidroponik. Artikel yang dipublikasikan akan tampil di katalog artikel dan beranda mitra.' ?></p>
                </div>
                <div>
                    <?php if ($isArticleEditorOpen): ?>
                        <a href="admin_dashboard.php?page=articles" class="btn btn-light" style="padding:10px 18px;border-radius:10px">
                            <i class="fa-solid fa-arrow-left"></i> Kembali ke Daftar Artikel
                        </a>
                    <?php else: ?>
                        <a href="admin_dashboard.php?page=articles&amp;action=create#articleForm" class="btn" style="padding:10px 18px;border-radius:10px">
                            <i class="fa-solid fa-plus"></i> Tulis Artikel Baru
                        </a>
                    <?php endif; ?>
                </div>
            </section>

            <?php if ($articleMessage !== ''): ?>
                <div class="order-notice <?= e($articleMessageType) ?>" style="margin-bottom:20px"><?= $articleMessage ?></div>
            <?php endif; ?>

            <!-- STATISTIK KONTEN ARTIKEL (DISEMBUNYIKAN SAAT EDITOR DIBUKA) -->
            <?php if (!$isArticleEditorOpen): ?>
                <section class="metrics-grid" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 24px;">
                    <article class="metric-card">
                        <span class="metric-label"><i class="fa-solid fa-newspaper"></i> Total Artikel</span>
                        <div class="metric-value"><?= (int)$artStats['total'] ?></div>
                        <div class="metric-caption">Semua konten artikel di sistem</div>
                    </article>
                    <article class="metric-card">
                        <span class="metric-label"><i class="fa-solid fa-circle-check"></i> Artikel Publik</span>
                        <div class="metric-value" style="color:var(--green)"><?= (int)$artStats['published'] ?></div>
                        <div class="metric-caption">Tayang aktif untuk mitra</div>
                    </article>
                    <article class="metric-card">
                        <span class="metric-label"><i class="fa-solid fa-star"></i> Unggulan / Hero</span>
                        <div class="metric-value" style="color:#d97706"><?= (int)$artStats['featured'] ?></div>
                        <div class="metric-caption">Tampil pada banner utama</div>
                    </article>
                    <article class="metric-card">
                        <span class="metric-label"><i class="fa-solid fa-eye"></i> Total Pembaca</span>
                        <div class="metric-value" style="color:#2563eb"><?= number_format((int)$artStats['views']) ?></div>
                        <div class="metric-caption">Total views seluruh artikel</div>
                    </article>
                </section>
            <?php endif; ?>

            <!-- FORMULIR ARTIKEL DENGAN RICH TEXT EDITOR & LAYOUT BERSIH TERSTRUKTUR -->
            <?php if ($isArticleEditorOpen): ?>
                <div id="articleForm" style="margin-bottom: 28px;">
                    <form method="post" action="admin_dashboard.php?page=articles" enctype="multipart/form-data" id="articleMainForm">
                        <input type="hidden" name="article_action" value="save_article">
                        <input type="hidden" name="article_csrf" value="<?= e($_SESSION['order_status_csrf']) ?>">
                        <input type="hidden" name="article_id" value="<?= $editArticleData ? (int)$editArticleData['id'] : 0 ?>">
                        <input type="hidden" name="existing_image" value="<?= $editArticleData ? e($editArticleData['image']) : '' ?>">

                        <div class="article-form-grid-layout">
                            <!-- KOLOM KIRI (KONTEN UTAMA & INFORMASI INTI) -->
                            <div style="display:flex;flex-direction:column;gap:18px">

                                <!-- 1. INFORMASI ARTIKEL -->
                                <div class="article-card-section">
                                    <div class="article-section-header">
                                        <div class="article-section-icon"><i class="fa-solid fa-file-lines"></i></div>
                                        <div>
                                            <h3 class="article-section-title">Informasi Artikel</h3>
                                            <p class="article-section-subtitle">Tentukan judul artikel, slug tautan, kategori pembahasan, dan estimasi waktu baca.</p>
                                        </div>
                                    </div>
                                    <div class="article-section-body">
                                        <div>
                                            <label class="form-field-label">Judul Artikel <span class="required-star">*</span></label>
                                            <input class="order-filter article-input-title" type="text" name="title" id="articleTitleInput" value="<?= $editArticleData ? e($editArticleData['title']) : '' ?>" placeholder="Contoh: Strategi Efektif Menjaga Kualitas dan Kesegaran Sayur Hidroponik..." required>
                                        </div>

                                        <div style="display:grid;grid-template-columns: 1fr 1fr 140px;gap:14px;margin-top:14px">
                                            <div>
                                                <label class="form-field-label">Slug URL <small style="color:var(--muted);font-weight:normal">(otomatis dari judul)</small></label>
                                                <input class="order-filter" type="text" name="slug" id="articleSlugInput" value="<?= $editArticleData ? e($editArticleData['slug']) : '' ?>" placeholder="strategi-efektif-sayur-hidroponik">
                                            </div>
                                            <div>
                                                <label class="form-field-label">Kategori Artikel <span class="required-star">*</span></label>
                                                <?php $curCat = $editArticleData['category'] ?? 'Branding & Kemasan'; ?>
                                                <select class="order-filter" name="category" required>
                                                    <option value="Branding & Kemasan" <?= ($curCat === 'Branding & Kemasan') ? 'selected' : '' ?>>Branding &amp; Kemasan</option>
                                                    <option value="Budidaya & Teknis" <?= ($curCat === 'Budidaya & Teknis') ? 'selected' : '' ?>>Budidaya &amp; Teknis</option>
                                                    <option value="Pemasaran & Penjualan" <?= ($curCat === 'Pemasaran & Penjualan') ? 'selected' : '' ?>>Pemasaran &amp; Penjualan</option>
                                                    <option value="Manajemen Bisnis" <?= ($curCat === 'Manajemen Bisnis') ? 'selected' : '' ?>>Manajemen Bisnis</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="form-field-label">Estimasi Baca</label>
                                                <input class="order-filter" type="text" name="read_time" value="<?= $editArticleData ? e($editArticleData['read_time']) : '5 menit baca' ?>" placeholder="5 menit">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- 2. RINGKASAN ARTIKEL (EXCERPT) -->
                                <div class="article-card-section">
                                    <div class="article-section-header">
                                        <div class="article-section-icon"><i class="fa-solid fa-align-left"></i></div>
                                        <div>
                                            <h3 class="article-section-title">Ringkasan Artikel (Excerpt)</h3>
                                            <p class="article-section-subtitle">Ringkasan singkat 1-2 kalimat untuk preview di kartu katalog dan media sosial.</p>
                                        </div>
                                    </div>
                                    <div class="article-section-body">
                                        <textarea class="order-filter" name="excerpt" rows="2" style="resize:vertical" placeholder="Tulis ringkasan singkat artikel di sini..."><?= $editArticleData ? e($editArticleData['excerpt']) : '' ?></textarea>
                                    </div>
                                </div>

                                <!-- 3. KONTEN UTAMA MENGGUNAKAN RICH TEXT EDITOR -->
                                <div class="article-card-section">
                                    <div class="article-section-header" style="justify-content:space-between">
                                        <div style="display:flex;align-items:center;gap:12px">
                                            <div class="article-section-icon"><i class="fa-solid fa-feather-pointed"></i></div>
                                            <div>
                                                <h3 class="article-section-title">Konten Utama Artikel <span class="required-star">*</span></h3>
                                                <p class="article-section-subtitle">Format teks artikel menggunakan toolbar editor modern di bawah.</p>
                                            </div>
                                        </div>
                                        <span style="font-size:11px;color:var(--muted);font-weight:700"><i class="fa-solid fa-pen-to-square"></i> Rich Text Active</span>
                                    </div>
                                    <div class="article-section-body" style="padding:0">
                                        <div class="rte-editor-container">
                                            <!-- TOOLBAR -->
                                            <div class="rte-toolbar" id="articleEditorToolbar">
                                                <!-- Tool Group 1: Undo / Redo -->
                                                <div class="rte-tool-group">
                                                    <button type="button" class="rte-btn" data-command="undo" title="Urungkan (Undo)"><i class="fa-solid fa-rotate-left"></i></button>
                                                    <button type="button" class="rte-btn" data-command="redo" title="Ulangi (Redo)"><i class="fa-solid fa-rotate-right"></i></button>
                                                </div>
                                                <div class="rte-divider"></div>

                                                <!-- Tool Group 2: Headings & Font Size -->
                                                <div class="rte-tool-group">
                                                    <select class="rte-select" id="rteHeadingSelect" title="Format Judul & Paragraf">
                                                        <option value="p">Normal (Paragraf)</option>
                                                        <option value="h1">Judul Utama (H1)</option>
                                                        <option value="h2">Sub-Judul Besar (H2)</option>
                                                        <option value="h3">Sub-Judul Sedang (H3)</option>
                                                    </select>

                                                    <select class="rte-select" id="rteFontSizeSelect" title="Pilih Ukuran Font">
                                                        <option value="">Ukuran Font</option>
                                                        <option value="12px">12 px (Kecil)</option>
                                                        <option value="14px">14 px (Standar)</option>
                                                        <option value="16px">16 px (Sedang)</option>
                                                        <option value="18px">18 px (Agak Besar)</option>
                                                        <option value="24px">24 px (Besar)</option>
                                                        <option value="32px">32 px (Ekstra Besar)</option>
                                                    </select>
                                                </div>
                                                <div class="rte-divider"></div>

                                                <!-- Tool Group 3: Formatting (Bold, Italic, Underline, Strike) -->
                                                <div class="rte-tool-group">
                                                    <button type="button" class="rte-btn" data-command="bold" title="Tebal (Bold)"><i class="fa-solid fa-bold"></i></button>
                                                    <button type="button" class="rte-btn" data-command="italic" title="Miring (Italic)"><i class="fa-solid fa-italic"></i></button>
                                                    <button type="button" class="rte-btn" data-command="underline" title="Garis Bawah (Underline)"><i class="fa-solid fa-underline"></i></button>
                                                    <button type="button" class="rte-btn" data-command="strikeThrough" title="Coret (Strikethrough)"><i class="fa-solid fa-strikethrough"></i></button>
                                                </div>
                                                <div class="rte-divider"></div>

                                                <!-- Tool Group 4: Alignment -->
                                                <div class="rte-tool-group">
                                                    <button type="button" class="rte-btn" data-command="justifyLeft" title="Rata Kiri"><i class="fa-solid fa-align-left"></i></button>
                                                    <button type="button" class="rte-btn" data-command="justifyCenter" title="Rata Tengah"><i class="fa-solid fa-align-center"></i></button>
                                                    <button type="button" class="rte-btn" data-command="justifyRight" title="Rata Kanan"><i class="fa-solid fa-align-right"></i></button>
                                                </div>
                                                <div class="rte-divider"></div>

                                                <!-- Tool Group 5: Lists -->
                                                <div class="rte-tool-group">
                                                    <button type="button" class="rte-btn" data-command="insertUnorderedList" title="Daftar Poin (Bullet List)"><i class="fa-solid fa-list-ul"></i></button>
                                                    <button type="button" class="rte-btn" data-command="insertOrderedList" title="Daftar Bernomor (Numbered List)"><i class="fa-solid fa-list-ol"></i></button>
                                                </div>
                                                <div class="rte-divider"></div>

                                                <!-- Tool Group 6: Links & Extras -->
                                                <div class="rte-tool-group">
                                                    <button type="button" class="rte-btn" id="rteBtnInsertLink" title="Sisipkan Tautan Web"><i class="fa-solid fa-link"></i></button>
                                                    <button type="button" class="rte-btn" data-command="unlink" title="Hapus Tautan"><i class="fa-solid fa-link-slash"></i></button>
                                                    <button type="button" class="rte-btn" data-command="removeFormat" title="Hapus Pemformatan"><i class="fa-solid fa-text-slash"></i></button>
                                                </div>
                                            </div>

                                            <!-- EDITABLE CANVAS (WYSIWYG) -->
                                            <div class="rte-canvas" id="articleEditorCanvas" contenteditable="true" spellcheck="false" data-placeholder="Tuliskan isi artikel edukasi lengkap di sini..."></div>

                                            <!-- HIDDEN TEXTAREA UNTUK POST DATA FORM -->
                                            <textarea name="content" id="articleContentInput" style="display:none" required><?= $editArticleData ? e($editArticleData['content']) : '' ?></textarea>

                                            <!-- FOOTER EDITOR -->
                                            <div class="rte-footer">
                                                <span id="rteWordCount"><i class="fa-solid fa-pen-nib" style="margin-right:4px"></i> 0 kata</span>
                                                <span style="opacity:0.4">|</span>
                                                <span id="rteCharCount">0 karakter</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- KOLOM KANAN (SIDEBAR FORM: PUBLIKASI, GAMBAR, PENULIS) -->
                            <div style="display:flex;flex-direction:column;gap:18px">

                                <!-- 4. PENGATURAN PUBLIKASI & AKSI -->
                                <div class="article-card-section">
                                    <div class="article-section-header">
                                        <div class="article-section-icon"><i class="fa-solid fa-sliders"></i></div>
                                        <div>
                                            <h3 class="article-section-title">Status Publikasi</h3>
                                        </div>
                                    </div>
                                    <div class="article-section-body">
                                        <div class="pub-toggle-card">
                                            <label class="pub-toggle-label">
                                                <input type="checkbox" name="is_published" value="1" <?= (!$editArticleData || !empty($editArticleData['is_published'])) ? 'checked' : '' ?> class="pub-checkbox green">
                                                <div>
                                                    <strong style="display:block;font-size:13px;color:#0f172a">Publikasikan Artikel</strong>
                                                    <small style="display:block;font-size:11.5px;color:var(--muted)">Artikel aktif dan tampil di katalog publik website.</small>
                                                </div>
                                            </label>
                                        </div>

                                        <div class="pub-toggle-card" style="margin-top:10px">
                                            <label class="pub-toggle-label">
                                                <input type="checkbox" name="is_featured" value="1" <?= ($editArticleData && !empty($editArticleData['is_featured'])) ? 'checked' : '' ?> class="pub-checkbox orange">
                                                <div>
                                                    <strong style="display:block;font-size:13px;color:#0f172a">Artikel Unggulan (Hero)</strong>
                                                    <small style="display:block;font-size:11.5px;color:var(--muted)">Ditampilkan pada banner utama halaman artikel.</small>
                                                </div>
                                            </label>
                                        </div>

                                        <div style="margin-top:18px;display:grid;gap:8px">
                                            <button class="btn btn-primary" type="submit" style="width:100%;padding:12px;font-size:13px;font-weight:700;display:inline-flex;align-items:center;justify-content:center;gap:8px;border-radius:10px">
                                                <i class="fa-solid fa-floppy-disk"></i> <?= $editArticleData ? 'Simpan Perubahan' : 'Simpan &amp; Terbitkan' ?>
                                            </button>
                                            <a href="admin_dashboard.php?page=articles" class="btn btn-light" style="width:100%;padding:10px;font-size:12px;text-align:center;border-radius:10px">
                                                Batal &amp; Kembali
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                <!-- 5. GAMBAR SAMPUL ARTIKEL -->
                                <div class="article-card-section">
                                    <div class="article-section-header">
                                        <div class="article-section-icon"><i class="fa-solid fa-image"></i></div>
                                        <div>
                                            <h3 class="article-section-title">Gambar Sampul</h3>
                                        </div>
                                    </div>
                                    <div class="article-section-body">
                                        <?php 
                                            $previewImg = ($editArticleData && !empty($editArticleData['image'])) ? $editArticleData['image'] : 'https://images.unsplash.com/photo-1540420773420-3366772f4999?auto=format&fit=crop&w=1200&q=80';
                                        ?>
                                        <div>
                                            <img id="articleCoverPreview" src="<?= e($previewImg) ?>" alt="Sampul Artikel" style="width:100%;height:140px;object-fit:cover;border-radius:10px;border:1px solid var(--line);background:#f1f5f9">
                                        </div>

                                        <div style="margin-top:12px">
                                            <label class="form-field-label">Unggah File Gambar <small style="color:var(--muted)">(JPG/PNG/WEBP maks 4MB)</small></label>
                                            <input class="order-filter" type="file" name="article_image_file" id="articleImageFileInput" accept="image/jpeg,image/png,image/webp" style="font-size:11.5px">
                                        </div>

                                        <div style="margin-top:10px">
                                            <label class="form-field-label">Atau URL Gambar Eksternal</label>
                                            <input class="order-filter" type="text" name="article_image_url" id="articleImageUrlInput" value="<?= ($editArticleData && str_starts_with((string)$editArticleData['image'], 'http')) ? e($editArticleData['image']) : '' ?>" placeholder="https://images.unsplash.com/photo-...">
                                        </div>
                                    </div>
                                </div>

                                <!-- 6. INFORMASI PENULIS -->
                                <div class="article-card-section">
                                    <div class="article-section-header">
                                        <div class="article-section-icon"><i class="fa-solid fa-user-pen"></i></div>
                                        <div>
                                            <h3 class="article-section-title">Informasi Penulis</h3>
                                        </div>
                                    </div>
                                    <div class="article-section-body">
                                        <div>
                                            <label class="form-field-label">Nama Penulis <span class="required-star">*</span></label>
                                            <input class="order-filter" type="text" name="author_name" value="<?= $editArticleData ? e($editArticleData['author_name']) : 'Tim Balla’ Fresh' ?>" placeholder="Nama penulis" required>
                                        </div>

                                        <div style="margin-top:10px">
                                            <label class="form-field-label">Jabatan / Peran Penulis</label>
                                            <input class="order-filter" type="text" name="author_role" value="<?= $editArticleData ? e($editArticleData['author_role']) : 'Spesialis Branding & Konsultan' ?>" placeholder="Contoh: Konsultan SOP Hidroponik">
                                        </div>

                                        <div style="margin-top:10px">
                                            <label class="form-field-label">Avatar Penulis (URL Gambar)</label>
                                            <div style="display:flex;gap:10px;align-items:center">
                                                <img id="authorAvatarPreview" src="<?= $editArticleData ? e($editArticleData['author_avatar']) : 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80' ?>" alt="Avatar" style="width:38px;height:38px;border-radius:50%;object-fit:cover;border:1px solid var(--line);flex-shrink:0">
                                                <input class="order-filter" type="text" name="author_avatar" id="authorAvatarInput" value="<?= $editArticleData ? e($editArticleData['author_avatar']) : 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=200&q=80' ?>" placeholder="https://images.unsplash.com/..." style="flex:1">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <!-- TABEL DAFTAR ARTIKEL -->
            <article class="panel">
                <div class="panel-head">
                    <div>
                        <h2>Daftar Artikel &amp; Edukasi Terdaftar</h2>
                        <p>Kelola artikel, ubah status publikasi atau status unggulan, dan pantau jumlah pembaca.</p>
                    </div>
                    <div style="display:flex;align-items:center;gap:10px">
                        <a href="artikel.php" target="_blank" class="btn btn-light" style="font-size:12px;padding:6px 12px">
                            <i class="fa-solid fa-arrow-up-right-from-square"></i> Buka Artikel Publik
                        </a>
                        <div style="font-size:12px;color:var(--muted)">
                            Total: <strong><?= count($allArticlesList) ?></strong> artikel
                        </div>
                    </div>
                </div>

                <!-- FILTER KATEGORI & PENCARIAN LIVE -->
                <div style="padding:14px 18px 0;display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap">
                    <div class="cms-nav-pills" style="margin-bottom:0;padding:4px 8px">
                        <button type="button" class="cms-pill active filter-art-btn" data-category="all" style="cursor:pointer">Semua (<?= count($allArticlesList) ?>)</button>
                        <button type="button" class="cms-pill filter-art-btn" data-category="Branding &amp; Kemasan" style="cursor:pointer">Branding &amp; Kemasan</button>
                        <button type="button" class="cms-pill filter-art-btn" data-category="Budidaya &amp; Teknis" style="cursor:pointer">Budidaya &amp; Teknis</button>
                        <button type="button" class="cms-pill filter-art-btn" data-category="Pemasaran &amp; Penjualan" style="cursor:pointer">Pemasaran &amp; Penjualan</button>
                        <button type="button" class="cms-pill filter-art-btn" data-category="Manajemen Bisnis" style="cursor:pointer">Manajemen Bisnis</button>
                    </div>
                    <div style="position:relative">
                        <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:12px"></i>
                        <input type="text" id="adminArticleSearch" class="order-filter" placeholder="Cari judul atau penulis..." style="padding-left:32px;min-width:240px">
                    </div>
                </div>

                <div class="table-wrap">
                    <table style="min-width: 900px">
                        <thead>
                            <tr>
                                <th style="width:70px">Sampul</th>
                                <th>Judul Artikel &amp; Kategori</th>
                                <th>Penulis</th>
                                <th style="text-align:center">Dibaca</th>
                                <th style="text-align:center">Unggulan</th>
                                <th style="text-align:center">Status</th>
                                <th style="text-align:center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="adminArticlesTbody">
                            <?php if (empty($allArticlesList)): ?>
                                <tr>
                                    <td colspan="7"><div class="empty">Belum ada artikel. Silakan gunakan form di atas untuk membuat artikel pertama.</div></td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($allArticlesList as $art):
                                    $artImg = !empty($art['image']) ? $art['image'] : 'https://images.unsplash.com/photo-1540420773420-3366772f4999?auto=format&fit=crop&w=200&q=80';
                                ?>
                                    <tr class="admin-article-row" data-category="<?= e($art['category']) ?>" data-title="<?= e(strtolower($art['title'])) ?>" data-author="<?= e(strtolower($art['author_name'])) ?>">
                                        <td>
                                            <img src="<?= e($artImg) ?>" alt="<?= e($art['title']) ?>" class="art-admin-thumb">
                                        </td>
                                        <td>
                                            <div style="font-weight:700;color:#0f172a;font-size:13px;line-height:1.35;max-width:360px">
                                                <?= e($art['title']) ?>
                                            </div>
                                            <div style="margin-top:4px;display:flex;align-items:center;gap:6px">
                                                <span class="badge-cat-pill"><?= e($art['category']) ?></span>
                                                <span style="font-size:11px;color:#94a3b8"><i class="fa-regular fa-clock"></i> <?= e($art['read_time']) ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight:600;font-size:12.5px;color:#334155"><?= e($art['author_name']) ?></div>
                                            <div style="font-size:11px;color:var(--muted)"><?= date('d M Y', strtotime((string)$art['created_at'])) ?></div>
                                        </td>
                                        <td style="text-align:center;font-weight:700;font-size:12.5px;color:#2563eb">
                                            <?= number_format((int)$art['views_count']) ?>
                                        </td>
                                        <td style="text-align:center">
                                            <form method="post" action="admin_dashboard.php?page=articles" style="display:inline">
                                                <input type="hidden" name="article_action" value="toggle_featured">
                                                <input type="hidden" name="article_csrf" value="<?= e($_SESSION['order_status_csrf']) ?>">
                                                <input type="hidden" name="article_id" value="<?= (int)$art['id'] ?>">
                                                <?php if (!empty($art['is_featured'])): ?>
                                                    <button type="submit" class="badge-featured" title="Klik untuk nonaktifkan hero" style="border:none;cursor:pointer">
                                                        <i class="fa-solid fa-star"></i> Unggulan
                                                    </button>
                                                <?php else: ?>
                                                    <button type="submit" class="badge-draft" title="Klik untuk jadikan unggulan" style="border:none;cursor:pointer">
                                                        <i class="fa-regular fa-star"></i> Standar
                                                    </button>
                                                <?php endif; ?>
                                            </form>
                                        </td>
                                        <td style="text-align:center">
                                            <form method="post" action="admin_dashboard.php?page=articles" style="display:inline">
                                                <input type="hidden" name="article_action" value="toggle_publish">
                                                <input type="hidden" name="article_csrf" value="<?= e($_SESSION['order_status_csrf']) ?>">
                                                <input type="hidden" name="article_id" value="<?= (int)$art['id'] ?>">
                                                <?php if (!empty($art['is_published'])): ?>
                                                    <button type="submit" class="badge-published" title="Klik untuk sembunyikan artikel" style="border:none;cursor:pointer">
                                                        <i class="fa-solid fa-circle-check"></i> Publik
                                                    </button>
                                                <?php else: ?>
                                                    <button type="submit" class="badge-draft" title="Klik untuk publikasikan artikel" style="border:none;cursor:pointer">
                                                        <i class="fa-solid fa-eye-slash"></i> Draf
                                                    </button>
                                                <?php endif; ?>
                                            </form>
                                        </td>
                                        <td style="text-align:center">
                                            <div style="display:inline-flex;align-items:center;gap:6px">
                                                <a href="baca_artikel.php?slug=<?= urlencode($art['slug']) ?>" target="_blank" class="btn btn-light" style="padding:5px 9px;font-size:11.5px" title="Lihat di Website">
                                                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                                </a>
                                                <a href="admin_dashboard.php?page=articles&amp;edit_article=<?= (int)$art['id'] ?>#articleForm" class="btn btn-light" style="padding:5px 9px;font-size:11.5px" title="Edit Artikel">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </a>
                                                <form method="post" action="admin_dashboard.php?page=articles" style="display:inline" onsubmit="return confirm('Apakah Anda yakin ingin menghapus artikel ini secara permanen?')">
                                                    <input type="hidden" name="article_action" value="delete_article">
                                                    <input type="hidden" name="article_csrf" value="<?= e($_SESSION['order_status_csrf']) ?>">
                                                    <input type="hidden" name="article_id" value="<?= (int)$art['id'] ?>">
                                                    <button type="submit" class="btn btn-light" style="padding:5px 9px;font-size:11.5px;color:#ef4444" title="Hapus Artikel">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </article>

        <?php elseif ($page === 'admins'): ?>
            <!-- ========================================================== -->
            <!-- HALAMAN KELOLA AKSES ADMINISTRATOR & STATUS REAL-TIME      -->
            <!-- ========================================================== -->
            <section class="welcome-row">
                <div>
                    <span class="eyebrow">BALLA’ FRESH · AKSES &amp; KEAMANAN SISTEM</span>
                    <h1>Kelola Akses Administrator</h1>
                    <p>Daftar seluruh personil yang memegang hak akses Dashboard Admin Balla' Fresh beserta status aktivitas real-time.</p>
                </div>
                <span class="welcome-tag">
                    <span class="status-dot-online" style="margin-right:4px;"></span>
                    <?= (int)$onlineAdminCount ?> Admin Sedang Online
                </span>
            </section>

            <?php if ($adminManageMessage !== ''): ?>
                <div class="order-notice <?= e($adminManageMessageType) ?>" style="margin-bottom:20px;"><?= e($adminManageMessage) ?></div>
            <?php endif; ?>

            <!-- Ringkasan Statistik Administrator -->
            <div class="order-summary-grid" style="margin-bottom:22px;">
                <div class="order-mini-stat">
                    <span>Total Administrator</span>
                    <strong><?= (int)$totalAdminsCount ?></strong>
                </div>
                <div class="order-mini-stat">
                    <span>Sedang Online</span>
                    <strong style="color:#059669;display:flex;align-items:center;gap:6px;">
                        <span class="status-dot-online"></span> <?= (int)$onlineAdminCount ?>
                    </strong>
                </div>
                <div class="order-mini-stat">
                    <span>Admin Utama (Owner)</span>
                    <strong style="color:#b45309;"><?= (int)$ownerAdminCount ?></strong>
                </div>
                <div class="order-mini-stat">
                    <span>Admin Operasional</span>
                    <strong><?= max(0, (int)$totalAdminsCount - (int)$ownerAdminCount) ?></strong>
                </div>
            </div>

            <!-- Panel Form Tambah Admin Baru (Khusus Admin Utama / Owner) -->
            <?php if ($adminIsOwner === 1): ?>
                <article class="admin-form-panel">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap;gap:10px;">
                        <div>
                            <h3 style="margin:0 0 4px;font-size:16px;font-weight:800;font-family:'Manrope',sans-serif;color:#1e293b;display:flex;align-items:center;gap:8px;">
                                <i class="fa-solid fa-user-plus" style="color:#5b8121;"></i> Tambah Administrator Baru
                            </h3>
                            <p style="margin:0;font-size:12px;color:#64748b;">Sebagai Admin Utama (Owner), Anda dapat mendaftarkan personil pengelola baru ke dashboard.</p>
                        </div>
                        <span class="admin-role-badge owner"><i class="fa-solid fa-crown"></i> Hak Akses Owner</span>
                    </div>

                    <form method="post" action="admin_dashboard.php?page=admins">
                        <input type="hidden" name="action" value="create_admin">
                        <input type="hidden" name="csrf" value="<?= e($_SESSION['admin_manage_csrf'] ?? '') ?>">

                        <div class="admin-form-grid">
                            <div class="admin-form-field">
                                <label for="admin_name">Nama Lengkap *</label>
                                <input type="text" id="admin_name" name="name" required placeholder="Contoh: Ahmad Ridwan" maxlength="100">
                            </div>
                            <div class="admin-form-field">
                                <label for="admin_email">Alamat Email *</label>
                                <input type="email" id="admin_email" name="email" required placeholder="admin@ballafresh.com" maxlength="150">
                            </div>
                            <div class="admin-form-field">
                                <label for="admin_phone">Nomor Telepon / WhatsApp</label>
                                <input type="text" id="admin_phone" name="phone" placeholder="Contoh: 081234567890" maxlength="50">
                            </div>
                            <div class="admin-form-field">
                                <label for="admin_role">Peran &amp; Wewenang *</label>
                                <select id="admin_role" name="role" required>
                                    <option value="admin" selected>Admin Operasional (Kelola Pesanan, Layanan, Artikel)</option>
                                    <option value="owner">Admin Utama / Owner (Hak Akses Penuh Sistem)</option>
                                </select>
                            </div>
                            <div class="admin-form-field">
                                <label for="admin_password">Password Akses Baru *</label>
                                <input type="password" id="admin_password" name="password" required placeholder="Minimal 8 karakter" minlength="8">
                            </div>
                            <div class="admin-form-field">
                                <label for="admin_confirm_password">Konfirmasi Password *</label>
                                <input type="password" id="admin_confirm_password" name="confirm_password" required placeholder="Ulangi password" minlength="8">
                            </div>
                        </div>

                        <div style="display:flex;justify-content:flex-end;">
                            <button type="submit" class="btn btn-primary" style="padding:10px 22px;border-radius:10px;font-weight:700;">
                                <i class="fa-solid fa-user-shield"></i> Daftarkan Akses Admin
                            </button>
                        </div>
                    </form>
                </article>
            <?php else: ?>
                <div class="bi-tracking-notice" style="margin-bottom:24px;">
                    <i class="fa-solid fa-lock" style="font-size:16px;color:#e48e1e;"></i>
                    <div>
                        <strong>Informasi Hak Akses:</strong> Anda masuk sebagai Admin Operasional. Penambahan admin baru dan perubahan hak akses hanya dapat dilakukan oleh <strong>Admin Utama (Owner)</strong>.
                    </div>
                </div>
            <?php endif; ?>

            <!-- Tabel Daftar Seluruh Administrator -->
            <article class="panel">
                <div class="panel-head">
                    <div>
                        <h2>Daftar Pengelola Agency &amp; Administrator</h2>
                        <p>Catatan seluruh akun yang memiliki hak akses login ke dashboard admin Balla' Fresh.</p>
                    </div>
                    <span class="welcome-tag"><span></span> Zona Waktu: Asia/Makassar (WITA)</span>
                </div>

                <div class="table-wrap">
                    <table class="order-table">
                        <thead>
                            <tr>
                                <th>Administrator</th>
                                <th>Kontak</th>
                                <th>Peran</th>
                                <th>Status Real-Time</th>
                                <th>Hak Akses</th>
                                <th>Terdaftar Sejak</th>
                                <th style="text-align:right;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($adminList)): ?>
                                <tr>
                                    <td colspan="7"><div class="empty">Belum ada data administrator tersimpan.</div></td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($adminList as $adm): 
                                    $aId = (int)$adm['id'];
                                    $isSelf = ($aId === $adminId);
                                    $isAdmOwner = ((int)($adm['is_owner'] ?? 0) === 1);
                                    $isApproved = ((int)($adm['is_approved'] ?? 0) === 1);
                                    $st = $adm['online_status'] ?? formatAdminOnlineStatus($adm['last_active_at'] ?? null);

                                    $aInitials = 'AD';
                                    $parts = preg_split('/\s+/', trim((string)$adm['name'])) ?: [];
                                    if (count($parts) >= 2) {
                                        $aInitials = strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
                                    } elseif (!empty($parts[0])) {
                                        $aInitials = strtoupper(substr($parts[0], 0, 2));
                                    }
                                ?>
                                    <tr>
                                        <!-- Kolom 1: Administrator Avatar & Info -->
                                        <td>
                                            <div style="display:flex;align-items:center;gap:12px;">
                                                <div style="width:38px;height:38px;border-radius:10px;background:<?= $isAdmOwner ? '#fef3c7' : '#eff5e5' ?>;color:<?= $isAdmOwner ? '#b45309' : '#5b8121' ?>;display:grid;place-items:center;font-weight:800;font-size:13px;flex-shrink:0;">
                                                    <?= e($aInitials) ?>
                                                </div>
                                                <div>
                                                    <div style="display:flex;align-items:center;gap:6px;">
                                                        <strong><?= e($adm['name']) ?></strong>
                                                        <?php if ($isSelf): ?>
                                                            <span style="background:#eff6ff;color:#2563eb;font-size:9.5px;font-weight:800;padding:2px 6px;border-radius:4px;">Anda</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <small style="color:#64748b;font-size:11px;">ID #<?= $aId ?> · <?= e($adm['email']) ?></small>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Kolom 2: Kontak -->
                                        <td>
                                            <div><?= e($adm['email']) ?></div>
                                            <?php if (!empty($adm['phone'])): ?>
                                                <small style="color:#64748b;"><i class="fa-brands fa-whatsapp" style="color:#10b981;"></i> <?= e($adm['phone']) ?></small>
                                            <?php else: ?>
                                                <small style="color:#94a3b8;">-</small>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Kolom 3: Peran -->
                                        <td>
                                            <?php if ($isAdmOwner): ?>
                                                <span class="admin-role-badge owner">
                                                    <i class="fa-solid fa-crown"></i> Owner
                                                </span>
                                            <?php else: ?>
                                                <span class="admin-role-badge staff">
                                                    <i class="fa-solid fa-shield-halved"></i> Operasional
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Kolom 4: Status Real-Time -->
                                        <td>
                                            <div style="display:flex;flex-direction:column;gap:3px;">
                                                <span class="admin-online-badge <?= e($st['badge_class']) ?>">
                                                    <span class="status-dot-<?= e($st['status_type'] === 'online' ? 'online' : ($st['status_type'] === 'recent' ? 'recent' : 'offline')) ?>"></span>
                                                    <?= e($st['label']) ?>
                                                </span>
                                                <span style="font-size:10.5px;color:#64748b;"><?= e($st['time_text']) ?></span>
                                            </div>
                                        </td>

                                        <!-- Kolom 5: Hak Akses -->
                                        <td>
                                            <?php if ($isApproved): ?>
                                                <span class="status-pill completed"><i class="fa-solid fa-check"></i> Disetujui</span>
                                            <?php else: ?>
                                                <span class="status-pill cancelled"><i class="fa-solid fa-ban"></i> Ditangguhkan</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Kolom 6: Tanggal Didaftarkan -->
                                        <td>
                                            <span style="color:#64748b;font-size:11.5px;">
                                                <?= date('d M Y, H:i', strtotime((string)$adm['created_at'])) ?>
                                            </span>
                                        </td>

                                        <!-- Kolom 7: Aksi -->
                                        <td style="text-align:right;">
                                            <?php if ($adminIsOwner === 1 && !$isSelf): ?>
                                                <div style="display:inline-flex;gap:6px;align-items:center;">
                                                    <!-- Toggle Status Disetujui / Ditangguhkan -->
                                                    <form method="post" action="admin_dashboard.php?page=admins" style="display:inline;" onsubmit="return confirm('Apakah Anda yakin ingin mengubah status aktif administrator ini?')">
                                                        <input type="hidden" name="action" value="toggle_admin_status">
                                                        <input type="hidden" name="csrf" value="<?= e($_SESSION['admin_manage_csrf'] ?? '') ?>">
                                                        <input type="hidden" name="admin_id" value="<?= $aId ?>">
                                                        <input type="hidden" name="new_status" value="<?= $isApproved ? 0 : 1 ?>">
                                                        <button type="submit" class="btn btn-light" style="padding:5px 9px;font-size:11.5px;<?= $isApproved ? 'color:#e48e1e' : 'color:#059669' ?>" title="<?= $isApproved ? 'Tangguhkan Akun' : 'Aktifkan Akun' ?>">
                                                            <i class="fa-solid <?= $isApproved ? 'fa-user-slash' : 'fa-user-check' ?>"></i>
                                                        </button>
                                                    </form>

                                                    <!-- Hapus Administrator -->
                                                    <form method="post" action="admin_dashboard.php?page=admins" style="display:inline;" onsubmit="return confirm('PERINGATAN: Hapus permanen akun administrator <?= e($adm['name']) ?>?')">
                                                        <input type="hidden" name="action" value="delete_admin">
                                                        <input type="hidden" name="csrf" value="<?= e($_SESSION['admin_manage_csrf'] ?? '') ?>">
                                                        <input type="hidden" name="admin_id" value="<?= $aId ?>">
                                                        <button type="submit" class="btn btn-light" style="padding:5px 9px;font-size:11.5px;color:#ef4444" title="Hapus Administrator">
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php elseif ($isSelf): ?>
                                                <span style="font-size:11px;color:#94a3b8;font-style:italic;">Akun aktif Anda</span>
                                            <?php else: ?>
                                                <span style="font-size:11px;color:#94a3b8;font-style:italic;">Akses Owner saja</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </article>

        <?php elseif ($page === 'settings'): ?>
            <section class="welcome-row">
                <div>
                    <span class="eyebrow">BALLA’ FRESH · LEGAL &amp; POLICY SETTINGS</span>
                    <h1>Pengaturan &amp; Kebijakan Privasi</h1>
                    <p>Kelola naskah resmi Kebijakan Privasi dan Syarat &amp; Ketentuan Layanan. Dokumen ini otomatis tayang pada modal popup profil pengguna dan tautan footer website.</p>
                </div>
                <span class="welcome-tag"><span></span> Mode Pengaturan: Edit-Only</span>
            </section>

            <?php if ($settingsMessage !== ''): ?>
                <div class="order-notice <?= e($settingsMessageType) ?>" style="margin-bottom:20px"><?= e($settingsMessage) ?></div>
            <?php endif; ?>

            <!-- INFORMASI MODE EDIT-ONLY -->
            <div class="policy-alert">
                <i class="fa-solid fa-shield-halved" style="font-size:22px;flex-shrink:0"></i>
                <div>
                    <strong>Kebijakan Resmi Bersifat Edit-Only</strong>
                    <div style="font-size:12px;margin-top:2px;line-height:1.45">
                        Demi kepatuhan standar hukum perlindungan data pribadi dan ketentuan layanan agensi hidroponik, halaman ini hanya menyediakan fitur untuk <em>mengedit (memperbarui)</em> isi naskah yang sudah terdaftar. Penambahan konten baru secara sembarang dinonaktifkan.
                    </div>
                </div>
            </div>

            <article class="panel policy-edit-box">
                <div class="panel-head" style="border-bottom:1px solid var(--line);margin:-24px -24px 20px -24px;padding:20px 24px">
                    <div>
                        <h2>Edit Kebijakan Privasi &amp; Syarat Ketentuan</h2>
                        <p>Perbarui dokumen resmi agensi. Perubahan langsung berlaku secara realtime pada antarmuka mitra.</p>
                    </div>
                    <?php if (!empty($policyContent['policy_updated_at'])): ?>
                        <span style="font-size:11.5px;color:var(--muted);background:#f1f5f9;padding:6px 12px;border-radius:99px">
                            <i class="fa-regular fa-clock"></i> Terakhir diperbarui: <?= date('d M Y, H:i', strtotime((string)$policyContent['policy_updated_at'])) ?>
                        </span>
                    <?php endif; ?>
                </div>

                <form method="post" style="display:grid;gap:22px">
                    <input type="hidden" name="settings_action" value="save_policies">
                    <input type="hidden" name="settings_csrf" value="<?= e($_SESSION['order_status_csrf']) ?>">

                    <!-- 1. KEBIJAKAN PRIVASI -->
                    <div style="display:grid;gap:8px">
                        <label style="font-size:13px;font-weight:800;color:#1e293b;display:flex;align-items:center;justify-content:space-between">
                            <span><i class="fa-solid fa-user-shield" style="color:#5b8121;margin-right:6px"></i> 1. Naskah Kebijakan Privasi (Privacy Policy)</span>
                            <span style="font-size:11px;font-weight:600;color:var(--muted)">Tayang di Profil &amp; Footer</span>
                        </label>
                        <p style="font-size:12px;color:var(--muted);margin:0">Jelaskan bagaimana Balla’ Fresh mengumpulkan, melindungi, dan memanfaatkan data identitas usaha, kontak, serta data hidroponik mitra.</p>
                        <textarea class="order-filter" name="privacy_policy" rows="12" style="font-family:monospace;font-size:12.5px;line-height:1.6" placeholder="Ketik naskah kebijakan privasi lengkap..." required><?= e($policyContent['privacy_policy']) ?></textarea>
                    </div>

                    <!-- 2. SYARAT & KETENTUAN -->
                    <div style="display:grid;gap:8px">
                        <label style="font-size:13px;font-weight:800;color:#1e293b;display:flex;align-items:center;justify-content:space-between">
                            <span><i class="fa-solid fa-file-contract" style="color:#5b8121;margin-right:6px"></i> 2. Naskah Syarat &amp; Ketentuan Layanan (Terms &amp; Conditions)</span>
                            <span style="font-size:11px;font-weight:600;color:var(--muted)">Tayang di Profil &amp; Footer</span>
                        </label>
                        <p style="font-size:12px;color:var(--muted);margin:0">Jelaskan hak, kewajiban, ketentuan pembayaran, hak kekayaan intelektual karya desain, serta tata cara konsultasi agensi.</p>
                        <textarea class="order-filter" name="terms_conditions" rows="12" style="font-family:monospace;font-size:12.5px;line-height:1.6" placeholder="Ketik naskah syarat dan ketentuan layanan lengkap..." required><?= e($policyContent['terms_conditions']) ?></textarea>
                    </div>

                    <div style="display:flex;align-items:center;justify-content:space-between;padding-top:12px;border-top:1px solid var(--line)">
                        <span style="font-size:12px;color:var(--muted)"><i class="fa-solid fa-circle-info"></i> Perubahan tersimpan ke database `agency_site_content` pada ID 1.</span>
                        <button class="btn" type="submit" style="padding:10px 24px">
                            <i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan Kebijakan
                        </button>
                    </div>
                </form>
            </article>
        <?php endif; ?>
        </div>
    </main>
</div>
<!-- POPUP KONFIRMASI LOGOUT ADMIN -->
<div
    class="logout-modal"
    id="logoutModal"
    aria-hidden="true"
>
    <div
        class="logout-dialog"
        role="dialog"
        aria-modal="true"
        aria-labelledby="logoutTitle"
        aria-describedby="logoutDescription"
    >

        <div class="logout-icon">
            <i class="fa-solid fa-right-from-bracket"></i>
        </div>

        <h2 id="logoutTitle">Yakin ingin keluar?</h2>

        <p id="logoutDescription">
            Apakah kamu yakin ingin mengakhiri sesi administrator
            Balla’ Fresh? Kamu perlu login kembali untuk mengakses
            dashboard admin.
        </p>

        <div class="logout-actions">

            <button
                type="button"
                class="logout-cancel"
                id="cancelLogout"
            >
                Tetap di Dashboard
            </button>

            <form action="admin_logout.php" method="POST">
                <input
                    type="hidden"
                    name="csrf"
                    value="<?= e($_SESSION['admin_logout_csrf']) ?>"
                >

                <button
                    type="submit"
                    class="logout-confirm"
                >
                    Ya, Keluar
                </button>
            </form>

        </div>
    </div>
</div>

<!-- LIGHTBOX MODAL PREVIEW GAMBAR CHAT -->
<div id="chatLightbox" class="chat-lightbox" role="dialog" aria-modal="true" aria-label="Pratinjau Gambar Lampiran">
    <div class="chat-lightbox-inner">
        <div style="width:100%;display:flex;justify-content:space-between;align-items:center;color:#fff;padding:0 4px">
            <span id="chatLightboxTitle" style="font-size:13px;font-weight:600;max-width:70vw;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"></span>
            <div style="display:flex;gap:10px;align-items:center">
                <a id="chatLightboxDl" href="#" download="" class="btn" style="background:rgba(255,255,255,0.2);color:#fff;padding:6px 14px;font-size:12px;border:none;border-radius:6px;text-decoration:none">
                    <i class="fa-solid fa-download"></i> Unduh
                </a>
                <button type="button" onclick="closeChatLightbox()" style="background:rgba(255,255,255,0.2);color:#fff;border:none;width:32px;height:32px;border-radius:50%;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>
        <img id="chatLightboxImg" src="" alt="Pratinjau Gambar" class="chat-lightbox-img">
    </div>
</div>

<!-- MODAL INSERT LINK UNTUK RICH TEXT EDITOR -->
<div id="rteLinkModal" class="rte-modal-overlay" role="dialog" aria-modal="true" aria-label="Sisipkan Tautan">
    <div class="rte-modal-card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
            <h4 style="margin:0;font-size:15px;font-weight:800;color:#0f172a"><i class="fa-solid fa-link" style="color:var(--green);margin-right:6px"></i> Sisipkan Tautan Web</h4>
            <button type="button" id="rteLinkCloseBtn" style="background:none;border:none;color:#94a3b8;font-size:16px;cursor:pointer"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div style="display:grid;gap:12px">
            <div>
                <label class="form-field-label">Teks yang Ditampilkan</label>
                <input class="order-filter" type="text" id="rteLinkTextInput" placeholder="Contoh: Baca panduan lengkap di sini">
            </div>
            <div>
                <label class="form-field-label">Alamat URL Tautan <span class="required-star">*</span></label>
                <input class="order-filter" type="url" id="rteLinkUrlInput" placeholder="https://contoh-website.com">
            </div>
            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:6px">
                <button type="button" id="rteLinkCancelBtn" class="btn btn-light" style="padding:8px 16px;font-size:12px">Batal</button>
                <button type="button" id="rteLinkApplyBtn" class="btn btn-primary" style="padding:8px 18px;font-size:12px">Sisipkan Tautan</button>
            </div>
        </div>
    </div>
</div>

<script>
const orderStatusFilter = document.getElementById('orderStatusFilter');
if (orderStatusFilter) {
    orderStatusFilter.addEventListener('change', function () {
        const chosen = this.value;
        document.querySelectorAll('#ordersTableBody tr[data-order-status]').forEach(row => {
            row.style.display = chosen === 'all' || row.dataset.orderStatus === chosen ? '' : 'none';
        });
    });
}

const todayLabel = document.getElementById('todayLabel');
if (todayLabel) {
    todayLabel.textContent = new Intl.DateTimeFormat('id-ID', {
        day: 'numeric', month: 'long', year: 'numeric'
    }).format(new Date());
}

const adminChatScroll = document.getElementById('adminChatScroll');
if (adminChatScroll) {
    adminChatScroll.scrollTop = adminChatScroll.scrollHeight;
}

// ===== CHAT ATTACHMENTS & LIGHTBOX ADMIN =====
const chatLightbox = document.getElementById('chatLightbox');
const chatLightboxImg = document.getElementById('chatLightboxImg');
const chatLightboxTitle = document.getElementById('chatLightboxTitle');
const chatLightboxDl = document.getElementById('chatLightboxDl');

function openChatLightbox(url, title) {
    if (!chatLightbox || !chatLightboxImg) return;
    chatLightboxImg.src = url;
    if (chatLightboxTitle) chatLightboxTitle.textContent = title || 'Pratinjau Gambar';
    if (chatLightboxDl) {
        chatLightboxDl.href = url;
        chatLightboxDl.setAttribute('download', title || 'gambar');
    }
    chatLightbox.classList.add('show');
}

function closeChatLightbox() {
    if (!chatLightbox) return;
    chatLightbox.classList.remove('show');
    if (chatLightboxImg) chatLightboxImg.src = '';
}

if (chatLightbox) {
    chatLightbox.addEventListener('click', function(e) {
        if (e.target === chatLightbox) closeChatLightbox();
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && chatLightbox.classList.contains('show')) {
            closeChatLightbox();
        }
    });
}

// Handler Lampiran File Chat Admin
const adminChatFilesInput = document.getElementById('adminChatFilesInput');
const adminFilePreviewWrap = document.getElementById('adminFilePreviewWrap');
const adminChatForm = document.getElementById('adminChatForm');
const adminChatMessage = document.getElementById('adminChatMessage');

let adminChatDT = new DataTransfer();

function renderAdminFileChips() {
    if (!adminFilePreviewWrap) return;
    adminFilePreviewWrap.innerHTML = '';
    const files = adminChatDT.files;
    if (files.length === 0) {
        adminFilePreviewWrap.style.display = 'none';
        return;
    }
    adminFilePreviewWrap.style.display = 'flex';

    Array.from(files).forEach((file, index) => {
        const chip = document.createElement('div');
        chip.className = 'admin-file-chip';

        let iconClass = 'fa-file-lines';
        if (file.type.startsWith('image/')) iconClass = 'fa-file-image';
        else if (file.type.startsWith('video/')) iconClass = 'fa-file-video';
        else if (file.type.includes('pdf')) iconClass = 'fa-file-pdf';
        else if (file.type.includes('word') || file.name.match(/\.(docx?)$/i)) iconClass = 'fa-file-word';
        else if (file.type.includes('sheet') || file.type.includes('excel') || file.name.match(/\.(xlsx?|csv)$/i)) iconClass = 'fa-file-excel';
        else if (file.type.includes('presentation') || file.name.match(/\.(pptx?)$/i)) iconClass = 'fa-file-powerpoint';

        const sizeFormatted = file.size >= 1048576 ? (file.size / 1048576).toFixed(1) + ' MB' : (file.size / 1024).toFixed(0) + ' KB';

        chip.innerHTML = `
            <i class="fa-solid ${iconClass}" style="color:#5b8121"></i>
            <span title="${file.name}">${file.name} (${sizeFormatted})</span>
            <button type="button" data-index="${index}" title="Hapus file">&times;</button>
        `;

        chip.querySelector('button').addEventListener('click', function(e) {
            e.stopPropagation();
            removeAdminChatFile(index);
        });

        adminFilePreviewWrap.appendChild(chip);
    });
}

function removeAdminChatFile(index) {
    const newDT = new DataTransfer();
    Array.from(adminChatDT.files).forEach((file, i) => {
        if (i !== index) newDT.items.add(file);
    });
    adminChatDT = newDT;
    if (adminChatFilesInput) adminChatFilesInput.files = adminChatDT.files;
    renderAdminFileChips();
}

if (adminChatFilesInput) {
    adminChatFilesInput.addEventListener('change', function() {
        const maxFiles = 5;
        const maxBytes = 20 * 1024 * 1024; // 20 MB

        Array.from(this.files).forEach(file => {
            if (adminChatDT.files.length >= maxFiles) {
                alert('Maksimal lampiran adalah 5 file dalam sekali kirim.');
                return;
            }
            if (file.size > maxBytes) {
                alert(`File "${file.name}" melebihi ukuran maksimum 20 MB.`);
                return;
            }
            adminChatDT.items.add(file);
        });

        adminChatFilesInput.files = adminChatDT.files;
        renderAdminFileChips();
    });
}

if (adminChatForm) {
    adminChatForm.addEventListener('submit', function(e) {
        const textVal = adminChatMessage ? adminChatMessage.value.trim() : '';
        const filesCount = adminChatDT ? adminChatDT.files.length : 0;
        if (textVal === '' && filesCount === 0) {
            e.preventDefault();
            alert('Silakan tulis pesan atau pilih minimal satu file lampiran.');
            if (adminChatMessage) adminChatMessage.focus();
        }
    });
}

const sidebar = document.getElementById('sidebar');
const mobileMenu = document.getElementById('mobileMenu');
const mobileOverlay = document.getElementById('mobileOverlay');

function closeMobileMenu() {
    sidebar?.classList.remove('open');
    mobileOverlay?.classList.remove('show');
}
mobileMenu?.addEventListener('click', () => {
    sidebar?.classList.toggle('open');
    mobileOverlay?.classList.toggle('show');
});
mobileOverlay?.addEventListener('click', closeMobileMenu);
document.querySelectorAll('.side-link').forEach(link => {
    link.addEventListener('click', () => {
        document.querySelectorAll('.side-link').forEach(item => item.classList.remove('active'));
        link.classList.add('active');
        closeMobileMenu();
    });
});
// ===== POPUP LOGOUT ADMIN =====

const logoutButton = document.getElementById('logoutButton');
const logoutModal = document.getElementById('logoutModal');
const cancelLogout = document.getElementById('cancelLogout');

function openLogoutModal() {
    logoutModal.classList.add('show');
    logoutModal.setAttribute('aria-hidden', 'false');
    cancelLogout.focus();
}

function closeLogoutModal() {
    logoutModal.classList.remove('show');
    logoutModal.setAttribute('aria-hidden', 'true');
    logoutButton.focus();
}

// Klik tombol logout.
logoutButton.addEventListener('click', openLogoutModal);

// Klik tetap di dashboard.
cancelLogout.addEventListener('click', closeLogoutModal);

// Klik area luar popup.
logoutModal.addEventListener('click', function(event) {
    if (event.target === logoutModal) {
        closeLogoutModal();
    }
});

// Tekan Escape untuk menutup popup.
document.addEventListener('keydown', function(event) {
    if (
        event.key === 'Escape' &&
        logoutModal.classList.contains('show')
    ) {
        closeLogoutModal();
    }
});

// ===== FILTER & PENCARIAN LIVE ARTIKEL ADMIN =====
const adminArticleSearch = document.getElementById('adminArticleSearch');
const artCatButtons = document.querySelectorAll('.filter-art-btn');
const articleRows = document.querySelectorAll('.admin-article-row');
let currentArtCat = 'all';

function filterAdminArticles() {
    const query = (adminArticleSearch ? adminArticleSearch.value : '').toLowerCase().trim();
    articleRows.forEach(row => {
        const cat = row.getAttribute('data-category') || '';
        const title = (row.getAttribute('data-title') || '').toLowerCase();
        const author = (row.getAttribute('data-author') || '').toLowerCase();

        const matchCat = (currentArtCat === 'all' || cat === currentArtCat);
        const matchQuery = (query === '' || title.includes(query) || author.includes(query));

        row.style.display = (matchCat && matchQuery) ? '' : 'none';
    });
}

if (adminArticleSearch) {
    adminArticleSearch.addEventListener('input', filterAdminArticles);
}

artCatButtons.forEach(btn => {
    btn.addEventListener('click', function() {
        artCatButtons.forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        currentArtCat = this.getAttribute('data-category') || 'all';
        filterAdminArticles();
    });
});

// ===== RICH TEXT EDITOR (WYSIWYG) ARTIKEL =====
const articleEditorCanvas = document.getElementById('articleEditorCanvas');
const articleContentInput = document.getElementById('articleContentInput');
const rteHeadingSelect = document.getElementById('rteHeadingSelect');
const rteFontSizeSelect = document.getElementById('rteFontSizeSelect');
const rteWordCount = document.getElementById('rteWordCount');
const rteCharCount = document.getElementById('rteCharCount');
const articleMainForm = document.getElementById('articleMainForm');

// Modal Link
const rteLinkModal = document.getElementById('rteLinkModal');
const rteBtnInsertLink = document.getElementById('rteBtnInsertLink');
const rteLinkCloseBtn = document.getElementById('rteLinkCloseBtn');
const rteLinkCancelBtn = document.getElementById('rteLinkCancelBtn');
const rteLinkApplyBtn = document.getElementById('rteLinkApplyBtn');
const rteLinkTextInput = document.getElementById('rteLinkTextInput');
const rteLinkUrlInput = document.getElementById('rteLinkUrlInput');
let savedSelectionRange = null;

function syncEditorContent() {
    if (!articleEditorCanvas || !articleContentInput) return;
    const html = articleEditorCanvas.innerHTML;
    const textOnly = (articleEditorCanvas.textContent || '').trim();
    if (textOnly === '' && !html.includes('<img') && !html.includes('<table')) {
        articleContentInput.value = '';
    } else {
        articleContentInput.value = html;
    }
    updateEditorCounts();
}

function updateEditorCounts() {
    if (!articleEditorCanvas) return;
    const text = (articleEditorCanvas.textContent || '').trim();
    const words = text ? text.split(/\s+/).filter(w => w.length > 0).length : 0;
    const chars = text.length;
    if (rteWordCount) rteWordCount.innerHTML = `<i class="fa-solid fa-pen-nib" style="margin-right:4px"></i> ${words} kata`;
    if (rteCharCount) rteCharCount.textContent = `${chars} karakter`;
}

function updateRteToolbarState() {
    if (!articleEditorCanvas) return;
    ['bold', 'italic', 'underline', 'strikeThrough', 'justifyLeft', 'justifyCenter', 'justifyRight', 'insertUnorderedList', 'insertOrderedList'].forEach(cmd => {
        const btn = document.querySelector(`.rte-btn[data-command="${cmd}"]`);
        if (btn) {
            try {
                if (document.queryCommandState(cmd)) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            } catch (err) {}
        }
    });
}

// Inisialisasi konten awal ke dalam canvas
if (articleEditorCanvas && articleContentInput) {
    if (articleContentInput.value.trim() !== '') {
        articleEditorCanvas.innerHTML = articleContentInput.value;
    }
    updateEditorCounts();

    articleEditorCanvas.addEventListener('input', syncEditorContent);
    articleEditorCanvas.addEventListener('blur', syncEditorContent);
    articleEditorCanvas.addEventListener('keyup', () => {
        syncEditorContent();
        updateRteToolbarState();
    });
    articleEditorCanvas.addEventListener('mouseup', updateRteToolbarState);
}

// Toolbar button click commands
document.querySelectorAll('.rte-btn[data-command]').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const cmd = this.getAttribute('data-command');
        if (!cmd) return;
        articleEditorCanvas?.focus();
        document.execCommand(cmd, false, null);
        syncEditorContent();
        updateRteToolbarState();
    });
});

// Headings change
if (rteHeadingSelect) {
    rteHeadingSelect.addEventListener('change', function() {
        const tag = this.value;
        if (!tag) return;
        articleEditorCanvas?.focus();
        document.execCommand('formatBlock', false, tag);
        syncEditorContent();
        this.value = 'p';
    });
}

// Font size change
if (rteFontSizeSelect) {
    rteFontSizeSelect.addEventListener('change', function() {
        const size = this.value;
        if (!size) return;
        articleEditorCanvas?.focus();
        const sel = window.getSelection();
        if (!sel || !sel.rangeCount || sel.isCollapsed) {
            alert('Silakan sorot (blok) teks yang ingin diubah ukuran fontnya terlebih dahulu.');
            this.value = '';
            return;
        }
        const range = sel.getRangeAt(0);
        const span = document.createElement('span');
        span.style.fontSize = size;
        span.appendChild(range.extractContents());
        range.insertNode(span);

        sel.removeAllRanges();
        const newRange = document.createRange();
        newRange.selectNodeContents(span);
        sel.addRange(newRange);

        syncEditorContent();
        this.value = '';
    });
}

// Modal Link Handling
function openLinkModal() {
    if (!rteLinkModal) return;
    const sel = window.getSelection();
    if (sel && sel.rangeCount > 0) {
        savedSelectionRange = sel.getRangeAt(0).cloneRange();
        if (rteLinkTextInput) rteLinkTextInput.value = sel.toString().trim();
    } else {
        savedSelectionRange = null;
        if (rteLinkTextInput) rteLinkTextInput.value = '';
    }
    if (rteLinkUrlInput) rteLinkUrlInput.value = 'https://';
    rteLinkModal.classList.add('show');
    rteLinkUrlInput?.focus();
}

function closeLinkModal() {
    if (!rteLinkModal) return;
    rteLinkModal.classList.remove('show');
    savedSelectionRange = null;
}

if (rteBtnInsertLink) {
    rteBtnInsertLink.addEventListener('click', function(e) {
        e.preventDefault();
        openLinkModal();
    });
}
if (rteLinkCloseBtn) rteLinkCloseBtn.addEventListener('click', closeLinkModal);
if (rteLinkCancelBtn) rteLinkCancelBtn.addEventListener('click', closeLinkModal);
if (rteLinkModal) {
    rteLinkModal.addEventListener('click', function(e) {
        if (e.target === rteLinkModal) closeLinkModal();
    });
}

if (rteLinkApplyBtn) {
    rteLinkApplyBtn.addEventListener('click', function() {
        let url = (rteLinkUrlInput ? rteLinkUrlInput.value.trim() : '');
        const text = (rteLinkTextInput ? rteLinkTextInput.value.trim() : '');
        if (!url || url === 'https://') {
            alert('Silakan masukkan alamat URL tautan yang valid.');
            return;
        }
        if (!/^https?:\/\//i.test(url) && !url.startsWith('/') && !url.startsWith('#') && !url.startsWith('mailto:')) {
            url = 'https://' + url;
        }

        articleEditorCanvas?.focus();
        const sel = window.getSelection();
        if (savedSelectionRange && sel) {
            sel.removeAllRanges();
            sel.addRange(savedSelectionRange);
        }

        if (savedSelectionRange && !savedSelectionRange.collapsed) {
            document.execCommand('createLink', false, url);
        } else {
            const anchor = document.createElement('a');
            anchor.href = url;
            anchor.target = '_blank';
            anchor.rel = 'noopener noreferrer';
            anchor.textContent = text || url;
            if (savedSelectionRange) {
                savedSelectionRange.insertNode(anchor);
            } else if (articleEditorCanvas) {
                articleEditorCanvas.appendChild(anchor);
            }
        }

        closeLinkModal();
        syncEditorContent();
    });
}

// Pastikan sebelum submit form, sync terakhir dieksekusi
if (articleMainForm) {
    articleMainForm.addEventListener('submit', function(e) {
        syncEditorContent();
        const textOnly = (articleEditorCanvas ? articleEditorCanvas.textContent : '').trim();
        const html = articleContentInput ? articleContentInput.value.trim() : '';
        if (html === '' || (textOnly === '' && !html.includes('<img'))) {
            e.preventDefault();
            alert('Isi konten artikel wajib diisi.');
            articleEditorCanvas?.focus();
        }
    });
}

// Auto generate slug dari judul
const articleTitleInput = document.getElementById('articleTitleInput');
const articleSlugInput = document.getElementById('articleSlugInput');
if (articleTitleInput && articleSlugInput) {
    articleTitleInput.addEventListener('input', function() {
        if (!articleSlugInput.dataset.manualEdited) {
            articleSlugInput.value = this.value
                .toLowerCase()
                .trim()
                .replace(/[^\w\s-]/g, '')
                .replace(/[\s_-]+/g, '-')
                .replace(/^-+|-+$/g, '');
        }
    });
    articleSlugInput.addEventListener('input', function() {
        this.dataset.manualEdited = 'true';
    });
}

// Live preview gambar sampul dan avatar penulis
const articleImageFileInput = document.getElementById('articleImageFileInput');
const articleImageUrlInput = document.getElementById('articleImageUrlInput');
const articleCoverPreview = document.getElementById('articleCoverPreview');

if (articleImageFileInput && articleCoverPreview) {
    articleImageFileInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                articleCoverPreview.src = e.target.result;
            };
            reader.readAsDataURL(this.files[0]);
        }
    });
}
if (articleImageUrlInput && articleCoverPreview) {
    articleImageUrlInput.addEventListener('input', function() {
        const val = this.value.trim();
        if (val.startsWith('http')) {
            articleCoverPreview.src = val;
        }
    });
}

const authorAvatarInput = document.getElementById('authorAvatarInput');
const authorAvatarPreview = document.getElementById('authorAvatarPreview');
if (authorAvatarInput && authorAvatarPreview) {
    authorAvatarInput.addEventListener('input', function() {
        const val = this.value.trim();
        if (val.startsWith('http')) {
            authorAvatarPreview.src = val;
        }
    });
}
</script>
</body>
</html>
