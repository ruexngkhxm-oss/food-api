<?php
/**
 * toggle_bookmark.php
 * บันทึก/ยกเลิกบุ๊กมาร์กสูตรอาหาร (สลับสถานะอัตโนมัติ)
 *
 * Method: POST
 * Body (JSON): { "user_id", "recipe_id" }
 */

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_response(405, ['success' => false, 'message' => 'อนุญาตเฉพาะ method POST เท่านั้น']);
}

$body = get_json_body();

$userId   = isset($body['user_id']) ? (int) $body['user_id'] : 0;
$recipeId = isset($body['recipe_id']) ? (int) $body['recipe_id'] : 0;

if ($userId <= 0 || $recipeId <= 0) {
    send_response(400, ['success' => false, 'message' => 'กรุณาระบุ user_id และ recipe_id ให้ถูกต้อง']);
}

// ----- ตรวจสอบว่าบุ๊กมาร์กไว้อยู่แล้วหรือไม่ -----
$checkStmt = mysqli_prepare($conn, 'SELECT id FROM bookmarks WHERE user_id = ? AND recipe_id = ? LIMIT 1');
mysqli_stmt_bind_param($checkStmt, 'ii', $userId, $recipeId);
mysqli_stmt_execute($checkStmt);
mysqli_stmt_store_result($checkStmt);

if (mysqli_stmt_num_rows($checkStmt) > 0) {
    // ----- มีอยู่แล้ว -> ยกเลิกบุ๊กมาร์ก (DELETE) -----
    mysqli_stmt_close($checkStmt);

    $deleteStmt = mysqli_prepare($conn, 'DELETE FROM bookmarks WHERE user_id = ? AND recipe_id = ?');
    mysqli_stmt_bind_param($deleteStmt, 'ii', $userId, $recipeId);

    if (mysqli_stmt_execute($deleteStmt)) {
        send_response(200, [
            'success' => true,
            'message' => 'ยกเลิกบุ๊กมาร์กแล้ว',
            'is_bookmarked' => false,
        ]);
    } else {
        send_response(500, ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการยกเลิกบุ๊กมาร์ก']);
    }
    mysqli_stmt_close($deleteStmt);
} else {
    // ----- ยังไม่มี -> เพิ่มบุ๊กมาร์กใหม่ (INSERT) -----
    mysqli_stmt_close($checkStmt);

    $insertStmt = mysqli_prepare($conn, 'INSERT INTO bookmarks (user_id, recipe_id) VALUES (?, ?)');
    mysqli_stmt_bind_param($insertStmt, 'ii', $userId, $recipeId);

    if (mysqli_stmt_execute($insertStmt)) {
        send_response(201, [
            'success' => true,
            'message' => 'บันทึกสูตรโปรดแล้ว',
            'is_bookmarked' => true,
        ]);
    } else {
        send_response(500, ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการบันทึกบุ๊กมาร์ก']);
    }
    mysqli_stmt_close($insertStmt);
}

mysqli_close($conn);
