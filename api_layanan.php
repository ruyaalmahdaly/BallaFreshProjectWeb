<?php
header("Content-Type: application/json; charset=UTF-8");

require_once "db.php";

// Pastikan koneksi database tersedia.
if (!isset($conn) || $conn->connect_error) {
    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Koneksi database gagal."
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

// Gunakan UTF-8 untuk data bahasa Indonesia.
$conn->set_charset("utf8mb4");

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

if (!$result) {
    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "Gagal mengambil data layanan."
    ], JSON_UNESCAPED_UNICODE);

    $conn->close();
    exit;
}

$layanan = [];

while ($row = $result->fetch_assoc()) {
    $layanan[] = [
        "id" => (int) $row["id"],
        "name" => $row["name"],
        "description" => $row["description"],
        "features" => $row["features"],
        "price" => (float) $row["price"],
        "image" => $row["image"],
        "estimated_days" => $row["estimated_days"],
        "category_name" => $row["category_name"]
    ];
}

$result->free();
$conn->close();

echo json_encode([
    "success" => true,
    "total" => count($layanan),
    "data" => $layanan
], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
?>
