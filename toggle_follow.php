<?php
/**
 * toggle_follow.php
 * API สลับการติดตามเชฟ (Follow / Unfollow)
 *
 * Method: POST
 * Body (JSON): { "user_id", "chef_id" }
 */

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_response(405, ['success' => false, 'message' => 'อนุญาตเฉพาะ method POST เท่านั้น']);
}

$body = get_json_body();

$userId = isset($body['user_id']) ? (int) $body['user_id'] : 0;
$chefId = isset($body['chef_id']) ? (int) $body['chef_id'] : 0;

if ($userId <= 0 || $chefId <= 0) {
    send_response(400, ['success' => false, 'message' => 'ข้อมูลผู้ใช้หรือเชฟไม่ถูกต้อง']);
}

if ($userId === $chefId) {
    send_response(400, ['success' => false, 'message' => 'คุณไม่สามารถติดตามตัวเองได้']);
}

// ตรวจสอบว่าติดตามอยู่แล้วหรือไม่
$checkStmt = db_prepare($conn, 'SELECT id FROM follows WHERE user_id = ? AND chef_id = ? LIMIT 1');
db_bind_param($checkStmt, 'ii', $userId, $chefId);
db_execute($checkStmt);
$checkRes = db_get_result($checkStmt);

$isFollowing = false;
$message = '';

if (db_num_rows($checkRes) > 0) {
    // มีอยู่แล้ว -> ยกเลิกการติดตาม (Unfollow)
    db_stmt_close($checkStmt);

    $deleteStmt = db_prepare($conn, 'DELETE FROM follows WHERE user_id = ? AND chef_id = ?');
    db_bind_param($deleteStmt, 'ii', $userId, $chefId);
    db_execute($deleteStmt);
    db_stmt_close($deleteStmt);

    $isFollowing = false;
    $message = 'ยกเลิกการติดตามเชฟเรียบร้อยแล้ว';
} else {
    // ยังไม่มี -> เริ่มติดตาม (Follow)
    db_stmt_close($checkStmt);

    $insertStmt = db_prepare($conn, 'INSERT INTO follows (user_id, chef_id) VALUES (?, ?)');
    db_bind_param($insertStmt, 'ii', $userId, $chefId);
    db_execute($insertStmt);
    db_stmt_close($insertStmt);

    $isFollowing = true;
    $message = 'ติดตามเชฟเรียบร้อยแล้ว';
}

// นับจำนวนผู้ติดตามล่าสุดของเชฟ
$countStmt = db_prepare($conn, 'SELECT COUNT(*) AS cnt FROM follows WHERE chef_id = ?');
db_bind_param($countStmt, 'i', $chefId);
db_execute($countStmt);
$countRes = db_get_result($countStmt);
$countRow = db_fetch_assoc($countRes);
$followerCount = $countRow ? (int) $countRow['cnt'] : 0;
db_stmt_close($countStmt);

send_response(200, [
    'success' => true,
    'message' => $message,
    'is_following' => $isFollowing,
    'follower_count' => $followerCount,
]);

db_close($conn);
?>
