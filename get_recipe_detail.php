<?php
/**
 * get_recipe_detail.php
 * ดึงรายละเอียดสูตรอาหาร 1 รายการ พร้อมวัตถุดิบและขั้นตอนการทำ + อัปเดตยอดเข้าชม (view_count)
 *
 * Method: GET
 * Query params:
 *   - id      (required) : รหัสสูตรอาหาร
 *   - user_id (optional)  : ใช้เช็คสถานะ is_bookmarked ของผู้ใช้คนนั้น
 */

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_response(405, ['success' => false, 'message' => 'อนุญาตเฉพาะ method GET เท่านั้น']);
}

$recipeId = isset($_GET['id']) ? (int) $_GET['id'] : (isset($_GET['recipe_id']) ? (int) $_GET['recipe_id'] : 0);
$userId   = isset($_GET['user_id']) ? (int) $_GET['user_id'] : null;

if ($recipeId <= 0) {
    send_response(400, ['success' => false, 'message' => 'กรุณาระบุ id ของสูตรอาหาร']);
}

// ----- 0. อัปเดตยอดเข้าชมเรียลไทม์ (view_count) -----
$viewStmt = db_prepare($conn, "UPDATE recipes SET view_count = view_count + 1 WHERE id = ?");
db_bind_param($viewStmt, 'i', $recipeId);
db_execute($viewStmt);
db_stmt_close($viewStmt);

// ----- ข้อมูลหลักของสูตร -----
$stmt = db_prepare($conn, "
    SELECT r.id, r.title, r.description, r.image_url, r.prep_time, r.servings,
           r.is_featured, r.view_count, r.created_at, r.ingredients AS raw_ingredients, r.instructions AS raw_instructions,
           (SELECT COUNT(*) FROM bookmarks b WHERE b.recipe_id = r.id) AS favorite_count,
           COALESCE((SELECT ROUND(AVG(rr.rating), 1) FROM recipe_reviews rr WHERE rr.recipe_id = r.id AND rr.rating IS NOT NULL), 0.0) AS avg_rating,
           (SELECT COUNT(*) FROM recipe_reviews rr WHERE rr.recipe_id = r.id AND rr.rating IS NOT NULL) AS rating_count,
           u.id AS author_id, u.full_name AS author_name, u.avatar_url AS author_avatar, u.bio AS author_bio,
           c.id AS category_id, c.name AS category_name, c.icon AS category_icon
    FROM recipes r
    INNER JOIN users u ON r.user_id = u.id
    LEFT JOIN categories c ON r.category_id = c.id
    WHERE r.id = ?
    LIMIT 1
");
db_bind_param($stmt, 'i', $recipeId);
db_execute($stmt);
$result = db_get_result($stmt);
$recipe = db_fetch_assoc($result);
db_stmt_close($stmt);

if (!$recipe) {
    send_response(404, ['success' => false, 'message' => 'ไม่พบสูตรอาหารนี้']);
}

// ----- วัตถุดิบ -----
$ingStmt = db_prepare($conn, "
    SELECT ingredient_name, quantity
    FROM recipe_ingredients
    WHERE recipe_id = ?
    ORDER BY order_no ASC, id ASC
");
db_bind_param($ingStmt, 'i', $recipeId);
db_execute($ingStmt);
$ingResult = db_get_result($ingStmt);

$ingredients = [];
while ($row = db_fetch_assoc($ingResult)) {
    $ingredients[] = [
        'name'     => $row['ingredient_name'],
        'quantity' => $row['quantity'],
    ];
}
db_stmt_close($ingStmt);

if (empty($ingredients) && !empty($recipe['raw_ingredients'])) {
    $decoded = json_decode($recipe['raw_ingredients'], true);
    if (is_array($decoded)) {
        foreach ($decoded as $item) {
            if (is_array($item)) {
                $ingredients[] = [
                    'name'     => $item['name'] ?? $item['ingredient_name'] ?? '',
                    'quantity' => $item['quantity'] ?? null,
                ];
            } else if (is_string($item)) {
                $ingredients[] = ['name' => $item, 'quantity' => ''];
            }
        }
    }
}

// ----- ขั้นตอนการทำ -----
$stepStmt = db_prepare($conn, "
    SELECT step_no, description
    FROM recipe_steps
    WHERE recipe_id = ?
    ORDER BY step_no ASC
");
db_bind_param($stepStmt, 'i', $recipeId);
db_execute($stepStmt);
$stepResult = db_get_result($stepStmt);

$steps = [];
while ($row = db_fetch_assoc($stepResult)) {
    $steps[] = [
        'step_no'     => (int) $row['step_no'],
        'description' => $row['description'],
    ];
}
db_stmt_close($stepStmt);

if (empty($steps) && !empty($recipe['raw_instructions'])) {
    $decoded = json_decode($recipe['raw_instructions'], true);
    if (is_array($decoded)) {
        $no = 1;
        foreach ($decoded as $item) {
            if (is_array($item)) {
                $steps[] = [
                    'step_no'     => (int) ($item['step_no'] ?? $no),
                    'description' => $item['description'] ?? '',
                ];
            } else if (is_string($item)) {
                $steps[] = [
                    'step_no'     => $no,
                    'description' => $item,
                ];
            }
            $no++;
        }
    }
}

// ----- ป้ายกำกับสายสุขภาพ -----
$tagStmt = db_prepare($conn, "
    SELECT dt.id, dt.name, dt.icon
    FROM recipe_dietary_tags rdt
    INNER JOIN dietary_tags dt ON rdt.tag_id = dt.id
    WHERE rdt.recipe_id = ?
    ORDER BY dt.sort_order ASC
");
db_bind_param($tagStmt, 'i', $recipeId);
db_execute($tagStmt);
$tagResult = db_get_result($tagStmt);

$dietaryTags = [];
while ($row = db_fetch_assoc($tagResult)) {
    $dietaryTags[] = [
        'id'   => (int) $row['id'],
        'name' => $row['name'],
        'icon' => $row['icon'],
    ];
}
db_stmt_close($tagStmt);

// ----- สถานะบุ๊กมาร์ก (ถ้ามี user_id) -----
$isBookmarked = false;
if ($userId) {
    $bmStmt = db_prepare($conn, "SELECT id FROM bookmarks WHERE user_id = ? AND recipe_id = ? LIMIT 1");
    db_bind_param($bmStmt, 'ii', $userId, $recipeId);
    db_execute($bmStmt);
    $bmResult = db_get_result($bmStmt);
    $isBookmarked = db_num_rows($bmResult) > 0;
    db_stmt_close($bmStmt);
}

send_response(200, [
    'success' => true,
    'recipe'  => [
        'id'            => (int) $recipe['id'],
        'title'         => $recipe['title'],
        'description'   => $recipe['description'],
        'image_url'     => $recipe['image_url'],
        'prep_time'     => (int) $recipe['prep_time'],
        'servings'      => (int) $recipe['servings'],
        'is_featured'   => (bool) $recipe['is_featured'],
        'view_count'    => (int) $recipe['view_count'],
        'favorite_count' => (int) $recipe['favorite_count'],
        'created_at'    => $recipe['created_at'],
        'avg_rating'    => (float) $recipe['avg_rating'],
        'rating_count'  => (int) $recipe['rating_count'],
        'is_bookmarked' => $isBookmarked,
        'author' => [
            'id'         => (int) $recipe['author_id'],
            'full_name'  => $recipe['author_name'],
            'avatar_url' => $recipe['author_avatar'],
            'bio'        => $recipe['author_bio'],
        ],
        'category' => $recipe['category_id'] ? [
            'id'   => (int) $recipe['category_id'],
            'name' => $recipe['category_name'],
            'icon' => $recipe['category_icon'],
        ] : null,
        'dietary_tags' => $dietaryTags,
        'ingredients'  => $ingredients,
        'steps'        => $steps,
    ],
]);

db_close($conn);
?>
