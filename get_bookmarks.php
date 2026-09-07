<?php
/**
 * get_bookmarks.php
 * ดึงรายการสูตรอาหารที่ผู้ใช้บันทึกเป็นสูตรโปรดไว้ (ใช้ในหน้าโปรไฟล์)
 *
 * Method: GET
 * Query params:
 *   - user_id (required)
 */

require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_response(405, ['success' => false, 'message' => 'อนุญาตเฉพาะ method GET เท่านั้น']);
}

$userId = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

if ($userId <= 0) {
    send_response(400, ['success' => false, 'message' => 'กรุณาระบุ user_id']);
}

$stmt = mysqli_prepare($conn, "
    SELECT r.id, r.title, r.description, r.image_url, r.prep_time, r.servings,
           r.is_featured, r.created_at,
           u.id AS author_id, u.full_name AS author_name, u.avatar_url AS author_avatar,
           c.id AS category_id, c.name AS category_name, c.icon AS category_icon
    FROM bookmarks b
    INNER JOIN recipes r ON b.recipe_id = r.id
    INNER JOIN users u ON r.user_id = u.id
    LEFT JOIN categories c ON r.category_id = c.id
    WHERE b.user_id = ?
    ORDER BY b.created_at DESC
");
mysqli_stmt_bind_param($stmt, 'i', $userId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$recipes = [];
while ($row = mysqli_fetch_assoc($result)) {
    $recipes[] = [
        'id'          => (int) $row['id'],
        'title'       => $row['title'],
        'description' => $row['description'],
        'image_url'   => $row['image_url'],
        'prep_time'   => (int) $row['prep_time'],
        'servings'    => (int) $row['servings'],
        'is_featured' => (bool) $row['is_featured'],
        'created_at'  => $row['created_at'],
        'is_bookmarked' => true,
        'author' => [
            'id'         => (int) $row['author_id'],
            'full_name'  => $row['author_name'],
            'avatar_url' => $row['author_avatar'],
        ],
        'category' => $row['category_id'] ? [
            'id'   => (int) $row['category_id'],
            'name' => $row['category_name'],
            'icon' => $row['category_icon'],
        ] : null,
    ];
}
mysqli_stmt_close($stmt);

send_response(200, [
    'success' => true,
    'count'   => count($recipes),
    'recipes' => $recipes,
]);

mysqli_close($conn);
