<?php
// ============================================================
//  slots.php – Karelia Ulkorakennus Oy
//  Palauttaa JSON-muodossa varatut aikaslotit tietylle päivälle.
//  Kutsutaan: slots.php?date=2026-03-15
// ============================================================

define('DB_HOST',    'localhost');
define('DB_NAME',    'karelia-db');
define('DB_USER',    'karelia-user');
define('DB_PASS',    'iq3A5oHZX8w9izVw7jH2');
define('DB_CHARSET', 'utf8mb4');

header('Content-Type: application/json');
header('Cache-Control: no-store');

$date = $_GET['date'] ?? '';

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['booked' => []]);
    exit;
}

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $stmt = $pdo->prepare(
        "SELECT toivottu_aika FROM varaukset
         WHERE toivottu_pvm = :date
           AND tila != 'peruttu'"
    );
    $stmt->execute([':date' => $date]);
    $booked = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode(['booked' => $booked]);

} catch (PDOException $e) {
    // Return empty on error — don't expose DB details
    echo json_encode(['booked' => [], 'error' => true]);
}