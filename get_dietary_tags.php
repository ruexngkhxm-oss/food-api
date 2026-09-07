<?php
/**
 * get_dietary_tags.php
 * ดึงรายการป้ายกำกับสายสุขภาพทั้งหมด (คีโต, คลีน, มังสวิรัติ, ฮาลาล, ฯลฯ)
 *
 * Method: GET
 */

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_response(405, ['success' => false, 'message' => 'อนุญาตเฉพาะ method GET เท่านั้น']);
}

$sql = "SELECT id, name, icon, sort_order FROM dietary_tags ORDER BY sort_order ASC, id ASC";
$result = mysqli_query($conn, $sql);

$tags = [];
while ($row = mysqli_fetch_assoc($result)) {
    $tags[] = [
        'id'   => (int) $row['id'],
        'name' => $row['name'],
        'icon' => $row['icon'],
    ];
}

send_response(200, [
    'success' => true,
    'count'   => count($tags),
    'tags'    => $tags,
]);

mysqli_close($conn);
