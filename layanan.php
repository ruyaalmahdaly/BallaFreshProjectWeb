<?php
require_once "db.php";

$sql = "SELECT
            services.id,
            services.name,
            services.description,
            services.price,
            services.estimated_days,
            categories.name AS category_name
        FROM services
        INNER JOIN categories
            ON services.category_id = categories.id
        WHERE services.is_available = 1
        ORDER BY services.id ASC";

$result = $conn->query($sql);

if (!$result) {
    die("Gagal mengambil data layanan.");
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Layanan Balla'Fresh</title>
</head>
<body>

<h1>Daftar Layanan Balla'Fresh</h1>

<?php if ($result->num_rows > 0): ?>
    <?php while ($layanan = $result->fetch_assoc()): ?>
        <article>
            <h2>
                <?= htmlspecialchars($layanan["name"]) ?>
            </h2>

            <p>
                Kategori:
                <?= htmlspecialchars($layanan["category_name"]) ?>
            </p>

            <p>
                <?= htmlspecialchars($layanan["description"]) ?>
            </p>

            <p>
                Harga:
                Rp<?= number_format((float) $layanan["price"], 0, ',', '.') ?>
            </p>

            <p>
                Estimasi pengerjaan:
                <?= (int) $layanan["estimated_days"] ?> hari
            </p>
        </article>
        <hr>
    <?php endwhile; ?>
<?php else: ?>
    <p>Belum ada layanan yang tersedia.</p>
<?php endif; ?>

<?php
$result->free();
$conn->close();
?>

</body>
</html>
