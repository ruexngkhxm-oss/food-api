<?php
/**
 * get_chef_recipes.php
 * ดึงรายการสูตรอาหารที่เชฟ/ผู้ใช้สร้างขึ้น พร้อมนับจำนวนผู้คนที่กดรายการโปรด (favorite_count)
 *
 * Method: GET
 * Query params:
 *   - user_id (required) : รหัสผู้ใช้/เชฟ
 */

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_response(405, ['success' => false, 'message' => 'อนุญาตเฉพาะ method GET เท่านั้น']);
}

$userId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

if ($userId <= 0) {
    send_response(400, ['success' => false, 'message' => 'กรุณาระบุ user_id ของเชฟ']);
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

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, 'i', $userId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$recipes = [];
while ($row = mysqli_fetch_assoc($result)) {
    $recipeId = (int) $row['id'];

    // ดึงวัตถุดิบ
    $ingStmt = mysqli_prepare($conn, "SELECT ingredient_name, quantity FROM recipe_ingredients WHERE recipe_id = ? ORDER BY order_no ASC, id ASC");
    mysqli_stmt_bind_param($ingStmt, 'i', $recipeId);
    mysqli_stmt_execute($ingStmt);
    $ingResult = mysqli_stmt_get_result($ingStmt);
    $ingredients = [];
    while ($ingRow = mysqli_fetch_assoc($ingResult)) {
        $ingredients[] = [
            'name'     => $ingRow['ingredient_name'],
            'quantity' => $ingRow['quantity'],
        ];
    }
    mysqli_stmt_close($ingStmt);

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
    $stepStmt = mysqli_prepare($conn, "SELECT step_no, description FROM recipe_steps WHERE recipe_id = ? ORDER BY step_no ASC");
    mysqli_stmt_bind_param($stepStmt, 'i', $recipeId);
    mysqli_stmt_execute($stepStmt);
    $stepResult = mysqli_stmt_get_result($stepStmt);
    $steps = [];
    while ($stepRow = mysqli_fetch_assoc($stepResult)) {
        $steps[] = [
            'step_no'     => (int) $stepRow['step_no'],
            'description' => $stepRow['description'],
        ];
    }
    mysqli_stmt_close($stepStmt);

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
    $tagStmt = mysqli_prepare($conn, "SELECT dt.id, dt.name, dt.icon FROM recipe_dietary_tags rdt INNER JOIN dietary_tags dt ON rdt.tag_id = dt.id WHERE rdt.recipe_id = ? ORDER BY dt.sort_order ASC");
    mysqli_stmt_bind_param($tagStmt, 'i', $recipeId);
    mysqli_stmt_execute($tagStmt);
    $tagResult = mysqli_stmt_get_result($tagStmt);
    $dietaryTags = [];
    while ($tagRow = mysqli_fetch_assoc($tagResult)) {
        $dietaryTags[] = [
            'id'   => (int) $tagRow['id'],
            'name' => $tagRow['name'],
            'icon' => $tagRow['icon'],
        ];
    }
    mysqli_stmt_close($tagStmt);

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
mysqli_stmt_close($stmt);

send_response(200, [
    'success' => true,
    'count'   => count($recipes),
    'recipes' => $recipes,
]);

mysqli_close($conn);
