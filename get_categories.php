<?php
/**
 * get_categories.php
 * ดึงรายการหมวดหมู่อาหารทั้งหมด (ใช้แสดงแถบหมวดหมู่ในหน้า Home)
 *
 * Method: GET
 */

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_response(405, ['success' => false, 'message' => 'อนุญาตเฉพาะ method GET เท่านั้น']);
}

$result = mysqli_query($conn, 'SELECT id, name, icon FROM categories ORDER BY sort_order ASC, id ASC');

$categories = [];
while ($row = mysqli_fetch_assoc($result)) {
    $categories[] = [
        'id'   => (int) $row['id'],
        'name' => $row['name'],
        'icon' => $row['icon'],
    ];
}

send_response(200, [
    'success'    => true,
    'categories' => $categories,
]);

mysqli_close($conn);
