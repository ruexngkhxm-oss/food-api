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
$checkStmt = mysqli_prepare($conn, 'SELECT id FROM follows WHERE user_id = ? AND chef_id = ? LIMIT 1');
mysqli_stmt_bind_param($checkStmt, 'ii', $userId, $chefId);
mysqli_stmt_execute($checkStmt);
mysqli_stmt_store_result($checkStmt);

$isFollowing = false;
$message = '';

if (mysqli_stmt_num_rows($checkStmt) > 0) {
    // มีอยู่แล้ว -> ยกเลิกการติดตาม (Unfollow)
    mysqli_stmt_close($checkStmt);

    $deleteStmt = mysqli_prepare($conn, 'DELETE FROM follows WHERE user_id = ? AND chef_id = ?');
    mysqli_stmt_bind_param($deleteStmt, 'ii', $userId, $chefId);
    mysqli_stmt_execute($deleteStmt);
    mysqli_stmt_close($deleteStmt);

    $isFollowing = false;
    $message = 'ยกเลิกการติดตามเชฟเรียบร้อยแล้ว';
} else {
    // ยังไม่มี -> เริ่มติดตาม (Follow)
    mysqli_stmt_close($checkStmt);

    $insertStmt = mysqli_prepare($conn, 'INSERT INTO follows (user_id, chef_id) VALUES (?, ?)');
    mysqli_stmt_bind_param($insertStmt, 'ii', $userId, $chefId);
    mysqli_stmt_execute($insertStmt);
    mysqli_stmt_close($insertStmt);

    $isFollowing = true;
    $message = 'ติดตามเชฟเรียบร้อยแล้ว';
}

// นับจำนวนผู้ติดตามล่าสุดของเชฟ
$countStmt = mysqli_prepare($conn, 'SELECT COUNT(*) FROM follows WHERE chef_id = ?');
mysqli_stmt_bind_param($countStmt, 'i', $chefId);
mysqli_stmt_execute($countStmt);
mysqli_stmt_bind_result($countStmt, $followerCount);
mysqli_stmt_fetch($countStmt);
mysqli_stmt_close($countStmt);

send_response(200, [
    'success' => true,
    'message' => $message,
    'is_following' => $isFollowing,
    'follower_count' => (int) $followerCount,
]);

mysqli_close($conn);
