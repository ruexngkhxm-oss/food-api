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

$recipeId = isset($body['recipe_id']) ? (int) $body['recipe_id'] : (isset($body['id']) ? (int) $body['id'] : 0);
$userId   = isset($body['user_id']) ? (int) $body['user_id'] : 3;

if ($recipeId <= 0) {
    send_response(400, ['success' => false, 'message' => 'กรุณาระบุ recipe_id ของสูตรอาหารที่ต้องการลบ']);
}

// 1. ลบข้อมูลย่อยที่เกี่ยวข้องในตารางต่างๆ
$delIng = db_prepare($conn, "DELETE FROM recipe_ingredients WHERE recipe_id = ?");
db_bind_param($delIng, 'i', $recipeId);
db_execute($delIng);
db_stmt_close($delIng);

$delSteps = db_prepare($conn, "DELETE FROM recipe_steps WHERE recipe_id = ?");
db_bind_param($delSteps, 'i', $recipeId);
db_execute($delSteps);
db_stmt_close($delSteps);

$delTags = db_prepare($conn, "DELETE FROM recipe_dietary_tags WHERE recipe_id = ?");
db_bind_param($delTags, 'i', $recipeId);
db_execute($delTags);
db_stmt_close($delTags);

$delBM = db_prepare($conn, "DELETE FROM bookmarks WHERE recipe_id = ?");
db_bind_param($delBM, 'i', $recipeId);
db_execute($delBM);
db_stmt_close($delBM);

$delRev = db_prepare($conn, "DELETE FROM recipe_reviews WHERE recipe_id = ?");
db_bind_param($delRev, 'i', $recipeId);
db_execute($delRev);
db_stmt_close($delRev);

// 2. ลบสูตรอาหารหลักจากตาราง recipes
$stmt = db_prepare($conn, "DELETE FROM recipes WHERE id = ?");
db_bind_param($stmt, 'i', $recipeId);
$executed = db_execute($stmt);
$affected = db_affected_rows($stmt);
db_stmt_close($stmt);

if ($executed || $affected > 0) {
    send_response(200, [
        'success' => true,
        'message' => 'ลบสูตรอาหารสำเร็จ',
        'recipe_id' => $recipeId,
    ]);
} else {
    send_response(500, [
        'success' => false,
        'message' => 'เกิดข้อผิดพลาดในการลบสูตรอาหาร: ' . db_error($conn),
    ]);
}

db_close($conn);
?>
