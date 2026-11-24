<?php
ob_start();
ini_set('display_errors', '0');

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/db.php";

$fallbackServices = [
    [
        "id" => 1,
        "name" => "Paket Starter Branding",
        "description" => "Layanan dasar untuk membantu membangun identitas visual usaha hidroponik.",
        "features" => "Konsep visual|Logo utama & variasi|File siap pakai",
        "price" => 150000.00,
        "image" => "https://images.unsplash.com/photo-1634942536790-2534a3b6a3a5?auto=format&fit=crop&w=900&q=80",
        "estimated_days" => 3,
        "category_name" => "Starter Branding"
    ],
    [
        "id" => 2,
        "name" => "Paket E-Commerce Grow",
        "description" => "Layanan untuk membantu menata katalog produk dan mengembangkan penjualan digital.",
        "features" => "Penataan katalog produk|Informasi dan deskripsi produk|Persiapan tampilan penjualan digital",
        "price" => 300000.00,
        "image" => "https://images.unsplash.com/photo-1558655146-d09347e92766?auto=format&fit=crop&w=900&q=80",
        "estimated_days" => 5,
        "category_name" => "E-Commerce Grow"
    ],
    [
        "id" => 3,
        "name" => "Paket Full Marketing Scale",
        "description" => "Layanan pemasaran digital untuk mendukung pertumbuhan bisnis hidroponik.",
        "features" => "Perencanaan konten pemasaran|Strategi media sosial|Rekomendasi promosi digital",
        "price" => 500000.00,
        "image" => "https://images.unsplash.com/photo-1553877522-43269d4ea984?auto=format&fit=crop&w=900&q=80",
        "estimated_days" => 7,
        "category_name" => "Full Marketing Scale"
    ]
];

$layanan = [];

if (isset($conn) && $conn) {
    try {
        if (method_exists($conn, 'set_charset')) {
            $conn->set_charset("utf8mb4");
        }

        $sql = "SELECT
                    s.id,
                    s.name,
                    s.description,
                    s.features,
                    s.price,
                    s.image,
                    s.estimated_days,
                    COALESCE(c.name, 'Layanan Digital') AS category_name
                FROM services AS s
                LEFT JOIN categories AS c
                    ON s.category_id = c.id
                WHERE s.is_available = 1
                ORDER BY s.id ASC";

        $result = $conn->query($sql);

        if ($result && method_exists($result, 'fetch_assoc')) {
            while ($row = $result->fetch_assoc()) {
                $layanan[] = [
                    "id" => (int) $row["id"],
                    "name" => $row["name"],
                    "description" => $row["description"] ?? '',
                    "features" => $row["features"] ?? '',
                    "price" => (float) $row["price"],
                    "image" => $row["image"] ?? '',
                    "estimated_days" => (int) ($row["estimated_days"] ?? 3),
                    "category_name" => $row["category_name"] ?? 'Layanan Digital'
                ];
            }
            if (method_exists($result, 'free')) {
                $result->free();
            }
        }
    } catch (Throwable $e) {
        error_log("Gagal query api_layanan: " . $e->getMessage());
    }
}

// Fallback jika database belum ada tabel atau masih kosong
if (empty($layanan)) {
    $layanan = $fallbackServices;
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

echo json_encode([
    "success" => true,
    "total" => count($layanan),
    "data" => $layanan
], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
exit;
?>
