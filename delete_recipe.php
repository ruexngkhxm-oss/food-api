<?php
/**
 * delete_recipe.php
 * ลบสูตรอาหารออกจากระบบ
 *
 * Method: POST
 * Body (JSON):
 *   - recipe_id (required int) : รหัสสูตรอาหาร
 *   - user_id   (required int) : รหัสผู้ใช้เชฟที่สั่งลบ
 */

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_response(405, ['success' => false, 'message' => 'อนุญาตเฉพาะ method POST เท่านั้น']);
}

$body = get_json_body();

$recipeId = isset($body['recipe_id']) ? (int) $body['recipe_id'] : 0;
$userId   = isset($body['user_id']) ? (int) $body['user_id'] : 0;

if ($recipeId <= 0 || $userId <= 0) {
    send_response(400, ['success' => false, 'message' => 'กรุณาระบุ recipe_id และ user_id']);
}

// ตรวจสอบสิทธิ์และลบสูตรอาหาร
$stmt = mysqli_prepare(
    $conn,
    "DELETE FROM recipes
     WHERE id = ? AND (user_id = ? OR EXISTS (SELECT 1 FROM users WHERE id = ? AND role IN ('chef', 'admin')))"
);
mysqli_stmt_bind_param($stmt, 'iii', $recipeId, $userId, $userId);
mysqli_stmt_execute($stmt);

$affected = mysqli_stmt_affected_rows($stmt);
mysqli_stmt_close($stmt);

if ($affected > 0) {
    send_response(200, [
        'success' => true,
        'message' => 'ลบสูตรอาหารสำเร็จ',
    ]);
} else {
    send_response(404, [
        'success' => false,
        'message' => 'ไม่พบสูตรอาหารที่ต้องการลบ หรือคุณไม่มีสิทธิ์ในการลบสูตรนี้',
    ]);
}

mysqli_close($conn);
