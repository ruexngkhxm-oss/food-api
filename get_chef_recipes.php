<?php
/**
 * get_chef_recipes.php
 * ดึงรายการสูตรอาหารที่เชฟ/ผู้ใช้สร้างขึ้น
 */

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_response(405, ['success' => false, 'message' => 'อนุญาตเฉพาะ method GET เท่านั้น']);
}

$userId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : (isset($_GET['chef_id']) ? (int) $_GET['chef_id'] : 1);

if ($userId <= 0) {
    $userId = 1;
}

$sql = "
    SELECT r.id, r.title, r.description, r.image_url, r.prep_time, r.servings,
           r.is_featured, r.view_count, r.created_at, r.ingredients AS raw_ingredients, r.instructions AS raw_instructions,
           (SELECT COUNT(*) FROM bookmarks b WHERE b.recipe_id = r.id) AS favorite_count,
           u.id AS author_id, u.full_name AS author_name, u.avatar_url AS author_avatar,
           c.id AS category_id, c.name AS category_name, c.icon AS category_icon
    FROM recipes r
    INNER JOIN users u ON r.user_id = u.id
    LEFT JOIN categories c ON r.category_id = c.id
    WHERE r.user_id = ?
    ORDER BY r.created_at DESC, r.id DESC
";

$stmt = db_prepare($conn, $sql);
db_bind_param($stmt, 'i', $userId);
db_execute($stmt);
$result = db_get_result($stmt);

$recipes = [];
while ($row = db_fetch_assoc($result)) {
    $recipeId = (int) $row['id'];

    // ดึงวัตถุดิบ
    $ingStmt = db_prepare($conn, "SELECT ingredient_name, quantity FROM recipe_ingredients WHERE recipe_id = ? ORDER BY order_no ASC, id ASC");
    db_bind_param($ingStmt, 'i', $recipeId);
    db_execute($ingStmt);
    $ingResult = db_get_result($ingStmt);
    $ingredients = [];
    while ($ingRow = db_fetch_assoc($ingResult)) {
        $ingredients[] = [
            'name'     => $ingRow['ingredient_name'],
            'quantity' => $ingRow['quantity'],
        ];
    }
    db_stmt_close($ingStmt);

    if (empty($ingredients) && !empty($row['raw_ingredients'])) {
        $decoded = json_decode($row['raw_ingredients'], true);
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

    // ดึงขั้นตอนการทำ
    $stepStmt = db_prepare($conn, "SELECT step_no, description FROM recipe_steps WHERE recipe_id = ? ORDER BY step_no ASC");
    db_bind_param($stepStmt, 'i', $recipeId);
    db_execute($stepStmt);
    $stepResult = db_get_result($stepStmt);
    $steps = [];
    while ($stepRow = db_fetch_assoc($stepResult)) {
        $steps[] = [
            'step_no'     => (int) $stepRow['step_no'],
            'description' => $stepRow['description'],
        ];
    }
    db_stmt_close($stepStmt);

    if (empty($steps) && !empty($row['raw_instructions'])) {
        $decoded = json_decode($row['raw_instructions'], true);
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

    // ดึงป้ายกำกับสายสุขภาพ
    $tagStmt = db_prepare($conn, "SELECT dt.id, dt.name, dt.icon FROM recipe_dietary_tags rdt INNER JOIN dietary_tags dt ON rdt.tag_id = dt.id WHERE rdt.recipe_id = ? ORDER BY dt.sort_order ASC");
    db_bind_param($tagStmt, 'i', $recipeId);
    db_execute($tagStmt);
    $tagResult = db_get_result($tagStmt);
    $dietaryTags = [];
    while ($tagRow = db_fetch_assoc($tagResult)) {
        $dietaryTags[] = [
            'id'   => (int) $tagRow['id'],
            'name' => $tagRow['name'],
            'icon' => $tagRow['icon'],
        ];
    }
    db_stmt_close($tagStmt);

    $recipes[] = [
        'id'             => $recipeId,
        'title'          => $row['title'],
        'description'    => $row['description'],
        'image_url'      => $row['image_url'],
        'prep_time'      => (int) $row['prep_time'],
        'servings'       => (int) $row['servings'],
        'is_featured'    => (bool) $row['is_featured'],
        'view_count'     => (int) $row['view_count'],
        'created_at'     => $row['created_at'],
        'favorite_count' => (int) $row['favorite_count'],
        'author'         => [
            'id'         => (int) $row['author_id'],
            'full_name'  => $row['author_name'],
            'avatar_url' => $row['author_avatar'],
        ],
        'category' => $row['category_id'] ? [
            'id'   => (int) $row['category_id'],
            'name' => $row['category_name'],
            'icon' => $row['category_icon'],
        ] : null,
        'dietary_tags'   => $dietaryTags,
        'ingredients'    => $ingredients,
        'steps'          => $steps,
    ];
}
db_stmt_close($stmt);

send_response(200, [
    'success' => true,
    'count'   => count($recipes),
    'recipes' => $recipes,
]);

db_close($conn);
?>
