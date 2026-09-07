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

$recipeId    = isset($body['id']) ? (int) $body['id'] : 0;
$userId      = isset($body['user_id']) ? (int) $body['user_id'] : 0;
$title       = trim($body['title'] ?? '');
$description = trim($body['description'] ?? '');
$prepTime    = isset($body['prep_time']) ? max(0, (int) $body['prep_time']) : 0;
$servings    = isset($body['servings']) ? max(1, (int) $body['servings']) : 1;
$categoryId  = isset($body['category_id']) && (int) $body['category_id'] > 0 ? (int) $body['category_id'] : null;
$imageUrl    = trim($body['image_url'] ?? '');
$isFeatured  = !empty($body['is_featured']) ? 1 : 0;

$rawIngredients  = $body['ingredients'] ?? [];
$rawInstructions = $body['instructions'] ?? [];
$dietaryTagIds   = $body['dietary_tag_ids'] ?? [];

if ($userId <= 0) {
    send_response(400, ['success' => false, 'message' => 'กรุณาระบุ user_id ผู้สร้าง']);
}

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
            $desc = trim($step['description'] ?? '');
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
    $stmt = mysqli_prepare(
        $conn,
        "UPDATE recipes
         SET title = ?, description = ?, prep_time = ?, servings = ?, category_id = ?,
             image_url = ?, is_featured = ?, ingredients = ?, instructions = ?
         WHERE id = ? AND (user_id = ? OR EXISTS (SELECT 1 FROM users WHERE id = ? AND role IN ('chef', 'admin')))"
    );
    mysqli_stmt_bind_param(
        $stmt,
        'ssiiisisiiii',
        $title,
        $description,
        $prepTime,
        $servings,
        $categoryId,
        $imageUrl,
        $isFeatured,
        $ingredientsJson,
        $instructionsJson,
        $recipeId,
        $userId,
        $userId
    );
    $executed = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if (!$executed) {
        send_response(500, ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการแก้ไขสูตรอาหาร: ' . mysqli_error($conn)]);
    }

    $targetRecipeId = $recipeId;
    $message = 'แก้ไขสูตรอาหารสำเร็จ';
} else {
    // ----- โหมดเพิ่มใหม่ (Insert) -----
    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO recipes (user_id, category_id, title, description, image_url, prep_time, servings, is_featured, ingredients, instructions)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param(
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
    $executed = mysqli_stmt_execute($stmt);
    if (!$executed) {
        send_response(500, ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการบันทึกสูตรอาหาร: ' . mysqli_error($conn)]);
    }
    $targetRecipeId = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    $message = 'เพิ่มสูตรอาหารสำเร็จ';
}

// ----- ซิงค์ตารางย่อย recipe_ingredients และ recipe_steps -----
// ลบข้อมูลเดิมของ recipe_id นี้ก่อน
$delIng = mysqli_prepare($conn, "DELETE FROM recipe_ingredients WHERE recipe_id = ?");
mysqli_stmt_bind_param($delIng, 'i', $targetRecipeId);
mysqli_stmt_execute($delIng);
mysqli_stmt_close($delIng);

$delSteps = mysqli_prepare($conn, "DELETE FROM recipe_steps WHERE recipe_id = ?");
mysqli_stmt_bind_param($delSteps, 'i', $targetRecipeId);
mysqli_stmt_execute($delSteps);
mysqli_stmt_close($delSteps);

// เพิ่มวัตถุดิบเข้าตาราง recipe_ingredients
if (!empty($formattedIngredients)) {
    $ingInsertStmt = mysqli_prepare(
        $conn,
        "INSERT INTO recipe_ingredients (recipe_id, ingredient_name, quantity, order_no) VALUES (?, ?, ?, ?)"
    );
    foreach ($formattedIngredients as $ing) {
        mysqli_stmt_bind_param($ingInsertStmt, 'issi', $targetRecipeId, $ing['name'], $ing['quantity'], $ing['order_no']);
        mysqli_stmt_execute($ingInsertStmt);
    }
    mysqli_stmt_close($ingInsertStmt);
}

// เพิ่มขั้นตอนเข้าตาราง recipe_steps
if (!empty($formattedSteps)) {
    $stepInsertStmt = mysqli_prepare(
        $conn,
        "INSERT INTO recipe_steps (recipe_id, step_no, description) VALUES (?, ?, ?)"
    );
    foreach ($formattedSteps as $step) {
        mysqli_stmt_bind_param($stepInsertStmt, 'iis', $targetRecipeId, $step['step_no'], $step['description']);
        mysqli_stmt_execute($stepInsertStmt);
    }
    mysqli_stmt_close($stepInsertStmt);
}

// ซิงค์ป้ายกำกับสายสุขภาพ (recipe_dietary_tags)
$delTags = mysqli_prepare($conn, "DELETE FROM recipe_dietary_tags WHERE recipe_id = ?");
mysqli_stmt_bind_param($delTags, 'i', $targetRecipeId);
mysqli_stmt_execute($delTags);
mysqli_stmt_close($delTags);

if (is_array($dietaryTagIds) && !empty($dietaryTagIds)) {
    $tagInsertStmt = mysqli_prepare($conn, "INSERT INTO recipe_dietary_tags (recipe_id, tag_id) VALUES (?, ?)");
    foreach ($dietaryTagIds as $tId) {
        $tagId = (int) $tId;
        if ($tagId > 0) {
            mysqli_stmt_bind_param($tagInsertStmt, 'ii', $targetRecipeId, $tagId);
            mysqli_stmt_execute($tagInsertStmt);
        }
    }
    mysqli_stmt_close($tagInsertStmt);
}

send_response(200, [
    'success'   => true,
    'message'   => $message,
    'recipe_id' => $targetRecipeId,
]);

mysqli_close($conn);
