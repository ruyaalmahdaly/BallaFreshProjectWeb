<?php
declare(strict_types=1);

/*
 * Balla' Fresh - Halaman Profil Mitra (Tersambung ke Database)
 */
require_once __DIR__ . '/session_bootstrap.php';
require_once __DIR__ . '/db.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$profileMessage = '';
$profileMessageType = 'success';

// 1. Ambil data pengguna saat ini dari database
$userStmt = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$userStmt->bind_param('i', $userId);
$userStmt->execute();
$userResult = $userStmt->get_result();
$user = $userResult->fetch_assoc();
$userStmt->close();

if (!$user) {
    header('Location: logout.php');
    exit;
}

// 2. Handle POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['profile_action'])) {
    $action = (string) $_POST['profile_action'];

    // A. SIMPAN DATA PENGGUNA
    if ($action === 'save_personal') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $birthDate = !empty($_POST['birth_date']) ? trim((string)$_POST['birth_date']) : null;
        $address = trim((string) ($_POST['address'] ?? ''));

        if ($name === '') {
            $profileMessage = 'Nama lengkap wajib diisi.';
            $profileMessageType = 'error';
        } else {
            $uploadedAvatar = null;
            if (!empty($_FILES['profile_photo']['name']) && is_uploaded_file($_FILES['profile_photo']['tmp_name'])) {
                $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                $mime = @mime_content_type($_FILES['profile_photo']['tmp_name']);
                if (!isset($allowed[$mime]) || (int)$_FILES['profile_photo']['size'] > 2 * 1024 * 1024) {
                    $profileMessage = 'Foto profil harus berupa JPG, PNG, atau WEBP dan maksimal 2 MB.';
                    $profileMessageType = 'error';
                } else {
                    $avatarDir = __DIR__ . '/uploads/avatars';
                    if (!is_dir($avatarDir)) @mkdir($avatarDir, 0755, true);
                    $filename = 'avatar-' . $userId . '-' . bin2hex(random_bytes(5)) . '.' . $allowed[$mime];
                    if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $avatarDir . '/' . $filename)) {
                        $uploadedAvatar = 'uploads/avatars/' . $filename;
                    }
                }
            }

            if ($profileMessageType === 'success') {
                if ($uploadedAvatar !== null) {
                    $updateStmt = $conn->prepare("UPDATE users SET name = ?, phone = ?, birth_date = ?, address = ?, profile_image = ? WHERE id = ?");
                    $updateStmt->bind_param('sssssi', $name, $phone, $birthDate, $address, $uploadedAvatar, $userId);
                } else {
                    $updateStmt = $conn->prepare("UPDATE users SET name = ?, phone = ?, birth_date = ?, address = ? WHERE id = ?");
                    $updateStmt->bind_param('ssssi', $name, $phone, $birthDate, $address, $userId);
                }

                if ($updateStmt && $updateStmt->execute()) {
                    $profileMessage = 'Data pengguna dan foto profil berhasil disimpan ke database!';
                    $_SESSION['user_name'] = $name;
                    $user['name'] = $name;
                    $user['phone'] = $phone;
                    $user['birth_date'] = $birthDate;
                    $user['address'] = $address;
                    if ($uploadedAvatar !== null) {
                        $user['profile_image'] = $uploadedAvatar;
                    }
                } else {
                    $profileMessage = 'Gagal menyimpan perubahan data pengguna.';
                    $profileMessageType = 'error';
                }
                if ($updateStmt) $updateStmt->close();
            }
        }

    // B. SIMPAN PROFIL USAHA
    } elseif ($action === 'save_business') {
        $businessName = trim((string) ($_POST['business_name'] ?? ''));
        $businessType = trim((string) ($_POST['business_type'] ?? ''));
        $businessRole = trim((string) ($_POST['business_role'] ?? ''));
        $socialMedia = trim((string) ($_POST['social_media'] ?? ''));
        $websiteUrl = trim((string) ($_POST['website_url'] ?? ''));
        $businessAddress = trim((string) ($_POST['business_address'] ?? ''));
        $businessDescription = trim((string) ($_POST['business_description'] ?? ''));
        $prefs = isset($_POST['service_preferences']) && is_array($_POST['service_preferences'])
            ? implode('|', array_map('trim', $_POST['service_preferences']))
            : '';

        $bStmt = $conn->prepare("UPDATE users SET business_name = ?, business_type = ?, business_role = ?, social_media = ?, website_url = ?, business_address = ?, business_description = ?, service_preferences = ? WHERE id = ?");
        if ($bStmt) {
            $bStmt->bind_param('ssssssssi', $businessName, $businessType, $businessRole, $socialMedia, $websiteUrl, $businessAddress, $businessDescription, $prefs, $userId);
            if ($bStmt->execute()) {
                $profileMessage = 'Profil usaha Anda berhasil disimpan dan tersinkronisasi langsung ke Dashboard Admin!';
                $user['business_name'] = $businessName;
                $user['business_type'] = $businessType;
                $user['business_role'] = $businessRole;
                $user['social_media'] = $socialMedia;
                $user['website_url'] = $websiteUrl;
                $user['business_address'] = $businessAddress;
                $user['business_description'] = $businessDescription;
                $user['service_preferences'] = $prefs;
            } else {
                $profileMessage = 'Gagal menyimpan profil usaha.';
                $profileMessageType = 'error';
            }
            $bStmt->close();
        }

    // C. AJUKAN PERMINTAAN KONSULTASI BARU
    } elseif ($action === 'request_consultation') {
        $category = trim((string) ($_POST['category'] ?? 'Starter Branding'));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        $clientName = (string) ($user['name'] ?: 'Mitra Balla’ Fresh');
        $bName = (string) ($user['business_name'] ?: 'Usaha Hidroponik Mitra');
        $phone = (string) ($user['phone'] ?: '');
        $email = (string) ($user['email'] ?: '');

        if ($notes === '') {
            $profileMessage = 'Silakan isi kebutuhan atau kendala usaha yang ingin dikonsultasikan.';
            $profileMessageType = 'error';
        } else {
            $insC = $conn->prepare("INSERT INTO consultations (user_id, client_name, business_name, phone, email, category, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
            if ($insC) {
                $insC->bind_param('issssss', $userId, $clientName, $bName, $phone, $email, $category, $notes);
                if ($insC->execute()) {
                    $newCid = $insC->insert_id;
                    // Kirim pesan pertama ke riwayat chat
                    $initMsg = "Permintaan konsultasi diajukan:\nTopik: {$category}\nKebutuhan: {$notes}";
                    $mStmt = $conn->prepare("INSERT INTO consultation_messages (consultation_id, sender_type, sender_id, sender_name, message) VALUES (?, 'user', ?, ?, ?)");
                    if ($mStmt) {
                        $mStmt->bind_param('iiss', $newCid, $userId, $clientName, $initMsg);
                        $mStmt->execute();
                        $mStmt->close();
                    }
                    $profileMessage = 'Permintaan konsultasi berhasil dikirim! Menunggu persetujuan (ACC) dari tim admin untuk membuka room chat.';
                } else {
                    $profileMessage = 'Gagal mengajukan konsultasi.';
                    $profileMessageType = 'error';
                }
                $insC->close();
            }
        }

    // D. KIRIM PESAN CHAT DI ROOM KONSULTASI YANG SUDAH DI-ACC
    } elseif ($action === 'send_chat_message') {
        $cId = (int) ($_POST['consultation_id'] ?? 0);
        $messageText = trim((string) ($_POST['chat_message'] ?? ''));

        // Validasi room terlebih dahulu. Lampiran hanya boleh dikirim ke room milik user yang sudah ACC.
        $checkC = null;
        $cRow = null;
        if ($cId > 0) {
            $checkC = $conn->prepare("SELECT id, status FROM consultations WHERE id = ? AND user_id = ? LIMIT 1");
            if ($checkC) {
                $checkC->bind_param('ii', $cId, $userId);
                $checkC->execute();
                $cRow = $checkC->get_result()->fetch_assoc();
                $checkC->close();
            }
        }

        if (!$cRow || $cRow['status'] !== 'approved') {
            $profileMessage = 'Room chat belum disetujui (ACC) oleh admin atau sesi telah ditutup.';
            $profileMessageType = 'error';
        } else {
            // Jenis file yang diizinkan. MIME diperiksa dari isi file, bukan hanya nama/ekstensi.
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
                    $uploadError = 'Maksimal 5 file dalam satu pesan.';
                } else {
                    $uploadDir = __DIR__ . '/uploads/chat_attachments';
                    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                        $uploadError = 'Folder penyimpanan lampiran tidak dapat dibuat.';
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
                                $uploadError = 'File ' . $originalName . ' tidak sesuai format yang diizinkan atau melebihi 20 MB.';
                                break;
                            }

                            $storedName = 'chat-' . $cId . '-' . bin2hex(random_bytes(12)) . '.' . $extension;
                            if (!move_uploaded_file($tmpName, $uploadDir . '/' . $storedName)) {
                                $uploadError = 'File ' . $originalName . ' gagal disimpan.';
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

            // Bersihkan file yang sudah terlanjur dipindahkan jika ada file lain yang gagal.
            if ($uploadError !== '') {
                foreach ($uploadedChatFiles as $uploadedFile) {
                    $storedPath = __DIR__ . '/' . $uploadedFile['path'];
                    if (is_file($storedPath)) {
                        @unlink($storedPath);
                    }
                }
                $profileMessage = $uploadError;
                $profileMessageType = 'error';
            } elseif ($messageText === '' && empty($uploadedChatFiles)) {
                $profileMessage = 'Tulis pesan atau pilih minimal satu file untuk dikirim.';
                $profileMessageType = 'error';
            } else {
                // URL absolut memudahkan admin membuka lampiran dari dashboard admin.
                $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
                $scheme = $isHttps ? 'https' : 'http';
                $host = preg_replace('/[^a-zA-Z0-9.:-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
                $basePath = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/profil.php')));
                $basePath = ($basePath === '/' || $basePath === '.') ? '' : rtrim($basePath, '/');
                $messageToSave = $messageText;

                foreach ($uploadedChatFiles as $uploadedFile) {
                    $publicUrl = $scheme . '://' . $host . $basePath . '/' . $uploadedFile['path'];
                    $messageToSave .= ($messageToSave !== '' ? "\n\n" : '') .
                        '[Lampiran] ' . $uploadedFile['original_name'] .
                        "\nURL: " . $publicUrl;
                }

                $senderName = (string) ($user['name'] ?: 'Mitra');
                $insMsg = $conn->prepare("INSERT INTO consultation_messages (consultation_id, sender_type, sender_id, sender_name, message) VALUES (?, 'user', ?, ?, ?)");
                if ($insMsg) {
                    $insMsg->bind_param('iiss', $cId, $userId, $senderName, $messageToSave);
                    if ($insMsg->execute()) {
                        $profileMessage = 'Pesan dan lampiran berhasil dikirim ke tim admin!';
                    } else {
                        foreach ($uploadedChatFiles as $uploadedFile) {
                            $storedPath = __DIR__ . '/' . $uploadedFile['path'];
                            if (is_file($storedPath)) {
                                @unlink($storedPath);
                            }
                        }
                        $profileMessage = 'Pesan gagal disimpan. Silakan coba kembali.';
                        $profileMessageType = 'error';
                    }
                    $insMsg->close();
                } else {
                    foreach ($uploadedChatFiles as $uploadedFile) {
                        $storedPath = __DIR__ . '/' . $uploadedFile['path'];
                        if (is_file($storedPath)) {
                            @unlink($storedPath);
                        }
                    }
                    $profileMessage = 'Koneksi penyimpanan pesan gagal disiapkan.';
                    $profileMessageType = 'error';
                }
            }
        }

    // E. UBAH KATA SANDI
    } elseif ($action === 'change_password') {
        $currentPass = (string) ($_POST['current_password'] ?? '');
        $newPass = (string) ($_POST['new_password'] ?? '');
        $confirmPass = (string) ($_POST['confirm_password'] ?? '');

        if (strlen($newPass) < 6) {
            $profileMessage = 'Kata sandi baru minimal harus 6 karakter.';
            $profileMessageType = 'error';
        } elseif ($newPass !== $confirmPass) {
            $profileMessage = 'Konfirmasi kata sandi baru tidak sesuai.';
            $profileMessageType = 'error';
        } else {
            $pQuery = $conn->prepare("SELECT password FROM users WHERE id = ? LIMIT 1");
            $pQuery->bind_param('i', $userId);
            $pQuery->execute();
            $pRow = $pQuery->get_result()->fetch_assoc();
            $pQuery->close();

            $isMatch = false;
            if ($pRow && !empty($pRow['password'])) {
                if (password_verify($currentPass, $pRow['password']) || $currentPass === $pRow['password']) {
                    $isMatch = true;
                }
            } else {
                // Jika password lama di DB kosong, izinkan set password baru
                $isMatch = true;
            }

            if (!$isMatch) {
                $profileMessage = 'Kata sandi saat ini tidak cocok.';
                $profileMessageType = 'error';
            } else {
                $hashed = password_hash($newPass, PASSWORD_DEFAULT);
                $updPass = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $updPass->bind_param('si', $hashed, $userId);
                if ($updPass->execute()) {
                    $profileMessage = 'Kata sandi akun Anda berhasil diperbarui!';
                } else {
                    $profileMessage = 'Gagal memperbarui kata sandi.';
                    $profileMessageType = 'error';
                }
                $updPass->close();
            }
        }

    // F. SIMPAN PREFERENSI NOTIFIKASI
    } elseif ($action === 'save_notifications') {
        $profileMessage = 'Preferensi notifikasi akun Anda berhasil disimpan!';
    }
}

// 3. Ambil data pesanan pengguna
$orders = [];
$oStmt = $conn->prepare("SELECT * FROM orders WHERE customer_id = ? OR user_id = ? ORDER BY id DESC");
$oStmt->bind_param('ii', $userId, $userId);
$oStmt->execute();
$oRes = $oStmt->get_result();
while ($o = $oRes->fetch_assoc()) {
    $orders[] = $o;
}
$oStmt->close();

// 4. Ambil daftar konsultasi pengguna
$consultations = [];
$cListStmt = $conn->prepare("SELECT * FROM consultations WHERE user_id = ? ORDER BY id DESC");
$cListStmt->bind_param('i', $userId);
$cListStmt->execute();
$cListRes = $cListStmt->get_result();
while ($c = $cListRes->fetch_assoc()) {
    $consultations[] = $c;
}
$cListStmt->close();

// Temukan konsultasi aktif (approved) untuk room chat
$activeConsultation = null;
$selectedCid = (int) ($_GET['chat_id'] ?? 0);

if ($selectedCid > 0) {
    foreach ($consultations as $c) {
        if ((int)$c['id'] === $selectedCid) {
            $activeConsultation = $c;
            break;
        }
    }
}
if (!$activeConsultation) {
    foreach ($consultations as $c) {
        if ($c['status'] === 'approved') {
            $activeConsultation = $c;
            break;
        }
    }
}
if (!$activeConsultation && !empty($consultations)) {
    $activeConsultation = $consultations[0];
}

$chatMessages = [];
if ($activeConsultation) {
    $mStmt = $conn->prepare("SELECT * FROM consultation_messages WHERE consultation_id = ? ORDER BY created_at ASC");
    $mStmt->bind_param('i', $activeConsultation['id']);
    $mStmt->execute();
    $mRes = $mStmt->get_result();
    while ($m = $mRes->fetch_assoc()) {
        $chatMessages[] = $m;
    }
    $mStmt->close();
}

// 5. Ambil Kebijakan Privasi dan Ketentuan Layanan dari agency_site_content
$policyQuery = $conn->query("SELECT privacy_policy, terms_conditions, updated_at FROM agency_site_content WHERE id = 1 LIMIT 1");
$policyRow = $policyQuery ? $policyQuery->fetch_assoc() : [];
$privacyPolicy = (string) ($policyRow['privacy_policy'] ?? 'Kebijakan privasi sedang dipersiapkan oleh administrator.');
$termsConditions = (string) ($policyRow['terms_conditions'] ?? 'Syarat dan ketentuan sedang dipersiapkan oleh administrator.');

// 6. Hitung persentase kelengkapan profil (10 poin)
$completionPoints = 0;
if (!empty($user['name'])) $completionPoints++;
if (!empty($user['email'])) $completionPoints++;
if (!empty($user['phone'])) $completionPoints++;
if (!empty($user['birth_date'])) $completionPoints++;
if (!empty($user['address'])) $completionPoints++;
if (!empty($user['profile_image'])) $completionPoints++;
if (!empty($user['business_name'])) $completionPoints++;
if (!empty($user['business_type'])) $completionPoints++;
if (!empty($user['business_role'])) $completionPoints++;
if (!empty($user['social_media']) || !empty($user['website_url'])) $completionPoints++;
$profileCompletion = round(($completionPoints / 10) * 100);

$profileName = (string) ($user['name'] ?: 'Mitra Balla’ Fresh');
$profileEmail = (string) ($user['email'] ?: '');
$nameParts = preg_split('/\s+/', trim($profileName)) ?: [];
$profileInitial = strtoupper(substr($nameParts[0] ?? 'M', 0, 1));
if (!empty($nameParts[1])) {
    $profileInitial .= strtoupper(substr($nameParts[1], 0, 1));
}

$userServicesPref = !empty($user['service_preferences']) ? explode('|', $user['service_preferences']) : [];

function p_escape($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
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
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#5b8121">
<title>Profil Mitra | Balla’ Fresh</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
:root{
 --green:#5b8121;--green-dark:#405b18;--green-light:#789f31;--green-soft:#f0f5e7;--green-pale:#f7faef;
 --ink:#0f172a;--muted:#64748b;--line:#e2e8f0;--orange:#e48e1e;--orange-soft:#fff6e8;
 --blue:#2563eb;--blue-soft:#eff6ff;--purple:#7c3aed;--purple-soft:#f5f3ff;--red:#b91c1c;
 --shadow-sm:0 4px 6px -1px rgba(0,0,0,.03),0 2px 4px -2px rgba(0,0,0,.03);
 --shadow:0 10px 25px -5px rgba(0,0,0,.05),0 8px 10px -6px rgba(0,0,0,.03);
 --shadow-lg:0 20px 25px -5px rgba(0,0,0,.08),0 8px 10px -6px rgba(0,0,0,.04);
 --radius:20px;--ease:all .25s cubic-bezier(.4,0,.2,1)
}
*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;background:#f8faf6;color:var(--ink);font-family:"DM Sans",sans-serif;line-height:1.6;-webkit-font-smoothing:antialiased}
button,input,textarea,select{font:inherit}button{cursor:pointer}a{color:inherit;text-decoration:none}img{max-width:100%;display:block}[hidden]{display:none!important}
h1,h2,h3,h4{margin:0;font-family:Manrope,sans-serif;line-height:1.25;letter-spacing:-.03em}.container{width:min(1180px,92%);margin:auto}

.site-header{position:sticky;top:0;z-index:1000;background:rgba(248,250,246,.91);backdrop-filter:blur(16px);border-bottom:1px solid rgba(15,23,42,.07)}
.navbar{min-height:78px;display:flex;align-items:center;justify-content:space-between;gap:24px}.logo{width:145px;min-width:125px;height:48px;display:flex;align-items:center}.logo img{max-height:42px;width:auto}.logo-fallback{color:var(--green);font:800 22px Manrope,sans-serif;letter-spacing:-.04em}
.header-right{display:flex;align-items:center;gap:16px}.header-link{display:inline-flex;align-items:center;gap:8px;color:#526047;font-size:13px;font-weight:700;transition:var(--ease)}.header-link:hover{color:var(--green);transform:translateX(-2px)}
.header-profile{display:inline-flex;align-items:center;gap:9px;padding:8px 13px 8px 8px;background:#fff;border:1px solid var(--line);border-radius:999px;color:var(--green-dark);font-size:13px;font-weight:800;box-shadow:var(--shadow-sm)}
.header-avatar{width:32px;height:32px;border-radius:50%;display:grid;place-items:center;background:var(--green-soft);color:var(--green);font-size:11px;font-weight:800;overflow:hidden}
.header-avatar img{width:100%;height:100%;object-fit:cover}

.page-wrap{padding:36px 0 64px}.page-heading{display:flex;align-items:flex-end;justify-content:space-between;gap:18px;margin-bottom:25px}.eyebrow{display:inline-flex;align-items:center;gap:8px;padding:7px 13px;border-radius:999px;color:var(--green);background:var(--green-soft);border:1px solid rgba(91,129,33,.15);font-size:11px;font-weight:800;letter-spacing:.1em;text-transform:uppercase}
.page-heading h1{margin:12px 0 6px;font-size:clamp(27px,3vw,36px);color:#1d2a12}.page-heading p{margin:0;color:var(--muted);font-size:14px}
.sync-tag{display:inline-flex;align-items:center;gap:8px;padding:9px 14px;border-radius:12px;background:#eef7e5;border:1px solid #c9e2b4;color:#2f5817;font-size:11px;font-weight:700}

.alert-notice{padding:14px 18px;border-radius:14px;margin-bottom:20px;font-size:13px;font-weight:700;display:flex;align-items:center;gap:12px}
.alert-notice.success{background:#edf7e8;color:#2c5a17;border:1px solid #c3e2b2}
.alert-notice.error{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}

.profile-layout{display:grid;grid-template-columns:260px minmax(0,1fr);gap:24px;align-items:start}.sidebar{position:sticky;top:100px;display:grid;gap:15px}.identity-card,.side-menu,.content-card,.stat-card{background:#fff;border:1px solid var(--line);border-radius:var(--radius);box-shadow:var(--shadow-sm)}
.identity-card{padding:22px 18px;text-align:center}.avatar-wrap{width:92px;height:92px;position:relative;margin:0 auto 13px}.avatar{width:92px;height:92px;display:grid;place-items:center;overflow:hidden;border-radius:50%;background:linear-gradient(145deg,#edf5df,#dce9bd);color:var(--green-dark);border:4px solid #fff;box-shadow:0 0 0 1px #dce8cc,var(--shadow-sm);font:800 26px Manrope,sans-serif}.avatar img{width:100%;height:100%;object-fit:cover}
.avatar-edit{position:absolute;right:0;bottom:0;width:30px;height:30px;display:grid;place-items:center;border:2px solid #fff;border-radius:50%;background:var(--green);color:#fff;font-size:12px;cursor:pointer;transition:var(--ease)}
.avatar-edit:hover{background:var(--green-dark);transform:scale(1.08)}
.identity-card h2{font-size:17px;margin-bottom:3px}.identity-card .email{color:var(--muted);font-size:11.5px;overflow-wrap:anywhere}.member-badge{display:inline-flex;align-items:center;gap:6px;margin-top:12px;padding:5px 12px;border-radius:999px;background:var(--green-soft);color:var(--green-dark);font-size:10.5px;font-weight:800}

.profile-completion{margin-top:18px;text-align:left}.completion-head{display:flex;justify-content:space-between;gap:8px;margin-bottom:7px;font-size:11px;font-weight:800}.completion-head span:last-child{color:var(--green)}.progress-track{height:8px;background:#edf1e8;border-radius:999px;overflow:hidden}.progress-fill{height:100%;border-radius:inherit;background:linear-gradient(90deg,var(--green),#a4c45e);transition:width 0.4s ease}.completion-help{margin:7px 0 0;color:var(--muted);font-size:10px}

.side-menu{padding:10px}.menu-label{padding:9px 10px 7px;color:#9aa394;font-size:10px;font-weight:800;letter-spacing:.12em;text-transform:uppercase}
.menu-item{width:100%;display:flex;align-items:center;gap:12px;padding:12px 12px;border:0;border-radius:12px;background:transparent;color:#64705b;text-align:left;font-size:13px;font-weight:700;transition:var(--ease)}.menu-item i{width:18px;text-align:center;font-size:14px}.menu-item:hover{color:var(--green);background:#f8faf4}.menu-item.active{color:var(--green-dark);background:var(--green-soft)}.menu-count{margin-left:auto;min-width:20px;height:20px;padding:0 6px;display:grid;place-items:center;border-radius:999px;background:#fff;color:var(--green);font-size:10px;font-weight:800}

.content-area{min-width:0}.view{display:block}.view-title{display:flex;justify-content:space-between;align-items:flex-start;gap:14px;margin:2px 0 18px}.view-title h2{font-size:22px;margin-bottom:4px}.view-title p{margin:0;color:var(--muted);font-size:13px}.content-card{padding:24px;margin-bottom:20px}.card-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:18px}.card-heading h3{font-size:16px}.card-heading p{margin:4px 0 0;color:var(--muted);font-size:12px}

.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:42px;padding:10px 18px;border:0;border-radius:12px;font-size:12.5px;font-weight:800;transition:var(--ease);text-decoration:none}.btn-primary{background:var(--green);color:#fff;box-shadow:0 4px 12px rgba(91,129,33,.18)}.btn-primary:hover{background:var(--green-dark);transform:translateY(-1px)}.btn-outline{color:var(--green);background:#fff;border:1.5px solid #cbd8bc}.btn-outline:hover{background:var(--green-soft)}.btn-soft{color:var(--green-dark);background:var(--green-soft)}.btn-danger-soft{color:#a33c32;background:#fef2f2;border:1px solid #fecaca}

.stats-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin-bottom:20px}.stat-card{padding:18px}.stat-top{display:flex;justify-content:space-between;align-items:center;gap:8px}.stat-icon{width:36px;height:36px;display:grid;place-items:center;border-radius:11px;background:var(--green-soft);color:var(--green);font-size:15px}.stat-icon.orange{background:var(--orange-soft);color:var(--orange)}.stat-icon.blue{background:var(--blue-soft);color:var(--blue)}.stat-number{margin-top:12px;font:800 26px Manrope,sans-serif;letter-spacing:-.04em}.stat-label{color:var(--muted);font-size:11px;font-weight:700}

.status-pill{display:inline-flex;align-items:center;gap:5px;padding:5px 11px;border-radius:999px;font-size:10.5px;font-weight:800;white-space:nowrap}
.status-green{color:#2e5a17;background:#edf7e5;border:1px solid #c8e4b4}
.status-orange{color:#92400e;background:#fef3c7;border:1px solid #fde68a}
.status-blue{color:#1e40af;background:#dbeafe;border:1px solid #bfdbfe}
.status-gray{color:#475569;background:#f1f5f9;border:1px solid #e2e8f0}

.form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}.form-group{display:flex;flex-direction:column;gap:6px;min-width:0}.form-group.full{grid-column:1/-1}.form-group label{color:#2d3a24;font-size:12px;font-weight:800}.required{color:#dc2626}.field-help{color:#64748b;font-size:10.5px;line-height:1.5}.form-control{width:100%;min-height:44px;padding:10px 14px;outline:none;border:1.5px solid #d5ded0;border-radius:12px;background:#fff;color:var(--ink);font-size:12.5px;transition:var(--ease)}.form-control:focus{border-color:var(--green);box-shadow:0 0 0 3px rgba(91,129,33,.12)}.form-control[readonly]{color:#64748b;background:#f8faf6;cursor:not-allowed}textarea.form-control{min-height:95px;resize:vertical}.form-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:20px;padding-top:18px;border-top:1px solid #edf0eb}

.avatar-upload-row{display:flex;align-items:center;gap:18px;margin-bottom:22px;padding:16px;border:1.5px dashed #cbd8bc;border-radius:16px;background:#fbfdf8}
.avatar-large{width:76px;height:76px;flex:0 0 76px;display:grid;place-items:center;overflow:hidden;border-radius:50%;background:var(--green-soft);color:var(--green);font:800 22px Manrope,sans-serif;border:3px solid #fff;box-shadow:var(--shadow-sm)}.avatar-large img{width:100%;height:100%;object-fit:cover}.avatar-upload-copy strong{display:block;font-size:13px}.avatar-upload-copy p{margin:3px 0 10px;color:var(--muted);font-size:11px}

/* Chat styles */
.chat-shell{overflow:hidden;border:1px solid var(--line);border-radius:18px;box-shadow:var(--shadow-sm)}
.chat-head{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:14px 18px;background:#fbfcf9;border-bottom:1px solid var(--line)}
.chat-person{display:flex;align-items:center;gap:10px}
.chat-person-avatar{width:36px;height:36px;display:grid;place-items:center;border-radius:50%;color:var(--green);background:var(--green-soft);font-size:16px}
.chat-body{min-height:280px;max-height:380px;overflow-y:auto;display:flex;flex-direction:column;gap:12px;padding:18px;background:#ffffff}
.chat-date{align-self:center;padding:5px 12px;border-radius:999px;color:var(--muted);background:#f1f5f9;font-size:9.5px;font-weight:700}
.chat-bubble{max-width:78%;padding:11px 14px;border-radius:14px;font-size:12px;line-height:1.5;position:relative}
.chat-bubble.from-admin{align-self:flex-start;background:#f0f5e7;color:#1e3010;border-bottom-left-radius:3px;border:1px solid #dce9bd}
.chat-bubble.from-user{align-self:flex-end;background:var(--green);color:#ffffff;border-bottom-right-radius:3px}
.chat-bubble small{display:block;margin-top:5px;font-size:9.5px;opacity:.75;text-align:right}
.chat-compose{display:flex;gap:10px;padding:14px;border-top:1px solid var(--line);background:#fbfcf9}
.chat-compose input{flex:1;min-width:0;padding:11px 14px;border:1.5px solid var(--line);border-radius:12px;outline:none;background:#fff;font-size:12px}
.chat-compose input:focus{border-color:var(--green)}
.chat-compose button{min-width:44px;border:0;border-radius:12px;color:#fff;background:var(--green);display:grid;place-items:center;font-size:14px}
.chat-compose{align-items:flex-end}
.chat-compose-main{flex:1;min-width:0;display:grid;gap:9px}
.chat-compose-main>input{width:100%;min-height:42px}
.chat-compose-tools{display:flex;align-items:center;gap:12px;flex-wrap:wrap}
.chat-attach-button{display:inline-flex;align-items:center;gap:7px;padding:8px 12px;border:1px solid #cbd8bc;border-radius:10px;background:#fff;color:var(--green-dark);font-size:11px;font-weight:800;cursor:pointer;transition:var(--ease)}
.chat-attach-button:hover{background:var(--green-soft);border-color:var(--green)}
.chat-upload-help{color:var(--muted);font-size:10px}
.chat-file-preview{display:flex;flex-wrap:wrap;gap:7px}
.chat-file-chip{display:inline-flex;align-items:center;gap:7px;max-width:100%;padding:6px 9px;border-radius:9px;background:#eef5e6;color:#31491c;font-size:10.5px;overflow-wrap:anywhere}
.chat-attachment{display:grid;gap:7px;margin-top:9px;padding:8px;border:1px solid rgba(128,145,110,.25);border-radius:10px;background:rgba(255,255,255,.55)}
.chat-attachment img{width:min(240px,100%);max-height:220px;object-fit:contain;border-radius:8px;background:#fff}
.chat-attachment video{width:min(280px,100%);max-height:220px;border-radius:8px;background:#111}
.chat-attachment-link{display:inline-flex;align-items:center;gap:7px;overflow-wrap:anywhere;color:inherit;font-size:10.5px;font-weight:800;text-decoration:underline}
.chat-bubble.from-user .chat-attachment{color:#20310f;background:rgba(255,255,255,.92)}
@media(max-width:600px){.chat-compose{gap:8px;padding:10px}.chat-compose button{min-width:42px;height:42px}.chat-upload-help{width:100%}}


/* Setting rows & switches */
.setting-row{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:16px 0;border-bottom:1px solid #edf0eb}
.setting-row:last-child{border-bottom:0}
.setting-info{flex:1;min-width:0}
.setting-info strong{display:block;font-size:13.5px;color:#1e293b}
.setting-info p{margin:3px 0 0;color:var(--muted);font-size:11.5px;line-height:1.5}
.switch{position:relative;display:inline-block;width:48px;height:26px;flex-shrink:0}
.switch input{opacity:0;width:0;height:0}
.switch-track{position:absolute;cursor:pointer;inset:0;background-color:#cbd5e1;transition:.25s ease;border-radius:999px}
.switch-track:before{position:absolute;content:"";height:20px;width:20px;left:3px;bottom:3px;background-color:#fff;transition:.25s ease;border-radius:50%;box-shadow:0 2px 4px rgba(0,0,0,.15)}
.switch input:checked+.switch-track{background-color:var(--green)}
.switch input:checked+.switch-track:before{transform:translateX(22px)}

/* Password Modal */
.pwd-modal{position:fixed;inset:0;z-index:3000;background:rgba(15,23,42,0.65);backdrop-filter:blur(4px);display:none;align-items:center;justify-content:center;padding:20px}
.pwd-modal.active{display:flex}
.pwd-box{background:#fff;width:min(480px,100%);border-radius:24px;overflow:hidden;box-shadow:var(--shadow-lg)}
.pwd-head{display:flex;justify-content:space-between;align-items:center;padding:18px 24px;border-bottom:1px solid var(--line);background:#f8faf6}
.pwd-body{padding:24px}

/* Policy modal */
.policy-modal{position:fixed;inset:0;z-index:3000;background:rgba(15,23,42,0.65);backdrop-filter:blur(4px);display:none;align-items:center;justify-content:center;padding:20px}
.policy-modal.active{display:flex}
.policy-box{background:#fff;width:min(680px,100%);max-height:85vh;border-radius:24px;overflow:hidden;display:flex;flex-direction:column;box-shadow:var(--shadow-lg)}
.policy-header{display:flex;justify-content:space-between;align-items:center;padding:18px 24px;border-bottom:1px solid var(--line);background:#f8faf6}
.policy-body{padding:24px;overflow-y:auto;font-size:13px;line-height:1.8;color:#334155}
.policy-footer{padding:14px 24px;border-top:1px solid var(--line);display:flex;justify-content:flex-end;background:#f8faf6}

.footer{padding:28px 15px;border-top:1px solid var(--line);background:#fff;color:var(--muted);text-align:center;font-size:12px}
.footer-policy-links{display:flex;align-items:center;justify-content:center;gap:16px;margin-top:8px}
.footer-policy-links a{color:var(--green);font-weight:700;text-decoration:none}
.footer-policy-links a:hover{text-decoration:underline}

@media(max-width:880px){.profile-layout{grid-template-columns:1fr}.sidebar{position:static}}
</style>
</head>
<body>

<header class="site-header">
 <div class="container navbar">
  <a class="logo" href="index.php" aria-label="Balla’ Fresh">
   <img src="logo-balla-fresh.png" alt="Logo Balla’ Fresh" onerror="this.style.display='none';this.nextElementSibling.hidden=false">
   <span class="logo-fallback" hidden>Balla’ Fresh</span>
  </a>
  <div class="header-right">
   <a class="header-link" href="index.php"><i class="fa-solid fa-arrow-left"></i> Kembali ke Beranda</a>
   <a class="header-profile" href="profil.php">
     <span class="header-avatar">
       <?php if (!empty($user['profile_image'])): ?>
         <img src="<?= p_escape($user['profile_image']) ?>" alt="Avatar">
       <?php else: ?>
         <?= p_escape($profileInitial) ?>
       <?php endif; ?>
     </span>
     <span><?= p_escape($profileName) ?></span>
   </a>
  </div>
 </div>
</header>

<main class="page-wrap">
 <div class="container">

  <div class="page-heading">
   <div>
     <span class="eyebrow"><i class="fa-solid fa-seedling"></i> Member Area</span>
     <h1>Profil Mitra & Usaha</h1>
     <p>Kelola data pribadi, identitas usaha, pesanan layanan, konsultasi chat, dan pengaturan akun Anda.</p>
   </div>
   <span class="sync-tag"><i class="fa-solid fa-database"></i> Tersinkronisasi ke Database</span>
  </div>

  <?php if ($profileMessage !== ''): ?>
   <div class="alert-notice <?= p_escape($profileMessageType) ?>">
     <i class="fa-solid <?= $profileMessageType === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' ?>"></i>
     <span><?= p_escape($profileMessage) ?></span>
   </div>
  <?php endif; ?>

  <div class="profile-layout">
   <!-- SIDEBAR AKUN -->
   <aside class="sidebar">
    <section class="identity-card">
     <div class="avatar-wrap">
       <div class="avatar" id="sidebarAvatar">
         <?php if (!empty($user['profile_image'])): ?>
           <img src="<?= p_escape($user['profile_image']) ?>" alt="Foto profil">
         <?php else: ?>
           <span><?= p_escape($profileInitial) ?></span>
         <?php endif; ?>
       </div>
       <label class="avatar-edit" for="profilePhotoInput" title="Ganti foto profil"><i class="fa-solid fa-camera"></i></label>
     </div>
     <h2 id="sidebarName"><?= p_escape($profileName) ?></h2>
     <div class="email"><?= p_escape($profileEmail) ?></div>
     <?php if (!empty($user['business_name'])): ?>
       <div style="font-size:11px;color:var(--green);font-weight:700;margin-top:4px"><i class="fa-solid fa-store"></i> <?= p_escape($user['business_name']) ?></div>
     <?php endif; ?>
     <span class="member-badge"><i class="fa-solid fa-circle-check"></i> Mitra Terdaftar</span>

     <div class="profile-completion">
       <div class="completion-head"><span>Kelengkapan profil</span><span><?= $profileCompletion ?>%</span></div>
       <div class="progress-track"><div class="progress-fill" style="width:<?= $profileCompletion ?>%"></div></div>
       <p class="completion-help">Lengkapi data usaha dan alamat untuk memudahkan pemrosesan layanan.</p>
     </div>
    </section>

    <section class="side-menu">
     <div class="menu-label">Menu navigasi</div>
     <nav aria-label="Menu profil">
      <button class="menu-item active" type="button" data-view="overview"><i class="fa-solid fa-grip"></i> Ringkasan</button>
      <button class="menu-item" type="button" data-view="personal"><i class="fa-regular fa-user"></i> Data Pengguna</button>
      <button class="menu-item" type="button" data-view="business"><i class="fa-solid fa-store"></i> Profil Usaha</button>
      <button class="menu-item" type="button" data-view="orders"><i class="fa-solid fa-box"></i> Pesanan Layanan <span class="menu-count"><?= count($orders) ?></span></button>
      <button class="menu-item" type="button" data-view="consultation"><i class="fa-regular fa-comments"></i> Konsultasi &amp; Chat <span class="menu-count"><?= count($consultations) ?></span></button>
      <button class="menu-item" type="button" data-view="settings"><i class="fa-solid fa-sliders"></i> Pengaturan &amp; Privasi</button>
     </nav>
    </section>

    <section class="identity-card" style="padding:16px;background:var(--green-soft);border-color:#dce9bd">
      <div style="font-size:12px;font-weight:800;color:var(--green-dark);margin-bottom:6px"><i class="fa-solid fa-headset"></i> Butuh Bantuan Cepat?</div>
      <p style="font-size:11px;color:#526047;margin:0 0 10px">Ajukan konsultasi langsung atau hubungi tim melalui WhatsApp.</p>
      <a href="https://wa.me/6281213725107" target="_blank" class="btn btn-outline" style="width:100%;font-size:11.5px"><i class="fa-brands fa-whatsapp"></i> Chat WhatsApp</a>
    </section>
   </aside>

   <!-- KONTEN AREA TAB -->
   <div class="content-area">

    <!-- 1. RINGKASAN AKUN -->
    <section class="view" id="view-overview">
     <div class="content-card" style="background:linear-gradient(135deg,#f2f7e9 0%,#ffffff 80%);border-color:#dce8cc">
      <span class="eyebrow"><i class="fa-solid fa-sparkles"></i> Dashboard Mitra</span>
      <h2 style="font-size:24px;margin:8px 0 6px;color:#1d2a12">Halo, <?= p_escape($profileName) ?>!</h2>
      <p style="color:#526047;font-size:13px;margin:0 0 18px">Selamat datang di member area Balla’ Fresh. Di sini Anda dapat memantau pesanan, memperbarui identitas usaha, dan berkonsultasi langsung dengan tim kami.</p>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <button class="btn btn-primary" type="button" data-go="personal"><i class="fa-regular fa-pen-to-square"></i> Perbarui Data Diri</button>
        <button class="btn btn-outline" type="button" data-go="business"><i class="fa-solid fa-store"></i> Lengkapi Profil Usaha</button>
        <button class="btn btn-soft" type="button" data-go="consultation"><i class="fa-regular fa-comments"></i> Konsultasi Chat</button>
      </div>
     </div>

     <div class="stats-grid">
      <article class="stat-card">
        <div class="stat-top"><span class="stat-icon"><i class="fa-solid fa-box"></i></span><span class="status-pill status-blue"><?= count($orders) ?> Total</span></div>
        <div class="stat-number"><?= count($orders) ?></div>
        <div class="stat-label">Pesanan Layanan Digital</div>
      </article>
      <article class="stat-card">
        <div class="stat-top"><span class="stat-icon orange"><i class="fa-regular fa-comments"></i></span><span class="status-pill status-orange"><?= count($consultations) ?> Sesi</span></div>
        <div class="stat-number"><?= count($consultations) ?></div>
        <div class="stat-label">Permintaan Konsultasi</div>
      </article>
      <article class="stat-card">
        <div class="stat-top"><span class="stat-icon"><i class="fa-solid fa-seedling"></i></span><span class="status-pill status-green"><?= $profileCompletion ?>%</span></div>
        <div class="stat-number"><?= $profileCompletion ?>%</div>
        <div class="stat-label">Kelengkapan Akun Mitra</div>
      </article>
     </div>

     <div class="content-card">
       <div class="card-heading">
         <div>
           <h3>Informasi Usaha Terdaftar</h3>
           <p>Data usaha ini otomatis terhubung dengan dashboard admin dan proses checkout layanan.</p>
         </div>
         <button class="btn btn-outline" type="button" data-go="business"><i class="fa-solid fa-pen"></i> Edit Usaha</button>
       </div>
       <div class="form-grid">
         <div><strong>Nama Usaha:</strong> <span style="color:#526047"><?= p_escape($user['business_name'] ?: 'Belum diisi') ?></span></div>
         <div><strong>Bidang Usaha:</strong> <span style="color:#526047"><?= p_escape($user['business_type'] ?: 'Belum diisi') ?></span></div>
         <div><strong>Posisi / Jabatan:</strong> <span style="color:#526047"><?= p_escape($user['business_role'] ?: 'Belum diisi') ?></span></div>
         <div><strong>Media Sosial:</strong> <span style="color:#526047"><?= p_escape($user['social_media'] ?: 'Belum diisi') ?></span></div>
         <div class="form-group full"><strong>Lokasi Usaha:</strong> <span style="color:#526047"><?= p_escape($user['business_address'] ?: 'Belum diisi') ?></span></div>
       </div>
     </div>
    </section>

    <!-- 2. DATA PENGGUNA -->
    <section class="view" id="view-personal" hidden>
     <div class="view-title">
       <div>
         <h2>Data Pengguna</h2>
         <p>Perbarui informasi pribadi, tanggal lahir, dan foto profil akun Anda. Perubahan tersimpan langsung ke database.</p>
       </div>
       <span class="status-pill status-green"><i class="fa-solid fa-shield-halved"></i> Data Pribadi</span>
     </div>

     <form class="content-card" method="post" enctype="multipart/form-data">
      <input type="hidden" name="profile_action" value="save_personal">

      <div class="card-heading">
        <div>
          <h3>Foto Profil Pengguna</h3>
          <p>Unggah foto profil yang jelas. Foto ini juga akan tampil pada dashboard admin.</p>
        </div>
      </div>

      <div class="avatar-upload-row">
        <div class="avatar-large" id="largeAvatar">
          <?php if (!empty($user['profile_image'])): ?>
            <img src="<?= p_escape($user['profile_image']) ?>" alt="Foto profil">
          <?php else: ?>
            <span><?= p_escape($profileInitial) ?></span>
          <?php endif; ?>
        </div>
        <div class="avatar-upload-copy">
          <strong>Pilih Foto dari Komputer</strong>
          <p>Format JPG, PNG, atau WEBP. Maksimal 2 MB.</p>
          <input type="file" id="profilePhotoInput" name="profile_photo" accept="image/png,image/jpeg,image/webp">
        </div>
      </div>

      <div class="card-heading">
        <div>
          <h3>Informasi Pribadi &amp; Kontak</h3>
          <p>Informasi ini digunakan sebagai narahubung kemitraan dan invoice layanan.</p>
        </div>
      </div>

      <div class="form-grid">
       <div class="form-group">
         <label for="fullName">Nama Lengkap <span class="required">*</span></label>
         <input class="form-control" id="fullName" name="name" type="text" maxlength="100" value="<?= p_escape($user['name']) ?>" required>
       </div>

       <div class="form-group">
         <label for="email">Alamat Email (Identitas Akun)</label>
         <input class="form-control" id="email" type="email" value="<?= p_escape($user['email']) ?>" readonly>
         <span class="field-help">Email digunakan untuk login dan verifikasi akun.</span>
       </div>

       <div class="form-group">
         <label for="phone">Nomor HP / WhatsApp <span class="required">*</span></label>
         <input class="form-control" id="phone" name="phone" type="tel" maxlength="25" placeholder="Contoh: 081234567890" value="<?= p_escape($user['phone']) ?>" required>
       </div>

       <div class="form-group">
         <label for="birthDate">Tanggal Lahir</label>
         <input class="form-control" id="birthDate" name="birth_date" type="date" value="<?= p_escape($user['birth_date'] ?? '') ?>">
         <span class="field-help">Digunakan untuk verifikasi identitas mitra bisnis.</span>
       </div>

       <div class="form-group full">
         <label for="address">Alamat Tempat Tinggal / Domisili <span class="required">*</span></label>
         <textarea class="form-control" id="address" name="address" maxlength="500" placeholder="Nama jalan, nomor rumah, RT/RW, kelurahan, kecamatan, kota, provinsi, dan kode pos" required><?= p_escape($user['address'] ?? '') ?></textarea>
         <span class="field-help">Alamat domisili mitra yang tersimpan di sistem Balla’ Fresh.</span>
       </div>
      </div>

      <div class="form-actions">
        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan Data Pengguna</button>
      </div>
     </form>
    </section>

    <!-- 3. PROFIL USAHA -->
    <section class="view" id="view-business" hidden>
     <div class="view-title">
       <div>
         <h2>Profil Usaha &amp; Kustomisasi Layanan</h2>
         <p>Data profil usaha ini terhubung langsung dengan Dashboard Admin dan otomatis mengisi form checkout pemesanan layanan.</p>
       </div>
       <span class="status-pill status-green"><i class="fa-solid fa-store"></i> Identitas Bisnis</span>
     </div>

     <form class="content-card" method="post">
      <input type="hidden" name="profile_action" value="save_business">

      <div class="card-heading">
        <div>
          <h3>Identitas Usaha / Kebun Hidroponik</h3>
          <p>Informasi brand, bidang usaha, dan posisi Anda dalam pengelolaan usaha.</p>
        </div>
      </div>

      <div class="form-grid">
       <div class="form-group">
         <label for="bName">Nama Usaha / Brand Kebun <span class="required">*</span></label>
         <input class="form-control" id="bName" name="business_name" type="text" maxlength="150" placeholder="Contoh: Celebes Hydro Farm" value="<?= p_escape($user['business_name'] ?? '') ?>" required>
       </div>

       <div class="form-group">
         <label for="bType">Bidang Usaha</label>
         <select class="form-control" id="bType" name="business_type">
           <option value="">Pilih bidang usaha</option>
           <?php 
           $types = ['Hidroponik Rumahan', 'Pertanian & Perkebunan', 'Sayuran & Buah Segar', 'F&B Sehat / Salad Bar', 'Retail & Distribusi', 'Penyedia Alat & Nutrisi', 'Jasa & Edukasi', 'Lainnya'];
           foreach ($types as $t): 
           ?>
             <option value="<?= p_escape($t) ?>" <?= ($user['business_type'] ?? '') === $t ? 'selected' : '' ?>><?= p_escape($t) ?></option>
           <?php endforeach; ?>
         </select>
       </div>

       <div class="form-group">
         <label for="bRole">Posisi / Peran di Usaha</label>
         <input class="form-control" id="bRole" name="business_role" type="text" maxlength="100" placeholder="Contoh: Owner &amp; Pengelola / Manajer Pemasaran" value="<?= p_escape($user['business_role'] ?? '') ?>">
       </div>

       <div class="form-group">
         <label for="socMedia">Akun Media Sosial Usaha</label>
         <input class="form-control" id="socMedia" name="social_media" type="text" maxlength="255" placeholder="Contoh: @celebes_hydrofarm atau link Instagram" value="<?= p_escape($user['social_media'] ?? '') ?>">
       </div>

       <div class="form-group full">
         <label for="webUrl">Website Usaha (Jika Ada)</label>
         <input class="form-control" id="webUrl" name="website_url" type="url" maxlength="255" placeholder="https://celebeshydro.com" value="<?= p_escape($user['website_url'] ?? '') ?>">
       </div>

       <div class="form-group full">
         <label for="bAddress">Lokasi / Alamat Kebun Operasional</label>
         <textarea class="form-control" id="bAddress" name="business_address" placeholder="Alamat lokasi instalasi hidroponik atau workshop usaha Anda..."><?= p_escape($user['business_address'] ?? '') ?></textarea>
       </div>

       <div class="form-group full">
         <label for="bDesc">Deskripsi Singkat Usaha</label>
         <textarea class="form-control" id="bDesc" name="business_description" rows="3" placeholder="Ceritakan varietas tanaman yang Anda kembangkan (misal: selada romaine, pakcoy, mint) dan target pasar kebun Anda."><?= p_escape($user['business_description'] ?? '') ?></textarea>
       </div>
      </div>

      <div class="card-heading" style="margin-top:24px">
        <div>
          <h3>Preferensi Kebutuhan Digitalisasi</h3>
          <p>Pilih solusi yang paling mendesak dibutuhkan oleh kebun Anda saat ini.</p>
        </div>
      </div>

      <div class="form-grid">
       <label class="stat-card" style="display:flex;align-items:center;gap:12px;cursor:pointer">
         <input type="checkbox" name="service_preferences[]" value="Branding" <?= in_array('Branding', $userServicesPref, true) ? 'checked' : '' ?> style="width:18px;height:18px;accent-color:var(--green)">
         <div><strong>Starter Branding</strong><small style="display:block;color:var(--muted);font-size:11px">Desain logo, stiker kemasan, dan profil visual</small></div>
       </label>
       <label class="stat-card" style="display:flex;align-items:center;gap:12px;cursor:pointer">
         <input type="checkbox" name="service_preferences[]" value="E-Commerce" <?= in_array('E-Commerce', $userServicesPref, true) ? 'checked' : '' ?> style="width:18px;height:18px;accent-color:var(--green)">
         <div><strong>E-Commerce Grow</strong><small style="display:block;color:var(--muted);font-size:11px">Website pemesanan sayur segar &amp; katalog online</small></div>
       </label>
       <label class="stat-card" style="display:flex;align-items:center;gap:12px;cursor:pointer">
         <input type="checkbox" name="service_preferences[]" value="Marketing" <?= in_array('Marketing', $userServicesPref, true) ? 'checked' : '' ?> style="width:18px;height:18px;accent-color:var(--green)">
         <div><strong>Full Marketing Scale</strong><small style="display:block;color:var(--muted);font-size:11px">Konten edukasi, manajemen media sosial &amp; iklan</small></div>
       </label>
       <label class="stat-card" style="display:flex;align-items:center;gap:12px;cursor:pointer">
         <input type="checkbox" name="service_preferences[]" value="Packaging" <?= in_array('Packaging', $userServicesPref, true) ? 'checked' : '' ?> style="width:18px;height:18px;accent-color:var(--green)">
         <div><strong>Standardisasi Kemasan</strong><small style="display:block;color:var(--muted);font-size:11px">Label nutrisi, barcode, dan packaging ramah lingkungan</small></div>
       </label>
      </div>

      <div class="form-actions">
        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Simpan Profil Usaha</button>
      </div>
     </form>
    </section>

    <!-- 4. PESANAN LAYANAN -->
    <section class="view" id="view-orders" hidden>
     <div class="view-title">
       <div>
         <h2>Pesanan Layanan Saya</h2>
         <p>Pantau status transaksi, invoice, dan kemajuan pengerjaan proyek digital kebun Anda.</p>
       </div>
       <span class="status-pill status-blue"><?= count($orders) ?> Pesanan Tercatat</span>
     </div>

     <?php if (empty($orders)): ?>
       <div class="content-card" style="text-align:center;padding:48px 20px">
         <i class="fa-solid fa-box-open" style="font-size:42px;color:#cbd5e1;margin-bottom:14px"></i>
         <h3 style="font-size:18px;margin-bottom:6px">Belum Ada Pesanan Layanan</h3>
         <p style="color:var(--muted);font-size:13px;max-width:440px;margin:0 auto 20px">Anda belum memesan paket digitalisasi. Pilih layanan yang sesuai dengan kebutuhan kebun Anda sekarang.</p>
         <a href="index.php#layanan" class="btn btn-primary"><i class="fa-solid fa-bag-shopping"></i> Jelajahi Katalog Layanan</a>
       </div>
     <?php else: ?>
       <?php foreach ($orders as $order): ?>
         <div class="content-card">
           <div class="card-heading">
             <div>
               <h3>Pesanan #<?= p_escape($order['order_number']) ?></h3>
               <p>Dipesan pada <?= p_escape(date('d M Y, H:i', strtotime((string)$order['created_at']))) ?> · Metode: <?= p_escape(strtoupper((string)($order['payment_method'] ?? 'Transfer Bank'))) ?></p>
             </div>
             <span class="status-pill <?= strtolower((string)$order['status']) === 'completed' ? 'status-green' : (strtolower((string)$order['status']) === 'pending' ? 'status-orange' : 'status-blue') ?>">
               <?= p_escape(ucfirst((string)$order['status'])) ?>
             </span>
           </div>

           <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-top:1px solid #f1f5f9;border-bottom:1px solid #f1f5f9">
             <div>
               <strong style="display:block;font-size:14px"><?= p_escape($order['business_name'] ?: 'Usaha Mitra') ?></strong>
               <small style="color:var(--muted)"><?= p_escape($order['notes'] ?: 'Pemesanan layanan digital Balla’ Fresh') ?></small>
             </div>
             <div style="text-align:right">
               <small style="display:block;color:var(--muted);font-size:11px">Total Investasi</small>
               <strong style="font-size:17px;color:#e48e1e">Rp<?= number_format((float)$order['total_amount'], 0, ',', '.') ?></strong>
             </div>
           </div>

           <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:14px;flex-wrap:wrap">
             <a href="invoice.php?order=<?= urlencode($order['order_number']) ?>" target="_blank" class="btn btn-outline" style="text-decoration:none">
               <i class="fa-solid fa-file-invoice"></i> Invoice Digital
             </a>
             <?php if (!in_array($order['payment_status'] ?? '', ['settlement', 'capture', 'lunas'], true) && ($order['payment_method'] ?? '') === 'midtrans'): ?>
               <a href="payment_pending.php?order_id=<?= urlencode($order['order_number']) ?>" class="btn btn-primary" style="text-decoration:none;background:#002b49;color:#00c2ff;border-color:#002b49">
                 <i class="fa-solid fa-credit-card"></i> Bayar Sekarang
               </a>
             <?php endif; ?>
             <button class="btn btn-outline" type="button" data-go="consultation"><i class="fa-regular fa-comments"></i> Hubungi Konsultan Terkait Pesanan</button>
           </div>
         </div>
       <?php endforeach; ?>
     <?php endif; ?>
    </section>

    <!-- 5. KONSULTASI & ROOM CHAT -->
    <section class="view" id="view-consultation" hidden>
     <div class="view-title">
       <div>
         <h2>Konsultasi &amp; Room Chat Mitra</h2>
         <p>Ruang konsultasi langsung dengan tim Balla’ Fresh. Room chat dapat digunakan setelah permintaan disetujui (ACC) oleh admin.</p>
       </div>
       <span class="status-pill status-green"><i class="fa-solid fa-headset"></i> Layanan Konsultasi</span>
     </div>

     <!-- LIST KONSULTASI -->
     <div class="content-card">
       <div class="card-heading">
         <div>
           <h3>Daftar Permintaan Konsultasi Anda</h3>
           <p>Lihat status persetujuan dari admin untuk setiap sesi konsultasi.</p>
         </div>
         <button class="btn btn-primary" type="button" id="btnToggleNewConsultation"><i class="fa-solid fa-plus"></i> Ajukan Konsultasi Baru</button>
       </div>

       <!-- FORM PENGAJUAN KONSULTASI BARU (TOGGLE) -->
       <div id="newConsultationFormWrapper" style="display:none;background:#f9fbf7;border:1.5px solid #dce9bd;border-radius:16px;padding:20px;margin-bottom:20px">
         <h4 style="font-size:15px;margin-bottom:6px"><i class="fa-solid fa-paper-plane" style="color:var(--green)"></i> Ajukan Sesi Konsultasi Kebutuhan Usaha</h4>
         <p style="font-size:12px;color:var(--muted);margin-bottom:14px">Ceritakan kendala atau target yang ingin Anda capai agar tim kami dapat mempersiapkan solusi yang tepat.</p>
         <form method="post">
           <input type="hidden" name="profile_action" value="request_consultation">
           <div class="form-grid">
             <div class="form-group full">
               <label>Topik / Layanan yang Diminati</label>
               <select class="form-control" name="category" required>
                 <option>Starter Branding &amp; Kemasan Sayur</option>
                 <option>Pengembangan Website E-Commerce Hidroponik</option>
                 <option>Pemasaran Digital &amp; Social Media Management</option>
                 <option>Penyusunan Strategi &amp; Riset Pasar Segar</option>
                 <option>Konsultasi Umum / Belum Tahu Pilihan</option>
               </select>
             </div>
             <div class="form-group full">
               <label>Kendala / Deskripsi Kebutuhan Kebun <span class="required">*</span></label>
               <textarea class="form-control" name="notes" placeholder="Contoh: Kami memproduksi 200 kg selada per bulan, namun kemasan masih polos dan ingin membuat katalog pemesanan online via website..." required></textarea>
             </div>
           </div>
           <div class="form-actions" style="border-top:none;margin-top:10px;padding-top:0">
             <button class="btn btn-primary" type="submit"><i class="fa-solid fa-paper-plane"></i> Kirim Permintaan ke Admin</button>
           </div>
         </form>
       </div>

       <?php if (empty($consultations)): ?>
         <div style="text-align:center;padding:32px 14px;color:var(--muted)">
           <i class="fa-regular fa-comments" style="font-size:36px;color:#cbd5e1;margin-bottom:10px"></i>
           <p style="font-size:13px;margin:0">Belum ada sesi konsultasi. Klik tombol <strong>"Ajukan Konsultasi Baru"</strong> di atas untuk memulai konsultasi dengan tim ahli kami.</p>
         </div>
       <?php else: ?>
         <?php foreach ($consultations as $c): 
           $isApproved = ($c['status'] === 'approved');
           $isPending = ($c['status'] === 'pending');
           $isCompleted = ($c['status'] === 'completed');
         ?>
           <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 16px;border:1px solid #e2e8f0;border-radius:14px;margin-bottom:12px;background:#fff">
             <div style="display:flex;align-items:center;gap:14px">
               <div style="width:40px;height:40px;border-radius:12px;display:grid;place-items:center;background:<?= $isApproved ? 'var(--green-soft)' : ($isPending ? '#fff7ed' : '#f1f5f9') ?>;color:<?= $isApproved ? 'var(--green)' : ($isPending ? '#c2410c' : '#475569') ?>;font-size:16px">
                 <i class="fa-solid <?= $isApproved ? 'fa-comments' : ($isPending ? 'fa-hourglass-half' : 'fa-check') ?>"></i>
               </div>
               <div>
                 <strong style="display:block;font-size:13.5px"><?= p_escape($c['category']) ?></strong>
                 <small style="color:var(--muted)">Diajukan pada <?= p_escape(date('d M Y, H:i', strtotime((string)$c['created_at']))) ?> · <?= p_escape(substr((string)$c['notes'], 0, 80)) ?>...</small>
               </div>
             </div>
             <div style="display:flex;align-items:center;gap:10px">
               <span class="status-pill <?= $isApproved ? 'status-green' : ($isPending ? 'status-orange' : 'status-gray') ?>">
                 <i class="fa-solid <?= $isApproved ? 'fa-check-circle' : ($isPending ? 'fa-clock' : 'fa-check-double') ?>"></i>
                 <?= $isApproved ? 'Disetujui Admin (Room Aktif)' : ($isPending ? 'Menunggu ACC Admin' : 'Selesai') ?>
               </span>
               <?php if ($isApproved): ?>
                 <a href="profil.php?chat_id=<?= (int)$c['id'] ?>#chatRoomSection" class="btn btn-primary" style="padding:6px 14px;min-height:34px;font-size:11.5px">Buka Room Chat <i class="fa-solid fa-arrow-right"></i></a>
               <?php else: ?>
                 <span style="font-size:11px;color:var(--muted);font-style:italic"><i class="fa-solid fa-lock"></i> Chat Terkunci</span>
               <?php endif; ?>
             </div>
           </div>
         <?php endforeach; ?>
       <?php endif; ?>
     </div>

     <!-- ROOM CHAT AKTIF (JIKA ADA KONSULTASI YANG SUDAH DI-ACC) -->
     <div class="content-card" id="chatRoomSection">
       <?php if ($activeConsultation && $activeConsultation['status'] === 'approved'): ?>
         <div class="card-heading">
           <div>
             <h3>Room Chat Konsultasi Resmi</h3>
             <p>Sesi aktif untuk: <strong><?= p_escape($activeConsultation['category']) ?></strong> (ID #<?= (int)$activeConsultation['id'] ?>)</p>
           </div>
           <span class="status-pill status-green"><i class="fa-solid fa-circle"></i> Terhubung ke Admin</span>
         </div>

         <div class="chat-shell">
           <div class="chat-head">
             <div class="chat-person">
               <div class="chat-person-avatar"><i class="fa-solid fa-headset"></i></div>
               <div>
                 <strong>Konsultan &amp; Admin Balla’ Fresh</strong>
                 <small style="color:var(--green);font-weight:700"><i class="fa-solid fa-shield-halved"></i> Resmi Tersambung Langsung ke Dashboard Admin</small>
               </div>
             </div>
             <span class="status-pill status-green"><?= p_escape($activeConsultation['business_name'] ?: 'Mitra Balla’ Fresh') ?></span>
           </div>

           <div class="chat-body" id="chatBodyContainer">
             <div class="chat-date">Permintaan disetujui pada <?= p_escape(date('d M Y, H:i', strtotime((string)($activeConsultation['approved_at'] ?: $activeConsultation['created_at'])))) ?></div>
             <?php if (empty($chatMessages)): ?>
               <div style="text-align:center;color:var(--muted);font-size:12px;padding:20px">
                 Belum ada pesan obrolan. Silakan kirimkan pesan pertama Anda kepada konsultan melalui kotak di bawah.
               </div>
             <?php else: ?>
               <?php foreach ($chatMessages as $msg): 
                 $isAdmin = ($msg['sender_type'] === 'admin');
               ?>
                  <?php
                    $parsedChat = parseChatAttachments((string)($msg['message'] ?? ''));
                    $plainMessage = $parsedChat['text'];
                    $chatAttachments = $parsedChat['attachments'];
                  ?>
                  <div class="chat-bubble <?= $isAdmin ? 'from-admin' : 'from-user' ?>">
                    <div style="font-weight:700;font-size:11px;margin-bottom:3px;opacity:0.85">
                      <i class="fa-solid <?= $isAdmin ? 'fa-user-tie' : 'fa-user' ?>"></i> <?= p_escape($msg['sender_name']) ?>
                    </div>
                    <?php if ($plainMessage !== ''): ?>
                      <div style="word-break:break-word"><?= autoLinkUrls(nl2br(p_escape($plainMessage))) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($chatAttachments)): ?>
                      <div style="display:flex;flex-direction:column;gap:8px;margin-top:6px">
                        <?php foreach ($chatAttachments as $att):
                          $ext = strtolower((string)$att['ext']);
                          $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
                          $isVideo = in_array($ext, ['mp4', 'webm', 'mov'], true);
                        ?>
                          <div class="chat-attachment" style="max-width:280px">
                            <?php if ($isImage): ?>
                              <a href="<?= p_escape($att['url']) ?>" target="_blank" rel="noopener noreferrer">
                                <img src="<?= p_escape($att['url']) ?>" alt="<?= p_escape($att['name']) ?>" loading="lazy" style="border-radius:8px;max-height:200px;object-fit:cover;width:100%">
                              </a>
                            <?php elseif ($isVideo): ?>
                              <video controls preload="metadata" style="border-radius:8px;max-height:200px;width:100%;background:#000">
                                <source src="<?= p_escape($att['url']) ?>">
                                Browser Anda tidak mendukung pemutar video.
                              </video>
                            <?php endif; ?>
                            <a class="chat-attachment-link" href="<?= p_escape($att['url']) ?>" target="_blank" rel="noopener noreferrer" download="<?= p_escape($att['name']) ?>" style="display:inline-flex;align-items:center;gap:6px;font-size:11.5px;padding:6px 10px;background:rgba(0,0,0,0.04);border-radius:6px;text-decoration:none;word-break:break-all">
                              <i class="fa-solid <?= $isImage ? 'fa-image' : ($isVideo ? 'fa-video' : (in_array($ext, ['pdf'], true) ? 'fa-file-pdf' : (in_array($ext, ['doc','docx'], true) ? 'fa-file-word' : (in_array($ext, ['xls','xlsx','csv'], true) ? 'fa-file-excel' : (in_array($ext, ['ppt','pptx'], true) ? 'fa-file-powerpoint' : 'fa-file-lines'))))) ?>" style="color:var(--green)"></i>
                              <span>Buka / Unduh <?= p_escape($att['name']) ?><?= !empty($att['size']) ? ' (' . p_escape($att['size']) . ')' : '' ?></span>
                            </a>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                    <small><?= p_escape(date('H:i', strtotime((string)$msg['created_at']))) ?></small>
                  </div>
<?php endforeach; ?>
             <?php endif; ?>
           </div>

           <form class="chat-compose" method="post" enctype="multipart/form-data" id="chatComposeForm">
             <input type="hidden" name="profile_action" value="send_chat_message">
             <input type="hidden" name="consultation_id" value="<?= (int)$activeConsultation['id'] ?>">
             <div class="chat-compose-main">
               <input type="text" name="chat_message" id="chatMessageInput" placeholder="Tulis pesan konsultasi untuk tim admin..." autocomplete="off">
               <div class="chat-compose-tools">
                 <label class="chat-attach-button" for="chatFilesInput" title="Lampirkan gambar, video, atau dokumen">
                   <i class="fa-solid fa-paperclip"></i><span>Lampirkan file</span>
                 </label>
                 <input type="file" name="chat_files[]" id="chatFilesInput" multiple hidden
                   accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime,.jpg,.jpeg,.png,.webp,.gif,.mp4,.webm,.mov,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv">
                 <span class="chat-upload-help">Maks. 5 file, 20 MB/file</span>
               </div>
               <div id="chatFilePreview" class="chat-file-preview" hidden></div>
             </div>
             <button type="submit" title="Kirim Pesan atau Lampiran" aria-label="Kirim pesan"><i class="fa-solid fa-paper-plane"></i></button>
           </form>
         </div>

       <?php else: ?>
         <div style="text-align:center;padding:36px 16px;background:#fcfdfa;border-radius:16px;border:1.5px dashed #cbd8bc">
           <i class="fa-solid fa-shield-halved" style="font-size:38px;color:var(--green);margin-bottom:12px"></i>
           <h3 style="font-size:17px;color:#1d2a12;margin-bottom:6px">Room Chat Belum Tersedia / Menunggu Persetujuan Admin</h3>
           <p style="font-size:12.5px;color:var(--muted);max-width:480px;margin:0 auto 16px">Sesuai alur konsultasi Balla’ Fresh, room chat baru dapat digunakan setelah permintaan konsultasi disetujui (ACC) oleh tim admin melalui dashboard.</p>
           <button class="btn btn-primary" type="button" onclick="document.getElementById('btnToggleNewConsultation').click();window.scrollTo({top:150,behavior:'smooth'});"><i class="fa-solid fa-paper-plane"></i> Ajukan Permintaan Konsultasi Sekarang</button>
         </div>
       <?php endif; ?>
     </div>
    </section>

    <!-- 6. PENGATURAN & KEBIJAKAN PRIVASI -->
    <section class="view" id="view-settings" hidden>
     <div class="view-title">
       <div>
         <h2>Pengaturan Akun &amp; Privasi</h2>
         <p>Atur preferensi notifikasi, keamanan kata sandi, kontrol privasi profil usaha, serta pelajari ketentuan layanan resmi Balla’ Fresh.</p>
       </div>
       <span class="status-pill status-gray"><i class="fa-solid fa-sliders"></i> Pengaturan</span>
     </div>

     <!-- 1. NOTIFIKASI AKUN -->
     <div class="content-card">
       <div class="card-heading">
         <div>
           <h3>Notifikasi Akun</h3>
           <p>Pilih jenis informasi dan pembaruan yang ingin Anda terima.</p>
         </div>
         <span class="status-pill status-gray"><i class="fa-regular fa-bell"></i> Notifikasi</span>
       </div>
       <div class="setting-row">
         <div class="setting-info">
           <strong>Notifikasi Status Pesanan</strong>
           <p>Informasi otomatis saat pembayaran berhasil, pesanan di-ACC, atau tahap pengerjaan desain berubah.</p>
         </div>
         <label class="switch">
           <input type="checkbox" checked onchange="showToast('Pengaturan notifikasi pesanan diperbarui')">
           <span class="switch-track"></span>
         </label>
       </div>
       <div class="setting-row">
         <div class="setting-info">
           <strong>Notifikasi Konsultasi &amp; Chat</strong>
           <p>Pemberitahuan ketika permohonan konsultasi di-ACC oleh admin dan pesan balasan baru masuk.</p>
         </div>
         <label class="switch">
           <input type="checkbox" checked onchange="showToast('Pengaturan notifikasi konsultasi diperbarui')">
           <span class="switch-track"></span>
         </label>
       </div>
       <div class="setting-row">
         <div class="setting-info">
           <strong>Informasi Layanan &amp; Penawaran Khusus</strong>
           <p>Pembaruan katalog layanan baru, diskon agensi, atau tips pertumbuhan UMKM hidroponik.</p>
         </div>
         <label class="switch">
           <input type="checkbox" checked onchange="showToast('Pengaturan informasi promosi diperbarui')">
           <span class="switch-track"></span>
         </label>
       </div>
       <div class="form-actions" style="margin-top:14px;padding-top:14px">
         <button class="btn btn-primary" type="button" onclick="showToast('Preferensi notifikasi akun berhasil disimpan!')"><i class="fa-regular fa-floppy-disk"></i> Simpan Preferensi</button>
       </div>
     </div>

     <!-- 2. KEAMANAN AKUN -->
     <div class="content-card">
       <div class="card-heading">
         <div>
           <h3>Keamanan Akun</h3>
           <p>Kelola kata sandi dan perlindungan akses masuk ke akun mitra Anda.</p>
         </div>
         <span class="status-pill status-green"><i class="fa-solid fa-lock"></i> Terlindungi</span>
       </div>
       <div class="setting-row">
         <div class="setting-info">
           <strong>Ubah Kata Sandi Akun</strong>
           <p>Gunakan kombinasi kata sandi yang kuat dan tidak digunakan pada platform lain.</p>
         </div>
         <button class="btn btn-outline" type="button" onclick="openPasswordModal()"><i class="fa-solid fa-key"></i> Ubah Password</button>
       </div>
       <div class="setting-row">
         <div class="setting-info">
           <strong>Alamat Email Akun Terdaftar</strong>
           <p><?= p_escape($user['email']) ?></p>
         </div>
         <span class="status-pill status-green"><i class="fa-solid fa-check"></i> Email Aktif</span>
       </div>
     </div>

     <!-- 3. PRIVASI & TAMPILAN PROFIL -->
     <div class="content-card">
       <div class="card-heading">
         <div>
           <h3>Privasi &amp; Tampilan Profil</h3>
           <p>Kontrol visibilitas identitas usaha dan sesi masuk akun Anda.</p>
         </div>
         <span class="status-pill status-gray"><i class="fa-solid fa-eye"></i> Tampilan</span>
       </div>
       <div class="setting-row">
         <div class="setting-info">
           <strong>Tampilkan Nama Usaha di Ringkasan Profil</strong>
           <p>Nama bisnis hidroponik dapat ditampilkan pada banner profil akun mitra Anda.</p>
         </div>
         <label class="switch">
           <input type="checkbox" checked onchange="showToast('Visibilitas nama usaha diperbarui')">
           <span class="switch-track"></span>
         </label>
       </div>
       <div class="setting-row">
         <div class="setting-info">
           <strong>Ingat Perangkat Ini</strong>
           <p>Menjaga sesi masuk Anda tetap aktif saat membuka kembali browser ini.</p>
         </div>
         <label class="switch">
           <input type="checkbox" checked onchange="showToast('Pengaturan sesi perangkat diperbarui')">
           <span class="switch-track"></span>
         </label>
       </div>
     </div>

     <!-- 4. DOKUMEN KEBIJAKAN PRIVASI -->
     <div class="content-card">
       <div class="card-heading">
         <div>
           <h3>Kebijakan Privasi Balla’ Fresh</h3>
           <p>Komitmen kami dalam menjaga kerahasiaan identitas dan data usaha mitra hidroponik.</p>
         </div>
         <button class="btn btn-outline" type="button" onclick="openPolicyModal('privacy')"><i class="fa-solid fa-expand"></i> Baca Selengkapnya</button>
       </div>
       <div style="background:#f8faf6;padding:16px;border-radius:14px;border:1px solid #e2e8f0;font-size:12.5px;color:#475569;line-height:1.7;max-height:110px;overflow:hidden;position:relative">
         <?= nl2br(p_escape(substr($privacyPolicy, 0, 240))) ?>...
         <div style="position:absolute;bottom:0;left:0;right:0;height:40px;background:linear-gradient(transparent,#f8faf6);display:flex;align-items:flex-end;justify-content:center;padding-bottom:4px">
           <span style="font-size:11px;font-weight:700;color:var(--green);cursor:pointer" onclick="openPolicyModal('privacy')">Klik untuk membaca dokumen lengkap</span>
         </div>
       </div>
     </div>

     <!-- 5. SYARAT & KETENTUAN LAYANAN -->
     <div class="content-card">
       <div class="card-heading">
         <div>
           <h3>Syarat &amp; Ketentuan Layanan</h3>
           <p>Pedoman resmi pemesanan layanan, hak cipta aset digital, dan tata cara konsultasi mitra.</p>
         </div>
         <button class="btn btn-outline" type="button" onclick="openPolicyModal('terms')"><i class="fa-solid fa-expand"></i> Baca Selengkapnya</button>
       </div>
       <div style="background:#f8faf6;padding:16px;border-radius:14px;border:1px solid #e2e8f0;font-size:12.5px;color:#475569;line-height:1.7;max-height:110px;overflow:hidden;position:relative">
         <?= nl2br(p_escape(substr($termsConditions, 0, 240))) ?>...
         <div style="position:absolute;bottom:0;left:0;right:0;height:40px;background:linear-gradient(transparent,#f8faf6);display:flex;align-items:flex-end;justify-content:center;padding-bottom:4px">
           <span style="font-size:11px;font-weight:700;color:var(--green);cursor:pointer" onclick="openPolicyModal('terms')">Klik untuk membaca dokumen lengkap</span>
         </div>
       </div>
     </div>

     <!-- 6. KELUAR DARI AKUN -->
     <div class="content-card">
       <div class="card-heading">
         <div>
           <h3>Keluar dari Akun</h3>
           <p>Akhiri sesi aktif Anda pada perangkat ini.</p>
         </div>
       </div>
       <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px">
         <div>
           <strong>Sesi Akun Aktif</strong>
           <p style="margin:2px 0 0;color:var(--muted);font-size:11px">Pastikan seluruh perubahan data telah disimpan sebelum keluar.</p>
         </div>
         <a href="logout.php" class="btn btn-danger-soft"><i class="fa-solid fa-right-from-bracket"></i> Keluar dari Akun</a>
       </div>
     </div>
    </section>

   </div>
  </div>

 </div>
</main>

<!-- POPUP MODAL UBAH KATA SANDI -->
<div class="pwd-modal" id="passwordModal" role="dialog" aria-modal="true">
  <div class="pwd-box">
    <div class="pwd-head">
      <h3 style="font-size:16px;color:#1d2a12;display:flex;align-items:center;gap:8px">
        <i class="fa-solid fa-key" style="color:var(--green)"></i> Ubah Kata Sandi Akun
      </h3>
      <button type="button" class="btn btn-outline" style="min-height:30px;padding:4px 10px;font-size:11px" onclick="closePasswordModal()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="pwd-body">
      <form method="post" style="display:grid;gap:14px">
        <input type="hidden" name="profile_action" value="change_password">
        <div class="form-group">
          <label>Kata Sandi Saat Ini</label>
          <input class="form-control" type="password" name="current_password" placeholder="Masukkan kata sandi lama Anda" required>
        </div>
        <div class="form-group">
          <label>Kata Sandi Baru</label>
          <input class="form-control" type="password" name="new_password" placeholder="Minimal 6 karakter" required minlength="6">
        </div>
        <div class="form-group">
          <label>Konfirmasi Kata Sandi Baru</label>
          <input class="form-control" type="password" name="confirm_password" placeholder="Ulangi kata sandi baru" required minlength="6">
        </div>
        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:8px">
          <button type="button" class="btn btn-outline" onclick="closePasswordModal()">Batal</button>
          <button type="submit" class="btn btn-primary"><i class="fa-solid fa-check"></i> Simpan Password Baru</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- POPUP MODAL KEBIJAKAN PRIVASI & SYARAT KETENTUAN -->
<div class="policy-modal" id="policyModal" role="dialog" aria-modal="true">
  <div class="policy-box">
    <div class="policy-header">
      <h3 id="policyModalTitle" style="font-size:17px;color:#1d2a12">Dokumen Kebijakan</h3>
      <button type="button" class="btn btn-outline" style="min-height:32px;padding:6px 12px;font-size:11px" onclick="closePolicyModal()"><i class="fa-solid fa-xmark"></i> Tutup</button>
    </div>
    <div class="policy-body" id="policyModalContent">
      <!-- Diisi oleh JS -->
    </div>
    <div class="policy-footer">
      <button type="button" class="btn btn-primary" onclick="closePolicyModal()">Saya Mengerti</button>
    </div>
  </div>
</div>

<footer class="footer">
 <div class="container">
  <div>© 2026 Balla’ Fresh · Menjangkau lebih banyak peluang, #DariRumahAja.</div>
  <div class="footer-policy-links">
    <a href="javascript:void(0)" onclick="openPolicyModal('privacy')"><i class="fa-solid fa-shield-halved"></i> Kebijakan Privasi</a>
    <span>•</span>
    <a href="javascript:void(0)" onclick="openPolicyModal('terms')"><i class="fa-solid fa-file-contract"></i> Ketentuan Layanan</a>
    <span>•</span>
    <a href="mailto:ballafresh01@gmail.com"><i class="fa-regular fa-envelope"></i> Hubungi Kami</a>
  </div>
 </div>
</footer>

<div class="toast" id="toast" role="status" aria-live="polite"></div>

<script>
"use strict";
const menuButtons = document.querySelectorAll("[data-view]");
const views = document.querySelectorAll(".view");
const toast = document.getElementById("toast");
let toastTimer;

function showToast(message) {
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add("show");
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove("show"), 3400);
}

function openView(name) {
    views.forEach(v => v.hidden = (v.id !== "view-" + name));
    menuButtons.forEach(btn => btn.classList.toggle("active", btn.dataset.view === name));
    window.scrollTo({ top: 0, behavior: "smooth" });
}

menuButtons.forEach(btn => btn.addEventListener("click", () => openView(btn.dataset.view)));
document.querySelectorAll("[data-go]").forEach(btn => btn.addEventListener("click", () => openView(btn.dataset.go)));

// Pratinjau foto profil instan saat dipilih dari komputer
const photoInput = document.getElementById("profilePhotoInput");
if (photoInput) {
    photoInput.addEventListener("change", e => {
        const file = e.target.files && e.target.files[0];
        if (!file) return;
        if (!["image/jpeg", "image/png", "image/webp"].includes(file.type)) {
            alert("Gunakan file foto berekstensi JPG, PNG, atau WEBP.");
            photoInput.value = "";
            return;
        }
        if (file.size > 2 * 1024 * 1024) {
            alert("Ukuran foto maksimal 2 MB.");
            photoInput.value = "";
            return;
        }
        const reader = new FileReader();
        reader.onload = () => {
            const markup = `<img src="${reader.result}" alt="Foto profil">`;
            const sbAvatar = document.getElementById("sidebarAvatar");
            const lgAvatar = document.getElementById("largeAvatar");
            if (sbAvatar) sbAvatar.innerHTML = markup;
            if (lgAvatar) lgAvatar.innerHTML = markup;
        };
        reader.readAsDataURL(file);
    });
}

// Toggle form konsultasi baru
const toggleBtn = document.getElementById("btnToggleNewConsultation");
const formWrap = document.getElementById("newConsultationFormWrapper");
if (toggleBtn && formWrap) {
    toggleBtn.addEventListener("click", () => {
        formWrap.style.display = (formWrap.style.display === "none" || formWrap.style.display === "") ? "block" : "none";
    });
}

// Scroll otomatis ke bawah chat
const chatBox = document.getElementById("chatBodyContainer");
if (chatBox) {
    chatBox.scrollTop = chatBox.scrollHeight;
}

// Lampiran room chat: validasi awal dan daftar file sebelum dikirim.
const chatFilesInput = document.getElementById("chatFilesInput");
const chatFilePreview = document.getElementById("chatFilePreview");
const chatComposeForm = document.getElementById("chatComposeForm");
const chatMessageInput = document.getElementById("chatMessageInput");
const chatAllowedExtensions = [
    "jpg", "jpeg", "png", "webp", "gif", "mp4", "webm", "mov",
    "pdf", "doc", "docx", "xls", "xlsx", "ppt", "pptx", "txt", "csv"
];
const chatMaxFileSize = 20 * 1024 * 1024;
const chatMaxFiles = 5;

if (chatFilesInput && chatFilePreview) {
    chatFilesInput.addEventListener("change", () => {
        const files = Array.from(chatFilesInput.files || []);
        const invalid = files.find(file => {
            const ext = file.name.split(".").pop().toLowerCase();
            return !chatAllowedExtensions.includes(ext) || file.size <= 0 || file.size > chatMaxFileSize;
        });

        if (files.length > chatMaxFiles) {
            alert("Maksimal 5 file dalam satu pesan.");
            chatFilesInput.value = "";
        } else if (invalid) {
            alert("File " + invalid.name + " tidak didukung atau melebihi 20 MB.");
            chatFilesInput.value = "";
        }

        const selectedFiles = Array.from(chatFilesInput.files || []);
        chatFilePreview.innerHTML = "";
        chatFilePreview.hidden = selectedFiles.length === 0;

        selectedFiles.forEach(file => {
            const chip = document.createElement("span");
            chip.className = "chat-file-chip";
            const icon = document.createElement("i");
            icon.className = file.type.startsWith("image/") ? "fa-regular fa-image" :
                (file.type.startsWith("video/") ? "fa-solid fa-video" : "fa-regular fa-file-lines");
            const label = document.createElement("span");
            label.textContent = file.name + " (" + (file.size / (1024 * 1024)).toFixed(2) + " MB)";
            chip.append(icon, label);
            chatFilePreview.appendChild(chip);
        });
    });
}

if (chatComposeForm) {
    chatComposeForm.addEventListener("submit", event => {
        const hasText = !!(chatMessageInput && chatMessageInput.value.trim());
        const hasFiles = !!(chatFilesInput && chatFilesInput.files && chatFilesInput.files.length);
        if (!hasText && !hasFiles) {
            event.preventDefault();
            alert("Tulis pesan atau pilih minimal satu file untuk dikirim.");
            return;
        }
        if (chatFilesInput && chatFilesInput.files.length > chatMaxFiles) {
            event.preventDefault();
            alert("Maksimal 5 file dalam satu pesan.");
            return;
        }
        if (chatFilesInput && Array.from(chatFilesInput.files).some(file => file.size > chatMaxFileSize)) {
            event.preventDefault();
            alert("Ukuran maksimal setiap file adalah 20 MB.");
        }
    });
}

// Kebijakan Privasi & Syarat Ketentuan Modal
const policyTexts = {
    privacy: <?= json_encode($privacyPolicy) ?>,
    terms: <?= json_encode($termsConditions) ?>
};

function openPolicyModal(type) {
    const modal = document.getElementById("policyModal");
    const title = document.getElementById("policyModalTitle");
    const content = document.getElementById("policyModalContent");
    if (!modal || !title || !content) return;

    if (type === "privacy") {
        title.innerHTML = '<i class="fa-solid fa-shield-halved" style="color:var(--green)"></i> Kebijakan Privasi Balla’ Fresh';
        content.innerHTML = policyTexts.privacy.replace(/\n/g, '<br>');
    } else {
        title.innerHTML = '<i class="fa-solid fa-file-contract" style="color:var(--green)"></i> Syarat &amp; Ketentuan Layanan';
        content.innerHTML = policyTexts.terms.replace(/\n/g, '<br>');
    }
    modal.classList.add("active");
}

function closePolicyModal() {
    const modal = document.getElementById("policyModal");
    if (modal) modal.classList.remove("active");
}

function openPasswordModal() {
    const modal = document.getElementById("passwordModal");
    if (modal) modal.classList.add("active");
}

function closePasswordModal() {
    const modal = document.getElementById("passwordModal");
    if (modal) modal.classList.remove("active");
}

window.addEventListener("click", e => {
    const pModal = document.getElementById("policyModal");
    if (e.target === pModal) closePolicyModal();
    const pwdModal = document.getElementById("passwordModal");
    if (e.target === pwdModal) closePasswordModal();
});

// Buka tab konsultasi otomatis jika URL memuat chat_id
if (window.location.search.includes("chat_id") || window.location.hash.includes("chatRoom")) {
    openView("consultation");
}
</script>
</body>
</html>
