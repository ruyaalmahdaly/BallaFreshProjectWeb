<?php
header("Content-Type: application/json; charset=UTF-8");

require_once "db.php";

$sql = "SELECT
            services.id,
            services.name,
            services.description,
            services.features,
            services.price,
            services.image,
            services.estimated_days,
            categories.name AS category_name
        FROM services
        INNER JOIN categories
            ON services.category_id = categories.id
        WHERE services.is_available = 1
        ORDER BY services.id ASC";

$result = $conn->query($sql);

if (!$result) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Gagal mengambil data layanan."
    ]);
    exit;
}

$layanan = [];

while ($row = $result->fetch_assoc()) {
    $layanan[] = $row;
}

echo json_encode([
    "success" => true,
    "data" => $layanan
], JSON_UNESCAPED_UNICODE);

$result->free();
$conn->close();
?>
