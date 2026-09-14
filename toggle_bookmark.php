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
$checkStmt = db_prepare($conn, 'SELECT id FROM bookmarks WHERE user_id = ? AND recipe_id = ? LIMIT 1');
db_bind_param($checkStmt, 'ii', $userId, $recipeId);
db_execute($checkStmt);
$checkRes = db_get_result($checkStmt);

if (db_num_rows($checkRes) > 0) {
    // ----- มีอยู่แล้ว -> ยกเลิกบุ๊กมาร์ก (DELETE) -----
    db_stmt_close($checkStmt);

    $deleteStmt = db_prepare($conn, 'DELETE FROM bookmarks WHERE user_id = ? AND recipe_id = ?');
    db_bind_param($deleteStmt, 'ii', $userId, $recipeId);

    if (db_execute($deleteStmt)) {
        db_stmt_close($deleteStmt);
        send_response(200, [
            'success' => true,
            'message' => 'ยกเลิกบุ๊กมาร์กแล้ว',
            'is_bookmarked' => false,
        ]);
    } else {
        db_stmt_close($deleteStmt);
        send_response(500, ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการยกเลิกบุ๊กมาร์ก']);
    }
} else {
    // ----- ยังไม่มี -> เพิ่มบุ๊กมาร์กใหม่ (INSERT) -----
    db_stmt_close($checkStmt);

    $insertStmt = db_prepare($conn, 'INSERT INTO bookmarks (user_id, recipe_id) VALUES (?, ?)');
    db_bind_param($insertStmt, 'ii', $userId, $recipeId);

    if (db_execute($insertStmt)) {
        db_stmt_close($insertStmt);
        send_response(201, [
            'success' => true,
            'message' => 'บันทึกสูตรโปรดแล้ว',
            'is_bookmarked' => true,
        ]);
    } else {
        db_stmt_close($insertStmt);
        send_response(500, ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการบันทึกบุ๊กมาร์ก']);
    }
}

db_close($conn);
?>
