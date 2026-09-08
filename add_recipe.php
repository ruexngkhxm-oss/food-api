<?php
/**
 * add_recipe.php
 * เพิ่มหรือแก้ไขสูตรอาหารสำหรับเชฟ/แอดมิน
 *
 * Method: POST
 * Body (JSON):
 *   - id          (optional int)  : รหัสสูตรอาหาร (หากระบุจะเป็นการแก้ไข)
 *   - user_id     (required int)  : รหัสผู้ใช้เชฟ
 *   - title       (required string) : ชื่อสูตรอาหาร
 *   - description (optional string)
 *   - prep_time   (optional int)  : เวลาที่ใช้ (นาที)
 *   - servings    (optional int)  : จำนวนเสิร์ฟ
 *   - category_id (optional int)  : รหัสหมวดหมู่
 *   - image_url   (optional string)
 *   - is_featured (optional int/bool)
 *   - ingredients (array)         : [{ "name": "...", "quantity": "..." }]
 *   - instructions (array)        : [{ "step_no": 1, "description": "..." }] หรือ ["ขั้นตอนที่ 1", ...]
 */

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_response(405, ['success' => false, 'message' => 'อนุญาตเฉพาะ method POST เท่านั้น']);
}

$body = get_json_body();

$recipeId    = isset($body['id']) ? (int) $body['id'] : (isset($body['recipe_id']) ? (int) $body['recipe_id'] : 0);
$userId      = isset($body['user_id']) ? (int) $body['user_id'] : 3;
$title       = trim($body['title'] ?? '');
$description = trim($body['description'] ?? '');
$prepTime    = isset($body['prep_time']) ? max(0, (int) $body['prep_time']) : 0;
$servings    = isset($body['servings']) ? max(1, (int) $body['servings']) : 1;
$categoryId  = isset($body['category_id']) && (int) $body['category_id'] > 0 ? (int) $body['category_id'] : 1;
$imageUrl    = trim($body['image_url'] ?? '');
$isFeatured  = !empty($body['is_featured']) ? 1 : 0;

$rawIngredients  = $body['ingredients'] ?? [];
$rawInstructions = $body['instructions'] ?? ($body['steps'] ?? []);
$dietaryTagIds   = $body['dietary_tag_ids'] ?? ($body['tag_ids'] ?? []);

if ($title === '') {
    send_response(400, ['success' => false, 'message' => 'กรุณากรอกชื่อสูตรอาหาร']);
}

// จัดรูปแบบวัตถุดิบ
$formattedIngredients = [];
if (is_array($rawIngredients)) {
    foreach ($rawIngredients as $index => $ing) {
        if (is_array($ing)) {
            $name = trim($ing['name'] ?? $ing['ingredient_name'] ?? '');
            $qty  = trim($ing['quantity'] ?? '');
        } else {
            $name = trim((string) $ing);
            $qty  = '';
        }
        if ($name !== '') {
            $formattedIngredients[] = [
                'name'     => $name,
                'quantity' => $qty,
                'order_no' => $index + 1,
            ];
        }
    }
}

// จัดรูปแบบขั้นตอน
$formattedSteps = [];
if (is_array($rawInstructions)) {
    $stepNo = 1;
    foreach ($rawInstructions as $step) {
        if (is_array($step)) {
            $desc = trim($step['description'] ?? $step['text'] ?? '');
            $no   = isset($step['step_no']) ? (int) $step['step_no'] : $stepNo;
        } else {
            $desc = trim((string) $step);
            $no   = $stepNo;
        }
        if ($desc !== '') {
            $formattedSteps[] = [
                'step_no'     => $no,
                'description' => $desc,
            ];
            $stepNo++;
        }
    }
}

$ingredientsJson  = json_encode($formattedIngredients, JSON_UNESCAPED_UNICODE);
$instructionsJson = json_encode($formattedSteps, JSON_UNESCAPED_UNICODE);

if ($recipeId > 0) {
    // ----- โหมดแก้ไข (Update) -----
    $stmt = db_prepare(
        $conn,
        "UPDATE recipes
         SET title = ?, description = ?, prep_time = ?, servings = ?, category_id = ?,
             image_url = ?, is_featured = ?, ingredients = ?, instructions = ?, user_id = ?
         WHERE id = ?"
    );
    db_bind_param(
        $stmt,
        'ssiiisissii',
        $title,
        $description,
        $prepTime,
        $servings,
        $categoryId,
        $imageUrl,
        $isFeatured,
        $ingredientsJson,
        $instructionsJson,
        $userId,
        $recipeId
    );
    $executed = db_execute($stmt);
    db_stmt_close($stmt);

    if (!$executed) {
        send_response(500, ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการแก้ไขสูตรอาหาร: ' . db_error($conn)]);
    }

    $targetRecipeId = $recipeId;
    $message = 'แก้ไขสูตรอาหารสำเร็จ';
} else {
    // ----- โหมดเพิ่มใหม่ (Insert) -----
    $stmt = db_prepare(
        $conn,
        "INSERT INTO recipes (user_id, category_id, title, description, image_url, prep_time, servings, is_featured, ingredients, instructions)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    db_bind_param(
        $stmt,
        'iisssiiiss',
        $userId,
        $categoryId,
        $title,
        $description,
        $imageUrl,
        $prepTime,
        $servings,
        $isFeatured,
        $ingredientsJson,
        $instructionsJson
    );
    $executed = db_execute($stmt);
    if (!$executed) {
        send_response(500, ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการบันทึกสูตรอาหาร: ' . db_error($conn)]);
    }
    $targetRecipeId = db_insert_id($conn);
    db_stmt_close($stmt);
    $message = 'เพิ่มสูตรอาหารสำเร็จ';
}

// ----- ซิงค์ตารางย่อย recipe_ingredients และ recipe_steps -----
// ลบข้อมูลเดิมของ recipe_id นี้ก่อน เพื่ออัปเดตตามรายการใหม่ (หากลบขั้นตอนใดไป ข้อมูลเก่าจะถูกลบตามทันที)
$delIng = db_prepare($conn, "DELETE FROM recipe_ingredients WHERE recipe_id = ?");
db_bind_param($delIng, 'i', $targetRecipeId);
db_execute($delIng);
db_stmt_close($delIng);

$delSteps = db_prepare($conn, "DELETE FROM recipe_steps WHERE recipe_id = ?");
db_bind_param($delSteps, 'i', $targetRecipeId);
db_execute($delSteps);
db_stmt_close($delSteps);

// เพิ่มวัตถุดิบเข้าตาราง recipe_ingredients
if (!empty($formattedIngredients)) {
    $ingInsertStmt = db_prepare(
        $conn,
        "INSERT INTO recipe_ingredients (recipe_id, ingredient_name, quantity, order_no) VALUES (?, ?, ?, ?)"
    );
    foreach ($formattedIngredients as $ing) {
        db_bind_param($ingInsertStmt, 'issi', $targetRecipeId, $ing['name'], $ing['quantity'], $ing['order_no']);
        db_execute($ingInsertStmt);
    }
    db_stmt_close($ingInsertStmt);
}

// เพิ่มขั้นตอนเข้าตาราง recipe_steps
if (!empty($formattedSteps)) {
    $stepInsertStmt = db_prepare(
        $conn,
        "INSERT INTO recipe_steps (recipe_id, step_no, description) VALUES (?, ?, ?)"
    );
    foreach ($formattedSteps as $step) {
        db_bind_param($stepInsertStmt, 'iis', $targetRecipeId, $step['step_no'], $step['description']);
        db_execute($stepInsertStmt);
    }
    db_stmt_close($stepInsertStmt);
}

// ซิงค์ป้ายกำกับสายสุขภาพ (recipe_dietary_tags)
$delTags = db_prepare($conn, "DELETE FROM recipe_dietary_tags WHERE recipe_id = ?");
db_bind_param($delTags, 'i', $targetRecipeId);
db_execute($delTags);
db_stmt_close($delTags);

if (is_array($dietaryTagIds) && !empty($dietaryTagIds)) {
    $tagInsertStmt = db_prepare($conn, "INSERT INTO recipe_dietary_tags (recipe_id, tag_id) VALUES (?, ?)");
    foreach ($dietaryTagIds as $tId) {
        $tagId = (int) $tId;
        if ($tagId > 0) {
            db_bind_param($tagInsertStmt, 'ii', $targetRecipeId, $tagId);
            db_execute($tagInsertStmt);
        }
    }
    db_stmt_close($tagInsertStmt);
}

send_response(200, [
    'success'   => true,
    'message'   => $message,
    'recipe_id' => $targetRecipeId,
]);

db_close($conn);
?>
