<?php
/**
 * get_notifications.php
 * API ดึงข้อมูลการแจ้งเตือนสำหรับผู้ใช้จากฐานข้อมูลจริง
 *
 * Method: GET
 * Query params:
 *   - user_id (required int)
 */

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_response(405, ['success' => false, 'message' => 'อนุญาตเฉพาะ method GET เท่านั้น']);
}

$userId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

if ($userId <= 0) {
    send_response(400, ['success' => false, 'message' => 'กรุณาระบุ user_id']);
}

$stmt = mysqli_prepare($conn, "
    SELECT id, title, body, is_read, created_at 
    FROM notifications 
    WHERE user_id = ? 
    ORDER BY created_at DESC, id DESC 
    LIMIT 50
");
mysqli_stmt_bind_param($stmt, 'i', $userId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$notifications = [];
while ($row = mysqli_fetch_assoc($result)) {
    $notifications[] = [
        'id'         => (int) $row['id'],
        'title'      => $row['title'],
        'body'       => $row['body'],
        'is_read'    => (bool) $row['is_read'],
        'created_at' => $row['created_at'],
    ];
}
mysqli_stmt_close($stmt);

send_response(200, [
    'success'       => true,
    'count'         => count($notifications),
    'notifications' => $notifications,
]);

mysqli_close($conn);
